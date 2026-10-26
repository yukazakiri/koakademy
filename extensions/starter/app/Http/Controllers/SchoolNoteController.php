<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Http\Controllers;

use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Koakademy\SchoolNotes\Http\Requests\StoreSchoolNoteRequest;
use Koakademy\SchoolNotes\Models\SchoolNote;

final class SchoolNoteController
{
    public function index(Request $request, TenantContext $tenants): Response
    {
        Gate::authorize('viewAny', SchoolNote::class);
        $school = $this->school($request, $tenants);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('SchoolNotes/Index', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'avatar' => $user->avatar_url, 'role' => $user->role->getLabel(),
                'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            ],
            'school' => ['id' => $school->id, 'name' => $school->name],
            'notes' => SchoolNote::query()->forSchool($school)->orderByDesc('id')->limit(50)->get(['id', 'body', 'created_at']),
            'canCreate' => $user->can('create', SchoolNote::class),
        ]);
    }

    public function store(StoreSchoolNoteRequest $request, TenantContext $tenants): RedirectResponse
    {
        $school = $this->school($request, $tenants);
        /** @var User $user */
        $user = $request->user();
        SchoolNote::query()->create([
            'body' => $request->validated('body'),
            'school_id' => $school->id,
            'created_by' => $user->getAuthIdentifier(),
        ]);

        return to_route('administrators.school-notes.index');
    }

    private function school(Request $request, TenantContext $tenants): School
    {
        $school = $tenants->getCurrentSchool();
        $user = $request->user();
        abort_unless($school instanceof School && $school->is_active && $user instanceof User
            && ($user->is_super_admin || $user->hasAccessToOrganization($school)), 403);

        return $school;
    }
}
