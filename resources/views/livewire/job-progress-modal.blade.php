<div>
    <!-- Progress Modal -->
    <x-filament::modal
        id="job-progress-modal"
        :visible="$isOpen"
        max-width="md"
        :close-by-clicking-away="false"
        :close-by-escaping="false"
    >
        <x-slot name="heading">
            <div class="flex items-center gap-3">
                <x-filament::icon
                    :icon="$this->progressIcon"
                    :class="[
                        'h-6 w-6',
                        match($this->progressColor) {
                            'success' => 'text-success-600',
                            'danger' => 'text-danger-600',
                            'warning' => 'text-warning-600',
                            default => 'text-primary-600'
                        }
                    ]"
                />
                <span>{{ $jobType }} Progress</span>
            </div>
        </x-slot>

        <div class="space-y-6">
            <!-- Student Information (if available) -->
            @if(!empty($progress['student_name']))
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                Student: {{ $progress['student_name'] }}
                            </p>
                            @if(!empty($progress['student_email']))
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Email: {{ $progress['student_email'] }}
                                </p>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Job ID: {{ Str::limit($jobId, 12) }}
                        </div>
                    </div>
                </div>
            @endif

            <!-- Progress Bar -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Progress
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $progress['percentage'] }}%
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                    <div
                        class="h-3 rounded-full transition-all duration-500 ease-in-out {{
                            match($this->progressColor) {
                                'success' => 'bg-success-600',
                                'danger' => 'bg-danger-600',
                                'warning' => 'bg-warning-600',
                                default => 'bg-primary-600'
                            }
                        }}"
                        style="width: {{ $progress['percentage'] }}%"
                    ></div>
                </div>
            </div>

            <!-- Current Status Message -->
            <div class="bg-white dark:bg-gray-900 border rounded-lg p-4">
                <div class="flex items-start gap-3">
                    @if($progress['failed'])
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="h-5 w-5 text-danger-600 flex-shrink-0 mt-0.5"
                        />
                    @elseif($progress['percentage'] >= 100)
                        <x-filament::icon
                            icon="heroicon-o-check-circle"
                            class="h-5 w-5 text-success-600 flex-shrink-0 mt-0.5"
                        />
                    @else
                        <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-primary-600 flex-shrink-0 mt-0.5"></div>
                    @endif

                    <div class="flex-1">
                        <p class="text-sm {{ $progress['failed'] ? 'text-danger-700 dark:text-danger-400' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $progress['message'] }}
                        </p>

                        @if(!empty($progress['updated_at']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Last updated: {{ \Carbon\Carbon::parse($progress['updated_at'])->diffForHumans() }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Time Information -->
            @if(!empty($progress['started_at']))
                <div class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    Started: {{ \Carbon\Carbon::parse($progress['started_at'])->format('M j, Y g:i A') }}
                </div>
            @endif
        </div>

        <x-slot name="footerActions">
            @if($progress['percentage'] < 100 && !$progress['failed'])
                <!-- Refresh Button -->
                <x-filament::button
                    color="gray"
                    wire:click="refreshProgress"
                    wire:loading.attr="disabled"
                >
                    <x-filament::loading-indicator wire:loading wire:target="refreshProgress" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="refreshProgress">Refresh</span>
                    <span wire:loading wire:target="refreshProgress">Refreshing...</span>
                </x-filament::button>

                <!-- Auto-refresh indicator -->
                <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <div class="animate-pulse w-2 h-2 bg-primary-600 rounded-full"></div>
                    <span>Auto-refreshing every 5 seconds</span>
                </div>
            @endif

            @if($progress['percentage'] >= 100 || $progress['failed'])
                <!-- Close Button -->
                <x-filament::button
                    color="gray"
                    wire:click="closeModal"
                >
                    Close
                </x-filament::button>
            @endif
        </x-slot>
    </x-filament::modal>

    <!-- Auto-refresh Script -->
    @if($isOpen && $progress['percentage'] < 100 && !$progress['failed'])
        <script>
            setTimeout(() => {
                if (@js($isOpen) && @js($progress['percentage']) < 100 && !@js($progress['failed'])) {
                    @this.call('refreshProgress');
                }
            }, 5000);
        </script>
    @endif

    <!-- Real-time updates via polling -->
    @script
    <script>
        // Auto-refresh when modal is open and job is not complete
        const refreshInterval = setInterval(() => {
            if (@js($isOpen) && @js($progress['percentage']) < 100 && !@js($progress['failed'])) {
                $wire.refreshProgress();
            } else if (@js($progress['percentage']) >= 100 || @js($progress['failed'])) {
                clearInterval(refreshInterval);
            }
        }, 3000); // Refresh every 3 seconds

        // Clean up interval when component is destroyed
        document.addEventListener('livewire:navigated', () => {
            clearInterval(refreshInterval);
        });
    </script>
    @endscript
</div>
