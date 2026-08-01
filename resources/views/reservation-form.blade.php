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
        #apTgl[readonly] { cursor: pointer; background: #fffaf5; }
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
        .category-btn.category-featured { grid-column: 1 / -1; font-size: 0.95rem; padding: 16px 20px; }
        .category-hint { font-size: 0.78rem; color: #8b7a6b; margin-bottom: 12px; font-weight: 600; }

        /* Branch cards */
        .branch-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .branch-card { display: flex; align-items: center; gap: 12px; padding: 16px 18px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; cursor: pointer; font-family: 'Montserrat', sans-serif; font-size: 0.88rem; font-weight: 700; color: #5a3a20; transition: all 0.3s; text-align: left; width: 100%; }
        .branch-card:hover { border-color: rgba(70,21,0,0.3); background: #fef5ed; }
        .branch-card.active { border-color: #461500; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .branch-card .branch-icon { width: 34px; height: 34px; border-radius: 50%; background: rgba(70,21,0,0.08); display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0; transition: all 0.3s; }
        .branch-card.active .branch-icon { background: rgba(255,255,255,0.2); color: #fff; }

        /* Behel terlaris badge */
        .terlaris-badge { display: inline-block; font-size: 0.58rem; font-weight: 800; background: #c0392b; color: #fff; padding: 2px 8px; border-radius: 20px; margin-left: 10px; vertical-align: middle; letter-spacing: 0.4px; text-transform: uppercase; }
        .category-btn.active .terlaris-badge { background: rgba(255,255,255,0.3); }
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

        /* Session-grouped accordion (Pagi/Siang/Sore) */
        .slot-sessions { display: flex; flex-direction: column; gap: 10px; }
        .slot-session { border: 1px solid rgba(70,21,0,0.12); border-radius: 14px; background: #fffaf5; overflow: hidden; transition: border-color 0.2s; }
        .slot-session.empty { opacity: 0.55; }
        .session-header { width: 100%; display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; background: transparent; border: 0; cursor: pointer; font-size: 0.92rem; font-weight: 700; color: #461500; font-family: inherit; }
        .session-header:hover { background: rgba(70,21,0,0.03); }
        .session-header .session-title { display: flex; align-items: center; gap: 10px; }
        .session-header .session-range { font-size: 0.72rem; font-weight: 500; color: #8b7a6b; margin-left: 4px; }
        .session-header .session-count { font-size: 0.72rem; font-weight: 700; color: #6b2400; background: rgba(70,21,0,0.06); padding: 4px 10px; border-radius: 999px; margin-right: 10px; }
        .slot-session.empty .session-count { color: #b0a090; background: #f5f0eb; }
        .session-header .chev { transition: transform 0.2s; color: #8b7a6b; }
        .slot-session[aria-expanded="false"] .chev { transform: rotate(-90deg); }
        .slot-session[aria-expanded="false"] .session-slots { display: none; }
        .session-slots { padding: 0 14px 14px; display: grid; grid-template-columns: repeat(auto-fill, minmax(95px, 1fr)); gap: 8px; }
        .session-slots .slot { padding: 12px 6px; font-size: 0.88rem; }
        .session-empty-hint { padding: 0 18px 16px; font-size: 0.78rem; color: #8b7a6b; font-style: italic; }

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

        /* Daftar pilihan pasien (1 nomor banyak pasien) */
        /* Daftar pasien (1 nomor → banyak pasien) */
        .patient-choice-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 4px; }
        .pc-row { background: #fffaf5; border: 2px solid rgba(70,21,0,0.10); border-radius: 12px; padding: 12px 14px; color: #461500; }
        .pc-row.pc-new { border-style: dashed; border-color: rgba(70,21,0,0.25); background: #fff7ef; }
        .pc-head { display: flex; align-items: center; gap: 12px; cursor: pointer; font-size: 0.92rem; justify-content: flex-start; line-height: 1.4; }
        .pc-head > span:first-child, .pc-head > label, .pc-head input + span { flex: 1; }
        .pc-head input[type=checkbox] { accent-color: #461500; width: 18px; height: 18px; flex-shrink: 0; margin: 0; cursor: pointer; }
        .pc-head strong { vertical-align: middle; }
        .pc-head small { color: #8b7a6b; font-weight: 500; vertical-align: middle; margin-left: 4px; }
        .pc-badge { background: #461500; color: #fff; border-radius: 6px; padding: 1px 6px; font-size: 0.66rem; font-weight: 700; letter-spacing: .4px; }
        .pc-remove { color: #b91c1c; font-weight: 800; text-decoration: none; font-size: 1.05rem; padding: 0 6px; }
        .pc-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; font-size: 0.82rem; }
        .pc-meta a { color: #461500; font-weight: 700; text-decoration: underline; }
        .pc-ok { color: #16a34a; font-weight: 700; }
        .pc-wait { color: #b45309; font-weight: 600; }
        .pc-pending { color: #8b7a6b; font-style: italic; }
        .pc-empty { color: #8b7a6b; font-size: 0.85rem; padding: 12px; text-align: center; border: 1px dashed rgba(70,21,0,0.2); border-radius: 12px; }
        .pc-override { display: flex; gap: 12px; margin-top: 10px; flex-wrap: wrap; }
        .pc-override > label, .pc-override > .pc-override-svc { flex: 1; min-width: 180px; font-size: 0.8rem; color: #8b7a6b; font-weight: 600; display: flex; flex-direction: column; gap: 6px; }
        .pc-override select { font-size: 0.85rem; }
        .pc-override-svc .pc-svc-title { color: #8b7a6b; font-size: 0.8rem; font-weight: 600; }
        .pc-override-svc .pc-svc-title small { font-weight: 500; color: #a89888; }
        .pc-override-services { display: flex; flex-direction: column; max-height: 140px; overflow-y: auto; background: #fff; border: 1.5px solid rgba(70,21,0,0.15); border-radius: 8px; }
        .pc-svc-chk label { display: flex; align-items: center; gap: 8px; padding: 6px 10px; cursor: pointer; transition: background 0.15s; font-size: 0.82rem; font-weight: 500; color: #5a3a20; margin: 0; line-height: 1.3; }
        .pc-svc-chk + .pc-svc-chk label { border-top: 1px solid rgba(70,21,0,0.06); }
        .pc-svc-chk input[type=checkbox] { accent-color: #461500; width: 15px; height: 15px; margin: 0; flex-shrink: 0; cursor: pointer; }
        .pc-svc-chk label:hover { background: #fffaf5; }
        .pc-svc-chk input:checked ~ .pc-svc-name { color: #461500; font-weight: 700; }
        .add-patient-btn { width: 100%; margin-top: 12px; padding: 12px; background: #fff; color: #461500; border: 2px dashed rgba(70,21,0,0.35); border-radius: 12px; font-family: 'Montserrat',sans-serif; font-weight: 700; font-size: 0.88rem; cursor: pointer; }
        .add-patient-btn:hover { background: #fef5ed; }
        .add-patient-form { margin-top: 12px; padding: 14px; background: #fffaf5; border: 1px solid #e6d5c4; border-radius: 12px; }
        .btn-cancel-add { background: #f0e8e0; color: #461500; border: none; border-radius: 12px; padding: 0 18px; font-weight: 700; font-family: 'Montserrat',sans-serif; cursor: pointer; }

        /* Doctor cards */
        .doctor-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .doctor-card { display: flex; align-items: center; gap: 14px; padding: 16px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.08); border-radius: 14px; cursor: pointer; transition: all 0.3s; }
        .doctor-card:hover { border-color: rgba(70,21,0,0.25); background: #fef5ed; transform: translateY(-2px); }
        .doctor-card.selected { border-color: #461500; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(70,21,0,0.2); }
        .doctor-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #e8d5c4, #d4b89a); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #461500; font-weight: 800; flex-shrink: 0; transition: all 0.3s; overflow: hidden; }
        .doctor-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .doctor-card.selected .doctor-avatar { background: rgba(255,255,255,0.2); color: #fff; }
        .doctor-info { flex: 1; min-width: 0; }
        .doctor-name { font-size: 0.9rem; font-weight: 700; color: #461500; transition: color 0.3s; }
        .doctor-card.selected .doctor-name { color: #fff; }
        .doctor-days { font-size: 0.72rem; font-weight: 600; color: #8b7a6b; margin-top: 3px; transition: color 0.3s; }
        .doctor-card.selected .doctor-days { color: rgba(255,255,255,0.75); }
        .doctor-empty { grid-column: 1 / -1; text-align: center; padding: 24px; color: #8b7a6b; font-size: 0.9rem; }

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
            .container { padding: 0 16px; }
            .navbar { padding: 0 16px; }
            .navbar-inner { height: 60px; }
            .navbar-brand { font-size: 1.2rem; }
            .btn-back { padding: 8px 14px; font-size: 0.78rem; }
            .form-section { padding: 32px 0 60px; }
            .form-header { margin-bottom: 28px; }
            .form-header h1 { font-size: 1.5rem; }
            .form-header p { font-size: 0.88rem; }
            .form-card { padding: 24px 16px; border-radius: 18px; }
            .form-row { grid-template-columns: 1fr; }
            .services-select-grid { grid-template-columns: 1fr; }

            /* Step indicator mobile — inactive/done = hanya circle, active = pill + label */
            .steps { gap: 4px; flex-wrap: nowrap; }
            .step { padding: 5px 6px; background: transparent; font-size: 0.7rem; }
            .step.done { background: transparent; }
            .step.active { padding: 7px 12px; background: linear-gradient(135deg, #461500, #6b2400); }
            .step span { display: none; }
            .step.active span { display: inline; }
            .step-num { width: 22px; height: 22px; font-size: 0.66rem; }
            .step.done .step-num { background: #2e7d32; color: #fff; }

            /* Branch grid — 1 kolom di layar sempit */
            .branch-grid { grid-template-columns: 1fr; gap: 10px; }
            .branch-card { padding: 14px 16px; font-size: 0.85rem; }

            /* Kategori */
            .category-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .category-btn { padding: 12px 10px; font-size: 0.78rem; gap: 8px; }
            .category-icon { width: 28px; height: 28px; font-size: 0.75rem; }
            .category-btn.category-featured { padding: 14px 14px; font-size: 0.85rem; }
            .terlaris-badge { font-size: 0.54rem; padding: 2px 6px; margin-left: 6px; }

            /* Patient type */
            .patient-type-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .patient-type-btn { padding: 16px 12px; }
            .patient-type-btn .btn-title { font-size: 0.85rem; }
            .patient-type-icon { width: 40px; height: 40px; font-size: 1rem; }

            /* Form */
            .form-group label { font-size: 0.8rem; }
            .form-control { padding: 12px 14px; font-size: 0.88rem; border-radius: 12px; }

            /* Doctor */
            .doctor-grid { grid-template-columns: 1fr; }
            .doctor-card { padding: 14px; }

            /* Date */
            .date-grid { grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 8px; }
            .date-pill { padding: 10px 6px; border-radius: 12px; min-height: 64px; }
            .date-pill .date-num { font-size: 0.95rem; }
            .date-pill .date-day { font-size: 0.68rem; }

            /* Slot sessions — 3 kolom, tap target lebih besar */
            .session-header { padding: 14px 16px; min-height: 52px; }
            .session-slots { grid-template-columns: repeat(3, 1fr); padding: 0 10px 12px; gap: 8px; }
            .session-slots .slot { padding: 13px 4px; font-size: 0.82rem; border-radius: 10px; min-height: 44px; }

            /* Chain section spacing */
            .chain-section + .chain-section { margin-top: 20px; }

            /* Duration */
            .duration-summary { padding: 14px 16px; flex-wrap: wrap; gap: 12px; }
            .duration-summary .summary-label { font-size: 0.65rem; }
            .duration-summary .summary-value { font-size: 0.95rem; }

            /* Submit & modal */
            .btn-submit { padding: 14px; font-size: 0.9rem; border-radius: 12px; }
            .modal-box { border-radius: 18px; margin: 16px; }
            .modal-body, .modal-header, .modal-footer { padding-left: 18px; padding-right: 18px; }
            .modal-header h2 { font-size: 1.2rem; }
            .preview-row { font-size: 0.82rem; }
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

                    <form method="POST" action="{{ route('landing.reservation.store') }}" id="reservationForm" novalidate>                   
                        @csrf

                    <div class="form-group" id="branchGroup">
                        <label>Pilih Cabang <span class="required">*</span></label>
                        {{-- Hidden select untuk value tracking di JS yang sudah ada --}}
                        <select name="raw_branch_id" id="branchSelect" style="display:none">
                            <option value="" selected></option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <div id="branchGrid" class="branch-grid">
                            @foreach ($branches as $branch)
                                <button type="button" class="branch-card"
                                        data-id="{{ $branch->id }}"
                                        data-name="{{ $branch->name }}">
                                    <span class="branch-icon"><i class="fa-solid fa-map-marker-alt"></i></span>
                                    <span>{{ $branch->name }}</span>
                                </button>
                            @endforeach
                        </div>
                        <div id="branchLocked" style="display:none; padding:14px 18px; background:#f5f0eb; border:2px solid rgba(70,21,0,0.15); border-radius:14px; font-weight:700; color:#461500;"></div>
                    </div>

                    <div class="chain-section" id="serviceSection">
                        <div class="form-group">
                            <label>Pilih Perawatan <span class="required">*</span></label>
                            <div class="category-grid" id="categoryGrid"></div>
                            <div class="service-list-area" id="serviceListArea">
                                <p class="category-hint"><i class="fa-solid fa-info-circle"></i> Pilih <strong>1 perawatan</strong>:</p>
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
                    </div>

                    <div class="chain-section" id="doctorSection">
                        <div class="form-group">
                            <label>Pilih Dokter <span class="required">*</span></label>
                            <input type="hidden" name="raw_doctor_id" id="doctorValue">
                            <div class="doctor-grid" id="doctorGrid">
                                <!-- populated by JS -->
                            </div>
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
                            <div id="slotsGrid" class="slot-sessions"></div>
                            <input type="hidden" name="reservation_time" id="reservationTime">
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

                    <div class="chain-section" id="customerSection">

                        {{-- 1 nomor → banyak pasien. Jadwal di atas (cabang/
                             perawatan/dokter/tanggal/jam) jadi DEFAULT semua
                             pasien; jam tiap pasien disusun otomatis. --}}
                        <div class="phone-check-box">
                            <label>Masukkan Nomor WhatsApp Kamu <span class="required">*</span></label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" id="phoneCheckInput" class="form-control"
                                    placeholder="08xxxxxxxxxx" oninput="resetPhoneCheck()">
                                <button type="button" id="btnCekHP" onclick="cekNomorHP()"
                                    style="background:#461500;color:white;border:none;border-radius:10px;padding:14px 18px;font-size:0.85rem;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap;">
                                    Cek
                                </button>
                            </div>
                            <div id="phoneCheckStatus" style="margin-top:10px;"></div>
                        </div>

                        <div id="patientPanel" style="display:none;">
                            <div class="form-group">
                                <label>Untuk siapa reservasi ini? <span class="required">*</span>
                                    <span style="color:#8b7a6b;font-weight:400;font-size:0.75rem;text-transform:none">(bisa lebih dari 1)</span>
                                </label>
                                <div id="patientList" class="patient-choice-list"></div>
                            </div>

                            <button type="button" class="add-patient-btn" id="addPatientBtn"
                                onclick="RES.toggleAddForm(true)">
                                <i class="fa-solid fa-user-plus"></i> Tambah pasien baru di nomor ini
                            </button>

                            {{-- Form tambah pasien baru (inline, bisa diulang) --}}
                            <div id="addPatientForm" class="add-patient-form" style="display:none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Nama Lengkap <span class="required">*</span></label>
                                        <input type="text" id="apName" class="form-control" placeholder="Nama pasien">
                                    </div>
                                    <div class="form-group">
                                        <label>Pasien ini adalah</label>
                                        <select id="apRelasi" class="form-control">
                                            <option value="diri_sendiri">Diri sendiri</option>
                                            <option value="pasangan">Pasangan (suami/istri)</option>
                                            <option value="anak" selected>Anak</option>
                                            <option value="orang_tua">Orang tua</option>
                                            <option value="lainnya">Lainnya</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Jenis Kelamin <span class="required">*</span></label>
                                        <select id="apJk" class="form-control">
                                            <option value="">-- Pilih --</option>
                                            <option value="L">Laki-laki</option>
                                            <option value="P">Perempuan</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Lahir <span class="required">*</span></label>
                                        <input type="text" id="apTgl" class="form-control"
                                            placeholder="Pilih tanggal lahir" readonly>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Email <span style="color:#8b7a6b;font-weight:400;font-size:0.75rem;text-transform:none">(opsional)</span></label>
                                        <input type="email" id="apEmail" class="form-control" placeholder="email@contoh.com">
                                    </div>
                                </div>
                                <div style="display:flex;gap:8px;">
                                    <button type="button" class="btn-submit" style="flex:1" onclick="RES.commitAddPatient()">
                                        <i class="fa-solid fa-check"></i> Tambahkan pasien
                                    </button>
                                    <button type="button" class="btn-cancel-add" onclick="RES.toggleAddForm(false)">Batal</button>
                                </div>
                                <div id="apStatus" style="margin-top:6px;font-size:0.82rem;"></div>
                            </div>

                            <div class="form-group" style="margin-top:18px;">
                                <label>Kode Promo <span style="color:#8b7a6b;font-weight:400;font-size:0.75rem;text-transform:none">(opsional, berlaku semua pasien)</span></label>
                                <div style="display:flex;gap:8px;align-items:center">
                                    <input type="text" name="promo_code" id="promoInput" class="form-control" style="text-transform:uppercase">
                                    <button type="button" id="checkPromoBtn" onclick="checkPromo()"
                                        style="background:#461500;color:white;border:none;border-radius:8px;padding:14px 18px;font-size:0.85rem;font-family:'Montserrat',sans-serif;font-weight:700;cursor:pointer;white-space:nowrap">
                                        Cek
                                    </button>
                                </div>
                                <div id="promoStatus" style="margin-top:6px;font-size:0.82rem"></div>
                            </div>
                        </div>

                        {{-- Hidden global fields untuk server (patients[] di-inject saat submit) --}}
                        <input type="hidden" name="phone" id="phoneInput" value="">
                        <input type="hidden" name="default_start" id="defaultStartInput" value="">
                        <input type="hidden" name="promo_id" id="promoIdInput" value="">
                        <div id="patientsHiddenWrap"></div>

                        <button type="button" class="btn-submit" id="previewBtn" disabled onclick="showPreview()">
                            <i class="fa-solid fa-eye"></i> Preview Reservasi
                        </button>
                        <p class="form-note">
                            <i class="fa-solid fa-shield-alt"></i>
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
    let effectiveDuration = 0; // durasi dari server (override per-dokter), 0 = fallback ke data-duration service

    $(function () {
        const BASE = '/api/reservation';
        let selectedDate      = null;
        let selectedTime      = null;
        let servicesData      = [];
        let allDates          = [];
        let currentMonthIndex = 0;

            const categoryIcons = {
                'behel'      : 'fa-solid fa-teeth',
                'estetika'   : 'fa-solid fa-star',
                'standar'    : 'fa-solid fa-tooth',
                'pembedahan' : 'fa-solid fa-syringe',
                'lainnya'    : 'fa-solid fa-ellipsis-h',
            };
            const categoryLabels = {
                'behel'      : 'Behel',
                'estetika'   : 'Estetika',
                'standar'    : 'Perawatan Standar',
                'pembedahan' : 'Pembedahan',
                'lainnya'    : 'Lainnya',
            };

            function buildCategoryButtons(data) {
                const fixedOrder = ['behel', 'estetika', 'standar', 'pembedahan', 'lainnya'];
                const extraCategories = [...new Set(data.map(s => s.category).filter(Boolean))]
                    .filter(cat => !fixedOrder.includes(cat));
                const allCategories = [...fixedOrder, ...extraCategories];
                let html = '';
                allCategories.forEach(function (cat) {
                    const icon  = categoryIcons[cat]  || 'fa-solid fa-dot-circle';
                    const label = categoryLabels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
                    const featured = cat === 'behel' ? ' category-featured' : '';
                    html += `<button type="button" class="category-btn${featured}" data-category="${cat}">
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

            if (getCookie('st_phone')) $('#phoneCheckInput').val(getCookie('st_phone'));

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
                } else if (effectiveDuration > 0) {
                    // Prioritas: durasi efektif dari server (sudah perhitungkan override per-dokter)
                    total = effectiveDuration;
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
                // Durasi efektif hanya valid setelah doctor + slot diambil. Reset kalau user mundur sebelum itu.
                if (idx <= order.indexOf('slot')) {
                    effectiveDuration = 0;
                }
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
                    if (s === 'doctor') { $('#doctorGrid').empty(); $('#doctorValue').val(''); }
                    if (s === 'date')   { $('#dateGrid').empty(); $('#reservationDate').val(''); selectedDate = null; allDates = []; }
                    if (s === 'slot')   { $('#slotsGrid').empty(); $('#reservationTime').val(''); selectedTime = null; }
                    if (s === 'customer') {
                        // Reset panel daftar pasien (model 1-nomor-banyak-pasien)
                        if (window.RES) RES.resetPanel();
                    }
                }
                $('#durationSummary').removeClass('visible');
                $('#previewBtn').prop('disabled', true);
            }

            function scrollToSection(id, delay) {
                setTimeout(function () {
                    const el = document.getElementById(id);
                    if (!el) return;
                    const offset = window.innerWidth < 640 ? 76 : 24;
                    const top = el.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({ top, behavior: 'smooth' });
                }, delay || 80);
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
                    html += '<span class="service-meta"><span>Est. ' + svc.duration_minutes + ' menit</span></span></span></label></div>';
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
                scrollToSection('serviceListArea', 60);
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
                resetFrom('doctor');
                updateDurationSummary();
                setStep(3);
                $('#doctorSection').addClass('visible');
                scrollToSection('doctorSection');
                $('#doctorGrid').html('<div class="loading"><i class="fa-solid fa-spinner"></i> Memuat dokter...</div>');
                if (activeCategory === 'lainnya') {
                    const firstSvc = servicesData[0];
                    if (firstSvc) { $.getJSON(BASE + '/doctors-by-service/' + branchId + '/' + firstSvc.id, populateDoctors); }
                    else { $('#doctorGrid').html('<div class="doctor-empty">Tidak ada dokter tersedia</div>'); }
                } else {
                    $.getJSON(BASE + '/doctors-by-service/' + branchId + '/' + firstServiceId, populateDoctors);
                }
            }

            function populateDoctors(data) {
                if (data.length === 0) { $('#doctorGrid').html('<div class="doctor-empty"><i class="fa-solid fa-user-md" style="font-size:2rem;display:block;margin-bottom:8px;color:#c0b0a0;"></i>Tidak ada dokter tersedia</div>'); return; }
                let html = '';
                data.forEach(function (d) {
                    const initials = d.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                    const daysText = d.days && d.days.length ? d.days.join(', ') : 'Jadwal belum diset';
                    const nameSafe = d.name.replace(/"/g, '&quot;');
                    const avatar   = d.photo_url
                        ? '<img src="' + d.photo_url + '" alt="' + nameSafe + '" onerror="this.parentNode.innerHTML=\'' + initials + '\';">'
                        : initials;
                    html += '<div class="doctor-card" data-id="' + d.id + '" data-name="' + nameSafe + '">'
                        + '<div class="doctor-avatar">' + avatar + '</div>'
                        + '<div class="doctor-info">'
                        + '<div class="doctor-name">' + d.name + '</div>'
                        + '<div class="doctor-days"><i class="fa-regular fa-calendar"></i> ' + daysText + '</div>'
                        + '</div></div>';
                });
                $('#doctorGrid').html(html);
            }

            $(document).on('change', '.service-select-check', function () { checkServiceSelected(); });
            $(document).on('input', '#lainnyaText', function () { checkServiceSelected(); checkSubmit(); });

            $(document).on('click', '.branch-card', function () {
                const $card = $(this);
                $('.branch-card').removeClass('active');
                $card.addClass('active');
                $('#branchSelect').val($card.data('id')).trigger('change');
            });

            $('#branchSelect').on('change', function () {
                const branchId = $(this).val();
                resetFrom('service');
                if (!branchId) { setStep(1); return; }
                setStep(2);
                $('#serviceSection').addClass('visible');
                scrollToSection('serviceSection');
                $.getJSON(BASE + '/services', function (data) {
                    servicesData = data;
                    buildCategoryButtons(data);
                });
            });

            $(document).on('click', '.doctor-card', function () {
                $('.doctor-card').removeClass('selected');
                $(this).addClass('selected');
                $('#doctorValue').val($(this).data('id'));
                const doctorId = $(this).data('id');
                const branchId = $('#branchSelect').val();
                resetFrom('date');
                if (!doctorId) { setStep(3); return; }
                setStep(4);
                $('#dateSection').addClass('visible');
                scrollToSection('dateSection');
                $('#dateGrid').html('<div class="loading"><i class="fa-solid fa-spinner"></i> Memuat jadwal...</div>');
                $.getJSON(BASE + '/dates/' + branchId + '/' + doctorId, function (data) {
                    if (data.length === 0) { $('#dateGrid').html('<div class="empty-state"><i class="fa-regular fa-calendar-times"></i> Tidak ada jadwal tersedia</div>'); return; }
                    allDates = data; currentMonthIndex = 0; renderMonthDates();
                });
            });

            $(document).on('click', '.date-pill', function () {
                const date = $(this).data('date');
                const branchId = $('#branchSelect').val(), doctorId = $('#doctorValue').val();
                const serviceId = $('input[name="services[]"]:checked').val() || '';
                resetFrom('slot');
                $('.date-pill').removeClass('selected');
                $(this).addClass('selected');
                selectedDate = date;
                $('#reservationDate').val(date);
                $('#slotSection').addClass('visible');
                scrollToSection('slotSection');
                $('#slotsGrid').html('<div class="loading"><i class="fa-solid fa-spinner"></i> Memuat slot waktu...</div>');

                const url = BASE + '/slots/' + branchId + '/' + doctorId + '/' + date
                          + (serviceId ? ('?service_id=' + encodeURIComponent(serviceId)) : '');

                $.getJSON(url, function (data) {
                    if (!data.slots || data.slots.length === 0) {
                        $('#slotsGrid').html('<div class="empty-state"><i class="fa-regular fa-clock"></i> Tidak ada slot tersedia</div>');
                        return;
                    }

                    // Update durasi dari server (authoritative, sudah pertimbangkan override per dokter)
                    if (data.slot_interval) {
                        effectiveDuration = parseInt(data.slot_interval) || 0;
                        updateDurationSummary();
                    }

                    $('#slotsGrid').html(renderSlotSessions(data.sessions || {}));

                    // Auto-expand: sesi pertama yang punya slot tersedia; kalau tak ada, sesi pertama yg punya slot sama sekali
                    const $sessions = $('#slotsGrid .slot-session');
                    let expanded = false;
                    $sessions.each(function () {
                        const avail = parseInt($(this).data('available')) || 0;
                        if (!expanded && avail > 0) {
                            $(this).attr('aria-expanded', 'true');
                            expanded = true;
                        } else {
                            $(this).attr('aria-expanded', 'false');
                        }
                    });
                    if (!expanded && $sessions.length) {
                        $sessions.first().attr('aria-expanded', 'true');
                    }
                });
            });

            function renderSlotSessions(sessions) {
                const order = ['pagi', 'siang', 'sore'];
                let html = '';
                order.forEach(function (key) {
                    const sess = sessions[key];
                    if (!sess) return;
                    const slots = sess.slots || [];
                    const availCount = sess.available_count || 0;
                    const totalCount = sess.total_count || 0;
                    const isEmpty = totalCount === 0;
                    const isFull = !isEmpty && availCount === 0;

                    const badgeText = isEmpty
                        ? 'Tidak ada jadwal'
                        : (isFull ? 'Penuh' : availCount + ' tersedia');

                    html += '<div class="slot-session' + (isEmpty || isFull ? ' empty' : '') + '"'
                         + ' data-session="' + key + '"'
                         + ' data-available="' + availCount + '"'
                         + ' aria-expanded="false">';

                    html += '<button type="button" class="session-header"'
                          + (isEmpty ? ' disabled' : '') + '>';
                    html += '<span class="session-title"><span>' + sess.label + '</span>'
                          + '<span class="session-range">' + sess.range + '</span></span>';
                    html += '<span style="display:flex;align-items:center;">'
                          + '<span class="session-count">' + badgeText + '</span>'
                          + '<i class="fa-solid fa-chevron-down chev"></i></span>';
                    html += '</button>';

                    if (isEmpty) {
                        html += '<div class="session-empty-hint">Dokter tidak praktik di sesi ini.</div>';
                    } else {
                        html += '<div class="session-slots">';
                        slots.forEach(function (slot) {
                            let cls = 'slot', statusText = 'Tersedia';
                            if (slot.is_booked) { cls += ' booked'; statusText = 'Terisi'; }
                            else if (slot.is_past) { cls += ' past'; statusText = 'Lewat'; }
                            html += '<div class="' + cls + '" data-time="' + slot.time + '" data-end="' + slot.end_time + '">';
                            html += '<div>' + slot.label + '</div><div class="slot-status">' + statusText + '</div></div>';
                        });
                        html += '</div>';
                    }

                    html += '</div>';
                });
                return html;
            }

            // Toggle expand/collapse sesi
            $(document).on('click', '.session-header', function (e) {
                const $sess = $(this).closest('.slot-session');
                if ($(this).is(':disabled')) return;
                const expanded = $sess.attr('aria-expanded') === 'true';
                $sess.attr('aria-expanded', expanded ? 'false' : 'true');
            });

            $(document).on('click', '.slot:not(.booked):not(.past)', function () {
                $('.slot').removeClass('selected');
                $(this).addClass('selected');
                selectedTime = $(this).data('time');
                $('#reservationTime').val(selectedTime);
                updateDurationSummary();
                setStep(5);
                $('#customerSection').addClass('visible');
                scrollToSection('customerSection');
                checkSubmit();
            });

            window.checkSubmit = function () {
                const promoCode = $('#promoInput').val().trim();
                const promoOk   = promoCode === '' || (promoFilled && promoValid);
                if (promoCode !== '' && !promoFilled) {
                    $('#promoStatus').html('<span style="color:#f59e0b"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><path d="M8 2.2 14.4 13.2H1.6L8 2.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6.4v3M8 11.2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Klik "Cek" untuk validasi kode promo</span>');
                }

                let serviceOk = false;
                if (activeCategory === 'lainnya') {
                    serviceOk = $('#lainnyaText').val().trim().length > 0;
                } else {
                    serviceOk = $('input[name="services[]"]:checked').length > 0;
                }

                // Minimal 1 pasien terpilih/ditambah (model 1-nomor-banyak-pasien).
                const patientOk = window.RES && RES.selectedCount() > 0;

                const ok = serviceOk
                    && $('#doctorValue').val()
                    && $('#reservationDate').val()
                    && $('#reservationTime').val()
                    && $('#branchSelect').val()
                    && patientOk
                    && promoOk;

                $('#previewBtn').prop('disabled', !ok);
            };

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
                    $('#branchGrid').hide();
                    $('#branchLocked').html('<i class="fa-solid fa-map-marker-alt" style="color:#461500;margin-right:6px"></i>' + matchedName).show();
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

        /* ───────── Modul Multi-Pasien (1 nomor → banyak pasien) ───────── */
        const RELASI_LABEL = { diri_sendiri:'Diri sendiri', pasangan:'Pasangan', anak:'Anak', orang_tua:'Orang tua', lainnya:'Lainnya' };
        function escapeHtml(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

        window.RES = {
            phone:'', existing:[], sel:{}, newp:[], seq:0, doctors:[], services:[], _t:null,

            defaults() {
                const sids = $('input[name="services[]"]:checked').map(function(){return $(this).val();}).get();
                return {
                    branchId:   $('#branchSelect').val(),
                    date:       $('#reservationDate').val(),
                    start:      $('#reservationTime').val(),
                    doctorId:   $('#doctorValue').val(),
                    doctorName: $('.doctor-card.selected').data('name') || '-',
                    serviceIds: sids,
                    lainnya:    (typeof activeCategory !== 'undefined' && activeCategory === 'lainnya'),
                    lainnyaText:$('#lainnyaText').val().trim()
                };
            },

            selectedCount() { return Object.keys(this.sel).length + this.newp.length; },

            resetPanel() {
                this.phone=''; this.existing=[]; this.sel={}; this.newp=[]; this.seq=0;
                $('#phoneCheckInput').val(''); $('#phoneCheckStatus').html('');
                $('#patientPanel').hide(); $('#patientList').html('');
                $('#patientsHiddenWrap').empty(); this.toggleAddForm(false);
                if (window.checkSubmit) checkSubmit();
            },

            check() {
                const phone = $('#phoneCheckInput').val().trim();
                if (!phone) { $('#phoneCheckStatus').html('<div class="not-found-alert"><i class="fa-solid fa-exclamation-circle"></i> Masukkan nomor HP terlebih dahulu</div>'); return; }
                const d = this.defaults();
                if (!d.branchId || !d.doctorId || !d.date || !d.start) {
                    $('#phoneCheckStatus').html('<div class="not-found-alert"><i class="fa-solid fa-exclamation-circle"></i> Lengkapi cabang, perawatan, dokter, tanggal & jam dulu.</div>'); return;
                }
                const self = this;
                $('#btnCekHP').text('Mengecek...').prop('disabled', true);
                $('#phoneCheckStatus').html('<div style="color:#8b7a6b;font-size:0.85rem;padding:8px 0"><i class="fa-solid fa-spinner fa-spin"></i> Mencari data...</div>');
                if (!self.doctors.length) $.getJSON('/api/reservation/doctors/' + d.branchId, function(x){ self.doctors = x || []; });
                if (!self.services.length) $.getJSON('/api/reservation/services', function(x){ self.services = x || []; });

                $.get('/api/reservation/check-customer', { phone: phone }, function (res) {
                    self.phone = phone; self.sel = {}; self.newp = []; self.seq = 0;
                    self.existing = (res.found && res.patients) ? res.patients : [];
                    $('#phoneCheckStatus').html(
                        '<div class="found-alert"><i class="fa-solid fa-check-circle"></i> ' +
                        (self.existing.length ? 'Nomor ditemukan. Centang pasien yang ikut, atau tambah pasien baru.' : 'Nomor baru. Tambahkan data pasien di bawah.') +
                        '</div>'
                    );
                    $('#patientPanel').show();
                    self.render();
                    if (!self.existing.length) self.toggleAddForm(true);
                }).fail(function () {
                    $('#phoneCheckStatus').html('<div class="not-found-alert"><i class="fa-solid fa-exclamation-circle"></i> Gagal menghubungi server, coba lagi.</div>');
                }).always(function () {
                    $('#btnCekHP').text('Cek').prop('disabled', false);
                });
            },

            toggleAddForm(show) {
                if (show) { $('#addPatientForm').slideDown(120); $('#addPatientBtn').hide(); $('#apName').focus(); }
                else { $('#addPatientForm').slideUp(120); $('#addPatientBtn').show(); $('#apStatus').html(''); $('#apName,#apEmail,#apTgl').val(''); $('#apJk').val(''); $('#apRelasi').val('anak'); }
            },

            commitAddPatient() {
                const name = $('#apName').val().trim(), jk = $('#apJk').val(), tgl = $('#apTgl').val();
                if (!name) { $('#apStatus').html('<span style="color:#dc2626">Nama wajib diisi.</span>'); return; }
                if (!jk)   { $('#apStatus').html('<span style="color:#dc2626">Jenis kelamin wajib dipilih.</span>'); return; }
                if (!tgl)  { $('#apStatus').html('<span style="color:#dc2626">Tanggal lahir wajib diisi.</span>'); return; }
                this.newp.push({ key:'n'+(++this.seq), name:name, relasi:$('#apRelasi').val(), jk:jk, tgl:tgl,
                    email:$('#apEmail').val().trim(), ov:false, doctorId:null, serviceIds:null, lainnya:null, slot:null });
                this.toggleAddForm(false); this.render(); this.recompute();
            },

            removeNew(key) { this.newp = this.newp.filter(function(p){return p.key!==key;}); this.render(); this.recompute(); },

            toggleExisting(cid, on) {
                if (on) this.sel[cid] = { ov:false, doctorId:null, serviceIds:null, lainnya:null, slot:null };
                else delete this.sel[cid];
                this.render(); this.recompute();
            },

            entry(kind, id) { return kind==='e' ? this.sel[id] : this.newp.find(function(p){return p.key===id;}); },

            toggleOverride(kind, id) {
                const e = this.entry(kind, id); if (!e) return;
                e.ov = !e.ov;
                if (e.ov && e.doctorId === null) { const d=this.defaults(); e.doctorId=d.doctorId; e.serviceIds=d.serviceIds.slice(); e.lainnya=d.lainnya; }
                this.render(); this.recompute();
            },

            setOverride(kind, id, field, val) {
                const e = this.entry(kind, id); if (!e) return;
                if (field==='doctor')   e.doctorId  = val;
                if (field==='services') { e.serviceIds = val; e.lainnya = false; }
                this.recompute();
            },

            payload() {
                const d = this.defaults(), list = [], self = this;
                this.existing.forEach(function(p){
                    const e = self.sel[p.customer_id]; if (!e) return;
                    list.push({ key:'e:'+p.customer_id, doctor_id:e.ov?e.doctorId:d.doctorId,
                        services:e.ov?(e.serviceIds||[]):d.serviceIds, lainnya:e.ov?!!e.lainnya:d.lainnya });
                });
                this.newp.forEach(function(p){
                    list.push({ key:'n:'+p.key, doctor_id:p.ov?p.doctorId:d.doctorId,
                        services:p.ov?(p.serviceIds||[]):d.serviceIds, lainnya:p.ov?!!p.lainnya:d.lainnya });
                });
                return { branch_id:d.branchId, date:d.date, default_start:d.start, patients:list, _token:$('input[name=_token]').val() };
            },

            recompute() {
                if (window.checkSubmit) checkSubmit();
                if (this.selectedCount()===0) return;
                const self = this;
                clearTimeout(this._t);
                this._t = setTimeout(function(){
                    $.ajax({ url:'/api/reservation/plan-schedule', method:'POST', data:self.payload() })
                    .done(function(res){
                        const map = {};
                        (res.patients||[]).forEach(function(a){ map[a.key]=a; });
                        self.renderTimes(map);
                    });
                }, 250);
            },

            renderTimes(map) {
                const self = this;
                this.existing.forEach(function(p){ const e=self.sel[p.customer_id]; if(e) e.slot = map['e:'+p.customer_id]||null; });
                this.newp.forEach(function(p){ p.slot = map['n:'+p.key]||null; });
                $('#patientList .pc-time').each(function(){ $(this).html(self.timeHtml(map[$(this).data('k')])); });
            },

            timeHtml(a) {
                if (!a) return '<span class="pc-pending">menghitung…</span>';
                if (a.waiting) return '<span class="pc-wait">⏳ ' + escapeHtml(a.reason||'Tim akan konfirmasi jam') + '</span>';
                return '<span class="pc-ok"><i class="fa-solid fa-clock"></i> ' + a.start + '–' + a.end + '</span>';
            },

            overrideHtml(kind, id, e) {
                const d = this.defaults();
                const docId = e.ov ? (e.doctorId||d.doctorId) : d.doctorId;
                let docs = '<option value="">- pilih dokter -</option>';
                this.doctors.forEach(function(x){ docs += '<option value="'+x.id+'"'+(x.id===docId?' selected':'')+'>'+escapeHtml(x.name)+'</option>'; });
                const selSvc = (e.ov ? (e.serviceIds||[]) : d.serviceIds).map(String);
                const gridId = 'ovsvc_' + kind + '_' + id;
                let svc = '';
                this.services.forEach(function(x){
                    const sid = String(x.id);
                    const chkId = gridId + '_' + sid;
                    const checked = selSvc.indexOf(sid) > -1 ? ' checked' : '';
                    svc += '<div class="pc-svc-chk">'
                        + '<label for="'+chkId+'">'
                        + '<input type="checkbox" id="'+chkId+'" value="'+sid+'"'+checked
                        + ' onchange="RES.collectOvSvc(\''+kind+'\',\''+id+'\')">'
                        + '<span class="pc-svc-name">'+escapeHtml(x.name)+'</span></label>'
                        + '</div>';
                });
                return '<div class="pc-override">'
                    + '<label>Dokter<select class="form-control" onchange="RES.setOverride(\''+kind+'\',\''+id+'\',\'doctor\',this.value)">'+docs+'</select></label>'
                    + '<div class="pc-override-svc">'
                    +   '<span class="pc-svc-title">Perawatan <small>(bisa pilih &gt;1)</small></span>'
                    +   '<div class="pc-override-services" id="'+gridId+'">'+svc+'</div>'
                    + '</div>'
                    + '</div>';
            },

            collectOvSvc(kind, id) {
                const gridId = 'ovsvc_' + kind + '_' + id;
                const arr = $('#' + gridId + ' input[type=checkbox]:checked').map(function(){ return $(this).val(); }).get();
                this.setOverride(kind, id, 'services', arr);
            },

            render() {
                const self = this; let h = '';
                this.existing.forEach(function(p){
                    const on = !!self.sel[p.customer_id];
                    const rel = RELASI_LABEL[p.relasi] || 'Pasien';
                    const dob = p.tanggal_lahir ? ' · ' + p.tanggal_lahir : '';
                    h += '<div class="pc-row">'
                      + '<label class="pc-head"><input type="checkbox" '+(on?'checked':'')+' onclick="RES.toggleExisting(\''+p.customer_id+'\',this.checked)">'
                      + '<span><strong>'+escapeHtml(p.name)+'</strong><small> ('+rel+dob+')</small></span></label>';
                    if (on) {
                        const e = self.sel[p.customer_id];
                        h += '<div class="pc-meta"><span class="pc-time" data-k="e:'+p.customer_id+'">'+self.timeHtml(e.slot)+'</span>'
                          + '<a href="#" onclick="event.preventDefault();RES.toggleOverride(\'e\',\''+p.customer_id+'\')">'+(e.ov?'Pakai default':'Ubah perawatan/dokter')+'</a></div>';
                        if (e.ov) h += self.overrideHtml('e', p.customer_id, e);
                    }
                    h += '</div>';
                });
                this.newp.forEach(function(p){
                    const rel = RELASI_LABEL[p.relasi] || 'Pasien';
                    h += '<div class="pc-row pc-new">'
                      + '<div class="pc-head"><span><strong>'+escapeHtml(p.name)+'</strong> <small class="pc-badge">BARU</small> <small>('+rel+(p.tgl?' · '+p.tgl:'')+')</small></span>'
                      + '<a href="#" class="pc-remove" onclick="event.preventDefault();RES.removeNew(\''+p.key+'\')">&times;</a></div>'
                      + '<div class="pc-meta"><span class="pc-time" data-k="n:'+p.key+'">'+self.timeHtml(p.slot)+'</span>'
                      + '<a href="#" onclick="event.preventDefault();RES.toggleOverride(\'n\',\''+p.key+'\')">'+(p.ov?'Pakai default':'Ubah perawatan/dokter')+'</a></div>';
                    if (p.ov) h += self.overrideHtml('n', p.key, p);
                    h += '</div>';
                });
                if (!h) h = '<div class="pc-empty">Belum ada pasien dipilih. Centang pasien atau “Tambah pasien baru di nomor ini”.</div>';
                $('#patientList').html(h);
                if (window.checkSubmit) checkSubmit();
            },

            injectAndSubmit() {
                if (this._submitting) return;
                this._submitting = true;
                const d = this.defaults(), self = this;
                const wrap = $('#patientsHiddenWrap').empty();
                let i = 0;
                const add = function(n,v){ wrap.append($('<input type="hidden">').attr('name','patients['+i+']['+n+']').val(v)); };
                const addSvc = function(arr){ (arr||[]).forEach(function(s){ wrap.append($('<input type="hidden">').attr('name','patients['+i+'][services][]').val(s)); }); };

                this.existing.forEach(function(p){
                    const e = self.sel[p.customer_id]; if (!e) return;
                    add('type','existing'); add('existing_customer_id', p.customer_id);
                    add('raw_doctor_id', e.ov?e.doctorId:d.doctorId);
                    if (e.ov ? e.lainnya : d.lainnya) add('lainnya_text', d.lainnyaText || 'Konsultasi');
                    else addSvc(e.ov?(e.serviceIds||[]):d.serviceIds);
                    i++;
                });
                this.newp.forEach(function(p){
                    add('type','new'); add('name',p.name); add('relasi',p.relasi);
                    add('jenis_kelamin',p.jk); add('tanggal_lahir',p.tgl); add('email',p.email||'');
                    add('raw_doctor_id', p.ov?p.doctorId:d.doctorId);
                    if (p.ov ? p.lainnya : d.lainnya) add('lainnya_text', d.lainnyaText || 'Konsultasi');
                    else addSvc(p.ov?(p.serviceIds||[]):d.serviceIds);
                    i++;
                });

                $('#phoneInput').val(self.phone);
                $('#defaultStartInput').val(d.start);
                $('#reservationForm').submit();
            }
        };

        function cekNomorHP() { RES.check(); }
        function resetPhoneCheck() {
            RES.phone=''; RES.existing=[]; RES.sel={}; RES.newp=[];
            $('#phoneCheckStatus').html(''); $('#patientPanel').hide(); $('#patientList').html('');
            RES.toggleAddForm(false);
            if (window.checkSubmit) checkSubmit();
        }

        /* Promo: 1 kode, berlaku semua pasien */
        let promoValid = false, promoFilled = false;
        window.checkPromo = function () {
            const code = $('#promoInput').val().trim().toUpperCase();
            if (!code) { $('#promoStatus').html('<span style="color:#dc2626"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><path d="M8 2.2 14.4 13.2H1.6L8 2.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6.4v3M8 11.2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Masukkan kode promo terlebih dahulu</span>'); return; }
            $('#checkPromoBtn').text('Mengecek...').prop('disabled', true);
            $.get('/api/promo/validate', { code: code }, function (res) {
                if (res.valid) {
                    promoValid = true; promoFilled = true;
                    $('#promoIdInput').val(res.promo_id ?? '');
                    let msg = '<svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.5"/><path d="M5.2 8.3 7.2 10.2 10.9 6.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg> ' + res.message;
                    msg += res.valid_until ? ' <span style="color:#6b7280;font-weight:500">· Berlaku s/d ' + res.valid_until + '</span>' : ' <span style="color:#6b7280;font-weight:500">· Berlaku selamanya</span>';
                    $('#promoStatus').html('<span style="color:#16a34a;font-weight:600">' + msg + '</span>');
                } else {
                    promoValid = false; promoFilled = true;
                    $('#promoIdInput').val('');
                    $('#promoStatus').html('<span style="color:#dc2626"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.5"/><path d="M5.8 5.8l4.4 4.4M10.2 5.8l-4.4 4.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> ' + res.message + '</span>');
                }
                checkSubmit();
            }).fail(function () {
                promoValid = false;
                $('#promoStatus').html('<span style="color:#dc2626"><svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="vertical-align:-.1em"><circle cx="8" cy="8" r="6.2" stroke="currentColor" stroke-width="1.5"/><path d="M5.8 5.8l4.4 4.4M10.2 5.8l-4.4 4.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Gagal mengecek promo</span>');
                checkSubmit();
            }).always(function () { $('#checkPromoBtn').text('Cek').prop('disabled', false); });
        };
        $('#promoInput').on('input', function () {
            promoValid = false; promoFilled = false;
            $('#promoIdInput').val(''); $('#promoStatus').html('');
            checkSubmit();
        });

        function showPreview() {
            const d = RES.defaults();
            const branchText = $('#branchSelect option:selected').text();
            const dateText   = $('.date-pill.selected').data('label') || d.date;
            const promoCode  = $('#promoInput').val().trim().toUpperCase();
            const promoMsg   = promoValid ? $('#promoStatus').text().trim() : null;

            function svcName(id){ const s=RES.services.find(function(x){return String(x.id)===String(id);}); return s?s.name:id; }
            function row(label, rel, slot, ov, doctorId, serviceIds, lainnya){
                const docName = ov ? ((RES.doctors.find(function(x){return x.id===doctorId;})||{}).name || '-') : d.doctorName;
                const svc = lainnya ? 'Lainnya' : (ov ? (serviceIds||[]).map(svcName).join(', ') : d.serviceIds.map(svcName).join(', '));
                const t = slot ? (slot.waiting ? '⏳ '+escapeHtml(slot.reason||'tim konfirmasi') : slot.start+'–'+slot.end) : '-';
                return '<div class="preview-row"><span class="preview-label">'+escapeHtml(label)+'<br><small style="color:#8b7a6b">'+escapeHtml(rel)+'</small></span>'
                     + '<span class="preview-value">'+escapeHtml(svc)+'<br><small>'+escapeHtml(docName)+' · '+t+'</small></span></div>';
            }

            let html = '';
            html += '<div class="preview-row"><span class="preview-label">Cabang</span><span class="preview-value">' + branchText + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label">Tanggal</span><span class="preview-value">' + dateText + '</span></div>';
            if (promoCode && promoValid) html += '<div class="preview-row"><span class="preview-label">Promo</span><span class="preview-value" style="color:#16a34a;font-weight:600">' + promoCode + ' — ' + promoMsg + '</span></div>';
            html += '<div class="preview-row"><span class="preview-label" style="font-weight:700">Pasien (' + RES.selectedCount() + ')</span><span class="preview-value"></span></div>';
            RES.existing.forEach(function(p){ const e=RES.sel[p.customer_id]; if(!e) return;
                html += row(p.name, RELASI_LABEL[p.relasi]||'Pasien', e.slot, e.ov, e.doctorId, e.serviceIds, e.ov?e.lainnya:d.lainnya); });
            RES.newp.forEach(function(p){
                html += row(p.name+' (baru)', RELASI_LABEL[p.relasi]||'Pasien', p.slot, p.ov, p.doctorId, p.serviceIds, p.ov?p.lainnya:d.lainnya); });

            $('#previewBody').html(html);
            $('#previewModal').addClass('show');
        }

        function closePreview() { $('#previewModal').removeClass('show'); }

        function confirmSubmit() {
            const btn = document.querySelector('.btn-modal.confirm');
            if (btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
            const x = new Date(); x.setTime(x.getTime() + (30*864e5));
            document.cookie = 'st_phone=' + encodeURIComponent(RES.phone) + ';expires=' + x.toUTCString() + ';path=/';
            RES.injectAndSubmit();
        }

        $(document).on('click', '.modal-overlay', function (e) { if (e.target === this) closePreview(); });

        flatpickr("#apTgl", {
            locale: "id",
            dateFormat: "Y-m-d",
            maxDate: "today",
            yearRange: [1940, new Date().getFullYear() - 1],
            disableMobile: true
        });
    </script>
</body>
</html>