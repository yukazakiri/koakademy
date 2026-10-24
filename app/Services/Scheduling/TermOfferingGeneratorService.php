<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Classes;
use App\Models\StrandSubject;
use App\Models\Subject;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TermOfferingGeneratorService
{
    public function __construct(
        private SchedulingService $schedulingService,
    ) {}

    /**
     * Generate class section offerings from curriculum subjects for a cohort.
     *
     * @param  array<string, mixed>  $params
     * @return array{created: Collection<int, Classes>, skipped_count: int, subjects_found: int}
     */
    public function generateCohortOfferings(array $params): array
    {
        $classification = (string) ($params['classification'] ?? 'college');
        $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) $params['school_year']);
        $semesterRaw = $params['semester'] ?? 1;
        $numericSemester = is_numeric($semesterRaw) ? (int) $semesterRaw : 1;
        $sections = array_values(array_unique(array_filter(array_map(strval(...), (array) ($params['sections'] ?? ['A'])))));
        $maximumSlots = isset($params['maximum_slots']) ? max(1, (int) $params['maximum_slots']) : 40;
        $deliveryMode = (string) ($params['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE);
        $roomId = $deliveryMode !== Classes::DELIVERY_ONLINE && ! empty($params['room_id']) ? (int) $params['room_id'] : null;
        $facultyId = ! empty($params['faculty_id']) ? (string) $params['faculty_id'] : null;

        $compactSchoolYear = str_replace(' ', '', $schoolYear);
        $schoolYearVariants = array_values(array_unique([$schoolYear, $compactSchoolYear]));

        if ($classification === 'shs') {
            $strandId = (int) ($params['shs_strand_id'] ?? 0);
            $trackId = (int) ($params['shs_track_id'] ?? 0);
            $gradeLevel = (string) ($params['grade_level'] ?? 'Grade 11');
            $gradeYear = str_contains($gradeLevel, '12') ? 12 : 11;

            $subjects = StrandSubject::query()
                ->where('strand_id', $strandId)
                ->where('grade_year', $gradeYear)
                ->where('semester', $numericSemester)
                ->orderBy('code')
                ->get();

            if ($subjects->isEmpty()) {
                return [
                    'created' => new Collection,
                    'skipped_count' => 0,
                    'subjects_found' => 0,
                ];
            }

            $existing = Classes::query()
                ->where('classification', 'shs')
                ->whereIn('school_year', $schoolYearVariants)
                ->where('semester', $numericSemester)
                ->where('shs_strand_id', $strandId)
                ->where('grade_level', $gradeLevel)
                ->get(['id', 'subject_code', 'section']);

            return DB::transaction(function () use (
                $subjects,
                $sections,
                $existing,
                $schoolYear,
                $numericSemester,
                $trackId,
                $strandId,
                $gradeLevel,
                $maximumSlots,
                $deliveryMode,
                $roomId,
                $facultyId,
            ): array {
                $created = new Collection;
                $skippedCount = 0;

                foreach ($subjects as $subject) {
                    foreach ($sections as $section) {
                        $alreadyExists = $existing->contains(
                            fn (Classes $c): bool => (string) $c->subject_code === (string) $subject->code
                                && (string) $c->section === $section
                        );

                        if ($alreadyExists) {
                            $skippedCount++;

                            continue;
                        }

                        /** @var Classes $class */
                        $class = Classes::query()->create([
                            'classification' => 'shs',
                            'shs_track_id' => $trackId > 0 ? $trackId : $subject->strand?->track_id,
                            'shs_strand_id' => $strandId,
                            'grade_level' => $gradeLevel,
                            'subject_code' => $subject->code,
                            'school_year' => $schoolYear,
                            'semester' => $numericSemester,
                            'section' => $section,
                            'maximum_slots' => $maximumSlots,
                            'delivery_mode' => $deliveryMode,
                            'room_id' => $roomId,
                            'faculty_id' => $facultyId,
                            'settings' => Classes::getDefaultSettings(),
                        ]);

                        $created->push($class);
                    }
                }

                return [
                    'created' => $created,
                    'skipped_count' => $skippedCount,
                    'subjects_found' => $subjects->count(),
                ];
            });
        }

        // College cohort generation
        $courseId = (int) ($params['course_id'] ?? 0);
        $academicYear = (int) ($params['academic_year'] ?? 1);

        $subjects = Subject::query()
            ->where('course_id', $courseId)
            ->where('academic_year', $academicYear)
            ->where('semester', $numericSemester)
            ->orderBy('code')
            ->get();

        if ($subjects->isEmpty()) {
            return [
                'created' => collect(),
                'skipped_count' => 0,
                'subjects_found' => 0,
            ];
        }

        $existing = Classes::query()
            ->where('classification', 'college')
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $numericSemester)
            ->get(['id', 'subject_id', 'subject_ids', 'subject_code', 'section']);

        return DB::transaction(function () use (
            $subjects,
            $sections,
            $existing,
            $courseId,
            $academicYear,
            $schoolYear,
            $numericSemester,
            $maximumSlots,
            $deliveryMode,
            $roomId,
            $facultyId,
        ): array {
            $created = new Collection;
            $skippedCount = 0;

            foreach ($subjects as $subject) {
                foreach ($sections as $section) {
                    $alreadyExists = $existing->contains(function (Classes $c) use ($subject, $section): bool {
                        if ((string) $c->section !== $section) {
                            return false;
                        }

                        if ((int) $c->subject_id === (int) $subject->id) {
                            return true;
                        }

                        $ids = is_array($c->subject_ids) ? array_map(intval(...), $c->subject_ids) : [];
                        if (in_array((int) $subject->id, $ids, true)) {
                            return true;
                        }

                        return (string) $c->subject_code === (string) $subject->code;
                    });

                    if ($alreadyExists) {
                        $skippedCount++;

                        continue;
                    }

                    /** @var Classes $class */
                    $class = Classes::query()->create([
                        'classification' => 'college',
                        'course_codes' => [$courseId],
                        'subject_ids' => [$subject->id],
                        'subject_id' => $subject->id,
                        'subject_code' => $subject->code,
                        'academic_year' => $academicYear,
                        'school_year' => $schoolYear,
                        'semester' => $numericSemester,
                        'section' => $section,
                        'maximum_slots' => $maximumSlots,
                        'delivery_mode' => $deliveryMode,
                        'room_id' => $roomId,
                        'faculty_id' => $facultyId,
                        'settings' => Classes::getDefaultSettings(),
                    ]);

                    $created->push($class);
                }
            }

            return [
                'created' => $created,
                'skipped_count' => $skippedCount,
                'subjects_found' => $subjects->count(),
            ];
        });
    }

    /**
     * Clone class offerings from one term into another term.
     *
     * @return array{created: Collection<int, Classes>, skipped_count: int}
     */
    public function cloneTermOfferings(
        string $fromSchoolYear,
        int|string $fromSemester,
        string $toSchoolYear,
        int|string $toSemester,
        bool $copySchedules = true,
    ): array {
        $normalizedFromSy = GeneralSettingsService::normalizeSchoolYear($fromSchoolYear);
        $fromSyVariants = array_values(array_unique([$fromSchoolYear, $normalizedFromSy, str_replace(' ', '', $normalizedFromSy)]));
        $fromSem = is_numeric($fromSemester) ? (int) $fromSemester : 1;

        $normalizedToSy = GeneralSettingsService::normalizeSchoolYear($toSchoolYear);
        $toSyVariants = array_values(array_unique([$toSchoolYear, $normalizedToSy, str_replace(' ', '', $normalizedToSy)]));
        $toSem = is_numeric($toSemester) ? (int) $toSemester : 1;

        $sourceClasses = Classes::query()
            ->with('schedules')
            ->whereIn('school_year', $fromSyVariants)
            ->where('semester', $fromSem)
            ->get();

        $existingTarget = Classes::query()
            ->whereIn('school_year', $toSyVariants)
            ->where('semester', $toSem)
            ->get(['id', 'subject_code', 'section', 'classification']);

        return DB::transaction(function () use ($sourceClasses, $existingTarget, $normalizedToSy, $toSem, $copySchedules): array {
            $created = new Collection;
            $skippedCount = 0;

            foreach ($sourceClasses as $source) {
                $exists = $existingTarget->contains(
                    fn (Classes $c): bool => (string) $c->subject_code === (string) $source->subject_code
                        && (string) $c->section === (string) $source->section
                        && (string) $c->classification === (string) $source->classification
                );

                if ($exists) {
                    $skippedCount++;

                    continue;
                }

                $replica = $source->replicate(['school_year', 'semester']);
                $replica->school_year = $normalizedToSy;
                $replica->semester = $toSem;
                $replica->save();

                if ($copySchedules) {
                    $this->schedulingService->copyClassSchedules($source, $replica);
                }

                $created->push($replica);
            }

            return [
                'created' => $created,
                'skipped_count' => $skippedCount,
            ];
        });
    }
}
