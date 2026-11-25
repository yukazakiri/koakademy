<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class AdministratorEnrollmentTransferController extends Controller
{
    public function __construct(
        private readonly EnrollmentTransferService $transferService,
    ) {}

    public function index(StudentEnrollment $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return response()->json($this->transferService->options($enrollment));
    }

    public function preview(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $validated = $request->validate([
            'transfer_type' => ['required', 'string', 'in:section_transfer,program_shift'],
            'target_section' => ['required_if:transfer_type,section_transfer', 'nullable', 'string', 'max:80'],
            'target_course_id' => ['required_if:transfer_type,program_shift', 'nullable', 'integer', 'exists:courses,id'],
            'target_year_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'force_overload' => ['boolean'],
        ]);

        try {
            if ($validated['transfer_type'] === 'section_transfer') {
                return response()->json(
                    $this->transferService->previewSectionTransfer(
                        $enrollment,
                        (string) $validated['target_section'],
                        (bool) ($validated['force_overload'] ?? false),
                    ),
                );
            }

            return response()->json(
                $this->transferService->previewProgramShift(
                    $enrollment,
                    (int) $validated['target_course_id'],
                    isset($validated['target_year_level']) ? (int) $validated['target_year_level'] : null,
                ),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function transferSection(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validate([
            'target_section' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'force_overload' => ['boolean'],
        ]);

        try {
            $log = $this->transferService->executeSectionTransfer(
                enrollment: $enrollment,
                targetSection: (string) $validated['target_section'],
                reason: (string) $validated['reason'],
                actor: $actor,
                forceOverload: (bool) ($validated['force_overload'] ?? false),
            );
            $log->load(['fromCourse:id,code,title', 'toCourse:id,code,title', 'performedBy:id,name']);

            return response()->json([
                'transfer' => $this->transferService->serialize($log),
                'message' => sprintf(
                    'Transferred student to Section %s (%d class%s reassigned).',
                    $log->to_section,
                    $log->reassigned_classes_count,
                    $log->reassigned_classes_count === 1 ? '' : 'es',
                ),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function shiftProgram(Request $request, StudentEnrollment $enrollment): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validate([
            'target_course_id' => ['required', 'integer', 'exists:courses,id'],
            'target_year_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'target_section' => ['nullable', 'string', 'max:80'],
            'reconcile_curriculum' => ['boolean'],
            'force_overload' => ['boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $log = $this->transferService->executeProgramShift(
                enrollment: $enrollment,
                targetCourseId: (int) $validated['target_course_id'],
                reason: (string) $validated['reason'],
                actor: $actor,
                targetYearLevel: isset($validated['target_year_level']) ? (int) $validated['target_year_level'] : null,
                targetSection: isset($validated['target_section']) ? (string) $validated['target_section'] : null,
                reconcileCurriculum: (bool) ($validated['reconcile_curriculum'] ?? true),
                forceOverload: (bool) ($validated['force_overload'] ?? false),
            );
            $log->load(['fromCourse:id,code,title', 'toCourse:id,code,title', 'performedBy:id,name']);

            return response()->json([
                'transfer' => $this->transferService->serialize($log),
                'message' => sprintf(
                    'Program shifted from %s to %s (%d subject%s credited).',
                    $log->fromCourse?->code ?? 'previous',
                    $log->toCourse?->code ?? 'target',
                    $log->credited_subjects_count,
                    $log->credited_subjects_count === 1 ? '' : 's',
                ),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
