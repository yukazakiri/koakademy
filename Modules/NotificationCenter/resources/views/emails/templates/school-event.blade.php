<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4c1d95 100%); font-family: 'Montserrat', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width: 640px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 30px 0; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 20px;">
                                @for($i = 0; $i < 3; $i++)
                                <div style="width: 8px; height: 8px; background: rgba(255,255,255,0.4); border-radius: 50%;"></div>
                                @endfor
                                <span style="font-size: 14px; font-weight: 700; color: #fbbf24; letter-spacing: 4px; text-transform: uppercase;">{{ $event_type ?? 'School Event' }}</span>
                                @for($i = 0; $i < 3; $i++)
                                <div style="width: 8px; height: 8px; background: rgba(255,255,255,0.4); border-radius: 50%;"></div>
                                @endfor
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: rgba(255,255,255,0.05); border-radius: 28px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px);">
                                
                                <div style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 50%, #ec4899 100%); padding: 50px 40px; text-align: center; position: relative; overflow: hidden;">
                                    <div style="position: absolute; top: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    <div style="position: absolute; bottom: -40px; right: -20px; width: 200px; height: 200px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                                    
                                    @if(!empty($event_logo))
                                    <div style="margin-bottom: 20px;">
                                        <img src="{{ $event_logo }}" alt="{{ $title }}" style="height: 60px; width: auto;">
                                    </div>
                                    @endif
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <div style="font-family: 'Bebas Neue', sans-serif; font-size: 56px; line-height: 1; color: #ffffff; margin-bottom: 12px; text-shadow: 0 4px 20px rgba(0,0,0,0.3);">{{ $title }}</div>
                                        @if(!empty($tagline))
                                        <div style="font-size: 18px; color: rgba(255,255,255,0.9); font-weight: 500;">{{ $tagline }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="display: flex; gap: 16px; margin-bottom: 32px; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 140px; background: rgba(124,58,237,0.2); border-radius: 16px; padding: 20px; text-align: center; border: 1px solid rgba(124,58,237,0.3);">
                                            <div style="font-size: 28px; margin-bottom: 8px;">📅</div>
                                            <div style="font-size: 14px; font-weight: 700; color: #c4b5fd;">Date</div>
                                            <div style="font-size: 16px; font-weight: 600; color: #ffffff;">{{ $event_date }}</div>
                                        </div>
                                        <div style="flex: 1; min-width: 140px; background: rgba(236,72,153,0.2); border-radius: 16px; padding: 20px; text-align: center; border: 1px solid rgba(236,72,153,0.3);">
                                            <div style="font-size: 28px; margin-bottom: 8px;">🕐</div>
                                            <div style="font-size: 14px; font-weight: 700; color: #f9a8d4;">Time</div>
                                            <div style="font-size: 16px; font-weight: 600; color: #ffffff;">{{ $event_time }}</div>
                                        </div>
                                        <div style="flex: 1; min-width: 140px; background: rgba(34,197,94,0.2); border-radius: 16px; padding: 20px; text-align: center; border: 1px solid rgba(34,197,94,0.3);">
                                            <div style="font-size: 28px; margin-bottom: 8px;">📍</div>
                                            <div style="font-size: 14px; font-weight: 700; color: #86efac;">Venue</div>
                                            <div style="font-size: 16px; font-weight: 600; color: #ffffff;">{{ $venue }}</div>
                                        </div>
                                    </div>

                                    <div style="background: rgba(255,255,255,0.05); border-radius: 16px; padding: 24px; margin-bottom: 28px;">
                                        <p style="font-size: 16px; line-height: 1.8; color: rgba(255,255,255,0.85); margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($activities))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">🎯 Event Highlights</div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                            @foreach($activities as $activity)
                                            <div style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 100px; padding: 10px 20px;">
                                                <span style="font-size: 14px; color: #ffffff;">{{ $activity }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($schedule))
                                    <div style="background: rgba(0,0,0,0.2); border-radius: 16px; overflow: hidden; margin-bottom: 28px;">
                                        <div style="background: rgba(124,58,237,0.3); padding: 16px 24px;">
                                            <span style="font-size: 14px; font-weight: 700; color: #ffffff;">📋 Event Schedule</span>
                                        </div>
                                        <div style="padding: 16px;">
                                            @foreach($schedule as $item)
                                            <div style="display: flex; padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                                <div style="width: 100px; font-family: monospace; font-size: 14px; color: #a78bfa;">{{ $item['time'] }}</div>
                                                <div style="font-size: 14px; color: #ffffff;">{{ $item['activity'] }}</div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($participants))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #a78bfa; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">👥 Who Can Participate</div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                            @foreach($participants as $participant)
                                            <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); border-radius: 10px; padding: 10px 16px;">
                                                <span style="font-size: 16px;">{{ $participant['icon'] ?? '👤' }}</span>
                                                <span style="font-size: 14px; color: rgba(255,255,255,0.9);">{{ $participant['name'] }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($requirements))
                                    <div style="background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); border-radius: 16px; padding: 20px; margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">📝 Requirements / What to Bring</div>
                                        <ul style="margin: 0; padding-left: 20px; color: rgba(255,255,255,0.85);">
                                            @foreach($requirements as $requirement)
                                            <li style="font-size: 14px; margin-bottom: 6px;">{{ $requirement }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 18px 50px; background: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%); color: #ffffff; text-decoration: none; border-radius: 50px; font-size: 16px; font-weight: 700; box-shadow: 0 8px 30px rgba(124,58,237,0.4);">
                                            {{ $action_text ?? 'Register Now' }}
                                            <span style="margin-left: 8px;">→</span>
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: rgba(0,0,0,0.2); padding: 24px 40px; border-top: 1px solid rgba(255,255,255,0.1);">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td style="vertical-align: middle;">
                                                <div style="font-size: 14px; color: rgba(255,255,255,0.6);">
                                                    Organized by: <strong style="color: #ffffff;">{{ $organizer ?? 'Student Affairs Office' }}</strong>
                                                </div>
                                            </td>
                                            <td align="right" style="vertical-align: middle;">
                                                @if(!empty($contact_info))
                                                <div style="font-size: 13px; color: rgba(255,255,255,0.5);">
                                                    Questions? {{ $contact_info }}
                                                </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0; text-align: center;">
                            <p style="font-size: 13px; color: rgba(255,255,255,0.4); margin: 0;">
                                {{ config('app.name') }} • Don't miss out on the excitement!
                            </p>
                            <p style="font-size: 11px; color: rgba(255,255,255,0.3); margin: 8px 0 0;">
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
