<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentClasses;
use App\Features\Toggles\StudentOnlineResources;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Pennant\Feature;
use Modules\OnlineResources\Contracts\OnlineResourceScopeHandler;
use Modules\OnlineResources\Enums\OnlineResourceVisibility;
use Modules\OnlineResources\Models\OnlineResourceCategory;
use Modules\OnlineResources\Models\OnlineResourceLink;
use Modules\OnlineResources\Services\OnlineResourceScopeRegistry;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    config([
        'inertia.pages.paths' => [
            ...((array) config('inertia.pages.paths', [resource_path('js/pages')])),
            base_path('vendor/koakademy/online-resources/resources/assets/js/Pages'),
        ],
    ]);
});

function createOnlineResourceClassScenario(): array
{
    $school = School::factory()->create([
        'code' => 'KOA',
        'name' => 'Koakademy College',
        'is_active' => true,
    ]);

    app(TenantContext::class)->setCurrentSchool($school);

    $facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $school->id,
        'email' => 'prof-resources@example.com',
        'faculty_id_number' => 'FAC-50001',
    ]);

    $faculty = Faculty::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => $facultyUser->email,
        'password' => bcrypt('password'),
        'status' => 'active',
        'faculty_id_number' => 'FAC-50001',
    ]);

    $otherFacultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $school->id,
        'email' => 'other-prof@example.com',
        'faculty_id_number' => 'FAC-50002',
    ]);

    $otherFaculty = Faculty::create([
        'id' => (string) Str::uuid(),
        'school_id' => $school->id,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => $otherFacultyUser->email,
        'password' => bcrypt('password'),
        'status' => 'active',
        'faculty_id_number' => 'FAC-50002',
    ]);

    $class = Classes::create([
        'school_id' => $school->id,
        'subject_code' => 'CS101',
        'section' => 'A1',
        'faculty_id' => $faculty->id,
        'school_year' => '2025-2026',
        'semester' => '1st',
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    $enrolledUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $school->id,
        'email' => 'enrolled-student@example.com',
    ]);

    $enrolledStudent = Student::factory()->create([
        'school_id' => $school->id,
        'user_id' => $enrolledUser->id,
        'email' => $enrolledUser->email,
    ]);

    ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $enrolledStudent->id,
    ]);

    $unenrolledUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $school->id,
        'email' => 'outsider-student@example.com',
    ]);

    Student::factory()->create([
        'school_id' => $school->id,
        'user_id' => $unenrolledUser->id,
        'email' => $unenrolledUser->email,
    ]);

    return [
        'school' => $school,
        'facultyUser' => $facultyUser,
        'faculty' => $faculty,
        'otherFacultyUser' => $otherFacultyUser,
        'otherFaculty' => $otherFaculty,
        'class' => $class,
        'enrolledUser' => $enrolledUser,
        'enrolledStudent' => $enrolledStudent,
        'unenrolledUser' => $unenrolledUser,
    ];
}

it('allows the class instructor to manage class online resources and blocks other faculty', function (): void {
    $ctx = createOnlineResourceClassScenario();
    $class = $ctx['class'];

    actingAs($ctx['facultyUser'])
        ->post(route('faculty.classes.resources.categories.store', ['class' => $class->id]), [
            'name' => 'Week 1 Lecture Slides',
            'description' => 'Introductory materials and starter code.',
            'icon' => 'book',
            'accent_color' => 'indigo',
        ])
        ->assertRedirect();

    $category = OnlineResourceCategory::withoutGlobalScopes()
        ->forOwner('class', $class->id)
        ->where('name', 'Week 1 Lecture Slides')
        ->firstOrFail();

    expect($category->visibility)->toBe(OnlineResourceVisibility::Public)
        ->and($category->school_id)->toBe($ctx['school']->id);

    actingAs($ctx['facultyUser'])
        ->post(route('faculty.classes.resources.links.store', ['class' => $class->id, 'category' => $category->id]), [
            'title' => 'Course Syllabus',
            'url' => 'https://docs.example.edu/cs101-syllabus',
            'is_featured' => true,
        ])
        ->assertRedirect();

    // Non-owner instructor cannot create or modify resources on this class
    actingAs($ctx['otherFacultyUser'])
        ->post(route('faculty.classes.resources.categories.store', ['class' => $class->id]), [
            'name' => 'Unauthorized Category',
        ])
        ->assertForbidden();

    actingAs($ctx['otherFacultyUser'])
        ->put(route('faculty.classes.resources.categories.update', ['class' => $class->id, 'category' => $category->id]), [
            'name' => 'Hijacked',
        ])
        ->assertForbidden();

    // QR export works for owner
    $qrResponse = actingAs($ctx['facultyUser'])
        ->get(route('faculty.classes.resources.categories.qr.svg', ['class' => $class->id, 'category' => $category->id]));

    $qrResponse->assertSuccessful();
    expect((string) $qrResponse->headers->get('Content-Type'))->toStartWith('image/svg+xml');
});

