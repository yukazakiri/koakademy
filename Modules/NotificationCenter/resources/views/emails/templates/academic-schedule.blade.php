<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #f3f4f6; font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f3f4f6;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width: 620px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 24px 0; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 12px; background: #ffffff; padding: 10px 24px; border-radius: 100px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                <span style="font-size: 20px;">🎓</span>
                                <span style="font-size: 12px; font-weight: 600; color: #6b7280; letter-spacing: 2px; text-transform: uppercase;">Academic Calendar Update</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.08);">
                                
                                <div style="background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); padding: 45px 40px; text-align: center;">
                                    <div style="width: 70px; height: 70px; margin: 0 auto 20px; background: rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <span style="font-size: 32px;">📅</span>
                                    </div>
                                    <h1 style="font-family: 'Merriweather', serif; font-size: 28px; font-weight: 900; color: #ffffff; margin: 0 0 10px;">{{ $title }}</h1>
                                    <div style="font-size: 15px; color: rgba(255,255,255,0.8);">School Year {{ $school_year ?? date('Y').'-'.(date('Y')+1) }}</div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="background: #eff6ff; border-radius: 16px; padding: 24px; margin-bottom: 28px; border: 1px solid #bfdbfe;">
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="width: 56px; height: 56px; background: #2563eb; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <span style="font-size: 28px;">{{ $period_icon ?? '📆' }}</span>
                                            </div>
                                            <div>
                                                <div style="font-size: 13px; font-weight: 600; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px;">{{ $period_label ?? 'Current Period' }}</div>
                                                <div style="font-size: 20px; font-weight: 700; color: #1e3a5f; margin-top: 4px;">{{ $period_name ?? 'First Semester' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 28px;">
                                        <p style="font-size: 16px; line-height: 1.8; color: #374151; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($important_dates))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid #e5e7eb;">📌 Important Dates</div>
                                        <div style="background: #f9fafb; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
                                            @foreach($important_dates as $date)
                                            <div style="padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <div style="font-size: 15px; font-weight: 600; color: #1f2937;">{{ $date['event'] }}</div>
                                                    @if(!empty($date['note']))
                                                    <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">{{ $date['note'] }}</div>
                                                    @endif
                                                </div>
                                                <div style="background: #dbeafe; color: #1d4ed8; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; white-space: nowrap;">
                                                    {{ $date['date'] }}
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($deadlines))
                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; padding: 24px; margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">⏰ Upcoming Deadlines</div>
                                        <div style="display: flex; flex-direction: column; gap: 10px;">
                                            @foreach($deadlines as $deadline)
                                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background: rgba(255,255,255,0.6); border-radius: 8px;">
                                                <span style="font-size: 14px; color: #78350f; font-weight: 500;">{{ $deadline['item'] }}</span>
                                                <span style="font-size: 13px; color: #92400e; font-weight: 700;">{{ $deadline['deadline'] }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($notes))
                                    <div style="background: #f0fdf4; border-radius: 12px; padding: 20px; margin-bottom: 28px; border-left: 4px solid #22c55e;">
                                        <div style="font-size: 13px; font-weight: 700; color: #166534; margin-bottom: 8px;">💡 Important Notes</div>
                                        <ul style="margin: 0; padding-left: 18px; color: #15803d;">
                                            @foreach($notes as $note)
                                            <li style="font-size: 14px; margin-bottom: 6px; line-height: 1.5;">{{ $note }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: #ffffff; text-decoration: none; border-radius: 10px; font-size: 15px; font-weight: 700; box-shadow: 0 4px 15px rgba(37,99,235,0.3);">
                                            {{ $action_text ?? 'View Full Schedule' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <div style="font-size: 14px; color: #6b7280;">
                                                    <strong style="color: #374151;">{{ $department ?? 'Registrar\'s Office' }}</strong>
                                                </div>
                                            </td>
                                            <td align="right" style="vertical-align: middle;">
                                                <div style="font-size: 13px; color: #9ca3af;">
                                                    Published: {{ $published_date ?? now()->format('F j, Y') }}
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
                            <p style="font-size: 13px; color: #6b7280; margin: 0;">
                                Stay updated with {{ config('app.name') }} academic calendar
                            </p>
                            <p style="font-size: 11px; color: #9ca3af; margin: 8px 0 0;">
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
