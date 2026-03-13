<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Reservasi - Sweet Treats Dental Clinic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            color: #3a2a1a;
            background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%);
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            max-width: 780px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navbar */
        .navbar {
            background: rgba(255, 250, 245, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(70, 21, 0, 0.08);
            padding: 0 24px;
        }

        .navbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 800;
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

        .brand-icon {
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

        .btn-new {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.3s;
        }

        .btn-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(70, 21, 0, 0.3);
        }

        /* Page */
        .page-section {
            padding: 48px 0 80px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header .tag {
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

        .page-header h1 {
            font-size: 2rem;
            font-weight: 900;
            color: #461500;
            margin-bottom: 8px;
        }

        .page-header p {
            font-size: 0.95rem;
            color: #6b4a30;
        }

        /* Alert */
        .alert-success {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 1px solid #a5d6a7;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 14px;
            color: #2e7d32;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .alert-success i {
            font-size: 1.3rem;
        }

        /* Reservation cards */
        .res-card {
            background: #fff;
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 20px;
            box-shadow: 0 8px 30px rgba(70, 21, 0, 0.06);
            border: 1px solid rgba(70, 21, 0, 0.04);
            transition: all 0.3s;
        }

        .res-card:hover {
            box-shadow: 0 12px 40px rgba(70, 21, 0, 0.1);
            transform: translateY(-2px);
        }

        .res-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .res-date {
            font-size: 1.1rem;
            font-weight: 800;
            color: #461500;
        }

        .res-date small {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #8b7a6b;
            margin-top: 2px;
        }

        .res-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Status badge colors are now applied inline from DB */

        .res-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }

        .res-meta-item .meta-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8b7a6b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .res-meta-item .meta-value {
            font-size: 0.9rem;
            font-weight: 700;
            color: #461500;
        }

        .res-services {
            border-top: 1px solid rgba(70, 21, 0, 0.06);
            padding-top: 16px;
        }

        .res-services-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8b7a6b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .res-svc-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .res-svc-tag {
            padding: 6px 14px;
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #461500;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(70, 21, 0, 0.06);
        }

        .empty-state .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #c0b0a0;
            margin: 0 auto 20px;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            font-weight: 800;
            color: #461500;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 0.9rem;
            color: #8b7a6b;
            margin-bottom: 24px;
        }

        .empty-state .btn-new-res {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            transition: all 0.3s;
        }

        .empty-state .btn-new-res:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(70, 21, 0, 0.35);
        }

        /* Not logged in */
        .login-prompt {
            text-align: center;
            padding: 60px 24px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 30px rgba(70, 21, 0, 0.06);
        }

        .login-prompt .form-group {
            max-width: 360px;
            margin: 0 auto 20px;
        }

        .login-prompt label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-prompt .form-control {
            width: 100%;
            padding: 14px 18px;
            background: #fffaf5;
            border: 2px solid rgba(70, 21, 0, 0.1);
            border-radius: 14px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.95rem;
            color: #3a2a1a;
            outline: none;
            transition: all 0.3s;
        }

        .login-prompt .form-control:focus {
            border-color: #461500;
            box-shadow: 0 0 0 4px rgba(70, 21, 0, 0.08);
        }

        .login-prompt .btn-check {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #461500, #6b2400);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-prompt .btn-check:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(70, 21, 0, 0.35);
        }

        @media (max-width: 640px) {
            .res-meta {
                grid-template-columns: 1fr;
            }

            .res-card {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="{{ route('home') }}" class="navbar-brand">
                <div class="brand-icon"><i class="fa-solid fa-tooth"></i></div>
                <span>Sweet Treats</span>
            </a>
            <a href="{{ route('landing.reservation') }}" class="btn-new">
                <i class="fa-solid fa-plus"></i> Buat Reservasi
            </a>
        </div>
    </nav>

    <section class="page-section">
        <div class="container">

            <div class="page-header">
                <div class="tag"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat</div>
                <h1>Reservasi Kamu</h1>
                @if ($phone)
                    <p>Menampilkan reservasi untuk <strong>{{ $phone }}</strong></p>
                @endif
            </div>

            @if (session('success'))
                <div class="alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (!$phone)
                <!-- Enter phone to lookup -->
                <div class="login-prompt">
                    <h3 style="font-size:1.2rem;font-weight:800;color:#461500;margin-bottom:8px">Cek Riwayat Reservasi
                    </h3>
                    <p style="font-size:0.9rem;color:#8b7a6b;margin-bottom:24px">Masukkan nomor WhatsApp untuk melihat
                        riwayat reservasi kamu</p>
                    <form method="GET" action="/reservasi/riwayat">
                        <div class="form-group">
                            <label>No. WhatsApp</label>
                            <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx"
                                required>
                        </div>
                        <button type="submit" class="btn-check">
                            <i class="fa-solid fa-magnifying-glass"></i> Cari Reservasi
                        </button>
                    </form>
                </div>
            @elseif ($reservations->isEmpty())
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-regular fa-calendar"></i></div>
                    <h3>Belum Ada Reservasi</h3>
                    <p>Kamu belum memiliki reservasi. Buat reservasi pertamamu sekarang!</p>
                    <a href="{{ route('landing.reservation') }}" class="btn-new-res">
                        <i class="fa-solid fa-calendar-plus"></i> Buat Reservasi
                    </a>
                </div>
            @else
                @foreach ($reservations as $res)
                    @php
                        $si = $res->status_info;
                        $dayNames = [
                            1 => 'Senin',
                            2 => 'Selasa',
                            3 => 'Rabu',
                            4 => 'Kamis',
                            5 => 'Jumat',
                            6 => 'Sabtu',
                            7 => 'Minggu',
                        ];
                        $dayName = $res->date ? $dayNames[$res->date->dayOfWeekIso] ?? '' : '';
                    @endphp
                    <div class="res-card">
                        <div class="res-card-header">
                            <div class="res-date">
                                {{ $dayName }}, {{ $res->date ? $res->date->format('d M Y') : '-' }}
                                <small>{{ $res->start_hour ? \Carbon\Carbon::parse($res->start_hour)->format('H:i') : '' }}
                                    —
                                    {{ $res->end_hour ? \Carbon\Carbon::parse($res->end_hour)->format('H:i') : '' }}</small>
                            </div>
                            <span class="res-status" style="background:{{ $si['bg'] }};color:{{ $si['color'] }}">
                                <i class="fa-solid {{ $si['icon'] }}"></i>
                                {{ $si['label'] }}
                            </span>
                        </div>

                        <div class="res-meta">
                            <div class="res-meta-item">
                                <div class="meta-label">Cabang</div>
                                <div class="meta-value">{{ $res->branch->name ?? '-' }}</div>
                            </div>
                            <div class="res-meta-item">
                                <div class="meta-label">Dokter</div>
                                <div class="meta-value">{{ $res->doctor->name ?? '-' }}</div>
                            </div>
                        </div>

                        @if ($res->services->count() > 0)
                            <div class="res-services">
                                <div class="res-services-label">Perawatan</div>
                                <div class="res-svc-tags">
                                    @foreach ($res->services as $svc)
                                        <span class="res-svc-tag">
                                            {{ $svc->docService->name ?? 'Service' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    </section>

</body>

</html>
