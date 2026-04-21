<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statement of Account</title>
    @if (! empty($school['favicon']))
        <link rel="icon" href="{{ $school['favicon'] }}" type="image/png">
    @endif
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #0f172a;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .document-shell {
            border: 1px solid #cbd5e1;
            padding: 10px;
        }

        .topbar {
            border-bottom: 3px solid #0f172a;
            padding-bottom: 8px;
        }

        .school-row {
            width: 100%;
        }

        .school-row td {
            vertical-align: middle;
        }

        .school-row .logo-wrap {
            width: 56px;
        }

        .school-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .school-meta {
            text-align: center;
        }

        .school-name {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .school-tagline,
        .school-address {
            margin: 2px 0 0;
            font-size: 8pt;
            color: #475569;
        }

        .favicon-wrap {
            width: 34px;
            text-align: right;
        }

        .school-favicon {
            width: 26px;
            height: 26px;
            object-fit: contain;
            border-radius: 4px;
        }

        .document-title {
            margin-top: 10px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: center;
        }

        .document-title h2 {
            margin: 0;
            font-size: 12pt;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .document-title p {
            margin: 2px 0 0;
            font-size: 8pt;
            color: #475569;
        }

        .meta {
            margin-top: 8px;
            font-size: 8.3pt;
        }

        .meta td {
            padding: 2px 0;
        }

        .meta .right {
            text-align: right;
        }

        .student-table {
            margin-top: 7px;
        }

        .student-table td {
            border: 1px solid #0f172a;
            padding: 5px;
        }

        .label {
            width: 110px;
            font-weight: bold;
            background: #e2e8f0;
        }

        .section {
            margin-top: 10px;
        }

        .section-title {
            margin: 0 0 4px;
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .box-table td,
        .box-table th {
            border: 1px solid #0f172a;
            padding: 5px;
        }

        .box-table th {
            background: #f1f5f9;
            font-size: 8pt;
            text-transform: uppercase;
            text-align: left;
        }

        .money {
            text-align: right;
            font-family: "Courier New", monospace;
            white-space: nowrap;
        }

        .emphasis {
            font-weight: bold;
            background: #e2e8f0;
        }

        .status-pill {
            margin-top: 5px;
            border: 1px solid #0f172a;
            text-align: center;
            padding: 5px;
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pill.settled {
            background: #dcfce7;
        }

        .status-pill.open {
            background: #fee2e2;
        }

        .empty-note {
            text-align: center;
            color: #64748b;
            font-style: italic;
        }

        .certification {
            margin-top: 10px;
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            font-size: 8.2pt;
        }

        .signatories {
            margin-top: 20px;
            width: 100%;
            table-layout: fixed;
        }

        .signatories td {
            text-align: center;
            font-size: 8.2pt;
            padding: 0 12px;
        }

        .line {
            border-bottom: 1px solid #0f172a;
            height: 24px;
            margin-bottom: 4px;
        }

        .footer {
            margin-top: 10px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 7.6pt;
            color: #64748b;
        }

        .footer td:last-child {
            text-align: right;
        }
    </style>
</head>
<body>
@php
    $currencySymbol = is_string($currency_symbol ?? null) && $currency_symbol !== '' ? $currency_symbol : '₱';

    $formatMoney = static function (int|float|string|null $value) use ($currencySymbol): string {
        return $currencySymbol.number_format((float) $value, 2);
    };

    $studentNo = (string) ($student['student_no'] ?? $student['id'] ?? 'N/A');
    $semester = (int) ($filters['semester'] ?? 1);
    $semesterLabel = $semester === 1 ? '1st Semester' : ($semester === 2 ? '2nd Semester' : 'Semester '.$semester);
    $schoolYear = (string) ($filters['school_year'] ?? '');
    $documentNo = $studentNo.'-'.preg_replace('/\s|-/u', '', $schoolYear).'-'.$semester;

    $assessmentTotal = (float) ($tuition?->overall_tuition ?? 0);
    $ledgerBalance = (float) ($tuition?->total_balance ?? 0);
    $paymentHistoryTotal = collect($transactions)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
    $ledgerPaid = $tuition ? max(0, $assessmentTotal - $ledgerBalance) : $paymentHistoryTotal;
    $totalPayments = $tuition ? $ledgerPaid : $paymentHistoryTotal;
    $balanceDue = $tuition ? $ledgerBalance : max(0, $assessmentTotal - $paymentHistoryTotal);

    $lectureFee = (float) ($tuition?->total_lectures ?? 0);
    $laboratoryFee = (float) ($tuition?->total_laboratory ?? 0);
    $tuitionSubtotal = (float) ($tuition?->total_tuition ?? 0);
    $miscellaneousFee = (float) ($tuition?->total_miscelaneous_fees ?? 0);
    $adjustmentAmount = $tuition ? $assessmentTotal - ($tuitionSubtotal + $miscellaneousFee) : 0;
    $transactionsForPrint = collect($transactions)->take(12)->all();
@endphp

<div class="document-shell">
    <header class="topbar">
        <table class="school-row">
            <tr>
                <td class="logo-wrap">
                    @if (! empty($school['logo']))
                        <img src="{{ $school['logo'] }}" alt="School logo" class="school-logo">
                    @endif
                </td>
                <td class="school-meta">
                    <h1 class="school-name">{{ $school['name'] ?? 'KoAkademy' }}</h1>
                    @if (! empty($school['tagline']))
                        <p class="school-tagline">{{ $school['tagline'] }}</p>
                    @endif
                    @if (! empty($school['address']))
                        <p class="school-address">{{ $school['address'] }}</p>
                    @endif
                </td>
                <td class="favicon-wrap">
                    @if (! empty($school['favicon']))
                        <img src="{{ $school['favicon'] }}" alt="Site icon" class="school-favicon">
                    @endif
                </td>
            </tr>
        </table>
    </header>

    <section class="document-title">
        <h2>Statement of Account</h2>
        <p>Official Student Financial Ledger</p>
    </section>

    <table class="meta">
        <tr>
            <td><strong>Control No.:</strong> SOA-{{ $documentNo }}</td>
            <td class="right"><strong>Date Issued:</strong> {{ $generated_at }}</td>
        </tr>
    </table>

    <table class="student-table">
        <tr>
            <td class="label">Student No.</td>
            <td>{{ $studentNo }}</td>
            <td class="label">Student Name</td>
            <td>{{ $student['name'] ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Course</td>
            <td>{{ $student['course'] ?? 'N/A' }}</td>
            <td class="label">Term</td>
            <td>{{ $semesterLabel }}, A.Y. {{ $schoolYear }}</td>
        </tr>
    </table>

    <section class="section">
        <table>
            <tr>
                <td style="width: 62%; padding-right: 6px; vertical-align: top;">
                    <p class="section-title">Assessment of Fees</p>
                    <table class="box-table">
                        <tr>
                            <td>Lecture Fee</td>
                            <td class="money">{{ $formatMoney($lectureFee) }}</td>
                        </tr>
                        <tr>
                            <td>Laboratory Fee</td>
                            <td class="money">{{ $formatMoney($laboratoryFee) }}</td>
                        </tr>
                        <tr>
                            <td>Tuition Subtotal</td>
                            <td class="money">{{ $formatMoney($tuitionSubtotal) }}</td>
                        </tr>
                        <tr>
                            <td>Miscellaneous Fee</td>
                            <td class="money">{{ $formatMoney($miscellaneousFee) }}</td>
                        </tr>
                        @if (abs($adjustmentAmount) >= 0.01)
                            <tr>
                                <td>Other Adjustments</td>
                                <td class="money">{{ $formatMoney($adjustmentAmount) }}</td>
                            </tr>
                        @endif
                        @if ((int) ($tuition->discount ?? 0) > 0)
                            <tr>
                                <td>Discount Applied</td>
                                <td class="money">{{ (int) $tuition->discount }}%</td>
                            </tr>
                        @endif
                        <tr class="emphasis">
                            <td>TOTAL ASSESSMENT</td>
                            <td class="money">{{ $formatMoney($assessmentTotal) }}</td>
                        </tr>
                    </table>
                </td>
                <td style="width: 38%; padding-left: 6px; vertical-align: top;">
                    <p class="section-title">Account Summary</p>
                    <table class="box-table">
                        <tr>
                            <td>Total Assessment</td>
                            <td class="money">{{ $formatMoney($assessmentTotal) }}</td>
                        </tr>
                        <tr>
                            <td>Total Payments</td>
                            <td class="money">{{ $formatMoney($totalPayments) }}</td>
                        </tr>
                        <tr class="emphasis">
                            <td>BALANCE DUE</td>
                            <td class="money">{{ $formatMoney($balanceDue) }}</td>
                        </tr>
                    </table>

                    <div class="status-pill {{ $balanceDue <= 0 ? 'settled' : 'open' }}">
                        {{ $balanceDue <= 0 ? 'Account Settled' : 'Account With Outstanding Balance' }}
                    </div>
                </td>
            </tr>
        </table>
    </section>

    <section class="section">
        <p class="section-title">Payment History</p>
        <table class="box-table">
            <thead>
            <tr>
                <th style="width: 22%;">Date</th>
                <th style="width: 18%;">OR No.</th>
                <th>Particulars</th>
                <th style="width: 24%; text-align: right;">Amount</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($transactionsForPrint as $transaction)
                <tr>
                    <td>{{ $transaction['date'] ?? '—' }}</td>
                    <td>{{ $transaction['invoice'] ?? '-' }}</td>
                    <td>{{ $transaction['description'] ?? 'Tuition Payment' }}</td>
                    <td class="money">{{ $formatMoney($transaction['amount'] ?? 0) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-note">No payment records found for the selected term.</td>
                </tr>
            @endforelse
            @if (count($transactions) > count($transactionsForPrint))
                <tr>
                    <td colspan="4" class="empty-note">... and {{ count($transactions) - count($transactionsForPrint) }} more transaction(s)</td>
                </tr>
            @endif
            </tbody>
            <tfoot>
            <tr class="emphasis">
                <td colspan="3">PAYMENT HISTORY TOTAL</td>
                <td class="money">{{ $formatMoney($paymentHistoryTotal) }}</td>
            </tr>
            </tfoot>
        </table>
    </section>

    <section class="certification">
        <strong>Certification:</strong>
        This document certifies that the foregoing figures reflect the recorded financial status of the student as of the date and time of issuance.
    </section>

    <table class="signatories">
        <tr>
            <td>
                <div class="line"></div>
                <strong>Prepared By</strong>
            </td>
            <td>
                <div class="line"></div>
                <strong>Verified By</strong>
            </td>
            <td>
                <div class="line"></div>
                <strong>Received By</strong>
            </td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td>Generated: {{ $generated_at }}</td>
            <td>This is a system-generated official document.</td>
        </tr>
    </table>
</div>
</body>
</html>
