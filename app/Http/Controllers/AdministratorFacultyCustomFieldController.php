<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\StoreFacultyCustomFieldDefinitionRequest;
use App\Http\Requests\Administrators\UpdateFacultyCustomFieldDefinitionRequest;
use App\Models\FacultyCustomFieldDefinition;
use App\Models\GeneralSetting;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class AdministratorFacultyCustomFieldController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function store(StoreFacultyCustomFieldDefinitionRequest $request): RedirectResponse
    {
        $schoolId = $this->schoolId();
        $data = $request->validated();
        $exists = FacultyCustomFieldDefinition::query()->where('school_id', $schoolId)->where('key', $data['key'])->exists();
        if ($exists) {
            return back()->withErrors(['key' => 'This field key is already used by the active school.']);
        }
        FacultyCustomFieldDefinition::query()->create([
            ...$data,
            'school_id' => $schoolId,
            'options' => array_values($data['options'] ?? []),
            'source_header_aliases' => array_values($data['source_header_aliases'] ?? []),
            'is_active' => true,
        ]);

        return back()->with('success', 'Faculty field added successfully.');
    }

    public function update(UpdateFacultyCustomFieldDefinitionRequest $request, FacultyCustomFieldDefinition $facultyCustomFieldDefinition): RedirectResponse
    {
        abort_unless($facultyCustomFieldDefinition->school_id === $this->schoolId(), 404);
        $data = $request->validated();
        $facultyCustomFieldDefinition->update([
            ...$data,
            'options' => array_values($data['options'] ?? []),
            'source_header_aliases' => array_values($data['source_header_aliases'] ?? []),
        ]);

        return back()->with('success', 'Faculty field updated successfully.');
    }

    public function destroy(FacultyCustomFieldDefinition $facultyCustomFieldDefinition): RedirectResponse
    {
        Gate::authorize('updateFacultyFields', GeneralSetting::class);
        abort_unless($facultyCustomFieldDefinition->school_id === $this->schoolId(), 404);
        $facultyCustomFieldDefinition->update(['is_active' => false]);

        return back()->with('success', 'Faculty field removed from future templates.');
    }

    private function schoolId(): int
    {
        $schoolId = $this->tenantContext->getCurrentSchoolId();
        abort_if($schoolId === null, 422, 'Choose an active school before configuring faculty fields.');

        return $schoolId;
    }
}
