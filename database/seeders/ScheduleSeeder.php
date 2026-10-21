<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

final class ScheduleSeeder extends Seeder
{
    /**
     * The weekly schedule of every class.
     *
     * Each entry names the class by its subject code and section, the room by
     * its name and the meeting time. The class and the room are resolved at
     * seed time, so a class that was skipped by the class seeder skips its
     * schedule too instead of taking the whole seed down.
     *
     * @var list<array{subject: string, section: string|null, room: string, day: string, start: string, end: string}>
     */
    private const array SCHEDULE_DEFINITIONS = [
        // IT111 Section A - Monday & Wednesday
        ['subject' => 'IT111', 'section' => 'A', 'room' => 'Computer Lab 1', 'day' => 'Monday', 'start' => '08:00:00', 'end' => '10:00:00'],
        ['subject' => 'IT111', 'section' => 'A', 'room' => 'Computer Lab 1', 'day' => 'Wednesday', 'start' => '08:00:00', 'end' => '10:00:00'],

        // IT111 Section B - Tuesday & Thursday
        ['subject' => 'IT111', 'section' => 'B', 'room' => 'Computer Lab 2', 'day' => 'Tuesday', 'start' => '08:00:00', 'end' => '10:00:00'],
        ['subject' => 'IT111', 'section' => 'B', 'room' => 'Computer Lab 2', 'day' => 'Thursday', 'start' => '08:00:00', 'end' => '10:00:00'],

        // IT112 Programming - Monday, Wednesday, Friday
        ['subject' => 'IT112', 'section' => null, 'room' => 'Programming Lab', 'day' => 'Monday', 'start' => '10:30:00', 'end' => '12:30:00'],
        ['subject' => 'IT112', 'section' => null, 'room' => 'Programming Lab', 'day' => 'Wednesday', 'start' => '10:30:00', 'end' => '12:30:00'],
        ['subject' => 'IT112', 'section' => null, 'room' => 'Programming Lab', 'day' => 'Friday', 'start' => '10:30:00', 'end' => '12:30:00'],

        // MATH111 - Tuesday & Thursday
        ['subject' => 'MATH111', 'section' => null, 'room' => 'Room 101', 'day' => 'Tuesday', 'start' => '13:00:00', 'end' => '15:00:00'],
        ['subject' => 'MATH111', 'section' => null, 'room' => 'Room 101', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:00:00'],

        // ENG111 - Monday & Wednesday
        ['subject' => 'ENG111', 'section' => null, 'room' => 'Room 102', 'day' => 'Monday', 'start' => '13:00:00', 'end' => '15:00:00'],
        ['subject' => 'ENG111', 'section' => null, 'room' => 'Room 102', 'day' => 'Wednesday', 'start' => '13:00:00', 'end' => '15:00:00'],

        // PATHFIT1 Section A - Friday
        ['subject' => 'PATHFIT1', 'section' => 'A', 'room' => 'Auditorium', 'day' => 'Friday', 'start' => '07:00:00', 'end' => '09:00:00'],

        // PATHFIT1 Section B - Friday
        ['subject' => 'PATHFIT1', 'section' => 'B', 'room' => 'Auditorium', 'day' => 'Friday', 'start' => '09:30:00', 'end' => '11:30:00'],

        // IT211 Data Structures - Tuesday & Thursday
        ['subject' => 'IT211', 'section' => null, 'room' => 'Computer Lab 3', 'day' => 'Tuesday', 'start' => '10:30:00', 'end' => '12:30:00'],
        ['subject' => 'IT211', 'section' => null, 'room' => 'Computer Lab 3', 'day' => 'Thursday', 'start' => '10:30:00', 'end' => '12:30:00'],

        // IT212 Database - Monday & Wednesday
        ['subject' => 'IT212', 'section' => null, 'room' => 'Computer Lab 4', 'day' => 'Monday', 'start' => '15:30:00', 'end' => '17:30:00'],
        ['subject' => 'IT212', 'section' => null, 'room' => 'Computer Lab 4', 'day' => 'Wednesday', 'start' => '15:30:00', 'end' => '17:30:00'],

        // IT213 OOP - Tuesday & Friday
        ['subject' => 'IT213', 'section' => null, 'room' => 'Programming Lab', 'day' => 'Tuesday', 'start' => '15:30:00', 'end' => '17:30:00'],
        ['subject' => 'IT213', 'section' => null, 'room' => 'Programming Lab', 'day' => 'Friday', 'start' => '15:30:00', 'end' => '17:30:00'],

        // IT311 Software Engineering - Monday & Wednesday
        ['subject' => 'IT311', 'section' => null, 'room' => 'Computer Lab 1', 'day' => 'Monday', 'start' => '18:00:00', 'end' => '20:00:00'],
        ['subject' => 'IT311', 'section' => null, 'room' => 'Computer Lab 1', 'day' => 'Wednesday', 'start' => '18:00:00', 'end' => '20:00:00'],

        // IT312 Network Administration - Tuesday & Thursday
        ['subject' => 'IT312', 'section' => null, 'room' => 'Network Lab', 'day' => 'Tuesday', 'start' => '18:00:00', 'end' => '20:00:00'],
        ['subject' => 'IT312', 'section' => null, 'room' => 'Network Lab', 'day' => 'Thursday', 'start' => '18:00:00', 'end' => '20:00:00'],

        // IT313 Mobile Development - Wednesday & Friday
        ['subject' => 'IT313', 'section' => null, 'room' => 'Multimedia Lab', 'day' => 'Wednesday', 'start' => '20:30:00', 'end' => '22:30:00'],
        ['subject' => 'IT313', 'section' => null, 'room' => 'Multimedia Lab', 'day' => 'Friday', 'start' => '18:00:00', 'end' => '20:00:00'],

        // IT411 Capstone 1 - Monday, Wednesday, Friday
        ['subject' => 'IT411', 'section' => null, 'room' => 'Computer Lab 2', 'day' => 'Monday', 'start' => '07:00:00', 'end' => '08:00:00'],
        ['subject' => 'IT411', 'section' => null, 'room' => 'Computer Lab 2', 'day' => 'Wednesday', 'start' => '07:00:00', 'end' => '08:00:00'],
        ['subject' => 'IT411', 'section' => null, 'room' => 'Computer Lab 2', 'day' => 'Friday', 'start' => '07:00:00', 'end' => '08:00:00'],

        // IT412 Project Management - Tuesday & Thursday
        ['subject' => 'IT412', 'section' => null, 'room' => 'Room 201', 'day' => 'Tuesday', 'start' => '07:00:00', 'end' => '09:00:00'],
        ['subject' => 'IT412', 'section' => null, 'room' => 'Room 201', 'day' => 'Thursday', 'start' => '07:00:00', 'end' => '09:00:00'],

        // BA111 - Monday & Wednesday
        ['subject' => 'BA111', 'section' => null, 'room' => 'Business Lab', 'day' => 'Monday', 'start' => '09:00:00', 'end' => '11:00:00'],
        ['subject' => 'BA111', 'section' => null, 'room' => 'Business Lab', 'day' => 'Wednesday', 'start' => '09:00:00', 'end' => '11:00:00'],

        // BA112 Marketing - Tuesday & Thursday
        ['subject' => 'BA112', 'section' => null, 'room' => 'Marketing Lab', 'day' => 'Tuesday', 'start' => '09:00:00', 'end' => '11:00:00'],
        ['subject' => 'BA112', 'section' => null, 'room' => 'Marketing Lab', 'day' => 'Thursday', 'start' => '09:00:00', 'end' => '11:00:00'],

        // ACC111 Accounting - Monday, Wednesday, Friday
        ['subject' => 'ACC111', 'section' => null, 'room' => 'Accounting Lab', 'day' => 'Monday', 'start' => '14:00:00', 'end' => '15:30:00'],
        ['subject' => 'ACC111', 'section' => null, 'room' => 'Accounting Lab', 'day' => 'Wednesday', 'start' => '14:00:00', 'end' => '15:30:00'],
        ['subject' => 'ACC111', 'section' => null, 'room' => 'Accounting Lab', 'day' => 'Friday', 'start' => '14:00:00', 'end' => '15:30:00'],

        // HM111 - Tuesday & Thursday
        ['subject' => 'HM111', 'section' => null, 'room' => 'Room 301', 'day' => 'Tuesday', 'start' => '11:30:00', 'end' => '13:30:00'],
        ['subject' => 'HM111', 'section' => null, 'room' => 'Room 301', 'day' => 'Thursday', 'start' => '11:30:00', 'end' => '13:30:00'],

        // HM112 Food Service - Monday, Wednesday, Friday
        ['subject' => 'HM112', 'section' => null, 'room' => 'Kitchen Lab', 'day' => 'Monday', 'start' => '16:00:00', 'end' => '17:30:00'],
        ['subject' => 'HM112', 'section' => null, 'room' => 'Kitchen Lab', 'day' => 'Wednesday', 'start' => '16:00:00', 'end' => '17:30:00'],
        ['subject' => 'HM112', 'section' => null, 'room' => 'Kitchen Lab', 'day' => 'Friday', 'start' => '16:00:00', 'end' => '17:30:00'],

        // HM113 Front Office - Tuesday & Thursday
        ['subject' => 'HM113', 'section' => null, 'room' => 'Front Office Lab', 'day' => 'Tuesday', 'start' => '14:00:00', 'end' => '16:00:00'],
        ['subject' => 'HM113', 'section' => null, 'room' => 'Front Office Lab', 'day' => 'Thursday', 'start' => '14:00:00', 'end' => '16:00:00'],

        // The three demo classes
        ['subject' => 'IT121', 'section' => 'DEMO-A', 'room' => 'Computer Lab 1', 'day' => 'Monday', 'start' => '09:00:00', 'end' => '11:00:00'],
        ['subject' => 'IT121', 'section' => 'DEMO-A', 'room' => 'Computer Lab 1', 'day' => 'Wednesday', 'start' => '09:00:00', 'end' => '11:00:00'],
        ['subject' => 'BA111', 'section' => 'DEMO-B', 'room' => 'Business Lab', 'day' => 'Tuesday', 'start' => '13:00:00', 'end' => '15:00:00'],
        ['subject' => 'BA111', 'section' => 'DEMO-B', 'room' => 'Business Lab', 'day' => 'Thursday', 'start' => '13:00:00', 'end' => '15:00:00'],
        ['subject' => 'HM111', 'section' => 'DEMO-C', 'room' => 'Room 301', 'day' => 'Friday', 'start' => '09:00:00', 'end' => '12:00:00'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = Classes::all();
        $rooms = Room::all()->keyBy('name');

        $created = 0;
        $skipped = 0;

        foreach (self::SCHEDULE_DEFINITIONS as $definition) {
            $class = $this->resolveClass($classes, $definition['subject'], $definition['section']);
            $room = $rooms->get($definition['room']);

            if (! $class instanceof Classes) {
                $skipped++;

                continue;
            }

            if (! $room instanceof Room) {
                $skipped++;

                continue;
            }

            Schedule::query()->create([
                'class_id' => $class->id,
                'day_of_week' => $definition['day'],
                'start_time' => $definition['start'],
                'end_time' => $definition['end'],
                'room_id' => $room->id,
            ]);

            $created++;
        }

        $this->command?->info(sprintf('Seeded %d schedule block(s), skipped %d.', $created, $skipped));
    }

    /**
     * Find the class a schedule block belongs to.
     *
     * @param  Collection<int, Classes>  $classes
     */
    private function resolveClass(Collection $classes, string $subjectCode, ?string $section): ?Classes
    {
        $matches = $classes->where('subject_code', $subjectCode);

        if ($section !== null) {
            $matches = $matches->where('section', $section);
        }

        return $matches->first();
    }
}
