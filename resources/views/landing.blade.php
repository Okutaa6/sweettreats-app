<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Treats Dental Clinic - Senyum Rapi Tanpa Drama</title>
    <meta name="description"
        content="Klinik gigi kekinian yang buat pengalaman ke dokter gigi jadi lebih fun dan menyenangkan! Sweet, Cool, and Friendly.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ===== RESET & BASE ===== */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: #3a2a1a;
            background: #fffaf5;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ===== NAVBAR ===== */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(255, 250, 245, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(70, 21, 0, 0.08);
            transition: box-shadow 0.3s ease;
        }

        .navbar.scrolled {
            box-shadow: 0 2px 20px rgba(70, 21, 0, 0.1);
        }

        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            color: #461500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand span {
            background: linear-gradient(135deg, #461500, #8b3a00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .navbar-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #461500, #8b3a00);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.1rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-links a {
            font-size: 0.9rem;
            font-weight: 600;
            color: #5a3a20;
            transition: color 0.3s;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #461500;
            transition: width 0.3s;
        }

        .nav-links a:hover {
            color: #461500;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .btn-reservasi {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff !important;
            border: none;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-reservasi:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(70, 21, 0, 0.35);
        }

        .btn-reservasi:active {
            transform: translateY(0);
        }

        .nav-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #461500;
            cursor: pointer;
        }

        /* ===== HERO ===== */
        .hero {
            padding: 140px 0 80px;
            background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -60%;
            right: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(70, 21, 0, 0.04) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 58, 0, 0.03) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hero .container {
            display: flex;
            align-items: center;
            gap: 60px;
            position: relative;
            z-index: 1;
        }

        .hero-content {
            flex: 1;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(70, 21, 0, 0.08);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #461500;
            margin-bottom: 24px;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 900;
            line-height: 1.15;
            color: #461500;
            margin-bottom: 20px;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, #c45200, #ff7b2e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #6b4a30;
            margin-bottom: 36px;
            max-width: 520px;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: transparent;
            color: #461500;
            border: 2px solid #461500;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #461500;
            color: #fff;
        }

        .hero-visual {
            flex: 1;
            position: relative;
        }

        .hero-card {
            background: #fff;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(70, 21, 0, 0.1);
            position: relative;
        }

        .hero-card-emoji {
            font-size: 4rem;
            margin-bottom: 16px;
        }

        .hero-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 12px;
        }

        .hero-card p {
            font-size: 0.95rem;
            color: #6b4a30;
            line-height: 1.6;
        }

        .hero-float-card {
            position: absolute;
            background: #fff;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(70, 21, 0, 0.12);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: float 3s ease-in-out infinite;
        }

        .hero-float-card.card-1 {
            top: -20px;
            right: -30px;
            animation-delay: 0s;
        }

        .hero-float-card.card-2 {
            bottom: -20px;
            left: -30px;
            animation-delay: 1.5s;
        }

        .hero-float-card .float-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .hero-float-card .float-icon.green {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .hero-float-card .float-icon.blue {
            background: #e3f2fd;
            color: #1565c0;
        }

        .hero-float-card .float-text strong {
            display: block;
            font-size: 0.85rem;
            color: #461500;
        }

        .hero-float-card .float-text span {
            font-size: 0.75rem;
            color: #8b7a6b;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        /* ===== USP SECTION ===== */
        .usp {
            padding: 100px 0;
            background: #fff;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header .tag {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(70, 21, 0, 0.06);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #461500;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #461500;
            margin-bottom: 16px;
        }

        .section-header p {
            font-size: 1.05rem;
            color: #6b4a30;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        .usp-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .usp-card {
            background: linear-gradient(145deg, #fffaf5, #fff);
            border: 1px solid rgba(70, 21, 0, 0.06);
            border-radius: 20px;
            padding: 40px 32px;
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .usp-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #461500, #c45200);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .usp-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(70, 21, 0, 0.1);
        }

        .usp-card:hover::before {
            transform: scaleX(1);
        }

        .usp-icon {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 20px;
        }

        .usp-icon.sweet {
            background: linear-gradient(135deg, #fce4ec, #f8bbd0);
            color: #c2185b;
        }

        .usp-icon.cool {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1565c0;
        }

        .usp-icon.friendly {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32;
        }

        .usp-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 12px;
        }

        .usp-card p {
            font-size: 0.95rem;
            color: #6b4a30;
            line-height: 1.7;
        }

        /* ===== SERVICES ===== */
        .services {
            padding: 100px 0;
            background: linear-gradient(180deg, #fffaf5, #fef5ed);
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 28px;
        }

        .service-card {
            background: #fff;
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(70, 21, 0, 0.06);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(70, 21, 0, 0.1);
        }

        .service-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #fef0e2, #fce8d4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .service-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 10px;
        }

        .service-card p {
            font-size: 0.9rem;
            color: #6b4a30;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .service-card .service-price {
            font-size: 0.85rem;
            font-weight: 700;
            color: #c45200;
        }

        /* ===== STATS ===== */
        .stats {
            padding: 80px 0;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            text-align: center;
        }

        .stat-item h3 {
            font-size: 2.8rem;
            font-weight: 900;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff, #ffd9b3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-item p {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.75);
            font-weight: 500;
        }

        /* ===== TESTIMONIALS ===== */
        .testimonials {
            padding: 100px 0;
            background: #fff;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
        }

        .testimonial-card {
            background: linear-gradient(145deg, #fffaf5, #fff);
            border: 1px solid rgba(70, 21, 0, 0.06);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            box-shadow: 0 12px 30px rgba(70, 21, 0, 0.08);
        }

        .testimonial-stars {
            color: #f59e0b;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .testimonial-card blockquote {
            font-size: 0.95rem;
            color: #5a3a20;
            line-height: 1.7;
            margin-bottom: 20px;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testimonial-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #461500, #8b3a00);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .testimonial-author strong {
            display: block;
            font-size: 0.9rem;
            color: #461500;
        }

        .testimonial-author span {
            font-size: 0.8rem;
            color: #8b7a6b;
        }

        /* ===== FAQ ===== */
        .faq {
            padding: 100px 0;
            background: linear-gradient(180deg, #fffaf5, #fef5ed);
        }

        .faq-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: #fff;
            border: 1px solid rgba(70, 21, 0, 0.06);
            border-radius: 16px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: rgba(70, 21, 0, 0.15);
        }

        .faq-question {
            width: 100%;
            padding: 20px 24px;
            background: none;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            color: #461500;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            transition: color 0.3s;
        }

        .faq-question:hover {
            color: #c45200;
        }

        .faq-question i {
            font-size: 0.8rem;
            transition: transform 0.3s;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 300px;
        }

        .faq-answer p {
            padding: 0 24px 20px;
            font-size: 0.9rem;
            color: #6b4a30;
            line-height: 1.7;
        }

        /* ===== CTA ===== */
        .cta {
            padding: 100px 0;
            background: linear-gradient(135deg, #461500, #6b2400);
            text-align: center;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .cta h2 {
            font-size: 2.8rem;
            font-weight: 900;
            margin-bottom: 16px;
            position: relative;
        }

        .cta p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 40px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }

        .btn-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 40px;
            background: #fff;
            color: #461500;
            border: none;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        /* ===== FOOTER ===== */
        .footer {
            padding: 60px 0 30px;
            background: #1a0a00;
            color: rgba(255, 255, 255, 0.7);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 40px;
        }

        .footer h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
        }

        .footer p {
            font-size: 0.9rem;
            line-height: 1.7;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: #ffd9b3;
        }

        .footer-social {
            display: flex;
            gap: 12px;
        }

        .footer-social a {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
        }

        .footer-social a:hover {
            background: #461500;
            color: #fff;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 24px;
            text-align: center;
            font-size: 0.85rem;
        }

        /* ===== LOCATION SECTION ===== */
        .locations {
            padding: 100px 0;
            background: #fff;
        }

        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 28px;
        }

        .location-card {
            background: linear-gradient(145deg, #fffaf5, #fff);
            border: 1px solid rgba(70, 21, 0, 0.06);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s ease;
        }

        .location-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(70, 21, 0, 0.1);
        }

        .location-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, #fef0e2, #fce8d4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #461500;
            margin-bottom: 20px;
        }

        .location-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 8px;
        }

        .location-card p {
            font-size: 0.9rem;
            color: #6b4a30;
            line-height: 1.6;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 968px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 72px;
                left: 0;
                right: 0;
                background: #fffaf5;
                flex-direction: column;
                padding: 24px;
                gap: 16px;
                border-bottom: 1px solid rgba(70, 21, 0, 0.08);
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            }

            .nav-links.show {
                display: flex;
            }

            .nav-toggle {
                display: block;
            }

            .hero .container {
                flex-direction: column;
                text-align: center;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                margin: 0 auto 36px;
            }

            .hero-actions {
                justify-content: center;
            }

            .usp-grid {
                grid-template-columns: 1fr;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .cta h2 {
                font-size: 2rem;
            }

            .section-header h2 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero-actions {
                flex-direction: column;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 24px;
            }

            .stat-item h3 {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="{{ route('home') }}" class="navbar-brand">
                <div class="brand-icon"><i class="fa-solid fa-tooth"></i></div>
                <span>Sweet Treats</span>
            </a>

            <ul class="nav-links" id="navLinks">
                <li><a href="#beranda">Beranda</a></li>
                <li><a href="#tentang">Tentang Kami</a></li>
                <li><a href="#perawatan">Perawatan</a></li>
                <li><a href="#lokasi">Lokasi</a></li>
                <li><a href="#faq">FAQ</a></li>
                <li style="margin-left: 8px"><a href="/reservasi/riwayat"
                        style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border:1.5px solid rgba(70,21,0,0.2);border-radius:50px;font-size:0.82rem;transition:all 0.3s"><i
                            class="fa-solid fa-clock-rotate-left"></i> Cek Riwayat</a></li>
                <li>
                    <a href="#reservasi" class="btn-reservasi">
                        <i class="fa-solid fa-calendar-check"></i> Reservasi
                    </a>
                </li>
            </ul>

            <button class="nav-toggle" id="navToggle" onclick="toggleNav()">
                <i class="fa-solid fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero" id="beranda">
        <div class="container">
            <div class="hero-content">
                <div class="hero-badge">
                    <i class="fa-solid fa-sparkles"></i> Sweet, Cool & Friendly
                </div>
                <h1>Senyum Rapi<br><span class="highlight">Tanpa Drama</span></h1>
                <p>Pengalaman manis di setiap kunjungan kamu ke Sweet Treats yang bisa jadi bahan cerita yang seru!
                    Klinik gigi kekinian yang bikin ke dokter gigi jadi lebih fun.</p>
                <div class="hero-actions">
                    <a href="#reservasi" class="btn-reservasi">
                        <i class="fa-solid fa-calendar-check"></i> Reservasi Sekarang
                    </a>
                    <a href="#perawatan" class="btn-secondary">
                        <i class="fa-solid fa-teeth"></i> Lihat Perawatan
                    </a>
                </div>
                <div style="margin-top:16px">
                    <a href="/reservasi/riwayat"
                        style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;font-weight:600;color:#461500;opacity:0.7;transition:opacity 0.3s"
                        onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        <i class="fa-solid fa-clock-rotate-left"></i> Cek Riwayat Reservasi
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-card">
                    <div class="hero-card-emoji">🦷✨</div>
                    <h3>Kesehatan Gigi Kamu Prioritas Kami!</h3>
                    <p>Didukung oleh dokter gigi umum & spesialis berpengalaman dengan peralatan modern untuk hasil
                        terbaik.</p>
                </div>
                <div class="hero-float-card card-1">
                    <div class="float-icon green"><i class="fa-solid fa-check"></i></div>
                    <div class="float-text">
                        <strong>17K+ Pasien</strong>
                        <span>Sudah Percaya</span>
                    </div>
                </div>
                <div class="hero-float-card card-2">
                    <div class="float-icon blue"><i class="fa-solid fa-star"></i></div>
                    <div class="float-text">
                        <strong>Rating 4.9</strong>
                        <span>Google Reviews</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- USP -->
    <section class="usp" id="tentang">
        <div class="container">
            <div class="section-header">
                <div class="tag">Kenapa Harus Sweet Treats?</div>
                <h2>Sweet, Cool & Friendly</h2>
                <p>Tiga pilar yang membuat pengalaman ke dokter gigi jadi lebih menyenangkan</p>
            </div>
            <div class="usp-grid">
                <div class="usp-card">
                    <div class="usp-icon sweet"><i class="fa-solid fa-heart"></i></div>
                    <h3>Sweet</h3>
                    <p>Pengalaman manis di setiap kunjungan kamu ke Sweet Treats yang bisa jadi bahan cerita yang
                        seru!</p>
                </div>
                <div class="usp-card">
                    <div class="usp-icon cool"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                    <h3>Cool</h3>
                    <p>Klinik gigi kekinian yang buat pengalaman ke dokter gigi jadi lebih fun dan
                        menyenangkan!</p>
                </div>
                <div class="usp-card">
                    <div class="usp-icon friendly"><i class="fa-solid fa-face-smile"></i></div>
                    <h3>Friendly</h3>
                    <p>Pelayanan yang friendly dari sweet dentist & sweet crew pasti bikin kamu nyaman dan betah.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- SERVICES -->
    <section class="services" id="perawatan">
        <div class="container">
            <div class="section-header">
                <div class="tag">Perawatan</div>
                <h2>Layanan Kami</h2>
                <p>Perawatan gigi lengkap untuk senyum yang lebih sehat dan percaya diri</p>
            </div>
            <div class="services-grid">
                @forelse ($services as $service)
                    <div class="service-card">
                        <div class="service-icon">🦷</div>
                        <h3>{{ $service->name }}</h3>
                        <p>Durasi perawatan {{ $service->duration_minutes }} menit dengan penanganan profesional.</p>
                        <div class="service-price">
                            <i class="fa-solid fa-tag"></i> Mulai dari
                            Rp {{ number_format($service->price, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <div class="service-card" style="grid-column: 1 / -1; text-align: center;">
                        <div class="service-icon" style="margin: 0 auto 20px;">🦷</div>
                        <h3>Segera Hadir</h3>
                        <p>Informasi layanan segera tersedia. Hubungi kami untuk konsultasi.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- STATS -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>17K+</h3>
                    <p>Pasien Terlayani</p>
                </div>
                <div class="stat-item">
                    <h3>5+</h3>
                    <p>Dokter Spesialis</p>
                </div>
                <div class="stat-item">
                    <h3>{{ $branchCount }}+</h3>
                    <p>Cabang Klinik</p>
                </div>
                <div class="stat-item">
                    <h3>4.9</h3>
                    <p>Rating Google</p>
                </div>
            </div>
        </div>
    </section>

    <!-- LOCATIONS -->
    <section class="locations" id="lokasi">
        <div class="container">
            <div class="section-header">
                <div class="tag">Lokasi</div>
                <h2>Cabang Kami</h2>
                <p>Temui kami di lokasi terdekat dari kamu</p>
            </div>
            <div class="locations-grid">
                @forelse ($branches as $branch)
                    <div class="location-card">
                        <div class="location-icon"><i class="fa-solid fa-location-dot"></i></div>
                        <h3>{{ $branch->name }}</h3>
                        <p>Sweet Treats Dental Clinic — {{ $branch->name }}</p>
                    </div>
                @empty
                    <div class="location-card" style="text-align: center;">
                        <div class="location-icon" style="margin: 0 auto 20px;"><i
                                class="fa-solid fa-location-dot"></i></div>
                        <h3>Informasi Lokasi</h3>
                        <p>Hubungi kami untuk info lebih lanjut mengenai lokasi klinik.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="testimonials">
        <div class="container">
            <div class="section-header">
                <div class="tag">Testimoni</div>
                <h2>Kata Mereka</h2>
                <p>Testimoni dari mereka yang sudah perawatan di Sweet Treats!</p>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i>
                    </div>
                    <blockquote>Ga pernah urus gigi karena ga pernah berani. Say no more! Sweet Treats Dental Clinic
                        Really Helps Me</blockquote>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">CL</div>
                        <div>
                            <strong>Chandra Liow</strong>
                            <span>Youtuber</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i>
                    </div>
                    <blockquote>Tiap kali ke dokter gigi, aku selalu merasa cemas karena takut. Awal ke sini, ternyata
                        ambiencenya fun. Dokternya ramah dan baik banget!</blockquote>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">S</div>
                        <div>
                            <strong>Salsabila</strong>
                            <span>Public Figure</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i>
                    </div>
                    <blockquote>Akhirnya menemukan klinik gigi terpercaya untuk perawatan saluran akar. Hanya 2x visit
                        dan beres. Sekarang bebas dari rasa sakit!</blockquote>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">U</div>
                        <div>
                            <strong>Uus</strong>
                            <span>Komedian</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                            class="fa-solid fa-star"></i>
                    </div>
                    <blockquote>Seneng banget scaling dan whitening di sini karena ga sakit. Prosesnya seru, bisa sambil
                        nonton. 10/10 untuk Sweet Treats!</blockquote>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar">AC</div>
                        <div>
                            <strong>Amel Carla</strong>
                            <span>Public Figure</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="faq" id="faq">
        <div class="container">
            <div class="section-header">
                <div class="tag">FAQ</div>
                <h2>Pertanyaan Umum</h2>
                <p>Pertanyaan yang paling sering ditanya oleh pasien Sweet Treats</p>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Jam operasional Sweet Treats?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Kami melayani pasien dari hari Selasa – Minggu, mulai pukul 10:00 WIB hingga 20:00 WIB. Pada
                            hari tertentu di tanggal merah, mohon cek pengumuman khusus di media sosial kami.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Bagaimana cara reservasi?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Kamu dapat melakukan reservasi langsung melalui tombol "Reservasi" di halaman ini. Pilih
                            cabang, dokter, dan waktu yang kamu inginkan.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Bagaimana jika ingin reschedule?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Mohon informasikan kepada kami minimal 3 jam sebelumnya melalui kontak reservasi kami. Agar
                            kami dapat mengatur ulang jadwal kamu.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Perawatan apa saja yang tersedia?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Kami menyediakan layanan lengkap, mulai dari perawatan dasar (scaling, penambalan,
                            pencabutan), estetik (bleaching, veneer), hingga perawatan kompleks (implant, kawat gigi,
                            operasi gigi bungsu).</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Metode pembayaran apa saja yang diterima?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Kami menerima pembayaran melalui Tunai, Kartu Debit, QRIS, dan Transfer Bank. Kami juga
                            menyediakan fasilitas cicilan untuk promo tertentu.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question" onclick="toggleFaq(this)">
                        <span>Apakah ada estimasi biaya sebelum perawatan?</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="faq-answer">
                        <p>Ya. Setelah pemeriksaan dan rencana perawatan ditentukan, tim kami akan memberikan estimasi
                            biaya yang terperinci dan transparan sebelum kamu menyetujui dan memulai tindakan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta" id="reservasi">
        <div class="container">
            <h2>Mulai dari Sini</h2>
            <p>Jangan nunggu sakit, atur jadwalmu hari ini</p>
            <a href="{{ route('landing.reservation') }}" class="btn-cta">
                <i class="fa-solid fa-calendar-check"></i> Reservasi Di Sini!
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>Tentang Sweet Treats</h4>
                    <p>Sweet Treats Dental Clinic adalah klinik gigi dengan konsep dan desain kekinian. Tagline Sweet,
                        Cool and Friendly menghantarkan pasien pada pengalaman yang berbeda ke klinik gigi.</p>
                </div>
                <div>
                    <h4>Hubungi Kami</h4>
                    <ul class="footer-links">
                        <li><a href="mailto:cs@sweettreats.id"><i class="fa-solid fa-envelope"></i>
                                cs@sweettreats.id</a></li>
                        <li><a href="tel:082188883510"><i class="fa-solid fa-phone"></i> 0821-8888-3510</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Follow Us</h4>
                    <div class="footer-social">
                        <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                        <a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                        <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; {{ date('Y') }} Sweet Treats Dental Clinic. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Mobile nav toggle
        function toggleNav() {
            document.getElementById('navLinks').classList.toggle('show');
        }

        // FAQ accordion
        function toggleFaq(btn) {
            const item = btn.parentElement;
            const isActive = item.classList.contains('active');

            // Close all
            document.querySelectorAll('.faq-item').forEach(el => el.classList.remove('active'));

            // Toggle clicked
            if (!isActive) {
                item.classList.add('active');
            }
        }

        // Smooth scroll for nav links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
                // Close mobile nav
                document.getElementById('navLinks').classList.remove('show');
            });
        });
    </script>
</body>

</html>
