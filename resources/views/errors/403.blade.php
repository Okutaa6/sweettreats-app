<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — Akses Ditolak</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            background: #FBF6EE; color: #5A4632;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }
        .box {
            max-width: 420px; width: 100%; text-align: center;
            background: #FFFFFF; border: 1px solid #EFE3D2; border-radius: 18px;
            padding: 34px 28px; box-shadow: 0 10px 30px rgba(138, 58, 18, .06);
        }
        .badge {
            width: 62px; height: 62px; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; background: #F2E7D8; color: #8A3A12; font-size: 26px;
        }
        h1 { margin: 0 0 6px; font-size: 20px; color: #8A3A12; }
        .code { font-size: 12px; font-weight: 700; letter-spacing: .08em; color: #B7926F; margin-bottom: 14px; }
        p { margin: 0 0 20px; font-size: 14px; line-height: 1.6; color: #7A5F45; }
        .msg {
            background: #FBF6EE; border-radius: 11px; padding: 10px 13px;
            font-size: 12.5px; color: #9A7256; margin-bottom: 20px; word-break: break-word;
        }
        a.btn {
            display: inline-block; text-decoration: none;
            background: #8A3A12; color: #FFFFFF; font-weight: 700; font-size: 14px;
            padding: 11px 22px; border-radius: 11px;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="badge">&#128274;</div>
        <div class="code">403 · AKSES DITOLAK</div>
        <h1>Anda tidak punya akses ke halaman ini</h1>
        <p>Halaman ini terbatas untuk peran/cabang tertentu. Bila menurut Anda ini keliru, hubungi admin sistem.</p>
        @if(($exception->getMessage() ?? '') !== '')
            <div class="msg">{{ $exception->getMessage() }}</div>
        @endif
        <a class="btn" href="{{ url('/') }}">Kembali ke Beranda</a>
    </div>
</body>
</html>
