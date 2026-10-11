<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentPolicyManager;
use App\Enums\UserRole;
use App\Models\EnrollmentPolicy;
use App\Models\User;
use App\Support\SystemManagementPermissions;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

it('renders the email templates page with a card per workflow step', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/email-templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/system-management/email-templates', false)
            ->has('access.sections.email_templates')
            ->has('email_template_notification_types')
            ->has('email_template_policies')
            ->where('email_template_policies.0.id', $policy->id)
        );

    // Every workflow step is exposed so administrators can add a missing email.
    $templates = data_get(app(App\Services\EnrollmentEmailTemplateService::class)->templatesFor($policy), '*', null);
    expect($templates)->toBeArray();
});

it('persists an email template to the policy draft through the standalone endpoint', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $draft = $policy->versions()->draft()->firstOrFail();
    $stepKey = data_get($draft->configuration, 'workflow.steps.0.key');

    $response = actingAs($user)->put(
        portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"),
        [
            'step_key' => $stepKey,
            'configuration' => [
                'notification' => 'status_update',
                'subject' => 'Your application moved to {{ status }}',
                'headline' => 'Application Update',
                'custom_body' => 'Hi {{ student_name }}, your application advanced.',
                'button_label' => 'Track Application',
                'button_url' => '/student/applications',
                'include_details' => true,
                'include_subjects' => false,
                'include_tuition' => false,
            ],
        ],
    );

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $draft->refresh();
    $actions = data_get($draft->configuration, 'workflow.steps.0.actions', []);
    $notify = collect($actions)->firstWhere('handler', 'enrollment.notify');

    expect($notify)->not->toBeNull()
        ->and($notify['configuration']['notification'])->toBe('status_update')
        ->and($notify['configuration']['subject'])->toBe('Your application moved to {{ status }}')
        ->and($notify['configuration']['button_url'])->toBe('/student/applications')
        ->and($notify['configuration']['include_details'])->toBeTrue();
});

it('refuses an absolute call-to-action url because the policy compiler rejects external links', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $stepKey = data_get($policy->versions()->draft()->firstOrFail()->configuration, 'workflow.steps.0.key');

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), [
            'step_key' => $stepKey,
            'configuration' => [
                'notification' => 'custom',
                'button_url' => 'https://evil.test/phish',
            ],
        ])
        ->assertSessionHasErrors('configuration.button_url');
});

it('rejects an unsupported email template type', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $stepKey = data_get($policy->versions()->draft()->firstOrFail()->configuration, 'workflow.steps.0.key');

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), [
            'step_key' => $stepKey,
            'configuration' => ['notification' => 'not_a_real_template'],
        ])
        ->assertSessionHasErrors('configuration.notification');
});

it('rejects a call to action button that is not a same-portal path', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $stepKey = data_get($policy->versions()->draft()->firstOrFail()->configuration, 'workflow.steps.0.key');

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), [
            'step_key' => $stepKey,
            'configuration' => [
                'notification' => 'custom',
                'button_url' => 'javascript:alert(1)',
            ],
        ])
        ->assertSessionHasErrors('configuration.button_url');
});

it('rejects an update targeting a workflow step that does not exist', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), [
            'step_key' => 'no_such_step',
            'configuration' => ['notification' => 'custom'],
        ])
        ->assertSessionHasErrors('step_key');
});

it('forbids saving email templates without the update permission', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    Permission::findOrCreate(SystemManagementPermissions::viewPermission('email_templates'), 'web');
    $user->givePermissionTo(SystemManagementPermissions::viewPermission('email_templates'));

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $stepKey = data_get($policy->versions()->draft()->firstOrFail()->configuration, 'workflow.steps.0.key');

    actingAs($user)
        ->put(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), [
            'step_key' => $stepKey,
            'configuration' => ['notification' => 'custom'],
        ])
        ->assertForbidden();
});

it('adds the notify action when a step does not dispatch an email yet', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $policy = EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Email Template Policy'], $user);
    $draft = $policy->versions()->draft()->firstOrFail();
    $stepKey = data_get($draft->configuration, 'workflow.steps.0.key');

    // Start from a step that has no notify action at all.
    $config = $draft->configuration;
    $config['workflow']['steps'][0]['actions'] = [];
    $draft->update(['configuration' => $config]);

    actingAs($user)->put(
        portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"),
        [
            'step_key' => $stepKey,
            'configuration' => ['notification' => 'custom', 'subject' => 'Hello there'],
        ],
    )->assertSessionHasNoErrors();

    $draft->refresh();
    $actions = data_get($draft->configuration, 'workflow.steps.0.actions', []);

    expect($actions)->toHaveCount(1)
        ->and($actions[0]['handler'])->toBe('enrollment.notify')
        ->and($actions[0]['configuration']['subject'])->toBe('Hello there');
});
