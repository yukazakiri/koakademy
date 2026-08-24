<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $data['title'] ?? 'Registrar Document' }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 14mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202a; font-family: Arial, Helvetica, sans-serif; font-size: 10pt; line-height: 1.45; }
        .document { max-width: 182mm; margin: 0 auto; }
        .masthead { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #17202a; padding-bottom: 10px; }
        .logo { max-width: 54px; max-height: 54px; object-fit: contain; }
        .school-name { margin: 0; font-size: 15pt; letter-spacing: .08em; text-transform: uppercase; }
        .school-meta { margin-top: 2px; color: #5d6872; font-size: 8.5pt; }
        .document-heading { margin: 18px 0 4px; text-align: center; }
        .document-heading h1 { margin: 0; font-size: 16pt; letter-spacing: .08em; text-transform: uppercase; }
        .document-heading p { margin: 4px 0 0; color: #5d6872; font-size: 9pt; }
        .format-label { margin-top: 5px; color: #68737d; font-size: 7.5pt; letter-spacing: .08em; text-transform: uppercase; }
        .period { display: flex; justify-content: space-between; gap: 16px; border-top: 1px solid #b8c0c7; border-bottom: 1px solid #b8c0c7; margin: 14px 0; padding: 7px 0; font-size: 8.5pt; }
        .student-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 7px 18px; margin-bottom: 16px; }
        .field-label { display: block; color: #68737d; font-size: 7.5pt; letter-spacing: .08em; text-transform: uppercase; }
        .field-value { display: block; min-height: 17px; font-weight: 600; }
        .statement { margin: 18px 0; text-align: justify; }
        .verification-statement { border-top: 2px solid #17202a; border-bottom: 2px solid #17202a; margin: 24px 0; padding: 20px 12px; font-size: 11pt; text-align: justify; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; font-size: 8.5pt; }
        th { background: #edf0f2; border: 1px solid #aab3bb; padding: 6px; text-align: left; font-size: 7.5pt; letter-spacing: .05em; text-transform: uppercase; }
        td { border: 1px solid #c9d0d5; padding: 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #fafbfb; }
        .number { text-align: right; }
        .summary { display: flex; justify-content: flex-end; gap: 26px; border-top: 2px solid #17202a; padding-top: 7px; font-weight: 700; }
        .purpose { margin: 18px 0; padding: 9px 11px; border: 1px solid #b8c0c7; background: #f7f8f9; }
        .signature-row { display: flex; justify-content: space-between; gap: 34px; margin-top: 46px; }
        .signature { width: 42%; border-top: 1px solid #17202a; padding-top: 5px; text-align: center; }
        .signature small { display: block; color: #68737d; }
        .adviser-signatures { margin-top: 30px; }
        .adviser-signatures .signature { width: 30%; }
        .footer { display: flex; justify-content: space-between; gap: 16px; border-top: 1px solid #b8c0c7; margin-top: 24px; padding-top: 6px; color: #68737d; font-size: 7.5pt; }
        .empty { margin: 20px 0; border: 1px dashed #aab3bb; padding: 22px; color: #68737d; text-align: center; }
    </style>
</head>
<body>
@php
    $template = (string) ($data['template'] ?? '');
    $school = $data['school'] ?? [];
    $student = $data['student'] ?? [];
    $enrollment = $data['enrollment'] ?? [];
    $variant = (string) ($data['variant'] ?? 'full_certificate');
    $grades = data_get($data, 'grades.subjects', []);
    $subjects = is_array($enrollment['subjects'] ?? null) ? $enrollment['subjects'] : [];
    $formatTitles = [
        'full_certificate' => 'Full enrollment certificate',
        'verification_letter' => 'Enrollment verification letter',
        'units_certificate' => 'Enrollment and units certificate',
        'student_copy' => 'Student copy',
        'adviser_copy' => 'Adviser review copy',
        'receipt_copy' => 'Registration receipt copy',
        'official_record' => 'Official grade record',
        'transcript_style' => 'Transcript-style record',
        'grade_slip' => 'Student grade slip',
    ];
    $formatTitle = $formatTitles[$variant] ?? 'Official registrar document';
@endphp
<main class="document format-{{ $variant }}">
    <header class="masthead">
        @if (!empty($school['logo']))
            <img src="{{ $school['logo'] }}" alt="School logo" class="logo">
        @endif
        <div>
            <h2 class="school-name">{{ $school['name'] ?? 'School Registrar' }}</h2>
            <div class="school-meta">{{ $school['address'] ?? '' }}</div>
            <div class="school-meta">{{ $school['contact'] ?? '' }} @if (!empty($school['email'])) · {{ $school['email'] }} @endif</div>
        </div>
    </header>

    <section class="document-heading">
        <h1>{{ $data['title'] ?? 'Registrar Document' }}</h1>
        <p>{{ $data['subtitle'] ?? '' }}</p>
        <div class="format-label">{{ $formatTitle }}</div>
    </section>

    <div class="period">
        <span><strong>School Year:</strong> {{ $enrollment['school_year'] ?? '' }}</span>
        <span><strong>Academic Period:</strong> {{ $enrollment['semester_label'] ?? '' }}</span>
    </div>

    <section class="student-grid">
        <div><span class="field-label">Student name</span><span class="field-value">{{ $student['full_name'] ?? '—' }}</span></div>
        <div><span class="field-label">Student number</span><span class="field-value">{{ $student['student_number'] ?? '—' }}</span></div>
        <div><span class="field-label">Year level</span><span class="field-value">{{ !empty($student['year_level']) ? 'Year '.$student['year_level'] : '—' }}</span></div>
        <div><span class="field-label">Program</span><span class="field-value">{{ $student['course_code'] ?? '—' }}</span></div>
        <div><span class="field-label">Department</span><span class="field-value">{{ $student['department'] ?? '—' }}</span></div>
        <div><span class="field-label">Enrollment status</span><span class="field-value">{{ $enrollment['status'] ?? '—' }}</span></div>
    </section>

    @if ($template === 'certificate_of_enrollment')
        @if ($variant === 'verification_letter')
            <div class="verification-statement">
                This letter certifies that <strong>{{ $student['full_name'] ?? 'the student' }}</strong>, student number <strong>{{ $student['student_number'] ?? '—' }}</strong>,
                is officially enrolled in the <strong>{{ $student['course_code'] ?? '—' }}</strong> program for the {{ strtolower($enrollment['semester_label'] ?? 'current academic period') }} of
                School Year <strong>{{ $enrollment['school_year'] ?? '—' }}</strong>. The student's enrollment status is recorded as <strong>{{ $enrollment['status'] ?? '—' }}</strong>.
            </div>
        @else
            <p class="statement">This is to certify that <strong>{{ $student['full_name'] ?? 'the student' }}</strong>, student number <strong>{{ $student['student_number'] ?? '—' }}</strong>, is officially enrolled in the <strong>{{ $student['course_code'] ?? '—' }}</strong> program for the {{ strtolower($enrollment['semester_label'] ?? 'current academic period') }} of School Year <strong>{{ $enrollment['school_year'] ?? '—' }}</strong>.</p>
        @endif
        @if (!empty($data['purpose']))
            <div class="purpose"><strong>Issued for:</strong> {{ $data['purpose'] }}</div>
        @endif
        @if ($variant !== 'verification_letter')
            @if (count($subjects) > 0)
                <table>
                    <thead>
                    <tr>
                        <th>No.</th><th>Code</th><th>Descriptive title</th>
                        @if ($variant === 'full_certificate') <th>Section</th> @endif
                        <th class="number">Units</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($subjects as $index => $subject)
                        <tr>
                            <td>{{ $index + 1 }}</td><td>{{ $subject['code'] }}</td><td>{{ $subject['title'] }}</td>
                            @if ($variant === 'full_certificate') <td>{{ $subject['section'] }}</td> @endif
                            <td class="number">{{ $subject['units'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="summary"><span>Total units</span><span>{{ $enrollment['total_units'] ?? 0 }}</span></div>
            @else
                <div class="empty">No registered subjects were found for this academic period.</div>
            @endif
        @endif
        <p class="statement">Issued upon the request of the student for whatever lawful purpose this certification may serve.</p>
    @elseif ($template === 'registration_form')
        <p class="statement">The following subjects constitute the student's registration for the academic period shown above.</p>
        @if (count($subjects) > 0)
            <table>
                <thead>
                <tr>
                    <th>No.</th><th>Code</th><th>Descriptive title</th><th>Section</th>
                    @if ($variant !== 'receipt_copy') <th>Schedule</th> @endif
                    <th class="number">Units</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($subjects as $index => $subject)
                    <tr>
                        <td>{{ $index + 1 }}</td><td>{{ $subject['code'] }}</td><td>{{ $subject['title'] }}</td><td>{{ $subject['section'] }}</td>
                        @if ($variant !== 'receipt_copy') <td>{{ $subject['schedule'] }}</td> @endif
                        <td class="number">{{ $subject['units'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="summary"><span>Total registered units</span><span>{{ $enrollment['total_units'] ?? 0 }}</span></div>
        @else
            <div class="empty">No registered subjects were found for this academic period.</div>
        @endif
        @if ($variant === 'adviser_copy')
            <div class="signature-row adviser-signatures">
                <div class="signature">Adviser<small>Reviewed and approved</small></div>
                <div class="signature">Student<small>Confirmed registration</small></div>
                <div class="signature">Registrar<small>Registration validated</small></div>
            </div>
        @endif
    @elseif ($template === 'grade_report')
        <p class="statement">This report reflects the grades currently recorded for the student in the selected academic period.</p>
        @if (count($grades) > 0)
            <table>
                <thead>
                @if ($variant === 'transcript_style')
                    <tr><th>Code</th><th>Descriptive title</th><th class="number">Units</th><th class="number">Average</th><th>Status</th></tr>
                @elseif ($variant === 'grade_slip')
                    <tr><th>Code</th><th>Descriptive title</th><th class="number">Average</th></tr>
                @else
                    <tr><th>Code</th><th>Descriptive title</th><th class="number">Units</th><th class="number">Prelim</th><th class="number">Midterm</th><th class="number">Finals</th><th class="number">Average</th><th>Status</th></tr>
                @endif
                </thead>
                <tbody>
                @foreach ($grades as $grade)
                    @if ($variant === 'transcript_style')
                        <tr><td>{{ $grade['code'] }}</td><td>{{ $grade['title'] }}</td><td class="number">{{ $grade['units'] }}</td><td class="number">{{ $grade['average'] ?? '—' }}</td><td>{{ $grade['status'] }}</td></tr>
                    @elseif ($variant === 'grade_slip')
                        <tr><td>{{ $grade['code'] }}</td><td>{{ $grade['title'] }}</td><td class="number">{{ $grade['average'] ?? '—' }}</td></tr>
                    @else
                        <tr><td>{{ $grade['code'] }}</td><td>{{ $grade['title'] }}</td><td class="number">{{ $grade['units'] }}</td><td class="number">{{ $grade['prelim'] ?? '—' }}</td><td class="number">{{ $grade['midterm'] ?? '—' }}</td><td class="number">{{ $grade['finals'] ?? '—' }}</td><td class="number">{{ $grade['average'] ?? '—' }}</td><td>{{ $grade['status'] }}</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
            <div class="summary"><span>Term average</span><span>{{ $data['grades']['term_average'] ?? '—' }}</span></div>
        @else
            <div class="empty">No grade records were found for this academic period.</div>
        @endif
    @endif

    <div class="signature-row">
        <div class="signature">{{ $data['generated_by'] ?? 'Registrar' }}<small>Prepared by</small></div>
        <div class="signature">Registrar<small>Authorized signature</small></div>
    </div>
    <footer class="footer"><span>Generated {{ $data['generated_at'] ?? '' }}</span><span>{{ $formatTitle }}</span></footer>
</main>
</body>
</html>
