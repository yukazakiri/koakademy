<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AssessmentExport;
use App\Models\AssessmentExportItem;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\AssessmentExportCoordinator;
use App\Services\AssessmentExportNotificationService;
use App\Services\EnrollmentPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GenerateBulkAssessmentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public bool $failOnTimeout = true;

    public function __construct(public string $exportId)
    {
        $this->timeout = (int) config('assessment-exports.prepare.timeout');
        $this->tries = (int) config('assessment-exports.prepare.tries');
        $this->onConnection((string) config('assessment-exports.connection'));
        $this->onQueue((string) config('assessment-exports.render_queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('assessment-exports.prepare.backoff', [15, 60]);
    }

    public function uniqueId(): string
    {
        return 'prepare-'.$this->exportId;
    }

    public function handle(
        EnrollmentPipelineService $pipeline,
        AssessmentExportCoordinator $coordinator,
        AssessmentExportNotificationService $notifications,
    ): void {
        $export = AssessmentExport::withoutSchoolScope()->findOrFail($this->exportId);
        if ($export->isTerminal() || $export->cancel_requested_at !== null) {
            return;
        }

        $export->forceFill([
            'status' => 'processing',
            'stage' => 'preparing',
            'percentage' => 2,
            'message' => 'Selecting enrolled students and preparing assessment jobs...',
            'started_at' => $export->started_at ?? now(),
        ])->save();
        $coordinator->broadcast($export->refresh());

        if ($export->items()->withoutSchoolScope()->forSchool((int) $export->school_id)->exists()) {
            $itemCount = $export->items()->withoutSchoolScope()->forSchool((int) $export->school_id)->count();
            $export->forceFill([
                'total_count' => $itemCount,
                'status' => 'processing',
                'stage' => 'rendering',
                'percentage' => 5,
                'message' => sprintf('Prepared %d assessment jobs.', $itemCount),
            ])->save();
            $coordinator->dispatchPendingItems($export->id);

            return;
        }

        $filters = $export->filters;
        $query = StudentEnrollment::withoutSchoolScope()
            ->forSchool((int) $export->school_id)
            ->where('student_enrollment.school_year', (string) $filters['school_year'])
            ->where('student_enrollment.semester', (int) $filters['semester'])
            ->where('student_enrollment.status', $pipeline->getCashierVerifiedStatus());
        $textCast = match ($query->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => 'CHAR',
            default => 'TEXT',
        };
        $query
            ->leftJoin((new Course)->getTable().' as export_courses', function ($join) use ($textCast): void {
                $join->on(
                    DB::raw("CAST(export_courses.id AS {$textCast})"),
                    '=',
                    DB::raw("CAST(student_enrollment.course_id AS {$textCast})"),
                );
            })
            ->leftJoin((new Student)->getTable().' as export_students', function ($join) use ($textCast): void {
                $join->on(
                    DB::raw("CAST(export_students.id AS {$textCast})"),
                    '=',
                    DB::raw("CAST(student_enrollment.student_id AS {$textCast})"),
                );
            })
            ->select('student_enrollment.id')
            ->orderBy('export_courses.code')
            ->orderBy('student_enrollment.academic_year')
            ->orderBy('export_students.last_name')
            ->orderBy('student_enrollment.id');

        if (($filters['include_deleted'] ?? false) === true) {
            $query->withTrashed();
        }
        if (($filters['course_id'] ?? null) !== null) {
            $query->whereRaw(
                "CAST(student_enrollment.course_id AS {$textCast}) = ?",
                [(string) $filters['course_id']],
            );
        }
        if (($filters['year_level'] ?? null) !== null) {
            $query->where('student_enrollment.academic_year', (int) $filters['year_level']);
        }
        if (($filters['student_limit'] ?? null) !== null) {
            $query->limit((int) $filters['student_limit']);
        }

        $enrollmentIds = $query->pluck('student_enrollment.id')->map(static fn ($id): int => (int) $id)->all();
        $sequence = count($enrollmentIds);

        $prepared = DB::transaction(function () use ($export, $enrollmentIds, $sequence): bool {
            $locked = AssessmentExport::withoutSchoolScope()
                ->forSchool((int) $export->school_id)
                ->lockForUpdate()
                ->findOrFail($export->id);
            if ($locked->cancel_requested_at !== null || $locked->isTerminal()) {
                return false;
            }

            foreach (array_chunk($enrollmentIds, 250, true) as $chunk) {
                $rows = [];
                foreach ($chunk as $offset => $enrollmentId) {
                    $rows[] = [
                        'assessment_export_id' => $export->id,
                        'school_id' => $export->school_id,
                        'enrollment_id' => $enrollmentId,
                        'sequence' => $offset + 1,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                AssessmentExportItem::withoutSchoolScope()->insert($rows);
            }

            $locked->forceFill([
                'total_count' => $sequence,
                'message' => $sequence === 0 ? 'No enrolled students matched the selected filters.' : sprintf('Prepared %d assessment jobs.', $sequence),
                'percentage' => $sequence === 0 ? 100 : 5,
                'status' => $sequence === 0 ? 'completed' : 'processing',
                'stage' => $sequence === 0 ? 'no_matches' : 'rendering',
                'completed_at' => $sequence === 0 ? now() : null,
            ])->save();

            return true;
        });
        $export->refresh();
        if (! $prepared) {
            $coordinator->synchronize($export->id);

            return;
        }

        if ($sequence === 0) {
            $coordinator->broadcast($export->refresh());
            $notifications->sendTerminal($export);

            return;
        }

        $coordinator->dispatchPendingItems($export->id);
    }

    public function failed(?Throwable $throwable): void
    {
        if ($throwable !== null) {
            app(AssessmentExportCoordinator::class)->fail($this->exportId, 'preparing', $throwable);
        }
    }
}
