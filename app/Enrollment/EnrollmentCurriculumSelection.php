<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Enrollment\Rules\EnrollmentPrerequisiteRule;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final readonly class EnrollmentCurriculumSelection
{
    public function __construct(private EnrollmentPrerequisiteRule $prerequisites) {}

    /** @return Collection<int, Subject> */
    public function eligibleBackSubjects(StudentEnrollment $enrollment): Collection
    {
        if (! $enrollment->exists || $enrollment->school_id === null || $enrollment->course_id === null) {
            throw new InvalidArgumentException('Eligible back-subject selection requires a saved enrollment with school and program.');
        }
        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', mb_trim((string) $enrollment->school_year), $years) !== 1
            || (int) $years[2] !== (int) $years[1] + 1 || (int) $enrollment->semester < 1 || (int) $enrollment->academic_year < 1) {
            throw new InvalidArgumentException('Eligible back-subject selection requires a valid academic period and year level.');
        }
        $subjects = Subject::query()->where('course_id', $enrollment->course_id)
            ->whereHas('course', fn ($query) => $query->where('school_id', $enrollment->school_id))
            ->where('academic_year', '>=', 1)->where('academic_year', '<=', $enrollment->academic_year)
            ->where('semester', $enrollment->semester)->orderBy('academic_year')->orderBy('code')->orderBy('id')->get();
        $ids = $subjects->modelKeys();
        $requirements = [];
        foreach ($subjects as $subject) {
            $prerequisites = $subject->pre_riquisite ?? [];
            if (! is_array($prerequisites)) {
                $requirements[$subject->id] = null;

                continue;
            }
            $valid = [];
            foreach ($prerequisites as $id) {
                if (! (is_int($id) || (is_string($id) && ctype_digit($id))) || (int) $id <= 0 || (int) $id === (int) $subject->id) {
                    $valid = null;
                    break;
                }
                $valid[] = (int) $id;
            }
            $requirements[$subject->id] = $valid;
            $ids = [...$ids, ...($valid ?? [])];
        }
        $ids = array_values(array_unique($ids));
        $completed = $this->prerequisites->completedSubjectIds($enrollment, $ids);
        $validPrerequisites = Subject::query()->whereIn('id', $ids)
            ->whereHas('course', fn ($query) => $query->where('school_id', $enrollment->school_id))->pluck('id')->all();

        return $subjects->filter(static function (Subject $subject) use ($requirements, $completed, $validPrerequisites): bool {
            $required = $requirements[$subject->id];

            return ! in_array($subject->id, $completed, true) && $required !== null
                && array_diff($required, $validPrerequisites) === [] && array_diff($required, $completed) === [];
        })->values();
    }
}
