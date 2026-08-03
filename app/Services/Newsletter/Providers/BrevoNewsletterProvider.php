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

final class BrevoNewsletterProvider extends AbstractNewsletterProvider implements NewsletterProviderContract
{
    public function name(): NewsletterProvider
    {
        return NewsletterProvider::Brevo;
    }

    public function isConfigured(): bool
    {
        return filled($this->configuration['api_key'] ?? null)
            && filter_var($this->configuration['list_id'] ?? null, FILTER_VALIDATE_INT) !== false;
    }

    public function testConnection(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            return $this->request()->get('contacts/lists/'.$this->listId())->successful();
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return false;
        }
    }

    public function status(string $email): NewsletterRemoteStatus
    {
        if (! $this->isConfigured() || blank($email)) {
            return NewsletterRemoteStatus::Unavailable;
        }

        try {
            $response = $this->request()->get('contacts/'.rawurlencode($email));
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return NewsletterRemoteStatus::Unavailable;
        }

        if ($response->notFound()) {
            return NewsletterRemoteStatus::Missing;
        }
        if (! $response->successful()) {
            return NewsletterRemoteStatus::Unavailable;
        }
        if ((bool) $response->json('emailBlacklisted', false)) {
            return NewsletterRemoteStatus::OptedOut;
        }

        $listIds = array_map('intval', $response->json('listIds', []));

        return in_array($this->listId(), $listIds, true)
            ? NewsletterRemoteStatus::Subscribed
            : NewsletterRemoteStatus::Missing;
    }

    public function subscribe(NewsletterContact $contact): NewsletterSubscribeResult
    {
        if (! $this->isConfigured()) {
            return NewsletterSubscribeResult::NotConfigured;
        }

        try {
            $response = $this->request()->post('contacts', [
                'email' => $contact->email,
                'attributes' => array_filter([
                    'FIRSTNAME' => $contact->firstName,
                    'LASTNAME' => $contact->lastName,
                    'ROLE' => $contact->role,
                    'SOURCE' => 'portal_prompt',
                ], fn (mixed $value): bool => filled($value)),
                'listIds' => [$this->listId()],
                'updateEnabled' => true,
            ]);
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return NewsletterSubscribeResult::Failed;
        }

        if (in_array($response->status(), [201, 204], true)) {
            return $response->status() === 204
                ? NewsletterSubscribeResult::AlreadySubscribed
                : NewsletterSubscribeResult::Created;
        }

        return NewsletterSubscribeResult::Failed;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->client((string) config('newsletter.providers.brevo.url'))
            ->withHeaders(['api-key' => (string) ($this->configuration['api_key'] ?? '')]);
    }

    private function listId(): int
    {
        return (int) ($this->configuration['list_id'] ?? 0);
    }

    private function logUnavailable(ConnectionException $exception): void
    {
        Log::warning('Newsletter provider request failed.', [
            'provider' => $this->name()->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
