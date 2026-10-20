<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * SEC-001 · Anonymous self-registration accepted an attacker-chosen faculty role.
 *
 * A faculty account must be backed by an institutional record, and the role must come
 * from that record. The registration request is never allowed to name the role.
 */
beforeEach(function (): void {
    $this->school = School::factory()->create();
});

/**
 * Build the payload an attacker would post to the signup endpoint.
 *
 * @return array<string, mixed>
 */
function facultySignupPayload(string $email, array $overrides = []): array
{
    return array_merge([
        'name' => 'Mallory Attacker',
        'email' => $email,
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
        'role' => 'professor',
        'otp' => 'SEC001',
    ], $overrides);
}

it('refuses to create a faculty account without an institutional record', function (): void {
    Cache::put('signup_otp_attacker@test.com', 'SEC001', 600);

    $response = $this->postJson('/api/v1/auth/signup', facultySignupPayload('attacker@test.com'));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    expect($response->json('errors.email.0'))
        ->toContain('No active faculty record matches this email');

    $this->assertDatabaseMissing('users', ['email' => 'attacker@test.com']);
    $this->assertGuest();
});

it('never issues a bearer token to a registrant without an institutional record', function (): void {
    Cache::put('signup_otp_attacker@test.com', 'SEC001', 600);

    $response = $this->postJson('/api/v1/auth/signup', facultySignupPayload('attacker@test.com'));

    expect($response->json('data.token'))->toBeNull();
    $this->assertDatabaseCount('users', 0);
});

it('refuses a web faculty registration without an institutional record', function (): void {
    Cache::put('signup_otp_attacker@test.com', 'SEC001', 600);

    $this->post('/signup', facultySignupPayload('attacker@test.com'))
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseMissing('users', ['email' => 'attacker@test.com']);
    $this->assertGuest();
});

