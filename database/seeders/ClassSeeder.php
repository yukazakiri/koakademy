<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Seeder;

final class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;
        $faculties = Faculty::all();
        $rooms = Room::all();
        $subjects = Subject::all();

        $classes = [
            // First Year IT Classes
            [
                'subject_id' => $subjects->where('code', 'IT111')->first()->id,
                'subject_code' => 'IT111',
                'faculty_id' => $faculties->where('department', 'Information Technology')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 35,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT111')->first()->id,
                'subject_code' => 'IT111',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(1)->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'B',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 35,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT112')->first()->id,
                'subject_code' => 'IT112',
                'faculty_id' => $faculties->where('department', 'Information Technology')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 30,
            ],
            [
                'subject_id' => $subjects->where('code', 'MATH111')->first()->id,
                'subject_code' => 'MATH111',
                'faculty_id' => $faculties->where('department', 'General Education')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1', '4', '2'], // BSIT, BSBA-MM, BSHM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Room 101')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 40,
            ],
            [
                'subject_id' => $subjects->where('code', 'ENG111')->first()->id,
                'subject_code' => 'ENG111',
                'faculty_id' => $faculties->where('department', 'General Education')->skip(1)->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1', '4', '2'], // BSIT, BSBA-MM, BSHM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Room 102')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 40,
            ],

            // PATHFIT classes (available for all years)
            [
                'subject_id' => $subjects->where('code', 'PATHFIT1')->first()->id,
                'subject_code' => 'PATHFIT1',
                'faculty_id' => $faculties->where('department', 'General Education')->first()->id,
                'academic_year' => null, // Available for all years
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1', '4', '2', '6', '5'], // All courses
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Auditorium')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 50,
            ],
            [
                'subject_id' => $subjects->where('code', 'PATHFIT1')->first()->id,
                'subject_code' => 'PATHFIT1',
                'faculty_id' => $faculties->where('department', 'General Education')->skip(1)->first()->id,
                'academic_year' => null, // Available for all years
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1', '4', '2', '6', '5'], // All courses
                'section' => 'B',
                'room_id' => $rooms->where('name', 'Auditorium')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 50,
            ],

            // Second Year IT Classes
            [
                'subject_id' => $subjects->where('code', 'IT211')->first()->id,
                'subject_code' => 'IT211',
                'faculty_id' => $faculties->where('department', 'Information Technology')->first()->id,
                'academic_year' => 2,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Computer Lab 3')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 30,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT212')->first()->id,
                'subject_code' => 'IT212',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(1)->first()->id,
                'academic_year' => 2,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Computer Lab 4')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 30,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT213')->first()->id,
                'subject_code' => 'IT213',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(2)->first()->id,
                'academic_year' => 2,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 30,
            ],

            // Third Year IT Classes
            [
                'subject_id' => $subjects->where('code', 'IT311')->first()->id,
                'subject_code' => 'IT311',
                'faculty_id' => $faculties->where('department', 'Information Technology')->first()->id,
                'academic_year' => 3,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 25,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT312')->first()->id,
                'subject_code' => 'IT312',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(1)->first()->id,
                'academic_year' => 3,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Network Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 25,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT313')->first()->id,
                'subject_code' => 'IT313',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(2)->first()->id,
                'academic_year' => 3,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Multimedia Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 25,
            ],

            // Fourth Year IT Classes
            [
                'subject_id' => $subjects->where('code', 'IT411')->first()->id,
                'subject_code' => 'IT411',
                'faculty_id' => $faculties->where('department', 'Information Technology')->first()->id,
                'academic_year' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 20,
            ],
            [
                'subject_id' => $subjects->where('code', 'IT412')->first()->id,
                'subject_code' => 'IT412',
                'faculty_id' => $faculties->where('department', 'Information Technology')->skip(1)->first()->id,
                'academic_year' => 4,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['1'], // BSIT
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Room 201')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 20,
            ],

            // Business Administration Classes
            [
                'subject_id' => $subjects->where('code', 'BA111')->first()->id,
                'subject_code' => 'BA111',
                'faculty_id' => $faculties->where('department', 'Business Administration')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['4'], // BSBA-MM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Business Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 35,
            ],
            [
                'subject_id' => $subjects->where('code', 'BA112')->first()->id,
                'subject_code' => 'BA112',
                'faculty_id' => $faculties->where('department', 'Business Administration')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['4'], // BSBA-MM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Marketing Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 35,
            ],
            [
                'subject_id' => $subjects->where('code', 'ACC111')->first()->id,
                'subject_code' => 'ACC111',
                'faculty_id' => $faculties->where('department', 'Business Administration')->skip(1)->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['4', '5'], // BSBA-MM, BSBA-FM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Accounting Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 40,
            ],

            // Hotel Management Classes
            [
                'subject_id' => $subjects->where('code', 'HM111')->first()->id,
                'subject_code' => 'HM111',
                'faculty_id' => $faculties->where('department', 'Hotel Management')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['2'], // BSHM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Room 301')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 30,
            ],
            [
                'subject_id' => $subjects->where('code', 'HM112')->first()->id,
                'subject_code' => 'HM112',
                'faculty_id' => $faculties->where('department', 'Hotel Management')->skip(1)->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['2'], // BSHM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Kitchen Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 25,
            ],
            [
                'subject_id' => $subjects->where('code', 'HM113')->first()->id,
                'subject_code' => 'HM113',
                'faculty_id' => $faculties->where('department', 'Hotel Management')->first()->id,
                'academic_year' => 1,
                'semester' => 1,
                'school_year' => '2024-2025',
                'course_codes' => ['2'], // BSHM
                'section' => 'A',
                'room_id' => $rooms->where('name', 'Front Office Lab')->first()->id,
                'classification' => 'college',
                'maximum_slots' => 25,
            ],
        ];

        foreach ($classes as $class) {
            Classes::query()->create(array_merge($class, [
                'school_id' => $schoolId,
            ]));
        }

        $this->command->info('Classes seeded successfully!');
    }
}
