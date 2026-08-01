<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Models\AssessmentExport;
use App\Services\AssessmentExportCoordinator;
use App\Services\AssessmentExportPayloadService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final class ActiveJobsController extends Controller
{
    public function __construct(
        private readonly AssessmentExportPayloadService $payloads,
        private readonly AssessmentExportCoordinator $coordinator,
        private readonly TenantContext $tenants,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['jobs' => [], 'count' => 0, 'has_active' => false]);
        }

        $schoolId = $this->tenants->getCurrentSchoolId();
        if ($schoolId === null) {
            return response()->json(['jobs' => [], 'count' => 0, 'has_active' => false]);
        }
        $terminalSince = now()->subMinutes((int) config('assessment-exports.visibility.terminal_minutes', 15));
        $exports = AssessmentExport::withoutSchoolScope()
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->whereNull('dismissed_at')
            ->where(function ($query) use ($terminalSince): void {
                $query->whereIn('status', AssessmentExport::ACTIVE_STATUSES)
                    ->orWhere('updated_at', '>=', $terminalSince);
            })
            ->latest()
            ->limit(20)
            ->get();
        $jobs = $exports->map(fn (AssessmentExport $export): array => $this->payloads->make($export))->all();

        return response()->json([
            'jobs' => $jobs,
            'count' => count($jobs),
            'has_active' => $exports->contains(fn (AssessmentExport $export): bool => in_array($export->status, AssessmentExport::ACTIVE_STATUSES, true)),
        ]);
    }

    public function show(Request $request, AssessmentExport $assessmentExport): JsonResponse
    {
        $this->authorizeOwner($request, $assessmentExport);

        return response()->json(['job' => $this->payloads->make($assessmentExport, true)]);
    }

    public function retry(Request $request, AssessmentExport $assessmentExport): JsonResponse
    {
        $this->authorizeOwner($request, $assessmentExport);
        abort_unless(in_array($assessmentExport->status, ['failed', 'cancelled'], true), 409, 'Only failed or cancelled exports can be retried.');

        $retryState = DB::transaction(function () use ($assessmentExport): array {
            $export = AssessmentExport::query()->lockForUpdate()->findOrFail($assessmentExport->id);
            $export->items()->whereIn('status', ['failed', 'cancelled', 'processing'])->update([
                'status' => 'pending',
                'failed_at' => null,
                'completed_at' => null,
                'updated_at' => now(),
            ]);
            $needsPreparation = ! $export->items()->exists();
            $hasIncompleteItems = $export->items()->where('status', 'pending')->exists();
            $export->forceFill([
                'status' => 'processing',
                'stage' => $needsPreparation ? 'preparing' : ($hasIncompleteItems ? 'rendering' : 'merging'),
                'message' => $needsPreparation
                    ? 'Retrying assessment export preparation...'
                    : ($hasIncompleteItems ? 'Retrying failed assessment forms...' : 'Retrying the final PDF merge...'),
                'error_code' => null,
                'error_message' => null,
                'error_context' => null,
                'cancel_requested_at' => null,
                'completed_at' => null,
                'failed_at' => null,
                'dismissed_at' => null,
                'terminal_notified_at' => null,
                'merge_dispatched_at' => null,
                'batch_id' => null,
            ])->save();

            return ['needs_preparation' => $needsPreparation, 'has_incomplete_items' => $hasIncompleteItems];
        });

        if ($retryState['needs_preparation']) {
            GenerateBulkAssessmentsJob::dispatch($assessmentExport->id);
        } elseif ($retryState['has_incomplete_items']) {
            $this->coordinator->dispatchPendingItems($assessmentExport->id);
        } else {
            $this->coordinator->synchronize($assessmentExport->id);
        }

        return response()->json(['job' => $this->payloads->make($assessmentExport->refresh())], 202);
    }

    public function cancel(Request $request, AssessmentExport $assessmentExport): JsonResponse
    {
        $this->authorizeOwner($request, $assessmentExport);
        if ($assessmentExport->isTerminal()) {
            return response()->json(['job' => $this->payloads->make($assessmentExport)]);
        }

        DB::transaction(function () use ($assessmentExport): void {
            $export = AssessmentExport::query()->lockForUpdate()->findOrFail($assessmentExport->id);
            $export->forceFill([
                'status' => 'cancelling',
                'stage' => 'cancelling',
                'message' => 'Cancelling assessment export...',
                'cancel_requested_at' => now(),
            ])->save();
            $export->items()->whereIn('status', ['pending', 'processing', 'failed'])->update([
                'status' => 'cancelled',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        if ($assessmentExport->batch_id !== null) {
            Bus::findBatch($assessmentExport->batch_id)?->cancel();
        }
        $this->coordinator->synchronize($assessmentExport->id);

        return response()->json(['job' => $this->payloads->make($assessmentExport->refresh())], 202);
    }

    public function dismiss(Request $request, AssessmentExport $assessmentExport): JsonResponse
    {
        $this->authorizeOwner($request, $assessmentExport);
        abort_unless($assessmentExport->isTerminal(), 409, 'An active export cannot be dismissed.');

        $assessmentExport->forceFill(['dismissed_at' => now()])->save();

        return response()->json(['success' => true]);
    }

    private function authorizeOwner(Request $request, AssessmentExport $export): void
    {
        $user = $request->user();
        $schoolId = $this->tenants->getCurrentSchoolId();
        abort_unless(
            $user !== null
            && $user->canAccessAdminPortal()
            && (int) $export->user_id === (int) $user->id
            && $schoolId !== null
            && (int) $export->school_id === $schoolId,
            404,
        );
    }
}
