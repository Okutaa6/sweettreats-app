{{--
    Finance dashboard — KPI cross-branch.
    Variables:
      $rupiah, $totalOpening, $totalSaldo, $totalCashIn, $totalCashOut, $netFlow
      $prevTotalSaldo, $saldoMoMDelta, $saldoMoMPct, $prevMonthName
      $pendingCount
      $distribution (collection of {name,pct}), $distributionRestCount, $distributionRestPct
      $idleCount, $branchesWithCashOut, $branches
--}}
@php
    $distribution           = $distribution ?? collect();
    $distributionRestCount  = $distributionRestCount ?? 0;
    $distributionRestPct    = $distributionRestPct ?? 0;
    $idleCount              = $idleCount ?? 0;
    $branchesWithCashOut    = $branchesWithCashOut ?? 0;
    $branchCount            = $branches->count();
    $totalOpening           = $totalOpening ?? 0;
    $netFlow                = $netFlow ?? 0;
    $prevTotalSaldo         = $prevTotalSaldo ?? 0;
    $saldoMoMDelta          = $saldoMoMDelta ?? 0;
    $saldoMoMPct            = $saldoMoMPct ?? null;
    $prevMonthName          = $prevMonthName ?? '-';

    // Palette warna untuk segmen distribusi (max 3 segmen tampil + rest)
    $palette = ['#22c55e', '#06b6d4', '#a855f7', '#f59e0b'];
