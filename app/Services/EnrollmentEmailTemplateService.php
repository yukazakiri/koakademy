<?php

declare(strict_types=1);

namespace App\Services;

use App\Email\EmailTemplateCatalog;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enums\UserRole;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicyVersion;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Reads and writes the `enrollment.notify` action configuration that backs the
 * enrollment email templates. Templates live inside the workflow definition of
 * an enrollment policy version, so the Pipeline Studio canvas and the dedicated
 * Email Templates page both read and write through this service.
 */
final class EnrollmentEmailTemplateService
{
    public const HANDLER = 'enrollment.notify';

    /** @var list<array<string, mixed>> */
    public const NOTIFICATION_TYPES = [
        [
            'value' => 'assessment',
            'label' => 'Official enrollment & assessment certificate (with PDF)',
        ],
        [
            'value' => 'academic_verified',
            'label' => 'Academic clearance notice (Subjects & fees)',
        ],
        [
            'value' => 'status_update',
            'label' => 'Status change progression notice',
        ],
        [
            'value' => 'custom',
            'label' => 'Customized email template',
        ],
    ];

    public function __construct(private readonly EnrollmentPolicyManager $manager) {}

    /**
     * Every workflow step that dispatches (or could dispatch) an email, flattened
     * into a list an administrator can scan without opening the canvas.
     *
     * @return list<array<string, mixed>>
     */
    public function templatesFor(EnrollmentPolicy $policy): array
    {
        $version = $this->editableVersion($policy);

        if (! $version instanceof EnrollmentPolicyVersion) {
            return [];
        }

        $steps = self::arrayAt(self::configurationArray($version), ['workflow', 'steps']);

        $templates = [];
        foreach ($steps as $position => $step) {
            if (! is_array($step)) {
                continue;
            }

            $stepKey = is_string($step['key'] ?? null) ? $step['key'] : (string) $position;
            $actions = self::arrayAt($step, ['actions']);
            $notifyIndex = self::notifyIndex($actions);

            $notify = $notifyIndex !== null ? $actions[$notifyIndex] : null;
            $template = self::arrayAt(is_array($notify) ? $notify : [], ['configuration']);

            // The integration handler also accepts this legacy assessment alias.
            if (($template['notification'] ?? null) === 'official_enrollment') {
                $template['notification'] = 'assessment';
            }

            $templates[] = [
                'policy_id' => $policy->id,
                'policy_name' => $policy->name,
                'version_id' => $version->id,
                'version_state' => $version->state,
                'step_key' => $stepKey,
                'step_label' => is_string($step['label'] ?? null) ? $step['label'] : $stepKey,
                'step_status' => is_string($step['status'] ?? null) ? $step['status'] : null,
                'terminal' => (bool) ($step['terminal'] ?? false),
                'position' => $position,
                'configured' => $notifyIndex !== null,
                'action_key' => is_array($notify) && is_string($notify['key'] ?? null)
                    ? $notify['key']
                    : "notify_{$stepKey}",
                'notification' => is_string($template['notification'] ?? null)
                    ? $template['notification']
                    : null,
                'configuration' => $template,
            ];
        }

        return $templates;
    }

    /**
     * Pick the built-in template a workflow step should use by default.
     *
     * A step whose key already names an enrollment template (for example
     * `academic_verified`) gets that template; the terminal enrolment milestone gets
     * the final assessment certificate; everything else gets the status update.
     *
     * @param  array<array-key, mixed>  $step
     */
    public function defaultNotificationForStep(array $step): string
    {
        $key = is_string($step['key'] ?? null) ? $step['key'] : '';

        if (in_array($key, EmailTemplateCatalog::ENROLLMENT_KEYS, true)) {
            return $key;
        }

        if (($step['terminal'] ?? false) === true) {
            return 'assessment';
        }

        return 'status_update';
    }

    /**
     * Fill in a missing `enrollment.notify` action (or an empty one) so every
     * workflow step has a working built-in template. Existing explicit choices are
     * always preserved.
     */
    public function backfillDefaultsFor(EnrollmentPolicy $policy): int
    {
        $version = $this->editableVersion($policy);

        if (! $version instanceof EnrollmentPolicyVersion) {
            return 0;
        }

        $configuration = self::configurationArray($version);
        $steps = self::arrayAt($configuration, ['workflow', 'steps']);
        $changed = 0;

        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $stepKey = is_string($step['key'] ?? null) ? $step['key'] : (string) $index;
            $actions = array_values(self::arrayAt($step, ['actions']));
            $notifyIndex = self::notifyIndex($actions);

            if ($notifyIndex !== null && self::arrayAt($actions[$notifyIndex], ['configuration']) !== []) {
                continue;
            }

            $notification = $this->defaultNotificationForStep($step);
            $default = EmailTemplateCatalog::defaultFor($notification) ?? [];

            $action = $notifyIndex !== null && is_array($actions[$notifyIndex]) ? $actions[$notifyIndex] : [];
            $action['handler'] = self::HANDLER;
            $action['key'] = is_string($action['key'] ?? null)
                ? $action['key']
                : "notify_{$stepKey}";
            $action['configuration'] = [
                'notification' => $notification,
                'subject' => $default['subject'] ?? '',
                'custom_body' => $default['intro'] ?? '',
            ];

            if ($notifyIndex !== null) {
                $actions[$notifyIndex] = $action;
            } else {
                $actions[] = $action;
            }

            $step['actions'] = $actions;
            $steps[$index] = $step;
            $changed++;
        }

