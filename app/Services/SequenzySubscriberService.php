<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NewsletterSubscriptionStatus;
use App\Enums\SequenzySubscribeResult;
use App\Mail\SequenzyApiKeyResolver;
use App\Models\Faculty;
use App\Models\NewsletterSubscription;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates and looks up Sequenzy subscribers for portal users and decides who
 * should be asked to join the school newsletter.
 */
final class SequenzySubscriberService
{
    private const string LOOKUP_CACHE_PREFIX = 'newsletter:sequenzy-lookup:';

    private const int LOOKUP_CACHE_TTL_HOURS = 12;

    private ?bool $isConfigured = null;

    public function __construct(
        private readonly SequenzyApiKeyResolver $apiKeyResolver,
    ) {}

    /**
     * Whether a Sequenzy API key is available for outbound calls.
     */
    public function isConfigured(): bool
    {
        return $this->isConfigured ??= filled($this->apiKeyResolver->resolve());
    }

    /**
     * Determine whether the portal should ask this user to join the newsletter.
     *
     * Returns false when the user already responded (subscribed/declined) or
     * when their email already exists as a Sequenzy subscriber — in which case
     * the answer is persisted locally so the API is never queried again.
     */
    public function shouldPromptUser(User $user): bool
    {
        if (! $user->isStudentRole() && ! $user->isFaculty()) {
            return false;
        }

        if (! $this->isConfigured()) {
            return false;
        }

        $hasResponded = NewsletterSubscription::query()
            ->where('user_id', $user->id)
            ->exists();

        if ($hasResponded) {
            return false;
        }

        return ! $this->syncExistingSubscriber($user);
    }

