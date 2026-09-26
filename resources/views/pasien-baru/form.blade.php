<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Pasien Baru — {{ $branch->name }} · Sweet Treats Dental Clinic</title>
    <meta name="description" content="Daftar sebagai pasien baru Sweet Treats Dental Clinic {{ $branch->name }}. Sweet Crew akan menghubungi via WhatsApp untuk mengatur jadwal.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; color: #3a2a1a; background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%); min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .container { max-width: 640px; margin: 0 auto; padding: 0 24px; }

        .navbar { background: rgba(255,250,245,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(70,21,0,0.08); padding: 0 24px; }
        .navbar-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 72px; }
        .navbar-brand { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .navbar-brand span { background: linear-gradient(135deg, #461500, #8b3a00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #461500; color: #461500; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 700; transition: all 0.3s; }
        .btn-back:hover { background: #461500; color: #fff; }

        .form-section { padding: 48px 0 80px; }
        .form-header { text-align: center; margin-bottom: 32px; }
        .form-header .tag { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; background: rgba(70,21,0,0.06); border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: #461500; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .form-header h1 { font-size: 2.1rem; font-weight: 900; color: #461500; margin-bottom: 12px; }
        .form-header p { font-size: 1rem; color: #6b4a30; line-height: 1.6; }
        .form-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(70,21,0,0.08); border: 1px solid rgba(70,21,0,0.04); }

        .branch-banner { display: flex; align-items: center; gap: 14px; padding: 16px 18px; background: linear-gradient(135deg,#fef5ed,#fef0e2); border: 1px solid rgba(70,21,0,0.1); border-radius: 16px; margin-bottom: 28px; }
        .branch-banner .branch-icon { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #461500, #8b3a00); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .branch-banner .branch-label { font-size: 0.72rem; font-weight: 700; color: #8b7a6b; text-transform: uppercase; letter-spacing: 0.5px; }
        .branch-banner .branch-name { font-size: 1.1rem; font-weight: 800; color: #461500; }

        .alert-success { background: linear-gradient(135deg,#e8f5e9,#c8e6c9); border: 1px solid #a5d6a7; border-radius: 16px; padding: 20px 24px; margin-bottom: 28px; display: flex; align-items: center; gap: 14px; color: #2e7d32; font-size: 0.95rem; font-weight: 600; }
        .alert-error { background: linear-gradient(135deg,#ffebee,#ffcdd2); border: 1px solid #ef9a9a; border-radius: 16px; padding: 16px 24px; margin-bottom: 28px; color: #c62828; font-size: 0.9rem; }
        .alert-error ul { margin: 8px 0 0 16px; }

        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #461500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group label .required { color: #c45200; }
        .form-group label .label-opt { font-weight: 500; font-size: 0.72rem; color: #8b7a6b; text-transform: none; letter-spacing: 0; }
        .form-control { width: 100%; padding: 14px 18px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; color: #3a2a1a; transition: all 0.3s; outline: none; }
        .form-control:focus { border-color: #461500; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); }
        textarea.form-control { resize: vertical; min-height: 110px; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23461500' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 01.708 0L8 10.293l5.646-5.647a.5.5 0 01.708.708l-6 6a.5.5 0 01-.708 0l-6-6a.5.5 0 010-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; padding-right: 44px; }
        .tgl-lahir[readonly] { cursor: pointer; background: #fffaf5; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .field-hint { font-size: 0.75rem; color: #8b7a6b; margin-top: 6px; font-weight: 500; }

        .btn-submit { width: 100%; padding: 16px; margin-top: 8px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(70,21,0,0.2); }

        .form-footnote { text-align: center; font-size: 0.78rem; color: #8b7a6b; margin-top: 18px; line-height: 1.5; }

        /* Kode promo — satu kode berlaku untuk semua pasien di submit ini */
        .promo-section { margin-top: 24px; margin-bottom: 0; }
        .promo-row { display: flex; gap: 10px; align-items: stretch; }
        .promo-row .form-control { flex: 1; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-promo { flex-shrink: 0; padding: 0 24px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: all 0.25s; }
        .btn-promo:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(70,21,0,0.18); }
        .btn-promo:disabled { opacity: 0.6; cursor: default; }
        .promo-status { display: none; margin-top: 10px; padding: 12px 16px; border-radius: 12px; font-size: 0.83rem; font-weight: 600; line-height: 1.45; }
        .promo-status.is-shown { display: block; }
        .promo-status--ok { background: linear-gradient(135deg,#e8f5e9,#d3ecd4); border: 1px solid #a5d6a7; color: #2e7d32; }
        .promo-status--bad { background: #ffebee; border: 1px solid #ef9a9a; color: #c62828; }
        .promo-status small { display: block; margin-top: 4px; font-weight: 500; opacity: 0.85; }

        /* Langkah 1: cek nomor */
        .phone-row { display: flex; gap: 10px; align-items: stretch; }
        .phone-row .form-control { flex: 1; }
        .btn-check { flex-shrink: 0; padding: 0 24px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.9rem; font-weight: 800; cursor: pointer; transition: all 0.25s; }
        .btn-check:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(70,21,0,0.18); }
        .phone-note { display: none; align-items: center; gap: 10px; margin-top: 12px; padding: 12px 16px; background: linear-gradient(135deg,#e8f5e9,#d3ecd4); border: 1px solid #a5d6a7; border-radius: 12px; color: #2e7d32; font-size: 0.85rem; font-weight: 600; }
        .phone-note.is-shown { display: flex; }
        .phone-error { display: none; margin-top: 8px; color: #c62828; font-size: 0.8rem; font-weight: 600; }
        .phone-error.is-shown { display: block; }

        /* Langkah 2: daftar pasien */
        .patients-section { display: none; margin-top: 28px; }
        .patients-section.is-shown { display: block; }
        .section-title { font-size: 0.85rem; font-weight: 800; color: #461500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .section-title__hint { font-weight: 600; color: #c45200; text-transform: none; letter-spacing: 0; font-size: 0.78rem; }
        .section-sub { font-size: 0.8rem; color: #8b7a6b; margin-bottom: 16px; font-weight: 500; }

        .patient-card { position: relative; background: #fffaf5; border: 1.5px solid rgba(70,21,0,0.12); border-radius: 18px; padding: 22px; margin-bottom: 16px; }
        .patient-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .patient-card__badge { display: inline-flex; align-items: center; gap: 8px; font-size: 0.78rem; font-weight: 800; color: #461500; text-transform: uppercase; letter-spacing: 0.5px; }
        .patient-card__badge .num { width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg,#461500,#8b3a00); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; }
        .btn-remove { background: transparent; border: none; color: #c45200; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 8px; }
        .btn-remove:hover { background: rgba(196,82,0,0.08); }
        .patient-card .form-group:last-child { margin-bottom: 0; }

        .btn-add { width: 100%; padding: 14px; background: transparent; border: 2px dashed rgba(70,21,0,0.28); border-radius: 14px; color: #461500; font-family: 'Montserrat', sans-serif; font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: all 0.25s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-add:hover { border-color: #461500; background: rgba(70,21,0,0.04); }

        /* Modal konfirmasi */
        .modal-overlay { position: fixed; inset: 0; background: rgba(40,15,0,0.5); backdrop-filter: blur(3px); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 1000; }
        .modal-overlay.is-open { display: flex; }
        .modal { background: #fff; border-radius: 24px; width: 100%; max-width: 480px; padding: 32px; box-shadow: 0 30px 80px rgba(40,15,0,0.35); max-height: 90vh; overflow-y: auto; }
        .modal__icon { width: 56px; height: 56px; border-radius: 16px; background: #fbe6d4; color: #8b3a00; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin: 0 auto 16px; }
        .modal__title { text-align: center; font-size: 1.4rem; font-weight: 900; color: #461500; }
        .modal__sub { text-align: center; font-size: 0.85rem; color: #8b7a6b; margin: 4px 0 22px; }
        .modal__row { display: flex; justify-content: space-between; gap: 14px; padding: 13px 0; border-top: 1px solid rgba(70,21,0,0.08); font-size: 0.92rem; }
        .modal__row .k { color: #8b7a6b; font-weight: 600; }
        .modal__row .v { color: #461500; font-weight: 800; text-align: right; }
        .modal__plabel { padding: 13px 0 2px; border-top: 1px solid rgba(70,21,0,0.08); font-size: 0.92rem; color: #8b7a6b; font-weight: 600; }
        .modal__patient { display: flex; justify-content: space-between; gap: 14px; padding: 10px 0; }
        .modal__patient + .modal__patient { border-top: 1px dashed rgba(70,21,0,0.08); }
        .modal__patient .pname { font-weight: 800; color: #461500; }
        .modal__patient .prel { font-size: 0.76rem; color: #8b7a6b; margin-top: 2px; }
        .modal__patient .pkeluhan { text-align: right; color: #6b4a30; font-size: 0.82rem; max-width: 58%; line-height: 1.4; }
        .modal__actions { display: flex; gap: 12px; margin-top: 26px; }
        .modal__actions button { flex: 1; padding: 15px; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-weight: 800; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.25s; }
        .btn-cancel { background: #f2e7d8; color: #6b4a30; }
        .btn-cancel:hover { background: #ecdcc7; }
        .btn-confirm { background: linear-gradient(135deg,#461500,#6b2400); color: #fff; }
        .btn-confirm:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(70,21,0,0.22); }

        @media (max-width: 560px) {
            .container { padding: 0 16px; }
            .form-section { padding: 28px 0 60px; }
            .form-card { padding: 24px 18px; border-radius: 18px; }
            .form-header { margin-bottom: 24px; }
            .form-header h1 { font-size: 1.55rem; }
            .form-header p { font-size: 0.9rem; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .phone-row { flex-direction: column; }
            .btn-check { padding: 14px; }
            .promo-row { flex-direction: column; }
            .btn-promo { padding: 14px; }

            /* Judul section: hint pindah ke baris sendiri biar rapi, ga nyangkut */
            .section-title { font-size: 0.8rem; letter-spacing: 0.3px; line-height: 1.35; }
            .section-title__hint { display: block; margin-top: 3px; }

            .patient-card { padding: 18px 16px; border-radius: 14px; }
            .patient-card__head { margin-bottom: 14px; }

            .modal { padding: 24px 20px; border-radius: 20px; }
            .modal__title { font-size: 1.2rem; }
            .modal__patient .pkeluhan { max-width: 52%; }
            .modal__row, .modal__plabel { font-size: 0.86rem; }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="https://sweettreats.id/" class="navbar-brand">
                <span>Sweet Treats</span>
            </a>
            <a href="https://sweettreats.id/" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>
    </nav>

    <section class="form-section">
        <div class="container">
            <div class="form-header">
                <div class="tag"><i class="fa-solid fa-tooth"></i> Reservasi Pasien Baru</div>
                <h1>Buat Reservasi</h1>
                <p>Isi nomor WhatsApp &amp; data pasien. Sweet Crew akan menghubungi via WhatsApp untuk atur jadwal.</p>
            </div>

            <div class="form-card">
                <div class="branch-banner">
                    <span class="branch-icon"><i class="fa-solid fa-map-marker-alt"></i></span>
                    <div>
                        <div class="branch-label">Cabang</div>
                        <div class="branch-name">{{ $branch->name }}</div>
                    </div>
                </div>

                @if (session('success'))
                    <div class="alert-success">
                        <i class="fa-solid fa-check-circle"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert-error">
                        <strong><i class="fa-solid fa-exclamation-circle"></i> Mohon periksa data berikut:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    // Repopulasi saat validasi gagal. Minimal satu kartu pasien.
                    $oldPatients = old('patients');
                    if (! is_array($oldPatients) || count($oldPatients) === 0) {
                        $oldPatients = [[]];
                    }
                    // Bagian pasien selalu tampil (tak perlu klik "Cek" dulu).
                    // Catatan hijau hanya muncul saat nomor sudah terisi & valid.
                    $notePhone = filled(old('phone')) && ! $errors->has('phone');

                    $relasiOptions = [
                        'diri_sendiri' => 'Diri sendiri',
                        'anak'         => 'Anak',
                        'pasangan'     => 'Pasangan',
                        'orang_tua'    => 'Orang tua',
                        'lainnya'      => 'Lainnya',
                    ];
                @endphp

                <form method="POST" action="{{ route('pasien-baru.store', $branch->slug) }}" id="pasienBaruForm">
                    @csrf

                    {{-- Anti-spam: umpan untuk bot (tak terlihat & tak bisa di-tab)
                         + stempel waktu render untuk menolak submit instan. --}}
                    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true"
                           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;">
                    <input type="hidden" name="ts" value="{{ now()->timestamp }}">

                    {{-- LANGKAH 1 — Nomor WhatsApp --}}
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="phone">Masukkan Nomor WhatsApp Kamu <span class="required">*</span></label>
                        <div class="phone-row">
                            <input type="tel" id="phone" name="phone" class="form-control" inputmode="tel"
                                   value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                            <button type="button" class="btn-check" id="btnCheckPhone">Cek</button>
                        </div>
                        <div class="phone-error {{ $errors->has('phone') ? 'is-shown' : '' }}" id="phoneError">
                            {{ $errors->first('phone') ?: 'Nomor WhatsApp tidak valid.' }}
                        </div>
                        <div class="phone-note {{ $notePhone ? 'is-shown' : '' }}" id="phoneNote">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Nomor siap. Tambahkan data pasien di bawah.</span>
                        </div>
                    </div>

                    {{-- LANGKAH 2 — Data pasien (bisa lebih dari 1) --}}
                    <div class="patients-section is-shown" id="patientsSection">
                        <div class="section-title">Untuk siapa reservasi ini? <span class="section-title__hint">(bisa lebih dari 1)</span></div>
                        <div class="section-sub">Satu nomor bisa mendaftarkan beberapa pasien.</div>

                        <div id="patientList">
                            @foreach ($oldPatients as $idx => $p)
                                <div class="patient-card" data-patient>
                                    <div class="patient-card__head">
                                        <span class="patient-card__badge"><span class="num" data-num>{{ $idx + 1 }}</span> Pasien</span>
                                        <button type="button" class="btn-remove" data-remove style="{{ $loop->first ? 'display:none;' : '' }}">
                                            <i class="fa-solid fa-trash-can"></i> Hapus
                                        </button>
                                    </div>

                                    <div class="form-group">
                                        <label>Nama Lengkap <span class="required">*</span></label>
                                        <input type="text" name="patients[{{ $idx }}][name]" class="form-control"
                                               value="{{ $p['name'] ?? '' }}" placeholder="Nama sesuai identitas" maxlength="255" required>
                                    </div>

                                    {{-- NIK: tanpa maxlength — NIK yang ditempel
                                         dari KTP digital sering membawa spasi,
                                         dan pemotongan diam-diam akan menyimpan
                                         16 digit yang SALAH. Spasi dibuang oleh
                                         JS di bawah, lalu dicek ulang 16 digit
                                         di server. --}}
                                    <div class="form-group">
                                        <label>NIK <span class="required">*</span> <span class="label-opt">(16 digit)</span></label>
                                        <input type="text" name="patients[{{ $idx }}][nik]" class="form-control"
                                               value="{{ $p['nik'] ?? '' }}" inputmode="numeric" autocomplete="off"
                                               pattern="[0-9]{16}" title="NIK harus 16 digit angka."
                                               placeholder="16 digit NIK — wajib untuk data Satu Sehat" required>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Pasien Ini Adalah</label>
                                            <select name="patients[{{ $idx }}][relasi]" class="form-control">
                                                @foreach ($relasiOptions as $val => $lbl)
                                                    <option value="{{ $val }}" @selected(($p['relasi'] ?? 'diri_sendiri') === $val)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Jenis Kelamin</label>
                                            <select name="patients[{{ $idx }}][jenis_kelamin]" class="form-control">
                                                <option value="">Pilih...</option>
                                                <option value="L" @selected(($p['jenis_kelamin'] ?? '') === 'L')>Laki-laki</option>
                                                <option value="P" @selected(($p['jenis_kelamin'] ?? '') === 'P')>Perempuan</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Tanggal Lahir</label>
                                        <input type="text" name="patients[{{ $idx }}][tanggal_lahir]" class="form-control tgl-lahir"
                                               value="{{ $p['tanggal_lahir'] ?? '' }}" placeholder="Pilih tanggal..." readonly>
                                    </div>

                                    <div class="form-group">
                                        <label>Keluhan Gigi / Mulut <span class="required">*</span></label>
                                        <textarea name="patients[{{ $idx }}][keluhan]" class="form-control" maxlength="2000" required
                                                  placeholder="Contoh: sakit gigi geraham kanan bawah, ingin pasang behel, mau scaling...">{{ $p['keluhan'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" class="btn-add" id="btnAddPatient">
                            <i class="fa-solid fa-user-plus"></i> Tambah pasien lain di nomor ini
                        </button>

                        {{-- LANGKAH 3 (opsional) — Kode promo.
                             Satu kode untuk seluruh pasien di submit ini, sama
                             seperti form reservasi publik (/reservasi). Tombol
                             "Cek" hanya memberi umpan balik; kode dicocokkan
                             ULANG di server saat submit. --}}
                        <div class="form-group promo-section">
                            <label for="promo_code">Kode Promo <span class="label-opt">(opsional, berlaku semua pasien)</span></label>
                            <div class="promo-row">
                                <input type="text" id="promo_code" name="promo_code" class="form-control"
                                       value="{{ old('promo_code') }}" maxlength="50" autocomplete="off">
                                <button type="button" class="btn-promo" id="btnCheckPromo">Cek</button>
                            </div>
                            <div class="promo-status" id="promoStatus"></div>
                        </div>

                        <button type="button" class="btn-submit" id="btnReview" style="margin-top:20px;">
                            <i class="fa-brands fa-whatsapp"></i> Kirim & Tunggu Dihubungi
                        </button>

                        <p class="form-footnote">
                            Dengan mengirim, kamu setuju dihubungi tim Sweet Treats {{ $branch->name }} untuk penjadwalan.
                        </p>
                    </div>
                </form>

                {{-- Template kartu pasien untuk JS "tambah pasien" (__IDX__ diganti indeks) --}}
                <template id="patientTemplate">
                    <div class="patient-card" data-patient>
                        <div class="patient-card__head">
                            <span class="patient-card__badge"><span class="num" data-num>0</span> Pasien</span>
                            <button type="button" class="btn-remove" data-remove>
                                <i class="fa-solid fa-trash-can"></i> Hapus
                            </button>
                        </div>

                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="patients[__IDX__][name]" class="form-control"
                                   placeholder="Nama sesuai identitas" maxlength="255" required>
                        </div>

                        <div class="form-group">
                            <label>NIK <span class="required">*</span> <span class="label-opt">(16 digit)</span></label>
                            <input type="text" name="patients[__IDX__][nik]" class="form-control"
                                   inputmode="numeric" autocomplete="off"
                                   pattern="[0-9]{16}" title="NIK harus 16 digit angka."
                                   placeholder="16 digit NIK — wajib untuk data Satu Sehat" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Pasien Ini Adalah</label>
                                <select name="patients[__IDX__][relasi]" class="form-control">
                                    @foreach ($relasiOptions as $val => $lbl)
                                        <option value="{{ $val }}" @selected($val === 'anak')>{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select name="patients[__IDX__][jenis_kelamin]" class="form-control">
                                    <option value="">Pilih...</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Tanggal Lahir</label>
                            <input type="text" name="patients[__IDX__][tanggal_lahir]" class="form-control tgl-lahir"
                                   placeholder="Pilih tanggal..." readonly>
                        </div>

                        <div class="form-group">
                            <label>Keluhan Gigi / Mulut <span class="required">*</span></label>
                            <textarea name="patients[__IDX__][keluhan]" class="form-control" maxlength="2000" required
                                      placeholder="Contoh: sakit gigi geraham kanan bawah, ingin pasang behel, mau scaling..."></textarea>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </section>

    {{-- Modal konfirmasi sebelum kirim --}}
    <div class="modal-overlay" id="confirmModal">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cmTitle">
            <div class="modal__icon"><i class="fa-solid fa-clipboard-check"></i></div>
            <div class="modal__title" id="cmTitle">Konfirmasi Reservasi</div>
            <div class="modal__sub">Pastikan data di bawah ini sudah benar</div>

            <div class="modal__row"><span class="k">Cabang</span><span class="v">{{ $branch->name }}</span></div>
            <div class="modal__row"><span class="k">Nomor WhatsApp</span><span class="v" id="cmPhone">-</span></div>
            <div class="modal__row" id="cmPromoRow" style="display:none;">
                <span class="k">Kode Promo</span>
                <span class="v" id="cmPromo">-</span>
            </div>

            <div class="modal__plabel" id="cmPatientsLabel">Pasien (0)</div>
            <div id="cmPatients"></div>

            <div class="modal__actions">
                <button type="button" class="btn-cancel" id="cmBack">Kembali</button>
                <button type="button" class="btn-confirm" id="cmConfirm"><i class="fa-solid fa-check"></i> Kirim Reservasi</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var fpOpts = {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j F Y',
                locale: 'id',
                maxDate: 'today',
                disableMobile: true,
            };
            function initDate(el) {
                if (el && !el._flatpickr) flatpickr(el, fpOpts);
            }
            // Init flatpickr utk kartu yang sudah dirender server.
            document.querySelectorAll('.tgl-lahir').forEach(initDate);

            var phoneInput = document.getElementById('phone');
            var btnCheck   = document.getElementById('btnCheckPhone');
            var phoneNote  = document.getElementById('phoneNote');
            var phoneError = document.getElementById('phoneError');
            var list       = document.getElementById('patientList');
            var tpl        = document.getElementById('patientTemplate');
            var nextIdx    = list.querySelectorAll('[data-patient]').length;

            // Normalisasi ringan sisi klien (server tetap sumber kebenaran):
            // "+62812…"/"62812…" → "0812…", buang spasi & tanda hubung.
            function normalizePhone(v) {
                v = (v || '').replace(/[\s\-().]/g, '');
                if (v.indexOf('+62') === 0) v = '0' + v.slice(3);
                else if (v.indexOf('62') === 0) v = '0' + v.slice(2);
                return v;
            }
            function phoneValid(v) { return /^0[0-9]{9,14}$/.test(v); }

            // Bagian pasien selalu tampil. "Cek" hanya memvalidasi + merapikan
            // format nomor, lalu memberi catatan hijau / pesan error.
            function checkPhone() {
                var norm = normalizePhone(phoneInput.value);
                if (!phoneValid(norm)) {
                    phoneError.classList.add('is-shown');
                    phoneNote.classList.remove('is-shown');
                    phoneInput.focus();
                    return;
                }
                phoneInput.value = norm;
                phoneError.classList.remove('is-shown');
                phoneNote.classList.add('is-shown');
                var firstField = list.querySelector('input[name$="[name]"]');
                if (firstField) firstField.focus();
            }

            function renumber() {
                var cards = list.querySelectorAll('[data-patient]');
                cards.forEach(function (card, i) {
                    var num = card.querySelector('[data-num]');
                    if (num) num.textContent = i + 1;
                    var rm = card.querySelector('[data-remove]');
                    if (rm) rm.style.display = cards.length > 1 ? '' : 'none';
                });
            }

            function addPatient() {
                var html = tpl.innerHTML.replace(/__IDX__/g, nextIdx);
                nextIdx++;
                var wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                var card = wrap.firstElementChild;
                list.appendChild(card);
                initDate(card.querySelector('.tgl-lahir'));
                renumber();
                var nm = card.querySelector('input[name$="[name]"]');
                if (nm) nm.focus();
            }

            btnCheck.addEventListener('click', checkPhone);
            phoneInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); checkPhone(); }
            });
            document.getElementById('btnAddPatient').addEventListener('click', addPatient);
            // NIK: buang apa pun yang bukan angka (spasi/titik dari KTP digital)
            // dan potong di 16. Dipasang di list, jadi kartu pasien yang
            // ditambah belakangan ikut terurus tanpa pasang listener lagi.
            list.addEventListener('input', function (e) {
                var el = e.target;
                if (!el.name || el.name.slice(-5) !== '[nik]') return;
                var clean = (el.value || '').replace(/[^0-9]/g, '').slice(0, 16);
                if (clean !== el.value) el.value = clean;
            });

            list.addEventListener('click', function (e) {
                var rm = e.target.closest('[data-remove]');
                if (!rm) return;
                var card = rm.closest('[data-patient]');
                if (card) card.remove();
                renumber();
            });

            // ── Kode promo (opsional) ──
            // Dicek ke endpoint yang sama dengan form reservasi publik
            // (/api/promo/validate). Hasil cek HANYA umpan balik di layar:
            // kode yang tidak dikenal tidak menahan pengiriman, karena tugas
            // halaman ini menangkap calon pasien, bukan menghitung diskon.
            // Server mencocokkan ulang kodenya sendiri saat submit.
            var promoInput  = document.getElementById('promo_code');
            var btnPromo    = document.getElementById('btnCheckPromo');
            var promoStatus = document.getElementById('promoStatus');
            var promoState  = { checked: false, valid: false, label: '' };

            function escapeHtml(s) {
                return String(s).replace(/[&<>"']/g, function (c) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                });
            }
            function setPromoStatus(kind, html) {
                promoStatus.className = 'promo-status is-shown promo-status--' + kind;
                promoStatus.innerHTML = html;
            }
            function resetPromoState() {
                promoState = { checked: false, valid: false, label: '' };
                promoStatus.className = 'promo-status';
                promoStatus.innerHTML = '';
            }
            function checkPromo() {
                var code = (promoInput.value || '').trim().toUpperCase();
                promoInput.value = code;
                if (!code) {
                    resetPromoState();
                    setPromoStatus('bad', 'Tulis kode promonya dulu, baru klik Cek.');
                    return;
                }
                btnPromo.disabled = true;
                btnPromo.textContent = 'Cek...';
                fetch('/api/promo/validate?code=' + encodeURIComponent(code), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.valid) {
                            promoState = { checked: true, valid: true, label: res.message || '' };
                            setPromoStatus('ok',
                                '<i class="fa-solid fa-circle-check"></i> Kode <strong>' + escapeHtml(code) + '</strong> aktif — '
                                + escapeHtml(res.message || 'promo ditemukan')
                                + '<small>' + (res.valid_until ? 'Berlaku sampai ' + escapeHtml(res.valid_until) + '. ' : '')
                                + 'Kode ini ikut tercatat dan dipakai Sweet Crew saat membuatkan jadwal kamu.</small>');
                        } else {
                            promoState = { checked: true, valid: false, label: '' };
                            setPromoStatus('bad',
                                '<i class="fa-solid fa-circle-xmark"></i> '
                                + escapeHtml((res && res.message) || 'Kode promo tidak ditemukan')
                                + '<small>Coba cek lagi penulisannya. Kamu tetap bisa mengirim tanpa kode — '
                                + 'nanti bisa disampaikan saat Sweet Crew menghubungi.</small>');
                        }
                    })
                    .catch(function () {
                        resetPromoState();
                        setPromoStatus('bad',
                            '<i class="fa-solid fa-triangle-exclamation"></i> Gagal mengecek kode promo.'
                            + '<small>Koneksi sedang bermasalah. Kirim saja — kodenya tetap kami cocokkan di sistem.</small>');
                    })
                    .then(function () {
                        btnPromo.disabled = false;
                        btnPromo.textContent = 'Cek';
                    });
            }

            btnPromo.addEventListener('click', checkPromo);
            promoInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); checkPromo(); }
            });
            promoInput.addEventListener('input', resetPromoState);

            // ── Modal konfirmasi sebelum kirim ──
            var form   = document.getElementById('pasienBaruForm');
            var modal  = document.getElementById('confirmModal');
            var relasiLabels = {
                diri_sendiri: 'Diri sendiri', anak: 'Anak', pasangan: 'Pasangan',
                orang_tua: 'Orang tua', lainnya: 'Lainnya',
            };

            function openConfirm() {
                // Validasi HTML5 dulu (nama & keluhan wajib, dll.).
                if (!form.checkValidity()) { form.reportValidity(); return; }
                // Lalu validasi format nomor.
                var norm = normalizePhone(phoneInput.value);
                if (!phoneValid(norm)) { checkPhone(); return; }
                phoneInput.value = norm;

                document.getElementById('cmPhone').textContent = norm;

                // Baris promo hanya muncul kalau kodenya memang diisi. Statusnya
                // ditulis apa adanya — termasuk saat kodenya belum/gagal dicek —
                // supaya tidak ada yang mengira dapat diskon padahal tidak.
                var promoRow  = document.getElementById('cmPromoRow');
                var promoCode = (promoInput.value || '').trim().toUpperCase();
                promoInput.value = promoCode;
                if (promoCode) {
                    var ket;
                    if (promoState.checked && promoState.valid) {
                        ket = promoState.label ? ' · ' + promoState.label : ' · aktif';
                    } else if (promoState.checked) {
                        ket = ' · tidak dikenal, tidak ikut tercatat';
                    } else {
                        ket = ' · belum dicek';
                    }
                    document.getElementById('cmPromo').textContent = promoCode + ket;
                    promoRow.style.display = '';
                } else {
                    promoRow.style.display = 'none';
                }

                var cards = list.querySelectorAll('[data-patient]');
                document.getElementById('cmPatientsLabel').textContent = 'Pasien (' + cards.length + ')';

                var box = document.getElementById('cmPatients');
                box.innerHTML = '';
                cards.forEach(function (card) {
                    var name = (card.querySelector('input[name$="[name]"]').value || '').trim();
                    var relEl = card.querySelector('select[name$="[relasi]"]');
                    var rel = relasiLabels[relEl ? relEl.value : ''] || '';
                    var kel = (card.querySelector('textarea[name$="[keluhan]"]').value || '').trim();
                    // NIK ikut ditampilkan di konfirmasi: salah satu angka di sini
                    // membuat pasien gagal dicocokkan di Satu Sehat, dan ini
                    // kesempatan terakhir orangnya membetulkan sendiri.
                    var nikEl = card.querySelector('input[name$="[nik]"]');
                    var nik = nikEl ? (nikEl.value || '').trim() : '';
                    if (nik) rel = rel ? rel + ' · NIK ' + nik : 'NIK ' + nik;

                    var row = document.createElement('div');
                    row.className = 'modal__patient';
                    row.innerHTML = '<div><div class="pname"></div><div class="prel"></div></div><div class="pkeluhan"></div>';
                    row.querySelector('.pname').textContent = name;
                    row.querySelector('.prel').textContent = rel;
                    row.querySelector('.pkeluhan').textContent = kel;
                    box.appendChild(row);
                });

                modal.classList.add('is-open');
            }

            document.getElementById('btnReview').addEventListener('click', openConfirm);
            document.getElementById('cmBack').addEventListener('click', function () { modal.classList.remove('is-open'); });
            document.getElementById('cmConfirm').addEventListener('click', function () { form.submit(); });
            modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('is-open'); });

            renumber();
        })();
    </script>
</body>

</html>