        if ($changed === 0) {
            return 0;
        }

        $configuration['workflow'] = [
            ...self::arrayAt($configuration, ['workflow']),
            'steps' => $steps,
        ];

        $this->manager->saveDraft(
            $policy,
            self::normalizeConfiguration($configuration),
            'Backfilled the built-in enrollment email templates.',
            $this->resolveAuthor($policy),
        );

        return $changed;
    }

    /**
     * Persist a single step's email template into the policy draft, creating the
     * `enrollment.notify` action when the step does not have one yet.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function update(
        EnrollmentPolicy $policy,
        string $stepKey,
        array $configuration,
        ?string $changeNotes,
        User $author,
    ): EnrollmentPolicyVersion {
        $version = $this->editableVersion($policy);

        if (! $version instanceof EnrollmentPolicyVersion) {
            throw ValidationException::withMessages([
                'policy' => 'This policy has no configuration to update.',
            ]);
        }

        $next = self::configurationArray($version);
        $steps = self::arrayAt($next, ['workflow', 'steps']);

        $targetIndex = null;
        foreach ($steps as $index => $step) {
            $candidate = is_array($step) && is_string($step['key'] ?? null) ? $step['key'] : (string) $index;
            if ($candidate === $stepKey) {
                $targetIndex = $index;

                break;
            }
        }

        if ($targetIndex === null) {
            throw ValidationException::withMessages([
                'step_key' => "The workflow step [{$stepKey}] does not exist in this policy.",
            ]);
        }

        $step = is_array($steps[$targetIndex]) ? $steps[$targetIndex] : [];
        $actions = array_values(self::arrayAt($step, ['actions']));
        $notifyIndex = self::notifyIndex($actions);

        if ($notifyIndex === null) {
            $actions[] = [
                'key' => "notify_{$stepKey}",
                'handler' => self::HANDLER,
                'configuration' => $configuration,
            ];
        } else {
            $existing = is_array($actions[$notifyIndex]) ? $actions[$notifyIndex] : [];
            $actions[$notifyIndex] = [
                ...$existing,
                'configuration' => [
                    ...self::arrayAt($existing, ['configuration']),
                    ...$configuration,
                ],
            ];
        }

        $step['actions'] = $actions;
        $steps[$targetIndex] = $step;

        $next['workflow'] = [
            ...self::arrayAt($next, ['workflow']),
            'steps' => $steps,
        ];

        return $this->manager->saveDraft(
            $policy,
            self::normalizeConfiguration(Arr::undot($next)),
            $changeNotes ?? 'Email template updated from the Email Templates page.',
            $author,
        );
    }

    /**
     * @param  array<array-key, mixed>  $configuration
     * @return array<string, mixed>
     */
    private static function normalizeConfiguration(array $configuration): array
    {
        $normalized = [];

        foreach ($configuration as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    /**
     * The policy version stores its workflow as a JSON document.
     *
     * @return array<array-key, mixed>
     */
    private static function configurationArray(EnrollmentPolicyVersion $version): array
    {
        return self::arrayAt($version->configuration, []);
    }

    /**
     * Read a nested value out of untyped policy configuration, guaranteeing an array.
     *
     * @param  array<int, string>  $path
     * @return array<array-key, mixed>
     */
    private static function arrayAt(mixed $source, array $path): array
    {
        $value = $source;

        foreach ($path as $segment) {
            if (! is_array($value)) {
                return [];
            }

            $value = $value[$segment] ?? null;
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Locate the `enrollment.notify` action inside a step's action list.
     *
     * @param  array<array-key, mixed>  $actions
     */
    private static function notifyIndex(array $actions): ?int
    {
        foreach ($actions as $index => $action) {
            if (is_array($action) && ($action['handler'] ?? null) === self::HANDLER) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Attribute the backfill to whoever authored the version being edited, falling
     * back to an existing administrator when the author record is unavailable.
     */
    private function resolveAuthor(EnrollmentPolicy $policy): User
    {
        $version = $this->editableVersion($policy);
        $author = $version?->author;

        if ($author instanceof User) {
            return $author;
        }

        $fallback = User::query()->where('role', UserRole::SuperAdmin)->first()
            ?? User::query()->first();

        if ($fallback instanceof User) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'policy' => 'No administrator exists to attribute this backfill to.',
        ]);
    }

    /**
     * The draft is what operators edit; fall back to the published version when a
     * policy has never been drafted so the page still renders something editable.
     */
    private function editableVersion(EnrollmentPolicy $policy): ?EnrollmentPolicyVersion
    {
        $versions = EnrollmentPolicyVersion::query()
            ->where('enrollment_policy_id', $policy->getKey())
            ->latest('version')
            ->get();

        // Always query fresh: a cached relation would serve pre-save data, so a
        // read immediately after a write would not see the new configuration.
        $draft = $versions->firstWhere('state', EnrollmentPolicyVersion::Draft);

        if ($draft instanceof EnrollmentPolicyVersion) {
            return $draft;
        }

        $published = $versions->firstWhere('state', EnrollmentPolicyVersion::Published);

        return $published instanceof EnrollmentPolicyVersion
            ? $published
            : $versions->first();
    }
}
