<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\GradeRelease;
use App\Models\Student;
use App\Models\User;
use App\Services\GradeReleaseNotificationService;
use App\Services\GradeReleaseService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdministratorGradeReleaseController extends Controller
{
    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('updateGrading', GeneralSetting::class), 403);
        $data = $request->validate(['require_clearance' => ['required', 'boolean']]);

        DB::transaction(function () use ($data): void {
            $settings = GeneralSetting::firstOrCreateGlobal();
            $locked = GeneralSetting::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();
            $config = is_array($locked->more_configs) ? $locked->more_configs : [];
            data_set($config, 'grade_release.require_clearance', (bool) $data['require_clearance']);
            $locked->update(['more_configs' => $config]);
        });

        return back()->with('success', 'Grade-release clearance requirement updated.');
    }

    public function clearance(Request $request, Student $student, GradeReleaseService $service): RedirectResponse
    {
        $this->authorizeStudent($request, $student, true);
        $data = $request->validate([
            'school_year' => ['required', 'string', 'regex:/^\d{4}\s*-\s*\d{4}$/'],
            'semester' => ['required', 'integer', 'between:1,3'],
            'is_cleared' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->updateClearance($student, $data['school_year'], (int) $data['semester'], (bool) $data['is_cleared'], $data['remarks'] ?? null, $request->user());

        return back()->with('success', 'Grade-release clearance updated.');
    }

    public function publish(Request $request, Student $student, GradeReleaseService $service, GradeReleaseNotificationService $notifications): RedirectResponse
    {
        $this->authorizeStudent($request, $student, false);
        $data = $request->validate([
            'school_year' => ['required', 'string', 'regex:/^\d{4}\s*-\s*\d{4}$/'],
            'semester' => ['required', 'integer', 'between:1,3'],
            'correction_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = DB::transaction(function () use ($student, $data, $service, $request, $notifications): array {
            $result = $service->publish($student, $data['school_year'], (int) $data['semester'], $request->user(), $data['correction_reason'] ?? null);
            if ($result['changed']) {
                DB::afterCommit(fn () => $notifications->send($result['release'], $student));
            }

            return $result;
        });
        if (! $result['changed']) {
            return back()->with('success', 'These grades are already published.');
        }

        return back()->with('success', 'Grades published. Notification delivery is queued after the publication is committed.');
    }

    public function retryNotifications(Request $request, Student $student, GradeReleaseNotificationService $notifications): RedirectResponse
    {
        $this->authorizeStudent($request, $student, false);
        $data = $request->validate([
            'school_year' => ['required', 'string', 'regex:/^\d{4}\s*-\s*\d{4}$/'],
            'semester' => ['required', 'integer', 'between:1,3'],
        ]);
        $release = GradeRelease::query()->where('student_id', $student->id)
            ->where('school_year', app(GradeReleaseService::class)->normalizeYear($data['school_year']))
            ->where('semester', $data['semester'])->whereNotNull('published_by')->firstOrFail();
        $status = $notifications->send($release, $student, retry: true);

        return back()->with('success', $status['warnings'] ? 'Notification retry completed with warnings.' : 'Notification retry queued.');
    }

    private function authorizeStudent(Request $request, Student $student, bool $clearance): void
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $privileged = in_array($user->role, [UserRole::Developer, UserRole::SuperAdmin, UserRole::Admin], true);
        $academicAdministrator = $user->isAdministrative() || in_array($user->role, [UserRole::Registrar, UserRole::AssistantRegistrar], true);
        abort_unless($privileged || ($clearance ? $user->can('manage_clearance') : ($academicAdministrator && $user->can('Update:Student'))), 403);
        $schoolId = $student->school_id ?: $student->institution_id;
        abort_if($schoolId && ! app(TenantContext::class)->canAccessOrganization((int) $schoolId), 404);
        abort_if($student->trashed(), 403);
    }
}
