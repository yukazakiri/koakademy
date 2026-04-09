<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Booking;
use App\Models\ResourceBooking;
use Carbon\Carbon;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class ResourceAvailabilityWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        // Only show if user has access to resource management
        return auth()->user()?->can('viewAny', ResourceBooking::class) ?? false;
    }

    public static function getWidgetConfig(): array
    {
        return [
            'title' => 'Resource Availability',
            'description' => 'Overview of resource bookings and availability',
        ];
    }

    protected function getStats(): array
    {
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $thisWeek = Carbon::now()->startOfWeek();
        Carbon::now()->addWeek()->startOfWeek();

        // Get total active resources
        $totalResources = ResourceBooking::where('is_active', true)->count();

        // Get bookings for today
        $todayBookings = Booking::whereDate('start_datetime', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Get bookings for tomorrow
        $tomorrowBookings = Booking::whereDate('start_datetime', $tomorrow)
            ->where('status', '!=', 'cancelled')
            ->count();

        // Get this week's bookings
        $thisWeekBookings = Booking::whereBetween('start_datetime', [$thisWeek, $thisWeek->copy()->endOfWeek()])
            ->where('status', '!=', 'cancelled')
            ->count();

        // Calculate availability percentages
        $todayAvailability = $totalResources > 0 ? max(0, 100 - (($todayBookings / $totalResources) * 100)) : 100;
        $tomorrowAvailability = $totalResources > 0 ? max(0, 100 - (($tomorrowBookings / $totalResources) * 100)) : 100;

        // Get pending bookings that need approval
        $pendingApprovals = Booking::where('status', 'pending')
            ->where('start_datetime', '>=', now())
            ->count();

        // Get most booked resource type
        $popularResourceType = Booking::join('resource_bookings', 'bookings.resource_booking_id', '=', 'resource_bookings.id')
            ->where('bookings.start_datetime', '>=', now()->startOfMonth())
            ->where('bookings.status', '!=', 'cancelled')
            ->groupBy('resource_bookings.resource_type')
            ->selectRaw('resource_bookings.resource_type, COUNT(*) as bookings_count')
            ->orderBy('bookings_count', 'desc')
            ->first();

        return [
            Stat::make('Today\'s Availability', number_format($todayAvailability, 1).'%')
                ->description("{$todayBookings} bookings today")
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($todayAvailability > 60 ? 'success' : ($todayAvailability > 30 ? 'warning' : 'danger'))
                ->chart($this->getAvailabilityChart()),

            Stat::make('Tomorrow\'s Availability', number_format($tomorrowAvailability, 1).'%')
                ->description("{$tomorrowBookings} bookings tomorrow")
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($tomorrowAvailability > 60 ? 'success' : ($tomorrowAvailability > 30 ? 'warning' : 'danger')),

            Stat::make('Pending Approvals', $pendingApprovals)
                ->description('Bookings awaiting approval')
                ->descriptionIcon(Heroicon::OutlinedClockIcon)
                ->color($pendingApprovals > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.bookings.index', ['tableFilters[status][value]' => 'pending'], false)),

            Stat::make('Most Popular Resource', $popularResourceType ? ucfirst((string) $popularResourceType->resource_type) : 'None')
                ->description($popularResourceType ? "{$popularResourceType->bookings_count} bookings this month" : 'No bookings yet')
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary'),

            Stat::make('Total Resources', $totalResources)
                ->description('Active resources available')
                ->descriptionIcon(Heroicon::OutlinedSquares2x2)
                ->color('info')
                ->url(route('filament.admin.resources.resource-bookings.index', [], false)),

            Stat::make('This Week\'s Bookings', $thisWeekBookings)
                ->description('Total bookings this week')
                ->descriptionIcon(Heroicon::OutlinedChartBarSquare)
                ->color('secondary')
                ->chart($this->getWeeklyBookingsChart()),
        ];
    }

    private function getAvailabilityChart(): array
    {
        $availabilities = [];
        // Get data for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $totalResources = ResourceBooking::where('is_active', true)->count();

            $bookingsCount = Booking::whereDate('start_datetime', $date)
                ->where('status', '!=', 'cancelled')
                ->count();

            $availability = $totalResources > 0 ? max(0, 100 - (($bookingsCount / $totalResources) * 100)) : 100;

            $availabilities[] = $availability;
        }

        return $availabilities;
    }

    private function getWeeklyBookingsChart(): array
    {
        $bookings = [];

        // Get bookings for the last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Booking::whereDate('start_datetime', $date)
                ->where('status', '!=', 'cancelled')
                ->count();

            $bookings[] = $count;
        }

        return $bookings;
    }
}
