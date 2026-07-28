<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ClassEnrollmentService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function enrollOnce(int $studentId, int $classId, array $attributes = []): ClassEnrollment
    {
        return DB::transaction(function () use ($studentId, $classId, $attributes): ClassEnrollment {
            Student::query()->whereKey($studentId)->lockForUpdate()->firstOrFail();

            return $this->enrollOnceWhileLocked($studentId, $classId, $attributes);
        });
    }

    /**
     * The caller must already hold a row lock for the student.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function enrollOnceWhileLocked(int $studentId, int $classId, array $attributes = []): ClassEnrollment
    {
        $existing = ClassEnrollment::query()
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->first();

        if ($existing instanceof ClassEnrollment) {
            return $existing;
        }

        return ClassEnrollment::query()->create([
            ...Arr::except($attributes, ['id', 'student_id', 'class_id', 'deleted_at']),
            'student_id' => $studentId,
            'class_id' => $classId,
        ]);
    }
}
