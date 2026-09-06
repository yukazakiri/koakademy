<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\RegulatoryReportAdapter;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

final class ChedFormBcExportService implements RegulatoryReportAdapter
{
    private const TEMPLATE_PATH = 'resources/templates/ched/ched-form-bc-template.xlsx';

    private const START_ROW = 10;

    /**
     * Map course type names or course codes to sheet names in the CHED workbook.
     *
     * @var array<string, string>
     */
    private const SHEET_MAP = [
        'College Undergraduate' => 'Baccalaureate',
        'Baccalaureate' => 'Baccalaureate',
        'Undergraduate' => 'Baccalaureate',
        'College' => 'Baccalaureate',
        'Masters' => 'Masters',
        'Master' => 'Masters',
        'Graduate' => 'Masters',
        'Doctoral' => 'Doctoral',
        'Doctorate' => 'Doctoral',
        'Post-Baccalaureate' => 'Post-Baccalaureate',
        'Pre-Baccalaureate' => 'Pre-Baccalaureate',
        'TESDA' => 'VocTech',
        'VocTech' => 'VocTech',
        'Technical Vocational' => 'VocTech',
        'Senior High School' => 'Basic',
        'Basic Education' => 'Basic',
        'Basic' => 'Basic',
    ];

    /**
     * @param array{
     *     school_year?: string|null,
     *     semester?: int|string|null,
     *     department_id?: int|string|null,
     *     course_id?: int|string|null,
     *     student_type?: string|null,
     *     gender?: string|null,
     *     school_id?: int|null
     * } $filters
     */
    public function generate(array $filters = []): Spreadsheet
    {
        $templateFile = resource_path('templates/ched/ched-form-bc-template.xlsx');
        if (! file_exists($templateFile)) {
            $templateFile = base_path(self::TEMPLATE_PATH);
        }
        if (! file_exists($templateFile)) {
            throw new RuntimeException("CHED Form B/C template file not found at: {$templateFile}");
        }

        $spreadsheet = IOFactory::load($templateFile);

        $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) ($filters['school_year'] ?? ''));
        $semester = ! empty($filters['semester']) ? (int) $filters['semester'] : null;
        $schoolId = isset($filters['school_id']) ? (int) $filters['school_id'] : null;

        $courses = $this->queryCourses($filters);
        $enrollmentCounts = $this->queryEnrollmentMatrix($schoolYear, $semester, $schoolId);
        $graduatesCounts = $this->queryGraduatesMatrix($schoolYear, $semester, $schoolId);

        $this->populateCurricularSheets($spreadsheet, $courses, $enrollmentCounts, $graduatesCounts);
        $this->populateSpecialEquitySheet($spreadsheet, $courses, $schoolYear, $semester, $schoolId);

        return $spreadsheet;
    }

    /**
     * Build preview matrix for frontend tables.
     *
     * @param array{
     *     school_year?: string|null,
     *     semester?: int|string|null,
     *     department_id?: int|string|null,
     *     course_id?: int|string|null,
     *     student_type?: string|null,
     *     gender?: string|null,
     *     school_id?: int|null
     * } $filters
     * @return array<string, mixed>
     */
    public function buildPreviewData(array $filters = []): array
    {
        $schoolYear = GeneralSettingsService::normalizeSchoolYear((string) ($filters['school_year'] ?? ''));
        $semester = ! empty($filters['semester']) ? (int) $filters['semester'] : null;
        $schoolId = isset($filters['school_id']) ? (int) $filters['school_id'] : null;

        $courses = $this->queryCourses($filters);
        $enrollmentCounts = $this->queryEnrollmentMatrix($schoolYear, $semester, $schoolId);
        $graduatesCounts = $this->queryGraduatesMatrix($schoolYear, $semester, $schoolId);

        $sheetsData = [];
        $totalStudents = 0;
        $totalGraduates = 0;

        foreach ($courses as $course) {
            $sheetName = $this->resolveSheetName($course);
            $cId = (int) $course->id;
            $enr = $enrollmentCounts->get($cId, []);
            $grad = $graduatesCounts->get($cId, ['male' => 0, 'female' => 0, 'total' => 0]);

            $subtotalMale = (int) ($enr['freshman_male'] ?? 0)
                + (int) ($enr['continuing_first_year_male'] ?? 0)
                + (int) ($enr['year_2_male'] ?? 0)
                + (int) ($enr['year_3_male'] ?? 0)
                + (int) ($enr['year_4_male'] ?? 0)
                + (int) ($enr['year_5_male'] ?? 0)
                + (int) ($enr['year_6_male'] ?? 0)
                + (int) ($enr['year_7_male'] ?? 0);

            $subtotalFemale = (int) ($enr['freshman_female'] ?? 0)
                + (int) ($enr['continuing_first_year_female'] ?? 0)
                + (int) ($enr['year_2_female'] ?? 0)
                + (int) ($enr['year_3_female'] ?? 0)
                + (int) ($enr['year_4_female'] ?? 0)
                + (int) ($enr['year_5_female'] ?? 0)
                + (int) ($enr['year_6_female'] ?? 0)
                + (int) ($enr['year_7_female'] ?? 0);

            $grandTotal = $subtotalMale + $subtotalFemale;
            $totalStudents += $grandTotal;
            $totalGraduates += (int) $grad['total'];

            $rowData = [
                'course_id' => $course->id,
                'program_title' => $course->title,
                'program_code' => $course->ched_program_code ?: $course->code,
                'major' => $course->ched_major,
                'major_code' => $course->ched_major_code,
                'with_thesis' => $course->ched_has_thesis ? '1 - Yes' : '2 - No',
                'program_status' => $course->ched_program_status ?: 'CO',
                'year_implemented' => $course->ched_year_implemented,
                'authority_category' => $course->ched_authority_category,
                'authority_serial' => $course->ched_authority_serial,
                'authority_year' => $course->ched_authority_year,
                'delivery_mode' => $course->ched_delivery_mode ?: 'SE',
                'normal_length_years' => $course->ched_normal_length_years,
                'credit_units' => $course->ched_program_credit_units ?: $course->units,
                'tuition_per_unit' => $course->ched_tuition_per_unit,
                'program_fee' => $course->ched_program_fee,
                'enrolment' => [
                    'new_freshmen' => ['male' => (int) ($enr['freshman_male'] ?? 0), 'female' => (int) ($enr['freshman_female'] ?? 0)],
                    'old_first_year' => ['male' => (int) ($enr['continuing_first_year_male'] ?? 0), 'female' => (int) ($enr['continuing_first_year_female'] ?? 0)],
                    'year_2' => ['male' => (int) ($enr['year_2_male'] ?? 0), 'female' => (int) ($enr['year_2_female'] ?? 0)],
                    'year_3' => ['male' => (int) ($enr['year_3_male'] ?? 0), 'female' => (int) ($enr['year_3_female'] ?? 0)],
                    'year_4' => ['male' => (int) ($enr['year_4_male'] ?? 0), 'female' => (int) ($enr['year_4_female'] ?? 0)],
                    'year_5' => ['male' => (int) ($enr['year_5_male'] ?? 0), 'female' => (int) ($enr['year_5_female'] ?? 0)],
                    'year_6' => ['male' => (int) ($enr['year_6_male'] ?? 0), 'female' => (int) ($enr['year_6_female'] ?? 0)],
                    'year_7' => ['male' => (int) ($enr['year_7_male'] ?? 0), 'female' => (int) ($enr['year_7_female'] ?? 0)],
                    'subtotal' => ['male' => $subtotalMale, 'female' => $subtotalFemale],
                    'total' => $grandTotal,
                ],
                'graduates' => [
                    'male' => (int) $grad['male'],
                    'female' => (int) $grad['female'],
                    'total' => (int) $grad['total'],
                ],
            ];

            $sheetsData[$sheetName][] = $rowData;
        }

        return [
            'type' => 'ched_eform_bc',
            'title' => 'CHED E-Form B/C - Curriculum Program Profile, Enrolment & Graduates',
            'subtitle' => "School Year: {$schoolYear}".($semester ? " Term {$semester}" : ''),
            'sheets' => $sheetsData,
            'summary' => [
                'total_programs' => $courses->count(),
                'total_enrolled' => $totalStudents,
                'total_graduates' => $totalGraduates,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Course>
     */
    private function queryCourses(array $filters): Collection
    {
        $query = Course::query()
            ->with(['courseType', 'department'])
            ->where('is_active', true);

        if (! empty($filters['school_id'])) {
            $query->where('school_id', $filters['school_id']);
        }
        if (! empty($filters['department_id']) && $filters['department_id'] !== 'all') {
            $query->where('department_id', $filters['department_id']);
        }
        if (! empty($filters['course_id']) && $filters['course_id'] !== 'all') {
            $query->where('id', $filters['course_id']);
        }

        return $query->orderBy('code')->get();
    }

    /**
     * @return Collection<int, array<string, int>>
     */
    private function queryEnrollmentMatrix(string $schoolYear, ?int $semester, ?int $schoolId): Collection
    {
        $query = StudentEnrollment::query()
            ->join('students', 'student_enrollment.student_id', '=', 'students.id')
            ->whereNull('student_enrollment.deleted_at')
            ->whereNull('students.deleted_at');

        if ($schoolYear !== '') {
            $query->whereIn('student_enrollment.school_year', $this->schoolYearVariants($schoolYear));
        }
        if ($semester !== null) {
            $query->where('student_enrollment.semester', $semester);
        }
        if ($schoolId !== null) {
            $query->where('student_enrollment.school_id', $schoolId);
        }

        $genderLower = "LOWER(TRIM(COALESCE(students.gender, '')))";

        $selects = [
            'student_enrollment.course_id',
            "SUM(CASE WHEN student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'new_freshman' AND {$genderLower} = 'male' THEN 1 ELSE 0 END) as freshman_male",
            "SUM(CASE WHEN student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'new_freshman' AND {$genderLower} = 'female' THEN 1 ELSE 0 END) as freshman_female",
            "SUM(CASE WHEN student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'continuing_first_year' AND {$genderLower} = 'male' THEN 1 ELSE 0 END) as continuing_first_year_male",
            "SUM(CASE WHEN student_enrollment.academic_year = 1 AND student_enrollment.intake_category = 'continuing_first_year' AND {$genderLower} = 'female' THEN 1 ELSE 0 END) as continuing_first_year_female",
        ];

        foreach (range(2, 7) as $year) {
            $selects[] = "SUM(CASE WHEN student_enrollment.academic_year = {$year} AND {$genderLower} = 'male' THEN 1 ELSE 0 END) as year_{$year}_male";
            $selects[] = "SUM(CASE WHEN student_enrollment.academic_year = {$year} AND {$genderLower} = 'female' THEN 1 ELSE 0 END) as year_{$year}_female";
        }

        $results = $query->selectRaw(implode(', ', $selects))
            ->groupBy('student_enrollment.course_id')
            ->get();

        return $results->keyBy('course_id')->map(function ($row) {
            return (array) $row->getAttributes();
        });
    }

    /**
     * @return Collection<int, array{male: int, female: int, total: int}>
     */
    private function queryGraduatesMatrix(string $schoolYear, ?int $semester, ?int $schoolId): Collection
    {
        $query = Student::query()
            ->where('status', StudentStatus::Graduated->value)
            ->whereNull('deleted_at');

        if ($schoolYear !== '') {
            $query->whereIn('graduation_school_year', $this->schoolYearVariants($schoolYear));
        }
        if ($semester !== null) {
            $query->where('graduation_semester', $semester);
        }
        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        $genderLower = "LOWER(TRIM(COALESCE(gender, '')))";

        $results = $query->selectRaw("
            course_id,
            SUM(CASE WHEN {$genderLower} = 'male' THEN 1 ELSE 0 END) as male,
            SUM(CASE WHEN {$genderLower} = 'female' THEN 1 ELSE 0 END) as female,
            count(*) as total
        ")
            ->whereNotNull('course_id')
            ->groupBy('course_id')
            ->get();

        return $results->keyBy('course_id')->map(fn ($row) => [
            'male' => (int) $row->male,
            'female' => (int) $row->female,
            'total' => (int) $row->total,
        ]);
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, array<string, int>>  $enrollmentCounts
     * @param  Collection<int, array{male: int, female: int, total: int}>  $graduatesCounts
     */
    private function populateCurricularSheets(
        Spreadsheet $spreadsheet,
        Collection $courses,
        Collection $enrollmentCounts,
        Collection $graduatesCounts,
    ): void {
        $sheetRows = [];

        foreach ($courses as $course) {
            $sheetName = $this->resolveSheetName($course);
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (! $sheet) {
                continue;
            }

            if (! isset($sheetRows[$sheetName])) {
                $sheetRows[$sheetName] = self::START_ROW;
            }
            $row = $sheetRows[$sheetName];

            $cId = (int) $course->id;
            $enr = $enrollmentCounts->get($cId, []);
            $grad = $graduatesCounts->get($cId, ['male' => 0, 'female' => 0, 'total' => 0]);

            // Program Information (Columns A - Q)
            $this->setTextCell($sheet, "A{$row}", $course->title);
            $this->setTextCell($sheet, "B{$row}", $course->ched_program_code ?: $course->code);
            $this->setTextCell($sheet, "C{$row}", $course->ched_major ?: '');
            $this->setTextCell($sheet, "D{$row}", $course->ched_major_code ?: '');
            $sheet->setCellValue("E{$row}", $course->ched_has_thesis ? 1 : 2);
            $this->setTextCell($sheet, "F{$row}", $course->ched_program_status ?: 'CO');
            $sheet->setCellValue("G{$row}", $course->ched_year_implemented ?: '');
            $this->setTextCell($sheet, "H{$row}", $course->ched_authority_category ?: '');
            $this->setTextCell($sheet, "I{$row}", $course->ched_authority_serial ?: '');
            $sheet->setCellValue("J{$row}", $course->ched_authority_year ?: '');
            $this->setTextCell($sheet, "K{$row}", $course->remarks ?: '');
            $this->setTextCell($sheet, "L{$row}", $course->ched_delivery_mode ?: 'SE');
            $this->setTextCell($sheet, "M{$row}", $course->ched_authority_other_program ?: '');
            $sheet->setCellValue("N{$row}", $course->ched_normal_length_years ?: 4);
            $sheet->setCellValue("O{$row}", $course->ched_program_credit_units ?: ($course->units ?: 0));
            $sheet->setCellValue("P{$row}", $course->ched_tuition_per_unit ?: 0);
            $sheet->setCellValue("Q{$row}", $course->ched_program_fee ?: 0);

            // Enrollment Data (Columns R - AG)
            $sheet->setCellValue("R{$row}", (int) ($enr['freshman_male'] ?? 0));
            $sheet->setCellValue("S{$row}", (int) ($enr['freshman_female'] ?? 0));
            $sheet->setCellValue("T{$row}", (int) ($enr['continuing_first_year_male'] ?? 0));
            $sheet->setCellValue("U{$row}", (int) ($enr['continuing_first_year_female'] ?? 0));
            $sheet->setCellValue("V{$row}", (int) ($enr['year_2_male'] ?? 0));
            $sheet->setCellValue("W{$row}", (int) ($enr['year_2_female'] ?? 0));
            $sheet->setCellValue("X{$row}", (int) ($enr['year_3_male'] ?? 0));
            $sheet->setCellValue("Y{$row}", (int) ($enr['year_3_female'] ?? 0));
            $sheet->setCellValue("Z{$row}", (int) ($enr['year_4_male'] ?? 0));
            $sheet->setCellValue("AA{$row}", (int) ($enr['year_4_female'] ?? 0));
            $sheet->setCellValue("AB{$row}", (int) ($enr['year_5_male'] ?? 0));
            $sheet->setCellValue("AC{$row}", (int) ($enr['year_5_female'] ?? 0));
            $sheet->setCellValue("AD{$row}", (int) ($enr['year_6_male'] ?? 0));
            $sheet->setCellValue("AE{$row}", (int) ($enr['year_6_female'] ?? 0));
            $sheet->setCellValue("AF{$row}", (int) ($enr['year_7_male'] ?? 0));
            $sheet->setCellValue("AG{$row}", (int) ($enr['year_7_female'] ?? 0));

            // Totals: Ensure formulas exist in AH, AI, AJ
            $sheet->setCellValue("AH{$row}", "=(R{$row}+T{$row}+V{$row}+X{$row}+Z{$row}+AB{$row}+AD{$row}+AF{$row})");
            $sheet->setCellValue("AI{$row}", "=(S{$row}+U{$row}+W{$row}+Y{$row}+AA{$row}+AC{$row}+AE{$row}+AG{$row})");
            $sheet->setCellValue("AJ{$row}", "=(AH{$row}+AI{$row})");

            // Graduates Data (Columns AK - AM)
            $sheet->setCellValue("AK{$row}", (int) $grad['male']);
            $sheet->setCellValue("AL{$row}", (int) $grad['female']);
            $sheet->setCellValue("AM{$row}", "=(AK{$row}+AL{$row})");

            $sheetRows[$sheetName]++;
        }
    }

    /**
     * Populate Special Equity Groups Sheet (Disabilities, IP, Solo Parents, Senior Citizens, Magna Carta, Underprivileged)
     *
     * @param  Collection<int, Course>  $courses
     */
    private function populateSpecialEquitySheet(
        Spreadsheet $spreadsheet,
        Collection $courses,
        string $schoolYear,
        ?int $semester,
        ?int $schoolId,
    ): void {
        $sheet = $spreadsheet->getSheetByName('NEW Special Equity Groups Form ');
        if (! $sheet) {
            return;
        }

        // 1. Enrollment Equity Aggregates per course
        $enrQuery = StudentEnrollment::query()
            ->join('students', 'student_enrollment.student_id', '=', 'students.id')
            ->whereNull('student_enrollment.deleted_at')
            ->whereNull('students.deleted_at')
            ->where('students.status', '!=', StudentStatus::Graduated->value);

        if ($schoolYear !== '') {
            $enrQuery->whereIn('student_enrollment.school_year', $this->schoolYearVariants($schoolYear));
        }
        if ($semester !== null) {
            $enrQuery->where('student_enrollment.semester', $semester);
        }
        if ($schoolId !== null) {
            $enrQuery->where('student_enrollment.school_id', $schoolId);
        }

        $enrEquity = $this->aggregateEquityByCourse($enrQuery, 'student_enrollment.course_id');

        // 2. Graduate Equity Aggregates per course
        $gradQuery = Student::query()
            ->where('status', StudentStatus::Graduated->value)
            ->whereNull('deleted_at');

        if ($schoolYear !== '') {
            $gradQuery->whereIn('students.graduation_school_year', $this->schoolYearVariants($schoolYear));
        }
        if ($semester !== null) {
            $gradQuery->where('students.graduation_semester', $semester);
        }
        if ($schoolId !== null) {
            $gradQuery->where('students.school_id', $schoolId);
        }

        $gradEquity = $this->aggregateEquityByCourse($gradQuery, 'students.course_id');

        $row = self::START_ROW;
        foreach ($courses as $course) {
            $cId = (int) $course->id;
            $e = $enrEquity->get($cId, []);
            $g = $gradEquity->get($cId, []);

            // Program and Major
            $this->setTextCell($sheet, "A{$row}", $course->title);
            $this->setTextCell($sheet, "B{$row}", $course->ched_major ?: 'None');

            // Enrollment Distribution by Special Equity Group (Columns C - S)
            $sheet->setCellValue("C{$row}", (int) ($e['apparent_physical'] ?? 0));
            $sheet->setCellValue("D{$row}", (int) ($e['deaf'] ?? 0));
            $sheet->setCellValue("E{$row}", (int) ($e['intellectual'] ?? 0));
            $sheet->setCellValue("F{$row}", (int) ($e['learning'] ?? 0));
            $sheet->setCellValue("G{$row}", (int) ($e['mental'] ?? 0));
            $sheet->setCellValue("H{$row}", (int) ($e['visual'] ?? 0));
            $sheet->setCellValue("I{$row}", (int) ($e['speech'] ?? 0));
            $sheet->setCellValue("J{$row}", (int) ($e['cancer'] ?? 0));
            $sheet->setCellValue("K{$row}", (int) ($e['rare_disease'] ?? 0));
            $sheet->setCellValue("L{$row}", "=SUM(C{$row}:K{$row})");
            $sheet->setCellValue("M{$row}", (int) ($e['indigenous'] ?? 0));
            $sheet->setCellValue("N{$row}", (int) ($e['solo_parent'] ?? 0));
            $sheet->setCellValue("O{$row}", (int) ($e['solo_parent_dependent'] ?? 0));
            $sheet->setCellValue("P{$row}", (int) ($e['senior_citizen'] ?? 0));
            $sheet->setCellValue("Q{$row}", (int) ($e['magna_carta'] ?? 0));
            $sheet->setCellValue("R{$row}", (int) ($e['underprivileged'] ?? 0));
            $sheet->setCellValue("S{$row}", "=(L{$row}+M{$row}+N{$row}+O{$row}+P{$row}+Q{$row}+R{$row})");

            // Graduate Distribution by Special Equity Group (Columns T - AJ)
            $sheet->setCellValue("T{$row}", (int) ($g['apparent_physical'] ?? 0));
            $sheet->setCellValue("U{$row}", (int) ($g['deaf'] ?? 0));
            $sheet->setCellValue("V{$row}", (int) ($g['intellectual'] ?? 0));
            $sheet->setCellValue("W{$row}", (int) ($g['learning'] ?? 0));
            $sheet->setCellValue("X{$row}", (int) ($g['mental'] ?? 0));
            $sheet->setCellValue("Y{$row}", (int) ($g['visual'] ?? 0));
            $sheet->setCellValue("Z{$row}", (int) ($g['speech'] ?? 0));
            $sheet->setCellValue("AA{$row}", (int) ($g['cancer'] ?? 0));
            $sheet->setCellValue("AB{$row}", (int) ($g['rare_disease'] ?? 0));
            $sheet->setCellValue("AC{$row}", "=SUM(T{$row}:AB{$row})");
            $sheet->setCellValue("AD{$row}", (int) ($g['indigenous'] ?? 0));
            $sheet->setCellValue("AE{$row}", (int) ($g['solo_parent'] ?? 0));
            $sheet->setCellValue("AF{$row}", (int) ($g['solo_parent_dependent'] ?? 0));
            $sheet->setCellValue("AG{$row}", (int) ($g['senior_citizen'] ?? 0));
            $sheet->setCellValue("AH{$row}", (int) ($g['magna_carta'] ?? 0));
            $sheet->setCellValue("AI{$row}", (int) ($g['underprivileged'] ?? 0));
            $sheet->setCellValue("AJ{$row}", "=(AC{$row}+AD{$row}+AE{$row}+AF{$row}+AG{$row}+AH{$row}+AI{$row})");

            $row++;
        }
    }

    /**
     * @return Collection<int, array<string, int>>
     */
    private function aggregateEquityByCourse(Builder $query, string $courseColumn): Collection
    {
        $pwdLower = "LOWER(TRIM(COALESCE(students.pwd_type, '')))";

        $selects = [
            'courses.id as course_id',
            "SUM(CASE WHEN students.is_pwd = true AND ({$pwdLower} LIKE '%physical%' OR {$pwdLower} LIKE '%orthopedic%' OR {$pwdLower} LIKE '%apparent%') THEN 1 ELSE 0 END) as apparent_physical",
            "SUM(CASE WHEN students.is_pwd = true AND ({$pwdLower} LIKE '%deaf%' OR {$pwdLower} LIKE '%hearing%') THEN 1 ELSE 0 END) as deaf",
            "SUM(CASE WHEN students.is_pwd = true AND {$pwdLower} LIKE '%intellectual%' THEN 1 ELSE 0 END) as intellectual",
            "SUM(CASE WHEN students.is_pwd = true AND {$pwdLower} LIKE '%learning%' THEN 1 ELSE 0 END) as learning",
            "SUM(CASE WHEN students.is_pwd = true AND ({$pwdLower} LIKE '%mental%' OR {$pwdLower} LIKE '%psycho%') THEN 1 ELSE 0 END) as mental",
            "SUM(CASE WHEN students.is_pwd = true AND ({$pwdLower} LIKE '%visual%' OR {$pwdLower} LIKE '%blind%') THEN 1 ELSE 0 END) as visual",
            "SUM(CASE WHEN students.is_pwd = true AND ({$pwdLower} LIKE '%speech%' OR {$pwdLower} LIKE '%language%') THEN 1 ELSE 0 END) as speech",
            "SUM(CASE WHEN students.is_pwd = true AND {$pwdLower} LIKE '%cancer%' THEN 1 ELSE 0 END) as cancer",
            "SUM(CASE WHEN students.is_pwd = true AND {$pwdLower} LIKE '%rare%' THEN 1 ELSE 0 END) as rare_disease",
            'SUM(CASE WHEN students.is_indigenous_person = true THEN 1 ELSE 0 END) as indigenous',
            'SUM(CASE WHEN students.is_solo_parent = true THEN 1 ELSE 0 END) as solo_parent',
            'SUM(CASE WHEN students.is_solo_parent_dependent = true THEN 1 ELSE 0 END) as solo_parent_dependent',
            'SUM(CASE WHEN students.is_senior_citizen = true THEN 1 ELSE 0 END) as senior_citizen',
            'SUM(CASE WHEN students.is_magna_carta = true THEN 1 ELSE 0 END) as magna_carta',
            'SUM(CASE WHEN students.is_underprivileged = true THEN 1 ELSE 0 END) as underprivileged',
        ];

        $results = (clone $query)
            ->join('courses', function ($join) use ($courseColumn): void {
                $join->on($courseColumn, '=', 'courses.id');
            })
            ->selectRaw(implode(', ', $selects))
            ->groupBy('courses.id')
            ->get();

        return $results->keyBy('course_id')->map(function ($row) {
            return (array) $row->getAttributes();
        });
    }

    /**
     * @return array<string>
     */
    private function schoolYearVariants(string $schoolYear): array
    {
        $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);

        return array_values(array_unique([$normalized, str_replace(' ', '', $normalized)]));
    }

    private function setTextCell(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    private function resolveSheetName(Course $course): string
    {
        $typeName = $course->courseType?->name;
        if ($typeName && isset(self::SHEET_MAP[$typeName])) {
            return self::SHEET_MAP[$typeName];
        }

        // Fallback checks on course code or title
        $title = (string) $course->title;
        $code = (string) $course->code;

        if (str_starts_with($code, 'MS') || str_starts_with($code, 'MA') || str_contains($title, 'Master')) {
            return 'Masters';
        }
        if (str_starts_with($code, 'PHD') || str_starts_with($code, 'EDD') || str_contains($title, 'Doctor')) {
            return 'Doctoral';
        }
        if (str_contains($title, 'TESDA') || str_contains($title, 'Certificate in')) {
            return 'VocTech';
        }

        return 'Baccalaureate';
    }
}
