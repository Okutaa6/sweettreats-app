<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Program Referral - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Daftar Program Referral Sweet Treats — pasien behel maupun perawatan standar bisa ikut.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', sans-serif; color: #3a2a1a; background: linear-gradient(165deg, #fffaf5 0%, #fef0e2 50%, #fce8d4 100%); min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .container { max-width: 780px; margin: 0 auto; padding: 0 24px; }

        .navbar { background: rgba(255,250,245,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(70,21,0,0.08); padding: 0 24px; }
        .navbar-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 72px; }
        .navbar-brand { font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .navbar-brand span { background: linear-gradient(135deg, #461500, #8b3a00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: transparent; border: 2px solid #461500; color: #461500; border-radius: 50px; font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 700; transition: all 0.3s; }
        .btn-back:hover { background: #461500; color: #fff; }

        .form-section { padding: 48px 0 80px; }
        .form-header { text-align: center; margin-bottom: 32px; }
        .form-header .tag { display: inline-block; padding: 6px 16px; background: rgba(70,21,0,0.06); border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: #461500; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .form-header h1 { font-size: 2.2rem; font-weight: 900; color: #461500; margin-bottom: 12px; line-height: 1.2; }
        .form-header p { font-size: 1rem; color: #6b4a30; line-height: 1.6; }

        /* Stepper */
        .stepper { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 32px; flex-wrap: wrap; }
        .step-dot { display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #fff; border: 2px solid rgba(70,21,0,0.1); border-radius: 50px; font-size: 0.78rem; font-weight: 700; color: #8b7a6b; transition: all 0.3s; }
        .step-dot.is-active { background: #461500; border-color: #461500; color: #fff; box-shadow: 0 6px 18px rgba(70,21,0,0.25); }
        .step-dot.is-done   { background: rgba(46,125,50,0.1); border-color: #2e7d32; color: #2e7d32; }
        .step-dot .num { width: 22px; height: 22px; border-radius: 50%; background: rgba(70,21,0,0.08); display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; }
        .step-dot.is-active .num, .step-dot.is-done .num { background: rgba(255,255,255,0.25); color: inherit; }
        .step-arrow { color: rgba(70,21,0,0.25); }

        .form-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(70,21,0,0.08); border: 1px solid rgba(70,21,0,0.04); }

        .step-pane { display: none; }
        .step-pane.is-active { display: block; animation: stepFadeIn .25s ease; }
        @keyframes stepFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .section-block { margin-bottom: 28px; }
        .section-title { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 800; color: #461500; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid rgba(70,21,0,0.08); }
        .section-title .section-icon { width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#fef5ed,#fef0e2); display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #461500; }
        .section-desc { font-size: 0.85rem; color: #8b7a6b; margin-top: -8px; margin-bottom: 16px; line-height: 1.5; }

        .alert-error { background: linear-gradient(135deg,#ffebee,#ffcdd2); border: 1px solid #ef9a9a; border-radius: 16px; padding: 16px 22px; margin-bottom: 28px; color: #c62828; font-size: 0.88rem; }
        .alert-error ul { margin: 6px 0 0 18px; }
        .alert-error .alert-title { font-weight: 800; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #461500; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group label .required { color: #c45200; margin-left: 2px; }
        .form-control { width: 100%; padding: 14px 18px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.1); border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.95rem; color: #3a2a1a; transition: all 0.3s; outline: none; }
        .form-control:focus { border-color: #461500; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); background: #fff; }
        .form-control::placeholder { color: #bfa890; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23461500' viewBox='0 0 16 16'%3E%3Cpath d='M1.646 4.646a.5.5 0 01.708 0L8 10.293l5.646-5.647a.5.5 0 01.708.708l-6 6a.5.5 0 01-.708 0l-6-6a.5.5 0 010-.708z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 18px center; padding-right: 44px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

        /* Scope picker (Step 1) */
        .scope-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .scope-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .scope-option label { display: flex; flex-direction: column; gap: 14px; padding: 28px 22px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.08); border-radius: 18px; cursor: pointer; transition: all 0.25s; height: 100%; }
        .scope-option label:hover { border-color: rgba(70,21,0,0.25); transform: translateY(-2px); box-shadow: 0 14px 28px rgba(70,21,0,0.08); }
        .scope-option input:checked + label { border-color: #461500; background: linear-gradient(135deg, #fff, #fef5ed); box-shadow: 0 14px 32px rgba(70,21,0,0.16); }
        .scope-icon { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg,#fef5ed,#fef0e2); color: #461500; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .scope-option input:checked + label .scope-icon { background: linear-gradient(135deg, #461500, #6b2400); color: #fff; }
        .scope-name { font-size: 1.05rem; font-weight: 900; color: #461500; }
        .scope-desc { font-size: 0.82rem; color: #8b7a6b; line-height: 1.5; }
        .scope-perks { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
        .scope-perk { font-size: 0.7rem; font-weight: 700; padding: 4px 10px; border-radius: 50px; background: rgba(70,21,0,0.06); color: #461500; }

        /* Reward tier cards (Step 3 — only for behel) */
        .reward-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        .reward-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .reward-option label { display: grid; grid-template-columns: 56px 1fr 26px; gap: 16px; align-items: center; padding: 18px 20px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.08); border-radius: 18px; cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden; }
        .reward-option label::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--tier-color, #c68a56); opacity: 0; transition: opacity 0.3s; }
        .reward-option label:hover { border-color: rgba(70,21,0,0.2); background: #fef5ed; transform: translateY(-2px); }
        .reward-option label:hover::before { opacity: 0.5; }
        .reward-option input:checked + label { border-color: var(--tier-color, #461500); background: #fff; transform: translateY(-2px); box-shadow: 0 14px 32px rgba(70,21,0,0.12); }
        .reward-option input:checked + label::before { opacity: 1; }
        .tier-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--tier-bg, linear-gradient(135deg,#fef5ed,#fef0e2)); color: var(--tier-color, #461500); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .reward-option input:checked + label .tier-icon { background: var(--tier-gradient, linear-gradient(135deg,#461500,#6b2400)); color: #fff; }
        .tier-info { min-width: 0; }
        .tier-name { font-size: 1rem; font-weight: 900; color: #461500; margin-bottom: 3px; }
        .tier-task { font-size: 0.78rem; color: #8b7a6b; font-weight: 600; margin-bottom: 8px; }
        .tier-reward { display: inline-flex; align-items: center; gap: 6px; font-size: 0.76rem; font-weight: 700; color: var(--tier-color, #461500); background: var(--tier-bg, rgba(70,21,0,0.06)); padding: 5px 12px; border-radius: 50px; }
        .reward-check { width: 26px; height: 26px; border: 2px solid rgba(70,21,0,0.18); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: transparent; font-size: 0.78rem; transition: all 0.3s; }
        .reward-option input:checked + label .reward-check { background: var(--tier-color, #461500); border-color: var(--tier-color, #461500); color: #fff; }

        .reward-lock-note { margin-top: 16px; padding: 14px 16px; background: linear-gradient(135deg, #fef5ed, #fef0e2); border: 1px dashed rgba(70,21,0,0.25); border-radius: 14px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.8rem; color: #6b4a30; line-height: 1.55; }
        .reward-lock-note i { color: #c45200; font-size: 1rem; margin-top: 2px; flex-shrink: 0; }
        .reward-lock-note strong { color: #461500; }

        .social-combo { display: grid; grid-template-columns: 180px 1fr; gap: 12px; }
        .social-combo select { padding-right: 38px; }

        /* Step nav buttons */
        .step-nav { display: flex; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid rgba(70,21,0,0.08); }
        .step-nav .spacer { flex: 1; }
        .btn-step { display: inline-flex; align-items: center; gap: 8px; padding: 14px 24px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 0.92rem; font-weight: 800; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-step:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(70,21,0,0.3); }
        .btn-step:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }
        .btn-step--outline { background: transparent; color: #461500; border: 2px solid #461500; }
        .btn-step--outline:hover { background: #461500; color: #fff; }
        .btn-submit-final { background: linear-gradient(135deg, #2e7d32, #1b5e20); }

        .form-note { text-align: center; margin-top: 20px; font-size: 0.82rem; color: #8b7a6b; line-height: 1.6; }
        .form-note a { color: #461500; font-weight: 700; border-bottom: 1px dashed rgba(70,21,0,0.4); }

        /* Step Summary box (sebelum submit) */
        .summary-box { background: linear-gradient(135deg, #fef5ed, #fef0e2); border: 1px solid rgba(70,21,0,0.1); border-radius: 16px; padding: 20px; margin-bottom: 12px; }
        .summary-box h6 { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #8b7a6b; font-weight: 800; margin-bottom: 10px; }
        .summary-box .row-kv { display: flex; justify-content: space-between; padding: 6px 0; font-size: 0.88rem; border-bottom: 1px dashed rgba(70,21,0,0.08); }
        .summary-box .row-kv:last-child { border-bottom: none; }
        .summary-box .row-kv .k { color: #8b7a6b; font-weight: 600; }
        .summary-box .row-kv .v { color: #461500; font-weight: 700; text-align: right; }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .navbar { padding: 0 16px; }
            .navbar-inner { height: 60px; }
            .navbar-brand { font-size: 1.2rem; }
            .btn-back { padding: 8px 14px; font-size: 0.78rem; }
            .form-section { padding: 28px 0 60px; }
            .form-header { margin-bottom: 24px; }
            .form-header h1 { font-size: 1.55rem; }
            .form-header p { font-size: 0.88rem; }
            .form-card { padding: 24px 18px; border-radius: 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .scope-grid { grid-template-columns: 1fr; }
            .social-combo { grid-template-columns: 1fr; }
            .step-dot { font-size: 0.72rem; padding: 6px 12px; }
            .step-arrow { display: none; }
            .form-group label { font-size: 0.75rem; }
            .form-control { padding: 12px 14px; font-size: 0.88rem; border-radius: 12px; }
            .btn-step { padding: 12px 18px; font-size: 0.82rem; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="https://sweettreats.id/" class="navbar-brand">
                <span>Sweet Treats</span>
            </a>
            <a href="{{ route('affiliate.login') }}" class="btn-back">
                <i class="fa-solid fa-sign-in-alt"></i> Login
            </a>
        </div>
    </nav>

    <section class="form-section">
        <div class="container">
            <div class="form-header">
                <div class="tag"><i class="fa-solid fa-handshake"></i> Program Referral Sweet Treats</div>
                <h1>Senyummu Punya Cerita</h1>
                <p>
                    Yuk ikutan! Pasien behel maupun perawatan standar bisa daftar.<br>
                    Cuma butuh 4 langkah singkat.
                </p>
            </div>

            {{-- Stepper indicator --}}
            <div class="stepper" id="stepper">
                <div class="step-dot" data-step="1"><span class="num">1</span> Jenis</div>
                <span class="step-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                <div class="step-dot" data-step="2"><span class="num">2</span> Data Diri</div>
                <span class="step-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                <div class="step-dot" data-step="3" data-behel-only><span class="num">3</span> Tier</div>
                <span class="step-arrow" data-behel-only><i class="fa-solid fa-chevron-right"></i></span>
                <div class="step-dot" data-step="4"><span class="num">4</span> Sosmed</div>
            </div>

            <div class="form-card">

                @if(session('error'))
                    <div class="alert-error">
                        <div class="alert-title"><i class="fa-solid fa-exclamation-circle"></i> Terjadi kesalahan</div>
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert-error">
                        <div class="alert-title"><i class="fa-solid fa-exclamation-circle"></i> Periksa kembali data kamu</div>
                        <ul>
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('affiliate.register.process') }}" id="registerForm" novalidate>
                    @csrf

                    {{-- ─── STEP 1: PILIH SCOPE ─── --}}
                    <div class="step-pane is-active" data-step-pane="1">
                        <div class="section-title">
                            <span class="section-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            Pilih Jenis Perawatan
                        </div>
                        <p class="section-desc">
                            Kamu pasien behel atau pasien perawatan standar (scaling/whitening/PSA/veneer)?
                            Pilih sesuai perawatan yang sudah/sedang kamu lakukan di Sweet Treats.
                        </p>

                        @php
                            $oldScope = old('referral_scope');
                            $scopes   = \App\Models\RawAffiliate::referralScopes();
                        @endphp

                        <div class="scope-grid">
                            @foreach($scopes as $key => $scope)
                                <div class="scope-option">
                                    <input type="radio" name="referral_scope" id="scope_{{ $key }}"
                                           value="{{ $key }}" {{ $oldScope === $key ? 'checked' : '' }} required>
                                    <label for="scope_{{ $key }}">
                                        <span class="scope-icon"><i class="fa-solid {{ $scope['icon'] }}"></i></span>
                                        <span class="scope-name">{{ $scope['label'] }}</span>
                                        <span class="scope-desc">{{ $scope['desc'] }}</span>
                                        <div class="scope-perks">
                                            <span class="scope-perk">Voucher pasien Rp {{ number_format($scope['voucher_amount'], 0, ',', '.') }}</span>
                                            <span class="scope-perk">Komisi Rp {{ number_format($scope['commission_amount'], 0, ',', '.') }}/pasien</span>
                                            @if($scope['has_reward_program'])
                                                <span class="scope-perk">+ Program Rewards</span>
                                            @endif
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="step-nav">
                            <div class="spacer"></div>
                            <button type="button" class="btn-step" data-next="2">
                                Lanjut <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ─── STEP 2: DATA DIRI + PERAWATAN ─── --}}
                    <div class="step-pane" data-step-pane="2">
                        <div class="section-block">
                            <div class="section-title">
                                <span class="section-icon"><i class="fa-solid fa-user"></i></span>
                                Data Diri
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" maxlength="255"
                                       value="{{ old('name') }}" placeholder="Nama sesuai identitas" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email <span class="required">*</span></label>
                                    <input type="email" name="email" class="form-control" maxlength="255"
                                           value="{{ old('email') }}" placeholder="kamu@email.com" required>
                                </div>
                                <div class="form-group">
                                    <label>No WhatsApp <span class="required">*</span></label>
                                    <input type="text" name="phone" class="form-control" maxlength="20"
                                           value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required inputmode="tel">
                                </div>
                            </div>
                        </div>

                        <div class="section-block">
                            <div class="section-title">
                                <span class="section-icon"><i class="fa-solid fa-tooth"></i></span>
                                <span data-scope-label-behel>Perawatan Behel</span>
                                <span data-scope-label-non_behel style="display:none">Jenis Perawatan</span>
                            </div>
                            <p class="section-desc" data-scope-desc-behel>
                                Pilih jenis behel yang kamu pasang di Sweet Treats.
                            </p>
                            <p class="section-desc" data-scope-desc-non_behel style="display:none">
                                Pilih perawatan yang sudah kamu lakukan (Scaling, Whitening, PSA, atau Veneer).
                            </p>

                            {{-- Behel scope: pilih dari behelTypes (custom list) --}}
                            <div class="form-group" data-scope-field-behel>
                                <label>Jenis Behel <span class="required">*</span></label>
                                <select name="behel_type" id="behelTypeSelect" class="form-control">
                                    <option value="">-- Pilih Jenis Behel --</option>
                                    @foreach($behelTypes as $key => $label)
                                        <option value="{{ $key }}" {{ old('behel_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Non-behel scope: pilih dari raw_doc_service (Scaling/Whitening/PSA/Veneer) --}}
                            <div class="form-group" data-scope-field-non_behel style="display:none">
                                <label>Jenis Perawatan <span class="required">*</span></label>
                                <select name="raw_doc_service_id" id="serviceSelect" class="form-control">
                                    <option value="">-- Pilih Perawatan --</option>
                                    @foreach($servicesNonBehel as $svc)
                                        <option value="{{ $svc->id }}" {{ old('raw_doc_service_id') == $svc->id ? 'selected' : '' }}>{{ $svc->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Cabang Sweet Treats <span class="required">*</span></label>
                                    <select name="raw_branch_id" class="form-control" required>
                                        <option value="">-- Pilih Cabang --</option>
                                        @foreach($branches as $br)
                                            <option value="{{ $br->id }}" {{ old('raw_branch_id') == $br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Dokter <span class="required">*</span></label>
                                    <select name="raw_doctor_id" class="form-control" required>
                                        <option value="">-- Pilih Dokter --</option>
                                        @foreach($doctors as $dr)
                                            <option value="{{ $dr->id }}" {{ old('raw_doctor_id') == $dr->id ? 'selected' : '' }}>{{ $dr->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-step btn-step--outline" data-prev="1">
                                <i class="fa-solid fa-arrow-left"></i> Kembali
                            </button>
                            <div class="spacer"></div>
                            <button type="button" class="btn-step" data-next-smart>
                                Lanjut <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ─── STEP 3 (BEHEL ONLY): PILIH TIER REWARDS ─── --}}
                    <div class="step-pane" data-step-pane="3" data-behel-only>
                        <div class="section-title">
                            <span class="section-icon"><i class="fa-solid fa-trophy"></i></span>
                            Pilih Tier Sweet Mates
                        </div>
                        <p class="section-desc">
                            Semakin aktif kamu bercerita, semakin spesial reward kesehatan gigi yang bisa kamu dapatkan.
                            Pilih satu tier sesuai komitmenmu — <strong>pilihan ini bersifat final</strong>.
                        </p>

                        @php
                            $selectedReward = old('reward_program');
                            $tiers = \App\Models\RawAffiliate::rewardTiers();
                        @endphp

                        <div class="reward-grid">
                            @foreach($tiers as $key => $tier)
                                <div class="reward-option"
                                     style="--tier-color: {{ $tier['color'] }}; --tier-bg: {{ $tier['bg_soft'] }}; --tier-gradient: {{ $tier['gradient'] }};">
                                    <input type="radio" name="reward_program" id="reward_{{ $key }}"
                                           value="{{ $key }}" {{ $selectedReward === $key ? 'checked' : '' }}>
                                    <label for="reward_{{ $key }}">
                                        <span class="tier-icon"><i class="fa-solid {{ $tier['icon'] }}"></i></span>
                                        <span class="tier-info">
                                            <span class="tier-name">{{ $tier['name'] }}</span>
                                            <span class="tier-task">{{ $tier['content_desc'] }}</span>
                                            <span class="tier-reward">
                                                <i class="fa-solid fa-gift"></i> {{ $tier['reward'] }}
                                            </span>
                                        </span>
                                        <span class="reward-check"><i class="fa-solid fa-check"></i></span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="reward-lock-note">
                            <i class="fa-solid fa-info-circle"></i>
                            <div>
                                Reward hanya bisa <strong>diklaim di akhir perawatan saat lepas behel</strong>.
                                Sekali pilih tier, tidak bisa diubah — pastikan komitmenmu ya!
                            </div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-step btn-step--outline" data-prev="2">
                                <i class="fa-solid fa-arrow-left"></i> Kembali
                            </button>
                            <div class="spacer"></div>
                            <button type="button" class="btn-step" data-next="4">
                                Lanjut <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    {{-- ─── STEP 4: SOSMED + SUBMIT ─── --}}
                    <div class="step-pane" data-step-pane="4">
                        <div class="section-block">
                            <div class="section-title">
                                <span class="section-icon"><i class="fa-solid fa-share-alt"></i></span>
                                Sosial Media
                            </div>
                            <p class="section-desc">
                                Platform utama tempat kamu akan posting konten / share kode referral.
                            </p>
                            <div class="form-group">
                                <label>Platform &amp; Username <span class="required">*</span></label>
                                <div class="social-combo">
                                    <select name="social_platform" class="form-control" required>
                                        <option value="">-- Platform --</option>
                                        @foreach(\App\Models\RawAffiliate::socialPlatforms() as $key => $label)
                                            <option value="{{ $key }}" {{ old('social_platform') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="social_username" class="form-control"
                                           maxlength="100" placeholder="@username"
                                           value="{{ old('social_username') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="summary-box" id="summaryBox">
                            <h6><i class="fa-solid fa-clipboard-check"></i> Ringkasan Pendaftaran</h6>
                            <div class="row-kv"><span class="k">Jenis</span> <span class="v" data-summary="scope">-</span></div>
                            <div class="row-kv"><span class="k">Nama</span> <span class="v" data-summary="name">-</span></div>
                            <div class="row-kv"><span class="k">Email</span> <span class="v" data-summary="email">-</span></div>
                            <div class="row-kv"><span class="k">WhatsApp</span> <span class="v" data-summary="phone">-</span></div>
                            <div class="row-kv" data-behel-only><span class="k">Tier</span> <span class="v" data-summary="tier">-</span></div>
                        </div>

                        <div class="step-nav">
                            <button type="button" class="btn-step btn-step--outline" data-prev-smart>
                                <i class="fa-solid fa-arrow-left"></i> Kembali
                            </button>
                            <div class="spacer"></div>
                            <button type="submit" class="btn-step btn-submit-final">
                                <i class="fa-solid fa-paper-plane"></i> Daftar Sekarang
                            </button>
                        </div>
                    </div>
                </form>

                <div class="form-note">
                    Sudah terdaftar? <a href="{{ route('affiliate.login') }}">Login di sini</a>
                </div>

            </div>
        </div>
    </section>

    <script>
        (function () {
            const oldScope = @json(old('referral_scope'));

            const form          = document.getElementById('registerForm');
            const stepper       = document.getElementById('stepper');
            const stepDots      = stepper.querySelectorAll('.step-dot');
            const panes         = form.querySelectorAll('.step-pane');
            const summaryBox    = document.getElementById('summaryBox');

            let currentStep = 1;
            const TOTAL_STEPS = 4;

            // ── Helpers ───────────────────────────────────────────────
            function getScope() {
                const r = form.querySelector('input[name="referral_scope"]:checked');
                return r ? r.value : null;
            }

            function isBehel() {
                return getScope() === 'behel';
            }

            // Toggle visibility of scope-specific elements based on selected scope
            function applyScopeVisibility() {
                const scope = getScope();

                // Hide all behel-only elements when non-behel
                document.querySelectorAll('[data-behel-only]').forEach(el =>
                    el.style.display = (scope === 'behel') ? '' : 'none'
                );

                // Section labels & descs
                document.querySelectorAll('[data-scope-label-behel]').forEach(el => el.style.display = scope === 'behel' ? '' : 'none');
                document.querySelectorAll('[data-scope-label-non_behel]').forEach(el => el.style.display = scope === 'non_behel' ? '' : 'none');
                document.querySelectorAll('[data-scope-desc-behel]').forEach(el => el.style.display = scope === 'behel' ? '' : 'none');
                document.querySelectorAll('[data-scope-desc-non_behel]').forEach(el => el.style.display = scope === 'non_behel' ? '' : 'none');

                // Scope-specific field containers (one OR the other visible)
                const behelField    = document.querySelector('[data-scope-field-behel]');
                const nonBehelField = document.querySelector('[data-scope-field-non_behel]');
                const behelSelect   = document.getElementById('behelTypeSelect');
                const svcSelect     = document.getElementById('serviceSelect');

                if (scope === 'behel') {
                    if (behelField)    behelField.style.display = '';
                    if (nonBehelField) nonBehelField.style.display = 'none';
                    if (behelSelect) behelSelect.required = true;
                    if (svcSelect)   { svcSelect.required = false; svcSelect.value = ''; }
                } else if (scope === 'non_behel') {
                    if (behelField)    behelField.style.display = 'none';
                    if (nonBehelField) nonBehelField.style.display = '';
                    if (behelSelect) { behelSelect.required = false; behelSelect.value = ''; }
                    if (svcSelect)   svcSelect.required = true;
                } else {
                    if (behelSelect) behelSelect.required = false;
                    if (svcSelect)   svcSelect.required = false;
                }

                // Disable reward_program inputs when non_behel (so HTML5 required doesn't fire)
                form.querySelectorAll('input[name="reward_program"]').forEach(inp => {
                    inp.disabled = (scope !== 'behel');
                    if (scope !== 'behel') inp.checked = false;
                });
            }

            function showStep(n) {
                if (n < 1 || n > TOTAL_STEPS) return;

                // If non-behel, skip step 3
                if (n === 3 && !isBehel()) {
                    n = (n > currentStep) ? 4 : 2;
                }
                currentStep = n;

                panes.forEach(p => p.classList.toggle('is-active', parseInt(p.dataset.stepPane) === n));

                stepDots.forEach(dot => {
                    const step = parseInt(dot.dataset.step);
                    dot.classList.remove('is-active', 'is-done');
                    if (step === n) dot.classList.add('is-active');
                    else if (step < n) dot.classList.add('is-done');
                });

                if (n === 4) renderSummary();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            function validateStep(n) {
                if (n === 1) {
                    if (!getScope()) { alert('Pilih dulu jenis perawatan kamu.'); return false; }
                    return true;
                }
                if (n === 2) {
                    const required = ['name', 'email', 'phone', 'raw_branch_id', 'raw_doctor_id'];
                    // Scope-specific service field
                    required.push(isBehel() ? 'behel_type' : 'raw_doc_service_id');

                    for (const name of required) {
                        const el = form.querySelector('[name="' + name + '"]');
                        if (!el || !el.value.trim()) {
                            alert('Lengkapi dulu semua field di Data Diri & Perawatan.');
                            el?.focus();
                            return false;
                        }
                    }
                    return true;
                }
                if (n === 3) {
                    if (isBehel() && !form.querySelector('input[name="reward_program"]:checked')) {
                        alert('Pilih salah satu Tier Sweet Mates.');
                        return false;
                    }
                    return true;
                }
                return true;
            }

            function renderSummary() {
                const scope = getScope();
                const scopeLabel = scope === 'behel' ? 'Pasien Behel' : (scope === 'non_behel' ? 'Pasien Perawatan Standar' : '-');
                summaryBox.querySelector('[data-summary="scope"]').textContent = scopeLabel;
                summaryBox.querySelector('[data-summary="name"]').textContent  = form.querySelector('[name="name"]').value || '-';
                summaryBox.querySelector('[data-summary="email"]').textContent = form.querySelector('[name="email"]').value || '-';
                summaryBox.querySelector('[data-summary="phone"]').textContent = form.querySelector('[name="phone"]').value || '-';

                const tierEl = form.querySelector('input[name="reward_program"]:checked');
                if (tierEl) {
                    const tierLabel = tierEl.closest('.reward-option').querySelector('.tier-name').textContent;
                    summaryBox.querySelector('[data-summary="tier"]').textContent = tierLabel;
                }
            }

            // ── Event wiring ──────────────────────────────────────────
            form.querySelectorAll('input[name="referral_scope"]').forEach(inp => {
                inp.addEventListener('change', applyScopeVisibility);
            });

            form.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!validateStep(currentStep)) return;
                    showStep(parseInt(btn.dataset.next));
                });
            });

            form.querySelectorAll('[data-prev]').forEach(btn => {
                btn.addEventListener('click', () => showStep(parseInt(btn.dataset.prev)));
            });

            // Smart next: from step 2 → behel goes to 3, non_behel jumps to 4
            form.querySelectorAll('[data-next-smart]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!validateStep(currentStep)) return;
                    showStep(isBehel() ? 3 : 4);
                });
            });

            // Smart prev: from step 4 → behel goes back to 3, non_behel back to 2
            form.querySelectorAll('[data-prev-smart]').forEach(btn => {
                btn.addEventListener('click', () => showStep(isBehel() ? 3 : 2));
            });

            // Init from old() values if any
            if (oldScope) {
                applyScopeVisibility();
                if (@json($errors->any())) {
                    // On validation errors → jump to last step so user sees errors banner + stays close to where they failed
                    showStep(4);
                }
            } else {
                applyScopeVisibility(); // disables reward_program required when no scope yet
            }
        })();
    </script>

</body>
</html>
