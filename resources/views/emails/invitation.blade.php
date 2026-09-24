<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invitation</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; padding: 24px;">
    <h1 style="font-size: 18px; margin-bottom: 12px;">You are invited</h1>
    <p style="font-size: 14px; line-height: 1.6;">
        You have been invited to join <strong>{{ $companyName }}</strong> as
        <strong>{{ $role }}</strong> on {{ config('app.name') }}.
    </p>
    <p style="font-size: 14px; line-height: 1.6; margin: 20px 0;">
        <a href="{{ $acceptUrl }}"
           style="background:#4f46e5;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;">
            Accept invitation
        </a>
    </p>
    <p style="font-size: 12px; color: #64748b;">
        This invitation expires {{ $expiresAt->toFormattedDateString() }}.
    </p>
</body>
</html>
