<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentPolicyManager;
use App\Models\EnrollmentPolicy;
use App\Services\EnrollmentEmailTemplateService;

function emailBackfillPolicy(): EnrollmentPolicy
{
    $admin = App\Models\User::factory()->create(['role' => App\Enums\UserRole::SuperAdmin]);

    return EnrollmentPolicy::first() ?? app(EnrollmentPolicyManager::class)->create(['name' => 'Backfill Policy'], $admin);
}

function emailBackfillAuthor(EnrollmentPolicy $policy): App\Models\User
{
    return App\Models\User::first();
}

it('chooses the built-in template that matches the step', function (string $expected, array $step): void {
    expect(app(EnrollmentEmailTemplateService::class)->defaultNotificationForStep($step))->toBe($expected);
})->with([
    // A step named after a template gets that template.
    'named step' => ['academic_verified', ['key' => 'academic_verified', 'terminal' => false]],
    'assessment step' => ['assessment', ['key' => 'assessment', 'terminal' => false]],
    // The terminal milestone gets the final certificate.
    'terminal step' => ['assessment', ['key' => 'custom_step', 'terminal' => true]],
    // Everything else is a plain status update.
    'ordinary step' => ['status_update', ['key' => 'document_review', 'terminal' => false]],
]);

it('gives every step a built-in email template', function (): void {
    $policy = emailBackfillPolicy();
    $service = app(EnrollmentEmailTemplateService::class);

    $service->backfillDefaultsFor($policy);

    foreach ($service->templatesFor($policy) as $template) {
        expect($template['configured'])->toBeTrue("{$template['step_key']} has no email template");
        expect($template['notification'])->not->toBeNull();
    }
});

it('never overwrites an explicitly configured template', function (): void {
    $policy = emailBackfillPolicy();
    $service = app(EnrollmentEmailTemplateService::class);
    $manager = app(EnrollmentPolicyManager::class);

    $draft = $policy->versions()->draft()->first();
    $config = $draft->configuration;
    $steps = data_get($config, 'workflow.steps', []);

    $steps[0]['actions'] = [[
        'key' => 'notify_custom',
        'handler' => 'enrollment.notify',
        'configuration' => [
            'notification' => 'custom',
            'subject' => 'My tailored subject',
            'custom_body' => 'My tailored body.',
        ],
    ]];
    $config['workflow']['steps'] = $steps;
    $manager->saveDraft($policy, $config, 'Customised first step.', emailBackfillAuthor($policy));

    $service->backfillDefaultsFor($policy);

    $templates = collect($service->templatesFor($policy));
    $first = $templates->first();

    expect($first['configuration']['notification'])->toBe('custom')
        ->and($first['configuration']['subject'])->toBe('My tailored subject');
});

it('is safe to run repeatedly', function (): void {
    $policy = emailBackfillPolicy();
    $service = app(EnrollmentEmailTemplateService::class);

    $service->backfillDefaultsFor($policy);
    $before = collect($service->templatesFor($policy))
        ->map(fn (array $t): array => $t['configuration'])
        ->toJson();

    $service->backfillDefaultsFor($policy);
    $service->backfillDefaultsFor($policy);

    expect(collect($service->templatesFor($policy))
        ->map(fn (array $t): array => $t['configuration'])
        ->toJson())->toBe($before);
});

it('reports how many steps it filled', function (): void {
    $policy = emailBackfillPolicy();
    $service = app(EnrollmentEmailTemplateService::class);

    // Blank out one step's template so there is something to fill.
    $manager = app(EnrollmentPolicyManager::class);
    $draft = $policy->versions()->draft()->first();
    $config = $draft->configuration;
    $config['workflow']['steps'][0]['actions'] = [];
    $manager->saveDraft($policy, $config, 'Blanked first step.', emailBackfillAuthor($policy));

    expect($service->backfillDefaultsFor($policy))->toBeGreaterThan(0)
        ->and($service->backfillDefaultsFor($policy))->toBe(0);
});
