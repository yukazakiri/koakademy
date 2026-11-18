<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\GradeRelease;
use App\Models\GradeReleaseClearance;
use App\Models\GradeReleaseEvent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Student-visible final grades are snapshots, never live draft values. */
final class GradeReleaseService
{
    /** @var array<int, Collection<string, GradeRelease>> */
    private array $releaseCache = [];

    /** @var array<int, Collection<string, GradeReleaseClearance>> */
    private array $clearanceCache = [];

    public function requiresClearance(): bool
    {
        return (bool) data_get(app(GeneralSettingsService::class)->getGlobalSettingsModel()?->more_configs, 'grade_release.require_clearance', false);
    }

    public function normalizeYear(string $year): string
    {
        if (preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $year, $matches) === 1) {
            return "{$matches[1]} - {$matches[2]}";
        }

        return GeneralSettingsService::normalizeSchoolYear($year);
    }

    /** @return list<string> */
    public function yearVariants(string $year): array
    {
        if (preg_match('/^\s*(\d{4})\s*-\s*(\d{4})\s*$/', $year, $matches) === 1) {
            return [
                "{$matches[1]} - {$matches[2]}",
                "{$matches[1]}-{$matches[2]}",
                "{$matches[1]}- {$matches[2]}",
                "{$matches[1]} -{$matches[2]}",
            ];
        }

        $year = $this->normalizeYear($year);

        return array_values(array_unique([$year, str_replace(' ', '', $year)]));
    }

    public function isCurrentPeriod(string $year, int $semester): bool
    {
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();

        return $settings !== null
            && $this->normalizeYear($year) === $this->normalizeYear($settings->getSchoolYearString())
            && $semester === (int) $settings->semester;
    }

    public function clearanceApplies(string $year, int $semester): bool
    {
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        if (! $settings) {
            return true;
        }

        $year = $this->normalizeYear($year);
        $currentYear = $this->normalizeYear($settings->getSchoolYearString());
        if (preg_match('/^\d{4} - \d{4}$/', $year) !== 1 || $semester < 1 || $semester > 3
            || preg_match('/^\d{4} - \d{4}$/', $currentYear) !== 1 || (int) $settings->semester < 1 || (int) $settings->semester > 3) {
            return true;
        }

        return $year > $currentYear || ($year === $currentYear && $semester >= (int) $settings->semester);
    }

    /** @return array{subjects: array<string, array<string, mixed>>, classes: array<string, array<string, mixed>>} */
    public function snapshot(Student $student, string $year, int $semester, bool $lock = false): array
    {
        $years = $this->yearVariants($year);
        $subjects = SubjectEnrollment::withoutSchoolScope()->where('student_id', $student->id)
            ->whereIn('school_year', $years)->where('semester', $semester)->orderBy('id');
        $classes = ClassEnrollment::withoutSchoolScope()->where('student_id', $student->id)
            ->whereHas('class', fn ($query) => $query->withoutSchoolScope()->whereIn('school_year', $years)->where('semester', $semester))
            ->orderBy('id');

        if ($lock) {
            $subjects->lockForUpdate();
            $classes->lockForUpdate();
        }

        $subjectRows = $subjects->get();
        $classRows = $classes->get();
        $classById = $classRows->keyBy('class_id');

        return [
            'subjects' => $subjectRows->mapWithKeys(function (SubjectEnrollment $row) use ($classById): array {
                $matchingClass = $row->class_id ? $classById->get($row->class_id) : null;
                $useClassResult = $row->grade === null && $row->grade_symbol === null && $matchingClass !== null;

                return [(string) $row->id => [
                    'grade' => $useClassResult ? $matchingClass->total_average : $row->grade,
                    'grade_symbol' => $useClassResult ? $matchingClass->grade_symbol : $row->grade_symbol,
                    'grade_outcome' => $useClassResult ? $matchingClass->grade_outcome : $row->grade_outcome,
                    'grade_quality_points' => $useClassResult ? $matchingClass->grade_quality_points : $row->grade_quality_points,
                    'remarks' => $row->remarks,
                    'is_credited' => (bool) $row->is_credited,
                ]];
            })->all(),
            'classes' => $classRows->mapWithKeys(fn (ClassEnrollment $row): array => [(string) $row->id => [
                'finals_grade' => $row->finals_grade,
                'total_average' => $row->total_average,
                'grade_symbol' => $row->grade_symbol,
                'grade_outcome' => $row->grade_outcome,
                'grade_quality_points' => $row->grade_quality_points,
                'grading_components' => $row->grading_components,
            ]])->all(),
        ];
    }

    /** @param array<string, array<string, array<string, mixed>>> $snapshot */
    public function missingCount(array $snapshot): int
    {
        return collect($snapshot['subjects'])->filter(fn (array $grade): bool => $grade['grade'] === null && $grade['grade_symbol'] === null)->count()
            + collect($snapshot['classes'])->filter(fn (array $grade): bool => $grade['total_average'] === null && $grade['grade_symbol'] === null)->count();
    }

    /** @return array{release: GradeRelease, changed: bool} */
    public function publish(Student $student, string $year, int $semester, User $actor, ?string $correctionReason = null): array
    {
        $year = $this->normalizeYear($year);

        return DB::transaction(function () use ($student, $year, $semester, $actor, $correctionReason): array {
            Student::withoutSchoolScope()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($student, $year, $semester, true);
            $existing = GradeRelease::query()->where('student_id', $student->id)->whereIn('school_year', $this->yearVariants($year))
                ->where('semester', $semester)->orderByDesc('updated_at')->orderByDesc('id')->lockForUpdate()->first();

            if ($existing && $existing->published_by !== null && $this->canonical($existing->snapshot) === $this->canonical($snapshot)) {
                return ['release' => $existing, 'changed' => false];
            }

            if (count($snapshot['subjects']) + count($snapshot['classes']) === 0) {
                throw ValidationException::withMessages(['period' => 'There are no enrolled subjects or classes for this semester.']);
            }

            if ($this->missingCount($snapshot) > 0) {
                throw ValidationException::withMessages(['period' => 'Enter a final result for every enrolled subject and class before publishing.']);
            }

            if ($existing?->published_by !== null && blank($correctionReason)) {
                throw ValidationException::withMessages(['correction_reason' => 'Explain why the published grades are being changed.']);
            }

            $release = $existing ?? new GradeRelease(['student_id' => $student->id, 'school_year' => $year, 'semester' => $semester]);
            $notificationState = $this->canViewPeriod($student, $year, $semester) ? 'pending' : 'deferred';
            $release->fill([
                'snapshot' => $snapshot,
                'revision' => $existing && $existing->published_by !== null ? $existing->revision + 1 : 1,
                'published_by' => $actor->id,
                'published_at' => now(),
                'notification_status' => ['mail' => $notificationState, 'database' => $notificationState, 'warnings' => []],
            ])->save();

            GradeReleaseEvent::query()->create([
                'student_id' => $student->id, 'school_year' => $year, 'semester' => $semester,
                'event' => 'published', 'actor_id' => $actor->id,
                'details' => ['revision' => $release->revision, 'correction_reason' => $correctionReason, 'snapshot' => $snapshot],
            ]);

            unset($this->releaseCache[$student->id]);

            return ['release' => $release, 'changed' => true];
        });
    }

    public function updateClearance(Student $student, string $year, int $semester, bool $cleared, ?string $remarks, User $actor): GradeReleaseClearance
    {
        $year = $this->normalizeYear($year);

        return DB::transaction(function () use ($student, $year, $semester, $cleared, $remarks, $actor): GradeReleaseClearance {
            Student::withoutSchoolScope()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $clearance = GradeReleaseClearance::query()->where('student_id', $student->id)
                ->whereIn('school_year', $this->yearVariants($year))->where('semester', $semester)
                ->orderByDesc('updated_at')->orderByDesc('id')->lockForUpdate()->first()
                ?? new GradeReleaseClearance(['student_id' => $student->id, 'school_year' => $year, 'semester' => $semester]);
            $clearance->fill([
                'is_cleared' => $cleared, 'remarks' => $remarks,
                'cleared_by' => $cleared ? $actor->id : null, 'cleared_at' => $cleared ? now() : null,
                'updated_by' => $actor->id,
            ])->save();
            GradeReleaseEvent::query()->create([
                'student_id' => $student->id, 'school_year' => $year, 'semester' => $semester,
                'event' => $cleared ? 'clearance_granted' : 'clearance_revoked', 'actor_id' => $actor->id,
                'details' => ['remarks' => $remarks],
            ]);

            unset($this->clearanceCache[$student->id]);

            return $clearance;
        });
    }

    /** @return Collection<string, GradeRelease> */
    public function releases(Student $student): Collection
    {
        return $this->releaseCache[$student->id] ??= GradeRelease::query()->where('student_id', $student->id)->get()
            ->sortBy(fn (GradeRelease $row): string => ($row->updated_at?->format('Y-m-d H:i:s.u') ?? '').sprintf('%020d', $row->id))
            ->keyBy(fn (GradeRelease $release): string => $this->key($release->school_year, $release->semester));
    }

    /** @return list<array<string, mixed>> */
    public function periods(Student $student): array
    {
        $subjectLabels = SubjectEnrollment::withoutSchoolScope()->where('student_id', $student->id)->with(['subject' => fn ($query) => $query->withoutSchoolScope()])->get()
            ->mapWithKeys(fn (SubjectEnrollment $row): array => [(string) $row->id => $row->subject?->code ?? $row->external_subject_code ?? 'Subject #'.$row->id]);
        $classLabels = ClassEnrollment::withoutSchoolScope()->where('student_id', $student->id)->with(['class' => fn ($query) => $query->withoutSchoolScope()])->get()
            ->mapWithKeys(fn (ClassEnrollment $row): array => [(string) $row->id => $row->class?->subject_code ?? 'Class #'.$row->class_id]);
        $periods = [];
        $add = function (?string $year, int|string|null $semester) use (&$periods): void {
            if (! $year || preg_match('/^\s*\d{4}\s*-\s*\d{4}\s*$/', $year) !== 1 || (int) $semester < 1 || (int) $semester > 3) {
                return;
            }
            $year = $this->normalizeYear($year);
            $periods[$this->key($year, $semester)] = ['school_year' => $year, 'semester' => (int) $semester];
        };

        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        $add($settings?->getSchoolYearString(), $settings?->semester);
        StudentEnrollment::withoutSchoolScope()->where('student_id', $student->id)->get(['school_year', 'semester'])->each(fn ($row) => $add($row->school_year, $row->semester));
        SubjectEnrollment::withoutSchoolScope()->where('student_id', $student->id)->get(['school_year', 'semester'])->each(fn ($row) => $add($row->school_year, $row->semester));
        ClassEnrollment::withoutSchoolScope()->where('student_id', $student->id)->with(['class' => fn ($query) => $query->withoutSchoolScope()->select('id', 'school_year', 'semester')])->get()->each(fn ($row) => $add($row->class?->school_year, $row->class?->semester));
        $clearances = GradeReleaseClearance::query()->where('student_id', $student->id)->with('clearedBy:id,name')->get()->keyBy(fn ($row): string => $this->key($row->school_year, $row->semester));
        $releases = $this->releases($student);
        $clearances->each(fn ($row) => $add($row->school_year, $row->semester));
        $releases->each(fn ($row) => $add($row->school_year, $row->semester));
        krsort($periods);
        $eventsByPeriod = GradeReleaseEvent::query()->where('student_id', $student->id)->orderByDesc('id')->get()
            ->groupBy(fn (GradeReleaseEvent $event): string => $this->key($event->school_year, $event->semester));
        $actors = User::query()->whereIn('id', $eventsByPeriod->flatten()->pluck('actor_id')->filter()->unique())->pluck('name', 'id');

        return collect($periods)->map(function (array $period, string $key) use ($student, $clearances, $releases, $eventsByPeriod, $actors, $subjectLabels, $classLabels): array {
            $clearance = $clearances->get($key);
            $release = $releases->get($key);
            $snapshot = $this->snapshot($student, $period['school_year'], $period['semester']);
            $total = count($snapshot['subjects']) + count($snapshot['classes']);
            $missing = $this->missingCount($snapshot);
            $missingItems = collect($snapshot['subjects'])->filter(fn (array $grade): bool => $grade['grade'] === null && $grade['grade_symbol'] === null)
                ->map(fn (array $grade, int|string $id): string => 'Subject: '.$subjectLabels->get($id, '#'.$id))
                ->concat(collect($snapshot['classes'])->filter(fn (array $grade): bool => $grade['total_average'] === null && $grade['grade_symbol'] === null)
                    ->map(fn (array $grade, int|string $id): string => 'Class: '.$classLabels->get($id, '#'.$id)))
                ->values()->all();
            $events = $eventsByPeriod->get($key, collect())->take(10);

            return [
                ...$period,
                'is_current' => $this->isCurrentPeriod($period['school_year'], $period['semester']),
                'clearance_required' => $this->requiresClearance() && $this->clearanceApplies($period['school_year'], $period['semester']),
                'clearance' => $clearance ? [
                    'is_cleared' => $clearance->is_cleared, 'remarks' => $clearance->remarks,
                    'cleared_by' => $clearance->clearedBy?->name, 'cleared_at' => $clearance->cleared_at?->toIso8601String(),
                ] : null,
                'total' => $total, 'missing' => $missing, 'missing_items' => $missingItems,
                'published_at' => $release?->published_at?->toIso8601String(),
                'revision' => $release?->revision,
                'needs_correction_reason' => $release !== null && $release->published_by !== null,
                'has_changes' => $release ? $release->published_by === null || $this->canonical($release->snapshot) !== $this->canonical($snapshot) : false,
                'notification_status' => $release?->notification_status,
                'events' => $events->map(fn (GradeReleaseEvent $event): array => [
                    'id' => $event->id, 'event' => $event->event, 'actor' => $actors->get($event->actor_id),
                    'at' => $event->created_at?->toIso8601String(),
                    'revision' => $event->details['revision'] ?? null,
                    'reason' => $event->details['correction_reason'] ?? $event->details['remarks'] ?? null,
                ])->all(),
            ];
        })->values()->all();
    }

    public function isClearedForPeriod(Student $student, string $year, int $semester): ?bool
    {
        $normalized = $this->normalizeYear($year);
        if (preg_match('/^\d{4} - \d{4}$/', $normalized) !== 1 || $semester < 1 || $semester > 3) {
            return null;
        }

        $clearance = $this->clearanceCache[$student->id] ??= GradeReleaseClearance::query()
            ->where('student_id', $student->id)->get()
            ->sortBy(fn (GradeReleaseClearance $row): string => ($row->updated_at?->format('Y-m-d H:i:s.u') ?? '').sprintf('%020d', $row->id))
            ->keyBy(fn (GradeReleaseClearance $row): string => $this->key($row->school_year, $row->semester));

        return $clearance->get($this->key($normalized, $semester))?->is_cleared;
    }

    public function canViewPeriod(Student $student, ?string $year, int|string|null $semester): bool
    {
        if (! $this->requiresClearance()) {
            return true;
        }

        $normalized = $this->normalizeYear((string) $year);
        $term = (int) $semester;
        if (preg_match('/^\d{4} - \d{4}$/', $normalized) !== 1 || $term < 1 || $term > 3) {
            return false;
        }

        return $this->isClearedForPeriod($student, $normalized, $term) === true
            || ! $this->clearanceApplies($normalized, $term);
    }

    public function canViewPeriodFresh(Student $student, ?string $year, int|string|null $semester): bool
    {
        unset($this->clearanceCache[$student->id]);

        return $this->canViewPeriod($student, $year, $semester);
    }

    public function key(?string $year, int|string|null $semester): string
    {
        return $this->normalizeYear((string) $year).'|'.(int) $semester;
    }

    /** @param Collection<string, GradeRelease> $releases @return array<string, mixed>|null */
    public function publishedSubject(SubjectEnrollment $enrollment, Collection $releases, ?Student $student = null): ?array
    {
        $student ??= $enrollment->relationLoaded('student') ? $enrollment->student : Student::withoutSchoolScope()->find($enrollment->student_id);
        if (! $student instanceof Student || (int) $student->id !== (int) $enrollment->student_id || ! $this->canViewPeriod($student, $enrollment->school_year, $enrollment->semester)) {
            return null;
        }

        $release = $releases->get($this->key($enrollment->school_year, $enrollment->semester));

        $published = $release?->snapshot['subjects'][(string) $enrollment->id] ?? null;
        if ($published === null) {
            return null;
        }

        return $published;
    }

    /**
     * Return an in-memory copy containing only the student's posted, visible
     * result. Never persist this copy; staff calculations still use live data.
     *
     * @param  Collection<string, GradeRelease>  $releases
     */
    public function studentVisibleSubject(SubjectEnrollment $enrollment, Collection $releases, Student $student): SubjectEnrollment
    {
        $visible = $this->publishedSubject($enrollment, $releases, $student);
        $copy = $enrollment->replicate();
        $copy->id = $enrollment->id;
        $copy->grade = $visible['grade'] ?? null;
        $copy->grade_symbol = $visible['grade_symbol'] ?? null;
        $copy->grade_outcome = $visible['grade_outcome'] ?? null;
        $copy->grade_quality_points = $visible['grade_quality_points'] ?? null;
        $copy->remarks = $visible['remarks'] ?? null;
        $copy->is_credited = $visible['is_credited'] ?? false;

        return $copy;
    }

    /** @param Collection<string, GradeRelease> $releases @return array<string, mixed>|null */
    public function publishedClass(ClassEnrollment $enrollment, Collection $releases, ?Student $student = null): ?array
    {
        $class = $enrollment->relationLoaded('class')
            ? $enrollment->class
            : \App\Models\Classes::withoutSchoolScope()->find($enrollment->class_id);
        if (! $class) {
            return null;
        }
        $student ??= $enrollment->relationLoaded('student') ? $enrollment->student : Student::withoutSchoolScope()->find($enrollment->student_id);
        if (! $student instanceof Student || (int) $student->id !== (int) $enrollment->student_id || ! $this->canViewPeriod($student, $class->school_year, $class->semester)) {
            return null;
        }
        $release = $releases->get($this->key($class->school_year, $class->semester));

        $published = $release?->snapshot['classes'][(string) $enrollment->id] ?? null;
        if ($published === null) {
            return null;
        }

        return $published;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function canonical(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
