<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ config('app.name') }} — Two-Factor Authentication</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #09090b !important; }
            .email-card { background-color: #18181b !important; border-color: #27272a !important; }
            .email-banner { background-color: #27272a !important; border-color: #3f3f46 !important; }
            .email-heading { color: #fafafa !important; }
            .email-text { color: #d4d4d8 !important; }
            .email-muted { color: #a1a1aa !important; }
            .email-code-box { background-color: #09090b !important; border-color: #27272a !important; }
            .email-code-value { color: #fafafa !important; }
            .email-meta { background-color: #27272a !important; border-color: #3f3f46 !important; }
            .email-notice { background-color: #450a0a !important; border-left-color: #f87171 !important; }
            .email-notice-title { color: #fca5a5 !important; }
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
                                {{ config('app.name') }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div class="email-card" style="background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);">
                                <div class="email-banner" style="background-color: #fafafa; padding: 32px; text-align: center; border-bottom: 1px solid #e4e4e7;">
                                    <div style="display: inline-block; padding: 4px 12px; background-color: rgba(59, 130, 246, 0.12); border-radius: 9999px; margin-bottom: 12px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #2563eb; letter-spacing: 1px; text-transform: uppercase;">Security Verification</span>
                                    </div>
                                    <h1 class="email-heading" style="font-size: 24px; font-weight: 700; color: #18181b; margin: 0; line-height: 1.3;">
                                        Two-Factor Authentication
                                    </h1>
                                    <p class="email-muted" style="font-size: 13px; color: #52525b; margin: 8px 0 0;">
                                        Use this verification code to complete your sign-in
                                    </p>
                                </div>

                                <div style="padding: 32px;">
                                    <p class="email-text" style="font-size: 15px; line-height: 1.65; color: #3f3f46; margin: 0 0 24px;">
                                        You are receiving this email because a sign-in attempt requires two-factor authentication verification. Enter the code below to continue:
                                    </p>

                                    <div class="email-code-box" style="text-align: center; background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 12px; padding: 24px 16px; margin: 0 0 24px;">
                                        <p class="email-muted" style="font-size: 11px; font-weight: 600; color: #71717a; letter-spacing: 1.5px; text-transform: uppercase; margin: 0 0 12px;">
                                            Your Verification Code
                                        </p>
                                        <div class="email-code-value" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace; font-size: 36px; font-weight: 700; color: #18181b; letter-spacing: 10px; line-height: 1;">
                                            {{ $code }}
                                        </div>
                                    </div>

                                    <div class="email-notice" style="border-left: 3px solid #dc2626; background-color: #fef2f2; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 0 0 24px;">
                                        <p class="email-notice-title" style="font-size: 13px; color: #b91c1c; font-weight: 600; margin: 0 0 4px;">
                                            This code expires in 5 minutes.
                                        </p>
                                        <p class="email-muted" style="font-size: 12px; color: #52525b; margin: 0; line-height: 1.5;">
                                            For your security, this code can only be used once and will become invalid after 5 minutes.
                                        </p>
                                    </div>

                                    <div class="email-meta" style="background-color: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 10px; padding: 14px 18px; margin-bottom: 24px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px; width: 40%;">Requested</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">{{ now()->format('F j, Y \a\t g:i A') }}</td>
                                            </tr>
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">Validity</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">5 minutes</td>
                                            </tr>
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">Type</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">One-Time Code</td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style="border-top: 1px solid #e4e4e7; padding-top: 16px;">
                                        <p class="email-muted" style="font-size: 12px; line-height: 1.6; color: #71717a; margin: 0;">
                                            If you did not attempt to sign in to your account, please disregard this email and consider changing your password.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 0; text-align: center;">
                            <p class="email-muted" style="font-size: 11px; color: #71717a; margin: 0 0 6px;">
                                {{ config('app.name') }} &bull; {{ config('app.url') }}
                            </p>
                            <p class="email-muted" style="font-size: 11px; color: #a1a1aa; margin: 0;">
                                &copy; {{ date('Y') }} All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
