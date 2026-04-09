<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%); min-height: 100vh; font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 20px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="width: 33.33%; vertical-align: middle;">
                                        <div style="height: 2px; background: rgba(255,255,255,0.3); border-radius: 2px;"></div>
                                    </td>
                                    <td align="center" style="padding: 0 20px; width: 33.33%;">
                                        <span style="font-size: 28px;">🎉</span>
                                    </td>
                                    <td style="width: 33.33%; vertical-align: middle;">
                                        <div style="height: 2px; background: rgba(255,255,255,0.3); border-radius: 2px;"></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: rgba(255,255,255,0.95); border-radius: 32px; overflow: hidden; box-shadow: 0 25px 80px rgba(0,0,0,0.15);">
                                
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 50px 40px; text-align: center; position: relative; overflow: hidden;">
                                    <div style="position: absolute; top: -20px; left: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    <div style="position: absolute; bottom: -30px; right: -10px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
                                    <div style="position: absolute; top: 50%; left: 10%; width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <div style="display: inline-flex; gap: 8px; margin-bottom: 20px;">
                                            <span style="font-size: 32px; animation: bounce 1s infinite;">⭐</span>
                                            <span style="font-size: 40px;">🏆</span>
                                            <span style="font-size: 32px; animation: bounce 1s infinite 0.2s;">⭐</span>
                                        </div>
                                        <h1 style="font-family: 'Outfit', sans-serif; font-size: 36px; font-weight: 800; color: #ffffff; margin: 0; line-height: 1.1;">{{ $title }}</h1>
                                        @if(!empty($badge))
                                        <div style="display: inline-block; margin-top: 16px; padding: 8px 20px; background: rgba(255,255,255,0.2); border-radius: 100px; backdrop-filter: blur(10px);">
                                            <span style="font-size: 13px; font-weight: 600; color: #ffffff; letter-spacing: 1px; text-transform: uppercase;">{{ $badge }}</span>
                                        </div>
                                        @endif
                                    </div>
                                </div>

                                <div style="padding: 50px 40px;">
                                    <div style="text-align: center; margin-bottom: 36px;">
                                        <p style="font-size: 18px; line-height: 1.7; color: #4a5568; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($achievement))
                                    <div style="background: linear-gradient(135deg, #f6f8fb 0%, #eef2f7 100%); border-radius: 20px; padding: 28px; margin-bottom: 32px; border: 1px solid rgba(102,126,234,0.1);">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="width: 60px; vertical-align: top;">
                                                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                                                        <span style="font-size: 24px;">{{ $achievement['icon'] ?? '🎖️' }}</span>
                                                    </div>
                                                </td>
                                                <td style="padding-left: 16px; vertical-align: middle;">
                                                    <div style="font-family: 'Outfit', sans-serif; font-size: 20px; font-weight: 700; color: #1a202c; margin-bottom: 4px;">{{ $achievement['title'] ?? 'Achievement Unlocked' }}</div>
                                                    <div style="font-size: 14px; color: #718096;">{{ $achievement['description'] ?? '' }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    @endif

                                    @if(!empty($stats))
                                    <div style="display: flex; gap: 16px; margin-bottom: 32px;">
                                        @foreach($stats as $stat)
                                        <div style="flex: 1; text-align: center; padding: 20px; background: linear-gradient(180deg, #fafbfc 0%, #f0f4f8 100%); border-radius: 16px; border: 1px solid rgba(102,126,234,0.08);">
                                            <div style="font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">{{ $stat['value'] }}</div>
                                            <div style="font-size: 12px; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">{{ $stat['label'] }}</div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 18px 48px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 14px; font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 600; box-shadow: 0 8px 25px rgba(102,126,234,0.4);">
                                            {{ $action_text ?? 'View Details' }}
                                            <span style="margin-left: 8px;">✨</span>
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #f8fafc; padding: 24px 40px; border-top: 1px solid rgba(0,0,0,0.05);">
                                    <p style="font-size: 14px; color: #718096; margin: 0; text-align: center;">
                                        🎊 Congratulations from the entire team at <strong style="color: #667eea;">{{ config('app.name') }}</strong>
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0; text-align: center;">
                            <p style="font-size: 12px; color: rgba(255,255,255,0.6); margin: 0;">
                                © {{ date('Y') }} {{ config('app.name') }}. Celebrating your success! 🌟
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
