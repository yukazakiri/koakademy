@php
    $schedules = $getState();
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $timeSlots = [];

    // Build the time slots array
    foreach ($schedules as $schedule) {
        $startTime = $schedule->start_time->format('H:i');
        $endTime = $schedule->end_time->format('H:i');
        $timeSlots[$schedule->day_of_week][$startTime . '-' . $endTime][] = [
            'room' => $schedule->room->name,
            'subject' => $schedule->subject, // Access subject through the relationship
        ];
    }

@endphp

<div class="fi-infolists-affixable-table-container overflow-x-auto relative">
    <table class="fi-infolists-table w-full text-start divide-y divide-gray-200 dark:divide-white/5">
        <thead>
            <tr class="bg-gray-50 dark:bg-white/5">
                <th class="fi-infolists-table-header-cell px-4 py-2 font-medium text-sm text-gray-600 dark:text-gray-300">
                    Day
                </th>
                <th class="fi-infolists-table-header-cell px-4 py-2 font-medium text-sm text-gray-600 dark:text-gray-300">
                    Time
                </th>
                <th class="fi-infolists-table-header-cell px-4 py-2 font-medium text-sm text-gray-600 dark:text-gray-300">
                    Room
                </th>

            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
            @foreach ($days as $day)
                @if (isset($timeSlots[$day]))
                    @foreach ($timeSlots[$day] as $time => $entries)
                        @foreach($entries as $entry)
                            <tr>
                                <td class="fi-infolists-table-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $day }}
                                </td>
                                 <td class="fi-infolists-table-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($time)->format('g:i A') }}
                                </td>
                                <td class="fi-infolists-table-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $entry['room'] }}
                                </td>

                            </tr>
                        @endforeach
                    @endforeach
                @else
                    <tr>
                        <td class="fi-infolists-table-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $day }}
                        </td>
                        <td class="fi-infolists-table-cell px-4 py-2 text-sm text-gray-500 dark:text-gray-400" colspan="3">
                            No Schedule
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div> 