<?php

declare(strict_types=1);

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;

it('bootstraps CHED degree programs with departments during setup', function (): void {
    $this->post('/setup', setupPayload([
        'curriculum_framework' => CurriculumFramework::ChedPsg->value,
        'programs' => ['BSIT', 'BSCS'],
    ]))->assertRedirect('/');

    $school = School::query()->first();

    expect($school)->not->toBeNull();
    expect($school?->curriculum_framework)->toBe(CurriculumFramework::ChedPsg);
    expect($school?->curriculum_reference)->toBe('CMO 46 s. 2012 (OBE) + program PSGs');

    $bsit = Course::query()->where('code', 'BSIT')->first();

    expect($bsit)->not->toBeNull();
    $bsitTypeId = $bsit !== null && is_int($bsit->getAttribute('course_type_id')) ? $bsit->getAttribute('course_type_id') : null;
    $bsitDepartmentId = $bsit !== null && is_int($bsit->getAttribute('department_id')) ? $bsit->getAttribute('department_id') : null;

    expect(CourseType::query()->whereKey($bsitTypeId)->value('name'))->toBe("Bachelor's Degree");
    expect(Department::query()->whereKey($bsitDepartmentId)->value('code'))->toBe('IT');
    expect($bsit?->getAttribute('school_id'))->toBe($school?->id);
    expect($bsit?->getAttribute('remarks'))->toContain('CMO 25 s. 2015');

    expect(Course::query()->where('code', 'BSCS')->exists())->toBeTrue();
    expect(Department::query()->where('school_id', $school?->id)->where('code', 'IT')->exists())->toBeTrue();
});

it('bootstraps SHS tracks and strands during setup', function (): void {
    $this->post('/setup', setupPayload([
        'school_level' => SchoolLevel::SeniorHigh->value,
        'curriculum_framework' => CurriculumFramework::DepedShsK12->value,
        'programs' => ['academic:stem', 'tvl:ict'],
    ]))->assertRedirect('/');

    $academic = ShsTrack::query()->where('track_name', 'Academic Track')->first();
    $tvl = ShsTrack::query()->where('track_name', 'Technical-Vocational-Livelihood (TVL) Track')->first();

    expect($academic)->not->toBeNull();
    expect($tvl)->not->toBeNull();
    expect(ShsStrand::query()->where('strand_name', 'STEM')->where('track_id', $academic?->id)->exists())->toBeTrue();
    expect(ShsStrand::query()->where('strand_name', 'ICT')->where('track_id', $tvl?->id)->exists())->toBeTrue();
});

it('preloads SHS core subjects for selected strands when requested', function (): void {
    $this->post('/setup', setupPayload([
        'school_level' => SchoolLevel::SeniorHigh->value,
        'curriculum_framework' => CurriculumFramework::DepedShsK12->value,
        'programs' => ['academic:stem'],
        'seed_strand_subjects' => true,
    ]))->assertRedirect('/');

    $stem = ShsStrand::query()->where('strand_name', 'STEM')->first();

    expect($stem)->not->toBeNull();
    expect(StrandSubject::query()->where('strand_id', $stem?->id)->count())->toBeGreaterThanOrEqual(20);
});

it('bootstraps TESDA qualifications for technical-vocational institutions', function (): void {
    $this->post('/setup', setupPayload([
        'school_level' => SchoolLevel::TechnicalVocational->value,
        'curriculum_framework' => CurriculumFramework::TesdaTr->value,
        'programs' => ['CSS-NC2'],
    ]))->assertRedirect('/');

    $course = Course::query()->where('code', 'CSS-NC2')->first();

    expect($course)->not->toBeNull();
    $courseTypeId = $course !== null && is_int($course->getAttribute('course_type_id')) ? $course->getAttribute('course_type_id') : null;

    expect(CourseType::query()->whereKey($courseTypeId)->value('name'))->toBe('TESDA Qualification (NC I-IV)');
    expect($course?->getAttribute('remarks'))->toContain('NC 2');
    expect(School::query()->first()?->curriculum_framework)->toBe(CurriculumFramework::TesdaTr);
});

it('records the MATATAG framework without creating courses', function (): void {
    $this->post('/setup', setupPayload([
        'school_level' => SchoolLevel::Elementary->value,
        'curriculum_framework' => CurriculumFramework::DepedMatatag->value,
    ]))->assertRedirect('/');

    $school = School::query()->first();

    expect($school?->curriculum_framework)->toBe(CurriculumFramework::DepedMatatag);
    expect($school?->curriculum_reference)->toBe('DepEd Order No. 010, s. 2024');
    expect(Course::query()->count())->toBe(0);
});

it('rejects a curriculum framework that does not match the school level', function (): void {
    $this->post('/setup', setupPayload([
        'school_level' => SchoolLevel::SeniorHigh->value,
        'curriculum_framework' => CurriculumFramework::ChedPsg->value,
    ]))->assertSessionHasErrors('curriculum_framework');
});

it('rejects program codes outside the chosen framework', function (): void {
    $this->post('/setup', setupPayload([
        'curriculum_framework' => CurriculumFramework::ChedPsg->value,
        'programs' => ['CSS-NC2', 'NOT-A-PROGRAM'],
    ]))->assertSessionHasErrors('programs');

    expect(Course::query()->count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function setupPayload(array $overrides = []): array
{
    return array_merge([
        'admin_name' => 'System Administrator',
        'admin_email' => 'admin@example.edu',
        'admin_password' => 'password123',
        'admin_password_confirmation' => 'password123',
        'school_name' => 'Example Academy',
        'school_code' => 'EXA',
        'school_level' => SchoolLevel::HigherEducation->value,
        'school_starting_date' => '2026-06-08',
        'school_ending_date' => '2027-03-31',
        'semester' => '1',
    ], $overrides);
}
