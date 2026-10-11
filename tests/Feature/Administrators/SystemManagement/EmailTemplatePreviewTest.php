<?php

declare(strict_types=1);

use App\Email\EmailTemplateCatalog;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enums\UserRole;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicyVersion;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Support\SystemManagementPermissions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/** @return array{EnrollmentPolicy, string} */
function enrollmentEmailPreviewPolicy(User $author): array
{
    $policy = app(EnrollmentPolicyManager::class)->create([
        'name' => 'Synthetic Preview Policy',
        'school_year' => '2098-2099',
    ], $author);
    $draft = $policy->versions()->draft()->firstOrFail();
    $configuration = $draft->configuration;
    $configuration['workflow']['steps'][0]['key'] = 'preview_review_stage';
    $configuration['workflow']['steps'][0]['label'] = 'A Different Workflow Label';
    $configuration['workflow']['steps'][0]['status'] = 'Preview Review Pending';
    $draft->update(['configuration' => $configuration]);

    return [$policy, $configuration['workflow']['steps'][0]['key']];
}

function enrollmentEmailPreviewUrl(EnrollmentPolicy $policy): string
{
    return route('administrators.system-management.email-templates.preview', $policy);
}

it('exposes only enrollment definitions with catalog copy, actual capabilities and installation branding', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $settings = app(SiteSettings::class);
    $settings->organization_name = 'Preview Academy';
    $settings->logo = '/preview-academy.png';
    $settings->tagline = 'Learning together';
    $settings->theme_color = '#123456';
    $settings->support_email = 'support@example.test';
    $settings->support_phone = '+63 555 0100';

    actingAs($user)
        ->get(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}/edit?step_key={$stepKey}"))
        ->assertOk()
        ->assertInertia(function ($page): void {
            $page->has('email_template_editor.definitions', 4)
                ->where('email_template_editor.definitions', function ($definitions): bool {
                    expect(array_column($definitions->toArray(), 'key'))->toBe(EmailTemplateCatalog::ENROLLMENT_KEYS);
                    foreach ($definitions as $definition) {
                        $default = EmailTemplateCatalog::defaultFor($definition['key']);
                        expect($definition['defaults']['subject'])->toBe($default['subject']);
                        expect($definition['defaults']['custom_body'])->toBe($default['intro']);
                    }

                    return true;
                })
                ->where('email_template_editor.definitions.0.capabilities', ['headline' => true, 'button_label' => true, 'button_url' => true, 'sections' => true])
                ->where('email_template_editor.definitions.1.capabilities', ['headline' => false, 'button_label' => true, 'button_url' => false, 'sections' => false])
                ->where('email_template_editor.definitions.2.capabilities', ['headline' => false, 'button_label' => false, 'button_url' => false, 'sections' => false])
                ->where('email_template_editor.definitions.3.capabilities', ['headline' => true, 'button_label' => true, 'button_url' => true, 'sections' => true])
                ->where('email_template_editor.definitions.0.defaults.headline', 'Application Status Update')
                ->where('email_template_editor.definitions.1.defaults.headline', 'Enrollment Verified')
                ->where('email_template_editor.definitions.2.defaults.headline', 'Welcome to Preview Academy!')
                ->where('email_template_editor.definitions.2.defaults.button_label', 'Access Student Portal')
                ->where('email_template_editor.sample_tokens.student_name', 'Santos, Maria')
                ->where('email_template_editor.sample_tokens.status', 'Preview Review Pending')
                ->where('email_template_editor.sample_tokens.step_name', 'Preview Review Stage')
                ->where('email_template_editor.branding.organization_name', 'Preview Academy')
                ->where('email_template_editor.branding.logo_url', url('/preview-academy.png'))
                ->where('email_template_editor.branding.tagline', 'Learning together')
                ->where('email_template_editor.branding.theme_color', '#123456')
                ->where('email_template_editor.branding.support_email', 'support@example.test')
                ->where('email_template_editor.branding.support_phone', '+63 555 0100');
        });
});

