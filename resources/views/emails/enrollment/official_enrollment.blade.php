{{--
Expected data: student_name, school_year, semester, siteSettings, logoUrl, has_portal_account,
student_id_display, student_email, signup_url, and pdfAttached. This shared template is used by
enrollment confirmation emails and assessment resend notifications.
--}}
@php
    $themeColor = $siteSettings['themeColor'] ?? '#0f172a';
    $organizationName = $siteSettings['organizationName'] ?? config('app.name');
    $surfaceColor = '#ffffff';
    $softSurfaceColor = '#f8fafc';
    $borderColor = '#e2e8f0';
    $mutedColor = '#64748b';
    $textColor = '#0f172a';
    $successColor = '#166534';
    $successSurfaceColor = '#f0fdf4';
    $spaceXs = '4px';
    $spaceSm = '8px';
    $spaceMd = '12px';
    $spaceLg = '16px';
    $spaceXl = '24px';
    $radiusSm = '8px';
    $radiusMd = '12px';
    $radiusLg = '18px';
@endphp
@component('mail::message')
<div style="text-align: center; padding: {{ $spaceXl }} {{ $spaceLg }}; border-bottom: 2px solid {{ $themeColor }}; margin-bottom: {{ $spaceXl }}; background: linear-gradient(135deg, {{ $softSurfaceColor }} 0%, {{ $surfaceColor }} 100%); border-radius: {{ $radiusLg }} {{ $radiusLg }} {{ $radiusSm }} {{ $radiusSm }};">
@if(!empty($logoUrl))
<img src="{{ $logoUrl }}" alt="{{ $organizationName }}" style="max-height: 64px; margin-bottom: {{ $spaceMd }}; display: inline-block;">
@endif
<h2 style="margin: 0; color: {{ $themeColor }}; font-size: 21px; line-height: 1.25; font-family: system-ui, -apple-system, sans-serif;">
{{ $organizationName }}
</h2>
@if(!empty($siteSettings['tagline']))
<p style="margin: {{ $spaceSm }} 0 0; color: {{ $mutedColor }}; font-size: 13px; line-height: 1.5; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
{{ $siteSettings['tagline'] }}
</p>
@endif
</div>

<div style="border-left: 4px solid {{ $themeColor }}; padding-left: {{ $spaceLg }}; margin-bottom: {{ $spaceXl }};">
<p style="margin: 0 0 {{ $spaceSm }}; color: {{ $mutedColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif;">
Enrollment Confirmation
</p>
<h1 style="margin: 0; color: {{ $textColor }}; font-size: 24px; line-height: 1.25; font-family: system-ui, -apple-system, sans-serif;">
Welcome to {{ $organizationName }}!
</h1>
</div>

Dear {{ $student_name }},

<p>{!! nl2br(e($intro)) !!}</p>

### Your Enrollment Details

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: {{ $spaceLg }} 0 {{ $spaceXl }}; border: 1px solid {{ $borderColor }}; border-radius: {{ $radiusMd }}; overflow: hidden; background: {{ $surfaceColor }};">
<tr>
<td width="50%" style="padding: {{ $spaceLg }}; border-right: 1px solid {{ $borderColor }}; background: {{ $softSurfaceColor }}; vertical-align: top;">
<p style="margin: 0 0 {{ $spaceXs }}; color: {{ $mutedColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif;">School Year</p>
<p style="margin: 0; color: {{ $textColor }}; font-size: 16px; font-weight: 700; font-family: system-ui, -apple-system, sans-serif;">{{ $school_year }}</p>
</td>
<td width="50%" style="padding: {{ $spaceLg }}; background: {{ $surfaceColor }}; vertical-align: top;">
<p style="margin: 0 0 {{ $spaceXs }}; color: {{ $mutedColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif;">Semester</p>
<p style="margin: 0; color: {{ $textColor }}; font-size: 16px; font-weight: 700; font-family: system-ui, -apple-system, sans-serif;">{{ $semester }}</p>
</td>
</tr>
</table>

### Next Steps

@if(!empty($pdfAttached) && $pdfAttached)
1. **Review your Class Schedule:** Please check the attached Assessment Form PDF for your detailed class schedule and room assignments.
2. **Keep your Assessment Form:** The attached PDF serves as your official proof of enrollment and contains important payment details.

