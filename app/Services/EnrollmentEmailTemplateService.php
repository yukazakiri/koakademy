<?php

declare(strict_types=1);

namespace App\Services;

use App\Enrollment\EnrollmentPolicyManager;
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
     * The draft is what operators edit; fall back to the published version when a
     * policy has never been drafted so the page still renders something editable.
     */
    private function editableVersion(EnrollmentPolicy $policy): ?EnrollmentPolicyVersion
    {
        $policy->loadMissing(['versions', 'activeVersion']);

        $draft = $policy->versions
            ->sortByDesc('version')
            ->first(fn (EnrollmentPolicyVersion $version): bool => $version->state === EnrollmentPolicyVersion::Draft);

        return $draft instanceof EnrollmentPolicyVersion ? $draft : $policy->activeVersion;
    }
}
