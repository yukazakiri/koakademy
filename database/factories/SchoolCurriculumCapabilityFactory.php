<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\School;
use App\Models\SchoolCurriculumCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolCurriculumCapability>
 */
final class SchoolCurriculumCapabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'school_level' => SchoolLevel::HigherEducation,
            'curriculum_framework' => CurriculumFramework::ChedPsg,
            'curriculum_reference' => CurriculumFramework::ChedPsg->getReference(),
            'is_enabled' => true,
        ];
    }
}
