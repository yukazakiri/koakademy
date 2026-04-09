<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: #f8fafc; font-family: 'Manrope', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 24px 0;">
                            <div style="display: inline-block; padding: 12px 24px; background: #fef3c7; border-radius: 100px; border: 2px solid #f59e0b;">
                                <span style="font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 600; color: #92400e; letter-spacing: 2px;">⚠️ CLASS SUSPENSION NOTICE</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e5e7eb;">
                                
                                <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); padding: 40px; text-align: center; border-bottom: 4px solid #f59e0b;">
                                    <div style="width: 72px; height: 72px; margin: 0 auto 20px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
                                        <span style="font-size: 36px;">🌧️</span>
                                    </div>
                                    <h1 style="font-size: 28px; font-weight: 800; color: #78350f; margin: 0 0 8px;">{{ $title }}</h1>
                                    <p style="font-size: 15px; color: #92400e; margin: 0;">Official Class Suspension Advisory</p>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="background: #fffbeb; border: 2px dashed #f59e0b; border-radius: 16px; padding: 24px; margin-bottom: 28px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="width: 50%;">
                                                    <div style="font-size: 12px; color: #92400e; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Date of Suspension</div>
                                                    <div style="font-family: 'DM Mono', monospace; font-size: 20px; font-weight: 700; color: #78350f;">{{ $suspension_date }}</div>
                                                </td>
                                                <td style="width: 50%;">
                                                    <div style="font-size: 12px; color: #92400e; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Affected Levels</div>
                                                    <div style="font-size: 20px; font-weight: 700; color: #78350f;">{{ $affected_levels ?? 'All Levels' }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Reason for Suspension</div>
                                        <div style="background: #f9fafb; border-radius: 12px; padding: 20px;">
                                            <p style="font-size: 16px; line-height: 1.7; color: #374151; margin: 0;">
                                                {!! $content !!}
                                            </p>
                                        </div>
                                    </div>

                                    @if(!empty($reason_details))
                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%); border-radius: 16px; padding: 24px; margin-bottom: 28px;">
                                        <div style="display: flex; align-items: flex-start; gap: 16px;">
                                            <div style="width: 48px; height: 48px; background: #f59e0b; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                <span style="font-size: 24px;">{{ $reason_icon ?? '⚡' }}</span>
                                            </div>
                                            <div>
                                                <div style="font-size: 16px; font-weight: 700; color: #78350f; margin-bottom: 4px;">{{ $reason_title ?? 'Weather Advisory' }}</div>
                                                <div style="font-size: 14px; color: #92400e; line-height: 1.6;">{{ $reason_details }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($resumption_info))
                                    <div style="background: #ecfdf5; border-radius: 16px; padding: 20px; margin-bottom: 28px; border-left: 4px solid #10b981;">
                                        <div style="font-size: 13px; font-weight: 700; color: #047857; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">📚 Classes Resume</div>
                                        <div style="font-size: 16px; color: #065f46;">{{ $resumption_info }}</div>
                                    </div>
                                    @endif

                                    @if(!empty($instructions))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Important Instructions</div>
                                        <div style="background: #f9fafb; border-radius: 12px; overflow: hidden;">
                                            @foreach($instructions as $instruction)
                                            <div style="padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px;">
                                                <div style="width: 24px; height: 24px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <span style="font-size: 12px;">{{ $loop->iteration }}</span>
                                                </div>
                                                <span style="font-size: 15px; color: #374151;">{{ $instruction }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 15px; font-weight: 700; box-shadow: 0 4px 15px rgba(245,158,11,0.4);">
                                            {{ $action_text ?? 'View Full Announcement' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <div style="font-size: 14px; color: #6b7280;">
                                                    <strong style="color: #374151;">Issued by:</strong> {{ $issued_by ?? 'School Administration' }}
                                                </div>
                                                @if(!empty($issued_at))
                                                <div style="font-size: 13px; color: #9ca3af; margin-top: 4px;">
                                                    {{ $issued_at }}
                                                </div>
                                                @endif
                                            </td>
                                            <td align="right">
                                                <span style="font-family: 'DM Mono', monospace; font-size: 11px; color: #9ca3af; background: #f3f4f6; padding: 6px 12px; border-radius: 6px;">
                                                    Ref: {{ $reference_no ?? date('Ymd-His') }}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 0; text-align: center;">
                            <p style="font-size: 13px; color: #9ca3af; margin: 0;">
                                Stay safe! Follow official announcements from {{ config('app.name') }}
                            </p>
                            <p style="font-size: 11px; color: #d1d5db; margin: 8px 0 0;">
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
