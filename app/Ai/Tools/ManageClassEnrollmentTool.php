<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Services\Ai\InstitutionEntityResolver;
use App\Services\Ai\SectionTransferService;
use App\Services\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Approval-gated AI entry point for moving a student between sections.
 *
 * The agent is expected to call `action='preview'` first and read the blockers
 * out loud to the administrator. Only then does it call `action='transfer'`,
 * which surfaces a confirmation card quoting the real student, subject, and
 * destination — never a value the model invented.
 */
final class ManageClassEnrollmentTool implements Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private ?SectionTransferService $transfers = null,
        private ?InstitutionEntityResolver $entities = null,
    ) {
        $this->transfers ??= app(SectionTransferService::class);
        $this->entities ??= app(InstitutionEntityResolver::class);
    }

    public function description(): Stringable|string
    {
        return 'Move an enrolled student from one class section to another (e.g. "move Maria from GE-3 B to GE-3 A"), or remove a section while keeping the subject. Always preview first to surface schedule conflicts, seat availability, and the tuition impact before transferring. Transfers require administrator confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return $this->encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        $action = mb_strtolower((string) $request['action']);

        if ($action === 'preview') {
            if (! $user->hasRole('super_admin') && ! $user->can('View:StudentEnrollment') && ! $user->can('View:Classes')) {
                return $this->encode(['error' => true, 'message' => 'You are not permitted to view class enrollments.']);
            }
        } elseif ($user->hasRole('super_admin') || $user->can('Update:StudentEnrollment') || $user->can('Update:Classes')) {
            // permitted
        } else {
            return $this->encode(['error' => true, 'message' => 'You are not permitted to move students between sections.']);
        }

        $validated = $request->validate([
            'action' => 'nullable|string|in:preview,transfer',
            'student' => 'nullable|string|max:120',
            'student_id' => 'nullable',
            'subject' => 'nullable|string|max:120',
            'from_class_id' => 'nullable|integer',
            'to_section' => 'nullable|string|max:120',
            'reason' => 'nullable|string|max:500',
            'force' => 'nullable|boolean',
        ]);

        $identifier = $validated['student'] ?? $validated['student_id'] ?? null;

        if (blank($identifier)) {
            return $this->encode([
                'error' => true,
                'message' => 'A student number, name, or email is required. Use SearchStudentsTool to resolve the student first if you only have a partial name.',
            ]);
        }

        try {
            $student = $this->entities->student($identifier, $this->currentSchool());
        } catch (\App\Services\Ai\Exceptions\EntityNotFoundException|\App\Services\Ai\Exceptions\AmbiguousEntityException $e) {
            return $this->encode(['error' => true, 'message' => $e->getMessage()]);
        }

        if ($action === 'preview') {
            return $this->encode($this->transfers->preview(
                student: $student,
                sourceIdentifier: $validated['subject'] ?? null,
                destinationIdentifier: $validated['to_section'] ?? null,
                fromClassId: isset($validated['from_class_id']) ? (int) $validated['from_class_id'] : null,
            ));
        }

        $result = $this->transfers->transfer(
            student: $student,
            sourceIdentifier: $validated['subject'] ?? null,
            destinationIdentifier: $validated['to_section'] ?? null,
            actor: $user,
            reason: $validated['reason'] ?? null,
            fromClassId: isset($validated['from_class_id']) ? (int) $validated['from_class_id'] : null,
            idempotencyKey: null,
            force: (bool) ($validated['force'] ?? false),
        );

        return $this->encode($result);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['preview', 'transfer'])
                ->required()
                ->description('"preview" is read-only and shows what a move would do. "transfer" performs it after confirmation.'),
            'student' => $schema->string()->max(120)->description('Student number, full name, or email (e.g. "2024-0012", "Maria Santos").'),
            'student_id' => $schema->string()->description('Alias for "student". Accepts the internal student record ID too.'),
            'subject' => $schema->string()->max(120)->description('Which subject to move: a subject code (e.g. "GE-3"), the current section ("B"), or "all" to list the student\'s active subjects.'),
            'from_class_id' => $schema->integer()->description('Disambiguates the source when the identifier is ambiguous.'),
            'to_section' => $schema->string()->max(120)->description('Destination section (e.g. "GE-3 Section A", or a class ID). Omit to keep the subject but remove the section.'),
            'reason' => $schema->string()->max(500)->description('Why the student is moving. Recorded in the enrollment audit trail.'),
            'force' => $schema->boolean()->description('Proceed despite schedule clashes, a full destination, or a subject mismatch. Default false.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action !== 'transfer') {
            return false;
        }

        $subject = $request['subject'] ?? 'the subject';
        $destination = $request['to_section'] ?? null;
        $student = $request['student'] ?? ($request['student_id'] ?? 'the student');

        if (blank($destination)) {
            return Approval::required(
                "Remove the class section for {$subject} from {$student}? The subject enrollment is kept, but the section, room, and instructor are cleared."
            );
        }

        return Approval::required(
            "Move {$student} from {$subject} into {$destination}? The roster, timetable, and tuition assessment will be updated. Run action='preview' first to confirm there are no conflicts."
        );
    }

    private function currentSchool(): ?\App\Models\School
    {
        $school = app(TenantContext::class)->getCurrentSchool();

        return $school instanceof \App\Models\School ? $school : null;
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
