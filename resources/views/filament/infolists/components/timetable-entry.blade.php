@php
    $schedules = $entry->getSchedulesData();
    $timeSlots = $entry->generateTimeSlots($schedules);
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $showHeader = $entry->getShowHeader();
    $showLegend = $entry->getShowLegend();
    $allowToggle = $entry->getAllowToggle();
    
    $generalSettingsService = app(\App\Services\GeneralSettingsService::class);
    $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
    $currentSemester = $generalSettingsService->getCurrentSemester();
    
    $classesCount = $entry->getSchedulesCount();
    $scheduleEntriesCount = $schedules->count();
@endphp

<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    <div {{ $getExtraAttributeBag() }}>
        @if($schedules->isEmpty())
            <div class="text-center py-8 text-muted-foreground">
                <svg class="mx-auto h-12 w-12 text-muted-foreground/60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-foreground">No timetable schedules</h3>
                <p class="mt-1 text-sm text-muted-foreground">This student has no class schedules for the current academic period.</p>
            </div>
        @else
            <div class="space-y-3">
                @if($showHeader)
                    <div class="flex justify-between items-start flex-wrap gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Weekly Timetable</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Academic Period: {{ $currentSchoolYear }} - Semester {{ $currentSemester }} | 
                                Classes: {{ $classesCount }} | Weekly Schedule Entries: {{ $scheduleEntriesCount }}
                            </p>
                        </div>
                        @if($allowToggle)
                            <button 
                                type="button" 
                                class="fi-btn fi-btn-sm fi-color-gray fi-btn-ghost"
                                onclick="toggleTimetableView(this)"
                            >
                                <span id="toggle-text">Switch to List</span>
                            </button>
                        @endif
                    </div>
                @endif

                @if($showLegend)
                    <div class="flex gap-4 text-sm flex-wrap">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-success-500 rounded"></div>
                            <span class="text-gray-700 dark:text-gray-300">College</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 bg-warning-500 rounded"></div>
                            <span class="text-gray-700 dark:text-gray-300">SHS</span>
                        </div>
                    </div>
                @endif

                <!-- Grid View -->
                <div id="timetable-grid-view">
                    <div class="fi-table-container overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="fi-table w-full min-w-[600px] text-xs">
                            <thead>
                                <tr>
                                    <th class="fi-table-header-cell w-20 text-xs font-semibold bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 border-b-2 border-gray-200 dark:border-gray-700 px-2 py-1">
                                        Time
                                    </th>
                                    @foreach($days as $day)
                                        <th class="fi-table-header-cell text-xs font-semibold bg-primary-600 text-white border-b-2 border-primary-700 min-w-[80px] px-2 py-1">
                                            <div class="flex flex-col">
                                                <span class="text-xs">{{ $day }}</span>
                                                <span class="text-xs opacity-90">{{ substr($day, 0, 3) }}</span>
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $processedSchedules = []; // Track schedules to avoid duplicates
                                @endphp
                                @foreach($timeSlots as $timeSlot)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                        <td class="fi-table-cell w-20 text-xs font-medium bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 whitespace-nowrap border-r border-gray-200 dark:border-gray-700 px-2 py-1">
                                            @php
                                                // Convert 24-hour to 12-hour format
                                                $timeParts = explode(':', $timeSlot);
                                                $hour = (int)$timeParts[0];
                                                $minute = $timeParts[1] ?? '00';
                                                $period = $hour >= 12 ? 'PM' : 'AM';
                                                $displayHour = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                                                $formattedTime = "{$displayHour}:{$minute} {$period}";
                                            @endphp
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-xs">{{ $formattedTime }}</span>
                                                <span class="text-xs opacity-75">{{ $timeSlot }}</span>
                                            </div>
                                        </td>
                                        @foreach($days as $day)
                                            @php
                                                $spanInfo = $entry->getScheduleSpanInfo($schedules, $day, $timeSlot);
                                                $scheduleKey = null;
                                                
                                                if ($spanInfo) {
                                                    $schedule = $spanInfo['schedule'];
                                                    $scheduleKey = $schedule->id . '_' . $day;
                                                    
                                                    // Check if we've already processed this schedule
                                                    if (isset($processedSchedules[$scheduleKey])) {
                                                        $spanInfo = null;
                                                    } else {
                                                        $processedSchedules[$scheduleKey] = true;
                                                    }
                                                }
                                            @endphp
                                            
                                            @if($spanInfo)
                                                @php
                                                    $schedule = $spanInfo['schedule'];
                                                    $span = $spanInfo['span'];
                                                    $colorClass = $spanInfo['color'];
                                                    $class = $schedule->class;
                                                    $subjectCode = $class->subject_code ?? 'N/A';
                                                    $subjectTitle = $class->subject_title ?? 'N/A';
                                                    $room = $schedule->room?->name ?? 'No Room';
                                                    $section = $class->section ?? '';
                                                    $instructor = $class->faculty?->full_name ?? 'No Instructor';
                                                    $classId = $class->id;
                                                     $startTime = \Carbon\Carbon::parse($schedule->start_time)->format('g:i A');
                                                     $endTime = \Carbon\Carbon::parse($schedule->end_time)->format('g:i A');
                                                     $scheduleDay = ucfirst($schedule->day_of_week);
                                                     $heightClass = $span >= 3 ? 'min-h-[120px]' : ($span >= 2 ? 'min-h-[80px]' : 'min-h-[40px]');
                                                     $tooltipContent = "{$subjectCode} - {$subjectTitle}\\nDay: {$scheduleDay}\\nTime: {$startTime} - {$endTime}\\nDuration: " . ($span * 30) . " minutes\\nRoom: {$room}\\nSection: {$section}\\nInstructor: {$instructor}\\nType: " . strtoupper($class->classification);
                                                @endphp
                                                
                                                 <td class="fi-table-cell p-1 relative align-top border-l border-gray-200 dark:border-gray-700" rowspan="{{ $span }}">
                                                     <a href="/admin/classes/{{ $classId }}" 
                                                        class="block absolute inset-0.5 {{ $colorClass }} text-white rounded-md p-2 text-xs overflow-hidden group cursor-pointer hover:scale-[1.02] hover:shadow-lg hover:z-10 transition-all duration-200 no-underline ring-1 ring-white/20"
                                                        x-data="{ tooltip: '{{ $tooltipContent }}' }"
                                                        x-tooltip:click="tooltip"
                                                        @click="$event.stopPropagation()">
                                                         <div class="flex flex-col h-full justify-between">
                                                             <div class="flex-1">
                                                                 <div class="font-bold text-xs leading-tight mb-1 text-white drop-shadow-sm">{{ Str::limit($subjectCode, 12) }}</div>
                                                                 <div class="text-xs opacity-95 leading-tight mb-1 text-white drop-shadow-sm">{{ Str::limit($subjectTitle, 25) }}</div>
                                                                 <div class="text-xs opacity-90 font-medium text-white drop-shadow-sm">
                                                                     {{ $startTime }} - {{ $endTime }}
                                                                 </div>
                                                             </div>
                                                             <div class="space-y-0.5 border-t border-white/30 pt-1 mt-1">
                                                                 <div class="flex items-center gap-0.5">
                                                                     <svg class="w-2.5 h-2.5 text-white drop-shadow-sm flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                         <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                                                                     </svg>
                                                                     <span class="text-xs text-white drop-shadow-sm truncate">{{ Str::limit($room, 8) }}</span>
                                                                 </div>
                                                                 @if($section)
                                                                     <div class="flex items-center gap-0.5">
                                                                         <svg class="w-2.5 h-2.5 text-white drop-shadow-sm flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                                             <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                                             <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 100 4h2a2 2 0 100-4h-.5a1 1 0 000-2H8a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                                                         </svg>
                                                                         <span class="text-xs text-white drop-shadow-sm truncate">{{ Str::limit($section, 8) }}</span>
                                                                     </div>
                                                                 @endif
                                                             </div>
                                                         </div>
                                                         <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity bg-white/30 rounded p-0.5">
                                                             <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                 <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                                                 <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                                             </svg>
                                                         </div>
                                                     </a>
                                                 </td>
                                             @elseif(!$entry->findScheduleForSlot($schedules, $day, $timeSlot))
                                                 <td class="fi-table-cell p-1 h-8 bg-gray-50 dark:bg-gray-800/30 border-l border-gray-200 dark:border-gray-700 align-top">
                                                     <div class="h-full flex items-center justify-center text-gray-400 dark:text-gray-600 text-xs">
                                                         <span class="opacity-50">—</span>
                                                     </div>
                                                 </td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- List View (Initially Hidden) -->
                <div id="timetable-list-view" class="hidden">
                    <div class="space-y-3">
                        @foreach($schedules as $schedule)
                            @php
                                $class = $schedule->class;
                                $isShs = $class->classification === 'shs';
                                $borderColor = $isShs ? 'border-warning-500' : 'border-success-500';
                                $badgeColor = $isShs ? 'bg-warning-500 text-white' : 'bg-success-500 text-white';
                                $classId = $class->id;
                                
                                // Convert to 12-hour format
                                $startTime = \Carbon\Carbon::parse($schedule->start_time)->format('g:i A');
                                $endTime = \Carbon\Carbon::parse($schedule->end_time)->format('g:i A');
                            @endphp
                            
                            <a href="/admin/classes/{{ $classId }}" 
                               class="block fi-tile fi-tile-hover border-l-4 {{ $borderColor }} bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] no-underline group"
                               @click="$event.stopPropagation()">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <span class="fi-badge bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 text-sm font-semibold px-3 py-1 rounded">
                                                {{ $schedule->day_of_week }}
                                            </span>
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                {{ $startTime }} - {{ $endTime }}
                                            </span>
                                            <span class="fi-badge {{ $badgeColor }} text-xs font-semibold px-2 py-1 rounded">
                                                {{ strtoupper($class->classification) }}
                                            </span>
                                        </div>
                                        <div class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                            {{ $class->subject_code ?? 'N/A' }} - {{ $class->subject_title ?? 'N/A' }}
                                        </div>
                                    </div>
                                    <div class="ml-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                                            <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                                        </svg>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <svg class="w-4 h-4 opacity-60" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
                                        </svg>
                                        <span>{{ $schedule->room?->name ?? 'No Room' }}</span>
                                    </div>
                                    
                                    @if($class->section)
                                        <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                            <svg class="w-4 h-4 opacity-60" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 100 4h2a2 2 0 100-4h-.5a1 1 0 000-2H8a2 2 0 012-2h2a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V5z" clip-rule="evenodd"/>
                                            </svg>
                                            <span>Section {{ $class->section }}</span>
                                        </div>
                                    @endif
                                    
                                    <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                        <svg class="w-4 h-4 opacity-60" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                        <span>{{ $class->faculty?->full_name ?? 'No Instructor' }}</span>
                                    </div>
                                </div>
                                
                                @if($class->subject?->units || $class->shsSubject?->units)
                                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Units: {{ $class->subject?->units ?? $class->shsSubject?->units ?? 'N/A' }}</span>
                                            <span>Class ID: {{ $class->id }}</span>
                                        </div>
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($allowToggle)
                <script>
                    function toggleTimetableView(button) {
                        const gridView = document.getElementById('timetable-grid-view');
                        const listView = document.getElementById('timetable-list-view');
                        const toggleText = document.getElementById('toggle-text');
                        
                        if (gridView.style.display === 'none') {
                            gridView.style.display = 'block';
                            listView.style.display = 'none';
                            toggleText.textContent = 'Switch to List';
                        } else {
                            gridView.style.display = 'none';
                            listView.style.display = 'block';
                            toggleText.textContent = 'Switch to Grid';
                        }
                    }
                </script>
            @endif
        @endif
    </div>
</x-dynamic-component>