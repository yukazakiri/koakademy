<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DemoUniversitySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoUniversityCoreSeeder::class,
            DemoUniversityAcademicSeeder::class,
            DemoUniversityOperationsSeeder::class,
            DemoUniversityModulesSeeder::class,
        ]);

        $this->command?->info('Demo university dataset is ready.');
    }
}