@endphp
<div class="fpc-kpi-grid">
    {{-- KPI 1: Total Saldo — primary, hero, dengan distribution bar + breakdown --}}
    <div class="fpc-kpi-card fpc-kpi-card--hero">
        <div class="fpc-kpi-card__top">
            <span class="fpc-kpi-card__label">Total Saldo Semua Cabang</span>
            <span class="fpc-kpi-card__icon"><i class="fa fa-chart-bar"></i></span>
        </div>
        <div class="fpc-kpi-card__value">{{ $rupiah($totalSaldo) }}</div>

        {{-- MoM delta vs bulan sebelumnya --}}
        @if($saldoMoMPct !== null)
            @php
                $isUp   = $saldoMoMDelta >= 0;
                $arrow  = $isUp ? 'fa-arrow-up' : 'fa-arrow-down';
                $sign   = $isUp ? '+' : '−';
                $absDelta = abs($saldoMoMDelta);
                $absPct   = abs($saldoMoMPct);
            @endphp
            <div class="fpc-mom {{ $isUp ? 'fpc-mom--up' : 'fpc-mom--down' }}">
                <i class="fa {{ $arrow }}"></i>
                <strong>{{ $sign }}{{ $rupiah($absDelta) }}</strong>
                <span>({{ $sign }}{{ number_format($absPct, 1, ',', '.') }}%)</span>
                <span class="fpc-mom__label">dibandingkan saldo akhir {{ $prevMonthName }} ({{ $rupiah($prevTotalSaldo) }})</span>
            </div>
        @else
            <div class="fpc-kpi-card__sub">Akumulasi saldo akhir bulan berjalan</div>
        @endif

        {{-- Breakdown matriks: Opening → +Cash In → −Cash Out = Closing --}}
        <div class="fpc-flow-matrix">
            <div class="fpc-flow-matrix__cell">
                <span class="fpc-flow-matrix__label">Opening</span>
                <span class="fpc-flow-matrix__val">{{ $rupiah($totalOpening) }}</span>
            </div>
            <div class="fpc-flow-matrix__op">+</div>
            <div class="fpc-flow-matrix__cell">
                <span class="fpc-flow-matrix__label">Cash In</span>
                <span class="fpc-flow-matrix__val fpc-flow-matrix__val--in">{{ $rupiah($totalCashIn) }}</span>
            </div>
            <div class="fpc-flow-matrix__op">−</div>
            <div class="fpc-flow-matrix__cell">
                <span class="fpc-flow-matrix__label">Cash Out</span>
                <span class="fpc-flow-matrix__val fpc-flow-matrix__val--out">{{ $rupiah($totalCashOut) }}</span>
            </div>
            <div class="fpc-flow-matrix__op">=</div>
            <div class="fpc-flow-matrix__cell fpc-flow-matrix__cell--total">
                <span class="fpc-flow-matrix__label">Closing</span>
                <span class="fpc-flow-matrix__val">{{ $rupiah($totalSaldo) }}</span>
            </div>
        </div>

        @if($totalSaldo > 0)
            <div class="fpc-distribution">
                <div class="fpc-distribution__bar">
                    @foreach($distribution as $i => $d)
                        <span class="fpc-distribution__seg"
                              style="width: {{ $d->pct }}%; background: {{ $palette[$i] ?? '#94a3b8' }};"
                              title="{{ $d->name }} — {{ $d->pct }}%"></span>
                    @endforeach
                </div>
                <div class="fpc-distribution__legend">
                    @foreach($distribution as $i => $d)
                        <span class="fpc-distribution__chip">
                            <span class="fpc-distribution__dot" style="background: {{ $palette[$i] ?? '#94a3b8' }};"></span>
                            {{ $d->name }} <strong>{{ $d->pct }}%</strong>
                        </span>
                    @endforeach
                    @if($distributionRestCount > 0)
                        <span class="fpc-distribution__chip fpc-distribution__chip--muted">
                            <span class="fpc-distribution__dot" style="background: rgba(255,255,255,0.55);"></span>
                            {{ $distributionRestCount }} cabang lain <strong>{{ $distributionRestPct }}%</strong>
                        </span>
                    @endif
                    @if($idleCount > 0)
                        <span class="fpc-distribution__chip fpc-distribution__chip--muted">
                            <span class="fpc-distribution__dot" style="background: rgba(255,255,255,0.35);"></span>
                            {{ $idleCount }} cabang idle
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- KPI 2: Arus Kas Bulan Ini — Cash In vs Cash Out + Net Flow --}}
    @php
        $netIsPositive = $netFlow >= 0;
    @endphp
    <div class="fpc-kpi-card fpc-kpi-card--flow">
        <div class="fpc-kpi-card__top">
            <span class="fpc-kpi-card__label">Arus Kas Bulan Ini</span>
            <span class="fpc-kpi-card__icon fpc-kpi-card__icon--out"><i class="fa fa-exchange-alt"></i></span>
        </div>

        <div class="fpc-flow-row">
            <div class="fpc-flow-row__item">
                <span class="fpc-flow-row__label"><i class="fa fa-arrow-up"></i> Cash In</span>
                <span class="fpc-flow-row__val fpc-flow-row__val--in">{{ $rupiah($totalCashIn) }}</span>
            </div>
            <div class="fpc-flow-row__item">
                <span class="fpc-flow-row__label"><i class="fa fa-arrow-down"></i> Cash Out</span>
                <span class="fpc-flow-row__val fpc-flow-row__val--out">{{ $rupiah($totalCashOut) }}</span>
            </div>
        </div>

        <div class="fpc-net-flow {{ $netIsPositive ? 'fpc-net-flow--up' : 'fpc-net-flow--down' }}">
            <span class="fpc-net-flow__label">Net Flow</span>
            <span class="fpc-net-flow__val">
                {{ $netIsPositive ? '+' : '−' }}{{ $rupiah(abs($netFlow)) }}
            </span>
        </div>

        <div class="fpc-kpi-card__footer">
            <strong>{{ $branchesWithCashOut }} dari {{ $branchCount }}</strong> cabang ada pengeluaran
        </div>
    </div>

    {{-- KPI 3: Pengajuan Menunggu --}}
    <div class="fpc-kpi-card {{ $pendingCount > 0 ? 'fpc-kpi-card--alert' : 'fpc-kpi-card--neutral' }}">
        <div class="fpc-kpi-card__top">
            <span class="fpc-kpi-card__label">Pengajuan Menunggu</span>
            <span class="fpc-kpi-card__icon {{ $pendingCount > 0 ? 'fpc-kpi-card__icon--pending' : 'fpc-kpi-card__icon--ok' }}">
                <i class="fa {{ $pendingCount > 0 ? 'fa-hourglass-half' : 'fa-check' }}"></i>
            </span>
        </div>
        <div class="fpc-kpi-card__value {{ $pendingCount > 0 ? 'fpc-kpi-card__value--alert' : '' }}">
            {{ $pendingCount }}
        </div>
        <div class="fpc-kpi-card__sub">
            @if($pendingCount > 0)
                Perlu di-review oleh Finance
            @else
                Semua sudah di-review
            @endif
        </div>
        <div class="fpc-kpi-card__footer">
            @if($pendingCount > 0)
                <i class="fa fa-bell" style="color:#f59e0b;"></i> Klik tabel di bawah untuk review
            @else
                <i class="fa fa-check" style="color:#16a34a;"></i> Semua sudah di-review
            @endif
        </div>
    </div>
