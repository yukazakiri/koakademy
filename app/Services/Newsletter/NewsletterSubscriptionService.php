<?php

declare(strict_types=1);

namespace App\Services\Newsletter;

use App\Enums\NewsletterProvider;
use App\Enums\NewsletterRemoteStatus;
use App\Enums\NewsletterSubscribeResult;
use App\Enums\NewsletterSubscriptionStatus;
use App\Models\NewsletterSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class NewsletterSubscriptionService
{
    private const int LOOKUP_CACHE_TTL_HOURS = 12;

    public function __construct(
        private readonly NewsletterSettingsService $settings,
        private readonly NewsletterProviderManager $providers,
        private readonly NewsletterContactFactory $contacts,
    ) {}

    public function isEnabled(): bool
    {
        $settings = $this->settings->get();

        return (bool) $settings['enabled'] && $this->providers->forSettings($settings)->isConfigured();
    }

    public function shouldPromptUser(User $user): bool
    {
        if (! $this->supports($user) || ! $this->isEnabled()) {
            return false;
        }

        if (NewsletterSubscription::query()->where('user_id', $user->id)->exists()) {
            return false;
        }

        $settings = $this->settings->get();
        $provider = $this->providers->forSettings($settings);
        $cacheKey = $this->cacheKey($user, $settings);
        $cached = Cache::get($cacheKey);
        $status = is_string($cached)
            ? NewsletterRemoteStatus::tryFrom($cached)
            : null;

        if (! $status instanceof NewsletterRemoteStatus) {
            $status = $provider->status((string) $user->email);
            if ($status !== NewsletterRemoteStatus::Unavailable) {
                Cache::put($cacheKey, $status->value, now()->addHours(self::LOOKUP_CACHE_TTL_HOURS));
            }
        }

        if ($status === NewsletterRemoteStatus::Subscribed || $status === NewsletterRemoteStatus::OptedOut) {
            $this->recordRemoteResponse($user, $provider->name(), $status);

            return false;
        }

        // An unavailable lookup must not silently disable the consent prompt.
        // The subscribe action still requires a successful provider response
        // before anything is recorded locally, so prompting here is safe.
        return in_array($status, [
            NewsletterRemoteStatus::Missing,
            NewsletterRemoteStatus::Unavailable,
        ], true);
    }

    public function subscribe(User $user): NewsletterSubscribeResult
    {
        if (! $this->supports($user)) {
            return NewsletterSubscribeResult::Failed;
        }

        $settings = $this->settings->get();
        $provider = $this->providers->forSettings($settings);
        if (! (bool) $settings['enabled'] || ! $provider->isConfigured()) {
            return NewsletterSubscribeResult::NotConfigured;
        }

        $result = $provider->subscribe($this->contacts->forUser($user));
        if (! $result->succeeded()) {
            return $result;
        }

        NewsletterSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'provider' => $provider->name(),
                'status' => NewsletterSubscriptionStatus::Subscribed,
                'subscribed_at' => now(),
                'declined_at' => null,
            ],
        );
        Cache::forget($this->cacheKey($user, $settings));

        return $result;
    }

    public function decline(User $user): void
    {
        $settings = $this->settings->get();
        $provider = NewsletterProvider::tryFrom((string) ($settings['provider'] ?? ''));

        NewsletterSubscription::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'provider' => $provider,
                'status' => NewsletterSubscriptionStatus::Declined,
                'subscribed_at' => null,
                'declined_at' => now(),
            ],
        );
    }

    public function supports(User $user): bool
    {
        return $user->isStudentRole() || $user->isFaculty();
    }

    /** @param array<string, mixed> $settings */
    private function cacheKey(User $user, array $settings): string
    {
        $provider = (string) ($settings['provider'] ?? 'unknown');
        $destination = $settings['providers'][$provider] ?? [];
        $identity = hash('sha256', mb_strtolower((string) $user->email).'|'.json_encode($destination));

        return 'newsletter:lookup:'.$this->settings->lookupCacheVersion().':'.$provider.':'.$user->id.':'.$identity;
    }

    private function recordRemoteResponse(User $user, NewsletterProvider $provider, NewsletterRemoteStatus $status): void
    {
        $subscribed = $status === NewsletterRemoteStatus::Subscribed;

        NewsletterSubscription::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'provider' => $provider,
                'status' => $subscribed ? NewsletterSubscriptionStatus::Subscribed : NewsletterSubscriptionStatus::Declined,
                'subscribed_at' => $subscribed ? now() : null,
                'declined_at' => $subscribed ? null : now(),
            ],
        );
    }
}
