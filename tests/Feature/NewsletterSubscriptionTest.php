<?php

declare(strict_types=1);

use App\Enums\NewsletterProvider;
use App\Enums\NewsletterSubscriptionStatus;
use App\Enums\UserRole;
use App\Models\NewsletterSubscription;
use App\Models\User;
use App\Services\Newsletter\NewsletterSettingsService;
use App\Services\Newsletter\NewsletterSubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Cache::flush();
    config([
        'newsletter.providers.sequenzy.url' => 'https://sequenzy.test/api/v1',
        'newsletter.providers.brevo.url' => 'https://brevo.test/v3',
        'newsletter.providers.mailchimp.url' => 'https://{server}.mailchimp.test/3.0',
    ]);
});

function configureNewsletterForTest(NewsletterProvider $provider, array $configuration, bool $enabled = true): void
{
    $settings = app(NewsletterSettingsService::class)->get();
    $settings['enabled'] = $enabled;
    $settings['provider'] = $provider->value;
    $settings['providers'][$provider->value] = $configuration;
    app(NewsletterSettingsService::class)->save($settings);
}

it('subscribes through Sequenzy and records the provider used', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    Http::preventStrayRequests();
    Http::fake([
        'https://sequenzy.test/api/v1/subscribers' => Http::response([
            'success' => true,
            'subscriber' => ['created' => true, 'skipped' => false],
        ]),
    ]);
    $user = User::factory()->create([
        'role' => UserRole::Student,
        'name' => 'Louis Mariano',
        'email' => 'louis@example.test',
    ]);

    actingAs($user)->post(route('newsletter.subscribe'))
        ->assertRedirect()
        ->assertSessionHas('newsletter_feedback.type', 'success');

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-API-Key', 'seq-key')
        && $request['externalId'] === 'user_'.$user->id
        && $request['customAttributes']['role'] === 'student');

    $subscription = NewsletterSubscription::query()->where('user_id', $user->id)->sole();
    expect($subscription->status)->toBe(NewsletterSubscriptionStatus::Subscribed)
        ->and($subscription->provider)->toBe(NewsletterProvider::Sequenzy);
});

it('subscribes through Brevo with list mapping and provider authentication', function (): void {
    configureNewsletterForTest(NewsletterProvider::Brevo, ['api_key' => 'brevo-key', 'list_id' => '42']);
    Http::preventStrayRequests();
    Http::fake(['https://brevo.test/v3/contacts' => Http::response(['id' => 123], 201)]);
    $user = User::factory()->create(['role' => UserRole::Professor, 'email' => 'faculty@example.test']);

    actingAs($user)->post(route('newsletter.subscribe'))->assertSessionHas('newsletter_feedback.type', 'success');

    Http::assertSent(fn ($request): bool => $request->hasHeader('api-key', 'brevo-key')
        && $request['email'] === 'faculty@example.test'
        && $request['listIds'] === [42]
        && $request['updateEnabled'] === true);
    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->provider)->toBe(NewsletterProvider::Brevo);
});

it('subscribes through the stable Mailchimp audience member endpoint', function (): void {
    configureNewsletterForTest(NewsletterProvider::Mailchimp, [
        'api_key' => 'mailchimp-key',
        'server_prefix' => 'us21',
        'audience_id' => 'audience-7',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://us21.mailchimp.test/3.0/lists/audience-7/members/*' => Http::response(['status' => 'subscribed'])]);
    $user = User::factory()->create(['role' => UserRole::Instructor, 'email' => 'CaseSensitive@example.test']);

    actingAs($user)->post(route('newsletter.subscribe'))->assertSessionHas('newsletter_feedback.type', 'success');

    $hash = md5('casesensitive@example.test');
    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === "https://us21.mailchimp.test/3.0/lists/audience-7/members/{$hash}"
        && $request->hasHeader('Authorization')
        && $request['status_if_new'] === 'subscribed');
    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->provider)->toBe(NewsletterProvider::Mailchimp);
});

it('normalizes a provider duplicate as a successful local subscription', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    Http::fake(['https://sequenzy.test/api/v1/subscribers' => Http::response(['error' => 'duplicate'], 409)]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    actingAs($user)->post(route('newsletter.subscribe'))
        ->assertSessionHas('newsletter_feedback.type', 'success');

    expect(NewsletterSubscription::query()->where('user_id', $user->id)->sole()->status)
        ->toBe(NewsletterSubscriptionStatus::Subscribed);
});

it('suppresses the prompt and records a remote Brevo opt-out', function (): void {
    configureNewsletterForTest(NewsletterProvider::Brevo, ['api_key' => 'brevo-key', 'list_id' => '42']);
    Http::fake(['https://brevo.test/v3/contacts/*' => Http::response(['emailBlacklisted' => true, 'listIds' => [42]])]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(NewsletterSubscriptionService::class)->shouldPromptUser($user))->toBeFalse();

    $subscription = NewsletterSubscription::query()->where('user_id', $user->id)->sole();
    expect($subscription->status)->toBe(NewsletterSubscriptionStatus::Declined)
        ->and($subscription->provider)->toBe(NewsletterProvider::Brevo);
});

it('keeps the consent prompt eligible without recording success when the provider lookup is unavailable', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    Http::fake(['https://sequenzy.test/api/v1/subscribers/*' => Http::response([], 503)]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(NewsletterSubscriptionService::class)->shouldPromptUser($user))->toBeTrue()
        ->and(NewsletterSubscription::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('shares an enabled prompt with the student portal when provider lookup is temporarily unavailable', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    Http::fake(['https://sequenzy.test/api/v1/subscribers/*' => Http::response([], 503)]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    actingAs($user)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('newsletter.enabled', true)
            ->where('newsletter.shouldPrompt', true));

    expect(NewsletterSubscription::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('never prompts when newsletter marketing is disabled', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key'], false);
    Http::preventStrayRequests();
    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(NewsletterSubscriptionService::class)->shouldPromptUser($user))->toBeFalse();
});

it('isolates remote lookup caches when the provider changes', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    Http::fake([
        'https://sequenzy.test/api/v1/subscribers/*' => Http::response([], 404),
        'https://brevo.test/v3/contacts/*' => Http::response([], 404),
    ]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    expect(app(NewsletterSubscriptionService::class)->shouldPromptUser($user))->toBeTrue();
    configureNewsletterForTest(NewsletterProvider::Brevo, ['api_key' => 'brevo-key', 'list_id' => '42']);
    expect(app(NewsletterSubscriptionService::class)->shouldPromptUser($user))->toBeTrue();

    Http::assertSentCount(2);
});

it('records a permanent decline with the active provider', function (): void {
    configureNewsletterForTest(NewsletterProvider::Mailchimp, [
        'api_key' => 'key',
        'server_prefix' => 'us1',
        'audience_id' => 'audience',
    ]);
    $user = User::factory()->create(['role' => UserRole::Student]);

    actingAs($user)->post(route('newsletter.decline'))->assertRedirect();

    $subscription = NewsletterSubscription::query()->where('user_id', $user->id)->sole();
    expect($subscription->status)->toBe(NewsletterSubscriptionStatus::Declined)
        ->and($subscription->provider)->toBe(NewsletterProvider::Mailchimp)
        ->and($subscription->subscribed_at)->toBeNull();
});

it('forbids roles outside student and faculty portals', function (): void {
    configureNewsletterForTest(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)->post(route('newsletter.subscribe'))->assertForbidden();
    actingAs($user)->post(route('newsletter.decline'))->assertForbidden();
});
