<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Course Schedule - {{ $course->code }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 10px;
            padding: 0;
            color: #333;
            font-size: 10px;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }

        .header .course-info {
            font-size: 12px;
            font-weight: 600;
            color: #555;
            margin: 2px 0;
        }

        .header .meta-info {
            font-size: 9px;
            color: #666;
            margin: 1px 0;
        }

        .year-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .year-title {
            font-size: 14px;
            font-weight: 700;
            color: #000;
            margin: 0 0 8px 0;
            padding: 4px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9px;
        }

        .schedule-table th {
            background-color: #f8f9fa;
            border: 1px solid #333;
            padding: 4px 3px;
            text-align: left;
            font-weight: 700;
            color: #000;
            font-size: 10px;
        }

        .schedule-table td {
            border: 1px solid #666;
            padding: 3px 2px;
            vertical-align: top;
        }

        .schedule-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .code-cell {
            font-weight: 600;
            font-family: 'Courier New', monospace;
            color: #000;
            width: 18%;
        }

        .title-cell {
            font-weight: 500;
            color: #333;
            width: 42%;
        }

        .time-cell {
            font-weight: 500;
            color: #555;
            width: 25%;
            font-family: 'Courier New', monospace;
        }

        .room-cell {
            font-weight: 500;
            color: #555;
            width: 15%;
            text-align: center;
        }
        
        .no-schedules {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 10px;
        }

        .footer {
            margin-top: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #666;
            text-align: center;
        }

        /* Ensure content fits on one page */
        @page {
            size: A4;
            margin: 8mm;
        }

        @media print {
            body {
                margin: 0;
                font-size: 8px;
                transform: scale(0.85);
                transform-origin: top left;
            }

            .header {
                margin-bottom: 8px;
                padding-bottom: 5px;
            }

            .header h1 {
                font-size: 14px;
                margin-bottom: 3px;
            }

            .header .course-info {
                font-size: 10px;
            }

            .header .meta-info {
                font-size: 7px;
            }

            .year-section {
                page-break-inside: avoid;
                margin-bottom: 10px;
            }

            .year-title {
                font-size: 11px;
                margin-bottom: 5px;
                padding: 2px 0;
            }

            .schedule-table {
                font-size: 7px;
                margin-bottom: 8px;
            }

            .schedule-table th {
                font-size: 8px;
                padding: 2px 1px;
            }

            .schedule-table td {
                padding: 2px 1px;
            }

            .footer {
                margin-top: 8px;
                padding-top: 5px;
                font-size: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Course Schedule</h1>
        <div class="course-info">{{ $course->code }} - {{ $course->title }}</div>
        <div class="meta-info">Department: {{ $course->department }}</div>
        <div class="meta-info">School Year: {{ $currentSchoolYear }} - Semester: {{ $currentSemester }}</div>
        <div class="meta-info">Generated on: {{ now()->format('F d, Y H:i:s') }}</div>
    </div>

    @forelse($schedulesByYear as $year => $yearSchedules)
        <div class="year-section">
            <h2 class="year-title">
                @php
                    echo match($year) {
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                        default => "Year {$year}"
                    };
                @endphp
            </h2>
            
            @if(!empty($yearSchedules))
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Title</th>
                            <th>Time & Day</th>
                            <th>Room</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($yearSchedules as $group)
                            <tr>
                                <td class="code-cell">{{ $group['code_with_section'] }}</td>
                                <td class="title-cell">{{ $group['subject_title'] }}</td>
                                <td class="time-cell">{{ $group['time_and_day'] }}</td>
                                <td class="room-cell">{{ $group['room'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="no-schedules">No schedules found for this year level.</div>
            @endif
        </div>
    @empty
        <div class="no-schedules">No schedules found for this course.</div>
    @endforelse

    <div class="footer">
        <p>This document was generated automatically from the {{ app(\App\Settings\SiteSettings::class)->getAppName() }}.</p>
    </div>
</body>
</html>
