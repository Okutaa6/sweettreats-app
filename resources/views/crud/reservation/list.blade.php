@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Reservation</h4>
            </div>
        </div>
    </div>

    <div id="newReservationToast" class="st-toast-floating">
        <div class="st-toast-floating__header">
            <strong><i class="fa fa-bell"></i> Reservasi Baru Masuk</strong>
            <button type="button" class="close" onclick="$('#newReservationToast').fadeOut()" aria-label="Close">&times;</button>
        </div>
        <div id="toastBody" class="st-toast-floating__body">
            {{-- Content injected by JS --}}
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">

                    @php
                        // Mode View Only — di-set di controller. Default false biar
                        // backward-compat (kalau halaman ini di-render dari context lain).
                        $isViewOnly = $isViewOnly ?? false;
                        $accessModeGroups = $accessModeGroups ?? [];

                        $hasFilter = $filterSearch || $filterBranch || $filterDoctor || $filterStatus || $filterDateFrom || $filterDateTo || $filterPromo || $filterAffiliate || request('new') || request('reservation_id');
                        // Filter chips & info hitungan di tab Jadwal Dokter di-suppress
                        // (info sudah ada di banner).
                        $showFilterChips = $hasFilter;
                        if (($view ?? 'list') === 'jadwal') {
                            $showFilterChips = false;
                            // Empty state awal (dokter belum dipilih) → filter tertutup
                            // supaya step-by-step card jadi fokus utama.
                            // Setelah dokter dipilih → filter tetap kebuka biar
                            // gampang di-adjust.
                            if ($jadwalEmptyState ?? false) {
                                $hasFilter = false;
                            }
                        }
                        $showBranchColumn = $branches->count() > 1;
                    @endphp

                    {{-- ── Toolbar: Tambah + Filter Button + Tab toggle ── --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">
                        <div class="d-flex align-items-center" style="gap:8px;">
                            @if(!$isViewOnly)
                                <a href="{{ route('rawreservation.new') }}" id="rrBtnTambah" class="st-btn-brand">
                                    <i class="fa fa-plus"></i> Tambah
                                </a>
                            @endif
                            <button class="st-btn-outline-brown st-btn-filter {{ $hasFilter ? 'is-active' : '' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                                <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                                <i class="fa fa-chevron-down st-btn-filter-chev"></i>
                            </button>
                            <a href="{{ route('export.reservasi', request()->only(['status','branch','doctor','date_from','date_to','promo','affiliate'])) }}" class="st-btn-outline-brown">
                                <i class="fa fa-download"></i> Export CSV
                            </a>
                            @if(canManagePageAccess())
                                <button type="button" class="st-btn-outline-brown" data-pc-modal="rrModalAccessModes" title="Atur akses View Only per role">
                                    <i class="fa fa-user-shield"></i> Kelola Halaman
                                </button>
                            @endif
                        </div>
                        <div class="view-tab-group" style="font-size: 1.2em;">
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'list'])) }}" class="view-tab {{ !request('new') && request('view', 'list') === 'list' ? 'active' : '' }}">
                                <i class="fa fa-list"></i> List
                            </a>
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['new' => 1, 'view' => 'list'])) }}" class="view-tab {{ request('new') ? 'active' : '' }}">
                                <i class="fa fa-star"></i> Reservasi Baru
                                @if(($newCount ?? 0) > 0)
                                    <span class="view-tab-badge">{{ $newCount }}</span>
                                @endif
                            </a>
                            @if(!$isViewOnly)
                                <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'waitlist'])) }}" class="view-tab {{ request('view') === 'waitlist' ? 'active' : '' }}">
                                    <i class="fa fa-hourglass-half"></i> Waiting List
                                    @if(($waitlistCount ?? 0) > 0)
                                        <span class="view-tab-badge">{{ $waitlistCount }}</span>
                                    @endif
                                </a>
                            @endif
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'cal'])) }}" class="view-tab {{ request('view') === 'cal' ? 'active' : '' }}">
                                <i class="fa fa-calendar"></i> Kalender
                            </a>
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'jadwal'])) }}" class="view-tab {{ request('view') === 'jadwal' ? 'active' : '' }}" title="Tampilan ramah-screenshot untuk kirim jadwal ke dokter">
                                <i class="fa fa-user-md"></i> Jadwal Dokter
                            </a>
                        </div>
                    </div>

                    {{-- ── Form filter (Collapsible) ── --}}
                    <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
                        <div class="st-filter-panel">
                            <div class="st-filter-panel__header">
                                <i class="fa fa-sliders-h"></i> Filter Lanjutan
                            </div>
                            <form method="GET" action="{{ route('rawreservation') }}" id="filterForm" class="mb-0">
                                <input type="hidden" name="view" value="{{ request('view', 'list') }}">
                                @if(request('new'))
                                <input type="hidden" name="new" value="1">
                                @endif
                                <div class="row" style="row-gap:14px;">
                                    <div class="col-md-{{ $showBranchColumn ? '3' : '4' }}">
                                        <label class="st-filter-label">Pencarian</label>
                                        <input type="text" class="form-control form-control-sm" name="search" placeholder="Nama / No. HP..." value="{{ $filterSearch }}">
                                    </div>
                                    @if($showBranchColumn)
                                    <div class="col-md-3">
                                        <label class="st-filter-label">Cabang</label>
                                        <select class="form-control form-control-sm" name="branch_id">
                                            <option value="">Semua Cabang</option>
                                            @foreach ($branches as $b)
                                                <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @endif
                                    <div class="col-md-{{ $showBranchColumn ? '3' : '4' }}">
                                        <label class="st-filter-label">Dokter</label>
                                        <select class="form-control form-control-sm" name="doctor_id">
                                            <option value="">Semua Dokter</option>
                                            @foreach ($doctors as $d)
                                                <option value="{{ $d->id }}" {{ $filterDoctor == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-{{ $showBranchColumn ? '3' : '4' }}">
                                        <label class="st-filter-label">Status</label>
                                        <select class="form-control form-control-sm" name="status">
                                            <option value="">Semua Status</option>
                                            @foreach ($statuses as $k => $opt)
                                                <option value="{{ $k }}" {{ $filterStatus == $k ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="st-filter-label">Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $filterDateFrom }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="st-filter-label">Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $filterDateTo }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="st-filter-label">Promo</label>
                                        <select class="form-control form-control-sm" name="promo_id">
                                            <option value="">Semua Promo</option>
                                            @foreach ($promos as $p)
                                                <option value="{{ $p->id }}" {{ $filterPromo == $p->id ? 'selected' : '' }}>{{ $p->promo_code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="st-filter-label">Affiliate</label>
                                        <select class="form-control form-control-sm" name="affiliate_id">
                                            <option value="">Semua Affiliate</option>
                                            @foreach ($affiliates as $a)
                                                <option value="{{ $a->id }}" {{ $filterAffiliate == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <div class="st-filter-actions">
                                            <button type="submit" class="st-filter-submit">
                                                <i class="fa fa-search"></i> Terapkan
                                            </button>
                                            <a href="{{ route('rawreservation', ($view ?? 'list') === 'list' ? [] : ['view' => $view]) }}" class="st-filter-reset" title="Reset Filter">
                                                <i class="fa fa-times"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ── Info hasil filter ── --}}
                    @if ($showFilterChips)
                        @php
                            $chips = [];
                            $removeUrl = function(array $keys) {
                                return url()->current() . '?' . http_build_query(array_merge(request()->except(array_merge($keys, ['page'])), []));
                            };
                            $fmtDate = function($d) { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '...'; };
                            if ($filterSearch)   $chips[] = ['label' => 'Cari: '    . $filterSearch, 'url' => $removeUrl(['search'])];
                            if ($filterBranch)   $chips[] = ['label' => 'Cabang: '  . ($branches->firstWhere('id', $filterBranch)?->name ?? '-'), 'url' => $removeUrl(['branch_id'])];
                            if ($filterDoctor)   $chips[] = ['label' => 'Dokter: '  . ($doctors->firstWhere('id', $filterDoctor)?->name ?? '-'), 'url' => $removeUrl(['doctor_id'])];
                            if ($filterStatus)   $chips[] = ['label' => 'Status: '  . ($statuses[$filterStatus]['label'] ?? $filterStatus), 'url' => $removeUrl(['status'])];
                            if ($filterDateFrom || $filterDateTo) $chips[] = ['label' => 'Tgl: ' . $fmtDate($filterDateFrom) . '–' . $fmtDate($filterDateTo), 'url' => $removeUrl(['date_from','date_to'])];
                            if ($filterPromo)    $chips[] = ['label' => 'Promo: '   . ($promos->firstWhere('id', $filterPromo)?->promo_code ?? '-'), 'url' => $removeUrl(['promo_id'])];
                            if ($filterAffiliate)$chips[] = ['label' => 'Affiliate: '. ($affiliates->firstWhere('id', $filterAffiliate)?->name ?? '-'), 'url' => $removeUrl(['affiliate_id'])];
                            if (request('new'))  $chips[] = ['label' => 'Reservasi Baru', 'url' => $removeUrl(['new'])];
                            if (request('reservation_id')) $chips[] = ['label' => 'Reservasi Spesifik', 'url' => $removeUrl(['reservation_id'])];
                        @endphp
                        @if (count($chips))
                            <div class="st-filter-chips">
                                <span class="st-filter-chips__label"><i class="fa fa-filter"></i> Filter aktif:</span>
                                @foreach ($chips as $chip)
                                    <span class="st-filter-chip">
                                        {{ $chip['label'] }}
                                        <a href="{{ $chip['url'] }}" title="Hapus filter">&times;</a>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if (! (request('view', 'list') === 'list' && !request('new')))
                            {{-- Tab List: count sudah tampil di quick-segment row → hindari dobel --}}
                            <div class="st-info-count">
                                <i class="fa fa-list"></i>
                                Menampilkan <strong>{{ $reservations->total() }}</strong> reservasi
                            </div>
                        @endif
                    @endif

                    {{-- ── Quick segments (filter cepat) — hanya tab List ── --}}
                    @if(request('view', 'list') === 'list' && !request('new'))
                        @php
                            $segToday  = now()->toDateString();
                            $segBesok  = now()->addDay()->toDateString();
                            $segWStart = now()->startOfWeek(\Carbon\Carbon::SUNDAY)->toDateString();
                            $segWEnd   = now()->endOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();
                            $segPending = \App\Models\RawCusReservation::STATUS_PENDING;
                            $segBase = request()->except(['status', 'date_from', 'date_to', 'page', 'new', 'view']);
                            $segUrl = function ($extra) use ($segBase) {
                                return route('rawreservation', array_merge($segBase, ['view' => 'list'], $extra));
                            };
                            $qS = request('status'); $qF = request('date_from'); $qT = request('date_to');
                            $segAct = [
                                'belum'  => $qS === $segPending,
                                'hari'   => !$qS && $qF === $segToday  && $qT === $segToday,
                                'besok'  => !$qS && $qF === $segBesok  && $qT === $segBesok,
                                'minggu' => !$qS && $qF === $segWStart && $qT === $segWEnd,
                                'semua'  => !$qS && !$qF && !$qT,
                            ];
                        @endphp
                        <div class="st-quickseg">
                            <div class="st-quickseg__tabs">
                                <a href="{{ $segUrl(['status' => $segPending]) }}" class="st-seg {{ $segAct['belum'] ? 'active' : '' }}">Belum dikonfirmasi</a>
                                <a href="{{ $segUrl(['date_from' => $segToday, 'date_to' => $segToday]) }}" class="st-seg {{ $segAct['hari'] ? 'active' : '' }}">Hari ini</a>
                                <a href="{{ $segUrl(['date_from' => $segBesok, 'date_to' => $segBesok]) }}" class="st-seg {{ $segAct['besok'] ? 'active' : '' }}">Besok</a>
                                <a href="{{ $segUrl(['date_from' => $segWStart, 'date_to' => $segWEnd]) }}" class="st-seg {{ $segAct['minggu'] ? 'active' : '' }}">Minggu ini</a>
                                <a href="{{ $segUrl([]) }}" class="st-seg {{ $segAct['semua'] ? 'active' : '' }}">Semua</a>
                            </div>
                            <span class="st-quickseg__count"><i class="fa fa-list"></i> Menampilkan <strong>{{ $reservations->total() }}</strong> reservasi</span>
                        </div>
                    @endif

                    {{-- ══════════════════════════════════════
                         TAB: LIST
                    ══════════════════════════════════════ --}}
                    <div id="viewList" style="{{ in_array(request('view', 'list'), ['cal', 'jadwal']) ? 'display:none;' : '' }}">
                        <div class="st-table-wrap st-table-wrap--scroll">
                        <table class="st-table st-table--clickable" cellspacing="0" style="white-space: nowrap;">
                            <thead>
                                <tr>
                                    <th>Pasien</th>
                                    <th>Dokter</th>
                                    @if($showBranchColumn)<th>Cabang</th>@endif
                                    <th>Jam</th>
                                    <th>Perawatan</th>
                                    <th style="min-width:150px;">Keterangan</th>
                                    <th>Follow-up</th>
                                    <th>Status</th>
                                    <th class="st-chev-th" aria-hidden="true"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $hariList = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                                    // Jumlah kolom tbody (utk colspan day-header): Pasien,Dokter,Jam,Perawatan,
                                    // Keterangan,Follow-up,Status,chevron = 8 (+Cabang bila tampil).
                                    $colCount = 8 + ($showBranchColumn ? 1 : 0);
                                    // Jumlah reservasi per tanggal (dalam halaman ini) utk badge day-header.
                                    $dayCounts = collect($reservations->items())->groupBy(fn($r) => optional($r->date)->format('Y-m-d'));
                                    $prevDate = null;
                                    // Label status PENDEK untuk pill di list (design pakai short label).
                                    $rrShortMap = [
                                        \App\Models\RawCusReservation::STATUS_PENDING   => 'Menunggu',
                                        \App\Models\RawCusReservation::STATUS_CONFIRMED  => 'Dikonfirmasi',
                                        \App\Models\RawCusReservation::STATUS_WAITLIST   => 'Waiting',
                                        \App\Models\RawCusReservation::STATUS_COMPLETED  => 'Selesai',
                                        \App\Models\RawCusReservation::STATUS_CANCELLED  => 'Batal',
                                    ];
                                @endphp
                                @foreach ($reservations as $key => $row)
                                    @php
                                        $si = $row->status_info;
                                        // Cek apakah data ini dibuat kurang dari 6 jam yang lalu
                                        $isNew = false;
                                        if (!empty($row->created_at)) {
                                            $isNew = \Carbon\Carbon::parse($row->created_at)->diffInHours(\Carbon\Carbon::now()) < 6;
                                        }
                                        $curDate = optional($row->date)->format('Y-m-d');
                                        // Payload untuk Detail drawer (dibaca JS saat baris diklik)
                                        $rrWa = $row->customer?->phone ? preg_replace('/[^0-9]/', '', $row->customer->phone) : '';
                                        if ($rrWa && str_starts_with($rrWa, '0')) $rrWa = '62' . substr($rrWa, 1);
                                        $rrStatusOpt = \App\Models\RawCusReservation::statusOptions()[$row->status] ?? null;
                                        $rrTreat = $row->services->map(fn($s) => $s->docService->name ?? null)->filter()->unique()->implode(', ');
                                        if (!$rrTreat && $row->lainnya_text) $rrTreat = 'Keluhan: ' . $row->lainnya_text;
                                        $rrData = [
                                            'id'          => $row->id,
                                            'name'        => $row->customer->name ?? 'Pasien Terhapus',
                                            'phone'       => $row->customer->phone ?? '',
                                            'wa'          => $rrWa,
                                            'status'      => $row->status,
                                            'statusLabel' => $rrStatusOpt['label'] ?? '—',
                                            'statusBg'    => $rrStatusOpt['bg'] ?? '',
                                            'statusColor' => $rrStatusOpt['color'] ?? '',
                                            'jadwal'      => $row->date ? ($hariList[$row->date->dayOfWeek] . ', ' . $row->date->format('d M Y')) : '—',
                                            'time'        => ($row->start_hour && $row->end_hour) ? (\Carbon\Carbon::parse($row->start_hour)->format('H:i') . ' – ' . \Carbon\Carbon::parse($row->end_hour)->format('H:i')) : '',
                                            'doctor'      => $row->doctor->name ?? '—',
                                            'treatment'   => $rrTreat ?: '—',
                                            'keterangan'  => $row->keterangan ?? '',
                                            'fu1'         => $row->follow_up_h_min_1 ?? '',
                                            'fuh'         => $row->follow_up_h ?? '',
                                            'kehadiran'   => $row->keterangan_kehadiran ?? '',
                                            'promo'       => $row->promo->promo_code ?? '',
                                            'promoId'     => $row->raw_promo_id,
                                            'klaim'       => $row->promo_klaim ? 'done' : ($row->raw_promo_id ? 'pending' : 'none'),
                                            'editUrl'     => route('rawreservation.edit', $row->id),
                                            'klaimUrl'    => route('rawreservation.klaim', $row->id),
                                        ];
                                    @endphp
                                    @if ($curDate !== $prevDate)
                                        @php
                                            $prevDate = $curDate;
                                            $hdLabel  = $row->date ? $hariList[$row->date->dayOfWeek] . ', ' . $row->date->format('d M Y') : 'Tanpa Tanggal';
                                            $hdCount  = $curDate ? ($dayCounts[$curDate]->count() ?? 0) : 0;
                                        @endphp
                                        <tr class="st-day-row">
                                            <td colspan="{{ $colCount }}">
                                                <span class="st-day-dot"></span>
                                                <span class="st-day-label">{{ $hdLabel }}</span>
                                                <span class="st-day-count">{{ $hdCount }}</span>
                                            </td>
                                        </tr>
                                    @endif
                                    <tr class="st-row st-row--{{ $row->status }} {{ $isNew ? 'st-row-new' : '' }}"
                                        @if(!$isViewOnly) data-href="{{ route('rawreservation.edit', $row->id) }}" data-rr="{{ json_encode($rrData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" @endif
                                        {!! $isViewOnly ? '' : ($isNew ? 'title="Data baru masuk — klik untuk lihat detail"' : 'title="Klik baris untuk lihat detail"') !!}>
                                        @php
                                            $waPhoneCell = $row->customer?->phone ? preg_replace('/[^0-9]/', '', $row->customer->phone) : '';
                                            if ($waPhoneCell && str_starts_with($waPhoneCell, '0')) $waPhoneCell = '62' . substr($waPhoneCell, 1);
                                        @endphp
                                        <td class="st-pasien-cell" style="white-space: normal; min-width: 165px;">
                                            @if($row->customer)
                                                <span class="st-primary">{{ $row->customer->name }}</span>
                                            @else
                                                <span style="color:#B23A3A;" title="Pasien telah dihapus, assign ulang di halaman edit"><i class="fa fa-exclamation-triangle"></i> Pasien Terhapus</span>
                                            @endif
                                            @if($isNew)
                                                <span class="st-pill st-pill--success ml-1"><i class="fa fa-star"></i> Baru</span>
                                            @endif
                                            @if($row->customer?->phone)
                                                <a href="https://wa.me/{{ $waPhoneCell }}" target="_blank" rel="noopener" class="st-pasien-phone" onclick="event.stopPropagation()" title="Chat via WhatsApp">
                                                    <i class="fab fa-whatsapp"></i> {{ $row->customer->phone }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="st-dokter-cell"><span class="st-trunc" title="{{ $row->doctor->name ?? '' }}">{{ $row->doctor->name ?? '-' }}</span></td>
                                        @if($showBranchColumn)
                                        <td>
                                            @if($row->branch)
                                                <span class="st-branch-chip">{{ $row->branch->name }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        @endif
                                        <td nowrap>
                                            @if($row->start_hour && $row->end_hour)
                                                <span class="st-time">{{ \Carbon\Carbon::parse($row->start_hour)->format('H:i') }} – {{ \Carbon\Carbon::parse($row->end_hour)->format('H:i') }}</span>
                                            @elseif(!empty($row->slot_available))
                                                <span class="st-pill st-pill--success" title="Dokter {{ $row->doctor->name ?? '' }} kini punya slot kosong di tanggal ini — klik baris untuk pilih jam & konfirmasi">
                                                    <i class="fa fa-bolt"></i> Slot tersedia{{ $row->slot_start ? ' · ' . $row->slot_start : '' }}
                                                </span>
                                            @else
                                                <span class="st-muted" title="Belum ada slot — masih waiting list">—</span>
                                            @endif
                                        </td>
                                        <td style="white-space: normal; min-width: 130px; max-width: 170px;">
                                        @php
                                            $uniqueServiceNames = $row->services->map(function($svc) {
                                                return $svc->docService->name ?? null;
                                            })->filter()->unique();
                                        @endphp
                                        @foreach ($uniqueServiceNames as $serviceName)
                                            <span class="st-pill st-pill--neutral st-pill--plain" style="margin:1px 2px 1px 0;">
                                                {{ $serviceName }}
                                            </span>
                                        @endforeach
                                        @if($row->lainnya_text)
                                            <div style="margin-top:4px;">
                                                <span class="st-pill st-pill--warning st-pill--plain" style="white-space:normal; text-align:left;">
                                                    <i class="fa fa-comment-dots"></i>
                                                    Keluhan: {{ $row->lainnya_text }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($uniqueServiceNames->isEmpty() && !$row->lainnya_text)
                                            <span style="color: var(--text-subtle);">-</span>
                                        @endif
                                    </td>
                                    <td class="st-ket-cell" style="white-space: normal; min-width: 120px;">
                                        {{-- Keterangan: inline-editable (subtle), save via endpoint inline (reuse). --}}
                                        <input type="text" class="inline-update st-ket-input"
                                            data-id="{{ $row->id }}" data-field="keterangan"
                                            value="{{ $row->keterangan }}" placeholder="+ catatan"
                                            title="Klik untuk isi/ubah catatan" onclick="event.stopPropagation()">
                                    </td>
                                        @php
                                            $waPhone = $row->customer?->phone ? preg_replace('/[^0-9]/', '', $row->customer->phone) : '';
                                            if ($waPhone && str_starts_with($waPhone, '0')) $waPhone = '62' . substr($waPhone, 1);
                                            $fuLabelMap = [
                                                'confirm'    => '✅ Confirm',
                                                'reschedule' => '🔄 Reschedule',
                                                'cancel'     => '❌ Cancel',
                                                'no_respon'  => '📵 No Respon',
                                            ];
                                            $khLabelMap = [
                                                'hadir'       => '✅ Hadir',
                                                'tidak_hadir' => '❌ Tidak Hadir',
                                                'reschedule'  => '🔄 Reschedule',
                                                'cancel'      => '❌ Cancel',
                                            ];
                                        @endphp
                                        {{-- Follow-up sebelum Status (urutan ikut design). Kolom Promo/Klaim
                                             dipindah ke Detail drawer (fase berikutnya) → list slim spt design. --}}
                                        <td class="st-fu-cell">
                                            {{-- Follow-up read-only chips (edit via Detail drawer / Edit lengkap). --}}
                                            @php $rrAtt = $row->keterangan_kehadiran; @endphp
                                            <div class="st-fu-chips">
                                                <span class="st-fuchip {{ $row->follow_up_h_min_1 ? 'is-done' : '' }}" data-id="{{ $row->id }}" data-fu="h1" title="Follow-up H-1 · {{ $fuLabelMap[$row->follow_up_h_min_1] ?? 'Belum di-follow up' }}">H-1</span>
                                                <span class="st-fuchip {{ $row->follow_up_h ? 'is-done' : '' }}" data-id="{{ $row->id }}" data-fu="h" title="Follow-up H · {{ $fuLabelMap[$row->follow_up_h] ?? 'Belum di-follow up' }}">H</span>
                                                <span class="st-fuchip st-fuchip--att {{ $rrAtt === 'hadir' ? 'is-hadir' : ($rrAtt === 'tidak_hadir' ? 'is-tidak' : '') }}" data-id="{{ $row->id }}" data-fu="att" title="Kehadiran · {{ $khLabelMap[$rrAtt] ?? 'Belum' }}">{{ $rrAtt === 'hadir' ? '✓' : ($rrAtt === 'tidak_hadir' ? '✕' : '?') }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            {{-- Status read-only (edit via Detail drawer). data-id utk sync dari drawer. --}}
                                            @php $stCur = \App\Models\RawCusReservation::statusOptions()[$row->status] ?? null; @endphp
                                            @if($stCur)
                                                <span class="st-pill st-rowstatus" data-id="{{ $row->id }}" style="background:{{ $stCur['bg'] }};color:{{ $stCur['color'] }};">{{ $rrShortMap[$row->status] ?? $stCur['label'] }}</span>
                                            @else
                                                <span class="st-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="st-chev-cell"><i class="fa fa-chevron-right"></i></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        <div class="st-pagination-bar">
                            <div class="st-perpage">
                                <span>Tampilkan</span>
                                <select onchange="window.location.href=this.value;">
                                    @foreach([10, 25, 50, 100] as $pp)
                                        <option value="{{ route('rawreservation', array_merge(request()->all(), ['per_page' => $pp, 'page' => 1])) }}" {{ $perPage == $pp ? 'selected' : '' }}>{{ $pp }}</option>
                                    @endforeach
                                </select>
                                <span>data per halaman</span>
                            </div>
                            <div>{{ $reservations->appends(request()->all())->links() }}</div>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════
                         TAB: CALENDAR
                    ══════════════════════════════════════ --}}
                    <div id="viewCal" style="{{ request('view', 'list') === 'cal' ? '' : 'display:none;' }}">
                        <div class="st-cal-wrap">
                            <div class="st-cal-toolbar">
                                <div class="st-cal-toolbar__nav">
                                    <button class="st-cal-nav-btn" id="prevMonth" title="Bulan sebelumnya"><i class="fa fa-chevron-left"></i></button>
                                    <span class="st-cal-month-label" id="monthLabel"></span>
                                    <button class="st-cal-nav-btn" id="nextMonth" title="Bulan berikutnya"><i class="fa fa-chevron-right"></i></button>
                                </div>
                                <button class="st-cal-today-btn" id="todayBtn"><i class="fa fa-crosshairs"></i> Hari Ini</button>
                            </div>
                            <div class="st-cal-grid st-cal-grid--head">
                                @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $d)
                                    <div class="st-cal-dow">{{ $d }}</div>
                                @endforeach
                            </div>
                            <div class="st-cal-grid" id="calBody"></div>
                            <div class="st-cal-legend">
                                <span class="st-cal-legend-item"><span class="st-cal-legend-dot st-cal-legend-dot--pending"></span>Menunggu Konfirmasi</span>
                                <span class="st-cal-legend-item"><span class="st-cal-legend-dot st-cal-legend-dot--confirmed"></span>Dikonfirmasi</span>
                                <span class="st-cal-legend-item"><span class="st-cal-legend-dot st-cal-legend-dot--waitlist"></span>Waiting List</span>
                                <span class="st-cal-legend-item"><span class="st-cal-legend-dot st-cal-legend-dot--cancelled"></span>Dibatalkan</span>
                                <span class="st-cal-legend-item"><span class="st-cal-legend-dot st-cal-legend-dot--completed"></span>Selesai</span>
                            </div>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════
                         TAB: JADWAL DOKTER (ramah-screenshot)
                    ══════════════════════════════════════ --}}
                    @if(request('view') === 'jadwal')
                        @include('crud.reservation._jadwal_dokter', [
                            'reservations'     => $reservations,
                            'jadwalEmptyState' => $jadwalEmptyState ?? false,
                            'doctors'          => $doctors,
                            'branches'         => $branches,
                            'filterDoctor'     => $filterDoctor,
                            'filterBranch'     => $filterBranch,
                            'filterDateFrom'   => $filterDateFrom,
                            'filterDateTo'     => $filterDateTo,
                        ])
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ════════ Detail Drawer (slide-in kanan) — dipopulasi via JS dari data-rr ════════ --}}
@if(!$isViewOnly)
<div id="rrDrawer" class="rr-drawer" aria-hidden="true">
    <div class="rr-drawer__scrim" data-rr-close></div>
    <aside class="rr-drawer__panel" role="dialog" aria-label="Detail Reservasi">
        <div class="rr-drawer__head">
            <button type="button" class="rr-drawer__close" data-rr-close aria-label="Tutup">&times;</button>
            <div class="rr-drawer__eyebrow">DETAIL RESERVASI</div>
            <h3 class="rr-drawer__name" id="rrdName">—</h3>
            <div class="rr-drawer__headmeta">
                <a class="rr-drawer__wa" id="rrdWa" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> <span id="rrdPhone"></span></a>
                <span class="st-pill rr-drawer__statuspill" id="rrdStatusPill">—</span>
            </div>
        </div>
        <div class="rr-drawer__body">
            <div class="rr-drawer__cards">
                <div class="rr-card">
                    <div class="rr-card__label">JADWAL</div>
                    <div class="rr-card__val" id="rrdJadwal">—</div>
                    <div class="rr-card__sub" id="rrdTime"></div>
                </div>
                <div class="rr-card">
                    <div class="rr-card__label">DOKTER</div>
                    <div class="rr-card__val" id="rrdDoctor">—</div>
                </div>
            </div>
            <div class="rr-card rr-card--wide">
                <div class="rr-card__label">PERAWATAN</div>
                <div class="rr-card__val" id="rrdTreatment">—</div>
            </div>

            <div class="rr-section">
                <div class="rr-section__title">STATUS RESERVASI</div>
                <div class="rr-seg" id="rrdStatusSeg">
                    @foreach (\App\Models\RawCusReservation::statusOptions() as $k => $opt)
                        <button type="button" class="rr-seg__btn" data-field="status" data-value="{{ $k }}" data-bg="{{ $opt['bg'] }}" data-color="{{ $opt['color'] }}">{{ $opt['label'] }}</button>
                    @endforeach
                </div>
            </div>

            <div class="rr-section">
                <div class="rr-section__title">FOLLOW-UP</div>
                <div class="rr-fu">
                    <div class="rr-fu__row">
                        <span class="rr-fu__badge rr-fu__badge--h1">H-1</span>
                        <select class="rr-fu__sel" id="rrdFu1Sel">
                            <option value="">Belum di-follow up</option>
                            <option value="confirm">✅ Confirm</option>
                            <option value="reschedule">🔄 Reschedule</option>
                            <option value="cancel">❌ Cancel</option>
                            <option value="no_respon">📵 No Respon</option>
                        </select>
                        <a class="rr-fu__wa" id="rrdFu1Wa" target="_blank" rel="noopener" title="Chat WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                    <div class="rr-fu__row">
                        <span class="rr-fu__badge rr-fu__badge--h">H</span>
                        <select class="rr-fu__sel" id="rrdFuhSel">
                            <option value="">Belum di-follow up</option>
                            <option value="confirm">✅ Confirm</option>
                            <option value="reschedule">🔄 Reschedule</option>
                            <option value="cancel">❌ Cancel</option>
                            <option value="no_respon">📵 No Respon</option>
                        </select>
                        <a class="rr-fu__wa" id="rrdFuhWa" target="_blank" rel="noopener" title="Chat WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
            </div>

            <div class="rr-section">
                <div class="rr-section__title">KEHADIRAN</div>
                <div class="rr-seg" id="rrdKehadiranSeg">
                    <button type="button" class="rr-seg__btn" data-field="keterangan_kehadiran" data-value="hadir">Hadir</button>
                    <button type="button" class="rr-seg__btn" data-field="keterangan_kehadiran" data-value="tidak_hadir">Tidak Hadir</button>
                    <button type="button" class="rr-seg__btn" data-field="keterangan_kehadiran" data-value="">Belum</button>
                </div>
            </div>

            <div class="rr-section">
                <div class="rr-section__title">KETERANGAN</div>
                <textarea id="rrdKeterangan" class="rr-textarea" rows="3" placeholder="Tambahkan catatan untuk reservasi ini..."></textarea>
            </div>

            <div class="rr-section">
                <div class="rr-section__title">PROMO</div>
                <div id="rrdPromoWrap"></div>
            </div>
        </div>
        <div class="rr-drawer__foot">
            <button type="button" class="rr-btn rr-btn--confirm" id="rrdConfirm"><i class="fa fa-check"></i> Konfirmasi</button>
            <a class="rr-btn rr-btn--edit" id="rrdEdit" href="#"><i class="fa fa-edit"></i> Edit lengkap</a>
            <button type="button" class="rr-btn rr-btn--cancel" id="rrdCancel">Batal</button>
        </div>
    </aside>
</div>

{{-- ════════ Form Drawer (Tambah / Edit lengkap, 660px) — isi di-fetch via AJAX ════════ --}}
<div id="rrFormDrawer" class="rr-drawer rr-drawer--form" aria-hidden="true">
    <div class="rr-drawer__scrim" data-rrform-close></div>
    <aside class="rr-drawer__panel" role="dialog" aria-label="Form Reservasi">
        <div class="rr-drawer__head">
            <button type="button" class="rr-drawer__close" data-rrform-close aria-label="Tutup">&times;</button>
            <div class="rr-drawer__eyebrow">RESERVASI</div>
            <h3 class="rr-drawer__name" id="rrFormTitle">Reservasi</h3>
        </div>
        <div class="rr-drawer__body" id="rrFormBody">
            {{-- Fase 1a: placeholder. Form di-inject di Fase 1b. --}}
            <div id="rrFormPlaceholder" class="text-muted" style="padding:24px 4px;">
                <i class="fa fa-spinner fa-spin"></i> Memuat form…
            </div>
        </div>
    </aside>
</div>
@endif

{{-- Modal --}}
<div class="modal fade st-modal" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="st-modal-header">
                <div class="st-title-wrap">
                    <svg class="st-title-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <div>
                        <h5 class="st-modal-title">Detail Reservasi</h5>
                        <p class="st-modal-subtitle" id="modalSubtitle"></p>
                    </div>
                </div>
                <button type="button" class="st-modal-close" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="st-modal-body" id="modalBody"></div>
            <div class="st-modal-footer">
                <p class="st-footer-note" id="modalFooterNote">Klik jadwal pasien untuk lihat detail reservasi</p>
                <div style="display:flex; gap:8px; align-items:center;">
                    <a href="#" id="modalDetailBtn" class="btn btn-primary btn-sm" style="display:none;">
                        <i class="fa fa-edit"></i> Detail / Edit
                    </a>
                    <button type="button" class="st-btn-tutup" id="modalCloseBtn" data-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Kelola Akses Halaman (Super Admin / Admin General only) --}}
