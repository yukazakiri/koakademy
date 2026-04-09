<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Student Timetable - {{ $student['full_name'] }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        /* Official Header */
        .official-header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 8px;
        }
        
        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 5px;
        }
        
        .school-logo {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
        }
        
        .school-info {
            text-align: left;
        }
        
        .school-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            line-height: 1.1;
        }
        
        .document-title {
            font-size: 20px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 5px 0 2px 0;
            letter-spacing: 1.5px;
        }
        
        .academic-period {
            font-size: 13px;
            font-weight: 600;
            margin: 2px 0;
            color: #1e40af;
        }
        
        .document-info {
            font-size: 10px;
            color: #666;
            margin: 2px 0;
        }
        
        /* Student Information Section */
        .student-section {
            background: #f8f9fa;
            border: 2px solid #1e40af;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 10px;
        }
        
        .student-header {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 6px 0;
            color: #1e40af;
            border-bottom: 1px solid #1e40af;
            padding-bottom: 2px;
        }
        
        .student-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .student-column {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .info-row {
            display: flex;
            font-size: 9px;
        }
        
        .info-label {
            font-weight: 600;
            width: 70px;
            flex-shrink: 0;
            color: #333;
        }
        
        .info-value {
            flex: 1;
            color: #000;
        }
        
        /* Class List Section */
        .class-section {
            margin-bottom: 10px;
        }
        
        .section-header {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 6px 0;
            color: #1e40af;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 2px;
        }
        
        .class-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            border: 2px solid #1e40af;
        }
        
        .class-table th {
            background: #1e40af;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 3px 2px;
            border: 1px solid #1e40af;
            text-transform: uppercase;
        }
        
        .class-table td {
            padding: 2px 2px;
            border: 1px solid #1e40af;
            text-align: left;
        }
        
        .class-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        /* Timetable Section */
        .timetable-section {
            margin-bottom: 10px;
        }
        
        .timetable-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7px;
            border: 2px solid #1e40af;
        }
        
        .timetable-table th {
            background: #1e40af;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 3px 1px;
            border: 1px solid #1e40af;
            text-transform: uppercase;
        }
        
        .timetable-table td {
            padding: 1px 1px;
            border: 1px solid #1e40af;
            text-align: center;
            vertical-align: top;
        }
        
        .time-cell {
            background: #e3f2fd;
            font-weight: bold;
            width: 45px;
            font-size: 6px;
        }
        
        .schedule-cell {
            padding: 1px !important;
            font-size: 6px;
            line-height: 1.1;
        }
        
        .schedule-block {
            padding: 1px;
            border-radius: 1px;
            color: white;
            height: 100%;
            min-height: 16px;
            font-weight: 600;
            box-shadow: 0 1px 1px rgba(0,0,0,0.2);
        }
        
        .subject-code {
            font-weight: bold;
            font-size: 6px;
            margin-bottom: 0px;
            text-transform: uppercase;
        }
        
        .subject-title {
            font-size: 5px;
            margin-bottom: 0px;
            font-weight: 500;
        }
        
        .time-info {
            font-size: 5px;
            margin-bottom: 0px;
            font-weight: 500;
        }
        
        .room-info {
            font-size: 5px;
            font-weight: 500;
        }
        
        /* Official Footer */
        .official-footer {
            margin-top: 8px;
            border-top: 2px solid #1e40af;
            padding-top: 6px;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 6px;
        }
        
        .signature-box {
            text-align: center;
            width: 45%;
        }
        
        .signature-line {
            border-bottom: 1px solid #000;
            margin: 15px 0 2px 0;
            height: 1px;
        }
        
        .signature-label {
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .document-footer {
            text-align: center;
            font-size: 7px;
            color: #666;
            margin-top: 6px;
            font-style: italic;
        }
        
        .page-break {
            page-break-inside: avoid;
        }
        
        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: #e3f2fd;
            font-weight: bold;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="watermark">OFFICIAL</div>
    
    <div class="page-break">
        <!-- Official Header -->
        <div class="official-header">
            <div class="header-content">
                <img src="{{ public_path('web-app-manifest-192x192.png') }}" alt="School Logo" class="school-logo">
                <div class="school-info">
                    <h1 class="school-name">{{ app(\App\Settings\SiteSettings::class)->getOrganizationName() }}</h1>
                    <div class="document-title">Student Timetable</div>
                </div>
            </div>
            <div class="academic-period">Academic Year: {{ $timetable['school_year'] }} - Semester {{ $timetable['semester'] }}</div>
            <div class="document-info">Document Generated: {{ date('F d, Y g:i A') }}</div>
        </div>

        <!-- Two Column Layout for Landscape -->
        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
            <!-- Student Information (Left Column) -->
            <div style="flex: 1;">
                <div class="student-section">
                    <h2 class="student-header">Student Information</h2>
                    <div class="student-grid">
                        <div class="student-column">
                            <div class="info-row">
                                <span class="info-label">ID:</span>
                                <span class="info-value">{{ $student['student_id'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span class="info-value">{{ $student['full_name'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <span class="info-value">{{ $student['email'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value">{{ $student['phone'] }}</span>
                            </div>
                        </div>
                        <div class="student-column">
                            <div class="info-row">
                                <span class="info-label">Course:</span>
                                <span class="info-value">{{ $student['course'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Birth Date:</span>
                                <span class="info-value">{{ $student['birth_date'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Gender:</span>
                                <span class="info-value">{{ $student['gender'] }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value">{{ Str::limit($student['address'], 30) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Class List (Right Column) -->
            <div style="flex: 1;">
                <div class="class-section">
                    <h2 class="section-header">Enrolled Classes ({{ $classes->count() }})</h2>
                    <table class="class-table">
                        <thead>
                            <tr>
                                <th width="18%">Code</th>
                                <th width="35%">Title</th>
                                <th width="12%">Section</th>
                                <th width="20%">Instructor</th>
                                <th width="7%">Units</th>
                                <th width="8%">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($classes as $index => $class)
                                <tr>
                                    <td>{{ $class['subject_code'] }}</td>
                                    <td>{{ $class['subject_title'] }}</td>
                                    <td>{{ $class['section'] }}</td>
                                    <td>{{ Str::limit($class['instructor'], 15) }}</td>
                                    <td align="center">{{ $class['units'] }}</td>
                                    <td align="center">{{ $class['classification'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Timetable -->
        <div class="timetable-section">
            <h2 class="section-header">Weekly Class Schedule</h2>
            <table class="timetable-table">
                <thead>
                    <tr>
                        <th width="10%">Time</th>
                        @foreach($timetable['days'] as $day)
                            <th width="15%">{{ substr($day, 0, 3) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        $processedCells = []; // Track which cells have been processed for spanning
                    @endphp
                    @foreach($timetable['grid'] as $rowIndex => $row)
                        <tr>
                            <td class="time-cell">
                                @php
                                    $timeParts = explode(':', $row['time']);
                                    $hour = (int)$timeParts[0];
                                    $minute = $timeParts[1] ?? '00';
                                    $period = $hour >= 12 ? 'PM' : 'AM';
                                    $displayHour = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                                    $formattedTime = "{$displayHour}:{$minute}";
                                @endphp
                                <div>{{ $formattedTime }}</div>
                                <div style="font-size: 5px; color: #666;">{{ $row['time'] }}</div>
                            </td>
                            @foreach($timetable['days'] as $day)
                                @php
                                    $cellKey = $rowIndex . '_' . $day;
                                    $shouldRender = true;
                                    
                                    // Check if this cell is part of a span from a previous row
                                    if (isset($processedCells[$cellKey])) {
                                        $shouldRender = false;
                                    }
                                    
                                    // If this cell has a schedule with span, mark future cells as processed
                                    if ($shouldRender && isset($row[$day]) && $row[$day] !== null) {
                                        $schedule = $row[$day];
                                        $span = $schedule['span'];
                                        
                                        // Mark the next (span-1) cells as processed
                                        for ($i = 1; $i < $span; $i++) {
                                            $futureCellKey = ($rowIndex + $i) . '_' . $day;
                                            $processedCells[$futureCellKey] = true;
                                        }
                                    }
                                @endphp
                                
                                @if($shouldRender)
                                    @if(isset($row[$day]) && $row[$day] !== null)
                                        @php
                                            $schedule = $row[$day];
                                            $span = $schedule['span'];
                                        @endphp
                                        <td class="schedule-cell" rowspan="{{ $span }}" style="vertical-align: top; padding: 1px;">
                                            <div class="schedule-block" style="background-color: {{ $schedule['color'] }}; height: {{ $span * 16 }}px; min-height: 16px;">
                                                <div class="subject-code">{{ $schedule['subject_code'] }}</div>
                                                <div class="subject-title">{{ $schedule['subject_title'] }}</div>
                                                <div class="time-info">{{ $schedule['start_time'] }}</div>
                                                <div class="room-info">{{ $schedule['room'] }}</div>
                                            </div>
                                        </td>
                                    @else
                                        <td class="schedule-cell">
                                            &mdash;
                                        </td>
                                    @endif
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Official Footer -->
        <div class="official-footer">
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Student Signature</div>
                    <div style="font-size: 9px; margin-top: 5px;">Over Printed Name</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Registrar / Authorized Signatory</div>
                    <div style="font-size: 9px; margin-top: 5px;">Over Printed Name</div>
                </div>
            </div>
            
            <div class="document-footer">
                This is an official document of {{ app(\App\Settings\SiteSettings::class)->getOrganizationName() }}.<br>
                Student ID: {{ $student['student_id'] }} | Generated: {{ date('F d, Y g:i A') }} | Page 1 of 1
            </div>
        </div>
    </div>
</body>
</html>