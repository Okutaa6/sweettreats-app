{{--
    Finance dashboard — grid card per cabang.
    Variables:
      $rupiah, $branchSummaries (array of obj{id,name,opening,cash_in,cash_out,closing,last_activity,period_closed})
      $year, $month (opsional — untuk link "Riwayat"; fallback ke bulan berjalan)
--}}
@php
    $gridYear  = $year  ?? (int) date('Y');
    $gridMonth = $month ?? (int) date('n');
@endphp
<div class="fpc-section">
    <div class="fpc-section__head">
        <h5 class="fpc-section__title">
            <i class="fa fa-building"></i> Saldo per Cabang
        </h5>
        {{-- Dulu di sini ada teks instruksi "Klik kartu untuk…". Diganti chevron
             di tiap kartu (pola-interaksi P1): kalau butuh kalimat instruksi,
             tanda visualnya gagal. --}}
    </div>

    <div class="fpc-branch-grid">
        @foreach($branchSummaries as $b)
            @php
                // Hitung "health" cabang:
                //   - idle    → tidak ada aktivitas bulan ini
                //   - low     → saldo < 25% dari (opening atau 500k)
                //   - healthy → saldo aman
                $hasActivity = ($b->cash_in + $b->cash_out) > 0 || $b->opening > 0;
                $threshold   = max($b->opening, 500000) * 0.25;
                $isLow       = $hasActivity && $b->closing < $threshold;
                $isIdle      = ! $hasActivity;
                $status      = $isIdle ? 'idle' : ($isLow ? 'low' : 'healthy');
                $statusLabel = $isIdle ? 'Idle' : ($isLow ? 'Saldo Rendah' : 'Normal');
            @endphp
            <div class="fpc-branch-card fpc-branch-card--{{ $status }} {{ $b->period_closed ? 'is-closed' : '' }}">

                {{-- Stretched link: klik area kartu → halaman Riwayat transaksi cabang. --}}
                <a href="{{ route('finance.pettycash.branch-history', ['branchId' => $b->id, 'year' => $gridYear, 'month' => $gridMonth]) }}"
                   class="fpc-branch-card__stretch"
                   aria-label="Lihat riwayat transaksi {{ $b->name }}"
                   title="Lihat riwayat transaksi {{ $b->name }}"></a>

                <div class="fpc-branch-card__head">
                    <div class="fpc-branch-card__name-wrap">
                        <span class="fpc-branch-card__dot"></span>
                        <span class="fpc-branch-card__name">{{ $b->name }}</span>
                        {{-- chevron = penanda "kartu ini bisa dibuka" (P1) --}}
                        <i class="fa fa-chevron-right fpc-branch-card__chev" aria-hidden="true"></i>
                    </div>
                    <span class="fpc-branch-card__pill fpc-branch-card__pill--{{ $status }}">{{ $statusLabel }}</span>
                </div>

                <div class="fpc-branch-card__balance">{{ $rupiah($b->closing) }}</div>
                <div class="fpc-branch-card__balance-sub">Saldo akhir (closing)</div>

                {{-- Matriks finance: Opening → +Cash In → −Cash Out --}}
                @php
                    $netCabang = $b->cash_in - $b->cash_out;
                    $netPos    = $netCabang >= 0;
                @endphp
                <div class="fpc-branch-card__matrix">
                    <div class="fpc-branch-card__row">
                        <span class="fpc-branch-card__row-label">Opening</span>
                        <span class="fpc-branch-card__row-val">{{ $rupiah($b->opening) }}</span>
                    </div>
                    <div class="fpc-branch-card__row">
                        <span class="fpc-branch-card__row-label">
                            <i class="fa fa-plus" style="color:#16a34a;"></i> Cash In
                        </span>
                        <span class="fpc-branch-card__row-val fpc-branch-card__row-val--in">
                            {{ $rupiah($b->cash_in) }}
                        </span>
                    </div>
                    <div class="fpc-branch-card__row">
                        <span class="fpc-branch-card__row-label">
                            <i class="fa fa-minus" style="color:#dc2626;"></i> Cash Out
                        </span>
                        <span class="fpc-branch-card__row-val fpc-branch-card__row-val--out">
                            {{ $rupiah($b->cash_out) }}
                        </span>
                    </div>
                    <div class="fpc-branch-card__row fpc-branch-card__row--net">
                        <span class="fpc-branch-card__row-label">Net Flow</span>
                        <span class="fpc-branch-card__row-val {{ $netPos ? 'fpc-branch-card__row-val--in' : 'fpc-branch-card__row-val--out' }}">
                            {{ $netPos ? '+' : '−' }}{{ $rupiah(abs($netCabang)) }}
                        </span>
                    </div>
                </div>

                <div class="fpc-branch-card__footer">
                    <span class="fpc-branch-card__footer-info">
                        @if($b->last_activity)
                            <i class="fa fa-history"></i>
                            Aktivitas terakhir {{ $b->last_activity->translatedFormat('d M, H:i') }}
                        @else
                            <i class="fa fa-minus-circle"></i>
                            Belum ada aktivitas bulan ini
                        @endif
                    </span>
                    <span class="fpc-branch-card__footer-cta">Lihat riwayat <i class="fa fa-arrow-right"></i></span>
                </div>

                @if($b->period_closed)
                    <span class="fpc-branch-card__locked"><i class="fa fa-lock"></i> Periode Ditutup</span>
                @endif
            </div>
        @endforeach
    </div>
