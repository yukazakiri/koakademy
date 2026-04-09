<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timetable Schedule</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 15px;
            padding: 0;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 22px;
            font-weight: 600;
        }
        .header p {
            margin: 3px 0;
            font-size: 14px;
            color: #555;
        }
        .header .entity-name {
            font-weight: bold;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px; /* Adjusted base font size for table */
        }
        th, td {
            border: 1px solid #ccc; /* Lighter border */
            padding: 5px; /* Reduced padding */
            text-align: center;
            vertical-align: top; /* Align content to the top */
        }
        th {
            background-color: #f0f0f0; /* Lighter background for headers */
            font-weight: bold;
            font-size: 12px;
        }
        .schedule-cell {
            padding: 4px; /* Reduced padding */
            border-radius: 3px;
            font-weight: 500;
            font-size: 10px; /* Slightly smaller font for cell content */
            line-height: 1.3;
            min-height: 30px; /* Minimum height for cells */
            position: relative;
        }
        .schedule-cell div {
            margin-bottom: 2px; /* Space between lines */
        }
        .subject-title {
            font-weight: bold;
        }

        /* Color classes - ensure these are broad enough for various subjects */
        .bg-red { background-color: rgba(239, 68, 68, 0.4); border-left: 3px solid rgba(239, 68, 68, 0.8); }
        .bg-blue { background-color: rgba(59, 130, 246, 0.4); border-left: 3px solid rgba(59, 130, 246, 0.8); }
        .bg-green { background-color: rgba(34, 197, 94, 0.4); border-left: 3px solid rgba(34, 197, 94, 0.8); }
        .bg-yellow { background-color: rgba(234, 179, 8, 0.4); border-left: 3px solid rgba(234, 179, 8, 0.8); color: #543400;}
        .bg-purple { background-color: rgba(168, 85, 247, 0.4); border-left: 3px solid rgba(168, 85, 247, 0.8); }
        .bg-pink { background-color: rgba(236, 72, 153, 0.4); border-left: 3px solid rgba(236, 72, 153, 0.8); }
        .bg-indigo { background-color: rgba(99, 102, 241, 0.4); border-left: 3px solid rgba(99, 102, 241, 0.8); }
        .bg-orange { background-color: rgba(249, 115, 22, 0.4); border-left: 3px solid rgba(249, 115, 22, 0.8); }
        .bg-teal { background-color: rgba(20, 184, 166, 0.4); border-left: 3px solid rgba(20, 184, 166, 0.8); }
        .bg-cyan { background-color: rgba(6, 182, 212, 0.4); border-left: 3px solid rgba(6, 182, 212, 0.8); }
        .bg-lime { background-color: rgba(132, 204, 22, 0.4); border-left: 3px solid rgba(132, 204, 22, 0.8); color: #3a480b;}
        .bg-emerald { background-color: rgba(16, 185, 129, 0.4); border-left: 3px solid rgba(16, 185, 129, 0.8); }
        .bg-violet { background-color: rgba(139, 92, 246, 0.4); border-left: 3px solid rgba(139, 92, 246, 0.8); }
        .bg-fuchsia { background-color: rgba(217, 70, 239, 0.4); border-left: 3px solid rgba(217, 70, 239, 0.8); }
        .bg-rose { background-color: rgba(244, 63, 94, 0.4); border-left: 3px solid rgba(244, 63, 94, 0.8); }
        .bg-sky { background-color: rgba(14, 165, 233, 0.4); border-left: 3px solid rgba(14, 165, 233, 0.8); }
        .bg-amber { background-color: rgba(245, 158, 11, 0.4); border-left: 3px solid rgba(245, 158, 11, 0.8); color: #563a06;}

        .empty-cell {
            background-color: #fdfdfd;
        }
        .time-header-cell {
            min-width: 80px; /* Ensure time column has enough space */
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            {{ ucfirst($selectedView) }} Schedule: <span class="entity-name">{{ $entityName }}</span>
        </h1>
        <p>School Year: {{ $currentSchoolYear }} - Semester: {{ $currentSemester }}</p>
        <p>Generated on: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    <table>
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
                $schedules = collect($schedules); // Ensure it's a collection
                $processedTimeSlots = [];
                foreach ($timeSlots as $ts) {
                    $processedTimeSlots[] = \Carbon\Carbon::parse($ts)->format('H:i');
                }

                // Use the enhanced subject colors if available, otherwise generate them
                $subjectColorMap = [];
                if (isset($subjectColors) && !empty($subjectColors)) {
                    foreach ($subjectColors as $code => $colorData) {
                        $subjectColorMap[$code] = $colorData['class'];
                    }
                } else {
                    // Fallback to original color generation
                    $colorClasses = [
                        'bg-red', 'bg-blue', 'bg-green', 'bg-yellow', 'bg-purple', 'bg-pink',
                        'bg-indigo', 'bg-orange', 'bg-teal', 'bg-cyan', 'bg-lime', 'bg-emerald',
                        'bg-violet', 'bg-fuchsia', 'bg-rose', 'bg-sky', 'bg-amber',
                    ];
                    $colorIndex = 0;

                    $schedules->each(function($schedule) use (&$subjectColorMap, $colorClasses, &$colorIndex, $selectedView) {
                        $key = null;
                        if (isset($schedule['class']['subject']['code'])) {
                            $key = $schedule['class']['subject']['code'];
                        } elseif (isset($schedule['class']['id'])) {
                             // Fallback for student view where subject might not be primary grouping entity
                            $key = 'class_'.$schedule['class']['id'];
                        }

                        if ($key && !isset($subjectColorMap[$key])) {
                            $subjectColorMap[$key] = $colorClasses[$colorIndex % count($colorClasses)];
                            $colorIndex++;
                        }
                    });
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
                        // Handle both array and object formats
                        $startTime = is_array($schedule) ? $schedule['start_time'] : $schedule['start_time'];
                        return is_string($startTime) ? $startTime : \Carbon\Carbon::parse($startTime)->format('H:i');
                    });
                }

            @endphp

            @foreach ($processedTimeSlots as $timeIndex => $time)
                @php
                    $slotStartTime = \Carbon\Carbon::parse($time);
                    // Define slot duration (e.g., 1 hour, matching $this->getTimeSlots())
                    // This assumes your getTimeSlots are hourly. Adjust if they are 30-min etc.
                    $nextSlotTime = isset($processedTimeSlots[$timeIndex + 1]) ? \Carbon\Carbon::parse($processedTimeSlots[$timeIndex + 1]) : $slotStartTime->copy()->addHour();
                    $slotEndTime = $nextSlotTime;
                @endphp
                <tr>
                    <td class="time-header-cell">
                        {{ $slotStartTime->format('h:i A') }}
                         {{-- - {{ $slotEndTime->format('h:i A') }} --}}
                    </td>
                    @foreach ($days as $day)
                        @php $currentDayLower = strtolower($day); @endphp
                        @if (!isset($rowspans[$currentDayLower][$time]))
                            @php
                                $conflictingSchedule = null;
                                // Find a schedule that STARTS in this slot for this day
                                if(isset($schedulesMap[$currentDayLower][$time])) {
                                    $conflictingSchedule = $schedulesMap[$currentDayLower][$time];
                                }
                            @endphp

                            @if ($conflictingSchedule)
                                @php
                                    // Handle both array and object formats for times
                                    $startTimeValue = is_array($conflictingSchedule) ? $conflictingSchedule['start_time'] : $conflictingSchedule['start_time'];
                                    $endTimeValue = is_array($conflictingSchedule) ? $conflictingSchedule['end_time'] : $conflictingSchedule['end_time'];

                                    $scheduleStart = is_string($startTimeValue) ? \Carbon\Carbon::parse($startTimeValue) : $startTimeValue;
                                    $scheduleEnd = is_string($endTimeValue) ? \Carbon\Carbon::parse($endTimeValue) : $endTimeValue;

                                    // Calculate rowspan based on 1-hour slots or actual duration
                                    // This needs to be robust if slots are not always 1hr or schedules don't align perfectly.
                                    // For simplicity, let's assume getTimeSlots are start of each hour.
                                    $durationMinutes = $scheduleStart->diffInMinutes($scheduleEnd);
                                    $rowspan = 0;
                                    $tempSlotStart = $slotStartTime->copy();
                                    while($tempSlotStart < $scheduleEnd && $tempSlotStart < $slotStartTime->copy()->addDay()){ // Limit to one day
                                        $rowspan++;
                                        if(isset($processedTimeSlots[$timeIndex + $rowspan])){
                                            $tempSlotStart = \Carbon\Carbon::parse($processedTimeSlots[$timeIndex + $rowspan]);
                                        } else {
                                            // If next slot is not in processedTimeSlots, assume hourly increment for calculation
                                            $tempSlotStart->addHour();
                                        }
                                    }
                                    $rowspan = max(1, $rowspan); // Ensure at least 1


                                    for ($i = 0; $i < $rowspan; $i++) {
                                        if(isset($processedTimeSlots[$timeIndex + $i])){
                                            $rowspans[$currentDayLower][$processedTimeSlots[$timeIndex + $i]] = true;
                                        }
                                    }

                                    $colorKey = null;
                                    if (isset($conflictingSchedule['class']['subject']['code'])) {
                                        $colorKey = $conflictingSchedule['class']['subject']['code'];
                                    } elseif (isset($conflictingSchedule['class']['id'])) {
                                        $colorKey = 'class_'.$conflictingSchedule['class']['id'];
                                    }
                                    $bgColorClass = $subjectColorMap[$colorKey] ?? 'bg-gray'; // Default color
                                @endphp
                                <td rowspan="{{ $rowspan }}" class="schedule-cell {{ $bgColorClass }}">
                                    <div class="subject-title">{{ $conflictingSchedule['class']['subject']['title'] ?? 'N/A' }}</div>
                                    <div>Sec: {{ $conflictingSchedule['class']['section'] ?? 'N/A' }}</div>
                                    <div>
                                        @if(isset($conflictingSchedule['start_time_formatted']))
                                            {{ $conflictingSchedule['start_time_formatted'] }} - {{ $conflictingSchedule['end_time_formatted'] }}
                                        @else
                                            {{ $scheduleStart->format('h:i A') }} - {{ $scheduleEnd->format('h:i A') }}
                                        @endif
                                    </div>
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

    @if(!empty($subjectColorMap) || !empty($subjectColors))
    <div style="margin-top: 20px; padding-top:10px; border-top: 1px solid #eee; font-size:10px;">
        <strong>Legend:</strong>
        @if(isset($subjectColors) && !empty($subjectColors))
            @foreach($subjectColors as $code => $colorData)
                <span style="margin-right: 10px; padding: 2px 5px; border-radius: 3px;" class="{{ $colorData['class'] }}">
                    {{ $colorData['title'] }} ({{ $code }})
                </span>
            @endforeach
        @else
            @foreach($subjectColorMap as $key => $colorClass)
                @php
                    // Attempt to find the original schedule entry to get a display name for the legend key
                    $legendName = $key; // Default to key
                    if (strpos($key, 'class_') === 0) {
                        $classId = substr($key, strlen('class_'));
                        $scheduleEntryForLegend = $schedules->firstWhere('class.id', (int)$classId);
                        if ($scheduleEntryForLegend && isset($scheduleEntryForLegend['class']['subject']['title'])) {
                            $legendName = $scheduleEntryForLegend['class']['subject']['title'] . " (Sec: " . ($scheduleEntryForLegend['class']['section'] ?? 'N/A') . ")";
                        } elseif($scheduleEntryForLegend) {
                             $legendName = "Class ID: " . $classId;
                        }
                    } else {
                        // Assuming key is subject code
                        $scheduleEntryForLegend = $schedules->firstWhere('class.subject.code', $key);
                         if ($scheduleEntryForLegend && isset($scheduleEntryForLegend['class']['subject']['title'])) {
                            $legendName = $scheduleEntryForLegend['class']['subject']['title'] . " (Code: " . $key .")";
                        }
                    }
                @endphp
                <span style="margin-right: 10px; padding: 2px 5px; border-radius: 3px;" class="{{ $colorClass }}">
                    {{ $legendName }}
                </span>
            @endforeach
        @endif
    </div>
    @endif

</body>
</html> 