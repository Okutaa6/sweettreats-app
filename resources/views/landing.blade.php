<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Treats Dental Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--bg);
            color: var(--ink);
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* faint grid backdrop for depth */
        body::before {
            content: "";
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(var(--line) 1px, transparent 1px),
                linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(circle at 50% 30%, #000, transparent 70%);
            opacity: 0.5;
        }

        .wrap { position: relative; z-index: 1; width: 100%; max-width: 1080px; margin: 0 auto; padding: 0 28px; }

        /* ============ NAV ============ */
        header {
            position: relative; z-index: 2;
            padding: 26px 0;
        }
        header .wrap { display: flex; align-items: center; justify-content: space-between; }

        .brand { display: inline-flex; align-items: center; gap: 11px; text-decoration: none; color: var(--ink); font-weight: 800; font-size: 1.1rem; letter-spacing: -0.2px; }
        .brand .mark {
            width: 38px; height: 38px; border-radius: 11px; background: var(--ink); color: #fff;
            display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;
        }

        .nav-right { display: flex; align-items: center; gap: 26px; }
        .nav-right a { text-decoration: none; color: var(--muted); font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; transition: color 0.25s; }
        .nav-right a:hover { color: var(--ink); }
        .nav-right a.accent:hover { color: var(--accent); }

        /* ============ MAIN / HERO ============ */
        main { position: relative; z-index: 1; flex: 1; display: flex; align-items: center; padding: 40px 0 70px; }
        .hero { width: 100%; }

        .eyebrow {
            display: inline-flex; align-items: center; gap: 9px; margin-bottom: 26px;
            font-size: 0.72rem; font-weight: 700; letter-spacing: 2.6px; text-transform: uppercase; color: var(--muted);
        }
        .eyebrow .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }

        .hero h1 {
            font-size: clamp(2.7rem, 7vw, 5.6rem);
            font-weight: 900; line-height: 0.98; letter-spacing: -0.045em; color: var(--ink);
            max-width: 14ch;
        }
        .hero h1 em { font-style: normal; background: var(--grad); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }

        .hero .lead { margin-top: 26px; max-width: 460px; font-size: 1.06rem; line-height: 1.6; color: var(--muted); }

        .meta { margin-top: 30px; display: flex; flex-wrap: wrap; gap: 10px 26px; align-items: center; font-size: 0.82rem; font-weight: 600; color: var(--muted); }
        .meta span { display: inline-flex; align-items: center; gap: 8px; }
        .meta i { color: var(--accent); }
        .meta .sep { width: 4px; height: 4px; border-radius: 50%; background: var(--line-strong); }

        /* ============ PORTAL ============ */
        .portal-label { margin: 60px 0 20px; display: flex; align-items: center; gap: 16px; font-size: 0.7rem; font-weight: 800; letter-spacing: 2.4px; text-transform: uppercase; color: var(--muted); }
        .portal-label::after { content: ""; flex: 1; height: 1px; background: var(--line); }

        .portal { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .card {
            position: relative; display: block; text-decoration: none; color: inherit;
            background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 34px 34px 30px;
            overflow: hidden; transition: transform 0.35s cubic-bezier(0.22,1,0.36,1), border-color 0.35s, box-shadow 0.35s;
        }
        .card::before {
            content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--accent);
            transform: scaleY(0); transform-origin: top; transition: transform 0.4s ease;
        }
        .card:hover { transform: translateY(-6px); border-color: var(--line-strong); box-shadow: 0 24px 50px rgba(23, 19, 16, 0.09); }
        .card:hover::before { transform: scaleY(1); }

        .card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 42px; }
        .card-index { font-size: 0.74rem; font-weight: 800; letter-spacing: 1.4px; color: var(--muted); }
        .card-ic {
            width: 46px; height: 46px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.05rem; background: var(--accent-soft); color: var(--accent); transition: background 0.3s, color 0.3s;
        }
        .card:hover .card-ic { background: var(--accent); color: #fff; }

        .card h2 { font-size: 1.7rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.08; }
        .card p { margin-top: 12px; font-size: 0.92rem; line-height: 1.6; color: var(--muted); max-width: 34ch; }

        .card-cta { margin-top: 26px; display: inline-flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 800; color: var(--ink); transition: gap 0.28s, color 0.28s; }
        .card:hover .card-cta { gap: 15px; color: var(--accent); }

        /* staff card = inverted, understated */
        .card.staff { background: var(--grad); border-color: transparent; }
        .card.staff .card-index { color: rgba(255, 255, 255, 0.5); }
        .card.staff h2 { color: #fff; }
        .card.staff p { color: rgba(255, 255, 255, 0.6); }
        .card.staff .card-ic { background: rgba(255, 255, 255, 0.12); color: #fff; }
        .card.staff:hover .card-ic { background: #fff; color: var(--accent); }
        .card.staff .card-cta { color: #fff; }
        .card.staff:hover .card-cta { color: #ffd9bf; }
        .card.staff::before { background: #ffd9bf; }

        /* ============ FOOTER ============ */
        footer { position: relative; z-index: 1; border-top: 1px solid var(--line); padding: 22px 0; }
        footer .wrap { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px 20px; }
        footer .copy { font-size: 0.78rem; color: var(--muted); }
        footer .links { display: flex; align-items: center; gap: 20px; }
        footer .links a { font-size: 0.78rem; color: var(--muted); text-decoration: none; display: inline-flex; align-items: center; gap: 7px; transition: color 0.25s; }
        footer .links a:hover { color: var(--accent); }

        /* ============ ENTRANCE ============ */
        .fx { opacity: 0; transform: translateY(16px); animation: rise 0.7s cubic-bezier(0.22,1,0.36,1) forwards; }
        .fx.d1 { animation-delay: 0.05s; }
        .fx.d2 { animation-delay: 0.13s; }
        .fx.d3 { animation-delay: 0.21s; }
        .fx.d4 { animation-delay: 0.29s; }
        .fx.d5 { animation-delay: 0.36s; }
        @keyframes rise { to { opacity: 1; transform: none; } }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 780px) {
            .portal { grid-template-columns: 1fr; }
            .card-top { margin-bottom: 30px; }
            main { padding: 20px 0 50px; }
        }
        @media (max-width: 520px) {
            .nav-right { gap: 16px; }
            .nav-right a span { display: none; }
            .hero .lead { font-size: 0.98rem; }
        }
    </style>
</head>

<body>

    <header>
        <div class="wrap">
            <a href="{{ route('home') }}" class="brand">
                <span class="mark"><i class="fa-solid fa-tooth"></i></span> Sweet Treats
            </a>
            <nav class="nav-right">
                <a href="/reservasi/riwayat"><i class="fa-solid fa-clock-rotate-left"></i> <span>Cek Riwayat</span></a>
                <a href="{{ route('signin') }}" class="accent"><i class="fa-solid fa-user-lock"></i> <span>Login Staff</span></a>
            </nav>
        </div>
    </header>

    <main>
        <div class="wrap hero">
            <div class="eyebrow fx"><span class="dot"></span> Sweet Treats Dental Clinic</div>
            <h1 class="fx d1">Senyum rapi,<br><em>tanpa drama.</em></h1>
            <p class="lead fx d2">Klinik gigi kekinian yang bikin ke dokter gigi jadi lebih fun. Mulai dari sini — pilih portal kamu.</p>

            <div class="meta fx d3">
                <span><i class="fa-solid fa-user-group"></i> 17.000+ pasien</span>
                <span class="sep"></span>
                <span><i class="fa-solid fa-star"></i> 4.9 rating Google</span>
                <span class="sep"></span>
                <span><i class="fa-solid fa-location-dot"></i> {{ $branchCount }} cabang</span>
            </div>

            <div class="portal-label fx d3">Pilih Portal</div>

            <div class="portal">
                <a href="{{ route('landing.reservation') }}" class="card fx d4">
                    <div class="card-top">
                        <span class="card-index">01 — PASIEN</span>
                        <span class="card-ic"><i class="fa-solid fa-calendar-check"></i></span>
                    </div>
                    <h2>Jadwalkan Kunjungan</h2>
                    <p>Pilih cabang, dokter, dan jam yang pas. Reservasi online tanpa antre.</p>
                    <span class="card-cta">Reservasi Sekarang <i class="fa-solid fa-arrow-right"></i></span>
                </a>

                <a href="{{ route('signin') }}" class="card staff fx d5">
                    <div class="card-top">
                        <span class="card-index">02 — STAFF ACCESS</span>
                        <span class="card-ic"><i class="fa-solid fa-user-lock"></i></span>
                    </div>
                    <h2>Dashboard Operasional</h2>
                    <p>Login untuk mengakses jadwal, pasien, absensi, dan sistem operasional Sweet Treats.</p>
                    <span class="card-cta">Login Staff <i class="fa-solid fa-arrow-right-to-bracket"></i></span>
                </a>
            </div>
        </div>
    </main>

    <footer>
        <div class="wrap">
            <span class="copy">&copy; {{ date('Y') }} Sweet Treats Dental Clinic</span>
            <div class="links">
                <a href="mailto:cs@sweettreats.id"><i class="fa-solid fa-envelope"></i> cs@sweettreats.id</a>
                <a href="tel:082188883510"><i class="fa-solid fa-phone"></i> 0821-8888-3510</a>
                <a href="{{ route('affiliate.login') }}">Portal Afiliasi</a>
            </div>
        </div>
    </footer>

</body>
</html>
