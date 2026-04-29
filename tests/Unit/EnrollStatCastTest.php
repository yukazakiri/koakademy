<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EnrollStatCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_legacy_enrolled_status_as_string(): void
    {
        $id = DB::table('student_enrollment')->insertGetId([
            'student_id' => '99999',
            'status' => 'enrolled',
            'semester' => 1,
            'academic_year' => 2024,
            'school_year' => '2024-2025',
        ]);

        $enrollment = StudentEnrollment::find($id);

        $this->assertSame('enrolled', $enrollment->status);
    }

    public function test_it_keeps_unknown_status_values_as_string(): void
    {
        $id = DB::table('student_enrollment')->insertGetId([
            'student_id' => '99999',
            'status' => 'some_weird_unknown_value',
            'semester' => 1,
            'academic_year' => 2024,
            'school_year' => '2024-2025',
        ]);

        $enrollment = StudentEnrollment::find($id);

        $this->assertSame('some_weird_unknown_value', $enrollment->status);
    }
}
