<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ConnectedAccount;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function enableSocialProvider(string $provider = 'google', array $overrides = []): void
{
    $config = array_merge([
        "{$provider}_client_id" => "{$provider}-client-id",
        "{$provider}_client_secret" => "{$provider}-client-secret",
        "{$provider}_enabled" => true,
        "{$provider}_redirect_uri" => portalUrlForAdministrators("/auth/{$provider}/callback"),
    ], $overrides);

    GeneralSetting::factory()->create([
        'social_network' => $config,
    ]);

    config([
        "services.{$provider}.client_id" => $config["{$provider}_client_id"],
        "services.{$provider}.client_secret" => $config["{$provider}_client_secret"],
        "services.{$provider}.redirect" => $config["{$provider}_redirect_uri"],
    ]);
}

function fakeGoogleUser(array $attributes = []): void
{
    Socialite::fake('google', SocialiteUser::fake(array_merge([
        'id' => 'google-123',
        'name' => 'Google User',
        'email' => 'google-user@example.com',
        'avatar' => 'https://lh3.googleusercontent.com/avatar.jpg',
        'token' => 'google-token',
        'refreshToken' => 'google-refresh-token',
        'expiresIn' => 3600,
    ], $attributes)));
}

it('persists enabled social providers and exposes them to auth pages', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Developer,
    ]);

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/socialite'), [
            'google_client_id' => 'google-client-id',
            'google_client_secret' => 'google-client-secret',
            'google_enabled' => true,
            'google_redirect_uri' => portalUrlForAdministrators('/auth/google/callback'),
        ])
        ->assertRedirect();

    $socialNetwork = GeneralSetting::query()->first()?->social_network;

    expect($socialNetwork['google_enabled'])->toBeTrue()
        ->and($socialNetwork['google_client_id'])->toBe('google-client-id');

    $this->get(portalUrlForAdministrators('/login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('login', false)
            ->where('socialAuthProviders.0.key', 'google')
            ->where('socialAuthProviders.0.label', 'Google'));
});

it('does not expose incomplete or disabled providers to auth pages', function (): void {
    enableSocialProvider('google', [
        'google_client_secret' => '',
        'google_enabled' => true,
    ]);

    $this->get(portalUrlForAdministrators('/login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('login', false)
            ->where('socialAuthProviders', []));
});

it('uses database backed socialite settings for provider redirects', function (): void {
    $redirectUri = portalUrlForAdministrators('/auth/google/callback');

    GeneralSetting::factory()->create([
        'social_network' => [
            'google_client_id' => 'database-google-client-id',
            'google_client_secret' => 'database-google-client-secret',
            'google_enabled' => true,
            'google_redirect_uri' => $redirectUri,
        ],
    ]);

    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
        'services.google.redirect' => null,
    ]);

    $response = $this->get(portalUrlForAdministrators('/auth/google/redirect'))
        ->assertRedirect();

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY) ?: '', $query);

    expect($query['client_id'] ?? null)->toBe('database-google-client-id')
        ->and($query['redirect_uri'] ?? null)->toBe($redirectUri);
});

it('logs in a user with an already linked google account', function (): void {
    enableSocialProvider();
    fakeGoogleUser();

    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    ConnectedAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'email' => 'google-user@example.com',
        'token' => 'old-token',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/student/dashboard');

    $this->assertAuthenticatedAs($user);

    expect(ConnectedAccount::query()->where('provider_id', 'google-123')->first()?->token)
        ->toBe('google-token');
});

it('links a google account to an existing user email and syncs the avatar', function (): void {
    enableSocialProvider();
    fakeGoogleUser([
        'email' => 'student@example.com',
        'avatar' => 'https://lh3.googleusercontent.com/student.jpg',
    ]);

    $user = User::factory()->create([
        'email' => 'student@example.com',
        'role' => UserRole::Student,
        'avatar_url' => null,
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/student/dashboard')
        ->assertSessionHas('status', 'Google is now linked. You can use it to sign in next time.');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->avatar_url)->toBe('https://lh3.googleusercontent.com/student.jpg');

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'email' => 'student@example.com',
    ]);
});

