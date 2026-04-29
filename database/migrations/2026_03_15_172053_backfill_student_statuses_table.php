<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_statuses') || ! Schema::hasTable('students')) {
            return;
        }

        // Use raw query to avoid SoftDeletes trait issues
        $settings = DB::table('general_settings')->first();
        $startYear = $settings?->school_starting_date?->format('Y') ?? date('Y');
        $academicYear = $startYear.' - '.((int) $startYear + 1);
        $semester = (int) ($settings?->semester ?? 1);
        $timestamp = now();

        DB::table('students')
            ->select(['id', 'status'])
            ->whereNotNull('status')
            ->orderBy('id')
            ->chunkById(500, function ($students) use ($academicYear, $semester, $timestamp): void {
                $rows = [];

                foreach ($students as $student) {
                    if (! $student->status) {
                        continue;
                    }

                    $rows[] = [
                        'student_id' => $student->id,
                        'academic_year' => $academicYear,
                        'semester' => $semester,
                        'status' => $student->status,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                if ($rows !== []) {
                    DB::table('student_statuses')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        // No rollback for backfill.
    }
};
