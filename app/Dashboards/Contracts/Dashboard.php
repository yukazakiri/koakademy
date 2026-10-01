<?php

declare(strict_types=1);

namespace App\Dashboards\Contracts;

use App\Dashboards\DashboardContext;
use App\Models\User;

/**
 * A role-scoped administrative dashboard ("desk").
 *
 * One desk owns the widgets for a single job function. AdministratorPortalData::build()
 * served an identical payload to every role, so each user saw most of the widgets as noise.
 * Splitting by desk keeps each screen limited to what its audience can act on, and keeps any
 * one class small instead of growing a single monolith.
 *
 * Implementations must be pure readers: no mutations, and no tenant crossing beyond
 * DashboardContext's school.
 */
interface Dashboard
{
    /**
     * Stable identifier used in routes, cache keys and the desk switcher. Changing this
     * orphans cached payloads, so keep it stable once shipped.
     */
    public function id(): string;

    /** Short label for the desk switcher and page title. */
    public function title(): string;

    /** One-line explanation of who this desk is for and what it answers. */
    public function description(): string;

    /**
     * Whether this user may open the desk at all.
     *
     * Gate on permissions the role already holds rather than on role names, so access stays
     * consistent with how the rest of the administrator panel authorizes. System
     * administrators bypass everything (see DashboardRegistry::canAccess).
     *
     * @param  list<string>  $permissions  Alternative permission names that also grant access.
     */
    public function canView(User $user, array $permissions = []): bool;

    /**
     * Widget payloads for this desk.
     *
     * Return only widgets the user is permitted to see. Keys are read by the front-end
     * desk components, so treat them as a contract:
     *  - kpis:    list<array{label, value, description?, tone?, format?, trend?, series?}>
     *  - trends:  list<array{id, title, description?, data: list<array<string, mixed>>}>
     *  - queues:  list<array{id, title, description?, count, severity, href, icon?}>
     *  - tables:  list<array{id, title, description?, columns: list<...>, rows: list<...>}>
     *
     * Anything expensive belongs under `deferred`, which the controller streams after first
     * paint; see DashboardRegistry::deferredKeys().
     *
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array;
}
