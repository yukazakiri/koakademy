<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\SetupCatalogRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class SetupExecutionService
{
    public function __construct(
        private readonly SetupCatalogRegistry $catalogRegistry,
        private readonly LogoConversionService $logoConversionService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{user: User, school: School, general_setting: GeneralSetting}
     *
     * @throws ValidationException
     */
    public function execute(array $input, ?UploadedFile $logoFile = null): array
    {
        $schoolLevel = $input['school_level'] instanceof SchoolLevel
            ? $input['school_level']
            : SchoolLevel::from((string) $input['school_level']);

        $countryCode = mb_strtoupper(mb_trim((string) $input['country_code']));
        $availableFrameworks = $this->catalogRegistry->frameworks($countryCode, $schoolLevel);
        $provider = $this->catalogRegistry->provider($countryCode);

        $frameworkValue = $input['curriculum_framework'] ?? null;
        $framework = null;
        if ($frameworkValue instanceof CurriculumFramework) {
            $framework = $frameworkValue;
        } elseif (is_string($frameworkValue) && $frameworkValue !== '') {
            $framework = CurriculumFramework::from($frameworkValue);
        }

        if ($framework !== null && ! in_array($framework, $availableFrameworks, true)) {
            throw ValidationException::withMessages([
                'curriculum_framework' => 'The selected curriculum framework does not apply to the chosen institution level.',
            ]);
        }

        $programs = [];
        foreach ((array) ($input['programs'] ?? []) as $programCode) {
            if (is_string($programCode) && $programCode !== '') {
                $programs[] = $programCode;
            }
        }

        if ($framework !== null && $provider !== null) {
            $allowed = $provider->validProgramCodes($framework);
            $invalid = array_values(array_diff($programs, $allowed));

            if ($invalid !== []) {
                throw ValidationException::withMessages([
                    'programs' => 'One or more selected programs are not part of the chosen curriculum framework.',
                ]);
            }
        }

        if ($framework === null && $programs !== []) {
            throw ValidationException::withMessages([
                'programs' => 'Programs require a supported curriculum framework.',
            ]);
        }

        $seedStrandSubjects = (bool) ($input['seed_strand_subjects'] ?? false);
        if ($countryCode !== 'PH' && $seedStrandSubjects) {
            throw ValidationException::withMessages([
                'seed_strand_subjects' => 'Subject preloading is only available for Philippine curricula.',
            ]);
        }

        $curriculumYearValue = $input['curriculum_year'] ?? null;
        $startValue = $input['school_starting_date'] ?? null;
        $endValue = $input['school_ending_date'] ?? null;
        $curriculumYear = is_string($curriculumYearValue) && $curriculumYearValue !== ''
            ? $curriculumYearValue
            : ($framework === CurriculumFramework::DepedMatatag
                ? '2026-2027'
                : Carbon::parse(is_string($startValue) ? $startValue : 'today')->format('Y').'-'.Carbon::parse(is_string($endValue) ? $endValue : 'today')->format('Y'));

        $curriculumReferenceValue = $input['curriculum_reference'] ?? null;
        $curriculumReference = is_string($curriculumReferenceValue) && $curriculumReferenceValue !== ''
            ? $curriculumReferenceValue
            : $framework?->getReference();

        return DB::transaction(function () use ($input, $schoolLevel, $countryCode, $framework, $provider, $programs, $curriculumYear, $curriculumReference, $seedStrandSubjects, $logoFile) {
            $school = School::create([
                'name' => $input['school_name'],
                'code' => $input['school_code'],
                'country_code' => $countryCode,
                'school_level' => $schoolLevel->value,
                'curriculum_framework' => $framework?->value,
                'curriculum_reference' => $curriculumReference,
                'description' => $input['school_description'] ?? null,
                'email' => $input['school_email'] ?? null,
                'phone' => $input['school_phone'] ?? null,
                'location' => $input['school_location'] ?? null,
                'dean_name' => $input['dean_name'] ?? null,
                'dean_email' => $input['dean_email'] ?? null,
                'is_active' => true,
            ]);

            $generalSetting = GeneralSetting::first() ?? new GeneralSetting();
            $generalSetting->site_name = ! empty($input['site_name']) ? (string) $input['site_name'] : (string) $input['school_name'];
            $generalSetting->site_description = $input['site_description'] ?? null;
            $generalSetting->theme_color = $input['theme_color'] ?? '#0f172a';
            $generalSetting->currency = $input['currency'] ?? ($countryCode === 'PH' ? 'PHP' : 'USD');
            $generalSetting->support_email = $input['support_email'] ?? null;
            $generalSetting->support_phone = $input['support_phone'] ?? null;
            $generalSetting->school_starting_date = $input['school_starting_date'];
            $generalSetting->school_ending_date = $input['school_ending_date'];
            $generalSetting->semester = (int) ($input['semester'] ?? 1);
            $generalSetting->curriculum_year = $curriculumYear;
            $generalSetting->school_portal_enabled = (bool) ($input['school_portal_enabled'] ?? true);
            $generalSetting->online_enrollment_enabled = (bool) ($input['online_enrollment_enabled'] ?? true);
            $generalSetting->enable_clearance_check = (bool) ($input['enable_clearance_check'] ?? true);
            $generalSetting->enable_signatures = (bool) ($input['enable_signatures'] ?? false);
            $generalSetting->enable_qr_codes = (bool) ($input['enable_qr_codes'] ?? false);
            $generalSetting->enable_public_transactions = (bool) ($input['enable_public_transactions'] ?? false);
            $generalSetting->enable_support_page = (bool) ($input['enable_support_page'] ?? true);
            $generalSetting->inventory_module_enabled = (bool) ($input['inventory_module_enabled'] ?? false);
            $generalSetting->library_module_enabled = (bool) ($input['library_module_enabled'] ?? false);
            $generalSetting->enable_student_transfer_email_notifications = (bool) ($input['enable_student_transfer_email_notifications'] ?? true);
            $generalSetting->enable_faculty_transfer_email_notifications = (bool) ($input['enable_faculty_transfer_email_notifications'] ?? true);
            $generalSetting->is_setup = true;
            $generalSetting->save();

            if ($framework !== null && $provider !== null) {
                $provider->bootstrap(
                    school: $school,
                    framework: $framework,
                    programCodes: $programs,
                    curriculumYear: $curriculumYear,
                    seedStrandSubjects: $seedStrandSubjects,
                );
            }

            if ($logoFile !== null) {
                $paths = $this->logoConversionService->process($logoFile);

                $siteSettings = app(SiteSettings::class);
                $siteSettings->logo = $paths['logo'];
                $siteSettings->favicon = $paths['favicon'];
                $siteSettings->og_image = $paths['og_image'];
                $siteSettings->save();
            }

            $user = User::create([
                'name' => $input['admin_name'],
                'email' => $input['admin_email'],
                'password' => Hash::make((string) $input['admin_password']),
                'role' => UserRole::SuperAdmin,
                'school_id' => $school->id,
            ]);

            $roleName = config('filament-shield.super_admin.name', 'super_admin');
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);

            return [
                'user' => $user,
                'school' => $school,
                'general_setting' => $generalSetting,
            ];
        });
    }
}
