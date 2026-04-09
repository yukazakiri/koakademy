<div>
    <div class="text-sm text-gray-600 fi-in-entry">
        <div class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('filament-passkeys::passkeys.description') }} Test
        </div>
        
        <!-- Create Passkey Form -->
        <div class="mt-4">
            <form id="passkeyForm" wire:submit="validatePasskeyProperties" class="flex items-start gap-3">
                <div class="flex-1">
                    <x-filament::input.wrapper prefix="{{ __('filament-passkeys::passkeys.name') }}" :valid="! $errors->has('name')">
                        <x-filament::input
                            type="text"
                            wire:model="name"
                            placeholder="{{ __('filament-passkeys::passkeys.name_placeholder') }}"
                        />
                    </x-filament::input.wrapper>
        
                    @error('name')
                        <p class="fi-fo-field-wrp-error-message">{{ $message }}</p>
                    @enderror
                </div>
        
                <x-filament::button type="submit" size="sm">
                    {{ __('passkeys::passkeys.create') }}
                </x-filament::button>
            </form>
        </div>

        <!-- Passkeys List -->
        @if($passkeys->isNotEmpty())
            <div class="fi-in-entry mt-4">
                @foreach($passkeys as $passkey)
                    <div class="fi-sc fi-inline fi-sc-has-gap mb-3">
                        <div>
                            <x-filament::icon
                                icon="heroicon-o-key"
                                class="w-8 h-8 text-gray-500 dark:text-gray-400"
                            />
                        </div>

                        <div class="ms-3 flex-1">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $passkey->name }}
                            </div>

                            <div>
                                <div class="text-xs text-gray-500">
                                    {{ __('passkeys::passkeys.last_used') }}: 
                                    @if($passkey->last_used_at)
                                        <span class="font-medium">{{ $passkey->last_used_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-gray-400">{{ __('passkeys::passkeys.not_used_yet') }}</span>
                                    @endif
                                    
                                    @if($passkey->created_at)
                                        • {{ __('passkeys::passkeys.created') }}: {{ $passkey->created_at->diffForHumans() }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="ms-auto">
                            {{ ($this->deleteAction)(['passkey' => $passkey->id]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="fi-in-entry mt-4">
                <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                    {{ __('passkeys::passkeys.no_passkeys_registered') }}
                </div>
            </div>
        @endif
    </div>
    
    <x-filament-actions::modals />
    
    @include('passkeys::livewire.partials.createScript')
</div>
