<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $financialDocument['document']['label'] }} {{ $financialDocument['document']['number'] }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; color: #17202a; font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.45; }
        .document { border: 1px solid #d9dee5; }
        .header, .section, .footer { padding: 20px 28px; }
        .header { border-bottom: 3px solid #17202a; }
        .section { border-bottom: 1px solid #e3e7ec; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .brand { font-size: 18px; font-weight: 700; }
        .muted { color: #68717d; }
        .title { text-align: right; font-size: 22px; font-weight: 700; letter-spacing: .06em; }
        .number { margin-top: 4px; text-align: right; font-family: monospace; }
        .label { margin-bottom: 4px; color: #68717d; font-size: 9px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .value { font-size: 12px; font-weight: 600; }
        .meta td { width: 33.333%; padding-right: 18px; }
        .items th { padding: 9px 0; border-bottom: 1px solid #cdd3da; color: #68717d; font-size: 9px; text-align: left; text-transform: uppercase; }
        .items td { padding: 10px 0; border-bottom: 1px solid #edf0f3; }
        .amount { text-align: right !important; }
        .summary { margin-left: auto; width: 48%; }
        .summary td { padding: 7px 0; }
        .summary tr:last-child td { padding-top: 12px; border-top: 2px solid #17202a; font-size: 15px; font-weight: 700; }
        .verification td:first-child { width: 84px; }
        .qr { width: 72px; height: 72px; }
        .footer { color: #68717d; font-size: 9px; }
    </style>
</head>
<body>
@php
    $formatter = new NumberFormatter($financialDocument['currency'] === 'USD' ? 'en_US' : 'en_PH', NumberFormatter::CURRENCY);
    $currency = fn (float $amount): string => $formatter->formatCurrency($amount, $financialDocument['currency']);
@endphp
<main class="document">
    <header class="header">
        <table><tr>
            <td><div class="brand">{{ $financialDocument['institution']['name'] }}</div>@if($financialDocument['institution']['description'])<div class="muted">{{ $financialDocument['institution']['description'] }}</div>@endif</td>
            <td><div class="title">OFFICIAL eINVOICE</div><div class="number">{{ $financialDocument['document']['number'] }}</div></td>
        </tr></table>
    </header>
    <section class="section">
        <table class="meta"><tr>
            <td><div class="label">Billed to</div><div class="value">{{ $financialDocument['student']['name'] }}</div><div class="muted">Student ID {{ $financialDocument['student']['student_id'] }}</div></td>
            <td><div class="label">Academic period</div><div class="value">Semester {{ $financialDocument['billing_period']['semester'] }}</div><div class="muted">A.Y. {{ $financialDocument['billing_period']['school_year'] }}</div></td>
            <td><div class="label">Issued</div><div class="value">{{ $financialDocument['document']['issued_at'] }}</div><div class="muted">No due date specified</div></td>
        </tr></table>
    </section>
    <section class="section">
        <table class="items">
            <thead><tr><th>Assessment item</th><th class="amount">Amount</th></tr></thead>
            <tbody>
            @foreach($financialDocument['charges'] as $charge)
                @if(abs((float) $charge['amount']) > 0.00001)
                    <tr><td>{{ $charge['label'] }}</td><td class="amount">{{ $currency((float) $charge['amount']) }}</td></tr>
                @endif
            @endforeach
            @if((int) $financialDocument['discount']['percentage'] > 0)
                <tr><td>Discount ({{ $financialDocument['discount']['name'] ?: $financialDocument['discount']['percentage'].'%' }})</td><td class="amount">Applied</td></tr>
            @endif
            </tbody>
        </table>
        <table class="summary">
            <tr><td>Assessed</td><td class="amount">{{ $currency((float) $financialDocument['totals']['assessed']) }}</td></tr>
            <tr><td>Payments received</td><td class="amount">− {{ $currency((float) $financialDocument['totals']['paid']) }}</td></tr>
            <tr><td>Outstanding balance</td><td class="amount">{{ $currency((float) $financialDocument['totals']['balance']) }}</td></tr>
        </table>
    </section>
    <section class="section">
        <table class="verification"><tr>
            <td><img class="qr" src="{{ $financialDocument['document']['qr_code'] }}" alt="Verification QR code"></td>
            <td><div class="label">Verify this document</div><div class="value">{{ $financialDocument['document']['verification_code'] }}</div><div class="muted">{{ $financialDocument['document']['verification_url'] }}</div></td>
        </tr></table>
    </section>
    <footer class="footer">Institution-issued electronic invoice for the stated enrollment balance. This is not a government-accredited tax invoice.</footer>
</main>
</body>
</html>
