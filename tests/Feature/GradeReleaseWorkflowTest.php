<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\GeneralSetting;
use App\Models\GradeRelease;
use App\Models\GradeReleaseClearance;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Notifications\StudentGradesPublishedNotification;
use App\Services\GradeReleaseService;
use App\Services\StudentDataTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    $this->withoutVite();
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'more_configs' => ['grade_release' => ['require_clearance' => true], 'notification_channels' => ['enabled_channels' => ['database', 'mail']]],
    ]);
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->studentUser = User::factory()->create(['role' => UserRole::Student]);
    $this->student = Student::factory()->create(['user_id' => $this->studentUser->id, 'email' => $this->studentUser->email]);
    $this->studentUser->update(['school_id' => $this->student->school_id]);
    $this->subject = Subject::factory()->create(['course_id' => $this->student->course_id, 'school_id' => $this->student->school_id]);
    $this->enrollment = StudentEnrollment::factory()->create(['student_id' => $this->student->id, 'course_id' => $this->student->course_id, 'school_id' => $this->student->school_id, 'school_year' => '2025 - 2026', 'semester' => 2]);
    $this->subjectEnrollment = SubjectEnrollment::factory()->create([
        'student_id' => $this->student->id, 'subject_id' => $this->subject->id, 'enrollment_id' => $this->enrollment->id,
        'school_id' => $this->student->school_id, 'school_year' => '2025-2026', 'semester' => 2, 'grade' => 87, 'grade_symbol' => null, 'grade_outcome' => 'pass', 'remarks' => null,
    ]);
    Notification::fake();
});

it('requires the separate current-term clearance before publication and sends only one notice per revision', function (): void {
    $url = portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases");
    $payload = ['school_year' => '2025 - 2026', 'semester' => 2];
    $this->actingAs($this->admin)->from('/')->post($url, $payload)->assertSessionHasErrors('period');
    expect(GradeRelease::query()->count())->toBe(0);

    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-release-clearance"), [
        ...$payload, 'is_cleared' => true, 'remarks' => 'Library complete',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasNoErrors();

    expect(GradeRelease::query()->first()->revision)->toBe(1)
        ->and(GradeReleaseClearance::query()->first()->is_cleared)->toBeTrue();
    Notification::assertSentToTimes($this->studentUser, StudentGradesPublishedNotification::class, 1);
    Notification::assertSentOnDemandTimes(StudentGradesPublishedNotification::class, 1);
});

it('retries only channels that were not already queued', function (): void {
    $this->student->update(['user_id' => null]);
    $service = app(GradeReleaseService::class);
    $service->updateClearance($this->student, '2025 - 2026', 2, true, null, $this->admin);
    $payload = ['school_year' => '2025 - 2026', 'semester' => 2];
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), $payload)->assertSessionHasNoErrors();
    expect(GradeRelease::query()->first()->notification_status['database'])->toBe('unavailable');
    $this->student->update(['user_id' => $this->studentUser->id]);
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases/retry-notifications"), $payload)->assertSessionHasNoErrors();
    Notification::assertSentToTimes($this->studentUser, StudentGradesPublishedNotification::class, 1);
    Notification::assertSentOnDemandTimes(StudentGradesPublishedNotification::class, 1);
});

it('blocks missing grades even if clearance is optional and accepts recorded symbolic outcomes', function (): void {
    $this->subjectEnrollment->update(['grade' => null, 'grade_symbol' => null]);
    $settings = GeneralSetting::query()->first();
    $settings->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $url = portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases");
    $payload = ['school_year' => '2025-2026', 'semester' => 2];
    $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasErrors('period');
    $this->subjectEnrollment->update(['grade_symbol' => 'INC', 'grade_outcome' => 'incomplete']);
    $this->actingAs($this->admin)->post($url, $payload)->assertSessionHasNoErrors();
    expect(GradeRelease::query()->count())->toBe(1);
});

