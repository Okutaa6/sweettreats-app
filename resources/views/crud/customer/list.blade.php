@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/pasien-redesign.css') }}?v={{ @filemtime(public_path('assets/css/pasien-redesign.css')) ?: '1' }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-title-box"><h4 class="page-title">Pasien</h4></div>

    @include('layouts.notif')

    @php
        $hasFilter = request('search') || request('service_id') || request('doctor_id') || request('branch_id');
        $showBranchFilter = isset($branches) && $branches->isNotEmpty();
        $acqClass = ['Instagram' => 'ig', 'TikTok' => 'tiktok', 'Teman' => 'teman', 'Lainnya' => 'lain'];
    @endphp

    <div class="px-wrap">

        {{-- ══════ Insight strip (KPI clinic-wide, dari controller $kpi) ══════ --}}
        <div class="px-insight">
            <div class="px-kpi">
                <div class="px-kpi__head">
                    <div class="px-kpi__icon" style="background:#EDE9FE;color:#7C3AED;"><i class="fa fa-user-friends"></i></div>
                    <div class="px-kpi__label">Total Pasien Aktif</div>
                </div>
                <div class="px-kpi__num">{{ number_format($kpi['total_aktif'], 0, ',', '.') }}</div>
                <div class="px-kpi__sub"><span class="px-kpi__sub--up">+{{ number_format($segCounts['baru'] ?? 0, 0, ',', '.') }}</span> pasien baru bulan ini</div>
            </div>
            <div class="px-kpi">
                <div class="px-kpi__head">
                    <div class="px-kpi__icon" style="background:#E3F4E8;color:#2F8E55;"><i class="fa fa-sync-alt"></i></div>
                    <div class="px-kpi__label">Retensi 6 Bulan</div>
                </div>
                <div class="px-kpi__num">{{ $kpi['retensi'] }}%</div>
                <div class="px-kpi__sub">kembali berkunjung</div>
            </div>
            <div class="px-kpi">
                <div class="px-kpi__head">
                    <div class="px-kpi__icon" style="background:#FBEFD9;color:#9A6614;"><i class="fa fa-coins"></i></div>
                    <div class="px-kpi__label">Rata-rata LTV</div>
                </div>
                <div class="px-kpi__num">Rp {{ number_format($kpi['avg_ltv'] / 1000000, 2, ',', '.') }} jt</div>
                <div class="px-kpi__sub">per pasien</div>
            </div>
            <div class="px-kpi">
                <div class="px-kpi__head" style="justify-content:space-between;">
                    <div class="px-kpi__label">Sumber Akuisisi</div>
                </div>
                <div class="px-acq">
                    @foreach($kpi['acquisition'] as $a)
                        <div class="px-acq__row">
                            <span class="px-acq__label">{{ $a['label'] }}</span>
                            <span class="px-acq__track"><span class="px-acq__fill px-acq__fill--{{ $acqClass[$a['label']] ?? 'lain' }}" style="width:{{ $a['pct'] }}%"></span></span>
                            <span class="px-acq__pct">{{ $a['pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══════ Toolbar: search + Filter + Tambah ══════ --}}
        <div class="px-toolbar">
            <form method="GET" action="{{ route('rawcustomer') }}" class="px-search">
                @if(($seg ?? 'semua') !== 'semua')<input type="hidden" name="seg" value="{{ $seg }}">@endif
                <i class="fa fa-search"></i>
                <input type="text" name="search" value="{{ $filterSearch ?? '' }}" placeholder="Cari nama, no. WhatsApp, atau email…">
            </form>
            <button class="px-btn {{ $hasFilter ? 'is-active' : '' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
            </button>
            <a href="{{ route('rawcustomer.new') }}" class="px-btn px-btn--primary">
                <i class="fa fa-plus"></i> Tambah
            </a>
        </div>

        {{-- ══════ Filter panel (collapsible) — dipertahankan, re-skin nyusul ══════ --}}
        <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
            <div class="st-card mb-0" style="background-color: var(--cream-header);">
                <div class="st-card__body" style="padding:14px 18px;">
                    <form method="GET" action="{{ route('rawcustomer') }}" class="mb-0">
                        {{-- Preserve search (toolbar) + segment aktif saat submit filter. --}}
                        <input type="hidden" name="search" value="{{ $filterSearch ?? '' }}">
                        @if(($seg ?? 'semua') !== 'semua')<input type="hidden" name="seg" value="{{ $seg }}">@endif
                        <div class="row" style="gap:8px 0;">
                            @if($showBranchFilter)
                            <div class="col-md-3">
                                <label class="small mb-1">Cabang</label>
                                <select name="branch_id" class="form-control form-control-sm">
                                    <option value="">Semua Cabang</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ ($filterBranch ?? '') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-{{ $showBranchFilter ? '2' : '3' }}">
                                <label class="small mb-1">Perawatan</label>
                                <select name="service_id" class="form-control form-control-sm">
                                    <option value="">Semua Perawatan</option>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}" {{ ($filterService ?? '') == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small mb-1">Dokter</label>
                                <select name="doctor_id" class="form-control form-control-sm">
                                    <option value="">Semua Dokter</option>
                                    @foreach($doctors as $doctor)
                                        <option value="{{ $doctor->id }}" {{ ($filterDoctor ?? '') == $doctor->id ? 'selected' : '' }}>{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end" style="gap:4px;">
                                <button type="submit" class="st-btn-brand w-100">Filter</button>
                                <a href="{{ route('rawcustomer') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ══════ Segment tabs (worklist) ══════ --}}
        @php
            $segBase = request()->except(['seg', 'page']);
            $segUrl = fn ($s) => route('rawcustomer', $s === 'semua' ? $segBase : array_merge($segBase, ['seg' => $s]));
            $segDefs = ['semua' => 'Semua', 'baru' => 'Pasien Baru', 'belum_datang' => 'Belum Datang', 'ultah' => 'Ulang Tahun'];
        @endphp
        <div class="px-segs">
            <div class="px-segs__tabs">
                @foreach($segDefs as $k => $label)
                    <a href="{{ $segUrl($k) }}" class="px-seg {{ ($seg ?? 'semua') === $k ? 'active' : '' }}">
                        {{ $label }} <span class="px-seg__badge">{{ number_format($segCounts[$k] ?? 0, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>
            <span class="px-segs__count">Menampilkan <strong>{{ number_format($customers->total(), 0, ',', '.') }}</strong> dari {{ number_format($segCounts['semua'] ?? 0, 0, ',', '.') }} pasien</span>
        </div>

        {{-- ══════ Context banner (Belum Datang / Ulang Tahun) ══════ --}}
        @if(($seg ?? 'semua') === 'belum_datang')
            <div class="px-banner px-banner--lead">
                <div class="px-banner__icon">📞</div>
                <div class="px-banner__text">
                    <div class="px-banner__title">{{ number_format($segCounts['belum_datang'] ?? 0, 0, ',', '.') }} pasien belum pernah datang</div>
                    <div class="px-banner__sub">Sudah terdaftar tapi belum berkunjung — follow-up untuk konversi jadi pasien.</div>
                </div>
                {{-- CTA "Pilih semua & chat" di-wire di L5 (bulk select). --}}
            </div>
        @elseif(($seg ?? 'semua') === 'ultah')
            <div class="px-banner px-banner--ultah">
                <div class="px-banner__icon">🎂</div>
                <div class="px-banner__text">
                    <div class="px-banner__title">{{ number_format($segCounts['ultah'] ?? 0, 0, ',', '.') }} pasien ulang tahun bulan ini</div>
                    <div class="px-banner__sub">Kirim ucapan + promo spesial ulang tahun untuk menjaga loyalitas.</div>
                </div>
                {{-- CTA "Pilih semua & chat" di-wire di L5 (bulk select). --}}
            </div>
        @endif

        {{-- ══════ Tabel pasien (design View A) — baris klik → detail ══════ --}}
        @php
            // Palette avatar di-cycle by id (design handoff).
            $avatarColors = ['#7A3415', '#52200A', '#8A3A12', '#6B2400', '#9A6614', '#5A2410', '#7E2E0E', '#693012'];
            $waNum = function ($phone) {
                $p = preg_replace('/[^0-9]/', '', (string) $phone);
                if ($p && str_starts_with($p, '0')) $p = '62' . substr($p, 1);
                return $p;
            };
        @endphp
        <div class="px-table-card">
            <div class="px-table-wrap">
                <table class="px-table st-table--clickable">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>WhatsApp</th>
                            <th>Usia</th>
                            <th>Cabang</th>
                            <th>Perawatan</th>
                            <th>Dokter</th>
                            <th>Kunjungan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $row)
                            @php
                                $latestReservation = $row->latestReservation;
                                $initial = mb_strtoupper(mb_substr(trim($row->name ?: '?'), 0, 1));
                                $avColor = $avatarColors[crc32((string) $row->id) % count($avatarColors)];
                                $usia = ($row->tanggal_lahir && $row->tanggal_lahir != '0000-00-00')
                                    ? \Carbon\Carbon::parse($row->tanggal_lahir)->age : null;
                                $perawatan = ($latestReservation && $latestReservation->services->isNotEmpty())
                                    ? $latestReservation->services->map(fn ($s) => $s->docService?->name)->filter()->implode(', ')
                                    : null;
                                $wa = $waNum($row->phone);
                            @endphp
                            <tr data-href="{{ route('rawcustomer.edit', $row->id) }}">
                                <td>
                                    <div class="px-name">
                                        <div class="px-avatar" style="background:{{ $avColor }};">{{ $initial }}</div>
                                        <div class="px-name__main">
                                            <div class="px-name__name">{{ $row->name }}</div>
                                            @if($row->source_app)<div class="px-name__via">via {{ $row->source_app }}</div>@endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($row->phone)
                                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="px-wa-link" onclick="event.stopPropagation()">
                                            <i class="fab fa-whatsapp"></i> {{ $row->phone }}
                                        </a>
                                    @else
                                        <span class="px-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-tnum">{{ $usia !== null ? $usia : '—' }}</td>
                                <td>{{ $row->branch?->name ?? '—' }}</td>
                                <td>@if($perawatan)<span class="px-treat">{{ $perawatan }}</span>@else<span class="px-muted">—</span>@endif</td>
                                <td>@if($latestReservation?->doctor?->name)<span class="px-doctor" title="{{ $latestReservation->doctor->name }}">{{ $latestReservation->doctor->name }}</span>@else<span class="px-muted">—</span>@endif</td>
                                <td class="px-tnum">{{ $row->last_visit ? \Carbon\Carbon::parse($row->last_visit)->format('d M Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-muted" style="text-align:center; padding:32px;">Tidak ada pasien di segmen ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-pagination">{{ $customers->links() }}</div>
        </div>

    </div>
</div>
@endsection
