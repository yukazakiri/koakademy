<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Support\IsoAlpha2CountryCodes;
use App\Support\SetupCatalogRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

final class SetupController extends Controller
{
    /**
     * Show the setup form.
     */
    public function show(Request $request, SetupCatalogRegistry $catalogRegistry): \Illuminate\Http\Response|\Inertia\Response|RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $hasCoreData = User::query()->exists()
            || School::query()->exists();

        $setupCompleted = GeneralSetting::query()->where('is_setup', true)->exists();

        if ($setupCompleted && ! $hasCoreData) {
            GeneralSetting::query()->where('is_setup', true)->update(['is_setup' => false]);
            $setupCompleted = false;
        }

        if (! $setupCompleted && $hasCoreData) {
            $generalSetting = GeneralSetting::query()->first() ?? new GeneralSetting();
            $generalSetting->is_setup = true;
            $generalSetting->save();
            $setupCompleted = true;
        }

        if ($setupCompleted) {
            return Inertia::render('errors/forbidden', [
                'message' => 'Setup has already been completed.',
            ])->toResponse($request)->setStatusCode(403);
        }

        // If a super admin already exists, abort or redirect.
        if (! $setupCompleted && User::where('role', UserRole::SuperAdmin)->exists()) {
            return redirect()->route('login');
        }

        $countryCode = $request->query('country_code', 'PH');
        $level = $request->query('school_level');

        return Inertia::render('setup/index', [
            'catalog' => array_merge($catalogRegistry->catalog(
                is_string($countryCode) ? mb_strtoupper(mb_trim($countryCode)) : '',
                is_string($level) ? SchoolLevel::tryFrom($level) : null,
            ), [
                'countries' => $catalogRegistry->countries(),
                'by_country' => $catalogRegistry->catalogsByCountry(),
            ]),
        ]);
    }

    /**
     * Process the setup submission.
     */
    public function store(Request $request, SetupCatalogRegistry $catalogRegistry, \App\Services\SetupExecutionService $setupService): RedirectResponse
    {
        $currentUser = Auth::user();
        $isSuperAdmin = $currentUser?->role === UserRole::SuperAdmin;

        if (GeneralSetting::query()->where('is_setup', true)->exists() && ! $isSuperAdmin) {
            abort(403, 'Setup has already been completed.');
        }

        // Check again to prevent race conditions.
        if (GeneralSetting::query()->where('is_setup', true)->exists() || User::where('role', UserRole::SuperAdmin)->exists()) {
            abort(403, 'Setup has already been completed.');
        }

        if ($request->has('country_code')) {
            $request->merge(['country_code' => mb_strtoupper(mb_trim((string) $request->input('country_code')))]);
        }

        $request->validate([
            // Step 1: Administrator (required)
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            // Step 2: Institution (required name & code)
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50', 'unique:schools,code'],
            'country_code' => ['required', 'string', 'regex:/\A[A-Z]{2}\z/', Rule::in(IsoAlpha2CountryCodes::codes())],
            'school_level' => ['required', Rule::enum(SchoolLevel::class)],
            'school_description' => ['nullable', 'string', 'max:1000'],
            'school_email' => ['nullable', 'string', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'school_location' => ['nullable', 'string', 'max:500'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'string', 'email', 'max:255'],
            // Step 3: Academic Period (required)
            'school_starting_date' => ['required', 'date'],
            'school_ending_date' => ['required', 'date', 'after:school_starting_date'],
            'semester' => ['required', 'in:1,2,3'],
            'curriculum_year' => ['nullable', 'string', 'max:20'],
            // Step 3b: Curriculum & Programs (recommended)
            'curriculum_framework' => ['nullable', Rule::enum(CurriculumFramework::class)],
            'curriculum_reference' => ['nullable', 'string', 'max:255'],
            'programs' => ['nullable', 'array', 'max:100'],
            'programs.*' => ['string', 'max:50'],
            'seed_strand_subjects' => ['nullable', 'boolean'],
            // Step 4: Brand & Appearance (optional)
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'theme_color' => ['nullable', 'string', 'max:20'],
            'currency' => ['required_unless:country_code,PH', 'nullable', 'string', 'regex:/\A[A-Z]{3}\z/'],
            'support_email' => ['nullable', 'string', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'max:5120'],
            // Step 5: Feature Toggles (optional)
            'school_portal_enabled' => ['nullable', 'boolean'],
            'online_enrollment_enabled' => ['nullable', 'boolean'],
            'enable_clearance_check' => ['nullable', 'boolean'],
            'enable_signatures' => ['nullable', 'boolean'],
            'enable_qr_codes' => ['nullable', 'boolean'],
            'enable_public_transactions' => ['nullable', 'boolean'],
            'enable_support_page' => ['nullable', 'boolean'],
            'inventory_module_enabled' => ['nullable', 'boolean'],
            'library_module_enabled' => ['nullable', 'boolean'],
            'enable_student_transfer_email_notifications' => ['nullable', 'boolean'],
            'enable_faculty_transfer_email_notifications' => ['nullable', 'boolean'],
        ]);

        $result = $setupService->execute(
            $request->all(),
            $request->file('logo'),
        );

        // Log in the new user
        Auth::login($result['user']);

        return redirect('/');
    }
}
