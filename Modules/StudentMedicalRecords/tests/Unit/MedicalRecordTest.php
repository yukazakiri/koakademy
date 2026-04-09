<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\StudentMedicalRecords\Enums\MedicalRecordPriority;
use Modules\StudentMedicalRecords\Enums\MedicalRecordStatus;
use Modules\StudentMedicalRecords\Enums\MedicalRecordType;
use Modules\StudentMedicalRecords\Models\MedicalRecord;
use Tests\TestCase;

final class MedicalRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_medical_record(): void
    {
        $medicalRecord = $this->createMedicalRecord();

        $this->assertDatabaseHas('medical_records', [
            'id' => $medicalRecord->id,
            'student_id' => $medicalRecord->student_id,
            'title' => $medicalRecord->title,
        ]);
    }

    public function test_medical_record_attributes(): void
    {
        $medicalRecord = $this->createMedicalRecord([
            'title' => 'Test Title',
            'description' => 'Test Description',
        ]);

        $this->assertEquals('Test Title', $medicalRecord->title);
        $this->assertEquals('Test Description', $medicalRecord->description);
        $this->assertEquals(MedicalRecordType::Checkup, $medicalRecord->record_type);
    }

    public function test_can_calculate_bmi(): void
    {
        $medicalRecord = $this->createMedicalRecord([
            'height' => 175.0, // 175 cm
            'weight' => 70.0,  // 70 kg
        ]);

        $expectedBmi = 70.0 / ((175.0 / 100) ** 2);

        $this->assertEquals(round($expectedBmi, 1), $medicalRecord->bmi);
    }

    public function test_bmi_status_calculation(): void
    {
        // Test normal BMI
        $normalRecord = $this->createMedicalRecord([
            'height' => 175.0,
            'weight' => 70.0,
        ]);

        $this->assertEquals('Normal', $normalRecord->bmi_status);

        // Test overweight BMI
        $overweightRecord = $this->createMedicalRecord([
            'height' => 175.0,
            'weight' => 80.0,
        ]);

        $this->assertEquals('Overweight', $overweightRecord->bmi_status);
    }

    public function test_blood_pressure_status_calculation(): void
    {
        // Test normal blood pressure
        $normalRecord = $this->createMedicalRecord([
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
        ]);

        $this->assertEquals('Normal', $normalRecord->blood_pressure_status);

        // Test high blood pressure
        $highRecord = $this->createMedicalRecord([
            'blood_pressure_systolic' => 150,
            'blood_pressure_diastolic' => 95,
        ]);

        $this->assertEquals('High Stage 2', $highRecord->blood_pressure_status);
    }

    public function test_urgent_record_scope(): void
    {
        $this->createMedicalRecord(); // Normal record
        $this->createMedicalRecord(['priority' => MedicalRecordPriority::Urgent]); // Urgent record
        $this->createMedicalRecord(['priority' => MedicalRecordPriority::Urgent]); // Another urgent record

        $urgentRecords = MedicalRecord::urgent()->get();

        $this->assertCount(2, $urgentRecords);
        $this->assertTrue($urgentRecords->every(fn ($record) => $record->priority === MedicalRecordPriority::Urgent));
    }

    public function test_emergency_record_scope(): void
    {
        $this->createMedicalRecord(); // Normal record
        $this->createMedicalRecord(['record_type' => MedicalRecordType::Emergency]); // Emergency record
        $this->createMedicalRecord(['record_type' => MedicalRecordType::Emergency]); // Another emergency record

        $emergencyRecords = MedicalRecord::emergency()->get();

        $this->assertCount(2, $emergencyRecords);
        $this->assertTrue($emergencyRecords->every(fn ($record) => $record->record_type === MedicalRecordType::Emergency));
    }

    public function test_needs_follow_up_scope(): void
    {
        $this->createMedicalRecord(); // Normal record
        $this->createMedicalRecord([
            'follow_up_date' => now()->addDays(3),
            'status' => MedicalRecordStatus::Ongoing,
        ]); // Needs follow-up
        $this->createMedicalRecord([
            'status' => MedicalRecordStatus::Resolved,
        ]); // Resolved record

        $needsFollowUp = MedicalRecord::query()->needsFollowUp()->get();

        $this->assertCount(1, $needsFollowUp);
        $this->assertNotNull($needsFollowUp->first()->follow_up_date);
    }

    public function test_confidential_scope(): void
    {
        $this->createMedicalRecord(); // Normal record
        $this->createMedicalRecord(['is_confidential' => true]); // Confidential record
        $this->createMedicalRecord(['is_confidential' => true]); // Another confidential record

        $confidentialRecords = MedicalRecord::confidential()->get();

        $this->assertCount(2, $confidentialRecords);
        $this->assertTrue($confidentialRecords->every(fn ($record) => $record->is_confidential));
    }

    public function test_record_type_scope(): void
    {
        $this->createMedicalRecord(['record_type' => MedicalRecordType::Checkup]);
        $this->createMedicalRecord(['record_type' => MedicalRecordType::Vaccination]);
        $this->createMedicalRecord(['record_type' => MedicalRecordType::Checkup]);

        $checkupRecords = MedicalRecord::ofType(MedicalRecordType::Checkup)->get();

        $this->assertCount(2, $checkupRecords);
        $this->assertTrue($checkupRecords->every(fn ($record) => $record->record_type === MedicalRecordType::Checkup));
    }

    public function test_emergency_contact_notification(): void
    {
        $medicalRecord = $this->createMedicalRecord(['record_type' => MedicalRecordType::Emergency]);

        $this->assertFalse($medicalRecord->emergency_contact_notified ?? false);

        $medicalRecord->markEmergencyContactNotified();

        $this->assertTrue($medicalRecord->fresh()->emergency_contact_notified);
        $this->assertNotNull($medicalRecord->fresh()->emergency_notification_sent_at);
    }

    public function test_formatted_visit_date(): void
    {
        $medicalRecord = $this->createMedicalRecord(['visit_date' => '2025-01-15']);

        $this->assertEquals('Jan 15, 2025', $medicalRecord->formatted_visit_date);
    }

    public function test_record_is_urgent_check(): void
    {
        $urgentRecord = $this->createMedicalRecord(['priority' => MedicalRecordPriority::Urgent]);
        $normalRecord = $this->createMedicalRecord(['priority' => MedicalRecordPriority::Normal]);

        $this->assertTrue($urgentRecord->isUrgent());
        $this->assertFalse($normalRecord->isUrgent());
    }

    public function test_record_is_emergency_check(): void
    {
        $emergencyRecord = $this->createMedicalRecord(['record_type' => MedicalRecordType::Emergency]);
        $normalRecord = $this->createMedicalRecord(['record_type' => MedicalRecordType::Checkup]);

        $this->assertTrue($emergencyRecord->isEmergency());
        $this->assertFalse($normalRecord->isEmergency());
    }

    public function test_record_needs_follow_up_check(): void
    {
        $followUpRecord = $this->createMedicalRecord([
            'follow_up_date' => now()->addDays(3),
            'status' => MedicalRecordStatus::Ongoing,
        ]);
        $resolvedRecord = $this->createMedicalRecord([
            'status' => MedicalRecordStatus::Resolved,
        ]);

        $this->assertTrue($followUpRecord->needsFollowUp());
        $this->assertFalse($resolvedRecord->needsFollowUp());
    }

    private function createMedicalRecord(array $attributes = []): MedicalRecord
    {
        return MedicalRecord::create(array_merge([
            'student_id' => 1, // We'll use a simple ID since we're testing the model directly
            'record_type' => MedicalRecordType::Checkup,
            'title' => 'Test Medical Record',
            'description' => 'Test description',
            'visit_date' => now(),
            'status' => MedicalRecordStatus::Active,
            'priority' => MedicalRecordPriority::Normal,
            'is_confidential' => false,
        ], $attributes));
    }
}
