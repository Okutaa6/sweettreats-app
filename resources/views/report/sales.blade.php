@extends('layouts.app')

@php
    $rp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $qs = fn($overrides = []) => http_build_query(array_merge([
        'tab' => $tab, 'date_from' => $start, 'date_to' => $end, 'place' => request('place'),
    ], $overrides));
    $tabIcons = [
        'rekap'    => 'fa-clipboard-list',
        'prosedur' => 'fa-tooth',
        'dokter'   => 'fa-user-md',
        'pasien'   => 'fa-users',
        'staff'    => 'fa-user-tie',
        'rincian'  => 'fa-receipt',
        'piutang'  => 'fa-exclamation-circle',
    ];
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">
                    <i class="fa fa-file-invoice-dollar mr-2" style="color:var(--brown)"></i>
                    Laporan Penjualan
                    <span style="font-size:0.85rem; color:var(--text-subtle); font-weight:400;">
                        — {{ $branchName }} ·
                        {{ \Carbon\Carbon::parse($start)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($end)->format('d M Y') }}
                        ({{ \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)) + 1 }} hari)
                    </span>
                </h4>
            </div>
        </div>
    </div>

    {{-- Filter Periode --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:1.15rem 1.4rem;">
                    <form method="GET">
                        <input type="hidden" name="tab" value="{{ $tab }}">

                        <div style="display:flex; flex-wrap:wrap; gap:22px; align-items:flex-end;">

                            {{-- Periode laporan — date range terhubung jadi satu unit --}}
                            <div>
                                <label style="display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.05em;
                                              color:var(--brown); margin-bottom:7px;">
                                    <i class="fa fa-calendar-alt mr-1"></i> PERIODE LAPORAN
                                </label>
                                <div style="display:inline-flex; align-items:stretch; background:#fff;
                                            border:1.5px solid var(--brown-pale); border-radius:10px; overflow:hidden;">
                                    <input type="date" name="date_from" value="{{ $start }}" required
                                           style="border:none; outline:none; background:transparent; color:var(--text-primary);
                                                  font-size:0.86rem; padding:9px 12px; cursor:pointer;">
                                    <span style="display:flex; align-items:center; padding:0 12px;
                                                 background:var(--brown); color:#fff; font-size:0.78rem;">
                                        <i class="fa fa-arrow-right"></i>
                                    </span>
                                    <input type="date" name="date_to" value="{{ $end }}" required
                                           style="border:none; outline:none; background:transparent; color:var(--text-primary);
                                                  font-size:0.86rem; padding:9px 12px; cursor:pointer;">
                                </div>
                            </div>

                            @unless($branchLocked)
                            <div>
                                <label style="display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.05em;
                                              color:var(--brown); margin-bottom:7px;">
                                    <i class="fa fa-hospital mr-1"></i> CABANG
                                </label>
                                <select name="place"
                                        style="width:200px; padding:9px 12px; font-size:0.86rem; cursor:pointer;
                                               border:1.5px solid var(--brown-pale); border-radius:10px;
                                               background:#fff; color:var(--text-primary); outline:none;">
                                    <option value="">Semua Cabang</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" {{ request('place') == $b->id ? 'selected' : '' }}>
                                            {{ $b->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @endunless

                            <button type="submit" class="st-btn-brand"
                                    style="padding:10px 24px; font-size:0.85rem; font-weight:600; border-radius:10px;">
                                <i class="fa fa-filter mr-1"></i> Terapkan
                            </button>
                        </div>

                        {{-- Pintasan cepat — sekunder --}}
                        <div style="margin-top:16px; padding-top:13px; border-top:1px dashed var(--divider-strong);
                                    display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                            <span style="font-size:0.73rem; color:var(--text-subtle); font-weight:600;">
                                <i class="fa fa-bolt" style="color:var(--brown-2);"></i> Pintasan
                            </span>
                            @foreach($presets as $label => $range)
                                @php $ap = ($start === $range[0] && $end === $range[1]); @endphp
                                <a href="?{{ $qs(['date_from' => $range[0], 'date_to' => $range[1]]) }}"
                                   style="text-decoration:none; padding:5px 14px; font-size:0.75rem; border-radius:20px;
                                   {{ $ap
                                      ? 'background:var(--brown); color:#fff; font-weight:600;'
                                      : 'background:var(--cream-hover); color:var(--text-muted); border:1px solid var(--brown-pale);' }}">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab navigation --}}
    <div class="row mb-3">
        <div class="col-12">
            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">
                {{-- Tabs jenis report --}}
                <div style="display:flex; flex-wrap:wrap; gap:7px;">
                    @foreach($tabs as $key => $label)
                        @php $isActive = ($tab === $key); @endphp
                        <a href="?{{ $qs(['tab' => $key]) }}"
                           style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;
                                  padding:9px 16px; border-radius:9px; font-size:0.82rem;
                                  font-weight:{{ $isActive ? '700' : '500' }}; transition:all .12s;
                                  {{ $isActive
                                     ? 'background:var(--brown); color:#fff; box-shadow:0 3px 9px rgba(70,21,0,0.28);'
                                     : 'background:#fff; color:var(--brown-2); border:1.5px solid var(--brown-pale);' }}">
                            <i class="fa {{ $tabIcons[$key] ?? 'fa-file' }}"
                               style="font-size:0.92rem; {{ $isActive ? '' : 'color:var(--brown);' }}"></i>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
                {{-- Aksi: export --}}
                <a href="{{ route('report.sales.export') }}?{{ $qs() }}"
                   style="display:inline-flex; align-items:center; gap:8px; text-decoration:none;
                          padding:9px 18px; border-radius:9px; font-size:0.82rem; font-weight:600;
                          background:#2E7D32; color:#fff; box-shadow:0 3px 9px rgba(46,125,50,0.28);">
                    <i class="fa fa-file-csv"></i> Export CSV
                </a>
            </div>
        </div>
    </div>

    {{-- ═══ TAB: REKAP ═══ --}}
    @if($tab === 'rekap')
        @php
            $s    = $report['rekap']['summary'];
            $comp = $report['rekap']['composition'];
            $ps   = $report['rekap']['patientStats'];
            $typeLabels = ['P'=>'Prosedur / Tindakan','D'=>'Obat & BHP','SC'=>'Service Charge','V'=>'Voucher Tunai','S'=>'Supply / BHP'];
            $compTotal = max($comp->sum('net'), 1);
        @endphp

        {{-- Summary cards --}}
        <div class="row mb-3">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card st-exec-summary">
                    <p class="st-dash-card__label">Total Penjualan (Net)</p>
                    <p class="st-dash-card__value" style="color:#2E7D32">{{ $rp($s->net) }}</p>
                    <small style="color:var(--text-subtle)">prosedur + obat — tanpa voucher &amp; service charge</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card st-exec-summary">
                    <p class="st-dash-card__label">Rata-rata / Kunjungan</p>
                    <p class="st-dash-card__value" style="color:#1E5A8C">{{ $rp($s->visits > 0 ? $s->net / $s->visits : 0) }}</p>
                    <small style="color:var(--text-subtle)">net &divide; kunjungan</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card st-exec-summary">
                    <p class="st-dash-card__label">Total Diskon</p>
                    <p class="st-dash-card__value" style="color:#B26B00">{{ $rp($s->discount) }}</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="st-dash-card st-exec-summary">
                    <p class="st-dash-card__label">Total Kunjungan</p>
                    <p class="st-dash-card__value">{{ number_format($s->visits) }}</p>
                    <small style="color:var(--text-subtle)">{{ number_format($s->patients) }} pasien unik &middot; {{ number_format($s->line_count) }} line item</small>
                </div>
            </div>
        </div>

        {{-- Komposisi penjualan + Pasien baru/lama --}}
        <div class="row">
            <div class="col-lg-7 mb-3">
                <div class="st-card h-100"><div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-layer-group"></i> Komposisi Penjualan per Tipe</h6>
                    @forelse($comp as $c)
                        @php
                            $lbl = $typeLabels[$c->item_type] ?? ('Tipe ' . $c->item_type);
                            $pct = round($c->net / $compTotal * 100, 1);
                            $isExcluded = in_array($c->item_type, ['V', 'SC'], true);
                        @endphp
                        <div style="margin-bottom:11px;">
                            <div class="d-flex justify-content-between align-items-center" style="font-size:0.84rem; margin-bottom:4px;">
                                <span style="font-weight:600;">
                                    {{ $lbl }}
                                    @if($isExcluded)
                                        <span style="font-size:0.64rem; background:#FFF3E0; color:#B26B00; padding:1px 7px; border-radius:10px; margin-left:4px;">tidak masuk total</span>
                                    @endif
                                </span>
                                <span class="st-tnum">{{ $rp($c->net) }}
                                    <small style="color:var(--text-subtle)">· {{ $pct }}%</small></span>
                            </div>
                            <div style="background:var(--divider-soft); border-radius:6px; height:9px; overflow:hidden;">
                                <div style="height:100%; width:{{ min($pct,100) }}%; background:{{ $isExcluded ? '#B26B00' : 'var(--brown)' }};"></div>
                            </div>
                        </div>
                    @empty
                        <p class="st-muted text-center">Belum ada data</p>
                    @endforelse
                </div></div>
            </div>
            <div class="col-lg-5 mb-3">
                <div class="st-card h-100"><div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-user-plus"></i> Pasien Baru vs Repeat</h6>

                    {{-- Pasien Baru --}}
                    <div style="text-align:center; background:#E8F5E9; border-radius:10px; padding:13px 8px; margin-bottom:11px;">
                        <div style="font-size:1.7rem; font-weight:700; color:#2E7D32;">{{ number_format($ps->baru) }}</div>
                        <div style="font-size:0.77rem; color:var(--text-subtle);">Pasien Baru — registrasi di periode ini</div>
                    </div>

                    {{-- Pasien Repeat — dipisah ortho vs non-ortho --}}
                    <div style="font-size:0.7rem; font-weight:700; color:var(--brown); letter-spacing:0.04em; margin-bottom:6px;">
                        PASIEN REPEAT — {{ number_format($ps->repeat_total) }}
                    </div>
                    <div class="d-flex" style="gap:10px;">
                        <div style="flex:1; text-align:center; background:var(--cream-hover); border-radius:10px; padding:13px 6px;">
                            <div style="font-size:1.45rem; font-weight:700; color:var(--brown);">{{ number_format($ps->repeat_ortho) }}</div>
                            <div style="font-size:0.72rem; color:var(--text-subtle);">Behel / Ortho</div>
                        </div>
                        <div style="flex:1; text-align:center; background:#E3F2FD; border-radius:10px; padding:13px 6px;">
                            <div style="font-size:1.45rem; font-weight:700; color:#1E5A8C;">{{ number_format($ps->repeat_nonortho) }}</div>
                            <div style="font-size:0.72rem; color:var(--text-subtle);">Non-Ortho</div>
                        </div>
                    </div>

                    <small style="display:block; margin-top:11px; color:var(--text-subtle); font-size:0.7rem; line-height:1.55;">
                        <i class="fa fa-info-circle"></i>
                        <strong>Behel/Ortho</strong> = repeat untuk kontrol behel / pasang ortho — pasien "terikat" jadwal kontrol.
                        <strong>Non-Ortho</strong> = repeat untuk scaling, tambal, cabut, dll. — sinyal loyalitas lebih murni.
                        "Baru" = bulan registrasi (kode ID pasien) jatuh di periode ini.
                    </small>
                </div></div>
            </div>
        </div>

        {{-- Penjualan per hari + status pembayaran --}}
        <div class="row">
            <div class="col-lg-8 mb-3">
                <div class="st-card"><div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-calendar-day"></i> Penjualan per Hari</h6>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead><tr>
                                <th>Tanggal</th><th class="text-right">Line</th>
                                <th class="text-right">Kunjungan</th><th class="text-right">Net</th>
                            </tr></thead>
                            <tbody>
                                @forelse($report['rekap']['perDay'] as $d)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($d->trx_date)->format('d M Y') }}</td>
                                    <td class="text-right st-tnum">{{ $d->line_count }}</td>
                                    <td class="text-right st-tnum">{{ $d->visits }}</td>
                                    <td class="text-right st-tnum">{{ $rp($d->net) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center st-muted">Belum ada data periode ini</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="st-card"><div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-credit-card"></i> Status Pembayaran</h6>
                    @forelse($report['rekap']['payment'] as $p)
                        <div class="st-kv-row">
                            <span class="st-kv-row__label">{{ ucwords(str_replace('_', ' ', $p->payment_status)) }}</span>
                            <span class="st-kv-row__value">{{ $rp($p->net) }} <small style="color:var(--text-subtle)">({{ $p->line_count }})</small></span>
                        </div>
                    @empty
                        <p class="st-muted text-center">—</p>
                    @endforelse
                </div></div>
            </div>
        </div>
    @endif

    {{-- ═══ TAB: PROSEDUR ═══ --}}
    @if($tab === 'prosedur')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-tooth"></i> Penjualan per Prosedur / Item (ranked)</h6>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>Kode</th><th>Nama Item</th><th>Tipe</th>
                        <th class="text-right">Terjual</th><th class="text-right">Gross</th>
                        <th class="text-right">Diskon</th><th class="text-right">Net</th>
                        <th class="text-right">Profit</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['prosedur'] as $i => $r)
                        <tr>
                            <td class="st-tnum">{{ $i + 1 }}</td>
                            <td>{{ $r->item_code ?: '—' }}</td>
                            <td>{{ $r->item_name }}</td>
                            <td><span class="st-pill st-pill--light">{{ $r->item_type ?: '?' }}</span></td>
                            <td class="text-right st-tnum"><strong>{{ (int) $r->qty }}</strong></td>
                            <td class="text-right st-tnum">{{ $rp($r->gross) }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->discount) }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->net) }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->profit) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif

    {{-- ═══ TAB: DOKTER ═══ --}}
    @if($tab === 'dokter')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-user-md"></i> Penjualan per Dokter</h6>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>Dokter</th><th class="text-right">Kunjungan</th>
                        <th class="text-right">Line Items</th><th class="text-right">Net</th>
                        <th class="text-right">Profit</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['dokter'] as $i => $r)
                        <tr>
                            <td class="st-tnum">{{ $i + 1 }}</td>
                            <td>{{ $r->doctor_name }}</td>
                            <td class="text-right st-tnum">{{ $r->visits }}</td>
                            <td class="text-right st-tnum">{{ $r->line_count }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->net) }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->profit) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif

    {{-- ═══ TAB: PASIEN ═══ --}}
    @if($tab === 'pasien')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-users"></i> Transaksi per Pasien</h6>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>ID Pasien</th><th>Nama</th><th class="text-right">Kunjungan</th>
                        <th class="text-right">Total</th><th class="text-right">Rata-rata/Kunjungan</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['pasien'] as $i => $r)
                        <tr>
                            <td class="st-tnum">{{ $i + 1 }}</td>
                            <td>{{ $r->p_id }}</td>
                            <td>{{ $r->p_name }}</td>
                            <td class="text-right st-tnum">{{ $r->visits }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->total) }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->avg_visit) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif

    {{-- ═══ TAB: STAFF ═══ --}}
    @if($tab === 'staff')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-user-tie"></i> Penjualan per Staff (Front Office)</h6>
            <p style="font-size:0.78rem; color:var(--text-subtle); margin-bottom:10px;">
                Berdasarkan field <code>username</code> Omnicare — staff yang memproses/input transaksi.
            </p>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>#</th><th>Staff / Username</th>
                        <th class="text-right">Faktur</th><th class="text-right">Kunjungan</th>
                        <th class="text-right">Line Items</th><th class="text-right">Total Net</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['staff'] as $i => $r)
                        <tr>
                            <td class="st-tnum">{{ $i + 1 }}</td>
                            <td><i class="fa fa-user-circle" style="color:var(--brown-pale); margin-right:5px;"></i>{{ $r->username }}</td>
                            <td class="text-right st-tnum">{{ $r->invoices }}</td>
                            <td class="text-right st-tnum">{{ $r->visits }}</td>
                            <td class="text-right st-tnum">{{ $r->line_count }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->net) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif

    {{-- ═══ TAB: RINCIAN ═══ --}}
    @if($tab === 'rincian')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-list"></i> Rincian Penjualan (Line Item)</h6>
            @if($report['rincianTotal'] > 300)
                <div style="background:#FFF8F0; border-left:3px solid #B26B00; padding:6px 10px; margin-bottom:8px; font-size:0.8rem;">
                    <i class="fa fa-info-circle"></i> Menampilkan 300 dari {{ number_format($report['rincianTotal']) }} baris.
                    Klik <strong>Export CSV</strong> untuk data lengkap.
                </div>
            @endif
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>Tanggal</th><th>Faktur</th><th>Pasien</th><th>Dokter</th>
                        <th>Item</th><th>Tipe</th><th class="text-right">Qty</th>
                        <th class="text-right">Net</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['rincian'] as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r->trx_date)->format('d/m/y') }}</td>
                            <td>{{ $r->invoice_num }}</td>
                            <td>{{ $r->p_name }}</td>
                            <td>{{ $r->doctor }}</td>
                            <td>{{ $r->item_name }}</td>
                            <td><span class="st-pill st-pill--light">{{ $r->item_type ?: '?' }}</span></td>
                            <td class="text-right st-tnum">{{ (float) $r->qty_sold }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->net_sell_price) }}</td>
                            <td>
                                <span style="color:{{ $r->payment_status === 'unpaid' ? '#B23A3A' : '#2E7D32' }}; font-size:0.78rem;">
                                    {{ ucwords(str_replace('_', ' ', $r->payment_status)) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center st-muted">Belum ada data periode ini</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif

    {{-- ═══ TAB: PIUTANG ═══ --}}
    @if($tab === 'piutang')
        <div class="row"><div class="col-12"><div class="st-card"><div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-exclamation-circle"></i> Piutang — Invoice Belum Lunas</h6>
            @php $piutangTotal = $report['piutang']->sum('net_sell_price'); @endphp
            <p style="font-size:0.9rem;">Total belum lunas: <strong style="color:#B23A3A">{{ $rp($piutangTotal) }}</strong>
               <span style="color:var(--text-subtle)">({{ $report['piutang']->count() }} line item)</span></p>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead><tr>
                        <th>Tanggal</th><th>Faktur</th><th>Pasien</th><th>Dokter</th>
                        <th>Item</th><th class="text-right">Qty</th><th class="text-right">Nilai</th>
                    </tr></thead>
                    <tbody>
                        @forelse($report['piutang'] as $r)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($r->trx_date)->format('d/m/y') }}</td>
                            <td>{{ $r->invoice_num }}</td>
                            <td>{{ $r->p_name }}</td>
                            <td>{{ $r->doctor }}</td>
                            <td>{{ $r->item_name }}</td>
                            <td class="text-right st-tnum">{{ (float) $r->qty_sold }}</td>
                            <td class="text-right st-tnum">{{ $rp($r->net_sell_price) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center st-muted">Tidak ada piutang — semua lunas 🎉</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div></div></div>
    @endif
</div>
@endsection
