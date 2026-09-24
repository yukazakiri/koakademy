<?php

declare(strict_types=1);

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\Course;
use App\Models\CourseType;
use App\Models\Department;
use App\Models\School;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Support\SetupCatalogRegistry;
use Inertia\Testing\AssertableInertia as Assert;

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

it('returns the PH catalog by default and scopes its frameworks by level when requested', function (): void {
    $this->get('/setup')->assertInertia(fn (Assert $page): Assert => $page
        ->component('setup/index')
        ->where('catalog.frameworks.0.value', 'ched_psg')
        ->where('catalog.by_country.PH.frameworks.0.value', 'ched_psg')
        ->where('catalog.by_country.PH.shs.legacy.0.key', 'academic')
        ->has('catalog.ched')
        ->etc());

    $this->get('/setup?country_code=PH&school_level=senior_high')->assertInertia(fn (Assert $page): Assert => $page
        ->where('catalog.frameworks.0.value', 'deped_shs_k12')
        ->missing('catalog.ched.0')
        ->has('catalog.shs.legacy')
        ->etc());
});

it('exposes structured PH groups across levels while preserving detailed legacy catalog records', function (): void {
    $this->get('/setup')->assertInertia(fn (Assert $page): Assert => $page
        ->where('catalog.by_country.PH.program_groups.ched_psg.0.programs.0.code', 'BSIT')
        ->where('catalog.by_country.PH.program_groups.ched_psg.0.programs.0.title', 'Bachelor of Science in Information Technology')
        ->where('catalog.by_country.PH.program_groups.ched_psg.0.programs.0.meta', '120 units · 4-year · CMO 25 s. 2015 (ICT PSGs)')
        ->where('catalog.by_country.PH.ched.0.programs.0.units', 120)
        ->where('catalog.by_country.PH.program_groups.tesda_tr.0.programs.0.code', 'CSS-NC2')
        ->where('catalog.by_country.PH.program_groups.deped_shs_k12.0.programs.0.code', 'academic:stem')
        ->where('catalog.by_country.PH.program_groups.deped_shs_revised.0.programs.0.code', 'academic:stem')
        ->etc());

    $this->get('/setup?country_code=PH&school_level=senior_high')->assertInertia(fn (Assert $page): Assert => $page
        ->missing('catalog.program_groups.ched_psg')
        ->missing('catalog.program_groups.tesda_tr')
        ->where('catalog.program_groups.deped_shs_k12.0.key', 'academic')
        ->etc());
});

it('exposes selectable ISO countries with display names and registered catalogs for switching without reload', function (): void {
    $this->get('/setup')->assertInertia(fn (Assert $page): Assert => $page
        ->where('catalog.countries.0.code', 'AD')
        ->where('catalog.countries.0.name', 'Andorra')
        ->where('catalog.by_country.PH.frameworks.0.value', 'ched_psg')
        ->missing('catalog.by_country.US')
        ->etc());
});

it('does not send Philippine framework and program data in the selected non-PH catalog', function (): void {
    $this->get('/setup?country_code=US')->assertInertia(fn (Assert $page): Assert => $page
        ->component('setup/index')
        ->where('catalog.frameworks', [])
        ->where('catalog.ched', [])
        ->where('catalog.shs.legacy', [])
        ->where('catalog.shs.revised', [])
        ->where('catalog.tesda', [])
        ->where('catalog.matatag.phases', [])
        ->where('catalog.calendars', [])
        ->where('catalog.program_groups', [])
        ->etc());
});

it('rejects PH-only setup inputs for another country without writing setup data', function (array $inputs, string $field): void {
    $this->post('/setup', setupPayload(array_merge(['country_code' => 'US', 'currency' => 'USD'], $inputs)))
        ->assertSessionHasErrors($field);

    expect(School::query()->exists())->toBeFalse();
    expect(Course::query()->exists())->toBeFalse();
})->with([
    'framework' => [['curriculum_framework' => 'ched_psg'], 'curriculum_framework'],
    'program' => [['programs' => ['BSIT']], 'programs'],
    'preload' => [['seed_strand_subjects' => true], 'seed_strand_subjects'],
]);

