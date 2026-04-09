@php
    $autoMatched = $autoMatched ?? 0;
    $requiresReview = $requiresReview ?? 0;
    $noCreditTransfer = $noCreditTransfer ?? 0;
    $showPlaceholder = $showPlaceholder ?? false;
@endphp

@if($showPlaceholder)
    <div class="text-center py-4 text-gray-500">
        Please select a new course to see subject comparison.
    </div>
@else
    <div class="grid grid-cols-3 gap-4">
    {{-- Auto-Matched Card --}}
    <div class="bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-1">
            <svg class="w-5 h-5 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="text-sm font-medium text-success-700 dark:text-success-300">Auto-Matched</span>
        </div>
        <div class="text-3xl font-bold text-success-600 dark:text-success-400">
            {{ $autoMatched }}
        </div>
        <div class="text-xs text-success-600 dark:text-success-400 mt-1">
            Exact code matches
        </div>
    </div>

    {{-- Requires Review Card --}}
    <div class="bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-1">
            <svg class="w-5 h-5 text-warning-600 dark:text-warning-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <span class="text-sm font-medium text-warning-700 dark:text-warning-300">Requires Review</span>
        </div>
        <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">
            {{ $requiresReview }}
        </div>
        <div class="text-xs text-warning-600 dark:text-warning-400 mt-1">
            Manual selection needed
        </div>
    </div>

    {{-- No Transfer Card --}}
    <div class="bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-1">
            <svg class="w-5 h-5 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <span class="text-sm font-medium text-danger-700 dark:text-danger-300">No Transfer</span>
        </div>
        <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">
            {{ $noCreditTransfer }}
        </div>
        <div class="text-xs text-danger-600 dark:text-danger-400 mt-1">
            No matching subjects
        </div>
    </div>
</div>
@endif
