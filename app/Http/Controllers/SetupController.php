<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Services\LogoConversionService;
use App\Settings\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

final class SetupController extends Controller
{
    /**
     * Show the setup form.
     */
    public function show(Request $request): \Illuminate\Http\Response|\Inertia\Response|RedirectResponse|\Symfony\Component\HttpFoundation\Response
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

        return Inertia::render('setup/index');
    }

    /**
     * Process the setup submission.
     */
    public function store(Request $request): RedirectResponse
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

        $request->validate([
            // Step 1: Administrator (required)
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            // Step 2: Institution (required name & code)
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50', 'unique:schools,code'],
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
            // Step 4: Brand & Appearance (optional)
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'theme_color' => ['nullable', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'max:10'],
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

        $user = DB::transaction(function () use ($request) {
            // Create the School
            $school = School::create([
                'name' => $request->input('school_name'),
                'code' => $request->input('school_code'),
                'description' => $request->input('school_description'),
                'email' => $request->input('school_email'),
                'phone' => $request->input('school_phone'),
                'location' => $request->input('school_location'),
                'dean_name' => $request->input('dean_name'),
                'dean_email' => $request->input('dean_email'),
                'is_active' => true,
            ]);

            // Create or update General Setting
            $generalSetting = GeneralSetting::first() ?? new GeneralSetting();
            $generalSetting->site_name = $request->filled('site_name') ? $request->input('site_name') : $request->input('school_name');
            $generalSetting->site_description = $request->input('site_description');
            $generalSetting->theme_color = $request->input('theme_color') ?? '#0f172a';
            $generalSetting->currency = $request->input('currency') ?? 'PHP';
            $generalSetting->support_email = $request->input('support_email');
            $generalSetting->support_phone = $request->input('support_phone');
            $generalSetting->school_starting_date = $request->input('school_starting_date');
            $generalSetting->school_ending_date = $request->input('school_ending_date');
            $generalSetting->semester = (int) $request->input('semester');
            $generalSetting->curriculum_year = $request->filled('curriculum_year')
                ? $request->input('curriculum_year')
                : Carbon::parse($request->input('school_starting_date'))->format('Y').'-'.Carbon::parse($request->input('school_ending_date'))->format('Y');
            $generalSetting->school_portal_enabled = $request->boolean('school_portal_enabled', true);
            $generalSetting->online_enrollment_enabled = $request->boolean('online_enrollment_enabled', true);
            $generalSetting->enable_clearance_check = $request->boolean('enable_clearance_check', true);
            $generalSetting->enable_signatures = $request->boolean('enable_signatures');
            $generalSetting->enable_qr_codes = $request->boolean('enable_qr_codes');
            $generalSetting->enable_public_transactions = $request->boolean('enable_public_transactions');
            $generalSetting->enable_support_page = $request->boolean('enable_support_page', true);
            $generalSetting->inventory_module_enabled = $request->boolean('inventory_module_enabled');
            $generalSetting->library_module_enabled = $request->boolean('library_module_enabled');
            $generalSetting->enable_student_transfer_email_notifications = $request->boolean('enable_student_transfer_email_notifications', true);
            $generalSetting->enable_faculty_transfer_email_notifications = $request->boolean('enable_faculty_transfer_email_notifications', true);
            $generalSetting->is_setup = true;
            $generalSetting->save();

            // Process logo upload — generates favicon, PWA icons, OG image
            if ($request->hasFile('logo')) {
                $paths = app(LogoConversionService::class)->process($request->file('logo'));

                $siteSettings = app(SiteSettings::class);
                $siteSettings->logo = $paths['logo'];
                $siteSettings->favicon = $paths['favicon'];
                $siteSettings->og_image = $paths['og_image'];
                $siteSettings->save();
            }

            // Create the User
            $user = User::create([
                'name' => $request->input('admin_name'),
                'email' => $request->input('admin_email'),
                'password' => Hash::make($request->input('admin_password')),
                'role' => UserRole::SuperAdmin,
                'school_id' => $school->id,
            ]);

            // Assign Spatie Role
            $roleName = config('filament-shield.super_admin.name', 'super_admin');
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $user->assignRole($role);

            return $user;
        });

        // Log in the new user
        Auth::login($user);

        return redirect('/');
    }
}
