<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Auth\SignupOtpService;
use App\Services\StudentConfigurationService;
use Illuminate\Support\Facades\Hash;
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
    $user = SocialiteUser::fake(array_merge([
        'id' => 'google-123',
        'name' => 'Google User',
        'email' => 'google-user@example.com',
        'avatar' => 'https://lh3.googleusercontent.com/avatar.jpg',
        'token' => 'google-token',
        'refreshToken' => 'google-refresh-token',
        'expiresIn' => 3600,
    ], $attributes));

    if (! array_key_exists('user', $attributes)) {
        $user->setRaw(['email_verified' => true]);
    }

    Socialite::fake('google', $user);
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

    auth()->logout();

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
        'email' => 'Student@Example.com',
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

it('sanitizes socialite signup props so oauth tokens are never exposed to the browser and expires stale sessions', function (): void {
    enableSocialProvider();
    fakeGoogleUser([
        'id' => 'google-secret-check',
        'email' => 'secret-check@example.com',
        'name' => 'Secret Check',
        'token' => 'super-secret-oauth-token',
        'refreshToken' => 'super-secret-refresh-token',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialiteSignup.provider', 'google')
            ->where('socialiteSignup.email', 'secret-check@example.com')
            ->where('socialiteSignup.email_verified', true)
            ->missing('socialiteSignup.token')
            ->missing('socialiteSignup.secret')
            ->missing('socialiteSignup.refresh_token'));

    // Expire pending session beyond 15-minute TTL
    $pending = session('socialite_signup');
    $pending['created_at'] = now()->subMinutes(20)->toIso8601String();
    session(['socialite_signup' => $pending]);

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialiteSignup', null)
            ->where('socialLookup', null));
});

it('lets a verified google student activate in one step without otp or password and later provision a password on profile', function (): void {
    enableSocialProvider();
    $school = School::factory()->create(['is_active' => true]);

    $student = Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'fast.student@example.com',
        'student_id' => 20260099,
    ]);

    fakeGoogleUser([
        'id' => 'google-fast-student',
        'name' => 'Fast Student',
        'email' => 'fast.student@example.com',
        'avatar' => 'https://lh3.googleusercontent.com/fast-student.jpg',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialiteSignup.email_verified', true)
            ->where('socialLookup.found', true)
            ->where('socialLookup.type', 'student')
            ->where('socialLookup.hints.require_password_on_social_signup', false));

    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Fast Student',
        'email' => 'fast.student@example.com',
        'user_type' => 'student',
        'identifier' => '20260099',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'fast.student@example.com')->firstOrFail();

    expect($user->hasProvisionedPassword())->toBeFalse()
        ->and($user->school_id)->toBe($school->id)
        ->and($user->avatar_url)->toBe('https://lh3.googleusercontent.com/fast-student.jpg')
        ->and($student->refresh()->user_id)->toBe($user->id);

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-fast-student',
    ]);

    // Profile shows has_password = false and allows provisioning a password without current_password
    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/student/profile'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('profile', false)
            ->where('user.has_password', false));

    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/student/profile/password'), [
            'password' => 'NewStrongPass123!',
            'password_confirmation' => 'NewStrongPass123!',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->hasProvisionedPassword())->toBeTrue()
        ->and(Hash::check('NewStrongPass123!', $user->password))->toBeTrue();

    // Subsequent password changes DO require current_password
    $this->actingAs($user)
        ->put(portalUrlForAdministrators('/student/profile/password'), [
            'password' => 'AnotherStrongPass456!',
            'password_confirmation' => 'AnotherStrongPass456!',
        ])
        ->assertSessionHasErrors('current_password');
});

it('enforces require_password_on_social_signup when configured in school settings', function (): void {
    enableSocialProvider();
    $school = School::factory()->create(['is_active' => true]);
    $configService = app(StudentConfigurationService::class);
    $config = $configService->get($school->id);
    $config['signup']['require_password_on_social_signup'] = true;
    $configService->update($config, $school->id);

    Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'strict.social@example.com',
        'student_id' => 20260111,
    ]);

    fakeGoogleUser([
        'id' => 'google-strict-student',
        'name' => 'Strict Student',
        'email' => 'strict.social@example.com',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialLookup.hints.require_password_on_social_signup', true));

    // Omitting password is rejected
    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Strict Student',
        'email' => 'strict.social@example.com',
        'user_type' => 'student',
        'identifier' => '20260111',
    ])->assertSessionHasErrors('password');

    // Providing a valid password succeeds (still skipping OTP because Google verified the email)
    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Strict Student',
        'email' => 'strict.social@example.com',
        'user_type' => 'student',
        'identifier' => '20260111',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'strict.social@example.com')->firstOrFail();
    expect($user->hasProvisionedPassword())->toBeTrue();
});

