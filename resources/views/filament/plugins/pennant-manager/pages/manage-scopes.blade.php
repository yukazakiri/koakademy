<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-user-plus" class="h-5 w-5" />
                    <span>Add Scope</span>
                </div>
            </x-slot>

            <form wire:submit.prevent="activateScope" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" color="primary" class="w-full">
                    Add Scope
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                    <span>Active Scopes</span>
                    <x-filament::badge>{{ count($activeScopes) }}</x-filament::badge>
                </div>
            </x-slot>

            @if(count($activeScopes))
                <div style="width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;">
                    <table style="min-width:900px;width:100%;" class="text-left text-sm">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white" style="text-align: left">Scope</th>
                                <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">Type</th>
                                <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">Source</th>
                                <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center">Status</th>
                                <th class="px-4 py-3 font-semibold text-gray-950 dark:text-white text-center whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeScopes as $scope)
                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                    <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        {{ $scope['user_name'] }}
                                    </td>

                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $scope['type'] }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 text-center whitespace-nowrap">
                                        @if($scope['source'] === 'segment')
                                            Segment · {{ $scope['segment'] }}
                                        @elseif($scope['source'] === 'rollout')
                                            Rollout
                                        @else
                                            Manual
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        @if($scope['active'])
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <x-filament::icon icon="heroicon-o-check-circle" class="h-3 w-3 mr-1" />
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                <x-filament::icon icon="heroicon-o-x-circle" class="h-3 w-3 mr-1" />
                                                Inactive
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <button
                                            wire:click="removeScope({{ $scope['id'] }})"
                                            wire:confirm="Are you sure you want to remove this scope?"
                                            class="inline-flex items-center gap-1 text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium"
                                        >
                                            <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-users" class="h-12 w-12 mx-auto mb-3 opacity-50" />
                    <p>No scopes configured yet.</p>
                    <p class="text-sm">Add a scope above to give individual access to this feature.</p>
                </div>
            @endif
        </x-filament::section>
    </div>

</x-filament-panels::page>
