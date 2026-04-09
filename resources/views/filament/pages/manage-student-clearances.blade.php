<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Information Banner --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-information-circle"
                        class="h-5 w-5 text-primary-500"
                    />
                    <span>Clearance Management Overview</span>
                </div>
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    This page allows you to manage student clearances for the <strong>previous semester</strong>.
                    Students must be cleared from the previous semester before they can enroll in the current semester.
                </p>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <x-filament::icon
                                icon="heroicon-o-check-circle"
                                class="h-5 w-5 text-green-600 dark:text-green-400"
                            />
                            <span class="font-semibold text-green-900 dark:text-green-100">Cleared</span>
                        </div>
                        <p class="text-xs text-green-700 dark:text-green-300">
                            Student can proceed with enrollment
                        </p>
                    </div>

                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <x-filament::icon
                                icon="heroicon-o-x-circle"
                                class="h-5 w-5 text-red-600 dark:text-red-400"
                            />
                            <span class="font-semibold text-red-900 dark:text-red-100">Not Cleared</span>
                        </div>
                        <p class="text-xs text-red-700 dark:text-red-300">
                            Student is blocked from enrollment
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-900/20 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <x-filament::icon
                                icon="heroicon-o-question-mark-circle"
                                class="h-5 w-5 text-gray-600 dark:text-gray-400"
                            />
                            <span class="font-semibold text-gray-900 dark:text-gray-100">No Record</span>
                        </div>
                        <p class="text-xs text-gray-700 dark:text-gray-300">
                            Student can enroll (assumed new student)
                        </p>
                    </div>
                </div>

                <div class="mt-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-2">
                        <x-filament::icon
                            icon="heroicon-o-light-bulb"
                            class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5"
                        />
                        <div class="text-xs text-blue-700 dark:text-blue-300">
                            <strong>Quick Actions:</strong>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Use filters to find specific students by course, year level, or clearance status</li>
                                <li>Click individual students to toggle their clearance status</li>
                                <li>Select multiple students and use bulk actions to clear or unclear them</li>
                                <li>Use the header actions to create missing records or clear all students at once</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Table --}}
        {{ $this->table }}
    </div>
</x-filament-panels::page>
