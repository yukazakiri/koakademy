<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Spatie\Permission\Models\Role as SpatieRole;
use Throwable;

/**
 * Resolves which desks a user can open, and returns their payloads.
 *
 * Adding a desk means registering one Dashboard implementation; the controller, the desk
 * switcher and the sidebar nav all read from here, so there is no second list to keep in sync.
 */
final class DashboardRegistry
{
    /**
     * Widget keys streamed after first paint. Charts and tables are the expensive part and the
     * user can act on the KPI strip and attention queue without them. Mirrors the existing
     * `admin-shell` deferred group in HandleInertiaRequests.
     *
     * @var list<string>
     */
    public const array DEFERRED_KEYS = ['trends', 'tables', 'activity'];

    /**
     * Desk implementations, in the order the switcher lists them. Ordered by seniority so a
     * multi-role user sees the broadest desk first.
     *
     * @var list<class-string<Dashboard>>
     */
    private const array DESKS = [
        ExecutiveDesk::class,
        RegistrarDesk::class,
        AccountingDesk::class,
        AcademicDesk::class,
        HrDesk::class,
        StudentAffairsDesk::class,
        ItAdminDesk::class,
    ];

    /**
     * Icon token per desk, matching AdminRouteIconKey in admin-routes.tsx.
     *
     * @var array<string, string>
     */
    private const array DESK_ICONS = [
        'executive' => 'report_analytics',
        'registrar' => 'clipboard_check',
        'accounting' => 'chart_bar',
        'academic' => 'school',
        'hr' => 'users_group',
        'student-affairs' => 'user_check',
        'it-admin' => 'tools',
    ];

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Every registered desk, regardless of the viewer.
     *
     * @return list<Dashboard>
     */
    public function all(): array
    {
        return array_values(array_filter(array_map(
            fn (string $desk): ?Dashboard => app($desk),
            self::DESKS,
        )));
    }

    /**
     * Desks this user may open, in switcher order.
     *
     * @return list<Dashboard>
     */
    public function visibleFor(User $user): array
    {
        $permissions = $this->permissionsFor($user);

        return array_values(array_filter(
            $this->all(),
            fn (Dashboard $desk): bool => $this->canAccess($user, $desk, $permissions),
        ));
    }

    /**
     * Switcher metadata for the desks this user can open.
     *
     * @return list<array{id: string, title: string, description: string, url: string}>
     */
    public function optionsFor(User $user): array
    {
        return array_map(
            fn (Dashboard $desk): array => [
                'id' => $desk->id(),
                'title' => $desk->title(),
                'description' => $desk->description(),
                'url' => route('administrators.desks.show', $desk->id()),
            ],
            $this->visibleFor($user),
        );
    }

    /**
     * Resolve a desk by id, or null when it does not exist or is not visible.
     */
    public function resolve(User $user, string $deskId): ?Dashboard
    {
        foreach ($this->visibleFor($user) as $desk) {
            if ($desk->id() === $deskId) {
                return $desk;
            }
        }

        return null;
    }

    /**
     * The desk to land on: the first visible one, or null when the user has no desk.
     */
    public function defaultFor(User $user): ?Dashboard
    {
        return $this->visibleFor($user)[0] ?? null;
    }

    /**
     * Desk entries for the administrator sidebar and command palette.
     *
     * Derived from the same visibleFor() the routes enforce, so a desk can never appear in the
     * navigation of someone who would get a 403 opening it. Returned in the shape
     * ModuleAdminRoute uses on the front end, which is what the sidebar merges.
     *
     * @return list<array{id: string, title: string, link: string, section: string, icon: string, description: string}>
     */
    public function navigationFor(User $user): array
    {
        return array_map(
            fn (Dashboard $desk): array => [
                'id' => 'admin-desk-'.$desk->id(),
                'title' => $desk->title(),
                'link' => route('administrators.desks.show', $desk->id()),
                'section' => 'core',
                'icon' => self::DESK_ICONS[$desk->id()] ?? 'dashboard',
                'description' => $desk->description(),
            ],
            $this->visibleFor($user),
        );
    }

    /**
     * Desk payload, permission-filtered and cached.
     *
     * The cache key includes the user id because visibility is per-role, not per-school: two
     * users in the same school can legitimately see different desks.
     */
    public function payloadFor(User $user, Dashboard $desk, DashboardContext $context): array
    {
        $key = sprintf('admin_dashboard:%s:%s:%d', $desk->id(), $context->cacheKey(), $user->id);

        $payload = $this->cache->remember(
            $key,
            300,
            fn (): array => $desk->data($user, $context),
        );

        // Widgets are filtered per request rather than cached: permission grants can change
        // between requests and a cached payload must not outlive a revoked permission.
        return $this->filterByPermissions($payload, $this->permissionsFor($user));
    }

    /**
     * Access check. System administrators bypass every desk, matching the frontend's
     * canAccessRoute() bypass and Gate::before() in AuthServiceProvider.
     */
    public function canAccess(User $user, Dashboard $desk, ?array $permissions = null): bool
    {
        if ($this->isSystemAdmin($user)) {
            return true;
        }

        return $desk->canView($user, $permissions ?? $this->permissionsFor($user));
    }

    public function isSystemAdmin(User $user): bool
    {
        return in_array($user->role, [UserRole::SuperAdmin, UserRole::Developer, UserRole::Admin], true);
    }

    /**
     * Every permission name granted to the user, from direct grants and role grants.
     *
     * Reads the Spatie role table directly because permission existence varies by install and
     * a missing permission must not throw during desk resolution.
     *
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        try {
            $direct = $user->getDirectPermissions()->pluck('name')->all();
        } catch (Throwable) {
            $direct = [];
        }

        $byRole = SpatieRole::query()
            ->where('name', $user->role?->value)
            ->with('permissions')
            ->first()?->permissions->pluck('name')->all() ?? [];

        return array_values(array_unique(array_merge($direct, $byRole)));
    }

    /**
     * Drop widgets the viewer lacks permission for.
     *
     * A widget may declare `permission` (string) or `permissions` (list). Widgets without one
     * are always shown. Only keys in DEFERRED_KEYS are gated here; kpis and queues are the
     * desk's contract and each desk is responsible for its own internal gating.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function filterByPermissions(array $payload, array $permissions): array
    {
        foreach (self::DEFERRED_KEYS as $key) {
            if (! isset($payload[$key]) || ! is_array($payload[$key])) {
                continue;
            }

            $payload[$key] = array_values(array_filter(
                $payload[$key],
                function (mixed $widget) use ($permissions): bool {
                    if (! is_array($widget)) {
                        return false;
                    }

                    $required = $widget['permissions'] ?? ($widget['permission'] ?? null);

                    if ($required === null) {
                        return true;
                    }

                    foreach ((array) $required as $permission) {
                        if (in_array($permission, $permissions, true)) {
                            return true;
                        }
                    }

                    return false;
                },
            ));
        }

        return $payload;
    }
}
