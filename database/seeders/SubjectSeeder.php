<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SubjectSeeder extends Seeder
{
    /**
     * Seed a complete 4-year × 2-semester CHED-aligned curriculum for all 12 college programs.
     */
    public function run(): void
    {
        $defaultSchoolId = School::query()->value('id') ?? 1;
        $courses = Course::withoutSchoolScope()->get()->keyBy('code');

        $curricula = $this->curricula();
        $totalCreatedOrUpdated = 0;

        DB::transaction(function () use ($curricula, $courses, $defaultSchoolId, &$totalCreatedOrUpdated): void {
            foreach ($curricula as $courseCode => $subjectDefinitions) {
                $course = $courses->get($courseCode);

                if (! $course instanceof Course) {
                    $this->command?->warn(sprintf('Skipped curriculum for %s: course not found.', $courseCode));

                    continue;
                }

                $schoolId = (int) ($course->school_id ?: $defaultSchoolId);
                $codeToId = [];
                $prereqMap = [];
                $totalUnits = 0;

                // Pass 1: Create or update all subjects for this course without prerequisites
                foreach ($subjectDefinitions as $definition) {
                    $code = $definition['code'];
                    $units = $definition['lecture'] + $definition['laboratory'];
                    $totalUnits += $units;

                    $subject = Subject::withoutSchoolScope()->updateOrCreate(
                        [
                            'course_id' => $course->id,
                            'code' => $code,
                        ],
                        [
                            'school_id' => $schoolId,
                            'classification' => SubjectEnrolledEnum::INTERNAL,
                            'title' => $definition['title'],
                            'units' => $units,
                            'lecture' => $definition['lecture'],
                            'laboratory' => $definition['laboratory'],
                            'academic_year' => $definition['academic_year'],
                            'semester' => $definition['semester'],
                            'is_credited' => true,
                            'pre_riquisite' => null,
                        ],
                    );

                    $codeToId[$code] = (int) $subject->id;

                    if ($definition['prereqs'] !== []) {
                        $prereqMap[$code] = $definition['prereqs'];
                    }

                    $totalCreatedOrUpdated++;
                }

                // Pass 2: Resolve prerequisite subject codes to integer subject IDs within this course
                foreach ($prereqMap as $code => $prereqCodes) {
                    $subjectId = $codeToId[$code] ?? null;
                    if ($subjectId === null) {
                        continue;
                    }

                    $resolvedIds = [];
                    foreach ($prereqCodes as $prereqCode) {
                        if (isset($codeToId[$prereqCode]) && $codeToId[$prereqCode] !== $subjectId) {
                            $resolvedIds[] = $codeToId[$prereqCode];
                        }
                    }

                    $resolvedIds = array_values(array_unique($resolvedIds));

                    Subject::withoutSchoolScope()
                        ->whereKey($subjectId)
                        ->update([
                            'pre_riquisite' => $resolvedIds === [] ? null : $resolvedIds,
                        ]);
                }

                // Keep the course's total catalog units synchronized with its seeded curriculum
                $course->update(['units' => $totalUnits]);
            }
        });

        $this->command?->info(sprintf('Subjects seeded successfully! (%d total curriculum subjects across %d programs)', $totalCreatedOrUpdated, count($curricula)));
    }

    /**
     * Build one curriculum subject definition entry.
     *
     * @param  list<string>  $prereqs
     * @return array{code: string, title: string, academic_year: int, semester: int, lecture: int, laboratory: int, prereqs: list<string>}
     */
    private function sub(
        string $code,
        string $title,
        int $year,
        int $semester,
        int $lecture = 3,
        int $laboratory = 0,
        array $prereqs = [],
    ): array {
        return [
            'code' => $code,
            'title' => $title,
            'academic_year' => $year,
            'semester' => $semester,
            'lecture' => $lecture,
            'laboratory' => $laboratory,
            'prereqs' => $prereqs,
        ];
    }

    /**
     * Complete 4-year × 2-semester curricula (5 subjects per semester = 40 subjects per program).
     *
     * @return array<string, list<array{code: string, title: string, academic_year: int, semester: int, lecture: int, laboratory: int, prereqs: list<string>}>>
     */
    private function curricula(): array
    {
        return [
            'BSIT' => [
                // 1st Year - 1st Semester
                $this->sub('IT111', 'Introduction to Computing', 1, 1, 2, 1),
                $this->sub('IT112', 'Computer Programming 1', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('ENG111', 'English Communication Skills', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('IT121', 'Computer Programming 2', 1, 2, 2, 1, ['IT112']),
                $this->sub('IT122', 'Web Development 1', 1, 2, 2, 1, ['IT111']),
                $this->sub('MATH121', 'Discrete Mathematics', 1, 2, 3, 0, ['MATH111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('IT211', 'Data Structures and Algorithms', 2, 1, 2, 1, ['IT121']),
                $this->sub('IT212', 'Database Management Systems', 2, 1, 2, 1, ['IT121']),
                $this->sub('IT213', 'Object-Oriented Programming', 2, 1, 2, 1, ['IT121']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('IT221', 'Web Development 2', 2, 2, 2, 1, ['IT122', 'IT212']),
                $this->sub('IT222', 'Information Management and Advanced Databases', 2, 2, 2, 1, ['IT212']),
                $this->sub('IT223', 'Networking 1 (Routing and Switching Fundamentals)', 2, 2, 2, 1, ['IT111']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('IT311', 'Software Engineering', 3, 1, 2, 1, ['IT213']),
                $this->sub('IT312', 'Network Administration', 3, 1, 2, 1, ['IT223']),
                $this->sub('IT313', 'Mobile Application Development', 3, 1, 2, 1, ['IT213']),
                $this->sub('IT314', 'Information Assurance and Security 1', 3, 1, 2, 1, ['IT212']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('IT321', 'Systems Integration and Architecture', 3, 2, 2, 1, ['IT311']),
                $this->sub('IT322', 'Information Assurance and Security 2', 3, 2, 2, 1, ['IT314']),
                $this->sub('IT323', 'Human-Computer Interaction', 3, 2, 2, 1, ['IT221']),
                $this->sub('IT324', 'Cloud Computing and Virtualization', 3, 2, 2, 1, ['IT312']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('IT411', 'Capstone Project 1', 4, 1, 1, 2, ['IT311']),
                $this->sub('IT412', 'IT Project Management', 4, 1, 3, 0, ['IT311']),
                $this->sub('IT413', 'Systems Administration and Maintenance', 4, 1, 2, 1, ['IT312']),
                $this->sub('IT414', 'Enterprise Resource Planning Systems', 4, 1, 2, 1, ['IT321']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('IT421', 'Capstone Project 2', 4, 2, 1, 2, ['IT411']),
                $this->sub('IT422', 'IT Practicum and Industry Internship (486 Hours)', 4, 2, 0, 6, ['IT411']),
                $this->sub('IT423', 'Social and Professional Issues in Computing', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('IT424', 'Technopreneurship', 4, 2, 3, 0, ['IT412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSCS' => [
                // 1st Year - 1st Semester
                $this->sub('CS111', 'Introduction to Computing', 1, 1, 2, 1),
                $this->sub('CS112', 'Fundamentals of Programming', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('ENG111', 'English Communication Skills', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('CS121', 'Intermediate Programming', 1, 2, 2, 1, ['CS112']),
                $this->sub('CS122', 'Discrete Structures 1', 1, 2, 3, 0, ['MATH111']),
                $this->sub('CS123', 'Digital Logic Design', 1, 2, 2, 1, ['CS111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('CS211', 'Data Structures and Algorithms', 2, 1, 2, 1, ['CS121']),
                $this->sub('CS212', 'Object-Oriented Programming', 2, 1, 2, 1, ['CS121']),
                $this->sub('CS213', 'Discrete Structures 2', 2, 1, 3, 0, ['CS122']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('CS221', 'Algorithms and Complexity', 2, 2, 2, 1, ['CS211']),
                $this->sub('CS222', 'Computer Architecture and Organization', 2, 2, 2, 1, ['CS123']),
                $this->sub('CS223', 'Information Management', 2, 2, 2, 1, ['CS211']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('CS311', 'Automata Theory and Formal Languages', 3, 1, 3, 0, ['CS221']),
                $this->sub('CS312', 'Operating Systems', 3, 1, 2, 1, ['CS222']),
                $this->sub('CS313', 'Software Engineering 1', 3, 1, 2, 1, ['CS212']),
                $this->sub('CS314', 'Programming Languages', 3, 1, 2, 1, ['CS212']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('CS321', 'Artificial Intelligence', 3, 2, 2, 1, ['CS221']),
                $this->sub('CS322', 'Software Engineering 2', 3, 2, 2, 1, ['CS313']),
                $this->sub('CS323', 'Networks and Communications', 3, 2, 2, 1, ['CS312']),
                $this->sub('CS324', 'Compiler Design', 3, 2, 2, 1, ['CS311']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('CS411', 'CS Thesis Writing 1', 4, 1, 1, 2, ['CS322']),
                $this->sub('CS412', 'Machine Learning and Data Mining', 4, 1, 2, 1, ['CS321']),
                $this->sub('CS413', 'Information Assurance and Security', 4, 1, 2, 1, ['CS323']),
                $this->sub('CS414', 'Parallel and Distributed Computing', 4, 1, 2, 1, ['CS312']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('CS421', 'CS Thesis Writing 2', 4, 2, 1, 2, ['CS411']),
                $this->sub('CS422', 'Computer Science Practicum and Internship', 4, 2, 0, 6, ['CS411']),
                $this->sub('CS423', 'Social and Professional Issues in Computing', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('CS424', 'Human-Computer Interaction', 4, 2, 2, 1, ['CS313']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSIS' => [
                // 1st Year - 1st Semester
                $this->sub('IS111', 'Introduction to Computing', 1, 1, 2, 1),
                $this->sub('IS112', 'Computer Programming 1', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('ENG111', 'English Communication Skills', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('IS121', 'Computer Programming 2', 1, 2, 2, 1, ['IS112']),
                $this->sub('IS122', 'Fundamentals of Information Systems', 1, 2, 3, 0, ['IS111']),
                $this->sub('IS123', 'Organization and Management Concepts', 1, 2, 3, 0),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('IS211', 'Data Structures and Algorithms', 2, 1, 2, 1, ['IS121']),
                $this->sub('IS212', 'Business Process Management', 2, 1, 2, 1, ['IS122']),
                $this->sub('IS213', 'Financial Accounting for Information Systems', 2, 1, 2, 1, ['IS123']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('IS221', 'Information Management and Databases', 2, 2, 2, 1, ['IS211']),
                $this->sub('IS222', 'Systems Analysis and Design', 2, 2, 2, 1, ['IS212']),
                $this->sub('IS223', 'IT Infrastructure and Network Technologies', 2, 2, 2, 1, ['IS122']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('IS311', 'Enterprise Architecture', 3, 1, 3, 0, ['IS222']),
                $this->sub('IS312', 'Application Development and Emerging Technologies', 3, 1, 2, 1, ['IS221']),
                $this->sub('IS313', 'Quantitative Methods and Business Analytics', 3, 1, 2, 1, ['MATH111']),
                $this->sub('IS314', 'IS Project Management 1', 3, 1, 3, 0, ['IS222']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('IS321', 'Enterprise Resource Planning Systems', 3, 2, 2, 1, ['IS311']),
                $this->sub('IS322', 'Information Security and IS Audit', 3, 2, 2, 1, ['IS223']),
                $this->sub('IS323', 'Evaluation of Business Performance', 3, 2, 3, 0, ['IS313']),
                $this->sub('IS324', 'Customer Relationship Management Systems', 3, 2, 2, 1, ['IS312']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('IS411', 'IS Capstone Project 1', 4, 1, 1, 2, ['IS314']),
                $this->sub('IS412', 'IS Strategy, Management, and Acquisition', 4, 1, 3, 0, ['IS311']),
                $this->sub('IS413', 'Business Intelligence and Data Warehousing', 4, 1, 2, 1, ['IS313']),
                $this->sub('IS414', 'E-Commerce and Digital Transformation', 4, 1, 2, 1, ['IS321']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('IS421', 'IS Capstone Project 2', 4, 2, 1, 2, ['IS411']),
                $this->sub('IS422', 'Information Systems Practicum and Internship', 4, 2, 0, 6, ['IS411']),
                $this->sub('IS423', 'Professional Issues in Information Systems', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('IS424', 'IT Service Management', 4, 2, 3, 0, ['IS412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSEMC' => [
                // 1st Year - 1st Semester
                $this->sub('EMC111', 'Introduction to Computing and Multimedia', 1, 1, 2, 1),
                $this->sub('EMC112', 'Computer Programming 1', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('ENG111', 'English Communication Skills', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('EMC121', 'Computer Programming 2', 1, 2, 2, 1, ['EMC112']),
                $this->sub('EMC122', 'Freehand and Digital Drawing', 1, 2, 1, 2, ['EMC111']),
                $this->sub('EMC123', 'Introduction to Game Design', 1, 2, 2, 1, ['EMC111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('EMC211', 'Data Structures and Algorithms', 2, 1, 2, 1, ['EMC121']),
                $this->sub('EMC212', '2D Animation and Principles of Design', 2, 1, 1, 2, ['EMC122']),
                $this->sub('EMC213', 'Computer Graphics Programming', 2, 1, 2, 1, ['EMC121']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('EMC221', '3D Modeling and Rigging', 2, 2, 1, 2, ['EMC212']),
                $this->sub('EMC222', 'Game Programming 1', 2, 2, 2, 1, ['EMC211']),
                $this->sub('EMC223', 'Digital Audio and Video Production', 2, 2, 2, 1, ['EMC122']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('EMC311', '3D Character Animation', 3, 1, 1, 2, ['EMC221']),
                $this->sub('EMC312', 'Game Programming 2 and Physics', 3, 1, 2, 1, ['EMC222']),
                $this->sub('EMC313', 'Artificial Intelligence for Games', 3, 1, 2, 1, ['EMC222']),
                $this->sub('EMC314', 'UI/UX Design for Interactive Media', 3, 1, 2, 1, ['EMC212']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('EMC321', 'Visual Effects and Compositing', 3, 2, 1, 2, ['EMC311']),
                $this->sub('EMC322', 'Multiplayer and Networked Game Development', 3, 2, 2, 1, ['EMC312']),
                $this->sub('EMC323', 'Game Production and Project Management', 3, 2, 3, 0, ['EMC312']),
                $this->sub('EMC324', 'Mobile and AR/VR Game Development', 3, 2, 2, 1, ['EMC313']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('EMC411', 'EMC Capstone Project 1', 4, 1, 1, 2, ['EMC323']),
                $this->sub('EMC412', 'Lighting, Rendering, and Portfolio Design', 4, 1, 1, 2, ['EMC321']),
                $this->sub('EMC413', 'Quality Assurance and Game Testing', 4, 1, 2, 1, ['EMC323']),
                $this->sub('EMC414', 'Media Publishing and Marketing', 4, 1, 3, 0, ['EMC323']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('EMC421', 'EMC Capstone Project 2', 4, 2, 1, 2, ['EMC411']),
                $this->sub('EMC422', 'EMC Industry Practicum and Internship', 4, 2, 0, 6, ['EMC411']),
                $this->sub('EMC423', 'Legal and Ethical Issues in Media and Games', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('EMC424', 'Creative Entrepreneurship', 4, 2, 3, 0, ['EMC414']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSBA-MM' => [
                // 1st Year - 1st Semester
                $this->sub('BA111', 'Fundamentals of Business', 1, 1, 3, 0),
                $this->sub('BA112', 'Principles of Marketing', 1, 1, 3, 0),
                $this->sub('ACC111', 'Fundamentals of Accounting', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('BA121', 'Microeconomics Theory and Practice', 1, 2, 3, 0, ['BA111']),
                $this->sub('MM121', 'Consumer Behavior', 1, 2, 3, 0, ['BA112']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('BA211', 'Human Resource Management', 2, 1, 3, 0, ['BA111']),
                $this->sub('BA212', 'Business Law (Obligations and Contracts)', 2, 1, 3, 0, ['BA111']),
                $this->sub('MM211', 'Professional Salesmanship', 2, 1, 3, 0, ['MM121']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('BA221', 'Income Taxation', 2, 2, 3, 0, ['ACC111']),
                $this->sub('BA222', 'Quantitative Techniques in Business', 2, 2, 3, 0, ['MATH111']),
                $this->sub('MM221', 'Advertising and Sales Promotion', 2, 2, 3, 0, ['MM121']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('MM311', 'Product and Brand Management', 3, 1, 3, 0, ['MM221']),
                $this->sub('MM312', 'Retail Management', 3, 1, 3, 0, ['MM211']),
                $this->sub('MM313', 'Digital and E-Commerce Marketing', 3, 1, 2, 1, ['MM221']),
                $this->sub('MM314', 'Marketing Research', 3, 1, 3, 0, ['BA222']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('MM321', 'Distribution and Channel Management', 3, 2, 3, 0, ['MM312']),
                $this->sub('MM322', 'Pricing Strategy and Analytics', 3, 2, 3, 0, ['MM311']),
                $this->sub('MM323', 'Services Marketing', 3, 2, 3, 0, ['MM311']),
                $this->sub('BA321', 'Operations Management and TQM', 3, 2, 3, 0, ['BA222']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('MM411', 'Strategic Marketing Management', 4, 1, 3, 0, ['MM314']),
                $this->sub('MM412', 'International Marketing', 4, 1, 3, 0, ['MM321']),
                $this->sub('BA411', 'Strategic Management', 4, 1, 3, 0, ['BA321']),
                $this->sub('BA412', 'Feasibility Study and Business Plan', 4, 1, 1, 2, ['MM314']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('MM421', 'Marketing Management Practicum (600 Hours)', 4, 2, 0, 6, ['MM411']),
                $this->sub('MM422', 'Special Topics in Marketing Management', 4, 2, 3, 0, ['MM411']),
                $this->sub('BA421', 'Good Governance and Social Responsibility', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('BA422', 'Entrepreneurial Management', 4, 2, 3, 0, ['BA412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSBA-FM' => [
                // 1st Year - 1st Semester
                $this->sub('BA111', 'Fundamentals of Business', 1, 1, 3, 0),
                $this->sub('ACC111', 'Fundamentals of Accounting', 1, 1, 2, 1),
                $this->sub('FM111', 'Introduction to Financial Management', 1, 1, 3, 0),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('BA121', 'Microeconomics Theory and Practice', 1, 2, 3, 0, ['BA111']),
                $this->sub('FM121', 'Financial Analysis and Reporting', 1, 2, 3, 0, ['ACC111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('BA211', 'Human Resource Management', 2, 1, 3, 0, ['BA111']),
                $this->sub('BA212', 'Business Law (Obligations and Contracts)', 2, 1, 3, 0, ['BA111']),
                $this->sub('FM211', 'Banking and Financial Institutions', 2, 1, 3, 0, ['FM111']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('BA221', 'Income Taxation', 2, 2, 3, 0, ['ACC111']),
                $this->sub('BA222', 'Quantitative Techniques in Business', 2, 2, 3, 0, ['MATH111']),
                $this->sub('FM221', 'Monetary Policy and Central Banking', 2, 2, 3, 0, ['FM211']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('FM311', 'Investment and Portfolio Management', 3, 1, 3, 0, ['FM121']),
                $this->sub('FM312', 'Credit and Collection Management', 3, 1, 3, 0, ['FM211']),
                $this->sub('FM313', 'Capital Markets and Fixed Income', 3, 1, 3, 0, ['FM221']),
                $this->sub('FM314', 'Financial Research Methods', 3, 1, 3, 0, ['BA222']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('FM321', 'Risk Management and Insurance', 3, 2, 3, 0, ['FM311']),
                $this->sub('FM322', 'Corporate Finance', 3, 2, 3, 0, ['FM311']),
                $this->sub('FM323', 'Public Finance', 3, 2, 3, 0, ['FM221']),
                $this->sub('BA321', 'Operations Management and TQM', 3, 2, 3, 0, ['BA222']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('FM411', 'Strategic Financial Management', 4, 1, 3, 0, ['FM322']),
                $this->sub('FM412', 'Global Finance and Multinational Operations', 4, 1, 3, 0, ['FM313']),
                $this->sub('BA411', 'Strategic Management', 4, 1, 3, 0, ['BA321']),
                $this->sub('BA412', 'Financial Feasibility Study', 4, 1, 1, 2, ['FM314']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('FM421', 'Financial Management Practicum (600 Hours)', 4, 2, 0, 6, ['FM411']),
                $this->sub('FM422', 'Special Topics in Financial Technology', 4, 2, 3, 0, ['FM411']),
                $this->sub('BA421', 'Good Governance and Social Responsibility', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('BA422', 'Entrepreneurial Finance', 4, 2, 3, 0, ['BA412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSBA-HRM' => [
                // 1st Year - 1st Semester
                $this->sub('BA111', 'Fundamentals of Business', 1, 1, 3, 0),
                $this->sub('ACC111', 'Fundamentals of Accounting', 1, 1, 2, 1),
                $this->sub('HRM111', 'Principles of Human Resource Management', 1, 1, 3, 0),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('BA121', 'Microeconomics Theory and Practice', 1, 2, 3, 0, ['BA111']),
                $this->sub('HRM121', 'Administrative and Office Management', 1, 2, 3, 0, ['HRM111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('HRM211', 'Recruitment and Selection', 2, 1, 3, 0, ['HRM111']),
                $this->sub('BA212', 'Business Law (Obligations and Contracts)', 2, 1, 3, 0, ['BA111']),
                $this->sub('HRM212', 'Organizational Behavior', 2, 1, 3, 0, ['HRM111']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('BA221', 'Income Taxation', 2, 2, 3, 0, ['ACC111']),
                $this->sub('BA222', 'Quantitative Techniques in Business', 2, 2, 3, 0, ['MATH111']),
                $this->sub('HRM221', 'Training and Development', 2, 2, 3, 0, ['HRM211']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('HRM311', 'Compensation Administration', 3, 1, 3, 0, ['HRM211']),
                $this->sub('HRM312', 'Labor Laws and Legislation', 3, 1, 3, 0, ['BA212']),
                $this->sub('HRM313', 'Labor Relations and Negotiations', 3, 1, 3, 0, ['HRM212']),
                $this->sub('HRM314', 'Human Resource Research', 3, 1, 3, 0, ['BA222']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('HRM321', 'Organizational Development and Change', 3, 2, 3, 0, ['HRM212']),
                $this->sub('HRM322', 'Occupational Safety and Health', 3, 2, 3, 0, ['HRM312']),
                $this->sub('HRM323', 'HR Information Systems and Analytics', 3, 2, 2, 1, ['HRM311']),
                $this->sub('BA321', 'Operations Management and TQM', 3, 2, 3, 0, ['BA222']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('HRM411', 'Strategic Human Resource Management', 4, 1, 3, 0, ['HRM321']),
                $this->sub('HRM412', 'International Human Resource Management', 4, 1, 3, 0, ['HRM321']),
                $this->sub('BA411', 'Strategic Management', 4, 1, 3, 0, ['BA321']),
                $this->sub('BA412', 'Feasibility Study and Business Plan', 4, 1, 1, 2, ['HRM314']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('HRM421', 'Human Resource Management Practicum (600 Hours)', 4, 2, 0, 6, ['HRM411']),
                $this->sub('HRM422', 'Special Topics in Talent Management', 4, 2, 3, 0, ['HRM411']),
                $this->sub('BA421', 'Good Governance and Social Responsibility', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('BA422', 'Entrepreneurial Management', 4, 2, 3, 0, ['BA412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSBA-OM' => [
                // 1st Year - 1st Semester
                $this->sub('BA111', 'Fundamentals of Business', 1, 1, 3, 0),
                $this->sub('ACC111', 'Fundamentals of Accounting', 1, 1, 2, 1),
                $this->sub('OM111', 'Introduction to Operations Management', 1, 1, 3, 0),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('BA121', 'Microeconomics Theory and Practice', 1, 2, 3, 0, ['BA111']),
                $this->sub('OM121', 'Productivity and Quality Tools', 1, 2, 3, 0, ['OM111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('BA211', 'Human Resource Management', 2, 1, 3, 0, ['BA111']),
                $this->sub('BA212', 'Business Law (Obligations and Contracts)', 2, 1, 3, 0, ['BA111']),
                $this->sub('OM211', 'Inventory Management and Control', 2, 1, 3, 0, ['OM111']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('BA221', 'Income Taxation', 2, 2, 3, 0, ['ACC111']),
                $this->sub('BA222', 'Quantitative Techniques in Business', 2, 2, 3, 0, ['MATH111']),
                $this->sub('OM221', 'Logistics and Supply Chain Management', 2, 2, 3, 0, ['OM211']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('OM311', 'Total Quality Management', 3, 1, 3, 0, ['OM121']),
                $this->sub('OM312', 'Facilities Planning and Layout', 3, 1, 2, 1, ['OM221']),
                $this->sub('OM313', 'Costing and Pricing in Operations', 3, 1, 3, 0, ['ACC111']),
                $this->sub('OM314', 'Operations Research', 3, 1, 3, 0, ['BA222']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('OM321', 'Project Management', 3, 2, 3, 0, ['OM314']),
                $this->sub('OM322', 'Warehouse and Distribution Management', 3, 2, 3, 0, ['OM221']),
                $this->sub('OM323', 'Enterprise Resource Planning for Operations', 3, 2, 2, 1, ['OM211']),
                $this->sub('OM324', 'Environmental and Safety Management', 3, 2, 3, 0, ['OM311']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('OM411', 'Strategic Operations Management', 4, 1, 3, 0, ['OM321']),
                $this->sub('OM412', 'Global Supply Chain Operations', 4, 1, 3, 0, ['OM322']),
                $this->sub('BA411', 'Strategic Management', 4, 1, 3, 0, ['OM321']),
                $this->sub('BA412', 'Feasibility Study and Business Plan', 4, 1, 1, 2, ['OM314']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('OM421', 'Operations Management Practicum (600 Hours)', 4, 2, 0, 6, ['OM411']),
                $this->sub('OM422', 'Special Topics in Lean Six Sigma', 4, 2, 3, 0, ['OM411']),
                $this->sub('BA421', 'Good Governance and Social Responsibility', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('BA422', 'Entrepreneurial Management', 4, 2, 3, 0, ['BA412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSHM' => [
                // 1st Year - 1st Semester
                $this->sub('HM111', 'Introduction to Hospitality Management', 1, 1, 3, 0),
                $this->sub('HM112', 'Food Service Operations', 1, 1, 2, 1),
                $this->sub('HM113', 'Front Office Operations', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('HM121', 'Risk Management as Applied to Safety, Security, and Sanitation', 1, 2, 3, 0, ['HM112']),
                $this->sub('HM122', 'Housekeeping Operations', 1, 2, 2, 1, ['HM111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('HM211', 'Kitchen Essentials and Basic Food Preparation', 2, 1, 1, 2, ['HM121']),
                $this->sub('HM212', 'Philippine Culture and Tourism Geography', 2, 1, 3, 0, ['HM111']),
                $this->sub('HM213', 'Micro Perspective of Tourism and Hospitality', 2, 1, 3, 0, ['HM111']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('HM221', 'Supply Chain Management in Hospitality', 2, 2, 3, 0, ['HM211']),
                $this->sub('HM222', 'Beverage and Bar Management', 2, 2, 2, 1, ['HM112']),
                $this->sub('HM223', 'Quality Service Management in Hospitality', 2, 2, 3, 0, ['HM213']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('HM311', 'Hospitality Property Management Systems', 3, 1, 2, 1, ['HM113']),
                $this->sub('HM312', 'Legal Aspects in Tourism and Hospitality', 3, 1, 3, 0, ['HM213']),
                $this->sub('HM313', 'MICE and Events Management', 3, 1, 2, 1, ['HM223']),
                $this->sub('HM314', 'Research in Hospitality Management', 3, 1, 3, 0, ['MATH111']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('HM321', 'Hospitality Accounting and Finance', 3, 2, 3, 0, ['MATH111']),
                $this->sub('HM322', 'Multicultural Diversity in Workplace for Hospitality', 3, 2, 3, 0, ['HM223']),
                $this->sub('HM323', 'Ergonomics and Facilities Planning for Hospitality', 3, 2, 2, 1, ['HM122']),
                $this->sub('HM324', 'Hospitality Marketing and Revenue Management', 3, 2, 3, 0, ['HM311']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('HM411', 'Strategic Management in Hospitality', 4, 1, 3, 0, ['HM324']),
                $this->sub('HM412', 'Cruise Ship and Resort Management', 4, 1, 3, 0, ['HM311']),
                $this->sub('HM413', 'Entrepreneurship in Tourism and Hospitality', 4, 1, 3, 0, ['HM321']),
                $this->sub('HM414', 'Foreign Language for Hospitality (Mandarin)', 4, 1, 3, 0, ['ENG111']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('HM421', 'Hotel and Lodging Operations Practicum (600 Hours)', 4, 2, 0, 6, ['HM411']),
                $this->sub('HM422', 'Food and Beverage Service Practicum', 4, 2, 1, 2, ['HM222']),
                $this->sub('HM423', 'Professional Development and Applied Ethics', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('HM424', 'Contemporary Trends in Global Hospitality', 4, 2, 3, 0, ['HM411']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSTM' => [
                // 1st Year - 1st Semester
                $this->sub('HM111', 'Macro Perspective of Tourism and Hospitality', 1, 1, 3, 0),
                $this->sub('TM112', 'Risk Management as Applied to Safety, Security, and Sanitation', 1, 1, 3, 0),
                $this->sub('TM113', 'Philippine Culture and Tourism Geography', 1, 1, 3, 0),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('TM121', 'Micro Perspective of Tourism and Hospitality', 1, 2, 3, 0, ['HM111']),
                $this->sub('TM122', 'Global Culture and Tourism Geography', 1, 2, 3, 0, ['TM113']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('TM211', 'Tour and Travel Management', 2, 1, 2, 1, ['TM121']),
                $this->sub('TM212', 'Quality Service Management in Tourism', 2, 1, 3, 0, ['TM121']),
                $this->sub('TM213', 'Sustainable Tourism', 2, 1, 3, 0, ['TM122']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('TM221', 'Tour Guiding and Escorting', 2, 2, 2, 1, ['TM211']),
                $this->sub('TM222', 'Global Distribution Systems and Ticketing', 2, 2, 2, 1, ['TM211']),
                $this->sub('TM223', 'Airline and Cruise Operations Management', 2, 2, 3, 0, ['TM211']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('TM311', 'Tourism Policy, Planning, and Development', 3, 1, 3, 0, ['TM213']),
                $this->sub('TM312', 'Legal Aspects in Tourism and Hospitality', 3, 1, 3, 0, ['TM212']),
                $this->sub('TM313', 'MICE and Destination Events Management', 3, 1, 2, 1, ['TM211']),
                $this->sub('TM314', 'Research in Tourism Management', 3, 1, 3, 0, ['MATH111']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('TM321', 'Destination Marketing and Branding', 3, 2, 3, 0, ['TM311']),
                $this->sub('TM322', 'Multicultural Diversity in Tourism Workplace', 3, 2, 3, 0, ['TM212']),
                $this->sub('TM323', 'Heritage and Ecotourism Management', 3, 2, 3, 0, ['TM213']),
                $this->sub('TM324', 'Foreign Language for Tourism 1', 3, 2, 3, 0, ['ENG111']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('TM411', 'Strategic Tourism Management', 4, 1, 3, 0, ['TM321']),
                $this->sub('TM412', 'Entrepreneurship in Tourism and Hospitality', 4, 1, 3, 0, ['TM321']),
                $this->sub('TM413', 'Foreign Language for Tourism 2', 4, 1, 3, 0, ['TM324']),
                $this->sub('TM414', 'Transportation and Airport Operations', 4, 1, 3, 0, ['TM223']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('TM421', 'Tourism Industry Practicum and Internship (600 Hours)', 4, 2, 0, 6, ['TM411']),
                $this->sub('TM422', 'Travel Agency Operations Practicum', 4, 2, 1, 2, ['TM222']),
                $this->sub('TM423', 'Professional Development and Applied Ethics', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('TM424', 'Contemporary Issues in Global Tourism', 4, 2, 3, 0, ['TM411']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSHRM' => [
                // 1st Year - 1st Semester
                $this->sub('HR111', 'Fundamentals of Hotel and Restaurant Operations', 1, 1, 3, 0),
                $this->sub('HR112', 'Food and Beverage Service Procedures', 1, 1, 2, 1),
                $this->sub('HR113', 'Culinary Fundamentals and Sanitation', 1, 1, 2, 1),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('HR121', 'Front Office and Guest Relations', 1, 2, 2, 1, ['HR111']),
                $this->sub('HR122', 'Housekeeping and Laundry Operations', 1, 2, 2, 1, ['HR111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('HR211', 'Commercial Cooking and Menu Planning', 2, 1, 1, 2, ['HR113']),
                $this->sub('HR212', 'Bar and Beverage Operations', 2, 1, 2, 1, ['HR112']),
                $this->sub('HR213', 'Baking and Pastry Production', 2, 1, 1, 2, ['HR113']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('HR221', 'Banquet, Catering, and Function Management', 2, 2, 2, 1, ['HR211']),
                $this->sub('HR222', 'Purchasing and Cost Control in Food Service', 2, 2, 3, 0, ['HR211']),
                $this->sub('HR223', 'Hospitality Quality Service Management', 2, 2, 3, 0, ['HR121']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('HR311', 'Restaurant Concept and Design', 3, 1, 2, 1, ['HR221']),
                $this->sub('HR312', 'Hospitality Law and Labor Relations', 3, 1, 3, 0, ['HR223']),
                $this->sub('HR313', 'Property Management and Reservation Systems', 3, 1, 2, 1, ['HR121']),
                $this->sub('HR314', 'Hospitality Research Methods', 3, 1, 3, 0, ['MATH111']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('HR321', 'Hotel and Restaurant Accounting', 3, 2, 3, 0, ['HR222']),
                $this->sub('HR322', 'Conventions and Events Management', 3, 2, 2, 1, ['HR221']),
                $this->sub('HR323', 'Asian and International Cuisine', 3, 2, 1, 2, ['HR211']),
                $this->sub('HR324', 'Hospitality Sales and Marketing', 3, 2, 3, 0, ['HR223']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('HR411', 'Strategic Hotel and Restaurant Management', 4, 1, 3, 0, ['HR324']),
                $this->sub('HR412', 'Resort and Club Management', 4, 1, 3, 0, ['HR313']),
                $this->sub('HR413', 'Hospitality Feasibility Study', 4, 1, 1, 2, ['HR314']),
                $this->sub('HR414', 'Foreign Language for Hospitality', 4, 1, 3, 0, ['ENG111']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('HR421', 'Hotel and Restaurant Practicum (600 Hours)', 4, 2, 0, 6, ['HR411']),
                $this->sub('HR422', 'Fine Dining and Culinary Practicum', 4, 2, 1, 2, ['HR323']),
                $this->sub('HR423', 'Professional Ethics and Leadership in Hospitality', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('HR424', 'Franchising and Restaurant Entrepreneurship', 4, 2, 3, 0, ['HR413']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],

            'BSCA' => [
                // 1st Year - 1st Semester
                $this->sub('CA111', 'Culinary Fundamentals and Knife Skills', 1, 1, 1, 2),
                $this->sub('CA112', 'Food Safety, Hygiene, and Sanitation', 1, 1, 3, 0),
                $this->sub('CA113', 'Nutrition and Food Science', 1, 1, 3, 0),
                $this->sub('MATH111', 'College Algebra', 1, 1, 3, 0),
                $this->sub('PATHFIT1', 'Physical Activities Towards Health and Fitness 1', 1, 1, 1, 1),
                // 1st Year - 2nd Semester
                $this->sub('ENG111', 'English Communication Skills', 1, 2, 3, 0),
                $this->sub('CA121', 'Stocks, Sauces, and Soups', 1, 2, 1, 2, ['CA111']),
                $this->sub('CA122', 'Fundamentals of Baking and Pastry', 1, 2, 1, 2, ['CA111']),
                $this->sub('GE101', 'Understanding the Self', 1, 2, 3, 0),
                $this->sub('PATHFIT2', 'Physical Activities Towards Health and Fitness 2', 1, 2, 1, 1, ['PATHFIT1']),
                // 2nd Year - 1st Semester
                $this->sub('CA211', 'Meat, Poultry, and Seafood Fabrication', 2, 1, 1, 2, ['CA121']),
                $this->sub('CA212', 'Garde Manger and Cold Kitchen', 2, 1, 1, 2, ['CA121']),
                $this->sub('CA213', 'Philippine Regional Gastronomy', 2, 1, 1, 2, ['CA121']),
                $this->sub('GE102', 'Readings in Philippine History', 2, 1, 3, 0),
                $this->sub('PATHFIT3', 'Physical Activities Towards Health and Fitness 3', 2, 1, 1, 1, ['PATHFIT2']),
                // 2nd Year - 2nd Semester
                $this->sub('CA221', 'Asian and Oriental Cuisine', 2, 2, 1, 2, ['CA211']),
                $this->sub('CA222', 'European and Mediterranean Cuisine', 2, 2, 1, 2, ['CA211']),
                $this->sub('CA223', 'Artisan Breads and Viennoiserie', 2, 2, 1, 2, ['CA122']),
                $this->sub('ETHICS', 'Ethics', 2, 2, 3, 0),
                $this->sub('PATHFIT4', 'Physical Activities Towards Health and Fitness 4', 2, 2, 1, 1, ['PATHFIT3']),
                // 3rd Year - 1st Semester
                $this->sub('CA311', 'Advanced Patisserie and Confectionery', 3, 1, 1, 2, ['CA223']),
                $this->sub('CA312', 'Menu Engineering, Purchasing, and Cost Control', 3, 1, 3, 0, ['MATH111']),
                $this->sub('CA313', 'Beverage Studies and Wine Pairing', 3, 1, 2, 1, ['CA222']),
                $this->sub('CA314', 'Culinary Research and Food Product Development', 3, 1, 2, 1, ['CA113']),
                $this->sub('RIZAL', 'Life and Works of Rizal', 3, 1, 3, 0),
                // 3rd Year - 2nd Semester
                $this->sub('CA321', 'Banquet and Volume Catering Production', 3, 2, 1, 2, ['CA222']),
                $this->sub('CA322', 'Kitchen Organization and Dining Room Service', 3, 2, 2, 1, ['CA312']),
                $this->sub('CA323', 'Contemporary Plating and Molecular Gastronomy', 3, 2, 1, 2, ['CA311']),
                $this->sub('CA324', 'Food Service Law and Sustainability', 3, 2, 3, 0, ['CA112']),
                $this->sub('GE103', 'The Contemporary World', 3, 2, 3, 0),
                // 4th Year - 1st Semester
                $this->sub('CA411', 'Restaurant Operations Simulation', 4, 1, 1, 2, ['CA321']),
                $this->sub('CA412', 'Culinary Business Plan and Concept Development', 4, 1, 3, 0, ['CA312']),
                $this->sub('CA413', 'Chocolate, Sugar Art, and Showpieces', 4, 1, 1, 2, ['CA311']),
                $this->sub('CA414', 'Culinary Marketing and Food Styling', 4, 1, 2, 1, ['CA323']),
                $this->sub('GE104', 'Science, Technology, and Society', 4, 1, 3, 0),
                // 4th Year - 2nd Semester
                $this->sub('CA421', 'Culinary Arts Industry Practicum (600 Hours)', 4, 2, 0, 6, ['CA411']),
                $this->sub('CA422', 'Chef\'s Table Capstone Showcase', 4, 2, 1, 2, ['CA411']),
                $this->sub('CA423', 'Professional Ethics and Kitchen Leadership', 4, 2, 3, 0, ['ETHICS']),
                $this->sub('CA424', 'Food Entrepreneurship and Gastronomy Trends', 4, 2, 3, 0, ['CA412']),
                $this->sub('GE105', 'Art Appreciation', 4, 2, 3, 0),
            ],
        ];
    }
}
