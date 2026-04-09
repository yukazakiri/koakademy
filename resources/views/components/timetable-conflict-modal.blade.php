@props(['conflicts' => [], 'isOpen' => false])

<div 
    x-data="{ 
        open: @js($isOpen),
        conflicts: @js($conflicts),
        selectedConflict: null,
        selectedTab: 'overview'
    }"
    x-show="open"
    x-on:open-conflict-modal.window="open = true; conflicts = $event.detail.conflicts || []"
    x-on:close-conflict-modal.window="open = false"
    class="fixed inset-0 z-50 overflow-y-auto"
    style="display: none;"
>
    <!-- Backdrop -->
    <div 
        x-show="open"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="open = false"
    ></div>

    <!-- Modal -->
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div 
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-6xl sm:p-6"
        >
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-4">
                <div>
                    <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">
                        Schedule Conflict Analysis
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Detailed analysis of detected scheduling conflicts
                    </p>
                </div>
                <button 
                    @click="open = false"
                    class="rounded-md bg-white dark:bg-gray-800 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    <span class="sr-only">Close</span>
                    <x-heroicon-o-x-mark class="h-6 w-6" />
                </button>
            </div>

            <!-- Tabs -->
            <div class="mt-4">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <button 
                        @click="selectedTab = 'overview'"
                        :class="selectedTab === 'overview' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium"
                    >
                        Overview
                    </button>
                    <button 
                        @click="selectedTab = 'room-conflicts'"
                        :class="selectedTab === 'room-conflicts' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium"
                    >
                        Room Conflicts
                    </button>
                    <button 
                        @click="selectedTab = 'faculty-conflicts'"
                        :class="selectedTab === 'faculty-conflicts' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium"
                    >
                        Faculty Conflicts
                    </button>
                    <button 
                        @click="selectedTab = 'student-conflicts'"
                        :class="selectedTab === 'student-conflicts' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="whitespace-nowrap border-b-2 py-2 px-1 text-sm font-medium"
                    >
                        Student Conflicts
                    </button>
                </nav>
            </div>

            <!-- Content -->
            <div class="mt-6">
                <!-- Overview Tab -->
                <div x-show="selectedTab === 'overview'" class="space-y-6">
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <!-- Total Conflicts -->
                        <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-red-400" />
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                                Total Conflicts
                                            </dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100" 
                                                x-text="Object.values(conflicts).flat().length">
                                                0
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- High Priority -->
                        <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <x-heroicon-o-fire class="h-6 w-6 text-red-500" />
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                                High Priority
                                            </dt>
                                            <dd class="text-lg font-medium text-red-600 dark:text-red-400" 
                                                x-text="Object.values(conflicts).flat().filter(c => c.severity === 'high').length">
                                                0
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Affected Resources -->
                        <div class="bg-white dark:bg-gray-700 overflow-hidden shadow rounded-lg">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <x-heroicon-o-building-office class="h-6 w-6 text-yellow-400" />
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                                Affected Resources
                                            </dt>
                                            <dd class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                <span x-text="(conflicts.time_room_conflicts || []).length + (conflicts.faculty_conflicts || []).length">0</span>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conflict Timeline -->
                    <div class="bg-white dark:bg-gray-700 shadow rounded-lg p-6">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Conflict Timeline
                        </h4>
                        <div class="space-y-4">
                            <template x-for="(conflictGroup, type) in conflicts" :key="type">
                                <div x-show="conflictGroup.length > 0">
                                    <h5 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" 
                                        x-text="type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())">
                                    </h5>
                                    <template x-for="conflict in conflictGroup" :key="conflict.id || Math.random()">
                                        <div class="flex items-center space-x-3 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                            <div class="flex-shrink-0">
                                                <div class="w-2 h-2 bg-red-400 rounded-full"></div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-900 dark:text-gray-100" 
                                                   x-text="`${conflict.day || 'Unknown Day'} - ${conflict.severity || 'medium'} priority`">
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Room Conflicts Tab -->
                <div x-show="selectedTab === 'room-conflicts'" class="space-y-4">
                    <template x-for="conflict in conflicts.time_room_conflicts || []" :key="conflict.id || Math.random()">
                        <div class="bg-white dark:bg-gray-700 shadow rounded-lg p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Room Conflict Details
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" 
                                       x-text="`Day: ${conflict.day_room || 'Unknown'}`">
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200">
                                    High Priority
                                </span>
                            </div>
                            
                            <!-- Conflicting Schedules -->
                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <template x-for="conflictDetail in conflict.conflicts || []" :key="Math.random()">
                                    <div class="space-y-4">
                                        <!-- Schedule 1 -->
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                            <h5 class="font-medium text-gray-900 dark:text-gray-100">Schedule 1</h5>
                                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <p x-text="`Subject: ${conflictDetail.schedule1?.class?.subject?.title || 'N/A'}`"></p>
                                                <p x-text="`Time: ${conflictDetail.schedule1?.start_time || 'N/A'} - ${conflictDetail.schedule1?.end_time || 'N/A'}`"></p>
                                                <p x-text="`Faculty: ${conflictDetail.schedule1?.class?.faculty?.full_name || 'N/A'}`"></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Schedule 2 -->
                                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                            <h5 class="font-medium text-gray-900 dark:text-gray-100">Schedule 2</h5>
                                            <div class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <p x-text="`Subject: ${conflictDetail.schedule2?.class?.subject?.title || 'N/A'}`"></p>
                                                <p x-text="`Time: ${conflictDetail.schedule2?.start_time || 'N/A'} - ${conflictDetail.schedule2?.end_time || 'N/A'}`"></p>
                                                <p x-text="`Faculty: ${conflictDetail.schedule2?.class?.faculty?.full_name || 'N/A'}`"></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Overlap Details -->
                                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                            <h5 class="font-medium text-red-800 dark:text-red-200">Overlap Details</h5>
                                            <div class="mt-2 space-y-1 text-sm text-red-700 dark:text-red-300">
                                                <p x-text="`Duration: ${conflictDetail.overlap_details?.overlap_duration || 0} minutes`"></p>
                                                <p x-text="`Time: ${conflictDetail.overlap_details?.overlap_start || 'N/A'} - ${conflictDetail.overlap_details?.overlap_end || 'N/A'}`"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="!conflicts.time_room_conflicts || conflicts.time_room_conflicts.length === 0" 
                         class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No room conflicts detected.
                    </div>
                </div>

                <!-- Faculty Conflicts Tab -->
                <div x-show="selectedTab === 'faculty-conflicts'" class="space-y-4">
                    <template x-for="conflict in conflicts.faculty_conflicts || []" :key="conflict.id || Math.random()">
                        <div class="bg-white dark:bg-gray-700 shadow rounded-lg p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Faculty Conflict Details
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" 
                                       x-text="`Faculty: ${conflict.day_faculty || 'Unknown'}`">
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200">
                                    High Priority
                                </span>
                            </div>
                            
                            <!-- Similar structure as room conflicts but for faculty -->
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Faculty member is scheduled for multiple classes at the same time.
                                </p>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="!conflicts.faculty_conflicts || conflicts.faculty_conflicts.length === 0" 
                         class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No faculty conflicts detected.
                    </div>
                </div>

                <!-- Student Conflicts Tab -->
                <div x-show="selectedTab === 'student-conflicts'" class="space-y-4">
                    <template x-for="conflict in conflicts.student_conflicts || []" :key="conflict.id || Math.random()">
                        <div class="bg-white dark:bg-gray-700 shadow rounded-lg p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Student Conflict Details
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" 
                                       x-text="`Student ID: ${conflict.student_id || 'Unknown'}`">
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200">
                                    Medium Priority
                                </span>
                            </div>
                            
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Student is enrolled in multiple classes that have overlapping schedules.
                                </p>
                            </div>
                        </div>
                    </template>
                    
                    <div x-show="!conflicts.student_conflicts || conflicts.student_conflicts.length === 0" 
                         class="text-center py-8 text-gray-500 dark:text-gray-400">
                        No student conflicts detected.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-6 flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                <button 
                    @click="open = false"
                    type="button" 
                    class="rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    Close
                </button>
                <button 
                    type="button" 
                    class="rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    @click="$dispatch('export-conflict-report')"
                >
                    Export Report
                </button>
            </div>
        </div>
    </div>
</div>
