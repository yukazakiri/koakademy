<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @php
    // dd($getRecord());
    $enrolledSubjects = \App\Models\SubjectEnrollment::with('subject')
        ->where('enrollment_id', $getRecord()->id)
        // ->where('semester', $getRecord()->semester)
        // ->where('school_year', $getRecord()->school_year)
        ->whereNull('grade')
        ->get()
        ->toArray();
    

    $scheduleData = [];
    foreach ($enrolledSubjects as $enrolledSubject) {
        // Debug enrolled subject
        // dd($enrolledSubject);
        if (!isset($enrolledSubject['subject'])) {
            \Log::error('Subject not found for enrolled subject:', $enrolledSubject);
            continue;
        }

        if (!isset($enrolledSubject['subject']['code'])) {
            \Log::error('Subject code not found:', $enrolledSubject['subject']);
            continue;
        }

        $class = \App\Models\Classes::where('id', $enrolledSubject['section'])
            ->with('Schedule.room')
            ->first();
        
        if ($class) {
            $schedules = $class->Schedule;
            
            if (!$schedules) {
                \Log::error('No schedules found for class:', $class->toArray());
                continue;
            }

            foreach ($schedules as $schedule) {
                if (!$schedule) {
                    \Log::error('Invalid schedule entry');
                    continue;
                }

                $weekDay = strtolower($schedule->day_of_week);
                $startTime = date('g:i A', strtotime($schedule->start_time));
                $endTime = date('g:i A', strtotime($schedule->end_time));
                $room = $schedule->room->name ?? 'No Room';
                $section = $schedule->class->section ?? 'No Section';
                
                $scheduleData[$enrolledSubject['subject']['code']][$weekDay] = "<b>$room | $section</b> <br> $startTime - $endTime";
            }
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
                    <td class="px-6 py-4 whitespace-nowrap">{{ $subjectCode }} </td>
                    @foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                    <td class="px-1 py-1 whitespace-nowrap {{ isset($schedule[$day]) ? 'bg-primary-500/50 border border-primary-500' : '' }}">{!! $schedule[$day] ?? '' !!}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-dynamic-component>
