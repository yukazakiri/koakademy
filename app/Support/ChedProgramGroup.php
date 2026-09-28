<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * One CHED curricular program assembled from every course record that shares a
 * program title inside a department.
 *
 * Schools keep a separate course row per curriculum implementation, for example
 * "BSBA (2018 - 2019) ABM" and "BSBA (2024 - 2025) NON-ABM" both titled
 * "Bachelor of Science in Business Administration". CHED Form B/C expects one
 * line per curricular program, so those rows collapse into a single group whose
 * enrolment and graduate counts are summed.
 *
 * @see ChedProgramGrouper
 */
final class ChedProgramGroup
{
    /**
     * @param  Collection<int, Course>  $courses  Member records, most current first.
     * @param  list<int>  $courseIds
     */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $programCode,
        public readonly Course $representative,
        public readonly Collection $courses,
        public readonly array $courseIds,
    ) {}

    public function departmentId(): int
    {
        return (int) ($this->representative->department_id ?? 0);
    }

    public function isSingleCourse(): bool
    {
        return count($this->courseIds) === 1;
    }
}
