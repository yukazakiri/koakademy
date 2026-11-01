<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('assigns school_id matching the student across multiple schools', function (): void {
    $schoolA = School::factory()->create(['name' => 'School Alpha', 'code' => 'ALPHA']);
    $schoolB = School::factory()->create(['name' => 'School Beta', 'code' => 'BETA']);

    $studentA = Student::factory()->create(['school_id' => $schoolA->id]);
    $studentB = Student::factory()->create(['school_id' => $schoolB->id]);

    $statusAId = DB::table('student_statuses')->insertGetId([
        'student_id' => $studentA->id,
        'academic_year' => '2026-2027',
        'semester' => 1,
        'status' => 'enrolled',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $statusBId = DB::table('student_statuses')->insertGetId([
        'student_id' => $studentB->id,
        'academic_year' => '2026-2027',
        'semester' => 1,
        'status' => 'enrolled',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require base_path('database/migrations/2026_03_21_100302_fix_school_id_on_student_statuses_table.php');
    $migration->up();

    $statusA = DB::table('student_statuses')->where('id', $statusAId)->first();
    $statusB = DB::table('student_statuses')->where('id', $statusBId)->first();

    expect($statusA->school_id)->toBe($schoolA->id)
        ->and($statusB->school_id)->toBe($schoolB->id);
});

it('falls back to dynamically resolved default school when student has no school_id', function (): void {
    $defaultSchool = School::factory()->create([
        'name' => 'Default Main School',
        'code' => 'DEFAULT',
        'is_active' => true,
    ]);

    $studentWithoutSchool = Student::factory()->create(['school_id' => null]);

    $statusId = DB::table('student_statuses')->insertGetId([
        'student_id' => $studentWithoutSchool->id,
        'academic_year' => '2026-2027',
        'semester' => 1,
        'status' => 'enrolled',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require base_path('database/migrations/2026_03_21_100302_fix_school_id_on_student_statuses_table.php');
    $migration->up();

    $status = DB::table('student_statuses')->where('id', $statusId)->first();

    expect($status->school_id)->toBe($defaultSchool->id);
});

it('does not abort migration when schools table is empty and retains null school_id', function (): void {
    Schema::disableForeignKeyConstraints();
    DB::table('schools')->delete();
    DB::table('students')->delete();
    DB::table('student_statuses')->delete();
    Schema::enableForeignKeyConstraints();

    $studentId = DB::table('students')->insertGetId([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'birth_date' => '2005-01-01',
        'gender' => 'female',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $statusId = DB::table('student_statuses')->insertGetId([
        'student_id' => $studentId,
        'academic_year' => '2026-2027',
        'semester' => 1,
        'status' => 'enrolled',
        'school_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require base_path('database/migrations/2026_03_21_100302_fix_school_id_on_student_statuses_table.php');
    $migration->up();

    $status = DB::table('student_statuses')->where('id', $statusId)->first();

    expect(DB::table('schools')->count())->toBe(0)
        ->and($status->school_id)->toBeNull();
});

it('executes down and up cleanly on student_statuses migration', function (): void {
    /** @var Migration $migration */
    $migration = require base_path('database/migrations/2026_03_21_100302_fix_school_id_on_student_statuses_table.php');

    $migration->down();
    expect(Schema::hasColumn('student_statuses', 'school_id'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('student_statuses', 'school_id'))->toBeTrue();
});

it('executes down and up cleanly on student_status_records migration when table exists', function (): void {
    Schema::create('student_status_records', function ($table): void {
        $table->id();
        $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
        $table->timestamps();
    });

    /** @var Migration $migration */
    $migration = require base_path('database/migrations/2026_03_21_095840_add_school_id_to_student_status_records_table.php');

    $migration->up();
    expect(Schema::hasColumn('student_status_records', 'school_id'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('student_status_records', 'school_id'))->toBeFalse();

    Schema::dropIfExists('student_status_records');
});

it('does not contain hardcoded school_id 4 in the migration source files', function (): void {
    $files = [
        base_path('database/migrations/2026_03_21_100302_fix_school_id_on_student_statuses_table.php'),
        base_path('database/migrations/2026_03_21_095840_add_school_id_to_student_status_records_table.php'),
        base_path('database/migrations/2026_03_21_000504_backfill_school_id_to_existing_records.php'),
    ];

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        expect($content)->not->toMatch('/[\'"]school_id[\'"]\s*=>\s*4\b/')
            ->and($content)->not->toMatch('/school_id\s*=\s*4\b/');
    }
});
