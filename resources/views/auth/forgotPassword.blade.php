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
            <h1 class="card-title">Forgot Password</h1>
            <p class="card-subtitle">Sweet Treats Dental Clinic</p>
        </div>

        <div class="divider"></div>

        @include('layouts.notif')

        <form action="{{ route('forgot-password.process') }}" method="post" autocomplete="off">
            @csrf

            <div class="field-group">
                <label class="field-label" for="email">Email</label>
                <div class="input-wrap">
                    <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <input type="email" name="email" id="email"
                           value="{{ old('email') }}"
                           class="@error('email') is-invalid @enderror"
                           placeholder="Masukkan email">
                </div>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="recaptcha-wrap">
                <div class="g-recaptcha" data-sitekey="{{ env('CAPTCHA_SITE_KEY') }}"></div>
            </div>

            <button type="submit" class="btn-auth">
                Kirim Link
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                </svg>
            </button>

            <a href="http://127.0.0.1:8000/signin" class="back-link">← Kembali ke Login</a>

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
