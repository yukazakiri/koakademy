<x-mail::message>
<div style="text-align: center; padding: 22px 0; border-bottom: 2px solid {{ $siteSettings['themeColor'] ?? '#0f172a' }}; margin-bottom: 24px;">
    @if(!empty($logoUrl))
        <img src="{{ $logoUrl }}" alt="{{ $siteSettings['organizationName'] ?? config('app.name') }}" style="max-height: 64px; margin-bottom: 10px; display: inline-block;">
    @endif
    <h2 style="margin: 0; color: {{ $siteSettings['themeColor'] ?? '#0f172a' }}; font-size: 21px; font-family: system-ui, -apple-system, sans-serif;">
        {{ $siteSettings['organizationName'] ?? config('app.name') }}
    </h2>
    @if(!empty($siteSettings['tagline']))
        <p style="margin: 6px 0 0; color: #718096; font-size: 13px; font-family: system-ui, -apple-system, sans-serif;">
            {{ $siteSettings['tagline'] }}
        </p>
    @endif
</div>

# Your Online Application Has Been Approved

Dear {{ $studentName }},

{{ $intro ?? 'Good news — your application has been reviewed and approved for the next step of the admission process.' }}

<x-mail::panel>
@if(!empty($program))
**Program:** {{ $program }}
<br>
@endif
@if(!empty($department))
**Department:** {{ $department }}
<br>
@endif
**Next Step:** Personal visit to the Registrar's Office
</x-mail::panel>

Please come personally to the school and proceed to the **Registrar's Office** to submit your enrollment requirements and complete the verification of your records.

### Please prepare your requirements

Kindly bring the original documents and photocopies required for your application, such as academic records, identification documents, certificates, and any additional requirements requested by the Registrar.

Our Registrar staff will review your submitted documents, verify your information, and guide you through the remaining enrollment steps.

<x-mail::button :url="config('app.url')">
Visit Our Website
</x-mail::button>

If you have questions before visiting, please contact the Registrar's Office using the contact details below.

We look forward to welcoming you personally to our campus.

Sincerely,<br>
{{ $senderName }}<br>
{{ $senderRole }}<br>
{{ $siteSettings['organizationName'] ?? config('app.name') }}

<x-mail::subcopy>
    @if(!empty($siteSettings['supportEmail']) || !empty($siteSettings['supportPhone']))
        Need help? Contact us:
        @if(!empty($siteSettings['supportEmail']))<br>Email: {{ $siteSettings['supportEmail'] }}@endif
        @if(!empty($siteSettings['supportPhone']))<br>Phone: {{ $siteSettings['supportPhone'] }}@endif
    @endif
</x-mail::subcopy>
</x-mail::message>