it('rejects program selection without a framework even in the Philippines', function (): void {
    $this->post('/setup', setupPayload(['programs' => ['BSIT']]))->assertSessionHasErrors('programs');

    expect(School::query()->exists())->toBeFalse();
});

it('requires an explicit currency for a non-PH institution', function (): void {
    $this->post('/setup', setupPayload(['country_code' => 'US']))->assertSessionHasErrors('currency');

    expect(School::query()->exists())->toBeFalse();
});

it('rejects an invalid currency code for a standard institution', function (): void {
    $this->post('/setup', setupPayload(['country_code' => 'US', 'currency' => 'INVALID']))
        ->assertSessionHasErrors('currency');

    expect(School::query()->exists())->toBeFalse();
});

it('allows standard setup without a curriculum framework outside the Philippines', function (): void {
    $this->post('/setup', setupPayload(['country_code' => 'US', 'currency' => 'USD']))->assertRedirect('/');

    expect(School::query()->first()?->country_code)->toBe('US');
    expect(School::query()->first()?->curriculum_framework)->toBeNull();
    expect(App\Models\GeneralSetting::query()->first()?->currency)->toBe('USD');
    expect(Course::query()->exists())->toBeFalse();
});

it('resolves PH provider contributions by institution level and program', function (): void {
    $registry = app(SetupCatalogRegistry::class);

    expect($registry->frameworks(' ph ', SchoolLevel::SeniorHigh))->toBe([
        CurriculumFramework::DepedShsK12,
        CurriculumFramework::DepedShsRevised,
    ]);
    expect($registry->provider('PH')?->validProgramCodes(CurriculumFramework::ChedPsg))->toContain('BSIT');
    expect($registry->catalog('PH', SchoolLevel::Elementary)['ched'])->toBe([]);
});

it('keeps PH frameworks unavailable even if another country provider returns them', function (): void {
    config(['setup_catalog.providers.US' => SetupTestForeignProvider::class]);
    $registry = app(SetupCatalogRegistry::class);

    expect($registry->frameworks('US', SchoolLevel::HigherEducation))->toBe([]);
    expect($registry->catalog('US', SchoolLevel::HigherEducation)['frameworks'])->toBe([]);

    $this->get('/setup?country_code=US')->assertInertia(fn (Assert $page): Assert => $page
        ->where('catalog.frameworks', [])
        ->where('catalog.by_country.US.frameworks', [])
        ->where('catalog.by_country.PH.frameworks.0.value', 'ched_psg')
        ->etc());

    $this->post('/setup', setupPayload([
        'country_code' => 'US',
        'currency' => 'USD',
        'curriculum_framework' => CurriculumFramework::ChedPsg->value,
    ]))->assertSessionHasErrors('curriculum_framework');
});

it('includes a registered foreign provider catalog without mixing it into PH', function (): void {
    config(['setup_catalog.providers.US' => SetupTestForeignCatalogProvider::class]);

    $this->get('/setup?country_code=US')->assertInertia(fn (Assert $page): Assert => $page
        ->where('catalog.calendars.0.key', 'us-calendar')
        ->where('catalog.by_country.US.calendars.0.key', 'us-calendar')
        ->where('catalog.by_country.US.frameworks', [])
        ->where('catalog.by_country.PH.frameworks.0.value', 'ched_psg')
        ->where('catalog.by_country.PH.calendars.0.key', 'sy2026-2027-3term')
        ->etc());
});

