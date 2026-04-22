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

# Welcome to {{ $siteSettings['organizationName'] ?? config('app.name') }}!

Dear {{ $student_name }},

**Congratulations!** Your enrollment has been successfully processed and verified. You are now officially enrolled for the upcoming academic term.

We are excited to welcome you to our academic community!

### Your Enrollment Details
<x-mail::panel>
**School Year:** {{ $school_year }}
<br>
**Semester:** {{ $semester }}
</x-mail::panel>

### Next Steps
@if(!empty($pdfAttached) && $pdfAttached)
1.  **Review your Class Schedule:** Please check the attached Assessment Form PDF for your detailed class schedule and room assignments.
2.  **Keep your Assessment Form:** The attached PDF serves as your official proof of enrollment and contains important payment details.
@else
1.  **Review your Class Schedule:** Your Assessment Form will be available in the Student Portal shortly. Please log in to download it.
2.  **Keep your Assessment Form:** This document serves as your official proof of enrollment and contains important payment details.
@endif

<x-mail::button :url="config('app.url')">
Access Student Portal
</x-mail::button>

If you have any questions regarding your schedule or account, please contact the Student Services office.

Best wishes for a successful semester!<br>
{{ $siteSettings['organizationName'] ?? config('app.name') }}

<x-mail::subcopy>
    @if(!empty($siteSettings['supportEmail']) || !empty($siteSettings['supportPhone']))
        Need help? Contact us:
        @if(!empty($siteSettings['supportEmail']))<br>Email: {{ $siteSettings['supportEmail'] }}@endif
        @if(!empty($siteSettings['supportPhone']))<br>Phone: {{ $siteSettings['supportPhone'] }}@endif
    @endif
</x-mail::subcopy>
</x-mail::message>
