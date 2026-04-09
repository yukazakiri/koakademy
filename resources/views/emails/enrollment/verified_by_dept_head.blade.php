<x-mail::message>
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
{{ config('app.name') }}
</x-mail::message>
