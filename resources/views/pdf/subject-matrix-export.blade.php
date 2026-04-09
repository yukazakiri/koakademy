<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subject Matrix Schedule</title>
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
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
        }
        .subject-col {
            width: 20%;
            background-color: #f9f9f9;
        }
        .day-col {
            width: 13.33%; /* Remaining 80% divided by 6 days */
        }
        .subject-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 2px;
        }
        .subject-code {
            font-size: 10px;
            color: #666;
            display: inline-block;
            padding: 2px 4px;
            border: 1px solid #ddd;
            border-radius: 3px;
            margin-right: 5px;
        }
        .section-badge {
            font-size: 10px;
            color: #666;
        }
        .entry-card {
            margin-bottom: 5px;
            padding: 4px;
            border-radius: 3px;
            font-size: 10px;
            border: 1px solid transparent;
        }
        .entry-time {
            font-weight: bold;
        }
        .entry-room {
            color: #555;
        }
        
        /* Color classes */
        .bg-red { background-color: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.3); }
        .bg-blue { background-color: rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.3); }
        .bg-green { background-color: rgba(34, 197, 94, 0.2); border-color: rgba(34, 197, 94, 0.3); }
        .bg-yellow { background-color: rgba(234, 179, 8, 0.2); border-color: rgba(234, 179, 8, 0.3); }
        .bg-purple { background-color: rgba(168, 85, 247, 0.2); border-color: rgba(168, 85, 247, 0.3); }
        .bg-pink { background-color: rgba(236, 72, 153, 0.2); border-color: rgba(236, 72, 153, 0.3); }
        .bg-indigo { background-color: rgba(99, 102, 241, 0.2); border-color: rgba(99, 102, 241, 0.3); }
        .bg-orange { background-color: rgba(249, 115, 22, 0.2); border-color: rgba(249, 115, 22, 0.3); }
        .bg-teal { background-color: rgba(20, 184, 166, 0.2); border-color: rgba(20, 184, 166, 0.3); }
        .bg-cyan { background-color: rgba(6, 182, 212, 0.2); border-color: rgba(6, 182, 212, 0.3); }
        .bg-lime { background-color: rgba(132, 204, 22, 0.2); border-color: rgba(132, 204, 22, 0.3); }
        .bg-emerald { background-color: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.3); }
        .bg-violet { background-color: rgba(139, 92, 246, 0.2); border-color: rgba(139, 92, 246, 0.3); }
        .bg-fuchsia { background-color: rgba(217, 70, 239, 0.2); border-color: rgba(217, 70, 239, 0.3); }
        .bg-rose { background-color: rgba(244, 63, 94, 0.2); border-color: rgba(244, 63, 94, 0.3); }
        .bg-sky { background-color: rgba(14, 165, 233, 0.2); border-color: rgba(14, 165, 233, 0.3); }
        .bg-amber { background-color: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.3); }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            Subject Matrix: <span class="entity-name">{{ $entityName }}</span>
        </h1>
        <p>School Year: {{ $currentSchoolYear }} - Semester: {{ $currentSemester }}</p>
        <p>Generated on: {{ now()->format('F d, Y H:i:s') }}</p>
    </div>

    @php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        // Group schedules by subject and section
        $groupedRows = [];
        foreach ($schedules as $schedule) {
            // Skip schedules without valid class or subject data
            if (!isset($schedule['class']) || !isset($schedule['class']['subject'])) {
                continue;
            }
            
            $subjectCode = $schedule['class']['subject']['code'] ?? 'N/A';
            $subjectTitle = $schedule['class']['subject']['title'] ?? 'Unknown Subject';
            $section = $schedule['class']['section'] ?? 'N/A';
            
            $key = $subjectCode . '-' . $section;
            if (!isset($groupedRows[$key])) {
                $groupedRows[$key] = [
                    'subject_code' => $subjectCode,
                    'subject_title' => $subjectTitle,
                    'section' => $section,
                    'entries' => []
                ];
            }
            
            $day = ucfirst(strtolower($schedule['day_of_week'] ?? ''));
            if (!isset($groupedRows[$key]['entries'][$day])) {
                $groupedRows[$key]['entries'][$day] = [];
            }
            $groupedRows[$key]['entries'][$day][] = $schedule;
        }
        
        // Sort rows by subject code
        usort($groupedRows, function($a, $b) {
            return strcmp($a['subject_code'], $b['subject_code']);
        });

        // Color mapping logic (simplified from timetable)
        $colorClasses = [
            'bg-red', 'bg-blue', 'bg-green', 'bg-yellow', 'bg-purple', 'bg-pink',
            'bg-indigo', 'bg-orange', 'bg-teal', 'bg-cyan', 'bg-lime', 'bg-emerald',
            'bg-violet', 'bg-fuchsia', 'bg-rose', 'bg-sky', 'bg-amber',
        ];
        $subjectColorMap = [];
        $colorIndex = 0;
    @endphp

    <table>
        <thead>
            <tr>
                <th class="subject-col">Subject</th>
                @foreach ($days as $day)
                    <th class="day-col">{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($groupedRows as $row)
                @php
                    $colorKey = $row['subject_code'] . '-' . $row['section'];
                    if (!isset($subjectColorMap[$colorKey])) {
                        $subjectColorMap[$colorKey] = $colorClasses[$colorIndex % count($colorClasses)];
                        $colorIndex++;
                    }
                    $rowColor = $subjectColorMap[$colorKey];
                @endphp
                <tr>
                    <td class="subject-col">
                        <div class="subject-title">{{ $row['subject_title'] }}</div>
                        <div>
                            <span class="subject-code">{{ $row['subject_code'] }}</span>
                            <span class="section-badge">Sec: {{ $row['section'] }}</span>
                        </div>
                    </td>
                    @foreach ($days as $day)
                        <td>
                            @if (isset($row['entries'][$day]))
                                @foreach ($row['entries'][$day] as $entry)
                                    <div class="entry-card {{ $rowColor }}">
                                        <div class="entry-time">
                                            {{ $entry['start_time_formatted'] }} - {{ $entry['end_time_formatted'] }}
                                        </div>
                                        <div class="entry-room">
                                            {{ $entry['room']['name'] ?? 'TBA' }}
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            
            @if (empty($groupedRows))
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px; color: #777;">
                        No schedule data available.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
