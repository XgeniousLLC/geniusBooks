<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $companyName }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; padding: 24px; line-height: 1.6;">
    <div style="font-size: 14px;">
        {!! nl2br(e($body)) !!}
    </div>
    <p style="font-size: 12px; color: #64748b; margin-top: 32px;">
        {{ $companyName }}
    </p>
</body>
</html>
