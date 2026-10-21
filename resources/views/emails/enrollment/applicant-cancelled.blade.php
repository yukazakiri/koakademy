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

# Update on Your Application

Dear {{ $studentName }},

{{ $intro }}

@if(!empty($program))
<x-mail::panel>
**Program:** {{ $program }}
<br>
**Reason for closure:** {{ $reasonLabel }}
</x-mail::panel>
@endif

@if(!empty($note))
> {{ $note }}
@endif

<x-mail::button :url="config('app.url')">
Visit Our Website
</x-mail::button>

If you have questions about this decision, please contact the Registrar's Office using the contact details below and quote your reference number.

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
