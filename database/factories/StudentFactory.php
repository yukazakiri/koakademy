<?php

declare(strict_types=1);

namespace Database\Factories;

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
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
final class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $course = Course::query()->inRandomOrder()->first();
        $courseId = $course?->id ?? Course::factory()->create()->id;
        $gender = Gender::random();
        $academicYear = $this->faker->numberBetween(1, 4);
        $birthDate = StudentData::randomBirthDate(16 + ($academicYear - 1), 25 + ($academicYear - 1));

        $firstName = $gender === Gender::Male
            ? $this->faker->randomElement(StudentData::filipinoFirstNamesMale())
            : $this->faker->randomElement(StudentData::filipinoFirstNamesFemale());

        $lastName = $this->faker->randomElement(StudentData::filipinoLastNames());

        return [
            'institution_id' => $schoolId,
            'school_id' => $schoolId,
            'student_id' => $this->faker->numberBetween(200000, 209999),
            'lrn' => StudentData::randomLrn(),
            'student_type' => 'college',
            'first_name' => $firstName,
            'middle_name' => $this->faker->randomElement(StudentData::filipinoMiddleNames()),
            'last_name' => $lastName,
            'suffix' => $this->faker->randomElement(StudentData::filipinoSuffixes()),
            'email' => mb_strtolower($firstName).'.'.mb_strtolower($lastName).$this->faker->numerify('#').'@student.koakademy.edu',
            'phone' => StudentData::randomPhoneNumber(),
            'birth_date' => $birthDate,
            'gender' => $gender->value,
            'civil_status' => CivilStatus::forStudents()->value,
            'nationality' => Nationality::forPhilippines()->value,
            'religion' => Religion::commonPhilippines()->value,
            'address' => StudentData::randomAddress(),
            'emergency_contact' => StudentData::emergencyContact(),
            'status' => StudentStatus::Enrolled->value,
            'age' => StudentData::calculateAge($birthDate),
            'course_id' => $courseId,
            'academic_year' => $academicYear,
            'clearance_status' => ClearanceStatus::CLEARED->value,
            'contacts' => json_encode(StudentData::randomContacts()),
            'region_of_origin' => $this->faker->randomElement(StudentData::philippineRegions()),
            'province_of_origin' => $this->faker->randomElement(StudentData::philippineProvinces()),
            'city_of_origin' => $this->faker->randomElement(StudentData::philippineCities()),
            'is_indigenous_person' => $this->faker->boolean(10),
            'indigenous_group' => $this->faker->boolean(10) ? $this->faker->randomElement([
                'Igorot',
                'Mangyan',
                'Tao',
                'Badjao',
                'Moro',
                'Lumad',
                'IP',
            ]) : null,
        ];
    }

    public function withContacts(?array $contacts = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'contacts' => $contacts ?? json_encode(StudentData::randomContacts()),
        ]);
    }

    public function minimal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'middle_name' => null,
            'suffix' => null,
            'address' => null,
            'contacts' => null,
            'emergency_contact' => null,
            'region_of_origin' => null,
            'province_of_origin' => null,
            'city_of_origin' => null,
            'is_indigenous_person' => false,
            'indigenous_group' => null,
        ]);
    }

    public function enrolled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentStatus::Enrolled->value,
        ]);
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentStatus::Graduated->value,
            'year_graduated' => $this->faker->numberBetween(2020, 2024),
            'academic_year' => 5,
        ]);
    }

    public function applicant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentStatus::Applicant->value,
            'academic_year' => 1,
        ]);
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => Gender::Male->value,
            'first_name' => $this->faker->randomElement(StudentData::filipinoFirstNamesMale()),
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes): array => [
            'gender' => Gender::Female->value,
            'first_name' => $this->faker->randomElement(StudentData::filipinoFirstNamesFemale()),
        ]);
    }

    public function fromRegion(string $region): static
    {
        return $this->state(fn (array $attributes): array => [
            'region_of_origin' => $region,
        ]);
    }
}
