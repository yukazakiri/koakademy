<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\FacultyDeadline;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Notifications\AdminFacultyNoticeNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
    ]);
});

function facultyAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

it('renders faculty operations index with filters sorting and capped pagination props', function (): void {
    $admin = facultyAdmin();

    Faculty::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'department' => 'CCS',
        'status' => 'active',
    ]);
    Faculty::factory()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'department' => 'CBA',
        'status' => 'on_leave',
    ]);

    $this->actingAs($admin)
        ->get(route('administrators.faculties.index', [
            'search' => 'Ada',
            'department' => 'CCS',
            'status' => 'active',
            'sort' => 'department',
            'direction' => 'desc',
            'per_page' => 500,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/faculties/index', false)
            ->where('filters.search', 'Ada')
            ->where('filters.department', 'CCS')
            ->where('filters.sort', 'department')
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 100)
            ->has('stats.needs_classes')
            ->has('segments')
            ->has('faculties.data.0.portal_account')
            ->has('faculties.data.0.profile_completion')
            ->has('faculties.data.0.workload_summary'));
});

it('bulk updates faculty statuses', function (): void {
    $admin = facultyAdmin();
    $faculties = Faculty::factory()->count(2)->create(['status' => 'active']);

    $this->actingAs($admin)
        ->patch(route('administrators.faculties.bulk.status'), [
            'faculty_ids' => $faculties->pluck('id')->all(),
            'status' => 'on_leave',
        ])
        ->assertRedirect();

    expect(Faculty::query()->whereIn('id', $faculties->pluck('id'))->pluck('status')->unique()->all())
        ->toBe(['on_leave']);
});

it('creates and repairs a linked faculty portal account', function (): void {
    $admin = facultyAdmin();
    $faculty = Faculty::factory()->create([
        'email' => 'faculty@example.test',
        'faculty_id_number' => 'FAC-100',
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.portal-account', $faculty), [
            'mode' => 'create',
            'role' => UserRole::Instructor->value,
            'send_reset_link' => false,
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'faculty@example.test')->firstOrFail();

    expect($user->faculty_id_number)->toBe('FAC-100')
        ->and((string) $user->record_id)->toBe((string) $faculty->id)
        ->and($user->role)->toBe(UserRole::Instructor);

    $faculty->update(['faculty_id_number' => 'FAC-200']);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.portal-account', $faculty), [
            'mode' => 'repair',
            'role' => UserRole::Professor->value,
            'send_reset_link' => false,
        ])
        ->assertRedirect();

    expect($user->refresh()->faculty_id_number)->toBe('FAC-200')
        ->and($user->role)->toBe(UserRole::Professor);
});

it('sends a faculty notice through database notifications', function (): void {
    Notification::fake();

    $admin = facultyAdmin();
    $faculty = Faculty::factory()->create([
        'email' => 'notice@example.test',
        'faculty_id_number' => 'FAC-300',
    ]);
    $portalUser = User::factory()->create([
        'email' => 'notice@example.test',
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-300',
        'record_id' => $faculty->id,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.notice', $faculty), [
            'title' => 'Load review',
            'message' => 'Please review your updated load.',
            'priority' => 'high',
        ])
        ->assertRedirect();

    Notification::assertSentTo($portalUser, AdminFacultyNoticeNotification::class);
});

it('creates a faculty deadline using the existing deadline table', function (): void {
    $admin = facultyAdmin();
    $faculty = Faculty::factory()->create();
    $class = Classes::factory()->create([
        'faculty_id' => $faculty->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.deadlines.store', $faculty), [
            'title' => 'Submit grades',
            'description' => 'Upload preliminary grades.',
            'due_date' => '2025-01-15 17:00:00',
            'priority' => 'high',
            'type' => 'grades',
            'class_id' => $class->id,
        ])
        ->assertRedirect();

    expect(FacultyDeadline::query()->where('faculty_id', $faculty->id)->where('title', 'Submit grades')->exists())
        ->toBeTrue();
});

it('blocks accidental reassignment unless confirmed', function (): void {
    $admin = facultyAdmin();
    $targetFaculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();
    $class = Classes::factory()->create([
        'faculty_id' => $otherFaculty->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.assign-classes', $targetFaculty), [
            'class_ids' => [$class->id],
            'notify_faculty' => false,
        ])
        ->assertSessionHasErrors('class_ids');

    expect($class->refresh()->faculty_id)->toBe($otherFaculty->id);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.assign-classes', $targetFaculty), [
            'class_ids' => [$class->id],
            'allow_reassignment' => true,
            'notify_faculty' => false,
        ])
        ->assertRedirect();

    expect($class->refresh()->faculty_id)->toBe($targetFaculty->id);
});
