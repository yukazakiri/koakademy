<x-mail::message>
# {{ $subjectLine }}

Dear {{ $studentName }},

{!! nl2br(e($body)) !!}

If you have any questions or need assistance, please contact our office.

Sincerely,<br>
{{ $senderName }}<br>
{{ $senderRole }}<br>
{{ $schoolName }}
</x-mail::message>
