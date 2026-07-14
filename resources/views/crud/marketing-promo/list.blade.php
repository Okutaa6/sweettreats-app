@extends('layouts.app')

@section('content')
@php
    $totalAll = array_sum($summary ?? []);
@endphp

<style>
/* ── Stat tiles (referensi halaman Dokter) ── */
.mp-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px; }
.mp-stat { display: flex; align-items: center; gap: 12px; background: #fff;
    border: 1px solid #EFE0CE; border-radius: 14px; padding: 13px 16px;
    box-shadow: 0 1px 2px rgba(70, 21, 0, .04), 0 8px 20px rgba(70, 21, 0, .045); }
.mp-stat__icon { width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 16px; }
.mp-stat__body { display: flex; flex-direction: column; line-height: 1.05; min-width: 0; }
.mp-stat__num { font-size: 23px; font-weight: 800; color: #3B1500; font-variant-numeric: tabular-nums; }
.mp-stat__label { font-size: 10.5px; font-weight: 700; letter-spacing: .5px;
    text-transform: uppercase; color: #9A7256; margin-top: 4px; }
/* Tile "Total" = hero, ter-fill brown gradient */
.mp-stat--total { background: linear-gradient(135deg, #6B2400, #461500); border-color: transparent;
    box-shadow: 0 2px 6px rgba(70, 21, 0, .18), 0 10px 24px rgba(70, 21, 0, .14); }
.mp-stat--total .mp-stat__icon { background: rgba(255, 255, 255, .16); color: #FFE6CF; }
.mp-stat--total .mp-stat__num { color: #fff; }
.mp-stat--total .mp-stat__label { color: #EBC9AC; }
@media (max-width: 900px) { .mp-stats { grid-template-columns: 1fr 1fr; } }
@media (max-width: 520px) { .mp-stats { grid-template-columns: 1fr; } }

/* ── Empty state (kontras jelas, tidak abu pudar) ── */
.mp-empty { text-align: center; color: #5A3A22; font-size: 14px; }
.mp-empty > i { display: block; font-size: 30px; color: #CBB49E; margin-bottom: 12px; }
.mp-empty__link { display: inline-block; margin-top: 10px; color: #8F4525;
    font-weight: 700; text-decoration: none; }
.mp-empty__link:hover { color: #572006; text-decoration: underline; }

/* ── Filter promo — panel rapih (cream/brown, selaras segmented control jadwal) ── */
.mpf-panel .st-card__body { padding: 16px 18px; }

/* Baris quick-periode + aksi (segmented control kiri, toggle/tambah kanan) */
.mpf-quick { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.mpf-quick__left { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.mpf-quick__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.mpf-quick__label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px;
    font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #8F4525; }
.mpf-quick__label i { color: #d99a5a; }

/* Segmented control — satu track krem, segmen aktif ter-fill */
.mpf-seg { display: inline-flex; flex-wrap: wrap; gap: 3px; padding: 4px;
    background: #f4e7d6; border: 1.5px solid #e6c19a; border-radius: 999px;
    box-shadow: inset 0 1px 2px rgba(70, 21, 0, .08); }
.mpf-seg__btn { font-size: .78rem; font-weight: 700; color: #8F4525; background: transparent;
    border: none; border-radius: 999px; padding: 6px 16px; cursor: pointer; text-decoration: none;
    transition: background .15s, color .15s, box-shadow .15s; }
.mpf-seg__btn:not(.is-active):hover { color: #572006; background: rgba(255, 255, 255, .65); }
.mpf-seg__btn.is-active { background: linear-gradient(135deg, #8F4525, #572006); color: #fff;
    box-shadow: 0 2px 8px rgba(87, 32, 6, .28); }

/* Divider halus */
.mpf-divider { height: 1px; background: #ecd9c2; margin: 15px 0; }

/* Grid filter — responsif, search paling lebar */
.mpf-form { display: grid; gap: 12px; align-items: end;
    grid-template-columns: minmax(200px, 2.2fr) repeat(4, minmax(130px, 1fr)) auto; }
.mpf-field label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: #8F4525; margin-bottom: 5px; }
.mpf-form .form-control { height: 40px; border: 1.5px solid #e6c19a; border-radius: 10px;
    font-size: 13px; background: #fff; box-shadow: none; }
.mpf-form .form-control:focus { border-color: #d99a5a; box-shadow: 0 0 0 3px rgba(217, 154, 90, .18); }

/* Input dengan ikon cari */
.mpf-input-icon { position: relative; }
.mpf-input-icon i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #c79a6a; font-size: 12px; pointer-events: none; }
.mpf-input-icon .form-control { padding-left: 32px; }

/* Tombol aksi */
.mpf-actions { display: flex; gap: 8px; }
.mpf-btn { height: 40px; display: inline-flex; align-items: center; gap: 6px;
    padding: 0 16px; border-radius: 10px; font-size: 13px; font-weight: 600; white-space: nowrap; }
.mpf-btn--reset { background: #fff; }

@media (max-width: 991px) {
    .mpf-form { grid-template-columns: repeat(2, 1fr); }
    .mpf-field--search { grid-column: 1 / -1; }
    .mpf-actions { grid-column: 1 / -1; }
    .mpf-btn { flex: 1; justify-content: center; }
}
@media (max-width: 575px) {
    .mpf-form { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Promo</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── Stat tiles (referensi: halaman Dokter) ─── --}}
    @php
        // Ikon + warna per warna-status; nama ikon FA5 (bukan FA6).
        $statColor = [
            'warning' => ['icon' => 'fa-pencil-alt',   'fg' => '#B5701A', 'bg' => '#FBEFD9'],
            'success' => ['icon' => 'fa-bolt',         'fg' => '#2F8E55', 'bg' => '#E3F4E8'],
            'info'    => ['icon' => 'fa-info-circle',  'fg' => '#2563EB', 'bg' => '#E5EEFF'],
            'neutral' => ['icon' => 'fa-check-circle', 'fg' => '#8A6A50', 'bg' => '#F1E4D3'],
            'dark'    => ['icon' => 'fa-check-circle', 'fg' => '#8A6A50', 'bg' => '#F1E4D3'],
            'danger'  => ['icon' => 'fa-ban',          'fg' => '#C0392B', 'bg' => '#FCE8E6'],
        ];
    @endphp
    <div class="mp-stats">
        <div class="mp-stat mp-stat--total">
            <div class="mp-stat__icon"><i class="fa fa-tags"></i></div>
            <div class="mp-stat__body">
                <span class="mp-stat__num">{{ $totalAll }}</span>
                <span class="mp-stat__label">Total Promo</span>
            </div>
        </div>
        @foreach ($statuses as $key => $opt)
            @php $m = $statColor[$opt['color']] ?? $statColor['neutral']; @endphp
            <div class="mp-stat">
                <div class="mp-stat__icon" style="background: {{ $m['bg'] }}; color: {{ $m['fg'] }};">
                    <i class="fa {{ $m['icon'] }}"></i>
                </div>
                <div class="mp-stat__body">
                    <span class="mp-stat__num">{{ $summary[$key] ?? 0 }}</span>
                    <span class="mp-stat__label">{{ $opt['label'] }}</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-md-12">
            @php
                $chips = [
                    ''           => 'Semua',
                    'today'      => 'Hari Ini',
                    'this_month' => 'Bulan Ini',
                    'next_month' => 'Bulan Depan',
                ];
                $qs = function ($overrides = []) {
                    return http_build_query(array_filter(array_merge(request()->all(), $overrides), fn($v) => $v !== null && $v !== ''));
                };
                $hasActiveFilter = $filterSearch || $filterCampaign || $filterStatus || $filterBranch || $filterAktifPada;
            @endphp
            <div class="st-card mb-3 mpf-panel">
                <div class="st-card__body">
                    {{-- ─── Quick periode (segmented control) + aksi tampilan/tambah ─── --}}
                    <div class="mpf-quick">
                        <div class="mpf-quick__left">
                            <span class="mpf-quick__label"><i class="fa fa-bolt"></i> Lihat promo</span>
                            <div class="mpf-seg">
                                @foreach ($chips as $key => $label)
                                    @php $isActive = ($filterPeriode ?? '') === $key && !$filterAktifPada; @endphp
                                    <a href="{{ route('marketingpromo') }}?{{ $qs(['periode' => $key, 'aktif_pada' => null]) }}"
                                       class="mpf-seg__btn {{ $isActive ? 'is-active' : '' }}">{{ $label }}</a>
                                @endforeach
                            </div>
                        </div>
                        <div class="mpf-quick__actions">
                            <div class="st-view-toggle">
                                <a href="{{ request()->fullUrlWithQuery(['view' => 'card']) }}"
                                   class="st-view-toggle__btn {{ $viewMode === 'card' ? 'is-active' : '' }}" title="Tampilan Card">
                                    <i class="fa fa-th-large"></i> Card
                                </a>
                                <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}"
                                   class="st-view-toggle__btn {{ $viewMode === 'list' ? 'is-active' : '' }}" title="Tampilan List">
                                    <i class="fa fa-list"></i> List
                                </a>
                            </div>
                            <a href="{{ route('marketingpromo.new') }}" class="st-btn-brand">
                                <i class="fa fa-plus"></i> Tambah Promo
                            </a>
                        </div>
                    </div>

                    <div class="mpf-divider"></div>

                    {{-- ─── Filter detail ─── --}}
                    <form method="GET" class="mpf-form">
                        <div class="mpf-field mpf-field--search">
                            <label>Cari</label>
                            <div class="mpf-input-icon">
                                <i class="fa fa-search"></i>
                                <input type="text" name="search" class="form-control" placeholder="Nama promo..." value="{{ $filterSearch }}">
                            </div>
                        </div>
                        <div class="mpf-field">
                            <label>Campaign</label>
                            <select name="campaign_type" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($campaigns as $key => $label)
                                    <option value="{{ $key }}" {{ $filterCampaign == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mpf-field">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($statuses as $key => $opt)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mpf-field">
                            <label>Cabang</label>
                            <select name="branch_id" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mpf-field">
                            <label>Aktif pada tgl</label>
                            <input type="date" name="aktif_pada" class="form-control" value="{{ $filterAktifPada }}">
                        </div>
                        <div class="mpf-actions">
                            <button type="submit" class="st-btn-brand mpf-btn"><i class="fa fa-filter"></i> Filter</button>
                            @if ($hasActiveFilter || $filterPeriode)
                                <a href="{{ route('marketingpromo') }}" class="st-btn-outline-brown mpf-btn mpf-btn--reset" title="Reset semua filter"><i class="fa fa-times"></i> Reset</a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            @if ($viewMode === 'list')
            <div class="st-card">
                <div class="st-card__body">
                    <div class="st-table-wrap--scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Nama Promo</th>
                                    <th>Campaign</th>
                                    <th>Periode</th>
                                    <th>Cabang</th>
                                    <th class="text-right">Harga Promo</th>
                                    <th class="text-right">Margin</th>
                                    <th>Status</th>
                                    <th width="60">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promos as $key => $row)
                                    @php
                                        $statusOpt = $statuses[$row->status] ?? ['label' => $row->status, 'color' => 'neutral'];
                                        $isWithinPeriode = $row->isWithinPeriode();
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $promos->firstItem() + $key }}</td>
                                        <td class="st-primary">
                                            <a href="{{ route('marketingpromo.edit', $row->id) }}" style="color: inherit; text-decoration: none;">
                                                {{ $row->nama_promo }}
                                            </a>
                                        </td>
                                        <td>{{ $campaigns[$row->campaign_type] ?? $row->campaign_type }}</td>
                                        <td>
                                            @if ($row->periode_start || $row->periode_end)
                                                <span class="st-date">
                                                    {{ $row->periode_start?->format('d M Y') ?? '...' }} – {{ $row->periode_end?->format('d M Y') ?? '...' }}
                                                </span>
                                                @if (!$isWithinPeriode)
                                                    <br><span class="st-pill st-pill--neutral mt-1" title="Tidak dalam periode aktif saat ini">Di luar periode</span>
                                                @endif
                                            @else
                                                <span class="st-muted">Tanpa periode</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($row->branches->count() === 0)
                                                <span class="st-pill st-pill--neutral">Semua Cabang</span>
                                            @else
                                                @foreach ($row->branches as $b)
                                                    <span class="st-pill st-pill--info st-pill--plain">{{ $b->name }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="text-right st-tnum">
                                            @if ($row->harga_promo)
                                                Rp {{ number_format($row->harga_promo, 0, ',', '.') }}
                                                @if ($row->harga_normal && $row->harga_normal > $row->harga_promo)
                                                    <br><small class="text-muted text-decoration-line-through">Rp {{ number_format($row->harga_normal, 0, ',', '.') }}</small>
                                                @endif
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right st-tnum">
                                            @if ($row->margin_pct !== null)
                                                <span style="color: {{ $row->margin_rp < 0 ? '#c1272d' : 'inherit' }};">
                                                    {{ number_format($row->margin_pct, 2) }}%
                                                </span>
                                                <br><small class="text-muted">Rp {{ number_format($row->margin_rp, 0, ',', '.') }}</small>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <select class="st-status-select" data-id="{{ $row->id }}"
                                                    data-original="{{ $row->status }}" data-status="{{ $row->status }}"
                                                    onchange="changePromoStatus(this)">
                                                @foreach ($statuses as $key => $opt)
                                                    <option value="{{ $key }}" {{ $row->status === $key ? 'selected' : '' }}>
                                                        {{ $opt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if (!$row->is_active)
                                                <br><span class="st-pill st-pill--neutral mt-1">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('marketingpromo.edit', $row->id) }}" class="st-btn-outline-brown btn-sm" title="Edit">
                                                <i class="fa fa-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($promos->isEmpty())
                                    <tr><td colspan="9" class="py-5">
                                        <div class="mp-empty">
                                            <i class="fa fa-tag"></i>
                                            <div>Tidak ada promo yang cocok dengan filter ini.</div>
                                            <a href="{{ route('marketingpromo') }}" class="mp-empty__link"><i class="fa fa-undo"></i> Reset filter</a>
                                        </div>
                                    </td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    {{ $promos->links() }}
                </div>
            </div>
            @else
            {{-- ═══════════════ TAMPILAN CARD (dikelompokkan per periode) ═══════════════ --}}
            @forelse ($grouped as $bucketKey => $group)
                <div class="promo-section">
                    <div class="promo-section__head" style="--accent: {{ $group['meta']['accent'] }};">
                        <i class="fa {{ $group['meta']['icon'] }}"></i>
                        <span class="promo-section__title">{{ $group['meta']['label'] }}</span>
                        <span class="promo-section__count">{{ $group['items']->count() }}</span>
                    </div>

                    <div class="promo-grid">
                        @foreach ($group['items'] as $row)
                            @php
                                $diskon    = $row->diskonPct();
                                $withinNow = $row->isWithinPeriode();
                                $cc        = $row->campaignColor();
                                $showBadge = (!$row->is_active) || (($row->periode_start || $row->periode_end) && !$withinNow);
                            @endphp
                            <div class="pc {{ !$row->is_active ? 'is-inactive' : '' }}"
                                 style="--cc: {{ $cc['color'] }};"
                                 data-href="{{ route('marketingpromo.edit', $row->id) }}"
                                 onclick="goEditPromo(event, this)" title="Klik untuk edit promo">

                                {{-- Zona 1 — identitas --}}
                                <div class="pc__head">
                                    <div class="pc__row">
                                        <span class="pc__campaign">{{ $campaigns[$row->campaign_type] ?? $row->campaign_type }}</span>
                                        <select class="st-status-select" data-id="{{ $row->id }}"
                                                data-original="{{ $row->status }}" data-status="{{ $row->status }}"
                                                onclick="event.stopPropagation()" onchange="changePromoStatus(this)">
                                            @foreach ($statuses as $sKey => $opt)
                                                <option value="{{ $sKey }}" {{ $row->status === $sKey ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <h5 class="pc__name">{{ $row->nama_promo }}</h5>
                                    <div class="pc__period">
                                        <i class="fa fa-calendar"></i>
                                        @if ($row->periode_start || $row->periode_end)
                                            {{ $row->periode_start?->format('d M Y') ?? '...' }} – {{ $row->periode_end?->format('d M Y') ?? '...' }}
                                        @else
                                            Tanpa periode
                                        @endif
                                    </div>
                                    @if ($showBadge)
                                        <div class="pc__badges">
                                            @if (!$row->is_active)
                                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                                            @endif
                                            @if (($row->periode_start || $row->periode_end) && !$withinNow)
                                                <span class="st-pill st-pill--neutral" title="Tidak dalam periode aktif saat ini">Di luar periode</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                {{-- Zona 2 — harga (focal point) --}}
                                <div class="pc__price">
                                    @if ($row->harga_promo)
                                        <div class="pc__price-top">
                                            <span class="pc__now">Rp {{ number_format($row->harga_promo, 0, ',', '.') }}</span>
                                            @if ($diskon)<span class="pc__save">Hemat {{ $diskon }}%</span>@endif
                                        </div>
                                        @if (($row->harga_normal && $row->harga_normal > $row->harga_promo) || $row->voucher_tambahan_pasien)
                                            <div class="pc__price-sub">
                                                @if ($row->harga_normal && $row->harga_normal > $row->harga_promo)
                                                    <span class="pc__old">Rp {{ number_format($row->harga_normal, 0, ',', '.') }}</span>
                                                @endif
                                                @if ($row->voucher_tambahan_pasien)
                                                    <span class="pc__voucher"><i class="fa fa-gift"></i> Voucher Rp {{ number_format($row->voucher_tambahan_pasien, 0, ',', '.') }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    @else
                                        <div class="pc__price-top"><span class="pc__now pc__now--empty">Harga belum diset</span></div>
                                    @endif
                                </div>

                                {{-- Zona 3 — detail + footer (redup) --}}
                                <div class="pc__body">
                                    @if (trim((string) $row->detail_promo) !== '')
                                        <div class="pc__text"><b>Detail</b><p>{{ $row->detail_promo }}</p></div>
                                    @endif
                                    @if (trim((string) $row->syarat_ketentuan) !== '')
                                        <div class="pc__text pc__text--sk"><b>Syarat &amp; Ketentuan</b><p>{{ $row->syarat_ketentuan }}</p></div>
                                    @endif

                                    <div class="pc__foot">
                                        @if (!empty($showMargin) && $row->margin_pct !== null)
                                            <span class="pc__margin {{ $row->margin_rp < 0 ? 'pc__margin--loss' : '' }}">Margin
                                                <b>{{ number_format($row->margin_pct, 2) }}%</b>
                                                · Rp {{ number_format($row->margin_rp, 0, ',', '.') }}</span>
                                        @else
                                            <span></span>
                                        @endif
                                        <div class="pc__branches">
                                            @if ($row->branches->count() === 0)
                                                <span class="chip">Semua Cabang</span>
                                            @else
                                                @foreach ($row->branches as $b)
                                                    <span class="chip">{{ $b->name }}</span>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="st-card"><div class="st-card__body py-5">
                    <div class="mp-empty">
                        <i class="fa fa-tag"></i>
                        <div>Tidak ada promo yang cocok dengan filter ini.</div>
                        <a href="{{ route('marketingpromo') }}" class="mp-empty__link"><i class="fa fa-undo"></i> Reset filter</a>
                    </div>
                </div></div>
            @endforelse
            @endif
        </div>
    </div>
</div>

<style>
    .st-status-select {
        border: 1px solid transparent;
        padding: 4px 26px 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23461500' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        transition: opacity .15s, transform .15s;
    }
    .st-status-select[data-status="aktif"]    { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .st-status-select[data-status="planning"] { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .st-status-select[data-status="selesai"]  { background-color: #e9ecef; color: #495057; border-color: #ced4da; }
    .st-status-select:hover    { opacity: .85; }
    .st-status-select:disabled { opacity: .5; cursor: wait; }

    /* ─── Toggle List ⇄ Card ─── */
    .st-view-toggle { display: inline-flex; border: 1px solid #e0d4c8; border-radius: 10px; overflow: hidden; background: #fff; }
    .st-view-toggle__btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px;
        font-size: 12.5px; font-weight: 600; color: #8a7a6e; text-decoration: none; transition: background .15s, color .15s;
    }
    .st-view-toggle__btn + .st-view-toggle__btn { border-left: 1px solid #e0d4c8; }
    .st-view-toggle__btn:hover { background: #faf6f2; color: #461500; }
    .st-view-toggle__btn.is-active { background: #461500; color: #fff; }

    /* ─── Section per periode ─── */
    .promo-section { margin-bottom: 26px; }
    .promo-section__head {
        display: flex; align-items: center; gap: 9px; margin-bottom: 12px;
        padding-bottom: 8px; border-bottom: 2px solid var(--accent, #461500); color: var(--accent, #461500);
    }
    .promo-section__head i { font-size: 15px; }
    .promo-section__title { font-size: 14px; font-weight: 700; letter-spacing: .3px; }
    .promo-section__count {
        margin-left: 2px; background: var(--accent, #461500); color: #fff; font-size: 11px; font-weight: 700;
        min-width: 22px; height: 22px; padding: 0 7px; border-radius: 11px; display: inline-flex; align-items: center; justify-content: center;
    }

    /* ─── Grid kartu ─── */
    .promo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }

    .pc {
        position: relative; background: #fff; border: 1px solid #ece1d8; border-left: 5px solid var(--cc, #461500);
        border-radius: 14px; overflow: hidden; cursor: pointer; display: flex; flex-direction: column;
        transition: box-shadow .15s, transform .15s;
    }
    .pc:hover { box-shadow: 0 10px 24px rgba(70,21,0,.14); transform: translateY(-2px); }
    .pc.is-inactive { opacity: .72; }

    /* Zona 1 — identitas */
    .pc__head { padding: 14px 16px 12px; }
    .pc__row { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
    .pc__campaign { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: var(--cc, #461500); }
    .pc__name { margin: 6px 0 0; font-size: 18px; font-weight: 800; line-height: 1.22; color: #231a14; letter-spacing: -.2px; }
    .pc:hover .pc__name { color: var(--cc, #461500); }
    .pc__period { margin-top: 6px; font-size: 11.5px; color: #9a8a7e; }
    .pc__period i { margin-right: 5px; color: #b09a8c; }
    .pc__badges { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 9px; }

    /* Status dikalemin KHUSUS di card (ghost/outline) — kontrol, bukan konten.
       Di tampilan List/tabel tetap pakai pill isi penuh (CSS .st-status-select global). */
    .pc .st-status-select { background-color: #fff; border-color: #e6dcd2; color: #8a7a6e; font-size: 10.5px; padding: 3px 22px 3px 9px; font-weight: 600; }
    .pc .st-status-select[data-status="aktif"]    { background-color: #fff; color: #1e7e34; border-color: #cfe8d6; }
    .pc .st-status-select[data-status="planning"] { background-color: #fff; color: #9a7b1f; border-color: #efe2b8; }
    .pc .st-status-select[data-status="selesai"]  { background-color: #fff; color: #6c757d; border-color: #dfe3e6; }

    /* Zona 2 — harga (focal point) */
    .pc__price { padding: 12px 16px; background: linear-gradient(0deg, #fbf7f3, #fdfbf9); border-top: 1px solid #f1e8e0; border-bottom: 1px solid #f1e8e0; }
    .pc__price-top { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
    .pc__now { font-size: 25px; font-weight: 800; color: #461500; line-height: 1; letter-spacing: -.5px; }
    .pc__now--empty { font-size: 14px; font-weight: 600; color: #b3a79e; }
    .pc__save { font-size: 10.5px; font-weight: 700; color: #1e7e34; background: #e3f4e8; padding: 2px 9px; border-radius: 20px; }
    .pc__price-sub { display: flex; align-items: center; gap: 10px; margin-top: 5px; flex-wrap: wrap; }
    .pc__old { font-size: 12px; color: #b3a79e; text-decoration: line-through; }
    .pc__voucher { font-size: 10.5px; color: #8a5a2b; }
    .pc__voucher i { margin-right: 3px; }

    /* Zona 3 — detail (redup) + footer */
    .pc__body { padding: 12px 16px 14px; display: flex; flex-direction: column; gap: 9px; flex: 1; }
    .pc__text { font-size: 11.5px; line-height: 1.5; color: #6a615a; }
    .pc__text b { display: block; font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #b3a79e; margin-bottom: 1px; }
    .pc__text p { margin: 0; white-space: pre-line; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .pc__text--sk p { font-style: italic; color: #9a8f86; -webkit-line-clamp: 1; }

    .pc__foot { margin-top: auto; padding-top: 10px; border-top: 1px dashed #efe6dd; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .pc__margin { font-size: 11px; color: #8a7a6e; white-space: nowrap; }
    .pc__margin b { font-weight: 700; }
    .pc__margin--loss { color: #c1272d; }
    .pc__margin--loss b { color: #c1272d; }
    .pc__branches { display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; }
    .chip { font-size: 10px; font-weight: 600; color: #3a6b8a; background: #eef4f8; padding: 2px 8px; border-radius: 20px; }
</style>
@endsection

@section('js')
<script>
    var promoStatusOptions = @json(\App\Models\MarketingPromo::statusOptions());

    // Card clickable → buka halaman edit. Abaikan klik dari kontrol interaktif
    // (dropdown status) atau saat user lagi nyeleksi teks (mau baca/copy S&K).
    function goEditPromo(e, el) {
        if (e.target.closest('.st-status-select')) return;
        if (window.getSelection && String(window.getSelection()).length > 0) return;
        window.location = el.dataset.href;
    }

    function changePromoStatus(el) {
        var id          = el.dataset.id;
        var newStatus   = el.value;
        var origStatus  = el.dataset.original;
        if (newStatus === origStatus) return;

        Swal.fire({
            title: 'Ubah status promo?',
            text: 'Status akan diubah ke "' + (promoStatusOptions[newStatus]?.label ?? newStatus) + '"',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, ubah',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.value) {
                el.value = origStatus;
                return;
            }
            el.disabled = true;
            $.ajax({
                url: '/marketing-promo/' + id + '/status',
                method: 'PATCH',
                data: { _token: '{{ csrf_token() }}', status: newStatus },
                success: function (res) {
                    el.dataset.original = res.status;
                    el.dataset.status   = res.status;
                    el.disabled = false;
                    Swal.fire({ title: 'Berhasil', text: 'Status diubah ke "' + res.label + '"', type: 'success', timer: 1500, showConfirmButton: false });
                },
                error: function (xhr) {
                    el.value = origStatus;
                    el.disabled = false;
                    Swal.fire({ title: 'Gagal', text: xhr.responseJSON?.message || 'Terjadi kesalahan', type: 'error' });
                }
            });
        });
    }
</script>
@endsection
