@extends('layouts.app')

@php
    $rp = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $confColor = [
        'exact'  => '#1f7a3d', 'high' => '#2f8f4e', 'medium' => '#b8860b',
        'manual' => '#2563eb', 'low' => '#c0392b', 'no_match' => '#8a8a8a',
    ];
    $payLabel = fn($p) => match(strtolower((string)$p)) {
        'cash_paid', 'paid'  => ['Lunas', '#1f7a3d'],
        'partial'            => ['Sebagian', '#b8860b'],
        'unpaid'             => ['Belum Bayar', '#c0392b'],
        default              => [$p ?: '-', '#8a8a8a'],
    };
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">
                    <i class="fa fa-link mr-2" style="color:var(--brown)"></i>
                    Rekonsiliasi Reservasi
                    <span style="font-size:0.85rem; color:var(--text-subtle); font-weight:400;">
                        — {{ $branchName }} · reservasi SweetTreats ↔ encounter Omnicare
                    </span>
                </h4>
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div style="margin-bottom:16px; padding:12px 16px; border-radius:10px; background:#e8f6ed;
                    border:1px solid #bfe3cc; color:#1f7a3d; font-size:0.88rem;">
            <i class="fa fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="margin-bottom:16px; padding:12px 16px; border-radius:10px; background:#fdecea;
                    border:1px solid #f4c4bd; color:#c0392b; font-size:0.88rem;">
            <i class="fa fa-circle-exclamation mr-1"></i> {{ session('error') }}
        </div>
    @endif

    {{-- Branch filter --}}
    @unless($branchLocked)
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:1rem 1.4rem;">
                    <form method="GET" style="display:flex; gap:14px; align-items:flex-end;">
                        <div>
                            <label style="display:block; font-size:0.7rem; font-weight:700; letter-spacing:0.05em;
                                          color:var(--brown); margin-bottom:7px;">
                                <i class="fa fa-hospital mr-1"></i> CABANG
                            </label>
                            <select name="place" onchange="this.form.submit()"
                                    style="width:220px; padding:9px 12px; font-size:0.86rem; cursor:pointer;
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
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endunless

    {{-- Konteks --}}
    <div style="margin-bottom:16px; padding:11px 15px; border-radius:10px; background:#f3f0ff;
                border:1px solid #ddd4f5; color:#5b4a8a; font-size:0.8rem;">
        <i class="fa fa-circle-info mr-1"></i>
        Rekonsiliasi hanya mencakup reservasi yang dibuat di <strong>Data Center</strong>
        (sistem reservasi baru, mulai Mei 2026). Reservasi era spreadsheet tidak ikut
        — jadi angka di bawah bukan ukuran walk-in operasional.
    </div>

    {{-- Summary cards --}}
    <div class="row mb-3">
        @php
            $cards = [
                ['Ter-link',     $summary['linked'],   'fa-circle-check',      '#1f7a3d', 'reservasi cocok ke encounter'],
                ['Perlu Tindakan', $summary['conflict'], 'fa-triangle-exclamation', '#c0392b', 'conflict — pilih manual'],
                ['No-Show',      $summary['no_show'],   'fa-user-slash',        '#b8860b', 'tidak ada encounter'],
            ];
        @endphp
        @foreach($cards as $c)
        <div class="col-md-4 col-sm-6 mb-2">
            <div class="st-card" style="height:100%;">
                <div class="st-card__body" style="padding:1.1rem 1.25rem;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:42px; height:42px; border-radius:11px; flex-shrink:0;
                                    display:flex; align-items:center; justify-content:center;
                                    background:{{ $c[3] }}1a; color:{{ $c[3] }}; font-size:1.1rem;">
                            <i class="fa {{ $c[2] }}"></i>
                        </div>
                        <div>
                            <div style="font-size:1.5rem; font-weight:700; color:var(--text-primary); line-height:1;">
                                {{ number_format($c[1]) }}
                            </div>
                            <div style="font-size:0.74rem; color:var(--brown); font-weight:700; margin-top:3px;">
                                {{ strtoupper($c[0]) }}
                            </div>
                        </div>
                    </div>
                    <div style="font-size:0.72rem; color:var(--text-subtle); margin-top:9px;">{{ $c[4] }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row mb-3">
        <div class="col-md-6 mb-2">
            <div class="st-card"><div class="st-card__body" style="padding:1rem 1.3rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.74rem; color:var(--brown); font-weight:700;">CONVERSION RATE</div>
                    <div style="font-size:0.72rem; color:var(--text-subtle);">linked ÷ (linked + no-show + conflict)</div>
                </div>
                <div style="font-size:1.7rem; font-weight:700; color:var(--brown);">{{ $summary['conversion'] }}%</div>
            </div></div>
        </div>
        <div class="col-md-6 mb-2">
            <div class="st-card"><div class="st-card__body" style="padding:1rem 1.3rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.74rem; color:var(--brown); font-weight:700;">REVENUE TER-REKONSILIASI</div>
                    <div style="font-size:0.72rem; color:var(--text-subtle);">total dari reservasi yang ter-link</div>
                </div>
                <div style="font-size:1.35rem; font-weight:700; color:var(--text-primary);">{{ $rp($summary['revenue']) }}</div>
            </div></div>
        </div>
    </div>

    {{-- ── Conflict queue ───────────────────────────────────────────────── --}}
    <div class="st-card mb-3">
        <div class="st-card__body" style="padding:1.2rem 1.4rem;">
            <h5 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin-bottom:4px;">
                <i class="fa fa-triangle-exclamation mr-1" style="color:#c0392b;"></i>
                Perlu Tindakan — Conflict ({{ count($conflicts) }})
            </h5>
            <p style="font-size:0.78rem; color:var(--text-subtle); margin-bottom:16px;">
                Engine menemukan lebih dari satu encounter yang sama kuat. Pilih encounter yang benar, atau tandai no-show.
            </p>

            @forelse($conflicts as $cf)
            <div style="border:1.5px solid #f4c4bd; border-radius:12px; padding:14px 16px; margin-bottom:14px; background:#fffaf9;">
                <div style="display:flex; flex-wrap:wrap; gap:18px; align-items:baseline; margin-bottom:10px;">
                    <div style="font-weight:700; color:var(--text-primary); font-size:0.95rem;">
                        <i class="fa fa-user mr-1" style="color:var(--brown);"></i> {{ $cf->cust_name ?: '—' }}
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-subtle);">
                        <i class="fa fa-calendar-day mr-1"></i> Reservasi {{ \Carbon\Carbon::parse($cf->res_date)->format('d M Y') }}
                    </div>
                    <div style="font-size:0.8rem; color:var(--text-subtle);">
                        <i class="fa fa-hospital mr-1"></i> {{ $cf->branch ?: '—' }}
                    </div>
                    @if($cf->doctor)
                    <div style="font-size:0.8rem; color:var(--text-subtle);">
                        <i class="fa fa-user-md mr-1"></i> {{ $cf->doctor }}
                    </div>
                    @endif
                    @if($cf->cust_phone)
                    <div style="font-size:0.8rem; color:var(--text-subtle);">
                        <i class="fa fa-phone mr-1"></i> {{ $cf->cust_phone }}
                    </div>
                    @endif
                </div>

                @if(count($cf->candidates))
                <form method="POST" action="{{ url('reconcile/' . $cf->id . '/link') }}">
                    @csrf
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        @foreach($cf->candidates as $cand)
                        <label style="display:flex; gap:11px; align-items:flex-start; cursor:pointer;
                                      border:1px solid var(--brown-pale); border-radius:9px; padding:10px 12px; background:#fff;">
                            <input type="radio" name="encounter_id" value="{{ $cand['encounter_id'] }}"
                                   style="margin-top:3px;" {{ $loop->first ? 'checked' : '' }}>
                            <div style="flex:1; min-width:0;">
                                <div style="display:flex; flex-wrap:wrap; gap:14px; align-items:baseline;">
                                    <span style="font-weight:700; color:var(--text-primary); font-size:0.86rem;">
                                        Encounter #{{ $cand['encounter_id'] }}
                                    </span>
                                    <span style="font-size:0.8rem; color:var(--text-subtle);">
                                        <i class="fa fa-calendar-day mr-1"></i>{{ \Carbon\Carbon::parse($cand['trx_date'])->format('d M Y') }}
                                    </span>
                                    <span style="font-size:0.8rem; color:{{ $cand['same_branch'] ? '#1f7a3d' : '#c0392b' }};">
                                        <i class="fa fa-hospital mr-1"></i>{{ $cand['branch_name'] ?: $cand['place_id'] }}
                                        {{ $cand['same_branch'] ? '' : '(beda cabang)' }}
                                    </span>
                                    @if($cand['doctor'])
                                    <span style="font-size:0.8rem; color:var(--text-subtle);">
                                        <i class="fa fa-user-md mr-1"></i>{{ $cand['doctor'] }}
                                    </span>
                                    @endif
                                    <span style="font-weight:700; color:var(--brown); font-size:0.86rem;">
                                        {{ $rp($cand['revenue']) }}
                                    </span>
                                </div>
                                <div style="font-size:0.74rem; color:var(--text-subtle); margin-top:4px;">
                                    {{ \Illuminate\Support\Str::limit(implode(' · ', $cand['items']), 160) }}
                                </div>
                            </div>
                        </label>
                        @endforeach
                    </div>
                    <div style="display:flex; gap:10px; margin-top:12px;">
                        <button type="submit" class="st-btn-brand"
                                style="padding:8px 18px; font-size:0.82rem; font-weight:600; border-radius:9px;">
                            <i class="fa fa-link mr-1"></i> Link Encounter Terpilih
                        </button>
                        <button type="submit" formaction="{{ url('reconcile/' . $cf->id . '/noshow') }}"
                                formnovalidate
                                style="padding:8px 18px; font-size:0.82rem; font-weight:600; border-radius:9px;
                                       background:#fff; color:#b8860b; border:1.5px solid #e3c48a; cursor:pointer;">
                            <i class="fa fa-user-slash mr-1"></i> Tandai No-Show
                        </button>
                    </div>
                </form>
                @else
                <div style="font-size:0.82rem; color:var(--text-subtle);">
                    Tidak ada encounter kandidat lagi (mungkin sudah dipindahkan).
                    <form method="POST" action="{{ url('reconcile/' . $cf->id . '/noshow') }}" style="display:inline;">
                        @csrf
                        <button type="submit" style="margin-left:8px; padding:6px 14px; font-size:0.8rem;
                                background:#fff; color:#b8860b; border:1.5px solid #e3c48a; border-radius:8px; cursor:pointer;">
                            Tandai No-Show
                        </button>
                    </form>
                </div>
                @endif
            </div>
            @empty
            <div style="text-align:center; padding:26px; color:var(--text-subtle); font-size:0.86rem;">
                <i class="fa fa-circle-check mr-1" style="color:#1f7a3d;"></i>
                Tidak ada conflict. Semua reservasi sudah terselesaikan.
            </div>
            @endforelse
        </div>
    </div>

    {{-- ── Customer belum ter-link ──────────────────────────────────────── --}}
    @if(count($noLink))
    <div class="st-card mb-3">
        <div class="st-card__body" style="padding:1.2rem 1.4rem;">
            <h5 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin-bottom:4px;">
                <i class="fa fa-user-xmark mr-1" style="color:#8a8a8a;"></i>
                Customer Belum Ter-link Omnicare ({{ count($noLink) }})
            </h5>
            <p style="font-size:0.78rem; color:var(--text-subtle); margin-bottom:14px;">
                Reservasi ini tidak bisa dicocokkan otomatis karena customer-nya belum terhubung ke pasien Omnicare.
            </p>
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--brown-pale); color:var(--brown); text-align:left;">
                        <th style="padding:8px 10px;">Tanggal</th>
                        <th style="padding:8px 10px;">Customer</th>
                        <th style="padding:8px 10px;">Telepon</th>
                        <th style="padding:8px 10px;">Cabang</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($noLink as $nl)
                    <tr style="border-bottom:1px solid var(--divider-strong);">
                        <td style="padding:7px 10px;">{{ \Carbon\Carbon::parse($nl->res_date)->format('d M Y') }}</td>
                        <td style="padding:7px 10px;">{{ $nl->cust_name ?: '—' }}</td>
                        <td style="padding:7px 10px; color:var(--text-subtle);">{{ $nl->cust_phone ?: '—' }}</td>
                        <td style="padding:7px 10px;">{{ $nl->branch ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ── Riwayat hasil ────────────────────────────────────────────────── --}}
    <div class="st-card">
        <div class="st-card__body" style="padding:1.2rem 1.4rem;">
            <h5 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin-bottom:14px;">
                <i class="fa fa-list-check mr-1" style="color:var(--brown);"></i>
                Hasil Rekonsiliasi ({{ count($resolved) }})
            </h5>
            <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--brown-pale); color:var(--brown); text-align:left;">
                        <th style="padding:8px 10px;">Reservasi</th>
                        <th style="padding:8px 10px;">Customer</th>
                        <th style="padding:8px 10px;">Cabang</th>
                        <th style="padding:8px 10px;">Status</th>
                        <th style="padding:8px 10px;">Encounter</th>
                        <th style="padding:8px 10px; text-align:right;">Revenue</th>
                        <th style="padding:8px 10px;">Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($resolved as $row)
                    @php
                        $isLinked = ! empty($row->omnicare_record_id);
                        $conf = $row->match_confidence;
                        [$payTxt, $payCol] = $payLabel($row->payment_status);
                    @endphp
                    <tr style="border-bottom:1px solid var(--divider-strong);">
                        <td style="padding:7px 10px;">{{ \Carbon\Carbon::parse($row->res_date)->format('d M Y') }}</td>
                        <td style="padding:7px 10px;">{{ $row->cust_name ?: '—' }}</td>
                        <td style="padding:7px 10px;">{{ $row->branch ?: '—' }}</td>
                        <td style="padding:7px 10px;">
                            @if($isLinked)
                                <span style="display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.7rem;
                                             font-weight:700; background:{{ ($confColor[$conf] ?? '#888') }}1a;
                                             color:{{ $confColor[$conf] ?? '#888' }};">
                                    LINKED · {{ strtoupper($conf) }}
                                </span>
                            @else
                                <span style="display:inline-block; padding:2px 9px; border-radius:20px; font-size:0.7rem;
                                             font-weight:700; background:#b8860b1a; color:#b8860b;">
                                    NO-SHOW
                                </span>
                            @endif
                        </td>
                        <td style="padding:7px 10px; color:var(--text-subtle);">
                            @if($isLinked)
                                #{{ $row->omnicare_record_id }}
                                <span style="font-size:0.72rem;">({{ \Carbon\Carbon::parse($row->encounter_date)->format('d/m/y') }})</span>
                            @else — @endif
                        </td>
                        <td style="padding:7px 10px; text-align:right; font-weight:600;">
                            {{ $isLinked ? $rp($row->actual_revenue) : '—' }}
                        </td>
                        <td style="padding:7px 10px;">
                            @if($isLinked)
                                <span style="color:{{ $payCol }}; font-weight:600;">{{ $payTxt }}</span>
                            @else — @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:22px; text-align:center; color:var(--text-subtle);">
                        Belum ada hasil rekonsiliasi. Jalankan <code>php artisan omnicare:reconcile</code>.
                    </td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

</div>
@endsection
