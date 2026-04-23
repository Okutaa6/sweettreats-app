<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pendaftaran Berhasil - Sweet Treats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #faf5f3 0%, #e8d5ce 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            font-family: 'Montserrat', Arial, sans-serif;
        }
        .ty-card {
            max-width: 520px;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,.1);
            overflow: hidden;
            text-align: center;
        }
        .ty-hero {
            background: linear-gradient(135deg, rgb(70,21,0) 0%, rgb(120,50,15) 100%);
            color: #fff;
            padding: 40px 28px 32px;
        }
        .ty-check {
            width: 72px;
            height: 72px;
            background: #fff;
            color: rgb(70,21,0);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            margin-bottom: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,.15);
        }
        .ty-hero h3 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .ty-hero p { margin: 0; font-size: 14px; opacity: .95; }
        .ty-body { padding: 32px 28px 36px; color: #444; }
        .ty-body h4 {
            color: rgb(70,21,0);
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .ty-body p { font-size: 14px; line-height: 1.65; color: #555; }
        .ty-steps {
            text-align: left;
            background: #faf5f3;
            border-left: 3px solid rgb(70,21,0);
            padding: 16px 18px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
        .ty-steps ol { margin: 0; padding-left: 20px; font-size: 13px; color: #444; line-height: 1.7; }
        .ty-steps li { margin-bottom: 6px; }
        .btn-brand {
            background: rgb(70,21,0);
            border: none;
            color: #fff;
            font-weight: 700;
            padding: 11px 22px;
            border-radius: 8px;
        }
        .btn-brand:hover { background: rgb(100,40,10); color: #fff; }
        .btn-outline-brand {
            background: #fff;
            border: 2px solid rgb(70,21,0);
            color: rgb(70,21,0);
            font-weight: 700;
            padding: 9px 20px;
            border-radius: 8px;
        }
        .btn-outline-brand:hover { background: #faf5f3; color: rgb(70,21,0); }
    </style>
</head>
<body>
    <div class="ty-card">
        <div class="ty-hero">
            <div class="ty-check"><i class="fa fa-check"></i></div>
            <h3>Pendaftaran Terkirim!</h3>
            <p>Terima kasih sudah mendaftar program reward Sweet Treats</p>
        </div>
        <div class="ty-body">
            <h4>Apa selanjutnya?</h4>
            <p>
                Tim admin Sweet Treats akan menghubungi kamu via <strong>WhatsApp</strong> dalam
                <strong>1&times;24 jam</strong> untuk memberikan <strong>username</strong> dan <strong>PIN</strong> akses portal kamu.
            </p>

            <div class="ty-steps">
                <strong style="color:rgb(70,21,0); font-size:13px;">Langkah selanjutnya:</strong>
                <ol>
                    <li>Admin verifikasi data kamu</li>
                    <li>Admin kirim username &amp; PIN via WhatsApp</li>
                    <li>Login ke portal Sweet Mates pakai PIN dari admin</li>
                    <li>Mulai posting konten behel journey kamu &amp; klaim reward!</li>
                </ol>
            </div>

            <p style="font-size:13px; color:#777;">
                Pastikan nomor WhatsApp yang kamu daftarkan aktif supaya admin bisa menghubungi.
            </p>

            <div class="mt-3 d-flex" style="gap:10px; justify-content:center; flex-wrap:wrap;">
                <a href="{{ route('affiliate.login') }}" class="btn btn-outline-brand">
                    <i class="fa fa-sign-in-alt mr-1"></i> Ke Halaman Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
