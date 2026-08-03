<?php

declare(strict_types=1);

use App\Data\NewsletterContact;
use App\Enums\NewsletterProvider;
use App\Enums\NewsletterRemoteStatus;
use App\Enums\NewsletterSubscribeResult;
use App\Services\Newsletter\NewsletterProviderManager;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'newsletter.providers.sequenzy.url' => 'https://sequenzy.test/api/v1',
        'newsletter.providers.brevo.url' => 'https://brevo.test/v3',
        'newsletter.providers.mailchimp.url' => 'https://{server}.mailchimp.test/3.0',
    ]);
    Http::preventStrayRequests();
});

function newsletterContactForProviderTest(): NewsletterContact
{
    return new NewsletterContact(
        email: 'student@example.test',
        externalId: 'user_7',
        role: 'student',
        firstName: 'Ada',
        lastName: 'Lovelace',
        tags: ['portal', 'student'],
        attributes: ['role' => 'student', 'source' => 'portal_prompt'],
    );
}

it('reports incomplete provider configurations without making requests', function (NewsletterProvider $provider, array $configuration): void {
    $adapter = app(NewsletterProviderManager::class)->make($provider, $configuration);

    expect($adapter->isConfigured())->toBeFalse()
        ->and($adapter->testConnection())->toBeFalse()
        ->and($adapter->subscribe(newsletterContactForProviderTest()))->toBe(NewsletterSubscribeResult::NotConfigured);
})->with([
    'sequenzy' => [NewsletterProvider::Sequenzy, ['api_key' => '']],
    'brevo' => [NewsletterProvider::Brevo, ['api_key' => 'key', 'list_id' => '']],
    'mailchimp' => [NewsletterProvider::Mailchimp, ['api_key' => 'key', 'server_prefix' => '', 'audience_id' => 'audience']],
]);

it('maps Sequenzy lookup states and validates its API key header', function (): void {
    Http::fake([
        'https://sequenzy.test/api/v1/subscribers/subscribed%40example.test' => Http::response(['subscriber' => ['status' => 'active']]),
        'https://sequenzy.test/api/v1/subscribers/opted%40example.test' => Http::response(['subscriber' => ['status' => 'unsubscribed']]),
        'https://sequenzy.test/api/v1/subscribers/missing%40example.test' => Http::response([], 404),
    ]);
    $adapter = app(NewsletterProviderManager::class)->make(NewsletterProvider::Sequenzy, ['api_key' => 'seq-key']);

    expect($adapter->status('subscribed@example.test'))->toBe(NewsletterRemoteStatus::Subscribed)
        ->and($adapter->status('opted@example.test'))->toBe(NewsletterRemoteStatus::OptedOut)
        ->and($adapter->status('missing@example.test'))->toBe(NewsletterRemoteStatus::Missing);
    Http::assertSent(fn ($request): bool => $request->hasHeader('X-API-Key', 'seq-key'));
});

it('maps Brevo contacts by blacklist and configured list membership', function (): void {
    Http::fake([
        'https://brevo.test/v3/contacts/subscribed%40example.test' => Http::response(['emailBlacklisted' => false, 'listIds' => [42]]),
        'https://brevo.test/v3/contacts/opted%40example.test' => Http::response(['emailBlacklisted' => true, 'listIds' => [42]]),
        'https://brevo.test/v3/contacts/missing%40example.test' => Http::response(['emailBlacklisted' => false, 'listIds' => [9]]),
        'https://brevo.test/v3/contacts/lists/42' => Http::response(['id' => 42]),
    ]);
    $adapter = app(NewsletterProviderManager::class)->make(NewsletterProvider::Brevo, ['api_key' => 'brevo-key', 'list_id' => 42]);

    expect($adapter->testConnection())->toBeTrue()
        ->and($adapter->status('subscribed@example.test'))->toBe(NewsletterRemoteStatus::Subscribed)
        ->and($adapter->status('opted@example.test'))->toBe(NewsletterRemoteStatus::OptedOut)
        ->and($adapter->status('missing@example.test'))->toBe(NewsletterRemoteStatus::Missing);
    Http::assertSent(fn ($request): bool => $request->hasHeader('api-key', 'brevo-key'));
});

it('uses Mailchimp list member statuses and stable member hashes', function (): void {
    $subscribedHash = md5('subscribed@example.test');
    $optedHash = md5('opted@example.test');
    Http::fake([
        'https://us21.mailchimp.test/3.0/lists/audience/members/'.$subscribedHash => Http::response(['status' => 'subscribed']),
        'https://us21.mailchimp.test/3.0/lists/audience/members/'.$optedHash => Http::response(['status' => 'unsubscribed']),
        'https://us21.mailchimp.test/3.0/lists/audience/members/*' => Http::response([], 404),
        'https://us21.mailchimp.test/3.0/lists/audience' => Http::response(['id' => 'audience']),
    ]);
    $adapter = app(NewsletterProviderManager::class)->make(NewsletterProvider::Mailchimp, [
        'api_key' => 'mailchimp-key',
        'server_prefix' => 'us21',
        'audience_id' => 'audience',
    ]);

    expect($adapter->testConnection())->toBeTrue()
        ->and($adapter->status('subscribed@example.test'))->toBe(NewsletterRemoteStatus::Subscribed)
        ->and($adapter->status('opted@example.test'))->toBe(NewsletterRemoteStatus::OptedOut)
        ->and($adapter->status('missing@example.test'))->toBe(NewsletterRemoteStatus::Missing);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization'));
});

it('returns unavailable for provider authentication and server failures', function (NewsletterProvider $provider, array $configuration, string $url): void {
    Http::fake([$url => Http::response([], 401)]);
    $adapter = app(NewsletterProviderManager::class)->make($provider, $configuration);

    expect($adapter->status('student@example.test'))->toBe(NewsletterRemoteStatus::Unavailable);
})->with([
    'sequenzy' => [NewsletterProvider::Sequenzy, ['api_key' => 'bad'], 'https://sequenzy.test/api/v1/subscribers/*'],
    'brevo' => [NewsletterProvider::Brevo, ['api_key' => 'bad', 'list_id' => 42], 'https://brevo.test/v3/contacts/*'],
    'mailchimp' => [NewsletterProvider::Mailchimp, ['api_key' => 'bad', 'server_prefix' => 'us1', 'audience_id' => 'aud'], 'https://us1.mailchimp.test/3.0/lists/aud/members/*'],
]);
