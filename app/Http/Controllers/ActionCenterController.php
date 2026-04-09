<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClassPostType;
use App\Models\ClassPost;
use App\Models\Faculty;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ActionCenterController extends Controller
{
    public function __invoke(): Response
    {
        $user = Auth::user();

        $today = Carbon::now()
            ->timezone(config('app.timezone'))
            ->toDateString();

        $faculty = $user ? Faculty::where('email', $user->email)->first() : null;
        $facultyClassIds = $faculty
            ? $faculty->classes()->currentAcademicPeriod()->pluck('id')
            : collect();

        $activityPosts = $facultyClassIds->isEmpty()
            ? collect()
            : ClassPost::query()
                ->whereIn('class_id', $facultyClassIds)
                ->whereIn('type', [
                    ClassPostType::Quiz->value,
                    ClassPostType::Assignment->value,
                    ClassPostType::Activity->value,
                ])
                ->with([
                    'class.subject',
                    'class.SubjectByCodeFallback',
                    'class.ShsSubject',
                    'class.Room',
                    'class.Faculty',
                    'assignedFaculty',
                ])
                ->latest()
                ->get();

        $activities = $activityPosts
            ->map(function (ClassPost $post) use ($user, $today): array {
                $class = $post->class;

                $primarySubject = $class?->subjects->first();

                if (! $primarySubject && $class) {
                    $primarySubject = $class->isShs()
                        ? $class->ShsSubject
                        : ($class->subject ?: $class->SubjectByCodeFallback);
                }

                $subjectCode = $primarySubject?->code ?? $class?->subject_code ?? 'Class';
                $section = $class?->section ?? 'N/A';
                $subtitle = mb_trim(sprintf('%s · %s', $subjectCode, $section), ' ·');

                $status = $post->status ?? 'backlog';
                $priority = $post->priority ?? 'medium';

                $startDate = $post->start_date?->toDateString()
                    ?? $post->created_at?->toDateString()
                    ?? $today;

                $dueDate = $post->due_date?->toDateString() ?? $startDate;
                $startLabel = Carbon::parse($startDate)->format('M d');
                $dueLabel = Carbon::parse($dueDate)->format('M d');
                $timelineLabel = $startLabel === $dueLabel
                    ? "Due {$dueLabel}"
                    : "{$startLabel}–{$dueLabel}";

                $progress = (int) ($post->progress_percent ?? 0);
                if ($status === 'done') {
                    $progress = 100;
                }

                $assignee = $post->assignedFaculty ?? $class?->Faculty;
                $assigneeName = $assignee?->full_name ?? $assignee?->name ?? 'Unassigned';
                $isSelf = $assignee?->email && $user?->email && $assignee->email === $user->email;
                $primaryHref = $class?->id ? "/classes/{$class->id}" : '#';

                return [
                    'id' => "post-{$post->id}",
                    'source_id' => $post->id,
                    'type' => $post->type instanceof ClassPostType ? $post->type->value : (string) $post->type,
                    'status' => $status,
                    'priority' => $priority,
                    'title' => $post->title,
                    'subtitle' => $subtitle,
                    'meta' => $timelineLabel,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'progress' => $progress,
                    'assignee' => [
                        'id' => $assignee?->id,
                        'name' => $assigneeName,
                        'is_self' => $isSelf,
                    ],
                    'primary_action' => [
                        'label' => $class ? 'Open class' : 'View details',
                        'href' => $primaryHref,
                    ],
                ];
            })
            ->values();

        $statusCounts = $activities->countBy('status');
        $doneCount = (int) ($statusCounts['done'] ?? 0);
        $totalCount = $activities->count();
        $activeCount = max($totalCount - $doneCount, 0);
        $focusCount = $activities
            ->where('status', '!=', 'done')
            ->where('priority', 'high')
            ->count();

        $completionRate = $totalCount > 0
            ? (int) round(($doneCount / $totalCount) * 100)
            : 0;

        return Inertia::render('faculty/action-center', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'User',
            ],
            'action_center' => [
                'date' => $today,
                'activities' => $activities->all(),
                'stats' => [
                    'active' => $activeCount,
                    'done' => $doneCount,
                    'focus' => $focusCount,
                    'total' => $totalCount,
                    'completion_rate' => $completionRate,
                    'status_breakdown' => [
                        'backlog' => (int) ($statusCounts['backlog'] ?? 0),
                        'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                        'review' => (int) ($statusCounts['review'] ?? 0),
                        'blocked' => (int) ($statusCounts['blocked'] ?? 0),
                        'done' => (int) ($statusCounts['done'] ?? 0),
                    ],
                ],
            ],
        ]);
    }

    public function updateStatus(Request $request, ClassPost $post): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['backlog', 'in_progress', 'review', 'done', 'blocked'])],
        ]);

        $user = Auth::user();
        /** @var Faculty|null $faculty */
        $faculty = $user ? Faculty::where('email', $user->email)->first() : null;

        if (! $faculty || $post->class->faculty_id !== $faculty->id) {
            abort(403);
        }

        $progress = $post->progress_percent;
        if ($data['status'] === 'done') {
            $progress = 100;
        }

        $post->update([
            'status' => $data['status'],
            'progress_percent' => $progress,
        ]);

        return redirect()->back();
    }
}
