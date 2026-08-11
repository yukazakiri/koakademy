<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Classes;
use App\Models\Subject;
use App\Services\ClassCurriculumPlacementService;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

final class AlignClassAcademicYears extends Command
{
    protected $signature = 'classes:align-academic-years
        {--school-id= : School ID to inspect}
        {--school-year= : School year, for example "2026 - 2027"}
        {--semester= : Semester number}
        {--apply : Persist unanimous curriculum-year corrections}
        {--report= : Optional JSON audit report path}';

    protected $description = 'Align class fallback years when all linked curriculum subjects agree';

    public function handle(
        ClassCurriculumPlacementService $curriculumPlacement,
        Filesystem $files,
    ): int {
        $schoolId = filter_var($this->option('school-id'), FILTER_VALIDATE_INT);
        $schoolYear = mb_trim((string) $this->option('school-year'));
        $semester = filter_var($this->option('semester'), FILTER_VALIDATE_INT);
        $apply = (bool) $this->option('apply');

        if (! is_int($schoolId) || $schoolId <= 0 || $schoolYear === '' || ! is_int($semester) || $semester <= 0) {
            $this->components->error('Provide a positive --school-id, --semester, and a non-empty --school-year.');

            return self::INVALID;
        }

        $classes = Classes::withoutSchoolScope()
            ->forSchool($schoolId)
            ->forAcademicPeriod($schoolYear, $semester)
            ->college()
            ->with(['subjects', 'Subject'])
            ->orderBy('id')
            ->get();

        $updates = [];
        $mixed = [];
        $unresolved = [];
        $alignedCount = 0;

        foreach ($classes as $class) {
            $subjects = $curriculumPlacement->subjectsForClass($class);
            $subjectYears = $subjects
                ->map(fn (Subject $subject): int => (int) $subject->academic_year);
            $hasUnresolvedSubject = $subjects->isEmpty()
                || $subjectYears->contains(fn (int $year): bool => $year < 1 || $year > 4);
            $years = $subjectYears
                ->filter(fn (int $year): bool => $year >= 1 && $year <= 4)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $details = $this->classDetails($class, $subjects->all(), $years);

            if ($hasUnresolvedSubject || $years === []) {
                $unresolved[] = $details;

                continue;
            }

            if (count($years) > 1) {
                $mixed[] = $details;

                continue;
            }

            $targetYear = $years[0];

            if ((int) $class->academic_year === $targetYear) {
                $alignedCount++;

                continue;
            }

            $updates[] = [
                ...$details,
                'old_academic_year' => $class->academic_year ? (int) $class->academic_year : null,
                'new_academic_year' => $targetYear,
            ];
        }

        $appliedCount = 0;
        $stale = [];

        try {
            $this->prepareReportDestination($files);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('The audit report destination is not writable: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($apply && $updates !== []) {
            DB::transaction(function () use (
                $updates,
                $schoolId,
                $schoolYear,
                $semester,
                $curriculumPlacement,
                &$appliedCount,
                &$stale,
            ): void {
                $lockedClasses = [];

                foreach ($updates as $update) {
                    $class = Classes::withoutSchoolScope()
                        ->where('school_id', $schoolId)
                        ->forAcademicPeriod($schoolYear, $semester)
                        ->college()
                        ->lockForUpdate()
                        ->find($update['class_id']);

                    if (! $class instanceof Classes) {
                        $stale[] = ['class_id' => $update['class_id'], 'reason' => 'Class no longer matches the requested school and academic period.'];

                        continue;
                    }

                    $years = $curriculumPlacement->yearsForClass($class);

                    if (
                        $years !== [$update['new_academic_year']]
                        || (int) $class->academic_year !== (int) $update['old_academic_year']
                    ) {
                        $stale[] = ['class_id' => $class->id, 'reason' => 'Curriculum placement changed during execution.'];

                        continue;
                    }

                    $lockedClasses[] = [$class, $update['new_academic_year']];
                }

                if ($stale !== []) {
                    return;
                }

                foreach ($lockedClasses as [$class, $newAcademicYear]) {
                    $class->forceFill(['academic_year' => $newAcademicYear])->save();
                    $appliedCount++;
                }
            });
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'mode' => $apply ? 'apply' : 'dry-run',
            'filters' => [
                'school_id' => $schoolId,
                'school_year' => $schoolYear,
                'semester' => $semester,
            ],
            'summary' => [
                'classes_scanned' => $classes->count(),
                'safe_updates' => count($updates),
                'mixed_year_skips' => count($mixed),
                'unresolved_skips' => count($unresolved),
                'already_aligned' => $alignedCount,
                'applied' => $appliedCount,
                'stale_skips' => count($stale),
            ],
            'updates' => $updates,
            'mixed_year_classes' => $mixed,
            'unresolved_classes' => $unresolved,
            'stale_classes' => $stale,
        ];

        try {
            $this->writeReport($files, $report);
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error('The audit report could not be written: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Mode: '.($apply ? 'APPLY' : 'DRY RUN'));
        $this->line('Classes scanned: '.$classes->count());
        $this->line('Safe updates: '.count($updates));
        $this->line('Mixed-year skips: '.count($mixed));
        $this->line('Unresolved skips: '.count($unresolved));
        $this->line('Already aligned: '.$alignedCount);
        $this->line('Applied: '.$appliedCount);

        if ($stale !== []) {
            $this->components->error('Some candidates changed during apply; no class years were updated.');

            return self::FAILURE;
        }

        if (! $apply && $updates !== []) {
            $this->components->warn('Dry run only. Re-run with --apply after reviewing the report.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<Subject>  $subjects
     * @param  list<int>  $years
     * @return array<string, mixed>
     */
    private function classDetails(Classes $class, array $subjects, array $years): array
    {
        return [
            'class_id' => (int) $class->id,
            'subject_code' => (string) $class->subject_code,
            'section' => (string) $class->section,
            'current_academic_year' => $class->academic_year ? (int) $class->academic_year : null,
            'course_codes' => array_values(is_array($class->course_codes) ? $class->course_codes : []),
            'subject_years' => $years,
            'subjects' => array_map(fn (Subject $subject): array => [
                'id' => (int) $subject->id,
                'code' => (string) $subject->code,
                'course_id' => $subject->course_id ? (int) $subject->course_id : null,
                'academic_year' => $subject->academic_year ? (int) $subject->academic_year : null,
            ], $subjects),
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     *
     * @throws JsonException
     */
    private function writeReport(Filesystem $files, array $report): void
    {
        $path = mb_trim((string) $this->option('report'));

        if ($path === '') {
            return;
        }

        $files->replace(
            $path,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
            0640,
        );

        $this->line('Report: '.$path);
    }

    private function prepareReportDestination(Filesystem $files): void
    {
        $path = mb_trim((string) $this->option('report'));

        if ($path === '') {
            return;
        }

        $directory = dirname($path);

        if (! $files->isDirectory($directory) && ! $files->makeDirectory($directory, 0750, true)) {
            throw new RuntimeException("Could not create {$directory}.");
        }

        if (! $files->isWritable($directory)) {
            throw new RuntimeException("Directory {$directory} is not writable.");
        }

        if ($files->exists($path) && ! $files->isWritable($path)) {
            throw new RuntimeException("File {$path} is not writable.");
        }
    }
}
