<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Rewards - Sweet Treats Dental Clinic</title>
    <meta name="description" content="Daftar Program Rewards Pasien Ortho Sweet Treats — abadikan #CeritaPasangBehel &amp; #BehelJourney kamu.">

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
        .form-header { text-align: center; margin-bottom: 40px; }
        .form-header .tag { display: inline-block; padding: 6px 16px; background: rgba(70,21,0,0.06); border-radius: 50px; font-size: 0.8rem; font-weight: 700; color: #461500; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .form-header h1 { font-size: 2.2rem; font-weight: 900; color: #461500; margin-bottom: 12px; line-height: 1.2; }
        .form-header p { font-size: 1rem; color: #6b4a30; line-height: 1.6; }
        .form-header .hashtags { margin-top: 14px; display: inline-flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .form-header .hashtag { font-size: 0.75rem; font-weight: 700; color: #461500; background: rgba(70,21,0,0.06); padding: 6px 14px; border-radius: 50px; letter-spacing: 0.3px; }

        .form-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 20px 60px rgba(70,21,0,0.08); border: 1px solid rgba(70,21,0,0.04); }

        .section-block { margin-bottom: 32px; }
        .section-block:last-of-type { margin-bottom: 16px; }
        .section-title { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; font-weight: 800; color: #461500; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid rgba(70,21,0,0.08); }
        .section-title .section-icon { width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg,#fef5ed,#fef0e2); display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; color: #461500; }
        .section-desc { font-size: 0.82rem; color: #8b7a6b; margin-top: -8px; margin-bottom: 16px; line-height: 1.5; }

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

        /* Reward tier cards */
        .reward-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
        .reward-option { position: relative; }
        .reward-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
        .reward-option label { display: grid; grid-template-columns: 56px 1fr 26px; gap: 16px; align-items: center; padding: 18px 20px; background: #fffaf5; border: 2px solid rgba(70,21,0,0.08); border-radius: 18px; cursor: pointer; transition: all 0.3s; position: relative; overflow: hidden; }
        .reward-option label::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: var(--tier-color, #c68a56); opacity: 0; transition: opacity 0.3s; }
        .reward-option label:hover { border-color: rgba(70,21,0,0.2); background: #fef5ed; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(70,21,0,0.08); }
        .reward-option label:hover::before { opacity: 0.5; }
        .reward-option input:checked + label { border-color: var(--tier-color, #461500); background: #fff; transform: translateY(-2px); box-shadow: 0 14px 32px rgba(70,21,0,0.12); }
        .reward-option input:checked + label::before { opacity: 1; }
        .tier-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--tier-bg, linear-gradient(135deg,#fef5ed,#fef0e2)); color: var(--tier-color, #461500); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; transition: all 0.3s; }
        .reward-option input:checked + label .tier-icon { background: var(--tier-gradient, linear-gradient(135deg,#461500,#6b2400)); color: #fff; transform: scale(1.05); box-shadow: 0 8px 18px rgba(0,0,0,0.12); }
        .tier-info { min-width: 0; }
        .tier-name { font-size: 1rem; font-weight: 900; color: #461500; letter-spacing: 0.3px; margin-bottom: 3px; }
        .tier-task { font-size: 0.78rem; color: #8b7a6b; font-weight: 600; margin-bottom: 8px; }
        .tier-reward { display: inline-flex; align-items: center; gap: 6px; font-size: 0.76rem; font-weight: 700; color: var(--tier-color, #461500); background: var(--tier-bg, rgba(70,21,0,0.06)); padding: 5px 12px; border-radius: 50px; }
        .tier-reward i { font-size: 0.72rem; }
        .reward-check { width: 26px; height: 26px; border: 2px solid rgba(70,21,0,0.18); border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: transparent; font-size: 0.78rem; transition: all 0.3s; }
        .reward-option input:checked + label .reward-check { background: var(--tier-color, #461500); border-color: var(--tier-color, #461500); color: #fff; }

        .reward-lock-note { margin-top: 16px; padding: 14px 16px; background: linear-gradient(135deg, #fef5ed, #fef0e2); border: 1px dashed rgba(70,21,0,0.25); border-radius: 14px; display: flex; align-items: flex-start; gap: 10px; font-size: 0.8rem; color: #6b4a30; line-height: 1.55; }
        .reward-lock-note i { color: #c45200; font-size: 1rem; margin-top: 2px; flex-shrink: 0; }
        .reward-lock-note strong { color: #461500; }

        /* Social combo */
        .social-combo { display: grid; grid-template-columns: 180px 1fr; gap: 12px; }
        .social-combo select { padding-right: 38px; }

        /* Submit */
        .btn-submit { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 16px; background: linear-gradient(135deg, #461500, #6b2400); color: #fff; border: none; border-radius: 14px; font-family: 'Montserrat', sans-serif; font-size: 1rem; font-weight: 800; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 12px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(70,21,0,0.35); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        .form-note { text-align: center; margin-top: 20px; font-size: 0.82rem; color: #8b7a6b; line-height: 1.6; }
        .form-note a { color: #461500; font-weight: 700; border-bottom: 1px dashed rgba(70,21,0,0.4); }
        .form-note a:hover { color: #6b2400; border-bottom-color: #6b2400; }

        @media (max-width: 640px) {
            .container { padding: 0 16px; }
            .navbar { padding: 0 16px; }
            .navbar-inner { height: 60px; }
            .navbar-brand { font-size: 1.2rem; }
            .btn-back { padding: 8px 14px; font-size: 0.78rem; }
            .form-section { padding: 32px 0 60px; }
            .form-header { margin-bottom: 28px; }
            .form-header h1 { font-size: 1.55rem; }
            .form-header p { font-size: 0.88rem; }
            .form-card { padding: 24px 18px; border-radius: 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .social-combo { grid-template-columns: 1fr; }
            .form-group label { font-size: 0.75rem; }
            .form-control { padding: 12px 14px; font-size: 0.88rem; border-radius: 12px; }
            .section-title { font-size: 0.82rem; }
            .reward-option label { padding: 14px 16px; gap: 10px; border-radius: 14px; }
            .reward-name { font-size: 0.88rem; }
            .reward-desc { font-size: 0.76rem; }
            .btn-submit { padding: 14px; font-size: 0.88rem; border-radius: 12px; }
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
                <i class="fa-solid fa-right-to-bracket"></i> Login
            </a>
        </div>
    </nav>

    <section class="form-section">
        <div class="container">
            <div class="form-header">
                <div class="tag"><i class="fa-solid fa-gift"></i> Program Rewards Pasien Ortho</div>
                <h1>Senyummu Punya Cerita</h1>
                <p>
                    Yuk ikutan Program Reward Pasien Ortho Sweet Treats!<br>
                    Isi data dirimu dan abadikan momen behel journey kamu.
                </p>
                <div class="hashtags">
                    <span class="hashtag">#CeritaPasangBehel</span>
                    <span class="hashtag">#BehelJourney</span>
                </div>
            </div>

            <div class="form-card">

                @if(session('error'))
                    <div class="alert-error">
                        <div class="alert-title"><i class="fa-solid fa-circle-exclamation"></i> Terjadi kesalahan</div>
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert-error">
                        <div class="alert-title"><i class="fa-solid fa-circle-exclamation"></i> Periksa kembali data kamu</div>
                        <ul>
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('affiliate.register.process') }}" id="registerForm">
                    @csrf

                    {{-- Data Diri --}}
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
                                       value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required
                                       inputmode="tel">
                            </div>
                        </div>
                    </div>

                    {{-- Perawatan --}}
                    <div class="section-block">
                        <div class="section-title">
                            <span class="section-icon"><i class="fa-solid fa-tooth"></i></span>
                            Perawatan Behel
                        </div>
                        <p class="section-desc">
                            Program Rewards ini khusus untuk pasien yang sudah menjalani perawatan behel di Sweet Treats.
                        </p>

                        <div class="form-group">
                            <label>Jenis Perawatan Behel <span class="required">*</span></label>
                            <select name="raw_doc_service_id" class="form-control" required>
                                <option value="">-- Pilih Jenis Behel --</option>
                                @foreach($services as $svc)
                                    <option value="{{ $svc->id }}" {{ old('raw_doc_service_id') == $svc->id ? 'selected' : '' }}>
                                        {{ $svc->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Cabang Sweet Treats <span class="required">*</span></label>
                                <select name="raw_branch_id" class="form-control" required>
                                    <option value="">-- Pilih Cabang --</option>
                                    @foreach($branches as $br)
                                        <option value="{{ $br->id }}" {{ old('raw_branch_id') == $br->id ? 'selected' : '' }}>
                                            {{ $br->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dokter <span class="required">*</span></label>
                                <select name="raw_doctor_id" class="form-control" required>
                                    <option value="">-- Pilih Dokter --</option>
                                    @foreach($doctors as $dr)
                                        <option value="{{ $dr->id }}" {{ old('raw_doctor_id') == $dr->id ? 'selected' : '' }}>
                                            {{ $dr->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Rewards Program --}}
                    <div class="section-block">
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
                                           value="{{ $key }}"
                                           {{ $selectedReward === $key ? 'checked' : '' }} required>
                                    <label for="reward_{{ $key }}">
                                        <span class="tier-icon">
                                            <i class="fa-solid {{ $tier['icon'] }}"></i>
                                        </span>
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
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                Reward hanya bisa <strong>diklaim di akhir perawatan saat lepas behel</strong>.
                                Sekali pilih tier, tidak bisa diubah — pastikan komitmenmu ya!
                            </div>
                        </div>
                    </div>

                    {{-- Social Media --}}
                    <div class="section-block">
                        <div class="section-title">
                            <span class="section-icon"><i class="fa-solid fa-share-nodes"></i></span>
                            Sosial Media
                        </div>
                        <p class="section-desc">
                            Platform utama tempat kamu akan posting konten behel journey.
                        </p>

                        <div class="form-group">
                            <label>Platform &amp; Username <span class="required">*</span></label>
                            <div class="social-combo">
                                <select name="social_platform" class="form-control" required>
                                    <option value="">-- Platform --</option>
                                    @foreach(\App\Models\RawAffiliate::socialPlatforms() as $key => $label)
                                        <option value="{{ $key }}" {{ old('social_platform') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="text" name="social_username" class="form-control"
                                       maxlength="100" placeholder="@username"
                                       value="{{ old('social_username') }}" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Daftar Sekarang
                    </button>
                </form>

                <div class="form-note">
                    Sudah terdaftar? <a href="{{ route('affiliate.login') }}">Login di sini</a>
                </div>

            </div>
        </div>
    </section>

</body>
</html>