<div style="margin: {{ $spaceLg }} 0 {{ $spaceXl }}; padding: {{ $spaceLg }}; border-left: 4px solid {{ $successColor }}; border-radius: {{ $radiusSm }}; background: {{ $successSurfaceColor }};">
<p style="margin: 0; color: {{ $successColor }}; font-size: 14px; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif;">
Your Assessment Form PDF is attached to this email for easy reference.
</p>
</div>
@else
1. **Review your Class Schedule:** Your Assessment Form will be available in the Student Portal shortly. Please log in to download it.
2. **Keep your Assessment Form:** This document serves as your official proof of enrollment and contains important payment details.
@endif

@if(!empty($has_portal_account) && $has_portal_account)
<div style="margin: {{ $spaceXl }} 0 {{ $spaceLg }}; padding: {{ $spaceLg }}; border: 1px solid {{ $borderColor }}; border-radius: {{ $radiusMd }}; background: {{ $softSurfaceColor }}; text-align: center;">
<p style="margin: 0; color: {{ $mutedColor }}; font-size: 14px; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
Your Student Portal account is ready. Use it to view schedules, enrollment details, and assessment updates.
</p>
</div>

@component('mail::button', ['url' => config('app.url'), 'color' => 'primary'])
Access Student Portal
@endcomponent
@else
@component('mail::panel')
<div style="text-align: center; padding: {{ $spaceSm }} 0 {{ $spaceXs }};">
<p style="margin: 0 0 {{ $spaceSm }}; color: {{ $themeColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
Student Portal Access
</p>
<h2 style="margin: 0 0 {{ $spaceMd }}; color: {{ $textColor }}; font-size: 20px; line-height: 1.3; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
Create Your Student Portal Account
</h2>
<p style="margin: 0 0 {{ $spaceLg }}; color: {{ $mutedColor }}; font-size: 14px; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
You can now create your account in the Student Portal using your Student ID and your email. Use the following details when signing up:
</p>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto {{ $spaceSm }};">
<tr>
<td style="padding: {{ $spaceSm }}; vertical-align: top;">
<div style="padding: {{ $spaceMd }}; border: 1px solid {{ $borderColor }}; border-radius: {{ $radiusSm }}; background: {{ $surfaceColor }};">
<p style="margin: 0 0 {{ $spaceXs }}; color: {{ $mutedColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif; text-align: center;">Student ID:</p>
<p style="margin: 0; color: {{ $textColor }}; font-size: 16px; font-weight: 700; font-family: system-ui, -apple-system, sans-serif; text-align: center;">{{ $student_id_display ?? 'N/A' }}</p>
</div>
</td>
<td style="padding: {{ $spaceSm }}; vertical-align: top;">
<div style="padding: {{ $spaceMd }}; border: 1px solid {{ $borderColor }}; border-radius: {{ $radiusSm }}; background: {{ $surfaceColor }};">
<p style="margin: 0 0 {{ $spaceXs }}; color: {{ $mutedColor }}; font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-family: system-ui, -apple-system, sans-serif; text-align: center;">Email:</p>
<p style="margin: 0; color: {{ $textColor }}; font-size: 14px; font-weight: 700; font-family: system-ui, -apple-system, sans-serif; text-align: center; word-break: break-word;">{{ $student_email ?? 'N/A' }}</p>
</div>
</td>
</tr>
</table>
</div>
@endcomponent

@component('mail::button', ['url' => $signup_url, 'color' => 'primary'])
Create Your Account
@endcomponent

<p style="margin: -{{ $spaceSm }} 0 {{ $spaceXl }}; color: {{ $mutedColor }}; font-size: 13px; line-height: 1.6; font-family: system-ui, -apple-system, sans-serif; text-align: center;">
Already have an account? <a href="{{ config('app.url') }}" style="color: {{ $themeColor }}; font-weight: 700; text-decoration: underline;">Sign in instead.</a>
</p>
@endif

If you have any questions regarding your schedule or account, please contact the Student Services office.

Best wishes for a successful semester!<br>
{{ $organizationName }}

@component('mail::subcopy')
@if(!empty($siteSettings['supportEmail']) || !empty($siteSettings['supportPhone']))
Need help? Contact us:
@if(!empty($siteSettings['supportEmail']))<br>Email: {{ $siteSettings['supportEmail'] }}@endif
@if(!empty($siteSettings['supportPhone']))<br>Phone: {{ $siteSettings['supportPhone'] }}@endif
@endif
@endcomponent
@endcomponent
