<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
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

/**
 * @return array{0: User, 1: Faculty}
 */
function facultyPortalUser(string $email = 'professor@example.test'): array
{
    $user = User::factory()->create([
        'role' => UserRole::Professor,
        'email' => $email,
        'faculty_id_number' => 'FAC-'.mb_strtoupper(mb_substr(md5($email), 0, 6)),
    ]);

    $faculty = Faculty::factory()->create(['email' => $email]);

    return [$user, $faculty];
}

it('serves the faculty search endpoints at their prefixed URLs for every faculty role', function (): void {
    [$user, $faculty] = facultyPortalUser();

    $subject = Subject::factory()->create(['code' => 'IT501', 'title' => 'Systems Integration']);
    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'BSIT']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'faculty_id' => $faculty->id,
        'course_codes' => [(string) $course->id],
    ]);

    $student = Student::factory()->create([
        'first_name' => 'Noel',
        'last_name' => 'Bautista',
        'course_id' => $course->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    actingAs($user);

    getJson('/faculty/search/classes?q=Systems')
        ->assertOk()
        ->assertJsonPath('classes.0.id', $class->id);

    getJson('/faculty/search/students?q=Noel+Bautista')
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id)
        ->assertJsonPath('students.0.sections.0', 'IT501 - B');
});

it('only returns classes and students the faculty actually teaches', function (): void {
    [$user, $faculty] = facultyPortalUser('owner@example.test');
    [, $otherFaculty] = facultyPortalUser('other@example.test');

    $ownSubject = Subject::factory()->create(['code' => 'IT601', 'title' => 'Shared Keyword Class']);
    $otherSubject = Subject::factory()->create(['code' => 'IT602', 'title' => 'Shared Keyword Class']);

    $ownClass = Classes::factory()->create([
        'subject_id' => $ownSubject->id,
        'subject_code' => $ownSubject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'faculty_id' => $faculty->id,
    ]);

    $otherClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'faculty_id' => $otherFaculty->id,
    ]);

    $mine = Student::factory()->create([
        'first_name' => 'Same',
        'last_name' => 'SurnameMine',
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    $theirs = Student::factory()->create([
        'first_name' => 'Same',
        'last_name' => 'SurnameTheirs',
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    ClassEnrollment::factory()->create(['class_id' => $ownClass->id, 'student_id' => $mine->id]);
    ClassEnrollment::factory()->create(['class_id' => $otherClass->id, 'student_id' => $theirs->id]);

    actingAs($user);

    $classes = getJson('/faculty/search/classes?q=Shared')->assertOk()->json('classes');

    expect(array_column($classes, 'id'))->toBe([$ownClass->id]);

    $students = getJson('/faculty/search/students?q=Same')->assertOk()->json('students');

    expect(array_column($students, 'id'))->toBe([$mine->id]);
});

it('rejects faculty search for non faculty users', function (): void {
    $studentUser = User::factory()->create(['role' => UserRole::Student]);

    actingAs($studentUser);

    getJson('/faculty/search/classes?q=anything')->assertForbidden();
});

it('renders the faculty results page with a destination per row', function (): void {
    withoutVite();

    [$user, $faculty] = facultyPortalUser();

    $subject = Subject::factory()->create(['code' => 'IT801', 'title' => 'Paginated Subject']);
    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'BSIT']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'C',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'faculty_id' => $faculty->id,
        'course_codes' => [(string) $course->id],
    ]);

    actingAs($user);

    get('/faculty/search/results?q=Paginated&type=classes')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('faculty/search/results')
            ->where('group', 'classes')
            ->where('query', 'Paginated')
            ->has('results.data', 1)
            ->where('results.data.0.url', fn (string $url): bool => str_contains($url, "/faculty/classes/{$class->id}")));
});

it('rejects the faculty results page for non faculty users', function (): void {
    $studentUser = User::factory()->create(['role' => UserRole::Student]);

    actingAs($studentUser);

    get('/faculty/search/results?q=anything')->assertForbidden();
});
