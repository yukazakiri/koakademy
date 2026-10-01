<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboards\DashboardContext;
use App\Dashboards\DashboardRegistry;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the role-scoped administrator dashboards ("desks").
 *
 * AdministratorPortalData::build() served one identical payload to every role, so every user
 * saw most widgets as noise. Each desk now returns only what its audience can act on, and the
 * registry decides which desks a user may open at all.
 */
final class AdministratorDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardRegistry $registry,
    ) {}

    /**
     * Land the user on their default desk.
     *
     * A user with no desk at all (no desk permissions) is sent to the original dashboard so
     * they are never left on a dead end.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $desk = $this->registry->defaultFor($user);

        if ($desk === null) {
            return redirect()->route('administrators.dashboard.legacy');
        }

        return redirect()->route('administrators.desks.show', $desk->id());
    }

    /**
     * Render one desk.
     *
     * An unknown desk id and a desk the user may not see both 403, so the response never
     * reveals which desks exist to someone without access to them.
     */
    public function show(Request $request, string $desk): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $resolved = $this->registry->resolve($user, $desk);

        abort_if($resolved === null, 403);

        $context = DashboardContext::for(
            $request->string('range')->toString() ?: null,
            $this->resolveDepartment($request, $user),
        );

        $payload = $this->registry->payloadFor($user, $resolved, $context);

        return Inertia::render('administrators/desks/show', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'desk' => [
                'id' => $resolved->id(),
                'title' => $resolved->title(),
                'description' => $resolved->description(),
            ],
            'desks' => $this->registry->optionsFor($user),
            'context' => [
                'period_label' => $context->periodLabel(),
                'range_label' => $context->rangeLabel(),
                'range' => $request->string('range')->toString() ?: 'year',
            ],
            // First paint: KPIs and the attention queue.
            'kpis' => $payload['kpis'] ?? [],
            'queues' => $payload['queues'] ?? [],
            // Streamed after first paint; mirrors DashboardRegistry::DEFERRED_KEYS.
            'trends' => $payload['trends'] ?? [],
            'tables' => $payload['tables'] ?? [],
            'activity' => $payload['activity'] ?? [],
        ]);
    }

    /**
     * Resolve the optional department scope.
     *
     * A department id in the query string only narrows the view for users already allowed to
     * see that department's data; the Academic desk is scoped server-side rather than trusting
     * the request, so a chair cannot widen their own scope by editing the URL.
     */
    private function resolveDepartment(Request $request, User $user): ?Department
    {
        $departmentId = $request->integer('department') ?: null;

        if ($departmentId === null) {
            return $this->ownDepartment($user);
        }

        return Department::query()
            ->active()
            ->whereKey($departmentId)
            ->first();
    }

    /**
     * The viewer's own department, for department heads and program chairs.
     */
    private function ownDepartment(User $user): ?Department
    {
        $departmentId = $user->department_id;

        return is_int($departmentId) && $departmentId > 0
            ? Department::query()->whereKey($departmentId)->first()
            : null;
    }
}
