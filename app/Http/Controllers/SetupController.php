<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
            'school_name' => ['required', 'string', 'max:255'],
            'school_code' => ['required', 'string', 'max:50', 'unique:schools,code'],
            'school_email' => ['nullable', 'string', 'email', 'max:255'],
            'school_starting_date' => ['required', 'date'],
            'school_ending_date' => ['required', 'date', 'after:school_starting_date'],
            'semester' => ['required', 'in:1,2,3'],
        ]);

        $user = DB::transaction(function () use ($request) {
            // Create the School
            $school = School::create([
                'name' => $request->input('school_name'),
                'code' => $request->input('school_code'),
                'email' => $request->input('school_email'),
                'is_active' => true,
            ]);

            // Create or update General Setting
            $generalSetting = GeneralSetting::first() ?? new GeneralSetting();
            $generalSetting->site_name = $request->input('school_name');
            $generalSetting->school_starting_date = $request->input('school_starting_date');
            $generalSetting->school_ending_date = $request->input('school_ending_date');
            $generalSetting->semester = $request->input('semester');
            $generalSetting->is_setup = true;
            $generalSetting->save();

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
