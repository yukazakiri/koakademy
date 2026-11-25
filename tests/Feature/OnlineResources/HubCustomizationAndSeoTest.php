<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\OnlineResources\Enums\OnlineResourceLayout;
use Modules\OnlineResources\Enums\OnlineResourceTheme;
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
        'name' => 'KoAkademy University',
        'is_active' => true,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
});

it('customizes the school hub website, uploads raster media, rejects SVG, and serves SSR SEO metadata in Blade head', function (): void {
    Storage::fake('public');
    config()->set('online-resources.media_disk', 'public');

    // Update hub settings with Minimalist layout, Client Native tokens, and custom SEO
    actingAs($this->admin)
        ->put(route('administrators.online-resources.hub-settings.update'), [
            'visibility' => 'public',
            'is_published' => true,
            'headline' => 'KoAkademy Student Digital Hub',
            'tagline' => 'All essential portals and guides in one clean place.',
            'announcement_text' => 'Midterm study guides are live',
            'announcement_url' => 'https://guides.koakademy.edu',
            'cta_label' => 'Main Website',
            'cta_url' => 'https://koakademy.edu',
            'footer_text' => 'KoAkademy Office of Student Services',
            'design_config' => [
                'layout' => OnlineResourceLayout::Minimalist->value,
                'theme' => OnlineResourceTheme::ClientDefault->value,
                'accent_color' => 'client',
                'hero_style' => 'gradient',
                'card_style' => 'bordered',
            ],
            'seo_config' => [
                'meta_title' => 'KoAkademy Digital Hub | Student Resources',
                'meta_description' => 'Curated official student links, forms, and portals at KoAkademy University.',
                'keywords' => 'koakademy, student links, campus portal',
                'robots_index' => true,
                'structured_data_enabled' => true,
            ],
        ])
        ->assertRedirect();

    // Upload valid PNG banner
    $bannerUpload = actingAs($this->admin)
        ->postJson(route('administrators.online-resources.hub-settings.media.store'), [
            'slot' => 'banner',
            'file' => UploadedFile::fake()->image('campus-banner.png', 1200, 400),
        ]);

    $bannerUpload->assertSuccessful()
        ->assertJsonPath('slot', 'banner');
    expect($bannerUpload->json('path'))->toStartWith('online-resources/'.$this->school->id.'/banner/');

    // Reject SVG uploads to prevent stored XSS
    actingAs($this->admin)
        ->postJson(route('administrators.online-resources.hub-settings.media.store'), [
            'slot' => 'logo',
            'file' => UploadedFile::fake()->create('evil.svg', 8, 'image/svg+xml'),
        ])
        ->assertUnprocessable();

    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Library Databases',
        'slug' => 'library-databases',
        'visibility' => OnlineResourceVisibility::Public,
        'design_mode' => 'custom',
        'hero_tagline' => 'Explore peer-reviewed journals and e-books.',
        'design_config' => [
            'layout' => OnlineResourceLayout::VisualShowcase->value,
            'theme' => OnlineResourceTheme::VisualShowcase->value,
            'accent_color' => 'client',
        ],
        'seo_config' => [
            'meta_title' => 'Library Databases — KoAkademy',
            'meta_description' => 'Direct access to KoAkademy research databases and digital archives.',
            'robots_index' => true,
        ],
    ]);

    OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Digital Journals',
        'url' => 'https://journals.koakademy.edu',
        'is_active' => true,
    ]);

    // 1. Main Public Hub Website (as guest) serves SSR HTML SEO + JSON-LD + public caching
    Auth::logout();
    $hubResponse = get(route('online-resources.directory', ['schoolCode' => 'KOA']));

    $hubResponse->assertSuccessful()
        ->assertSee('<title inertia>KoAkademy Digital Hub | Student Resources</title>', false)
        ->assertSee('Curated official student links, forms, and portals at KoAkademy University.', false)
        ->assertSee('application/ld+json', false)
        ->assertSee('CollectionPage', false)
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('viewMode', 'hub')
            ->where('resolvedDesign.layout', 'minimalist')
            ->where('resolvedDesign.accent_color', 'client')
            ->where('hubSettings.headline', 'KoAkademy Student Digital Hub')
        );

    expect((string) $hubResponse->headers->get('Cache-Control'))->toContain('public');

    // 2. Dedicated Category Hub Website via /q/{shortCode} uses category custom design & canonicalizes to /resources/KOA/library-databases
    $categoryResponse = get(route('online-resources.qr.resolve', ['shortCode' => $category->short_code]));
    $expectedCanonical = route('online-resources.directory', ['schoolCode' => 'KOA', 'categorySlug' => 'library-databases']);

    $categoryResponse->assertSuccessful()
        ->assertSee('<title inertia>Library Databases — KoAkademy</title>', false)
        ->assertSee('<link rel="canonical" href="'.$expectedCanonical.'">', false)
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('viewMode', 'category')
            ->where('activeCategory.id', $category->id)
            ->where('resolvedDesign.layout', 'visual_showcase')
            ->where('resolvedDesign.theme', 'visual_showcase')
            ->where('seo.canonical_url', $expectedCanonical)
        );
});

