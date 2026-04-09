<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Seeders;

use Illuminate\Database\Seeder;

final class LibraryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ResearchPaperSeeder::class,
        ]);
    }
}
