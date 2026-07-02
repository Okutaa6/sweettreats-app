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
                            @if(isSuperAdmin())
                                <a href="{{ route('rawreservation.import') }}" class="st-btn-outline-brown" title="Impor jadwal reservasi dari CSV / Google Sheets cabang (Super Admin)">
                                    <i class="fa fa-file-import"></i> Impor Sheets
                                    @if(($importPendingCount ?? 0) > 0)
                                        <span class="view-tab-badge" style="margin-left:6px;">{{ $importPendingCount }}</span>
                                    @endif
                                </a>
                            @endif
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
                    @if(request('new'))
                        {{-- ══════════════════════════════════════
                             VIEW: RESERVASI BARU (triage kartu) — design handoff View 2
                             Banner gradient + grid kartu 2-kolom. Reuse data-rr → klik kartu
                             buka Detail drawer; Konfirmasi pakai endpoint inline
                             (/rawreservation/{id}/inline, field=status). Tabel List tak disentuh.
                        ══════════════════════════════════════ --}}
                        @php $hariListB = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu']; @endphp
                        <div class="rr-baru-banner">
                            <div class="rr-baru-banner__icon"><i class="fa fa-star"></i></div>
                            <div class="rr-baru-banner__text">
                                <div class="rr-baru-banner__title"><strong>{{ $reservations->total() }}</strong> reservasi baru</div>
                                <div class="rr-baru-banner__sub">Menunggu konfirmasi — konfirmasi atau hubungi pasien lewat WhatsApp</div>
                            </div>
                            @if(!$isViewOnly && $reservations->count())
                                <button type="button" class="rr-baru-confirmall" id="rrBaruConfirmAll">
                                    <i class="fa fa-check"></i> Konfirmasi semua
                                </button>
                            @endif
                        </div>

                        @if($reservations->count())
                            <div class="rr-baru-grid">
                                @foreach ($reservations as $row)
                                    @php
                                        $rrWa = $row->customer?->phone ? preg_replace('/[^0-9]/', '', $row->customer->phone) : '';
                                        if ($rrWa && str_starts_with($rrWa, '0')) $rrWa = '62' . substr($rrWa, 1);
                                        $rrStatusOpt = \App\Models\RawCusReservation::statusOptions()[$row->status] ?? null;
                                        $rrTreat = $row->services->map(fn($s) => $s->docService->name ?? null)->filter()->unique()->implode(', ');
                                        if (!$rrTreat && $row->lainnya_text) $rrTreat = 'Keluhan: ' . $row->lainnya_text;
                                        $rrTimeLabel = ($row->start_hour && $row->end_hour)
                                            ? (\Carbon\Carbon::parse($row->start_hour)->format('H:i') . ' – ' . \Carbon\Carbon::parse($row->end_hour)->format('H:i'))
                                            : '';
                                        $rrDateLabel = $row->date ? ($hariListB[$row->date->dayOfWeek] . ', ' . $row->date->format('d M Y')) : '—';
                                        $rrData = [
                                            'id'          => $row->id,
                                            'name'        => $row->customer->name ?? 'Pasien Terhapus',
                                            'phone'       => $row->customer->phone ?? '',
                                            'wa'          => $rrWa,
                                            'status'      => $row->status,
                                            'statusLabel' => $rrStatusOpt['label'] ?? '—',
                                            'statusBg'    => $rrStatusOpt['bg'] ?? '',
                                            'statusColor' => $rrStatusOpt['color'] ?? '',
                                            'jadwal'      => $rrDateLabel,
                                            'time'        => $rrTimeLabel,
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
                                        $isConfirmed = $row->status === \App\Models\RawCusReservation::STATUS_CONFIRMED;
                                    @endphp
                                    <div class="rrb-card {{ $isConfirmed ? 'is-confirmed' : '' }}" data-id="{{ $row->id }}"
                                         @if(!$isViewOnly) data-rr="{{ json_encode($rrData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" @endif
                                         title="Klik untuk lihat detail">
                                        <div class="rrb-card__head">
                                            <div class="rrb-card__id">
                                                <div class="rrb-card__name">{{ $row->customer->name ?? 'Pasien Terhapus' }}</div>
                                                @if($row->customer?->phone)
                                                    <a href="https://wa.me/{{ $rrWa }}" target="_blank" rel="noopener" class="rrb-card__phone" onclick="event.stopPropagation()">
                                                        <i class="fab fa-whatsapp"></i> {{ $row->customer->phone }}
                                                    </a>
                                                @endif
                                            </div>
                                            <span class="rrb-card__badge"><i class="fa fa-circle"></i> Baru</span>
                                        </div>
                                        <div class="rrb-card__chips">
                                            @if($rrTimeLabel)
                                                <span class="rrb-card__time">{{ $rrTimeLabel }}</span>
                                            @else
                                                <span class="rrb-card__time rrb-card__time--wait">Belum ada slot</span>
                                            @endif
                                            @if($rrTreat)
                                                <span class="rrb-card__treat">{{ \Illuminate\Support\Str::limit($rrTreat, 40) }}</span>
                                            @endif
                                        </div>
                                        <div class="rrb-card__meta">{{ $rrDateLabel }} · {{ $row->doctor->name ?? '—' }}</div>
                                        @if(!$isViewOnly)
                                            <div class="rrb-card__actions">
                                                <button type="button" class="rrb-card__confirm" data-id="{{ $row->id }}">
                                                    <i class="fa fa-check"></i> <span class="rrb-card__confirm-label">{{ $isConfirmed ? 'Dikonfirmasi' : 'Konfirmasi' }}</span>
                                                </button>
                                                @if($row->customer?->phone)
                                                    <a href="https://wa.me/{{ $rrWa }}" target="_blank" rel="noopener" class="rrb-card__wa" onclick="event.stopPropagation()">
                                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rr-baru-empty">
                                <i class="fa fa-check-circle"></i>
                                <div class="rr-baru-empty__title">Tidak ada reservasi baru</div>
                                <div class="rr-baru-empty__sub">Reservasi yang masuk dalam 6 jam terakhir akan muncul di sini.</div>
                            </div>
                        @endif
                    @elseif(($view ?? 'list') === 'waitlist')
                        {{-- ══════════════════════════════════════
                             VIEW: WAITING LIST (design handoff View 3)
                             Header card + list rows. Badge = sinyal REAL (slot tersedia /
                             lama menunggu), BUKAN field prioritas palsu. Tanggal preferensi
                             selalu tampil; jam hanya saat slot dokter kebuka (slot_available).
                             Note = keterangan (real). "Tawarkan slot" → buka Edit drawer
                             (assign slot). Tabel List tak disentuh.
                        ══════════════════════════════════════ --}}
                        <div class="rrw-head">
                            <div class="rrw-head__icon"><i class="fa fa-hourglass-half"></i></div>
                            <div class="rrw-head__text">
                                <div class="rrw-head__title"><strong>{{ $reservations->total() }}</strong> pasien di waiting list</div>
                                <div class="rrw-head__sub">Belum dapat slot pasti — tawarkan begitu ada slot kosong</div>
                            </div>
                        </div>

                        @if($reservations->count())
                            <div class="rrw-list">
                                @foreach ($reservations as $row)
                                    @php
                                        $wName    = $row->customer->name ?? 'Pasien Terhapus';
                                        $wInitial = mb_strtoupper(mb_substr(trim($wName), 0, 1)) ?: '?';
                                        $wWa = $row->customer?->phone ? preg_replace('/[^0-9]/', '', $row->customer->phone) : '';
                                        if ($wWa && str_starts_with($wWa, '0')) $wWa = '62' . substr($wWa, 1);
                                        $wTreat = $row->services->map(fn($s) => $s->docService->name ?? null)->filter()->unique()->implode(', ');
                                        if (!$wTreat && $row->lainnya_text) $wTreat = 'Keluhan: ' . $row->lainnya_text;
                                        $wDate     = $row->date ? $row->date->format('d M Y') : null;
                                        $wSlotOpen = !empty($row->slot_available);
                                        $wSlotStart = $row->slot_start ?? null;
                                        $wWaitDays = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->startOfDay()->diffInDays(now()->startOfDay()) : 0;
                                    @endphp
                                    <div class="rrw-row {{ $wSlotOpen ? 'is-hot' : '' }}">
                                        <div class="rrw-avatar">{{ $wInitial }}</div>
                                        <div class="rrw-main">
                                            <div class="rrw-main__top">
                                                <span class="rrw-name">{{ $wName }}</span>
                                                @if($wSlotOpen)
                                                    <span class="rrw-badge rrw-badge--hot"><i class="fa fa-bolt"></i> Slot tersedia{{ $wSlotStart ? ' · ' . $wSlotStart : '' }}</span>
                                                @else
                                                    <span class="rrw-badge rrw-badge--wait"><i class="fa fa-clock"></i> {{ $wWaitDays > 0 ? 'Menunggu ' . $wWaitDays . ' hari' : 'Baru masuk' }}</span>
                                                @endif
                                            </div>
                                            <div class="rrw-meta">{{ $wTreat ?: 'Tanpa perawatan' }} · {{ $row->doctor->name ?? 'Tanpa dokter' }}@if($wDate) · {{ $wDate }}@endif</div>
                                            @if($row->keterangan)
                                                <div class="rrw-note">{{ $row->keterangan }}</div>
                                            @endif
                                        </div>
                                        <div class="rrw-actions">
                                            @if(!$isViewOnly)
                                                <button type="button" class="rrw-offer" data-url="{{ route('rawreservation.edit', $row->id) }}">
                                                    <i class="fa fa-bolt"></i> Tawarkan slot
                                                </button>
                                            @endif
                                            @if($row->customer?->phone)
                                                <a href="https://wa.me/{{ $wWa }}" target="_blank" rel="noopener" class="rrw-wa" title="Chat via WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rr-baru-empty">
                                <i class="fa fa-check-circle"></i>
                                <div class="rr-baru-empty__title">Waiting list kosong</div>
                                <div class="rr-baru-empty__sub">Belum ada pasien yang menunggu slot.</div>
                            </div>
                        @endif
                    @else
                        @php
                            // Sort state untuk kolom JAM. Cycle: null → asc → desc → null.
                            // Grouping per-tanggal TETAP terjaga karena controller mempertahankan
                            // `date DESC` sebagai primary sort; sort_jam hanya secondary di dalam hari.
                            $sortJam   = request('sort_jam');
                            $nextSort  = $sortJam === 'asc' ? 'desc' : ($sortJam === 'desc' ? '' : 'asc');
                            $sortUrl   = route('rawreservation', array_merge(
                                request()->except(['sort_jam', 'page']),
                                $nextSort ? ['sort_jam' => $nextSort] : []
                            ));
                            $sortIcon  = $sortJam === 'asc' ? 'fa-sort-up'
                                        : ($sortJam === 'desc' ? 'fa-sort-down' : 'fa-sort');
                            $sortTitle = $sortJam === 'asc'
                                ? 'Saat ini: jam pagi → malam (dalam tiap hari). Klik untuk balik (malam → pagi).'
                                : ($sortJam === 'desc'
                                    ? 'Saat ini: jam malam → pagi (dalam tiap hari). Klik untuk kembali ke default.'
                                    : 'Klik untuk urutkan berdasarkan jam (pagi → malam, di dalam tiap hari).');

                            // Toggle sort tanggal "hari ini di atas" — DI-RENDER di day-header
                            // row pertama (sekali saja, bukan di setiap day-header) supaya konteks
                            // visual jelas: yang sedang di-sort adalah TANGGAL. Cycle 2-state:
                            // null (default date DESC) ↔ 'today' (smart: hari ini & masa depan ASC
                            // di atas, lampau DESC di bawah). Mempertahankan filter lain.
                            $sortDateActive = request('sort_date') === 'today';
                            $sortDateUrl    = route('rawreservation', array_merge(
                                request()->except(['sort_date', 'page']),
                                $sortDateActive ? [] : ['sort_date' => 'today']
                            ));
                            $sortDateTitle  = $sortDateActive
                                ? 'Saat ini: hari ini di atas → besok → lusa → … (data lampau di bawah). Klik untuk kembali ke default (tanggal terbaru di atas).'
                                : 'Klik untuk urutkan: hari ini di atas, lalu besok, lusa, … (data lampau pindah ke bawah).';
                        @endphp
                        <div class="st-table-wrap st-table-wrap--scroll">
                        <table class="st-table st-table--clickable" cellspacing="0" style="white-space: nowrap;">
                            <thead>
                                <tr>
                                    <th>Pasien</th>
                                    <th>Dokter</th>
                                    @if($showBranchColumn)<th>Cabang</th>@endif
                                    <th class="st-th-sortable {{ $sortJam ? 'st-th-sortable--active' : '' }}">
                                        <a href="{{ $sortUrl }}" class="st-sort-link" title="{{ $sortTitle }}">
                                            Jam <i class="fa {{ $sortIcon }}"></i>
                                        </a>
                                    </th>
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
                                            // Tandai day-header pertama supaya toggle sort tanggal cuma muncul sekali.
                                            $isFirstDayRow = ($prevDate === null);
                                            $prevDate = $curDate;
                                            $hdLabel  = $row->date ? $hariList[$row->date->dayOfWeek] . ', ' . $row->date->format('d M Y') : 'Tanpa Tanggal';
                                            $hdCount  = $curDate ? ($dayCounts[$curDate]->count() ?? 0) : 0;
                                        @endphp
                                        <tr class="st-day-row">
                                            <td colspan="{{ $colCount }}">
                                                <span class="st-day-dot"></span>
                                                <span class="st-day-label">{{ $hdLabel }}</span>
                                                <span class="st-day-count">{{ $hdCount }}</span>
                                                @if ($isFirstDayRow)
                                                    <a href="{{ $sortDateUrl }}"
                                                       class="st-day-sort {{ $sortDateActive ? 'st-day-sort--active' : '' }}"
                                                       title="{{ $sortDateTitle }}">
                                                        <i class="fa fa-arrow-up"></i>
                                                        {{ $sortDateActive ? 'Hari ini di atas · aktif' : 'Urut: hari ini di atas' }}
                                                    </a>
                                                @endif
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
                    @endif
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

{{-- ════════ Day Drawer (Jadwal Harian) — kalender: klik hari → list reservasi ════════
     Di LUAR @if(!$isViewOnly) supaya tersedia di semua mode (read-friendly).
     Item → Detail drawer (hanya non-view-only; Detail drawer di-guard isViewOnly). --}}
<div id="rrDayDrawer" class="rr-drawer" aria-hidden="true">
    <div class="rr-drawer__scrim" data-rrday-close></div>
    <aside class="rr-drawer__panel" role="dialog" aria-label="Jadwal Harian">
        <div class="rr-drawer__head">
            <button type="button" class="rr-drawer__close" data-rrday-close aria-label="Tutup">&times;</button>
            <div class="rr-drawer__eyebrow">JADWAL HARIAN</div>
            <h3 class="rr-drawer__name" id="rrDayTitle">—</h3>
            <div class="rr-drawer__headmeta"><span class="rr-day__count" id="rrDayCount">0 reservasi</span></div>
        </div>
        <div class="rr-drawer__body">
            <div class="rr-day__list" id="rrDayList"></div>
        </div>
    </aside>
</div>

{{-- Modal detail kalender lama (#eventModal) DIHAPUS — diganti Day drawer + Detail drawer. --}}

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

// Status → kelas warna chip (5 token design: pending/confirmed/waitlist/completed/cancelled).
const STATUS_CHIP_KEY = {
    pending:   'pending',
    confirmed: 'confirmed',
    ongoing:   'confirmed',
    waitlist:  'waitlist',
    completed: 'completed',
    done:      'completed',
    selesai:   'completed',
    cancelled: 'cancelled',
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
    // Chip compact 1-baris (design View 4): dot + "HH:MM · Nama", tint per-status.
    const cfg     = STATUS_CFG[ev.status] || { label: ev.status || '-', cls: '' };
    const jam     = (ev.start_hour || '').substring(0, 5);
    const nama    = ev.customer ? ev.customer.name : 'Pasien Terhapus';
    const chipKey = STATUS_CHIP_KEY[ev.status] || 'pending';

    // Deteksi waktu sudah terlewat → chip diredupkan.
    const now = new Date();
    const nowTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
    const nowDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
    const jamSelesai = (ev.end_hour || '').substring(0, 5);
    let isPast = false;
    if (ev.date < nowDate) isPast = true;
    else if (ev.date === nowDate && jamSelesai && jamSelesai < nowTime) isPast = true;

    const chip = document.createElement('div');
    chip.className = 'st-cal-chip st-cal-chip--' + chipKey + (isPast ? ' is-past' : '');
    chip.title = (jam ? jam + ' · ' : '') + nama + ' — ' + cfg.label;
    chip.innerHTML = '<span class="st-cal-chip__dot"></span>'
        + (ev.is_new ? '<i class="fa fa-star st-cal-chip__new"></i>' : '')
        + '<span class="st-cal-chip__txt">' + (jam ? jam + ' · ' : '') + escapeHtml(nama) + '</span>';
    chip.addEventListener('click', (e) => {
        e.stopPropagation();
        if (typeof window.rrOpenDayDrawer === 'function') window.rrOpenDayDrawer((ev.date || '').substring(0, 10));
    });
    return chip;
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

        events.slice(0, 3).forEach(ev => cell.appendChild(makeEventCard(ev)));

        if (events.length > 3) {
            const more = document.createElement('span');
            more.className   = 'st-cal-more';
            more.textContent = `+${events.length - 3} lainnya`;
            more.addEventListener('click', (e) => {
                e.stopPropagation();
                if (typeof window.rrOpenDayDrawer === 'function') window.rrOpenDayDrawer(key);
            });
            cell.appendChild(more);
        }
        body.appendChild(cell);
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

// ══════════ Day Drawer (Jadwal Harian) — kalender klik hari ══════════
// Map event kalender (snake_case) → payload rrOpenDrawer; buka list 1 hari.
(function () {
    var EDIT_TPL  = '{{ route("rawreservation.edit", ["id" => ":id"]) }}';
    var KLAIM_TPL = '{{ route("rawreservation.klaim", ["id" => ":id"]) }}';
    var HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var BLN  = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    var $dd = $('#rrDayDrawer');
    var dayEvents = {};   // id → ev (untuk klik item → Detail drawer)

    function calEventToRr(ev) {
        var so = (typeof STATUS_OPTIONS !== 'undefined' && STATUS_OPTIONS[ev.status]) || {};
        var wa = (ev.customer && ev.customer.phone) ? String(ev.customer.phone).replace(/[^0-9]/g, '') : '';
        if (wa && wa.charAt(0) === '0') wa = '62' + wa.substring(1);
        var treat = (ev.services || []).map(function (s) { return s.doc_service ? s.doc_service.name : null; }).filter(Boolean).join(', ');
        if (!treat && ev.lainnya_text) treat = 'Keluhan: ' + ev.lainnya_text;
        var dk = ev.date ? ev.date.substring(0, 10) : '';
        var dObj = dk ? new Date(dk + 'T00:00:00') : null;
        var t1 = (ev.start_hour || '').substring(0, 5), t2 = (ev.end_hour || '').substring(0, 5);
        return {
            id: ev.id,
            name: ev.customer ? ev.customer.name : 'Pasien Terhapus',
            phone: ev.customer ? ev.customer.phone : '',
            wa: wa,
            status: ev.status,
            statusLabel: so.label || '—',
            statusBg: so.bg || '',
            statusColor: so.color || '',
            jadwal: dObj ? (HARI[dObj.getDay()] + ', ' + dObj.getDate() + ' ' + BLN[dObj.getMonth()] + ' ' + dObj.getFullYear()) : '—',
            time: t1 + (t2 ? ' – ' + t2 : ''),
            doctor: ev.doctor ? ev.doctor.name : '—',
            treatment: treat || '—',
            keterangan: ev.keterangan || '',
            fu1: ev.follow_up_h_min_1 || '',
            fuh: ev.follow_up_h || '',
            kehadiran: ev.keterangan_kehadiran || '',
            promo: ev.promo ? ev.promo.promo_code : '',
            promoId: ev.raw_promo_id,
            klaim: ev.promo_klaim ? 'done' : (ev.raw_promo_id ? 'pending' : 'none'),
            editUrl: EDIT_TPL.replace(':id', ev.id),
            klaimUrl: KLAIM_TPL.replace(':id', ev.id)
        };
    }

    window.rrOpenDayDrawer = function (dateKey) {
        var events = (typeof buildEventMap === 'function' ? buildEventMap()[dateKey] : null) || [];
        dayEvents = {};
        var dObj = new Date(dateKey + 'T00:00:00');
        $('#rrDayTitle').text(HARI[dObj.getDay()] + ', ' + dObj.getDate() + ' ' + BLN[dObj.getMonth()] + ' ' + dObj.getFullYear());
        $('#rrDayCount').text(events.length + ' reservasi');
        var html = '';
        events.forEach(function (ev) {
            dayEvents[ev.id] = ev;
            var so = (typeof STATUS_OPTIONS !== 'undefined' && STATUS_OPTIONS[ev.status]) || {};
            var t1 = (ev.start_hour || '').substring(0, 5), t2 = (ev.end_hour || '').substring(0, 5);
            var nm = ev.customer ? escapeHtml(ev.customer.name) : 'Pasien Terhapus';
            var dk = ev.doctor ? escapeHtml(ev.doctor.name) : '—';
            var treat = (ev.services || []).map(function (s) { return s.doc_service ? escapeHtml(s.doc_service.name) : null; }).filter(Boolean).join(', ');
            if (!treat && ev.lainnya_text) treat = 'Keluhan: ' + escapeHtml(ev.lainnya_text);
            html += '<div class="rr-day-card" data-evid="' + escapeHtml(String(ev.id)) + '" style="border-left-color:' + (so.color || '#B79A82') + '">'
                + '<div class="rr-day-card__top"><span class="rr-day-card__name">' + nm + '</span>'
                + '<span class="rr-day-card__pill" style="background:' + (so.bg || '#eee') + ';color:' + (so.color || '#555') + '">' + escapeHtml(so.label || ev.status || '-') + '</span></div>'
                + '<div class="rr-day-card__meta"><span class="rr-day-card__time">' + (t1 ? t1 + (t2 ? ' – ' + t2 : '') : '—') + '</span><span>' + dk + '</span></div>'
                + (treat ? '<div class="rr-day-card__treat">' + treat + '</div>' : '')
                + '</div>';
        });
        $('#rrDayList').html(html || '<div class="rr-day__empty">Tidak ada reservasi di tanggal ini.</div>');
        $dd.addClass('is-open').attr('aria-hidden', 'false');
        document.body.classList.add('rr-drawer-lock');
    };
    function rrDayClose() {
        $dd.removeClass('is-open').attr('aria-hidden', 'true');
        document.body.classList.remove('rr-drawer-lock');
    }
    $(document).on('click', '[data-rrday-close]', rrDayClose);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $dd.hasClass('is-open')) rrDayClose(); });

    // Klik kartu di Day drawer → Detail drawer (kalau tersedia; di view-only Detail
    // drawer tak di-render → item tidak melakukan apa-apa). Pass returnTo supaya
    // footer Konfirmasi/Batal balik ke Day drawer (lanjut triage) — bukan tutup
    // semua ke kalender.
    $(document).on('click', '.rr-day-card', function () {
        var ev = dayEvents[this.getAttribute('data-evid')];
        if (!ev) return;
        if (typeof window.rrOpenDrawer === 'function') {
            var dayKey = (ev.date || '').substring(0, 10);
            rrDayClose();
            window.rrOpenDrawer(calEventToRr(ev), { type: 'day', dateKey: dayKey });
        }
    });
})();

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
    // Sumber pemanggil drawer — dipakai footer Konfirmasi/Batal supaya tahu
    // mau balik ke mana setelah save. null = no-return (default), atau
    // { type:'day', dateKey:'YYYY-MM-DD' } kalau dibuka dari Day drawer.
    var rrReturnTo = null;

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
    window.rrOpenDrawer = function (d, returnTo) {
        rrId = d.id;
        rrReturnTo = returnTo || null;
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
        rrReturnTo = null;
    }
    function rrPatch(field, value) {
        if (!rrId) return;
        // Capture id lokal: dipakai di success callback supaya tetap valid walau
        // drawer keburu di-close (rrClose set rrId=null) — kasus footer Konfirmasi
        // & Batal yg trigger rrClose tepat setelah rrPatch.
        var id = rrId;
        $.ajax({
            url: '/rawreservation/' + id + '/inline', method: 'PATCH',
            data: { _token: RR_TOKEN, field: field, value: value },
            success: function () {
                // Sync data kalender in-memory → renderCalendar (chip) ikut fresh saat
                // status diubah dari Detail drawer (dibuka via kalender/Day drawer).
                if (typeof RAW_RESERVATIONS !== 'undefined') {
                    var rec = RAW_RESERVATIONS.find(function (r) { return String(r.id) === String(id); });
                    if (rec) rec[field] = value;
                }
                $('.inline-update[data-id="' + id + '"][data-field="' + field + '"]').each(function () {
                    $(this).val(value);
                    if (typeof syncInlineSelectValue === 'function') syncInlineSelectValue($(this));
                });
                if (field === 'status') {
                    var stInfo = RR_STATUS_MAP[value];
                    $('.st-rowstatus[data-id="' + id + '"]').each(function () {
                        if (stInfo) { this.textContent = stInfo.short || stInfo.label; this.style.background = stInfo.bg; this.style.color = stInfo.color; }
                    });
                    if (typeof renderCalendar === 'function' && typeof curYear !== 'undefined') renderCalendar(curYear, curMonth);
                }
                // sync chip Follow-up / Kehadiran di list
                if (field === 'follow_up_h_min_1' || field === 'follow_up_h') {
                    var which = field === 'follow_up_h_min_1' ? 'h1' : 'h';
                    $('.st-fuchip[data-id="' + id + '"][data-fu="' + which + '"]').toggleClass('is-done', !!value);
                }
                if (field === 'keterangan_kehadiran') {
                    var $att = $('.st-fuchip[data-id="' + id + '"][data-fu="att"]');
                    $att.removeClass('is-hadir is-tidak');
                    if (value === 'hadir') { $att.addClass('is-hadir').text('✓'); }
                    else if (value === 'tidak_hadir') { $att.addClass('is-tidak').text('✕'); }
                    else { $att.text('?'); }
                }
            },
            error: function () { alert('Gagal menyimpan, coba lagi.'); }
        });
    }

    // Helper: set status di drawer (visual + AJAX) — dipakai segmen STATUS, tombol
    // footer Konfirmasi, dan footer Batal. Sebelumnya footer pakai trigger('click')
    // indirection ke segmen — silent fail di sebagian browser; sekarang panggil
    // langsung supaya 1 jalur untuk semua entry point.
    function rrSetStatus(value) {
        var $b = $('#rrdStatusSeg .rr-seg__btn[data-value="' + value + '"]');
        if (!$b.length) { console.warn('[rrSetStatus] segmen status tidak ditemukan untuk value:', value); return; }
        $('#rrdStatusSeg .rr-seg__btn').removeClass('active'); $b.addClass('active');
        $('#rrdStatusPill').text($b.text()).css({ background: $b.data('bg') || '', color: $b.data('color') || '' });
        rrPatch('status', value);
    }

    $(document).on('click', '#rrdStatusSeg .rr-seg__btn', function () {
        rrSetStatus($(this).attr('data-value'));
    });
    $(document).on('click', '#rrdKehadiranSeg .rr-seg__btn', function () {
        var $b = $(this);
        $('#rrdKehadiranSeg .rr-seg__btn').removeClass('active'); $b.addClass('active');
        rrPatch('keterangan_kehadiran', String($b.attr('data-value') == null ? '' : $b.attr('data-value')));
    });
    $(document).on('change', '#rrdKeterangan', function () { rrPatch('keterangan', $(this).val()); });
    $(document).on('change', '#rrdFu1Sel', function () { rrPatch('follow_up_h_min_1', $(this).val()); });
    $(document).on('change', '#rrdFuhSel', function () { rrPatch('follow_up_h', $(this).val()); });
    // Footer tombol: Konfirmasi & Batal → set status + auto-close drawer (UX:
    // staff klik 1x langsung kembali ke list, tidak perlu manual tutup).
    // rrPatch sudah optimistic-update chip di list; alert error tetap muncul
    // kalau AJAX gagal (di handler error rrPatch).
    // Kalau dibuka dari Day drawer (kalender → tanggal → reservasi), re-open
    // Day drawer setelah close supaya staff lanjut triage tanpa klik tanggal lagi.
    // Tunda 280ms (lebih dari .25s animasi slide-out Detail) supaya transisi mulus.
    function rrAfterFooterSave() {
        var rt = rrReturnTo;
        rrClose();
        if (rt && rt.type === 'day' && rt.dateKey && typeof window.rrOpenDayDrawer === 'function') {
            setTimeout(function () { window.rrOpenDayDrawer(rt.dateKey); }, 280);
        }
    }
    // Tombol "Konfirmasi" = SIMPAN + tutup drawer. TIDAK memaksa ubah status —
    // status ditentukan lewat pill "Status Reservasi" di atas (auto-save via
    // rrPatch saat pill diklik). Kalau paksa RR_CONFIRMED di sini, admin yg
    // mau tandain "Selesai"/"Waiting List" bakal ke-reset ke "Dikonfirmasi".
    $(document).on('click', '#rrdConfirm', function () { rrAfterFooterSave(); });
    $(document).on('click', '#rrdCancel', function () { if (confirm('Batalkan reservasi ini?')) { rrSetStatus(RR_CANCELLED); rrAfterFooterSave(); } });
    $(document).on('click', '[data-rr-close]', rrClose);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && $drawer.hasClass('is-open')) rrClose(); });
})();

