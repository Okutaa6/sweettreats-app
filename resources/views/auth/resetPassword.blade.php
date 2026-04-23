<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ $sysprofile['systitle'] }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/style.sweettreats.css')) ?: '1' }}" rel="stylesheet" type="text/css" />
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body class="auth-body">

    <div class="auth-card">

        <div class="card-header-brand">
            <div class="logo-wrap">
                <img src="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}" alt="Logo">
            </div>
            <h1 class="card-title">Reset Password</h1>
            <p class="card-subtitle">{{ $sysprofile['systitle'] }}</p>
        </div>

        <div class="divider"></div>

        @include('layouts.notif')

        <form action="{{ route('reset-password.process', $token) }}" method="post" autocomplete="off">
            @csrf

            <div class="field-group">
                <label class="field-label" for="password">New Password</label>
                <div class="input-wrap">
                    <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password" id="password"
                           class="@error('password') is-invalid @enderror"
                           placeholder="Masukkan password baru">
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="field-group">
                <label class="field-label" for="password_confirmation">Confirm New Password</label>
                <div class="input-wrap">
                    <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           placeholder="Konfirmasi password baru">
                </div>
            </div>

            <div class="recaptcha-wrap">
                <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_SITE_KEY') }}"></div>
            </div>

            <button type="submit" class="btn-auth">
                Reset Password
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                </svg>
            </button>

            <a href="{{ url('login') }}" class="back-link">← Kembali ke Login</a>

            <p class="footer-note">© {{ date('Y') }} Sweet Treats Dental Clinic</p>
        </form>
    </div>

    <script>
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.btn-auth').click();
            }
        });
    </script>
</body>
</html>