it('enforces enrolled_only visibility and class enable_online_resources setting on QR codes', function (): void {
    $ctx = createOnlineResourceClassScenario();
    $class = $ctx['class'];

    $enrolledOnlyCategory = OnlineResourceCategory::factory()
        ->forClassOwner($class)
        ->enrolledOnly()
        ->create(['name' => 'Exam Prep Notes']);

    $link = OnlineResourceLink::factory()->forCategory($enrolledOnlyCategory)->create([
        'title' => 'Practice Exam PDF',
        'url' => 'https://example.edu/cs101/exam-prep',
    ]);

    // Guest is redirected to login
    get(route('online-resources.qr.resolve', ['shortCode' => $enrolledOnlyCategory->short_code]))
        ->assertRedirect(route('login'));

    // Unenrolled student is forbidden
    actingAs($ctx['unenrolledUser'])
        ->get(route('online-resources.qr.resolve', ['shortCode' => $enrolledOnlyCategory->short_code]))
        ->assertForbidden();

    // Enrolled student can view the category and follow the link
    actingAs($ctx['enrolledUser'])
        ->get(route('online-resources.qr.resolve', ['shortCode' => $enrolledOnlyCategory->short_code]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('focusedCategoryId', $enrolledOnlyCategory->id)
            ->where('headerMeta.scope', 'class')
        );

    actingAs($ctx['enrolledUser'])
        ->get(route('online-resources.qr.go', ['shortCode' => $link->short_code]))
        ->assertRedirect('https://example.edu/cs101/exam-prep');

    // Instructor can also view their own enrolled_only category
    actingAs($ctx['facultyUser'])
        ->get(route('online-resources.qr.resolve', ['shortCode' => $enrolledOnlyCategory->short_code]))
        ->assertSuccessful();

    // Disabling Online Resources in Class Settings hides QR access
    actingAs($ctx['facultyUser'])
        ->put(route('faculty.classes.settings.update', ['class' => $class->id]), array_merge(
            Classes::getDefaultSettings(),
            ['enable_online_resources' => false],
        ))
        ->assertRedirect();

    expect((bool) $class->fresh()->getSetting('enable_online_resources', true))->toBeFalse();

    actingAs($ctx['enrolledUser'])
        ->get(route('online-resources.qr.resolve', ['shortCode' => $enrolledOnlyCategory->short_code]))
        ->assertNotFound();

    actingAs($ctx['enrolledUser'])
        ->get(route('online-resources.qr.go', ['shortCode' => $link->short_code]))
        ->assertNotFound();
});

it('clones class resources from a previous class with fresh short codes and blocks unauthorized sources', function (): void {
    $ctx = createOnlineResourceClassScenario();
    $sourceClass = $ctx['class'];

    $targetClass = Classes::create([
        'school_id' => $ctx['school']->id,
        'subject_code' => 'CS101',
        'section' => 'A2',
        'faculty_id' => $ctx['faculty']->id,
        'school_year' => '2026-2027',
        'semester' => '1st',
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    $sourceCategory = OnlineResourceCategory::factory()->forClassOwner($sourceClass)->create([
        'name' => 'Lab Starters',
        'scan_count' => 25,
    ]);

    $sourceLink = OnlineResourceLink::factory()->forCategory($sourceCategory)->create([
        'title' => 'GitHub Classroom',
        'url' => 'https://classroom.github.com/cs101',
        'click_count' => 40,
    ]);

    actingAs($ctx['facultyUser'])
        ->post(route('faculty.classes.resources.copy-from-class', ['class' => $targetClass->id]), [
            'source_class_id' => $sourceClass->id,
        ])
        ->assertRedirect();

    $clonedCategory = OnlineResourceCategory::withoutGlobalScopes()
        ->with('links')
        ->forOwner('class', $targetClass->id)
        ->firstOrFail();

    expect($clonedCategory->name)->toBe('Lab Starters')
        ->and($clonedCategory->short_code)->not->toBe($sourceCategory->short_code)
        ->and($clonedCategory->scan_count)->toBe(0)
        ->and($clonedCategory->links)->toHaveCount(1)
        ->and($clonedCategory->links->first()->title)->toBe('GitHub Classroom')
        ->and($clonedCategory->links->first()->short_code)->not->toBe($sourceLink->short_code)
        ->and($clonedCategory->links->first()->click_count)->toBe(0);

    // Copying from another instructor's class is forbidden
    $otherClass = Classes::create([
        'school_id' => $ctx['school']->id,
        'subject_code' => 'MATH101',
        'section' => 'Z1',
        'faculty_id' => $ctx['otherFaculty']->id,
        'school_year' => '2025-2026',
        'semester' => '1st',
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);

    actingAs($ctx['facultyUser'])
        ->post(route('faculty.classes.resources.copy-from-class', ['class' => $targetClass->id]), [
            'source_class_id' => $otherClass->id,
        ])
        ->assertForbidden();
});

it('aggregates school-wide and enrolled class resources in the student portal', function (): void {
    $ctx = createOnlineResourceClassScenario();

    $schoolCategory = OnlineResourceCategory::factory()->forSchoolOwner($ctx['school'])->create([
        'name' => 'Campus Library',
    ]);
    OnlineResourceLink::factory()->forCategory($schoolCategory)->create([
        'title' => 'E-Books Portal',
        'url' => 'https://ebooks.example.edu',
    ]);

    $classCategory = OnlineResourceCategory::factory()->forClassOwner($ctx['class'])->enrolledOnly()->create([
        'name' => 'CS101 Readings',
    ]);
    OnlineResourceLink::factory()->forCategory($classCategory)->create([
        'title' => 'Algorithm Visualizer',
        'url' => 'https://algorithm-visualizer.org',
    ]);

    // Unenrolled class resources should not appear for this student
    $otherClass = Classes::create([
        'school_id' => $ctx['school']->id,
        'subject_code' => 'HIST200',
        'section' => 'C1',
        'faculty_id' => $ctx['otherFaculty']->id,
        'school_year' => '2025-2026',
        'semester' => '1st',
        'maximum_slots' => 40,
        'classification' => 'college',
    ]);
    $otherClassCategory = OnlineResourceCategory::factory()->forClassOwner($otherClass)->create(['name' => 'Secret History']);
    OnlineResourceLink::factory()->forCategory($otherClassCategory)->create();

    // Gated by the StudentOnlineResources Pennant feature toggle
    actingAs($ctx['enrolledUser'])
        ->get(route('student.resources.index'))
        ->assertForbidden();

    Feature::for($ctx['enrolledUser'])->activate(StudentOnlineResources::class);
    Feature::for($ctx['enrolledUser'])->activate(StudentClasses::class);

    actingAs($ctx['enrolledUser'])
        ->get(route('student.resources.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Student/Index')
            ->has('schoolCategories', 1)
            ->where('schoolCategories.0.name', 'Campus Library')
            ->has('classGroups', 1)
            ->where('classGroups.0.class_id', $ctx['class']->id)
            ->where('classGroups.0.categories.0.name', 'CS101 Readings')
        );

    actingAs($ctx['enrolledUser'])
        ->get(route('student.classes.show', ['class' => $ctx['class']->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('online_resources', 1)
            ->where('online_resources.0.name', 'CS101 Readings')
        );
});

it('supports registering new polymorphic scope handlers dynamically', function (): void {
    $registry = new OnlineResourceScopeRegistry;

    $customHandler = new class implements OnlineResourceScopeHandler
    {
        public function key(): string
        {
            return 'department';
        }

        public function modelClass(): string
        {
            return School::class;
        }

        public function findOwner(int $ownerId): ?Model
        {
            return School::query()->find($ownerId);
        }

        public function schoolIdForOwner(Model $owner): int
        {
            return (int) $owner->getKey();
        }

        public function isEnabledForOwner(Model $owner): bool
        {
            return true;
        }

        public function canViewAdmin(Authenticatable $user, Model $owner): bool
        {
            return true;
        }

        public function canManage(Authenticatable $user, Model $owner): bool
        {
            return true;
        }

        public function canView(?Authenticatable $user, Model $owner, OnlineResourceCategory $category): bool
        {
            return true;
        }

        public function allowedVisibilities(): array
        {
            return [OnlineResourceVisibility::Public];
        }

        public function resolveHeaderMeta(Model $owner): array
        {
            return [
                'scope' => 'department',
                'title' => 'Department Resources',
                'subtitle' => null,
                'badge' => 'Department Hub',
                'school_name' => 'Koakademy',
                'school_code' => 'KOA',
                'hub_url' => null,
            ];
        }
    };

    $registry->register($customHandler);

    expect($registry->keys())->toContain('department')
        ->and($registry->forKey('department'))->toBe($customHandler)
        ->and($registry->forModel(new School))->toBe($customHandler);
});
