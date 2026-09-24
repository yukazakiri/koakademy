<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Services\CurriculumBootstrapService;

final class PhilippineSetupCatalogProvider implements SetupCatalogProvider
{
    public function __construct(private CurriculumBootstrapService $bootstrapService) {}

    public function frameworks(SchoolLevel $level): array
    {
        return array_values(array_filter(
            CurriculumFramework::cases(),
            fn (CurriculumFramework $framework): bool => in_array($level, $framework->schoolLevels(), true),
        ));
    }

    public function catalog(SchoolLevel $level): array
    {
        $ched = $level === SchoolLevel::HigherEducation ? PhilippineCurriculumCatalog::chedClusters() : [];
        $tesda = in_array($level, [SchoolLevel::HigherEducation, SchoolLevel::TechnicalVocational], true)
            ? PhilippineCurriculumCatalog::tesdaSectors() : [];
        $legacy = $level === SchoolLevel::SeniorHigh ? PhilippineCurriculumCatalog::shsTracksLegacy() : [];
        $revised = $level === SchoolLevel::SeniorHigh ? PhilippineCurriculumCatalog::shsTracksRevised() : [];

        $programGroups = [];

        if ($ched !== []) {
            $programGroups[CurriculumFramework::ChedPsg->value] = array_map(fn (array $cluster): array => [
                'key' => $cluster['key'],
                'label' => $cluster['label'],
                'programs' => array_map(fn (array $program): array => [
                    'code' => $program['code'],
                    'title' => $program['title'],
                    'description' => $program['description'],
                    'meta' => $program['units'].' units · '.$program['year_level'].'-year · '.$program['reference'],
                    'hint' => $program['verified'] ? null : 'Issuance number not yet verified — confirm with CHED.',
                ], $cluster['programs']),
            ], $ched);
        }

        if ($tesda !== []) {
            $programGroups[CurriculumFramework::TesdaTr->value] = array_map(fn (array $sector): array => [
                'key' => $sector['key'],
                'label' => $sector['label'],
                'programs' => array_map(fn (array $qualification): array => [
                    'code' => $qualification['code'],
                    'title' => $qualification['title'],
                    'description' => $qualification['description'],
                    'meta' => $qualification['diploma']
                        ? 'TESDA Diploma · PQF Level '.$qualification['pqf_level']
                        : 'NC '.$qualification['nc_level'].' · PQF Level '.$qualification['pqf_level'].' · '.$qualification['reference'],
                    'hint' => $qualification['superseded'] ? 'Superseded TR — a newer Training Regulation exists.' : null,
                ], $sector['qualifications']),
            ], $tesda);
        }

        foreach ([CurriculumFramework::DepedShsK12->value => $legacy, CurriculumFramework::DepedShsRevised->value => $revised] as $framework => $tracks) {
            if ($tracks === []) {
                continue;
            }

            $programGroups[$framework] = array_map(fn (array $track): array => [
                'key' => $track['key'],
                'label' => $track['name'],
                'programs' => array_map(fn (array $strand): array => [
                    'code' => $track['key'].':'.$strand['key'],
                    'title' => $strand['name'],
                    'description' => $strand['description'],
                    'meta' => $track['description'],
                ], $track['strands']),
            ], $tracks);
        }

        return [
            'program_groups' => $programGroups,
            'as_of' => PhilippineCurriculumCatalog::AS_OF,
            'ched' => $ched,
            'shs' => ['legacy' => $legacy, 'revised' => $revised],
            'tesda' => $tesda,
            'matatag' => [
                'phases' => in_array($level, [SchoolLevel::Elementary, SchoolLevel::JuniorHigh], true) ? PhilippineCurriculumCatalog::matatagPhases() : [],
                'learning_areas' => in_array($level, [SchoolLevel::Elementary, SchoolLevel::JuniorHigh], true) ? PhilippineCurriculumCatalog::matatagLearningAreas() : [],
            ],
            'calendars' => PhilippineCurriculumCatalog::calendarPresets(),
        ];
    }

    public function validProgramCodes(CurriculumFramework $framework): array
    {
        return PhilippineCurriculumCatalog::validProgramCodes($framework);
    }

    public function bootstrap(School $school, CurriculumFramework $framework, array $programCodes, string $curriculumYear, bool $seedStrandSubjects): void
    {
        $this->bootstrapService->bootstrap($school, $framework, $programCodes, $curriculumYear, $seedStrandSubjects);
    }
}
