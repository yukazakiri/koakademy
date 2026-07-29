<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $documentType->label() }}</title></head>
<body style="margin:0;background:#f4f6f8;color:#17202a;font-family:Arial,sans-serif;line-height:1.5">
<div style="display:none;max-height:0;overflow:hidden">
    {{ $documentType === App\Enums\FinancialDocumentType::Receipt ? 'Your payment has been recorded.' : 'Your current enrollment balance is attached.' }}
</div>
<div style="max-width:620px;margin:0 auto;padding:32px 16px">
    <div style="overflow:hidden;border:1px solid #dfe4ea;border-radius:12px;background:#fff">
        <div style="padding:26px 30px;border-bottom:3px solid #17202a">
            <div style="font-size:13px;color:#68717d">{{ $financialDocument['institution']['name'] }}</div>
            <h1 style="margin:6px 0 0;font-size:24px">{{ $documentType->label() }}</h1>
            <div style="margin-top:4px;color:#68717d;font-family:monospace">{{ $financialDocument['document']['number'] }}</div>
        </div>
        <div style="padding:26px 30px">
            <p>Hello {{ $documentType === App\Enums\FinancialDocumentType::Receipt ? $financialDocument['student_name'] : $financialDocument['student']['name'] }},</p>
            @if($documentType === App\Enums\FinancialDocumentType::Receipt)
                <p>We recorded your payment of <strong>{{ Illuminate\Support\Number::currency((float) $financialDocument['amount'], in: $financialDocument['currency']) }}</strong>. Your official eReceipt is attached.</p>
                <p style="padding:12px;border:1px solid #dfe4ea;background:#f7f8fa"><strong>Paper O.R. reference:</strong> {{ $financialDocument['document']['paper_reference'] ?: 'Not applicable' }}</p>
            @else
                <p>Your official eInvoice for the selected enrollment is attached. The current outstanding balance is <strong>{{ Illuminate\Support\Number::currency((float) $financialDocument['totals']['balance'], in: $financialDocument['currency']) }}</strong>.</p>
                <p>This invoice does not specify a due date. Please contact the institution if you need payment guidance.</p>
            @endif
            <p style="margin-top:24px;color:#68717d;font-size:13px">Verify the QR code in the PDF before relying on a printed or forwarded copy.</p>
        </div>
    </div>
    <p style="text-align:center;color:#7c8591;font-size:12px">This is a system-generated message from {{ $financialDocument['institution']['name'] }}.</p>
</div>
</body>
</html>
