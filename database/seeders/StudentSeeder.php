<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CivilStatus;
use App\Enums\ClearanceStatus;
use App\Enums\Gender;
use App\Enums\Nationality;
use App\Enums\Religion;
use App\Enums\StudentData;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class StudentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedStudents();
        });

        $this->command?->info('Students seeded successfully!');
    }

    private function seedStudents(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $courses = Course::query()->orderBy('id')->pluck('id', 'code')->toArray();
        $courseIds = array_values($courses);

        if ($courseIds === []) {
            $this->command?->warn('Skipped StudentSeeder: no courses exist.');

            return;
        }

        $studentStatuses = [
            StudentStatus::Enrolled,
            StudentStatus::Enrolled,
            StudentStatus::Enrolled,
            StudentStatus::Enrolled,
            StudentStatus::Applicant,
            StudentStatus::Graduated,
        ];
        $clearanceStatuses = [
            ClearanceStatus::CLEARED,
            ClearanceStatus::CLEARED,
            ClearanceStatus::CLEARED,
            ClearanceStatus::PENDING,
            ClearanceStatus::NOT_CLEARED,
        ];

        $studentCount = 50;
        $baseYear = (int) date('Y');

        // 1. Seed the fixed demo student record so john.student@student.koakademy.edu has a full academic profile
        $demoUser = User::query()->where('email', 'john.student@student.koakademy.edu')->first();
        $bsitId = $courses['BSIT'] ?? $courseIds[0];
        $demoBirthDate = StudentData::randomBirthDate(20, 21);

        Student::withoutSchoolScope()->updateOrCreate(
            ['email' => 'john.student@student.koakademy.edu'],
            [
                'institution_id' => $schoolId,
                'school_id' => $schoolId,
                'user_id' => $demoUser?->id,
                'student_id' => $baseYear * 10000 + 1,
                'lrn' => StudentData::randomLrn(),
                'student_type' => 'college',
                'first_name' => 'John',
                'middle_name' => 'Santos',
                'last_name' => 'Student',
                'suffix' => null,
                'phone' => StudentData::randomPhoneNumber(),
                'birth_date' => $demoBirthDate,
                'gender' => Gender::Male->value,
                'civil_status' => CivilStatus::forStudents()->value,
                'nationality' => Nationality::forPhilippines()->value,
                'religion' => Religion::commonPhilippines()->value,
                'address' => StudentData::randomAddress(),
                'emergency_contact' => StudentData::emergencyContact(),
                'status' => StudentStatus::Enrolled->value,
                'age' => StudentData::calculateAge($demoBirthDate),
                'course_id' => $bsitId,
                'academic_year' => 3,
                'clearance_status' => ClearanceStatus::CLEARED->value,
                'contacts' => json_encode(StudentData::randomContacts()),
                'region_of_origin' => fake()->randomElement(StudentData::sampleRegions()),
                'province_of_origin' => fake()->randomElement(StudentData::sampleProvinces()),
                'city_of_origin' => fake()->randomElement(StudentData::sampleCities()),
                'is_indigenous_person' => false,
                'indigenous_group' => null,
            ],
        );

        // 2. Seed 50 college students evenly distributed across all 12 courses and all 4 year levels
        for ($i = 1; $i <= $studentCount; $i++) {
            $gender = Gender::random();
            $academicYear = (($i - 1) % 4) + 1;
            $studentStatus = $studentStatuses[($i - 1) % count($studentStatuses)];
            $birthDate = StudentData::randomBirthDate(16 + ($academicYear - 1), 25 + ($academicYear - 1));
            $age = StudentData::calculateAge($birthDate);
            $courseId = $courseIds[($i - 1) % count($courseIds)];
            $studentId = $baseYear * 10000 + ($i + 1);

            $firstName = $gender === Gender::Male
                ? fake()->randomElement(StudentData::filipinoFirstNamesMale())
                : fake()->randomElement(StudentData::filipinoFirstNamesFemale());

            $middleName = fake()->randomElement(StudentData::filipinoMiddleNames());
            $lastName = fake()->randomElement(StudentData::filipinoLastNames());
            $suffix = fake()->randomElement(StudentData::filipinoSuffixes());

            $slugFirst = preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $firstName)) ?: 'student';
            $slugLast = preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $lastName)) ?: 'user';
            $email = sprintf('%s.%s.%d@student.koakademy.edu', $slugFirst, $slugLast, $i);

            Student::withoutSchoolScope()->updateOrCreate(
                ['email' => $email],
                [
                    'institution_id' => $schoolId,
                    'school_id' => $schoolId,
                    'student_id' => $studentId,
                    'lrn' => StudentData::randomLrn(),
                    'student_type' => 'college',
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'last_name' => $lastName,
                    'suffix' => $suffix,
                    'phone' => StudentData::randomPhoneNumber(),
                    'birth_date' => $birthDate,
                    'gender' => $gender->value,
                    'civil_status' => CivilStatus::forStudents()->value,
                    'nationality' => Nationality::forPhilippines()->value,
                    'religion' => Religion::commonPhilippines()->value,
                    'address' => StudentData::randomAddress(),
                    'emergency_contact' => StudentData::emergencyContact(),
                    'status' => $studentStatus->value,
                    'age' => $age,
                    'course_id' => $courseId,
                    'academic_year' => $academicYear,
                    'clearance_status' => fake()->randomElement($clearanceStatuses)->value,
                    'contacts' => json_encode(StudentData::randomContacts()),
                    'region_of_origin' => fake()->randomElement(StudentData::sampleRegions()),
                    'province_of_origin' => fake()->randomElement(StudentData::sampleProvinces()),
                    'city_of_origin' => fake()->randomElement(StudentData::sampleCities()),
                    'is_indigenous_person' => fake()->boolean(10),
                    'indigenous_group' => fake()->boolean(10) ? fake()->randomElement([
                        'Igorot', 'Mangyan', 'Tao', 'Badjao', 'Moro', 'Lumad', 'IP', null, null, null,
                    ]) : null,
                ],
            );
        }
    }
}
