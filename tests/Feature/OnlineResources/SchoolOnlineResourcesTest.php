<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Services\ModuleAdminNavigationService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Http;
use Modules\OnlineResources\Enums\OnlineResourceAudience;
use Modules\OnlineResources\Enums\OnlineResourceVisibility;
use Modules\OnlineResources\Models\OnlineResourceCategory;
use Modules\OnlineResources\Models\OnlineResourceLink;
use Modules\OnlineResources\Services\OnlineResourceMetadataService;
use Spatie\LaravelPdf\Facades\Pdf;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

beforeEach(function (): void {
    config([
        'inertia.pages.paths' => [
            ...((array) config('inertia.pages.paths', [resource_path('js/pages')])),
            base_path('vendor/koakademy/online-resources/resources/assets/js/Pages'),
        ],
    ]);

    $this->school = School::factory()->create([
        'code' => 'KOA',
        'name' => 'Koakademy Main Campus',
        'is_active' => true,
    ]);

    app(TenantContext::class)->setCurrentSchool($this->school);

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
});

it('renders the administrator online resources hub with stats and categories', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Library Databases',
        'scan_count' => 12,
    ]);

    OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Google Scholar',
        'url' => 'https://scholar.google.com',
        'click_count' => 7,
    ]);

    actingAs($this->admin)
        ->get(route('administrators.online-resources.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnlineResources/Admin/Index')
            ->where('school.id', $this->school->id)
            ->where('canManage', true)
            ->has('categories', 1)
            ->where('categories.0.name', 'Library Databases')
            ->where('categories.0.visibility', 'public')
            ->where('categories.0.scan_count', 12)
            ->where('categories.0.total_clicks', 7)
            ->where('categories.0.links.0.title', 'Google Scholar')
        );
});

it('creates, updates, reorders, and deletes school categories while keeping short codes immutable', function (): void {
    actingAs($this->admin)
        ->post(route('administrators.online-resources.categories.store'), [
            'name' => 'Student Forms',
            'description' => 'Quick downloadable request forms.',
            'icon' => 'file',
            'accent_color' => 'emerald',
            'audience' => OnlineResourceAudience::Students->value,
        ])
        ->assertRedirect();

    $category = OnlineResourceCategory::withoutGlobalScopes()->where('name', 'Student Forms')->firstOrFail();

    expect($category->owner_type)->toBe('school')
        ->and($category->owner_id)->toBe($this->school->id)
        ->and($category->school_id)->toBe($this->school->id)
        ->and($category->visibility)->toBe(OnlineResourceVisibility::Public)
        ->and(mb_strlen($category->short_code))->toBe(8);

    $originalShortCode = $category->short_code;

    actingAs($this->admin)
        ->put(route('administrators.online-resources.categories.update', ['category' => $category->id]), [
            'name' => 'Registrar & Student Forms',
            'visibility' => OnlineResourceVisibility::Authenticated->value,
            'is_active' => true,
        ])
        ->assertRedirect();

    $category->refresh();

    expect($category->name)->toBe('Registrar & Student Forms')
        ->and($category->visibility)->toBe(OnlineResourceVisibility::Authenticated)
        ->and($category->short_code)->toBe($originalShortCode);

    $second = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'LMS & Portals',
        'sort_order' => 1,
    ]);

    actingAs($this->admin)
        ->patch(route('administrators.online-resources.categories.reorder'), [
            'ordered_ids' => [$second->id, $category->id],
        ])
        ->assertRedirect();

    expect($second->fresh()->sort_order)->toBe(1)
        ->and($category->fresh()->sort_order)->toBe(2);

    actingAs($this->admin)
        ->delete(route('administrators.online-resources.categories.destroy', ['category' => $category->id]))
        ->assertRedirect();

    assertSoftDeleted('online_resource_categories', ['id' => $category->id]);
});

it('creates, updates, reorders, and deletes links and rejects unsafe URL schemes', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Study Tools',
    ]);

    foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'ftp://files.example.com/doc.pdf'] as $unsafeUrl) {
        actingAs($this->admin)
            ->post(route('administrators.online-resources.links.store', ['category' => $category->id]), [
                'title' => 'Unsafe Link',
                'url' => $unsafeUrl,
            ])
            ->assertSessionHasErrors('url');
    }

    actingAs($this->admin)
        ->post(route('administrators.online-resources.links.store', ['category' => $category->id]), [
            'title' => 'Khan Academy',
            'url' => 'https://www.khanacademy.org',
            'description' => 'Free practice exercises.',
            'badge_text' => 'Free',
            'is_featured' => true,
        ])
        ->assertRedirect();

    $firstLink = OnlineResourceLink::withoutGlobalScopes()->where('title', 'Khan Academy')->firstOrFail();
    expect($firstLink->school_id)->toBe($this->school->id)
        ->and($firstLink->is_featured)->toBeTrue()
        ->and(mb_strlen($firstLink->short_code))->toBe(8);

    actingAs($this->admin)
        ->put(route('administrators.online-resources.links.update', ['link' => $firstLink->id]), [
            'title' => 'Khan Academy Math & Science',
            'url' => 'https://www.khanacademy.org/math',
            'is_featured' => false,
        ])
        ->assertRedirect();

    assertDatabaseHas('online_resource_links', [
        'id' => $firstLink->id,
        'title' => 'Khan Academy Math & Science',
        'is_featured' => false,
    ]);

    $secondLink = OnlineResourceLink::factory()->forCategory($category)->create([
        'title' => 'Desmos Calculator',
        'sort_order' => 1,
    ]);

    actingAs($this->admin)
        ->patch(route('administrators.online-resources.links.reorder', ['category' => $category->id]), [
            'ordered_ids' => [$secondLink->id, $firstLink->id],
        ])
        ->assertRedirect();

    expect($secondLink->fresh()->sort_order)->toBe(1)
        ->and($firstLink->fresh()->sort_order)->toBe(2);

    actingAs($this->admin)
        ->delete(route('administrators.online-resources.links.destroy', ['link' => $firstLink->id]))
        ->assertRedirect();

    assertSoftDeleted('online_resource_links', ['id' => $firstLink->id]);
});

