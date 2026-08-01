<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssessmentExportLimitReached;
use App\Jobs\GenerateBulkAssessmentsJob;
use App\Models\AssessmentExport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class QueueAssessmentExportService
{
    /** @param array<string, mixed> $filters */
    public function queue(User $user, int $schoolId, array $filters): AssessmentExport
    {
        $lock = Cache::lock(sprintf('assessment-export:create:%d:%d', $schoolId, $user->id), 10);
        $export = $lock->get(function () use ($user, $schoolId, $filters): AssessmentExport {
            return DB::transaction(function () use ($user, $schoolId, $filters): AssessmentExport {
                DB::table((new User)->getTable())
                    ->where('id', $user->id)
                    ->select('id')
                    ->lockForUpdate()
                    ->first();

                $activeCount = AssessmentExport::withoutSchoolScope()
                    ->where('user_id', $user->id)
                    ->where('school_id', $schoolId)
                    ->whereIn('status', AssessmentExport::ACTIVE_STATUSES)
                    ->count();

                if ($activeCount >= (int) config('assessment-exports.max_active_per_user', 1)) {
                    throw new AssessmentExportLimitReached('You already have an active assessment export. Wait for it to finish or cancel it first.');
                }

                $export = AssessmentExport::withoutSchoolScope()->create([
                    'user_id' => $user->id,
                    'school_id' => $schoolId,
                    'status' => 'pending',
                    'stage' => 'queued',
                    'filters' => $filters,
                    'percentage' => 0,
                    'message' => 'Bulk assessment export queued and waiting for the PDF worker.',
                ]);

                DB::afterCommit(fn () => GenerateBulkAssessmentsJob::dispatch($export->id));

                return $export;
            });
        });

        if (! $export instanceof AssessmentExport) {
            throw new AssessmentExportLimitReached('Another assessment export is currently being queued. Please try again.');
        }

        return $export;
    }
}
