<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GeneralSettingsService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class UserSettingController extends Controller
{
    public function __construct(
        private readonly GeneralSettingsService $settingsService
    ) {}

    public function updateSemester(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester' => ['required', 'integer'],
        ]);

        // Verify if the semester is valid based on available semesters
        $availableSemesters = $this->settingsService->getAvailableSemesters();
        if (! array_key_exists($validated['semester'], $availableSemesters)) {
            return back()->withErrors(['semester' => 'Invalid semester selected.']);
        }

        $this->settingsService->updateUserSemester($validated['semester']);

        return back();
    }

    public function updateSchoolYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_year_start' => ['required', 'integer'],
        ]);

        // Verify if the school year is valid based on available school years
        // We pass the selected year as reference to ensure it's in the list if it's a bit far out
        $availableSchoolYears = $this->settingsService->getAvailableSchoolYears($validated['school_year_start']);
        if (! array_key_exists($validated['school_year_start'], $availableSchoolYears)) {
            return back()->withErrors(['school_year_start' => 'Invalid school year selected.']);
        }

        $this->settingsService->updateUserSchoolYear($validated['school_year_start']);

        return back();
    }

    public function updateActiveSchool(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        $schoolId = (int) $validated['school_id'];
        $this->settingsService->updateActiveSchoolId($schoolId);

        try {
            $tenantContext = app(\App\Services\TenantContext::class);
            $tenantContext->setCurrentSchoolId($schoolId);
        } catch (Exception) {
            // Ignore if service not available
        }

        return back()->with('success', 'Active school updated successfully.');
    }
}
