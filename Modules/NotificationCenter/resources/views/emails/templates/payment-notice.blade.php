<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto+Mono:wght@500;600;700&display=swap" rel="stylesheet">
</head>
<body style="margin: 0; padding: 0; background: linear-gradient(180deg, #faf5ff 0%, #f3e8ff 100%); font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: transparent;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; width: 100%;">

                    <tr>
                        <td style="padding: 0 0 20px 0; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #ffffff; border-radius: 100px; box-shadow: 0 2px 10px rgba(168,85,247,0.1);">
                                <span style="font-size: 18px;">💳</span>
                                <span style="font-size: 12px; font-weight: 700; color: #7c3aed; letter-spacing: 2px; text-transform: uppercase;">Payment Notice</span>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <div style="background: #ffffff; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 40px rgba(168,85,247,0.15); border: 1px solid #e9d5ff;">
                                
                                <div style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); padding: 45px 40px; text-align: center; position: relative;">
                                    <div style="position: absolute; top: 10px; right: 20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                    <div style="position: absolute; bottom: -20px; left: 20px; width: 60px; height: 60px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
                                    
                                    <div style="position: relative; z-index: 1;">
                                        <div style="width: 64px; height: 64px; margin: 0 auto 16px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                            <span style="font-size: 32px;">{{ $payment_icon ?? '💰' }}</span>
                                        </div>
                                        <h1 style="font-size: 26px; font-weight: 800; color: #ffffff; margin: 0 0 8px;">{{ $title }}</h1>
                                        <p style="font-size: 15px; color: rgba(255,255,255,0.8); margin: 0;">{{ $subtitle ?? 'Fee Payment Information' }}</p>
                                    </div>
                                </div>

                                <div style="padding: 40px;">
                                    
                                    @if(!empty($student_info))
                                    <div style="background: #faf5ff; border-radius: 12px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                                        <div style="width: 50px; height: 50px; background: #7c3aed; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                            <span style="font-size: 24px;">👤</span>
                                        </div>
                                        <div>
                                            <div style="font-size: 16px; font-weight: 700; color: #1f2937;">{{ $student_info['name'] }}</div>
                                            <div style="font-size: 13px; color: #6b7280;">ID: {{ $student_info['id_number'] }} • {{ $student_info['course'] ?? '' }}</div>
                                        </div>
                                    </div>
                                    @endif

                                    <div style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-radius: 16px; padding: 24px; margin-bottom: 28px; border: 1px solid #e9d5ff;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px dashed #d8b4fe;">
                                                    <div style="font-size: 13px; color: #7c3aed; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Amount Due</div>
                                                </td>
                                                <td align="right" style="padding-bottom: 16px; border-bottom: 1px dashed #d8b4fe;">
                                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 32px; font-weight: 700; color: #7c3aed;">{{ $currency ?? '₱' }}{{ number_format($amount, 2) }}</div>
                                                </td>
                                            </tr>
                                            @if(!empty($balance_breakdown))
                                            @foreach($balance_breakdown as $item)
                                            <tr>
                                                <td style="padding-top: 12px;">
                                                    <div style="font-size: 14px; color: #6b7280;">{{ $item['label'] }}</div>
                                                </td>
                                                <td align="right" style="padding-top: 12px;">
                                                    <div style="font-family: 'Roboto Mono', monospace; font-size: 14px; color: #374151; font-weight: 600;">{{ $currency ?? '₱' }}{{ number_format($item['amount'], 2) }}</div>
                                                </td>
                                            </tr>
                                            @endforeach
                                            @endif
                                        </table>
                                    </div>

                                    @if(!empty($due_date))
                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; padding: 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                                        <div style="width: 48px; height: 48px; background: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <span style="font-size: 22px;">⏰</span>
                                        </div>
                                        <div>
                                            <div style="font-size: 13px; font-weight: 600; color: #92400e; text-transform: uppercase;">Payment Due Date</div>
                                            <div style="font-size: 18px; font-weight: 700; color: #78350f;">{{ $due_date }}</div>
                                        </div>
                                        @if(!empty($days_remaining))
                                        <div style="margin-left: auto; background: rgba(245,158,11,0.2); padding: 6px 12px; border-radius: 6px;">
                                            <span style="font-size: 13px; font-weight: 700; color: #92400e;">{{ $days_remaining }} days left</span>
                                        </div>
                                        @endif
                                    </div>
                                    @endif

                                    <div style="margin-bottom: 28px;">
                                        <p style="font-size: 15px; line-height: 1.7; color: #374151; margin: 0;">
                                            {!! $content !!}
                                        </p>
                                    </div>

                                    @if(!empty($payment_methods))
                                    <div style="margin-bottom: 28px;">
                                        <div style="font-size: 13px; font-weight: 700; color: #7c3aed; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">💳 Payment Methods</div>
                                        <div style="display: flex; flex-direction: column; gap: 12px;">
                                            @foreach($payment_methods as $method)
                                            <div style="background: #f9fafb; border-radius: 10px; padding: 16px; display: flex; align-items: center; gap: 12px; border: 1px solid #e5e7eb;">
                                                <div style="width: 40px; height: 40px; background: #e9d5ff; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                    <span style="font-size: 20px;">{{ $method['icon'] ?? '🏦' }}</span>
                                                </div>
                                                <div style="flex: 1;">
                                                    <div style="font-size: 14px; font-weight: 600; color: #1f2937;">{{ $method['name'] }}</div>
                                                    @if(!empty($method['details']))
                                                    <div style="font-size: 12px; color: #6b7280;">{{ $method['details'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($penalty_info))
                                    <div style="background: #fef2f2; border-radius: 12px; padding: 16px 20px; margin-bottom: 28px; border-left: 4px solid #ef4444;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-size: 20px;">⚠️</span>
                                            <div style="font-size: 14px; color: #991b1b;">
                                                <strong>Late Payment Penalty:</strong> {{ $penalty_info }}
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if(!empty($action_url))
                                    <div style="text-align: center; margin-top: 32px;">
                                        <a href="{{ $action_url }}" style="display: inline-block; padding: 18px 48px; background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: #ffffff; text-decoration: none; border-radius: 12px; font-size: 16px; font-weight: 700; box-shadow: 0 6px 20px rgba(124,58,237,0.35);">
                                            {{ $action_text ?? 'Pay Now' }}
                                        </a>
                                        <p style="font-size: 12px; color: #9ca3af; margin: 12px 0 0;">
                                            Secure payment gateway • Instant confirmation
                                        </p>
                                    </div>
                                    @endif
                                </div>

                                <div style="background: #faf5ff; padding: 20px; border-top: 1px solid #e9d5ff;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                        <tr>
                                            <td>
                                                <div style="font-size: 13px; color: #6b7280;">
                                                    Questions? Contact <strong style="color: #7c3aed;">{{ $contact ?? 'Cashier\'s Office' }}</strong>
                                                </div>
                                            </td>
                                            <td align="right">
                                                <div style="font-size: 12px; color: #9ca3af;">
                                                    Reference: {{ $reference_no ?? strtoupper(uniqid('PAY')) }}
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
                            <p style="font-size: 12px; color: #9ca3af; margin: 0;">
                                {{ config('app.name') }} • Finance Department
                            </p>
                            <p style="font-size: 11px; color: #c4b5fd; margin: 8px 0 0;">
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
