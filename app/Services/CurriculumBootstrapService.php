<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurriculumFramework;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Support\PhilippineCurriculumCatalog;
use Illuminate\Support\Collection;

/**
 * Creates the curriculum structure selected during setup.
 *
 * Depending on the chosen framework this seeds:
 * - CHED PSG: departments + degree/diploma/associate course records.
 * - TESDA TR: sector departments + qualification course records (NC I-IV,
 *   PQF levels, diploma programs).
 * - DepEd SHS: tracks + strands, optionally with the standard core subjects.
 * - DepEd MATATAG: nothing to create (the framework + reference are stored on
 *   the school record).
 *
 * All writes are idempotent (firstOrCreate).
 */
final class CurriculumBootstrapService
{
    /**
     * The standard 15 core subjects plus applied/track-independent subjects
     * of the K to 12 Senior High School curriculum.
     *
     * @var array<int, array{title: string, grade_year: int, semester: int}>
     */
    private const array SHS_CORE_SUBJECTS = [
        ['title' => 'Oral Communication', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Reading and Writing', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Komunikasyon at Pananaliksik sa Wika at Kulturang Pilipino', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Pagbasa at Pagsusuri ng Iba\'t-Ibang Teksto Tungo sa Pananaliksik', 'grade_year' => 11, 'semester' => 2],
        ['title' => '21st Century Literature from the Philippines and the World', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Contemporary Philippine Arts from the Regions', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Media and Information Literacy', 'grade_year' => 12, 'semester' => 2],
        ['title' => 'General Mathematics', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Statistics and Probability', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Earth and Life Science', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Physical Science', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Personal Development', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Understanding Culture, Society and Politics', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Introduction to the Philosophy of the Human Person', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Physical Education and Health 1', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Physical Education and Health 2', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Physical Education and Health 3', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Physical Education and Health 4', 'grade_year' => 12, 'semester' => 2],
        ['title' => 'English for Academic and Professional Purposes', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Practical Research 1', 'grade_year' => 11, 'semester' => 2],
        ['title' => 'Practical Research 2', 'grade_year' => 12, 'semester' => 1],
        ['title' => 'Filipino sa Piling Larang (Akademik)', 'grade_year' => 12, 'semester' => 2],
        ['title' => 'Empowerment Technologies', 'grade_year' => 11, 'semester' => 1],
        ['title' => 'Inquiries, Investigations and Immersion', 'grade_year' => 12, 'semester' => 2],
    ];

    /**
     * @param  list<string>  $programCodes
     * @return array{departments: int, courses: int, tracks: int, strands: int, strand_subjects: int}
     */
    public function bootstrap(
        School $school,
        CurriculumFramework $framework,
        array $programCodes = [],
        ?string $curriculumYear = null,
        bool $seedStrandSubjects = false,
    ): array {
        return match ($framework) {
            CurriculumFramework::ChedPsg => $this->bootstrapHigherEducation($school, $programCodes, $curriculumYear),
            CurriculumFramework::TesdaTr => $this->bootstrapTesda($school, $programCodes, $curriculumYear),
            CurriculumFramework::DepedShsK12 => $this->bootstrapShs($school, PhilippineCurriculumCatalog::shsTracksLegacy(), $programCodes, $seedStrandSubjects),
            CurriculumFramework::DepedShsRevised => $this->bootstrapShs($school, PhilippineCurriculumCatalog::shsTracksRevised(), $programCodes, $seedStrandSubjects),
            CurriculumFramework::DepedMatatag => $this->summary(),
        };
    }

    /**
     * Coerce a catalog value into a string without static-analysis complaints.
     */
    private static function str(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  list<string>  $programCodes
     * @return array{departments: int, courses: int, tracks: int, strands: int, strand_subjects: int}
     */
    private function bootstrapHigherEducation(School $school, array $programCodes, ?string $curriculumYear): array
    {
        $catalog = collect(PhilippineCurriculumCatalog::chedClusters())
            ->flatMap(fn (array $cluster): array => collect($cluster['programs'])
                ->map(fn (array $program): array => array_merge($program, [
                    'department_code' => $program['department_code'] ?? $cluster['department_code'],
                    'department_name' => $program['department_name'] ?? $cluster['department_name'],
                ]))
                ->all())
            ->keyBy('code');

        $selected = $catalog->only($programCodes);

        if ($selected->isEmpty()) {
            return $this->summary();
        }

        $types = $this->courseTypeIds();
        $departments = 0;
        $courses = 0;

        foreach ($selected->groupBy('department_code') as $departmentCode => $programs) {
            $department = Department::firstOrCreate(
                ['school_id' => $school->id, 'code' => mb_strtoupper(self::str($departmentCode))],
                [
                    'name' => self::str($programs->first()['department_name'] ?? ''),
                    'description' => 'Department created during setup from the CHED-aligned curriculum catalog.',
                    'is_active' => true,
                ],
            );

            if ($department->wasRecentlyCreated) {
                $departments++;
            }

            foreach ($programs as $program) {
                $code = $program['code'] ?? null;

                if (! is_string($code) || Course::query()->where('code', $code)->exists()) {
                    continue;
                }

                Course::create([
                    'code' => $code,
                    'title' => self::str($program['title'] ?? ''),
                    'description' => self::str($program['description'] ?? ''),
                    'department_id' => $department->id,
                    'course_type_id' => $types[$this->courseTypeName(self::str($program['type'] ?? ''))],
                    'units' => is_int($program['units'] ?? null) ? $program['units'] : 0,
                    'lec_per_unit' => null,
                    'lab_per_unit' => null,
                    'year_level' => is_int($program['year_level'] ?? null) ? $program['year_level'] : 1,
                    'semester' => 1,
                    'school_year' => $curriculumYear,
                    'curriculum_year' => $curriculumYear,
                    'miscellaneous' => '0.00',
                    'miscelaneous' => '0.00',
                    'remarks' => 'Aligned with '.self::str($program['reference'] ?? '').(($program['verified'] ?? false) ? '' : ' (verify with CHED)'),
                    'is_active' => true,
                    'school_id' => $school->id,
                ]);

                $courses++;
            }
        }

        return $this->summary(departments: $departments, courses: $courses);
    }

    /**
     * @param  list<string>  $programCodes
     * @return array{departments: int, courses: int, tracks: int, strands: int, strand_subjects: int}
     */
    private function bootstrapTesda(School $school, array $programCodes, ?string $curriculumYear): array
    {
        $catalog = collect(PhilippineCurriculumCatalog::tesdaSectors())
            ->flatMap(fn (array $sector): array => collect($sector['qualifications'])
                ->map(fn (array $qualification): array => array_merge($qualification, [
                    'department_code' => $sector['department_code'],
                    'department_name' => $sector['department_name'],
                ]))
                ->all())
            ->keyBy('code');

        $selected = $catalog->only($programCodes);

        if ($selected->isEmpty()) {
            return $this->summary();
        }

        $types = $this->courseTypeIds();
        $departments = 0;
        $courses = 0;

        foreach ($selected->groupBy('department_code') as $departmentCode => $qualifications) {
            $department = Department::firstOrCreate(
                ['school_id' => $school->id, 'code' => mb_strtoupper(self::str($departmentCode))],
                [
                    'name' => self::str($qualifications->first()['department_name'] ?? ''),
                    'description' => 'Department created during setup from the TESDA-aligned curriculum catalog.',
                    'is_active' => true,
                ],
            );

            if ($department->wasRecentlyCreated) {
                $departments++;
            }

            foreach ($qualifications as $qualification) {
                $code = $qualification['code'] ?? null;

                if (! is_string($code) || Course::query()->where('code', $code)->exists()) {
                    continue;
                }

                $remarks = ($qualification['diploma'] ?? false)
                    ? 'TESDA Diploma Program · PQF Level '.self::str($qualification['pqf_level'] ?? '').' (verify with TESDA)'
                    : 'TESDA TR'.(($qualification['superseded'] ?? false) ? ' (superseded)' : '').' · NC '.self::str($qualification['nc_level'] ?? '').' · PQF Level '.self::str($qualification['pqf_level'] ?? '');

                Course::create([
                    'code' => $code,
                    'title' => self::str($qualification['title'] ?? ''),
                    'description' => self::str($qualification['description'] ?? ''),
                    'department_id' => $department->id,
                    'course_type_id' => $types[$this->courseTypeName('tesda')],
                    'units' => 0,
                    'lec_per_unit' => null,
                    'lab_per_unit' => null,
                    'year_level' => ($qualification['diploma'] ?? false) ? 3 : ((is_int($qualification['nc_level'] ?? null) && $qualification['nc_level'] >= 3) ? 2 : 1),
                    'semester' => 1,
                    'school_year' => $curriculumYear,
                    'curriculum_year' => $curriculumYear,
                    'miscellaneous' => '0.00',
                    'miscelaneous' => '0.00',
                    'remarks' => $remarks,
                    'is_active' => true,
                    'school_id' => $school->id,
                ]);

                $courses++;
            }
        }

        return $this->summary(departments: $departments, courses: $courses);
    }

    /**
     * @param  array<int, array{key: string, name: string, description: string, strands: array<int, array{key: string, name: string, description: string}>}>  $tracks
     * @param  list<string>  $programCodes
     * @return array{departments: int, courses: int, tracks: int, strands: int, strand_subjects: int}
     */
    private function bootstrapShs(School $school, array $tracks, array $programCodes, bool $seedStrandSubjects): array
    {
        $tracksByKey = collect($tracks)->keyBy('key');

        $selections = collect($programCodes)
            ->map(fn (string $code): array => explode(':', $code, 2))
            ->filter(fn (array $parts): bool => count($parts) === 2)
            ->groupBy(fn (array $parts): string => $parts[0])
            ->map(fn (Collection $group): array => $group->pluck(1)->unique()->all());

        $tracksCount = 0;
        $strandsCount = 0;
        $subjectsCount = 0;

        foreach ($selections as $trackKey => $strandKeys) {
            if (! is_string($trackKey)) {
                continue;
            }

            $trackDefinition = $tracksByKey->get($trackKey);

            if ($trackDefinition === null) {
                continue;
            }

            $strandsByKey = collect($trackDefinition['strands'])->keyBy('key');

            $track = ShsTrack::firstOrCreate(
                ['track_name' => $trackDefinition['name']],
                ['description' => $trackDefinition['description']],
            );

            if ($track->wasRecentlyCreated) {
                $tracksCount++;
            }

            foreach ($strandKeys as $strandKey) {
                if (! is_string($strandKey)) {
                    continue;
                }

                $strandDefinition = $strandsByKey->get($strandKey);

                if ($strandDefinition === null) {
                    continue;
                }

                $strand = ShsStrand::firstOrCreate(
                    ['strand_name' => $strandDefinition['name'], 'track_id' => $track->id],
                    ['description' => $strandDefinition['description']],
                );

                if ($strand->wasRecentlyCreated) {
                    $strandsCount++;
                }

                if ($seedStrandSubjects) {
                    $subjectsCount += $this->seedShsCoreSubjects($strand);
                }
            }
        }

        return $this->summary(tracks: $tracksCount, strands: $strandsCount, strandSubjects: $subjectsCount);
    }

    private function seedShsCoreSubjects(ShsStrand $strand): int
    {
        $created = 0;

        foreach (self::SHS_CORE_SUBJECTS as $subject) {
            $model = StrandSubject::firstOrCreate(
                ['strand_id' => $strand->id, 'title' => $subject['title']],
                [
                    'description' => 'Core SHS subject under the K to 12 Senior High School curriculum.',
                    'grade_year' => $subject['grade_year'],
                    'semester' => $subject['semester'],
                ],
            );

            if ($model->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array<string, int> course type name => id
     */
    private function courseTypeIds(): array
    {
        $names = [
            "Bachelor's Degree",
            'Associate Degree',
            'Diploma',
            'TESDA Qualification (NC I-IV)',
        ];

        $ids = [];

        foreach ($names as $name) {
            $ids[$name] = (int) CourseType::firstOrCreate(['name' => $name])->id;
        }

        return $ids;
    }

    private function courseTypeName(string $type): string
    {
        return match ($type) {
            'bachelor' => "Bachelor's Degree",
            'associate' => 'Associate Degree',
            'diploma' => 'Diploma',
            'tesda' => 'TESDA Qualification (NC I-IV)',
            default => 'Diploma',
        };
    }

    /**
     * @return array{departments: int, courses: int, tracks: int, strands: int, strand_subjects: int}
     */
    private function summary(
        int $departments = 0,
        int $courses = 0,
        int $tracks = 0,
        int $strands = 0,
        int $strandSubjects = 0,
    ): array {
        return [
            'departments' => $departments,
            'courses' => $courses,
            'tracks' => $tracks,
            'strands' => $strands,
            'strand_subjects' => $strandSubjects,
        ];
    }
}
