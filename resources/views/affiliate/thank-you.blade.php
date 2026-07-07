<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Terkirim - Sweet Treats Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Montserrat', sans-serif;
            color: #3a2a1a;
            background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        a { text-decoration: none; color: inherit; }

        .ty-wrap { max-width: 580px; width: 100%; }

        .ty-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(70,21,0,0.12);
            border: 1px solid rgba(70,21,0,0.04);
            overflow: hidden;
        }

        /* HERO */
        .ty-hero {
            position: relative;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            padding: 56px 32px 44px;
            text-align: center;
            overflow: hidden;
        }
        .ty-hero::before, .ty-hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            pointer-events: none;
        }
        .ty-hero::before { width: 220px; height: 220px; top: -80px; left: -60px; }
        .ty-hero::after  { width: 160px; height: 160px; bottom: -60px; right: -40px; }
        .ty-check {
            position: relative;
            width: 88px; height: 88px;
            background: #fff;
            color: #2e7d32;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin-bottom: 18px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
            animation: tyPop .6s cubic-bezier(.2,.8,.3,1.4);
        }
        @keyframes tyPop {
            0%   { transform: scale(0); opacity: 0; }
            60%  { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
        .ty-hero h1 {
            position: relative;
            font-size: 1.7rem;
            font-weight: 900;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .ty-hero p {
            position: relative;
            font-size: 0.92rem;
            opacity: 0.92;
            line-height: 1.5;
            max-width: 380px;
            margin: 0 auto;
        }

        /* BODY */
        .ty-body { padding: 36px 32px 32px; }
        .ty-section-title {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #461500;
            text-align: center;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ty-section-title::before, .ty-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(70,21,0,0.12);
            max-width: 50px;
        }
        .ty-lead {
            text-align: center;
            font-size: 0.92rem;
            color: #6b4a30;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .ty-lead strong { color: #461500; font-weight: 700; }

        /* STEPS LIST */
        .ty-steps {
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 22px;
            border: 1px solid rgba(70,21,0,0.06);
        }
        .ty-step {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            padding: 10px 0;
        }
        .ty-step + .ty-step { border-top: 1px dashed rgba(70,21,0,0.1); }
        .ty-step-num {
            flex-shrink: 0;
            width: 30px; height: 30px;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.82rem;
            box-shadow: 0 4px 10px rgba(70,21,0,0.18);
        }
        .ty-step-text {
            font-size: 0.88rem;
            color: #4a3520;
            line-height: 1.5;
            padding-top: 4px;
        }
        .ty-step-text strong { color: #461500; font-weight: 700; }

        /* WA NOTE */
        .ty-wa-note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #fff;
            border: 1px dashed rgba(70,21,0,0.25);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 26px;
            font-size: 0.84rem;
            color: #6b4a30;
            line-height: 1.55;
        }
        .ty-wa-note .ty-wa-icon {
            color: #25d366;
            font-size: 1.2rem;
            margin-top: 1px;
        }

        /* ACTIONS */
        .ty-actions { display: flex; gap: 10px; flex-direction: column; }
        .btn-ty {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 22px;
            border-radius: 14px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            font-size: 0.92rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            transition: all 0.25s;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-ty--primary {
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
        }
        .btn-ty--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(70,21,0,0.3);
            color: #fff;
        }
        .btn-ty--outline {
            background: transparent;
            color: #461500;
            border: 2px solid rgba(70,21,0,0.18);
        }
        .btn-ty--outline:hover {
            background: #fef5ed;
            border-color: #461500;
            color: #461500;
        }

        .ty-foot {
            text-align: center;
            margin-top: 22px;
            font-size: 0.78rem;
            color: #8b7a6b;
        }
        .ty-foot a { color: #461500; font-weight: 700; }

        @media (max-width: 480px) {
            .ty-hero { padding: 44px 24px 36px; }
            .ty-hero h1 { font-size: 1.4rem; }
            .ty-hero p { font-size: 0.86rem; }
            .ty-check { width: 72px; height: 72px; font-size: 30px; }
            .ty-body { padding: 28px 22px 26px; }
            .ty-steps { padding: 18px 18px; }
            .ty-step-text { font-size: 0.84rem; }
        }
    </style>
</head>
<body>

    <div class="ty-wrap">
        <div class="ty-card">
            <div class="ty-hero">
                <div class="ty-check"><i class="fa-solid fa-check"></i></div>
                <h1>Pendaftaran Terkirim!</h1>
                <p>Terima kasih sudah mendaftar program referral Sweet Treats. Tim kami akan segera review pendaftaran kamu.</p>
            </div>

            <div class="ty-body">
                <div class="ty-section-title">Apa Selanjutnya?</div>
                <div class="ty-lead">
                    Tim admin Sweet Treats akan menghubungi kamu via <strong>WhatsApp</strong> dalam
                    <strong>1×24 jam</strong> untuk konfirmasi data &amp; share <strong>kode referral + PIN</strong> kamu.
                </div>

                <div class="ty-steps">
                    <div class="ty-step">
                        <div class="ty-step-num">1</div>
                        <div class="ty-step-text">Admin verifikasi data &amp; perawatan yang kamu daftarkan</div>
                    </div>
                    <div class="ty-step">
                        <div class="ty-step-num">2</div>
                        <div class="ty-step-text">Admin kirim <strong>kode referral + PIN 6 digit</strong> via WhatsApp</div>
                    </div>
                    <div class="ty-step">
                        <div class="ty-step-num">3</div>
                        <div class="ty-step-text">Login ke portal Sweet Mates pakai No HP &amp; PIN dari admin</div>
                    </div>
                    <div class="ty-step">
                        <div class="ty-step-num">4</div>
                        <div class="ty-step-text">Mulai share kode referral kamu &amp; kumpulin komisi!</div>
                    </div>
                </div>

                <div class="ty-wa-note">
                    <i class="fa-brands fa-whatsapp ty-wa-icon"></i>
                    <div>
                        Pastikan nomor WhatsApp yang kamu daftarkan <strong>aktif</strong> ya.
                        Kalau dalam 1×24 jam belum dihubungi, silakan hubungi admin di
                        <a href="https://wa.me/6281234567890" target="_blank" style="color:#461500; font-weight:700;">+62 812-3456-7890</a>.
                    </div>
                </div>

                <div class="ty-actions">
                    <a href="{{ route('affiliate.login') }}" class="btn-ty btn-ty--primary">
                        <i class="fa-solid fa-sign-in-alt"></i> Ke Halaman Login
                    </a>
                    <a href="https://sweettreats.id/" class="btn-ty btn-ty--outline">
                        <i class="fa-solid fa-home"></i> Kembali ke Beranda
                    </a>
                </div>

                <div class="ty-foot">
                    Sweet Treats Dental Clinic · <a href="https://sweettreats.id/">sweettreats.id</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
