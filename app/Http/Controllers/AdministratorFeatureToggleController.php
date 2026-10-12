<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\UpdateFeaturePolicyRequest;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureManagementService;
use App\Services\FeatureToggleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorFeatureToggleController extends Controller
{
    public function __construct(
        private readonly FeatureManagementService $management,
        private readonly FeatureAccessService $access,
        private readonly FeatureToggleService $legacy,
    ) {}

    public function index(Request $request): Response
    {
        $this->actor($request);

        return Inertia::render('administrators/feature-toggles/index', [
            'features' => $this->management->catalog(),
            'roles' => array_map(fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->getLabel()], UserRole::cases()),
            'schools' => DB::table('schools')->orderBy('name')->get(['id', 'name']),
            'groups' => $this->groups(), 'audit' => $this->auditEntries(),
        ]);
    }

    public function update(UpdateFeaturePolicyRequest $request, string $featureKey): RedirectResponse
    {
        $this->management->updatePolicy($featureKey, $request->validated(), $this->actor($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Feature policy saved.']);
    }

    public function details(Request $request, string $featureKey): JsonResponse
    {
        $this->actor($request);

        return response()->json(['assignments' => $this->management->assignments($featureKey), 'audit' => $this->auditEntries($featureKey)]);
    }

    public function impact(UpdateFeaturePolicyRequest $request, string $featureKey): JsonResponse
    {
        return response()->json($this->management->impact($featureKey, $this->actor($request), $request->validated()));
    }

    public function assign(Request $request, string $featureKey): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['user', 'role', 'school', 'group'])],
            'subject_key' => ['required', 'string', 'max:64'], 'effect' => ['required', Rule::in(['allow', 'deny'])],
        ]);
        $this->management->assign($featureKey, $data['subject_type'], $data['subject_key'], $data['effect'], $actor);

        return back()->with('flash', ['type' => 'success', 'message' => 'Target saved.']);
    }

    public function removeAssignment(Request $request, string $featureKey, int $assignment): RedirectResponse
    {
        $this->management->removeAssignment($featureKey, $assignment, $this->actor($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Target removed; inherited policy restored.']);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $this->actor($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $term = $data['search'] ?? '';
        $users = User::query()->when($term !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'email', 'role', 'school_id']);

        return response()->json(['users' => $users]);
    }

    public function preview(Request $request, string $featureKey): JsonResponse
    {
        $this->actor($request);
        $this->management->known($featureKey);
        $data = $request->validate(['user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')]]);
        $user = User::findOrFail($data['user_id']);

        return response()->json([
            'current' => $this->management->currentState($featureKey, $user),
            'managed' => $this->access->decide($featureKey, $user)->toArray(),
            'mode' => $this->access->definition($featureKey)?->mode,
        ]);
    }

    public function saveGroup(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:feature_groups,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('feature_groups', 'name')->ignore($request->input('id'))],
            'description' => ['nullable', 'string', 'max:1000'], 'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'user_ids' => ['present', 'array', 'max:500'], 'user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ]);
        if (! empty($data['school_id']) && User::whereIn('id', $data['user_ids'])->where(fn ($query) => $query->whereNull('school_id')->orWhere('school_id', '!=', $data['school_id']))->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['user_ids' => 'Every member must belong to the selected school.']);
        }
        DB::transaction(function () use ($data, $actor): void {
            $record = ['name' => $data['name'], 'description' => $data['description'] ?? null, 'school_id' => $data['school_id'] ?? null, 'updated_at' => now()];
            $before = ! empty($data['id']) ? DB::table('feature_groups')->where('id', $data['id'])->lockForUpdate()->first() : null;
            $id = $before !== null ? $before->id : DB::table('feature_groups')->insertGetId([...$record, 'created_at' => now()]);
            if ($before) {
                DB::table('feature_groups')->where('id', $id)->update($record);
            }
            DB::table('feature_group_user')->where('feature_group_id', $id)->delete();
            foreach ($data['user_ids'] as $userId) {
                DB::table('feature_group_user')->insert(['feature_group_id' => $id, 'user_id' => $userId]);
            }
            $this->management->audit($actor, null, 'beta_group_saved', $before ? (array) $before : null, [...$record, 'user_ids' => $data['user_ids']]);
        });
        $this->management->flush();

        return back()->with('flash', ['type' => 'success', 'message' => 'Beta group saved.']);
    }

    public function toggle(Request $request, string $featureKey): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->management->known($featureKey);
        if ($this->access->definition($featureKey)?->mode === 'enforced') {
            $data = $this->management->policyPayload($this->access->definition($featureKey));
            $data['enabled'] = ! $data['enabled'];
            $this->management->updatePolicy($featureKey, $data, $actor);
        } else {
            $this->legacy->isGloballyActivated($featureKey) ? $this->legacy->deactivateGlobally($featureKey) : $this->legacy->activateGlobally($featureKey);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Feature updated.']);
    }

    public function activateForUser(Request $request, string $featureKey): JsonResponse
    {
        return $this->setUser($request, $featureKey, 'allow');
    }

    public function deactivateForUser(Request $request, string $featureKey): JsonResponse
    {
        return $this->setUser($request, $featureKey, 'deny');
    }

    public function purgeOverrides(Request $request, string $featureKey): JsonResponse
    {
        $actor = $this->actor($request);
        $this->management->known($featureKey);
        DB::transaction(function () use ($featureKey, $actor): void {
            $query = DB::table('feature_assignments')->where('feature_key', $featureKey)->where('subject_type', 'user');
            $count = $query->count();
            $query->delete();
            $this->management->audit($actor, $featureKey, 'user_assignments_cleared', ['count' => $count], null);
        });
        $this->management->flush();

        return response()->json(['message' => 'Explicit user assignments cleared. Legacy evaluations preserved.']);
    }

    public function overriddenUsers(Request $request, string $featureKey): JsonResponse
    {
        $this->actor($request);
        $this->management->known($featureKey);

        return response()->json(['users' => array_values(array_filter($this->management->assignments($featureKey), fn ($row): bool => $row['subject_type'] === 'user'))]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless(FeatureManagementService::canManage($user), 403);

        return $user;
    }

    private function setUser(Request $request, string $key, string $effect): JsonResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $this->management->assign($key, 'user', (string) $data['user_id'], $effect, $actor);
        if ($this->access->definition($key)?->mode !== 'enforced') {
            $user = User::findOrFail($data['user_id']);
            $effect === 'allow' ? $this->legacy->activateForUser($key, $user) : $this->legacy->deactivateForUser($key, $user);
        }

        return response()->json(['message' => 'User assignment saved.']);
    }

    private function groups(): array
    {
        $members = DB::table('feature_group_user')->join('users', 'users.id', '=', 'feature_group_user.user_id')
            ->whereNull('users.deleted_at')->get(['feature_group_id', 'users.id', 'users.name', 'users.email', 'users.school_id'])->groupBy('feature_group_id');

        return DB::table('feature_groups')->orderBy('name')->get()->map(fn ($group): array => [
            'id' => $group->id, 'name' => $group->name, 'description' => $group->description, 'school_id' => $group->school_id,
            'members' => ($members[$group->id] ?? collect())->values()->all(),
        ])->all();
    }

    private function auditEntries(?string $key = null): array
    {
        return DB::table('feature_audit_logs')->leftJoin('users', 'users.id', '=', 'feature_audit_logs.actor_id')
            ->when($key, fn ($query) => $query->where('feature_key', $key))->orderByDesc('feature_audit_logs.id')->limit(20)
            ->get(['feature_audit_logs.id', 'feature_key', 'action', 'users.name as actor', 'feature_audit_logs.created_at'])->map(fn ($row): array => (array) $row)->all();
    }
}
