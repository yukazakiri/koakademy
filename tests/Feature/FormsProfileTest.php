<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Models\FormInvitation;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormTemplateService;

it('tests profile completion invitation submission and redirects to thanks', function (): void {
    $admin = User::factory()->create();
    $student = Student::factory()->create([
        'student_id' => '20260001',
        'email' => 'student@example.test',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);
    $templates = app(FormTemplateService::class);
    $definition = $templates->definition('student_profile_completion');
    $definition['template_key'] = 'student_profile_completion';
    $definition['template_id'] = $templates->templateId('student_profile_completion');
    $definition['slug'] = 'student-profile-test-'.uniqid();

    $form = app(FormLifecycleService::class)->create($definition, $admin);
    app(FormLifecycleService::class)->publish($form, $admin);

    $token = 'my-test-token-xyz';
    $invitation = FormInvitation::factory()->create([
        'form_id' => $form->getKey(),
        'model_key' => 'student',
        'model_type' => Student::class,
        'model_id' => (string) $student->getKey(),
        'token_hash' => FormInvitation::tokenHash($token),
        'recipient_email' => $student->email,
        'status' => 'sent',
        'expires_at' => now()->addDays(30),
    ]);

    // Test GET invitation show
    $getResponse = $this->get(route('forms.invitation.show', [
        'form' => $form->slug,
        'token' => $token,
    ]));
    $getResponse->assertOk();

    // Test POST invitation submit with missing required fields redirects back with validation errors
    $invalidPost = $this->post(route('forms.invitation.submit', [
        'form' => $form->slug,
        'token' => $token,
    ]), [
        'answers' => [
            'emergency_contact_phone' => 'invalid-phone',
        ],
    ]);
    $invalidPost->assertRedirect(route('forms.invitation.show', ['form' => $form->slug, 'token' => $token]));
    $invalidPost->assertSessionHasErrors();

    // Test POST invitation submit with all required fields filled
    $postResponse = $this->post(route('forms.invitation.submit', [
        'form' => $form->slug,
        'token' => $token,
    ]), [
        'answers' => [
            'permanent_address' => '123 Main St, City',
            'birthplace' => 'Manila',
            'weight' => 60,
            'height' => 170,
            'emergency_contact_phone' => '+63 912 345 6789',
            'emergency_contact_address' => '456 Safe St, City',
            'emergency_contact_relationship' => 'Father',
        ],
    ]);

    $postResponse->assertRedirect(route('forms.thanks', ['form' => $form->slug]));

    expect($invitation->fresh()->status)->toBe('completed')
        ->and(FormResponse::query()->where('form_id', $form->getKey())->count())->toBe(1);
});

it('tests profile completion guest verification and submission', function (): void {
    $admin = User::factory()->create();
    $student = Student::factory()->create([
        'student_id' => '20260002',
        'email' => 'student2@example.test',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);
    $templates = app(FormTemplateService::class);
    $definition = $templates->definition('student_profile_completion');
    $definition['template_key'] = 'student_profile_completion';
    $definition['template_id'] = $templates->templateId('student_profile_completion');
    $definition['access_mode'] = FormAccessMode::GuestIdentifier->value;
    $definition['identity_type'] = 'student_id';
    $definition['slug'] = 'student-profile-guest-'.uniqid();

    $form = app(FormLifecycleService::class)->create($definition, $admin);
    app(FormLifecycleService::class)->publish($form, $admin);

    // Test identify endpoint
    $identifyResponse = $this->post(route('forms.identify', ['form' => $form->slug]), [
        'respondent_identifier' => '20260002',
        'respondent_email' => 'student2@example.test',
    ]);
    $identifyResponse->assertOk()
        ->assertJson(['matched' => true]);

    // Test submit with verified credentials
    $submitResponse = $this->post(route('forms.submit', ['form' => $form->slug]), [
        'respondent_identifier' => '20260002',
        'respondent_email' => 'student2@example.test',
        'answers' => [
            'permanent_address' => '456 Guest Way, City',
            'birthplace' => 'Cebu City',
            'weight' => 55,
            'height' => 165,
            'emergency_contact_phone' => '+63 912 345 6789',
            'emergency_contact_address' => '789 Safe St, City',
            'emergency_contact_relationship' => 'Mother',
        ],
    ]);

    $submitResponse->assertRedirect(route('forms.thanks', ['form' => $form->slug]));

    $response = FormResponse::query()->where('form_id', $form->getKey())->first();
    expect($response)->not->toBeNull()
        ->and($response->status)->toBe(FormResponseStatus::Applied);
});
