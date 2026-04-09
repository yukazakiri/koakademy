<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List - {{ $class->subject_code ?? 'Unknown Subject' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 10px;
            color: #333;
            line-height: 1.2;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
        }

        .header h2 {
            margin: 3px 0;
            font-size: 14px;
            color: #374151;
        }
        
        .class-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            background-color: #f8fafc;
            padding: 10px;
            border-radius: 3px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        
        .class-info-left, .class-info-right {
            flex: 1;
        }
        
        .class-info-right {
            text-align: right;
        }
        
        .info-item {
            margin-bottom: 3px;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .student-table th {
            background-color: #2563eb;
            color: white;
            padding: 7px 5px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1d4ed8;
            font-size: 10px;
        }

        .student-table td {
            padding: 5px 4px;
            border: 1px solid #d1d5db;
            vertical-align: top;
            font-size: 9px;
        }
        
        .student-table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        .student-table tbody tr:hover {
            background-color: #f3f4f6;
        }
        
        .student-id {
            font-weight: bold;
            color: #1f2937;
        }
        
        .student-name {
            color: #374151;
        }
        
        .course-code {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .academic-year {
            color: #059669;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }
        
        .summary {
            background-color: #ecfdf5;
            border: 1px solid #10b981;
            border-radius: 3px;
            padding: 6px;
            margin-bottom: 12px;
            text-align: center;
        }

        .summary-text {
            color: #065f46;
            font-weight: bold;
            font-size: 11px;
        }
        
        /* Responsive scaling for different student counts */
        .compact-mode {
            font-size: 9px;
            line-height: 1.15;
        }

        .compact-mode .student-table th {
            padding: 4px 3px;
            font-size: 8px;
        }

        .compact-mode .student-table td {
            padding: 3px 2px;
            font-size: 8px;
        }

        .compact-mode .header {
            margin-bottom: 12px;
            padding-bottom: 6px;
        }

        .compact-mode .header h1 {
            font-size: 14px;
        }

        .compact-mode .header h2 {
            font-size: 12px;
        }

        .compact-mode .class-info {
            padding: 6px;
            margin-bottom: 10px;
            font-size: 8px;
        }

        .compact-mode .summary {
            padding: 5px;
            margin-bottom: 10px;
        }

        .compact-mode .summary-text {
            font-size: 9px;
        }

        @media print {
            body {
                margin: 6px;
                font-size: 10px;
            }

            .header {
                margin-bottom: 12px;
                padding-bottom: 6px;
            }

            .class-info {
                margin-bottom: 10px;
                padding: 7px;
            }

            .summary {
                margin-bottom: 12px;
                padding: 6px;
            }

            .student-table th {
                padding: 4px 3px;
                font-size: 9px;
            }

            .student-table td {
                padding: 3px 2px;
                font-size: 9px;
            }
        }
    </style>
</head>
<body class="{{ $total_students > 40 ? 'compact-mode' : '' }}">
    <div class="header">
        <h1>STUDENT LIST</h1>
        <h2>{{ $class->subject_code ?? 'Unknown Subject' }} - Section {{ $class->section ?? 'Unknown' }}</h2>
    </div>

    <div class="class-info">
        <div class="class-info-left">
            <div class="info-item">
                <span class="info-label">Subject:</span>
                <span class="info-value">{{ $class->subject_code ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Section:</span>
                <span class="info-value">{{ $class->section ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Faculty:</span>
                <span class="info-value">{{ $class->Faculty->full_name ?? 'Not Assigned' }}</span>
            </div>
            @if($class->classification === 'college')
            <div class="info-item">
                <span class="info-label">Year Level:</span>
                <span class="info-value">
                    @switch($class->academic_year)
                        @case('1') 1st Year @break
                        @case('2') 2nd Year @break
                        @case('3') 3rd Year @break
                        @case('4') 4th Year @break
                        @default {{ $class->academic_year ?? 'N/A' }}
                    @endswitch
                </span>
            </div>
            @else
            <div class="info-item">
                <span class="info-label">Grade Level:</span>
                <span class="info-value">{{ $class->grade_level ?? 'N/A' }}</span>
            </div>
            @endif
        </div>
        <div class="class-info-right">
            <div class="info-item">
                <span class="info-label">School Year:</span>
                <span class="info-value">{{ $class->school_year ?? 'N/A' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Semester:</span>
                <span class="info-value">
                    @switch($class->semester)
                        @case('1st') 1st Semester @break
                        @case('2nd') 2nd Semester @break
                        @case('summer') Summer @break
                        @default {{ $class->semester ?? 'N/A' }}
                    @endswitch
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Generated:</span>
                <span class="info-value">{{ $generated_at }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Type:</span>
                <span class="info-value">{{ $class->classification === 'shs' ? 'Senior High School' : 'College' }}</span>
            </div>
        </div>
    </div>

    <div class="summary">
        <div class="summary-text">
            Total Enrolled Students: {{ $total_students }}
        </div>
    </div>

    @if($students->count() > 0)
    <table class="student-table">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 15%;">Student ID</th>
                <th style="width: 40%;">Full Name</th>
                <th style="width: 20%;">Course Code</th>
                <th style="width: 17%;">Academic Year</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $enrollment)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td class="student-id">{{ $enrollment->student->student_id ?? 'N/A' }}</td>
                    <td class="student-name">{{ $enrollment->student->full_name ?? 'N/A' }}</td>
                    <td>
                        @if($enrollment->student->course)
                            <span class="course-code">{{ $enrollment->student->course->code }}</span>
                        @else
                            <span class="info-value">N/A</span>
                        @endif
                    </td>
                    <td class="academic-year">
                        @if($class->classification === 'college')
                            @switch($enrollment->student->academic_year)
                                @case(1) 1st Year @break
                                @case(2) 2nd Year @break
                                @case(3) 3rd Year @break
                                @case(4) 4th Year @break
                                @default {{ $enrollment->student->academic_year ?? 'N/A' }}
                            @endswitch
                        @else
                            {{ $class->grade_level ?? 'N/A' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div style="text-align: center; padding: 40px; color: #6b7280;">
        <p style="font-size: 16px; margin: 0;">No students enrolled in this class.</p>
    </div>
    @endif

    <div class="footer">
        <p>This document was generated automatically on {{ $generated_at }}.</p>
        <p>{{ app(\App\Settings\SiteSettings::class)->getAppName() }} - Student Management System</p>
    </div>
</body>
</html>
