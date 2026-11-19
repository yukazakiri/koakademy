<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;

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
