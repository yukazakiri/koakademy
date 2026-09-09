<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentTuition;

/**
 * Keeps a Finance-approved assessment revision frozen when enrollment subjects or
 * rates change. Academic edits can proceed, but assessment charges remain
 * unchanged until Finance reviews and applies a new assessment revision preview.
 *
 * Legacy assessment adjustments (no linked active revision) keep their additive
 * behavior: recalculated base plus stored reconciliation delta.
 */
final readonly class TuitionAdjustmentRecalculationService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function preserveFinanceAdjustment(?StudentTuition $existingTuition, array $attributes): array
    {
        if (! $existingTuition instanceof StudentTuition) {
            return $attributes;
        }

        $assessmentAdjustment = round((float) ($existingTuition->assessment_adjustment ?? 0), 2);
        $hasActiveRevision = $existingTuition->active_revision_id !== null;

        // Only revision-locked assessments freeze. Legacy adjustments predate
        // immutable revisions and stay additive so academic edits still apply.
        if ($hasActiveRevision) {
            $frozenTotal = (float) $existingTuition->overall_tuition;
            $recalculatedBase = round((float) ($attributes['overall_tuition'] ?? 0), 2);
            $hasDiscrepancy = abs($recalculatedBase - $frozenTotal) > 0.005;

            return [
                ...$attributes,
                'overall_tuition' => $frozenTotal,
                'total_tuition' => (float) $existingTuition->total_tuition,
                'total_lectures' => (float) $existingTuition->total_lectures,
                'total_laboratory' => (float) $existingTuition->total_laboratory,
                'total_miscelaneous_fees' => (float) $existingTuition->total_miscelaneous_fees,
                'total_balance' => (float) $existingTuition->total_balance,
                'assessment_adjustment' => $assessmentAdjustment,
                'needs_finance_review' => $hasDiscrepancy,
            ];
        }

        if (abs($assessmentAdjustment) < 0.005) {
            return $attributes;
        }

        $recalculatedBase = round((float) ($attributes['overall_tuition'] ?? 0), 2);
        $assessedTotal = round($recalculatedBase + $assessmentAdjustment, 2);

        return [
            ...$attributes,
            'overall_tuition' => $assessedTotal,
            'total_balance' => $assessedTotal,
            'assessment_adjustment' => $assessmentAdjustment,
        ];
    }
}
