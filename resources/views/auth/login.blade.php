<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>{{ $sysprofile['systitle'] }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('assets/css/style.sweettreats2.css') }}?v={{ @filemtime(public_path('assets/css/style.sweettreats.css')) ?: '1' }}" rel="stylesheet" type="text/css" />
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body class="auth-body">

    <div class="auth-card">

        <div class="card-header-brand">
            <div class="logo-wrap">
                <img src="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}" alt="Logo">
            </div>
            <h1 class="card-title">Login Admin</h1>
            <p class="card-subtitle">Sweet Treats Dental Clinic</p>
        </div>

        <div class="divider"></div>

        @include('layouts.notif')

        <form action="{{ route('signin') }}" method="post" autocomplete="off">
            @csrf

            <div class="field-group">
                <label class="field-label" for="email">Email</label>
                <div class="input-wrap">
                    <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
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

            <div class="field-group">
                <label class="field-label" for="password">Password</label>
                <div class="input-wrap">
                    <svg class="field-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" name="password" id="password"
                           class="@error('password') is-invalid @enderror"
                           placeholder="Masukkan password">
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <a href="{{ url('forgot-password') }}" class="forgot-link">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Lupa password?
            </a>

            <button type="submit" class="btn-auth">
                Masuk
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
            </button>

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
