<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    Cache::forget('general_settings_id');

    School::factory()->create(['id' => 1]);

    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
    ]);
});

it('returns current students, classes, and enrollments from the administrator global search', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
    ]);

    $student = Student::factory()->create([
        'student_id' => 205001,
        'first_name' => 'Alicia',
        'last_name' => 'Mercado',
        'middle_name' => 'Santos',
        'course_id' => $course->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    $subject = Subject::factory()->create([
        'code' => 'IT401',
        'title' => 'Capstone Project',
        'course_id' => $course->id,
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'course_codes' => [(string) $course->id],
        'room_id' => Room::factory()->create()->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 4,
        'status' => 'enrolled',
    ]);

    actingAs($admin);

    $studentSearchQuery = rawurlencode($student->full_name);

    getJson(portalUrlForAdministrators("/administrators/search?q={$studentSearchQuery}&type=students"))
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id)
        ->assertJsonPath('students.0.name', $student->full_name);

    getJson(portalUrlForAdministrators('/administrators/search?q=Capstone&type=classes'))
        ->assertOk()
        ->assertJsonPath('classes.0.id', $class->id)
        ->assertJsonPath('classes.0.subject_code', $subject->code);

    getJson(portalUrlForAdministrators("/administrators/search?q={$studentSearchQuery}&type=enrollments"))
        ->assertOk()
        ->assertJsonPath('enrollments.0.id', $enrollment->id)
        ->assertJsonPath('enrollments.0.student_name', $student->full_name);
});

it('matches multi-word queries and falls back to all groups for unknown types', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSED', 'title' => 'Bachelor of Secondary Education']);

    $student = Student::factory()->create([
        'student_id' => 205777,
        'first_name' => 'Ramon',
        'last_name' => 'Villanueva',
        'middle_name' => 'Cruz',
        'course_id' => $course->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    actingAs($admin);

    // Both terms have to match, in any order and ignoring punctuation.
    getJson(portalUrlForAdministrators('/administrators/search?q=Ramon+Villanueva&type=students'))
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id);

    getJson(portalUrlForAdministrators('/administrators/search?q='.rawurlencode('Villanueva, Ramon').'&type=students'))
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id);
});

it('returns every result group so the palette always has a stable shape', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin);

    getJson(portalUrlForAdministrators('/administrators/search?q=anything&type=nonsense'))
        ->assertOk()
        ->assertJsonStructure([
            'students',
            'classes',
            'users',
            'faculty',
            'enrollments',
            'courses',
        ]);
});

it('gives every row the destination the palette and the results page share', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSN', 'title' => 'Bachelor of Science in Nursing']);

    $student = Student::factory()->create([
        'student_id' => 301001,
        'first_name' => 'Destination',
        'last_name' => 'Row',
        'course_id' => $course->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    actingAs($admin);

    $studentRow = getJson(portalUrlForAdministrators('/administrators/search?q=Destination+Row&type=students'))
        ->assertOk()
        ->json('students.0');

    expect($studentRow['url'])->toContain("/administrators/students/{$student->id}");

    $courseRow = getJson(portalUrlForAdministrators('/administrators/search?q=BSN&type=courses'))
        ->assertOk()
        ->json('courses.0');

    expect($courseRow['url'])->toContain("/administrators/curriculum/programs/{$course->id}");
});

it('finds a program by its department code', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $department = Department::factory()->withNameAndCode('Nursing', 'NURS')->create(['school_id' => 1]);

    $course = Course::factory()->create([
        'code' => 'BSN',
        'title' => 'Bachelor of Science in Nursing',
        'department_id' => $department->id,
    ]);

    actingAs($admin);

    getJson(portalUrlForAdministrators('/administrators/search?q='.$department->code.'&type=courses'))
        ->assertOk()
        ->assertJsonPath('courses.0.id', $course->id);
});

it('ignores queries shorter than the minimum length', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin);

    getJson(portalUrlForAdministrators('/administrators/search?q=a&type=students'))
        ->assertOk()
        ->assertJsonPath('students', []);
});

