<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Report</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 9pt;
            line-height: 1.3;
            color: #111827;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .header-logo {
            max-height: 44px;
            margin-bottom: 4px;
        }

        .header-name {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-meta {
            margin-top: 3px;
            font-size: 8pt;
            color: #374151;
        }

        .report-title {
            margin: 8px 0 2px;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .report-subtitle {
            margin: 0 0 8px;
            text-align: center;
            color: #4b5563;
            font-size: 9pt;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #d1d5db;
            border-bottom: 1px solid #d1d5db;
            padding: 4px 0;
            margin-bottom: 8px;
            font-size: 8pt;
            color: #374151;
        }

        .filters {
            margin-bottom: 10px;
            font-size: 8pt;
            color: #374151;
        }

        .filters strong {
            margin-right: 4px;
        }

        .filter-chip {
            display: inline-block;
            margin-right: 6px;
            margin-bottom: 4px;
            border: 1px solid #d1d5db;
            background: #f9fafb;
            border-radius: 3px;
            padding: 2px 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }

        th {
            background: #f3f4f6;
            color: #111827;
            text-transform: uppercase;
            font-size: 7.5pt;
            text-align: left;
            border: 1px solid #d1d5db;
            padding: 4px 5px;
        }

        td {
            border: 1px solid #e5e7eb;
            padding: 4px 5px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background: #fafafa;
        }

        .section-title {
            margin: 10px 0 5px;
            padding-bottom: 2px;
            border-bottom: 1px solid #d1d5db;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .subject-group {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .subject-heading {
            margin: 0;
            border: 1px solid #d1d5db;
            border-bottom: 0;
            background: #f9fafb;
            padding: 5px 7px;
            font-size: 8.5pt;
            font-weight: bold;
        }

        .muted {
            color: #6b7280;
        }

        .footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #d1d5db;
            font-size: 8pt;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>
@php
    /** @var array<string, mixed> $data */
    $report = $data['report'] ?? [];
    $school = $data['school'] ?? [];
    $reportType = (string) ($report['type'] ?? '');
    $filtersApplied = $report['filters_applied'] ?? [];
@endphp

<header class="header">
    @if (! empty($school['logo']))
        <img src="{{ $school['logo'] }}" alt="School Logo" class="header-logo">
    @endif
    <h1 class="header-name">{{ $school['name'] ?? 'KoAkademy' }}</h1>
    <div class="header-meta">
        {{ $school['address'] ?? '' }}
    </div>
    <div class="header-meta">
        Tel: {{ $school['contact'] ?? '' }} @if(! empty($school['email'])) | Email: {{ $school['email'] }} @endif
    </div>
</header>

<div class="report-title">{{ $report['title'] ?? 'Enrollment Report' }}</div>
<p class="report-subtitle">{{ $report['subtitle'] ?? '' }}</p>

<div class="meta-row">
    <div>
        <strong>School Year:</strong> {{ $data['school_year'] ?? '' }}
        &nbsp;|&nbsp;
        <strong>Semester:</strong> {{ $data['semester'] ?? '' }}
    </div>
    <div>
        <strong>Generated:</strong> {{ $data['generated_at'] ?? '' }}
        &nbsp;|&nbsp;
        <strong>By:</strong> {{ $data['generated_by'] ?? '' }}
    </div>
</div>

@if (is_array($filtersApplied) && count($filtersApplied) > 0)
    <div class="filters">
        <strong>Filters:</strong>
        @foreach ($filtersApplied as $label => $value)
            @if (! empty($value))
                <span class="filter-chip">{{ $label }}: {{ $value }}</span>
            @endif
        @endforeach
    </div>
@endif

@if ($reportType === 'enrolled_by_course')
    @php
        $students = is_array($report['students'] ?? null) ? $report['students'] : [];
    @endphp
    <p><strong>Total Students:</strong> {{ $report['total_count'] ?? 0 }}</p>
    <table>
        <thead>
        <tr>
            <th>No.</th>
            <th>Student ID</th>
            <th>Full Name</th>
            <th>Course</th>
            <th>Department</th>
            <th>Year Level</th>
            <th>Subjects</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($students as $student)
            <tr>
                <td>{{ $student['no'] ?? '—' }}</td>
                <td>{{ $student['student_id'] ?? '—' }}</td>
                <td>{{ $student['full_name'] ?? '—' }}</td>
                <td>{{ $student['course'] ?? '—' }}</td>
                <td>{{ $student['department'] ?? '—' }}</td>
                <td>{{ ! empty($student['year_level']) ? 'Year '.$student['year_level'] : '—' }}</td>
                <td>{{ $student['subjects_count'] ?? '—' }}</td>
                <td>{{ $student['status'] ?? '—' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="muted">No students found for the selected filters.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
@endif

@if ($reportType === 'enrolled_by_subject')
    @php
        $subjectGroups = is_array($report['subject_groups'] ?? null) ? $report['subject_groups'] : [];
    @endphp
    <p><strong>Total Enrollments:</strong> {{ $report['total_count'] ?? 0 }}</p>
    @forelse ($subjectGroups as $group)
        @php
            $students = is_array($group['students'] ?? null) ? $group['students'] : [];
        @endphp
        <section class="subject-group">
            <p class="subject-heading">
                {{ $group['subject_code'] ?? '—' }} - {{ $group['subject_title'] ?? 'Unknown Subject' }}
                <span class="muted">({{ $group['total_enrolled'] ?? 0 }} students | {{ $group['subject_units'] ?? 0 }} units)</span>
            </p>
            <table>
                <thead>
                <tr>
                    <th>No.</th>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Course</th>
                    <th>Year Level</th>
                    <th>Section</th>
                    <th>Schedule</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td>{{ $student['no'] ?? '—' }}</td>
                        <td>{{ $student['student_id'] ?? '—' }}</td>
                        <td>{{ $student['full_name'] ?? '—' }}</td>
                        <td>{{ $student['course'] ?? '—' }}</td>
                        <td>{{ ! empty($student['year_level']) ? 'Year '.$student['year_level'] : '—' }}</td>
                        <td>{{ $student['section'] ?? '—' }}</td>
                        <td>{{ $student['class_schedule'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No students found in this subject group.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @empty
        <p class="muted">No subject enrollments found for the selected filters.</p>
    @endforelse
@endif

@if ($reportType === 'enrollment_summary')
    @php
        $total = (int) ($report['total_enrolled'] ?? 0);
        $byDepartment = is_array($report['by_department'] ?? null) ? $report['by_department'] : [];
        $byCourse = is_array($report['by_course'] ?? null) ? $report['by_course'] : [];
        $byYearLevel = is_array($report['by_year_level'] ?? null) ? $report['by_year_level'] : [];
        $byStatus = is_array($report['by_status'] ?? null) ? $report['by_status'] : [];
    @endphp

    <p><strong>Total Enrolled:</strong> {{ $total }}</p>

    <div class="section-title">By Department</div>
    <table>
        <thead>
        <tr>
            <th>Department</th>
            <th>Count</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($byDepartment as $item)
            <tr>
                <td>{{ $item['department'] ?? 'Unknown' }}</td>
                <td>{{ $item['count'] ?? 0 }}</td>
                <td>{{ $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="section-title">By Course</div>
    <table>
        <thead>
        <tr>
            <th>Course</th>
            <th>Title</th>
            <th>Department</th>
            <th>Count</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($byCourse as $item)
            <tr>
                <td>{{ $item['course_code'] ?? '—' }}</td>
                <td>{{ $item['course_title'] ?? '—' }}</td>
                <td>{{ $item['department'] ?? '—' }}</td>
                <td>{{ $item['count'] ?? 0 }}</td>
                <td>{{ $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="section-title">By Year Level</div>
    <table>
        <thead>
        <tr>
            <th>Year Level</th>
            <th>Count</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($byYearLevel as $item)
            <tr>
                <td>Year {{ $item['year_level'] ?? '—' }}</td>
                <td>{{ $item['count'] ?? 0 }}</td>
                <td>{{ $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="section-title">By Status</div>
    <table>
        <thead>
        <tr>
            <th>Status</th>
            <th>Count</th>
            <th>Percentage</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($byStatus as $item)
            <tr>
                <td>{{ $item['status'] ?? '—' }}</td>
                <td>{{ $item['count'] ?? 0 }}</td>
                <td>{{ $total > 0 ? round((($item['count'] ?? 0) / $total) * 100, 1) : 0 }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<footer class="footer">
    <div>This is a system-generated report.</div>
    <div>Page 1 of 1</div>
</footer>
</body>
</html>
