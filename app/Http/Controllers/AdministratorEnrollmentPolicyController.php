<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentPolicyCompiler;
use App\Enrollment\EnrollmentPolicyInheritanceService;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentPolicyRolloutService;
use App\Enrollment\EnrollmentPolicySimulationService;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Http\Requests\Administrators\ActivateEnrollmentPolicyEngineRequest;
use App\Http\Requests\Administrators\DeactivateEnrollmentPolicyEngineRequest;
use App\Http\Requests\Administrators\ImportEnrollmentPolicyRequest;
use App\Http\Requests\Administrators\PublishEnrollmentPolicyRequest;
use App\Http\Requests\Administrators\ReopenEnrollmentRequest;
use App\Http\Requests\Administrators\ReviewEnrollmentRequirementRequest;
use App\Http\Requests\Administrators\RollbackEnrollmentPolicyRequest;
use App\Http\Requests\Administrators\SimulateEnrollmentPolicyRequest;
use App\Http\Requests\Administrators\StoreEnrollmentPolicyRequest;
use App\Http\Requests\Administrators\TransitionEnrollmentRequest;
use App\Http\Requests\Administrators\UpdateEnrollmentPolicyDraftRequest;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentPolicyVersion;
use App\Models\EnrollmentRequirement;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdministratorEnrollmentPolicyController extends Controller
{
    public function compatibility(EnrollmentPolicyRolloutService $rollout): JsonResponse
    {
        $this->authorize('viewEnrollmentPipeline', \App\Models\GeneralSetting::class);

        return response()->json($rollout->report());
    }

    public function inheritance(
        EnrollmentPolicy $policy,
        EnrollmentPolicyInheritanceService $inheritance,
    ): JsonResponse {
        $this->authorize('viewEnrollmentPipeline', \App\Models\GeneralSetting::class);

        return response()->json($inheritance->describe($policy));
    }

    public function activate(
        ActivateEnrollmentPolicyEngineRequest $request,
        EnrollmentPolicyRolloutService $rollout,
    ): RedirectResponse {
        $report = $rollout->report();
        $versionId = $report['global_version_id'] ?? null;
        $simulation = is_int($versionId)
            ? $request->session()->get("enrollment_policy_simulations.{$versionId}")
            : null;
        $sessionChecksum = is_array($simulation) ? ($simulation['checksum'] ?? null) : $simulation;
        $requestedChecksum = $request->validated('simulation_checksum');

        if (! is_string($sessionChecksum)
            || ! is_string($requestedChecksum)
            || ! hash_equals($sessionChecksum, $requestedChecksum)) {
            return Redirect::back()->withErrors([
                'rollout' => 'Run a successful simulation of the active global policy before activation.',
            ]);
        }

        $rollout->activate();

        return Redirect::back()->with('success', 'Enrollment policies are active for future enrollments.');
    }

    public function deactivate(
        DeactivateEnrollmentPolicyEngineRequest $request,
        EnrollmentPolicyRolloutService $rollout,
    ): RedirectResponse {
        $rollout->deactivate();

        return Redirect::back()->with('success', 'Future enrollments will use the legacy workflow. Existing policy enrollments remain pinned.');
    }

    public function store(StoreEnrollmentPolicyRequest $request, EnrollmentPolicyManager $manager): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $policy = $manager->create($request->validated(), $user);

        return Redirect::back()->with('success', "Draft policy [{$policy->name}] created.");
    }

    public function clonePolicy(
        StoreEnrollmentPolicyRequest $request,
        EnrollmentPolicy $policy,
        EnrollmentPolicyManager $manager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $source = $policy->versions()->draft()->latest('version')->first() ?? $policy->activeVersion;
        abort_unless($source instanceof EnrollmentPolicyVersion, 422, 'The source policy has no configuration to clone.');
        $clone = $manager->create([
            ...$request->validated(),
            'configuration' => $source->configuration,
            'change_notes' => "Cloned from {$policy->name} version {$source->version}.",
        ], $user);

        return Redirect::back()->with('success', "Draft policy [{$clone->name}] cloned.");
    }

    public function updateDraft(
        UpdateEnrollmentPolicyDraftRequest $request,
        EnrollmentPolicy $policy,
        EnrollmentPolicyManager $manager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();
        $manager->saveDraft($policy, $validated['configuration'], $validated['change_notes'] ?? null, $user);

        return Redirect::back()->with('success', 'Policy draft saved.');
    }

    public function simulate(
        SimulateEnrollmentPolicyRequest $request,
        EnrollmentPolicy $policy,
        EnrollmentPolicyVersion $version,
        EnrollmentPolicyResolver $resolver,
        EnrollmentPolicyManager $manager,
        EnrollmentPolicySimulationService $simulation,
    ): JsonResponse {
        abort_unless($version->enrollment_policy_id === $policy->id, 404);
        $validated = $request->validated();
        $context = $this->context($validated);
        $previewVersion = clone $version;
        $previewVersion->configuration = $manager->publicationCandidate($policy, $version, $version->configuration);
        $compiled = $resolver->resolvePreview($policy, $previewVersion, $context);
        $request->session()->put("enrollment_policy_simulations.{$version->id}", [
            'checksum' => $compiled->checksum,
            'context' => $validated,
        ]);

        return response()->json($simulation->simulateCompiled($compiled, $context));
    }

    public function publish(
        PublishEnrollmentPolicyRequest $request,
        EnrollmentPolicy $policy,
        EnrollmentPolicyVersion $version,
        EnrollmentPolicyResolver $resolver,
        EnrollmentPolicyManager $manager,
    ): RedirectResponse {
        abort_unless($version->enrollment_policy_id === $policy->id, 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $simulation = $request->session()->get("enrollment_policy_simulations.{$version->id}");
        $sessionChecksum = is_array($simulation) ? ($simulation['checksum'] ?? null) : $simulation;
        $simulationContext = is_array($simulation) ? ($simulation['context'] ?? null) : null;
        if (! is_array($simulationContext)) {
            return Redirect::back()->withErrors(['simulation_checksum' => 'Run simulation again before publishing this draft.']);
        }

        $previewVersion = clone $version;
        $previewVersion->configuration = $manager->publicationCandidate($policy, $version, $version->configuration);
        $compiled = $resolver->resolvePreview($policy, $previewVersion, $this->context($simulationContext));
        if (! is_string($sessionChecksum)
            || ! hash_equals($compiled->checksum, $sessionChecksum)
            || ! hash_equals($compiled->checksum, $request->validated('simulation_checksum'))) {
            return Redirect::back()->withErrors(['simulation_checksum' => 'Run simulation again before publishing this draft.']);
        }

        $manager->publish($policy, $version, $user);

        return Redirect::back()->with('success', 'Policy published for future enrollments.');
    }

    public function rollback(
        RollbackEnrollmentPolicyRequest $request,
        EnrollmentPolicy $policy,
        EnrollmentPolicyVersion $version,
        EnrollmentPolicyManager $manager,
    ): RedirectResponse {
        abort_unless($version->enrollment_policy_id === $policy->id, 404);
        $manager->rollback($policy, $version);

        return Redirect::back()->with('success', 'Active policy version changed for future enrollments only.');
    }

    public function import(
        ImportEnrollmentPolicyRequest $request,
        EnrollmentPolicyCompiler $compiler,
        EnrollmentPolicyManager $manager,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();
        $compiled = $compiler->compile([['version_id' => 0, 'configuration' => $validated['configuration']]]);
        $manager->create([...$validated, 'configuration' => $compiled->configuration], $user);

        return Redirect::back()->with('success', 'Configuration imported as a new draft.');
    }

    public function export(EnrollmentPolicy $policy, EnrollmentPolicyVersion $version): StreamedResponse
    {
        $this->authorize('viewEnrollmentPipeline', \App\Models\GeneralSetting::class);
        abort_unless($version->enrollment_policy_id === $policy->id, 404);

        return response()->streamDownload(function () use ($policy, $version): void {
            echo json_encode([
                'format' => 'koakademy.enrollment-policy',
                'schema_version' => $version->schema_version,
                'name' => $policy->name,
                'configuration' => $version->configuration,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, "enrollment-policy-{$policy->id}-v{$version->version}.json", ['Content-Type' => 'application/json']);
    }

    public function transition(
        TransitionEnrollmentRequest $request,
        StudentEnrollment $enrollment,
        EnrollmentWorkflowCoordinator $coordinator,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();
        $result = $coordinator->transition(
            $enrollment,
            $user,
            $validated['transition_key'] ?? null,
            $validated['payload'] ?? [],
            $validated['idempotency_key'],
        );

        return response()->json($result);
    }

    public function reopen(
        ReopenEnrollmentRequest $request,
        StudentEnrollment $enrollment,
        EnrollmentWorkflowCoordinator $coordinator,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();

        return response()->json($coordinator->reopen(
            $enrollment,
            $user,
            $validated['target_step_key'] ?? null,
            $validated['reason'],
            $validated['idempotency_key'],
        ));
    }

    public function reviewRequirement(
        ReviewEnrollmentRequirementRequest $request,
        StudentEnrollment $enrollment,
        EnrollmentRequirement $requirement,
        EnrollmentWorkflowCoordinator $coordinator,
    ): JsonResponse {
        abort_unless((int) $requirement->student_enrollment_id === (int) $enrollment->id, 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();

        $updated = $validated['action'] === 'waive'
            ? $coordinator->waiveRequirement(
                $requirement,
                $user,
                (string) $validated['reason'],
                $validated['idempotency_key'],
            )
            : $coordinator->verifyRequirement(
                $requirement,
                $user,
                $validated['evidence_path'] ?? null,
                $validated['idempotency_key'],
            );

        return response()->json([
            'id' => $updated->id,
            'key' => $updated->requirement_key,
            'status' => $updated->status,
            'verified_at' => $updated->verified_at,
            'waived_at' => $updated->waived_at,
            'waiver_reason' => $updated->waiver_reason,
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function context(array $validated): EnrollmentContext
    {
        if (isset($validated['student_enrollment_id'])) {
            $enrollment = StudentEnrollment::query()->findOrFail($validated['student_enrollment_id']);

            return EnrollmentContext::fromEnrollment($enrollment, $validated['channel']);
        }

        return new EnrollmentContext(
            schoolId: $validated['school_id'] ?? null,
            studentType: $validated['student_type'] ?? null,
            courseId: $validated['course_id'] ?? null,
            schoolYear: $validated['school_year'] ?? null,
            semester: $validated['semester'] ?? null,
            yearLevel: $validated['year_level'] ?? null,
            channel: $validated['channel'],
            facts: $validated['facts'] ?? [],
        );
    }
}
