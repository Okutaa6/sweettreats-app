<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Mates Login - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Portal login Sweet Mates — komunitas pasien ortho Sweet Treats Dental Clinic.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; color: #3a2a1a; background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%); min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; color: inherit; }
        .container { max-width: 520px; margin: 0 auto; padding: 0 24px; width: 100%; }

        .navbar { background: rgba(255,250,245,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(70,21,0,0.08); padding: 0 24px; }
        .navbar-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 72px; }
        .navbar-brand { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .navbar-brand span { background: linear-gradient(135deg, #461500, #8b3a00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #461500; color: #461500; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 700; transition: all 0.3s; }
        .btn-back:hover { background: #461500; color: #fff; }

        .login-section { flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 0; }
        .login-wrap { width: 100%; }

        .login-header { text-align: center; margin-bottom: 32px; }
        .login-header .tag { display: inline-block; padding: 6px 16px; background: rgba(70,21,0,0.06); border-radius: 50px; font-size: 0.78rem; font-weight: 700; color: #461500; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .login-logo { width: 72px; height: 72px; margin: 0 auto 18px; border-radius: 20px; background: linear-gradient(135deg, #461500, #8b3a00); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; box-shadow: 0 12px 30px rgba(70,21,0,0.25); }
        .login-header h1 { font-size: 2rem; font-weight: 900; color: #461500; margin-bottom: 8px; line-height: 1.2; }
        .login-header p { font-size: 0.95rem; color: #6b4a30; line-height: 1.6; }

        .login-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(70,21,0,0.08); border: 1px solid rgba(70,21,0,0.04); }

        .alert-error { background: linear-gradient(135deg,#ffebee,#ffcdd2); border: 1px solid #ef9a9a; border-radius: 14px; padding: 14px 20px; margin-bottom: 24px; color: #c62828; font-size: 0.88rem; display: flex; align-items: flex-start; gap: 10px; }
        .alert-error i { margin-top: 2px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #461500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group label .required { color: #c45200; margin-left: 2px; }
        .form-control { width: 100%; padding: 14px 18px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; color: #3a2a1a; transition: all 0.3s; outline: none; }
        .form-control:focus { border-color: #461500; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); background: #fff; }
        .form-control::placeholder { color: #bfa890; }

        .pin-input { letter-spacing: 8px; font-weight: 700; font-size: 1.05rem; text-align: center; }
        .pin-input::placeholder { letter-spacing: 6px; font-weight: 500; }

        .input-with-icon { position: relative; }
        .input-with-icon .input-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: #8b7a6b; font-size: 0.95rem; pointer-events: none; }
        .input-with-icon .form-control { padding-left: 46px; }
        .input-with-icon .toggle-pin { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #8b7a6b; cursor: pointer; padding: 6px 8px; font-size: 0.9rem; border-radius: 8px; transition: all 0.2s; }
        .input-with-icon .toggle-pin:hover { background: rgba(70,21,0,0.06); color: #461500; }

        .btn-submit { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; font-weight: 800; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 8px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(70,21,0,0.35); }

        .divider { display: flex; align-items: center; gap: 14px; margin: 24px 0; color: #bfa890; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(70,21,0,0.1); }

        .register-cta { text-align: center; padding: 18px 20px; background: linear-gradient(135deg, #fef5ed, #fef0e2); border: 1px solid rgba(70,21,0,0.08); border-radius: 14px; }
        .register-cta .cta-label { font-size: 0.82rem; color: #6b4a30; margin-bottom: 8px; }
        .register-cta .cta-link { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; background: #fff; border: 2px solid #461500; color: #461500; border-radius: 50px; font-size: 0.85rem; font-weight: 700; transition: all 0.3s; }
        .register-cta .cta-link:hover { background: #461500; color: #fff; transform: translateY(-1px); box-shadow: 0 6px 18px rgba(70,21,0,0.2); }

        .helper-text { text-align: center; margin-top: 20px; font-size: 0.78rem; color: #8b7a6b; line-height: 1.6; }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .navbar { padding: 0 16px; }
            .navbar-inner { height: 60px; }
            .navbar-brand { font-size: 1.2rem; }
            .btn-back { padding: 8px 14px; font-size: 0.78rem; }
            .login-section { padding: 32px 0; }
            .login-header { margin-bottom: 24px; }
            .login-logo { width: 60px; height: 60px; font-size: 1.5rem; border-radius: 16px; }
            .login-header h1 { font-size: 1.5rem; }
            .login-header p { font-size: 0.85rem; }
            .login-card { padding: 28px 22px; border-radius: 18px; }
            .form-control { padding: 12px 14px; font-size: 0.88rem; border-radius: 12px; }
            .input-with-icon .form-control { padding-left: 42px; }
            .btn-submit { padding: 14px; font-size: 0.85rem; border-radius: 12px; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="https://sweettreats.id/" class="navbar-brand">
                <span>Sweet Treats</span>
            </a>
            <a href="https://sweettreats.id/" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>
    </nav>

    <section class="login-section">
        <div class="container">
            <div class="login-wrap">

                <div class="login-header">
                    <div class="login-logo"><i class="fa-solid fa-heart"></i></div>
                    <div class="tag"><i class="fa-solid fa-users"></i> Portal Sweet Mates</div>
                    <h1>Selamat Datang</h1>
                    <p>Masuk ke portal Sweet Mates untuk posting konten &amp; track reward kamu.</p>
                </div>

                <div class="login-card">

                    @if(session('error'))
                        <div class="alert-error">
                            <i class="fa-solid fa-exclamation-circle"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert-error" style="background: linear-gradient(135deg,#e8f5e9,#c8e6c9); border-color:#a5d6a7; color:#2e7d32;">
                            <i class="fa-solid fa-check-circle"></i>
                            <div>{{ session('success') }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('affiliate.login.process') }}">
                        @csrf

                        <div class="form-group">
                            <label>No WhatsApp <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon"><i class="fa-solid fa-phone"></i></span>
                                <input type="text" name="phone" class="form-control"
                                       placeholder="08xxxxxxxxxx" inputmode="tel"
                                       value="{{ old('phone') }}" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>PIN 6 Digit <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="pin" id="pinInput" class="form-control pin-input"
                                       placeholder="••••••" maxlength="6" minlength="6"
                                       inputmode="numeric" pattern="[0-9]{6}" required>
                                <button type="button" class="toggle-pin" onclick="togglePin()" title="Tampilkan PIN">
                                    <i class="fa-solid fa-eye" id="togglePinIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="fa-solid fa-sign-in-alt"></i> Masuk
                        </button>
                    </form>

                    <div class="divider">atau</div>

                    <div class="register-cta">
                        <div class="cta-label">Belum jadi Sweet Mate?</div>
                        <a href="{{ route('affiliate.register') }}" class="cta-link">
                            <i class="fa-solid fa-user-plus"></i> Gabung Sekarang
                        </a>
                    </div>

                </div>

                <div class="helper-text">
                    Lupa PIN atau butuh bantuan? Hubungi admin Sweet Treats via WhatsApp.
                </div>

            </div>
        </div>
    </section>

    <script>
        function togglePin() {
            const input = document.getElementById('pinInput');
            const icon = document.getElementById('togglePinIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>

</body>
</html>
