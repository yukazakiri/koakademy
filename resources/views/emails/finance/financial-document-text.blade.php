{{ $financialDocument['institution']['name'] }}
{{ $documentType->label() }} {{ $financialDocument['document']['number'] }}

Hello {{ $documentType === App\Enums\FinancialDocumentType::Receipt ? $financialDocument['student_name'] : $financialDocument['student']['name'] }},

@if($documentType === App\Enums\FinancialDocumentType::Receipt)
We recorded your payment of {{ Illuminate\Support\Number::currency((float) $financialDocument['amount'], in: $financialDocument['currency']) }}.
Paper O.R. reference: {{ $financialDocument['document']['paper_reference'] ?: 'Not applicable' }}
@else
Your official eInvoice is attached.
Outstanding balance: {{ Illuminate\Support\Number::currency((float) $financialDocument['totals']['balance'], in: $financialDocument['currency']) }}
No due date is specified.
@endif

Verification: {{ $financialDocument['document']['verification_url'] }}
Please retain the attached PDF for your records.
