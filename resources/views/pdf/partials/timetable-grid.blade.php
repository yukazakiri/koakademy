<table class="timetable">
    <thead>
        <tr>
            <th class="time-header-cell">Time / Day</th>
            @foreach ($days as $day)
                <th>{{ $day }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @php
            $schedules = collect($schedules);
            $processedTimeSlots = [];
            foreach ($timeSlots as $ts) {
                $processedTimeSlots[] = \Carbon\Carbon::parse($ts)->format('H:i');
            }

            $rowspans = [];
            foreach ($days as $day) {
                $rowspans[strtolower($day)] = [];
            }

            // Prepare schedules map for quick lookup
            $schedulesMap = [];
            foreach ($days as $day) {
                $schedulesMap[strtolower($day)] = $schedules->filter(function ($schedule) use ($day) {
                    return strtolower($schedule['day_of_week']) === strtolower($day);
                })->keyBy(function($schedule){
                    return $schedule['start_time'];
                });
            }
        @endphp

        @foreach ($processedTimeSlots as $timeIndex => $time)
            @php
                $slotStartTime = \Carbon\Carbon::parse($time);
                $nextSlotTime = isset($processedTimeSlots[$timeIndex + 1]) ? \Carbon\Carbon::parse($processedTimeSlots[$timeIndex + 1]) : $slotStartTime->copy()->addHour();
                $slotEndTime = $nextSlotTime;
            @endphp
            <tr>
                <td class="time-header-cell">
                    {{ $slotStartTime->format('h:i A') }}
                </td>
                @foreach ($days as $day)
                    @php $currentDayLower = strtolower($day); @endphp
                    @if (!isset($rowspans[$currentDayLower][$time]))
                        @php
                            $conflictingSchedule = null;
                            if(isset($schedulesMap[$currentDayLower][$time])) {
                                $conflictingSchedule = $schedulesMap[$currentDayLower][$time];
                            }
                        @endphp

                        @if ($conflictingSchedule)
                            @php
                                $scheduleStart = \Carbon\Carbon::parse($conflictingSchedule['start_time']);
                                $scheduleEnd = \Carbon\Carbon::parse($conflictingSchedule['end_time']);

                                $durationMinutes = $scheduleStart->diffInMinutes($scheduleEnd);
                                $rowspan = 0;
                                $tempSlotStart = $slotStartTime->copy();
                                while($tempSlotStart < $scheduleEnd && $tempSlotStart < $slotStartTime->copy()->addDay()){
                                    $rowspan++;
                                    if(isset($processedTimeSlots[$timeIndex + $rowspan])){
                                        $tempSlotStart = \Carbon\Carbon::parse($processedTimeSlots[$timeIndex + $rowspan]);
                                    } else {
                                        $tempSlotStart->addHour();
                                    }
                                }
                                $rowspan = max(1, $rowspan);

                                for ($i = 0; $i < $rowspan; $i++) {
                                    if(isset($processedTimeSlots[$timeIndex + $i])){
                                        $rowspans[$currentDayLower][$processedTimeSlots[$timeIndex + $i]] = true;
                                    }
                                }

                                $subjectCode = $conflictingSchedule['class']['subject']['code'] ?? 'N/A';
                                $bgColorClass = $subjectColors[$subjectCode]['class'] ?? 'bg-gray';
                            @endphp
                            <td rowspan="{{ $rowspan }}" class="schedule-cell {{ $bgColorClass }}">
                                <div class="subject-title">{{ $conflictingSchedule['class']['subject']['title'] ?? 'N/A' }}</div>
                                <div>Sec: {{ $conflictingSchedule['class']['section'] ?? 'N/A' }}</div>
                                <div>{{ $conflictingSchedule['start_time_formatted'] ?? $scheduleStart->format('h:i A') }} - {{ $conflictingSchedule['end_time_formatted'] ?? $scheduleEnd->format('h:i A') }}</div>
                                @if ($selectedView !== 'room' && isset($conflictingSchedule['room']['name']))
                                    <div>Room: {{ $conflictingSchedule['room']['name'] }}</div>
                                @endif
                                @if ($selectedView !== 'faculty' && isset($conflictingSchedule['class']['faculty']['full_name']))
                                    <div>Faculty: {{ $conflictingSchedule['class']['faculty']['full_name'] }}</div>
                                @endif
                            </td>
                        @else
                            <td class="empty-cell"></td>
                        @endif
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