it('enforces visibility gates, noindex headers, and excludes private or unlisted pages from sitemap.xml', function (): void {
    $publicIndexed = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Public Admissions',
        'slug' => 'public-admissions',
        'visibility' => OnlineResourceVisibility::Public,
        'seo_config' => ['robots_index' => true],
    ]);

    $publicUnlisted = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Unlisted Orientation',
        'slug' => 'unlisted-orientation',
        'visibility' => OnlineResourceVisibility::Public,
        'seo_config' => ['robots_index' => false],
    ]);

    $privateAuth = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Internal Faculty Memo',
        'slug' => 'internal-faculty-memo',
        'visibility' => OnlineResourceVisibility::Authenticated,
        'seo_config' => ['robots_index' => true],
    ]);

    // Sitemap includes only active public + indexable categories & hub
    $sitemap = get(route('online-resources.sitemap'));
    $sitemap->assertSuccessful()
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('/resources/KOA/public-admissions', false)
        ->assertDontSee('/resources/KOA/unlisted-orientation', false)
        ->assertDontSee('/resources/KOA/internal-faculty-memo', false);

    // Public unlisted category loads for guests but emits X-Robots-Tag: noindex, nofollow
    $unlistedResponse = get(route('online-resources.directory', ['schoolCode' => 'KOA', 'categorySlug' => $publicUnlisted->slug]));
    $unlistedResponse->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertDontSee('application/ld+json', false);

    // Private authenticated category hides slug from guests (404 anti-enumeration) and redirects QR scans to login
    get(route('online-resources.directory', ['schoolCode' => 'KOA', 'categorySlug' => $privateAuth->slug]))
        ->assertNotFound();

    get(route('online-resources.qr.resolve', ['shortCode' => $privateAuth->short_code]))
        ->assertRedirect(route('login'));

    // Signed-in admin can view private category, but it is still strictly noindex + private no-store
    $privateResponse = actingAs($this->admin)
        ->get(route('online-resources.directory', ['schoolCode' => 'KOA', 'categorySlug' => $privateAuth->slug]));

    $privateResponse->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertDontSee('application/ld+json', false);
    expect((string) $privateResponse->headers->get('Cache-Control'))->toContain('private');

    // Switching entire school hub to authenticated blocks guests from /resources/KOA and removes from sitemap
    actingAs($this->admin)
        ->put(route('administrators.online-resources.hub-settings.update'), [
            'visibility' => 'authenticated',
            'is_published' => true,
        ])
        ->assertRedirect();

    Auth::logout();
    get(route('online-resources.directory', ['schoolCode' => 'KOA']))
        ->assertRedirect(route('login'));

    get(route('online-resources.sitemap'))
        ->assertSuccessful()
        ->assertDontSee('/resources/KOA', false);
});

