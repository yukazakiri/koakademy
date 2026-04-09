<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schedule List</title>
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
        
        /* Color classes */
        .bg-red { background-color: rgba(239, 68, 68, 0.3); border-left: 4px solid rgba(239, 68, 68, 0.8); }
        .bg-blue { background-color: rgba(59, 130, 246, 0.3); border-left: 4px solid rgba(59, 130, 246, 0.8); }
        .bg-green { background-color: rgba(34, 197, 94, 0.3); border-left: 4px solid rgba(34, 197, 94, 0.8); }
        .bg-yellow { background-color: rgba(234, 179, 8, 0.3); border-left: 4px solid rgba(234, 179, 8, 0.8); color: #543400;}
        .bg-purple { background-color: rgba(168, 85, 247, 0.3); border-left: 4px solid rgba(168, 85, 247, 0.8); }
        .bg-pink { background-color: rgba(236, 72, 153, 0.3); border-left: 4px solid rgba(236, 72, 153, 0.8); }
        .bg-indigo { background-color: rgba(99, 102, 241, 0.3); border-left: 4px solid rgba(99, 102, 241, 0.8); }
        .bg-orange { background-color: rgba(249, 115, 22, 0.3); border-left: 4px solid rgba(249, 115, 22, 0.8); }
        .bg-teal { background-color: rgba(20, 184, 166, 0.3); border-left: 4px solid rgba(20, 184, 166, 0.8); }
        .bg-cyan { background-color: rgba(6, 182, 212, 0.3); border-left: 4px solid rgba(6, 182, 212, 0.8); }
        .bg-lime { background-color: rgba(132, 204, 22, 0.3); border-left: 4px solid rgba(132, 204, 22, 0.8); color: #3a480b;}
        .bg-emerald { background-color: rgba(16, 185, 129, 0.3); border-left: 4px solid rgba(16, 185, 129, 0.8); }
        .bg-violet { background-color: rgba(139, 92, 246, 0.3); border-left: 4px solid rgba(139, 92, 246, 0.8); }
        .bg-fuchsia { background-color: rgba(217, 70, 239, 0.3); border-left: 4px solid rgba(217, 70, 239, 0.8); }
        .bg-rose { background-color: rgba(244, 63, 94, 0.3); border-left: 4px solid rgba(244, 63, 94, 0.8); }
        .bg-sky { background-color: rgba(14, 165, 233, 0.3); border-left: 4px solid rgba(14, 165, 233, 0.8); }
        .bg-amber { background-color: rgba(245, 158, 11, 0.3); border-left: 4px solid rgba(245, 158, 11, 0.8); color: #563a06;}

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }
        .schedule-table th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        .schedule-table td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
            vertical-align: top;
        }
        .schedule-row {
            transition: background-color 0.2s;
        }
        .schedule-row:nth-child(even) {
            background-color: #f8f9fa;
        }
        .subject-cell {
            font-weight: 600;
            padding: 8px 10px;
            border-radius: 4px;
            margin: 2px 0;
        }
        .time-cell {
            font-weight: 500;
            color: #495057;
        }
        .day-cell {
            font-weight: 500;
            text-transform: capitalize;
        }
        .section-cell {
            font-size: 11px;
            color: #6c757d;
        }
        .faculty-cell {
            font-size: 11px;
            color: #495057;
        }
        .room-cell {
            font-size: 11px;
            color: #495057;
        }
        .units-cell {
            text-align: center;
            font-weight: 500;
        }
        
        .legend {
            margin-top: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .legend h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #495057;
        }
        .legend-item {
            display: inline-block;
            margin: 3px 8px 3px 0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .summary-stats {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #6c757d;
        }
        .summary-stats div {
            text-align: center;
        }
        .summary-stats .stat-number {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            display: block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            {{ ucfirst($selectedView) }} Schedule List: <span class="entity-name">{{ $entityName }}</span>
        </h1>
        <p>School Year: {{ $currentSchoolYear }} - Semester: {{ $currentSemester }}</p>
        <p>Generated on: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    @php
        $groupedSchedules = collect($schedules)->groupBy('day_of_week')->sortKeys();
        $totalSchedules = count($schedules);
        $totalSubjects = collect($schedules)->pluck('class.subject.code')->unique()->count();
        $totalHours = collect($schedules)->sum('duration_minutes') / 60;
    @endphp

    <div class="summary-stats">
        <div>
            <span class="stat-number">{{ $totalSchedules }}</span>
            Total Classes
        </div>
        <div>
            <span class="stat-number">{{ $totalSubjects }}</span>
            Unique Subjects
        </div>
        <div>
            <span class="stat-number">{{ number_format($totalHours, 1) }}</span>
            Total Hours/Week
        </div>
    </div>

    <table class="schedule-table">
        <thead>
            <tr>
                <th style="width: 12%;">Day</th>
                <th style="width: 15%;">Time</th>
                <th style="width: 25%;">Subject</th>
                <th style="width: 8%;">Section</th>
                <th style="width: 6%;">Units</th>
                @if($selectedView !== 'faculty')
                    <th style="width: 15%;">Faculty</th>
                @endif
                @if($selectedView !== 'room')
                    <th style="width: 10%;">Room</th>
                @endif
                @if($selectedView === 'course')
                    <th style="width: 9%;">Year Level</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($groupedSchedules as $day => $daySchedules)
                @foreach($daySchedules->sortBy('start_time') as $schedule)
                    @php
                        $subjectCode = $schedule['class']['subject']['code'] ?? 'N/A';
                        $colorClass = $subjectColors[$subjectCode]['class'] ?? 'bg-gray';
                    @endphp
                    <tr class="schedule-row">
                        <td class="day-cell">{{ $day }}</td>
                        <td class="time-cell">
                            {{ $schedule['start_time_formatted'] }} - {{ $schedule['end_time_formatted'] }}
                            <br><small>({{ $schedule['duration_minutes'] }} min)</small>
                        </td>
                        <td class="subject-cell {{ $colorClass }}">
                            <strong>{{ $schedule['class']['subject']['title'] ?? 'N/A' }}</strong>
                            <br><small>{{ $subjectCode }}</small>
                        </td>
                        <td class="section-cell">{{ $schedule['class']['section'] ?? 'N/A' }}</td>
                        <td class="units-cell">{{ $schedule['class']['subject']['units'] ?? '-' }}</td>
                        @if($selectedView !== 'faculty')
                            <td class="faculty-cell">{{ $schedule['class']['faculty']['full_name'] ?? 'N/A' }}</td>
                        @endif
                        @if($selectedView !== 'room')
                            <td class="room-cell">{{ $schedule['room']['name'] ?? 'N/A' }}</td>
                        @endif
                        @if($selectedView === 'course')
                            <td class="section-cell">
                                @php
                                    $yearLevel = $schedule['class']['academic_year'];
                                    echo match($yearLevel) {
                                        '1' => '1st Year',
                                        '2' => '2nd Year', 
                                        '3' => '3rd Year',
                                        '4' => '4th Year',
                                        default => $yearLevel ?? 'N/A'
                                    };
                                @endphp
                            </td>
                        @endif
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    @if(!empty($subjectColors))
        <div class="legend">
            <h3>Subject Color Legend</h3>
            @foreach($subjectColors as $code => $colorData)
                <span class="legend-item {{ $colorData['class'] }}">
                    {{ $colorData['title'] }} ({{ $code }})
                </span>
            @endforeach
        </div>
    @endif
</body>
</html>
