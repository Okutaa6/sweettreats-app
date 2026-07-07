<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Dokter - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Lihat jadwal praktik dokter gigi Sweet Treats di tiap cabang.">

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
            background: #fffaf5;
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
            width: 900px;
            height: 520px;
            background: radial-gradient(closest-side, rgba(235, 211, 176, 0.6), rgba(235, 211, 176, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        img {
            max-width: 100%;
            display: block;
        }

        .wrap {
            width: 100%;
            max-width: 1080px;
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

        .hero .badge-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            background: linear-gradient(135deg, #8F4525, #572006);
            box-shadow: 0 10px 26px rgba(87, 32, 6, 0.32);
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
            margin-bottom: 26px;
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
            border-radius: 999px;
            padding: 4px 22px;
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
            flex: 1 1 180px;
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

        /* Field tanggal (native date, dibungkus agar seragam) */
        .date-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 14px;
            padding: 0 16px;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05);
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .date-box:focus-within {
            border-color: #c68a4f;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05), 0 0 0 3px rgba(143, 69, 37, 0.13);
        }

        .date-box i {
            color: #8F4525;
            font-size: 0.92rem;
        }

        .date-box input {
            width: 100%;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            color: #461500;
            padding: 13px 0;
            cursor: pointer;
            outline: none;
        }

        .date-box input::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.55;
        }

        /* ===== SUMMARY + LEGEND + HINT ===== */
        .summary {
            text-align: center;
            font-size: 0.95rem;
            font-weight: 600;
            color: #461500;
            margin-bottom: 14px;
        }

        .summary strong {
            color: #8F4525;
        }

        .legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .hint {
            text-align: center;
            font-size: 0.8rem;
            color: #a08a76;
            margin-bottom: 30px;
        }

        .hint i {
            color: #d9a878;
            margin-right: 4px;
        }

        /* ===== GRID DOKTER ===== */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
            padding-bottom: 40px;
            align-items: start;
        }

        .card {
            position: relative;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 6px 22px rgba(70, 21, 0, 0.07);
            border: 1px solid rgba(70, 21, 0, 0.05);
            overflow: hidden;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .card::before {
            content: "";
            display: block;
            height: 4px;
            background: linear-gradient(90deg, #8F4525, #572006);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 38px rgba(70, 21, 0, 0.13);
        }

        .card-head {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 16px 12px;
        }

        .avatar,
        .avatar-fallback {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            flex: 0 0 50px;
        }

        .avatar {
            object-fit: cover;
            border: 2px solid #eeddc7;
        }

        .avatar-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #8F4525;
            background: linear-gradient(135deg, #f5e7d5, #ecd3b6);
            border: 2px solid #ecd3b6;
        }

        .card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #461500;
            line-height: 1.3;
            text-align: left;
        }

        .card h3 span {
            display: inline-block;
            margin-top: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: #8F4525;
            background: #f4e7d6;
            padding: 3px 9px;
            border-radius: 999px;
        }

        .card-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 8px;
            padding: 0 16px 12px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #a08a76;
        }

        .card-meta .branch-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #8F4525;
            background: #f4e7d6;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 0.72rem;
        }

        .card-meta .branch-pill i {
            color: #8F4525;
        }

        .card-meta i {
            color: #d9a878;
        }

        /* Tab cabang di dalam kartu (dokter multi-cabang) */
        .branch-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 0 16px 12px;
        }

        .branch-tab {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            color: #8F4525;
            background: #fff;
            border: 1.5px solid #e6c19a;
            border-radius: 999px;
            padding: 7px 15px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(70, 21, 0, 0.08);
            transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s, transform 0.12s;
        }

        .branch-tab i {
            font-size: 0.72rem;
        }

        .branch-tab:hover {
            background: #fff3e6;
            border-color: #d99a5a;
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(70, 21, 0, 0.12);
        }

        .branch-tab.active {
            background: linear-gradient(135deg, #8F4525, #572006);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 4px 12px rgba(87, 32, 6, 0.28);
        }

        .branch-panel[hidden] {
            display: none;
        }

        /* ===== ACCORDION JADWAL PER HARI ===== */
        .day-acc {
            border-top: 1px solid rgba(70, 21, 0, 0.07);
        }

        .acc-item + .acc-item {
            border-top: 1px solid rgba(70, 21, 0, 0.07);
        }

        .acc-head {
            width: 100%;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            background: #fffdfb;
            border: none;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s;
        }

        .acc-head:hover {
            background: #fbf3e9;
        }

        .acc-item.open .acc-head {
            background: #fff8f1;
        }

        .acc-day {
            font-size: 0.9rem;
            font-weight: 700;
            color: #461500;
            margin-right: auto;
        }

        .acc-chevron {
            color: #b07a45;
            font-size: 0.8rem;
            transition: transform 0.2s ease;
            flex: 0 0 auto;
        }

        .acc-item.open .acc-chevron {
            transform: rotate(180deg);
        }

        .acc-body {
            padding: 12px 16px 16px;
            background: #fffdfb;
        }

        .acc-body[hidden] {
            display: none;
        }

        .acc-range {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #5a4636;
            font-variant-numeric: tabular-nums;
            margin-bottom: 12px;
        }

        .acc-range i {
            color: #8F4525;
            font-size: 0.8rem;
        }

        .slots {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .slot {
            font-size: 0.76rem;
            font-weight: 700;
            padding: 6px 11px;
            border-radius: 8px;
            background: #eaf7ee;
            color: #1f7a3d;
            border: 1px solid #d3ecdb;
            font-variant-numeric: tabular-nums;
        }

        .slot.is-booked {
            background: #f4efe9;
            color: #b3a596;
            border-color: #ece3da;
            text-decoration: line-through;
        }

        .slot.is-past {
            background: #f7f3ef;
            color: #c8bbad;
            border-color: #efe7de;
        }

        .slots-foot {
            margin-top: 11px;
            font-size: 0.74rem;
            font-weight: 600;
            color: #1f7a3d;
        }

        .slots-foot i {
            margin-right: 4px;
        }

        .slots-foot.none {
            color: #b3a596;
        }

        .badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            white-space: nowrap;
        }

        .badge.available {
            color: #1f7a3d;
            background: #e4f6ea;
        }

        .badge.almost {
            color: #b06a00;
            background: #fff0d6;
        }

        .badge.full {
            color: #b02a2a;
            background: #fbe1e1;
        }

        /* ===== EMPTY STATE ===== */
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

        /* ===== PAGINATION ===== */
        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 0 46px;
        }

        .page-btn {
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 700;
            color: #8F4525;
            background: #fff;
            border: 1.5px solid rgba(70, 21, 0, 0.1);
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(70, 21, 0, 0.05);
            transition: border-color 0.15s, background 0.15s, color 0.15s, transform 0.12s;
        }

        .page-btn:hover:not(:disabled) {
            border-color: #e6c19a;
            transform: translateY(-2px);
        }

        .page-btn.active {
            background: linear-gradient(135deg, #8F4525, #572006);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 6px 16px rgba(87, 32, 6, 0.28);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ===== FOOTER ===== */
        .foot {
            text-align: center;
            padding: 26px 20px 40px;
            color: #b09a86;
            font-size: 0.8rem;
            border-top: 1px solid rgba(70, 21, 0, 0.06);
            margin-top: auto;
        }

        .foot strong {
            color: #8a5a2a;
        }

        .foot-text {
            margin-bottom: 18px;
        }

        .reserve-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            font-size: 0.92rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            background: linear-gradient(135deg, #8F4525, #572006);
            border-radius: 999px;
            box-shadow: 0 8px 22px rgba(87, 32, 6, 0.28);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .reserve-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(87, 32, 6, 0.36);
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

            .grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .filter-controls .field {
                flex: 1 1 100%;
            }
        }
    </style>
</head>

<body>
    @php
        $dayLabels = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $dayFull = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $monthId = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
        $statusLabels = ['available' => 'Masih tersedia', 'almost' => 'Hampir penuh', 'full' => 'Penuh'];
        $selectedLabel = $selectedDate
            ? $dayFull[$selectedDate->dayOfWeekIso] . ', ' . $selectedDate->format('d') . ' ' . $monthId[$selectedDate->month] . ' ' . $selectedDate->format('Y')
            : null;
        $branchName = optional($branches->firstWhere('id', $branchId))->name;
    @endphp

    <div class="wrap">
        <header class="hero">
            <div class="badge-icon"><i class="fa-solid fa-tooth"></i></div>
            <div class="brand">Sweet Treats Dental Clinic</div>
            <h1>Jadwal Dokter</h1>
            <p>Cek jadwal praktik dokter kami di tiap cabang</p>
        </header>

        <div class="filters">
            {{-- Pencarian nama dokter (filter instan di sisi klien). --}}
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="docSearch" placeholder="Cari nama dokter..."
                    aria-label="Cari nama dokter" oninput="applyFilter()"
                    onkeydown="if (event.key === 'Enter') event.preventDefault()">
            </div>

            {{-- Baris kontrol: jenis dokter (klien) · cabang (server) · tanggal (server). --}}
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
                        <div class="cselect" data-action="branch" data-value="{{ $allBranches ? 'all' : $branchId }}">
                            <button type="button" class="cselect-trigger" onclick="toggleSelect(this)">
                                <span class="cselect-label">{{ $allBranches ? 'Semua cabang' : $branchName }}</span>
                                <i class="fa-solid fa-chevron-down cselect-caret"></i>
                            </button>
                            <ul class="cselect-menu" role="listbox">
                                <li class="cselect-opt {{ $allBranches ? 'selected' : '' }}" data-value="all" onclick="chooseOpt(this)">Semua cabang</li>
                                @foreach ($branches as $branch)
                                    <li class="cselect-opt {{ !$allBranches && $branch->id === $branchId ? 'selected' : '' }}"
                                        data-value="{{ $branch->id }}" onclick="chooseOpt(this)">{{ $branch->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Tanggal (navigasi server, mempertahankan cabang di URL). --}}
                <div class="field">
                    <span class="field-label"><i class="fa-solid fa-calendar-day"></i> Tanggal</span>
                    <div class="date-box">
                        <i class="fa-solid fa-calendar-day"></i>
                        <input type="date" value="{{ $selectedStr }}" min="{{ $todayStr }}"
                            max="{{ $maxStr }}" onchange="goDate(this.value)" aria-label="Pilih tanggal">
                    </div>
                </div>
            </div>
        </div>

        @if ($doctors->isEmpty())
            <div class="empty">
                <i class="fa-regular fa-calendar-times"></i>
                @if ($mode === 'tanggal')
                    <p>Tidak ada dokter yang praktik pada {{ $selectedLabel }}.</p>
                @elseif ($allBranches)
                    <p>Belum ada jadwal dokter.</p>
                @else
                    <p>Belum ada jadwal dokter untuk cabang ini.</p>
                @endif
            </div>
        @else
            <p class="summary">
                <strong>{{ $doctors->count() }} dokter</strong> praktik
                @if ($allBranches) di semua cabang
                @elseif ($branchName) di {{ $branchName }} @endif
                @if ($mode === 'tanggal') pada {{ $selectedLabel }} @endif
            </p>

            @if ($mode === 'tanggal')
                <p class="hint"><i class="fa-solid fa-calendar-day"></i> Jadwal pada {{ $selectedLabel }}</p>
            @endif

            <div class="grid">
                @foreach ($doctors as $doctor)
                    <div class="card" data-name="{{ mb_strtolower($doctor->name) }}"
                        data-type="{{ $doctor->specialization === 'Dokter Gigi Umum' ? 'umum' : 'spesialis' }}">
                        <div class="card-head">
                            @if ($doctor->photo_url)
                                <img class="avatar" src="{{ $doctor->photo_url }}" alt="{{ $doctor->name }}"
                                    loading="lazy">
                            @else
                                <div class="avatar-fallback">{{ mb_strtoupper(mb_substr(preg_replace('/^(drg\.?|dr\.?)\s+/iu', '', $doctor->name), 0, 1)) }}</div>
                            @endif
                            <h3>{{ $doctor->name }}<span>{{ $doctor->specialization }}</span></h3>
                        </div>

                        @php $multiBranch = count($doctor->branches) > 1; @endphp

                        {{-- Tab cabang di dalam kartu (hanya bila dokter praktik di >1 cabang). --}}
                        @if ($multiBranch)
                            <div class="branch-tabs" role="tablist">
                                @foreach ($doctor->branches as $bi => $br)
                                    <button type="button" class="branch-tab {{ $bi === 0 ? 'active' : '' }}"
                                        data-branch="{{ $bi }}" onclick="selectBranch(this)">
                                        <i class="fa-solid fa-location-dot"></i> {{ $br->branch_name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @foreach ($doctor->branches as $bi => $br)
                            <div class="branch-panel" data-branch="{{ $bi }}" @if ($bi !== 0) hidden @endif>
                                <div class="card-meta">
                                    {{-- Pill cabang hanya kalau TIDAK ada tab (dokter 1 cabang di mode "Semua cabang"); kalau ada tab, cabang sudah jelas dari tab aktif. --}}
                                    @if ($allBranches && !$multiBranch)
                                        <span class="branch-pill"><i class="fa-solid fa-location-dot"></i> {{ $br->branch_name }}</span>
                                    @endif
                                    <span><i class="fa-regular fa-calendar-check"></i>
                                        Praktik {{ count($br->days) }} hari @if ($mode !== 'tanggal') per minggu @endif</span>
                                </div>

                                <div class="day-acc">
                                    @foreach ($br->days as $entry)
                                        @php
                                            $freeCount = 0;
                                            foreach ($entry['slots'] as $s) {
                                                if (!empty($s['available'])) {
                                                    $freeCount++;
                                                }
                                            }
                                            // Semua accordion tertutup saat halaman pertama dibuka.
                                            $isOpen = false;
                                        @endphp
                                        <div class="acc-item {{ $isOpen ? 'open' : '' }}" data-day="{{ $entry['day'] }}">
                                            <button type="button" class="acc-head" onclick="toggleAcc(this)"
                                                aria-expanded="{{ $isOpen ? 'true' : 'false' }}">
                                                <span class="acc-day">{{ $dayFull[$entry['day']] ?? '-' }}</span>
                                                @if ($entry['status'])
                                                    <span class="badge {{ $entry['status'] }}">{{ $statusLabels[$entry['status']] }}</span>
                                                @endif
                                                <i class="fa-solid fa-chevron-down acc-chevron"></i>
                                            </button>
                                            <div class="acc-body" @unless ($isOpen) hidden @endunless>
                                                <div class="acc-range"><i class="fa-regular fa-clock"></i>{{ implode(' · ', $entry['ranges']) }}</div>
                                                <div class="slots">
                                                    @foreach ($entry['slots'] as $slot)
                                                        <span
                                                            class="slot @if ($slot['available']) is-free @elseif ($slot['is_past']) is-past @else is-booked @endif">{{ $slot['time'] }}</span>
                                                    @endforeach
                                                </div>
                                                @if ($freeCount > 0)
                                                    <div class="slots-foot"><i class="fa-solid fa-circle-check"></i>{{ $freeCount }} slot masih tersedia</div>
                                                @else
                                                    <div class="slots-foot none"><i class="fa-solid fa-circle-xmark"></i>Slot penuh untuk hari ini</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Muncul via JS bila pencarian/jenis dokter tidak menemukan hasil. --}}
            <div class="empty" id="noResults" hidden>
                <i class="fa-regular fa-face-frown"></i>
                <p>Tidak ada dokter yang cocok dengan pencarianmu.</p>
            </div>

            {{-- Pagination (dibangun via JS, hanya tampil bila > 1 halaman). --}}
            <nav class="pagination" id="pagination" aria-label="Navigasi halaman dokter"></nav>
        @endif

        <footer class="foot">
            <p class="foot-text"><strong>Sweet Treats Dental Clinic</strong> &middot; Jadwal dapat berubah sewaktu-waktu.<br>
                Hubungi nomor cabang untuk konfirmasi ketersediaan.</p>
            <a class="reserve-btn" href="https://app.sweettreats.id/reservasi" target="_blank" rel="noopener">
                <i class="fa-solid fa-calendar-check"></i> Buat Reservasi
            </a>
        </footer>
    </div>

    <script>
        function toggleAcc(btn) {
            var item = btn.closest('.acc-item');
            var body = item.querySelector('.acc-body');
            var willOpen = !item.classList.contains('open');

            // Accordion: buka satu, tutup semua hari lain (termasuk di kartu dokter lain).
            document.querySelectorAll('.acc-item.open').forEach(function (other) {
                if (other !== item) {
                    other.classList.remove('open');
                    other.querySelector('.acc-body').hidden = true;
                    other.querySelector('.acc-head').setAttribute('aria-expanded', 'false');
                }
            });

            item.classList.toggle('open', willOpen);
            body.hidden = !willOpen;
            btn.setAttribute('aria-expanded', String(willOpen));
        }

        // Pindah tab cabang di dalam kartu dokter (untuk dokter multi-cabang).
        function selectBranch(btn) {
            var card = btn.closest('.card');
            var idx = btn.getAttribute('data-branch');
            card.querySelectorAll('.branch-tab').forEach(function (t) {
                t.classList.toggle('active', t === btn);
            });
            card.querySelectorAll('.branch-panel').forEach(function (p) {
                p.hidden = p.getAttribute('data-branch') !== idx;
            });
        }

        // ===== Filter + pagination (client-side) =====
        var PAGE_SIZE = 6;   // jumlah kartu dokter per halaman
        var currentPage = 1;

        // Kartu yang cocok dgn pencarian nama + jenis dokter (umum/spesialis).
        function matchingCards() {
            var search = document.getElementById('docSearch');
            var specSel = document.getElementById('specFilter');
            var q = (search ? search.value : '').trim().toLowerCase();
            var type = specSel ? specSel.getAttribute('data-value') : '';

            return Array.prototype.filter.call(document.querySelectorAll('.grid .card'), function (card) {
                var name = card.getAttribute('data-name') || '';
                var cardType = card.getAttribute('data-type') || '';
                return (!q || name.indexOf(q) !== -1) && (!type || cardType === type);
            });
        }

        // Tampilkan hanya kartu pd halaman aktif; kartu lain disembunyikan.
        function renderPage() {
            var matches = matchingCards();
            var totalPages = Math.max(1, Math.ceil(matches.length / PAGE_SIZE));
            if (currentPage > totalPages) currentPage = totalPages;

            var start = (currentPage - 1) * PAGE_SIZE;
            var end = start + PAGE_SIZE;

            // Sembunyikan semua, lalu tampilkan yg cocok & masuk rentang halaman.
            document.querySelectorAll('.grid .card').forEach(function (card) {
                card.style.display = 'none';
            });
            matches.forEach(function (card, i) {
                card.style.display = (i >= start && i < end) ? '' : 'none';
            });

            var noResults = document.getElementById('noResults');
            if (noResults) noResults.hidden = matches.length !== 0;

            renderPagination(totalPages);
        }

        // Reset ke halaman 1 tiap filter berubah, lalu render ulang.
        function applyFilter() {
            currentPage = 1;
            renderPage();
        }

        function gotoPage(n) {
            currentPage = n;
            renderPage();
            var grid = document.querySelector('.grid');
            if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Bangun tombol Prev · nomor halaman · Next. Sembunyi bila cuma 1 halaman.
        function renderPagination(totalPages) {
            var nav = document.getElementById('pagination');
            if (!nav) return;
            nav.innerHTML = '';
            if (totalPages <= 1) return;

            var makeBtn = function (label, page, opts) {
                opts = opts || {};
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'page-btn' + (opts.active ? ' active' : '') + (opts.nav ? ' nav' : '');
                b.innerHTML = label;
                if (opts.disabled) {
                    b.disabled = true;
                } else {
                    b.onclick = function () { gotoPage(page); };
                }
                nav.appendChild(b);
            };

            makeBtn('<i class="fa-solid fa-chevron-left"></i>', currentPage - 1, {
                nav: true, disabled: currentPage === 1
            });
            for (var p = 1; p <= totalPages; p++) {
                makeBtn(String(p), p, { active: p === currentPage });
            }
            makeBtn('<i class="fa-solid fa-chevron-right"></i>', currentPage + 1, {
                nav: true, disabled: currentPage === totalPages
            });
        }

        // Render awal (pasang pagination saat halaman dimuat).
        renderPage();

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

        // Ganti 1 query-param lalu reload — param lain (mis. tanggal/cabang) tetap dipertahankan.
        function navigateParam(key, value) {
            var params = new URLSearchParams(window.location.search);
            if (value) {
                params.set(key, value);
            } else {
                params.delete(key);
            }
            window.location.search = params.toString();
        }

        function goDate(value) {
            navigateParam('tanggal', value);
        }

        // Tutup dropdown saat klik di luar.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.cselect')) closeAllSelects(null);
        });
    </script>
</body>

</html>
