<x-mail::message>
<div style="text-align: center; padding: 20px 0; border-bottom: 2px solid {{ $siteSettings['themeColor'] ?? '#0f172a' }}; margin-bottom: 24px;">
    @if(!empty($logoUrl))
        <img src="{{ $logoUrl }}" alt="{{ $siteSettings['organizationName'] ?? config('app.name') }}" style="max-height: 60px; margin-bottom: 10px; display: inline-block;">
    @endif
    <h2 style="margin: 0; color: {{ $siteSettings['themeColor'] ?? '#0f172a' }}; font-size: 20px; font-family: system-ui, -apple-system, sans-serif;">
        {{ $siteSettings['organizationName'] ?? config('app.name') }}
    </h2>
    @if(!empty($siteSettings['tagline']))
        <p style="margin: 5px 0 0; color: #718096; font-size: 13px; font-family: system-ui, -apple-system, sans-serif;">
            {{ $siteSettings['tagline'] }}
        </p>
    @endif
</div>

# Enrollment Verified

Dear {{ $student_name }},

We are pleased to inform you that your subject enrollment has been **verified and approved** by the Department Head.

You are now one step away from being officially enrolled!

### Approved Subjects
<x-mail::table>
| Code | Title | Units |
| :--- | :--- | :--- |
@foreach($subjects as $subject)
| {{ $subject->subject->code }} | {{ $subject->subject->title }} | {{ $subject->subject->units }} |
@endforeach
</x-mail::table>

### Financial Summary
Please proceed to the Cashier's Office to settle your downpayment.

<x-mail::panel>
**Total Tuition:** ₱{{ number_format($tuition->overall_tuition, 2) }}
<br>
**Downpayment Due:** ₱{{ number_format($tuition->downpayment, 2) }}
</x-mail::panel>

Please visit the school within **3 to 5 days** to complete your payment.

<x-mail::button :url="config('app.url')">
Visit Student Portal
</x-mail::button>

Best regards,<br>
{{ $siteSettings['organizationName'] ?? config('app.name') }}

<x-mail::subcopy>
    @if(!empty($siteSettings['supportEmail']) || !empty($siteSettings['supportPhone']))
        Need help? Contact us:
        @if(!empty($siteSettings['supportEmail']))<br>Email: {{ $siteSettings['supportEmail'] }}@endif
        @if(!empty($siteSettings['supportPhone']))<br>Phone: {{ $siteSettings['supportPhone'] }}@endif
    @endif
</x-mail::subcopy>
</x-mail::message>