it('derives the faculty role from the institutional record instead of the request', function (): void {
    $faculty = Faculty::factory()->create([
        'email' => 'registrar.lecturer@test.com',
        'faculty_id_number' => 'FAC-SEC-001',
        'position' => 'Instructor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_registrar.lecturer@test.com', 'SEC001', 600);

    $this->postJson('/api/v1/auth/signup', facultySignupPayload('registrar.lecturer@test.com', [
        'faculty_id_number' => 'FAC-SEC-001',
        'role' => 'professor',
    ]))->assertCreated()
        ->assertJsonPath('data.user.role', UserRole::Instructor->value);

    $user = User::query()->where('email', 'registrar.lecturer@test.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Instructor)
        ->and($user->getRawOriginal('role'))->toBe(UserRole::Instructor->value)
        ->and($user->record_id)->toBe((string) $faculty->id)
        ->and($user->school_id)->toBe($this->school->id)
        ->and($user->faculty_id_number)->toBe('FAC-SEC-001');

    $this->assertDatabaseHas('model_has_roles', [
        'model_id' => $user->id,
        'role_id' => Spatie\Permission\Models\Role::where('name', UserRole::Instructor->value)->value('id'),
    ]);
    $this->assertDatabaseMissing('model_has_roles', [
        'model_id' => $user->id,
        'role_id' => Spatie\Permission\Models\Role::where('name', UserRole::Professor->value)->value('id'),
    ]);
});

it('grants the rank the institutional record carries', function (): void {
    $faculty = Faculty::factory()->create([
        'email' => 'chair.professor@test.com',
        'position' => 'Professor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_chair.professor@test.com', 'SEC001', 600);

    $this->postJson('/api/v1/auth/signup', facultySignupPayload('chair.professor@test.com', [
        'role' => 'instructor',
    ]))->assertCreated()
        ->assertJsonPath('data.user.role', UserRole::Professor->value);

    $user = User::query()->where('email', 'chair.professor@test.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::Professor);
});

it('cannot escalate a teaching rank by naming a higher one in the request', function (): void {
    Faculty::factory()->create([
        'email' => 'part.timer@test.com',
        'position' => 'Part-time Faculty',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_part.timer@test.com', 'SEC001', 600);

    $this->postJson('/api/v1/auth/signup', facultySignupPayload('part.timer@test.com', [
        'role' => 'professor',
    ]))->assertCreated()
        ->assertJsonPath('data.user.role', UserRole::PartTimeFaculty->value);

    $user = User::query()->where('email', 'part.timer@test.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::PartTimeFaculty);
});

it('keeps the web flow on the record derived role as well', function (): void {
    $faculty = Faculty::factory()->create([
        'email' => 'web.faculty@test.com',
        'position' => 'Assistant Professor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_web.faculty@test.com', 'SEC001', 600);

    $this->post('/signup', facultySignupPayload('web.faculty@test.com', [
        'name' => 'Web Faculty',
        'role' => 'professor',
    ]))->assertRedirect('/faculty/dashboard');

    $user = User::query()->where('email', 'web.faculty@test.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::AssistantProfessor)
        ->and($user->record_id)->toBe((string) $faculty->id)
        ->and($user->school_id)->toBe($this->school->id);

    $this->assertDatabaseHas('organization_user', [
        'user_id' => $user->id,
        'school_id' => $this->school->id,
        'role' => UserRole::AssistantProfessor->value,
        'is_primary' => true,
        'is_active' => true,
    ]);
});

it('rejects a faculty id number that does not belong to the registering email', function (): void {
    Faculty::factory()->create([
        'email' => 'real.faculty@test.com',
        'faculty_id_number' => 'FAC-REAL-1',
        'position' => 'Instructor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_real.faculty@test.com', 'SEC001', 600);

    $this->postJson('/api/v1/auth/signup', facultySignupPayload('real.faculty@test.com', [
        'faculty_id_number' => 'FAC-SOMEONE-ELSE',
    ]))->assertStatus(422)
        ->assertJsonValidationErrors(['faculty_id_number']);

    $this->assertDatabaseMissing('users', ['email' => 'real.faculty@test.com']);
});

it('refuses to send a verification code to an email with no faculty record', function (): void {
    Mail::fake();

    $this->postJson('/signup/send-otp', [
        'email' => 'attacker@test.com',
        'user_type' => 'faculty',
        'role' => 'professor',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

it('refuses to send an api verification code to an email with no faculty record', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/auth/signup/send-otp', [
        'email' => 'attacker@test.com',
        'user_type' => 'faculty',
        'role' => 'professor',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

it('exposes the institutional position on the email lookup', function (): void {
    Faculty::factory()->create([
        'email' => 'lookup.faculty@test.com',
        'position' => 'Associate Professor',
        'school_id' => $this->school->id,
    ]);

    $this->postJson('/signup/email-lookup', ['email' => 'lookup.faculty@test.com'])
        ->assertOk()
        ->assertJson([
            'found' => true,
            'type' => 'faculty',
            'position' => 'Associate Professor',
            'role' => UserRole::AssociateProfessor->value,
        ]);
});

it('refuses to register a faculty member the school stood down', function (): void {
    Faculty::factory()->inactive()->create([
        'email' => 'stood.down@test.com',
        'position' => 'Professor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_stood.down@test.com', 'SEC001', 600);

    $response = $this->postJson('/api/v1/auth/signup', facultySignupPayload('stood.down@test.com'));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    expect($response->json('errors.email.0'))
        ->toContain('No active faculty record matches this email');

    $this->assertDatabaseMissing('users', ['email' => 'stood.down@test.com']);
});

it('refuses a web registration for a faculty member the school stood down', function (): void {
    Faculty::factory()->inactive()->create([
        'email' => 'stood.down.web@test.com',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_stood.down.web@test.com', 'SEC001', 600);

    $this->post('/signup', facultySignupPayload('stood.down.web@test.com'))
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseMissing('users', ['email' => 'stood.down.web@test.com']);
    $this->assertGuest();
});

it('never issues a token to a stood down faculty member', function (): void {
    Faculty::factory()->inactive()->create([
        'email' => 'stood.down.token@test.com',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_stood.down.token@test.com', 'SEC001', 600);

    $response = $this->postJson('/api/v1/auth/signup', facultySignupPayload('stood.down.token@test.com'));

    expect($response->json('data.token'))->toBeNull();
    $this->assertDatabaseCount('users', 0);
});

it('refuses to send a verification code to a stood down faculty member', function (): void {
    Mail::fake();

    Faculty::factory()->inactive()->create([
        'email' => 'stood.down.otp@test.com',
        'school_id' => $this->school->id,
    ]);

    $this->postJson('/signup/send-otp', [
        'email' => 'stood.down.otp@test.com',
        'user_type' => 'faculty',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

it('does not report a stood down faculty record as a registrable match', function (): void {
    Faculty::factory()->inactive()->create([
        'email' => 'stood.down.lookup@test.com',
        'school_id' => $this->school->id,
    ]);

    $this->postJson('/signup/email-lookup', ['email' => 'stood.down.lookup@test.com'])
        ->assertOk()
        ->assertJson([
            'found' => false,
        ])
        ->assertJsonPath('message', 'This faculty record is not currently active. Please contact the registrar.');
});

it('still registers a faculty member who is temporarily away', function (): void {
    Faculty::factory()->onLeave()->create([
        'email' => 'on.leave@test.com',
        'position' => 'Instructor',
        'school_id' => $this->school->id,
    ]);

    Cache::put('signup_otp_on.leave@test.com', 'SEC001', 600);

    $this->postJson('/api/v1/auth/signup', facultySignupPayload('on.leave@test.com'))
        ->assertCreated()
        ->assertJsonPath('data.user.role', UserRole::Instructor->value);

    $this->assertDatabaseHas('users', ['email' => 'on.leave@test.com']);
});

it('treats a stood down roster state as ineligible for registration', function (): void {
    expect((new Faculty(['status' => 'active']))->isRegisterable())->toBeTrue()
        ->and((new Faculty(['status' => 'Active']))->isRegisterable())->toBeTrue()
        ->and((new Faculty(['status' => 'on_leave']))->isRegisterable())->toBeTrue()
        ->and((new Faculty(['status' => 'inactive']))->isRegisterable())->toBeFalse()
        ->and((new Faculty(['status' => 'Inactive']))->isRegisterable())->toBeFalse()
        ->and((new Faculty(['status' => '']))->isRegisterable())->toBeFalse()
        ->and((new Faculty(['status' => null]))->isRegisterable())->toBeFalse();
});