@if(canManagePageAccess())
    @include('crud.reservation._modal_access_modes', [
        'accessModeGroups' => $accessModeGroups ?? [],
    ])
@endif
@endsection

@section('css')
{{-- Redesign Fase 1 — re-skin scoped (aktif via body.redesign-reservasi). --}}
<link rel="stylesheet" href="{{ asset('assets/css/reservasi-redesign.css') }}?v={{ @filemtime(public_path('assets/css/reservasi-redesign.css')) ?: '1' }}">
{{-- Flatpickr — dibutuhkan form Tambah/Edit yang dimuat ke drawer (date picker). --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
/* ── Tab toggle (brand-cream, konsisten dengan components.sweettreats.css) ── */
.view-tab-group {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 4px;
    background: var(--cream-header);
    border: 1px solid var(--divider-strong);
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
}
.view-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none !important;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    outline: none;
}
.view-tab:hover {
    color: #ffffff;
    background: var(--brown);
    text-decoration: none !important;
}
.view-tab.active {
    background: var(--brown);
    color: #ffffff;
    box-shadow: 0 1px 3px rgba(61, 26, 0, 0.08);
}
.view-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    margin-left: 4px;
    border-radius: 9px;
    background: #f59e0b;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    transform-origin: center;
}
.view-tab.active .view-tab-badge { background: #fff; color: #b45309; animation: none; }

/* Badge berkedip + pulse di tab yang BELUM aktif supaya menarik perhatian. */
.view-tab:not(.active) .view-tab-badge {
    animation: pcBadgePulse 1.6s ease-in-out infinite;
}
@keyframes pcBadgePulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.55);
    }
    50% {
        transform: scale(1.18);
        box-shadow: 0 0 0 7px rgba(245, 158, 11, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .view-tab:not(.active) .view-tab-badge { animation: none; }
}

/* ── Modal ── */
#modalBody tr td:first-child { font-weight:600; color:var(--brown); width:30%; white-space:nowrap; }
#modalBody tr td { border-color:rgb(208,196,191) !important; vertical-align:middle; }
.modal-hover-row:hover td { background-color: rgb(250,245,243) !important; }
</style>
@endsection

@section('js')
{{-- Flatpickr + logika form reservasi (dipakai form Tambah/Edit yang di-inject ke drawer). --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
<script>
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

@php
    $calReservationsMapped = $calendarReservations->map(function($r) {
        $r->is_new = $r->created_at ? \Carbon\Carbon::parse($r->created_at)->diffInHours(\Carbon\Carbon::now()) < 6 : false;
        return $r;
    });
@endphp
const RAW_RESERVATIONS = @json($calReservationsMapped).map(r => {
    if (r.date) {
        const d = new Date(r.date);
        r.date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    return r;
});

const STATUS_OPTIONS = @json(\App\Models\RawCusReservation::statusOptions());
const FU_OPTIONS = {
    '': '-- --',
    'confirm': '✅ Confirm',
    'reschedule': '🔄 Reschedule',
    'cancel': '❌ Cancel',
    'no_respon': '📵 No Respon'
};
const KEHADIRAN_OPTIONS = {
    '': '-- --',
    'hadir': '✅ Hadir',
    'tidak_hadir': '❌ Tidak Hadir',
    'reschedule': '🔄 Reschedule',
    'cancel': '❌ Cancel'
};

// Flag global: kalau true, modal calendar render kontrol sebagai static text
// dan tombol Detail/Edit di-hide.
const IS_VIEW_ONLY = @json($isViewOnly ?? false);

function createSelect(options, field, selectedValue, reservationId) {
    // View-only mode: render static label, bukan dropdown.
    if (IS_VIEW_ONLY) {
        const opt = options[selectedValue || ''] ?? options[''];
        const label = typeof opt === 'object' ? opt.label : opt;
        return `<span class="rr-static-text" style="font-weight:600;">${label || '—'}</span>`;
    }
    let html = `<select class="form-control form-control-sm inline-update" data-id="${reservationId}" data-field="${field}" data-value="${selectedValue || ''}" style="min-width:120px;">`;
    for (const key in options) {
        const label = typeof options[key] === 'object' ? options[key].label : options[key];
        const isSelected = key == selectedValue ? 'selected' : '';
        html += `<option value="${key}" ${isSelected}>${label}</option>`;
    }
    html += `</select>`;
    return html;
}

const STATUS_CFG = {
    pending:   { label:'Menunggu Konfirmasi', cls:'st-cal-event--pending' },
    confirmed: { label:'Dikonfirmasi',        cls:'st-cal-event--confirmed' },
    waitlist:  { label:'Waiting List',        cls:'st-cal-event--waitlist' },
    ongoing:   { label:'Berlangsung',         cls:'st-cal-event--ongoing' },
    cancelled: { label:'Dibatalkan',          cls:'st-cal-event--cancelled' },
    done:      { label:'Selesai',             cls:'st-cal-event--completed' },
    completed: { label:'Selesai',             cls:'st-cal-event--completed' },
    selesai:   { label:'Selesai',             cls:'st-cal-event--completed' },
};

const MONTHS_ID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const today = new Date();
let curYear = today.getFullYear(), curMonth = today.getMonth();

function buildEventMap() {
    const map = {};
    RAW_RESERVATIONS.forEach(r => {
        if (!r.date) return;
        const key = r.date.substring(0, 10);
        if (!map[key]) map[key] = [];
        map[key].push(r);
    });
    // Urutkan jam pagi → sore
    Object.keys(map).forEach(k => {
        map[k].sort((a, b) => (a.start_hour||'').localeCompare(b.start_hour||''));
    });
    return map;
}

function makeEventCard(ev) {
    const now = new Date();
    const nowTime = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
    const nowDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');

    const cfg    = STATUS_CFG[ev.status] || { label: ev.status||'-', cls:'st-cal-event--default' };
    const jam    = (ev.start_hour||'').substring(0, 5);
    const jamSelesai = (ev.end_hour||'').substring(0, 5);
    const nama   = ev.customer ? escapeHtml(ev.customer.name) : '<span class="text-danger">Pasien Terhapus</span>';
    const dokter = ev.doctor ? escapeHtml(ev.doctor.name) : '-';

    let servicesHTML = '';
    if (ev.services && ev.services.length > 0) {
        const serviceNames = ev.services.map(s => s.doc_service ? escapeHtml(s.doc_service.name) : '').filter(Boolean).join(', ');
        servicesHTML = `<i class="fa fa-stethoscope" title="${serviceNames}"></i>`;
    } else if (ev.lainnya_text) {
        servicesHTML = `<i class="fa fa-comment-dots" title="Keluhan: ${escapeHtml(ev.lainnya_text)}"></i>`;
    }

    let promoHTML = '';
    if (ev.promo) {
        promoHTML = `<i class="fa fa-tag" title="Promo: ${escapeHtml(ev.promo.promo_code)}"></i>`;
    }

    // Deteksi apakah waktu sudah terlewat
    let isPast = false;
    if (ev.date < nowDate) {
        isPast = true;
    } else if (ev.date === nowDate && jamSelesai && jamSelesai < nowTime) {
        isPast = true;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'st-cal-event-wrap';

    const card = document.createElement('div');
    card.className = 'st-cal-event ' + cfg.cls
        + (isPast ? ' st-cal-event--past' : '')
        + (ev.is_new ? ' st-cal-event--new' : '');

    const iconsHtml = (servicesHTML || promoHTML)
        ? `<span class="st-cal-event__icons">${servicesHTML}${promoHTML}</span>`
        : '';
    const newBadge = ev.is_new
        ? ` <span class="st-card__badge-new" style="font-size:8px;padding:1px 5px;"><i class="fa fa-star"></i></span>`
        : '';

    card.innerHTML = `
        <div class="st-cal-event__top">
            <div class="st-cal-event__header">
                <span class="st-cal-event__time"><i class="fa fa-clock"></i>${jam}</span>
                ${iconsHtml}
            </div>
            <span class="st-cal-event__name"><i class="fa fa-user"></i>${nama}${newBadge}</span>
            <span class="st-cal-event__doctor"><i class="fa fa-user-md"></i>${dokter}</span>
        </div>
        <div class="st-cal-event__status">${cfg.label}</div>
    `;
    card.addEventListener('click', () => { lastAllModalCtx = null; showModal(ev); });
    wrapper.appendChild(card);
    return wrapper;
}

function renderCalendar(year, month) {
    const eventMap = buildEventMap();
    document.getElementById('monthLabel').textContent = MONTHS_ID[month] + ' ' + year;
    const body = document.getElementById('calBody');
    body.innerHTML = '';
    const firstDay    = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month+1, 0).getDate();
    const daysInPrev  = new Date(year, month, 0).getDate();

    for (let i = 0; i < 42; i++) {
        const cell = document.createElement('div');
        cell.className = 'st-cal-day';
        let dayNum, dateObj;
        if (i < firstDay) {
            dayNum  = daysInPrev - firstDay + 1 + i;
            dateObj = new Date(year, month-1, dayNum);
            cell.classList.add('st-cal-day--other');
        } else if (i >= firstDay + daysInMonth) {
            dayNum  = i - firstDay - daysInMonth + 1;
            dateObj = new Date(year, month+1, dayNum);
            cell.classList.add('st-cal-day--other');
        } else {
            dayNum  = i - firstDay + 1;
            dateObj = new Date(year, month, dayNum);
        }
        if (dateObj.toDateString() === today.toDateString()) cell.classList.add('st-cal-day--today');

        const dateDiv = document.createElement('div');
        dateDiv.innerHTML = `<span class="st-cal-date">${dayNum}</span>`;
        cell.appendChild(dateDiv);

        const yy  = dateObj.getFullYear();
        const mm  = String(dateObj.getMonth()+1).padStart(2,'0');
        const dd  = String(dateObj.getDate()).padStart(2,'0');
        const key = `${yy}-${mm}-${dd}`;
        const events = eventMap[key] || [];

        events.slice(0, 2).forEach(ev => cell.appendChild(makeEventCard(ev)));

        if (events.length > 2) {
            const more = document.createElement('span');
            more.className   = 'st-cal-more';
            more.textContent = `+${events.length - 2} lainnya`;
            more.addEventListener('click', () => showAllModal(key, events));
            cell.appendChild(more);
        }
        body.appendChild(cell);
    }
}

function showModal(ev) {
    const cfg = STATUS_CFG[ev.status] || { label: ev.status || '-', style: 'background:#eee;color:#333' };
    const tgl = ev.date ? ev.date.substring(0,10).split('-').reverse().join('/') : '-';
    const jam = (ev.start_hour||'').substring(0,5) + ' – ' + (ev.end_hour||'').substring(0,5);

    let servicesHtml = ev.services.map(svc => {
        return `<span class="badge badge-light border" style="font-size:0.75rem;margin:1px">${svc.doc_service ? escapeHtml(svc.doc_service.name) : '-'}</span>`;
    }).join(' ');
    if (ev.lainnya_text) {
        servicesHtml += `<div style="margin-top:4px;">
            <span class="badge badge-warning" style="font-size:0.72rem; white-space:normal; text-align:left; padding:3px 6px;">
                <i class="fa fa-comment-dots"></i> Keluhan: ${escapeHtml(ev.lainnya_text)}
            </span>
        </div>`;
    }
    if (!servicesHtml) servicesHtml = '<span class="text-muted">-</span>';

    let promoHtml = ev.promo ? `<span class="badge badge-info" style="font-size:0.75rem">${escapeHtml(ev.promo.promo_code)}</span>` : '<span class="text-muted">-</span>';

    let klaimHtml = '<span class="text-muted">-</span>';
    if (ev.raw_promo_id) {
        if (ev.promo_klaim) {
            klaimHtml = `<span class="badge badge-success"><i class="fa fa-check"></i> Diklaim</span>`;
        } else {
            const klaimRoute = '{{ route("rawreservation.klaim", ["id" => ":id"]) }}'.replace(':id', ev.id);
            klaimHtml = `
                <form method="POST" action="${klaimRoute}" onsubmit="return confirm('Konfirmasi klaim promo untuk reservasi ini?')">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="PATCH">
                    <button type="submit" class="btn btn-sm btn-warning" style="padding:2px 8px; font-size:0.75rem">
                        <i class="fa fa-tag"></i> Klaim
                    </button>
                </form>
            `;
        }
    }

    const statusSelect = createSelect(STATUS_OPTIONS, 'status', ev.status, ev.id);
    const fu1Select = createSelect(FU_OPTIONS, 'follow_up_h_min_1', ev.follow_up_h_min_1, ev.id);
    const fuHSelect = createSelect(FU_OPTIONS, 'follow_up_h', ev.follow_up_h, ev.id);
    const kehadiranSelect = createSelect(KEHADIRAN_OPTIONS, 'keterangan_kehadiran', ev.keterangan_kehadiran, ev.id);

    let waPhone = (ev.customer && ev.customer.phone) ? String(ev.customer.phone).replace(/[^0-9]/g, '') : '';
    if (waPhone && waPhone.startsWith('0')) waPhone = '62' + waPhone.substring(1);
    const waBtn = waPhone
        ? `<a href="https://wa.me/${waPhone}" target="_blank" class="btn btn-success btn-sm" style="padding:4px 7px; margin-left:4px;" title="Follow up via WhatsApp"><i class="fab fa-whatsapp"></i></a>`
        : '';
    const fu1Cell = `<div class="d-flex align-items-center">${fu1Select}${waBtn}</div>`;
    const fuHCell = `<div class="d-flex align-items-center">${fuHSelect}${waBtn}</div>`;

    document.getElementById('modalSubtitle').textContent = `${tgl} · ${jam}`;
    document.getElementById('modalBody').innerHTML = `
        <div style="padding:16px 22px;">
        <table class="table table-sm mb-0" style="font-size:0.88rem;">
            <tbody>
                <tr><td>Pasien</td><td>${ev.customer ? escapeHtml(ev.customer.name) : '<span class="text-danger">Pasien Terhapus</span>'}</td></tr>
                <tr><td>Dokter</td><td>${ev.doctor ? escapeHtml(ev.doctor.name) : '-'}</td></tr>
                <tr><td>Cabang</td><td>${ev.branch ? escapeHtml(ev.branch.name) : '-'}</td></tr>
                <tr><td>Tanggal</td><td>${tgl}</td></tr>
                <tr><td>Jam</td><td>${jam}</td></tr>
                <tr style="border-top: 2px solid #ddd;"><td valign="top">Perawatan</td><td style="white-space:normal;">${servicesHtml}</td></tr>
                <tr><td>Promo</td><td>${promoHtml}</td></tr>
                <tr><td>Klaim</td><td>${klaimHtml}</td></tr>
                <tr><td valign="top">Keterangan</td><td style="white-space:normal;">
                    ${IS_VIEW_ONLY
                        ? `<div class="rr-static-text">${ev.keterangan ? escapeHtml(ev.keterangan) : '—'}</div>`
                        : `<textarea class="form-control form-control-sm inline-update" rows="2"
                              data-id="${ev.id}" data-field="keterangan"
                              placeholder="Isi keterangan...">${ev.keterangan ? escapeHtml(ev.keterangan) : ''}</textarea>`}
                </td></tr>
                <tr style="border-top: 2px solid #ddd;"><td>Status</td><td>${statusSelect}</td></tr>
                <tr><td>FU H-1</td><td>${fu1Cell}</td></tr>
                <tr><td>FU H</td><td>${fuHCell}</td></tr>
                <tr><td>Kehadiran</td><td>${kehadiranSelect}</td></tr>
            </tbody>
        </table>
        </div>
    `;
    const detailRoute = '{{ route("rawreservation.edit", ["id" => ":id"]) }}'.replace(':id', ev.id);
    document.getElementById('modalDetailBtn').href = detailRoute;
    document.getElementById('modalDetailBtn').innerHTML = '<i class="fa fa-edit"></i> Detail / Edit Reservasi';
    // Sembunyikan tombol Detail/Edit kalau user view-only.
    document.getElementById('modalDetailBtn').style.display = IS_VIEW_ONLY ? 'none' : '';
    document.getElementById('modalFooterNote').style.visibility = 'hidden';
    $('#eventModal').modal('show');
}

const STATUS_DOT_MAP = {
    'pending':   's-menunggu',
    'waitlist':  's-waitlist',
    'confirmed': 's-dikonfirmasi',
    'ongoing':   's-dikonfirmasi',
    'done':      's-selesai',
    'completed': 's-selesai',
    'selesai':   's-selesai',
    'cancelled': 's-batal',
    'cancel':    's-batal',
    'dibatalkan':'s-batal',
};

function statusDotClass(status) {
    if (!status) return 's-default';
    const key = String(status).toLowerCase();
    return STATUS_DOT_MAP[key] || 's-default';
}

function formatLongDate(dateKey) {
    const [y, m, d] = dateKey.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    const days  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return `${days[dt.getDay()]}, ${d.toString().padStart(2,'0')} ${months[m-1]} ${y}`;
}

let lastAllModalCtx = null;

function setCloseBtnTutup() {
    const $btn = $('#modalCloseBtn');
    $btn.text('Tutup').attr('data-dismiss', 'modal').off('click.kembali');
}
function setCloseBtnKembali() {
    const $btn = $('#modalCloseBtn');
    $btn.text('← Kembali').removeAttr('data-dismiss').off('click.kembali').on('click.kembali', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        if (lastAllModalCtx) {
            const ctx = lastAllModalCtx; // simpan dulu, akan di-overwrite oleh showAllModal
            const fresh = ctx.events.map(ev =>
                RAW_RESERVATIONS.find(r => String(r.id) === String(ev.id)) || ev
            );
            showAllModal(ctx.dateKey, fresh);
        }
    });
}

function showAllModal(dateKey, events) {
    lastAllModalCtx = { dateKey, events };
    setCloseBtnTutup();
    document.getElementById('modalSubtitle').textContent =
        `${formatLongDate(dateKey)} · ${events.length} jadwal`;

    const rows = events.map(ev => {
        const cfg = STATUS_CFG[ev.status] || { label: ev.status || '-' };
        const dotClass = statusDotClass(ev.status);

        const services = (ev.services || []).map(s => {
            const name = (s.doc_service && s.doc_service.name) || s.name || '-';
            return `<span class="st-perawatan-badge">${escapeHtml(name)}</span>`;
        });
        if (ev.lainnya_text) {
            services.push(`<span class="st-perawatan-badge">${escapeHtml(ev.lainnya_text)}</span>`);
        }
        const perawatanHtml = services.length
            ? `<div class="st-perawatan-wrap">${services.join('')}</div>`
            : `<span class="text-muted">-</span>`;

        const newBadge = ev.is_new
            ? ` <span class="badge badge-success" style="font-size:0.62rem;"><i class="fa fa-star"></i> Baru</span>`
            : '';

        const jam = `${(ev.start_hour||'').substring(0,5)}–${(ev.end_hour||'').substring(0,5)}`;
        const pasien = ev.customer ? escapeHtml(ev.customer.name) : '<span class="text-danger">Pasien Terhapus</span>';
        const dokter = ev.doctor ? escapeHtml(ev.doctor.name) : '-';

        const stLower = String(ev.status||'').toLowerCase();
        const cancelledClass = (stLower === 'cancelled' || stLower === 'dibatalkan' || stLower === 'cancel') ? 'is-cancelled' : '';

        return `
            <tr class="${cancelledClass}" onclick="openSingleModal('${ev.id}')" title="Klik untuk lihat detail">
                <td><span class="st-time">${jam}</span></td>
                <td>
                    <div class="st-pasien-name">${pasien}${newBadge}</div>
                    <div class="st-pasien-sub">${dokter}</div>
                </td>
                <td>${perawatanHtml}</td>
                <td>
                    <span class="st-status-wrap">
                        <span class="st-status-dot ${dotClass}"></span>
                        ${escapeHtml(cfg.label || '-')}
                    </span>
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('modalBody').innerHTML = `
        <table class="st-list-table">
            <thead>
                <tr>
                    <th>Jam</th>
                    <th>Pasien &amp; Dokter</th>
                    <th>Perawatan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
    document.getElementById('modalDetailBtn').style.display = 'none';
    document.getElementById('modalFooterNote').style.visibility = 'visible';
    $('#eventModal').modal('show');
}

$(document).on('hidden.bs.modal', '#eventModal', function () {
    lastAllModalCtx = null;
});

function openSingleModal(id) {
    const ev = RAW_RESERVATIONS.find(r => r.id === id);
    if (ev) {
        showModal(ev);
        if (lastAllModalCtx) setCloseBtnKembali();
    }
}

document.getElementById('prevMonth').addEventListener('click', () => { curMonth--; if (curMonth<0){curMonth=11;curYear--;} renderCalendar(curYear,curMonth); });
document.getElementById('nextMonth').addEventListener('click', () => { curMonth++; if (curMonth>11){curMonth=0;curYear++;} renderCalendar(curYear,curMonth); });
document.getElementById('todayBtn').addEventListener('click', () => { curYear=today.getFullYear(); curMonth=today.getMonth(); renderCalendar(curYear,curMonth); });

// Sync data-value attribute for pill-style value-based coloring
function syncInlineSelectValue($el) {
    if ($el.is('select')) {
        $el.attr('data-value', $el.val() || '');
    }
}
$('.inline-update').each(function () { syncInlineSelectValue($(this)); });

$(document).on('change', '.inline-update', function () {
    const id = $(this).data('id'), field = $(this).data('field'), value = $(this).val(), $sel = $(this);
    syncInlineSelectValue($sel);
    $sel.prop('disabled', true);
    $.ajax({
        url: '/rawreservation/' + id + '/inline', method: 'PATCH',
        data: { _token: '{{ csrf_token() }}', field, value },
        success:  () => {
            $sel.css('border-color','#28a745');
            setTimeout(() => $sel.css('border-color',''), 1500);

            // 1) update in-memory data
            const rec = (typeof RAW_RESERVATIONS !== 'undefined')
                ? RAW_RESERVATIONS.find(r => String(r.id) === String(id)) : null;
            if (rec) rec[field] = value;

            // 2) sync semua input/select dengan data-id & data-field yg sama (tabel ↔ modal)
            $(`.inline-update[data-id="${id}"][data-field="${field}"]`).each(function () {
                if (this !== $sel[0]) {
                    if ($(this).is('textarea, input')) $(this).val(value);
                    else $(this).val(value);
                    syncInlineSelectValue($(this));
                }
            });

            // 3) jika status berubah, re-render kalender supaya dot/warna ikut update
            if (field === 'status' && typeof renderCalendar === 'function') {
                renderCalendar(curYear, curMonth);
            }
        },
        error:    () => alert('Gagal menyimpan, coba lagi.'),
        complete: () => $sel.prop('disabled', false),
    });
});

// Real-time notification polling
// Gunakan waktu server untuk menghindari notifikasi berulang akibat perbedaan zona waktu (WIB vs UTC)
let lastCheckTimestamp = '{!! now()->toDateTimeString() !!}';
let toastTimeout;

function checkNewReservations() {
    $.get('/api/reservation/check-new', { since: lastCheckTimestamp }, function(response) {
        if (response) {
            // Always update timestamp to server's time to stay in sync
            if (response.server_time) {
                lastCheckTimestamp = response.server_time;
            }

            if (response.new_count > 0) {
                const latest = response.latest;
                const toastBody = document.getElementById('toastBody');
                
                let message = `Ada <strong>${response.new_count}</strong> reservasi baru.`;
                if (latest && latest.customer) {
                    message += `<br>Terbaru dari: <strong>${escapeHtml(latest.customer.name)}</strong> di cabang <strong>${latest.branch ? escapeHtml(latest.branch.name) : ''}</strong>`;
                }
                
                @if(request('new'))
                    message += `<br><a href="javascript:void(0)" onclick="window.location.reload()" style="font-weight:bold; color: #0056b3;"><i class="fa fa-sync"></i> Muat ulang data &raquo;</a>`;
                @else
                    message += `<br><a href="{{ route('rawreservation') }}?new=1" style="font-weight:bold; color: #0056b3;">Lihat sekarang &raquo;</a>`;
                @endif
                
                toastBody.innerHTML = message;
                
                // Play a sound
                try {
                    const audio = new Audio('https://cdn.jsdelivr.net/gh/scottschiller/SoundManager2@master/demo/mp3/blip.mp3');
                    audio.play();
                } catch (e) {
                    console.warn("Could not play notification sound.", e);
                }

                // Show toast
                $('#newReservationToast').fadeIn();
                
                // Auto-hide setelah 8 detik (8000 ms)
                if (toastTimeout) clearTimeout(toastTimeout);
                toastTimeout = setTimeout(() => {
                    $('#newReservationToast').fadeOut();
                }, 7000);
            }
        }
    }).fail(function() {
        console.error("Failed to check for new reservations.");
    });
}

// Poll every 20 seconds
setInterval(checkNewReservations, 20000);

// Render kalender saat halaman dimuat jika tab kalender sedang aktif
$(document).ready(function() {
    if (document.getElementById('viewCal').style.display !== 'none') {
        renderCalendar(curYear, curMonth);
    }
});

// Row-click navigation — seluruh baris jadi clickable kecuali pada interactive child
$(document).on('click', '.st-table--clickable tbody tr[data-href]', function (e) {
    // Abaikan klik yang asalnya dari elemen interaktif di dalam row
    if (e.target.closest('input, select, textarea, button, a, label, form')) return;
    var href = this.getAttribute('data-href');
    if (e.ctrlKey || e.metaKey || e.button === 1) {
        if (href) window.open(href, '_blank');
        return;
    }
    // Klik biasa → buka Detail drawer (BUKAN navigasi ke halaman edit).
    var raw = this.getAttribute('data-rr');
    if (typeof window.rrOpenDrawer === 'function' && raw) {
        e.preventDefault();
        e.stopImmediatePropagation();
        try {
            window.rrOpenDrawer(JSON.parse(raw));
        } catch (err) {
            console.error('Gagal parse data-rr:', err);
        }
        return false;   // pastikan tidak ada navigasi/propagasi lain
    }
    // Drawer tak tersedia (mis. JS belum siap) → fallback ke halaman edit
    if (href) window.location.href = href;
});
// Middle-click / Ctrl+click → buka tab baru (mouseup agar dapat tombol tengah)
$(document).on('auxclick', '.st-table--clickable tbody tr[data-href]', function (e) {
    if (e.button !== 1) return;
    if (e.target.closest('input, select, textarea, button, a, label, form')) return;
    e.preventDefault();
    window.open($(this).data('href'), '_blank');
});

// Toggle modal pattern (compatible dgn pettycash modals).
// Dipakai untuk modal "Kelola Halaman" — pola .pc-modal + .is-open class.
document.querySelectorAll('[data-pc-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const id = btn.dataset.pcModal;
        const modal = document.getElementById(id);
        if (modal) modal.classList.add('is-open');
    });
});
document.querySelectorAll('[data-pc-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const m = btn.closest('.pc-modal');
        if (m) m.classList.remove('is-open');
    });
});
document.querySelectorAll('.pc-modal').forEach(function (m) {
    m.addEventListener('click', function (e) {
        if (e.target === m) m.classList.remove('is-open');
    });
});

