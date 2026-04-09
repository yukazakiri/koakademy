@php
    $course = $course ?? null;
@endphp

@if($course)
    <div class="space-y-1">
        <div class="font-semibold text-gray-900 dark:text-gray-100">
            {{ $course->title }}
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Course Code: <span class="font-mono">{{ $course->code }}</span>
        </div>
        @if($course->school_year)
            <div class="text-sm text-gray-600 dark:text-gray-400">
                School Year: {{ $course->school_year }}
            </div>
        @endif
    </div>
@else
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No course assigned
    </div>
@endif
