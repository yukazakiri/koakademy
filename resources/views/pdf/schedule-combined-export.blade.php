<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Complete Schedule Report</title>
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
        
        .section-header {
            margin: 30px 0 15px 0;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
            font-size: 18px;
            font-weight: 600;
            color: #495057;
        }
        
        .page-break {
            page-break-before: always;
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

        /* Timetable styles */
        .timetable {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10px;
        }
        .timetable th, .timetable td {
            border: 1px solid #ccc;
            padding: 4px;
            text-align: center;
            vertical-align: top;
        }
        .timetable th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 11px;
        }
        .schedule-cell {
            padding: 3px;
            border-radius: 3px;
            font-weight: 500;
            font-size: 9px;
            line-height: 1.2;
            min-height: 25px;
            position: relative;
        }
        .schedule-cell div {
            margin-bottom: 1px;
        }
        .subject-title {
            font-weight: bold;
        }
        .empty-cell {
            background-color: #fdfdfd;
        }
        .time-header-cell {
            min-width: 70px;
            font-size: 10px;
        }

        /* List styles */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px;
        }
        .schedule-table th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
            font-weight: 600;
            color: #495057;
        }
        .schedule-table td {
            border: 1px solid #dee2e6;
            padding: 6px 8px;
            vertical-align: top;
        }
        .schedule-row:nth-child(even) {
            background-color: #f8f9fa;
        }
        .subject-cell {
            font-weight: 600;
            padding: 6px 8px;
            border-radius: 3px;
            margin: 1px 0;
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
            font-size: 10px;
            color: #6c757d;
        }
        .faculty-cell {
            font-size: 10px;
            color: #495057;
        }
        .room-cell {
            font-size: 10px;
            color: #495057;
        }
        .units-cell {
            text-align: center;
            font-weight: 500;
        }
        
        .legend {
            margin-top: 20px;
            padding: 12px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .legend h3 {
            margin: 0 0 8px 0;
            font-size: 13px;
            color: #495057;
        }
        .legend-item {
            display: inline-block;
            margin: 2px 6px 2px 0;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 500;
        }
        
        .summary-stats {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #6c757d;
        }
        .summary-stats div {
            text-align: center;
        }
        .summary-stats .stat-number {
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            display: block;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            Complete Schedule Report: <span class="entity-name">{{ $entityName }}</span>
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

    <!-- Timetable View -->
    <div class="section-header">📅 Weekly Timetable View</div>
    
    @include('pdf.partials.timetable-grid', [
        'schedules' => $schedules,
        'days' => $days,
        'timeSlots' => $timeSlots,
        'subjectColors' => $subjectColors,
        'selectedView' => $selectedView
    ])

    <!-- List View -->
    <div class="section-header page-break">📋 Detailed Schedule List</div>
    
    @include('pdf.partials.schedule-list-table', [
        'schedules' => $schedules,
        'groupedSchedules' => $groupedSchedules,
        'subjectColors' => $subjectColors,
        'selectedView' => $selectedView
    ])

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
