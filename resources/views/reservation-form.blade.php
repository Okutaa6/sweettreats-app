<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservasi - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Buat reservasi perawatan gigi di Sweet Treats Dental Clinic.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; color: #3a2a1a; background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%); min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .container { max-width: 780px; margin: 0 auto; padding: 0 24px; }

        .navbar { background: rgba(255,250,245,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(70,21,0,0.08); padding: 0 24px; }
        .navbar-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 72px; }
        .navbar-brand { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .navbar-brand span { background: linear-gradient(135deg, #461500, #8b3a00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, #461500, #8b3a00); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.1rem; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #461500; color: #461500; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 700; transition: all 0.3s; }
        .btn-back:hover { background: #461500; color: #fff; }

        .form-section { padding: 48px 0 80px; }
        .form-header { text-align: center; margin-bottom: 40px; }
        .form-header .tag { display: inline-block; padding: 6px 16px; background: rgba(70,21,0,0.06); border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: #461500; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .form-header h1 { font-size: 2.2rem; font-weight: 900; color: #461500; margin-bottom: 12px; }
        .form-header p { font-size: 1rem; color: #6b4a30; line-height: 1.6; }
        .form-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(70,21,0,0.08); border: 1px solid rgba(70,21,0,0.04); }

        .steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 36px; flex-wrap: wrap; }
        .step { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; color: #8b7a6b; background: #f5f0eb; transition: all 0.4s ease; }
        .step.active { background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .step.done { background: #e8f5e9; color: #2e7d32; }
        .step-num { width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; }
        .step.active .step-num { background: rgba(255,255,255,0.2); }
        .step.done .step-num { background: #2e7d32; color: #fff; }

        .alert-success { background: linear-gradient(135deg,#e8f5e9,#c8e6c9); border: 1px solid #a5d6a7; border-radius: 16px; padding: 20px 24px; margin-bottom: 28px; display: flex; align-items: center; gap: 14px; color: #2e7d32; font-size: 0.95rem; font-weight: 600; }
        .alert-error { background: linear-gradient(135deg,#ffebee,#ffcdd2); border: 1px solid #ef9a9a; border-radius: 16px; padding: 16px 24px; margin-bottom: 28px; color: #c62828; font-size: 0.9rem; }
        .alert-error ul { margin: 8px 0 0 16px; }

        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #461500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group label .required { color: #c45200; }
        .form-control { width: 100%; padding: 14px 18px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; color: #3a2a1a; transition: all 0.3s; outline: none; }
        .form-control:focus { border-color: #461500; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); }
        .form-control:read-only { background: #f5f0eb; color: #6b4a30; cursor: default; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23461500' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 01.708 0L8 10.293l5.646-5.647a.5.5 0 01.708.708l-6 6a.5.5 0 01-.708 0l-6-6a.5.5 0 010-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; padding-right: 44px; }
        select.form-control:disabled { background-color: #f5f0eb; color: #6b4a30; cursor: default; }
        #tanggalLahirInput[readonly] { cursor: pointer; background: #fffaf5; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        .chain-section { display: none; animation: fadeIn 0.4s ease; }
        .chain-section.visible { display: block; }
        .chain-section + .chain-section { margin-top: 24px; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Kategori */
        .category-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px; }
        .category-btn { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; cursor: pointer; font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 700; color: #5a3a20; transition: all 0.3s; text-align: left; }
        .category-btn:hover { border-color: rgba(70,21,0,0.3); background: #fef5ed; }
        .category-btn.active { border-color: #461500; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .category-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(70,21,0,0.08); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; transition: all 0.3s; }
        .category-btn.active .category-icon { background: rgba(255,255,255,0.2); color: #fff; }
        .category-hint { font-size: 0.78rem; color: #8b7a6b; margin-bottom: 12px; font-weight: 600; }
        .service-list-area { display: none; animation: fadeIn 0.3s ease; }
        .service-list-area.visible { display: block; }

        /* Service cards */
        .services-select-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .service-select-card { position: relative; }
        .service-select-card input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .service-select-card label { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.08); border-radius: 12px; cursor: pointer; transition: all 0.3s; font-size: 0.85rem; font-weight: 600; color: #5a3a20; }
        .service-select-card label .check-icon { width: 22px; height: 22px; border: 2px solid rgba(70,21,0,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s; flex-shrink: 0; font-size: 0.7rem; color: transparent; }
        .service-select-card input:checked + label { border-color: #461500; background: rgba(70,21,0,0.04); }
        .service-select-card input:checked + label .check-icon { background: #461500; border-color: #461500; color: #fff; }
        .service-select-card label:hover { border-color: rgba(70,21,0,0.2); }
        .service-info { flex: 1; }
        .service-name { display: block; font-size: 0.85rem; font-weight: 600; color: #461500; }
        .service-meta { display: flex; gap: 10px; margin-top: 4px; font-size: 0.72rem; color: #8b7a6b; }

        /* Lainnya input */
        .lainnya-input-area { display: none; animation: fadeIn 0.3s ease; }
        .lainnya-input-area.visible { display: block; }
        .lainnya-input-area label { font-size: 0.8rem; font-weight: 700; color: #461500; margin-bottom: 6px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Date */
        .date-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
        .month-nav { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; padding: 12px 16px; background: #fff; border-radius: 16px; border: 1px solid rgba(70,21,0,0.06); box-shadow: 0 4px 12px rgba(70,21,0,0.02); }
        .month-nav .month-label { font-size: 1.05rem; font-weight: 800; color: #461500; }
        .month-nav button { background: #fffaf5; border: 1px solid rgba(70,21,0,0.1); border-radius: 10px; width: 36px; height: 36px; cursor: pointer; color: #461500; font-size: 0.9rem; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .month-nav button:hover:not(:disabled) { background: #461500; color: #fff; border-color: #461500; transform: scale(1.05); }
        .month-nav button:disabled { opacity: 0.3; cursor: not-allowed; background: transparent; border-color: transparent; }
        .date-pill { padding: 14px 10px; border: 2px solid rgba(70,21,0,0.08); border-radius: 14px; background: #fff; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; }
        .date-pill:hover { border-color: #461500; background: #fffaf5; transform: translateY(-3px); box-shadow: 0 6px 16px rgba(70,21,0,0.06); }
        .date-pill.selected { border-color: #461500; background: linear-gradient(135deg, #461500, #8b3a00); color: #fff; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(70,21,0,0.2); }
        .date-pill .date-day { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #8b7a6b; transition: all 0.3s; }
        .date-pill .date-num { font-size: 1.1rem; font-weight: 800; color: #461500; line-height: 1.2; transition: all 0.3s; }
        .date-pill.selected .date-day, .date-pill.selected .date-num { color: #fff; }

        /* Slots */
        .slots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
        .slot { position: relative; padding: 14px 8px; border: 2px solid rgba(70,21,0,0.1); border-radius: 12px; background: #fffaf5; text-align: center; cursor: pointer; transition: all 0.3s; font-size: 0.9rem; font-weight: 700; color: #461500; }
        .slot:hover:not(.booked):not(.past) { border-color: #461500; background: rgba(70,21,0,0.03); transform: translateY(-2px); }
        .slot.selected { border-color: #461500; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .slot.booked { background: #f5f0eb; color: #b0a090; cursor: not-allowed; border-color: rgba(70,21,0,0.06); }
        .slot.booked::after { content: ''; position: absolute; top: 50%; left: 10%; right: 10%; height: 2px; background: #c0b0a0; transform: rotate(-12deg); }
        .slot.past { background: #fafafa; color: #ccc; cursor: not-allowed; border-color: #eee; }
        .slot .slot-status { font-size: 0.65rem; font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
        .slot.booked .slot-status { color: #c45200; }
        .slot.selected .slot-status { color: rgba(255,255,255,0.8); }

        /* Duration summary */
        .duration-summary { background: linear-gradient(135deg,#fef5ed,#fef0e2); border: 1px solid rgba(70,21,0,0.08); border-radius: 14px; padding: 16px 20px; margin-top: 16px; display: none; }
        .duration-summary.visible { display: flex; align-items: center; justify-content: space-between; }
        .duration-summary .summary-item { text-align: center; }
        .duration-summary .summary-label { font-size: 0.7rem; font-weight: 600; color: #8b7a6b; text-transform: uppercase; letter-spacing: 0.5px; }
        .duration-summary .summary-value { font-size: 1.1rem; font-weight: 800; color: #461500; margin-top: 2px; }

        /* Grid 2 tombol pilihan pasien */
        .patient-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 24px;
        }

        /* Tombol pilihan pasien */
        .patient-type-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 20px 16px;
            background: #fffaf5;
            border: 2px solid rgba(70,21,0,0.1);
            border-radius: 16px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #5a3a20;
            transition: all 0.3s;
            text-align: center;
        }
        .patient-type-btn:hover { border-color: rgba(70,21,0,0.3); background: #fef5ed; transform: translateY(-2px); }
        .patient-type-btn.active { border-color: #461500; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(70,21,0,0.2); }

        /* Icon besar di tombol pasien */
        .patient-type-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(70,21,0,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        .patient-type-btn.active .patient-type-icon { background: rgba(255,255,255,0.2); color: #fff; }
        .patient-type-btn .btn-title { font-size: 0.95rem; }
        .patient-type-btn .btn-desc { font-size: 0.72rem; font-weight: 500; color: #8b7a6b; margin-top: 2px; }
        .patient-type-btn.active .btn-desc { color: rgba(255,255,255,0.75); }

        /* Area form setelah pilih tipe pasien */
        .patient-form-area { display: none; animation: fadeIn 0.3s ease; }
        .patient-form-area.visible { display: block; }

        /* Box cek nomor HP pasien lama */
        .phone-check-box {
            background: #fffaf5;
            border: 2px solid rgba(70,21,0,0.1);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .phone-check-box label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #461500;
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Alert pasien lama ditemukan */
        .found-alert {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border: 1px solid #a5d6a7;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2e7d32;
            font-size: 0.85rem;
            font-weight: 600;
        }
        /* Alert pasien tidak ditemukan */
        .not-found-alert {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border: 1px solid #ef9a9a;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #c62828;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .btn-submit { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 1rem; font-weight: 800; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 12px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(70,21,0,0.35); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .form-note { text-align: center; margin-top: 20px; font-size: 0.8rem; color: #8b7a6b; }

        .loading { text-align: center; padding: 24px; color: #8b7a6b; font-size: 0.9rem; }
        .loading i { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .empty-state { text-align: center; padding: 24px; color: #8b7a6b; font-size: 0.9rem; }
        .empty-state i { font-size: 2rem; margin-bottom: 8px; display: block; color: #c0b0a0; }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.55); backdrop-filter: blur(4px); z-index: 9999; display: none; align-items: center; justify-content: center; padding: 24px; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; border-radius: 24px; max-width: 560px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 24px 80px rgba(0,0,0,0.2); animation: modalIn 0.3s ease; }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-header { padding: 28px 32px 0; text-align: center; }
        .modal-header .modal-icon { width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg,#fef5ed,#fef0e2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #461500; margin: 0 auto 16px; }
        .modal-header h2 { font-size: 1.4rem; font-weight: 800; color: #461500; }
        .modal-header p { font-size: 0.85rem; color: #8b7a6b; margin-top: 4px; }
        .modal-body { padding: 24px 32px; }
        .preview-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid rgba(70,21,0,0.06); font-size: 0.9rem; }
        .preview-row:last-child { border-bottom: none; }
        .preview-row .preview-label { color: #8b7a6b; font-weight: 600; }
        .preview-row .preview-value { color: #461500; font-weight: 700; text-align: right; max-width: 60%; }
        .preview-services { margin-top: 8px; }
        .preview-services .svc-item { display: flex; justify-content: space-between; padding: 8px 12px; background: #fffaf5; border-radius: 10px; margin-bottom: 6px; font-size: 0.85rem; }
        .preview-services .svc-item .svc-name { font-weight: 600; color: #461500; }
        .preview-services .svc-item .svc-detail { color: #8b7a6b; font-size: 0.75rem; }
        .preview-total { display: flex; justify-content: space-between; padding: 16px 0 0; margin-top: 8px; border-top: 2px solid rgba(70,21,0,0.1); font-size: 1rem; font-weight: 800; }
        .modal-footer { padding: 0 32px 28px; display: flex; gap: 12px; }
        .btn-modal { flex: 1; padding: 14px; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.3s; text-align: center; }
        .btn-modal.cancel { background: #f5f0eb; color: #5a3a20; }
        .btn-modal.cancel:hover { background: #e8e0d8; }
        .btn-modal.confirm { background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .btn-modal.confirm:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(70,21,0,0.3); }


        @media (max-width: 640px) {
            .form-card { padding: 28px 20px; }
            .form-row { grid-template-columns: 1fr; }
            .services-select-grid { grid-template-columns: 1fr; }
            .category-grid { grid-template-columns: 1fr 1fr; }
            .patient-type-grid { grid-template-columns: 1fr 1fr; }
            .form-header h1 { font-size: 1.8rem; }
            .steps { flex-wrap: wrap; }
            .step span { display: none; }
            .slots-grid { grid-template-columns: repeat(3, 1fr); }
            .date-pill { min-width: unset; padding: 12px 8px; }
            .modal-body, .modal-header, .modal-footer { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>

<body>

        <div class="navbar-inner">
            <a href="https://sweettreats.id/" class="navbar-brand">
                <span>Sweet Treats</span>
            </a>
            <a href="https://sweettreats.id/" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Kembali
            </a>
        </div>



    <section class="form-section">
        <div class="container">
            <div class="form-header">
                <div class="tag"><i class="fa-solid fa-calendar-check"></i> Reservasi Online</div>
                <h1>Buat Reservasi</h1>
                <p>Pilih cabang, perawatan, dokter, dan jadwal yang kamu inginkan</p>
            </div>

            <div class="form-card">
                <div class="steps">
                    <div class="step active" data-step="1"><span class="step-num">1</span><span>Cabang</span></div>
                    <div class="step" data-step="2"><span class="step-num">2</span><span>Perawatan</span></div>
                    <div class="step" data-step="3"><span class="step-num">3</span><span>Dokter</span></div>
                    <div class="step" data-step="4"><span class="step-num">4</span><span>Jadwal</span></div>
                    <div class="step" data-step="5"><span class="step-num">5</span><span>Data Diri</span></div>
                </div>

                @if (session('success'))
                    <div class="alert-success">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert-error">
                        <strong><i class="fa-solid fa-circle-exclamation"></i> Mohon periksa data berikut:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                    <form method="POST" action="{{ route('landing.reservation.store') }}" id="reservationForm" novalidate>                   
                        @csrf

                    <div class="form-group" id="branchGroup">
                        <label>Pilih Cabang <span class="required">*</span></label>
                        <select name="raw_branch_id" id="branchSelect" class="form-control">
                        <option value="" hidden selected>-- Pilih Cabang --</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    {{ request('cabang') && strtolower($branch->name) === strtolower(request('cabang')) ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        <div id="branchLocked" style="display:none; padding:14px 18px; background:#f5f0eb; border:2px solid rgba(70,21,0,0.15); border-radius:14px; font-weight:700; color:#461500;"></div>
                    </div>

                    <div class="chain-section" id="serviceSection">
                        <div class="form-group">
                            <label>Pilih Perawatan <span class="required">*</span></label>
                            <div class="category-grid" id="categoryGrid"></div>
                            <div class="service-list-area" id="serviceListArea">
                                <p class="category-hint"><i class="fa-solid fa-circle-info"></i> Pilih <strong>1 perawatan</strong>:</p>
                                <div id="servicesSelectGrid" class="services-select-grid"></div>
                                <div class="lainnya-input-area" id="lainnyaInputArea">
                                    <label>Ceritakan keluhanmu <span class="required">*</span></label>
                                    <input type="text" id="lainnyaText" name="lainnya_text" class="form-control"
                                        placeholder="Contoh: sakit gigi geraham kanan bawah..." maxlength="200">
                                    <small style="color:#8b7a6b;font-size:0.75rem;margin-top:4px;display:block;">
                                        Jelaskan keluhanmu, tim kami akan membantu menentukan perawatan yang tepat.
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="duration-summary" id="durationSummary">
                            <div class="summary-item">
                                <div class="summary-label">Estimasi Durasi</div>
                                <div class="summary-value" id="totalDuration">0 menit</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Est. Selesai Pukul</div>
                                <div class="summary-value" id="endTime">-</div>
                            </div>
                        </div>
                    </div>

                    <div class="chain-section" id="doctorSection">
                        <div class="form-group">
                            <label>Pilih Dokter <span class="required">*</span></label>
                            <select name="raw_doctor_id" id="doctorSelect" class="form-control">
                            <option value="" hidden selected>-- Pilih Dokter --</option>                            
                        </select>
                        </div>
                    </div>

                    <div class="chain-section" id="dateSection">
                        <div class="form-group">
                            <label>Pilih Tanggal <span class="required">*</span></label>
                            <div id="dateGrid" class="date-grid"></div>
                            <input type="hidden" name="reservation_date" id="reservationDate">
                        </div>
                    </div>

                    <div class="chain-section" id="slotSection">
                        <div class="form-group">
                            <label>Jadwal <span class="required">*</span></label>
                            <div id="slotsGrid" class="slots-grid"></div>
                            <input type="hidden" name="reservation_time" id="reservationTime">
                        </div>
                    </div>

                    
                    <div class="chain-section" id="customerSection">

                        <div class="form-group">
                            <label>Apakah kamu pasien baru? <span class="required">*</span></label>
                            <div class="patient-type-grid">

                                <button type="button" class="patient-type-btn" id="btnPasienBaru"
                                    onclick="selectPatientType('baru')">
                                    <span class="patient-type-icon">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </span>
                                    <span class="btn-title">Pasien Baru</span>
                                    <span class="btn-desc">Belum pernah ke sini</span>
                                </button>

                                <button type="button" class="patient-type-btn" id="btnPasienLama"
                                    onclick="selectPatientType('lama')">
                                    <span class="patient-type-icon">
                                        <i class="fa-solid fa-user-check"></i>
                                    </span>
                                    <span class="btn-title">Pasien Lama</span>
                                    <span class="btn-desc">Sudah pernah ke sini</span>
                                </button>

                            </div>
                        </div>

                        <div class="patient-form-area" id="pasienLamaArea">
                            <div class="phone-check-box">
                                <label>Masukkan Nomor WhatsApp Kamu <span class="required">*</span></label>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <input type="text" id="phoneCheckInput" class="form-control"
                                        placeholder="08xxxxxxxxxx"
                                        oninput="resetPhoneCheck()">
                                    <button type="button" id="btnCekHP"
                                        onclick="cekNomorHP()"
                                        style="background:#461500;color:white;border:none;border-radius:10px;padding:14px 18px;font-size:0.85rem;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap;">
                                        Cek
                                    </button>
                                </div>
                                {{-- Status hasil cek --}}
                                <div id="phoneCheckStatus" style="margin-top:10px;"></div>
                            </div>

                            {{-- Form data diri pasien lama (muncul setelah ditemukan, read-only) --}}
                            <div id="pasienLamaForm" style="display:none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nama Lengkap</label>
                                        <input type="text" id="namaLama" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>No. WhatsApp</label>
                                        <input type="text" id="phoneLama" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="text" id="emailLama" class="form-control" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Jenis Kelamin</label>
                                        <input type="text" id="jenisKelaminLama" class="form-control" readonly>
                                    </div>
                                </div>
                                <div class="form-group" style="max-width:50%;">
                                    <label>Tanggal Lahir</label>
                                    <input type="text" id="tanggalLahirLama" class="form-control" readonly>
                                </div>
                                <small style="color:#8b7a6b;font-size:0.78rem;display:block;margin-bottom:16px;">
                                    <i class="fa-solid fa-circle-info"></i>
                                    Data diambil dari sistem. Jika ada yang tidak sesuai, hubungi staff kami.
                                </small>
                            </div>
                        </div>

                        <div class="patient-form-area" id="pasienBaruArea">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nama Lengkap </span></label>
                                    <input type="text" name="name" id="nameInput" class="form-control"
                                        placeholder="Masukkan nama lengkap">
                                </div>
                                <div class="form-group">
                                    <label>No. WhatsApp </span></label>
                                    <input type="text" name="phone" id="phoneInput" class="form-control"
                                        placeholder="08xxxxxxxxxx">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email </span></label>
                                    <input type="email" name="email" id="emailInput" class="form-control"
                                        placeholder="email@contoh.com">
                                </div>
                                <div class="form-group">
                                    <label>Jenis Kelamin </span></label>
                                    <select name="jenis_kelamin" id="jenisKelaminInput" class="form-control">
                                        <option value="">-- Pilih --</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Tanggal Lahir </span></label>
                                    <input type="text" name="tanggal_lahir" id="tanggalLahirInput"
                                        class="form-control" placeholder="Pilih tanggal lahir" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Kode Promo <span style="color:#8b7a6b;font-weight:400;font-size:0.75rem;text-transform:none">(opsional)</span></label>
                                    <div style="display:flex;gap:8px;align-items:center">
                                        <input type="text" name="promo_code" id="promoInput" class="form-control"
                                            style="text-transform:uppercase">
                                        <button type="button" id="checkPromoBtn" onclick="checkPromo()"
                                            style="background:#461500;color:white;border:none;border-radius:8px;padding:14px 18px;font-size:0.85rem;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap">
                                            Cek
                                        </button>
                                    </div>
                                    <div id="promoStatus" style="margin-top:6px;font-size:0.82rem"></div>
                                    <input type="hidden" name="promo_id" id="promoIdInput" value="">
                                </div>
                            </div>
                        </div>

                        {{-- Kode promo untuk pasien lama --}}
                        <div class="patient-form-area" id="pasienLamaPromoArea">
                            <div class="form-group">
                                <label>Kode Promo <span style="color:#8b7a6b;font-weight:400;font-size:0.75rem;text-transform:none">(opsional)</span></label>
                                <div style="display:flex;gap:8px;align-items:center">
                                    <input type="text" name="promo_code" id="promoInputLama" class="form-control"
                                    style="text-transform:uppercase">
                                    <button type="button" onclick="checkPromoLama()"
                                        id="checkPromoBtnLama"
                                        style="background:#461500;color:white;border:none;border-radius:8px;padding:14px 18px;font-size:0.85rem;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap">
                                        Cek
                                    </button>
                                </div>
                                <div id="promoStatusLama" style="margin-top:6px;font-size:0.82rem"></div>
                                <input type="hidden" name="promo_id" id="promoIdInputLama" value="">
                            </div>
                        </div>

                        {{-- Hidden fields untuk data pasien lama yang dikirim ke server --}}
                        <input type="hidden" name="existing_customer_id" id="existingCustomerId" value="">
                        <input type="hidden" name="patient_type" id="patientTypeInput" value="">

                        <button type="button" class="btn-submit" id="previewBtn" disabled onclick="showPreview()">
                            <i class="fa-solid fa-eye"></i> Preview Reservasi
                        </button>
                        <p class="form-note">
                            <i class="fa-solid fa-shield-halved"></i>
                            Tim kami akan menghubungi kamu untuk konfirmasi jadwal via WhatsApp
                        </p>
            
                    </div>

                </form>
            </div>
        </div>
    </section>

    <div class="modal-overlay" id="previewModal">
        <div class="modal-box">
            <div class="modal-header">
                <div class="modal-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <h2>Konfirmasi Reservasi</h2>
                <p>Pastikan data di bawah ini sudah benar</p>
            </div>
            <div class="modal-body" id="previewBody"></div>
            <div class="modal-footer">
                <button class="btn-modal cancel" onclick="closePreview()">Kembali</button>
                <button class="btn-modal confirm" onclick="confirmSubmit()">
                    <i class="fa-solid fa-check"></i> Kirim Reservasi
                </button>
            </div>
        </div>
    </div>

    <script>
    let activeCategory = null;

    $(function () {
        const BASE = '/api/reservation';
        let selectedDate      = null;
        let selectedTime      = null;
        let servicesData      = [];
        let allDates          = [];
        let currentMonthIndex = 0;

            const categoryIcons = {
                'estetika'   : 'fa-solid fa-star',
                'standar'    : 'fa-solid fa-tooth',
                'pembedahan' : 'fa-solid fa-syringe',
                'lainnya'    : 'fa-solid fa-ellipsis',
            };
            const categoryLabels = {
                'estetika'   : 'Estetika',
                'standar'    : 'Perawatan Standar',
                'pembedahan' : 'Pembedahan',
                'lainnya'    : 'Lainnya',
            };

            function buildCategoryButtons(data) {
                const fixedOrder = ['estetika', 'standar', 'pembedahan', 'lainnya'];
                const extraCategories = [...new Set(data.map(s => s.category).filter(Boolean))]
                    .filter(cat => !fixedOrder.includes(cat));
                const allCategories = [...fixedOrder, ...extraCategories];
                let html = '';
                allCategories.forEach(function (cat) {
                    const icon  = categoryIcons[cat]  || 'fa-solid fa-circle-dot';
                    const label = categoryLabels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
                    html += `<button type="button" class="category-btn" data-category="${cat}">
                        <span class="category-icon"><i class="${icon}"></i></span>
                        <span>${label}</span>
                    </button>`;
                });
                $('#categoryGrid').html(html);
            }

            function setCookie(name, value, days) {
                const d = new Date();
                d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
                document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/';
            }
            function getCookie(name) {
                const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
                return v ? decodeURIComponent(v.pop()) : '';
            }

            if (getCookie('st_name'))  $('#nameInput').val(getCookie('st_name'));
            if (getCookie('st_phone')) $('#phoneInput').val(getCookie('st_phone'));

            function setStep(num) {
                $('.step').each(function () {
                    const s = parseInt($(this).data('step'));
                    $(this).removeClass('active done');
                    if (s < num)  $(this).addClass('done');
                    if (s === num) $(this).addClass('active');
                });
            }

            function updateDurationSummary() {
                let total = 0;
                if (activeCategory === 'lainnya') {
                    total = 30;
                } else {
                    $('input[name="services[]"]:checked').each(function () {
                        total += parseInt($(this).data('duration')) || 0;
                    });
                }
                if (total > 0) {
                    $('#totalDuration').text(total + ' menit');
                    if (selectedTime) {
                        const parts = selectedTime.split(':');
                        const endMin = parseInt(parts[0]) * 60 + parseInt(parts[1]) + total;
                        $('#endTime').text(
                            String(Math.floor(endMin / 60)).padStart(2,'0') + ':' +
                            String(endMin % 60).padStart(2,'0')
                        );
                    }
                    $('#durationSummary').addClass('visible');
                } else {
                    $('#durationSummary').removeClass('visible');
                }
            }

            function resetFrom(section) {
                const order = ['service','doctor','date','slot','customer'];
                const idx   = order.indexOf(section);
                for (let i = idx; i < order.length; i++) {
                    const s = order[i];
                    $('#' + s + 'Section').removeClass('visible');
                    if (s === 'service') {
                        $('#servicesSelectGrid').empty();
                        $('#serviceListArea').removeClass('visible');
                        $('#lainnyaInputArea').removeClass('visible');
                        $('#lainnyaText').val('');
                        $('.category-btn').removeClass('active');
                        activeCategory = null;
                    }
                    if (s === 'doctor') { $('#doctorSelect').html('<option value="" hidden selected>-- Pilih Dokter --</option>'); }                   
                    if (s === 'date')   { $('#dateGrid').empty(); $('#reservationDate').val(''); selectedDate = null; allDates = []; }
                    if (s === 'slot')   { $('#slotsGrid').empty(); $('#reservationTime').val(''); selectedTime = null; }
                    if (s === 'customer') {
                        // STEP 3: reset pilihan tipe pasien juga
                        resetPatientType();
                    }
                }
                $('#durationSummary').removeClass('visible');
                $('#previewBtn').prop('disabled', true);
            }

            function resetPatientType() {
                $('#btnPasienBaru, #btnPasienLama').removeClass('active');
                $('#pasienBaruArea, #pasienLamaArea, #pasienLamaPromoArea').removeClass('visible');
                $('#patientTypeInput').val('');
                $('#existingCustomerId').val('');
                // Reset form pasien baru
                $('#nameInput, #phoneInput, #emailInput').val('');
                $('#jenisKelaminInput').val('');
                $('#tanggalLahirInput').val('');
                // Reset form pasien lama
                $('#phoneCheckInput').val('');
                $('#phoneCheckStatus').html('');
                $('#pasienLamaForm').hide();
                $('#namaLama, #phoneLama, #emailLama, #jenisKelaminLama, #tanggalLahirLama').val('');
            }

            function renderMonthDates() {
                const monthGroups = {};
                allDates.forEach(function (d) {
                    const key = d.date.substring(0, 7);
                    if (!monthGroups[key]) monthGroups[key] = [];
                    monthGroups[key].push(d);
                });
                const monthKeys = Object.keys(monthGroups).sort();
                if (monthKeys.length === 0) return;
                if (currentMonthIndex >= monthKeys.length) currentMonthIndex = monthKeys.length - 1;
                if (currentMonthIndex < 0) currentMonthIndex = 0;
                const currentKey = monthKeys[currentMonthIndex];
                const dates = monthGroups[currentKey];
                const bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                const [year, month] = currentKey.split('-');
                const monthLabel = bulan[parseInt(month) - 1] + ' ' + year;
                let html = '<div class="month-nav">';
                html += '<button id="prevMonth" ' + (currentMonthIndex === 0 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button>';
                html += '<span class="month-label">' + monthLabel + '</span>';
                html += '<button id="nextMonth" ' + (currentMonthIndex === monthKeys.length - 1 ? 'disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button>';
                html += '</div>';
                dates.forEach(function (d) {
                    const parts = d.label.split(', ');
                    html += '<div class="date-pill" data-date="' + d.date + '" data-label="' + d.label + '">';
                    html += '<div class="date-day">' + parts[0] + '</div>';
                    html += '<div class="date-num">' + parts[1] + '</div>';
                    html += '</div>';
                });
                $('#dateGrid').html(html);
                $('#prevMonth').on('click', function () { currentMonthIndex--; renderMonthDates(); });
                $('#nextMonth').on('click', function () { currentMonthIndex++; renderMonthDates(); });
            }

            function renderServicesForCategory(category) {
                const filtered = servicesData
                    .filter(svc => svc.category === category)
                    .sort((a, b) => (a.sort_order - b.sort_order) || a.name.localeCompare(b.name));
                if (filtered.length === 0) {
                    $('#servicesSelectGrid').html('<div class="empty-state" style="grid-column:1/-1"><i class="fa-solid fa-tooth"></i> Tidak ada perawatan untuk kategori ini</div>');
                    return;
                }
                let html = '';
                filtered.forEach(function (svc) {
                    html += '<div class="service-select-card">';
                    html += '<input type="radio" name="services[]" value="' + svc.id + '" id="svc_' + svc.id + '" class="service-select-check" data-duration="' + svc.duration_minutes + '" data-name="' + svc.name + '">';
                    html += '<label for="svc_' + svc.id + '"><span class="check-icon"><i class="fa-solid fa-check"></i></span>';
                    html += '<span class="service-info"><span class="service-name">' + svc.name + '</span>';
                    html += '<span class="service-meta"><span>' + svc.duration_minutes + ' menit</span></span></span></label></div>';
                });
                $('#servicesSelectGrid').html(html);
            }

            $(document).on('click', '.category-btn', function () {
                const category = $(this).data('category');
                $('.category-btn').removeClass('active');
                $(this).addClass('active');
                activeCategory = category;
                $('#servicesSelectGrid').empty();
                $('input[name="services[]"]').prop('checked', false);
                $('#lainnyaInputArea').removeClass('visible');
                $('#lainnyaText').val('');
                resetFrom('doctor');
                $('#serviceListArea').addClass('visible');
                if (category === 'lainnya') {
                    $('#servicesSelectGrid').hide();
                    $('#lainnyaInputArea').addClass('visible');
                    checkServiceSelected();
                } else {
                    $('#servicesSelectGrid').show();
                    renderServicesForCategory(category);
                }
            });

            function checkServiceSelected() {
                const branchId = $('#branchSelect').val();
                let serviceOk  = false, firstServiceId = null;
                if (activeCategory === 'lainnya') {
                    serviceOk = $('#lainnyaText').val().trim().length > 0;
                } else {
                    const checked  = $('input[name="services[]"]:checked');
                    serviceOk      = checked.length > 0;
                    firstServiceId = checked.first().val();
                }
                if (!serviceOk) { resetFrom('doctor'); setStep(2); updateDurationSummary(); return; }
                updateDurationSummary();
                resetFrom('doctor');
                setStep(3);
                $('#doctorSection').addClass('visible');
                $('#doctorSelect').html('<option value=""  hidden selected>Memuat dokter...</option>');
                if (activeCategory === 'lainnya') {
                    const firstSvc = servicesData[0];
                    if (firstSvc) { $.getJSON(BASE + '/doctors-by-service/' + branchId + '/' + firstSvc.id, populateDoctors); }
                    else { $('#doctorSelect').html('<option value="">Tidak ada dokter tersedia</option>'); }
                } else {
                    $.getJSON(BASE + '/doctors-by-service/' + branchId + '/' + firstServiceId, populateDoctors);
                }
            }

            function populateDoctors(data) {
                if (data.length === 0) { $('#doctorSelect').html('<option value="">Tidak ada dokter tersedia</option>'); return; }
                let opts = '<option value=""  hidden selected>-- Pilih Dokter --</option>';
                data.forEach(function (d) { opts += '<option value="' + d.id + '">' + d.name + '</option>'; });
                $('#doctorSelect').html(opts);
            }

            $(document).on('change', '.service-select-check', function () { checkServiceSelected(); });
            $(document).on('input', '#lainnyaText', function () { checkServiceSelected(); checkSubmit(); });

            $('#branchSelect').on('change', function () {
                const branchId = $(this).val();
                resetFrom('service');
                if (!branchId) { setStep(1); return; }
                setStep(2);
                $('#serviceSection').addClass('visible');
                $.getJSON(BASE + '/services', function (data) {
                    servicesData = data;
                    buildCategoryButtons(data);
                });
            });

            $('#doctorSelect').on('change', function () {
                const doctorId = $(this).val();
                const branchId = $('#branchSelect').val();
                resetFrom('date');
                if (!doctorId) { setStep(3); return; }
                setStep(4);
                $('#dateSection').addClass('visible');
                $('#dateGrid').html('<div class="loading"><i class="fa-solid fa-spinner"></i> Memuat jadwal...</div>');
                $.getJSON(BASE + '/dates/' + branchId + '/' + doctorId, function (data) {
                    if (data.length === 0) { $('#dateGrid').html('<div class="empty-state"><i class="fa-regular fa-calendar-xmark"></i> Tidak ada jadwal tersedia</div>'); return; }
                    allDates = data; currentMonthIndex = 0; renderMonthDates();
                });
            });

            $(document).on('click', '.date-pill', function () {
                const date = $(this).data('date');
                const branchId = $('#branchSelect').val(), doctorId = $('#doctorSelect').val();
                resetFrom('slot');
                $('.date-pill').removeClass('selected');
                $(this).addClass('selected');
                selectedDate = date;
                $('#reservationDate').val(date);
                $('#slotSection').addClass('visible');
                $('#slotsGrid').html('<div class="loading"><i class="fa-solid fa-spinner"></i> Memuat slot waktu...</div>');
                $.getJSON(BASE + '/slots/' + branchId + '/' + doctorId + '/' + date, function (data) {
                    if (!data.slots || data.slots.length === 0) { $('#slotsGrid').html('<div class="empty-state"><i class="fa-regular fa-clock"></i> Tidak ada slot tersedia</div>'); return; }
                    let html = '';
                    data.slots.forEach(function (slot) {
                        let cls = 'slot', statusText = 'Tersedia';
                        if (slot.is_booked) { cls += ' booked'; statusText = 'Terisi'; }
                        else if (slot.is_past) { cls += ' past'; statusText = 'Lewat'; }
                        html += '<div class="' + cls + '" data-time="' + slot.time + '" data-end="' + slot.end_time + '">';
                        html += '<div>' + slot.label + '</div><div class="slot-status">' + statusText + '</div></div>';
                    });
                    $('#slotsGrid').html(html);
                });
            });

            $(document).on('click', '.slot:not(.booked):not(.past)', function () {
                $('.slot').removeClass('selected');
                $(this).addClass('selected');
                selectedTime = $(this).data('time');
                $('#reservationTime').val(selectedTime);
                updateDurationSummary();
                setStep(5);
                $('#customerSection').addClass('visible');
                checkSubmit();
            });

            window.checkSubmit = function () {
                const patientType = $('#patientTypeInput').val();
                const promoCode   = $('#promoInput').val().trim();
                const promoCodeLama = $('#promoInputLama').val().trim();

                let customerOk = false;

                if (patientType === 'baru') {
                    // Pasien baru: semua field wajib diisi
                    const promoOk = promoCode === '' || (promoCode !== '' && promoFilled && promoValid);
                    customerOk = $('#nameInput').val().trim()
                        && $('#phoneInput').val().trim()
                        && $('#emailInput').val().trim()
                        && $('#jenisKelaminInput').val()
                        && $('#tanggalLahirInput').val()
                        && promoOk;
                    if (promoCode !== '' && !promoFilled) {
                        $('#promoStatus').html('<span style="color:#f59e0b">⚠️ Klik "Cek" untuk validasi kode promo</span>');
                    }
                } else if (patientType === 'lama') {
                    // Pasien lama: cukup cek customer ID sudah ada (dari hasil cek HP)
                    const promoLamaOk = promoCodeLama === '' || (promoCodeLama !== '' && promoFilledLama && promoValidLama);
                    customerOk = $('#existingCustomerId').val() !== '' && promoLamaOk;
                }

                let serviceOk = false;
                if (activeCategory === 'lainnya') {
                    serviceOk = $('#lainnyaText').val().trim().length > 0;
                } else {
                    serviceOk = $('input[name="services[]"]:checked').length > 0;
                }

                const ok = serviceOk
                    && $('#doctorSelect').val()
                    && $('#reservationDate').val()
                    && $('#reservationTime').val()
                    && $('#branchSelect').val()
                    && customerOk;

                $('#previewBtn').prop('disabled', !ok);
            };

            $('#nameInput, #phoneInput, #emailInput').on('input', function () { checkSubmit(); });
            $('#jenisKelaminInput, #tanggalLahirInput').on('change', function () { checkSubmit(); });

            // URL param preselect cabang
            const urlParams = new URLSearchParams(window.location.search);
            const cabangParam = urlParams.get('cabang');
            if (cabangParam) {
                let matchedId = null, matchedName = null;
                $('#branchSelect option').each(function () {
                    if ($(this).text().trim().toLowerCase() === cabangParam.trim().toLowerCase() && $(this).val()) {
                        $(this).prop('selected', true);
                        matchedId = $(this).val();
                        matchedName = $(this).text().trim();
                        return false;
                    }
                });
                if (matchedId) {
                    $('#branchSelect').hide();
                    $('#branchLocked').html('<i class="fa-solid fa-location-dot" style="color:#461500;margin-right:6px"></i>' + matchedName).show();
                    setStep(2);
                    resetFrom('service');
                    $('#serviceSection').addClass('visible');
                    $.getJSON(BASE + '/services', function (data) {
                        servicesData = data;
                        buildCategoryButtons(data);
                    });
                }
            }
        });

        function selectPatientType(type) {
            $('#patientTypeInput').val(type);
            $('#btnPasienBaru, #btnPasienLama').removeClass('active');
            $('#pasienBaruArea, #pasienLamaArea, #pasienLamaPromoArea').removeClass('visible');

            if (type === 'baru') {
                $('#btnPasienBaru').addClass('active');
                $('#pasienBaruArea').addClass('visible');
            } else {
                $('#btnPasienLama').addClass('active');
                $('#pasienLamaArea').addClass('visible');
            }
            checkSubmit();
        }

        function cekNomorHP() {
            const phone = $('#phoneCheckInput').val().trim();
            if (!phone) {
                $('#phoneCheckStatus').html('<div class="not-found-alert"><i class="fa-solid fa-circle-exclamation"></i> Masukkan nomor HP terlebih dahulu</div>');
                return;
            }

            // Loading state
            $('#btnCekHP').text('Mengecek...').prop('disabled', true);
            $('#phoneCheckStatus').html('<div style="color:#8b7a6b;font-size:0.85rem;padding:8px 0"><i class="fa-solid fa-spinner fa-spin"></i> Mencari data...</div>');

            $.get('/api/reservation/check-customer', { phone: phone }, function (res) {
                if (res.found) {
                    // Pasien ditemukan → isi form read-only
                    $('#existingCustomerId').val(res.customer_id);
                    $('#namaLama').val(res.name);
                    $('#phoneLama').val(res.phone);
                    $('#emailLama').val(res.email || '-');
                    $('#jenisKelaminLama').val(res.jenis_kelamin === 'L' ? 'Laki-laki' : res.jenis_kelamin === 'P' ? 'Perempuan' : '-');
                    $('#tanggalLahirLama').val(res.tanggal_lahir || '-');

                    $('#phoneCheckStatus').html(
                        '<div class="found-alert"><i class="fa-solid fa-circle-check"></i> Data ditemukan! Silakan periksa data di bawah.</div>'
                    );
                    $('#pasienLamaForm').show();
                    // Tampilkan juga field promo untuk pasien lama
                    $('#pasienLamaPromoArea').addClass('visible');
                } else {
                    // Tidak ditemukan
                    $('#existingCustomerId').val('');
                    $('#pasienLamaForm').hide();
                    $('#pasienLamaPromoArea').removeClass('visible');
                    $('#phoneCheckStatus').html(
                        '<div class="not-found-alert"><i class="fa-solid fa-circle-exclamation"></i> ' + res.message + '</div>'
                    );
                }
                checkSubmit();
            }).fail(function () {
                $('#phoneCheckStatus').html('<div class="not-found-alert"><i class="fa-solid fa-circle-exclamation"></i> Gagal menghubungi server, coba lagi.</div>');
                checkSubmit();
            }).always(function () {
                $('#btnCekHP').text('Cek').prop('disabled', false);
            });
        }

        // Reset state jika nomor HP diubah setelah dicek
        function resetPhoneCheck() {
            $('#existingCustomerId').val('');
            $('#phoneCheckStatus').html('');
            $('#pasienLamaForm').hide();
            $('#pasienLamaPromoArea').removeClass('visible');
            checkSubmit();
        }

        // Promo untuk pasien baru
        let promoValid = false, promoFilled = false;
        window.checkPromo = function () {
            const code = $('#promoInput').val().trim().toUpperCase();
            if (!code) { $('#promoStatus').html('<span style="color:#dc2626">⚠️ Masukkan kode promo terlebih dahulu</span>'); return; }
            $('#checkPromoBtn').text('Mengecek...').prop('disabled', true);
            $.get('/api/promo/validate', { code: code }, function (res) {
                if (res.valid) {
                    promoValid = true; promoFilled = true;
                    $('#promoIdInput').val(res.promo_id ?? '');
                    let msg = '✅ ' + res.message;
                    msg += res.valid_until ? ' <span style="color:#6b7280;font-weight:500">· Berlaku s/d ' + res.valid_until + '</span>' : ' <span style="color:#6b7280;font-weight:500">· Berlaku selamanya</span>';
                    $('#promoStatus').html('<span style="color:#16a34a;font-weight:600">' + msg + '</span>');
                } else {
                    promoValid = false; promoFilled = true;
                    $('#promoStatus').html('<span style="color:#dc2626">❌ ' + res.message + '</span>');
                }
                checkSubmit();
            }).fail(function () {
                promoValid = false;
                $('#promoStatus').html('<span style="color:#dc2626">❌ Gagal mengecek promo</span>');
                checkSubmit();
            }).always(function () { $('#checkPromoBtn').text('Cek').prop('disabled', false); });
        };
        $('#promoInput').on('input', function () {
            promoValid = false; promoFilled = false;
            $('#promoIdInput').val(''); $('#promoStatus').html('');
            checkSubmit();
        });

        // Promo untuk pasien lama
        let promoValidLama = false, promoFilledLama = false;
        window.checkPromoLama = function () {
            const code = $('#promoInputLama').val().trim().toUpperCase();
            if (!code) { $('#promoStatusLama').html('<span style="color:#dc2626">⚠️ Masukkan kode promo terlebih dahulu</span>'); return; }
            $('#checkPromoBtnLama').text('Mengecek...').prop('disabled', true);
            $.get('/api/promo/validate', { code: code }, function (res) {
                if (res.valid) {
                    promoValidLama = true; promoFilledLama = true;
                    $('#promoIdInputLama').val(res.promo_id ?? '');
                    let msg = '✅ ' + res.message;
                    msg += res.valid_until ? ' <span style="color:#6b7280;font-weight:500">· Berlaku s/d ' + res.valid_until + '</span>' : ' <span style="color:#6b7280;font-weight:500">· Berlaku selamanya</span>';
                    $('#promoStatusLama').html('<span style="color:#16a34a;font-weight:600">' + msg + '</span>');
                } else {
                    promoValidLama = false; promoFilledLama = true;
                    $('#promoStatusLama').html('<span style="color:#dc2626">❌ ' + res.message + '</span>');
                }
                checkSubmit();
            }).fail(function () {
                promoValidLama = false;
                $('#promoStatusLama').html('<span style="color:#dc2626">❌ Gagal mengecek promo</span>');
                checkSubmit();
            }).always(function () { $('#checkPromoBtnLama').text('Cek').prop('disabled', false); });
        };
        $('#promoInputLama').on('input', function () {
            promoValidLama = false; promoFilledLama = false;
            $('#promoIdInputLama').val(''); $('#promoStatusLama').html('');
            checkSubmit();
        });

        function showPreview() {
            const branchText = $('#branchSelect option:selected').text();
            const doctorText = $('#doctorSelect option:selected').text();
            const dateText   = $('.date-pill.selected').data('label') || $('#reservationDate').val();
            const timeText   = $('#reservationTime').val();
            const patientType = $('#patientTypeInput').val();

            // Ambil data sesuai tipe pasien
            let name, phone, email, jenisKel, tglLahir, promoCode, promoMsg;
            if (patientType === 'lama') {
                name      = $('#namaLama').val();
                phone     = $('#phoneLama').val();
                email     = $('#emailLama').val();
                jenisKel  = $('#jenisKelaminLama').val();
                tglLahir  = $('#tanggalLahirLama').val();
                promoCode = $('#promoInputLama').val().trim().toUpperCase();
                promoMsg  = promoValidLama ? $('#promoStatusLama').text().replace('✅ ', '') : null;
            } else {
                name      = $('#nameInput').val();
                phone     = $('#phoneInput').val();
                email     = $('#emailInput').val();
                jenisKel  = $('#jenisKelaminInput').val() === 'L' ? 'Laki-laki' : 'Perempuan';
                tglLahir  = $('#tanggalLahirInput').val();
                promoCode = $('#promoInput').val().trim().toUpperCase();
                promoMsg  = promoValid ? $('#promoStatus').text().replace('✅ ', '') : null;
            }

            let svcHtml = '', totalDuration = 0;
            if (activeCategory === 'lainnya') {
                const txt = $('#lainnyaText').val().trim();
                svcHtml = '<div class="svc-item"><span class="svc-name">Lainnya: ' + txt + '</span><span class="svc-detail">~30 menit</span></div>';
                totalDuration = 30;
            } else {
                $('input[name="services[]"]:checked').each(function () {
                    const n = $(this).data('name'), d = parseInt($(this).data('duration'));
                    totalDuration += d;
                    svcHtml += '<div class="svc-item"><span class="svc-name">' + n + '</span><span class="svc-detail">' + d + ' menit</span></div>';
                });
            }

            const parts = timeText.split(':');
            const endMin = parseInt(parts[0]) * 60 + parseInt(parts[1]) + totalDuration;
            const endH = String(Math.floor(endMin / 60)).padStart(2, '0');
            const endM = String(endMin % 60).padStart(2, '0');

            let html = '';
            html += '<div class="preview-row"><span class="preview-label">Tipe Pasien</span><span class="preview-value" style="color:' + (patientType === 'lama' ? '#1565c0' : '#2e7d32') + '">' + (patientType === 'lama' ? 'Pasien Lama' : 'Pasien Baru') + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Nama</span><span class="preview-value">' + name + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">WhatsApp</span><span class="preview-value">' + phone + '</span></div>';
            if (email && email !== '-') html += '<div class="preview-row"><span class="preview-label">Email</span><span class="preview-value">' + email + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Jenis Kelamin</span><span class="preview-value">' + jenisKel + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Tanggal Lahir</span><span class="preview-value">' + tglLahir + '</span></div>';
            if (promoCode && (promoValid || promoValidLama)) { html += '<div class="preview-row"><span class="preview-label">Promo</span><span class="preview-value" style="color:#16a34a;font-weight:600">' + promoCode + ' — ' + promoMsg + '</span></div>'; }
            html += '<div class="preview-row"><span class="preview-label">Cabang</span><span class="preview-value">' + branchText + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Perawatan</span><span class="preview-value"></span></div>';
            html += '<div class="preview-services">' + svcHtml + '</div>';
            html += '<div class="preview-row"><span class="preview-label">Dokter</span><span class="preview-value">' + doctorText + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Tanggal</span><span class="preview-value">' + dateText + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Jam</span><span class="preview-value">' + timeText + ' — ' + endH + ':' + endM + '</span></div>';
            html += '<div class="preview-total"><span>Estimasi Durasi</span><span>' + totalDuration + ' menit</span></div>';

            $('#previewBody').html(html);
            $('#previewModal').addClass('show');
        }

        function closePreview() { $('#previewModal').removeClass('show'); }

        function confirmSubmit() {
            const btn = document.querySelector('.btn-modal.confirm');
            if (btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';

            // Jika pasien lama, salin promo dari field lama ke field utama
            if ($('#patientTypeInput').val() === 'lama') {
                $('input[name="promo_code"]').first().val($('#promoInputLama').val());
                $('input[name="promo_id"]').first().val($('#promoIdInputLama').val());
            }

            function setCookie(n, v, d) {
                const dt = new Date();
                dt.setTime(dt.getTime() + (d * 24 * 60 * 60 * 1000));
                document.cookie = n + '=' + encodeURIComponent(v) + ';expires=' + dt.toUTCString() + ';path=/';
            }
            setCookie('st_name', $('#nameInput').val() || $('#namaLama').val(), 30);
            setCookie('st_phone', $('#phoneInput').val() || $('#phoneLama').val(), 30);
            $('#reservationForm').submit();
        }

        $(document).on('click', '.modal-overlay', function (e) { if (e.target === this) closePreview(); });

        flatpickr("#tanggalLahirInput", {
            locale: "id",
            dateFormat: "Y-m-d",
            maxDate: "today",
            yearRange: [1940, new Date().getFullYear() - 1],
            disableMobile: true,
            onChange: function () { checkSubmit(); }
        });
    </script>
</body>
</html>