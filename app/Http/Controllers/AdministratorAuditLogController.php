<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\TenantContext;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdministratorAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->extractFilters($request);

        $scopedBase = $this->scopedBaseQuery();
        $logsQuery = $this->applyFilters(clone $scopedBase, $filters)
            ->with(['causer'])
            ->latest('id');

        $logs = $logsQuery
            ->paginate(30)
            ->withQueryString();

        $logs->through(fn (Activity $activity): array => [
            'id' => $activity->id,
            'description' => $activity->description,
            'event' => $activity->event,
            'log_name' => $activity->log_name,
            'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'subject_url' => $this->resolveSubjectUrl($activity),
            'ip_address' => $this->resolveIpAddress($activity),
            'properties' => $activity->attribute_changes,
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name ?? 'System',
                'email' => $activity->causer->email ?? null,
                'avatar' => $activity->causer->avatar_url ?? null,
            ] : null,
            'created_at' => format_timestamp($activity->created_at),
            'created_at_human' => $activity->created_at?->shiftTimezone(config('app.timezone'))->diffForHumans() ?? '',
        ]);

        $totalCount = (clone $scopedBase)->count();
        $filteredCount = $logs->total();
        $uniqueActors = (clone $scopedBase)
            ->whereNotNull('causer_id')
            ->distinct('causer_id')
            ->count('causer_id');

        $actionBreakdown = (clone $scopedBase)
            ->selectRaw('event, COUNT(*) as count')
            ->whereNotNull('event')
            ->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'event' => $row->event,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $trendQuery = (clone $scopedBase)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count');
        $this->applyDateBounds($trendQuery, $filters);

        $activityTrend = $trendQuery
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $logNames = (clone $scopedBase)
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->values()
            ->all();

        $subjectTypes = (clone $scopedBase)
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => class_basename($type),
            ])
            ->values()
            ->all();

        $causerIds = (clone $scopedBase)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        $causers = User::query()
            ->whereIn('id', $causerIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'avatar_url'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
            ])
            ->values()
            ->all();

        $analytics = [
            'total' => $totalCount,
            'filtered' => $filteredCount,
            'unique_actors' => $uniqueActors,
            'action_breakdown' => $actionBreakdown,
            'trend' => $activityTrend,
            'last_updated_at' => format_timestamp_now(),
        ];

        return Inertia::render('administrators/audit-logs/index', [
            'user' => $this->getUserProps(),
            'logs' => $logs,
            'filters' => [
                'search' => $filters['search'],
                'event' => $filters['event'],
                'log_name' => $filters['log_name'],
                'subject_type' => $filters['subject_type'],
                'subject_id' => $filters['subject_id'],
                'causer_id' => $filters['causer_id'],
                'range' => $filters['range'],
                'from' => $filters['from'],
                'to' => $filters['to'],
            ],
            'options' => [
                'events' => ['created', 'updated', 'deleted', 'restored', 'impersonated'],
                'log_names' => $logNames,
                'subject_types' => $subjectTypes,
                'causers' => $causers,
                'ranges' => [
                    ['value' => '7d', 'label' => 'Last 7 days'],
                    ['value' => '30d', 'label' => 'Last 30 days'],
                    ['value' => '90d', 'label' => 'Last 90 days'],
                    ['value' => 'all', 'label' => 'All time'],
                ],
            ],
            'analytics' => $analytics,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->extractFilters($request);

        $query = $this->applyFilters($this->scopedBaseQuery(), $filters)
            ->with(['causer'])
            ->latest('id');

        $filename = sprintf('audit-logs-%s.csv', now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'timestamp',
                'event',
                'description',
                'causer',
                'subject_type',
                'subject_id',
                'ip_address',
            ]);

            $query->chunk(500, function ($activities) use ($handle): void {
                foreach ($activities as $activity) {
                    /** @var Activity $activity */
                    $causerLabel = $activity->causer
                        ? mb_trim(($activity->causer->name ?? 'System').($activity->causer->email ? " ({$activity->causer->email})" : ''))
                        : 'System';

                    fputcsv($handle, [
                        $this->sanitizeCsvCell($activity->created_at?->toIso8601String() ?? ''),
                        $this->sanitizeCsvCell($activity->event ?? ''),
                        $this->sanitizeCsvCell($activity->description ?? ''),
                        $this->sanitizeCsvCell($causerLabel),
                        $this->sanitizeCsvCell($activity->subject_type ? class_basename($activity->subject_type) : ''),
                        $this->sanitizeCsvCell($activity->subject_id !== null ? (string) $activity->subject_id : ''),
                        $this->sanitizeCsvCell($this->resolveIpAddress($activity) ?? ''),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * @return Builder<Activity>
     */
    private function scopedBaseQuery(): Builder
    {
        $query = Activity::query();

        $tenantContext = app(TenantContext::class);
        $schoolId = $tenantContext->getCurrentSchoolId();
        $isPlatformAdmin = $tenantContext->canAccessAllOrganizations();

        if ($schoolId === null) {
            if (! $isPlatformAdmin) {
                $userId = request()->user()?->id;

                return $userId
                    ? $query->where('causer_type', User::class)->where('causer_id', $userId)
                    : $query->whereRaw('1 = 0');
            }

            return $query;
        }

        $usersTable = (new User)->getTable();
        $studentsTable = (new Student)->getTable();
        $enrollmentsTable = (new StudentEnrollment)->getTable();
        $facultyTable = (new Faculty)->getTable();
        $classesTable = (new Classes)->getTable();

        return $query->where(function (Builder $scoped) use ($schoolId, $usersTable, $studentsTable, $enrollmentsTable, $facultyTable, $classesTable): void {
            $scoped->where(function (Builder $causerQuery) use ($schoolId, $usersTable): void {
                $causerQuery->where('causer_type', User::class)
                    ->where(function (Builder $userMatch) use ($schoolId, $usersTable): void {
                        $userMatch->whereIn('causer_id', function ($sub) use ($schoolId, $usersTable): void {
                            $sub->select('id')->from($usersTable)->where('school_id', $schoolId);
                        })->orWhereIn('causer_id', function ($sub) use ($schoolId): void {
                            $sub->select('user_id')->from('organization_user')->where('school_id', $schoolId);
                        });
                    });
            })->orWhere(function (Builder $subjectStudent) use ($schoolId, $studentsTable): void {
                $subjectStudent->where('subject_type', Student::class)
                    ->whereIn('subject_id', function ($sub) use ($schoolId, $studentsTable): void {
                        $sub->select('id')->from($studentsTable)->where('school_id', $schoolId);
                    });
            })->orWhere(function (Builder $subjectEnrollment) use ($schoolId, $enrollmentsTable): void {
                $subjectEnrollment->where('subject_type', StudentEnrollment::class)
                    ->whereIn('subject_id', function ($sub) use ($schoolId, $enrollmentsTable): void {
                        $sub->select('id')->from($enrollmentsTable)->where('school_id', $schoolId);
                    });
            })->orWhere(function (Builder $subjectFaculty) use ($schoolId, $facultyTable): void {
                $subjectFaculty->where('subject_type', Faculty::class)
                    ->whereIn('subject_id', function ($sub) use ($schoolId, $facultyTable): void {
                        $sub->select('id')->from($facultyTable)->where('school_id', $schoolId);
                    });
            })->orWhere(function (Builder $subjectClass) use ($schoolId, $classesTable): void {
                $subjectClass->where('subject_type', Classes::class)
                    ->whereIn('subject_id', function ($sub) use ($schoolId, $classesTable): void {
                        $sub->select('id')->from($classesTable)->where('school_id', $schoolId);
                    });
            })->orWhere(function (Builder $subjectUser) use ($schoolId, $usersTable): void {
                $subjectUser->where('subject_type', User::class)
                    ->whereIn('subject_id', function ($sub) use ($schoolId, $usersTable): void {
                        $sub->select('id')->from($usersTable)->where('school_id', $schoolId);
                    });
            })->orWhere('properties->school_id', $schoolId);
        });
    }

    /**
     * @return array{
     *     search: string|null,
     *     event: string|null,
     *     log_name: string|null,
     *     subject_type: string|null,
     *     subject_type_qualified: string|null,
     *     subject_id: string|null,
     *     causer_id: string|null,
     *     range: string,
     *     from: string|null,
     *     to: string|null,
     * }
     */
    private function extractFilters(Request $request): array
    {
        $search = $request->input('search');
        $event = $request->input('event');
        $logName = $request->input('log_name');
        $subjectType = $request->input('subject_type');
        $subjectTypeQualified = is_string($subjectType) && $subjectType !== '' && $subjectType !== 'all'
            ? (Str::startsWith($subjectType, 'App\\') ? $subjectType : 'App\\Models\\'.Str::studly($subjectType))
            : null;
        $subjectIdRaw = $request->input('subject_id');
        $subjectId = $subjectIdRaw !== null && mb_trim((string) $subjectIdRaw) !== ''
            ? mb_trim((string) $subjectIdRaw)
            : null;
        $causerId = $request->input('causer_id');
        $fromRaw = $request->input('from');
        $toRaw = $request->input('to');
        $from = is_string($fromRaw) && mb_trim($fromRaw) !== '' ? mb_trim($fromRaw) : null;
        $to = is_string($toRaw) && mb_trim($toRaw) !== '' ? mb_trim($toRaw) : null;
        $dateRange = (string) ($request->input('range') ?: '30d');

        return [
            'search' => is_string($search) && mb_trim($search) !== '' ? mb_trim($search) : null,
            'event' => is_string($event) && $event !== '' ? $event : null,
            'log_name' => is_string($logName) && $logName !== '' ? $logName : null,
            'subject_type' => is_string($subjectType) && $subjectType !== '' ? $subjectType : null,
            'subject_type_qualified' => $subjectTypeQualified,
            'subject_id' => $subjectId,
            'causer_id' => $causerId !== null && (string) $causerId !== '' ? (string) $causerId : null,
            'range' => $dateRange,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, string|null>  $filters
     * @return Builder<Activity>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = $filters['search'];
        $event = $filters['event'];
        $logName = $filters['log_name'];
        $subjectTypeQualified = $filters['subject_type_qualified'];
        $subjectId = $filters['subject_id'];
        $causerId = $filters['causer_id'];

        $query
            ->when($search !== null, function (Builder $nestedRoot) use ($search): void {
                $nestedRoot->where(function (Builder $nested) use ($search): void {
                    $nested->whereLike('description', "%{$search}%")
                        ->orWhereLike('subject_type', "%{$search}%")
                        ->orWhereLike('log_name', "%{$search}%");
                    if (ctype_digit($search)) {
                        $nested->orWhere('subject_id', (int) $search);
                    }
                });
            })
            ->when($event !== null && $event !== 'all', fn (Builder $q) => $q->where('event', $event))
            ->when($logName !== null && $logName !== 'all', fn (Builder $q) => $q->where('log_name', $logName))
            ->when($subjectTypeQualified !== null, fn (Builder $q) => $q->where('subject_type', $subjectTypeQualified))
            ->when($subjectId !== null, fn (Builder $q) => $q->where('subject_id', $subjectId))
            ->when($causerId !== null && $causerId !== 'all', fn (Builder $q) => $q->where('causer_id', $causerId));

        $this->applyDateBounds($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Activity>  $query
     * @param  array<string, string|null>  $filters
     */
    private function applyDateBounds(Builder $query, array $filters): void
    {
        $from = $this->parseDateBoundary($filters['from'] ?? null, startOfDay: true);
        $to = $this->parseDateBoundary($filters['to'] ?? null, startOfDay: false);

        if ($from !== null || $to !== null) {
            if ($from !== null) {
                $query->where('created_at', '>=', $from);
            }
            if ($to !== null) {
                $query->where('created_at', '<=', $to);
            }

            return;
        }

        $range = $filters['range'] ?? '30d';
        if ($range === 'all') {
            return;
        }

        $rangeStart = match ($range) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $query->where('created_at', '>=', $rangeStart);
    }

    private function parseDateBoundary(?string $raw, bool $startOfDay): ?Carbon
    {
        if ($raw === null || mb_trim($raw) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw);

            return $startOfDay ? $parsed->startOfDay() : $parsed->endOfDay();
        } catch (Exception) {
            return null;
        }
    }

    private function resolveSubjectUrl(Activity $activity): ?string
    {
        if (! $activity->subject_type || $activity->subject_id === null) {
            return null;
        }

        $id = $activity->subject_id;

        return match (class_basename($activity->subject_type)) {
            'Student' => "/administrators/students/{$id}",
            'StudentEnrollment' => "/administrators/enrollments/{$id}",
            'Faculty' => "/administrators/faculties/{$id}",
            'User' => "/administrators/users/{$id}",
            'Classes' => "/administrators/classes/{$id}",
            'Course' => "/administrators/curriculum/programs/{$id}",
            'HelpTicket' => "/administrators/help-tickets/{$id}",
            default => null,
        };
    }

    private function resolveIpAddress(Activity $activity): ?string
    {
        $columnIp = $activity->getAttribute('ip_address');
        if (is_string($columnIp) && mb_trim($columnIp) !== '') {
            return mb_trim($columnIp);
        }

        $properties = $activity->properties;
        $propIp = data_get($properties, 'ip') ?? data_get($properties, 'ip_address');

        return is_string($propIp) && mb_trim($propIp) !== '' ? mb_trim($propIp) : null;
    }

    private function sanitizeCsvCell(string $value): string
    {
        $trimmedLeading = mb_ltrim($value, " \t\r\n");

        if ($trimmedLeading !== '' && in_array($trimmedLeading[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        if ($value !== '' && in_array($value[0], ["\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }
}
