<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Faculty;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $passwordHash = Hash::make('password');

        // Create student accounts
        $students = Student::all();
        foreach ($students as $student) {
            if (! $student->email) {
                continue;
            }

            Account::query()->firstOrCreate(
                ['email' => $student->email],
                [
                    'name' => $student->full_name,
                    'username' => mb_strtolower($student->first_name.'.'.$student->last_name.'.'.$student->id),
                    'phone' => $student->phone,
                    'password' => $passwordHash,
                    'role' => 'student',
                    'is_active' => true,
                    'person_id' => $student->id,
                    'person_type' => Student::class,
                ],
            );
        }

        // Create faculty accounts
        $faculties = Faculty::all();
        foreach ($faculties as $faculty) {
            if (! $faculty->email) {
                continue;
            }

            Account::query()->firstOrCreate(
                ['email' => $faculty->email],
                [
                    'name' => $faculty->full_name,
                    'username' => mb_strtolower($faculty->first_name.'.'.$faculty->last_name),
                    'phone' => $faculty->phone_number,
                    'password' => $passwordHash,
                    'role' => 'faculty',
                    'is_active' => true,
                    'person_id' => null, // Faculty uses email matching
                    'person_type' => Faculty::class,
                ],
            );
        }

        // Create some guest accounts for testing
        $guestAccounts = [
            [
                'name' => 'Guest User 1',
                'username' => 'guest1',
                'email' => 'guest1@koakademy.edu',
                'password' => $passwordHash,
                'role' => 'guest',
                'is_active' => true,
            ],
            [
                'name' => 'Guest User 2',
                'username' => 'guest2',
                'email' => 'guest2@koakademy.edu',
                'password' => $passwordHash,
                'role' => 'guest',
                'is_active' => false, // Inactive for testing
            ],
        ];

        foreach ($guestAccounts as $guestAccount) {
            Account::query()->firstOrCreate(
                ['email' => $guestAccount['email']],
                $guestAccount,
            );
        }

        $this->command?->info('Accounts seeded successfully!');
    }
}
