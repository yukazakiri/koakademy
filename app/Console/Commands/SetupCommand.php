<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\SetupExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Override;
use Throwable;

final class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    #[Override]
    protected $signature = 'app:setup
        {--admin-name= : Full name of the initial super administrator}
        {--admin-email= : Email address of the initial super administrator}
        {--admin-password= : Password of the initial super administrator}
        {--school-name= : Institution display name}
        {--school-code= : Unique institution code}
        {--country-code= : Two-letter ISO country code (e.g. US, PH)}
        {--school-level= : Institution level (higher_education, senior_high, junior_high, elementary, technical_vocational)}
        {--school-description= : Short description of the institution}
        {--school-email= : Contact email for the institution}
        {--school-phone= : Contact phone number}
        {--school-location= : Physical address or campus}
        {--dean-name= : Head of academic affairs or dean}
        {--dean-email= : Contact email for the dean}
        {--school-starting-date= : Academic term start date (YYYY-MM-DD)}
        {--school-ending-date= : Academic term end date (YYYY-MM-DD)}
        {--semester= : Term semester number (1, 2, or 3)}
        {--curriculum-year= : Academic year (e.g. 2026-2027)}
        {--curriculum-framework= : Curriculum framework identifier}
        {--programs=* : Preloaded programs}
        {--seed-strand-subjects : Preload Philippine SHS strand subjects}
        {--site-name= : System branding site name}
        {--site-description= : System branding description}
        {--theme-color= : Primary accent color}
        {--currency= : Three-letter ISO currency code (USD, PHP, etc.)}
        {--unattended : Run non-interactively using defaults for missing options}
        {--force : Force execution even if already set up}';

    /**
     * The console command description.
     *
     * @var string
     */
    #[Override]
    protected $description = 'Perform initial application setup and create the first super administrator';

    /**
     * Execute the console command.
     */
    public function handle(SetupExecutionService $setupService): int
    {
        $isSetup = GeneralSetting::query()->where('is_setup', true)->exists()
            || User::where('role', UserRole::SuperAdmin)->exists();

        if ($isSetup && ! $this->option('force')) {
            $this->components->warn('KoAkademy is already set up. Pass --force to proceed anyway.');

            return self::SUCCESS;
        }

        $unattended = (bool) $this->option('unattended') || ! $this->input->isInteractive();

        $adminEmail = (string) ($this->option('admin-email') ?: ($unattended ? 'admin@example.com' : ''));
        if ($adminEmail === '') {
            $adminEmail = (string) $this->ask('Super Administrator Email', 'admin@example.com');
        }

        $adminPassword = (string) ($this->option('admin-password') ?: ($unattended ? 'password' : ''));
        if ($adminPassword === '') {
            $adminPassword = (string) $this->secret('Super Administrator Password');
        }

        if ($adminEmail === '' || $adminPassword === '') {
            $this->components->error('Admin email and password are required.');

            return self::FAILURE;
        }

        $adminName = (string) ($this->option('admin-name') ?: 'System Administrator');
        $schoolName = (string) ($this->option('school-name') ?: 'KoAkademy Institution');
        $schoolCode = (string) ($this->option('school-code') ?: 'KOA');
        $countryCode = mb_strtoupper(mb_trim((string) ($this->option('country-code') ?: 'US')));
        $schoolLevel = (string) ($this->option('school-level') ?: SchoolLevel::HigherEducation->value);

        $now = Carbon::now();
        $startDate = (string) ($this->option('school-starting-date') ?: $now->copy()->startOfYear()->addMonths(7)->format('Y-m-d'));
        $endDate = (string) ($this->option('school-ending-date') ?: $now->copy()->startOfYear()->addYears(1)->addMonths(4)->format('Y-m-d'));
        $semester = (int) ($this->option('semester') ?: 1);

        $currency = (string) ($this->option('currency') ?: ($countryCode === 'PH' ? 'PHP' : 'USD'));

        $payload = [
            'admin_name' => $adminName,
            'admin_email' => $adminEmail,
            'admin_password' => $adminPassword,
            'school_name' => $schoolName,
            'school_code' => $schoolCode,
            'country_code' => $countryCode,
            'school_level' => $schoolLevel,
            'school_description' => $this->option('school-description'),
            'school_email' => $this->option('school-email') ?: $adminEmail,
            'school_phone' => $this->option('school-phone'),
            'school_location' => $this->option('school-location') ?: 'Main Campus',
            'dean_name' => $this->option('dean-name') ?: $adminName,
            'dean_email' => $this->option('dean-email') ?: $adminEmail,
            'school_starting_date' => $startDate,
            'school_ending_date' => $endDate,
            'semester' => $semester,
            'curriculum_year' => $this->option('curriculum-year'),
            'curriculum_framework' => $this->option('curriculum-framework'),
            'programs' => (array) ($this->option('programs') ?: []),
            'seed_strand_subjects' => (bool) $this->option('seed-strand-subjects'),
            'site_name' => $this->option('site-name') ?: $schoolName,
            'site_description' => $this->option('site-description'),
            'theme_color' => $this->option('theme-color') ?: '#0f172a',
            'currency' => $currency,
        ];

        try {
            $result = $setupService->execute($payload);
            $this->components->info("KoAkademy successfully set up for {$result['school']->name}.");
            $this->components->twoColumnDetail('Admin Email', $result['user']->email);
            $this->components->twoColumnDetail('School Code', $result['school']->code);
            $this->components->twoColumnDetail('Country Code', $result['school']->country_code);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Setup failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
