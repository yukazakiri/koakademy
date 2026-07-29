<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enrollment\Actions\EnrollmentIntegrationActionHandler;
use App\Enrollment\Actions\EnrollmentStateActionHandler;
use App\Enrollment\EnrollmentAssignmentService;
use App\Enrollment\EnrollmentPaymentService;
use App\Enrollment\EnrollmentPolicyInheritanceService;
use App\Enrollment\EnrollmentPolicyManager;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentPolicySimulationService;
use App\Enrollment\EnrollmentStudentSynchronizationService;
use App\Enrollment\EnrollmentSubmissionContext;
use App\Enrollment\EnrollmentTransitionEngine;
use App\Enrollment\EnrollmentTuitionService;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Enrollment\Rules\ConfiguredEnrollmentRuleHandler;
use App\Enrollment\Strategies\ConfiguredAssignmentStrategy;
use App\Enrollment\Strategies\ConfiguredBillingStrategy;
use App\Models\StudentEnrollment;
use App\Observers\StudentEnrollmentPolicyObserver;
use Illuminate\Support\ServiceProvider;

final class EnrollmentPolicyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EnrollmentPolicyRegistry::class, function (): EnrollmentPolicyRegistry {
            $registry = new EnrollmentPolicyRegistry;
            $assignmentService = $this->app->make(EnrollmentAssignmentService::class);
            $tuitionService = $this->app->make(EnrollmentTuitionService::class);
            $paymentService = $this->app->make(EnrollmentPaymentService::class);
            $studentSynchronization = $this->app->make(EnrollmentStudentSynchronizationService::class);

            foreach ($this->ruleCatalog() as $key => [$label, $category]) {
                $registry->registerRule(new ConfiguredEnrollmentRuleHandler($key, $label, $category));
            }
            foreach ($this->stateActionCatalog() as $key => $label) {
                $registry->registerAction(new EnrollmentStateActionHandler($key, $label, $studentSynchronization));
            }
            foreach ($this->integrationActionCatalog() as $key => $label) {
                $registry->registerAction(new EnrollmentIntegrationActionHandler(
                    $key,
                    $label,
                    $assignmentService,
                    $paymentService,
                    $registry,
                ));
            }
            foreach ($this->assignmentCatalog() as $key => $label) {
                $registry->registerAssignmentStrategy(new ConfiguredAssignmentStrategy($key, $label, $assignmentService));
            }
            foreach ($this->billingCatalog() as $key => $label) {
                $registry->registerBillingStrategy(new ConfiguredBillingStrategy($key, $label, $tuitionService));
            }

            return $registry;
        });

        $this->app->scoped(EnrollmentPolicyResolver::class);
        $this->app->scoped(EnrollmentPolicyInheritanceService::class);
        $this->app->scoped(EnrollmentPolicyManager::class);
        $this->app->scoped(EnrollmentPolicySimulationService::class);
        $this->app->scoped(EnrollmentTransitionEngine::class);
        $this->app->scoped(EnrollmentWorkflowCoordinator::class);
        $this->app->scoped(EnrollmentSubmissionContext::class);
    }

    public function boot(): void
    {
        StudentEnrollment::observe(StudentEnrollmentPolicyObserver::class);
    }

    /** @return array<string, array{string, string}> */
    private function ruleCatalog(): array
    {
        return [
            'availability.channel' => ['Enrollment channel', 'availability'],
            'availability.date_window' => ['Enrollment date window', 'availability'],
            'eligibility.student_type' => ['Allowed student types', 'eligibility'],
            'eligibility.school' => ['Allowed schools', 'eligibility'],
            'eligibility.program' => ['Allowed programs', 'eligibility'],
            'eligibility.period' => ['Allowed periods', 'eligibility'],
            'eligibility.year_level' => ['Allowed year levels', 'eligibility'],
            'eligibility.clearance' => ['Clearance', 'eligibility'],
            'eligibility.documents' => ['Required documents', 'eligibility'],
            'eligibility.duplicate_enrollment' => ['Duplicate enrollment', 'eligibility'],
            'eligibility.prerequisites' => ['Prerequisites', 'academics'],
            'eligibility.grades' => ['Grades', 'academics'],
            'eligibility.outstanding_balance' => ['Outstanding balances', 'billing'],
            'eligibility.unit_limit' => ['Unit limits', 'academics'],
            'eligibility.class_capacity' => ['Class capacity', 'classes'],
            'eligibility.schedule_conflict' => ['Schedule conflicts', 'classes'],
            'billing.minimum_payment' => ['Minimum payment gate', 'billing'],
        ];
    }

    /** @return array<string, string> */
    private function stateActionCatalog(): array
    {
        return [
            'enrollment.change_status' => 'Change compatibility status',
            'enrollment.set_outcome' => 'Set terminal outcome',
            'enrollment.sync_student' => 'Synchronize student/account state',
        ];
    }

    /** @return array<string, string> */
    private function integrationActionCatalog(): array
    {
        return [
            'enrollment.verify_academic' => 'Record academic verification',
            'enrollment.verify_payment' => 'Record payment verification',
            'enrollment.assign_subjects' => 'Assign curriculum subjects',
            'enrollment.assign_classes' => 'Assign classes',
            'enrollment.assign_additional_fees' => 'Assign additional fees',
            'enrollment.calculate_tuition' => 'Calculate tuition',
            'enrollment.generate_assessment' => 'Generate assessment',
            'enrollment.notify' => 'Dispatch notification after commit',
        ];
    }

    /** @return array<string, string> */
    private function assignmentCatalog(): array
    {
        return [
            'assignment.manual' => 'Manual assignment',
            'assignment.recommendation' => 'Recommendation only',
            'assignment.curriculum_automatic' => 'Automatic curriculum subjects',
            'assignment.class_first_available' => 'First available class',
        ];
    }

    /** @return array<string, string> */
    private function billingCatalog(): array
    {
        return ['billing.course_rate' => 'Existing course-rate tuition and discounts'];
    }
}
