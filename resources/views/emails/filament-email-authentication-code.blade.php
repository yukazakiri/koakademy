<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $appName }} — Sign-in code</title>
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
                        <td align="center" style="padding: 0 0 24px;">
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
                                        <span style="font-size: 11px; font-weight: 600; color: #2563eb; letter-spacing: 1px; text-transform: uppercase;">Two-Factor Authentication</span>
                                    </div>
                                    <h1 class="email-heading" style="font-size: 24px; line-height: 1.3; color: #18181b; margin: 0; font-weight: 700;">
                                        Hello!
                                    </h1>
                                    <p class="email-muted" style="font-size: 13px; color: #52525b; margin: 8px 0 0;">
                                        Use this sign-in code to complete your two-factor authentication.
                                    </p>
                                </div>

                                <div style="padding: 32px;">
                                    <div class="email-code-box" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 12px; padding: 24px 16px; margin: 0 0 24px; text-align: center;">
                                        <p class="email-muted" style="font-size: 11px; font-weight: 600; color: #71717a; letter-spacing: 1.5px; text-transform: uppercase; margin: 0 0 12px;">
                                            Your sign-in code
                                        </p>
                                        <div class="email-code-value" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace; font-size: 36px; line-height: 1; font-weight: 700; letter-spacing: 10px; color: #18181b;">
                                            {{ $code }}
                                        </div>
                                    </div>

                                    <div class="email-notice" style="border-left: 3px solid #2563eb; background-color: #eff6ff; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 0 0 24px;">
                                        <p class="email-notice-title" style="font-size: 13px; color: #1d4ed8; font-weight: 600; margin: 0 0 4px;">
                                            This code expires in {{ $codeExpiryMinutes }} {{ Str::plural('minute', $codeExpiryMinutes) }}.
                                        </p>
                                        <p class="email-muted" style="font-size: 12px; color: #52525b; margin: 0; line-height: 1.5;">
                                            For your security, do not share this code with anyone.
                                        </p>
                                    </div>

                                    <div style="border-top: 1px solid #e4e4e7; padding-top: 16px;">
                                        <p class="email-muted" style="font-size: 12px; line-height: 1.6; color: #71717a; margin: 0;">
                                            If you did not attempt to sign in, you can safely ignore this email. For your security, consider changing your password if this keeps happening.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding: 24px 0;">
                            <p class="email-muted" style="font-size: 11px; color: #71717a; margin: 0 0 6px;">
                                {{ $appName }} &bull; {{ $appUrl }}
                            </p>
                            <p class="email-muted" style="font-size: 11px; color: #a1a1aa; margin: 0;">
                                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
