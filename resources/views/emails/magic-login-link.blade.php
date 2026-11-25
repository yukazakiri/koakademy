<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $appName }} — Passwordless Sign-In</title>
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #09090b !important; }
            .email-card { background-color: #18181b !important; border-color: #27272a !important; }
            .email-banner { background-color: #27272a !important; border-color: #3f3f46 !important; }
            .email-heading { color: #fafafa !important; }
            .email-text { color: #d4d4d8 !important; }
            .email-muted { color: #a1a1aa !important; }
            .email-meta { background-color: #27272a !important; border-color: #3f3f46 !important; }
            .email-notice { background-color: #1e293b !important; border-left-color: #60a5fa !important; }
            .email-notice-title { color: #93c5fd !important; }
            .email-code { background-color: #09090b !important; border-color: #27272a !important; color: #a1a1aa !important; }
            .email-cta { background-color: #fafafa !important; color: #09090b !important; }
        }
    </style>
</head>
<body class="email-bg" style="margin: 0; padding: 0; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" class="email-bg" style="background-color: #f4f4f5;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    {{-- Header Branding --}}
                    <tr>
                        <td style="padding: 0 0 24px 0; text-align: center;">
                            <div class="email-heading" style="font-size: 20px; font-weight: 700; color: #18181b; letter-spacing: 1.5px; text-transform: uppercase;">
                                {{ $appName }}
                            </div>
                        </td>
                    </tr>

                    {{-- Main Card --}}
                    <tr>
                        <td>
                            <div class="email-card" style="background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06);">
                                {{-- Card Banner --}}
                                <div class="email-banner" style="background-color: #fafafa; padding: 32px; text-align: center; border-bottom: 1px solid #e4e4e7;">
                                    <div style="display: inline-block; padding: 4px 12px; background-color: rgba(59,130,246,0.12); border-radius: 9999px; margin-bottom: 12px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #2563eb; letter-spacing: 1px; text-transform: uppercase;">Passwordless Access</span>
                                    </div>
                                    <h1 class="email-heading" style="font-size: 24px; font-weight: 700; color: #18181b; margin: 0; line-height: 1.3;">
                                        Sign In With Your Magic Link
                                    </h1>
                                    <p class="email-muted" style="font-size: 13px; color: #52525b; margin: 8px 0 0;">
                                        One-time secure authentication for {{ $user->email }}
                                    </p>
                                </div>

                                {{-- Card Content --}}
                                <div style="padding: 32px;">
                                    <p class="email-text" style="font-size: 15px; line-height: 1.65; color: #3f3f46; margin: 0 0 24px;">
                                        Hello {{ $user->name }},<br><br>
                                        We received a request to sign in to your {{ $appName }} account without entering a password. Select the button below to continue:
                                    </p>

                                    {{-- Primary CTA Button --}}
                                    <div style="text-align: center; margin: 28px 0;">
                                        <a href="{{ $url }}"
                                           class="email-cta"
                                           style="display: inline-block; background-color: #18181b; color: #ffffff; font-weight: 600; font-size: 15px; padding: 14px 32px; border-radius: 10px; text-decoration: none;">
                                            Sign in to {{ $appName }}
                                        </a>
                                    </div>

                                    {{-- Expiry Alert --}}
                                    <div class="email-notice" style="border-left: 3px solid #2563eb; background-color: #eff6ff; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 0 0 24px;">
                                        <p class="email-notice-title" style="font-size: 13px; color: #1d4ed8; font-weight: 600; margin: 0 0 4px;">
                                            This link expires in {{ $expiresInMinutes }} minutes.
                                        </p>
                                        <p class="email-muted" style="font-size: 12px; color: #52525b; margin: 0; line-height: 1.5;">
                                            For your security, this magic link can only be used once.
                                        </p>
                                    </div>

                                    {{-- Request Meta Details --}}
                                    <div class="email-meta" style="background-color: #f4f4f5; border: 1px solid #e4e4e7; border-radius: 10px; padding: 14px 18px; margin-bottom: 24px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px; width: 40%;">Requested At</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">{{ $requestTime }}</td>
                                            </tr>
                                            @if($requestIp)
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">IP Address</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">{{ $requestIp }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td class="email-muted" style="padding: 5px 0; color: #71717a; font-size: 12px;">Account</td>
                                                <td class="email-heading" style="padding: 5px 0; color: #18181b; font-size: 12px; font-weight: 500;">{{ $user->email }}</td>
                                            </tr>
                                        </table>
                                    </div>

                                    {{-- Raw URL Fallback --}}
                                    <div style="margin-bottom: 20px;">
                                        <p class="email-muted" style="font-size: 12px; color: #71717a; margin: 0 0 8px; line-height: 1.5;">
                                            If the button doesn&apos;t work, copy and paste this URL into your browser:
                                        </p>
                                        <p class="email-code" style="font-size: 11px; word-break: break-all; color: #52525b; background-color: #fafafa; padding: 10px 12px; border-radius: 6px; border: 1px solid #e4e4e7; margin: 0;">
                                            {{ $url }}
                                        </p>
                                    </div>

                                    {{-- Disregard Notice --}}
                                    <div style="border-top: 1px solid #e4e4e7; padding-top: 16px;">
                                        <p class="email-muted" style="font-size: 12px; line-height: 1.6; color: #71717a; margin: 0;">
                                            If you did not request this sign-in link, you can safely ignore this email. No one can sign in without access to this link.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 0; text-align: center;">
                            <p class="email-muted" style="font-size: 11px; color: #71717a; margin: 0 0 6px;">
                                {{ $appName }} &bull; {{ config('app.url') }}
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
