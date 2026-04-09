<div class="space-y-4">
    @if(empty($getState()) || collect($getState())->every(fn($day) => empty($day)))
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <p class="text-sm font-medium">No Schedule Assigned</p>
            <p class="text-xs text-gray-400 dark:text-gray-500">This class doesn't have any scheduled sessions yet.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Day
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Time
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Room
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Duration
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @php
                        $dayNames = [
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday'
                        ];

                        $dayBadgeClasses = [
                            'monday' => 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                            'tuesday' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                            'wednesday' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                            'thursday' => 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100',
                            'friday' => 'bg-pink-100 text-pink-800 dark:bg-pink-800 dark:text-pink-100',
                            'saturday' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-800 dark:text-indigo-100'
                        ];
                    @endphp

                    @foreach($dayNames as $day => $displayName)
                        @if(isset($getState()[$day]) && count($getState()[$day]) > 0)
                            @foreach($getState()[$day] as $index => $schedule)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    @if($index === 0)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100" rowspan="{{ count($getState()[$day]) }}">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $dayBadgeClasses[$day] }}">
                                                {{ $displayName }}
                                            </span>
                                        </td>
                                    @endif
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                            {{ $schedule['time_range'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                            {{ $schedule['room']['name'] }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        @php
                                            try {
                                                $start = \Carbon\Carbon::createFromFormat('h:i A', $schedule['start_time']);
                                                $end = \Carbon\Carbon::createFromFormat('h:i A', $schedule['end_time']);
                                                $diff = $start->diff($end);
                                                $hours = $diff->h;
                                                $minutes = $diff->i;

                                                if ($hours > 0 && $minutes > 0) {
                                                    $duration = "{$hours}h {$minutes}m";
                                                } elseif ($hours > 0) {
                                                    $duration = "{$hours}h";
                                                } elseif ($minutes > 0) {
                                                    $duration = "{$minutes}m";
                                                } else {
                                                    $duration = 'N/A';
                                                }
                                            } catch (Exception $e) {
                                                $duration = 'N/A';
                                            }
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100">
                                            {{ $duration }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    @endforeach

                    @if(collect($getState())->every(fn($day) => empty($day)))
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-8 h-8 mb-2 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm">No scheduled sessions</span>
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    @endif
</div>
