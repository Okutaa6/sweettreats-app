<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sweet Mates - {{ $affiliate->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/affiliate-portal.css') }}?v={{ @filemtime(public_path('assets/css/affiliate-portal.css')) ?: '1' }}">
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'dashboard'])

    <section class="sm-section">
        <div class="sm-container">

            @if(session('success'))
                <div class="sm-alert sm-alert-success">
                    <i class="fa-solid fa-check-circle"></i>
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if(session('error'))
                <div class="sm-alert sm-alert-error">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            @php
                $tier = $affiliate->rewardTier();
                $firstName = explode(' ', $affiliate->name)[0];
                $targetCount = $tier ? $tier['content_count'] : 3;
                $progress = $targetCount > 0 ? min(100, (int) round(($approvedCount / $targetCount) * 100)) : 0;
            @endphp

            {{-- HERO CARD --}}
            <div class="sm-hero"
                @if($tier) style="--tier-gradient: {{ $tier['gradient'] }}; --tier-color: {{ $tier['color'] }};" @endif>
                <div class="sm-hero-deco sm-hero-deco-1"></div>
                <div class="sm-hero-deco sm-hero-deco-2"></div>
                <div class="sm-hero-grid">
                    <div class="sm-hero-text">
                        <div class="sm-hero-greeting">
                            <i class="fa-solid fa-magic"></i>
                            Halo Sweet Mate,
                        </div>
                        <h1 class="sm-hero-name">{{ $firstName }}!</h1>
                        <p class="sm-hero-sub">
                            Senyummu punya cerita. Yuk lanjut posting #BehelJourney dan raih reward kesehatan gigi kamu di akhir perawatan!
                        </p>
                        <div class="sm-hero-actions">
                            <a href="{{ route('affiliate.content') }}" class="sm-btn sm-btn-white">
                                <i class="fa-solid fa-video"></i> Kirim Konten
                            </a>
                            <a href="{{ route('affiliate.rewards') }}" class="sm-btn sm-btn-ghost">
                                <i class="fa-solid fa-trophy"></i> Lihat Reward
                            </a>
                        </div>
                    </div>
                    <div class="sm-hero-avatar">
                        @if($affiliate->photo)
                            <img src="{{ $affiliate->photo_url }}" alt="avatar">
                        @else
                            <div class="sm-hero-avatar-initial">{{ strtoupper(substr($affiliate->name, 0, 1)) }}</div>
                        @endif
                        @if($tier)
                            <div class="sm-hero-tier-badge">
                                <i class="fa-solid {{ $tier['icon'] }}"></i>
                                {{ $tier['name'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TIER JOURNEY CARD --}}
            @if($tier)
                <div class="sm-card sm-tier-card"
                     style="--tier-color: {{ $tier['color'] }}; --tier-gradient: {{ $tier['gradient'] }}; --tier-bg: {{ $tier['bg_soft'] }};">
                    <div class="sm-tier-head">
                        <div class="sm-tier-icon">
                            <i class="fa-solid {{ $tier['icon'] }}"></i>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div class="sm-tier-label">Tier Sweet Mates Kamu</div>
                            <div class="sm-tier-name">{{ $tier['name'] }}</div>
                            <div class="sm-tier-task">{{ $tier['content_desc'] }}</div>
                        </div>
                        <div class="sm-tier-reward-pill">
                            <i class="fa-solid fa-gift"></i>
                            {{ $tier['reward'] }}
                        </div>
                    </div>

                    <div class="sm-progress-wrap">
                        <div class="sm-progress-label">
                            <span>Progress Konten Behel Journey</span>
                            <span class="sm-progress-num">
                                <strong>{{ $approvedCount }}</strong>
                                <span class="sm-muted">/ {{ $targetCount }} konten approved belum terpakai</span>
                            </span>
                        </div>
                        <div class="sm-progress-track">
                            <div class="sm-progress-bar" style="width: {{ $progress }}%;">
                                @if($progress >= 15)
                                    <span class="sm-progress-pct">{{ $progress }}%</span>
                                @endif
                            </div>
                        </div>
                        @if(($cycleClaim ?? null)?->status === \App\Models\AffiliateRewardClaim::STATUS_USED)
                            {{-- Saldo bisa ≥ target sesudah reward dipakai (surplus terbawa),
                                 tapi tier yang sama tak bisa diklaim ulang tanpa pindah program. --}}
                            <div class="sm-progress-note sm-progress-note-success">
                                <i class="fa-solid fa-check-circle"></i>
                                Reward program ini sudah dipakai. Pilih program berikutnya di halaman
                                <a href="{{ route('affiliate.rewards') }}">Rewards</a> — konten yang belum terpakai ikut terbawa.
                            </div>
                        @elseif($progress >= 100)
                            <div class="sm-progress-note sm-progress-note-success">
                                <i class="fa-solid fa-sparkles"></i>
                                Yeay! Target konten kamu sudah tercapai. Reward akan diklaim di akhir perawatan ya!
                            </div>
                        @else
                            <div class="sm-progress-note">
                                <i class="fa-solid fa-seedling"></i>
                                Masih butuh <strong>{{ max(0, $targetCount - $approvedCount) }} konten lagi</strong>
                                untuk menyelesaikan misi kamu.
                            </div>
                        @endif
                    </div>

                    <div class="sm-lock-note">
                        <i class="fa-solid fa-lock"></i>
                        <div>
                            <strong>Reward diklaim di akhir perawatan</strong> saat kamu lepas behel.
                            Terus posting konten dan nikmati perjalanan behel journey kamu!
                        </div>
                    </div>
                </div>
            @else
                <div class="sm-card sm-card-empty">
                    <div class="sm-empty-icon">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <div class="sm-empty-title">Kamu belum pilih tier rewards</div>
                    <div class="sm-empty-sub">Hubungi admin Sweet Treats untuk bergabung ke Program Rewards Sweet Mates.</div>
                </div>
            @endif

            {{-- QUICK STATS --}}
            <div class="sm-stats-grid">
                <div class="sm-stat-card sm-stat-1">
                    <div class="sm-stat-icon"><i class="fa-solid fa-video"></i></div>
                    <div>
                        <div class="sm-stat-num">{{ $totalApproved ?? $approvedCount }}</div>
                        <div class="sm-stat-label">Konten Approved</div>
                    </div>
                </div>
                <div class="sm-stat-card sm-stat-2">
                    <div class="sm-stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="sm-stat-num">{{ $commissions->count() }}</div>
                        <div class="sm-stat-label">Total Referral</div>
                    </div>
                </div>
                <div class="sm-stat-card sm-stat-3">
                    <div class="sm-stat-icon"><i class="fa-solid fa-ticket-alt"></i></div>
                    <div>
                        <div class="sm-stat-num">{{ $promos->count() }}</div>
                        <div class="sm-stat-label">Kode Promo</div>
                    </div>
                </div>
            </div>

            {{-- PROMO + KOMISI CTA --}}
            <style>
                .sm-komisi-card {
                    background: linear-gradient(135deg, #461500, #8b3a00);
                    color: #fff;
                    border-radius: 24px;
                    padding: 26px;
                    box-shadow: 0 16px 40px rgba(70,21,0,0.2);
                    margin-bottom: 20px;
                    position: relative;
                    overflow: hidden;
                }
                .sm-komisi-card::before {
                    content: ''; position: absolute;
                    top: -60px; right: -60px; width: 200px; height: 200px;
                    background: rgba(255,255,255,0.06); border-radius: 50%;
                }
                .sm-komisi-head {
                    display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
                    position: relative;
                }
                .sm-komisi-head .title {
                    font-size: 0.75rem; font-weight: 800;
                    text-transform: uppercase; letter-spacing: 1px; opacity: 0.9;
                }
                .sm-komisi-promo {
                    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
                    padding: 14px 18px; border-radius: 14px;
                    background: rgba(255,255,255,0.15); backdrop-filter: blur(6px);
                    margin-bottom: 14px; position: relative;
                    cursor: pointer; transition: all 0.2s;
                    border: 1px dashed rgba(255,255,255,0.35);
                }
                .sm-komisi-promo:hover { background: rgba(255,255,255,0.22); }
                .sm-komisi-promo .code {
                    font-size: 1.1rem; font-weight: 900; letter-spacing: 1.5px;
                }
                .sm-komisi-promo .value {
                    padding: 3px 10px; border-radius: 50px;
                    background: rgba(255,255,255,0.25); font-size: 0.72rem; font-weight: 700;
                }
                .sm-komisi-promo .copy-hint {
                    margin-left: auto; font-size: 0.72rem; opacity: 0.85;
                    display: inline-flex; align-items: center; gap: 6px;
                }
                .sm-komisi-empty {
                    padding: 14px 18px; border-radius: 14px;
                    background: rgba(255,255,255,0.12);
                    font-size: 0.85rem; opacity: 0.9; margin-bottom: 14px;
                }
                .sm-komisi-mini-grid {
                    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
                    margin-bottom: 16px; position: relative;
                }
                .sm-komisi-mini {
                    padding: 12px; border-radius: 14px;
                    background: rgba(255,255,255,0.12); backdrop-filter: blur(6px);
                }
                .sm-komisi-mini .label {
                    font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;
                    opacity: 0.85; font-weight: 700;
                }
                .sm-komisi-mini .amount {
                    font-size: 0.95rem; font-weight: 900; margin-top: 4px; line-height: 1.1;
                }
                .sm-komisi-cta {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 11px 20px; border-radius: 50px;
                    background: #fff; color: #461500;
                    font-size: 0.82rem; font-weight: 800;
                    text-transform: uppercase; letter-spacing: 0.5px;
                    transition: all 0.25s; position: relative;
                }
                .sm-komisi-cta:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
                @media (max-width: 520px) {
                    .sm-komisi-mini-grid { grid-template-columns: 1fr; }
                    .sm-komisi-mini .amount { font-size: 1rem; }
                }
            </style>
            <div class="sm-komisi-card">
                <div class="sm-komisi-head">
                    <i class="fa-solid fa-wallet"></i>
                    <span class="title">Kode Promo &amp; Komisi Referral</span>
                </div>

                @forelse($promos as $promo)
                    @php
                        $promoValue = $promo->discount_type === 'percent'
                            ? $promo->discount_value . '%'
                            : 'Rp ' . number_format((float) $promo->discount_value, 0, ',', '.');
                    @endphp
                    <div class="sm-komisi-promo" onclick="copyDashPromo('{{ $promo->promo_code }}', this)">
                        <i class="fa-solid fa-ticket-alt"></i>
                        <span class="code">{{ $promo->promo_code }}</span>
                        <span class="value">{{ $promoValue }}</span>
                        <span class="copy-hint"><i class="fa-regular fa-copy"></i> Klik untuk copy</span>
                    </div>
                @empty
                    <div class="sm-komisi-empty">
                        <i class="fa-solid fa-ticket-alt"></i>
                        Belum ada kode promo aktif. Admin akan generate setelah kamu disetujui.
                    </div>
                @endforelse

                <div class="sm-komisi-mini-grid">
                    <div class="sm-komisi-mini">
                        <div class="label">Pending</div>
                        <div class="amount">Rp {{ number_format($pendingTotal, 0, ',', '.') }}</div>
                    </div>
                    <div class="sm-komisi-mini">
                        <div class="label">Disetujui</div>
                        <div class="amount">Rp {{ number_format($approvedTotal, 0, ',', '.') }}</div>
                    </div>
                    <div class="sm-komisi-mini">
                        <div class="label">Dibayar</div>
                        <div class="amount">Rp {{ number_format($paidTotal, 0, ',', '.') }}</div>
                    </div>
                </div>

                <a href="{{ route('affiliate.commissions') }}" class="sm-komisi-cta">
                    <i class="fa-solid fa-receipt"></i>
                    Lihat Riwayat Referral ({{ $commissions->count() }})
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <script>
                function copyDashPromo(code, el) {
                    navigator.clipboard?.writeText(code);
                    const hint = el.querySelector('.copy-hint');
                    if (hint) {
                        const old = hint.innerHTML;
                        hint.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
                        setTimeout(() => { hint.innerHTML = old; }, 1400);
                    }
                }
            </script>


            {{-- ACCOUNT INFO --}}
            <div class="sm-card sm-card-tinted">
                <div class="sm-card-title">
                    <i class="fa-solid fa-id-card"></i>
                    Info Akun Kamu
                </div>
                <div class="sm-info-grid">
                    <div class="sm-info-row">
                        <span class="sm-info-label"><i class="fa-solid fa-user"></i> Nama</span>
                        <span class="sm-info-value">{{ $affiliate->name }}</span>
                    </div>
                    <div class="sm-info-row">
                        <span class="sm-info-label"><i class="fa-solid fa-phone"></i> WhatsApp</span>
                        <span class="sm-info-value">{{ $affiliate->phone }}</span>
                    </div>
                    @if($affiliate->email)
                        <div class="sm-info-row">
                            <span class="sm-info-label"><i class="fa-solid fa-envelope"></i> Email</span>
                            <span class="sm-info-value">{{ $affiliate->email }}</span>
                        </div>
                    @endif
                    @if($affiliate->docService)
                        <div class="sm-info-row">
                            <span class="sm-info-label"><i class="fa-solid fa-tooth"></i> Perawatan</span>
                            <span class="sm-info-value">{{ $affiliate->docService->name }}</span>
                        </div>
                    @endif
                    @if($affiliate->branch)
                        <div class="sm-info-row">
                            <span class="sm-info-label"><i class="fa-solid fa-map-marker-alt"></i> Cabang</span>
                            <span class="sm-info-value">{{ $affiliate->branch->name }}</span>
                        </div>
                    @endif
                    @if($affiliate->doctor)
                        <div class="sm-info-row">
                            <span class="sm-info-label"><i class="fa-solid fa-user-md"></i> Dokter</span>
                            <span class="sm-info-value">{{ $affiliate->doctor->name }}</span>
                        </div>
                    @endif
                    @if($affiliate->social_platform)
                        <div class="sm-info-row">
                            <span class="sm-info-label"><i class="fa-solid fa-share-alt"></i> Social Media</span>
                            <span class="sm-info-value">
                                {{ \App\Models\RawAffiliate::socialPlatforms()[$affiliate->social_platform] ?? $affiliate->social_platform }}
                                — <strong>{{ $affiliate->social_username }}</strong>
                            </span>
                        </div>
                    @endif
                </div>
                <a href="{{ route('affiliate.profile') }}" class="sm-btn sm-btn-outline" style="margin-top:16px;">
                    <i class="fa-solid fa-pen"></i> Edit Profil
                </a>
            </div>

        </div>
    </section>

    <a href="{{ route('affiliate.content') }}" class="sm-fab">
        <i class="fa-solid fa-plus"></i>
        <span>Kirim Konten</span>
    </a>

</body>
</html>
