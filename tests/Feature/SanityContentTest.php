<?php

declare(strict_types=1);

use App\Models\SanityContent;
use App\Models\User;
use App\Services\SanityService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function () {
    // Create an admin user for testing
    $this->admin = User::factory()->create([
        'role' => App\Enums\UserRole::SuperAdmin,
    ]);
});

it('can list sanity contents', function () {
    actingAs($this->admin);

    SanityContent::factory()->count(3)->create();

    get(route('administrators.sanity-content.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/sanity-content/index')
            ->has('contents.data', 3)
        );
});

it('can show create form', function () {
    actingAs($this->admin);

    get(route('administrators.sanity-content.create'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/sanity-content/create')
        );
});

it('can create sanity content', function () {
    actingAs($this->admin);

    $data = [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'excerpt' => 'This is a test excerpt for our post.',
        'post_kind' => 'news',
        'content' => 'This is the full content of the post.',
        'status' => 'draft',
        'published_at' => now()->toDateTimeString(),
    ];

    post(route('administrators.sanity-content.store'), $data)
        ->assertRedirect(route('administrators.sanity-content.index'));

    assertDatabaseHas('sanity_contents', [
        'title' => 'Test Post',
        'slug' => 'test-post',
        'post_kind' => 'news',
        'status' => 'draft',
    ]);
});

it('validates required fields when creating content', function () {
    actingAs($this->admin);

    post(route('administrators.sanity-content.store'), [])
        ->assertSessionHasErrors(['title', 'slug', 'post_kind', 'status']);
});

it('can update sanity content', function () {
    actingAs($this->admin);

    $content = SanityContent::factory()->create([
        'title' => 'Original Title',
        'status' => 'draft',
        'published_at' => now(),
    ]);

    $updateData = [
        'title' => 'Updated Title',
        'slug' => $content->slug,
        'excerpt' => $content->excerpt,
        'post_kind' => $content->post_kind,
        'status' => 'published',
        'published_at' => $content->published_at->toDateTimeString(),
    ];

    put(route('administrators.sanity-content.update', $content), $updateData)
        ->assertRedirect(route('administrators.sanity-content.index'));

    assertDatabaseHas('sanity_contents', [
        'id' => $content->id,
        'title' => 'Updated Title',
        'status' => 'published',
    ]);
});

it('can delete sanity content', function () {
    actingAs($this->admin);

    $content = SanityContent::factory()->create();

    delete(route('administrators.sanity-content.destroy', $content))
        ->assertRedirect(route('administrators.sanity-content.index'));

    expect(SanityContent::find($content->id))->toBeNull();
});

it('filters content by post kind', function () {
    actingAs($this->admin);

    SanityContent::factory()->create(['post_kind' => 'news']);
    SanityContent::factory()->create(['post_kind' => 'story']);
    SanityContent::factory()->create(['post_kind' => 'alert']);

    get(route('administrators.sanity-content.index', ['post_kind' => 'news']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('contents.data', 1)
            ->where('filters.post_kind', 'news')
        );
});

it('filters content by status', function () {
    actingAs($this->admin);

    SanityContent::factory()->create(['status' => 'draft']);
    SanityContent::factory()->create(['status' => 'published']);
    SanityContent::factory()->create(['status' => 'published']);

    get(route('administrators.sanity-content.index', ['status' => 'published']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('contents.data', 2)
            ->where('filters.status', 'published')
        );
});

it('searches content by title', function () {
    actingAs($this->admin);

    SanityContent::factory()->create(['title' => 'Laravel Testing Guide']);
    SanityContent::factory()->create(['title' => 'React Component Tutorial']);
    SanityContent::factory()->create(['title' => 'Laravel Performance Tips']);

    get(route('administrators.sanity-content.index', ['search' => 'Laravel']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('contents.data', 2)
            ->where('filters.search', 'Laravel')
        );
});

it('sanity service handles missing configuration gracefully', function () {
    // Temporarily clear config
    config(['services.sanity.project_id' => null]);
    config(['services.sanity.token' => null]);

    $service = new SanityService;

    expect($service->isConfigured())->toBeFalse();
    expect($service->getAllDocuments())->toBeEmpty();
});

it('can check if content is alert', function () {
    $alert = SanityContent::factory()->create(['post_kind' => 'alert']);
    $news = SanityContent::factory()->create(['post_kind' => 'news']);

    expect($alert->isAlert())->toBeTrue();
    expect($news->isAlert())->toBeFalse();
});

it('can check if content is news or story', function () {
    $news = SanityContent::factory()->create(['post_kind' => 'news']);
    $story = SanityContent::factory()->create(['post_kind' => 'story']);
    $alert = SanityContent::factory()->create(['post_kind' => 'alert']);

    expect($news->isNewsOrStory())->toBeTrue();
    expect($story->isNewsOrStory())->toBeTrue();
    expect($alert->isNewsOrStory())->toBeFalse();
});