it('publishes a historical semester without current-term clearance', function (): void {
    $this->subjectEnrollment->update(['school_year' => '2024 - 2025', 'semester' => 1]);
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2024-2025', 'semester' => 1])->assertSessionHasNoErrors();
    expect(GradeRelease::query()->where('school_year', '2024 - 2025')->exists())->toBeTrue();
});

it('requires clearance for future semesters when the rule is enabled', function (): void {
    $this->subjectEnrollment->update(['school_year' => '2026 - 2027', 'semester' => 1]);
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2026 - 2027', 'semester' => 1])->assertSessionHasErrors('period');
});

it('allows authorized grading administrators to configure the requirement', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $this->actingAs($user)->put(portalUrlForAdministrators('/administrators/system-management/grade-release'), ['require_clearance' => false])->assertSessionHasNoErrors();
    expect(app(GradeReleaseService::class)->requiresClearance())->toBeFalse();
});

it('manages historical clearance and clears stale approval details when revoked', function (): void {
    $url = portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-release-clearance");
    $data = ['school_year' => '2024-2025', 'semester' => 1, 'is_cleared' => true];
    $this->actingAs($this->admin)->post($url, $data)->assertSessionHasNoErrors();
    expect(GradeReleaseClearance::query()->first()->cleared_by)->toBe($this->admin->id);
    $this->actingAs($this->admin)->post($url, [...$data, 'is_cleared' => false])->assertSessionHasNoErrors();
    $clearance = GradeReleaseClearance::query()->first();
    expect($clearance->school_year)->toBe('2024 - 2025')->and($clearance->is_cleared)->toBeFalse()->and($clearance->cleared_by)->toBeNull()->and($clearance->cleared_at)->toBeNull();
});

it('shows clearance history for recorded terms including terms with no clearance', function (): void {
    app(GradeReleaseService::class)->updateClearance($this->student, '2024-2025', 1, true, 'Completed', $this->admin);
    $periods = collect(app(GradeReleaseService::class)->periods($this->student))->keyBy('school_year');
    expect($periods['2024 - 2025']['clearance']['is_cleared'])->toBeTrue()
        ->and($periods['2024 - 2025']['clearance']['cleared_by'])->toBe($this->admin->name)
        ->and($periods['2025 - 2026']['clearance'])->toBeNull();
});

it('returns the grade-release history on the administrator student page', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    app(GradeReleaseService::class)->updateClearance($this->student, '2024 - 2025', 1, true, 'Complete', $this->admin);
    $this->actingAs($this->admin)->get(portalUrlForAdministrators("/administrators/students/{$this->student->id}"))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('administrators/students/show', false)
        ->where('student.can_view_grade_release', true)
        ->where('student.grade_release_periods', fn ($periods): bool => collect($periods)->contains(fn ($period): bool => $period['school_year'] === '2024 - 2025' && $period['clearance']['is_cleared'])));
});

it('keeps baseline grades visible but permits explicit publication without changes', function (): void {
    $service = app(GradeReleaseService::class);
    GradeRelease::query()->create([
        'student_id' => $this->student->id, 'school_year' => '2025 - 2026', 'semester' => 2,
        'snapshot' => $service->snapshot($this->student, '2025 - 2026', 2), 'revision' => 1, 'published_at' => now(), 'published_by' => null,
    ]);
    expect($service->publishedSubject($this->subjectEnrollment, $service->releases($this->student))['grade'])->toBe('87.00');
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertSessionHasErrors('period');
    $service->updateClearance($this->student, '2025 - 2026', 2, true, null, $this->admin);
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertSessionHasNoErrors();
    expect(GradeRelease::query()->first()->published_by)->toBe($this->admin->id);
});

