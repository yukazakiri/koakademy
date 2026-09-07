<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssessmentExportLimitReached;
use App\Jobs\GenerateRegulatoryReportExportJob;
use App\Models\AssessmentExport;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class QueueRegulatoryReportExportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $definition
     */
    public function queue(User $user, int $schoolId, string $reportKey, array $filters, array $definition): AssessmentExport
    {
        $lock = Cache::lock(sprintf('regulatory-report-export:create:%d:%d', $schoolId, $user->id), 10);
        $export = $lock->get(function () use ($user, $schoolId, $reportKey, $filters, $definition): ?AssessmentExport {
            return DB::transaction(function () use ($user, $schoolId, $reportKey, $filters, $definition): AssessmentExport {
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
                    throw new AssessmentExportLimitReached('You already have an active export. Wait for it to finish or dismiss it first.');
                }

                $prefix = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($definition['file_name_prefix'] ?? $reportKey)) ?: $reportKey;
                $export = AssessmentExport::withoutSchoolScope()->create([
                    'user_id' => $user->id,
                    'school_id' => $schoolId,
                    'status' => 'pending',
                    'stage' => 'queued',
                    'filters' => [
                        ...$filters,
                        'export_type' => 'regulatory_report',
                        'report_key' => $reportKey,
                        'report_title' => (string) ($definition['title'] ?? $reportKey),
                        'file_name_prefix' => $prefix,
                    ],
                    'percentage' => 0,
                    'message' => 'Regulatory Excel export queued and waiting for the report worker.',
                ]);

                DB::afterCommit(fn (): mixed => GenerateRegulatoryReportExportJob::dispatch($export->id));

                return $export;
            });
        });

        if (! $export instanceof AssessmentExport) {
            throw new AssessmentExportLimitReached('Another export is currently being queued. Please try again.');
        }

        return $export;
    }
}