it('enforces cross-school tenant isolation for administrator actions', function (): void {
    $otherSchool = School::factory()->create(['code' => 'OTHER', 'is_active' => true]);
    $otherCategory = OnlineResourceCategory::factory()->forSchoolOwner($otherSchool)->create(['name' => 'Other School Category']);
    $otherLink = OnlineResourceLink::factory()->forCategory($otherCategory)->create(['title' => 'Other School Link']);

    actingAs($this->admin)
        ->put(route('administrators.online-resources.categories.update', ['category' => $otherCategory->id]), [
            'name' => 'Tampered Name',
        ])
        ->assertNotFound();

    actingAs($this->admin)
        ->delete(route('administrators.online-resources.links.destroy', ['link' => $otherLink->id]))
        ->assertNotFound();

    actingAs($this->admin)
        ->get(route('administrators.online-resources.categories.qr.svg', ['category' => $otherCategory->id]))
        ->assertNotFound();
});

it('generates SVG and PNG QR codes and printable PDF exports for categories and the master hub', function (): void {
    $category = OnlineResourceCategory::factory()->forSchoolOwner($this->school)->create([
        'name' => 'Campus Wifi & IT',
    ]);

    $svgResponse = actingAs($this->admin)
        ->get(route('administrators.online-resources.categories.qr.svg', ['category' => $category->id]));

    $svgResponse->assertSuccessful();
    expect((string) $svgResponse->headers->get('Content-Type'))->toStartWith('image/svg+xml')
        ->and($svgResponse->getContent())->toContain('<svg');

    $pngResponse = actingAs($this->admin)
        ->get(route('administrators.online-resources.categories.qr.png', ['category' => $category->id]));

    $pngResponse->assertSuccessful()
        ->assertHeader('Content-Type', 'image/png');
    expect(str_starts_with((string) $pngResponse->getContent(), "\x89PNG"))->toBeTrue();

    $hubSvgResponse = actingAs($this->admin)
        ->get(route('administrators.online-resources.hub.qr.svg'));
    $hubSvgResponse->assertSuccessful();
    expect((string) $hubSvgResponse->headers->get('Content-Type'))->toStartWith('image/svg+xml');

    Pdf::fake();

    actingAs($this->admin)
        ->get(route('administrators.online-resources.categories.poster.pdf', ['category' => $category->id]))
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(fn ($pdf): bool => $pdf->viewName === 'online-resources::pdf.qr-poster');

    actingAs($this->admin)
        ->get(route('administrators.online-resources.sheet.pdf'))
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(fn ($pdf): bool => $pdf->viewName === 'online-resources::pdf.qr-sheet');
});

it('discovers the standalone OnlineResources module in admin navigation', function (): void {
    $nav = app(ModuleAdminNavigationService::class);
    $nav->forgetCache();

    $routes = collect($nav->getRoutes());
    $entry = $routes->firstWhere('module', 'OnlineResources');

    expect($entry)->not->toBeNull()
        ->and($entry['inertiaPage'] ?? null)->toBe('OnlineResources/Admin/Index')
        ->and($entry['link'] ?? null)->toBe('/administrators/online-resources');
});

it('fetches link metadata for administrators and rejects unsafe URLs', function (): void {
    app()->instance(
        OnlineResourceMetadataService::class,
        new OnlineResourceMetadataService(static fn (): array => ['93.184.216.34']),
    );

    Http::fake([
        'https://scholar.example.edu/research-portal' => Http::response(
            '<html><head><meta property="og:title" content="Campus Research Portal"></head></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    actingAs($this->admin)
        ->postJson(route('administrators.online-resources.links.metadata'), [
            'url' => 'https://scholar.example.edu/research-portal',
        ])
        ->assertSuccessful()
        ->assertExactJson([
            'title' => 'Campus Research Portal',
            'description' => null,
            'source' => 'page',
        ]);

    actingAs($this->admin)
        ->postJson(route('administrators.online-resources.links.metadata'), [
            'url' => 'javascript:alert(1)',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});