// Status pill (redesign): warna pill ikut nilai terpilih saat diganti.
document.querySelectorAll('.st-status-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        if (o) {
            sel.style.background = o.dataset.bg || '';
            sel.style.color = o.dataset.color || '';
        }
    });
});

// ══════════ Detail Drawer ══════════
(function () {
    var RR_CONFIRMED = @json(\App\Models\RawCusReservation::STATUS_CONFIRMED);
    var RR_CANCELLED = @json(\App\Models\RawCusReservation::STATUS_CANCELLED);
    @php
        $rrStatusMapJs = collect(\App\Models\RawCusReservation::statusOptions())->mapWithKeys(function ($o, $k) {
            $short = [
                \App\Models\RawCusReservation::STATUS_PENDING   => 'Menunggu',
                \App\Models\RawCusReservation::STATUS_CONFIRMED  => 'Dikonfirmasi',
                \App\Models\RawCusReservation::STATUS_WAITLIST   => 'Waiting',
                \App\Models\RawCusReservation::STATUS_COMPLETED  => 'Selesai',
                \App\Models\RawCusReservation::STATUS_CANCELLED  => 'Batal',
            ];
            return [$k => ['label' => $o['label'], 'short' => $short[$k] ?? $o['label'], 'bg' => $o['bg'], 'color' => $o['color']]];
        })->toArray();
    @endphp
    var RR_STATUS_MAP = @json($rrStatusMapJs);
    var RR_TOKEN = '{{ csrf_token() }}';
    var $drawer = $('#rrDrawer');
    var rrId = null;

    function fuLabel(v) {
        return ({ confirm: '✅ Confirm', reschedule: '🔄 Reschedule', cancel: '❌ Cancel', no_respon: '📵 No Respon' })[v] || 'Belum ada';
    }
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
    function renderPromo(d) {
        var h = '';
        if (d.promo) h += '<span class="st-pill st-pill--info st-pill--plain">' + esc(d.promo) + '</span> ';
        if (d.promoId) {
            if (d.klaim === 'done') h += '<span class="st-pill st-pill--success"><i class="fa fa-check"></i> Diklaim</span>';
            else h += '<form method="POST" action="' + d.klaimUrl + '" onsubmit="return confirm(\'Konfirmasi klaim promo untuk reservasi ini?\')" style="display:inline-block;margin-top:6px;">' +
                '<input type="hidden" name="_token" value="' + RR_TOKEN + '"><input type="hidden" name="_method" value="PATCH">' +
                '<button type="submit" class="st-quick-btn st-quick-btn--warning"><i class="fa fa-tag"></i> Klaim Promo</button></form>';
        }
        if (!d.promo && !d.promoId) h = '<span style="color:var(--text-subtle)">Tidak ada promo</span>';
        $('#rrdPromoWrap').html(h);
    }
    window.rrOpenDrawer = function (d) {
        rrId = d.id;
        $('#rrdName').text(d.name || '—');
        $('#rrdPhone').text(d.phone || '');
        if (d.wa) { $('#rrdWa').attr('href', 'https://wa.me/' + d.wa).show(); } else { $('#rrdWa').hide(); }
        $('#rrdStatusPill').text(d.statusLabel || '—').css({ background: d.statusBg || '', color: d.statusColor || '' });
        $('#rrdJadwal').text(d.jadwal || '—');
        $('#rrdTime').text(d.time || '');
        $('#rrdDoctor').text(d.doctor || '—');
        $('#rrdTreatment').text(d.treatment || '—');
        $('#rrdKeterangan').val(d.keterangan || '');
        $('#rrdStatusSeg .rr-seg__btn').removeClass('active').filter('[data-value="' + d.status + '"]').addClass('active');
        $('#rrdKehadiranSeg .rr-seg__btn').removeClass('active').filter('[data-value="' + (d.kehadiran || '') + '"]').addClass('active');
        $('#rrdFu1Sel').val(d.fu1 || '');
        $('#rrdFuhSel').val(d.fuh || '');
        if (d.wa) { $('#rrdFu1Wa, #rrdFuhWa').attr('href', 'https://wa.me/' + d.wa).show(); } else { $('#rrdFu1Wa, #rrdFuhWa').hide(); }
        $('#rrdEdit').attr('href', d.editUrl || '#');
        renderPromo(d);
        $drawer.addClass('is-open').attr('aria-hidden', 'false');
        document.body.classList.add('rr-drawer-lock');
    };
    function rrClose() {
        $drawer.removeClass('is-open').attr('aria-hidden', 'true');
        document.body.classList.remove('rr-drawer-lock');
        rrId = null;
    }
    function rrPatch(field, value) {
        if (!rrId) return;
        $.ajax({
            url: '/rawreservation/' + rrId + '/inline', method: 'PATCH',
            data: { _token: RR_TOKEN, field: field, value: value },
            success: function () {
                $('.inline-update[data-id="' + rrId + '"][data-field="' + field + '"]').each(function () {
                    $(this).val(value);
                    if (typeof syncInlineSelectValue === 'function') syncInlineSelectValue($(this));
                });
                if (field === 'status') {
                    var stInfo = RR_STATUS_MAP[value];
                    $('.st-rowstatus[data-id="' + rrId + '"]').each(function () {
                        if (stInfo) { this.textContent = stInfo.short || stInfo.label; this.style.background = stInfo.bg; this.style.color = stInfo.color; }
                    });
                    if (typeof renderCalendar === 'function' && typeof curYear !== 'undefined') renderCalendar(curYear, curMonth);
                }
                // sync chip Follow-up / Kehadiran di list
                if (field === 'follow_up_h_min_1' || field === 'follow_up_h') {
                    var which = field === 'follow_up_h_min_1' ? 'h1' : 'h';
                    $('.st-fuchip[data-id="' + rrId + '"][data-fu="' + which + '"]').toggleClass('is-done', !!value);
                }
                if (field === 'keterangan_kehadiran') {
                    var $att = $('.st-fuchip[data-id="' + rrId + '"][data-fu="att"]');
                    $att.removeClass('is-hadir is-tidak');
                    if (value === 'hadir') { $att.addClass('is-hadir').text('✓'); }
                    else if (value === 'tidak_hadir') { $att.addClass('is-tidak').text('✕'); }
                    else { $att.text('?'); }
                }
            },
            error: function () { alert('Gagal menyimpan, coba lagi.'); }
        });
    }

    $(document).on('click', '#rrdStatusSeg .rr-seg__btn', function () {
        var $b = $(this);
        $('#rrdStatusSeg .rr-seg__btn').removeClass('active'); $b.addClass('active');
        $('#rrdStatusPill').text($b.text()).css({ background: $b.data('bg') || '', color: $b.data('color') || '' });
        rrPatch('status', $b.data('value'));
    });
    $(document).on('click', '#rrdKehadiranSeg .rr-seg__btn', function () {
        var $b = $(this);
        $('#rrdKehadiranSeg .rr-seg__btn').removeClass('active'); $b.addClass('active');
        rrPatch('keterangan_kehadiran', String($b.data('value') == null ? '' : $b.data('value')));
    });
    $(document).on('change', '#rrdKeterangan', function () { rrPatch('keterangan', $(this).val()); });
    $(document).on('change', '#rrdFu1Sel', function () { rrPatch('follow_up_h_min_1', $(this).val()); });
    $(document).on('change', '#rrdFuhSel', function () { rrPatch('follow_up_h', $(this).val()); });
    $(document).on('click', '#rrdConfirm', function () { $('#rrdStatusSeg .rr-seg__btn[data-value="' + RR_CONFIRMED + '"]').trigger('click'); });
    $(document).on('click', '#rrdCancel', function () { if (confirm('Batalkan reservasi ini?')) $('#rrdStatusSeg .rr-seg__btn[data-value="' + RR_CANCELLED + '"]').trigger('click'); });
    $(document).on('click', '[data-rr-close]', rrClose);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $drawer.hasClass('is-open')) rrClose(); });
})();

