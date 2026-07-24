<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>Login Staff — {{ $sysprofile['systitle'] }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="shortcut icon" href="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #fdfbf9;
            --ink: #3a1c0a;
            --muted: #8c7f74;
            --line: #ece5de;
            --line-strong: #ddd2c8;
            --accent: #6b2400;
            --accent-soft: #f2e7dd;
            --grad: linear-gradient(135deg, #8b3a00, #461500);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: var(--ink);
            background: var(--bg);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 22px;
            -webkit-font-smoothing: antialiased;
        }

        /* faint grid backdrop (senada landing) */
        body::before {
            content: "";
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(var(--line) 1px, transparent 1px),
                linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(circle at 50% 40%, #000, transparent 70%);
            opacity: 0.55;
        }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 410px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 40px 38px 34px;
            box-shadow: 0 24px 60px rgba(23, 19, 16, 0.07);
            animation: rise 0.7s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @keyframes rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

        .brand { display: flex; justify-content: center; margin-bottom: 26px; }
        .brand .mark { width: 56px; height: 56px; border-radius: 16px; background: #fff; color: var(--accent); border: 1px solid var(--line); box-shadow: 0 10px 24px rgba(58, 28, 10, 0.12); display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; overflow: hidden; }
        .brand .mark img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }

        .head { text-align: center; margin-bottom: 2px; }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 9px;
            font-size: 0.7rem; font-weight: 700; letter-spacing: 2.4px; text-transform: uppercase; color: var(--muted);
        }
        .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }

        .title { margin-top: 14px; font-size: 1.7rem; font-weight: 900; letter-spacing: -0.02em; color: var(--ink); }
        .sub { margin-top: 8px; color: var(--muted); font-size: 0.9rem; line-height: 1.5; }

        /* flash */
        .flash { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: 0.84rem; font-weight: 500; margin-top: 22px; line-height: 1.45; }
        .flash i { margin-top: 2px; }
        .flash.err { background: #fdecec; border: 1px solid #f5c2c2; color: #b02a2a; }
        .flash.ok { background: #e9f8ee; border: 1px solid #b7e6c6; color: #1e7a43; }

        form { margin-top: 26px; }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--ink); margin-bottom: 8px; }

        .input-wrap { position: relative; display: flex; align-items: center; }
        .input-wrap .lead-ic { position: absolute; left: 15px; color: var(--muted); pointer-events: none; display: inline-flex; }
        .input-wrap input {
            width: 100%; padding: 14px 16px 14px 44px;
            border: 1.6px solid var(--line-strong); border-radius: 13px; background: #fff;
            font-family: inherit; font-size: 0.95rem; color: var(--ink);
            transition: border-color 0.22s, box-shadow 0.22s;
        }
        .input-wrap input::placeholder { color: #bcaea0; }
        .input-wrap input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
        .input-wrap input.invalid { border-color: #e08585; box-shadow: 0 0 0 4px rgba(224, 133, 133, 0.14); }

        .toggle-pass { position: absolute; right: 12px; background: none; border: none; cursor: pointer; color: var(--muted); padding: 6px; display: inline-flex; border-radius: 8px; transition: color 0.2s, background 0.2s; }
        .toggle-pass:hover { color: var(--accent); background: var(--accent-soft); }

        .field-err { margin-top: 6px; font-size: 0.78rem; color: #b02a2a; font-weight: 500; }

        .forgot { display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 600; color: var(--muted); text-decoration: none; margin-bottom: 24px; transition: gap 0.2s, color 0.2s; }
        .forgot:hover { gap: 9px; color: var(--accent); }

        .remember { display: flex; align-items: flex-start; gap: 10px; margin: 2px 0 6px; cursor: pointer; }
        .remember input { margin-top: 3px; width: 15px; height: 15px; accent-color: var(--accent); cursor: pointer; flex: none; }
        .remember .tx { font-size: 0.84rem; font-weight: 600; color: var(--ink); line-height: 1.45; }
        .remember .hint { display: block; font-size: 0.76rem; font-weight: 500; color: var(--muted); }

        .btn-submit {
            width: 100%; margin-top: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 15px 24px; border: none; border-radius: 13px; cursor: pointer;
            font-family: inherit; font-size: 0.96rem; font-weight: 800; letter-spacing: 0.2px;
            color: #fff; background: var(--grad);
            transition: transform 0.22s, box-shadow 0.22s, filter 0.22s;
        }
        .btn-submit:hover { transform: translateY(-2px); filter: brightness(1.08); box-shadow: 0 14px 30px rgba(42, 13, 0, 0.3); }
        .btn-submit i { transition: transform 0.22s; }
        .btn-submit:hover i { transform: translateX(4px); }

        .foot { margin-top: 26px; text-align: center; font-size: 0.76rem; color: var(--muted); }
        .back-home { display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; color: var(--muted); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .back-home:hover { color: var(--accent); }

        @media (max-width: 420px) { .card { padding: 34px 26px 30px; } .title { font-size: 1.5rem; } }
    </style>
</head>

<body>
    <div class="card">
        <div class="brand">
            <span class="mark">
                @if (!empty($sysprofile['syslogo']))
                    <img src="{{ asset('sysprofile/' . $sysprofile['syslogo']) }}" alt="Logo">
                @else
                    <i class="fa-solid fa-tooth"></i>
                @endif
            </span>
        </div>

        <div class="head">
            <span class="eyebrow"><span class="dot"></span> Staff Access</span>
            <h1 class="title">Selamat Datang</h1>
            <p class="sub">Masuk untuk mengakses dashboard operasional Sweet Treats.</p>
        </div>

        {{-- Flash message (inline, tanpa Bootstrap) --}}
        @if (Session::has('success'))
            <div class="flash ok"><i class="fa-solid fa-circle-check"></i><span>{{ Session::get('success') }}</span></div>
        @elseif (Session::has('error'))
            <div class="flash err"><i class="fa-solid fa-circle-exclamation"></i><span>{{ Session::get('error') }}</span></div>
        @elseif ($errors->any())
            <div class="flash err"><i class="fa-solid fa-circle-exclamation"></i><span>{{ $errors->first() }}</span></div>
        @endif

        <form action="{{ route('signin') }}" method="post" autocomplete="off">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <div class="input-wrap">
                    <span class="lead-ic"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" id="email"
                           value="{{ old('email') }}"
                           class="{{ $errors->has('email') ? 'invalid' : '' }}"
                           placeholder="nama@sweettreats.id" autofocus>
                </div>
                @error('email')
                    <div class="field-err">{{ $message }}</div>
                @enderror
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="lead-ic"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" id="password"
                           class="{{ $errors->has('password') ? 'invalid' : '' }}"
                           placeholder="Masukkan password">
                    <button type="button" class="toggle-pass" id="togglePass" aria-label="Tampilkan password">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
                @error('password')
                    <div class="field-err">{{ $message }}</div>
                @enderror
            </div>

            <label class="remember">
                <input type="checkbox" name="remember_device" value="1">
                <span class="tx">Ingat perangkat ini
                    <span class="hint">Hanya centang di HP pribadimu — biar tidak perlu login ulang tiap absen.</span>
                </span>
            </label>

            <button type="submit" class="btn-submit">
                Masuk ke Dashboard
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>

            <div class="foot">
                <div>© {{ date('Y') }} Sweet Treats Dental Clinic</div>
                <a href="{{ route('home') }}" class="back-home">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke beranda
                </a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var btn = document.getElementById('togglePass');
            var input = document.getElementById('password');
            var icon = document.getElementById('eyeIcon');
            if (btn && input) {
                btn.addEventListener('click', function () {
                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    icon.className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
                    btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
                });
            }
        })();
    </script>
</body>
</html>
