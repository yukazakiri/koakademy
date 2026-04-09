<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GeneralSetting;
use Illuminate\Database\Seeder;

final class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create if no general settings exist
        if (GeneralSetting::query()->count() === 0) {
            GeneralSetting::factory()->create([
                'site_name' => 'KoAkademy',
                'site_description' => 'KoAkademy Administrative System',
                'semester' => 1,
                'curriculum_year' => '2024 - 2025',
                'school_starting_date' => '2024-08-15',
                'school_ending_date' => '2025-05-30',
            ]);
        }
    }
}
