@php
    $scheduleDataToUse = $scheduleData ?? collect();
    $context = $context ?? 'main';
@endphp

<!-- Desktop Timetable -->
<div class="hidden lg:block overflow-x-auto">
    <div class="min-w-full">
        <!-- Header Row -->
        <div class="grid grid-cols-8 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <div class="p-4 font-semibold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">
                Time
            </div>
            @foreach($days as $day)
                <div class="p-4 font-semibold text-center text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700 last:border-r-0">
                    {{ $day }}
                </div>
            @endforeach
        </div>

        <!-- Time Slots -->
        @foreach($timeSlots as $timeIndex => $time)
            @php
                $isHourStart = substr($time, -2) === '00';
                $displayTime = $isHourStart;
            @endphp

            @if($displayTime)
                <div class="grid grid-cols-8 border-b border-gray-200 dark:border-gray-700 {{ $timeIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800' }}">
                    <!-- Time Column -->
                    <div class="p-3 text-sm font-medium text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        {{ \Carbon\Carbon::createFromFormat('H:i', $time)->format('h:i A') }}
                    </div>

                    <!-- Day Columns -->
                    @foreach($days as $day)
                        @php
                            $daySchedules = $this->getScheduleForDayAndTime($day, $time, $scheduleDataToUse);
                        @endphp

                        <div class="p-2 border-r border-gray-200 dark:border-gray-700 last:border-r-0 min-h-[60px]">
                            @if($daySchedules->isNotEmpty())
                                @foreach($daySchedules as $schedule)
                                    @php
                                        $cardData = $this->getScheduleCardData($schedule);
                                        $duration = $schedule->end_time->diffInMinutes($schedule->start_time);
                                        $slots = ceil($duration / 30);

                                        // Different colors for different year levels in multi-year view
                                        $colors = [
                                            1 => 'bg-blue-100 border-blue-200 text-blue-900 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-100',
                                            2 => 'bg-green-100 border-green-200 text-green-900 dark:bg-green-900 dark:border-green-700 dark:text-green-100',
                                            3 => 'bg-yellow-100 border-yellow-200 text-yellow-900 dark:bg-yellow-900 dark:border-yellow-700 dark:text-yellow-100',
                                            4 => 'bg-purple-100 border-purple-200 text-purple-900 dark:bg-purple-900 dark:border-purple-700 dark:text-purple-100',
                                        ];

                                        $yearLevel = $cardData['year_level'] ?? 1;
                                        $colorClass = $colors[$yearLevel] ?? $colors[1];

                                        // Use default blue if not in multi-year context
                                        if ($context === 'main' || strpos($context, 'year-level-') !== false) {
                                            $colorClass = 'bg-blue-100 border-blue-200 text-blue-900 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-100';
                                        }
                                    @endphp

                                    <div class="{{ $colorClass }} border rounded-lg p-3 mb-2 text-xs space-y-1 transition-colors duration-200 hover:opacity-80"
                                         style="min-height: {{ $slots * 60 - 8 }}px;"
                                         title="{{ $cardData['subject'] }} - {{ $cardData['faculty'] }}">
                                        <div class="font-semibold truncate">
                                            {{ $cardData['subject'] }}
                                        </div>

                                        @if($cardData['faculty'])
                                            <div class="opacity-80 truncate">
                                                {{ $cardData['faculty'] }}
                                            </div>
                                        @endif

                                        <div class="flex justify-between items-center opacity-75">
                                            <span class="truncate">{{ $cardData['room'] }}</span>
                                            @if($selectedView === 'room' || $this->hasYearLevelSections())
                                                <span class="text-xs bg-black bg-opacity-10 px-1 rounded">
                                                    {{ $cardData['course_codes'] }}
                                                </span>
                                            @endif
                                        </div>

                                        @if($cardData['section'])
                                            <div class="opacity-75 text-xs">
                                                Sec: {{ $cardData['section'] }}
                                            </div>
                                        @endif

                                        <div class="flex justify-between items-center text-xs opacity-70">
                                            <span>
                                                {{ $cardData['time'] }}
                                            </span>
                                            <span>
                                                {{ $cardData['student_count'] }}/{{ $cardData['max_slots'] ?? '∞' }}
                                            </span>
                                        </div>

                                        @if($this->hasYearLevelSections() && $context === 'main')
                                            <div class="text-xs opacity-60">
                                                {{ $this->getYearLevelName($yearLevel) }} Yr
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="h-full flex items-center justify-center text-gray-400 dark:text-gray-500 text-xs">
                                    {{ $this->getEmptySlotMessage() }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>
</div>

<!-- Mobile Timetable -->
<div class="lg:hidden">
    @foreach($days as $day)
        <div class="border-b border-gray-200 dark:border-gray-700 last:border-b-0">
            <div class="bg-gray-50 dark:bg-gray-800 p-4 font-semibold text-gray-900 dark:text-white flex justify-between items-center">
                <span>{{ $day }}</span>
                @php
                    $daySchedules = $scheduleDataToUse->filter(function($schedule) use ($day) {
                        return strtolower($schedule->day_of_week) === strtolower($day);
                    })->sortBy('start_time');
                @endphp
                <span class="text-sm font-normal opacity-75">{{ $daySchedules->count() }} class{{ $daySchedules->count() !== 1 ? 'es' : '' }}</span>
            </div>

            <div class="p-4 space-y-3">
                @if($daySchedules->isNotEmpty())
                    @foreach($daySchedules as $schedule)
                        @php
                            $cardData = $this->getScheduleCardData($schedule);
                            $yearLevel = $cardData['year_level'] ?? 1;

                            // Different border colors for year levels in mobile view
                            $borderColors = [
                                1 => 'border-blue-200 dark:border-blue-700',
                                2 => 'border-green-200 dark:border-green-700',
                                3 => 'border-yellow-200 dark:border-yellow-700',
                                4 => 'border-purple-200 dark:border-purple-700',
                            ];

                            $borderClass = $borderColors[$yearLevel] ?? $borderColors[1];

                            if ($context === 'main' || strpos($context, 'year-level-') !== false) {
                                $borderClass = 'border-blue-200 dark:border-blue-700';
                            }
                        @endphp

                        <div class="bg-blue-50 dark:bg-blue-900 border {{ $borderClass }} rounded-lg p-4 space-y-2 transition-colors duration-200 hover:bg-blue-100 dark:hover:bg-blue-800">
                            <div class="flex justify-between items-start">
                                <div class="font-semibold text-blue-900 dark:text-blue-100 flex-1 pr-2">
                                    {{ $cardData['subject'] }}
                                </div>
                                @if($this->hasYearLevelSections() && $context === 'main')
                                    <span class="bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-200 px-2 py-1 rounded text-xs whitespace-nowrap">
                                        {{ $this->getYearLevelName($yearLevel) }} Yr
                                    </span>
                                @endif
                            </div>

                            @if($cardData['faculty'])
                                <div class="text-blue-700 dark:text-blue-300 text-sm">
                                    Faculty: {{ $cardData['faculty'] }}
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="bg-blue-100 dark:bg-blue-800 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                                    {{ $cardData['time'] }}
                                </span>
                                <span class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">
                                    {{ $cardData['room'] }}
                                </span>
                                @if($cardData['section'])
                                    <span class="bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-200 px-2 py-1 rounded">
                                        Sec: {{ $cardData['section'] }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex justify-between items-center text-sm text-blue-600 dark:text-blue-400">
                                <span>{{ $cardData['course_codes'] }}</span>
                                <span>{{ $cardData['student_count'] }}/{{ $cardData['max_slots'] ?? '∞' }} students</span>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-8 text-gray-400 dark:text-gray-500">
                        <div class="text-4xl mb-2">📅</div>
                        <div>{{ $this->getEmptySlotMessage() }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endforeach
</div>
