<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            font-size: 11px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .header p {
            margin: 4px 0;
            font-size: 12px;
            color: #666;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .info-item {
            text-align: center;
        }
        .info-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
        }
        .info-value {
            font-weight: bold;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 4px;
            text-align: center;
            font-size: 10px;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 9px;
        }
        .student-name {
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        .student-id {
            text-align: left;
            font-size: 9px;
            color: #666;
        }
        .status-P { background-color: rgba(34, 197, 94, 0.2); color: #16a34a; font-weight: bold; }
        .status-L { background-color: rgba(234, 179, 8, 0.2); color: #ca8a04; font-weight: bold; }
        .status-A { background-color: rgba(239, 68, 68, 0.2); color: #dc2626; font-weight: bold; }
        .status-E { background-color: rgba(99, 102, 241, 0.2); color: #4f46e5; font-weight: bold; }
        .summary-row {
            background-color: #fafafa;
        }
        .summary-row td {
            font-weight: bold;
        }
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 10px;
            color: #999;
        }
        .legend {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            display: flex;
            gap: 20px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
        }
        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 2px;
        }
        .legend-P { background-color: rgba(34, 197, 94, 0.3); }
        .legend-L { background-color: rgba(234, 179, 8, 0.3); }
        .legend-A { background-color: rgba(239, 68, 68, 0.3); }
        .legend-E { background-color: rgba(99, 102, 241, 0.3); }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Report</h1>
        <p>{{ $subject?->code ?? $class->subject_code ?? 'N/A' }} - {{ $subject?->title ?? 'N/A' }}</p>
        <p>Section: {{ $class->section ?? 'N/A' }} | {{ $class->school_year ?? 'N/A' }} - {{ $class->semester ?? 'N/A' }}</p>
    </div>

    <div class="info-row">
        <div class="info-item">
            <div class="info-label">Total Sessions</div>
            <div class="info-value">{{ $sessions->count() }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Total Students</div>
            <div class="info-value">{{ count($studentData) }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Generated</div>
            <div class="info-value">{{ $generatedAt }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 150px;">Student Name</th>
                <th style="width: 80px;">ID</th>
                @foreach ($sessions as $session)
                    <th style="width: 40px;">{{ $session->session_date?->format('m/d') ?? 'N/A' }}</th>
                @endforeach
                <th style="width: 30px;">P</th>
                <th style="width: 30px;">L</th>
                <th style="width: 30px;">A</th>
                <th style="width: 30px;">E</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($studentData as $data)
                <tr>
                    <td class="student-name">{{ $data['name'] }}</td>
                    <td class="student-id">{{ $data['student_id'] }}</td>
                    @foreach ($sessions as $session)
                        @php
                            $status = $data['attendance'][$session->id] ?? '-';
                            $statusChar = strtoupper(substr($status, 0, 1));
                            $statusClass = $statusChar !== '-' ? 'status-' . $statusChar : '';
                        @endphp
                        <td class="{{ $statusClass }}">{{ $statusChar }}</td>
                    @endforeach
                    <td class="status-P">{{ $data['summary']['present'] }}</td>
                    <td class="status-L">{{ $data['summary']['late'] }}</td>
                    <td class="status-A">{{ $data['summary']['absent'] }}</td>
                    <td class="status-E">{{ $data['summary']['excused'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="legend">
        <div class="legend-item">
            <div class="legend-dot legend-P"></div>
            <span>P = Present</span>
        </div>
        <div class="legend-item">
            <div class="legend-dot legend-L"></div>
            <span>L = Late</span>
        </div>
        <div class="legend-item">
            <div class="legend-dot legend-A"></div>
            <span>A = Absent</span>
        </div>
        <div class="legend-item">
            <div class="legend-dot legend-E"></div>
            <span>E = Excused</span>
        </div>
    </div>

    <div class="footer">
        Generated by {{ app(\App\Settings\SiteSettings::class)->getAppName() }} on {{ $generatedAt }}
    </div>
</body>
</html>
