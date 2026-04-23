<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rewards - {{ $affiliate->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/affiliate-portal.css') }}?v={{ @filemtime(public_path('assets/css/affiliate-portal.css')) ?: '1' }}">

    <style>
        .sm-reward-grid { display: grid; gap: 16px; margin-bottom: 20px; }

        .sm-reward-card {
            position: relative;
            background: #fff;
            border-radius: 22px;
            padding: 24px;
            border: 1px solid rgba(70,21,0,0.06);
            box-shadow: 0 12px 30px rgba(70,21,0,0.06);
            overflow: hidden;
            transition: all 0.3s;
        }
        .sm-reward-card.is-chosen {
            border: 2px solid var(--tier-color);
            box-shadow: 0 18px 45px rgba(0,0,0,0.08), 0 0 0 4px var(--tier-bg);
        }
        .sm-reward-card.is-chosen::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 6px; background: var(--tier-gradient);
        }
        .sm-reward-card.is-locked { opacity: 0.72; }

        .sm-reward-head {
            display: flex; align-items: flex-start; gap: 16px; flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .sm-reward-icon {
            width: 64px; height: 64px; border-radius: 18px; flex-shrink: 0;
            background: var(--tier-gradient);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem;
            box-shadow: 0 8px 22px rgba(0,0,0,0.12);
        }
        .sm-reward-info { flex: 1; min-width: 200px; }
        .sm-reward-tier-label { font-size: 0.72rem; font-weight: 700; color: #8b7a6b; text-transform: uppercase; letter-spacing: 1px; }
        .sm-reward-tier-name { font-size: 1.35rem; font-weight: 900; color: #461500; margin-top: 2px; line-height: 1.15; }
        .sm-reward-task { font-size: 0.85rem; color: #6b4a30; margin-top: 6px; }
        .sm-reward-gift {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 14px; border-radius: 50px;
            background: var(--tier-gradient); color: #fff;
            font-size: 0.82rem; font-weight: 800;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
            margin-top: 10px;
        }

        .sm-reward-status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 50px;
            font-size: 0.72rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .sm-reward-status-pill.chosen { background: var(--tier-gradient); color: #fff; }
        .sm-reward-status-pill.locked { background: rgba(70,21,0,0.06); color: #8b7a6b; }

        .sm-reward-progress { margin-top: 8px; }
        .sm-reward-progress-label {
            display: flex; justify-content: space-between; align-items: flex-end;
            font-size: 0.82rem; color: #6b4a30; margin-bottom: 10px;
        }
        .sm-reward-progress-label strong { color: #461500; font-size: 0.95rem; font-weight: 900; }
        .sm-reward-progress-track {
            height: 12px; background: rgba(70,21,0,0.08);
            border-radius: 50px; overflow: hidden;
        }
        .sm-reward-progress-bar {
            height: 100%; border-radius: 50px;
            background: var(--tier-gradient);
            transition: width 0.6s ease;
        }

        .sm-reward-lock-note {
            margin-top: 16px; padding: 14px 16px; border-radius: 14px;
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            border: 1px dashed rgba(70,21,0,0.2);
            display: flex; align-items: flex-start; gap: 10px;
            font-size: 0.85rem; color: #6b4a30; line-height: 1.55;
        }
        .sm-reward-lock-note i { color: var(--tier-color); margin-top: 2px; }
        .sm-reward-lock-note strong { color: #461500; }

        .sm-reward-done {
            margin-top: 16px; padding: 14px 16px; border-radius: 14px;
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            color: #2e7d32; font-size: 0.88rem; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }

        .sm-info-box {
            padding: 22px; border-radius: 22px;
            background: linear-gradient(135deg, #fffaf5, #fef0e2);
            border: 1px solid rgba(70,21,0,0.08);
            font-size: 0.9rem; color: #6b4a30; line-height: 1.6;
        }
        .sm-info-box h3 {
            font-size: 1rem; color: #461500; font-weight: 900;
            text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 8px;
        }
        .sm-info-box ul { list-style: none; padding: 0; margin: 0; }
        .sm-info-box li { padding: 8px 0 8px 26px; position: relative; }
        .sm-info-box li::before {
            content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900;
            position: absolute; left: 0; color: #8b3a00; font-size: 0.82rem;
        }

        @media (max-width: 640px) {
            .sm-reward-card { padding: 20px; }
            .sm-reward-icon { width: 52px; height: 52px; font-size: 1.3rem; border-radius: 14px; }
            .sm-reward-tier-name { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'rewards'])

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

            @php
                $chosenKey = $affiliate->reward_program;
                $chosenTier = $affiliate->rewardTier();
                $allTiers = \App\Models\RawAffiliate::rewardTiers();
            @endphp

            {{-- HERO --}}
            <div class="sm-hero"
                @if($chosenTier) style="--tier-gradient: {{ $chosenTier['gradient'] }}; --tier-color: {{ $chosenTier['color'] }};" @endif>
                <div class="sm-hero-deco sm-hero-deco-1"></div>
                <div class="sm-hero-deco sm-hero-deco-2"></div>
                <div class="sm-hero-grid">
                    <div class="sm-hero-text">
                        <div class="sm-hero-greeting">
                            <i class="fa-solid fa-trophy"></i>
                            Program Rewards Sweet Mates
                        </div>
                        <h1 class="sm-hero-name">Reward Behel Journey</h1>
                        <p class="sm-hero-sub">
                            Setiap konten yang kamu posting bikin perjalanan behel kamu makin berharga. Yuk lihat progress dan reward yang menanti di akhir perawatan!
                        </p>
                    </div>
                    <div class="sm-hero-avatar">
                        <div class="sm-hero-avatar-initial">
                            <i class="fa-solid {{ $chosenTier['icon'] ?? 'fa-trophy' }}"></i>
                        </div>
                        @if($chosenTier)
                            <div class="sm-hero-tier-badge">
                                <i class="fa-solid {{ $chosenTier['icon'] }}"></i>
                                {{ $chosenTier['name'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- REWARD TIERS --}}
            @if(!$chosenKey)
                <div class="sm-card sm-card-empty">
                    <div class="sm-empty-icon"><i class="fa-solid fa-circle-info"></i></div>
                    <div class="sm-empty-title">Kamu belum pilih tier reward</div>
                    <div class="sm-empty-sub">Hubungi admin Sweet Treats untuk bergabung ke program Sweet Mates dan pilih tier kamu.</div>
                </div>
            @endif

            <div class="sm-reward-grid">
                @foreach($allTiers as $key => $tier)
                    @php
                        $isChosen = $key === $chosenKey;
                        $targetCount = $tier['content_count'];
                        $progress = $isChosen && $targetCount > 0
                            ? min(100, (int) round(($approvedCount / $targetCount) * 100))
                            : 0;
                        $done = $isChosen && $approvedCount >= $targetCount;
                    @endphp

                    <div class="sm-reward-card {{ $isChosen ? 'is-chosen' : 'is-locked' }}"
                         style="--tier-color: {{ $tier['color'] }}; --tier-gradient: {{ $tier['gradient'] }}; --tier-bg: {{ $tier['bg_soft'] }};">
                        <div class="sm-reward-head">
                            <div class="sm-reward-icon">
                                <i class="fa-solid {{ $tier['icon'] }}"></i>
                            </div>
                            <div class="sm-reward-info">
                                <div class="sm-reward-tier-label">Tier Sweet Mates</div>
                                <div class="sm-reward-tier-name">{{ $tier['name'] }}</div>
                                <div class="sm-reward-task">{{ $tier['content_desc'] }}</div>
                                <div class="sm-reward-gift">
                                    <i class="fa-solid fa-gift"></i> {{ $tier['reward'] }}
                                </div>
                            </div>
                            <div>
                                @if($isChosen)
                                    <span class="sm-reward-status-pill chosen">
                                        <i class="fa-solid fa-check"></i> Tier Kamu
                                    </span>
                                @else
                                    <span class="sm-reward-status-pill locked">
                                        <i class="fa-solid fa-lock"></i> Tidak Dipilih
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if($isChosen)
                            <div class="sm-reward-progress">
                                <div class="sm-reward-progress-label">
                                    <span>Progress Konten</span>
                                    <span><strong>{{ min($approvedCount, $targetCount) }}</strong> / {{ $targetCount }} approved</span>
                                </div>
                                <div class="sm-reward-progress-track">
                                    <div class="sm-reward-progress-bar" style="width: {{ $progress }}%;"></div>
                                </div>
                            </div>

                            @if($done)
                                <div class="sm-reward-done">
                                    <i class="fa-solid fa-sparkles"></i>
                                    Yeay! Target konten sudah tercapai. Reward siap diklaim di akhir perawatan.
                                </div>
                            @endif

                            <div class="sm-reward-lock-note">
                                <i class="fa-solid fa-lock"></i>
                                <div>
                                    <strong>Reward diklaim di akhir perawatan.</strong>
                                    Sweet Treats akan memproses reward kamu saat waktunya lepas behel. Terus posting konten dan nikmati prosesnya ya!
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- INFO / KETENTUAN --}}
            <div class="sm-info-box">
                <h3><i class="fa-solid fa-circle-info"></i> Ketentuan Program</h3>
                <ul>
                    <li>Tier Sweet Mates dipilih di awal program dan <strong>tidak bisa diubah</strong>.</li>
                    <li>Setiap konten harus di-review dan di-approve admin untuk dihitung.</li>
                    <li>Reward hanya dapat diklaim di <strong>akhir perawatan</strong> (saat lepas behel).</li>
                    <li>Mention <strong>@sweet.treats.dental</strong> di caption kontenmu supaya verifikasi lebih cepat.</li>
                    <li>Hubungi admin Sweet Treats jika ada pertanyaan terkait reward kamu.</li>
                </ul>
            </div>

        </div>
    </section>

    <a href="{{ route('affiliate.content') }}" class="sm-fab">
        <i class="fa-solid fa-plus"></i>
        <span>Kirim Konten</span>
    </a>

</body>
</html>
