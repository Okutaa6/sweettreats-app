<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ganti Password — {{ $sysprofile->systitle ?? 'Sweet Treats' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #F4ECE2; font-family: 'Plus Jakarta Sans', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { width: 100%; max-width: 400px; background: #fff; border-radius: 18px; padding: 32px 28px; box-shadow: 0 12px 40px rgba(58,17,0,.12); }
        h4 { color: #461500; font-weight: 800; margin: 0 0 4px; font-size: 20px; }
        p.sub { color: #9A7256; font-size: 13.5px; margin: 0 0 20px; line-height: 1.5; }
        label { display: block; font-size: 12px; font-weight: 700; color: #7A5B43; text-transform: uppercase; letter-spacing: .05em; }
        input { width: 100%; border: 1px solid #E7D3BE; border-radius: 11px; padding: 11px 13px; margin: 6px 0 14px; font-size: 14px; font-family: inherit; }
        button { width: 100%; background: #461500; color: #fff; border: 0; border-radius: 12px; padding: 13px; font-weight: 800; font-size: 15px; cursor: pointer; font-family: inherit; }
        .err { background: #fdecea; color: #b71c1c; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
        .keluar { display: block; text-align: center; color: #9A7256; font-size: 13px; margin-top: 14px; text-decoration: none; }
    </style>
</head>
<body>
    @php $wajib = (bool) session('must_change_password'); @endphp
    <div class="card">
        <h4>{{ $wajib ? 'Ganti Password Dulu' : 'Ganti Kata Sandi' }}</h4>
        <p class="sub">
            @if($wajib)
                Password kamu barusan di-reset oleh IT. Buat password baru milikmu sendiri sebelum lanjut.
            @else
                Masukkan password lama untuk membuktikan ini benar kamu, lalu buat password baru.
            @endif
        </p>

        @if($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('ganti.password.process') }}">
            @csrf
            @unless($wajib)
                <label>Password lama</label>
                <input type="password" name="password_lama" required placeholder="Password yang sekarang">
            @endunless
            <label>Password baru</label>
            <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter" autofocus>

            <label>Ulangi password baru</label>
            <input type="password" name="password_confirmation" required minlength="6" placeholder="Ketik lagi">

            <button type="submit">Simpan &amp; Lanjut</button>
        </form>

        <a class="keluar" href="{{ route('signout') }}">Keluar</a>
    </div>
</body>
</html>
