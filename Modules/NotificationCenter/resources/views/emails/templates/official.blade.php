<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
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
                                    @if(!empty($official_badge))
                                    <div style="display: inline-block; padding: 6px 16px; background: rgba(255,255,255,0.15); border-radius: 4px; margin-bottom: 16px;">
                                        <span style="font-size: 11px; font-weight: 600; color: #ffffff; letter-spacing: 2px; text-transform: uppercase;">{{ $official_badge }}</span>
                                    </div>
                                    @endif
                                    <h1 style="font-family: 'Crimson Pro', serif; font-size: 28px; font-weight: 600; color: #ffffff; margin: 0; line-height: 1.3;">{{ $title }}</h1>
                                    @if(!empty($reference_number))
                                    <div style="margin-top: 16px; font-size: 13px; color: rgba(255,255,255,0.7); letter-spacing: 1px;">
                                        Ref: {{ $reference_number }}
                                    </div>
                                    @endif
                                </div>

                                <div style="padding: 50px 50px 40px;">
                                    <div style="margin-bottom: 30px;">
                                        @if(!empty($recipient_name))
                                        <p style="font-size: 15px; color: #4a5568; margin: 0 0 4px;"><strong>{{ $recipient_name }}</strong></p>
                                        @endif
                                        @if(!empty($recipient_details))
                                        <p style="font-size: 14px; color: #718096; margin: 0;">{{ $recipient_details }}</p>
                                        @endif
                                    </div>

                                    <div style="margin-bottom: 30px;">
                                        <p style="font-size: 14px; color: #718096; margin: 0;">
                                            Date: {{ $date ?? \Carbon\Carbon::now()->format('F j, Y') }}
                                        </p>
                                    </div>

                                    <div style="border-left: 3px solid #1a365d; padding-left: 24px; margin-bottom: 34px;">
                                        <p style="font-size: 16px; line-height: 1.8; color: #2d3748; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($details))
                                    <div style="background: #faf9f7; border: 1px solid #e2ddd5; margin-bottom: 34px;">
                                        <div style="padding: 16px 24px; background: #f4f1eb; border-bottom: 1px solid #e2ddd5;">
                                            <span style="font-family: 'Crimson Pro', serif; font-size: 16px; font-weight: 600; color: #1a365d;">Document Details</span>
                                        </div>
                                        <div style="padding: 24px;">
                                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                @foreach($details as $label => $value)
                                                <tr>
                                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2ddd5; width: 40%;">
                                                        <span style="font-size: 14px; color: #718096;">{{ $label }}</span>
                                                    </td>
                                                    <td style="padding: 10px 0; border-bottom: 1px solid #e2ddd5;">
                                                        <span style="font-size: 14px; color: #1a365d; font-weight: 500;">{{ $value }}</span>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </table>
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; padding: 20px 0;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 14px 40px; background: #1a365d; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; letter-spacing: 0.5px; border-radius: 4px;">
                                            {{ $action_text ?? 'View Document' }}
                                        </a>
                                    </div>
                                    @endif

                                    <div style="margin-top: 50px; padding-top: 30px; border-top: 1px solid #e2ddd5;">
                                        <p style="font-size: 15px; color: #4a5568; margin: 0 0 30px;">Respectfully,</p>
                                        <div>
                                            @if(!empty($signature_name))
                                            <p style="font-family: 'Crimson Pro', serif; font-size: 18px; font-weight: 600; color: #1a365d; margin: 0;">{{ $signature_name }}</p>
                                            @endif
                                            @if(!empty($signature_title))
                                            <p style="font-size: 14px; color: #718096; margin: 4px 0 0;">{{ $signature_title }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div style="background: #f4f1eb; padding: 24px 50px; border-top: 1px solid #e2ddd5;">
                                    <p style="font-size: 12px; color: #718096; margin: 0; text-align: center;">
                                        This is an official document from {{ config('app.name') }}. Please keep for your records.
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
                                            {{ config('app.name') }} • {{ config('app.url') }}
                                        </p>
                                        <p style="font-size: 11px; color: #cbd5e0; margin: 0;">
                                            © {{ date('Y') }} All rights reserved.
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