it('supports customizable Class Resource Hub websites with visibility enforcement for faculty and enrolled students', function (): void {
    $facultyUser = User::factory()->create([
        'role' => UserRole::Instructor,
        'school_id' => $this->school->id,
        'email' => 'prof.hub@koakademy.test',
        'faculty_id_number' => 'FAC-60001',
    ]);
    $faculty = Faculty::query()->create([
        'id' => (string) Str::uuid(),
        'school_id' => $this->school->id,
        'first_name' => 'Clara',
        'last_name' => 'Reyes',
        'email' => $facultyUser->email,
        'password' => bcrypt('password'),
        'department' => 'CS',
        'status' => 'active',
        'faculty_id_number' => 'FAC-60001',
    ]);

    $class = Classes::query()->create([
        'school_id' => $this->school->id,
        'faculty_id' => $faculty->id,
        'subject_code' => 'CS201',
        'section' => 'A',
        'school_year' => '2026-2027',
        'semester' => '1',
        'classification' => 'college',
        'settings' => ['enable_online_resources' => true],
    ]);

    $enrolledUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
        'email' => 'enrolled.hub@koakademy.test',
    ]);
    $student = Student::factory()->create([
        'school_id' => $this->school->id,
        'user_id' => $enrolledUser->id,
        'email' => $enrolledUser->email,
    ]);
    ClassEnrollment::query()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $outsiderUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
        'email' => 'outsider.hub@koakademy.test',
    ]);

    // Faculty customizes their Class Resource Hub Website with Bento layout & enrolled_only visibility
    actingAs($facultyUser)
        ->put(route('faculty.classes.resources.hub-settings.update', ['class' => $class->id]), [
            'visibility' => 'enrolled_only',
            'is_published' => true,
            'headline' => 'CS201 Algorithms Portal',
            'tagline' => 'Weekly slides, starter repos, and lab sheets.',
            'design_config' => [
                'layout' => OnlineResourceLayout::Bento->value,
                'theme' => OnlineResourceTheme::Compact->value,
                'accent_color' => 'client',
            ],
            'seo_config' => [
                'meta_title' => 'CS201 Resources',
                'robots_index' => true,
            ],
        ])
        ->assertRedirect();

    $category = OnlineResourceCategory::factory()->forClassOwner($class)->create([
        'name' => 'Lab Starter Kits',
        'slug' => 'lab-starter-kits',
        'visibility' => OnlineResourceVisibility::EnrolledOnly,
    ]);
    OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'GitHub Repo',
        'url' => 'https://github.com/koakademy/cs201-labs',
    ]);

    // Guest is redirected to login; non-enrolled student gets 403
    Auth::logout();
    get(route('online-resources.class-directory', ['class' => $class->id]))
        ->assertRedirect(route('login'));

    actingAs($outsiderUser)
        ->get(route('online-resources.class-directory', ['class' => $class->id]))
        ->assertForbidden();

    // Enrolled student sees the customized Bento Class Hub Website with forced noindex + private caching
    $enrolledResponse = actingAs($enrolledUser)
        ->get(route('online-resources.class-directory', ['class' => $class->id]));

    $enrolledResponse->assertSuccessful()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Public/Hub')
            ->where('viewMode', 'hub')
            ->where('hubSettings.headline', 'CS201 Algorithms Portal')
            ->where('resolvedDesign.layout', 'bento')
            ->where('resolvedDesign.theme', 'compact')
            ->where('seo.allow_indexing', false)
        );

    // Faculty can export Class Hub Master QR SVG
    actingAs($facultyUser)
        ->get(route('faculty.classes.resources.hub.qr.svg', ['class' => $class->id]))
        ->assertSuccessful();
});