it('renders real enrollment mail with interpolated escaped copy and no delivery or sample-record mutations', function (string $notification): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $versions = EnrollmentPolicyVersion::query()->get()->toArray();
    $policyBefore = $policy->fresh()->toArray();
    $counts = [Student::count(), StudentEnrollment::count(), User::count(), Resource::count()];
    Mail::fake();
    Notification::fake();
    Queue::fake();

    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => [
            'notification' => $notification,
            'subject' => 'Preview {{ student_name }} | {{status}} | {{ step_name }}',
            'custom_body' => "Hello {{ student_name }},\n\nEdited <script>alert(1)</script> for {{ program }} in {{ school_year }} semester {{semester}}.",
            'headline' => 'Edited <img src=x onerror=alert(2)> headline',
            'button_label' => 'Edited <script>alert(3)</script> button',
            'button_url' => '/student/preview',
            'include_details' => true,
            'include_subjects' => true,
            'include_tuition' => true,
        ],
    ])->assertOk()->assertExactJsonStructure(['subject', 'html'])
        ->assertJsonPath('subject', 'Preview Santos, Maria | Preview Review Pending | Preview Review Stage');

    $html = $response->json('html');
    expect($html)->toContain('<!DOCTYPE html', 'Dear Santos, Maria,', 'Edited', '2026-2027')
        ->not->toContain('<script>', '<img src=x', 'Hello Santos, Maria', '{{', '/signup', 'password');
    expect(mb_substr_count($html, 'Dear Santos, Maria,'))->toBe(1);
    expect(html_entity_decode(strip_tags($html)))->toContain('<script>alert(1)</script>');

    if ($notification === 'assessment') {
        expect($html)->toContain('Your Enrollment Details', 'Assessment Form PDF is attached', 'Your Student Portal account is ready', 'Access Student Portal')
            ->not->toContain('Edited &lt;img', 'SAMPLE-CS101', '/student/preview');
    } else {
        expect($html)->toContain('Approved Subjects', 'SAMPLE-CS101', 'Sample Introduction to Computing', 'Financial Summary', '15,000.00', '3,000.00');
        if ($notification === 'academic_verified') {
            expect($html)->toContain('Enrollment Verified')->not->toContain('/student/preview', 'Edited &lt;img');
        } else {
            expect($html)->toContain('Application Details', 'Preview Review Pending', 'Preview Review Stage', '/student/preview');
            expect(html_entity_decode(strip_tags($html)))->toContain('<img src=x onerror=alert(2)> headline');
        }
    }

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
    expect(EnrollmentPolicyVersion::query()->get()->toArray())->toBe($versions);
    expect($policy->fresh()->toArray())->toBe($policyBefore);
    expect([Student::count(), StudentEnrollment::count(), User::count(), Resource::count()])->toBe($counts);
})->with(EmailTemplateCatalog::ENROLLMENT_KEYS);

it('retains cashier instructions alongside catalog fallback copy when no intro override is supplied', function (?string $body): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $draft = $policy->versions()->draft()->firstOrFail();
    $configuration = $draft->configuration;
    $configuration['workflow']['steps'][0]['status'] = 'For Cashier';
    $draft->update(['configuration' => $configuration]);

    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => ['notification' => 'status_update', 'custom_body' => $body],
    ])->assertOk();

    expect($response->json('html'))->toContain(
        'Your enrollment application has advanced',
        'What This Means For You',
        'settling your downpayment or tuition balance',
    );
})->with([null, '   ']);

it('preserves an explicit status intro without adding the legacy fallback guidance', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => ['notification' => 'status_update', 'custom_body' => 'Please follow your personalized admissions instructions.'],
    ])->assertOk();

    expect($response->json('html'))->toContain('Please follow your personalized admissions instructions.')
        ->not->toContain('What This Means For You');
});

it('renders editable enrollment copy as plain text instead of Markdown links or formatting', function (string $notification): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => [
            'notification' => $notification,
            'headline' => '[Headline](https://outside.example.test/headline)',
            'custom_body' => 'Literal **bold** and [external](https://outside.example.test/) and [relative](//outside.example.test/path).',
        ],
    ])->assertOk();

    expect($response->json('html'))->toContain('Literal **bold**', '[external](https://outside.example.test/)', '[relative](//outside.example.test/path)')
        ->not->toContain('href="https://outside.example.test/', 'href="//outside.example.test/');
})->with(EmailTemplateCatalog::ENROLLMENT_KEYS);

it('renders catalog defaults when editable copy is omitted', function (string $notification, string $subject): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    app(SiteSettings::class)->organization_name = 'Preview Academy';

    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => ['notification' => $notification],
    ])->assertOk()->assertJsonPath('subject', $subject);

    expect($response->json('html'))->toContain('Dear Santos, Maria,')
        ->not->toContain('{{');
})->with([
    ['status_update', 'Enrollment Status Update: Preview Review Pending | Preview Academy'],
    ['academic_verified', 'Academic Clearance Approved - Subjects & Fees | Preview Academy'],
    ['assessment', 'Enrollment Confirmed & Assessment Certificate | Preview Academy'],
    ['custom', 'Important Update Regarding Your Application | Preview Academy'],
]);

