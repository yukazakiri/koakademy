<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%); font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 20px 0; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #ffffff; border-radius: 100px; box-shadow: 0 2px 10px rgba(59,130,246,0.1);">
                                <span style="font-size: 18px;">📊</span>
                                <span style="font-size: 12px; font-weight: 700; color: #2563eb; letter-spacing: 2px; text-transform: uppercase;">Grade Release</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(59,130,246,0.12);">
                                
                                <div style="background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 50%, #60a5fa 100%); padding: 45px 40px; text-align: center; position: relative;">
                                    <div style="position: absolute; top: 10px; right: 20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <div style="width: 72px; height: 72px; margin: 0 auto 16px; background: rgba(255,255,255,0.2); border-radius: 18px; display: flex; align-items: center; justify-content: center;">
                                            <span style="font-size: 36px;">📝</span>
                                        </div>
                                        <h1 style="font-family: 'Sora', sans-serif; font-size: 28px; font-weight: 800; color: #ffffff; margin: 0 0 8px;">{{ $title }}</h1>
                                        <p style="font-size: 15px; color: rgba(255,255,255,0.85); margin: 0;">{{ $semester }} • School Year {{ $school_year }}</p>
                                    </div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="text-align: center; margin-bottom: 28px;">
                                        <div style="font-size: 16px; font-weight: 700; color: #1f2937; margin-bottom: 8px;">{{ $student_name }}</div>
                                        <div style="font-size: 13px; color: #6b7280;">Student ID: {{ $student_id }}</div>
                                    </div>

                                    <div style="background: #f8fafc; border-radius: 16px; padding: 24px; margin-bottom: 28px; border: 1px solid #e2e8f0;">
                                        <div style="display: flex; justify-content: space-around; text-align: center;">
                                            <div>
                                                <div style="font-size: 13px; color: #64748b; margin-bottom: 4px;">Subjects Passed</div>
                                                <div style="font-size: 28px; font-weight: 800; color: #059669;">{{ $subjects_passed ?? '-' }}</div>
                                            </div>
                                            <div style="width: 1px; background: #e2e8f0;"></div>
                                            <div>
                                                <div style="font-size: 13px; color: #64748b; margin-bottom: 4px;">Units Earned</div>
                                                <div style="font-size: 28px; font-weight: 800; color: #2563eb;">{{ $units_earned ?? '-' }}</div>
                                            </div>
                                            <div style="width: 1px; background: #e2e8f0;"></div>
                                            <div>
                                                <div style="font-size: 13px; color: #64748b; margin-bottom: 4px;">GWA</div>
                                                <div style="font-size: 28px; font-weight: 800; color: #7c3aed;">{{ $gwa ?? '-' }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(!empty($grades))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">📋 Grade Details</div>
                                        <div style="background: #f8fafc; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
                                            <div style="background: #f1f5f9; padding: 12px 16px; display: flex; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <div style="width: 40%;">Subject</div>
                                                <div style="width: 20%; text-align: center;">Units</div>
                                                <div style="width: 20%; text-align: center;">Grade</div>
                                                <div style="width: 20%; text-align: center;">Remarks</div>
                                            </div>
                                            @foreach($grades as $grade)
                                            <div style="padding: 14px 16px; display: flex; border-top: 1px solid #e2e8f0; font-size: 13px; align-items: center;">
                                                <div style="width: 40%;">
                                                    <div style="font-weight: 600; color: #1e293b;">{{ $grade['code'] }}</div>
                                                    <div style="font-size: 11px; color: #64748b;">{{ $grade['description'] }}</div>
                                                </div>
                                                <div style="width: 20%; text-align: center; color: #475569;">{{ $grade['units'] }}</div>
                                                <div style="width: 20%; text-align: center;">
                                                    <span style="display: inline-block; padding: 4px 12px; background: {{ $grade['grade'] <= 2.0 ? '#dcfce7' : ($grade['grade'] <= 3.0 ? '#fef3c7' : '#fee2e2') }}; color: {{ $grade['grade'] <= 2.0 ? '#166534' : ($grade['grade'] <= 3.0 ? '#92400e' : '#991b1b') }}; border-radius: 6px; font-weight: 700;">
                                                        {{ $grade['grade'] }}
                                                    </span>
                                                </div>
                                                <div style="width: 20%; text-align: center; font-size: 12px; color: {{ $grade['remarks'] == 'PASSED' ? '#059669' : '#dc2626' }}; font-weight: 600;">{{ $grade['remarks'] }}</div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($academic_standing))
                                    <div style="background: {{ $academic_standing['type'] == 'dean_lister' ? '#fef3c7' : ($academic_standing['type'] == 'good' ? '#dcfce7' : '#fee2e2') }}; border-radius: 12px; padding: 16px 20px; margin-bottom: 28px; display: flex; align-items: center; gap: 12px;">
                                        <span style="font-size: 24px;">{{ $academic_standing['icon'] ?? '🏆' }}</span>
                                        <div>
                                            <div style="font-size: 14px; font-weight: 700; color: {{ $academic_standing['type'] == 'dean_lister' ? '#92400e' : ($academic_standing['type'] == 'good' ? '#166534' : '#991b1b') }};">{{ $academic_standing['title'] }}</div>
                                            <div style="font-size: 12px; color: {{ $academic_standing['type'] == 'dean_lister' ? '#a16207' : ($academic_standing['type'] == 'good' ? '#15803d' : '#b91c1c') }};">{{ $academic_standing['message'] }}</div>
                                        </div>
                                    </div>
                                    @endif

                                    <div style="font-size: 15px; line-height: 1.7; color: #374151; margin-bottom: 28px;">
                                        {!! $content !!}
                                    </div>

                                    @if(!empty($action_url))
                                    <div style="text-align: center;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 16px 40px; background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 15px; font-weight: 700; box-shadow: 0 4px 15px rgba(59,130,246,0.35);">
                                            {{ $action_text ?? 'View Full Transcript' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #f8fafc; padding: 20px; border-top: 1px solid #e2e8f0;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <div style="font-size: 13px; color: #64748b;">
                                                    Released by: <strong style="color: #1e293b;">{{ $released_by ?? 'Registrar\'s Office' }}</strong>
                                                </div>
                                            </td>
                                            <td align="right">
                                                <div style="font-size: 12px; color: #94a3b8;">
                                                    {{ $released_at ?? now()->format('F j, Y') }}
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
                            <p style="font-size: 12px; color: #64748b; margin: 0;">
                                Keep up the good work! - {{ config('app.name') }}
                            </p>
                            <p style="font-size: 11px; color: #94a3b8; margin: 8px 0 0;">
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
