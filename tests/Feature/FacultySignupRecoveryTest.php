<?php

declare(strict_types=1);

use App\Mail\FacultyIdentifierRecoveryMail;
use App\Mail\SignupOtpMail;
use App\Models\Faculty;
use App\Models\School;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->school = School::factory()->create([
        'name' => 'Northbridge University',
        'is_active' => true,
    ]);
    app(TenantContext::class)->setCurrentSchool($this->school);
});

it('emails FacultyIdentifierRecoveryMail with dynamic Employee ID label and prefilled signup link', function (): void {
    Mail::fake();

    app(IdentifierGenerator::class)
        ->sequenceFor(IdentifierGenerator::Staff, schoolId: $this->school->id)
        ->update(['display_label' => 'Employee ID']);

    Faculty::factory()->create([
        'school_id' => $this->school->id,
        'first_name' => 'Elena',
        'last_name' => 'Vance',
        'middle_name' => null,
        'email' => 'elena.vance@example.test',
        'faculty_id_number' => 'EMP-2026-042',
        'department' => 'Computer Science',
        'status' => 'active',
    ]);

    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'Elena.Vance@Example.Test',
        'user_type' => 'faculty',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'If a matching faculty record exists, we have sent your Employee ID to that email address.',
        ]);

    Mail::assertSent(FacultyIdentifierRecoveryMail::class, function (FacultyIdentifierRecoveryMail $mail): bool {
        $rendered = $mail->render();
        $expectedSignupUrl = url('/signup?email='.urlencode('elena.vance@example.test'));

        return $mail->hasTo('elena.vance@example.test')
            && str_contains($mail->envelope()->subject, 'Your Employee ID')
            && str_contains($rendered, 'Employee ID')
            && str_contains($rendered, 'EMP-2026-042')
            && str_contains($rendered, 'Computer Science')
            && str_contains($rendered, 'Northbridge University')
            && str_contains($rendered, $expectedSignupUrl);
    });
});

it('returns identical privacy-safe 200 JSON and sends no mail for unknown, inactive, or already-claimed faculty emails', function (): void {
    Mail::fake();

    Faculty::factory()->inactive()->create([
        'school_id' => $this->school->id,
        'email' => 'inactive.faculty@example.test',
        'faculty_id_number' => 'FAC-INACTIVE-1',
    ]);

    $claimedFaculty = Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'claimed.faculty@example.test',
        'faculty_id_number' => 'FAC-CLAIMED-1',
        'status' => 'active',
    ]);

    User::factory()->create([
        'email' => 'claimed.faculty@example.test',
        'record_id' => (string) $claimedFaculty->id,
        'school_id' => $this->school->id,
    ]);

    $expectedResponse = [
        'message' => 'If a matching faculty record exists, we have sent your Faculty ID to that email address.',
    ];

    foreach ([
        'nobody.here@example.test',
        'inactive.faculty@example.test',
        'claimed.faculty@example.test',
    ] as $email) {
        $this->postJson(route('signup.recover-identifier'), [
            'email' => $email,
            'user_type' => 'faculty',
        ])
            ->assertOk()
            ->assertExactJson($expectedResponse);
    }

    Mail::assertNotSent(FacultyIdentifierRecoveryMail::class);
});

it('suppresses faculty recovery email while returning 200 JSON when allow_identifier_recovery is disabled', function (): void {
    Mail::fake();

    $config = StudentConfigurationService::defaults();
    $config['signup']['allow_identifier_recovery'] = false;
    app(StudentConfigurationService::class)->update($config, $this->school->id);

    Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'suppressed.faculty@example.test',
        'faculty_id_number' => 'FAC-SUPP-1',
        'status' => 'active',
    ]);

    $this->postJson(route('signup.recover-identifier'), [
        'email' => 'suppressed.faculty@example.test',
        'user_type' => 'faculty',
    ])
        ->assertOk()
        ->assertExactJson([
            'message' => 'If a matching faculty record exists, we have sent your Faculty ID to that email address.',
        ]);

    Mail::assertNotSent(FacultyIdentifierRecoveryMail::class);
});

it('allows fast-track faculty OTP send without faculty_id_number when require_faculty_id is false and rejects when true', function (): void {
    Mail::fake();

    Faculty::factory()->create([
        'school_id' => $this->school->id,
        'email' => 'fasttrack.faculty@example.test',
        'faculty_id_number' => 'FAC-FAST-1',
        'status' => 'active',
    ]);

    // Default: require_faculty_id is false -> fast-track succeeds without faculty_id_number
    $this->postJson(route('signup.send-otp'), [
        'email' => 'fasttrack.faculty@example.test',
        'user_type' => 'faculty',
        'faculty_id_number' => '',
    ])->assertOk();

    Mail::assertSent(SignupOtpMail::class, 1);

    // Enable strict require_faculty_id -> blank faculty_id_number is rejected
    $config = StudentConfigurationService::defaults();
    $config['signup']['require_faculty_id'] = true;
    app(StudentConfigurationService::class)->update($config, $this->school->id);

    $this->postJson(route('signup.send-otp'), [
        'email' => 'fasttrack.faculty@example.test',
        'user_type' => 'faculty',
        'faculty_id_number' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['faculty_id_number']);

    Mail::assertSent(SignupOtpMail::class, 1);
});
