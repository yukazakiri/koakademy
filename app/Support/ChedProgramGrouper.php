<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * Collapses per-curriculum course records into one CHED curricular program.
 *
 * A school stores one `courses` row per curriculum implementation, so a single
 * program is spread across rows such as "BSBA (2018 - 2019) ABM",
 * "BSBA (2018 - 2019) NON-ABM" and "BSBA (2024 - 2025) NON-ABM". All of them
 * carry the same title and department, which produced one report line per row.
 * CHED Form B/C wants one line per curricular program, so this grouper folds
 * them together and the report sums their enrolments and graduates.
 */
final class ChedProgramGrouper
{
    /**
     * Curriculum-year range, e.g. "(2018 - 2019)" or "(2024-2025)".
     */
    private const string CURRICULUM_YEAR_PATTERN = '/\(\s*\d{4}\s*[-–—]\s*\d{4}\s*\)/u';

    /**
     * Senior high school track suffixes appended after the year, e.g. "NON-ABM",
     * "ABM" or "WEB". Stripped so "BSIT (2024 - 2025) WEB" reads as "BSIT".
     */
    private const string TRACK_SUFFIX_PATTERN = '/\s+(?:NON[\s-]?ABM|ABM|WEB|MOBILE|STEM|HUMSS|GAS|ICT|TVL|TLE|APAR|SPAN)\s*$/iu';

    /**
     * Group active course records into curricular programs.
     *
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, ChedProgramGroup>
     */
    public function group(Collection $courses): Collection
    {
        // groupBy keeps first-seen order, so the report stays in the same
        // course-code sequence the ungrouped listing used.
        return $courses
            ->groupBy(fn (Course $course): string => $this->groupKey($course))
            ->map(fn (Collection $members, string $key): ChedProgramGroup => $this->buildGroup($key, $members))
            ->values();
    }

    /**
     * Wrap a single course as its own program, used when merging is switched
     * off so every curriculum row is reported separately again.
     */
    public function singleProgram(Course $course): ChedProgramGroup
    {
        $code = $this->baseProgramCode($course);

        return new ChedProgramGroup(
            key: 'course:'.$course->id,
            title: (string) $course->title,
            programCode: $code !== '' ? $code : (string) $course->code,
            representative: $course,
            courses: new Collection([$course]),
            courseIds: [(int) $course->id],
        );
    }

    /**
     * Programs are identified by department plus the program title with the
     * curriculum year removed, so a school's year-to-year course rows for the
     * same program resolve to one key.
     */
    private function groupKey(Course $course): string
    {
        $departmentId = (int) ($course->department_id ?? 0);
        $title = $this->normaliseTitle((string) $course->title);

        return $departmentId.'|'.($title !== '' ? $title : mb_strtolower(mb_trim((string) $course->code)));
    }

    /**
     * @param  Collection<int, Course>  $members
     */
    private function buildGroup(string $key, Collection $members): ChedProgramGroup
    {
        $ordered = $members
            ->sortByDesc(fn (Course $course): int => $this->curriculumYearScore($course))
            ->sortByDesc(fn (Course $course): int => $this->chedMetadataScore($course))
            ->values();

        /** @var Course $representative */
        $representative = $ordered->first();

        return new ChedProgramGroup(
            key: $key,
            title: (string) $representative->title,
            programCode: $this->baseProgramCode($representative),
            representative: $representative,
            courses: $ordered,
            courseIds: $ordered
                ->map(static fn (Course $course): int => (int) $course->id)
                ->unique()
                ->values()
                ->all(),
        );
    }

    /**
     * The program code shown for the group: the authority or CHED program code
     * when one is set, otherwise the local course code with the curriculum year
     * and track suffix removed.
     */
    private function baseProgramCode(Course $course): string
    {
        $authoritative = $course->officialChedProgramCode();

        $stripped = (string) preg_replace(self::CURRICULUM_YEAR_PATTERN, '', $authoritative);
        $stripped = (string) preg_replace(self::TRACK_SUFFIX_PATTERN, '', $stripped);
        $stripped = mb_trim((string) preg_replace('/\s+/', ' ', $stripped));

        return $stripped !== '' ? $stripped : mb_trim($authoritative);
    }

    /**
     * Normalise the title so cosmetic differences between curriculum rows do
     * not split one program across several groups.
     */
    private function normaliseTitle(string $title): string
    {
        $collapsed = mb_trim((string) preg_replace('/\s+/', ' ', $title));

        return mb_strtolower((string) preg_replace('/[^a-z0-9 ]/i', '', $collapsed));
    }

    /**
     * Newer curricula win so the reported program profile matches what the
     * school currently offers.
     *
     * `curriculum_year` is the year the curriculum itself was implemented, so it
     * is the primary signal. The weaker fields are only consulted when it is
     * blank; reading all of them together would let an older curriculum that is
     * merely being offered this school year outrank a newer one.
     */
    private function curriculumYearScore(Course $course): int
    {
        foreach ([$course->curriculum_year, $course->ched_year_implemented, $course->school_year] as $candidate) {
            if (filled($candidate) && preg_match_all('/\d{4}/', (string) $candidate, $matches) > 0) {
                return max(array_map(static fn (string $year): int => (int) $year, $matches[0]));
            }
        }

        return 0;
    }

    /**
     * Break ties between rows of the same curriculum year by preferring the
     * record carrying the most complete CHED profile.
     */
    private function chedMetadataScore(Course $course): int
    {
        $fields = [
            'ched_major',
            'ched_major_code',
            'ched_program_status',
            'ched_year_implemented',
            'ched_authority_category',
            'ched_authority_serial',
            'ched_authority_year',
            'ched_delivery_mode',
            'ched_normal_length_years',
            'ched_program_credit_units',
        ];

        $score = 0;
        foreach ($fields as $field) {
            if (filled($course->{$field})) {
                $score++;
            }
        }

        return $course->ched_has_thesis ? $score + 1 : $score;
    }
}