</div>

<style>
.fpc-branch-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
}
/* Chevron kartu (pola-interaksi P1): warna aksen zona, redup saat diam,
   pekat + maju saat kartu di-hover. */
.fpc-branch-card__chev { font-size: 12px; color: var(--brown); opacity: .8; margin-left: 4px; transition: transform .15s ease, opacity .15s ease; }
.fpc-branch-card:hover .fpc-branch-card__chev { opacity: 1; transform: translateX(2px); }

.fpc-branch-card {
    position: relative;
    display: block;
    padding: 16px 18px 14px;
    background: #fff;
    border: 1px solid var(--divider-soft);
    border-radius: 14px;
    text-decoration: none;
    color: inherit;
    cursor: pointer;
    transition: box-shadow .2s, transform .15s, border-color .15s;
    overflow: hidden;
}
/* Stretched link menutupi seluruh kartu (di bawah elemen interaktif lain). */
.fpc-branch-card__stretch {
    position: absolute;
    inset: 0;
    z-index: 1;
    border-radius: 14px;
    text-decoration: none;
}
.fpc-branch-card__stretch:focus-visible {
    outline: 2px solid var(--brown);
    outline-offset: -3px;
}
.fpc-branch-card::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: #cbd5e1;
    transition: background .15s;
}
.fpc-branch-card:hover {
    box-shadow: 0 6px 16px rgba(70,21,0,0.08);
    border-color: #d6c4a8;
    transform: translateY(-2px);
    text-decoration: none;
    color: inherit;
}
.fpc-branch-card--healthy::before { background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%); }
.fpc-branch-card--low::before     { background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); }
.fpc-branch-card--idle::before    { background: #cbd5e1; }
.fpc-branch-card.is-closed { opacity: 0.75; }
.fpc-branch-card.is-closed::before { background: #94a3b8; }

.fpc-branch-card__head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
}
.fpc-branch-card__name-wrap { display: inline-flex; align-items: center; gap: 8px; min-width: 0; }
.fpc-branch-card__dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #cbd5e1; flex-shrink: 0;
}
.fpc-branch-card--healthy .fpc-branch-card__dot { background: #16a34a; box-shadow: 0 0 0 3px #dcfce7; }
.fpc-branch-card--low     .fpc-branch-card__dot { background: #f59e0b; box-shadow: 0 0 0 3px #fef3c7; }
.fpc-branch-card__name {
    font-weight: 700; color: var(--brown); font-size: 14px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

.fpc-branch-card__pill {
    font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 999px;
    text-transform: uppercase; letter-spacing: 0.4px; flex-shrink: 0;
}
.fpc-branch-card__pill--healthy { background: #dcfce7; color: #15803d; }
.fpc-branch-card__pill--low     { background: #fef3c7; color: #92400e; }
.fpc-branch-card__pill--idle    { background: #f1f5f9; color: #475569; }

.fpc-branch-card__balance {
    font-size: 22px; font-weight: 800; color: #0f172a;
    line-height: 1.1; margin-bottom: 2px;
}
.fpc-branch-card__balance-sub { font-size: 10.5px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; margin-bottom: 12px; }

/* Matriks finance per cabang: Opening / +In / −Out / Net Flow */
.fpc-branch-card__matrix {
    display: flex; flex-direction: column;
    padding: 10px 0 8px;
    margin-bottom: 4px;
    border-top: 1px dashed var(--divider-soft);
    border-bottom: 1px dashed var(--divider-soft);
}
.fpc-branch-card__row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 3px 0;
    font-size: 12.5px;
}
.fpc-branch-card__row-label {
    color: var(--text-muted); font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}
.fpc-branch-card__row-label i { font-size: 9px; }
.fpc-branch-card__row-val { font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; }
.fpc-branch-card__row-val--in  { color: #16a34a; }
.fpc-branch-card__row-val--out { color: #dc2626; }
.fpc-branch-card__row--net {
    margin-top: 4px; padding-top: 6px;
    border-top: 1px dotted var(--divider-soft);
    font-weight: 700;
}
.fpc-branch-card__row--net .fpc-branch-card__row-label { color: #0f172a; font-weight: 800; }

.fpc-branch-card__footer {
    font-size: 11.5px; color: #64748b; font-weight: 500;
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    margin-top: 8px;
}
.fpc-branch-card__footer i { font-size: 10px; }
.fpc-branch-card__footer-info { display: inline-flex; align-items: center; gap: 5px; min-width: 0; }

/* CTA hint di footer — seluruh kartu adalah link (stretched) ke Riwayat. */
.fpc-branch-card__footer-cta {
    display: inline-flex; align-items: center; gap: 5px;
    flex-shrink: 0;
    color: var(--brown); font-size: 11px; font-weight: 700;
    white-space: nowrap; opacity: .8;
    transition: gap .15s, opacity .15s;
}
.fpc-branch-card__footer-cta i { font-size: 9px; }
.fpc-branch-card:hover .fpc-branch-card__footer-cta { opacity: 1; gap: 8px; }

.fpc-branch-card__locked {
    position: absolute; top: 12px; right: 12px;
    font-size: 9.5px; font-weight: 700; padding: 3px 8px; border-radius: 999px;
    background: #f1f5f9; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;
}
.fpc-branch-card__locked i { margin-right: 3px; }
</style>
