<?php

declare(strict_types=1);

use App\Models\Classes;
use App\Models\Course;
use App\Models\School;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function academicYearRepairCandidate(School $school, int $subjectYear = 4): Classes
{
    $course = Course::factory()->create(['school_id' => $school->id]);
    $subject = Subject::factory()->for($course)->create(['academic_year' => $subjectYear]);

    return Classes::factory()->create([
        'school_id' => $school->id,
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
}

it('reports and applies only unanimous current-period class year corrections', function (): void {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $yearFourCourse = Course::factory()->create(['school_id' => $school->id]);
    $yearThreeCourse = Course::factory()->create(['school_id' => $school->id]);
    $otherSchoolCourse = Course::factory()->create(['school_id' => $otherSchool->id]);
    $yearFourSubject = Subject::factory()->for($yearFourCourse)->create([
        'code' => 'FS-HM',
        'academic_year' => 4,
    ]);
    $yearThreeSubject = Subject::factory()->for($yearThreeCourse)->create([
        'code' => 'FS-BA',
        'academic_year' => 3,
    ]);
    $otherSchoolSubject = Subject::factory()->for($otherSchoolCourse)->create(['academic_year' => 4]);

    $safe = Classes::factory()->create([
        'school_id' => $school->id,
        'subject_id' => $yearFourSubject->id,
        'subject_ids' => [$yearFourSubject->id],
        'course_codes' => [$yearFourCourse->id],
        'subject_code' => 'FS',
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
    $mixed = Classes::factory()->create([
        'school_id' => $school->id,
        'subject_id' => $yearFourSubject->id,
        'subject_ids' => [$yearFourSubject->id, $yearThreeSubject->id],
        'course_codes' => [$yearFourCourse->id, $yearThreeCourse->id],
        'subject_code' => 'FS',
        'academic_year' => 3,
        'school_year' => '2026-2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
    $historical = Classes::factory()->create([
        'school_id' => $school->id,
        'subject_id' => $yearFourSubject->id,
        'subject_ids' => [$yearFourSubject->id],
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'classification' => 'college',
    ]);
    $otherTenant = Classes::factory()->create([
        'school_id' => $otherSchool->id,
        'subject_id' => $otherSchoolSubject->id,
        'subject_ids' => [$otherSchoolSubject->id],
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
    $reportPath = storage_path('framework/testing/class-year-'.Str::uuid().'.json');
    $arguments = [
        '--school-id' => $school->id,
        '--school-year' => '2026 - 2027',
        '--semester' => 1,
        '--report' => $reportPath,
    ];

    try {
        $this->artisan('classes:align-academic-years', $arguments)
            ->expectsOutput('Mode: DRY RUN')
            ->expectsOutput('Safe updates: 1')
            ->expectsOutput('Mixed-year skips: 1')
            ->expectsOutput('Applied: 0')
            ->assertSuccessful();

        expect($safe->fresh()->academic_year)->toBe(1)
            ->and($mixed->fresh()->academic_year)->toBe(3)
            ->and($historical->fresh()->academic_year)->toBe(1)
            ->and($otherTenant->fresh()->academic_year)->toBe(1);

        $dryRunReport = json_decode(File::get($reportPath), true, flags: JSON_THROW_ON_ERROR);

        expect($dryRunReport['mode'])->toBe('dry-run')
            ->and($dryRunReport['summary']['safe_updates'])->toBe(1)
            ->and($dryRunReport['summary']['mixed_year_skips'])->toBe(1)
            ->and($dryRunReport['updates'][0]['class_id'])->toBe($safe->id)
            ->and($dryRunReport['updates'][0]['old_academic_year'])->toBe(1)
            ->and($dryRunReport['updates'][0]['new_academic_year'])->toBe(4)
            ->and($dryRunReport['mixed_year_classes'][0]['class_id'])->toBe($mixed->id);

        $this->artisan('classes:align-academic-years', [...$arguments, '--apply' => true])
            ->expectsOutput('Mode: APPLY')
            ->expectsOutput('Safe updates: 1')
            ->expectsOutput('Mixed-year skips: 1')
            ->expectsOutput('Applied: 1')
            ->assertSuccessful();

        expect($safe->fresh()->academic_year)->toBe(4)
            ->and($mixed->fresh()->academic_year)->toBe(3);

        $this->artisan('classes:align-academic-years', $arguments)
            ->expectsOutput('Safe updates: 0')
            ->expectsOutput('Mixed-year skips: 1')
            ->assertSuccessful();
    } finally {
        File::delete($reportPath);
    }
});

it('does not apply when a candidate leaves the requested period after the scan', function (): void {
    $school = School::factory()->create();
    $candidate = academicYearRepairCandidate($school);
    $moved = false;

    Event::listen('eloquent.retrieved: '.Classes::class, function (Classes $class) use ($candidate, &$moved): void {
        if ($moved || $class->id !== $candidate->id) {
            return;
        }

        $moved = true;
        DB::table('classes')->where('id', $candidate->id)->update(['school_year' => '2027 - 2028']);
    });

    $this->artisan('classes:align-academic-years', [
        '--school-id' => $school->id,
        '--school-year' => '2026 - 2027',
        '--semester' => 1,
        '--apply' => true,
    ])->assertFailed();

    expect(DB::table('classes')->where('id', $candidate->id)->value('academic_year'))->toBe(1)
        ->and(DB::table('classes')->where('id', $candidate->id)->value('school_year'))->toBe('2027 - 2028');
});

it('fails before applying when the report destination cannot be prepared', function (): void {
    $school = School::factory()->create();
    $candidate = academicYearRepairCandidate($school);
    $blockedParent = storage_path('framework/testing/class-year-blocked-'.Str::uuid());
    File::put($blockedParent, 'not a directory');

    try {
        $this->artisan('classes:align-academic-years', [
            '--school-id' => $school->id,
            '--school-year' => '2026 - 2027',
            '--semester' => 1,
            '--apply' => true,
            '--report' => $blockedParent.'/report.json',
        ])->assertFailed();

        expect($candidate->fresh()->academic_year)->toBe(1);
    } finally {
        File::delete($blockedParent);
    }
});