</div>

<style>
.fpc-kpi-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr;
    gap: 14px;
    margin: 14px 0 6px;
}
@media (max-width: 900px) {
    .fpc-kpi-grid { grid-template-columns: 1fr 1fr; }
    .fpc-kpi-card--hero { grid-column: span 2; }
}
@media (max-width: 600px) {
    .fpc-kpi-grid { grid-template-columns: 1fr; }
    .fpc-kpi-card--hero { grid-column: span 1; }
}

.fpc-kpi-card {
    position: relative;
    padding: 18px 20px;
    background: #fff;
    border: 1px solid var(--divider-soft);
    border-radius: 14px;
    transition: box-shadow .15s;
    display: flex;
    flex-direction: column;
}
.fpc-kpi-card:hover { box-shadow: 0 4px 12px rgba(70,21,0,0.06); }

.fpc-kpi-card__top {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 12px; margin-bottom: 12px;
}
.fpc-kpi-card__label {
    font-size: 11px; font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.5px;
}
.fpc-kpi-card__icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px;
    background: #f1f5f9; color: #64748b;
    border-radius: 10px;
    font-size: 14px;
    flex-shrink: 0;
}
.fpc-kpi-card__icon--out     { background: #fee2e2; color: #dc2626; }
.fpc-kpi-card__icon--pending { background: #fef3c7; color: #92400e; }
.fpc-kpi-card__icon--ok      { background: #dcfce7; color: #16a34a; }

.fpc-kpi-card__value {
    font-size: 26px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    margin-bottom: 4px;
}
.fpc-kpi-card__value--out   { color: #dc2626; }
.fpc-kpi-card__value--alert { color: #b45309; }
.fpc-kpi-card__sub {
    font-size: 12.5px;
    font-weight: 500;
    color: #64748b;
    line-height: 1.4;
}

/* Footer mini-stat at bottom of card */
.fpc-kpi-card__footer {
    margin-top: auto;
    padding-top: 14px;
    font-size: 12px; font-weight: 500;
    color: #64748b;
    display: flex; align-items: center; gap: 5px;
}
.fpc-kpi-card__footer strong { color: var(--brown); font-weight: 800; }
.fpc-kpi-card__footer i { font-size: 11px; }

/* Hero — primary metric, gradient brown */
.fpc-kpi-card--hero {
    background: linear-gradient(135deg, var(--brown) 0%, #5c1f00 100%);
    color: #fff;
    border: none;
    overflow: hidden;
}
.fpc-kpi-card--hero::after {
    content: ""; position: absolute;
    top: -40px; right: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    pointer-events: none;
}
.fpc-kpi-card--hero .fpc-kpi-card__label { color: rgba(255,255,255,0.82); }
.fpc-kpi-card--hero .fpc-kpi-card__value { color: #fff; font-size: 30px; }
.fpc-kpi-card--hero .fpc-kpi-card__sub   { color: rgba(255,255,255,0.88); font-weight: 500; }
.fpc-kpi-card--hero .fpc-kpi-card__icon  { background: rgba(255,255,255,0.15); color: #fff; }

/* Distribution bar + legend di hero card */
.fpc-distribution { margin-top: 14px; }
.fpc-distribution__bar {
    display: flex; gap: 2px;
    width: 100%; height: 6px;
    background: rgba(255,255,255,0.12);
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 10px;
}
.fpc-distribution__seg {
    display: block; height: 100%; border-radius: 999px;
    transition: width .3s ease;
}
.fpc-distribution__legend {
    display: flex; flex-wrap: wrap; gap: 8px 14px;
    font-size: 11.5px;
}
.fpc-distribution__chip {
    display: inline-flex; align-items: center; gap: 5px;
    color: rgba(255,255,255,0.92);
    font-weight: 500;
}
.fpc-distribution__chip strong { color: #fff; font-weight: 800; }
.fpc-distribution__chip--muted { color: rgba(255,255,255,0.55); }
.fpc-distribution__dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0;
}

/* MoM delta chip — di bawah angka total saldo hero */
.fpc-mom {
    display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap;
    font-size: 12.5px; font-weight: 600;
    margin: 4px 0 2px;
    color: rgba(255,255,255,0.92);
}
.fpc-mom strong { font-weight: 800; }
.fpc-mom__label { color: rgba(255,255,255,0.65); font-weight: 500; }
.fpc-mom--up   { color: #bbf7d0; }
.fpc-mom--down { color: #fecaca; }
.fpc-mom i { font-size: 11px; }

/* Flow matrix di hero: Opening + Cash In − Cash Out = Closing */
.fpc-flow-matrix {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
    align-items: end;
    gap: 4px 8px;
    margin: 14px 0 4px;
    padding: 12px 14px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 10px;
}
.fpc-flow-matrix__cell { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.fpc-flow-matrix__label {
    font-size: 10px; font-weight: 700; letter-spacing: 0.4px;
    text-transform: uppercase; color: rgba(255,255,255,0.6);
}
.fpc-flow-matrix__val {
    font-size: 13.5px; font-weight: 800; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.fpc-flow-matrix__val--in  { color: #86efac; }
.fpc-flow-matrix__val--out { color: #fca5a5; }
.fpc-flow-matrix__cell--total .fpc-flow-matrix__val {
    color: #fff; font-size: 15px;
}
.fpc-flow-matrix__op {
    font-size: 14px; font-weight: 800;
    color: rgba(255,255,255,0.4);
    padding-bottom: 1px;
}
@media (max-width: 700px) {
    .fpc-flow-matrix { grid-template-columns: 1fr 1fr; gap: 10px; padding: 12px; }
    .fpc-flow-matrix__op { display: none; }
}

/* Arus Kas card: Cash In / Cash Out side-by-side + Net Flow strip */
.fpc-flow-row {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 12px; margin-bottom: 12px;
}
.fpc-flow-row__item {
    display: flex; flex-direction: column; gap: 3px;
}
.fpc-flow-row__label {
    font-size: 10.5px; font-weight: 700; letter-spacing: 0.3px;
    text-transform: uppercase; color: var(--text-muted);
    display: inline-flex; align-items: center; gap: 4px;
}
.fpc-flow-row__label i { font-size: 9px; }
.fpc-flow-row__val {
    font-size: 18px; font-weight: 800; line-height: 1.1; color: #0f172a;
}
.fpc-flow-row__val--in  { color: #16a34a; }
.fpc-flow-row__val--out { color: #dc2626; }

.fpc-net-flow {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 12px; margin-bottom: 8px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid var(--divider-soft);
}
.fpc-net-flow__label {
    font-size: 11px; font-weight: 800; letter-spacing: 0.4px;
    text-transform: uppercase; color: #475569;
}
.fpc-net-flow__val { font-size: 16px; font-weight: 800; }
.fpc-net-flow--up   { background: #f0fdf4; border-color: #bbf7d0; }
.fpc-net-flow--up   .fpc-net-flow__val { color: #15803d; }
.fpc-net-flow--down { background: #fef2f2; border-color: #fecaca; }
.fpc-net-flow--down .fpc-net-flow__val { color: #b91c1c; }

/* Alert state — soft amber background when there are pending */
.fpc-kpi-card--alert {
    background: linear-gradient(180deg, #fffbeb 0%, #ffffff 60%);
    border-color: #fde68a;
}
.fpc-kpi-card--alert::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
    border-radius: 14px 14px 0 0;
}
</style>
