@php
    $matchType = $matchType ?? 'none';

    $config = match($matchType) {
        'auto' => [
            'bgColor' => 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800',
            'icon' => 'M5 13l4 4L19 7',
            'iconColor' => 'text-success-600 dark:text-success-400',
            'label' => 'Auto-Matched',
        ],
        'review' => [
            'bgColor' => 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800',
            'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
            'iconColor' => 'text-warning-600 dark:text-warning-400',
            'label' => 'Requires Review',
        ],
        'none' => [
            'bgColor' => 'bg-danger-50 dark:bg-danger-900/20 border-danger-200 dark:border-danger-800',
            'icon' => 'M6 18L18 6M6 6l12 12',
            'iconColor' => 'text-danger-600 dark:text-danger-400',
            'label' => 'No Transfer Available',
        ],
        default => [
            'bgColor' => 'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-800',
            'icon' => '',
            'iconColor' => 'text-gray-600 dark:text-gray-400',
            'label' => 'Unknown',
        ],
    };
@endphp

<div class="border-l-4 {{ $config['bgColor'] }} border rounded-lg p-4 mb-4">
    <div class="flex items-center gap-2">
        @if($config['icon'])
            <svg class="w-5 h-5 {{ $config['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $config['icon'] }}"></path>
            </svg>
        @endif
        <span class="font-medium">{{ $config['label'] }}</span>
    </div>
</div>