    /**
     * Check Sequenzy for an existing subscriber record for this user and, when
     * found, persist a local "subscribed" marker so the prompt stays hidden.
     *
     * @return bool true when the email already exists on Sequenzy
     */
    public function syncExistingSubscriber(User $user): bool
    {
        $exists = $this->remoteSubscriberExists($user);

        if ($exists !== true) {
            return false;
        }

        NewsletterSubscription::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email' => (string) $user->email,
                'status' => NewsletterSubscriptionStatus::Subscribed,
                'subscribed_at' => now(),
            ],
        );

        return true;
    }

    /**
     * Create the subscriber on Sequenzy for the given user.
     *
     * Existing subscribers are reported as {@see SequenzySubscribeResult::AlreadySubscribed}
     * — either through a 409 Conflict response or a 200 "skipped" duplicate —
     * which callers should treat as a successful subscription.
     */
    public function subscribe(User $user): SequenzySubscribeResult
    {
        $apiKey = $this->apiKeyResolver->resolve();

        if (blank($apiKey)) {
            return SequenzySubscribeResult::NotConfigured;
        }

        try {
            $response = Http::withHeaders($this->headers($apiKey))
                ->timeout($this->timeout())
                ->connectTimeout($this->timeout())
                ->post($this->endpoint('subscribers'), $this->buildPayload($user));
        } catch (ConnectionException $exception) {
            Log::warning('Unable to reach the Sequenzy API while creating a subscriber.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return SequenzySubscribeResult::Failed;
        }

        // 409 Conflict: the email is already tied to a subscriber identity on
        // Sequenzy — from the user's perspective they are already subscribed.
        if ($response->status() === 409) {
            Log::info('Sequenzy reported a subscriber conflict; treating it as an existing subscriber.', [
                'user_id' => $user->id,
                'error' => $response->json('error'),
            ]);

            $this->forgetLookupCache($user);

            return SequenzySubscribeResult::AlreadySubscribed;
        }

        if ($response->successful() && $response->json('success') === true) {
            $skipped = (bool) $response->json('subscriber.skipped', false);
            $created = (bool) $response->json('subscriber.created', false);

            $this->forgetLookupCache($user);

            return ($skipped || ! $created)
                ? SequenzySubscribeResult::AlreadySubscribed
                : SequenzySubscribeResult::Created;
        }

        Log::warning('Sequenzy subscriber creation failed.', [
            'user_id' => $user->id,
            'status' => $response->status(),
            'error' => $response->json('error'),
        ]);

        return SequenzySubscribeResult::Failed;
    }

    /**
     * Look up a subscriber by email address.
     *
     * Returns null when the lookup could not be completed (network failure,
     * unauthorized, server error, ...) so callers can decide how strict to be.
     */
    public function subscriberExists(string $email): ?bool
    {
        $apiKey = $this->apiKeyResolver->resolve();

        if (blank($apiKey) || blank($email)) {
            return null;
        }

        try {
            $response = Http::withHeaders($this->headers($apiKey))
                ->timeout($this->timeout())
                ->connectTimeout($this->timeout())
                ->get($this->endpoint('subscribers/'.rawurlencode($email)));
        } catch (ConnectionException $exception) {
            Log::warning('Unable to reach the Sequenzy API while checking a subscriber.', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return match ($response->status()) {
            200 => true,
            404 => false,
            default => null,
        };
    }

    /**
     * Cached existence check so the Sequenzy API is hit at most once per user
     * per TTL window while the prompt is pending.
     */
    private function remoteSubscriberExists(User $user): ?bool
    {
        $cacheKey = self::LOOKUP_CACHE_PREFIX.$user->id;
        $cached = Cache::get($cacheKey);

        if (is_bool($cached)) {
            return $cached;
        }

        $exists = $this->subscriberExists((string) $user->email);

        if ($exists !== null) {
            Cache::put($cacheKey, $exists, now()->addHours(self::LOOKUP_CACHE_TTL_HOURS));
        }

        return $exists;
    }

    private function forgetLookupCache(User $user): void
    {
        Cache::forget(self::LOOKUP_CACHE_PREFIX.$user->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(User $user): array
    {
        $role = $user->isStudentRole() ? 'student' : 'faculty';
        [$firstName, $lastName, $extraAttributes] = $this->resolveIdentity($user, $role);

        $payload = [
            'email' => (string) $user->email,
            'externalId' => 'user_'.$user->id,
            'tags' => ['portal', $role],
            'customAttributes' => array_merge([
                'role' => $role,
                'source' => 'portal_prompt',
            ], $extraAttributes),
            'enrollInSequences' => true,
            'duplicateStrategy' => 'skip',
        ];

        if (filled($firstName)) {
            $payload['firstName'] = $firstName;
        }

        if (filled($lastName)) {
            $payload['lastName'] = $lastName;
        }

        return $payload;
    }

    /**
     * Resolve names and role-specific custom attributes from the linked
     * Student/Faculty record, falling back to the user's display name.
     *
     * @return array{0: string|null, 1: string|null, 2: array<string, string>}
     */
    private function resolveIdentity(User $user, string $role): array
    {
        if ($role === 'student') {
            $student = Student::query()
                ->where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->first();

            if ($student instanceof Student) {
                $attributes = [];

                if (filled($student->student_id)) {
                    $attributes['student_id'] = (string) $student->student_id;
                }

                $courseCode = $student->Course?->code;

                if (filled($courseCode)) {
                    $attributes['course'] = (string) $courseCode;
                }

                return [$student->first_name, $student->last_name, $attributes];
            }
        }

        if ($role === 'faculty') {
            $faculty = Faculty::query()->where('email', $user->email)->first();

            if ($faculty instanceof Faculty) {
                $attributes = [];

                if (filled($faculty->faculty_id_number)) {
                    $attributes['faculty_id_number'] = (string) $faculty->faculty_id_number;
                }

                if (filled($faculty->department)) {
                    $attributes['department'] = (string) $faculty->department;
                }

                return [$faculty->first_name, $faculty->last_name, $attributes];
            }
        }

        $nameParts = preg_split('/\s+/', mb_trim((string) $user->name), 2) ?: [];

        return [$nameParts[0] ?? null, $nameParts[1] ?? null, []];
    }

    /**
     * The API accepts both X-API-Key and Bearer token authentication — send
     * both so either gateway configuration works.
     *
     * @return array<string, string>
     */
    private function headers(string $apiKey): array
    {
        return [
            'X-API-Key' => $apiKey,
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function endpoint(string $path): string
    {
        $base = mb_rtrim((string) config('services.sequenzy.url', 'https://api.sequenzy.com/api/v1'), '/');

        return $base.'/'.$path;
    }

    private function timeout(): float
    {
        return (float) config('services.sequenzy.timeout', 15);
    }
}
