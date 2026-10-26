<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TuitionAdjustmentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ApplyTuitionAdjustmentBatchTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Apply approved tuition fee deltas through the tuition adjustment workflow. Requires manage_tuition_fees permission, explicit approval, a stable batch UUID, and the current enrollment, tuition ID and state_hash from the tuition workspace. Positive amounts increase fees; negative amounts reduce fees. Reuse the batch key on retries. The entire batch rolls back if any row is rejected.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->can('manage_tuition_fees'), 403);

        $validated = $request->validate([
            'batch_key' => 'required|uuid',
            'adjustments' => 'required|array|min:1|max:100',
            'adjustments.*.student_id' => 'required|integer|min:1',
            'adjustments.*.enrollment_id' => 'required|integer|min:1',
            'adjustments.*.tuition_id' => 'required|integer|min:1|distinct',
            'adjustments.*.state_hash' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
            'adjustments.*.amount' => 'required|numeric|decimal:0,2|not_in:0',
            'adjustments.*.reason' => 'required|string|min:3|max:1000',
            'justification' => 'required|string|min:3|max:1000',
        ]);

        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        abort_unless($schoolId && app(TenantContext::class)->canAccessOrganization($schoolId), 403);
        $batchKey = 'ai_'.mb_substr(hash('sha256', $user->id.'|'.$validated['batch_key']), 0, 32);
        $service = app(TuitionAdjustmentService::class);

        $result = DB::transaction(function () use ($validated, $user, $schoolId, $batchKey, $service): array {
            $rows = [];
            foreach ($validated['adjustments'] as $index => $adjustment) {
                $enrollment = StudentEnrollment::query()
                    ->where('school_id', $schoolId)
                    ->where('student_id', $adjustment['student_id'])
                    ->whereHas('student', fn ($query) => $query->where('school_id', $schoolId))
                    ->lockForUpdate()
                    ->find($adjustment['enrollment_id']);
                $tuition = $enrollment instanceof StudentEnrollment
                    ? StudentTuition::query()->where('enrollment_id', $enrollment->id)
                        ->where('student_id', $adjustment['student_id'])->lockForUpdate()->find($adjustment['tuition_id'])
                    : null;

                if (! $tuition instanceof StudentTuition) {
                    throw ValidationException::withMessages(["adjustments.{$index}" => 'The student tuition account is unavailable in the current school.']);
                }

                $clientRowId = (string) $tuition->id;
                $existing = TuitionAdjustment::query()
                    ->where('idempotency_key', hash('sha256', $batchKey.'|'.$clientRowId))->first();
                $current = $existing?->before_snapshot ?? $service->serialize($enrollment);
                $total = round((float) $current['total_fees'] + (float) $adjustment['amount'], 2);
                if ($total < 0) {
                    throw ValidationException::withMessages(["adjustments.{$index}.amount" => 'The adjusted total fees cannot be negative.']);
                }
                if ($existing && (abs((float) $existing->after_snapshot['total_fees'] - $total) > 0.009
                    || $existing->reason !== mb_trim($adjustment['reason']))) {
                    throw ValidationException::withMessages(['batch_key' => 'This batch key has already been used for different adjustments. Use a new key for a new operation.']);
                }

                $rows[] = [
                    'client_row_id' => $clientRowId,
                    'enrollment_id' => $enrollment->id,
                    'tuition_id' => $tuition->id,
                    'state_hash' => $adjustment['state_hash'],
                    'total_fees' => $total,
                    'opening_paid' => (float) $current['paid'],
                    'balance' => round($total - (float) $current['paid'], 2),
                    'reason' => $adjustment['reason'],
                ];
            }

            $result = $service->applyBatch($user, $batchKey, $validated['justification'], $rows, 'ai_bursar_adjustment');
            foreach ($result['rows'] as $row) {
                if ($row['status'] === 'rejected') {
                    throw ValidationException::withMessages(['adjustments' => 'No adjustments were committed. '.$row['message']]);
                }
            }

            return $result;
        }, 3);

        return json_encode([
            'success' => true,
            'batch_key' => $validated['batch_key'],
            'committed_count' => count(array_filter($result['rows'], fn (array $row): bool => $row['status'] === 'recorded')),
            'duplicate_count' => count(array_filter($result['rows'], fn (array $row): bool => $row['status'] === 'duplicate')),
            ...$result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_key' => $schema->string()->required()->description('Stable UUID for this approved operation. Reuse exactly on retries.'),
            'adjustments' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'student_id' => $s->integer()->required(),
                    'enrollment_id' => $s->integer()->required(),
                    'tuition_id' => $s->integer()->required(),
                    'state_hash' => $s->string()->required()->description('Current state_hash from the tuition workspace. Never invent it.'),
                    'amount' => $s->number()->required()->description('Signed change to total fees in pesos, with at most two decimal places.'),
                    'reason' => $s->string()->required(),
                ])
            )->required(),
            'justification' => $schema->string()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $batchCount = count($request['adjustments'] ?? []);

        return Approval::required(
            "Committing {$batchCount} financial adjustments will mutate official student ledger balances and generate immutable accounting audit trails."
        );
    }
}
