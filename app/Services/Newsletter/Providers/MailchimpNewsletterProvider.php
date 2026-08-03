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

final class MailchimpNewsletterProvider extends AbstractNewsletterProvider implements NewsletterProviderContract
{
    public function name(): NewsletterProvider
    {
        return NewsletterProvider::Mailchimp;
    }

    public function isConfigured(): bool
    {
        return filled($this->configuration['api_key'] ?? null)
            && preg_match('/^[a-z0-9-]+$/i', (string) ($this->configuration['server_prefix'] ?? '')) === 1
            && filled($this->configuration['audience_id'] ?? null);
    }

    public function testConnection(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            return $this->request()->get('lists/'.rawurlencode($this->audienceId()))->successful();
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
            $response = $this->request()->get($this->memberEndpoint($email));
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

        return mb_strtolower((string) $response->json('status')) === 'subscribed'
            ? NewsletterRemoteStatus::Subscribed
            : NewsletterRemoteStatus::OptedOut;
    }

    public function subscribe(NewsletterContact $contact): NewsletterSubscribeResult
    {
        if (! $this->isConfigured()) {
            return NewsletterSubscribeResult::NotConfigured;
        }

        try {
            $response = $this->request()->put($this->memberEndpoint($contact->email), [
                'email_address' => $contact->email,
                'status_if_new' => 'subscribed',
                'status' => 'subscribed',
                'merge_fields' => array_filter([
                    'FNAME' => $contact->firstName,
                    'LNAME' => $contact->lastName,
                ], fn (mixed $value): bool => filled($value)),
                'tags' => $contact->tags,
            ]);
        } catch (ConnectionException $exception) {
            $this->logUnavailable($exception);

            return NewsletterSubscribeResult::Failed;
        }

        if (! $response->successful()) {
            return NewsletterSubscribeResult::Failed;
        }

        return NewsletterSubscribeResult::Created;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = str_replace('{server}', (string) ($this->configuration['server_prefix'] ?? ''), (string) config('newsletter.providers.mailchimp.url'));

        return $this->client($baseUrl)->withBasicAuth('koakademy', (string) ($this->configuration['api_key'] ?? ''));
    }

    private function memberEndpoint(string $email): string
    {
        return 'lists/'.rawurlencode($this->audienceId()).'/members/'.md5(mb_strtolower(mb_trim($email)));
    }

    private function audienceId(): string
    {
        return (string) ($this->configuration['audience_id'] ?? '');
    }

    private function logUnavailable(ConnectionException $exception): void
    {
        Log::warning('Newsletter provider request failed.', [
            'provider' => $this->name()->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
