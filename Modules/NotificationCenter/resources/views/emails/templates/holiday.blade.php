<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 50%, #fcd34d 100%); font-family: 'Nunito', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 24px 0; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 12px;">
                                <span style="font-size: 32px;">🌴</span>
                                <span style="font-size: 32px;">🎉</span>
                                <span style="font-size: 32px;">☀️</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 28px; overflow: hidden; box-shadow: 0 15px 50px rgba(245,158,11,0.2);">
                                
                                <div style="background: linear-gradient(135deg, #f59e0b 0%, #f97316 50%, #fb923c 100%); padding: 50px 40px; text-align: center; position: relative;">
                                    <div style="position: absolute; top: 20px; left: 20px; font-size: 60px; opacity: 0.2;">🏖️</div>
                                    <div style="position: absolute; bottom: 10px; right: 30px; font-size: 50px; opacity: 0.2;">⛱️</div>
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <h1 style="font-size: 32px; font-weight: 800; color: #ffffff; margin: 0 0 8px;">{{ $title }}</h1>
                                        <p style="font-size: 16px; color: rgba(255,255,255,0.9); margin: 0;">{{ $subtitle ?? 'No Classes & Office Closure' }}</p>
                                    </div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="text-align: center; margin-bottom: 32px;">
                                        <div style="display: inline-flex; gap: 20px; flex-wrap: wrap; justify-content: center;">
                                            <div style="background: #fffbeb; border-radius: 16px; padding: 20px 28px; text-align: center; border: 2px solid #fcd34d;">
                                                <div style="font-size: 28px; margin-bottom: 8px;">📅</div>
                                                <div style="font-size: 13px; color: #92400e; font-weight: 600;">Date</div>
                                                <div style="font-size: 16px; font-weight: 700; color: #78350f;">{{ $holiday_date }}</div>
                                            </div>
                                            <div style="background: #fffbeb; border-radius: 16px; padding: 20px 28px; text-align: center; border: 2px solid #fcd34d;">
                                                <div style="font-size: 28px; margin-bottom: 8px;">🗓️</div>
                                                <div style="font-size: 13px; color: #92400e; font-weight: 600;">Day</div>
                                                <div style="font-size: 16px; font-weight: 700; color: #78350f;">{{ $holiday_day }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-radius: 16px; padding: 24px; margin-bottom: 28px; border-left: 4px solid #f59e0b;">
                                        <p style="font-size: 16px; line-height: 1.7; color: #78350f; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($holiday_type))
                                    <div style="background: #f0fdf4; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                                        <span style="font-size: 24px;">{{ $holiday_type == 'regular' ? '🏛️' : '🏝️' }}</span>
                                        <div>
                                            <div style="font-size: 14px; font-weight: 700; color: #166534;">{{ $holiday_type == 'regular' ? 'Regular Holiday' : 'Special Non-Working Holiday' }}</div>
                                            <div style="font-size: 13px; color: #15803d;">{{ $holiday_type == 'regular' ? 'Full pay for work done + 100% premium' : 'Additional 30% pay for work done' }}</div>
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($duration))
                                    <div style="background: #eff6ff; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid #bfdbfe;">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <div>
                                                <div style="font-size: 13px; color: #1e40af; font-weight: 600;">Break Duration</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #1e3a8a;">{{ $duration }}</div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 13px; color: #1e40af; font-weight: 600;">Classes Resume</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #1e3a8a;">{{ $resume_date }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($activities))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #d97706; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">🎭 Special Activities</div>
                                        <div style="display: flex; flex-direction: column; gap: 10px;">
                                            @foreach($activities as $activity)
                                            <div style="background: #fffbeb; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 12px; border: 1px solid #fde68a;">
                                                <span style="font-size: 20px;">{{ $activity['icon'] ?? '✨' }}</span>
                                                <div style="flex: 1;">
                                                    <div style="font-size: 14px; font-weight: 600; color: #78350f;">{{ $activity['name'] }}</div>
                                                    @if(!empty($activity['time']))
                                                    <div style="font-size: 12px; color: #92400e;">{{ $activity['time'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($reminders))
                                    <div style="background: #fef2f2; border-radius: 12px; padding: 20px; margin-bottom: 24px; border-left: 4px solid #ef4444;">
                                        <div style="font-size: 13px; font-weight: 700; color: #991b1b; margin-bottom: 12px;">📌 Important Reminders</div>
                                        <ul style="margin: 0; padding-left: 18px; color: #7f1d1d;">
                                            @foreach($reminders as $reminder)
                                            <li style="font-size: 14px; margin-bottom: 6px;">{{ $reminder }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-size: 15px; font-weight: 700; box-shadow: 0 6px 20px rgba(245,158,11,0.4);">
                                            {{ $action_text ?? 'View School Calendar' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #fffbeb; padding: 20px; border-top: 2px dashed #fde68a;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="text-align: center;">
                                                <div style="font-size: 14px; color: #92400e;">
                                                    🎊 Enjoy your break! Stay safe and have fun!
                                                </div>
                                                <div style="font-size: 12px; color: #b45309; margin-top: 8px;">
                                                    - {{ config('app.name') }} Administration
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0; text-align: center;">
                            <p style="font-size: 12px; color: #92400e; margin: 0;">
                                {{ config('app.name') }} • Academic Calendar
                            </p>
                            <p style="font-size: 11px; color: #b45309; margin: 8px 0 0;">
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
