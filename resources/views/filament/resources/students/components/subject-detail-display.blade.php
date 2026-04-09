@php
    $title = $title ?? '';
    $code = $code ?? '';
    $units = $units ?? 0;
@endphp

<div class="space-y-1">
    <div class="font-semibold text-gray-900 dark:text-gray-100">
        {{ $title }}
    </div>
    <div class="text-sm text-gray-600 dark:text-gray-400">
        Code: <span class="font-mono">{{ $code }}</span>
    </div>
    <div class="text-sm text-gray-600 dark:text-gray-400">
        Units: <span class="font-semibold">{{ $units }}</span>
    </div>
</div>