it('paginates the full results page for a single group', function (): void {
    withoutVite();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $course = Course::factory()->create(['code' => 'BSCS', 'title' => 'Computer Science']);

    foreach (range(1, 7) as $index) {
        Student::factory()->create([
            'student_id' => 300000 + $index,
            'first_name' => 'Searchable',
            'last_name' => 'Candidate',
            'course_id' => $course->id,
            'school_id' => 1,
            'institution_id' => 1,
        ]);
    }

    actingAs($admin);

    $firstPage = get(portalUrlForAdministrators('/administrators/search/results?q=Searchable&type=students'));
    $firstPage->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('administrators/search/results')
        ->where('group', 'students')
        ->where('query', 'Searchable')
        ->has('results.data', 7)
        ->where('results.current_page', 1)
        ->where('results.total', 7));

    // A page size of 20 means these seven fit; the page must still be paginator-shaped.
    expect($firstPage->viewData('page')['props']['results']['last_page'])->toBe(1);

    get(portalUrlForAdministrators('/administrators/search/results?q=Searchable&type=classes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('administrators/search/results')
            ->where('group', 'classes')
            ->has('results.data', 0));

    // Programs are searchable too, and each row links to the program page.
    get(portalUrlForAdministrators('/administrators/search/results?q=Computer&type=courses'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('administrators/search/results')
            ->where('group', 'courses')
            ->has('results.data', 1)
            ->where('results.data.0.url', fn (string $url): bool => str_contains($url, '/administrators/curriculum/programs/')));
});

it('renders the results page empty state without a query', function (): void {
    withoutVite();

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin);

    get(portalUrlForAdministrators('/administrators/search/results'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('administrators/search/results')
            ->where('query', null)
            ->where('group', 'students')
            ->has('results.data', 0));
});

it('finds Monday meetings and narrows by the meeting room without mixing different days', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $class = Classes::factory()->create([
        'school_year' => '2025-2026', 'semester' => 1, 'classification' => 'college',
    ]);
    $lab = Room::factory()->create(['name' => 'Science Lab']);
    $hall = Room::factory()->create(['name' => 'West Hall']);
    $monday = App\Models\Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $lab->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00:00', 'end_time' => '09:00:00',
    ]);
    App\Models\Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $hall->id, 'day_of_week' => 'Tuesday',
    ]);
    $oldClass = Classes::factory()->create(['school_year' => '2024 - 2025', 'semester' => 1]);
    App\Models\Schedule::factory()->create([
        'class_id' => $oldClass->id, 'room_id' => $lab->id, 'day_of_week' => 'Monday',
    ]);
    actingAs($admin);

    getJson(portalUrlForAdministrators('/administrators/search?q=monday&type=schedule'))
        ->assertOk()->assertJsonCount(1, 'schedule')
        ->assertJsonPath('schedule.0.id', $monday->id)
        ->assertJsonPath('schedule.0.room', 'Science Lab')
        ->assertJsonPath('schedule.0.time_range', '08:00 AM - 09:00 AM');
    getJson(portalUrlForAdministrators('/administrators/search?q=monday+science+lab&type=schedule'))
        ->assertOk()->assertJsonCount(1, 'schedule');
    getJson(portalUrlForAdministrators('/administrators/search?q=monday+west+hall&type=schedule'))
        ->assertOk()->assertJsonCount(0, 'schedule');
    getJson(portalUrlForAdministrators('/administrators/search?q=!!&type=schedule'))
        ->assertOk()->assertJsonCount(0, 'schedule');

    withoutVite();
    get(portalUrlForAdministrators('/administrators/search/results?q=monday+science&type=schedule'))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->where('group', 'schedule')->where('results.total', 1)
        ->where('results.data.0.id', $monday->id));
});

it('provides a direct curriculum edit link alongside the existing view link', function (): void {
    actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create(['code' => 'EDITTEST']);
    $row = getJson(portalUrlForAdministrators('/administrators/search?q=EDITTEST&type=courses'))
        ->assertOk()->json('courses.0');
    expect($row['url'])->toContain("/curriculum/programs/{$course->id}")
        ->and($row['edit_url'])->toContain("/curriculum/programs/{$course->id}?tab=settings");

    withoutVite();
    get(portalUrlForAdministrators("/administrators/curriculum/programs/{$course->id}?tab=settings"))
        ->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('administrators/curriculum/programs/show')->where('initialTab', 'settings'));

});
