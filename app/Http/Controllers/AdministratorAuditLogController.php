<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

final class AdministratorAuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $event = $request->input('event');
        $logName = $request->input('log_name');
        $subjectType = $request->input('subject_type');
        $subjectTypeQualified = $subjectType && $subjectType !== 'all'
            ? Str::startsWith($subjectType, 'App\\') ? $subjectType : 'App\\Models\\'.Str::studly((string) $subjectType)
            : null;
        $causerId = $request->input('causer_id');
        $dateRange = $request->input('range', '30d');

        $rangeStart = match ($dateRange) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        $logsQuery = Activity::query()
            ->with(['causer'])
            ->when($search && is_string($search), function ($query) use ($search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('description', 'ilike', "%{$search}%")
                        ->orWhere('subject_type', 'ilike', "%{$search}%")
                        ->orWhere('log_name', 'ilike', "%{$search}%");
                });
            })
            ->when($event && $event !== 'all', fn ($query) => $query->where('event', $event))
            ->when($logName && $logName !== 'all', fn ($query) => $query->where('log_name', $logName))
            ->when($subjectTypeQualified, fn ($query) => $query->where('subject_type', $subjectTypeQualified))
            ->when($causerId && $causerId !== 'all', fn ($query) => $query->where('causer_id', $causerId))
            ->where('created_at', '>=', $rangeStart)
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
            'properties' => $activity->properties,
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name ?? 'System',
                'email' => $activity->causer->email ?? null,
                'avatar' => $activity->causer->avatar_url ?? null,
            ] : null,
            'created_at' => format_timestamp($activity->created_at),
            'created_at_human' => $activity->created_at?->shiftTimezone(config('app.timezone'))->diffForHumans() ?? '',
        ]);

        $totalCount = Activity::count();
        $filteredCount = $logs->total();
        $uniqueActors = Activity::query()
            ->whereNotNull('causer_id')
            ->distinct('causer_id')
            ->count('causer_id');

        $actionBreakdown = Activity::query()
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

        $activityTrend = Activity::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => $row->date,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $logNames = Activity::query()
            ->whereNotNull('log_name')
            ->distinct()
            ->orderBy('log_name')
            ->pluck('log_name')
            ->values()
            ->all();

        $subjectTypes = Activity::query()
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

        $causers = User::query()
            ->whereIn('id', Activity::query()->whereNotNull('causer_id')->pluck('causer_id'))
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
                'search' => $search,
                'event' => $event,
                'log_name' => $logName,
                'subject_type' => $subjectType,
                'causer_id' => $causerId,
                'range' => $dateRange,
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
                ],
            ],
            'analytics' => $analytics,
        ]);
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