it('keeps mandatory academic and assessment sections despite disabled status-template flags', function (string $notification, string $section): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => [
            'notification' => $notification,
            'include_details' => false,
            'include_subjects' => false,
            'include_tuition' => false,
        ],
    ])->assertOk();

    expect($response->json('html'))->toContain($section);
})->with([
    ['academic_verified', 'Approved Subjects'],
    ['academic_verified', 'Financial Summary'],
    ['assessment', 'Your Enrollment Details'],
]);

it('honors status-template section flags', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => ['notification' => 'custom', 'include_details' => false, 'include_subjects' => false, 'include_tuition' => false],
    ])->assertOk();

    expect($response->json('html'))->not->toContain('Application Details', 'Approved Subjects', 'Financial Summary')
        ->toContain('Status: Preview Review Pending', 'Stage: Preview Review Stage');
});

it('allows a view-only administrator to preview but not save', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Permission::findOrCreate(SystemManagementPermissions::viewPermission('email_templates'), 'web');
    $user->givePermissionTo(SystemManagementPermissions::viewPermission('email_templates'));
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $payload = ['step_key' => $stepKey, 'configuration' => ['notification' => 'custom']];

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), $payload)->assertOk();
    actingAs($user)->putJson(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}"), $payload)->assertForbidden();
});

it('denies preview without email-template view permission', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey, 'configuration' => ['notification' => 'status_update'],
    ])->assertForbidden();
});

it('rejects preview for missing workflow steps', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => 'nonexistent', 'configuration' => ['notification' => 'status_update'],
    ])->assertUnprocessable()->assertJsonValidationErrors('step_key');
});

it('requires a workflow step and a supported configuration before preview', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['step_key', 'configuration', 'configuration.notification']);
});

it('rejects unknown and credential-bearing notification keys', function (string $notification): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey, 'configuration' => ['notification' => $notification],
    ])->assertUnprocessable()->assertJsonValidationErrors('configuration.notification');
})->with(['unknown', 'two_factor_code', 'password_reset', 'magic_login_link', 'signup_otp', 'email_authentication_code', 'transaction_receipt']);

it('rejects non-portal and malicious CTA paths before rendering', function (string $url): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey, 'configuration' => ['notification' => 'custom', 'button_url' => $url],
    ])->assertUnprocessable()->assertJsonValidationErrors('configuration.button_url');
})->with(['https://evil.test/phish', '//evil.test', 'javascript:alert(1)', '/\\evil.test', "/student\nunsafe", '/%5cevil.test', '/student%0a']);

it('uses the shared configuration validation limits and rejects code-owned content', function (array $configuration, string $field): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);

    actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey,
        'configuration' => ['notification' => 'custom', ...$configuration],
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'subject limit' => [['subject' => str_repeat('x', 256)], 'configuration.subject'],
    'body limit' => [['custom_body' => str_repeat('x', 5001)], 'configuration.custom_body'],
    'headline limit' => [['headline' => str_repeat('x', 256)], 'configuration.headline'],
    'button label limit' => [['button_label' => str_repeat('x', 121)], 'configuration.button_label'],
    'button URL limit' => [['button_url' => '/'.str_repeat('x', 2048)], 'configuration.button_url'],
    'invalid flag' => [['include_subjects' => 'yes'], 'configuration.include_subjects'],
    'code-owned attachment' => [['attachments' => ['/private/file.pdf']], 'configuration'],
    'code-owned subjects' => [['subjects' => []], 'configuration'],
]);

it('normalizes the legacy assessment alias without writing the policy', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);
    [$policy, $stepKey] = enrollmentEmailPreviewPolicy($user);
    $draft = $policy->versions()->draft()->firstOrFail();
    $configuration = $draft->configuration;
    $configuration['workflow']['steps'][0]['actions'] = [[
        'key' => 'legacy_email', 'handler' => 'enrollment.notify',
        'configuration' => ['notification' => 'official_enrollment'],
    ]];
    $draft->update(['configuration' => $configuration]);

    actingAs($user)->get(portalUrlForAdministrators("/administrators/system-management/email-templates/{$policy->id}/edit?step_key={$stepKey}"))
        ->assertOk()->assertInertia(fn ($page) => $page->where('email_template.configuration.notification', 'assessment'));
    $response = actingAs($user)->postJson(enrollmentEmailPreviewUrl($policy), [
        'step_key' => $stepKey, 'configuration' => ['notification' => 'official_enrollment'],
    ])->assertOk();

    expect($response->json('html'))->toContain('Your Enrollment Details');
    expect($draft->fresh()->configuration)->toBe($configuration);
});
