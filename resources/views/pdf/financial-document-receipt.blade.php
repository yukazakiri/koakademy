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
        .total td { padding-top: 16px; border: 0; font-size: 15px; font-weight: 700; }
        .official-reference { padding: 12px; border: 1px solid #b9c1ca; background: #f5f7f9; }
        .verification td:first-child { width: 84px; }
        .qr { width: 72px; height: 72px; }
        .footer { color: #68717d; font-size: 9px; }
    </style>
</head>
<body>
@php
    $formatter = new NumberFormatter($financialDocument['currency'] === 'USD' ? 'en_US' : 'en_PH', NumberFormatter::CURRENCY);
    $currency = fn (float $amount): string => $formatter->formatCurrency($amount, $financialDocument['currency']);
    $label = fn (string $key): string => Illuminate\Support\Str::headline($key === 'tuition_fee' ? 'tuition fee payment' : $key);
@endphp
<main class="document">
    <header class="header">
        <table><tr>
            <td><div class="brand">{{ $financialDocument['institution']['name'] }}</div>@if($financialDocument['institution']['description'])<div class="muted">{{ $financialDocument['institution']['description'] }}</div>@endif</td>
            <td><div class="title">OFFICIAL eRECEIPT</div><div class="number">{{ $financialDocument['document']['number'] }}</div></td>
        </tr></table>
    </header>
    <section class="section">
        <table class="meta"><tr>
            <td><div class="label">Received from</div><div class="value">{{ $financialDocument['student_name'] }}</div><div class="muted">Student ID {{ $financialDocument['student_id'] }}</div></td>
            <td><div class="label">Payment date</div><div class="value">{{ $financialDocument['date'] }}</div><div class="muted">{{ $financialDocument['time'] }}</div></td>
            <td><div class="label">Transaction number</div><div class="value">{{ $financialDocument['transaction_number'] }}</div><div class="muted">{{ $financialDocument['method'] }}</div></td>
        </tr></table>
    </section>
    <section class="section">
        <div class="official-reference">
            <div class="label">Paper Official Receipt reference</div>
            <div class="value">{{ $financialDocument['document']['paper_reference'] ?: 'Not applicable' }}</div>
        </div>
    </section>
    <section class="section">
        <table class="items">
            <thead><tr><th>Payment description</th><th class="amount">Amount</th></tr></thead>
            <tbody>
            @foreach($financialDocument['items'] as $key => $amount)
                <tr><td>{{ $label($key) }}</td><td class="amount">{{ $currency((float) $amount) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total paid</td><td class="amount">{{ $currency((float) $financialDocument['amount']) }}</td></tr>
            </tbody>
        </table>
    </section>
    <section class="section">
        <table class="meta"><tr>
            <td><div class="label">Status</div><div class="value">{{ Illuminate\Support\Str::headline($financialDocument['status']) }}</div></td>
            <td><div class="label">Payment method</div><div class="value">{{ $financialDocument['method'] }}</div></td>
            <td><div class="label">Processed by</div><div class="value">{{ $financialDocument['cashier'] }}</div></td>
        </tr></table>
    </section>
    <section class="section">
        <table class="verification"><tr>
            <td><img class="qr" src="{{ $financialDocument['document']['qr_code'] }}" alt="Verification QR code"></td>
            <td><div class="label">Verify this document</div><div class="value">{{ $financialDocument['document']['verification_code'] }}</div><div class="muted">{{ $financialDocument['document']['verification_url'] }}</div></td>
        </tr></table>
    </section>
    <footer class="footer">Institution-issued electronic receipt. Verify the QR code before relying on a printed or forwarded copy.</footer>
</main>
</body>
</html>
