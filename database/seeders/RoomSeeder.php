<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

final class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            // Computer Laboratories
            ['name' => 'Computer Lab 1', 'class_code' => 'CL-101'],
            ['name' => 'Computer Lab 2', 'class_code' => 'CL-102'],
            ['name' => 'Computer Lab 3', 'class_code' => 'CL-103'],
            ['name' => 'Computer Lab 4', 'class_code' => 'CL-104'],
            ['name' => 'Programming Lab', 'class_code' => 'PL-201'],
            ['name' => 'Network Lab', 'class_code' => 'NL-202'],
            ['name' => 'Multimedia Lab', 'class_code' => 'ML-203'],

            // Regular Classrooms
            ['name' => 'Room 101', 'class_code' => 'R-101'],
            ['name' => 'Room 102', 'class_code' => 'R-102'],
            ['name' => 'Room 103', 'class_code' => 'R-103'],
            ['name' => 'Room 201', 'class_code' => 'R-201'],
            ['name' => 'Room 202', 'class_code' => 'R-202'],
            ['name' => 'Room 203', 'class_code' => 'R-203'],
            ['name' => 'Room 301', 'class_code' => 'R-301'],
            ['name' => 'Room 302', 'class_code' => 'R-302'],
            ['name' => 'Room 303', 'class_code' => 'R-303'],

            // Specialized Rooms
            ['name' => 'Conference Room', 'class_code' => 'CR-401'],
            ['name' => 'Audio Visual Room', 'class_code' => 'AVR-402'],
            ['name' => 'Library', 'class_code' => 'LIB-001'],
            ['name' => 'Auditorium', 'class_code' => 'AUD-001'],

            // Hotel Management Facilities
            ['name' => 'Kitchen Lab', 'class_code' => 'KL-301'],
            ['name' => 'Restaurant Lab', 'class_code' => 'RL-302'],
            ['name' => 'Housekeeping Lab', 'class_code' => 'HL-303'],
            ['name' => 'Front Office Lab', 'class_code' => 'FOL-304'],

            // Business Administration Rooms
            ['name' => 'Business Lab', 'class_code' => 'BL-401'],
            ['name' => 'Accounting Lab', 'class_code' => 'AL-402'],
            ['name' => 'Marketing Lab', 'class_code' => 'MAL-403'],
        ];

        foreach ($rooms as $room) {
            Room::query()->create($room);
        }

        $this->command->info('Rooms seeded successfully!');
    }
}
