<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

final class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = Classes::all();
        $rooms = Room::all();

        $schedules = [
            // IT111 Section A - Monday & Wednesday
            [
                'class_id' => $classes->where('subject_code', 'IT111')->where('section', 'A')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT111')->where('section', 'A')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
            ],

            // IT111 Section B - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'IT111')->where('section', 'B')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT111')->where('section', 'B')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
            ],

            // IT112 Programming - Monday, Wednesday, Friday
            [
                'class_id' => $classes->where('subject_code', 'IT112')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '10:30:00',
                'end_time' => '12:30:00',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT112')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '10:30:00',
                'end_time' => '12:30:00',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT112')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '10:30:00',
                'end_time' => '12:30:00',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
            ],

            // MATH111 - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'MATH111')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'room_id' => $rooms->where('name', 'Room 101')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'MATH111')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'room_id' => $rooms->where('name', 'Room 101')->first()->id,
            ],

            // ENG111 - Monday & Wednesday
            [
                'class_id' => $classes->where('subject_code', 'ENG111')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'room_id' => $rooms->where('name', 'Room 102')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'ENG111')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '13:00:00',
                'end_time' => '15:00:00',
                'room_id' => $rooms->where('name', 'Room 102')->first()->id,
            ],

            // PATHFIT1 Section A - Friday
            [
                'class_id' => $classes->where('subject_code', 'PATHFIT1')->where('section', 'A')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '07:00:00',
                'end_time' => '09:00:00',
                'room_id' => $rooms->where('name', 'Auditorium')->first()->id,
            ],

            // PATHFIT1 Section B - Friday
            [
                'class_id' => $classes->where('subject_code', 'PATHFIT1')->where('section', 'B')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '09:30:00',
                'end_time' => '11:30:00',
                'room_id' => $rooms->where('name', 'Auditorium')->first()->id,
            ],

            // Second Year IT Classes
            // IT211 Data Structures - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'IT211')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '10:30:00',
                'end_time' => '12:30:00',
                'room_id' => $rooms->where('name', 'Computer Lab 3')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT211')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '10:30:00',
                'end_time' => '12:30:00',
                'room_id' => $rooms->where('name', 'Computer Lab 3')->first()->id,
            ],

            // IT212 Database - Monday & Wednesday
            [
                'class_id' => $classes->where('subject_code', 'IT212')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '15:30:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Computer Lab 4')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT212')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '15:30:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Computer Lab 4')->first()->id,
            ],

            // IT213 OOP - Tuesday & Friday
            [
                'class_id' => $classes->where('subject_code', 'IT213')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '15:30:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT213')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '15:30:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Programming Lab')->first()->id,
            ],

            // Third Year IT Classes
            // IT311 Software Engineering - Monday & Wednesday
            [
                'class_id' => $classes->where('subject_code', 'IT311')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT311')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 1')->first()->id,
            ],

            // IT312 Network Admin - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'IT312')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'room_id' => $rooms->where('name', 'Network Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT312')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'room_id' => $rooms->where('name', 'Network Lab')->first()->id,
            ],

            // IT313 Mobile Dev - Wednesday & Friday
            [
                'class_id' => $classes->where('subject_code', 'IT313')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '20:30:00',
                'end_time' => '22:30:00',
                'room_id' => $rooms->where('name', 'Multimedia Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT313')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'room_id' => $rooms->where('name', 'Multimedia Lab')->first()->id,
            ],

            // Fourth Year IT Classes
            // IT411 Capstone 1 - Monday, Wednesday, Friday
            [
                'class_id' => $classes->where('subject_code', 'IT411')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '07:00:00',
                'end_time' => '08:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT411')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '07:00:00',
                'end_time' => '08:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT411')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '07:00:00',
                'end_time' => '08:00:00',
                'room_id' => $rooms->where('name', 'Computer Lab 2')->first()->id,
            ],

            // IT412 Project Management - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'IT412')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '07:00:00',
                'end_time' => '09:00:00',
                'room_id' => $rooms->where('name', 'Room 201')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'IT412')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '07:00:00',
                'end_time' => '09:00:00',
                'room_id' => $rooms->where('name', 'Room 201')->first()->id,
            ],

            // Business Administration Classes
            // BA111 - Monday & Wednesday
            [
                'class_id' => $classes->where('subject_code', 'BA111')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $rooms->where('name', 'Business Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'BA111')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $rooms->where('name', 'Business Lab')->first()->id,
            ],

            // BA112 Marketing - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'BA112')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $rooms->where('name', 'Marketing Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'BA112')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '09:00:00',
                'end_time' => '11:00:00',
                'room_id' => $rooms->where('name', 'Marketing Lab')->first()->id,
            ],

            // ACC111 Accounting - Monday, Wednesday, Friday
            [
                'class_id' => $classes->where('subject_code', 'ACC111')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '14:00:00',
                'end_time' => '15:30:00',
                'room_id' => $rooms->where('name', 'Accounting Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'ACC111')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '14:00:00',
                'end_time' => '15:30:00',
                'room_id' => $rooms->where('name', 'Accounting Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'ACC111')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '14:00:00',
                'end_time' => '15:30:00',
                'room_id' => $rooms->where('name', 'Accounting Lab')->first()->id,
            ],

            // Hotel Management Classes
            // HM111 - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'HM111')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '11:30:00',
                'end_time' => '13:30:00',
                'room_id' => $rooms->where('name', 'Room 301')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'HM111')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '11:30:00',
                'end_time' => '13:30:00',
                'room_id' => $rooms->where('name', 'Room 301')->first()->id,
            ],

            // HM112 Food Service - Monday, Wednesday, Friday
            [
                'class_id' => $classes->where('subject_code', 'HM112')->first()->id,
                'day_of_week' => 'Monday',
                'start_time' => '16:00:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Kitchen Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'HM112')->first()->id,
                'day_of_week' => 'Wednesday',
                'start_time' => '16:00:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Kitchen Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'HM112')->first()->id,
                'day_of_week' => 'Friday',
                'start_time' => '16:00:00',
                'end_time' => '17:30:00',
                'room_id' => $rooms->where('name', 'Kitchen Lab')->first()->id,
            ],

            // HM113 Front Office - Tuesday & Thursday
            [
                'class_id' => $classes->where('subject_code', 'HM113')->first()->id,
                'day_of_week' => 'Tuesday',
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'room_id' => $rooms->where('name', 'Front Office Lab')->first()->id,
            ],
            [
                'class_id' => $classes->where('subject_code', 'HM113')->first()->id,
                'day_of_week' => 'Thursday',
                'start_time' => '14:00:00',
                'end_time' => '16:00:00',
                'room_id' => $rooms->where('name', 'Front Office Lab')->first()->id,
            ],
        ];

        foreach ($schedules as $schedule) {
            Schedule::query()->create($schedule);
        }

        $this->command->info('Schedules seeded successfully!');
    }
}
