<x-filament-panels::page>
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>

    <div class="space-y-6" x-data="{
        refreshSelects() {
            // Force refresh of select components
            this.$nextTick(() => {
                this.$dispatch('refresh');
            });
        },
        conflicts: @js($this->getConflicts() ?? []),
        showConflictModal: false,
        showColorLegend: true,

        openConflictModal() {
            this.showConflictModal = true;
            this.$dispatch('open-conflict-modal', { conflicts: this.conflicts });
        },

        loading: false,
        selectedId: @js($selectedId),
        selectedView: @js($selectedView),

        showLoading() {
            this.loading = true;
            setTimeout(() => {
                this.loading = false;
            }, 1000);
        }
    }"
    @refresh-select-options.window="refreshSelects()"
    @openConflictModal.window="openConflictModal()"
    x-init="
        $watch('selectedId', () => showLoading());
        $watch('selectedView', () => showLoading());
    ">



        {{ $this->form }}

        @if($selectedId)
            <!-- Loading Screen -->
            <div x-show="loading" class="flex items-center justify-center min-h-96 flex-col gap-4">
                <x-filament::loading-indicator class="h-8 w-8" />
                <div class="text-sm text-gray-600 dark:text-gray-400">Loading schedule data...</div>
            </div>

            <div x-show="!loading" x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="grid grid-cols-1 gap-6">

                <!-- Color Legend -->
                <div x-show="showColorLegend"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0">
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium fi-section-header-heading">Color Legend</span>
                                <x-filament::icon-button
                                    icon="heroicon-m-x-mark"
                                    size="sm"
                                    @click="showColorLegend = false"
                                />
                            </div>
                        </x-slot>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded border bg-blue-100 border-blue-300 dark:bg-blue-900/50 dark:border-blue-600"></div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">College Courses</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded border bg-green-100 border-green-300 dark:bg-green-900/50 dark:border-green-600"></div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">SHS Courses</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded border bg-red-100 border-red-300 dark:bg-red-900/50 dark:border-red-600"></div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Conflicts</span>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                <!-- Weekly Calendar View -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <x-filament::icon
                                        icon="heroicon-o-calendar"
                                        class="h-5 w-5 text-primary-500"
                                    />
                                    <span class="fi-section-header-heading">
                                        {{ match($selectedView) {
                                            'room' => 'Room Schedule',
                                            'class' => 'Class Schedule',
                                            'student' => 'Student Schedule',
                                            'course' => 'Course Schedule',
                                            'faculty' => 'Faculty Schedule',
                                            default => 'Schedule View'
                                        } }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $this->getSelectedEntityName() }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($this->hasConflicts())
                                    <x-filament::button
                                        color="danger"
                                        icon="heroicon-o-exclamation-triangle"
                                        size="sm"
                                        @click="openConflictModal()"
                                    >
                                        {{ $this->getConflictCount() }} Conflicts
                                    </x-filament::button>
                                @endif
                                <x-filament::button
                                    color="gray"
                                    icon="heroicon-o-swatch"
                                    size="sm"
                                    @click="showColorLegend = !showColorLegend"
                                >
                                    Legend
                                </x-filament::button>
                            </div>
                        </div>
                    </x-slot>

                    <div class="overflow-x-auto fi-section-content-ctn rounded-xl border border-gray-200 dark:border-white/10 bg-white shadow-sm dark:bg-gray-900">
                        <div class="min-w-full relative">
                            <!-- Calendar Header -->
                            <div class="grid grid-cols-8 sticky top-0 z-30 bg-gray-50 dark:bg-gray-800/50">
                                <div class="p-4 border-b border-r border-gray-200 dark:border-white/10 text-sm font-semibold text-center text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center justify-center gap-2">
                                        <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                                        <span>Time</span>
                                    </div>
                                </div>
                                @foreach($this->getDays() as $day)
                                    <div class="p-4 border-b border-r border-gray-200 dark:border-white/10 text-sm font-semibold text-center text-gray-700 dark:text-gray-300">
                                        <div class="flex flex-col items-center gap-1">
                                            <span>{{ $day }}</span>
                                            <div class="w-8 h-0.5 bg-primary-500 rounded-full"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Calendar Body -->
                            <div class="relative">
                                @foreach($this->getTimeSlots() as $index => $timeSlot)
                                    @php
                                        $slotStart = \Carbon\Carbon::parse($timeSlot);
                                        $slotEnd = $slotStart->copy()->addHour();
                                        $isEvenRow = $index % 2 === 0;
                                    @endphp
                                    <div class="grid grid-cols-8 transition-colors hover:bg-gray-50/50 dark:hover:bg-white/5
                                        {{ $isEvenRow ? 'bg-gray-50/30 dark:bg-white/[0.02]' : 'bg-white dark:bg-transparent' }}"
                                        style="min-height: 80px; height: 80px;">
                                        <!-- Time Column -->
                                        <div class="p-4 border-r border-gray-200 dark:border-white/10 text-sm text-center font-medium text-gray-600 dark:text-gray-400 flex flex-col justify-center">
                                            <div class="text-lg font-semibold">{{ $slotStart->format('g:i') }}</div>
                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $slotStart->format('A') }}</div>
                                        </div>

                                        <!-- Days Columns -->
                                        @foreach($this->getDays() as $dayIndex => $day)
                                            <div class="border-r border-gray-200 dark:border-white/10 relative" style="min-height: 80px; height: 80px;">
                                                    @if($index === 0)
                                                        <!-- Render all schedules for this day in the first time slot only -->
                                                        @php
                                                            $daySchedules = $schedules->filter(function($schedule) use ($day) {
                                                                return strtolower($schedule->day_of_week) === strtolower($day);
                                                            });
                                                        @endphp

                                                        @foreach($daySchedules as $schedule)
                                                            @php
                                                                $scheduleStart = \Carbon\Carbon::parse($schedule->start_time);
                                                                $scheduleEnd = \Carbon\Carbon::parse($schedule->end_time);

                                                                // Calculate position and height based on time
                                                                $timeSlots = $this->getTimeSlots();
                                                                $startSlotIndex = 0;
                                                                $endSlotIndex = count($timeSlots) - 1;

                                                                // Find exact slot positions
                                                                foreach($timeSlots as $slotIndex => $slot) {
                                                                    $slotTime = \Carbon\Carbon::parse($slot);
                                                                    if ($slotTime->hour <= $scheduleStart->hour) {
                                                                        $startSlotIndex = $slotIndex;
                                                                    }
                                                                    if ($slotTime->hour < $scheduleEnd->hour) {
                                                                        $endSlotIndex = $slotIndex + 1;
                                                                    }
                                                                }

                                                                // Calculate precise positioning
                                                                $slotHeight = 80; // Height of each time slot
                                                                $startMinuteOffset = ($scheduleStart->minute / 60) * $slotHeight;
                                                                $endMinuteOffset = ($scheduleEnd->minute / 60) * $slotHeight;

                                                                $topPosition = ($startSlotIndex * $slotHeight) + $startMinuteOffset;
                                                                $scheduleHeight = (($endSlotIndex - $startSlotIndex) * $slotHeight) + $endMinuteOffset - $startMinuteOffset;

                                                                // Minimum height for readability and preventing text overlap
                                                                $scheduleHeight = max($scheduleHeight, 80);

                                                                // Get color coding and conflict status
                                                                $hasConflict = $this->scheduleHasConflict($schedule);
                                                                $conflictService = app(\App\Services\TimetableConflictService::class);
                                                                $colorClass = $conflictService->getScheduleCssClass($schedule, $hasConflict);

                                                            // Build tooltip content
                                                            $tooltipContent = [];
                                                            $tooltipContent[] = $schedule->class?->subject?->title ?? 'N/A';
                                                            $tooltipContent[] = "Section: " . ($schedule->class?->section ?? 'N/A');
                                                            $tooltipContent[] = "Time: {$scheduleStart->format('H:i')} - {$scheduleEnd->format('H:i')}";

                                                            if($selectedView !== 'class') {
                                                                $tooltipContent[] = "Subject Code: " . ($schedule->class?->subject?->code ?? 'N/A');
                                                            }

                                                            if($selectedView !== 'room') {
                                                                $tooltipContent[] = "Room: " . ($schedule->room?->name ?? 'N/A');
                                                            }

                                                            if($selectedView !== 'faculty') {
                                                                $tooltipContent[] = "Faculty: " . ($schedule->class?->faculty?->full_name ?? 'N/A');
                                                            }

                                                            // Add course information for relevant views
                                                            if(in_array($selectedView, ['room', 'student', 'faculty']) && $schedule->class) {
                                                                $courseCodes = $schedule->class->formatted_course_codes;
                                                                if($courseCodes && $courseCodes !== 'N/A') {
                                                                    $tooltipContent[] = "Course(s): " . $courseCodes;
                                                                }
                                                            }

                                                            // Add classification for course and faculty views
                                                            if(in_array($selectedView, ['course', 'faculty']) && $schedule->class) {
                                                                $classification = $schedule->class->classification ?: 'college';
                                                                $tooltipContent[] = "Type: " . ucfirst($classification);
                                                            }

                                                            if($selectedView === 'student') {
                                                                // Add enrolled students count for student view
                                                                $enrolledCount = $schedule->class ? $schedule->class->class_enrollments->count() : 0;
                                                                $tooltipContent[] = "Enrolled Students: {$enrolledCount}";
                                                            }

                                                                // Build enhanced tooltip content
                                                                $tooltipContent = [];
                                                                $tooltipContent[] = '<strong>' . ($schedule->class?->subject?->title ?? 'N/A') . '</strong>';
                                                                $tooltipContent[] = "Section: " . ($schedule->class?->section ?? 'N/A');
                                                                $tooltipContent[] = "Time: {$scheduleStart->format('g:i A')} - {$scheduleEnd->format('g:i A')}";
                                                                $tooltipContent[] = "Duration: " . $scheduleStart->diffInHours($scheduleEnd) . "h " . ($scheduleStart->diffInMinutes($scheduleEnd) % 60) . "m";

                                                                if($selectedView !== 'class') {
                                                                    $tooltipContent[] = "Subject Code: " . ($schedule->class?->subject?->code ?? 'N/A');
                                                                }

                                                                if($selectedView !== 'room') {
                                                                    $tooltipContent[] = "Room: " . ($schedule->room?->name ?? 'N/A');
                                                                }

                                                                if($selectedView !== 'faculty') {
                                                                    $tooltipContent[] = "Faculty: " . ($schedule->class?->faculty?->full_name ?? 'N/A');
                                                                }

                                                                // Add course information for relevant views
                                                                if(in_array($selectedView, ['room', 'student', 'faculty']) && $schedule->class) {
                                                                    $courseCodes = $schedule->class->formatted_course_codes;
                                                                    if($courseCodes && $courseCodes !== 'N/A') {
                                                                        $tooltipContent[] = "Course(s): " . $courseCodes;
                                                                    }
                                                                }

                                                                // Add classification for course and faculty views
                                                                if(in_array($selectedView, ['course', 'faculty']) && $schedule->class) {
                                                                    $classification = $schedule->class->classification ?: 'college';
                                                                    $tooltipContent[] = "Type: " . ucfirst($classification);
                                                                }

                                                                if($selectedView === 'student') {
                                                                    // Add enrolled students count for student view
                                                                    $enrolledCount = $schedule->class ? $schedule->class->class_enrollments->count() : 0;
                                                                    $tooltipContent[] = "Enrolled Students: {$enrolledCount}";
                                                                }

                                                                // Add conflict warning if applicable
                                                                if($hasConflict) {
                                                                    $tooltipContent[] = "<strong style='color: #dc2626;'>⚠️ CONFLICT DETECTED</strong>";
                                                                }

                                                                $tooltipHtml = implode('<br>', $tooltipContent);
                                                            @endphp

                                                            <div
                                                                class="absolute rounded-lg p-3 text-xs cursor-pointer transition-all hover:shadow-lg hover:-translate-y-0.5 border overflow-hidden
                                                                    @if($hasConflict)
                                                                        bg-red-100 border-red-300 text-red-900 dark:bg-red-900/50 dark:border-red-600 dark:text-red-100 animate-pulse
                                                                    @elseif(($schedule->class?->classification ?? 'college') === 'shs')
                                                                        bg-green-100 border-green-300 text-green-900 dark:bg-green-900/50 dark:border-green-600 dark:text-green-100
                                                                    @else
                                                                        bg-blue-100 border-blue-300 text-blue-900 dark:bg-blue-900/50 dark:border-blue-600 dark:text-blue-100
                                                                    @endif
                                                                "
                                                                style="
                                                                    top: {{ $topPosition }}px;
                                                                    height: {{ max($scheduleHeight, 80) }}px;
                                                                    left: 6px;
                                                                    right: 6px;
                                                                    z-index: {{ $hasConflict ? 50 : 20 }};
                                                                "
                                                                onclick="window.open('{{ $schedule->class ? route('filament.admin.resources.classes.view', ['record' => $schedule->class_id]) : '#' }}', '_blank')"
                                                                x-data="{}"
                                                                x-tooltip.html
                                                                x-tooltip.raw="{{ $tooltipHtml }}"
                                                                @if($hasConflict)
                                                                    @click.stop="openConflictModal()"
                                                                @endif
                                                            >
                                                                <!-- Header with indicators and badges -->
                                                                <div class="flex items-start justify-between mb-2">
                                                                    <!-- Classification badge -->
                                                                    @php
                                                                        $classification = $schedule->class?->classification ?: 'college';
                                                                    @endphp
                                                                    <x-filament::badge
                                                                        color="{{ $classification === 'shs' ? 'success' : 'primary' }}"
                                                                        size="xs"
                                                                    >
                                                                        {{ strtoupper($classification) }}
                                                                    </x-filament::badge>

                                                                    <!-- Conflict indicator -->
                                                                    @if($hasConflict)
                                                                        <div class="w-3 h-3 bg-red-500 rounded-full border-2 border-white dark:border-gray-900 animate-pulse flex-shrink-0"></div>
                                                                    @endif
                                                                </div>

                                                                <!-- Schedule content -->
                                                                <div class="flex flex-col h-full justify-between">
                                                                    <div class="flex-1 space-y-1">
                                                                        <div class="text-sm font-semibold leading-tight line-clamp-2">
                                                                            {{ $schedule->class?->subject?->title ?? 'N/A' }}
                                                                        </div>
                                                                        <div class="text-xs opacity-90">
                                                                            Section {{ $schedule->class?->section ?? 'N/A' }}
                                                                        </div>
                                                                        
                                                                        <!-- Details section - only show if there's enough space -->
                                                                        @if($scheduleHeight >= 100)
                                                                            @if($selectedView !== 'room')
                                                                                <div class="flex items-center gap-1 text-xs opacity-80 truncate">
                                                                                    <x-filament::icon icon="heroicon-m-map-pin" class="h-3 w-3 shrink-0" />
                                                                                    <span class="truncate">{{ $schedule->room?->name ?? 'N/A' }}</span>
                                                                                </div>
                                                                            @endif
                                                                            @if($selectedView !== 'faculty')
                                                                                <div class="flex items-center gap-1 text-xs opacity-80 truncate">
                                                                                    <x-filament::icon icon="heroicon-m-user" class="h-3 w-3 shrink-0" />
                                                                                    <span class="truncate">{{ $schedule->class?->faculty?->full_name ?? 'N/A' }}</span>
                                                                                </div>
                                                                            @endif
                                                                        @endif
                                                                    </div>

                                                                    <!-- Time section - always show -->
                                                                    <div class="border-t border-current/20 pt-2 mt-2">
                                                                        <div class="flex items-center gap-1 text-xs font-medium">
                                                                            <x-filament::icon icon="heroicon-m-clock" class="h-3 w-3 shrink-0" />
                                                                            <span class="truncate">{{ $scheduleStart->format('g:i A') }} - {{ $scheduleEnd->format('g:i A') }}</span>
                                                                        </div>
                                                                        @if($scheduleHeight > 120)
                                                                            <div class="text-xs opacity-75 mt-1">
                                                                                {{ $scheduleStart->diffInHours($scheduleEnd) }}h {{ $scheduleStart->diffInMinutes($scheduleEnd) % 60 }}m
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <!-- List View -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-2">
                            <x-filament::icon
                                icon="heroicon-o-list-bullet"
                                class="h-5 w-5 text-primary-500"
                            />
                            <span class="fi-section-header-heading">Schedule List View</span>
                        </div>
                    </x-slot>
                    {{ $this->table }}
                </x-filament::section>
            </div>
        @else
            <x-filament::section>
                <div class="flex items-center justify-center py-12">
                    <div class="text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <x-filament::icon
                                icon="heroicon-o-calendar"
                                class="h-6 w-6 text-gray-400 dark:text-gray-500"
                            />
                        </div>
                        <h3 class="mt-4 text-sm font-medium text-gray-900 dark:text-gray-100">No schedule selected</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Please select a {{ strtolower($this->getSecondSelectLabel()) }} to view its schedule
                        </p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        <!-- Conflict Analysis Modal -->
        <x-timetable-conflict-modal :conflicts="$this->getConflicts() ?? []" />
    </div>

    @push('scripts')
        <script>
            // Handle conflict modal events
            document.addEventListener('alpine:init', () => {
                Alpine.data('timetableConflicts', () => ({
                    init() {
                        this.$watch('conflicts', (value) => {
                            if (value && Object.keys(value).length > 0) {
                                console.log('Conflicts detected:', value);
                            }
                        });
                    }
                }));
            });

            // Handle conflict resolution events
            window.addEventListener('resolveConflict', (event) => {
                console.log('Resolving conflict:', event.detail);
            });

            window.addEventListener('viewConflictingSchedule', (event) => {
                console.log('Viewing conflicting schedule:', event.detail);
            });
        </script>
    @endpush
</x-filament-panels::page>
