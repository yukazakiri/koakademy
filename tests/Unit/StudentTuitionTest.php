<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\StudentTuition;

it('returns zero total paid when school year is missing', function () {
    $student = Student::factory()->create();

    $tuition = StudentTuition::make([
        'student_id' => $student->id,
        'school_year' => null,
        'semester' => 1,
        'paid' => null,
    ]);

    $tuition->setRelation('student', $student);

    expect($tuition->total_paid)->toBe(0.0);
});
