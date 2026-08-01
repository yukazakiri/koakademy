<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\AssessmentFormPdfRenderer;
use App\Models\AssessmentExportItem;
use App\Models\StudentEnrollment;
use App\Services\AssessmentExportArtifactService;
use App\Services\AssessmentExportCoordinator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class GenerateBulkAssessmentItemJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;

    public int $tries;

    public bool $failOnTimeout = true;

    public function __construct(public int $itemId)
    {
        $this->timeout = (int) config('assessment-exports.render.timeout');
        $this->tries = (int) config('assessment-exports.render.tries');
        $this->onConnection((string) config('assessment-exports.connection'));
        $this->onQueue((string) config('assessment-exports.render_queue'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return (array) config('assessment-exports.render.backoff', [30, 120, 300]);
    }

    public function handle(
        AssessmentFormPdfRenderer $renderer,
        AssessmentExportArtifactService $artifacts,
        AssessmentExportCoordinator $coordinator,
    ): void {
        $item = AssessmentExportItem::withoutSchoolScope()->findOrFail($this->itemId);
        $export = $item->export()
            ->withoutSchoolScope()
            ->forSchool((int) $item->school_id)
            ->firstOrFail();

        if ($item->status === 'completed' || $item->status === 'skipped' || $item->status === 'cancelled') {
            $coordinator->synchronize($export->id);

            return;
        }
        if ($this->batch()?->cancelled() === true || $export->cancel_requested_at !== null) {
            $this->markCancelled($item);
            $coordinator->synchronize($export->id);

            return;
        }

        $claimed = DB::transaction(function () use ($item): bool {
            $locked = AssessmentExportItem::withoutSchoolScope()
                ->forSchool((int) $item->school_id)
                ->lockForUpdate()
                ->findOrFail($item->id);
            if (in_array($locked->status, ['completed', 'skipped', 'cancelled'], true)) {
                return false;
            }
            if ($locked->status === 'processing' && $locked->updated_at?->isAfter(now()->subSeconds($this->timeout * 2))) {
                return false;
            }
            $locked->forceFill([
                'status' => 'processing',
                'attempts' => $locked->attempts + 1,
                'started_at' => $locked->started_at ?? now(),
                'failed_at' => null,
            ])->save();

            return true;
        });

        if (! $claimed) {
            return;
        }

        $localPath = null;
        try {
            $item->refresh();
            $export = $item->export()
                ->withoutSchoolScope()
                ->forSchool((int) $item->school_id)
                ->firstOrFail();
            $enrollment = StudentEnrollment::withoutSchoolScope()
                ->forSchool((int) $export->school_id)
                ->withTrashed()
                ->with([
                    'student.Course',
                    'course',
                    'subjectsEnrolled.subject.course',
                    'subjectsEnrolled.class.Schedule.room',
                    'subjectsEnrolled.class.Room',
                    'studentTuition',
                    'additionalFees',
                ])
                ->find($item->enrollment_id);

            $skipReason = $this->invalidEnrollmentReason($enrollment, (int) $export->school_id);
            if ($skipReason !== null) {
                $item->forceFill([
                    'status' => 'skipped',
                    'error_code' => 'invalid_enrollment',
                    'error_message' => $skipReason,
                    'error_context' => ['enrollment_id' => $item->enrollment_id, 'sequence' => $item->sequence],
                    'completed_at' => now(),
                ])->save();
                $coordinator->synchronize($export->id);

                return;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'assessment_export_item_');
            if ($temporary === false) {
                throw new RuntimeException('Unable to allocate a temporary assessment PDF path.');
            }
            $localPath = $temporary.'.pdf';
            rename($temporary, $localPath);
            $renderer->render($enrollment, $localPath);

            $export->refresh();
            if ($this->batch()?->cancelled() === true || $export->cancel_requested_at !== null) {
                $this->markCancelled($item);
                $coordinator->synchronize($export->id);

                return;
            }

            $disk = (string) config('assessment-exports.disk');
            $storagePath = sprintf(
                'assessment-exports/%d/%d/%s/items/%06d.pdf',
                $export->school_id,
                $export->user_id,
                $export->id,
                $item->sequence,
            );
            $metadata = $artifacts->storeValidatedPdf($disk, $storagePath, $localPath);

            DB::transaction(function () use ($item, $disk, $storagePath, $metadata): void {
                $locked = AssessmentExportItem::withoutSchoolScope()
                    ->forSchool((int) $item->school_id)
                    ->lockForUpdate()
                    ->findOrFail($item->id);
                if (in_array($locked->status, ['completed', 'skipped', 'cancelled'], true)) {
                    return;
                }
                $locked->forceFill([
                    'status' => 'completed',
                    'artifact_disk' => $disk,
                    'artifact_path' => $storagePath,
                    'page_count' => $metadata['page_count'],
                    'byte_size' => $metadata['byte_size'],
                    'checksum' => $metadata['checksum'],
                    'error_code' => null,
                    'error_message' => null,
                    'error_context' => null,
                    'completed_at' => now(),
                    'failed_at' => null,
                ])->save();
            });
            $coordinator->synchronize($export->id);
        } catch (Throwable $throwable) {
            $this->recordAttemptFailure($throwable);
            Log::error('Assessment export item attempt failed', [
                'export_id' => $export->id,
                'item_id' => $item->id,
                'enrollment_id' => $item->enrollment_id,
                'sequence' => $item->sequence,
                'attempt' => $item->attempts,
                'exception' => $throwable,
            ]);
            throw $throwable;
        } finally {
            if ($localPath !== null) {
                @unlink($localPath);
            }
        }
    }

    public function failed(?Throwable $throwable): void
    {
        if ($throwable === null) {
            return;
        }

        $item = AssessmentExportItem::withoutSchoolScope()->find($this->itemId);
        if ($item === null || in_array($item->status, ['completed', 'skipped', 'cancelled'], true)) {
            return;
        }

        $context = $this->errorContext($item, $throwable);
        $item->forceFill([
            'status' => 'failed',
            'error_code' => 'assessment_render_failed',
            'error_message' => mb_substr($throwable->getMessage() ?: 'Assessment rendering failed.', 0, 500),
            'error_context' => $context,
            'failed_at' => now(),
        ])->save();
        app(AssessmentExportCoordinator::class)->synchronize($item->assessment_export_id);
    }

    private function invalidEnrollmentReason(?StudentEnrollment $enrollment, int $schoolId): ?string
    {
        if ($enrollment === null) {
            return 'Enrollment no longer exists.';
        }
        if ($enrollment->student === null || ! $enrollment->student->exists) {
            return 'Student record is missing.';
        }
        if ($enrollment->course === null || ! $enrollment->course->exists) {
            return 'Course record is missing.';
        }
        if ((int) $enrollment->school_id !== $schoolId || (int) $enrollment->student->school_id !== $schoolId || (int) $enrollment->course->school_id !== $schoolId) {
            return 'Enrollment relationships do not belong to the export school.';
        }
        if (! $this->belongsToSchoolWhenSet($enrollment->student->Course, $schoolId)) {
            return 'The student course does not belong to the export school.';
        }
        foreach ($enrollment->subjectsEnrolled as $subjectEnrollment) {
            if (! $this->belongsToSchoolWhenSet($subjectEnrollment, $schoolId)
                || ! $this->belongsToSchoolWhenSet($subjectEnrollment->subject, $schoolId)
                || ! $this->belongsToSchoolWhenSet($subjectEnrollment->subject?->course, $schoolId)
                || ! $this->belongsToSchoolWhenSet($subjectEnrollment->class, $schoolId)) {
                return 'An enrolled subject or class does not belong to the export school.';
            }
        }

        return null;
    }

    private function belongsToSchoolWhenSet(?object $model, int $schoolId): bool
    {
        if ($model === null || ! method_exists($model, 'getAttribute')) {
            return true;
        }

        $relatedSchoolId = $model->getAttribute('school_id');

        return $relatedSchoolId === null || (int) $relatedSchoolId === $schoolId;
    }

    private function markCancelled(AssessmentExportItem $item): void
    {
        $item->forceFill(['status' => 'cancelled', 'completed_at' => now()])->save();
    }

    private function recordAttemptFailure(Throwable $throwable): void
    {
        $item = AssessmentExportItem::withoutSchoolScope()->find($this->itemId);
        if ($item === null || in_array($item->status, ['completed', 'skipped', 'cancelled'], true)) {
            return;
        }
        $item->forceFill([
            'status' => 'pending',
            'error_code' => 'assessment_render_attempt_failed',
            'error_message' => mb_substr($throwable->getMessage() ?: 'Assessment rendering attempt failed.', 0, 500),
            'error_context' => $this->errorContext($item, $throwable),
        ])->save();
    }

    /** @return array<string, mixed> */
    private function errorContext(AssessmentExportItem $item, Throwable $throwable): array
    {
        return [
            'export_id' => $item->assessment_export_id,
            'item_id' => $item->id,
            'enrollment_id' => $item->enrollment_id,
            'sequence' => $item->sequence,
            'attempt' => $item->attempts,
            'exception_class' => $throwable::class,
            'file' => basename($throwable->getFile()),
            'line' => $throwable->getLine(),
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
