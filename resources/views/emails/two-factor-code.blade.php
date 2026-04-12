<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} — Two-Factor Authentication</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #f4f1eb; font-family: 'Source Sans 3', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f4f1eb;">
        <tr>
            <td align="center" style="padding: 50px 20px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width: 640px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 30px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="width: 50px; border-top: 3px solid #1a365d;"></td>
                                    <td style="padding: 0 20px;">
                                        <div style="font-family: 'Crimson Pro', serif; font-size: 24px; font-weight: 700; color: #1a365d; letter-spacing: 2px; text-transform: uppercase;">{{ config('app.name') }}</div>
                                    </td>
                                    <td style="width: 50px; border-top: 3px solid #1a365d;"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border: 1px solid #e2ddd5; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">

                                <div style="background: #1a365d; padding: 40px; text-align: center;">
                                    <div style="display: inline-block; padding: 6px 16px; background: rgba(255,255,255,0.15); border-radius: 4px; margin-bottom: 16px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #ffffff; letter-spacing: 2px; text-transform: uppercase;">Security Verification</span>
                                    </div>
                                    <h1 style="font-family: 'Crimson Pro', serif; font-size: 28px; font-weight: 600; color: #ffffff; margin: 0; line-height: 1.3;">Two-Factor Authentication</h1>
                                    <p style="font-size: 14px; color: rgba(255,255,255,0.7); margin: 12px 0 0;">Use this code to complete your sign-in</p>
                                </div>

                                <div style="padding: 50px 50px 40px;">
                                    <p style="font-size: 16px; line-height: 1.8; color: #2d3748; margin: 0 0 30px;">
                                        You are receiving this email because a sign-in attempt requires two-factor authentication verification. Please enter the following code to continue:
                                    </p>

                                    <div style="text-align: center; background: #faf9f7; border: 1px solid #e2ddd5; border-radius: 4px; padding: 32px 20px; margin: 0 0 30px;">
                                        <p style="font-size: 12px; font-weight: 600; color: #718096; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 12px;">Your Verification Code</p>
                                        <div style="font-family: 'Crimson Pro', serif; font-size: 40px; font-weight: 700; color: #1a365d; letter-spacing: 12px; line-height: 1;">{{ $code }}</div>
                                    </div>

                                    <div style="border-left: 3px solid #c53030; padding-left: 20px; margin: 0 0 30px;">
                                        <p style="font-size: 14px; color: #c53030; font-weight: 600; margin: 0 0 4px;">This code expires in 5 minutes.</p>
                                        <p style="font-size: 14px; color: #718096; margin: 0;">For your security, this code can only be used once and will become invalid after 5 minutes.</p>
                                    </div>

                                    <div style="background: #faf9f7; border: 1px solid #e2ddd5; margin-bottom: 34px;">
                                        <div style="padding: 16px 24px; background: #f4f1eb; border-bottom: 1px solid #e2ddd5;">
                                            <span style="font-family: 'Crimson Pro', serif; font-size: 16px; font-weight: 600; color: #1a365d;">Security Notice</span>
                                        </div>
                                        <div style="padding: 20px 24px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                <tr>
                                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2ddd5; width: 40%;">
                                                        <span style="font-size: 14px; color: #718096;">Requested</span>
                                                    </td>
                                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2ddd5;">
                                                        <span style="font-size: 14px; color: #1a365d; font-weight: 500;">{{ now()->format('F j, Y \a\t g:i A') }}</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2ddd5;">
                                                        <span style="font-size: 14px; color: #718096;">Validity</span>
                                                    </td>
                                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2ddd5;">
                                                        <span style="font-size: 14px; color: #1a365d; font-weight: 500;">5 minutes</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 8px 0;">
                                                        <span style="font-size: 14px; color: #718096;">Type</span>
                                                    </td>
                                                    <td style="padding: 8px 0;">
                                                        <span style="font-size: 14px; color: #1a365d; font-weight: 500;">One-Time Code</span>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <div style="border-left: 3px solid #1a365d; padding-left: 24px;">
                                        <p style="font-size: 14px; line-height: 1.8; color: #4a5568; margin: 0;">
                                            If you did not attempt to sign in to your account, please disregard this email and consider changing your password. No further action is required on your part.
                                        </p>
                                    </div>
                                </div>

                                <div style="background: #f4f1eb; padding: 24px 50px; border-top: 1px solid #e2ddd5;">
                                    <p style="font-size: 12px; color: #718096; margin: 0; text-align: center;">
                                        This is an automated security message from {{ config('app.name') }}. Do not reply to this email.
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center">
                                        <p style="font-size: 12px; color: #a0aec0; margin: 0 0 8px;">
                                            {{ config('app.name') }} &bull; {{ config('app.url') }}
                                        </p>
                                        <p style="font-size: 11px; color: #cbd5e0; margin: 0;">
                                            &copy; {{ date('Y') }} All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
