<!DOCTYPE html>
<html>
<head>
    <title>{{ app(\App\Settings\SiteSettings::class)->getAppName() }} - Signup Verification</title>
</head>
<body>
    <div style="font-family: Arial, sans-serif; padding: 20px;">
        <h2>Verify Your Email</h2>
        <p>Your verification code for {{ app(\App\Settings\SiteSettings::class)->getAppName() }} signup is:</p>
        <div style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #4F46E5; margin: 20px 0;">
            {{ $otp }}
        </div>
        <p>This code will expire in 10 minutes.</p>
        <p>If you did not request this code, please ignore this email.</p>
    </div>
</body>
</html>
