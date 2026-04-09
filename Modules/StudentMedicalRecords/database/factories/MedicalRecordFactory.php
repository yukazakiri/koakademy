<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\StudentMedicalRecords\Enums\MedicalRecordPriority;
use Modules\StudentMedicalRecords\Enums\MedicalRecordStatus;
use Modules\StudentMedicalRecords\Enums\MedicalRecordType;
use Modules\StudentMedicalRecords\Models\MedicalRecord;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Modules\StudentMedicalRecords\Models\MedicalRecord>
 */
final class MedicalRecordFactory extends Factory
{
    protected $model = MedicalRecord::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $recordType = $this->faker->randomElement(MedicalRecordType::cases());
        $visitDate = $this->faker->dateTimeBetween('-2 years', 'now');

        return [
            'student_id' => Student::factory(),
            'record_type' => $recordType,
            'title' => $this->generateTitle($recordType),
            'description' => $this->faker->paragraph(),
            'diagnosis' => $this->faker->optional(0.7)->sentence(),
            'treatment' => $this->faker->optional(0.8)->paragraph(),
            'prescription' => $this->faker->optional(0.6)->paragraph(),
            'notes' => $this->faker->optional(0.5)->paragraph(),
            'doctor_name' => $this->faker->name(),
            'clinic_name' => $this->faker->company(),
            'clinic_address' => $this->faker->address(),
            'doctor_contact' => $this->faker->phoneNumber(),
            'visit_date' => $visitDate,
            'next_appointment' => $this->faker->optional(0.4)->dateTimeBetween($visitDate, '+6 months'),
            'follow_up_date' => $this->faker->optional(0.3)->dateTimeBetween($visitDate, '+3 months'),
            'status' => $this->faker->randomElement(MedicalRecordStatus::cases()),
            'priority' => $this->faker->randomElement(MedicalRecordPriority::cases()),
            'is_confidential' => $this->faker->boolean(20), // 20% chance of being confidential
            'height' => $this->faker->optional(0.7)->randomFloat(2, 140, 200), // 140-200 cm
            'weight' => $this->faker->optional(0.7)->randomFloat(2, 40, 120), // 40-120 kg
            'blood_pressure_systolic' => $this->faker->optional(0.6)->numberBetween(90, 180),
            'blood_pressure_diastolic' => $this->faker->optional(0.6)->numberBetween(60, 110),
            'temperature' => $this->faker->optional(0.8)->randomFloat(1, 36.0, 39.5),
            'heart_rate' => $this->faker->optional(0.6)->numberBetween(60, 120),
            'vital_signs' => $this->faker->optional(0.3)->randomElements([
                ['name' => 'Oxygen Saturation', 'value' => '98', 'unit' => '%'],
                ['name' => 'Respiratory Rate', 'value' => '16', 'unit' => 'breaths/min'],
                ['name' => 'Blood Glucose', 'value' => '90', 'unit' => 'mg/dL'],
            ], $this->faker->numberBetween(1, 3)),
            'lab_results' => $this->faker->optional(0.4)->randomElements([
                ['test_name' => 'Complete Blood Count', 'result' => 'Normal', 'normal_range' => 'Normal', 'unit' => ''],
                ['test_name' => 'Cholesterol', 'result' => '180', 'normal_range' => '<200', 'unit' => 'mg/dL'],
                ['test_name' => 'Blood Sugar', 'result' => '95', 'normal_range' => '70-100', 'unit' => 'mg/dL'],
            ], $this->faker->numberBetween(1, 2)),
            'emergency_contact_notified' => $this->faker->boolean(10),
            'emergency_notification_sent_at' => $this->faker->optional(0.1)->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the record is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => MedicalRecordPriority::Urgent,
            'status' => MedicalRecordStatus::Active,
        ]);
    }

    /**
     * Indicate that the record is an emergency.
     */
    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'record_type' => MedicalRecordType::Emergency,
            'priority' => MedicalRecordPriority::Urgent,
            'status' => MedicalRecordStatus::Active,
            'is_confidential' => false,
        ]);
    }

    /**
     * Indicate that the record is confidential.
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_confidential' => true,
        ]);
    }

    /**
     * Indicate that the record needs follow-up.
     */
    public function needsFollowUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'follow_up_date' => $this->faker->dateTimeBetween('now', '+1 week'),
            'status' => MedicalRecordStatus::Ongoing,
        ]);
    }

    /**
     * Indicate that the record is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MedicalRecordStatus::Resolved,
            'follow_up_date' => null,
        ]);
    }

    /**
     * Generate appropriate title based on record type.
     */
    private function generateTitle(MedicalRecordType $recordType): string
    {
        return match ($recordType) {
            MedicalRecordType::Checkup => $this->faker->randomElement([
                'Annual Health Checkup',
                'General Medical Examination',
                'Pre-enrollment Medical Assessment',
                'Routine Health Screening',
            ]),
            MedicalRecordType::Vaccination => $this->faker->randomElement([
                'COVID-19 Vaccination',
                'Flu Shot',
                'Hepatitis B Vaccination',
                'MMR Vaccination',
            ]),
            MedicalRecordType::Allergy => $this->faker->randomElement([
                'Allergy Assessment',
                'Food Allergy Consultation',
                'Seasonal Allergy Treatment',
                'Allergy Testing Results',
            ]),
            MedicalRecordType::Medication => $this->faker->randomElement([
                'Prescription Medication',
                'Over-the-counter Medication',
                'Pain Management',
                'Antibiotic Treatment',
            ]),
            MedicalRecordType::Emergency => $this->faker->randomElement([
                'Emergency Room Visit',
                'Accident Injury',
                'Acute Illness',
                'Emergency Medical Intervention',
            ]),
            MedicalRecordType::Dental => $this->faker->randomElement([
                'Dental Cleaning',
                'Tooth Extraction',
                'Dental Checkup',
                'Orthodontic Consultation',
            ]),
            MedicalRecordType::Vision => $this->faker->randomElement([
                'Eye Examination',
                'Vision Screening',
                'Prescription Update',
                'Contact Lens Fitting',
            ]),
            MedicalRecordType::MentalHealth => $this->faker->randomElement([
                'Mental Health Assessment',
                'Counseling Session',
                'Stress Management Consultation',
                'Psychological Evaluation',
            ]),
            MedicalRecordType::Laboratory => $this->faker->randomElement([
                'Blood Test',
                'Urine Analysis',
                'Lab Work Results',
                'Diagnostic Testing',
            ]),
            MedicalRecordType::Surgery => $this->faker->randomElement([
                'Minor Surgery',
                'Surgical Consultation',
                'Post-surgery Follow-up',
                'Surgical Procedure',
            ]),
            MedicalRecordType::FollowUp => $this->faker->randomElement([
                'Follow-up Visit',
                'Progress Check',
                'Treatment Follow-up',
                'Recovery Assessment',
            ]),
        };
    }
}
