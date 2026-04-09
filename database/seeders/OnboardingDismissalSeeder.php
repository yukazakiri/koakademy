<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OnboardingDismissal;
use Illuminate\Database\Seeder;

final class OnboardingDismissalSeeder extends Seeder
{
    public function run(): void
    {
        OnboardingDismissal::factory()->count(2)->create();
    }
}
