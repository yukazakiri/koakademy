<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Dashboards\Contracts\Dashboard;
use App\Enums\UserRole;
use App\Models\HelpTicket;
use App\Models\User;
use Modules\Inventory\Models\InventoryBorrowing;
use Modules\Inventory\Models\InventoryProduct;

/**
 * Support desk for IT, maintenance and security staff.
 *
 * Operational rather than academic: tickets, assets and anything overdue.
 */
final class ItAdminDesk implements Dashboard
{
    public function id(): string
    {
        return 'it-admin';
    }

    public function title(): string
    {
        return 'IT & Support';
    }

    public function description(): string
    {
        return 'Support tickets, inventory and outstanding asset borrowings.';
    }

    /**
     * @param  list<string>  $permissions
     */
    public function canView(User $user, array $permissions = []): bool
    {
        if ($permissions === []) {
            return in_array($user->role, [
                UserRole::ITSupport,
                UserRole::MaintenanceStaff,
                UserRole::AdministrativeAssistant,
            ], true);
        }

        return array_intersect(['View:InventoryProduct', 'ViewAny:GeneralSetting', 'ViewAny:User'], $permissions) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(User $user, DashboardContext $context): array
    {
        $openTickets = $this->openTicketCount();
        $overdue = $this->overdueBorrowings();

        return [
            'kpis' => [
                [
                    'label' => 'Open Tickets',
                    'value' => $openTickets,
                    'description' => 'Help requests awaiting resolution.',
                    'tone' => $openTickets > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
                [
                    'label' => 'Tracked Assets',
                    'value' => $this->assetCount(),
                    'description' => 'Inventory products under management.',
                    'tone' => 'neutral',
                    'format' => 'number',
                ],
                [
                    'label' => 'Overdue Borrowings',
                    'value' => $overdue,
                    'description' => 'Items past their expected return date.',
                    'tone' => $overdue > 0 ? 'warning' : 'success',
                    'format' => 'number',
                ],
            ],
            'queues' => [
                [
                    'id' => 'open-tickets',
                    'title' => 'Open support tickets',
                    'description' => 'Help requests not yet closed.',
                    'count' => $openTickets,
                    'severity' => $openTickets > 0 ? 'warning' : 'success',
                    'href' => '/administrators/help-tickets',
                    'icon' => 'help',
                ],
                [
                    'id' => 'overdue-borrowings',
                    'title' => 'Overdue asset borrowings',
                    'description' => 'Borrowed items past their return date.',
                    'count' => $overdue,
                    'severity' => $overdue > 0 ? 'warning' : 'success',
                    'href' => '/administrators/inventory',
                    'icon' => 'tools',
                    'permission' => 'View:InventoryBorrowing',
                ],
            ],
            'trends' => [],
            'tables' => [],
        ];
    }

    private function openTicketCount(): int
    {
        return HelpTicket::query()
            ->whereNotIn('status', ['closed', 'resolved'])
            ->count();
    }

    private function assetCount(): int
    {
        return InventoryProduct::query()->count();
    }

    private function overdueBorrowings(): int
    {
        // Reuses the module's own scope so the desk agrees with the inventory screens.
        return InventoryBorrowing::query()->overdue()->count();
    }
}
