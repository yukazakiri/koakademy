<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); min-height: 100vh; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">
                    
                    <tr>
                        <td style="padding: 0 0 30px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding: 0;">
                                        <div style="display: inline-block; padding: 16px 32px; background: rgba(255,255,255,0.08); border-radius: 100px; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1);">
                                            <span style="font-family: 'Space Grotesk', sans-serif; font-size: 14px; font-weight: 600; color: #e94560; letter-spacing: 3px; text-transform: uppercase;">📢 System Announcement</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: rgba(255,255,255,0.03); border-radius: 24px; border: 1px solid rgba(255,255,255,0.08); overflow: hidden; backdrop-filter: blur(20px);">
                                
                                <div style="padding: 60px 50px 40px; text-align: center; background: linear-gradient(180deg, rgba(233,69,96,0.1) 0%, transparent 100%);">
                                    <div style="width: 80px; height: 80px; margin: 0 auto 30px; background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%); border-radius: 20px; display: flex; align-items: center; justify-content: center; box-shadow: 0 20px 40px rgba(233,69,96,0.3);">
                                        <span style="font-size: 40px;">📣</span>
                                    </div>
                                    <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 32px; font-weight: 700; color: #ffffff; margin: 0 0 16px; line-height: 1.2;">{{ $title }}</h1>
                                    <p style="font-size: 16px; color: rgba(255,255,255,0.6); margin: 0;">{{ $subtitle ?? 'Important update from the administration' }}</p>
                                </div>

                                <div style="padding: 0 50px 50px;">
                                    <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 32px; margin-bottom: 32px; border-left: 4px solid #e94560;">
                                        <p style="font-size: 16px; line-height: 1.8; color: rgba(255,255,255,0.85); margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($action_url))
                                    <div style="text-align: center; padding: 20px 0;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 48px; background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%); color: #ffffff; text-decoration: none; border-radius: 12px; font-family: 'Space Grotesk', sans-serif; font-size: 16px; font-weight: 600; box-shadow: 0 10px 30px rgba(233,69,96,0.4); transition: transform 0.2s;">
                                            {{ $action_text ?? 'Learn More' }}
                                            <span style="margin-left: 8px;">→</span>
                                        </a>
                                    </div>
                                    @endif

                                    @if(!empty($metadata))
                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 32px;">
                                        @foreach($metadata as $label => $value)
                                        <div style="flex: 1; min-width: 120px; background: rgba(255,255,255,0.03); border-radius: 12px; padding: 20px; text-align: center; border: 1px solid rgba(255,255,255,0.06);">
                                            <div style="font-size: 24px; font-weight: 700; color: #e94560; font-family: 'Space Grotesk', sans-serif;">{{ $value }}</div>
                                            <div style="font-size: 12px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">{{ $label }}</div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 0 20px; text-align: center;">
                            <p style="font-size: 13px; color: rgba(255,255,255,0.4); margin: 0 0 16px;">
                                Sent on {{ \Carbon\Carbon::now()->format('F j, Y \a\t g:i A') }}
                            </p>
                            <p style="font-size: 12px; color: rgba(255,255,255,0.3); margin: 0;">
                                © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
