<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komisi - {{ $affiliate->name }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="stylesheet" href="{{ asset('assets/css/affiliate-portal.css') }}?v={{ @filemtime(public_path('assets/css/affiliate-portal.css')) ?: '1' }}">

    <style>
        .sm-promo-row { display: flex; flex-wrap: wrap; gap: 12px; }
        .sm-promo-big {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 20px; border-radius: 16px;
            background: linear-gradient(135deg, #461500, #8b3a00);
            color: #fff; box-shadow: 0 10px 24px rgba(70,21,0,0.2);
            cursor: pointer; transition: all 0.25s;
            border: none; font-family: 'Montserrat', sans-serif;
        }
        .sm-promo-big:hover { transform: translateY(-2px); box-shadow: 0 14px 32px rgba(70,21,0,0.28); }
        .sm-promo-big .code {
            font-size: 1.1rem; font-weight: 900; letter-spacing: 1.5px;
        }
        .sm-promo-big .value {
            font-size: 0.72rem; font-weight: 700; opacity: 0.9;
            background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 50px;
        }
        .sm-promo-big .copy-icon {
            font-size: 0.9rem; opacity: 0.8;
        }

        .sm-hint {
            margin-top: 10px; font-size: 0.78rem; color: #8b7a6b;
            display: flex; align-items: center; gap: 8px;
        }
        .sm-hint i { color: #c68a56; }

        .sm-ref-table {
            display: grid; gap: 10px;
        }
        .sm-ref-item {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; border-radius: 14px;
            background: #fffaf5; border: 1px solid rgba(70,21,0,0.06);
            transition: all 0.2s;
        }
        .sm-ref-item:hover { background: #fef5ed; border-color: rgba(70,21,0,0.12); }
        .sm-ref-avatar {
            width: 44px; height: 44px; border-radius: 14px; flex-shrink: 0;
            background: linear-gradient(135deg, #c68a56, #8b3a00);
            color: #fff; font-weight: 800; font-size: 1rem;
            display: flex; align-items: center; justify-content: center;
        }
        .sm-ref-main { flex: 1; min-width: 0; }
        .sm-ref-name { font-size: 0.95rem; font-weight: 800; color: #461500; }
        .sm-ref-meta {
            font-size: 0.75rem; color: #8b7a6b; margin-top: 4px;
            display: flex; gap: 10px; flex-wrap: wrap;
        }
        .sm-ref-meta i { color: #c68a56; margin-right: 3px; }
        .sm-ref-right { text-align: right; flex-shrink: 0; }
        .sm-ref-amount { font-size: 1rem; font-weight: 900; color: #461500; line-height: 1; }
        .sm-ref-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 50px; margin-top: 6px;
            font-size: 0.65rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .sm-ref-badge.pending { background: #fff8e1; color: #f57c00; }
        .sm-ref-badge.approved { background: #e3f2fd; color: #1976d2; }
        .sm-ref-badge.paid { background: #e8f5e9; color: #2e7d32; }
        .sm-ref-badge.rejected { background: #ffebee; color: #c62828; }

        .sm-filter-bar {
            display: flex; gap: 6px; flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .sm-filter-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 50px;
            background: #fff; border: 1.5px solid rgba(70,21,0,0.12);
            color: #6b4a30; font-size: 0.78rem; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
            font-family: 'Montserrat', sans-serif;
        }
        .sm-filter-chip:hover { border-color: #461500; color: #461500; }
        .sm-filter-chip.active {
            background: #461500; color: #fff; border-color: #461500;
        }
        .sm-filter-chip .count {
            background: rgba(255,255,255,0.25); padding: 1px 7px;
            border-radius: 50px; font-size: 0.7rem;
        }
        .sm-filter-chip:not(.active) .count {
            background: rgba(70,21,0,0.08);
        }

        .sm-total-bar {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 16px; border-radius: 12px;
            background: linear-gradient(135deg, #fef5ed, #fef0e2);
            font-size: 0.82rem; color: #6b4a30; margin-top: 14px;
        }
        .sm-total-bar strong { color: #461500; font-size: 0.92rem; }

        @media (max-width: 520px) {
            .sm-ref-item { flex-wrap: wrap; }
            .sm-ref-right { width: 100%; text-align: left; margin-top: 8px; padding-left: 58px; }
        }
    </style>
</head>
<body>

    @include('affiliate._portal_navbar', ['active' => 'commissions'])

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

            {{-- HERO --}}
            <div class="sm-hero">
                <div class="sm-hero-deco sm-hero-deco-1"></div>
                <div class="sm-hero-deco sm-hero-deco-2"></div>
                <div class="sm-hero-grid">
                    <div class="sm-hero-text">
                        <div class="sm-hero-greeting">
                            <i class="fa-solid fa-wallet"></i>
                            Program Referral
                        </div>
                        <h1 class="sm-hero-name">Komisi Kamu</h1>
                        <p class="sm-hero-sub">
                            Share kode promo ke teman &mdash; setiap kali dipakai di klinik, kamu dapat komisi. Pantau semua transaksi referral kamu di sini.
                        </p>
                    </div>
                    <div class="sm-hero-avatar">
                        <div class="sm-hero-avatar-initial"><i class="fa-solid fa-sack-dollar"></i></div>
                    </div>
                </div>
            </div>

            {{-- KODE PROMO --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-ticket"></i>
                    Kode Promo Kamu
                </div>
                @forelse($promos as $promo)
                    @php
                        $value = $promo->discount_type === 'percent'
                            ? $promo->discount_value . '%'
                            : 'Rp ' . number_format((float) $promo->discount_value, 0, ',', '.');
                    @endphp
                    <button type="button" class="sm-promo-big" onclick="copyPromo('{{ $promo->promo_code }}', this)">
                        <i class="fa-solid fa-ticket"></i>
                        <span class="code">{{ $promo->promo_code }}</span>
                        <span class="value">{{ $value }}</span>
                        <i class="fa-regular fa-copy copy-icon"></i>
                    </button>
                @empty
                    <div class="sm-inline-empty">
                        <i class="fa-solid fa-ticket"></i>
                        Belum ada kode promo aktif. Admin akan generate setelah kamu disetujui.
                    </div>
                @endforelse

                @if($promos->isNotEmpty())
                    <div class="sm-hint">
                        <i class="fa-solid fa-lightbulb"></i>
                        Klik kode untuk copy. Share ke teman via WhatsApp, Instagram story, atau caption konten kamu.
                    </div>
                @endif
            </div>

            {{-- RINGKASAN --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-chart-pie"></i>
                    Ringkasan Komisi
                </div>
                <div class="sm-summary-grid">
                    <div class="sm-summary-item sm-summary-warn">
                        <div class="sm-summary-icon"><i class="fa-solid fa-clock"></i></div>
                        <div>
                            <div class="sm-summary-label">Pending</div>
                            <div class="sm-summary-value">Rp {{ number_format($pendingTotal, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="sm-summary-item sm-summary-info">
                        <div class="sm-summary-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div>
                            <div class="sm-summary-label">Disetujui</div>
                            <div class="sm-summary-value">Rp {{ number_format($approvedTotal, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="sm-summary-item sm-summary-success">
                        <div class="sm-summary-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div>
                            <div class="sm-summary-label">Sudah Dibayar</div>
                            <div class="sm-summary-value">Rp {{ number_format($paidTotal, 0, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIWAYAT --}}
            <div class="sm-card">
                <div class="sm-card-title">
                    <i class="fa-solid fa-receipt"></i>
                    Riwayat Referral ({{ $commissions->count() }})
                </div>

                @if($commissions->isEmpty())
                    <div class="sm-inline-empty" style="flex-direction:column; gap:10px; padding:32px; text-align:center; justify-content:center;">
                        <i class="fa-solid fa-receipt" style="font-size:2rem;"></i>
                        <div>
                            <div style="font-weight:800; color:#461500; font-size:1rem;">Belum ada referral masuk</div>
                            <div style="margin-top:6px;">Share kode promo kamu ke teman-teman &mdash; setiap transaksi yang pakai kode kamu akan muncul di sini.</div>
                        </div>
                    </div>
                @else
                    @php
                        $counts = [
                            'all' => $commissions->count(),
                            'pending' => $commissions->where('status','pending')->count(),
                            'approved' => $commissions->where('status','approved')->count(),
                            'paid' => $commissions->where('status','paid')->count(),
                            'rejected' => $commissions->where('status','rejected')->count(),
                        ];
                    @endphp
                    <div class="sm-filter-bar">
                        <button type="button" class="sm-filter-chip active" data-filter="all">
                            Semua <span class="count">{{ $counts['all'] }}</span>
                        </button>
                        <button type="button" class="sm-filter-chip" data-filter="pending">
                            Pending <span class="count">{{ $counts['pending'] }}</span>
                        </button>
                        <button type="button" class="sm-filter-chip" data-filter="approved">
                            Disetujui <span class="count">{{ $counts['approved'] }}</span>
                        </button>
                        <button type="button" class="sm-filter-chip" data-filter="paid">
                            Dibayar <span class="count">{{ $counts['paid'] }}</span>
                        </button>
                        @if($counts['rejected'] > 0)
                            <button type="button" class="sm-filter-chip" data-filter="rejected">
                                Ditolak <span class="count">{{ $counts['rejected'] }}</span>
                            </button>
                        @endif
                    </div>

                    <div class="sm-ref-table">
                        @foreach($commissions as $c)
                            @php
                                $cust = $c->reservation?->customer;
                                $custName = $cust?->name ?? 'Pelanggan';
                                $parts = explode(' ', trim($custName));
                                $first = $parts[0] ?? 'P';
                                $lastInitial = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) . '.' : '';
                                $displayName = trim($first . ' ' . $lastInitial);
                                $initial = strtoupper(substr($first, 0, 1));
                                $branchName = $c->reservation?->branch?->name ?? '-';
                                $resDate = $c->reservation?->date;
                                $statusCfg = \App\Models\RawCommission::statusOptions()[$c->status] ?? ['label' => $c->status];
                            @endphp
                            <div class="sm-ref-item" data-status="{{ $c->status }}">
                                <div class="sm-ref-avatar">{{ $initial }}</div>
                                <div class="sm-ref-main">
                                    <div class="sm-ref-name">{{ $displayName }}</div>
                                    <div class="sm-ref-meta">
                                        <span><i class="fa-solid fa-location-dot"></i>{{ $branchName }}</span>
                                        @if($resDate)
                                            <span><i class="fa-solid fa-calendar"></i>{{ \Carbon\Carbon::parse($resDate)->format('d M Y') }}</span>
                                        @endif
                                        @if($c->created_at)
                                            <span><i class="fa-solid fa-clock"></i>{{ $c->created_at->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="sm-ref-right">
                                    <div class="sm-ref-amount">Rp {{ number_format((float) $c->amount, 0, ',', '.') }}</div>
                                    <span class="sm-ref-badge {{ $c->status }}">{{ $statusCfg['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="sm-total-bar">
                        <span>Total komisi yang masuk:</span>
                        <strong>Rp {{ number_format($commissions->sum('amount'), 0, ',', '.') }}</strong>
                    </div>
                @endif
            </div>

        </div>
    </section>

    <script>
        function copyPromo(code, btn) {
            navigator.clipboard?.writeText(code);
            const original = btn.querySelector('.copy-icon');
            if (original) {
                const prev = original.className;
                original.className = 'fa-solid fa-check copy-icon';
                setTimeout(() => { original.className = prev; }, 1400);
            }
        }

        // Filter chips
        document.querySelectorAll('.sm-filter-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.sm-filter-chip').forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                const filter = chip.getAttribute('data-filter');
                document.querySelectorAll('.sm-ref-item').forEach(item => {
                    const status = item.getAttribute('data-status');
                    item.style.display = (filter === 'all' || status === filter) ? '' : 'none';
                });
            });
        });
    </script>

</body>
</html>
