<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - {{ $affiliate->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/affiliate-portal.css') }}?v={{ @filemtime(public_path('assets/css/affiliate-portal.css')) ?: '1' }}">

    <style>
        .sm-form-group { margin-bottom: 16px; }
        .sm-form-label {
            display: block; font-size: 0.78rem; font-weight: 700;
            color: #461500; margin-bottom: 8px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sm-form-label .sm-opt { font-weight: 400; text-transform: none; color: #8b7a6b; letter-spacing: 0; }
        .sm-form-control {
            width: 100%; padding: 13px 16px; background: #fffaf5;
            border: 2px solid rgba(70,21,0,0.1); border-radius: 12px;
            font-family: 'Montserrat', sans-serif; font-size: 0.92rem; color: #3a2a1a;
            transition: all 0.3s; outline: none;
        }
        .sm-form-control:focus { border-color: #461500; background: #fff; box-shadow: 0 0 0 4px rgba(70,21,0,0.08); }
        .sm-form-control.readonly { background: #f5ede3; color: #8b7a6b; cursor: not-allowed; }
        textarea.sm-form-control { resize: vertical; min-height: 80px; }

        .sm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .sm-help { font-size: 0.72rem; color: #8b7a6b; margin-top: 5px; }

        .sm-social-input { position: relative; }
        .sm-social-input > i {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #461500; font-size: 1rem;
        }
        .sm-social-input .sm-form-control { padding-left: 44px; }

        /* Profile hero override for avatar upload */
        .sm-profile-hero {
            background: linear-gradient(135deg, #461500, #8b3a00);
        }
        .sm-avatar-lg {
            width: 120px; height: 120px; border-radius: 28px;
            background: rgba(255,255,255,0.2); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 3rem; font-weight: 900;
            flex-shrink: 0; box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }
        .sm-avatar-lg img { width: 100%; height: 100%; object-fit: cover; }

        .sm-submit-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 13px 26px; border: none; border-radius: 50px;
            background: linear-gradient(135deg, #461500, #6b2400); color: #fff;
            font-family: 'Montserrat', sans-serif; font-size: 0.85rem; font-weight: 800;
            cursor: pointer; transition: all 0.3s;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .sm-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(70,21,0,0.3); }

        .sm-photo-row { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .sm-photo-preview {
            width: 96px; height: 96px; border-radius: 24px;
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            color: #461500; font-size: 2.2rem; font-weight: 900;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
            border: 2px solid rgba(70,21,0,0.1);
        }
        .sm-photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .sm-photo-info { flex: 1; min-width: 220px; }

        .sm-sub-title {
            font-size: 0.82rem; font-weight: 900; color: #461500;
            text-transform: uppercase; letter-spacing: 0.8px;
            margin: 26px 0 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .sm-sub-title i { color: #8b3a00; }

        @media (max-width: 640px) {
            .sm-form-row { grid-template-columns: 1fr; }
            .sm-avatar-lg { width: 96px; height: 96px; font-size: 2.4rem; border-radius: 22px; }
        }
    </style>
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'profile'])

    <section class="sm-section">
        <div class="sm-container">

            @if(session('success'))
                <div class="sm-alert sm-alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="sm-alert sm-alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="sm-alert sm-alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        @foreach($errors->all() as $err)
                            <div>{{ $err }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            @php
                $tier = $affiliate->rewardTier();
            @endphp

            {{-- PROFILE HERO --}}
            <div class="sm-hero sm-profile-hero"
                @if($tier) style="--tier-gradient: {{ $tier['gradient'] }}; --tier-color: {{ $tier['color'] }};" @endif>
                <div class="sm-hero-deco sm-hero-deco-1"></div>
                <div class="sm-hero-deco sm-hero-deco-2"></div>
                <div class="sm-hero-grid">
                    <div class="sm-hero-text">
                        <div class="sm-hero-greeting">
                            <i class="fa-solid fa-user"></i> Profil Sweet Mate
                        </div>
                        <h1 class="sm-hero-name">{{ $affiliate->name }}</h1>
                        <p class="sm-hero-sub">
                            {{ ucfirst($affiliate->role ?? 'Sweet Mate') }}
                            @if($affiliate->type) &middot; {{ strtoupper($affiliate->type) }} @endif
                            &middot; {{ $affiliate->phone }}
                        </p>
                        <div class="sm-hero-actions">
                            <span class="sm-btn sm-btn-white" style="pointer-events:none;">
                                <i class="fa-solid fa-video"></i> {{ $approvedCount }} Konten Approved
                            </span>
                            @if($tier)
                                <span class="sm-btn sm-btn-ghost" style="pointer-events:none;">
                                    <i class="fa-solid {{ $tier['icon'] }}"></i> {{ $tier['name'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="sm-avatar-lg">
                        @if($affiliate->photo)
                            <img src="{{ $affiliate->photo_url }}" alt="{{ $affiliate->name }}">
                        @else
                            {{ strtoupper(substr($affiliate->name, 0, 1)) }}
                        @endif
                    </div>
                </div>
            </div>

            {{-- UPLOAD FOTO --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-camera"></i>
                    Foto Profil
                </div>
                <form method="POST" action="{{ route('affiliate.profile.photo') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="sm-photo-row">
                        <div class="sm-photo-preview">
                            @if($affiliate->photo)
                                <img src="{{ $affiliate->photo_url }}" alt="preview">
                            @else
                                {{ strtoupper(substr($affiliate->name, 0, 1)) }}
                            @endif
                        </div>
                        <div class="sm-photo-info">
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sm-form-control" required>
                            <div class="sm-help">Format: JPG, PNG, atau WEBP. Maksimal 2MB.</div>
                            <button type="submit" class="sm-submit-btn" style="margin-top:12px;">
                                <i class="fa-solid fa-upload"></i> Upload Foto
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- DATA DIRI --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-id-card"></i>
                    Data Diri
                </div>
                <form method="POST" action="{{ route('affiliate.profile.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="sm-form-row">
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="name">Nama Lengkap</label>
                            <input type="text" name="name" id="name" class="sm-form-control"
                                   value="{{ old('name', $affiliate->name) }}" maxlength="255" required>
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="phone">Nomor WhatsApp</label>
                            <input type="text" name="phone" id="phone" class="sm-form-control"
                                   value="{{ old('phone', $affiliate->phone) }}" maxlength="20" required>
                        </div>
                    </div>

                    <div class="sm-form-row">
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="email">Email <span class="sm-opt">(opsional)</span></label>
                            <input type="email" name="email" id="email" class="sm-form-control"
                                   value="{{ old('email', $affiliate->email) }}" maxlength="255">
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-form-label">Komisi per Referral</label>
                            <input type="text" class="sm-form-control readonly" readonly
                                   value="Rp {{ number_format((float) $affiliate->commission_rate, 0, ',', '.') }}">
                            <div class="sm-help">Hubungi admin untuk mengubah.</div>
                        </div>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="bank_account_info">
                            Info Rekening Bank <span class="sm-opt">(untuk pencairan komisi)</span>
                        </label>
                        <textarea name="bank_account_info" id="bank_account_info" class="sm-form-control" maxlength="255"
                                  placeholder="Contoh: BCA 1234567890 a.n. Budi Santoso">{{ old('bank_account_info', $affiliate->bank_account_info) }}</textarea>
                    </div>

                    <div class="sm-sub-title">
                        <i class="fa-solid fa-share-nodes"></i> Social Media
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="social_tiktok">TikTok <span class="sm-opt">(opsional)</span></label>
                        <div class="sm-social-input">
                            <i class="fa-brands fa-tiktok"></i>
                            <input type="text" name="social_tiktok" id="social_tiktok" class="sm-form-control"
                                   value="{{ old('social_tiktok', $affiliate->social_tiktok) }}" maxlength="100"
                                   placeholder="@username">
                        </div>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="social_instagram">Instagram <span class="sm-opt">(opsional)</span></label>
                        <div class="sm-social-input">
                            <i class="fa-brands fa-instagram"></i>
                            <input type="text" name="social_instagram" id="social_instagram" class="sm-form-control"
                                   value="{{ old('social_instagram', $affiliate->social_instagram) }}" maxlength="100"
                                   placeholder="@username">
                        </div>
                    </div>

                    <div class="sm-form-group">
                        <label class="sm-form-label" for="social_youtube">YouTube <span class="sm-opt">(opsional)</span></label>
                        <div class="sm-social-input">
                            <i class="fa-brands fa-youtube"></i>
                            <input type="text" name="social_youtube" id="social_youtube" class="sm-form-control"
                                   value="{{ old('social_youtube', $affiliate->social_youtube) }}" maxlength="100"
                                   placeholder="@channel">
                        </div>
                    </div>

                    <div style="margin-top:12px;">
                        <button type="submit" class="sm-submit-btn">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

            {{-- GANTI PIN --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-key"></i>
                    Ganti PIN
                </div>
                <form method="POST" action="{{ route('affiliate.profile.pin') }}">
                    @csrf
                    @method('PUT')

                    <div class="sm-form-row">
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="current_pin">PIN Saat Ini</label>
                            <input type="password" name="current_pin" id="current_pin" class="sm-form-control"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                                   placeholder="••••••" style="letter-spacing:6px; text-align:center; font-weight:700;">
                        </div>
                    </div>

                    <div class="sm-form-row">
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="new_pin">PIN Baru</label>
                            <input type="password" name="new_pin" id="new_pin" class="sm-form-control"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                                   placeholder="••••••" style="letter-spacing:6px; text-align:center; font-weight:700;">
                        </div>
                        <div class="sm-form-group">
                            <label class="sm-form-label" for="new_pin_confirmation">Konfirmasi PIN Baru</label>
                            <input type="password" name="new_pin_confirmation" id="new_pin_confirmation" class="sm-form-control"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                                   placeholder="••••••" style="letter-spacing:6px; text-align:center; font-weight:700;">
                        </div>
                    </div>

                    <div style="margin-top:12px;">
                        <button type="submit" class="sm-submit-btn">
                            <i class="fa-solid fa-key"></i> Ganti PIN
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </section>

</body>
</html>
