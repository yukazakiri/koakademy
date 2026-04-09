<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassAttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ClassAttendanceRecordFactory extends Factory
{
    protected $model = ClassAttendanceRecord::class;

    public function definition()
    {
        return [
            'class_attendance_session_id' => ClassAttendanceSessionFactory::new(),
            'class_enrollment_id' => \App\Models\ClassEnrollment::factory(),
            'student_id' => \App\Models\Student::factory(),
            'class_id' => \App\Models\Classes::factory(),
            'status' => 'present',
            'remarks' => $this->faker->sentence(),
            'marked_by' => \App\Models\Faculty::factory(),
            'marked_at' => now(),
        ];
    }
}
