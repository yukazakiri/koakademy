<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;
use LogicException;

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