it('migrates previously visible grades into a baseline without notifications', function (): void {
    $class = Classes::factory()->create(['school_id' => $this->student->school_id, 'school_year' => '2025-2026', 'semester' => 2]);
    $enrollment = ClassEnrollment::factory()->create(['school_id' => $this->student->school_id, 'student_id' => $this->student->id, 'class_id' => $class->id, 'finals_grade' => 93, 'total_average' => 90]);
    $migration = require database_path('migrations/2026_11_14_145253_create_grade_release_workflow.php');
    $migration->down();
    $migration->up();
    $release = GradeRelease::query()->where('student_id', $this->student->id)->firstOrFail();
    expect($release->published_by)->toBeNull()
        ->and((float) $release->snapshot['subjects'][$this->subjectEnrollment->id]['grade'])->toBe(87.0)
        ->and((float) $release->snapshot['classes'][$enrollment->id]['total_average'])->toBe(90.0)
        ->and(DB::table('grade_release_events')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('blocks student data transfers that would strand grade-release records', function (): void {
    app(GradeReleaseService::class)->updateClearance($this->student, '2025 - 2026', 2, true, null, $this->admin);
    $target = Student::factory()->create(['school_id' => $this->student->school_id, 'institution_id' => $this->student->institution_id]);
    $service = app(StudentDataTransferService::class);
    expect($service->preview($this->student, $target)['safe_to_proceed'])->toBeFalse();
    expect(fn () => $service->transfer($this->student, $target, $this->admin))->toThrow(InvalidArgumentException::class, 'grade-release');
});

it('uses a class result for its linked subject without overwriting the administrative grade record', function (): void {
    $class = Classes::factory()->create(['school_id' => $this->student->school_id, 'school_year' => '2025 - 2026', 'semester' => 2]);
    $this->subjectEnrollment->update(['class_id' => $class->id, 'grade' => null]);
    ClassEnrollment::factory()->create(['school_id' => $this->student->school_id, 'student_id' => $this->student->id, 'class_id' => $class->id, 'total_average' => 89, 'grade_symbol' => null]);
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $service = app(GradeReleaseService::class);
    $release = $service->publish($this->student, '2025 - 2026', 2, $this->admin)['release'];
    expect((float) $release->snapshot['subjects'][$this->subjectEnrollment->id]['grade'])->toBe(89.0)
        ->and($this->subjectEnrollment->fresh()->grade)->toBeNull();
});

it('enforces the publication and clearance permissions for administrative staff', function (): void {
    $user = User::factory()->create(['role' => UserRole::Cashier, 'school_id' => $this->student->school_id]);
    $this->actingAs($user)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertForbidden();
    $this->actingAs($user)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-release-clearance"), ['school_year' => '2025 - 2026', 'semester' => 2, 'is_cleared' => true])->assertForbidden();
});

it('does not allow a registrar to change another school student', function (): void {
    Permission::findOrCreate('Update:Student', 'web');
    $otherSchool = School::factory()->create();
    $registrar = User::factory()->create(['role' => UserRole::Registrar, 'school_id' => $otherSchool->id]);
    $registrar->givePermissionTo('Update:Student');
    $this->actingAs($registrar)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertNotFound();
    expect(GradeRelease::query()->count())->toBe(0);
});

it('keeps edits private until a new snapshot is published', function (): void {
    $settings = GeneralSetting::query()->first();
    $settings->update(['more_configs' => ['grade_release' => ['require_clearance' => false], 'notification_channels' => ['enabled_channels' => []]]]);
    $service = app(GradeReleaseService::class);
    $service->publish($this->student, '2025 - 2026', 2, $this->admin);
    $this->subjectEnrollment->update(['grade' => 91]);
    expect((float) $service->publishedSubject($this->subjectEnrollment->fresh(), $service->releases($this->student))['grade'])->toBe(87.0);
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertSessionHasErrors('correction_reason');
    $service->publish($this->student, '2025 - 2026', 2, $this->admin, 'Registrar corrected the submitted grade.');
    expect((float) $service->publishedSubject($this->subjectEnrollment->fresh(), $service->releases($this->student))['grade'])->toBe(91.0)
        ->and(GradeRelease::query()->first()->revision)->toBe(2);
});

it('does not republish an unchanged numeric class snapshot', function (): void {
    $class = Classes::factory()->create(['school_id' => $this->student->school_id, 'school_year' => '2025 - 2026', 'semester' => 2]);
    ClassEnrollment::factory()->create(['school_id' => $this->student->school_id, 'student_id' => $this->student->id, 'class_id' => $class->id, 'finals_grade' => 90, 'total_average' => 85, 'grade_symbol' => null]);
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $service = app(GradeReleaseService::class);
    expect($service->publish($this->student, '2025 - 2026', 2, $this->admin)['changed'])->toBeTrue()
        ->and($service->publish($this->student, '2025 - 2026', 2, $this->admin)['changed'])->toBeFalse();
});

it('masks finals in the student API until published and never exposes later drafts', function (): void {
    $class = Classes::factory()->create(['school_id' => $this->student->school_id, 'school_year' => '2025 - 2026', 'semester' => 2]);
    $classEnrollment = ClassEnrollment::factory()->create(['school_id' => $this->student->school_id, 'student_id' => $this->student->id, 'class_id' => $class->id, 'prelim_grade' => 80, 'finals_grade' => 90, 'total_average' => 85, 'grade_symbol' => null]);
    $url = "/api/class-enrollments/{$classEnrollment->id}";
    $this->actingAs($this->studentUser, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.finals_grade', null)->assertJsonPath('data.total_average', null);
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    app(GradeReleaseService::class)->publish($this->student, '2025 - 2026', 2, $this->admin);
    $classEnrollment->update(['finals_grade' => 95, 'total_average' => 92]);
    $this->actingAs($this->studentUser, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('data.finals_grade', '90.00')->assertJsonPath('data.total_average', '85.00');
    $this->actingAs($this->studentUser, 'sanctum')->getJson('/api/class-enrollments?has_grades=1')->assertForbidden();
});

it('does not include draft results or draft completion in the student checklist', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->withoutMiddleware();
    $this->actingAs($this->studentUser)->get('/student/classes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('student/classes/index')
        ->where('curriculum.'.$this->subject->academic_year.'.'.$this->subject->semester.'.0.grade', null)
        ->where('progress.earned', 0));
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    app(GradeReleaseService::class)->publish($this->student, '2025 - 2026', 2, $this->admin);
    $this->subjectEnrollment->update(['grade' => 92]);
    $this->actingAs($this->studentUser)->get('/student/classes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('curriculum.'.$this->subject->academic_year.'.'.$this->subject->semester.'.0.grade', '87.00'));
});

it('masks finals on the student dashboard and class page', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $class = Classes::factory()->create(['school_id' => $this->student->school_id, 'school_year' => '2025 - 2026', 'semester' => 2]);
    ClassEnrollment::factory()->create(['school_id' => $this->student->school_id, 'student_id' => $this->student->id, 'class_id' => $class->id, 'prelim_grade' => 80, 'finals_grade' => 90, 'total_average' => 85]);
    $this->withoutMiddleware([App\Http\Middleware\EnsureStudentAccess::class, App\Http\Middleware\EnsureFeatureEnabled::class]);
    $this->actingAs($this->studentUser)->get('/student/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('student_data.enrolled_classes.0.grades.finals', null)->where('student_data.enrolled_classes.0.grades.average', null));
    $this->actingAs($this->studentUser)->get(route('student.classes.show', $class))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('my_grades.final', null)->where('my_grades.average', null)->where('my_grades.final_status', 'clearance_required'));
});

it('preserves legacy records without letting them block enrollment or clear final grades', function (): void {
    StudentClearance::query()->create(['student_id' => $this->student->id, 'academic_year' => '2025 - 2026', 'semester' => 1, 'is_cleared' => false]);
    expect($this->student->validateEnrollmentClearance('2025 - 2026', 2)['allowed'])->toBeTrue();
    $this->actingAs($this->admin)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertSessionHasErrors('period');
    expect(StudentClearance::query()->count())->toBe(1);
});

it('does not let students publish their own grades', function (): void {
    $this->actingAs($this->studentUser)->post(portalUrlForAdministrators("/administrators/students/{$this->student->id}/grade-releases"), ['school_year' => '2025 - 2026', 'semester' => 2])->assertForbidden();
});

it('treats snapshots with reordered JSON keys as unchanged', function (): void {
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $service = app(GradeReleaseService::class);
    $release = $service->publish($this->student, '2025 - 2026', 2, $this->admin)['release'];

    $reordered = [
        'classes' => $release->snapshot['classes'],
        'subjects' => collect($release->snapshot['subjects'])->map(fn (array $row): array => array_reverse($row, true))->all(),
    ];
    $release->update(['snapshot' => $reordered]);

    expect($service->publish($this->student, '2025 - 2026', 2, $this->admin)['changed'])->toBeFalse();
    $period = collect($service->periods($this->student))->firstWhere('school_year', '2025 - 2026');
    expect($period['has_changes'])->toBeFalse();
});

it('resolves symbolic grades using stored outcome in student checklist', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->withoutMiddleware();
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $this->subjectEnrollment->update(['grade' => null, 'grade_symbol' => 'A', 'grade_outcome' => 'pass']);
    app(GradeReleaseService::class)->publish($this->student, '2025 - 2026', 2, $this->admin);

    $this->actingAs($this->studentUser)->get('/student/classes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('curriculum.'.$this->subject->academic_year.'.'.$this->subject->semester.'.0.grade', 'A')
        ->where('curriculum.'.$this->subject->academic_year.'.'.$this->subject->semester.'.0.status', 'completed'));

    $this->subjectEnrollment->update(['grade' => null, 'grade_symbol' => 'F', 'grade_outcome' => 'fail']);
    app(GradeReleaseService::class)->publish($this->student, '2025 - 2026', 2, $this->admin, 'Updated to fail');

    $this->actingAs($this->studentUser)->get('/student/classes')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('curriculum.'.$this->subject->academic_year.'.'.$this->subject->semester.'.0.status', 'failed'));
});

it('scopes grade-release clearance counts in QueryCampusAnalyticsTool to the active school', function (): void {
    $otherSchool = School::factory()->create();
    $otherStudent = Student::factory()->create(['school_id' => $otherSchool->id, 'institution_id' => $otherSchool->id]);
    app(GradeReleaseService::class)->updateClearance($otherStudent, '2025 - 2026', 2, false, 'Other school hold', $this->admin);

    app(App\Services\TenantContext::class)->setCurrentSchoolId((int) $this->student->school_id);
    $this->actingAs($this->admin);

    $overview = json_decode((string) (new App\Ai\Tools\QueryCampusAnalyticsTool)->handle(new Laravel\Ai\Tools\Request(['category' => 'overview'])), true);
    $clearance = json_decode((string) (new App\Ai\Tools\QueryCampusAnalyticsTool)->handle(new Laravel\Ai\Tools\Request(['category' => 'clearance'])), true);

    expect($overview['headline_metrics']['pending_clearances'])->toBe(0)
        ->and($clearance['clearances_recorded_this_term'])->toBe(0);
});

it('marks terminal queue failures on the matching release revision so retry can resend', function (): void {
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false], 'notification_channels' => ['enabled_channels' => ['mail', 'database']]]]);
    $service = app(GradeReleaseService::class);
    $release = $service->publish($this->student, '2025 - 2026', 2, $this->admin)['release'];
    app(App\Services\GradeReleaseNotificationService::class)->send($release, $this->student);

    $notification = new StudentGradesPublishedNotification('2025 - 2026', 2, 'mail', $release->id, $release->revision);
    $notification->failed(new RuntimeException('SMTP connection refused'));

    expect($release->fresh()->notification_status['mail'])->toBe('failed');
});

it('matches asymmetrically spaced school years accepted by the baseline migration', function (): void {
    $this->subjectEnrollment->update(['school_year' => '2025- 2026']);
    GeneralSetting::query()->first()->update(['more_configs' => ['grade_release' => ['require_clearance' => false]]]);
    $release = app(GradeReleaseService::class)->publish($this->student, '2025 - 2026', 2, $this->admin)['release'];
    expect($release->snapshot['subjects'])->toHaveKey((string) $this->subjectEnrollment->id);
});
