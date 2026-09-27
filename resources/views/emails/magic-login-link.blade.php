<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }} — Passwordless Sign-In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #09090b; font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, sans-serif; color: #f4f4f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #09090b;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    {{-- Header Branding --}}
                    <tr>
                        <td style="padding: 0 0 24px 0; text-align: center;">
                            <div style="font-family: 'Crimson Pro', serif; font-size: 22px; font-weight: 700; color: #ffffff; letter-spacing: 2px; text-transform: uppercase;">
                                {{ $appName }}
                            </div>
                        </td>
                    </tr>

                    {{-- Main Card --}}
                    <tr>
                        <td>
                            <div style="background: #18181b; border: 1px solid #27272a; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                                {{-- Card Banner --}}
                                <div style="background: #27272a; padding: 36px 32px; text-align: center; border-bottom: 1px solid #3f3f46;">
                                    <div style="display: inline-block; padding: 4px 12px; background: rgba(255,255,255,0.1); border-radius: 9999px; margin-bottom: 14px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #a1a1aa; letter-spacing: 1.5px; text-transform: uppercase;">Passwordless Access</span>
                                    </div>
                                    <h1 style="font-family: 'Crimson Pro', serif; font-size: 26px; font-weight: 600; color: #ffffff; margin: 0; line-height: 1.3;">
                                        Sign In With Your Magic Link
                                    </h1>
                                    <p style="font-size: 13px; color: #a1a1aa; margin: 10px 0 0;">
                                        One-click secure authentication for {{ $user->email }}
                                    </p>
                                </div>

                                {{-- Card Content --}}
                                <div style="padding: 36px 32px;">
                                    <p style="font-size: 15px; line-height: 1.7; color: #d4d4d8; margin: 0 0 28px;">
                                        Hello {{ $user->name }},<br><br>
                                        We received a request to sign in to your {{ $appName }} account without entering a password. Tap the button below to securely sign in:
                                    </p>

                                    {{-- Primary CTA Button --}}
                                    <div style="text-align: center; margin: 32px 0;">
                                        <a href="{{ $url }}"
                                           style="display: inline-block; background: #ffffff; color: #09090b; font-weight: 600; font-size: 15px; padding: 14px 36px; border-radius: 10px; text-decoration: none; box-shadow: 0 4px 12px rgba(255,255,255,0.15);">
                                            Sign in to {{ $appName }}
                                        </a>
                                    </div>

                                    {{-- Expiry Alert --}}
                                    <div style="border-left: 3px solid #3b82f6; background: #1c1917; padding: 14px 18px; border-radius: 0 8px 8px 0; margin: 0 0 28px;">
                                        <p style="font-size: 13px; color: #93c5fd; font-weight: 600; margin: 0 0 4px;">
                                            This link expires in {{ $expiresInMinutes }} minutes.
                                        </p>
                                        <p style="font-size: 12px; color: #a1a1aa; margin: 0; line-height: 1.5;">
                                            For security, this magic link can only be used once. It will expire after being clicked or after {{ $expiresInMinutes }} minutes.
                                        </p>
                                    </div>

                                    {{-- Request Meta Details --}}
                                    <div style="background: #27272a; border-radius: 10px; padding: 16px 20px; margin-bottom: 28px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding: 6px 0; color: #71717a; font-size: 12px; width: 40%;">Requested At</td>
                                                <td style="padding: 6px 0; color: #e4e4e7; font-size: 12px; font-weight: 500;">{{ $requestTime }}</td>
                                            </tr>
                                            @if($requestIp)
                                            <tr>
                                                <td style="padding: 6px 0; color: #71717a; font-size: 12px;">IP Address</td>
                                                <td style="padding: 6px 0; color: #e4e4e7; font-size: 12px; font-weight: 500;">{{ $requestIp }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 6px 0; color: #71717a; font-size: 12px;">Account</td>
                                                <td style="padding: 6px 0; color: #e4e4e7; font-size: 12px; font-weight: 500;">{{ $user->email }}</td>
                                            </tr>
                                        </table>
                                    </div>

                                    {{-- Raw URL Fallback --}}
                                    <div style="margin-bottom: 24px;">
                                        <p style="font-size: 12px; color: #71717a; margin: 0 0 8px; line-height: 1.5;">
                                            If the button doesn&apos;t work, copy and paste this URL into your browser:
                                        </p>
                                        <p style="font-size: 11px; word-break: break-all; color: #a1a1aa; background: #09090b; padding: 10px 12px; border-radius: 6px; border: 1px solid #27272a; margin: 0;">
                                            {{ $url }}
                                        </p>
                                    </div>

                                    {{-- Disregard Notice --}}
                                    <div style="border-top: 1px solid #27272a; padding-top: 20px;">
                                        <p style="font-size: 12px; line-height: 1.6; color: #71717a; margin: 0;">
                                            If you did not request this sign-in link, you can safely ignore this email. No one can sign in without access to this email link.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 0; text-align: center;">
                            <p style="font-size: 11px; color: #52525b; margin: 0 0 6px;">
                                {{ $appName }} &bull; {{ config('app.url') }}
                            </p>
                            <p style="font-size: 11px; color: #3f3f46; margin: 0;">
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
