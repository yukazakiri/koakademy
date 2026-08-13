<?php

declare(strict_types=1);

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Support\PhilippineCurriculumCatalog;

it('maps frameworks to their authority school levels', function (): void {
    expect(CurriculumFramework::ChedPsg->schoolLevels())->toBe([SchoolLevel::HigherEducation]);
    expect(CurriculumFramework::DepedMatatag->schoolLevels())->toBe([SchoolLevel::Elementary, SchoolLevel::JuniorHigh]);
    expect(CurriculumFramework::DepedShsK12->schoolLevels())->toBe([SchoolLevel::SeniorHigh]);
    expect(CurriculumFramework::DepedShsRevised->schoolLevels())->toBe([SchoolLevel::SeniorHigh]);
    expect(CurriculumFramework::TesdaTr->schoolLevels())->toBe([SchoolLevel::HigherEducation, SchoolLevel::TechnicalVocational]);
});

it('exposes options for the frontend', function (): void {
    $options = CurriculumFramework::optionsForFrontend();

    expect($options)->toHaveCount(5)
        ->and($options[0])->toHaveKeys(['value', 'label', 'authority', 'description', 'reference', 'school_levels', 'source_url']);
});

it('defines unique CHED program codes with references', function (): void {
    $programs = collect(PhilippineCurriculumCatalog::chedClusters())
        ->flatMap(fn (array $cluster): array => $cluster['programs']);

    $codes = $programs->pluck('code');

    expect($codes->unique()->count())->toBe($codes->count())
        ->and($programs->every(fn (array $program): bool => $program['reference'] !== '' && $program['units'] > 0 && $program['year_level'] > 0))->toBeTrue();
});

it('defines unique TESDA qualification codes with valid NC and PQF levels', function (): void {
    $qualifications = collect(PhilippineCurriculumCatalog::tesdaSectors())
        ->flatMap(fn (array $sector): array => $sector['qualifications']);

    $codes = $qualifications->pluck('code');

    expect($codes->unique()->count())->toBe($codes->count())
        ->and($qualifications->every(fn (array $q): bool => $q['nc_level'] >= 0 && $q['nc_level'] <= 4 && $q['pqf_level'] >= 1 && $q['pqf_level'] <= 5))->toBeTrue();
});

it('defines consistent SHS track and strand keys', function (): void {
    foreach ([PhilippineCurriculumCatalog::shsTracksLegacy(), PhilippineCurriculumCatalog::shsTracksRevised()] as $tracks) {
        foreach ($tracks as $track) {
            expect($track['key'])->not->toBe('')
                ->and($track['strands'])->not->toBeEmpty();
        }
    }
});

it('resolves valid program codes per framework', function (): void {
    $chedCodes = PhilippineCurriculumCatalog::validProgramCodes(CurriculumFramework::ChedPsg);
    $tesdaCodes = PhilippineCurriculumCatalog::validProgramCodes(CurriculumFramework::TesdaTr);
    $shsCodes = PhilippineCurriculumCatalog::validProgramCodes(CurriculumFramework::DepedShsK12);

    expect($chedCodes)->toContain('BSIT')
        ->and($chedCodes)->toContain('DIT')
        ->and($tesdaCodes)->toContain('CSS-NC2')
        ->and($shsCodes)->toContain('academic:stem')
        ->and($shsCodes)->toContain('tvl:ict')
        ->and(PhilippineCurriculumCatalog::validProgramCodes(CurriculumFramework::DepedMatatag))->toBe([])
        ->and(PhilippineCurriculumCatalog::validProgramCodes(null))->toBe([]);
});

it('provides calendar presets with valid dates', function (): void {
    $presets = PhilippineCurriculumCatalog::calendarPresets();

    expect($presets)->not->toBeEmpty()
        ->and($presets[0])->toHaveKeys(['key', 'label', 'starts', 'ends', 'terms', 'source']);
});
