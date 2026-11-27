<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $appName }} — Your {{ $identifierHeading ?? 'Student ID' }}</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #09090b !important; }
            .email-card { background-color: #18181b !important; border-color: #27272a !important; }
            .email-banner { background-color: #27272a !important; border-color: #3f3f46 !important; }
            .email-heading { color: #fafafa !important; }
            .email-text { color: #d4d4d8 !important; }
            .email-muted { color: #a1a1aa !important; }
            .email-record-box { background-color: #09090b !important; border-color: #27272a !important; }
            .email-notice { background-color: #1e293b !important; border-left-color: #60a5fa !important; }
            .email-notice-title { color: #93c5fd !important; }
        }
    </style>
</head>
<body class="email-bg" style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-bg" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="padding: 0 0 24px 0; text-align: center;">
                            <div class="email-heading" style="font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: 1.5px; text-transform: uppercase;">
                                {{ $appName }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div class="email-card" style="background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);">
                                <div class="email-banner" style="background-color: #fafafa; padding: 32px; text-align: center; border-bottom: 1px solid #e4e4e7;">
                                    <div style="display: inline-block; padding: 4px 12px; background-color: rgba(59, 130, 246, 0.12); border-radius: 9999px; margin-bottom: 12px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #2563eb; letter-spacing: 1px; text-transform: uppercase;">Portal Activation Help</span>
                                    </div>
                                    <h1 class="email-heading" style="font-size: 24px; font-weight: 700; color: #18181b; margin: 0; line-height: 1.3;">
                                        Your Student Identifier
                                    </h1>
                                    <p class="email-muted" style="font-size: 13px; color: #52525b; margin: 8px 0 0;">
                                        Use the registered identifier below to finish activating your student portal account
                                    </p>
                                </div>

                                <div style="padding: 32px;">
                                    <p class="email-text" style="font-size: 15px; line-height: 1.65; color: #3f3f46; margin: 0 0 20px;">
                                        We received a request to recover the {{ $identifierHeading ?? 'Student ID' }} associated with <strong>{{ $recipientEmail }}</strong>.
                                    </p>

                                    @foreach($records as $record)
                                        @php
                                            $externalValue = $record['external_id'] ?? $record['lrn'] ?? null;
                                            $externalLabel = $record['external_id_label'] ?? 'External Identifier';
                                            $primaryLabel = $record['student_id_label'] ?? 'Student ID';
                                        @endphp
                                        <div class="email-record-box" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 12px; padding: 20px; margin: 0 0 16px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                @if(!empty($record['student_name']))
                                                    <tr>
                                                        <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px; width: 42%;">Student Name</td>
                                                        <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 13px; font-weight: 600;">{{ $record['student_name'] }}</td>
                                                    </tr>
                                                @endif
                                                @if(!empty($record['student_id']))
                                                    <tr>
                                                        <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">{{ $primaryLabel }}</td>
                                                        <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 16px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 1px;">{{ $record['student_id'] }}</td>
                                                    </tr>
                                                @endif
                                                @if(!empty($externalValue) && $externalValue !== ($record['student_id'] ?? null))
                                                    <tr>
                                                        <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">{{ $externalLabel }}</td>
                                                        <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 16px; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 1px;">{{ $externalValue }}</td>
                                                    </tr>
                                                @endif
                                                @if(!empty($record['program']))
                                                    <tr>
                                                        <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">Program</td>
                                                        <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 13px; font-weight: 500;">{{ $record['program'] }}</td>
                                                    </tr>
                                                @endif
                                            </table>
                                        </div>
                                    @endforeach

                                    <div style="text-align: center; margin: 28px 0 24px;">
                                        <a href="{{ $signupUrl }}" style="display: inline-block; background-color: #18181b; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 10px;">
                                            Continue Account Activation
                                        </a>
                                    </div>

                                    <div class="email-notice" style="border-left: 3px solid #2563eb; background-color: #eff6ff; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 0 0 20px;">
                                        <p class="email-notice-title" style="font-size: 13px; color: #1d4ed8; font-weight: 600; margin: 0 0 4px;">
                                            Security reminder
                                        </p>
                                        <p class="email-muted" style="font-size: 12px; color: #52525b; margin: 0; line-height: 1.5;">
                                            If you did not request this email, you can safely ignore it. Your student portal account still requires email OTP verification before activation.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 0; text-align: center;">
                            <p class="email-muted" style="font-size: 11px; color: #71717a; margin: 0 0 6px;">
                                {{ $appName }} &bull; {{ $orgName }}
                            </p>
                            <p class="email-muted" style="font-size: 11px; color: #a1a1aa; margin: 0;">
                                &copy; {{ date('Y') }} {{ $orgName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
