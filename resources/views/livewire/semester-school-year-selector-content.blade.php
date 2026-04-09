<div class="flex items-center space-x-2 rtl:space-x-reverse">
    {{-- Semester Dropdown --}}
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <x-filament::badge
                :color="$isCustomSemester ? 'warning' : 'primary'"
                class="cursor-pointer sm:flex items-center h-9 px-3 text-sm font-medium"
                :tooltip="$isCustomSemester ? 'Your custom semester setting (different from system default)' : 'Using system default semester'"
            >
                {{-- Display the label based on the current integer value --}}
                <span class="flex items-center">
                    @if($isCustomSemester)
                        <x-filament::icon
                            icon="heroicon-m-user"
                            class="me-1 h-4 w-4"
                            aria-hidden="true"
                        />
                    @else
                        <x-filament::icon
                            icon="heroicon-m-globe-alt"
                            class="me-1 h-4 w-4"
                            aria-hidden="true"
                        />
                    @endif
                    Semester: {{ $availableSemesters[$currentSemester] ?? 'Not Set' }}
                </span>
                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="ms-1 -me-0.5 h-4 w-4 text-gray-400"
                    aria-hidden="true"
                 />
            </x-filament::badge>
        </x-slot>

        <x-filament::dropdown.list>
            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 mb-1">
                {{ $isCustomSemester ? 'Your custom semester' : 'Using system default' }}
            </div>
            
            {{-- Iterate over key-value pairs --}}
            @foreach ($availableSemesters as $semesterValue => $semesterLabel)
                <x-filament::dropdown.list.item
                    {{-- Pass the integer value to the update method --}}
                    wire:click="updateSemester({{ $semesterValue }})"
                    :icon="$currentSemester === $semesterValue ? 'heroicon-m-check-circle' : 'heroicon-m-user'"
                    :color="$currentSemester === $semesterValue ? 'primary' : null"
                    tag="button"
                    type="button"
                >
                    {{-- Display the string label --}}
                    {{ $semesterLabel }}
                    @if($semesterValue === $systemDefaultSemester)
                        <span class="text-xs text-gray-500 ms-1">(System Default)</span>
                    @endif
                </x-filament::dropdown.list.item>
            @endforeach
            
            @if($isCustomSemester)
                <div class="border-t border-gray-100 mt-1"></div>
                <x-filament::dropdown.list.item
                    wire:click="resetSemesterToDefault"
                    icon="heroicon-m-globe-alt"
                    color="gray"
                    tag="button"
                    type="button"
                >
                    Reset to System Default ({{ $availableSemesters[$systemDefaultSemester] ?? 'Unknown' }})
                </x-filament::dropdown.list.item>
            @endif
        </x-filament::dropdown.list>
    </x-filament::dropdown>

    {{-- School Year Dropdown --}}
    <x-filament::dropdown placement="bottom-end">
         <x-slot name="trigger">
            <x-filament::badge
                :color="$isCustomSchoolYear ? 'warning' : 'info'"
                class="cursor-pointer sm:flex items-center h-9 px-3 text-sm font-medium"
                :tooltip="$isCustomSchoolYear ? 'Your custom school year setting (different from system default)' : 'Using system default school year'"
            >
                {{-- Display the label based on the current integer start year value --}}
                <span class="flex items-center">
                    @if($isCustomSchoolYear)
                        <x-filament::icon
                            icon="heroicon-m-user"
                            class="me-1 h-4 w-4"
                            aria-hidden="true"
                        />
                    @else
                        <x-filament::icon
                            icon="heroicon-m-globe-alt"
                            class="me-1 h-4 w-4"
                            aria-hidden="true"
                        />
                    @endif
                    School Year: {{ $availableSchoolYears[$currentSchoolYearStart] ?? 'Not Set' }}
                </span>
                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="ms-1 -me-0.5 h-4 w-4 text-gray-400"
                    aria-hidden="true"
                 />
            </x-filament::badge>
        </x-slot>

        <x-filament::dropdown.list>
            <div class="px-4 py-2 text-xs text-gray-500 border-b border-gray-100 mb-1">
                {{ $isCustomSchoolYear ? 'Your custom school year' : 'Using system default' }}
            </div>
            
             {{-- Iterate over key-value pairs --}}
            @foreach ($availableSchoolYears as $yearValue => $yearLabel)
                 <x-filament::dropdown.list.item
                     {{-- Pass the integer start year value to the update method --}}
                    wire:click="updateSchoolYear({{ $yearValue }})"
                    :icon="$currentSchoolYearStart === $yearValue ? 'heroicon-m-check-circle' : 'heroicon-m-user'"
                    :color="$currentSchoolYearStart === $yearValue ? 'primary' : null"
                    tag="button"
                    type="button"
                >
                     {{-- Display the string label --}}
                    {{ $yearLabel }}
                    @if($yearValue === $systemDefaultSchoolYearStart)
                        <span class="text-xs text-gray-500 ms-1">(System Default)</span>
                    @endif
                </x-filament::dropdown.list.item>
            @endforeach
            
            @if($isCustomSchoolYear)
                <div class="border-t border-gray-100 mt-1"></div>
                <x-filament::dropdown.list.item
                    wire:click="resetSchoolYearToDefault"
                    icon="heroicon-m-globe-alt"
                    color="gray"
                    tag="button"
                    type="button"
                >
                    Reset to System Default ({{ $availableSchoolYears[$systemDefaultSchoolYearStart] ?? 'Unknown' }})
                </x-filament::dropdown.list.item>
            @endif
        </x-filament::dropdown.list>
    </x-filament::dropdown>

    {{-- Display Legend for Icons --}}
    <div class="hidden md:block">
        <span class="inline-flex items-center text-xs text-gray-500 ml-2">
            <x-filament::icon icon="heroicon-m-globe-alt" class="w-3 h-3 mr-1" />
            System Default
            <x-filament::icon icon="heroicon-m-user" class="w-3 h-3 mr-1 ml-2" />
            Your Custom Setting
        </span>
    </div>

    {{-- Display feedback messages --}}
    @if (session()->has('error'))
        <div class="text-danger-500 text-xs mt-1 ms-1">
            <x-filament::icon icon="heroicon-o-x-circle" class="h-4 w-4 inline-block" />
            {{ session('error') }}
        </div>
    @elseif (session()->has('success'))
         <div class="text-success-500 text-xs mt-1 ms-1">
             <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 inline-block" />
             {{ session('success') }}
         </div>
    @endif
    {{-- Remove the session flash display section as Filament handles notifications --}}
</div>
