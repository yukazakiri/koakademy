<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentClassWaitlist;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final readonly class EnrollmentWaitlistService
{
    public function __construct(
        private GeneralSettingsService $settingsService,
    ) {}

    /**
     * Build the term-level Unassigned Seats + Waitlist Queue snapshot without N+1 queries.
     *
     * @return array<string, mixed>
     */
    public function queueSnapshot(
        ?string $schoolYear = null,
        ?int $semester = null,
        ?string $search = null,
        ?int $courseId = null,
    ): array {
        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );
        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();

        $termClasses = Classes::query()
            ->with(['Faculty'])
            ->where('school_year', $resolvedYear)
            ->where('semester', $resolvedSemester)
            ->withCount('class_enrollments')
            ->get();

        // Active class enrollments keyed by "student_id:class_id" for O(1) lookup
        $activeClassEnrollments = ClassEnrollment::query()
            ->whereIn('class_id', $termClasses->pluck('id'))
            ->where('status', true)
            ->get(['student_id', 'class_id'])
            ->mapWithKeys(fn (ClassEnrollment $ce): array => [$ce->student_id.':'.$ce->class_id => true]);

        $subjectEnrollmentsQuery = SubjectEnrollment::query()
            ->with(['student.Course', 'subject', 'studentEnrollment'])
            ->where('school_year', $resolvedYear)
            ->where('semester', $resolvedSemester)
            ->whereNotNull('enrollment_id')
            ->whereNotNull('subject_id')
            ->whereHas('studentEnrollment', function ($q) use ($courseId): void {
                $q->whereNull('deleted_at')
                    ->where(function ($outcomes): void {
                        $outcomes->whereNull('terminal_outcome')
                            ->orWhereNotIn('terminal_outcome', StudentDirectoryQuery::ABANDONED_TERMINAL_OUTCOMES);
                    });
                if ($courseId !== null) {
                    $q->where('course_id', $courseId);
                }
            });

        if (filled($search)) {
            $like = '%'.mb_strtolower(mb_trim((string) $search)).'%';
            $subjectEnrollmentsQuery->where(function ($sub) use ($like): void {
                $sub->whereHas('subject', fn ($s) => $s->whereRaw('LOWER(code) LIKE ?', [$like])->orWhereRaw('LOWER(title) LIKE ?', [$like]))
                    ->orWhereHas('student', fn ($st) => $st->whereRaw('LOWER(first_name) LIKE ?', [$like])->orWhereRaw('LOWER(last_name) LIKE ?', [$like]));
            });
        }

        $allSubjectEnrollments = $subjectEnrollmentsQuery->limit(400)->get();

        $waitingBySubjectEnrollment = Schema::hasTable('enrollment_class_waitlists')
            ? EnrollmentClassWaitlist::query()
                ->waiting()
                ->where('school_year', $resolvedYear)
                ->where('semester', $resolvedSemester)
                ->get()
                ->keyBy('subject_enrollment_id')
            : collect();

        $unassigned = collect();
        $autoAssignableCount = 0;
        $allFullCount = 0;

        foreach ($allSubjectEnrollments as $se) {
            $hasActiveClass = $se->class_id !== null && $activeClassEnrollments->has($se->student_id.':'.$se->class_id);
            if ($hasActiveClass || ! $se->subject instanceof Subject) {
                continue;
            }

            $courseIdForMatch = $se->studentEnrollment?->course_id ?? $se->student?->course_id;
            $matchingClasses = $this->matchingClassesForSubject($termClasses, $se->subject, $courseIdForMatch);
            $openClasses = $matchingClasses->filter(fn (Classes $c): bool => $this->hasAvailableSlot($c))->values();
            $fullClasses = $matchingClasses->filter(fn (Classes $c): bool => ! $this->hasAvailableSlot($c))->values();

            if ($openClasses->isNotEmpty()) {
                $autoAssignableCount++;
            } elseif ($fullClasses->isNotEmpty()) {
                $allFullCount++;
            }

            $waitlist = $waitingBySubjectEnrollment->get($se->id);

            $unassigned->push([
                'subject_enrollment_id' => $se->id,
                'enrollment_id' => $se->enrollment_id,
                'student_id' => $se->student_id,
                'student_number' => $se->student?->student_id ?? $se->student_id,
                'student_name' => $se->student?->full_name ?? 'Unknown Student',
                'course_code' => $se->student?->Course?->code,
                'subject_id' => $se->subject->id,
                'subject_code' => $se->subject->code,
                'subject_title' => $se->subject->title,
                'has_open_seat' => $openClasses->isNotEmpty(),
                'is_waitlisted' => $waitlist instanceof EnrollmentClassWaitlist,
                'waitlist_id' => $waitlist?->id,
                'waitlist_position' => $waitlist?->position,
                'open_classes' => $openClasses->map(fn (Classes $c): array => $this->serializeClassOption($c))->all(),
                'full_classes' => $fullClasses->map(fn (Classes $c): array => $this->serializeClassOption($c))->all(),
            ]);
        }

        $waitlists = Schema::hasTable('enrollment_class_waitlists')
            ? EnrollmentClassWaitlist::query()
                ->with(['student.Course', 'subject', 'preferredClass', 'promotedClass', 'addedBy:id,name', 'promotedBy:id,name'])
                ->where('school_year', $resolvedYear)
                ->where('semester', $resolvedSemester)
                ->orderByRaw("CASE WHEN status = 'waiting' THEN 0 ELSE 1 END")
                ->orderBy('subject_id')
                ->orderBy('position')
                ->limit(250)
                ->get()
                ->map(function (EnrollmentClassWaitlist $w) use ($termClasses): array {
                    $openForSubject = $w->subject instanceof Subject
                        ? $this->matchingClassesForSubject($termClasses, $w->subject, $w->student?->course_id)
                            ->filter(fn (Classes $c): bool => $this->hasAvailableSlot($c))
                            ->values()
                            ->map(fn (Classes $c): array => $this->serializeClassOption($c))
                            ->all()
                        : [];

                    return [
                        'id' => $w->id,
                        'student_enrollment_id' => $w->student_enrollment_id,
                        'subject_enrollment_id' => $w->subject_enrollment_id,
                        'student_id' => $w->student_id,
                        'student_number' => $w->student?->student_id ?? $w->student_id,
                        'student_name' => $w->student?->full_name ?? 'Unknown Student',
                        'course_code' => $w->student?->Course?->code,
                        'subject_id' => $w->subject_id,
                        'subject_code' => $w->subject?->code ?? 'Subject',
                        'subject_title' => $w->subject?->title ?? 'Untitled',
                        'position' => $w->position,
                        'status' => $w->status,
                        'notes' => $w->notes,
                        'preferred_class_id' => $w->preferred_class_id,
                        'preferred_section' => $w->preferredClass?->section,
                        'promoted_section' => $w->promotedClass?->section,
                        'promoted_at' => $w->promoted_at?->toIso8601String(),
                        'added_by' => $w->addedBy?->name ?? 'Administrator',
                        'created_at' => $w->created_at?->toIso8601String(),
                        'open_classes' => $openForSubject,
                    ];
                })
                ->values()
                ->all()
            : [];

        $waitingCount = collect($waitlists)->where('status', EnrollmentClassWaitlist::StatusWaiting)->count();
        $promotedCount = collect($waitlists)->where('status', EnrollmentClassWaitlist::StatusPromoted)->count();

        return [
            'term' => [
                'school_year' => $resolvedYear,
                'semester' => $resolvedSemester,
            ],
            'unassigned' => $unassigned->values()->all(),
            'waitlists' => $waitlists,
            'stats' => [
                'unassigned_count' => $unassigned->count(),
                'auto_assignable_count' => $autoAssignableCount,
                'all_sections_full_count' => $allFullCount,
                'waiting_count' => $waitingCount,
                'promoted_count' => $promotedCount,
            ],
        ];
    }

    public function addToWaitlist(
        SubjectEnrollment $subjectEnrollment,
        User $actor,
        ?int $preferredClassId = null,
        ?string $notes = null,
    ): EnrollmentClassWaitlist {
        $existing = EnrollmentClassWaitlist::query()
            ->waiting()
            ->where('subject_enrollment_id', $subjectEnrollment->id)
            ->first();

        if ($existing instanceof EnrollmentClassWaitlist) {
            return $existing;
        }

        $nextPosition = ((int) EnrollmentClassWaitlist::query()
            ->waiting()
            ->where('subject_id', $subjectEnrollment->subject_id)
            ->where('school_year', $subjectEnrollment->school_year)
            ->where('semester', $subjectEnrollment->semester)
            ->max('position')) + 1;

        return EnrollmentClassWaitlist::query()->create([
            'student_enrollment_id' => $subjectEnrollment->enrollment_id,
            'student_id' => $subjectEnrollment->student_id,
            'subject_enrollment_id' => $subjectEnrollment->id,
            'subject_id' => $subjectEnrollment->subject_id,
            'preferred_class_id' => $preferredClassId,
            'school_year' => $subjectEnrollment->school_year,
            'semester' => $subjectEnrollment->semester,
            'position' => $nextPosition,
            'status' => EnrollmentClassWaitlist::StatusWaiting,
            'notes' => $notes !== null && mb_trim($notes) !== '' ? mb_trim($notes) : null,
            'added_by' => $actor->id,
        ]);
    }

    public function assignClass(
        SubjectEnrollment $subjectEnrollment,
        Classes $class,
        User $actor,
        bool $forceOverload = false,
    ): void {
        DB::transaction(function () use ($subjectEnrollment, $class, $actor, $forceOverload): void {
            $enrolledCount = ClassEnrollment::query()->where('class_id', $class->id)->count();
            if (! $forceOverload && $class->maximum_slots > 0 && $enrolledCount >= $class->maximum_slots) {
                throw new RuntimeException(sprintf('Section %s (%s) is at full capacity (%d/%d).', $class->section, $class->subject_code, $enrolledCount, $class->maximum_slots));
            }

            $classEnrollment = ClassEnrollment::query()->withTrashed()->firstOrNew([
                'student_id' => $subjectEnrollment->student_id,
                'class_id' => $class->id,
            ]);
            if ($classEnrollment->trashed()) {
                $classEnrollment->restore();
            }
            $classEnrollment->status = true;
            $classEnrollment->save();

            // Update SubjectEnrollment cleanly without triggering duplicate deletion
            $subjectEnrollment->forceFill([
                'class_id' => $class->id,
                'section' => (string) $class->section,
            ])->saveQuietly();

            if (Schema::hasTable('enrollment_class_waitlists')) {
                EnrollmentClassWaitlist::query()
                    ->waiting()
                    ->where('subject_enrollment_id', $subjectEnrollment->id)
                    ->update([
                        'status' => EnrollmentClassWaitlist::StatusPromoted,
                        'promoted_by' => $actor->id,
                        'promoted_class_id' => $class->id,
                        'promoted_at' => now(),
                    ]);
            }
        });
    }

    public function promoteWaitlist(
        EnrollmentClassWaitlist $waitlist,
        User $actor,
        ?int $targetClassId = null,
        bool $forceOverload = false,
    ): EnrollmentClassWaitlist {
        if ($waitlist->status !== EnrollmentClassWaitlist::StatusWaiting) {
            throw new RuntimeException('Only waiting entries can be promoted.');
        }

        $subjectEnrollment = SubjectEnrollment::query()->findOrFail($waitlist->subject_enrollment_id);
        $classId = $targetClassId ?? $waitlist->preferred_class_id;

        if ($classId !== null) {
            $class = Classes::query()->findOrFail($classId);
        } else {
            $subject = Subject::query()->findOrFail($waitlist->subject_id);
            $termClasses = Classes::query()
                ->where('school_year', $waitlist->school_year)
                ->where('semester', $waitlist->semester)
                ->withCount('class_enrollments')
                ->get();

            $class = $this->matchingClassesForSubject($termClasses, $subject, $waitlist->student?->course_id)
                ->filter(fn (Classes $c): bool => $forceOverload || $this->hasAvailableSlot($c))
                ->sortBy('class_enrollments_count')
                ->first();

            if (! $class instanceof Classes) {
                throw new RuntimeException('No open section is currently available for this subject.');
            }
        }

        $this->assignClass($subjectEnrollment, $class, $actor, $forceOverload);

        return $waitlist->fresh(['promotedClass', 'promotedBy']);
    }

    public function cancelWaitlist(EnrollmentClassWaitlist $waitlist): EnrollmentClassWaitlist
    {
        $waitlist->forceFill(['status' => EnrollmentClassWaitlist::StatusCancelled])->save();

        return $waitlist->fresh();
    }

    /**
     * One-click bulk auto-assign across unassigned term subject enrollments.
     *
     * @param  list<int>|null  $subjectEnrollmentIds
     * @return array<string, mixed>
     */
    public function bulkAutoAssign(
        User $actor,
        ?array $subjectEnrollmentIds = null,
        bool $waitlistWhenFull = true,
        ?string $schoolYear = null,
        ?int $semester = null,
    ): array {
        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );
        $resolvedSemester = $semester ?? $this->settingsService->getCurrentSemester();

        $termClasses = Classes::query()
            ->where('school_year', $resolvedYear)
            ->where('semester', $resolvedSemester)
            ->withCount('class_enrollments')
            ->get();

        // Maintain in-memory seat counters so capacity is respected across the whole batch
        $seatCounts = $termClasses->mapWithKeys(fn (Classes $c): array => [$c->id => (int) ($c->class_enrollments_count ?? 0)])->all();

        $activePairs = ClassEnrollment::query()
            ->whereIn('class_id', $termClasses->pluck('id'))
            ->where('status', true)
            ->get(['student_id', 'class_id'])
            ->mapWithKeys(fn (ClassEnrollment $ce): array => [$ce->student_id.':'.$ce->class_id => true]);

        $query = SubjectEnrollment::query()
            ->with(['student', 'subject', 'studentEnrollment'])
            ->where('school_year', $resolvedYear)
            ->where('semester', $resolvedSemester)
            ->whereNotNull('enrollment_id')
            ->whereNotNull('subject_id')
            ->whereHas('studentEnrollment', function ($q): void {
                $q->whereNull('deleted_at')
                    ->where(function ($outcomes): void {
                        $outcomes->whereNull('terminal_outcome')
                            ->orWhereNotIn('terminal_outcome', StudentDirectoryQuery::ABANDONED_TERMINAL_OUTCOMES);
                    });
            });

        if ($subjectEnrollmentIds !== null && $subjectEnrollmentIds !== []) {
            $query->whereIn('id', $subjectEnrollmentIds);
        }

        $candidates = $query->orderBy('id')->get();

        $assignedCount = 0;
        $waitlistedCount = 0;
        $skippedCount = 0;

        foreach ($candidates as $se) {
            if (($se->class_id !== null && $activePairs->has($se->student_id.':'.$se->class_id)) || ! $se->subject instanceof Subject) {
                continue;
            }

            $courseId = $se->studentEnrollment?->course_id ?? $se->student?->course_id;
            $matching = $this->matchingClassesForSubject($termClasses, $se->subject, $courseId);

            if ($matching->isEmpty()) {
                $skippedCount++;

                continue;
            }

            $openCandidate = $matching
                ->filter(function (Classes $c) use ($seatCounts): bool {
                    $max = (int) ($c->maximum_slots ?? 0);

                    return $max <= 0 || ($seatCounts[$c->id] ?? 0) < $max;
                })
                ->sortBy(fn (Classes $c): int => $seatCounts[$c->id] ?? 0)
                ->first();

            if ($openCandidate instanceof Classes) {
                $this->assignClass($se, $openCandidate, $actor, false);
                $seatCounts[$openCandidate->id] = ($seatCounts[$openCandidate->id] ?? 0) + 1;
                $activePairs->put($se->student_id.':'.$openCandidate->id, true);
                $assignedCount++;
            } elseif ($waitlistWhenFull) {
                $preferred = $matching->first();
                $this->addToWaitlist($se, $actor, $preferred?->id, 'Auto-waitlisted: all open sections reached capacity.');
                $waitlistedCount++;
            } else {
                $skippedCount++;
            }
        }

        return [
            'assigned_count' => $assignedCount,
            'waitlisted_count' => $waitlistedCount,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * @param  Collection<int, Classes>  $termClasses
     * @return Collection<int, Classes>
     */
    private function matchingClassesForSubject(Collection $termClasses, Subject $subject, ?int $courseId): Collection
    {
        $needle = mb_strtolower(mb_trim((string) $subject->code));

        return $termClasses->filter(function (Classes $class) use ($subject, $needle, $courseId): bool {
            if ($courseId !== null && ! empty($class->course_codes)) {
                $courseStrings = array_map(strval(...), (array) $class->course_codes);
                if (! in_array((string) $courseId, $courseStrings, true)) {
                    return false;
                }
            }

            $ids = array_map(intval(...), (array) ($class->subject_ids ?? []));
            if (in_array((int) $subject->id, $ids, true)) {
                return true;
            }

            $codes = array_map(
                fn (string $c): string => mb_strtolower(mb_trim($c)),
                explode(',', (string) $class->subject_code),
            );

            return in_array($needle, $codes, true);
        })->values();
    }

    private function hasAvailableSlot(Classes $class): bool
    {
        $max = (int) ($class->maximum_slots ?? 0);

        return $max <= 0 || (int) ($class->class_enrollments_count ?? 0) < $max;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClassOption(Classes $class): array
    {
        $enrolled = (int) ($class->class_enrollments_count ?? 0);
        $max = (int) ($class->maximum_slots ?? 0);

        return [
            'id' => $class->id,
            'section' => $class->section,
            'subject_code' => $class->subject_code,
            'faculty' => $class->Faculty?->full_name ?? 'TBA',
            'enrolled_count' => $enrolled,
            'max_slots' => $max,
            'available_slots' => $max > 0 ? max(0, $max - $enrolled) : null,
            'is_full' => $max > 0 && $enrolled >= $max,
        ];
    }
}
