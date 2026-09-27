<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use App\Notifications\MagicLoginLinkNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

beforeEach(function (): void {
    School::factory()->create();
});

it('displays the magic link request page', function (): void {
    $response = $this->get(route('magic-link.request'));

    $response->assertOk();
});

it('dispatches a magic login notification for an existing user', function (): void {
    Notification::fake();

    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'student.magic@school.edu',
    ]);

    $response = $this->from(route('login'))->post(route('magic-link.send'), [
        'email' => 'student.magic@school.edu',
        'remember' => true,
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    Notification::assertSentTo(
        $user,
        MagicLoginLinkNotification::class,
        function (MagicLoginLinkNotification $notification): bool {
            return str_contains($notification->url, 'magic-link/verify');
        }
    );
});

it('returns generic confirmation without sending mail when email does not exist', function (): void {
    Notification::fake();

    $response = $this->from(route('login'))->post(route('magic-link.send'), [
        'email' => 'nonexistent@school.edu',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('authenticates a user with a valid signed magic link and enforces single-use replay protection', function (): void {
    /** @var User $user */
    $user = User::factory()->create([
        'email' => 'verify.magic@school.edu',
    ]);

    $token = Str::random(40);
    Cache::put("magic_link:{$user->id}:{$token}", true, now()->addMinutes(15));

    $signedUrl = URL::temporarySignedRoute(
        'magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
            'remember' => 1,
        ]
    );

    // First visit: successfully logs in and consumes the single-use token
    $firstResponse = $this->get($signedUrl);

    $firstResponse->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect(Cache::has("magic_link:{$user->id}:{$token}"))->toBeFalse();

    // Log out to test replay attempt
    Auth::logout();
    $this->assertGuest();

    // Second visit: token already consumed, replay rejected
    $replayResponse = $this->get($signedUrl);

    $replayResponse->assertRedirect(route('login'));
    $replayResponse->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects an invalid or tampered signature on magic link verification', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $token = Str::random(40);
    Cache::put("magic_link:{$user->id}:{$token}", true, now()->addMinutes(15));

    $tamperedUrl = route('magic-link.verify', [
        'user' => $user->id,
        'token' => $token,
        'signature' => 'invalid-hmac-signature',
    ]);

    $response = $this->get($tamperedUrl);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('strictly connects to testing.sqlite and preserves database.sqlite during tests', function (): void {
    $activeDatabase = (string) config('database.connections.sqlite.database');
    $appDatabase = database_path('database.sqlite');
    $testingDatabase = database_path('testing.sqlite');

    expect($activeDatabase)->not->toBe($appDatabase)
        ->and(realpath($activeDatabase))->not->toBe(realpath($appDatabase))
        ->and(basename($activeDatabase))->toBe('testing.sqlite');
});
