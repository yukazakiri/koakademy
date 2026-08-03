<?php

declare(strict_types=1);

namespace App\Services\Newsletter\Providers;

use App\Contracts\NewsletterProvider as NewsletterProviderContract;
use App\Data\NewsletterContact;
use App\Enums\NewsletterProvider;
use App\Enums\NewsletterRemoteStatus;
use App\Enums\NewsletterSubscribeResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

final class SequenzyNewsletterProvider extends AbstractNewsletterProvider implements NewsletterProviderContract
{
    public function name(): NewsletterProvider
    {
        return NewsletterProvider::Sequenzy;
    }

    public function isConfigured(): bool
    {
        return filled($this->configuration['api_key'] ?? null);
    }

    public function testConnection(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->status('connection-test@koakademy.invalid') !== NewsletterRemoteStatus::Unavailable;
    }

    public function status(string $email): NewsletterRemoteStatus
    {
        if (! $this->isConfigured() || blank($email)) {
            return NewsletterRemoteStatus::Unavailable;
        }

        try {
            $response = $this->request()->get('subscribers/'.rawurlencode($email));
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return NewsletterRemoteStatus::Unavailable;
        }

        return match ($response->status()) {
            200 => in_array(mb_strtolower((string) $response->json('subscriber.status')), ['unsubscribed', 'opted_out'], true)
                ? NewsletterRemoteStatus::OptedOut
                : NewsletterRemoteStatus::Subscribed,
            404 => NewsletterRemoteStatus::Missing,
            default => NewsletterRemoteStatus::Unavailable,
        };
    }

    public function subscribe(NewsletterContact $contact): NewsletterSubscribeResult
    {
        if (! $this->isConfigured()) {
            return NewsletterSubscribeResult::NotConfigured;
        }

        $payload = [
            'email' => $contact->email,
            'externalId' => $contact->externalId,
            'tags' => $contact->tags,
            'customAttributes' => $contact->attributes,
            'enrollInSequences' => true,
            'duplicateStrategy' => 'skip',
        ];
        if (filled($contact->firstName)) {
            $payload['firstName'] = $contact->firstName;
        }
        if (filled($contact->lastName)) {
            $payload['lastName'] = $contact->lastName;
        }

        try {
            $response = $this->request()->post('subscribers', $payload);
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return NewsletterSubscribeResult::Failed;
        }

        if ($response->status() === 409) {
            return NewsletterSubscribeResult::AlreadySubscribed;
        }
        if ($response->successful() && $response->json('success') === true) {
            return ((bool) $response->json('subscriber.skipped', false) || ! (bool) $response->json('subscriber.created', false))
                ? NewsletterSubscribeResult::AlreadySubscribed
                : NewsletterSubscribeResult::Created;
        }

        return NewsletterSubscribeResult::Failed;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = (string) ($this->configuration['api_key'] ?? '');

        return $this->client((string) config('newsletter.providers.sequenzy.url'))
            ->withHeaders(['X-API-Key' => $apiKey])
            ->withToken($apiKey);
    }

    private function logUnavailable(ConnectionException $exception): void
    {
        Log::warning('Newsletter provider request failed.', [
            'provider' => $this->name()->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