// ══════════ Reservasi Baru (triage kartu) ══════════
// Klik kartu → Detail drawer (reuse data-rr). Konfirmasi/Konfirmasi semua → PATCH
// status via endpoint inline yang sama dgn drawer (/rawreservation/{id}/inline).
(function () {
    var RR_CONFIRMED = @json(\App\Models\RawCusReservation::STATUS_CONFIRMED);
    var RR_TOKEN = '{{ csrf_token() }}';

    // Klik kartu → buka Detail drawer (abaikan klik tombol/link di dalam kartu).
    $(document).on('click', '.rrb-card[data-rr]', function (e) {
        if (e.target.closest('input, select, textarea, button, a, label, form')) return;
        var raw = this.getAttribute('data-rr');
        if (typeof window.rrOpenDrawer === 'function' && raw) {
            try { window.rrOpenDrawer(JSON.parse(raw)); }
            catch (err) { console.error('Gagal parse data-rr:', err); }
        }
    });

    // Konfirmasi satu kartu.
    function confirmCard($btn) {
        var $card = $btn.closest('.rrb-card');
        if ($card.hasClass('is-confirmed') || $btn.prop('disabled')) return;
        var id = $btn.data('id');
        $btn.prop('disabled', true);
        $btn.find('.rrb-card__confirm-label').text('Memproses…');
        $.ajax({
            url: '/rawreservation/' + id + '/inline', method: 'PATCH',
            data: { _token: RR_TOKEN, field: 'status', value: RR_CONFIRMED },
            success: function () {
                $card.addClass('is-confirmed');
                $btn.find('.rrb-card__confirm-label').text('Dikonfirmasi');
            },
            error: function () {
                $btn.prop('disabled', false);
                $btn.find('.rrb-card__confirm-label').text('Konfirmasi');
                alert('Gagal mengonfirmasi, coba lagi.');
            }
        });
    }

    $(document).on('click', '.rrb-card__confirm', function () { confirmCard($(this)); });

    $(document).on('click', '#rrBaruConfirmAll', function () {
        var $btns = $('.rrb-card').not('.is-confirmed').find('.rrb-card__confirm');
        if (!$btns.length) { alert('Semua reservasi baru sudah dikonfirmasi.'); return; }
        if (!confirm('Konfirmasi semua ' + $btns.length + ' reservasi baru?')) return;
        $btns.each(function () { confirmCard($(this)); });
    });
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

    // Waiting List: "Tawarkan slot" → buka Edit drawer (staff pilih slot & simpan).
    $(document).on('click', '.rrw-offer', function () {
        var url = this.getAttribute('data-url');
        if (typeof window.rrOpenFormDrawer === 'function') {
            window.rrOpenFormDrawer({ title: 'Tawarkan Slot', url: url, mode: 'edit' });
        } else if (url) {
            window.location.href = url;
        }
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