<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AlertAction;
use App\Models\AssessmentRubric;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassResource;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\ClassPostSubmission;
use App\Models\Faculty;
use App\Models\FacultyRequest;
use App\Models\InboxMessage;
use App\Models\InboxTemplate;
use App\Models\InboxThread;
use App\Models\OfficeHourBooking;
use App\Models\OfficeHourSlot;
use App\Models\Student;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FacultyToolkitController extends Controller
{
    private function faculty(): ?Faculty
    {
        $user = Auth::user();

        return $user ? Faculty::where('email', $user->email)->first() : null;
    }

    private function classes(Faculty $faculty): array
    {
        return $faculty->classes()->currentAcademicPeriod()
            ->with(['Room:id,name'])
            ->withCount('class_enrollments')
            ->get()
            ->map(fn (Classes $class): array => [
                'id' => $class->id,
                'code' => $class->subject_code,
                'section' => $class->section,
                'students' => $class->class_enrollments_count,
                'room' => $class->Room?->name,
            ])->values()->all();
    }

    private function user(): array
    {
        $user = Auth::user();

        return [
            'name' => $user?->name,
            'email' => $user?->email,
            'avatar' => $user?->avatar_url,
            'role' => $user?->role?->getLabel() ?? 'User',
        ];
    }

    // ============ AT-RISK ALERTS ============
    public function atRisk(): Response
    {
        $faculty = $this->faculty();
        $classes = $faculty ? $this->classes($faculty) : [];
        $classIds = array_column($classes, 'id');

        $alerts = [];
        if ($classIds !== []) {
            $enrollments = \App\Models\ClassEnrollment::query()
                ->whereIn('class_id', $classIds)
                ->with(['student:id,first_name,last_name,student_id,email'])
                ->get();

            $absenceCounts = ClassAttendanceRecord::query()
                ->whereIn('class_id', $classIds)
                ->whereIn('status', ['absent'])
                ->selectRaw('student_id, COUNT(*) as c')
                ->groupBy('student_id')
                ->pluck('c', 'student_id');

            $missedSubmissions = ClassPostSubmission::query()
                ->whereIn('class_post_id', ClassPost::query()->whereIn('class_id', $classIds)->where('type', 'assignment')->select('id'))
                ->whereNull('submitted_at')
                ->selectRaw('student_id, COUNT(*) as c')
                ->groupBy('student_id')
                ->pluck('c', 'student_id');

            $byStudent = [];
            foreach ($enrollments as $enrollment) {
                $student = $enrollment->student;
                if (! $student) {
                    continue;
                }
                $avg = $enrollment->total_average !== null ? (float) $enrollment->total_average : null;
                $absences = (int) ($absenceCounts[$student->id] ?? 0);
                $missed = (int) ($missedSubmissions[$student->id] ?? 0);

                $reasons = [];
                $score = 0;
                if ($avg !== null && $avg < 75) {
                    $score += (75 - $avg);
                    $reasons[] = ['label' => 'Failing average', 'detail' => 'Average is '.number_format($avg, 1).'%'];
                } elseif ($avg === null) {
                    $score += 15;
                    $reasons[] = ['label' => 'No grades recorded', 'detail' => 'No graded work yet this term'];
                }
                if ($absences >= 3) {
                    $score += min($absences * 4, 20);
                    $reasons[] = ['label' => 'Frequent absences', 'detail' => $absences.' absences recorded'];
                }
                if ($missed >= 2) {
                    $score += min($missed * 5, 20);
                    $reasons[] = ['label' => 'Missing submissions', 'detail' => $missed.' assignments not submitted'];
                }

                if ($score >= 25 || $reasons !== []) {
                    $byStudent[$student->id][] = [
                        'class_id' => $enrollment->class_id,
                        'average' => $avg,
                        'absences' => $absences,
                        'missed' => $missed,
                    ];
                    if (! isset($alerts[$student->id])) {
                        $alerts[$student->id] = [
                            'student_id' => $student->id,
                            'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                            'student_number' => (string) $student->student_id,
                            'email' => $student->email,
                            'reasons' => $reasons,
                            'score' => 0,
                            'classes' => [],
                        ];
                    } else {
                        foreach ($reasons as $reason) {
                            if (! in_array($reason['label'], array_column($alerts[$student->id]['reasons'], 'label'), true)) {
                                $alerts[$student->id]['reasons'][] = $reason;
                            }
                        }
                    }
                    $alerts[$student->id]['score'] = min(100, $alerts[$student->id]['score'] + (int) round($score / max(count($byStudent[$student->id]), 1)));
                    $alerts[$student->id]['classes'][] = $byStudent[$student->id][count($byStudent[$student->id]) - 1];
                }
            }

            foreach ($alerts as $i => $alert) {
                $score = $alert['score'];
                $alerts[$i]['level'] = $score >= 50 ? 'high' : ($score >= 25 ? 'medium' : 'low');
                $actions = AlertAction::query()
                    ->where('student_id', $alert['student_id'])
                    ->whereIn('class_id', $classIds)
                    ->orderByDesc('created_at')
                    ->get()
                    ->map(fn (AlertAction $a): array => [
                        'action' => $a->action,
                        'note' => $a->note,
                        'date' => $a->created_at?->format('M d, Y'),
                    ])->values()->all();
                $alerts[$i]['actions'] = $actions;
            }
        }

        usort($alerts, fn ($a, $b) => $b['score'] <=> $a['score']);

        return Inertia::render('faculty/at-risk', [
            'user' => $this->user(),
            'classes' => $classes,
            'alerts' => array_values($alerts),
        ]);
    }

    public function logAlertAction(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'class_id' => 'required|integer',
            'action' => 'required|string|max:50',
            'note' => 'nullable|string|max:2000',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }
        AlertAction::create($validated + ['faculty_id' => $faculty->id]);

        return back();
    }

    // ============ ASSESSMENTS ============
    public function assessments(): Response
    {
        $faculty = $this->faculty();
        $classes = $faculty ? $this->classes($faculty) : [];
        $classIds = array_column($classes, 'id');

        $posts = ClassPost::query()
            ->whereIn('class_id', $classIds)
            ->whereIn('type', ['assignment', 'quiz', 'activity'])
            ->withCount(['submissions'])
            ->orderByDesc('due_date')
            ->get()
            ->map(function (ClassPost $post) use ($classes): array {
                $class = collect($classes)->firstWhere('id', $post->class_id);
                $graded = $post->submissions()->whereNotNull('points')->count();

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'type' => $post->type,
                    'class_id' => $post->class_id,
                    'class_code' => $class['code'] ?? '—',
                    'due_date' => $post->due_date?->format('M d, Y'),
                    'total_points' => $post->total_points,
                    'submitted' => $post->submissions_count,
                    'graded' => $graded,
                    'status' => $post->status,
                    'has_rubric' => is_array($post->rubric) && count($post->rubric) > 0,
                ];
            })->values()->all();

        $rubrics = AssessmentRubric::query()
            ->where('faculty_id', $faculty?->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (AssessmentRubric $r): array => [
                'id' => $r->id,
                'title' => $r->title,
                'description' => $r->description,
                'criteria' => $r->criteria,
                'total_points' => collect($r->criteria)->sum('points'),
                'is_shared' => $r->is_shared,
            ])->values()->all();

        return Inertia::render('faculty/assessments', [
            'user' => $this->user(),
            'classes' => $classes,
            'assessments' => $posts,
            'rubrics' => $rubrics,
        ]);
    }

    public function storeRubric(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'criteria' => 'required|array|min:1',
            'criteria.*.title' => 'required|string|max:200',
            'criteria.*.points' => 'required|integer|min:0',
            'is_shared' => 'boolean',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }
        AssessmentRubric::create($validated + [
            'faculty_id' => $faculty->id,
            'school_id' => $faculty->school_id,
        ]);

        return back();
    }

    public function destroyRubric(AssessmentRubric $rubric)
    {
        $faculty = $this->faculty();
        if (! $faculty || $rubric->faculty_id !== $faculty->id) {
            abort(403);
        }
        $rubric->delete();

        return back();
    }

    // ============ INBOX ============
    public function inbox(): Response
    {
        $faculty = $this->faculty();
        $classes = $faculty ? $this->classes($faculty) : [];
        $threads = InboxThread::query()
            ->where('faculty_id', $faculty?->id)
            ->with(['messages', 'student:id,first_name,last_name'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (InboxThread $thread): array {
                $student = $thread->student;
                $unread = $thread->messages->whereNull('read_at')->where('sender_type', 'student')->count();

                return [
                    'id' => $thread->id,
                    'subject' => $thread->subject,
                    'status' => $thread->status,
                    'class_id' => $thread->class_id,
                    'student' => $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : null,
                    'messages' => $thread->messages->map(fn (InboxMessage $m): array => [
                        'id' => $m->id,
                        'from' => $m->sender_type,
                        'body' => $m->body,
                        'at' => $m->created_at?->format('M d, Y g:i A'),
                        'unread' => $m->sender_type === 'student' && $m->read_at === null,
                    ])->values()->all(),
                    'unread' => $unread,
                    'last_at' => $thread->last_message_at?->format('M d, Y'),
                ];
            })->values()->all();

        $templates = InboxTemplate::query()->where('faculty_id', $faculty?->id)->orderBy('name')
            ->get(['id', 'name', 'subject', 'body'])->values()->all();

        $roster = [];
        $classIds = array_column($classes, 'id');
        if ($classIds !== []) {
            $roster = Student::query()
                ->whereHas('classEnrollments', fn ($q) => $q->whereIn('class_id', $classIds))
                ->get(['id', 'first_name', 'last_name', 'student_id'])
                ->map(fn (Student $s): array => [
                    'id' => $s->id,
                    'name' => trim(($s->first_name ?? '').' '.($s->last_name ?? '')),
                    'number' => (string) $s->student_id,
                ])->unique('id')->values()->all();
        }

        return Inertia::render('faculty/inbox', [
            'user' => $this->user(),
            'classes' => $classes,
            'threads' => $threads,
            'templates' => $templates,
            'roster' => $roster,
        ]);
    }

    public function sendInboxMessage(Request $request)
    {
        $validated = $request->validate([
            'thread_id' => 'nullable|integer|exists:inbox_threads,id',
            'student_id' => 'required_without:thread_id|integer',
            'class_id' => 'nullable|integer',
            'subject' => 'required_without:thread_id|string|max:200',
            'body' => 'required|string|max:5000',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }

        if (! empty($validated['thread_id'])) {
            $thread = InboxThread::where('faculty_id', $faculty->id)->findOrFail($validated['thread_id']);
        } else {
            $thread = InboxThread::create([
                'faculty_id' => $faculty->id,
                'class_id' => $validated['class_id'] ?? null,
                'student_id' => $validated['student_id'],
                'subject' => $validated['subject'],
                'last_message_at' => now(),
            ]);
        }

        InboxMessage::create([
            'thread_id' => $thread->id,
            'sender_type' => 'faculty',
            'sender_id' => $faculty->id,
            'body' => $validated['body'],
        ]);
        $thread->update(['last_message_at' => now()]);

        return back();
    }

    public function markThreadRead(InboxThread $thread)
    {
        $faculty = $this->faculty();
        if (! $faculty || $thread->faculty_id !== $faculty->id) {
            abort(403);
        }
        $thread->messages()->where('sender_type', 'student')->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function storeTemplate(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|max:5000',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }
        InboxTemplate::create($validated + ['faculty_id' => $faculty->id]);

        return back();
    }

    public function destroyTemplate(InboxTemplate $template)
    {
        $faculty = $this->faculty();
        if (! $faculty || $template->faculty_id !== $faculty->id) {
            abort(403);
        }
        $template->delete();

        return back();
    }

    // ============ OFFICE HOURS ============
    public function officeHours(): Response
    {
        $faculty = $this->faculty();
        $slots = OfficeHourSlot::query()
            ->where('faculty_id', $faculty?->id)
            ->with(['bookings.student:id,first_name,last_name'])
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
            ->orderBy('start_time')
            ->get()
            ->map(fn (OfficeHourSlot $slot): array => [
                'id' => $slot->id,
                'day' => $slot->day_of_week,
                'start' => substr((string) $slot->start_time, 0, 5),
                'end' => substr((string) $slot->end_time, 0, 5),
                'mode' => $slot->mode,
                'location' => $slot->location,
                'capacity' => $slot->capacity,
                'is_active' => $slot->is_active,
                'bookings' => $slot->bookings->where('status', 'booked')->map(fn (OfficeHourBooking $b): array => [
                    'id' => $b->id,
                    'student' => $b->student ? trim(($b->student->first_name ?? '').' '.($b->student->last_name ?? '')) : 'Student',
                    'topic' => $b->topic,
                ])->values()->all(),
            ])->values()->all();

        $upcoming = OfficeHourBooking::query()
            ->whereHas('slot', fn ($q) => $q->where('faculty_id', $faculty?->id))
            ->whereIn('status', ['booked', 'completed'])
            ->with(['student:id,first_name,last_name', 'slot'])
            ->orderBy('booked_at')
            ->get()
            ->map(fn (OfficeHourBooking $b): array => [
                'id' => $b->id,
                'student' => $b->student ? trim(($b->student->first_name ?? '').' '.($b->student->last_name ?? '')) : 'Student',
                'day' => $b->slot?->day_of_week,
                'time' => substr((string) $b->slot?->start_time, 0, 5),
                'topic' => $b->topic,
                'status' => $b->status,
            ])->values()->all();

        return Inertia::render('faculty/office-hours', [
            'user' => $this->user(),
            'slots' => $slots,
            'upcoming' => $upcoming,
        ]);
    }

    public function storeSlot(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'mode' => 'required|in:in_person,online',
            'location' => 'nullable|string|max:200',
            'capacity' => 'required|integer|min:1|max:50',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }
        OfficeHourSlot::create($validated + [
            'faculty_id' => $faculty->id,
            'school_id' => $faculty->school_id,
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
        ]);

        return back();
    }

    public function toggleSlot(OfficeHourSlot $slot)
    {
        $faculty = $this->faculty();
        if (! $faculty || $slot->faculty_id !== $faculty->id) {
            abort(403);
        }
        $slot->update(['is_active' => ! $slot->is_active]);

        return back();
    }

    public function destroySlot(OfficeHourSlot $slot)
    {
        $faculty = $this->faculty();
        if (! $faculty || $slot->faculty_id !== $faculty->id) {
            abort(403);
        }
        $slot->bookings()->whereIn('status', ['booked'])->update(['status' => 'cancelled']);
        $slot->delete();

        return back();
    }

    public function updateBooking(Request $request, OfficeHourBooking $booking)
    {
        $faculty = $this->faculty();
        if (! $faculty || $booking->slot?->faculty_id !== $faculty->id) {
            abort(403);
        }
        $validated = $request->validate(['status' => 'required|in:completed,cancelled,booked']);
        $booking->update($validated);

        return back();
    }

    // ============ REQUESTS & APPROVALS ============
    public function requests(): Response
    {
        $faculty = $this->faculty();
        $requests = FacultyRequest::query()
            ->where('faculty_id', $faculty?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FacultyRequest $r): array => [
                'id' => $r->id,
                'type' => $r->type,
                'title' => $r->title,
                'details' => $r->details,
                'status' => $r->status,
                'steps' => $r->steps ?? [],
                'current_step' => $r->current_step,
                'decision_note' => $r->decision_note,
                'decided_at' => $r->decided_at?->format('M d, Y'),
                'created_at' => $r->created_at?->format('M d, Y'),
            ])->values()->all();

        $types = [
            ['value' => 'leave', 'label' => 'Leave Request', 'steps' => ['Department Head', 'Dean']],
            ['value' => 'make_up_exam', 'label' => 'Make-up Exam', 'steps' => ['Department Head']],
            ['value' => 'grade_change', 'label' => 'Grade Change', 'steps' => ['Department Head', 'Registrar']],
            ['value' => 'room_change', 'label' => 'Room Change', 'steps' => ['Department Head', 'Admin']],
            ['value' => 'supply', 'label' => 'Supplies / Requisition', 'steps' => ['Department Head', 'Admin']],
            ['value' => 'other', 'label' => 'Other', 'steps' => ['Department Head']],
        ];

        return Inertia::render('faculty/requests', [
            'user' => $this->user(),
            'requests' => $requests,
            'request_types' => $types,
        ]);
    }

    public function storeRequest(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'title' => 'required|string|max:200',
            'details' => 'nullable|string|max:3000',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }
        $types = ['leave' => ['Department Head', 'Dean'], 'make_up_exam' => ['Department Head'],
            'grade_change' => ['Department Head', 'Registrar'], 'room_change' => ['Department Head', 'Admin'],
            'supply' => ['Department Head', 'Admin'], 'other' => ['Department Head']];
        $steps = $types[$validated['type']] ?? ['Department Head'];

        FacultyRequest::create($validated + [
            'faculty_id' => $faculty->id,
            'school_id' => $faculty->school_id,
            'steps' => array_map(fn (string $s): array => ['name' => $s, 'status' => 'pending'], $steps),
            'status' => 'pending',
        ]);

        return back();
    }

    public function withdrawRequest(FacultyRequest $requestModel)
    {
        $faculty = $this->faculty();
        if (! $faculty || $requestModel->faculty_id !== $faculty->id) {
            abort(403);
        }
        if ($requestModel->status === 'pending') {
            $requestModel->update(['status' => 'withdrawn']);
        }

        return back();
    }

    // ============ INSIGHTS ============
    public function insights(): Response
    {
        $faculty = $this->faculty();
        $classes = $faculty ? $this->classes($faculty) : [];
        $classIds = array_column($classes, 'id');

        $perClass = [];
        $overall = ['students' => 0, 'avg' => null, 'at_risk' => 0, 'submission_rate' => null];

        if ($classIds !== []) {
            $perClass = collect($classes)->map(function (array $class) {
                $enrollments = \App\Models\ClassEnrollment::query()->where('class_id', $class['id'])->get();
                $averages = $enrollments->pluck('total_average')->filter()->map(fn ($v) => (float) $v);
                $avg = $averages->isNotEmpty() ? round($averages->avg(), 1) : null;
                $atRisk = $enrollments->filter(fn ($e) => $e->total_average !== null && (float) $e->total_average < 75)->count();

                $distribution = [90 => 0, 80 => 0, 70 => 0, 60 => 0, 0 => 0];
                foreach ($averages as $a) {
                    $bucket = $a >= 90 ? 90 : ($a >= 80 ? 80 : ($a >= 70 ? 70 : ($a >= 60 ? 60 : 0)));
                    $distribution[$bucket]++;
                }

                $assignmentPosts = ClassPost::query()->where('class_id', $class['id'])->where('type', 'assignment')->count();
                $expected = $assignmentPosts * $enrollments->count();
                $submitted = ClassPostSubmission::query()
                    ->whereIn('class_post_id', ClassPost::query()->where('class_id', $class['id'])->where('type', 'assignment')->select('id'))
                    ->whereNotNull('submitted_at')->count();

                $sessions = \DB::table('class_attendance_sessions')->where('class_id', $class['id'])->count();
                $records = ClassAttendanceRecord::query()->where('class_id', $class['id'])->count();
                $present = ClassAttendanceRecord::query()->where('class_id', $class['id'])->whereIn('status', ['present', 'late'])->count();

                return [
                    'id' => $class['id'],
                    'code' => $class['code'],
                    'section' => $class['section'],
                    'students' => $enrollments->count(),
                    'avg' => $avg,
                    'at_risk' => $atRisk,
                    'distribution' => $distribution,
                    'submission_rate' => $expected > 0 ? round($submitted / $expected * 100) : null,
                    'attendance_rate' => $records > 0 ? round($present / $records * 100) : null,
                    'assignment_count' => $assignmentPosts,
                    'session_count' => $sessions,
                ];
            })->values()->all();

            $allAvg = collect($perClass)->pluck('avg')->filter();
            $overall = [
                'students' => collect($perClass)->sum('students'),
                'avg' => $allAvg->isNotEmpty() ? round($allAvg->avg(), 1) : null,
                'at_risk' => collect($perClass)->sum('at_risk'),
                'submission_rate' => collect($perClass)->pluck('submission_rate')->filter()->avg() !== null
                    ? round(collect($perClass)->pluck('submission_rate')->filter()->avg()) : null,
                'attendance_rate' => collect($perClass)->pluck('attendance_rate')->filter()->avg() !== null
                    ? round(collect($perClass)->pluck('attendance_rate')->filter()->avg()) : null,
            ];
        }

        return Inertia::render('faculty/insights', [
            'user' => $this->user(),
            'classes' => $classes,
            'per_class' => $perClass,
            'overall' => $overall,
        ]);
    }

    // ============ RESOURCES ============
    public function resources(): Response
    {
        $faculty = $this->faculty();
        $classes = $faculty ? $this->classes($faculty) : [];
        $classIds = array_column($classes, 'id');

        $resources = ClassResource::query()
            ->whereIn('class_id', $classIds)
            ->where('faculty_id', $faculty?->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ClassResource $r) use ($classes): array {
                $class = collect($classes)->firstWhere('id', $r->class_id);

                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'description' => $r->description,
                    'type' => $r->type,
                    'url' => $r->url,
                    'visibility' => $r->visibility,
                    'downloads' => $r->downloads,
                    'class_code' => $class['code'] ?? '—',
                    'class_id' => $r->class_id,
                    'created_at' => $r->created_at?->format('M d, Y'),
                ];
            })->values()->all();

        return Inertia::render('faculty/resources', [
            'user' => $this->user(),
            'classes' => $classes,
            'resources' => $resources,
        ]);
    }

    public function storeResource(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:2000',
            'type' => 'required|in:link,file',
            'url' => 'required_unless:type,file|string|max:1000',
            'file' => 'required_if:type,file|file|max:51200',
            'visibility' => 'required|in:faculty_only,students',
        ]);
        $faculty = $this->faculty();
        if (! $faculty) {
            abort(403);
        }

        $url = $validated['url'] ?? null;
        if (($validated['type'] ?? null) === 'file' && $request->hasFile('file')) {
            $path = $request->file('file')->store('class-resources/'.$validated['class_id']);
            $url = $path;
        }

        ClassResource::create([
            'class_id' => $validated['class_id'],
            'faculty_id' => $faculty->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'url' => (string) $url,
            'visibility' => $validated['visibility'],
            'school_id' => $faculty->school_id,
        ]);

        return back();
    }

    public function destroyResource(ClassResource $resource)
    {
        $faculty = $this->faculty();
        if (! $faculty || $resource->faculty_id !== $faculty->id) {
            abort(403);
        }
        if ($resource->type === 'file' && ! str_starts_with((string) $resource->url, 'http')) {
            Storage::delete((string) $resource->url);
        }
        $resource->delete();

        return back();
    }

    // ============ FACULTY FORMS ============
    public function forms(): Response
    {
        $user = Auth::user();
        $forms = Form::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'slug', 'description', 'access_mode', 'closes_at'])
            ->map(function (Form $form) use ($user): array {
                $responded = $user ? FormResponse::query()
                    ->where('form_id', $form->id)
                    ->where('respondent_user_id', (string) $user->id)
                    ->where('status', '!=', 'draft')
                    ->exists() : false;

                return [
                    'id' => $form->id,
                    'title' => $form->title,
                    'slug' => $form->slug,
                    'description' => $form->description,
                    'access_mode' => $form->access_mode,
                    'closes_at' => $form->closes_at?->format('M d, Y'),
                    'responded' => $responded,
                    'url' => '/forms/'.$form->slug,
                ];
            })->values()->all();

        return Inertia::render('faculty/forms', [
            'user' => $this->user(),
            'forms' => $forms,
        ]);
    }
}
