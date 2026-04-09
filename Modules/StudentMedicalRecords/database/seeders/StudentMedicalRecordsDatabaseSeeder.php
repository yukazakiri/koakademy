<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;
use Modules\StudentMedicalRecords\Database\Factories\MedicalRecordFactory;
use Modules\StudentMedicalRecords\Enums\MedicalRecordType;

final class StudentMedicalRecordsDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing students
        $students = Student::limit(50)->get();

        if ($students->isEmpty()) {
            $this->command->warn('No students found. Please run the student seeder first.');

            return;
        }

        $this->command->info('Creating medical records for existing students...');

        // Create various types of medical records for students
        foreach ($students as $student) {
            // Create a general checkup record for each student
            MedicalRecordFactory::new()
                ->for($student)
                ->state([
                    'record_type' => MedicalRecordType::Checkup,
                    'title' => 'Annual Health Checkup',
                    'description' => 'Routine annual health examination',
                    'doctor_name' => 'Dr. Sarah Johnson',
                    'clinic_name' => 'Campus Health Center',
                ])
                ->create();

            // Randomly create additional records
            if (fake()->boolean(70)) { // 70% chance
                MedicalRecordFactory::new()
                    ->for($student)
                    ->create();
            }

            if (fake()->boolean(30)) { // 30% chance
                MedicalRecordFactory::new()
                    ->for($student)
                    ->create();
            }

            // 10% chance of emergency record
            if (fake()->boolean(10)) {
                MedicalRecordFactory::new()
                    ->for($student)
                    ->emergency()
                    ->create();
            }

            // 15% chance of urgent record
            if (fake()->boolean(15)) {
                MedicalRecordFactory::new()
                    ->for($student)
                    ->urgent()
                    ->create();
            }

            // 20% chance of confidential record
            if (fake()->boolean(20)) {
                MedicalRecordFactory::new()
                    ->for($student)
                    ->confidential()
                    ->create();
            }

            // 25% chance of record needing follow-up
            if (fake()->boolean(25)) {
                MedicalRecordFactory::new()
                    ->for($student)
                    ->needsFollowUp()
                    ->create();
            }
        }

        $this->command->info('Medical records created successfully!');
    }
}
