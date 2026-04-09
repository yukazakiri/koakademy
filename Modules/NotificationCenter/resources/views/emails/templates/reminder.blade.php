<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(180deg, #ffecd2 0%, #fcb69f 100%); font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 24px 0;">
                            <div style="display: flex; justify-content: center; gap: 12px;">
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 24px;">🌸</span>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 24px;">🌺</span>
                                </div>
                                <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 24px;">🌻</span>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 28px; overflow: hidden; box-shadow: 0 20px 60px rgba(252,182,159,0.3);">
                                
                                <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 50px 40px; text-align: center;">
                                    <div style="width: 80px; height: 80px; margin: 0 auto 24px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(252,182,159,0.4);">
                                        <span style="font-size: 40px;">{{ $icon ?? '👋' }}</span>
                                    </div>
                                    <h1 style="font-family: 'Lora', serif; font-size: 30px; font-weight: 600; color: #4a3728; margin: 0 0 12px;">{{ $title }}</h1>
                                    <p style="font-size: 16px; color: #6b5344; margin: 0;">Just a friendly reminder for you! ✨</p>
                                </div>

                                <div style="padding: 50px 40px;">
                                    <div style="margin-bottom: 32px;">
                                        <p style="font-size: 17px; line-height: 1.8; color: #4a3728; margin: 0;">
                                            Hello {{ $recipient_name ?? 'there' }}, 👋
                                        </p>
                                    </div>

                                    <div style="background: linear-gradient(135deg, #fff9f5 0%, #fff5ee 100%); border-radius: 16px; padding: 28px; margin-bottom: 32px; border: 1px solid rgba(252,182,159,0.3);">
                                        <p style="font-size: 16px; line-height: 1.8; color: #4a3728; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($reminder_items))
                                    <div style="margin-bottom: 32px;">
                                        <div style="font-size: 14px; font-weight: 700; color: #c97b5d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">📋 Reminder Details</div>
                                        <div style="background: #fafafa; border-radius: 12px; overflow: hidden;">
                                            @foreach($reminder_items as $item)
                                            <div style="padding: 16px 20px; border-bottom: 1px solid #f0e6e0; display: flex; align-items: center;">
                                                <span style="font-size: 18px; margin-right: 12px;">{{ $item['icon'] ?? '•' }}</span>
                                                <div>
                                                    <div style="font-size: 14px; font-weight: 600; color: #4a3728;">{{ $item['label'] }}</div>
                                                    <div style="font-size: 13px; color: #8b7355;">{{ $item['value'] }}</div>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($due_date))
                                    <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 16px; padding: 24px; margin-bottom: 32px; text-align: center;">
                                        <div style="font-size: 13px; color: #8b5a3c; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Due Date</div>
                                        <div style="font-size: 24px; font-weight: 700; color: #4a3728;">{{ $due_date }}</div>
                                        @if(!empty($days_remaining))
                                        <div style="font-size: 13px; color: #8b5a3c; margin-top: 8px;">
                                            {{ $days_remaining }} days remaining
                                        </div>
                                        @endif
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-bottom: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #fcb69f 0%, #ff8a65 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-size: 16px; font-weight: 700; box-shadow: 0 8px 25px rgba(252,182,159,0.5);">
                                            {{ $action_text ?? 'Take Action' }}
                                            <span style="margin-left: 8px;">→</span>
                                        </a>
                                    </div>
                                    @endif

                                    <div style="background: #fafafa; border-radius: 12px; padding: 20px; text-align: center;">
                                        <p style="font-size: 14px; color: #8b7355; margin: 0;">
                                            💡 <em>Need help? We're here for you! Just reach out to our support team.</em>
                                        </p>
                                    </div>
                                </div>

                                <div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); padding: 28px 40px;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <p style="font-family: 'Lora', serif; font-size: 16px; color: #4a3728; margin: 0 0 4px;">
                                                    Warm regards,
                                                </p>
                                                <p style="font-size: 14px; color: #6b5344; margin: 0;">
                                                    The {{ config('app.name') }} Team 🌷
                                                </p>
                                            </td>
                                            <td align="right" style="vertical-align: middle;">
                                                <span style="font-size: 32px;">🌻</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0;">
                            <p style="font-size: 12px; color: rgba(74,55,40,0.5); text-align: center; margin: 0 0 8px;">
                                💝 Sent with care from {{ config('app.name') }}
                            </p>
                            <p style="font-size: 11px; color: rgba(74,55,40,0.4); text-align: center; margin: 0;">
                                © {{ date('Y') }} All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
