<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Load Slip — {{ $loadChange->reference_number }}</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; color: #0f172a; margin: 0; padding: 32px; background: #f8fafc; }
        .sheet { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 32px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .org { font-size: 18px; font-weight: 700; margin: 0; }
        .doc-title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; margin: 4px 0 0; }
        .ref { text-align: right; font-family: ui-monospace, monospace; font-size: 13px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: #e2e8f0; color: #1e293b; }
        .badge.approved { background: #dcfce7; color: #166534; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 24px; font-size: 13px; margin-bottom: 20px; }
        .label { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { font-weight: 600; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        th, td { border: 1px solid #cbd5e1; padding: 9px 12px; text-align: left; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
        .totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin: 16px 0; font-size: 13px; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 42px; font-size: 12px; text-align: center; }
        .sig-line { border-top: 1px solid #475569; padding-top: 6px; margin-top: 32px; }
        .actions { max-width: 780px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 8px; }
        .btn { padding: 8px 14px; border-radius: 6px; border: 1px solid #cbd5e1; background: #0f172a; color: #fff; font-size: 13px; cursor: pointer; }
        @media print { body { background: #fff; padding: 0; } .sheet { border: none; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <button class="btn" onclick="window.print()">Print Official Slip</button>
    </div>

    <div class="sheet">
        <div class="header">
            <div>
                <p class="org">{{ $organizationName }}</p>
                <p class="doc-title">Official Change of Load (Add / Drop / Swap) Slip</p>
            </div>
            <div class="ref">
                <div><strong>{{ $loadChange->reference_number }}</strong></div>
                <div style="margin-top: 6px;">
                    <span class="badge {{ $loadChange->status }}">{{ strtoupper($loadChange->status) }}</span>
                </div>
            </div>
        </div>

        <div class="grid">
            <div>
                <div class="label">Student Name</div>
                <div class="value">{{ $enrollment->student?->full_name ?? 'Unknown Student' }}</div>
            </div>
            <div>
                <div class="label">Student ID</div>
                <div class="value">{{ $enrollment->student?->student_id ?? $enrollment->student_id }}</div>
            </div>
            <div>
                <div class="label">Program &amp; Year Level</div>
                <div class="value">{{ $enrollment->course?->code ?? '—' }} · Year {{ $enrollment->academic_year }}</div>
            </div>
            <div>
                <div class="label">Academic Term</div>
                <div class="value">{{ $enrollment->school_year }} · Semester {{ $enrollment->semester }}</div>
            </div>
            <div>
                <div class="label">Transaction Type</div>
                <div class="value">{{ strtoupper($loadChange->change_type) }} SUBJECT</div>
            </div>
            <div>
                <div class="label">Filing Date</div>
                <div class="value">{{ $loadChange->created_at?->format('M j, Y g:i A') }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Subject Code</th>
                    <th>Subject Title</th>
                    <th>Section</th>
                </tr>
            </thead>
            <tbody>
                @if($loadChange->dropped_subject_code)
                    <tr>
                        <td><strong>DROPPED</strong></td>
                        <td>{{ $loadChange->dropped_subject_code }}</td>
                        <td>{{ $loadChange->dropped_subject_title }}</td>
                        <td>—</td>
                    </tr>
                @endif
                @if($loadChange->added_subject_code)
                    <tr>
                        <td><strong>ADDED</strong></td>
                        <td>{{ $loadChange->added_subject_code }}</td>
                        <td>{{ $loadChange->added_subject_title }}</td>
                        <td>{{ $loadChange->addedClass?->section ?? 'To be assigned' }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="totals">
            <div>
                <div class="label">Units Delta</div>
                <div class="value">{{ $loadChange->units_delta >= 0 ? '+' : '' }}{{ $loadChange->units_delta }} unit(s)</div>
            </div>
            <div>
                <div class="label">Tuition Adjustment ({{ $currency }})</div>
                <div class="value">{{ (float) $loadChange->tuition_delta >= 0 ? '+' : '' }}{{ number_format((float) $loadChange->tuition_delta, 2) }}</div>
            </div>
            <div>
                <div class="label">Updated Assessment Total</div>
                <div class="value">{{ number_format((float) $loadChange->new_tuition_total, 2) }}</div>
            </div>
        </div>

        <div style="font-size: 13px; margin-top: 12px;">
            <div class="label">Justification / Remarks</div>
            <p style="margin: 4px 0 0;">{{ $loadChange->reason }}</p>
        </div>

        <div class="signatures">
            <div>
                <div class="sig-line">Student Signature</div>
            </div>
            <div>
                <div class="sig-line">{{ $loadChange->requestedBy?->name ?? 'Registrar Staff' }}<br><span style="color:#64748b">Prepared By</span></div>
            </div>
            <div>
                <div class="sig-line">{{ $loadChange->approvedBy?->name ?? 'Pending Approval' }}<br><span style="color:#64748b">Approved By</span></div>
            </div>
        </div>
    </div>
</body>
</html>
