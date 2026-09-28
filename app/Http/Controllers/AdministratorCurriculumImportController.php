<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CurriculumImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdministratorCurriculumImportController extends Controller
{
    public function stage(Request $request, CurriculumImportService $imports): JsonResponse
    {
        $actor = $this->actor($request);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        return response()->json($imports->review($imports->stage($actor, $validated['file']), $actor));
    }

    public function show(Request $request, string $import, CurriculumImportService $imports): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($actor->hasRole('super_admin') || $actor->can('View:Course'), 403);

        return response()->json($imports->review($imports->find($import, $actor), $actor));
    }

    public function approve(Request $request, string $import, CurriculumImportService $imports): JsonResponse
    {
        $actor = $this->actor($request);
        $draft = $imports->find($import, $actor);
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['existing', 'new'])],
            'course_id' => ['required_if:mode,existing', 'nullable', 'integer', Rule::prohibitedIf($request->input('mode') !== 'existing')],
            'code' => ['required_if:mode,new', 'nullable', 'string', 'max:255', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'title' => ['required_if:mode,new', 'nullable', 'string', 'max:255', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'department_id' => ['required_if:mode,new', 'nullable', 'integer', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'course_type_id' => ['required_if:mode,new', 'nullable', 'integer', 'exists:course_types,id', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'curriculum_kind' => ['required_if:mode,new', 'nullable', Rule::in(['program', 'tesda_qualification']), Rule::prohibitedIf($request->input('mode') !== 'new')],
            'duration_hours' => ['required_if:curriculum_kind,tesda_qualification', 'nullable', 'integer', 'between:1,65535', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'duration_years' => ['required_if:curriculum_kind,tesda_qualification', 'nullable', 'numeric', 'between:0.5,10', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'internship_hours' => ['required_if:curriculum_kind,tesda_qualification', 'nullable', 'integer', 'between:0,65535', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'bundled_qualifications' => ['required_if:curriculum_kind,tesda_qualification', 'nullable', 'array', 'min:1', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'bundled_qualifications.*' => ['string', 'max:150'],
            'advanced_topics' => ['required_if:curriculum_kind,tesda_qualification', 'nullable', 'string', 'max:5000', Rule::prohibitedIf($request->input('mode') !== 'new')],
            'rows' => ['required', 'array', 'size:'.count($draft->draft['rows'])],
            'rows.*.skip' => ['required', 'boolean'],
            'rows.*.code' => ['required', 'string', 'max:255'],
            'rows.*.title' => ['required', 'string', 'max:255'],
            'rows.*.units' => ['nullable', 'integer', 'between:0,12'],
            'rows.*.lecture' => ['nullable', 'integer', 'between:0,40'],
            'rows.*.laboratory' => ['nullable', 'integer', 'between:0,40'],
            'rows.*.hours_confirmed' => ['nullable', 'boolean'],
            'rows.*.prerequisites' => ['nullable', 'string', 'max:500'],
        ]);
        $imports->approve($draft, $actor, $validated);

        return response()->json($imports->review($draft->refresh(), $actor));
    }

    public function apply(Request $request, string $import, CurriculumImportService $imports): JsonResponse
    {
        $actor = $this->actor($request);

        return response()->json($imports->apply($imports->find($import, $actor), $actor));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->canAccessAdminPortal(), 403);

        return $actor;
    }
}
