<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;

/**
 * Country-specific setup contributions. Register a provider class under its ISO alpha-2
 * country code in config/setup_catalog.php; the registry passes the institution
 * level to frameworks() and catalog(). To contribute a new non-PH framework:
 * 1. Add its case and countryCode(), schoolLevels(), labels/reference/source to
 *    CurriculumFramework (School and SchoolCurriculumCapability cast this enum).
 * 2. Update database enum/check constraints with a migration if they restrict the
 *    column. Unknown string IDs cannot be contributed by config alone: Rule::enum
 *    and model casts reject them.
 * 3. Implement this interface: return only that country's framework cases at their
 *    supported levels; expose selectable program_groups keyed by framework value;
 *    validate those same codes and create their records in bootstrap(). PH delegates
 *    bootstrap() to CurriculumBootstrapService; other countries can use their own
 *    implementation. Register the provider in config/setup_catalog.php and test it.
 * 4. Ensure the setup UI supports any new framework-specific interaction or data.
 */
interface SetupCatalogProvider
{
    /** @return list<CurriculumFramework> */
    public function frameworks(SchoolLevel $level): array;

    /**
     * Return country-appropriate data for this level. program_groups maps persisted
     * framework values to lists of {key, label, programs: [{code, title,
     * description?, meta?, hint?}]}. Program codes must match validProgramCodes()
     * and bootstrap(); legacy ched/shs/tesda/matatag/calendars keys remain optional.
     *
     * @return array<string, mixed>
     */
    public function catalog(SchoolLevel $level): array;

    /** @return list<string> Valid program codes for a framework returned by frameworks(). */
    public function validProgramCodes(CurriculumFramework $framework): array;

    /**
     * Persist the selected framework's curriculum inside the setup transaction.
     * Called only for a framework allowed for the school's country and level.
     *
     * @param  list<string>  $programCodes
     */
    public function bootstrap(School $school, CurriculumFramework $framework, array $programCodes, string $curriculumYear, bool $seedStrandSubjects): void;
}
