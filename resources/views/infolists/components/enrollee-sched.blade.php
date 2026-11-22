<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
    $enrolledSubjects = \App\Models\SubjectEnrollment::with('subject')
        ->where('enrollment_id', $getRecord()->id)
        ->whereNull('grade')
        ->get();

    $classIds = $enrolledSubjects
        ->pluck('section')
        ->filter()
        ->unique()
        ->values();

    $classesById = $classIds->isNotEmpty()
        ? \App\Models\Classes::query()
            ->whereIn('id', $classIds)
            ->with('Schedule.room')
            ->get()
            ->keyBy('id')
        : collect();

    $scheduleData = [];
    foreach ($enrolledSubjects as $enrolledSubject) {
        $subjectCode = $enrolledSubject->subject?->code;

        if (! filled($subjectCode)) {
            continue;
        }

        $class = $classesById->get($enrolledSubject->section);

        if (! $class || ! $class->Schedule) {
            continue;
        }

        foreach ($class->Schedule as $schedule) {
            if (! $schedule) {
                continue;
            }

            $weekDay = strtolower((string) $schedule->day_of_week);
            $startTime = date('g:i A', strtotime((string) $schedule->start_time));
            $endTime = date('g:i A', strtotime((string) $schedule->end_time));
            $room = (string) ($schedule->room?->name ?? 'No Room');
            $section = (string) ($class->section ?? 'No Section');

            $scheduleData[$subjectCode][$weekDay] = [
                'room' => $room,
                'section' => $section,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        }
    }
    @endphp
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subject</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tue</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Wed</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thu</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fri</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sat</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($scheduleData as $subjectCode => $schedule)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $subjectCode }}</td>
                    @foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                    <td class="px-1 py-1 whitespace-nowrap {{ isset($schedule[$day]) ? 'bg-primary-500/50 border border-primary-500' : '' }}">
                        @if (isset($schedule[$day]))
                            <b>{{ $schedule[$day]['room'] }} | {{ $schedule[$day]['section'] }}</b>
                            <br>
                            {{ $schedule[$day]['start_time'] }} - {{ $schedule[$day]['end_time'] }}
                        @endif
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
