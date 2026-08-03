<?php

declare(strict_types=1);

namespace App\Services\Newsletter;

use App\Contracts\NewsletterProvider as NewsletterProviderContract;
use App\Enums\NewsletterProvider;
use App\Services\Newsletter\Providers\BrevoNewsletterProvider;
use App\Services\Newsletter\Providers\MailchimpNewsletterProvider;
use App\Services\Newsletter\Providers\SequenzyNewsletterProvider;

final class NewsletterProviderManager
{
    /** @param array<string, mixed> $settings */
    public function forSettings(array $settings): NewsletterProviderContract
    {
        $provider = NewsletterProvider::tryFrom((string) ($settings['provider'] ?? '')) ?? NewsletterProvider::Sequenzy;
        $configuration = $settings['providers'][$provider->value] ?? [];

        return $this->make($provider, is_array($configuration) ? $configuration : []);
    }

    /** @param array<string, mixed> $configuration */
    public function make(NewsletterProvider $provider, array $configuration): NewsletterProviderContract
    {
        return match ($provider) {
            NewsletterProvider::Sequenzy => new SequenzyNewsletterProvider($configuration),
            NewsletterProvider::Brevo => new BrevoNewsletterProvider($configuration),
            NewsletterProvider::Mailchimp => new MailchimpNewsletterProvider($configuration),
        };
    }
}
