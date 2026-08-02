<?php

declare(strict_types=1);

use App\Enums\NewsletterSubscriptionStatus;
use App\Enums\UserRole;
use App\Models\NewsletterSubscription;
use App\Models\User;
use App\Services\SequenzySubscriberService;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config()->set('services.sequenzy.key', 'seq_test_key');
    config()->set('services.sequenzy.url', 'https://api.sequenzy.com/api/v1');
});

it('creates a subscriber on Sequenzy and records the subscription locally', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers' => Http::response([
            'success' => true,
            'subscriber' => ['created' => true, 'skipped' => false, 'updated' => false],
        ], 200),
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'name' => 'Louis Mariano',
        'email' => 'marianolouis18@gmail.com',
    ]);

    actingAs($user)
        ->post(route('newsletter.subscribe'))
        ->assertRedirect()
        ->assertSessionHas('newsletter_feedback.type', 'success');

    Http::assertSent(function ($request) use ($user): bool {
        return $request->method() === 'POST'
            && $request->url() === 'https://api.sequenzy.com/api/v1/subscribers'
            && $request->hasHeader('X-API-Key', 'seq_test_key')
            && $request['email'] === $user->email
            && $request['firstName'] === 'Louis'
            && $request['lastName'] === 'Mariano'
            && $request['externalId'] === 'user_'.$user->id
            && $request['enrollInSequences'] === true
            && $request['duplicateStrategy'] === 'skip'
            && in_array('portal', $request['tags'], true)
            && in_array('student', $request['tags'], true)
            && $request['customAttributes']['role'] === 'student'
            && $request['customAttributes']['source'] === 'portal_prompt';
    });

    $subscription = NewsletterSubscription::query()->where('user_id', $user->id)->sole();

    expect($subscription->status)->toBe(NewsletterSubscriptionStatus::Subscribed)
        ->and($subscription->subscribed_at)->not->toBeNull();
});

it('treats a 409 conflict as an existing subscriber', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers' => Http::response([
            'success' => false,
            'error' => 'Subscriber identity conflict: email and externalId point to different subscribers.',
        ], 409),
    ]);

    $user = User::factory()->create(['role' => UserRole::Professor]);

    actingAs($user)
        ->post(route('newsletter.subscribe'))
        ->assertRedirect()
        ->assertSessionHas('newsletter_feedback.type', 'success');

    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->status)
        ->toBe(NewsletterSubscriptionStatus::Subscribed);
});

it('treats a skipped duplicate response as an existing subscriber', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers' => Http::response([
            'success' => true,
            'subscriber' => ['created' => false, 'skipped' => true, 'updated' => false],
        ], 200),
    ]);

    $user = User::factory()->create(['role' => UserRole::Instructor]);

    actingAs($user)
        ->post(route('newsletter.subscribe'))
        ->assertRedirect()
        ->assertSessionHas('newsletter_feedback.type', 'success');

    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->status)
        ->toBe(NewsletterSubscriptionStatus::Subscribed);
});

it('reports an error and records nothing when the Sequenzy API fails', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers' => Http::response([
            'success' => false,
            'error' => 'Internal server error',
        ], 500),
    ]);

    $user = User::factory()->create(['role' => UserRole::Student]);

    actingAs($user)
        ->post(route('newsletter.subscribe'))
        ->assertRedirect()
        ->assertSessionHas('newsletter_feedback.type', 'error');

    expect(NewsletterSubscription::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('does not prompt users who already exist as Sequenzy subscribers', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers/*' => Http::response([
            'success' => true,
            'subscriber' => ['email' => 'marianolouis18@gmail.com', 'status' => 'active'],
        ], 200),
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'marianolouis18@gmail.com',
    ]);

    expect(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeFalse();

    // Persisted locally so no further API lookups are needed.
    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->status)
        ->toBe(NewsletterSubscriptionStatus::Subscribed);

    Http::assertSentCount(1);
});

it('prompts users who are not Sequenzy subscribers yet', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers/*' => Http::response([
            'success' => false,
            'error' => 'Subscriber not found',
        ], 404),
    ]);

    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeTrue()
        ->and(NewsletterSubscription::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('caches the remote lookup so the API is not queried on every request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.sequenzy.com/api/v1/subscribers/*' => Http::response([
            'success' => false,
            'error' => 'Subscriber not found',
        ], 404),
    ]);

    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeTrue()
        ->and(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeTrue();

    Http::assertSentCount(1);
});

it('does not prompt users who already responded to the prompt', function (): void {
    Http::preventStrayRequests();

    $user = User::factory()->create(['role' => UserRole::Student]);

    NewsletterSubscription::query()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'status' => NewsletterSubscriptionStatus::Declined,
        'declined_at' => now(),
    ]);

    expect(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeFalse();
});

it('does not prompt users without a configured Sequenzy API key', function (): void {
    config()->set('services.sequenzy.key', null);
    config()->set('services.sequenzy.legacy_key', null);

    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(SequenzySubscriberService::class)->shouldPromptUser($user))->toBeFalse();
});

it('records a decline so the prompt is hidden permanently', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);

    actingAs($user)
        ->post(route('newsletter.decline'))
        ->assertRedirect();

    $subscription = NewsletterSubscription::query()->where('user_id', $user->id)->sole();

    expect($subscription->status)->toBe(NewsletterSubscriptionStatus::Declined)
        ->and($subscription->declined_at)->not->toBeNull();
});

it('forbids users outside the student and faculty portals', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)->post(route('newsletter.subscribe'))->assertForbidden();
    actingAs($user)->post(route('newsletter.decline'))->assertForbidden();
});