// ════════ Form Drawer (Tambah / Edit lengkap) — Fase 1a: buka/tutup wadah ════════
// Isi form di-fetch & inject di Fase 1b; submit AJAX di Fase 1c.
(function () {
    var $fd = $('#rrFormDrawer');
    if (!$fd.length) return; // view-only: drawer tidak dirender
    var $body = $('#rrFormBody');

    // Buka drawer + fetch form (?drawer=1, bare layout) → inject → jalankan config → init.
    window.rrOpenFormDrawer = function (opts) {
        opts = opts || {};
        $('#rrFormTitle').text(opts.title || 'Reservasi');
        $body.html('<div class="text-muted" style="padding:24px 4px;"><i class="fa fa-spinner fa-spin"></i> Memuat form…</div>');
        $fd.addClass('is-open').attr('aria-hidden', 'false');
        document.body.classList.add('rr-drawer-lock');

        var url = opts.url || '';
        if (!url) return;
        var fetchUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'drawer=1';

        fetch(fetchUrl, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                // Bare layout balikin full HTML → ambil isi tag body (section content form).
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var srcBody = doc.body || doc.documentElement;
                // innerHTML TIDAK mengeksekusi <script> → pisahkan dulu, jalankan manual.
                var scripts = Array.prototype.slice.call(srcBody.querySelectorAll('script'));
                scripts.forEach(function (s) { if (s.parentNode) s.parentNode.removeChild(s); });
                $body.html(srcBody.innerHTML);
                // Jalankan script config (set window.rrFormConfig) di global scope.
                scripts.forEach(function (s) {
                    var n = document.createElement('script');
                    if (s.src) { n.src = s.src; } else { n.textContent = s.textContent; }
                    document.head.appendChild(n);
                    if (n.parentNode) n.parentNode.removeChild(n);
                });
                // Wiring booking untuk markup yang baru di-inject.
                if (typeof window.initReservationForm === 'function') {
                    window.initReservationForm();
                }
            })
            .catch(function (err) {
                $body.html('<div class="text-danger" style="padding:24px 4px;"><i class="fa fa-exclamation-circle"></i> Gagal memuat form: '
                    + (err && err.message ? err.message : err) + '</div>');
            });
    };
    function rrFormClose() {
        $fd.removeClass('is-open').attr('aria-hidden', 'true');
        document.body.classList.remove('rr-drawer-lock');
    }
    window.rrCloseFormDrawer = rrFormClose;

    $(document).on('click', '[data-rrform-close]', rrFormClose);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $fd.hasClass('is-open')) rrFormClose();
    });

    // Tombol "Tambah" → buka form drawer (mode add). preventDefault → tidak navigasi.
    // Kalau JS mati, <a href> tetap fallback ke halaman /rawreservation/new.
    $(document).on('click', '#rrBtnTambah', function (e) {
        e.preventDefault();
        window.rrOpenFormDrawer({ title: 'Tambah Reservasi', url: $(this).attr('href'), mode: 'add' });
    });

    // "Edit lengkap" di Detail drawer → tutup Detail, buka form drawer (mode edit).
    $(document).on('click', '#rrdEdit', function (e) {
        e.preventDefault();
        var url = $(this).attr('href');
        $('#rrDrawer').removeClass('is-open').attr('aria-hidden', 'true'); // tutup panel Detail
        window.rrOpenFormDrawer({ title: 'Edit Reservasi', url: url, mode: 'edit' });
    });

    // ── Fase 1c: submit form via AJAX (no reload saat ngedit) ──
    // Tampilkan banner error di atas form (di dalam drawer), tanpa reload.
    function rrFormShowErrors(errors) {
        var items = [];
        Object.keys(errors || {}).forEach(function (k) {
            var arr = errors[k];
            if (Array.isArray(arr)) { arr.forEach(function (m) { items.push(m); }); }
            else if (arr) { items.push(String(arr)); }
        });
        if (!items.length) items.push('Gagal menyimpan. Coba lagi.');
        var html = '<div id="rrFormErrors" class="rr-form-errors"><strong><i class="fa fa-exclamation-circle"></i> Periksa lagi:</strong><ul>'
            + items.map(function (m) { return '<li>' + escapeHtml(m) + '</li>'; }).join('') + '</ul></div>';
        $('#rrFormErrors').remove();
        $('#rrFormBody').prepend(html);
        $('#rrFormBody').scrollTop(0);
    }

    // Intercept submit form utama saja (#rrReservationForm) — BUKAN form hapus/komisi.
    $(document).on('submit', '#rrReservationForm', function (e) {
        e.preventDefault();
        var form = this;
        var $btns = $(form).find('button[type="submit"]');
        $('#rrFormErrors').remove();
        $btns.prop('disabled', true);

        fetch(form.action, {
            method: 'POST', // _method=PUT ikut di FormData untuk mode Edit (Laravel baca sebagai PUT)
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (r) {
            return r.text().then(function (t) {
                var data = null; try { data = JSON.parse(t); } catch (_) {}
                return { status: r.status, data: data };
            });
        })
        .then(function (res) {
            if (res.status >= 200 && res.status < 300 && res.data && res.data.ok) {
                // Opsi A: toast sukses lalu reload list (sekali, di akhir).
                if (window.Swal) {
                    Swal.fire({ icon: 'success', title: res.data.message || 'Tersimpan', timer: 1100, showConfirmButton: false })
                        .then(function () { window.location.reload(); });
                } else {
                    window.location.reload();
                }
                return;
            }
            if (res.status === 422 && res.data && res.data.errors) {
                rrFormShowErrors(res.data.errors);
            } else {
                rrFormShowErrors({ _: [(res.data && res.data.message) || ('Gagal menyimpan (HTTP ' + res.status + ').')] });
            }
            $btns.prop('disabled', false);
        })
        .catch(function (err) {
            rrFormShowErrors({ _: ['Gagal terhubung: ' + (err && err.message ? err.message : err)] });
            $btns.prop('disabled', false);
        });
    });
})();

</script>
@endsection