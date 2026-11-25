<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Modules\OnlineResources\Enums\OnlineResourceVisibility;
use Modules\OnlineResources\Models\OnlineResourceCategory;
use Modules\OnlineResources\Models\OnlineResourceLink;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    config([
        'inertia.pages.paths' => [
            ...((array) config('inertia.pages.paths', [resource_path('js/pages')])),
            base_path('vendor/koakademy/online-resources/resources/assets/js/Pages'),
        ],
    ]);

    $this->school = School::factory()->create([
        'code' => 'KOA',
        'name' => 'Koakademy Academy',
        'is_active' => true,
    ]);
});

it('serves public category QR codes instantly without login and increments scan count', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Quick Study Links',
        'visibility' => OnlineResourceVisibility::Public,
        'scan_count' => 0,
    ]);

    OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Active Link',
        'url' => 'https://example.org/active',
        'is_active' => true,
    ]);

    OnlineResourceLink::factory()->forCategory($category)->inactive()->create([
        'title' => 'Hidden Link',
        'url' => 'https://example.org/hidden',
    ]);

    get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('focusedCategoryId', $category->id)
            ->where('headerMeta.school_name', 'Koakademy Academy')
            ->has('categories', 1)
            ->has('categories.0.links', 1)
            ->where('categories.0.links.0.title', 'Active Link')
        );

    expect($category->fresh()->scan_count)->toBe(1);
});

it('redirects link short codes with no-referrer header and tracks clicks', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'visibility' => OnlineResourceVisibility::Public,
    ]);

    $link = OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Digital Library',
        'url' => 'https://library.example.edu/portal',
        'click_count' => 0,
    ]);

    get(route('online-resources.qr.go', ['shortCode' => $link->short_code]))
        ->assertRedirect('https://library.example.edu/portal')
        ->assertHeader('Referrer-Policy', 'no-referrer');

    $link->refresh();
    expect($link->click_count)->toBe(1)
        ->and($link->last_clicked_at)->not->toBeNull();

    // Direct /q/{linkShortCode} also redirects seamlessly
    get(route('online-resources.qr.resolve', ['shortCode' => $link->short_code]))
        ->assertRedirect('https://library.example.edu/portal');

    expect($link->fresh()->click_count)->toBe(2);
});

it('enforces authenticated visibility for guests and users from other schools', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->authenticated()->create([
        'name' => 'Internal Campus Resources',
    ]);

    $link = OnlineResourceLink::factory()->forCategory($category)->create([
        'url' => 'https://intranet.example.edu',
    ]);

    // Guests are redirected to login
    get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertRedirect(route('login'));

    get(route('online-resources.qr.go', ['shortCode' => $link->short_code]))
        ->assertRedirect(route('login'));

    // Authenticated user from same school is allowed
    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);

    actingAs($studentUser)
        ->get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertSuccessful();

    // Authenticated user from a different school is denied
    $otherSchool = School::factory()->create(['code' => 'OTHER', 'is_active' => true]);
    $otherSchoolUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $otherSchool->id,
    ]);

    actingAs($otherSchoolUser)
        ->get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertForbidden();
});

it('returns 404 for inactive or soft-deleted categories and links', function (): void {
    $inactiveCategory = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->inactive()->create();
    $activeCategory = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create();
    $inactiveLink = OnlineResourceLink::factory()->forCategory($activeCategory)->inactive()->create();

    get(route('online-resources.qr.resolve', ['shortCode' => $inactiveCategory->short_code]))
        ->assertNotFound();

    get(route('online-resources.qr.go', ['shortCode' => $inactiveLink->short_code]))
        ->assertNotFound();

    $activeCategory->delete();

    get(route('online-resources.qr.resolve', ['shortCode' => $activeCategory->short_code]))
        ->assertNotFound();
});

it('serves the human-readable school directory and category slug deep links', function (): void {
    $publicCategory = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Research Databases',
        'slug' => 'research-databases',
        'visibility' => OnlineResourceVisibility::Public,
    ]);

    OnlineResourceCategory::factory()->forSchoolOwner($this->school)->authenticated()->create([
        'name' => 'Staff Only',
        'slug' => 'staff-only',
    ]);

    get(route('online-resources.directory', ['schoolCode' => 'koa']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('focusedCategoryId', null)
            ->has('categories', 1)
            ->where('categories.0.id', $publicCategory->id)
        );

    get(route('online-resources.directory', ['schoolCode' => 'koa', 'categorySlug' => 'research-databases']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('focusedCategoryId', $publicCategory->id)
        );

    get(route('online-resources.directory', ['schoolCode' => 'koa', 'categorySlug' => 'staff-only']))
        ->assertNotFound();
});

it('resolves role-aware portal URLs on the public hub and allows reporting broken public links', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Campus Portals',
        'visibility' => OnlineResourceVisibility::Public,
    ]);
    $link = OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Broken Form',
        'url' => 'https://forms.example.edu/old',
    ]);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);

    $student = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);

    actingAs($admin)
        ->get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('portalUrl', '/administrators/online-resources')
        );

    actingAs($student)
        ->get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('portalUrl', '/student/resources')
        );

    $this->postJson(route('online-resources.qr.report-broken', ['shortCode' => $link->short_code]))
        ->assertSuccessful()
        ->assertExactJson([
            'reported' => true,
            'broken_report_count' => 1,
        ]);

    expect($link->fresh()->broken_report_count)->toBe(1);
});
