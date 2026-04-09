<x-mail::message>
# Welcome to {{ app(\App\Settings\SiteSettings::class)->getOrganizationName() }}!

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
1.  **Review your Class Schedule:** Please check the attached Assessment Form PDF for your detailed class schedule and room assignments.
2.  **Keep your Assessment Form:** The attached PDF serves as your official proof of enrollment and contains important payment details.

<x-mail::button :url="config('app.url')">
Access Student Portal
</x-mail::button>

If you have any questions regarding your schedule or account, please contact the Student Services office.

Best wishes for a successful semester!<br>
{{ config('app.name') }}
</x-mail::message>
