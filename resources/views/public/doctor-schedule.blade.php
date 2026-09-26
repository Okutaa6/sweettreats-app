<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Dokter - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Lihat jadwal praktik dokter gigi Sweet Treats per hari dalam seminggu.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
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
            background: #fdfbf8;
            -webkit-font-smoothing: antialiased;
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Dekorasi lembut di latar */
        body::before {
            content: "";
            position: fixed;
            top: -220px;
            left: 50%;
            transform: translateX(-50%);
            width: 820px;
            height: 460px;
            background: radial-gradient(closest-side, rgba(235, 211, 176, 0.32), rgba(235, 211, 176, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .wrap {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
        }

        /* ===== HEADER ===== */
        .hero {
            text-align: center;
            padding: 52px 20px 30px;
        }

        .hero .brand {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #8F4525;
            margin-bottom: 6px;
        }

        .hero h1 {
            font-size: 2.15rem;
            font-weight: 800;
            color: #461500;
            letter-spacing: -0.5px;
        }

        .hero p {
            margin-top: 10px;
            color: #8a7460;
            font-size: 0.98rem;
        }

        /* ===== FILTER ===== */
        .filters {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
            width: 100%;
        }

        /* Kotak pencarian nama dokter */
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            max-width: 640px;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 14px;
            padding: 4px 18px;
            box-shadow: 0 10px 30px rgba(70, 21, 0, 0.08);
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .search-box:focus-within {
            border-color: #c68a4f;
            box-shadow: 0 10px 30px rgba(70, 21, 0, 0.08), 0 0 0 3px rgba(143, 69, 37, 0.14);
        }

        .search-box i {
            color: #8F4525;
            font-size: 1rem;
        }

        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            color: #461500;
            padding: 12px 0;
            outline: none;
        }

        .search-box input::placeholder {
            color: #b39d88;
            font-weight: 500;
        }

        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            width: 100%;
            max-width: 640px;
        }

        /* Tiap kontrol: label kecil + field */
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1 1 240px;
            min-width: 0;
        }

        .field-label {
            display: flex;
            align-items: center;
            gap: 5px;
            padding-left: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: #8F4525;
        }

        .field-label i {
            color: #8F4525;
            font-size: 0.72rem;
        }

        /* Custom dropdown */
        .cselect {
            position: relative;
        }

        .cselect-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            color: #461500;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 14px;
            padding: 13px 16px;
            cursor: pointer;
            text-align: left;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05);
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .cselect-trigger:hover {
            border-color: #e6c19a;
        }

        .cselect.open .cselect-trigger {
            border-color: #c68a4f;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05), 0 0 0 3px rgba(143, 69, 37, 0.13);
        }

        .cselect-label {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cselect-caret {
            color: #8F4525;
            font-size: 0.72rem;
            transition: transform 0.2s ease;
        }

        .cselect.open .cselect-caret {
            transform: rotate(180deg);
        }

        .cselect.locked .cselect-trigger {
            cursor: default;
            opacity: 0.85;
        }

        .cselect-menu {
            list-style: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 20;
            margin: 0;
            padding: 6px;
            background: #fff;
            border: 1px solid rgba(70, 21, 0, 0.08);
            border-radius: 14px;
            box-shadow: 0 18px 44px rgba(70, 21, 0, 0.16);
            max-height: 264px;
            overflow-y: auto;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .cselect.open .cselect-menu {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .cselect-opt {
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #5a4636;
            cursor: pointer;
            transition: background 0.12s, color 0.12s;
        }

        .cselect-opt:hover {
            background: #fbf3e9;
        }

        .cselect-opt.selected {
            background: linear-gradient(135deg, #8F4525, #572006);
            color: #fff;
        }

        /* ===== SUMMARY + LEGEND ===== */
        .summary {
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            color: #461500;
            margin-bottom: 22px;
        }

        .summary strong {
            color: #8F4525;
        }

        /* ===== NAVIGASI MINGGU ===== */
        .week-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 22px;
        }

        /* Pita panah ‹ rentang tanggal › */
        .week-nav-group {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 999px;
            padding: 4px 6px;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05);
        }

        .week-nav-arrow {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            color: #8F4525;
            font-size: 0.85rem;
            border-radius: 50%;
            cursor: pointer;
            transition: background 0.15s;
        }

        .week-nav-arrow:hover {
            background: #fbf3e9;
        }

        .week-nav-range {
            min-width: 148px;
            text-align: center;
            padding: 0 6px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #461500;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .week-nav-today {
            font-family: inherit;
            font-size: 0.82rem;
            font-weight: 700;
            color: #8F4525;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 999px;
            padding: 9px 18px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05);
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
        }

        .week-nav-today:hover {
            border-color: #e6c19a;
            transform: translateY(-1px);
        }

        /* Saat sedang di minggu berjalan → tombol jadi solid (aktif). */
        .week-nav-today.is-current {
            color: #fff;
            background: linear-gradient(135deg, #8F4525, #572006);
            border-color: transparent;
            box-shadow: 0 8px 22px rgba(87, 32, 6, 0.28);
        }

        /* ===== KALENDER MINGGUAN ===== */
        /* Satu bingkai kalender; di layar sempit bisa digeser horizontal. */
        .week-scroll {
            overflow-x: auto;
            padding-bottom: 34px;
            -webkit-overflow-scrolling: touch;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(var(--cols), minmax(200px, 1fr));
            background: #fff;
            border: 1px solid #eee6da;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(70, 40, 10, 0.05);
        }

        /* Garis pemisah antar hari — tampilan tabel/kalender menyatu. */
        .cal-col {
            display: flex;
            flex-direction: column;
            border-right: 1px solid #efe7db;
        }

        .cal-col:last-child {
            border-right: none;
        }

        /* Header hari — pita cokelat gradient, teks putih. */
        .cal-col-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 16px;
            background: linear-gradient(135deg, #8F4525, #572006);
        }

        .cal-day-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .cal-day {
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #fff;
        }

        .cal-date {
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.72);
            white-space: nowrap;
        }

        .cal-count {
            margin-left: auto;
            font-size: 0.72rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.65);
            white-space: nowrap;
        }

        /* Chevron accordion — hanya tampil di mobile (lihat media query). */
        .cal-chevron {
            display: none;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.8rem;
            transition: transform 0.2s ease;
        }

        .cal-col-body {
            flex: 1;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        /* ===== KARTU DOKTER (ringan tapi jelas terdefinisi) ===== */
        .doc {
            padding: 12px 13px;
            border: 1px solid #e8dcc9;
            border-radius: 12px;
            background: #fffdfa;
            transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
        }

        .doc:hover {
            border-color: #d9a878;
            box-shadow: 0 6px 16px rgba(87, 32, 6, 0.1);
            transform: translateY(-1px);
        }

        .doc-head {
            display: flex;
            align-items: flex-start;
            gap: 11px;
        }

        .doc-avatar,
        .doc-avatar-fallback {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex: 0 0 36px;
        }

        .doc-avatar {
            object-fit: cover;
        }

        .doc-avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(135deg, #a5542e, #7a3213);
        }

        .doc-info {
            flex: 1;
            min-width: 0;
        }

        .doc-name {
            font-size: 0.82rem;
            font-weight: 700;
            color: #2a1c0e;
            line-height: 1.25;
        }

        .doc-spec {
            margin-top: 4px;
            font-size: 0.73rem;
            font-weight: 700;
            color: #8F4525;
        }

        .doc-time {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #4d3d2c;
            font-variant-numeric: tabular-nums;
        }

        .doc-time i {
            color: #8F4525;
            font-size: 0.74rem;
        }

        /* Kolom hari tanpa dokter / semua ter-filter */
        .day-empty {
            padding: 22px 12px;
            text-align: center;
            font-size: 0.76rem;
            font-weight: 600;
            color: #b3a596;
        }

        .day-empty i {
            display: block;
            font-size: 1.3rem;
            color: #e0cbb6;
            margin-bottom: 8px;
        }

        .day-empty[hidden] {
            display: none;
        }

        /* ===== EMPTY STATE (global) ===== */
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #8a7460;
        }

        .empty i {
            font-size: 2.5rem;
            color: #e0cbb6;
            margin-bottom: 14px;
        }

        .empty p {
            font-size: 1rem;
            font-weight: 500;
        }

        /* Di layar sempit: kolom hari ditumpuk vertikal (bukan digeser). */
        @media (max-width: 760px) {
            .week-scroll {
                overflow-x: visible;
            }

            /* Mobile: bingkai dilepas, tiap hari jadi kartu terpisah dgn jarak. */
            .calendar {
                grid-template-columns: 1fr;
                row-gap: 16px;
                border: none;
                border-radius: 0;
                overflow: visible;
                box-shadow: none;
                background: transparent;
            }

            .cal-col {
                border: 1px solid #eee6da;
                border-radius: 14px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 4px 18px rgba(70, 40, 10, 0.05);
            }

            .cal-col:last-child {
                border: 1px solid #eee6da;
            }

            /* Accordion: header bisa di-tap, chevron tampil, body tersembunyi saat tertutup. */
            .cal-col-head {
                cursor: pointer;
            }

            .cal-chevron {
                display: inline-block;
            }

            /* Transisi halus buka-tutup (bukan display:none instan). */
            .cal-col.collapsed .cal-col-body {
                max-height: 0;
                padding-top: 0;
                padding-bottom: 0;
                opacity: 0;
            }

            .cal-col:not(.collapsed) .cal-chevron {
                transform: rotate(180deg);
            }

            /* Ringkas untuk mobile: header slim, kartu & avatar lebih padat. */
            .cal-col-head {
                padding: 10px 14px;
            }

            .cal-day {
                font-size: 0.84rem;
            }

            .cal-col-body {
                padding: 8px;
                gap: 7px;
                max-height: 1200px;
                overflow: hidden;
                opacity: 1;
                transition: max-height 0.32s ease, opacity 0.25s ease,
                    padding-top 0.32s ease, padding-bottom 0.32s ease;
            }

            .doc {
                padding: 10px 11px;
            }

            .doc-head {
                gap: 10px;
            }

            .doc-avatar,
            .doc-avatar-fallback {
                width: 32px;
                height: 32px;
                flex: 0 0 32px;
            }

            .doc-avatar-fallback {
                font-size: 0.88rem;
            }

            .doc-time {
                margin-top: 6px;
            }
        }

        @media (max-width: 560px) {
            .wrap {
                padding: 0 14px;
            }

            .hero {
                padding: 34px 14px 20px;
            }

            .hero h1 {
                font-size: 1.65rem;
            }

            .filter-controls .field {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body>
    @php
        $dayFull = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $monthId = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $branchName = optional($branches->firstWhere('id', $branchId))->name;

        // $weekStart datang dari controller (sudah digeser sesuai offset minggu).
        $weekEnd = $weekStart->copy()->addDays(6);
        $sameMonth = $weekStart->month === $weekEnd->month;
        $sameYear = $weekStart->year === $weekEnd->year;
        $startLabel = $weekStart->day
            . (!$sameMonth ? ' ' . $monthId[$weekStart->month] : '')
            . (!$sameYear ? ' ' . $weekStart->year : '');
        $rangeLabel = $startLabel . ' – ' . $weekEnd->day . ' ' . $monthId[$weekEnd->month] . ' ' . $weekEnd->year;
    @endphp

    <div class="wrap">
        <header class="hero">
            <div class="brand">Sweet Treats Dental Clinic</div>
            <h1>Jadwal Dokter</h1>
            <p>Informasi jadwal praktik untuk memastikan kamu mendapatkan perawatan tepat waktu.</p>
        </header>

        <div class="filters">
            {{-- Pencarian nama dokter (filter instan di sisi klien). --}}
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="docSearch" placeholder="Cari nama dokter..."
                    aria-label="Cari nama dokter" oninput="applyFilter()"
                    onkeydown="if (event.key === 'Enter') event.preventDefault()">
            </div>

            {{-- Baris kontrol: jenis dokter (klien) · cabang (server). --}}
            <div class="filter-controls">
                {{-- Jenis dokter: Semua / Umum / Spesialis (filter instan di sisi klien). --}}
                <div class="field">
                    <span class="field-label"><i class="fa-solid fa-user-doctor"></i> Jenis Dokter</span>
                    <div class="cselect" id="specFilter" data-value="" data-action="filter">
                        <button type="button" class="cselect-trigger" onclick="toggleSelect(this)">
                            <span class="cselect-label">Semua</span>
                            <i class="fa-solid fa-chevron-down cselect-caret"></i>
                        </button>
                        <ul class="cselect-menu" role="listbox">
                            <li class="cselect-opt selected" data-value="" onclick="chooseOpt(this)">Semua</li>
                            <li class="cselect-opt" data-value="umum" onclick="chooseOpt(this)">Dokter Umum</li>
                            <li class="cselect-opt" data-value="spesialis" onclick="chooseOpt(this)">Dokter Spesialis</li>
                        </ul>
                    </div>
                </div>

                {{-- Cabang (navigasi server). Saat terkunci → tampil statis. --}}
                <div class="field">
                    <span class="field-label"><i class="fa-solid fa-map-marker-alt"></i> Cabang</span>
                    @if ($lockBranch)
                        <div class="cselect locked">
                            <button type="button" class="cselect-trigger" disabled>
                                <span class="cselect-label">{{ $branches->first()->name }}</span>
                            </button>
                        </div>
                    @else
                        <div class="cselect" data-action="branch" data-value="{{ $branchId }}">
                            <button type="button" class="cselect-trigger" onclick="toggleSelect(this)">
                                <span class="cselect-label">{{ $branchName }}</span>
                                <i class="fa-solid fa-chevron-down cselect-caret"></i>
                            </button>
                            <ul class="cselect-menu" role="listbox">
                                @foreach ($branches as $branch)
                                    <li class="cselect-opt {{ $branch->id === $branchId ? 'selected' : '' }}"
                                        data-value="{{ $branch->id }}" onclick="chooseOpt(this)">{{ $branch->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Navigasi antar-minggu: geser tanggal yang tampil (pola jadwal tetap). --}}
        <div class="week-nav">
            <div class="week-nav-group">
                <button type="button" class="week-nav-arrow" aria-label="Minggu sebelumnya"
                    onclick="navigateParam('minggu', {{ $weekOffset - 1 }})">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="week-nav-range">{{ $rangeLabel }}</span>
                <button type="button" class="week-nav-arrow" aria-label="Minggu berikutnya"
                    onclick="navigateParam('minggu', {{ $weekOffset + 1 }})">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
            <button type="button" class="week-nav-today {{ $weekOffset === 0 ? 'is-current' : '' }}"
                onclick="navigateParam('minggu', 0)">Minggu Ini</button>
        </div>

        @if (empty($week))
            <div class="empty">
                <i class="fa-regular fa-calendar-times"></i>
                <p>Belum ada jadwal dokter untuk cabang ini.</p>
            </div>
        @else
            <p class="summary">
                <strong>{{ $doctorCount }} dokter</strong> praktik
                @if ($branchName) di {{ $branchName }} @endif
            </p>

            <div class="week-scroll">
                <div class="calendar" style="--cols: {{ count($week) }}">
                    @foreach ($week as $day)
                        @php $d = $weekStart->copy()->addDays($day->iso - 1); @endphp
                        <div class="cal-col {{ $loop->first ? '' : 'collapsed' }}">
                            <div class="cal-col-head" onclick="toggleDay(this)">
                                <div class="cal-day-group">
                                    <span class="cal-day">{{ $dayFull[$day->iso] ?? '-' }}</span>
                                    <span class="cal-date">{{ $d->day }} {{ $monthId[$d->month] }}</span>
                                </div>
                                <span class="cal-count" data-total="{{ count($day->doctors) }}">{{ count($day->doctors) }} dokter</span>
                                <i class="fa-solid fa-chevron-down cal-chevron"></i>
                            </div>
                            <div class="cal-col-body">
                                @foreach ($day->doctors as $doc)
                                    <div class="doc" data-name="{{ mb_strtolower($doc->name) }}"
                                        data-type="{{ $doc->specialization === 'Dokter Gigi Umum' ? 'umum' : 'spesialis' }}">
                                        <div class="doc-head">
                                            @if ($doc->photo_url)
                                                <img class="doc-avatar" src="{{ $doc->photo_url }}" alt="{{ $doc->name }}" loading="lazy">
                                            @else
                                                <div class="doc-avatar-fallback">{{ mb_strtoupper(mb_substr(preg_replace('/^(drg\.?|dr\.?)\s+/iu', '', $doc->name), 0, 1)) }}</div>
                                            @endif
                                            <div class="doc-info">
                                                <div class="doc-name">{{ $doc->name }}</div>
                                                <div class="doc-spec">{{ $doc->specialization }}</div>
                                                <div class="doc-time">
                                                    <i class="fa-regular fa-clock"></i>{{ implode(' · ', $doc->ranges) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Placeholder saat semua dokter di kolom ini ter-filter (via JS). --}}
                                <div class="day-empty" data-filter-empty hidden>
                                    <i class="fa-regular fa-face-frown"></i>
                                    Tidak ada dokter yang cocok
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Muncul via JS bila pencarian/jenis dokter tidak menemukan hasil sama sekali. --}}
            <div class="empty" id="noResults" hidden>
                <i class="fa-regular fa-face-frown"></i>
                <p>Tidak ada dokter yang cocok dengan pencarianmu.</p>
            </div>
        @endif
    </div>

    <script>
        // ===== Accordion per hari (hanya di mobile) =====
        // Tap header hari untuk buka/tutup. Eksklusif: membuka satu hari otomatis
        // menutup hari lain. Di desktop tak berefek (body selalu tampil), jadi
        // klik header diabaikan di layar lebar.
        function toggleDay(head) {
            if (window.innerWidth > 760) return;
            var col = head.closest('.cal-col');
            var willOpen = col.classList.contains('collapsed');
            // Tutup semua dulu, lalu buka yang diklik kalau tadinya tertutup.
            document.querySelectorAll('.cal-col').forEach(function (c) {
                c.classList.add('collapsed');
            });
            if (willOpen) col.classList.remove('collapsed');
        }

        // ===== Filter nama dokter + jenis dokter (client-side) =====
        function applyFilter() {
            var search = document.getElementById('docSearch');
            var specSel = document.getElementById('specFilter');
            var q = (search ? search.value : '').trim().toLowerCase();
            var type = specSel ? specSel.getAttribute('data-value') : '';

            var totalVisible = 0;

            // Sembunyikan/tampilkan tiap mini-card sesuai kecocokan.
            document.querySelectorAll('.cal-col').forEach(function (col) {
                var visibleInCol = 0;
                col.querySelectorAll('.doc').forEach(function (doc) {
                    var name = doc.getAttribute('data-name') || '';
                    var docType = doc.getAttribute('data-type') || '';
                    var match = (!q || name.indexOf(q) !== -1) && (!type || docType === type);
                    doc.style.display = match ? '' : 'none';
                    if (match) visibleInCol++;
                });
                totalVisible += visibleInCol;

                // Update angka di header kolom + placeholder "tidak cocok".
                var count = col.querySelector('.cal-count');
                if (count) {
                    var total = count.getAttribute('data-total');
                    count.textContent = (q || type)
                        ? visibleInCol + '/' + total + ' dokter'
                        : total + ' dokter';
                }
                var emptyPh = col.querySelector('[data-filter-empty]');
                if (emptyPh) emptyPh.hidden = visibleInCol !== 0;

                // Mobile: saat filter aktif, buka hari yang ada hasilnya biar terlihat.
                if (window.innerWidth <= 760 && (q || type)) {
                    col.classList.toggle('collapsed', visibleInCol === 0);
                }
            });

            var noResults = document.getElementById('noResults');
            if (noResults) noResults.hidden = totalVisible !== 0;

            // Mobile: filter dibersihkan → balik ke default (hanya hari teratas terbuka).
            if (window.innerWidth <= 760 && !q && !type) {
                document.querySelectorAll('.cal-col').forEach(function (c, i) {
                    c.classList.toggle('collapsed', i !== 0);
                });
            }
        }

        // ===== Custom dropdown (halus) =====
        function closeAllSelects(except) {
            document.querySelectorAll('.cselect.open').forEach(function (s) {
                if (s !== except) s.classList.remove('open');
            });
        }

        function toggleSelect(trigger) {
            var sel = trigger.closest('.cselect');
            var willOpen = !sel.classList.contains('open');
            closeAllSelects(sel);
            sel.classList.toggle('open', willOpen);
        }

        function chooseOpt(opt) {
            var sel = opt.closest('.cselect');
            var value = opt.getAttribute('data-value');
            sel.setAttribute('data-value', value);
            sel.querySelector('.cselect-label').textContent = opt.textContent;
            sel.querySelectorAll('.cselect-opt').forEach(function (o) {
                o.classList.toggle('selected', o === opt);
            });
            sel.classList.remove('open');

            var action = sel.getAttribute('data-action');
            if (action === 'filter') {
                applyFilter();
            } else if (action === 'branch') {
                navigateParam('cabang', value);
            }
        }

        // Ganti 1 query-param lalu reload — param lain tetap dipertahankan.
        function navigateParam(key, value) {
            var params = new URLSearchParams(window.location.search);
            if (value) {
                params.set(key, value);
            } else {
                params.delete(key);
            }
            window.location.search = params.toString();
        }

        // Tutup dropdown saat klik di luar.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.cselect')) closeAllSelects(null);
        });
    </script>
</body>

</html>
