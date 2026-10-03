<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboards\DashboardContext;
use App\Dashboards\DashboardRegistry;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use App\Support\AdministratorPortalData;
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
     * A user with no desk at all (no desk permissions) is sent to the overview dashboard so
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
            return redirect()->route('administrators.dashboard.overview');
        }

        return redirect()->route('administrators.desks.show', $desk->id());
    }

    public function overview(Request $request): Response|RedirectResponse
    {
        return $this->dashboardView($request, 'overview');
    }

    public function enrollment(Request $request): Response|RedirectResponse
    {
        return $this->dashboardView($request, 'enrollment');
    }

    public function students(Request $request): Response|RedirectResponse
    {
        return $this->dashboardView($request, 'students');
    }

    public function operations(Request $request): Response|RedirectResponse
    {
        return $this->dashboardView($request, 'operations');
    }

    /**
     * Render one desk.
     *
     * An unknown desk id and a desk the user may not see both 403, so the response never
     * reveals which desks exist to someone without access to them.
     *
     * Charts and tables are sent with the rest of the payload rather than behind an Inertia
     * defer group. Deferring was measured and removed: DashboardRegistry already caches each
     * desk for five minutes, so a desk costs 2 queries warm and 5-11 cold, and the whole
     * payload is under 2.3 KB. Deferring bought a second round trip to avoid serializing at
     * most 1.3 KB of it, which is a worse trade than just sending the page once.
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
            // Which department this view is limited to, so the UI can state the scope
            // rather than implying an institution-wide figure.
            'scope' => $payload['scope'] ?? null,
            'kpis' => $payload['kpis'] ?? [],
            'queues' => $payload['queues'] ?? [],
            'trends' => $payload['trends'] ?? [],
            'tables' => $payload['tables'] ?? [],
            'activity' => $payload['activity'] ?? [],
        ]);
    }

    private function dashboardView(Request $request, string $activeView): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        $portalData = AdministratorPortalData::build($user);

        $quickActions = [
            [
                'title' => 'Review pending approvals',
                'description' => 'Approve or reject the latest requests.',
                'href' => '/administrators/approvals',
                'disabled' => true,
                'disabledTooltip' => 'Approvals workflow coming soon',
            ],
            [
                'title' => 'View faculty directory',
                'description' => 'Find faculty details quickly.',
                'href' => '/administrators/faculties',
                'disabled' => false,
            ],
            [
                'title' => 'Create announcement',
                'description' => 'Draft and publish an announcement.',
                'href' => '/administrators/announcements',
                'disabled' => false,
            ],
        ];

        $beginnerTips = [
            [
                'title' => 'Start with the Faculty Directory',
                'content' => 'Use it to confirm who is assigned to which department and spot missing records.',
            ],
            [
                'title' => 'Use search often',
                'content' => 'Most screens will support search so you don\'t need to scroll.',
            ],
            [
                'title' => 'Look for “Coming soon” labels',
                'content' => 'Some tools are still being rolled out. You\'ll see clear hints when a feature is not ready yet.',
            ],
        ];

        return Inertia::render('administrators/dashboard', [
            'active_view' => $activeView,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'admin_data' => [
                ...$portalData,
                'quick_actions' => $quickActions,
                'beginner_tips' => $beginnerTips,
            ],
            'flash' => session('flash'),
        ]);
    }

    /**
     * Resolve the department scope a desk payload may be limited to.
     *
     * A department head or program chair is pinned to their own department regardless of the
     * query string: accepting ?department=<id> for a scoped user would let them read another
     * department's faculty, teaching load and student counts by editing the URL. Only roles
     * that legitimately see the whole institution may choose a department, and they may only
     * pick an active one.
     */
    private function resolveDepartment(Request $request, User $user): ?Department
    {
        $own = $this->ownDepartment($user);

        if (! $this->mayBrowseAllDepartments($user)) {
            // Scoped roles get their own department, and nothing else.
            return $own;
        }

        $requested = $request->integer('department') ?: null;

        if ($requested === null) {
            return $own;
        }

        return Department::query()
            ->active()
            ->whereKey($requested)
            ->first();
    }

    /**
     * Roles permitted to view a department other than their own.
     *
     * Mirrors the roles the Executive desk is documented for; a department head is explicitly
     * absent.
     */
    private function mayBrowseAllDepartments(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::Developer,
            UserRole::Admin,
            UserRole::President,
            UserRole::VicePresident,
            UserRole::Dean,
            UserRole::AssociateDean,
            UserRole::HRManager,
        ], true);
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
