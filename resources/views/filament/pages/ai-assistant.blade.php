<x-filament-panels::page>
    @php
        $summary = $this->getActiveProviderSummary();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Active AI Provider</span>
            <div class="text-base font-semibold mt-1 text-gray-900 dark:text-white flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-emerald-500"></span>
                {{ $summary['provider'] }}
            </div>
        </div>

        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Chat & Reasoning Model</span>
            <div class="text-base font-semibold mt-1 text-gray-900 dark:text-white font-mono text-sm">
                {{ $summary['chat_model'] }}
            </div>
        </div>

        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">Failover Status</span>
                <div class="text-base font-semibold mt-1 text-gray-900 dark:text-white">
                    {{ ucfirst($summary['failover']) }}
                </div>
            </div>
            <a href="/administrators/system-management/ai" class="text-xs text-primary-600 dark:text-primary-400 hover:underline font-medium">
                Manage &rarr;
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Control & Prompt Panel -->
        <div class="lg:col-span-5 space-y-4">
            <div class="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs space-y-4">
                <div>
                    <label for="selectedAgent" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Target Institutional Agent
                    </label>
                    <select
                        id="selectedAgent"
                        wire:model.live="selectedAgent"
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-xs focus:border-primary-500 focus:ring-primary-500"
                    >
                        <option value="registrar_auditor">Registrar Compliance & Records Auditor</option>
                        <option value="bursar_finance">Bursar & Tuition Intelligence</option>
                        <option value="campus_support">Campus 24/7 Support & Policies</option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Select which specialist agent receives your prompt.
                    </p>
                </div>

                <div>
                    <label for="prompt" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Prompt / Task Description
                    </label>
                    <textarea
                        id="prompt"
                        wire:model="prompt"
                        rows="6"
                        placeholder="e.g. Audit graduation clearance for student ID 1, or explain tuition assessment fees..."
                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-xs focus:border-primary-500 focus:ring-primary-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button
                        type="button"
                        wire:click="clearWorkspace"
                        class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                    >
                        Clear Workspace
                    </button>

                    <button
                        type="button"
                        wire:click="askAgent"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-colors disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="askAgent">Execute Agent Prompt</span>
                        <span wire:loading wire:target="askAgent" class="inline-flex items-center gap-1.5">
                            <svg class="animate-spin size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Evaluating Tools & Generating...
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Agent Response & Execution Output -->
        <div class="lg:col-span-7">
            <div class="p-5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs h-full flex flex-col">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-3 mb-4">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-chat-bubble-left-right class="size-4 text-primary-500" />
                        Agent Execution Output
                    </h3>
                    @if($response)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 font-medium">
                            Completed
                        </span>
                    @endif
                </div>

                <div class="flex-1 min-h-[300px] overflow-y-auto">
                    @if($response)
                        <div class="prose dark:prose-invert max-w-none text-xs leading-relaxed whitespace-pre-wrap font-sans">
{{ $response }}
                        </div>
                    @else
                        <div class="h-full flex flex-col items-center justify-center text-center p-8 text-gray-400 dark:text-gray-500 space-y-2">
                            <x-heroicon-o-cpu-chip class="size-8 stroke-1 text-gray-300 dark:text-gray-700" />
                            <p class="text-xs">Select an agent and click "Execute Agent Prompt" to view output and tool execution logs.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
