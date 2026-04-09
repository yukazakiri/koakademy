<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\UserSetting;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Notifications\Notification; // Import Carbon for date manipulation
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
// Cache facade might not be directly used here anymore, service handles its caching
use Illuminate\Support\Facades\Auth; // Import Filament Notification
use Illuminate\Support\Facades\Log;
use Livewire\Component;

// Required for user context

final class SemesterSchoolYearSelectorComponent extends Component
{
    public int $currentSemester;

    public int $currentSchoolYearStart;

    public array $availableSemesters = [];

    public array $availableSchoolYears = []; // Key: int (start year), Value: string (display)

    // Track if the current settings are from user preferences or system defaults
    public bool $isCustomSemester = false;

    public bool $isCustomSchoolYear = false;

    // Store system defaults for reference
    public int $systemDefaultSemester;

    public int $systemDefaultSchoolYearStart;

    private GeneralSettingsService $settingsService;

    public function boot(GeneralSettingsService $generalSettingsService): void
    {
        $this->settingsService = $generalSettingsService;
    }

    public function mount(): void
    {
        if (! Auth::check()) {
            // Handle guest user scenario if necessary, perhaps redirect or show error
            // For now, we assume an authenticated user. If not, service might throw error or return defaults.
            // This component is likely used within authenticated areas.
            Log::warning('SemesterSchoolYearSelectorComponent mounted without authenticated user.');
            // Provide some safe defaults to avoid errors in view
            $this->currentSemester = 1;
            $this->currentSchoolYearStart = (int) date('Y');
            $this->availableSemesters = [1 => '1st Semester', 2 => '2nd Semester'];
            $this->availableSchoolYears = [$this->currentSchoolYearStart => $this->currentSchoolYearStart.' - '.($this->currentSchoolYearStart + 1)];

            $this->systemDefaultSemester = $this->currentSemester;
            $this->systemDefaultSchoolYearStart = $this->currentSchoolYearStart;

            return;
        }

        // Get system default settings
        $settings = $this->settingsService->getGlobalSettingsModel();
        $this->systemDefaultSemester = $settings?->semester ?? 1;
        $this->systemDefaultSchoolYearStart = (int) ($settings?->getSchoolYearStarting() ?? date('Y'));

        // Get effective settings (user preferences or system defaults)
        $this->currentSemester = $this->settingsService->getCurrentSemester();
        $this->currentSchoolYearStart = $this->settingsService->getCurrentSchoolYearStart();

        // Check if user has custom settings
        $userSettings = $this->settingsService->getUserSettingsModel();
        $this->isCustomSemester = $userSettings && ! is_null($userSettings->semester);
        $this->isCustomSchoolYear = $userSettings && ! is_null($userSettings->school_year_start);

        $this->availableSemesters = $this->settingsService->getAvailableSemesters();
        $this->availableSchoolYears = $this->settingsService->getAvailableSchoolYears($this->currentSchoolYearStart);

        // Ensure current semester from service is valid against available semesters from service
        if (! array_key_exists($this->currentSemester, $this->availableSemesters)) {
            Log::warning(sprintf("User's current semester '%d' is not in available semesters. Defaulting based on service logic or to 1.", $this->currentSemester));
            // The service's getCurrentSemester already handles defaults, so this might be redundant
            // or indicate a mismatch between getCurrentSemester logic and getAvailableSemesters.
            // For safety, we can re-fetch or default.
            $this->currentSemester = $this->settingsService->getCurrentSemester(); // Re-affirm from service
            if (! array_key_exists($this->currentSemester, $this->availableSemesters)) {
                $this->currentSemester = array_key_first($this->availableSemesters) ?? 1; // Fallback
            }
        }
    }

