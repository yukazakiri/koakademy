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

# {{ $headline ?? 'Application Status Update' }}

Dear {{ $student_name }},

@if(!empty($custom_body))
{!! nl2br(e($custom_body)) !!}
@else
Your enrollment application status has been officially updated to:
@endif

<x-mail::panel>
<div style="font-size: 16px; font-weight: 700; color: {{ $siteSettings['themeColor'] ?? '#0f172a' }};">
    Status: {{ $status }}
</div>
<div style="margin-top: 4px; font-size: 13px; color: #64748b;">
    Stage: {{ $step_name }}
</div>
</x-mail::panel>

@if(!empty($include_details) && $include_details)
### Application Details

<x-mail::table>
| Field | Details |
| :--- | :--- |
| **Current Status** | {{ $status }} |
| **Workflow Stage** | {{ $step_name }} |
| **Academic Term** | {{ $school_year }} &middot; Semester {{ $semester }} |
@if(!empty($program))
| **Program / Course** | {{ $program }} |
@endif
</x-mail::table>
@endif

@if(!empty($include_subjects) && !empty($subjects) && $subjects->count() > 0)
### Approved Subjects
<x-mail::table>
| Code | Title | Units |
| :--- | :--- | :--- |
@foreach($subjects as $subject)
| {{ $subject->subject->code ?? 'N/A' }} | {{ $subject->subject->title ?? 'Subject' }} | {{ $subject->subject->units ?? 0 }} |
@endforeach
</x-mail::table>
@endif

@if(!empty($include_tuition) && !empty($tuition))
### Financial Summary
<x-mail::panel>
**Total Tuition:** ₱{{ number_format($tuition->overall_tuition ?? 0, 2) }}
<br>
**Downpayment Due:** ₱{{ number_format($tuition->downpayment ?? 0, 2) }}
</x-mail::panel>
@endif

@if(empty($custom_body))
### What This Means For You

@php
    $lowerStatus = strtolower($status);
@endphp

@if(str_contains($lowerStatus, 'enrolled') || str_contains($lowerStatus, 'completed'))
Congratulations! Your enrollment is now officially complete. You may log into the student portal to view your enrolled class schedule, faculty assignments, and download your matriculation forms.
@elseif(str_contains($lowerStatus, 'cashier') || str_contains($lowerStatus, 'payment') || str_contains($lowerStatus, 'settlement'))
Your academic records have been cleared. Please proceed with settling your downpayment or tuition balance at the Cashier's Office or through your approved payment channels.
@elseif(str_contains($lowerStatus, 'reject') || str_contains($lowerStatus, 'cancel'))
Your enrollment application has been marked as {{ $status }}. If you believe this is in error or require further assistance, please contact the admissions department.
@elseif(str_contains($lowerStatus, 'verified') || str_contains($lowerStatus, 'clearance'))
Your requirements have passed academic clearance and are moving forward to the next stage of registration.
@else
Your enrollment record is actively progressing through our verification pipeline. Please monitor your portal account and inbox for subsequent updates and instructions.
@endif
@endif

<x-mail::button :url="$portalUrl">
{{ $button_label ?? 'Visit Student Portal' }}
</x-mail::button>

Best regards,<br>
{{ $siteSettings['organizationName'] ?? config('app.name') }}

<x-mail::subcopy>
    @if(!empty($siteSettings['supportEmail']) || !empty($siteSettings['supportPhone']))
        Need assistance with your enrollment? Contact us:
        @if(!empty($siteSettings['supportEmail']))<br>Email: {{ $siteSettings['supportEmail'] }}@endif
        @if(!empty($siteSettings['supportPhone']))<br>Phone: {{ $siteSettings['supportPhone'] }}@endif
    @endif
</x-mail::subcopy>
</x-mail::message>
