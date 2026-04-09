<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #0d0d0d; font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #0d0d0d;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 20px 0;">
                            <div style="background: linear-gradient(90deg, #f59e0b 0%, #ef4444 100%); height: 4px; border-radius: 2px;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 0;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="background: #1a1a1a; border: 1px solid #333; border-radius: 8px; padding: 12px 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="vertical-align: middle;">
                                                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700; color: #f59e0b; letter-spacing: 2px;">⚠ ALERT</span>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    @if(!empty($priority))
                                                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #ef4444; background: rgba(239,68,68,0.1); padding: 4px 10px; border-radius: 4px;">{{ strtoupper($priority) }} PRIORITY</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #111; border: 1px solid #333; border-radius: 12px; overflow: hidden;">
                                
                                <div style="background: linear-gradient(135deg, #1f1f1f 0%, #0a0a0a 100%); padding: 40px; border-bottom: 1px solid #333;">
                                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 16px;">
                                            <span style="font-size: 24px;">⚠️</span>
                                        </div>
                                        <div>
                                            <h1 style="font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 700; color: #ffffff; margin: 0;">{{ $title }}</h1>
                                        </div>
                                    </div>
                                    @if(!empty($timestamp))
                                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #666;">
                                        <span style="color: #f59e0b;">●</span> {{ $timestamp }}
                                    </div>
                                    @endif
                                </div>

                                <div style="padding: 40px;">
                                    
                                    @if(!empty($alert_code))
                                    <div style="background: #0a0a0a; border: 1px solid #f59e0b; border-radius: 6px; padding: 16px; margin-bottom: 24px;">
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #f59e0b; margin-bottom: 4px;">ALERT CODE</div>
                                        <div style="font-family: 'JetBrains Mono', monospace; font-size: 18px; color: #ffffff; letter-spacing: 1px;">{{ $alert_code }}</div>
                                    </div>
                                    @endif

                                    <div style="margin-bottom: 30px;">
                                        <p style="font-size: 16px; line-height: 1.7; color: #a0a0a0; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($action_items))
                                    <div style="background: #0f0f0f; border-radius: 8px; padding: 24px; margin-bottom: 30px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #f59e0b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">Required Actions</div>
                                        @foreach($action_items as $index => $item)
                                        <div style="display: flex; margin-bottom: {{ $loop->last ? '0' : '12px' }};">
                                            <div style="width: 24px; height: 24px; background: #1a1a1a; border: 1px solid #333; border-radius: 4px; margin-right: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #f59e0b;">{{ $index + 1 }}</span>
                                            </div>
                                            <div style="font-size: 14px; color: #d0d0d0; padding-top: 2px;">{{ $item }}</div>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif

                                    @if(!empty($deadline))
                                    <div style="background: linear-gradient(90deg, rgba(239,68,68,0.1) 0%, rgba(245,158,11,0.1) 100%); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="vertical-align: middle;">
                                                    <div style="font-size: 11px; color: #ef4444; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Deadline</div>
                                                    <div style="font-family: 'JetBrains Mono', monospace; font-size: 18px; color: #ffffff;">{{ $deadline }}</div>
                                                </td>
                                                <td align="right" style="vertical-align: middle;">
                                                    <div style="font-size: 12px; color: #ef4444;">
                                                        @php
                                                            $deadlineTime = \Carbon\Carbon::parse($deadline);
                                                            $remaining = $deadlineTime->diffForHumans(now(), ['parts' => 2]);
                                                        @endphp
                                                        {{ $remaining }} remaining
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(90deg, #f59e0b 0%, #ef4444 100%); color: #000000; text-decoration: none; font-family: 'JetBrains Mono', monospace; font-size: 14px; font-weight: 700; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px;">
                                            {{ $action_text ?? 'Take Action Now' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #0a0a0a; padding: 20px; border-top: 1px solid #333;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <span style="font-size: 11px; color: #666;">Do not reply to this automated alert.</span>
                                            </td>
                                            <td align="right">
                                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 10px; color: #444;">ID: {{ $alert_id ?? uniqid() }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0;">
                            <p style="font-size: 11px; color: #444; text-align: center; margin: 0;">
                                {{ config('app.name') }} Alert System • {{ config('app.url') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