    public function updateSemester(int $semester)
    {
        if (! Auth::check()) {
            Notification::make()->title('Authentication Required')->danger()->send();

            return null;
        }

        if (array_key_exists($semester, $this->availableSemesters)) {
            try {
                $this->settingsService->updateUserSemester($semester);
                $this->currentSemester = $semester;
                $this->isCustomSemester = true;

                // Use Filament Notification for success
                Notification::make()
                    ->title('Semester updated successfully')
                    ->success()
                    ->send();
                $this->dispatch('semesterUpdated'); // Dispatch event if needed elsewhere

                return $this->redirect(request()->header('Referer')); // Reinstate full page redirect
            } catch (Exception $e) {
                Log::error('Error updating semester in database: '.$e->getMessage());
                // Use Filament Notification for error
                Notification::make()
                    ->title('Failed to update semester')
                    ->body('Please check logs for details.')
                    ->danger()
                    ->send();
            }
        } else {
            Log::warning('Attempted to update semester with invalid value: '.$semester);
            // Use Filament Notification for error
            Notification::make()
                ->title('Invalid semester selected')
                ->danger()
                ->send();
        }

        return null;
    }

    public function updateSchoolYear(int $startYear)
    {
        if (! Auth::check()) {
            Notification::make()->title('Authentication Required')->danger()->send();

            return null;
        }

        if (array_key_exists($startYear, $this->availableSchoolYears)) {
            try {
                // Update user's preferred school year start via the service
                $this->settingsService->updateUserSchoolYear($startYear);

                $this->currentSchoolYearStart = $startYear;
                $this->isCustomSchoolYear = true;

                // Use Filament Notification for success
                Notification::make()
                    ->title('School year updated successfully')
                    ->success()
                    ->send();
                $this->dispatch('schoolYearUpdated'); // Dispatch event if needed elsewhere

                return $this->redirect(request()->header('Referer')); // Reinstate full page redirect
            } catch (Exception $e) {
                Log::error('Error updating school year dates in database: '.$e->getMessage());
                // Use Filament Notification for error
                Notification::make()
                    ->title('Failed to update school year')
                    ->body('Please check logs for details.')
                    ->danger()
                    ->send();
            }
        } else {
            Log::warning('Attempted to update school year with invalid start year: '.$startYear);
            // Use Filament Notification for error
            Notification::make()
                ->title('Invalid school year selected')
                ->danger()
                ->send();
        }

        return null;
    }

    /**
     * Reset the user's semester preference to use the system default
     */
    public function resetSemesterToDefault()
    {
        if (! Auth::check()) {
            Notification::make()->title('Authentication Required')->danger()->send();

            return null;
        }

        try {
            // Get user settings and set semester to null to use system default
            $userSettings = $this->settingsService->getUserSettingsModel();
            if ($userSettings instanceof UserSetting) {
                $userSettings->semester = null;
                $userSettings->save();

                // Update the component state
                $this->isCustomSemester = false;
                $this->currentSemester = $this->systemDefaultSemester;

                Notification::make()
                    ->title('Semester reset to system default')
                    ->success()
                    ->send();

                $this->dispatch('semesterUpdated');

                return $this->redirect(request()->header('Referer'));
            }
        } catch (Exception $exception) {
            Log::error('Error resetting semester to default: '.$exception->getMessage());
            Notification::make()
                ->title('Failed to reset semester')
                ->danger()
                ->send();
        }

        return null;
    }

    /**
     * Reset the user's school year preference to use the system default
     */
    public function resetSchoolYearToDefault()
    {
        if (! Auth::check()) {
            Notification::make()->title('Authentication Required')->danger()->send();

            return null;
        }

        try {
            // Get user settings and set school_year_start to null to use system default
            $userSettings = $this->settingsService->getUserSettingsModel();
            if ($userSettings instanceof UserSetting) {
                $userSettings->school_year_start = null;
                $userSettings->save();

                // Update the component state
                $this->isCustomSchoolYear = false;
                $this->currentSchoolYearStart = $this->systemDefaultSchoolYearStart;

                Notification::make()
                    ->title('School year reset to system default')
                    ->success()
                    ->send();

                $this->dispatch('schoolYearUpdated');

                return $this->redirect(request()->header('Referer'));
            }
        } catch (Exception $exception) {
            Log::error('Error resetting school year to default: '.$exception->getMessage());
            Notification::make()
                ->title('Failed to reset school year')
                ->danger()
                ->send();
        }

        return null;
    }

    // No longer needed: protected function updateConfigFile(...)

    public function render(): View|Factory
    {
        // Point to the new view file that will contain the dropdowns
        return view('livewire.semester-school-year-selector-content');
    }
}
