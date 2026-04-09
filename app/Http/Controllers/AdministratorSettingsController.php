<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorSettingsController extends Controller
{
    public function index(GeneralSettingsService $settingsService): Response|RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $globalSettings = $settingsService->getGlobalSettingsModel();

        return Inertia::render('administrators/settings/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'settings' => $globalSettings,
            'flash' => session('flash'),
        ]);
    }

    public function update(Request $request, GeneralSettingsService $settingsService): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $validated = $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'theme_color' => ['nullable', 'string', 'max:50'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],

            'school_starting_date' => ['nullable', 'date'],
            'school_ending_date' => ['nullable', 'date', 'after:school_starting_date'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'curriculum_year' => ['nullable', 'string', 'max:20'],

            'school_portal_url' => ['nullable', 'url', 'max:255'],
            'school_portal_enabled' => ['boolean'],
            'school_portal_maintenance' => ['boolean'],
            'online_enrollment_enabled' => ['boolean'],

            'enable_clearance_check' => ['boolean'],
            'enable_signatures' => ['boolean'],
            'enable_qr_codes' => ['boolean'],
            'enable_public_transactions' => ['boolean'],
            'enable_support_page' => ['boolean'],

            'inventory_module_enabled' => ['boolean'],
            'library_module_enabled' => ['boolean'],

            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_keywords' => ['nullable', 'string', 'max:255'],
        ]);

        $settings = $settingsService->getGlobalSettingsModel();

        if ($settings instanceof \App\Models\GeneralSetting) {
            $settings->update($validated);
        }

        return redirect()->back()->with('flash', [
            'success' => 'General settings updated successfully.',
        ]);
    }
}
