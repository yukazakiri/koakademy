<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\AdminExecutiveAgent;
use App\Ai\Agents\BursarFinanceAgent;
use App\Ai\Agents\CampusSupportAgent;
use App\Ai\Agents\FacultyCopilotAgent;
use App\Ai\Agents\RegistrarAuditAgent;
use App\Ai\Agents\StudentAdvisorAgent;
use App\Features\Contracts\FeatureToggle;
use App\Features\Toggles\AiAdminExecutive;
use App\Features\Toggles\AiFacultyAssistant;
use App\Features\Toggles\AiFinanceAssistant;
use App\Features\Toggles\AiHelpDeskAssistant;
use App\Features\Toggles\AiRegistrarAuditor;
use App\Features\Toggles\AiStudentAdvisor;
use App\Models\User;
use Laravel\Ai\Contracts\Agent;
use Laravel\Pennant\Feature;

/**
 * Resolves which AI agents the current user may use.
 *
 * The feature flags are the authority. UI surfaces filter their agent lists
 * with the same map so an agent cannot be presented when its backend request
 * would be rejected.
 */
final class AiAgentAccess
{
    /** @var list<string> */
    public const array AGENT_KEYS = [
        'admin_executive',
        'student_advisor',
        'faculty_copilot',
        'registrar_auditor',
        'bursar_finance',
        'campus_support',
    ];

    /** @var list<string> */
    public const array ADMIN_AGENT_KEYS = [
        'admin_executive',
        'registrar_auditor',
        'bursar_finance',
        'campus_support',
    ];

    /** @var list<string> */
    public const array FILAMENT_AGENT_KEYS = [
        'registrar_auditor',
        'bursar_finance',
        'campus_support',
    ];

    /** @var array<string, class-string<FeatureToggle>> */
    private const array AGENT_FEATURES = [
        'admin_executive' => AiAdminExecutive::class,
        'student_advisor' => AiStudentAdvisor::class,
        'faculty_copilot' => AiFacultyAssistant::class,
        'registrar_auditor' => AiRegistrarAuditor::class,
        'bursar_finance' => AiFinanceAssistant::class,
        'campus_support' => AiHelpDeskAssistant::class,
    ];

    /** @var array<string, class-string<Agent>> */
    private const array AGENT_CLASSES = [
        'admin_executive' => AdminExecutiveAgent::class,
        'student_advisor' => StudentAdvisorAgent::class,
        'faculty_copilot' => FacultyCopilotAgent::class,
        'registrar_auditor' => RegistrarAuditAgent::class,
        'bursar_finance' => BursarFinanceAgent::class,
        'campus_support' => CampusSupportAgent::class,
    ];

    /**
     * @return class-string<FeatureToggle>|null
     */
    public function featureClassFor(string $agentKey): ?string
    {
        return self::AGENT_FEATURES[$agentKey] ?? null;
    }

    public function keyForClass(string $agentClass): ?string
    {
        $flipped = array_flip(self::AGENT_CLASSES);

        return $flipped[$agentClass] ?? null;
    }

    public function isEnabled(string $agentKey, ?User $user): bool
    {
        $featureClass = $this->featureClassFor($agentKey);

        if ($featureClass === null || ! $user instanceof User) {
            return false;
        }

        return Feature::for($user)->active($featureClass);
    }

    /**
     * @param  list<string>|null  $agentKeys
     * @return list<string>
     */
    public function enabledKeys(?User $user, ?array $agentKeys = null): array
    {
        $agentKeys ??= self::AGENT_KEYS;

        return array_values(array_filter(
            $agentKeys,
            fn (string $agentKey): bool => $this->isEnabled($agentKey, $user),
        ));
    }

    /**
     * @param  list<string>|null  $agentKeys
     */
    public function hasAnyEnabled(?User $user, ?array $agentKeys = null): bool
    {
        return $this->enabledKeys($user, $agentKeys) !== [];
    }

    /**
     * Availability for every known agent, keyed by agent role.
     *
     * @return array<string, bool>
     */
    public function flagsFor(?User $user): array
    {
        $flags = [];

        foreach (self::AGENT_KEYS as $agentKey) {
            $flags[$agentKey] = $this->isEnabled($agentKey, $user);
        }

        return $flags;
    }
}