it('repairs a linked student organization before social login', function (): void {
    enableSocialProvider();
    fakeGoogleUser(['email' => 'linked.student@example.com']);

    $school = School::factory()->create();
    $user = User::factory()->create([
        'email' => 'linked.student@example.com',
        'role' => UserRole::Student,
        'school_id' => null,
    ]);
    $student = Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'linked.student@example.com',
        'student_id' => 9876543,
        'user_id' => $user->id,
    ]);
    $user->forceFill(['record_id' => $student->id])->save();

    ConnectedAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'email' => 'linked.student@example.com',
        'token' => 'old-token',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/student/dashboard');

    expect($user->refresh()->school_id)->toBe($school->id);
    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $school->id,
        'is_primary' => true,
        'is_active' => true,
    ]);
});

it('does not infer a student organization from social email alone', function (): void {
    enableSocialProvider();
    fakeGoogleUser(['email' => 'ambiguous.student@example.com']);

    $firstSchool = School::factory()->create();
    $secondSchool = School::factory()->create();
    Student::factory()->create([
        'institution_id' => $firstSchool->id,
        'school_id' => $firstSchool->id,
        'email' => 'ambiguous.student@example.com',
        'student_id' => 4444444,
    ]);
    Student::factory()->create([
        'institution_id' => $secondSchool->id,
        'school_id' => $secondSchool->id,
        'email' => 'ambiguous.student@example.com',
        'student_id' => 5555555,
    ]);
    $user = User::factory()->create([
        'email' => 'ambiguous.student@example.com',
        'role' => UserRole::Student,
        'record_id' => null,
        'school_id' => null,
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/student/dashboard');

    expect($user->refresh()->school_id)->toBeNull();
    $this->assertDatabaseMissing('organization_user', ['user_id' => $user->id]);
});

it('redirects unknown google users to signup with prefilled session data', function (): void {
    enableSocialProvider();
    fakeGoogleUser([
        'email' => 'new-student@example.com',
        'name' => 'New Student',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup')
        ->assertSessionHas('socialite_signup.email', 'new-student@example.com')
        ->assertSessionHas('socialite_signup.name', 'New Student');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', [
        'email' => 'new-student@example.com',
    ]);

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialiteSignup.email', 'new-student@example.com')
            ->where('socialiteSignup.name', 'New Student'));
});

it('allows authenticated users to link an additional google account without changing their email', function (): void {
    enableSocialProvider();
    fakeGoogleUser([
        'id' => 'google-secondary',
        'email' => 'secondary-google@example.com',
    ]);

    $user = User::factory()->create([
        'email' => 'primary@example.com',
        'role' => UserRole::Student,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/integrations/google/callback'))
        ->assertRedirect('/profile');

    expect($user->refresh()->email)->toBe('primary@example.com');

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-secondary',
        'email' => 'secondary-google@example.com',
    ]);
});

it('rejects linking a google account that belongs to another user', function (): void {
    enableSocialProvider();
    fakeGoogleUser([
        'id' => 'google-owned',
        'email' => 'owned-google@example.com',
    ]);

    $owner = User::factory()->create();
    $user = User::factory()->create();

    ConnectedAccount::query()->create([
        'user_id' => $owner->id,
        'provider' => 'google',
        'provider_id' => 'google-owned',
        'email' => 'owned-google@example.com',
        'token' => 'owner-token',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/integrations/google/callback'))
        ->assertRedirect('/profile')
        ->assertSessionHasErrors('socialite');

    expect(ConnectedAccount::query()->where('provider_id', 'google-owned')->first()?->user_id)
        ->toBe($owner->id);
});

it('shares connected google account details with the profile page', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Student,
    ]);

    ConnectedAccount::query()->create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-primary',
        'name' => 'Primary Google',
        'email' => 'primary-google@example.com',
        'avatar_path' => 'https://example.com/primary.jpg',
        'token' => 'primary-token',
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/student/profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('profile', false)
            ->where('connected_accounts.providers.google', true)
            ->where('connected_accounts.accounts.0.provider', 'google')
            ->where('connected_accounts.accounts.0.email', 'primary-google@example.com'));
});
