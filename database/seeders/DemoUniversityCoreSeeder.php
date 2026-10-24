<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\GradingSystemService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DemoUniversityCoreSeeder extends Seeder
{
    private const SCHOOL_ID = 8;

    private const INSTITUTION_ID = 1;

    private const YEAR = '2026 - 2027';

    private const SEMESTER = 1;

    private const BASE_STUDENT_ID = 20260087;

    private const STUDENT_DOMAIN = '@students.koakademy.edu';

    private const FACULTY_DOMAIN = '@faculty.koakademy.edu';

    /** @var list<string> */
    private array $firstNames = [
        'Andrea', 'Miguel', 'Sofia', 'Gabriel', 'Isabella', 'Rafael', 'Camila', 'Lucas', 'Bianca', 'Mateo',
        'Patricia', 'Daniel', 'Angela', 'Marco', 'Jasmine', 'Eduardo', 'Kristine', 'Paolo', 'Beatrice', 'Nathaniel',
        'Clarisse', 'Joaquin', 'Diana', 'Francis', 'Erika', 'Alfonso', 'Mikaela', 'Carlo', 'Denise', 'Ramon',
        'Elaine', 'Victor', 'Trisha', 'Emilio', 'Hannah', 'Jose', 'Lorraine', 'Antonio', 'Cristina', 'Manuel',
        'Alyssa', 'Renato', 'Kimberly', 'Ferdinand', 'Marielle', 'Enrique', 'Jasmine', 'Lorenzo', 'Alexa', 'Rogelio',
        'Nadine', 'Samuel', 'Pauline', 'Alfredo', 'Divine', 'Benedict', 'Celine', 'Gregorio', 'Trina', 'Vincent',
    ];

    /** @var list<string> */
    private array $lastNames = [
        'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Aquino',
        'Ramos', 'Del Rosario', 'Navarro', 'Salazar', 'Domingo', 'Castillo', 'Flores', 'Gonzales', 'Pascual', 'Rivera',
        'Aguilar', 'Marquez', 'Fernandez', 'De Leon', 'Soriano', 'Velasco', 'Padilla', 'Manalo', 'Escobar', 'Valdez',
        'Tolentino', 'Roxas', 'Alvarez', 'Mercado', 'Rosales', 'Panganiban', 'Lopez', 'Diaz', 'Hernandez', 'Perez',
        'Gomez', 'Morales', 'Jimenez', 'Alonzo', 'Cabrera', 'Dela Torre', 'Estrada', 'Fajardo', 'Galang', 'Hilario',
        'Ilagan', 'Javier', 'Katigbak', 'Lacson', 'Magbanua', 'Nolasco', 'Ocampo', 'Pineda', 'Quizon', 'Recto',
    ];

    private string $passwordHash = '';

    private string $now = '';

    /** @var array<int, object> */
    private array $courses = [];

    /** @var array<string, object> */
    private array $courseByCode = [];

    /** @var array<int, object> */
    private array $departments = [];

    /** @var array<int, int> */
    private array $roomIds = [];

    /** @var list<int> */
    private array $adminIds = [];

    private int $cashierId = 1;

    /** @var array<string, int> */
    private array $roleIds = [];

    public function run(): void
    {
        $this->passwordHash = Hash::make('password');
        $this->now = now()->toDateTimeString();

        $this->cleanup();
        $this->consolidateTenant();

        $this->courses = DB::table('courses')->orderBy('id')->get()->all();
        foreach ($this->courses as $course) {
            $this->courseByCode[$course->code] = $course;
        }

        $this->departments = DB::table('departments')->orderBy('id')->get()->all();
        $this->roomIds = DB::table('rooms')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->adminIds = DB::table('users')->whereIn('role', ['super_admin', 'admin', 'developer'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->cashierId = (int) (DB::table('users')->where('role', 'cashier')->value('id') ?? ($this->adminIds[0] ?? 1));
        $this->roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();

        $this->seedFaculty();
        $this->seedStudents();
        $this->seedPersonalInformation();
        $this->seedStudentAccounts();
        $this->seedShsStudents();
        $this->seedGradingPolicies();
        $this->seedFeeSchedules();
        $this->seedEnrollmentDiscounts();
        $this->seedCurriculumAndClasses();
        $this->seedEnrollmentPipeline();

        DB::table('general_settings')->update([
            'semester' => self::SEMESTER,
            'curriculum_year' => self::YEAR,
            'updated_at' => $this->now,
        ]);

        DB::table('id_sequences')->where('key', 'student')->update([
            'next_number' => self::BASE_STUDENT_ID + 500,
            'updated_at' => $this->now,
        ]);

        $this->command?->info('Core demo data ready: '.DB::table('students')->count().' students, '.DB::table('faculty')->count().' faculty, '.DB::table('classes')->count().' classes.');
    }

    private function consolidateTenant(): void
    {
        $this->command?->info('Consolidating demo data under the university tenant...');

        $nonTargetDefinitionIds = DB::table('faculty_custom_field_definitions')
            ->where('school_id', '!=', self::SCHOOL_ID)
            ->pluck('id');

        if ($nonTargetDefinitionIds->isNotEmpty()) {
            DB::table('faculty_custom_field_values')
                ->whereIn('faculty_custom_field_definition_id', $nonTargetDefinitionIds)
                ->delete();
            DB::table('faculty_custom_field_definitions')->whereIn('id', $nonTargetDefinitionIds)->delete();
        }

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (in_array($name, ['schools', 'grading_policies', 'faculty_custom_field_definitions'], true)) {
                continue;
            }

            if (! Schema::hasColumn($name, 'school_id')) {
                continue;
            }

            DB::table($name)
                ->where(fn ($query) => $query->whereBetween('school_id', [1, 7])->orWhereNull('school_id'))
                ->update(['school_id' => self::SCHOOL_ID]);
        }

        DB::table('user_settings')->update([
            'active_school_id' => self::SCHOOL_ID,
            'semester' => self::SEMESTER,
            'school_year_start' => 2026,
        ]);

        DB::table('schools')
            ->where('id', '!=', self::SCHOOL_ID)
            ->whereNull('deleted_at')
            ->update(['is_active' => 0, 'deleted_at' => $this->now]);
    }

    private function cleanup(): void
    {
        $this->command?->info('Cleaning previous demo data...');

        foreach ([
            'class_attendance_records',
            'class_attendance_sessions',
            'class_post_submissions',
            'class_posts',
            'class_schedule_change_student_notifications',
            'class_schedule_changes',
            'faculty_deadlines',
            'faculty_custom_field_values',
            'event_reminders',
            'event_rsvps',
            'events',
            'bookings',
            'resource_bookings',
            'help_ticket_replies',
            'help_tickets',
            'announcements',
            'notifications',
            'newsletter_subscriptions',
            'onboarding_progress',
            'onboarding_dismissals',
            'taggables',
            'tags',
            'mails',
            'inventory_borrowings',
            'inventory_stock_movements',
            'inventory_product_histories',
            'inventory_products',
            'inventory_suppliers',
            'inventory_categories',
            'library_borrow_records',
            'library_bookmarks',
            'library_user_book_states',
            'library_research_paper_student',
            'library_research_papers',
            'library_book_digital_editions',
            'library_books',
            'library_authors',
            'library_categories',
            'medical_records',
            'shs_students',
            'assessment_export_items',
            'assessment_exports',
            'financial_document_deliveries',
            'financial_document_issuances',
            'statement_of_account_issuances',
            'student_tuition_update_request_events',
            'student_tuition_update_requests',
            'tuition_adjustments',
            'tuition_adjustment_batches',
            'payment_allocations',
            'student_transactions',
            'admin_transactions',
            'additional_fees',
            'assessment_revisions',
            'student_tuition_installments',
            'student_tuition',
            'enrollment_requirements',
            'enrollment_workflow_events',
            'subject_enrollments',
            'class_enrollments',
            'student_enrollment',
            'student_clearances',
            'student_statuses',
            'student_id_change_logs',
            'applicant_cancellations',
            'transactions',
            'grading_policy_versions',
            'grading_policies',
            'fee_schedule_versions',
        ] as $table) {
            DB::table($table)->delete();
        }

        $demoFormIds = DB::table('forms')->where('slug', 'like', 'demo-%')->pluck('id');
        if ($demoFormIds->isNotEmpty()) {
            $responseIds = DB::table('form_responses')->whereIn('form_id', $demoFormIds)->pluck('id');
            if ($responseIds->isNotEmpty()) {
                DB::table('form_response_revisions')->whereIn('form_response_id', $responseIds)->delete();
                DB::table('form_response_links')->whereIn('form_response_id', $responseIds)->delete();
            }
            DB::table('form_invitations')->whereIn('form_id', $demoFormIds)->delete();
            DB::table('form_audit_events')->whereIn('form_id', $demoFormIds)->delete();
            DB::table('form_responses')->whereIn('form_id', $demoFormIds)->delete();
            DB::table('form_fields')->whereIn('form_id', $demoFormIds)->delete();
            DB::table('forms')->whereIn('id', $demoFormIds)->delete();
        }

        DB::table('form_templates')->where('name', 'like', 'Demo %')->delete();

        DB::table('classes')->whereIn('school_year', [self::YEAR, str_replace(' ', '', self::YEAR)])->delete();
        DB::table('subject')->where('code', 'like', 'CUR-%')->delete();

        DB::table('students_personal_info')->delete();
        DB::table('student_parents_info')->delete();
        DB::table('student_education_info')->delete();
        DB::table('student_contacts')->delete();
        DB::table('document_locations')->delete();

        $userIds = DB::table('users')
            ->where('email', 'like', '%'.self::STUDENT_DOMAIN)
            ->orWhere('email', 'like', '%'.self::FACULTY_DOMAIN)
            ->pluck('id');

        if ($userIds->isNotEmpty()) {
            DB::table('model_has_roles')->whereIn('model_id', $userIds)->delete();
            DB::table('accounts')->whereIn('email', DB::table('users')->whereIn('id', $userIds)->pluck('email'))->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        }

        DB::table('accounts')->where('email', 'like', '%'.self::STUDENT_DOMAIN)->orWhere('email', 'like', '%'.self::FACULTY_DOMAIN)->delete();
        DB::table('students')->where('student_id', '>=', self::BASE_STUDENT_ID)->delete();
        DB::table('faculty')->where('faculty_id_number', 'like', 'FAC-2026-%')->delete();
    }

    private function seedFaculty(): void
    {
        $this->command?->info('Creating faculty roster...');
        $existing = DB::table('faculty')->count();
        $target = 80;
        $toCreate = max(0, $target - $existing);

        $ranks = [
            ['role' => 'professor', 'position' => 'Professor I'],
            ['role' => 'associate_professor', 'position' => 'Associate Professor I'],
            ['role' => 'assistant_professor', 'position' => 'Assistant Professor I'],
            ['role' => 'instructor', 'position' => 'Instructor I'],
            ['role' => 'part_time_faculty', 'position' => 'Part-time Lecturer'],
        ];

        $departments = $this->departments;

        for ($i = 0; $i < $toCreate; $i++) {
            $first = $this->firstNames[($existing + $i) % count($this->firstNames)];
            $last = $this->lastNames[($existing + $i * 7) % count($this->lastNames)];
            $email = strtolower($first.'.'.$last.($i + 1).self::FACULTY_DOMAIN);
            $department = $departments[$i % count($departments)];
            $rank = $ranks[$i % count($ranks)];
            $facultyId = (string) Str::uuid();
            $fullName = $first.' '.$last;
            $phone = '0917'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT);

            DB::table('faculty')->insert([
                'id' => $facultyId,
                'faculty_id_number' => 'FAC-2026-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'first_name' => $first,
                'last_name' => $last,
                'middle_name' => $this->lastNames[($i * 3) % count($this->lastNames)],
                'email' => $email,
                'email_verified_at' => $this->now,
                'password' => $this->passwordHash,
                'phone_number' => $phone,
                'department' => $department->name,
                'department_id' => $department->id,
                'school_id' => self::SCHOOL_ID,
                'position' => $rank['position'],
                'status' => 'active',
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'age' => 28 + ($i % 25),
                'birth_date' => Carbon::now()->subYears(28 + ($i % 25))->subDays($i % 300)->toDateString(),
                'address_line1' => ($i + 10).' Faculty Lane, KoAkademy Campus',
                'biography' => 'Faculty member of the '.$department->name.' with expertise in teaching, research, and community extension.',
                'education' => json_encode([['degree' => 'Doctor of Philosophy', 'school' => 'University of the Philippines', 'year' => 2015 + ($i % 8)]]),
                'courses_taught' => json_encode([]),
                'office_hours' => json_encode(['Monday 09:00-11:00', 'Wednesday 14:00-16:00']),
                'date_employed' => Carbon::now()->subYears(1 + ($i % 10))->toDateString(),
                'full_name' => $fullName,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $userId = DB::table('users')->insertGetId([
                'name' => $fullName,
                'email' => $email,
                'role' => $rank['role'],
                'email_verified_at' => $this->now,
                'password' => $this->passwordHash,
                'school_id' => self::SCHOOL_ID,
                'department_id' => $department->id,
                'faculty_id_number' => 'FAC-2026-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'phone' => $phone,
                'address' => ($i + 10).' Faculty Lane',
                'city' => 'Quezon City',
                'state' => 'Metro Manila',
                'country' => 'Philippines',
                'postal_code' => '1100',
                'bio' => 'Faculty member and researcher.',
                'position' => $rank['position'],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            if (isset($this->roleIds[$rank['role']])) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $this->roleIds[$rank['role']],
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }

            DB::table('accounts')->insert([
                'name' => $last.', '.$first,
                'email' => $email,
                'password' => $this->passwordHash,
                'role' => 'faculty',
                'is_active' => 1,
                'is_login' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedStudents(): void
    {
        $this->command?->info('Creating student population...');
        $existing = DB::table('students')->count();
        $toCreate = max(0, 500 - $existing);

        $statusPool = array_merge(
            array_fill(0, (int) round($toCreate * 0.905), 'enrolled'),
            array_fill(0, (int) round($toCreate * 0.024), 'applicant'),
            array_fill(0, (int) round($toCreate * 0.014), 'on_leave'),
            array_fill(0, (int) round($toCreate * 0.014), 'withdrawn'),
            array_fill(0, (int) round($toCreate * 0.010), 'dropped'),
            array_fill(0, (int) round($toCreate * 0.010), 'graduated'),
            array_fill(0, (int) round($toCreate * 0.012), 'transferred'),
            array_fill(0, (int) round($toCreate * 0.011), 'rejected'),
        );
        shuffle($statusPool);

        $typePool = array_merge(
            array_fill(0, (int) round($toCreate * 0.845), 'college'),
            array_fill(0, (int) round($toCreate * 0.121), 'tesda'),
            array_fill(0, (int) round($toCreate * 0.034), 'shs'),
        );
        shuffle($typePool);

        $courseIds = array_map(fn ($course) => (int) $course->id, $this->courses);
        $tracks = DB::table('shs_tracks')->get();
        $strands = DB::table('shs_strands')->get();

        $rows = [];
        for ($i = 0; $i < $toCreate; $i++) {
            $type = $typePool[$i] ?? 'college';
            $status = $statusPool[$i] ?? 'enrolled';
            $first = $this->firstNames[($i * 3) % count($this->firstNames)];
            $last = $this->lastNames[($i * 5) % count($this->lastNames)];
            $studentId = self::BASE_STUDENT_ID + $i;
            $age = $status === 'graduated' ? 22 : 16 + ($i % 8);
            $academicYear = $status === 'graduated' ? 4 : 1 + ($i % 4);
            $courseId = $type === 'shs' ? null : $courseIds[$i % count($courseIds)];
            $track = null;
            $strand = null;
            if ($type === 'shs') {
                $track = $tracks->isNotEmpty() ? $tracks[$i % $tracks->count()] : null;
                $strand = $strands->isNotEmpty() ? $strands[$i % $strands->count()] : null;
            }

            $rows[] = [
                'institution_id' => self::INSTITUTION_ID,
                'student_id' => $studentId,
                'lrn' => (string) (100000000000 + $studentId),
                'student_type' => $type,
                'first_name' => $first,
                'middle_name' => $this->lastNames[($i * 11) % count($this->lastNames)],
                'last_name' => $last,
                'suffix' => $i % 17 === 0 ? 'Jr.' : null,
                'email' => strtolower($first.'.'.$last.$studentId.self::STUDENT_DOMAIN),
                'phone' => '09'.str_pad((string) (100000000 + ($i * 7919) % 899999999), 9, '0', STR_PAD_LEFT),
                'birth_date' => Carbon::now()->subYears($age)->subDays(($i * 13) % 360)->toDateString(),
                'gender' => ['male', 'female', 'female', 'male', 'prefer_not_to_say', 'other'][$i % 6],
                'civil_status' => 'single',
                'nationality' => 'filipino',
                'religion' => ['catholic', 'christian', 'muslim', 'iglesia_ni_cristo', 'others'][$i % 5],
                'address' => ($i + 1).' '.$this->lastNames[$i % count($this->lastNames)].' Street, Barangay '.(($i % 20) + 1).', Quezon City',
                'emergency_contact' => 'Parent: '.$this->firstNames[($i + 5) % count($this->firstNames)].' '.$last.' - 0918'.str_pad((string) (2000000 + $i), 7, '0', STR_PAD_LEFT),
                'status' => $status,
                'age' => $age,
                'contacts' => json_encode([
                    'mobile' => '09'.str_pad((string) (300000000 + $i), 9, '0', STR_PAD_LEFT),
                    'facebook' => strtolower($first.'.'.$last.($i + 1)),
                    'messenger' => null,
                    'viber' => '09'.str_pad((string) (400000000 + $i), 9, '0', STR_PAD_LEFT),
                    'whatsapp' => null,
                ]),
                'course_id' => $courseId,
                'academic_year' => $academicYear,
                'remarks' => null,
                'school_id' => self::SCHOOL_ID,
                'clearance_status' => $i % 3 === 0 ? 'not_cleared' : 'cleared',
                'ethnicity' => null,
                'city_of_origin' => 'Quezon City',
                'province_of_origin' => 'Metro Manila',
                'region_of_origin' => 'National Capital Region',
                'is_indigenous_person' => $i % 37 === 0 ? 1 : 0,
                'is_pwd' => $i % 29 === 0 ? 1 : 0,
                'is_solo_parent' => $i % 41 === 0 ? 1 : 0,
                'is_senior_citizen' => 0,
                'is_magna_carta' => $i % 31 === 0 ? 1 : 0,
                'is_underprivileged' => $i % 9 === 0 ? 1 : 0,
                'is_first_generation' => $i % 6 === 0 ? 1 : 0,
                'family_income_bracket' => ['A', 'B', 'C', 'D', 'E'][$i % 5],
                'scholarship_type' => $i % 11 === 0 ? 'institutional' : 'none',
                'scholarship_details' => $i % 11 === 0 ? 'Institutional academic scholarship grant' : null,
                'shs_track_id' => $track?->id,
                'shs_strand_id' => $strand?->id,
                'privacy_consent_at' => $status === 'enrolled' ? $this->now : null,
                'marketing_consent' => $i % 4 === 0 ? 1 : 0,
                'marketing_consent_at' => $i % 4 === 0 ? $this->now : null,
                'profile_details' => json_encode(['nickname' => $first, 'languages' => ['English', 'Filipino']]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $demoStudents = [
            [20261001, 'John', 'Student', 'john.student@student.koakademy.edu', 1, 1],
            [20261002, 'Jane', 'Doe', 'jane.doe@student.koakademy.edu', 4, 2],
            [20261003, 'Master', 'Student', 'master.student@student.koakademy.edu', 6, 4],
        ];
        foreach ($demoStudents as [$studentId, $first, $last, $email, $courseId, $year]) {
            $rows[] = [
                'institution_id' => self::INSTITUTION_ID,
                'student_id' => $studentId,
                'lrn' => (string) (200000000000 + $studentId),
                'student_type' => 'college',
                'first_name' => $first,
                'middle_name' => 'Demo',
                'last_name' => $last,
                'suffix' => null,
                'email' => $email,
                'phone' => '09170000000',
                'birth_date' => Carbon::now()->subYears(18 + $year)->toDateString(),
                'gender' => $first === 'Jane' ? 'female' : 'male',
                'civil_status' => 'single',
                'nationality' => 'filipino',
                'religion' => 'catholic',
                'address' => 'KoAkademy Demo University, Quezon City',
                'emergency_contact' => 'Registrar Office - 09170000001',
                'status' => 'enrolled',
                'age' => 18 + $year,
                'contacts' => json_encode(['mobile' => '09170000000', 'facebook' => null, 'messenger' => null, 'viber' => null, 'whatsapp' => null]),
                'course_id' => $courseId,
                'academic_year' => $year,
                'remarks' => null,
                'school_id' => self::SCHOOL_ID,
                'clearance_status' => 'cleared',
                'ethnicity' => null,
                'city_of_origin' => 'Quezon City',
                'province_of_origin' => 'Metro Manila',
                'region_of_origin' => 'National Capital Region',
                'is_indigenous_person' => 0,
                'is_pwd' => 0,
                'is_solo_parent' => 0,
                'is_senior_citizen' => 0,
                'is_magna_carta' => 0,
                'is_underprivileged' => 0,
                'is_first_generation' => 0,
                'family_income_bracket' => 'C',
                'scholarship_type' => 'none',
                'scholarship_details' => null,
                'shs_track_id' => null,
                'shs_strand_id' => null,
                'privacy_consent_at' => $this->now,
                'marketing_consent' => 1,
                'marketing_consent_at' => $this->now,
                'profile_details' => json_encode(['demo_account' => true]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        foreach (array_chunk($rows, 150) as $chunk) {
            DB::table('students')->insert($chunk);
        }
    }

    private function seedPersonalInformation(): void
    {
        $this->command?->info('Creating student profile records...');
        $students = DB::table('students')->orderBy('id')->get();
        $studentUpdates = [];

        foreach ($students as $index => $student) {
            $personalId = DB::table('students_personal_info')->insertGetId([
                'place_of_birth' => $student->city_of_origin.', Philippines',
                'citizenship' => 'Filipino',
                'blood_type' => ['A+', 'B+', 'O+', 'AB+', 'O-'][$index % 5],
                'height' => 150 + ($index % 40),
                'weight' => 45 + ($index % 45),
                'distinguishing_marks' => $index % 13 === 0 ? 'Small scar on left arm' : null,
                'father_occupation' => ['Engineer', 'Teacher', 'Farmer', 'Driver', 'Business Owner'][$index % 5],
                'mother_occupation' => ['Nurse', 'Teacher', 'Homemaker', 'Accountant', 'Government Employee'][$index % 5],
                'hobbies' => 'Reading, sports, music, and community volunteering',
                'special_skills' => 'Communication, teamwork, digital literacy, and problem solving',
                'current_adress' => $student->address,
                'permanent_address' => $student->address,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $parentId = DB::table('student_parents_info')->insertGetId([
                'father_name' => $this->firstNames[($index + 12) % count($this->firstNames)].' '.$student->last_name,
                'father_occupation' => ['Engineer', 'Teacher', 'Farmer', 'Driver', 'Business Owner'][$index % 5],
                'father_contact' => '0918'.str_pad((string) (3000000 + $index), 7, '0', STR_PAD_LEFT),
                'father_email' => 'father.'.strtolower(str_replace(' ', '', $student->last_name)).$index.'@example.com',
                'mother_name' => $this->firstNames[($index + 31) % count($this->firstNames)].' '.$student->last_name,
                'mother_occupation' => ['Nurse', 'Teacher', 'Homemaker', 'Accountant', 'Government Employee'][$index % 5],
                'mother_contact' => '0919'.str_pad((string) (4000000 + $index), 7, '0', STR_PAD_LEFT),
                'mother_email' => 'mother.'.strtolower(str_replace(' ', '', $student->last_name)).$index.'@example.com',
                'guardian_name' => $index % 10 === 0 ? $this->firstNames[($index + 7) % count($this->firstNames)].' '.$student->last_name : null,
                'guardian_relationship' => $index % 10 === 0 ? 'Aunt/Uncle' : null,
                'guardian_contact' => $index % 10 === 0 ? '0920'.str_pad((string) (5000000 + $index), 7, '0', STR_PAD_LEFT) : null,
                'family_address' => $student->address,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $educationId = DB::table('student_education_info')->insertGetId([
                'elementary_school' => 'Barangay '.((($index % 20) + 1)).' Elementary School',
                'elementary_year_graduated' => (string) (2014 + ($index % 5)),
                'high_school' => 'Quezon City National High School',
                'high_school_year_graduated' => (string) (2018 + ($index % 5)),
                'senior_high_school' => 'KoAkademy Senior High School',
                'senior_high_year_graduated' => (string) (2020 + ($index % 5)),
                'college_school' => $index % 4 === 0 ? 'Previous College Institution' : null,
                'college_course' => $index % 4 === 0 ? 'Undergraduate Program' : null,
                'college_year_graduated' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $contactId = DB::table('student_contacts')->insertGetId([
                'personal_contact' => $student->phone,
                'facebook' => 'facebook.com/'.strtolower($student->first_name.'.'.$student->last_name),
                'twitter' => null,
                'instagram' => 'instagram.com/'.strtolower($student->first_name.'.'.$student->last_name),
                'linkedin' => 'linkedin.com/in/'.strtolower($student->first_name.'.'.$student->last_name),
                'emergency_contact_name' => $this->firstNames[($index + 12) % count($this->firstNames)].' '.$student->last_name,
                'emergency_contact_phone' => '0918'.str_pad((string) (3000000 + $index), 7, '0', STR_PAD_LEFT),
                'emergency_contact_relationship' => 'Parent',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $documentId = DB::table('document_locations')->insertGetId([
                'picture_1x1' => 'student-documents/'.$student->student_id.'/picture-1x1.jpg',
                'picture_2x2' => 'student-documents/'.$student->student_id.'/picture-2x2.jpg',
                'birth_certificate' => 'student-documents/'.$student->student_id.'/birth-certificate.pdf',
                'transcript_records' => $index % 2 === 0 ? 'student-documents/'.$student->student_id.'/transcript.pdf' : null,
                'good_moral' => 'student-documents/'.$student->student_id.'/good-moral.pdf',
                'form_138' => 'student-documents/'.$student->student_id.'/form-138.pdf',
                'form_137' => 'student-documents/'.$student->student_id.'/form-137.pdf',
                'good_moral_cert' => 'student-documents/'.$student->student_id.'/good-moral-cert.pdf',
                'transfer_credentials' => $index % 7 === 0 ? 'student-documents/'.$student->student_id.'/transfer-credentials.pdf' : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $studentUpdates[] = [
                'id' => $student->id,
                'student_personal_id' => $personalId,
                'student_parent_info' => $parentId,
                'student_education_id' => $educationId,
                'student_contact_id' => $contactId,
                'document_location_id' => $documentId,
            ];
        }

        foreach ($studentUpdates as $studentUpdate) {
            $id = $studentUpdate['id'];
            unset($studentUpdate['id']);
            DB::table('students')->where('id', $id)->update($studentUpdate);
        }
    }

    private function seedStudentAccounts(): void
    {
        $this->command?->info('Creating student logins and portal accounts...');
        $students = DB::table('students')->orderBy('id')->get();
        $users = DB::table('users')->pluck('id', 'email');
        $accounts = DB::table('accounts')->pluck('id', 'email');

        foreach ($students as $student) {
            $role = match (true) {
                $student->student_type === 'shs' => 'shs_student',
                $student->status === 'graduated' => 'graduate_student',
                default => 'student',
            };

            $userId = null;
            if ($student->user_id) {
                $userId = (int) $student->user_id;
            } elseif (isset($users[$student->email])) {
                $userId = (int) $users[$student->email];
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $student->first_name.' '.$student->last_name,
                    'email' => $student->email,
                    'role' => $role,
                    'email_verified_at' => $this->now,
                    'password' => $this->passwordHash,
                    'school_id' => self::SCHOOL_ID,
                    'phone' => $student->phone,
                    'address' => $student->address,
                    'city' => 'Quezon City',
                    'state' => 'Metro Manila',
                    'country' => 'Philippines',
                    'postal_code' => '1100',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
                $users[$student->email] = $userId;

                if (isset($this->roleIds[$role])) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $this->roleIds[$role],
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $userId,
                    ]);
                }
            }

            if ((int) $student->user_id !== $userId) {
                DB::table('students')->where('id', $student->id)->update(['user_id' => $userId]);
            }

            if (! isset($accounts[$student->email])) {
                DB::table('accounts')->insert([
                    'name' => $student->last_name.', '.$student->first_name.' '.$student->middle_name,
                    'email' => $student->email,
                    'password' => $this->passwordHash,
                    'role' => 'student',
                    'is_active' => 1,
                    'is_login' => 1,
                    'person_id' => $student->id,
                    'person_type' => 'App\\Models\\Student',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
                $accounts[$student->email] = true;
            }
        }
    }

    private function seedShsStudents(): void
    {
        $this->command?->info('Creating senior high school records...');
        $tracks = DB::table('shs_tracks')->get();
        $strands = DB::table('shs_strands')->get();
        $shsStudents = DB::table('students')->where('student_type', 'shs')->get();

        foreach ($shsStudents as $index => $student) {
            if (! $student->shs_track_id && $tracks->isNotEmpty()) {
                DB::table('students')->where('id', $student->id)->update([
                    'shs_track_id' => $tracks[$index % $tracks->count()]->id,
                    'shs_strand_id' => $strands->isNotEmpty() ? $strands[$index % $strands->count()]->id : null,
                ]);
            }
        }

        $rows = [];
        foreach ($shsStudents as $index => $student) {
            $strand = $strands->isNotEmpty() ? $strands[$index % $strands->count()] : null;
            $rows[] = [
                'student_lrn' => $student->lrn ?? (string) (300000000000 + $index),
                'fullname' => $student->first_name.' '.$student->middle_name.' '.$student->last_name,
                'civil_status' => 'single',
                'religion' => $student->religion,
                'nationality' => 'Filipino',
                'birthdate' => $student->birth_date,
                'guardian_name' => $this->firstNames[($index + 12) % count($this->firstNames)].' '.$student->last_name,
                'guardian_contact' => $student->phone,
                'student_contact' => $student->phone,
                'complete_address' => $student->address,
                'grade_level' => $index % 2 === 0 ? '11' : '12',
                'gender' => $student->gender,
                'email' => $student->email,
                'remarks' => 'Senior high school student enrolled in the STEM strand.',
                'strand_id' => $strand?->id,
                'track_id' => $strand?->track_id,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $needed = max(0, 120 - count($rows));
        for ($i = 0; $i < $needed; $i++) {
            $first = $this->firstNames[($i * 7) % count($this->firstNames)];
            $last = $this->lastNames[($i * 9) % count($this->lastNames)];
            $strand = $strands->isNotEmpty() ? $strands[$i % $strands->count()] : null;
            $rows[] = [
                'student_lrn' => (string) (400000000000 + $i),
                'fullname' => $first.' '.$last,
                'civil_status' => 'single',
                'religion' => 'catholic',
                'nationality' => 'Filipino',
                'birthdate' => Carbon::now()->subYears(16 + ($i % 2))->subDays($i)->toDateString(),
                'guardian_name' => $this->firstNames[($i + 3) % count($this->firstNames)].' '.$last,
                'guardian_contact' => '0921'.str_pad((string) (6000000 + $i), 7, '0', STR_PAD_LEFT),
                'student_contact' => '0922'.str_pad((string) (7000000 + $i), 7, '0', STR_PAD_LEFT),
                'complete_address' => ($i + 1).' SHS Campus Road, Quezon City',
                'grade_level' => $i % 2 === 0 ? '11' : '12',
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'email' => strtolower($first.'.'.$last.($i + 1).'@shs.koakademy.edu'),
                'remarks' => 'Senior high school applicant record.',
                'strand_id' => $strand?->id,
                'track_id' => $strand?->track_id,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        foreach (array_chunk($rows, 150) as $chunk) {
            DB::table('shs_students')->insert($chunk);
        }
    }

    private function seedEnrollmentPipeline(): void
    {
        $settings = DB::table('general_settings')->first();
        $moreConfigs = json_decode($settings?->more_configs ?? '[]', true);

        if (! is_array($moreConfigs)) {
            $moreConfigs = [];
        }

        if (! empty($moreConfigs['enrollment_pipeline']['steps'])) {
            return;
        }

        $moreConfigs['enrollment_pipeline'] = [
            'submitted_label' => 'Submitted',
            'steps' => [
                [
                    'key' => 'pending',
                    'status' => 'Pending',
                    'label' => 'Department Verification',
                    'color' => 'yellow',
                    'allowed_roles' => [],
                    'action_type' => 'standard',
                    'actions' => ['advance_status'],
                ],
                [
                    'key' => 'department_verification',
                    'status' => 'Verified By Dept Head',
                    'label' => 'Cashier Verification',
                    'color' => 'blue',
                    'allowed_roles' => [],
                    'action_type' => 'department_verification',
                    'actions' => ['department_verification'],
                ],
                [
                    'key' => 'payment_verification',
                    'status' => 'Verified By Cashier',
                    'label' => 'Enrolled',
                    'color' => 'green',
                    'allowed_roles' => [],
                    'action_type' => 'cashier_verification',
                    'actions' => ['cashier_verification'],
                ],
            ],
            'entry_step_key' => 'pending',
            'completion_step_key' => 'payment_verification',
            'automation' => [
                'auto_create_student_enrollment' => false,
                'auto_assign_subjects' => false,
                'default_new_applicant_to_first_year' => true,
            ],
        ];

        DB::table('general_settings')->update([
            'more_configs' => json_encode($moreConfigs),
            'updated_at' => $this->now,
        ]);
    }

    private function seedGradingPolicies(): void
    {
        $policies = [
            ['name' => 'Default Grading Policy', 'weights' => [30, 30, 40], 'school_id' => self::SCHOOL_ID],
        ];

        foreach ($policies as $policy) {
            $configuration = GradingSystemService::defaults();
            $configuration['name'] = $policy['name'];
            $configuration['components'][0]['weight'] = $policy['weights'][0];
            $configuration['components'][1]['weight'] = $policy['weights'][1];
            $configuration['components'][2]['weight'] = $policy['weights'][2];

            $policyId = DB::table('grading_policies')->insertGetId([
                'school_id' => $policy['school_id'],
                'name' => $policy['name'],
                'created_by' => $this->adminIds[0] ?? null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $versionId = DB::table('grading_policy_versions')->insertGetId([
                'grading_policy_id' => $policyId,
                'version' => 1,
                'state' => 'published',
                'configuration' => json_encode($configuration),
                'created_by' => $this->adminIds[0] ?? null,
                'published_by' => $this->adminIds[0] ?? null,
                'published_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('grading_policies')->where('id', $policyId)->update(['active_version_id' => $versionId]);
        }
    }

    private function seedFeeSchedules(): void
    {
        $rows = [];
        foreach ($this->courses as $course) {
            foreach ([1, 2] as $semester) {
                $rows[] = [
                    'public_id' => (string) Str::uuid(),
                    'course_id' => $course->id,
                    'school_year' => self::YEAR,
                    'semester' => $semester,
                    'academic_year' => $course->year_level,
                    'version' => 1,
                    'lecture_rate_per_unit' => 550,
                    'laboratory_rate_per_unit' => 750,
                    'miscellaneous_fee' => 3800,
                    'modular_fee' => 2400,
                    'nstp_multiplier' => 0.5,
                    'modular_lab_multiplier' => 0.5,
                    'rules' => json_encode(['downpayment' => 5000, 'installments' => [25, 25, 25, 25]]),
                    'is_active' => 1,
                    'created_by_user_id' => $this->cashierId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('fee_schedule_versions')->insert($chunk);
        }
    }

    private function seedEnrollmentDiscounts(): void
    {
        $discounts = [
            ['name' => 'Academic Scholar', 'normalized_name' => 'academic scholar', 'percentage' => 100],
            ['name' => 'Sibling Discount', 'normalized_name' => 'sibling discount', 'percentage' => 10],
            ['name' => 'Early Bird Discount', 'normalized_name' => 'early bird discount', 'percentage' => 5],
            ['name' => 'Athlete Grant', 'normalized_name' => 'athlete grant', 'percentage' => 15],
            ['name' => 'Employee Dependent Discount', 'normalized_name' => 'employee dependent discount', 'percentage' => 20],
        ];

        foreach ($discounts as $discount) {
            if (! DB::table('enrollment_discounts')->where('normalized_name', $discount['normalized_name'])->exists()) {
                DB::table('enrollment_discounts')->insert($discount + ['created_at' => $this->now, 'updated_at' => $this->now]);
            }
        }
    }

    private function seedCurriculumAndClasses(): void
    {
        $this->command?->info('Building curriculum, classes, and schedules...');

        $generalEducation = [
            'Understanding the Self', 'Readings in Philippine History', 'The Contemporary World',
            'Mathematics in the Modern World', 'Purposive Communication', 'Art Appreciation',
            'Science, Technology and Society', 'Ethics', 'Life and Works of Rizal',
            'Filipino 1', 'Filipino 2', 'Physical Education 1', 'Physical Education 2',
            'Physical Education 3', 'Physical Education 4', 'NSTP 1', 'NSTP 2',
            'Foreign Language', 'Philippine Literature', 'Logic and Critical Thinking',
            'Statistics for Social Sciences', 'Research Methods', 'Entrepreneurship',
            'Management Principles',
        ];

        $professional = [
            'BSIT' => ['Programming Fundamentals', 'Intermediate Programming', 'Data Structures and Algorithms', 'Database Management Systems', 'Web Systems and Technologies', 'Networking Fundamentals', 'Object-Oriented Programming', 'Operating Systems', 'Software Engineering', 'Systems Analysis and Design', 'Information Assurance and Security', 'Mobile Application Development', 'Cloud Computing', 'Human-Computer Interaction', 'Advanced Database Systems', 'Network Administration', 'IT Project Management', 'Capstone Project 1', 'Capstone Project 2', 'Internship 1', 'Internship 2', 'Professional Elective 1', 'Professional Elective 2', 'Seminar in Information Technology'],
            'BSCS' => ['Introduction to Computing', 'Programming Fundamentals', 'Discrete Structures', 'Data Structures', 'Design and Analysis of Algorithms', 'Computer Organization', 'Operating Systems', 'Database Systems', 'Software Engineering', 'Theory of Computation', 'Computer Networks', 'Artificial Intelligence', 'Machine Learning', 'Web Development', 'Mobile Computing', 'Compiler Design', 'Computer Graphics', 'Distributed Systems', 'Capstone Project 1', 'Capstone Project 2', 'Practicum 1', 'Practicum 2', 'Professional Elective 1', 'Research Seminar'],
            'BSIS' => ['Foundations of Information Systems', 'Business Process Management', 'Programming for Information Systems', 'Database Design', 'Systems Analysis and Design', 'Enterprise Systems', 'IT Infrastructure', 'Data Analytics', 'Project Management', 'Cybersecurity', 'ERP Systems', 'Business Intelligence', 'Cloud Services', 'IS Strategy and Governance', 'Capstone Project 1', 'Capstone Project 2', 'Internship', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4', 'Professional Elective 5', 'Professional Elective 6', 'Research Seminar'],
            'BSBA-MM' => ['Principles of Marketing', 'Consumer Behavior', 'Marketing Research', 'Advertising and Promotion', 'Digital Marketing', 'Brand Management', 'Sales Management', 'Retail Management', 'International Marketing', 'Strategic Marketing', 'Marketing Analytics', 'Services Marketing', 'Professional Sales', 'E-Commerce', 'Business Ethics', 'Entrepreneurship', 'Operations Management', 'Financial Management', 'Business Law', 'Human Resource Management', 'Internship', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2'],
            'BSBA-FM' => ['Financial Accounting', 'Managerial Accounting', 'Business Finance', 'Financial Markets', 'Investment Management', 'Corporate Finance', 'Risk Management', 'Banking Operations', 'Financial Analysis', 'Taxation', 'Auditing', 'International Finance', 'Financial Modeling', 'Portfolio Management', 'Business Law', 'Economics', 'Entrepreneurship', 'Operations Management', 'Human Resource Management', 'Marketing Management', 'Internship', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2'],
            'BSBA-HRM' => ['Human Resource Management', 'Organizational Behavior', 'Recruitment and Selection', 'Training and Development', 'Compensation Management', 'Labor Relations', 'HR Analytics', 'Strategic Human Resource Management', 'Talent Management', 'Performance Management', 'Business Ethics', 'Business Law', 'Industrial Psychology', 'Business Communication', 'Operations Management', 'Financial Management', 'Marketing Management', 'Entrepreneurship', 'Internship', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4'],
            'BSBA-OM' => ['Operations Management', 'Supply Chain Management', 'Production Planning and Control', 'Quality Management', 'Inventory Management', 'Logistics Management', 'Project Management', 'Lean Management', 'Service Operations', 'Business Analytics', 'Procurement Management', 'Facilities Management', 'Business Process Management', 'Risk Management', 'Financial Management', 'Marketing Management', 'Human Resource Management', 'Entrepreneurship', 'Internship', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4'],
            'BSHM' => ['Introduction to Hospitality Management', 'Food Service Operations', 'Front Office Operations', 'Housekeeping Operations', 'Hospitality Marketing', 'Culinary Fundamentals', 'Food and Beverage Management', 'Events Management', 'Hospitality Finance', 'Tourism Concepts', 'Resort Management', 'Hospitality Law', 'Human Resource in Hospitality', 'Quality Service Management', 'Hospitality Accounting', 'Revenue Management', 'Purchasing Management', 'Internship 1', 'Internship 2', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4'],
            'BSHRM' => ['Introduction to Hotel and Restaurant Management', 'Food Service Operations', 'Front Office Operations', 'Housekeeping Operations', 'Hospitality Marketing', 'Culinary Fundamentals', 'Food and Beverage Management', 'Events Management', 'Hospitality Finance', 'Tourism Concepts', 'Resort Management', 'Hospitality Law', 'Human Resource Management', 'Quality Service Management', 'Hospitality Accounting', 'Revenue Management', 'Purchasing Management', 'Internship 1', 'Internship 2', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4'],
            'BSTM' => ['Introduction to Tourism', 'Philippine Tourism', 'Tourism Planning and Development', 'Travel Operations', 'Tour Guiding', 'Destination Management', 'Tourism Marketing', 'Event Management', 'Tourism Economics', 'Hospitality Operations', 'Sustainable Tourism', 'Cultural Tourism', 'Tourism Law', 'Transport Management', 'Tourism Research', 'Internship 1', 'Internship 2', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4', 'Professional Elective 5', 'Professional Elective 6'],
            'BSCA' => ['Culinary Fundamentals', 'Kitchen Management', 'Baking and Pastry', 'International Cuisine', 'Menu Planning and Costing', 'Food Safety and Sanitation', 'Nutrition', 'Garde Manger', 'Restaurant Operations', 'Food Costing', 'Culinary Entrepreneurship', 'Beverage Management', 'Advanced Culinary Techniques', 'Internship 1', 'Internship 2', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4', 'Professional Elective 5', 'Professional Elective 6', 'Professional Elective 7', 'Professional Elective 8'],
            'BSEMC' => ['Multimedia Fundamentals', 'Digital Graphics', 'Audio Production', 'Video Production', 'Animation', 'Game Design', 'Interactive Media', 'Web Multimedia', '3D Modeling', 'Visual Effects', 'Digital Storytelling', 'Media Law', 'Project Management', 'Portfolio Development', 'Internship 1', 'Internship 2', 'Capstone Project', 'Professional Elective 1', 'Professional Elective 2', 'Professional Elective 3', 'Professional Elective 4', 'Professional Elective 5', 'Professional Elective 6', 'Professional Elective 7'],
        ];

        $unitPatterns = [
            [3, 2, 1],
            [3, 2, 1],
            [3, 3, 0],
            [3, 3, 0],
            [2, 1, 1],
            [3, 3, 0],
        ];

        $subjectRows = [];
        foreach ($this->courses as $course) {
            $professionals = $professional[$course->code] ?? $professional['BSIT'];

            for ($year = 1; $year <= 4; $year++) {
                for ($semester = 1; $semester <= 2; $semester++) {
                    $semesterIndex = ($year - 1) * 2 + ($semester - 1);

                    for ($position = 1; $position <= 6; $position++) {
                        $isProfessional = $position <= 3;
                        $title = $isProfessional
                            ? $professionals[(($semesterIndex * 3) + $position - 1) % count($professionals)]
                            : $generalEducation[(($semesterIndex * 3) + $position - 4) % count($generalEducation)];

                        [$units, $lecture, $laboratory] = $unitPatterns[$position - 1];
                        if (str_contains($title, 'Physical Education')) {
                            [$units, $lecture, $laboratory] = [2, 1, 1];
                        } elseif (str_starts_with($title, 'NSTP')) {
                            [$units, $lecture, $laboratory] = [3, 3, 0];
                        }

                        $code = 'CUR-'.$course->code.'-'.$year.$semester.$position;
                        $subjectRows[] = [
                            'classification' => 'internal',
                            'code' => $code,
                            'title' => $title,
                            'units' => $units,
                            'lecture' => $lecture,
                            'laboratory' => $laboratory,
                            'pre_riquisite' => null,
                            'academic_year' => $year,
                            'semester' => $semester,
                            'course_id' => $course->id,
                            'group' => null,
                            'is_credited' => 1,
                            'description' => $title.' for the '.$course->title.' program.',
                            'name' => $title,
                            'school_id' => self::SCHOOL_ID,
                            'created_at' => $this->now,
                            'updated_at' => $this->now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($subjectRows, 150) as $chunk) {
            DB::table('subject')->insert($chunk);
        }

        $subjectIds = DB::table('subject')->where('code', 'like', 'CUR-%')->pluck('id', 'code');
        $facultyIds = DB::table('faculty')->orderBy('id')->pluck('id')->all();
        $facultyNames = DB::table('faculty')->orderBy('id')->pluck('full_name', 'id');
        $gradingVersionId = DB::table('grading_policy_versions')->where('state', 'published')->value('id');

        $classRows = [];
        $classIndex = 0;
        foreach ($subjectRows as $subject) {
            $course = DB::table('courses')->where('id', $subject['course_id'])->first();
            $facultyId = $facultyIds[$classIndex % count($facultyIds)];
            $classRows[] = [
                'subject_id' => $subjectIds[$subject['code']],
                'subject_code' => $subject['code'],
                'faculty_id' => $facultyId,
                'academic_year' => $subject['academic_year'],
                'semester' => $subject['semester'],
                'schedule_id' => null,
                'school_year' => self::YEAR,
                'course_codes' => json_encode([(string) $course->id]),
                'section' => 'A',
                'room_id' => $this->roomIds[$classIndex % count($this->roomIds)],
                'classification' => 'college',
                'maximum_slots' => 45,
                'subject_ids' => json_encode([$subjectIds[$subject['code']]]),
                'settings' => json_encode(['grading_policy_version_id' => $gradingVersionId]),
                'start_date' => $subject['semester'] === 1 ? '2026-08-03' : '2027-01-11',
                'school_id' => self::SCHOOL_ID,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
            $classIndex++;
        }

        foreach (array_chunk($classRows, 150) as $chunk) {
            DB::table('classes')->insert($chunk);
        }

        $classes = DB::table('classes')->where('school_year', self::YEAR)->orderBy('id')->get();
        $scheduleRows = [];
        $dayPairs = [
            ['Monday', 'Wednesday'],
            ['Tuesday', 'Thursday'],
            ['Wednesday', 'Friday'],
            ['Monday', 'Thursday'],
            ['Tuesday', 'Friday'],
            ['Saturday', 'Saturday'],
        ];
        $timeBlocks = [
            ['07:30:00', '09:00:00'],
            ['09:00:00', '10:30:00'],
            ['10:30:00', '12:00:00'],
            ['13:00:00', '14:30:00'],
            ['14:30:00', '16:00:00'],
            ['16:00:00', '17:30:00'],
        ];

        foreach ($classes as $index => $class) {
            $dayPair = $dayPairs[$index % count($dayPairs)];
            $timeBlock = $timeBlocks[$index % count($timeBlocks)];
            foreach ($dayPair as $day) {
                $scheduleRows[] = [
                    'day_of_week' => $day,
                    'start_time' => $timeBlock[0],
                    'end_time' => $timeBlock[1],
                    'room_id' => $class->room_id,
                    'class_id' => $class->id,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        foreach (array_chunk($scheduleRows, 200) as $chunk) {
            DB::table('schedule')->insert($chunk);
        }
    }
}
