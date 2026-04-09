<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Work+Sans:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%); font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 20px 0; text-align: center;">
                            <div style="display: inline-block; padding: 12px 24px; background: #ffffff; border-radius: 100px; box-shadow: 0 4px 15px rgba(16,185,129,0.15);">
                                <span style="font-size: 13px; font-weight: 700; color: #059669; letter-spacing: 2px; text-transform: uppercase;">✅ Enrollment Status Update</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(16,185,129,0.12);">
                                
                                <div style="background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%); padding: 50px 40px; text-align: center; position: relative; overflow: hidden;">
                                    <div style="position: absolute; top: -20px; right: -20px; width: 120px; height: 120px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    <div style="position: absolute; bottom: -30px; left: 10px; width: 100px; height: 100px; background: rgba(255,255,255,0.08); border-radius: 50%;"></div>
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <div style="width: 80px; height: 80px; margin: 0 auto 20px; background: #ffffff; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                                            <span style="font-size: 40px;">{{ $status_icon ?? '🎉' }}</span>
                                        </div>
                                        <h1 style="font-size: 28px; font-weight: 800; color: #ffffff; margin: 0 0 8px;">{{ $title }}</h1>
                                        <p style="font-size: 16px; color: rgba(255,255,255,0.9); margin: 0;">{{ $subtitle ?? 'Enrollment Confirmation' }}</p>
                                    </div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    <div style="text-align: center; margin-bottom: 32px;">
                                        <p style="font-size: 17px; line-height: 1.7; color: #374151; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    <div style="background: #f0fdf4; border-radius: 16px; padding: 24px; margin-bottom: 28px; border: 1px solid #bbf7d0;">
                                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #bbf7d0;">
                                            <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #059669 0%, #10b981 100%); border-radius: 14px; display: flex; align-items: center; justify-content: center;">
                                                <span style="font-size: 26px;">👤</span>
                                            </div>
                                            <div>
                                                <div style="font-size: 18px; font-weight: 700; color: #064e3b;">{{ $student_name }}</div>
                                                <div style="font-size: 13px; color: #059669;">Student ID: {{ $student_id }}</div>
                                            </div>
                                        </div>
                                        
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            @if(!empty($course))
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">Course/Program</td>
                                                <td align="right" style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #064e3b;">{{ $course }}</td>
                                            </tr>
                                            @endif
                                            @if(!empty($year_level))
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">Year Level</td>
                                                <td align="right" style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #064e3b;">{{ $year_level }}</td>
                                            </tr>
                                            @endif
                                            @if(!empty($section))
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">Section</td>
                                                <td align="right" style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #064e3b;">{{ $section }}</td>
                                            </tr>
                                            @endif
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">School Year</td>
                                                <td align="right" style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #064e3b;">{{ $school_year }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; font-size: 14px; color: #6b7280;">Semester</td>
                                                <td align="right" style="padding: 8px 0; font-size: 14px; font-weight: 600; color: #064e3b;">{{ $semester }}</td>
                                            </tr>
                                        </table>
                                    </div>

                                    @if(!empty($subjects))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #059669; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">📚 Enrolled Subjects ({{ count($subjects) }} units)</div>
                                        <div style="background: #f9fafb; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
                                            <div style="background: #f3f4f6; padding: 12px 16px; display: flex; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase;">
                                                <div style="width: 35%;">Subject</div>
                                                <div style="width: 20%;">Schedule</div>
                                                <div style="width: 25%;">Room</div>
                                                <div style="width: 20%; text-align: center;">Units</div>
                                            </div>
                                            @foreach($subjects as $subject)
                                            <div style="padding: 12px 16px; display: flex; border-top: 1px solid #e5e7eb; font-size: 13px;">
                                                <div style="width: 35%; color: #1f2937; font-weight: 500;">{{ $subject['code'] }}</div>
                                                <div style="width: 20%; color: #6b7280;">{{ $subject['schedule'] }}</div>
                                                <div style="width: 25%; color: #6b7280;">{{ $subject['room'] }}</div>
                                                <div style="width: 20%; text-align: center; color: #059669; font-weight: 600;">{{ $subject['units'] }}</div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($next_steps))
                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 16px; padding: 24px; margin-bottom: 28px;">
                                        <div style="font-size: 14px; font-weight: 700; color: #92400e; margin-bottom: 16px;">📋 Next Steps</div>
                                        <div style="display: flex; flex-direction: column; gap: 12px;">
                                            @foreach($next_steps as $step)
                                            <div style="display: flex; align-items: flex-start; gap: 12px;">
                                                <div style="width: 24px; height: 24px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <span style="font-size: 12px; font-weight: 700; color: #ffffff;">{{ $loop->iteration }}</span>
                                                </div>
                                                <span style="font-size: 14px; color: #78350f;">{{ $step }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($assessment_url))
                                    <div style="background: #eff6ff; border-radius: 12px; padding: 20px; margin-bottom: 28px; text-align: center; border: 1px solid #bfdbfe;">
                                        <div style="font-size: 14px; color: #1e40af; margin-bottom: 12px;">📄 Your Assessment Form is ready for download</div>
                                        <a href="{{ $assessment_url }}" style="display: inline-block; padding: 12px 24px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600;">
                                            Download Assessment Form
                                        </a>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 18px 50px; background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 16px; font-weight: 700; box-shadow: 0 6px 20px rgba(16,185,129,0.35);">
                                            {{ $action_text ?? 'View My Enrollment' }}
                                        </a>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #f0fdf4; padding: 20px; border-top: 1px solid #bbf7d0;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <div style="font-size: 13px; color: #059669;">
                                                    Processed by: <strong>{{ $processed_by ?? 'Enrollment Office' }}</strong>
                                                </div>
                                            </td>
                                            <td align="right">
                                                <div style="font-size: 12px; color: #6b7280;">
                                                    {{ $processed_date ?? now()->format('F j, Y \a\t g:i A') }}
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
                            <p style="font-size: 12px; color: #6b7280; margin: 0;">
                                Welcome to {{ config('app.name') }}! We're excited to have you.
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
