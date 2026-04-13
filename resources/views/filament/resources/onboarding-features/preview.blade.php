@props(['feature', 'featureClass', 'steps', 'overrideCount', 'globalState'])

<div class="space-y-4">
    {{-- Feature metadata grid --}}
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400 mb-1">Feature Key</p>
            <code class="bg-gray-100 dark:bg-gray-800 rounded px-2 py-1 font-mono text-xs">{{ $feature->feature_key }}</code>
        </div>
        @if($featureClass)
            <div>
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400 mb-1">Pennant Class</p>
                <code class="bg-gray-100 dark:bg-gray-800 rounded px-2 py-1 font-mono text-xs block truncate">{{ $featureClass }}</code>
            </div>
        @endif
        @if($feature->cta_url)
            <div>
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400 mb-1">CTA</p>
                <a href="{{ $feature->cta_url }}" class="text-primary-600 text-xs hover:underline">
                    {{ $feature->cta_label ?: $feature->cta_url }}
                </a>
            </div>
        @endif
        <div>
            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400 mb-1">User Overrides</p>
            <span class="text-xs">{{ $overrideCount }} user{{ $overrideCount !== 1 ? 's' : '' }} with overrides</span>
        </div>
    </div>

    {{-- Audience & status badges --}}
    <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-400">
            @if($feature->audience === 'student') Students
            @elseif($feature->audience === 'faculty') Faculty
            @else Everyone
            @endif
        </span>
        @if($feature->is_active)
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400">Active</span>
        @else
            <span class="inline-flex items-center rounded-full bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-800 dark:text-gray-400">Inactive</span>
        @endif
        @if($featureClass)
            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-400">Pennant Class</span>
        @endif
        @if($globalState)
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-400">Global</span>
        @endif
    </div>

    {{-- Onboarding Steps --}}
    @if(count($steps) > 0)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
            <p class="text-xs font-medium mb-2">Onboarding Steps ({{ count($steps) }})</p>
            <div class="space-y-2">
                @foreach($steps as $index => $step)
                    @php
                        $stepData = is_array($step) && isset($step['data']) ? $step['data'] : (is_array($step) ? $step : []);
                    @endphp
                    <div class="flex gap-2.5 rounded-md border border-gray-200 dark:border-gray-700 p-2.5">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400 text-[10px] font-bold">
                            {{ $index + 1 }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium leading-tight">{{ $stepData['title'] ?? 'Untitled' }}</p>
                            <p class="text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2 text-[11px]">{{ $stepData['summary'] ?? '' }}</p>
                            @if(!empty(array_filter($stepData['highlights'] ?? [])))
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach(array_filter($stepData['highlights'] ?? []) as $highlight)
                                        <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-[9px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ $highlight }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
