<?php

declare(strict_types=1);

use App\Ai\Mcp\ExternalMcpToolResolver;
use App\Enums\UserRole;
use App\Features\Toggles\AiAdminExecutive;
use App\Features\Toggles\AiFinanceAssistant;
use App\Features\Toggles\AiHelpDeskAssistant;
use App\Features\Toggles\AiRegistrarAuditor;
use App\Features\Toggles\AiStudentAdvisor;
use App\Features\Toggles\McpExternalIntegrations;
use App\Features\Toggles\McpServerAccess;
use App\Models\McpServer;
use App\Models\School;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureToggleRegistry;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Models\Conversation;
use Laravel\Pennant\Feature;

beforeEach(function (): void {
    $this->withoutVite();
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();
});

it('registers the ai and mcp flags with restricted role boundaries', function (): void {
    expect(FeatureToggleRegistry::classForKey('ai-admin-executive'))->toBe(AiAdminExecutive::class)
        ->and(FeatureToggleRegistry::classForKey('mcp-server-access'))->toBe(McpServerAccess::class)
        ->and(FeatureToggleRegistry::classForKey('mcp-external-integrations'))->toBe(McpExternalIntegrations::class);

    $adminExecutive = FeatureToggleRegistry::definition('ai-admin-executive');
    $serverAccess = FeatureToggleRegistry::definition('mcp-server-access');
    $externalIntegrations = FeatureToggleRegistry::definition('mcp-external-integrations');

    expect($adminExecutive['tier'])->toBe('restricted')
        ->and($adminExecutive['default_enabled'])->toBeFalse()
        ->and($adminExecutive['roles'])->toContain(UserRole::Admin->value)
        ->and($adminExecutive['roles'])->not->toContain(UserRole::Student->value)
        ->and($serverAccess['tier'])->toBe('restricted')
        ->and($serverAccess['default_enabled'])->toBeFalse()
        ->and($serverAccess['roles'])->toBe(array_map(fn (UserRole $role): string => $role->value, UserRole::cases()))
        ->and($externalIntegrations['tier'])->toBe('restricted')
        ->and($externalIntegrations['default_enabled'])->toBeFalse()
        ->and($externalIntegrations['roles'])->toContain(UserRole::Admin->value)
        ->and($externalIntegrations['roles'])->not->toContain(UserRole::Student->value);
});

it('seeds only missing ai and mcp definitions in legacy mode', function (): void {
    DB::table('feature_definitions')->where('key', 'student-classes')->update(['mode' => 'enforced']);
    DB::table('feature_definitions')->whereIn('key', [
        'ai-admin-executive',
        'mcp-server-access',
        'mcp-external-integrations',
    ])->delete();

    $migration = require database_path('migrations/2026_10_23_130000_seed_ai_mcp_feature_definitions.php');
    $migration->up();

    expect(DB::table('feature_definitions')->whereIn('key', [
        'ai-admin-executive',
        'mcp-server-access',
        'mcp-external-integrations',
    ])->where('mode', 'legacy')->count())->toBe(3)
        ->and(DB::table('feature_definitions')->where('key', 'student-classes')->value('mode'))->toBe('enforced');

    $migration->up();

    expect(DB::table('feature_definitions')->whereIn('key', [
        'ai-admin-executive',
        'mcp-server-access',
        'mcp-external-integrations',
    ])->count())->toBe(3);
});

it('preserves legacy access for the new ai and mcp flags', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = User::factory()->create(['role' => UserRole::Student]);

    // The old discovery migration seeded a false Pennant row for every class
    // on this test database. Legacy mode must not treat that artifact as an
    // administrator explicitly switching the brand-new features off.
    expect(Feature::for($admin)->active(AiAdminExecutive::class))->toBeTrue()
        ->and(Feature::for($student)->active(AiAdminExecutive::class))->toBeFalse()
        ->and(Feature::for($admin)->active(McpExternalIntegrations::class))->toBeTrue()
        ->and(Feature::for($student)->active(McpExternalIntegrations::class))->toBeFalse()
        ->and(Feature::for($student)->active(McpServerAccess::class))->toBeTrue();
});

it('shares per-agent availability with the portal shell', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Feature::activateForEveryone(AiAdminExecutive::class);
    Feature::activateForEveryone(AiStudentAdvisor::class);
    Feature::activateForEveryone(AiRegistrarAuditor::class);
    Feature::activateForEveryone(AiFinanceAssistant::class);
    Feature::activateForEveryone(AiHelpDeskAssistant::class);
    Feature::flushCache();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/ai'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('featureFlags.ai.any', true)
            ->where('featureFlags.ai.agents.admin_executive', true)
            ->where('featureFlags.ai.agents.registrar_auditor', true)
            ->where('featureFlags.ai.agents.bursar_finance', true)
            ->where('featureFlags.ai.agents.campus_support', true)
            ->where('featureFlags.ai.agents.student_advisor', false)
            ->where('featureFlags.ai.agents.faculty_copilot', false));
});

it('blocks disabled admin agents while preserving readable history', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $conversation = Conversation::query()->create([
        'id' => (string) str()->uuid(),
        'participant_id' => $admin->id,
        'participant_type' => $admin->getMorphClass(),
        'title' => 'Prior Enrollment Review',
    ]);

    DB::table('feature_definitions')
        ->where('key', 'ai-admin-executive')
        ->update(['mode' => 'enforced', 'enabled' => false]);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators('/administrators/ai/chat'), [
            'agent' => 'admin_executive',
            'message' => 'Summarize enrollment.',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/agent-tools?agent=admin_executive'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/ai?conversation='.$conversation->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('administrators/ai/index')
            ->where('initialConversation.id', $conversation->id));
});

it('keeps other agents available when only the executive flag is disabled', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Feature::activateForEveryone(AiRegistrarAuditor::class);
    Feature::flushCache();

    DB::table('feature_definitions')
        ->where('key', 'ai-admin-executive')
        ->update(['mode' => 'enforced', 'enabled' => false]);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();

    $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators('/administrators/ai/agent-tools?agent=registrar_auditor'))
        ->assertOk();
});

it('does not resolve outbound mcp tools without the external integrations flag', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $school->id,
    ]);

    McpServer::query()->create([
        'name' => 'tracker',
        'description' => 'Issue tracker integration',
        'transport' => 'web',
        'url' => 'https://mcp.example.test/tracker',
        'auth_type' => 'none',
        'enabled_tools' => ['create-issue'],
        'allowed_agents' => ['admin_executive'],
        'is_active' => true,
    ]);

    DB::table('feature_definitions')
        ->where('key', 'mcp-external-integrations')
        ->update(['mode' => 'enforced', 'enabled' => false]);
    app(FeatureAccessService::class)->reset();
    Feature::flushCache();

    $this->actingAs($admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(app(ExternalMcpToolResolver::class)->forAgent('admin_executive'))->toBe([])
        ->and(collect(DB::getQueryLog())->pluck('query')->filter(
            fn (string $query): bool => str_contains($query, 'mcp_servers'),
        ))->toBeEmpty();
});