it('merges groups for the same framework across supported levels', function (): void {
    config(['setup_catalog.providers.PH' => SetupTestMultiLevelProvider::class]);

    $catalog = app(SetupCatalogRegistry::class)->catalog('PH', null);

    expect($catalog['program_groups']['tesda_tr'])->toHaveCount(2);
    expect($catalog['program_groups']['tesda_tr'][0]['key'])->toBe('higher');
    expect($catalog['program_groups']['tesda_tr'][1]['key'])->toBe('technical');
});

it('uses the selected provider to bootstrap setup instead of calling the PH service directly', function (): void {
    config(['setup_catalog.providers.PH' => SetupTestMultiLevelProvider::class]);

    $this->post('/setup', setupPayload([
        'curriculum_framework' => CurriculumFramework::TesdaTr->value,
        'programs' => ['TEST-QUALIFICATION'],
    ]))->assertRedirect('/');

    expect(School::query()->first()?->description)->toBe('Bootstrapped TEST-QUALIFICATION');
});

it('rejects configured providers that do not implement the provider contract', function (): void {
    config(['setup_catalog.providers.US' => stdClass::class]);

    expect(fn (): ?SetupCatalogProvider => app(SetupCatalogRegistry::class)->provider('US'))
        ->toThrow(InvalidArgumentException::class);
});

final class SetupTestForeignProvider implements SetupCatalogProvider
{
    public function frameworks(SchoolLevel $level): array
    {
        return [CurriculumFramework::ChedPsg];
    }

    public function catalog(SchoolLevel $level): array
    {
        return [];
    }

    public function validProgramCodes(CurriculumFramework $framework): array
    {
        return ['BSIT'];
    }

    public function bootstrap(School $school, CurriculumFramework $framework, array $programCodes, string $curriculumYear, bool $seedStrandSubjects): void
    {
        throw new LogicException('Foreign provider bootstrap must not be called for a PH framework.');
    }
}

final class SetupTestForeignCatalogProvider implements SetupCatalogProvider
{
    public function frameworks(SchoolLevel $level): array
    {
        return [];
    }

    public function catalog(SchoolLevel $level): array
    {
        return $level === SchoolLevel::HigherEducation
            ? ['calendars' => [['key' => 'us-calendar', 'label' => 'US calendar']]]
            : [];
    }

    public function validProgramCodes(CurriculumFramework $framework): array
    {
        return [];
    }

    public function bootstrap(School $school, CurriculumFramework $framework, array $programCodes, string $curriculumYear, bool $seedStrandSubjects): void
    {
        throw new LogicException('Foreign provider has no frameworks to bootstrap.');
    }
}

final class SetupTestMultiLevelProvider implements SetupCatalogProvider
{
    public function frameworks(SchoolLevel $level): array
    {
        return in_array($level, [SchoolLevel::HigherEducation, SchoolLevel::TechnicalVocational], true)
            ? [CurriculumFramework::TesdaTr] : [];
    }

    public function catalog(SchoolLevel $level): array
    {
        return match ($level) {
            SchoolLevel::HigherEducation, SchoolLevel::TechnicalVocational => [
                'program_groups' => [CurriculumFramework::TesdaTr->value => [[
                    'key' => $level === SchoolLevel::HigherEducation ? 'higher' : 'technical',
                    'label' => 'Test qualifications',
                    'programs' => [['code' => 'TEST-QUALIFICATION', 'title' => 'Test qualification']],
                ]]],
            ],
            default => [],
        };
    }

    public function validProgramCodes(CurriculumFramework $framework): array
    {
        return ['TEST-QUALIFICATION'];
    }

    public function bootstrap(School $school, CurriculumFramework $framework, array $programCodes, string $curriculumYear, bool $seedStrandSubjects): void
    {
        $school->update(['description' => 'Bootstrapped '.implode(', ', $programCodes)]);
    }
}

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
        'country_code' => 'PH',
        'school_level' => SchoolLevel::HigherEducation->value,
        'school_starting_date' => '2026-06-08',
        'school_ending_date' => '2027-03-31',
        'semester' => '1',
    ], $overrides);
}
