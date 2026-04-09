@php
    $record = $getRecord();
    $schedules = $record->Schedule()->with('room')->get();
    $subjectCode = $record->subject_code;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="fi-in-entry-wrp">
        <x-timetable :schedules="$schedules" :code="$subjectCode" />
    </div>
</x-dynamic-component>
