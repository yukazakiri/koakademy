<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Class Schedule</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            font-size: 16px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .schedule-cell {
            padding: 10px;
            border-radius: 4px;
            font-weight: 600;
        }
        .bg-red { background-color: rgba(239, 68, 68, 0.3); }
        .bg-blue { background-color: rgba(59, 130, 246, 0.3); }
        .bg-green { background-color: rgba(34, 197, 94, 0.3); }
        .bg-yellow { background-color: rgba(234, 179, 8, 0.3); }
        .bg-purple { background-color: rgba(168, 85, 247, 0.3); }
        .bg-pink { background-color: rgba(236, 72, 153, 0.3); }
        .bg-indigo { background-color: rgba(99, 102, 241, 0.3); }
        .bg-orange { background-color: rgba(249, 115, 22, 0.3); }
        .bg-teal { background-color: rgba(20, 184, 166, 0.3); }
        .bg-cyan { background-color: rgba(6, 182, 212, 0.3); }
        .bg-lime { background-color: rgba(132, 204, 22, 0.3); }
        .bg-emerald { background-color: rgba(16, 185, 129, 0.3); }
        .bg-violet { background-color: rgba(139, 92, 246, 0.3); }
        .bg-fuchsia { background-color: rgba(217, 70, 239, 0.3); }
        .bg-rose { background-color: rgba(244, 63, 94, 0.3); }
        .bg-sky { background-color: rgba(14, 165, 233, 0.3); }
        .bg-amber { background-color: rgba(245, 158, 11, 0.3); }

        .page-break {
            page-break-before: always;
        }
        .day-schedule {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .day-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            font-size: 18px;
        }
        .schedule-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .schedule-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .schedule-item:last-child {
            border-bottom: none;
        }
        .time-slot {
            font-weight: bold;
            min-width: 150px;
        }
        .subject-info {
            flex-grow: 1;
            padding: 0 20px;
        }
        .room-info {
            min-width: 120px;
            text-align: right;
        }
        .legend {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .legend-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .legend-item {
            display: inline-block;
            margin-right: 15px;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .subject-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
            font-size: 0.85em;
        }
        .subject-table th {
            padding: 6px;
            font-size: 0.9em;
            background-color: #f5f5f5;
            text-align: left;
        }
        .subject-table td {
            padding: 6px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .subject-code {
            font-weight: bold;
            font-size: 1em;
            white-space: nowrap;
        }
        .schedule-time {
            padding: 2px 0;
            font-size: 0.85em;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .schedule-time strong {
            display: inline-block;
            width: 65px;
            font-weight: 600;
        }
        .room-slot {
            font-size: 0.85em;
            padding: 2px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        .subject-table tr:nth-child(even) {
            background-color: rgba(0,0,0,0.02);
        }
    </style>
</head>
<body>
    <!-- First page with timeline view -->
    <div class="header">
        <h1>Class Schedule - Timeline View</h1>
        <p>Course: {{ $course['code'] ?? '' }}</p>
        <p>Year Level: {{ $academicYear }} - Semester {{ $semester }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Time</th>
                @foreach (['Mon', 'Tue', 'Wed', 'Thur', 'Fri', 'Sat'] as $day)
                    <th>{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php
                $schedules = collect($schedules);
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

                $subjectColors = [
                    'bg-red',
                    'bg-blue',
                    'bg-green',
                    'bg-yellow',
                    'bg-purple',
                    'bg-pink',
                    'bg-indigo',
                    'bg-orange',
                    'bg-teal',
                    'bg-cyan',
                    'bg-lime',
                    'bg-emerald',
                    'bg-violet',
                    'bg-fuchsia',
                    'bg-rose',
                    'bg-sky',
                    'bg-amber',
                ];

                $subjectColorMap = $schedules
                    ->pluck('class.subject_code')
                    ->unique()
                    ->mapWithKeys(fn($code, $i) => [$code => $subjectColors[$i % count($subjectColors)]]);

                $timeSlots = collect();
                $start = \Carbon\Carbon::createFromTime(8, 0);
                $end = \Carbon\Carbon::createFromTime(19, 0);
                while ($start < $end) {
                    $timeSlots->push($start->format('H:i'));
                    $start->addMinutes(30);
                }

                $schedulesMap = $schedules
                    ->groupBy('day_of_week')
                    ->map(fn($daySchedules) => $daySchedules->keyBy('start_time'));

                foreach ($days as $day) {
                    if (!isset($schedulesMap[$day])) {
                        $schedulesMap[$day] = collect();
                    }
                }

                $rowspans = array_fill_keys($days, []);
            @endphp

            @foreach ($timeSlots as $time)
                <tr>
                    <td>
                        @php
                            $startTime = \Carbon\Carbon::createFromFormat('H:i', $time);
                            $endTime = $startTime->copy()->addMinutes(30);
                        @endphp
                        {{ $startTime->format('g:i A') }} - {{ $endTime->format('g:i A') }}
                    </td>
                    @foreach ($days as $day)
                        @if (!isset($rowspans[$day][$time]))
                            @php
                                $schedule = $schedulesMap[$day]->first(function ($schedule) use ($time) {
                                    if (!$schedule) return false;
                                    $scheduleStart = \Carbon\Carbon::createFromFormat('H:i', $schedule['start_time']);
                                    $scheduleEnd = \Carbon\Carbon::createFromFormat('H:i', $schedule['end_time']);
                                    $currentTime = \Carbon\Carbon::createFromFormat('H:i', $time);
                                    return $currentTime >= $scheduleStart && $currentTime < $scheduleEnd;
                                });
                            @endphp
                            @if ($schedule)
                                @php
                                    $scheduleStart = \Carbon\Carbon::createFromFormat('H:i', $schedule['start_time']);
                                    $scheduleEnd = \Carbon\Carbon::createFromFormat('H:i', $schedule['end_time']);
                                    $rowspan = ceil($scheduleStart->diffInMinutes($scheduleEnd) / 30);
                                    for ($i = 0; $i < $rowspan; $i++) {
                                        $rowspans[$day][
                                            $scheduleStart
                                                ->copy()
                                                ->addMinutes(30 * $i)
                                                ->format('H:i')
                                        ] = true;
                                    }
                                @endphp
                                <td rowspan="{{ $rowspan }}" class="schedule-cell {{ $subjectColorMap[$schedule['class']['subject_code']] ?? '' }}">
                                    {{ $schedule['class']['subject_code'] ?? 'N/A' }} ({{ $schedule['class']['section'] ?? 'N/A' }})
                                    <br>
                                    {{ $schedule['room']['name'] ?? 'N/A' }}
                                </td>
                            @else
                                <td></td>
                            @endif
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Second page with subject table view -->
    <div class="page-break">
        <div class="header">
            <h1>Class Schedule - Subject View</h1>
            <p>Course: {{ $course['code'] ?? '' }}</p>
            <p>Year Level: {{ $academicYear }} - Semester {{ $semester }}</p>
        </div>

        @php
            $schedules = collect($schedules);
            $days = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday'
            ];
            
            // Group schedules by subject code AND section
            $subjectSchedules = $schedules->groupBy(function ($schedule) {
                return $schedule['class']['subject_code'] . '_' . $schedule['class']['section'];
            });
        @endphp

        <table class="subject-table">
            <thead>
                <tr>
                    <th style="width: 15%">Subject</th>
                    <th style="width: 10%">Section</th>
                    <th style="width: 50%">Schedule</th>
                    <th style="width: 25%">Room</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subjectSchedules as $subjectKey => $subjectClasses)
                    @php
                        $subjectCode = explode('_', $subjectKey)[0];
                        $section = explode('_', $subjectKey)[1];
                    @endphp
                    <tr class="subject-row {{ $subjectColorMap[$subjectCode] ?? '' }}">
                        <td class="subject-code">
                            {{ $subjectCode }}
                        </td>
                        <td>
                            {{ $section }}
                        </td>
                        <td>
                            @php
                                $daySchedules = $subjectClasses->groupBy('day_of_week');
                                $sortedDays = collect($days)->filter(function($dayName, $dayKey) use ($daySchedules) {
                                    return $daySchedules->has($dayKey);
                                });
                            @endphp
                            @foreach ($sortedDays as $dayKey => $dayName)
                                @foreach ($daySchedules[$dayKey] as $schedule)
                                    <div class="schedule-time">
                                        <strong>{{ $dayName }}</strong>: 
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $schedule['start_time'])->format('g:i A') }} - 
                                        {{ \Carbon\Carbon::createFromFormat('H:i', $schedule['end_time'])->format('g:i A') }}
                                    </div>
                                @endforeach
                            @endforeach
                        </td>
                        <td>
                            @foreach ($sortedDays as $dayKey => $dayName)
                                @foreach ($daySchedules[$dayKey] as $schedule)
                                    <div class="room-slot">{{ $schedule['room']['name'] ?? 'N/A' }}</div>
                                @endforeach
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Legend -->
        <div class="legend">
            <div class="legend-title">Subject Legend:</div>
            @foreach ($schedules->pluck('class.subject_code')->unique() as $subjectCode)
                <div class="legend-item {{ $subjectColorMap[$subjectCode] ?? '' }}">
                    {{ $subjectCode }}
                </div>
            @endforeach
        </div>
    </div>
</body>
</html> 