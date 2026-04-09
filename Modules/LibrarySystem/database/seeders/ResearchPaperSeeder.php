<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\LibrarySystem\Models\ResearchPaper;

final class ResearchPaperSeeder extends Seeder
{
    public function run(): void
    {
        ResearchPaper::factory()
            ->count(5)
            ->create();
    }
}
