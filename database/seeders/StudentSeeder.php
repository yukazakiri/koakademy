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
use Illuminate\Database\Seeder;

final class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $courses = Course::query()->pluck('id', 'code')->toArray();
        $courseIds = array_values($courses);
        $academicYears = [1, 2, 3, 4];
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

        for ($i = 0; $i < $studentCount; $i++) {
            $gender = Gender::random();
            $academicYear = fake()->randomElement($academicYears);
            $studentStatus = fake()->randomElement($studentStatuses);
            $birthDate = StudentData::randomBirthDate(16 + ($academicYear - 1), 25 + ($academicYear - 1));
            $age = StudentData::calculateAge($birthDate);
            $courseId = fake()->randomElement($courseIds);
            $studentId = $baseYear * 10000 + ($i + 1);

            $firstName = $gender === Gender::Male
                ? fake()->randomElement(StudentData::filipinoFirstNamesMale())
                : fake()->randomElement(StudentData::filipinoFirstNamesFemale());

            $middleName = fake()->randomElement(StudentData::filipinoMiddleNames());
            $lastName = fake()->randomElement(StudentData::filipinoLastNames());
            $suffix = fake()->randomElement(StudentData::filipinoSuffixes());

            $email = mb_strtolower((string) $firstName).'.'.mb_strtolower((string) $lastName).'@student.koakademy.edu';
            if ($i > 0) {
                $email = mb_strtolower((string) $firstName).'.'.mb_strtolower((string) $lastName).fake()->numerify('#').'@student.koakademy.edu';
            }

            $studentType = fake()->randomElement(['college', 'college', 'college', 'shs', 'tesda']);

            Student::query()->create([
                'institution_id' => $schoolId,
                'school_id' => $schoolId,
                'student_id' => $studentId,
                'lrn' => StudentData::randomLrn(),
                'student_type' => $studentType,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'suffix' => $suffix,
                'email' => $email,
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
                'region_of_origin' => fake()->randomElement(StudentData::philippineRegions()),
                'province_of_origin' => fake()->randomElement(StudentData::philippineProvinces()),
                'city_of_origin' => fake()->randomElement(StudentData::philippineCities()),
                'is_indigenous_person' => fake()->boolean(10),
                'indigenous_group' => fake()->boolean(10) ? fake()->randomElement([
                    'Igorot', 'Mangyan', 'Tao', 'Badjao', 'Moro', 'Lumad', 'IP', null, null, null,
                ]) : null,
            ]);
        }

        $this->command->info('Students seeded successfully!');
    }
}