it('lets a verified google single-campus faculty member activate in one click without otp or password', function (): void {
    enableSocialProvider();
    $school = School::factory()->create(['is_active' => true]);

    Faculty::factory()->create([
        'school_id' => $school->id,
        'email' => 'fast.faculty@example.com',
        'status' => 'active',
        'faculty_id_number' => 'FAC-2026-088',
    ]);

    fakeGoogleUser([
        'id' => 'google-fast-faculty',
        'name' => 'Fast Faculty',
        'email' => 'fast.faculty@example.com',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Fast Faculty',
        'email' => 'fast.faculty@example.com',
        'user_type' => 'faculty',
    ])->assertRedirect('/faculty/dashboard');

    $user = User::query()->where('email', 'fast.faculty@example.com')->firstOrFail();

    expect($user->hasProvisionedPassword())->toBeFalse()
        ->and($user->school_id)->toBe($school->id);

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-fast-faculty',
    ]);
});

it('requires otp for cross-email social signup and links the personal google account once school email is verified', function (): void {
    enableSocialProvider();
    $school = School::factory()->create(['is_active' => true]);

    Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'alex@school.edu',
        'student_id' => 20260222,
    ]);

    fakeGoogleUser([
        'id' => 'google-personal-gmail',
        'name' => 'Alex Personal',
        'email' => 'alex.personal@gmail.com',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->get(portalUrlForAdministrators('/signup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('signup', false)
            ->where('socialiteSignup.email', 'alex.personal@gmail.com')
            ->where('socialLookup.found', false));

    // Cannot skip OTP when registering with a different school email
    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Alex Personal',
        'email' => 'alex@school.edu',
        'user_type' => 'student',
        'identifier' => '20260222',
    ])->assertSessionHasErrors('otp');

    // Once OTP proves ownership of alex@school.edu, personal Google account IS linked cleanly
    $otp = '654321';
    app(SignupOtpService::class)->issue('alex@school.edu', $otp);

    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Alex Personal',
        'email' => 'alex@school.edu',
        'user_type' => 'student',
        'identifier' => '20260222',
        'otp' => $otp,
    ])->assertRedirect('/student/dashboard');

    $user = User::query()->where('email', 'alex@school.edu')->firstOrFail();

    expect($user->hasProvisionedPassword())->toBeFalse();
    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-personal-gmail',
        'email' => 'alex.personal@gmail.com',
    ]);
});

it('links a pending personal google account when the user signs into an existing portal account', function (): void {
    enableSocialProvider();

    $user = User::factory()->create([
        'email' => 'existing.student@school.edu',
        'password' => Hash::make('PortalSecret123!'),
        'role' => UserRole::Student,
    ]);

    fakeGoogleUser([
        'id' => 'google-link-on-login',
        'name' => 'Personal Google',
        'email' => 'personal.alias@gmail.com',
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->post(portalUrlForAdministrators('/signup/social-login-link'))
        ->assertRedirect('/login');

    $this->get(portalUrlForAdministrators('/login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('login', false)
            ->where('pendingSocialLink.provider', 'google')
            ->where('pendingSocialLink.email', 'personal.alias@gmail.com')
            ->missing('pendingSocialLink.token'));

    $this->post(portalUrlForAdministrators('/login'), [
        'email' => 'existing.student@school.edu',
        'password' => 'PortalSecret123!',
    ])->assertRedirect('/student/dashboard');

    $this->assertDatabaseHas('connected_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-link-on-login',
        'email' => 'personal.alias@gmail.com',
    ]);
});

it('never bypasses email otp when the social provider email is not verified', function (): void {
    enableSocialProvider();
    $school = School::factory()->create(['is_active' => true]);

    Student::factory()->create([
        'institution_id' => $school->id,
        'school_id' => $school->id,
        'email' => 'unverified.social@example.com',
        'student_id' => 20260333,
    ]);

    fakeGoogleUser([
        'id' => 'google-unverified-signup',
        'name' => 'Unverified Student',
        'email' => 'unverified.social@example.com',
        'user' => ['email_verified' => false],
    ]);

    $this->get(portalUrlForAdministrators('/auth/google/callback'))
        ->assertRedirect('/signup');

    $this->post(portalUrlForAdministrators('/signup'), [
        'name' => 'Unverified Student',
        'email' => 'unverified.social@example.com',
        'user_type' => 'student',
        'identifier' => '20260333',
    ])->assertSessionHasErrors('otp');
});

it('persists require_password_on_social_signup from administrator system settings', function (): void {
    $school = School::factory()->create(['is_active' => true]);
    $admin = User::factory()->create([
        'role' => UserRole::Developer,
        'school_id' => $school->id,
    ]);

    $configService = app(StudentConfigurationService::class);
    $payload = $configService->get($school->id);
    $payload['signup']['require_password_on_social_signup'] = true;

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators('/administrators/system-management/student-configuration'), $payload)
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $configService->forget($school->id);
    expect($configService->get($school->id)['signup']['require_password_on_social_signup'])->toBeTrue()
        ->and($configService->signupPresentation($school->id)['require_password_on_social_signup'])->toBeTrue();
});
