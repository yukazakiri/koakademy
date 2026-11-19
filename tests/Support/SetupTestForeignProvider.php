<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SetupCatalogProvider;
use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;
use LogicException;

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
