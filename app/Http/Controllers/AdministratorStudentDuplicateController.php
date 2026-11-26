<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\FinancialAuditLockException;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentDataTransferService;
use App\Services\TenantContext;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final class AdministratorStudentDuplicateController extends Controller
{
    public function __construct(
        private readonly StudentDataTransferService $transferService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $user = $this->authorizeAdministrator();

        $duplicateEmails = Student::query()
            ->whereNotNull('email')
            ->whereRaw("TRIM(email) != ''")
            ->selectRaw('LOWER(TRIM(email)) as normalized_email')
            ->groupByRaw('LOWER(TRIM(email))')
            ->havingRaw('COUNT(*) > 1')
            ->limit(100)
            ->pluck('normalized_email')
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();

        $duplicateIdentityRows = Student::query()
            ->whereNotNull('birth_date')
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->whereRaw("TRIM(first_name) != ''")
            ->whereRaw("TRIM(last_name) != ''")
            ->selectRaw('LOWER(TRIM(first_name)) as norm_first, LOWER(TRIM(last_name)) as norm_last, DATE(birth_date) as norm_dob')
            ->groupByRaw('LOWER(TRIM(first_name)), LOWER(TRIM(last_name)), DATE(birth_date)')
            ->havingRaw('COUNT(*) > 1')
            ->limit(100)
            ->get();

        $emailStudents = collect();
        if (! empty($duplicateEmails)) {
            $emailStudents = Student::query()
                ->whereNotNull('email')
                ->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(email))'), $duplicateEmails)
                ->withCount(['studentEnrollments', 'classEnrollments'])
                ->withExists('account')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        $identityStudents = collect();
        if ($duplicateIdentityRows->isNotEmpty()) {
            $identityStudents = Student::query()
                ->whereNotNull('birth_date')
                ->where(function ($query) use ($duplicateIdentityRows): void {
                    foreach ($duplicateIdentityRows as $row) {
                        $query->orWhere(function ($sub) use ($row): void {
                            $sub->whereRaw('LOWER(TRIM(first_name)) = ?', [$row->norm_first])
                                ->whereRaw('LOWER(TRIM(last_name)) = ?', [$row->norm_last])
                                ->whereDate('birth_date', (string) $row->norm_dob);
                        });
                    }
                })
                ->withCount(['studentEnrollments', 'classEnrollments'])
                ->withExists('account')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
        }

        $clustersBySignature = [];

        foreach ($emailStudents->groupBy(fn (Student $student): string => mb_strtolower(mb_trim((string) $student->email))) as $normalizedEmail => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $signature = $group->pluck('id')->sort()->implode('-');
            $clustersBySignature[$signature] = [
                'id' => 'email:'.$normalizedEmail,
                'match_type' => 'email',
                'match_label' => 'Exact Email Match',
                'match_value' => (string) $normalizedEmail,
                'students' => $group->map(fn (Student $student): array => $this->serializeStudentCard($student))->values()->all(),
            ];
        }

        foreach ($identityStudents->groupBy(function (Student $student): string {
            $first = mb_strtolower(mb_trim((string) $student->first_name));
            $last = mb_strtolower(mb_trim((string) $student->last_name));
            $dob = $student->birth_date?->toDateString() ?? '';

            return "{$first}|{$last}|{$dob}";
        }) as $identityKey => $group) {
            if ($group->count() < 2) {
                continue;
            }

            $signature = $group->pluck('id')->sort()->implode('-');
            /** @var Student $firstStudent */
            $firstStudent = $group->first();
            $dobLabel = $firstStudent->birth_date?->toDateString() ?? 'Unknown DOB';
            $identityLabel = mb_trim("{$firstStudent->first_name} {$firstStudent->last_name}")." ({$dobLabel})";

            if (isset($clustersBySignature[$signature])) {
                $clustersBySignature[$signature]['match_type'] = 'both';
                $clustersBySignature[$signature]['match_label'] = 'Email + Name & Birth Date Match';
                $clustersBySignature[$signature]['match_value'] = $clustersBySignature[$signature]['match_value'].' · '.$identityLabel;

                continue;
            }

            $clustersBySignature[$signature] = [
                'id' => 'name_dob:'.$identityKey,
                'match_type' => 'name_dob',
                'match_label' => 'Name & Birth Date Match',
                'match_value' => $identityLabel,
                'students' => $group->map(fn (Student $student): array => $this->serializeStudentCard($student))->values()->all(),
            ];
        }

        $clusters = array_values($clustersBySignature);
        $affectedStudentIds = collect($clusters)
            ->flatMap(fn (array $cluster): array => array_column($cluster['students'], 'id'))
            ->unique()
            ->count();

        $emailClusterCount = collect($clusters)
            ->filter(fn (array $cluster): bool => in_array($cluster['match_type'], ['email', 'both'], true))
            ->count();

        $identityClusterCount = collect($clusters)
            ->filter(fn (array $cluster): bool => in_array($cluster['match_type'], ['name_dob', 'both'], true))
            ->count();

        return Inertia::render('administrators/students/duplicates', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'clusters' => $clusters,
            'stats' => [
                'total_clusters' => count($clusters),
                'email_clusters' => $emailClusterCount,
                'identity_clusters' => $identityClusterCount,
                'affected_students' => $affectedStudentIds,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeAdministrator();

        $validated = $request->validate([
            'source_student_id' => ['required', 'integer'],
            'target_student_id' => ['required', 'integer', 'different:source_student_id'],
        ]);

        [$source, $target] = $this->resolveScopedStudents(
            (int) $validated['source_student_id'],
            (int) $validated['target_student_id'],
        );

        try {
            $preview = $this->transferService->preview($source, $target);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($preview);
    }

    public function merge(Request $request): RedirectResponse
    {
        $user = $this->authorizeAdministrator();

        $validated = $request->validate([
            'source_student_id' => ['required', 'integer'],
            'target_student_id' => ['required', 'integer', 'different:source_student_id'],
            'archive_source' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        [$source, $target] = $this->resolveScopedStudents(
            (int) $validated['source_student_id'],
            (int) $validated['target_student_id'],
        );

        $archiveSource = array_key_exists('archive_source', $validated)
            ? (bool) $validated['archive_source']
            : true;

        $reason = mb_trim((string) ($validated['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Duplicate student record merge via Registrar Duplicate Scanner.';
        }

        try {
            $result = $this->transferService->transfer($source, $target, $user, [
                'reason' => $reason,
            ]);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->with('flash', [
                    'error' => $e->getMessage(),
                ]);
        }

        $archivedNote = '';
        if ($archiveSource && ($result['success'] ?? false) === true) {
            try {
                $source->delete();
                $archivedNote = ' Source profile archived.';
            } catch (FinancialAuditLockException) {
                $archivedNote = ' Source profile retained due to financial audit lock.';
            }
        }

        $totalTransferred = (int) ($result['transferred']['total'] ?? 0);

        return redirect()
            ->back()
            ->with('flash', [
                'success' => sprintf(
                    'Merged %s (#%s) into %s (#%s): %d record%s transferred.%s',
                    $source->full_name,
                    $source->student_id ?? (string) $source->id,
                    $target->full_name,
                    $target->student_id ?? (string) $target->id,
                    $totalTransferred,
                    $totalTransferred === 1 ? '' : 's',
                    $archivedNote,
                ),
            ]);
    }

    private function authorizeAdministrator(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403, 'Authentication required.');
        }

        if (! $user->canAccessAdminPortal()) {
            abort(403, 'You do not have permission to manage duplicate student records.');
        }

        return $user;
    }

    /**
     * @return array{0: Student, 1: Student}
     */
    private function resolveScopedStudents(int $sourceId, int $targetId): array
    {
        $source = Student::query()->find($sourceId);
        $target = Student::query()->find($targetId);

        if (! $source instanceof Student || ! $target instanceof Student) {
            abort(404, 'One or both students could not be found in the current school scope.');
        }

        $sourceSchoolId = $source->school_id ?: $source->institution_id;
        $targetSchoolId = $target->school_id ?: $target->institution_id;

        if ($sourceSchoolId !== null && ! $this->tenantContext->canAccessOrganization((int) $sourceSchoolId)) {
            abort(403, 'You do not have access to the source student institution.');
        }

        if ($targetSchoolId !== null && ! $this->tenantContext->canAccessOrganization((int) $targetSchoolId)) {
            abort(403, 'You do not have access to the target student institution.');
        }

        if ($sourceSchoolId !== null && $targetSchoolId !== null && (int) $sourceSchoolId !== (int) $targetSchoolId) {
            abort(403, 'Cannot merge students belonging to different schools.');
        }

        return [$source, $target];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStudentCard(Student $student): array
    {
        $statusValue = $student->status instanceof BackedEnum
            ? (string) $student->status->value
            : (string) ($student->status ?? 'unknown');

        return [
            'id' => (int) $student->id,
            'student_id' => $student->student_id !== null ? (string) $student->student_id : null,
            'full_name' => (string) ($student->full_name ?: mb_trim("{$student->first_name} {$student->last_name}")),
            'email' => $student->email,
            'birth_date' => $student->birth_date?->toDateString(),
            'status' => $statusValue,
            'enrollments_count' => (int) ($student->student_enrollments_count ?? 0),
            'class_enrollments_count' => (int) ($student->class_enrollments_count ?? 0),
            'portal_linked' => $student->user_id !== null || (bool) ($student->account_exists ?? false),
            'created_at' => $student->created_at?->toDateString(),
        ];
    }
}
