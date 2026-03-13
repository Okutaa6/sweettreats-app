<!DOCTYPE html>
<html>

<head>
    <title>Forgot Password</title>
</head>

<body>
    <h1>Forgot Password</h1>
    <p>Hi {{ $user->nama }},</p>
    <p>You requested to reset your password. Click the link below to reset it:</p>
    <a href="{{ url('reset-password', $token) }}">Reset Password</a>
    <p>If you did not request a password reset, please ignore this email.</p>
    <p>Thanks,</p>
    <p>Your Company Name</p>
</body>

</html>
