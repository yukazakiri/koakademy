<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\StudentHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class StudentHoldService
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function officeOptions(): array
    {
        return collect(StudentHold::OFFICES)
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, StudentHold>
     */
    public function activeHoldsForStudent(int $studentId): Collection
    {
        if (! Schema::hasTable('student_holds')) {
            return new Collection;
        }

        return StudentHold::query()
            ->with(['placedBy:id,name', 'releasedBy:id,name', 'overriddenBy:id,name'])
            ->where('student_id', $studentId)
            ->active()
            ->orderByRaw("CASE WHEN severity = 'blocking' THEN 0 ELSE 1 END")
            ->orderByDesc('placed_at')
            ->get();
    }

    /**
     * @return Collection<int, StudentHold>
     */
    public function allHoldsForStudent(int $studentId): Collection
    {
        if (! Schema::hasTable('student_holds')) {
            return new Collection;
        }

        return StudentHold::query()
            ->with(['placedBy:id,name', 'releasedBy:id,name', 'overriddenBy:id,name'])
            ->where('student_id', $studentId)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('placed_at')
            ->limit(25)
            ->get();
    }

    /**
     * @return Collection<int, StudentHold>
     */
    public function blockingEnrollmentHoldsForStudent(int $studentId): Collection
    {
        if (! Schema::hasTable('student_holds')) {
            return new Collection;
        }

        return StudentHold::query()
            ->where('student_id', $studentId)
            ->blockingEnrollment()
            ->orderByDesc('placed_at')
            ->get();
    }

    /**
     * @return Collection<int, StudentHold>
     */
    public function blockingAddDropHoldsForStudent(int $studentId): Collection
    {
        if (! Schema::hasTable('student_holds')) {
            return new Collection;
        }

        return StudentHold::query()
            ->where('student_id', $studentId)
            ->blockingAddDrop()
            ->orderByDesc('placed_at')
            ->get();
    }

    /**
     * Batch-resolve active hold summaries for a list of student IDs in a single query.
     *
     * @param  list<int>  $studentIds
     * @return array<int, array{active_count: int, blocking_count: int, offices: list<string>}>
     */
    public function summariesForStudents(array $studentIds): array
    {
        $ids = array_values(array_unique(array_filter($studentIds)));
        if ($ids === [] || ! Schema::hasTable('student_holds')) {
            return [];
        }

        $holds = StudentHold::query()
            ->whereIn('student_id', $ids)
            ->active()
            ->get(['student_id', 'office', 'severity', 'blocks_enrollment']);

        $result = [];
        foreach ($holds->groupBy('student_id') as $studentId => $studentHolds) {
            $result[(int) $studentId] = [
                'active_count' => $studentHolds->count(),
                'blocking_count' => $studentHolds
                    ->filter(fn (StudentHold $h): bool => $h->severity === StudentHold::SeverityBlocking && $h->blocks_enrollment)
                    ->count(),
                'offices' => $studentHolds->pluck('office')->unique()->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function placeHold(Student $student, array $data, User $actor): StudentHold
    {
        return StudentHold::query()->create([
            'student_id' => $student->id,
            'office' => (string) ($data['office'] ?? 'registrar'),
            'hold_type' => (string) ($data['hold_type'] ?? 'general'),
            'severity' => (string) ($data['severity'] ?? StudentHold::SeverityBlocking),
            'title' => mb_trim((string) $data['title']),
            'reason' => mb_trim((string) $data['reason']),
            'blocks_enrollment' => (bool) ($data['blocks_enrollment'] ?? true),
            'blocks_add_drop' => (bool) ($data['blocks_add_drop'] ?? false),
            'blocks_grade_release' => (bool) ($data['blocks_grade_release'] ?? false),
            'status' => StudentHold::StatusActive,
            'placed_by' => $actor->id,
            'placed_at' => now(),
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    public function releaseHold(StudentHold $hold, User $actor, ?string $notes = null): StudentHold
    {
        if ($hold->status !== StudentHold::StatusActive) {
            throw new RuntimeException('Only active holds can be released.');
        }

        $hold->forceFill([
            'status' => StudentHold::StatusReleased,
            'released_by' => $actor->id,
            'released_at' => now(),
            'release_notes' => $notes !== null && mb_trim($notes) !== '' ? mb_trim($notes) : null,
        ])->save();

        return $hold->fresh(['placedBy', 'releasedBy', 'overriddenBy']);
    }

    public function overrideHold(StudentHold $hold, User $actor, string $reason): StudentHold
    {
        $cleanReason = mb_trim($reason);
        if ($cleanReason === '') {
            throw new RuntimeException('An audit reason is required to override an active student hold.');
        }

        if ($hold->status !== StudentHold::StatusActive) {
            throw new RuntimeException('Only active holds can be overridden.');
        }

        $hold->forceFill([
            'status' => StudentHold::StatusOverridden,
            'overridden_by' => $actor->id,
            'overridden_at' => now(),
            'override_reason' => $cleanReason,
        ])->save();

        return $hold->fresh(['placedBy', 'releasedBy', 'overriddenBy']);
    }

    /**
     * Enforce blocking holds before enrollment creation, supporting audited admin overrides.
     */
    public function assertNoBlockingEnrollmentHolds(
        Student $student,
        ?User $actor = null,
        bool $overrideHolds = false,
        ?string $overrideReason = null,
    ): void {
        $blocking = $this->blockingEnrollmentHoldsForStudent((int) $student->id);
        if ($blocking->isEmpty()) {
            return;
        }

        if (! $overrideHolds) {
            $summary = $blocking
                ->map(fn (StudentHold $hold): string => sprintf('[%s] %s', $hold->officeLabel(), $hold->title))
                ->implode('; ');

            throw new RuntimeException(
                sprintf('Student has %d active blocking hold(s): %s. Resolve or provide an audited override reason to proceed.', $blocking->count(), $summary),
            );
        }

        $cleanReason = mb_trim((string) $overrideReason);
        if ($cleanReason === '') {
            throw new RuntimeException('An audited override reason is required when bypassing active student holds.');
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('An authenticated administrator is required to override student holds.');
        }

        DB::transaction(function () use ($blocking, $actor, $cleanReason): void {
            foreach ($blocking as $hold) {
                $this->overrideHold($hold, $actor, $cleanReason);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeHold(StudentHold $hold): array
    {
        return [
            'id' => $hold->id,
            'student_id' => $hold->student_id,
            'student_name' => $hold->relationLoaded('student') ? ($hold->student?->full_name ?? 'Unknown Student') : null,
            'student_number' => $hold->relationLoaded('student') ? ($hold->student?->student_id ?? $hold->student_id) : null,
            'course_code' => $hold->relationLoaded('student') ? $hold->student?->Course?->code : null,
            'office' => $hold->office,
            'office_label' => $hold->officeLabel(),
            'hold_type' => $hold->hold_type,
            'severity' => $hold->severity,
            'title' => $hold->title,
            'reason' => $hold->reason,
            'blocks_enrollment' => (bool) $hold->blocks_enrollment,
            'blocks_add_drop' => (bool) $hold->blocks_add_drop,
            'blocks_grade_release' => (bool) $hold->blocks_grade_release,
            'status' => $hold->status,
            'placed_by' => $hold->placedBy?->name ?? 'Administrator',
            'placed_at' => $hold->placed_at?->toIso8601String(),
            'released_by' => $hold->releasedBy?->name,
            'released_at' => $hold->released_at?->toIso8601String(),
            'release_notes' => $hold->release_notes,
            'overridden_by' => $hold->overriddenBy?->name,
            'overridden_at' => $hold->overridden_at?->toIso8601String(),
            'override_reason' => $hold->override_reason,
            'expires_at' => $hold->expires_at?->toIso8601String(),
        ];
    }
}
