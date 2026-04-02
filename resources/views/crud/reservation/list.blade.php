@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item active">Reservation</li>
                    </ol>
                </div>
                <h4 class="page-title">Reservation</h4>
            </div>
        </div>
    </div>

    <div id="newReservationToast" style="position: fixed; top: 80px; right: 20px; z-index: 1050; display: none; min-width: 350px; max-width: 400px;">
        <div class="toast-header" style="background: #28a745; color: white; padding: 8px 12px; border-top-left-radius: 8px; border-top-right-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
            <strong class="mr-auto"><i class="fa fa-bell"></i> Reservasi Baru Masuk</strong>
            <button type="button" class="ml-2 mb-1 close" onclick="$('#newReservationToast').fadeOut()" aria-label="Close">
                <span aria-hidden="true" style="color: white;">&times;</span>
            </button>
        </div>
        <div id="toastBody" class="toast-body" style="background: #f8f9fa; padding: 16px; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); font-size: 1.1em; color: #333;">
            <!-- Content will be injected by JS -->
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body" style="zoom: 0.65;">

                    @php 
                        $hasFilter = $filterSearch || $filterBranch || $filterDoctor || $filterStatus || $filterDateFrom || $filterDateTo || $filterPromo || $filterAffiliate || request('new');
                    @endphp

                    {{-- ── Toolbar: Tambah + Filter Button + Tab toggle ── --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">
                        <div class="d-flex align-items-center" style="gap:8px;">
                            <a href="{{ route('rawreservation.new') }}" class="btn btn-sm btn-primary">
                                <i class="fa fa-plus"></i> Tambah
                            </a>
                            <button class="btn btn-sm {{ $hasFilter ? 'btn-secondary' : 'btn-outline-secondary' }}" type="button" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="{{ $hasFilter ? 'true' : 'false' }}">
                                <i class="fa fa-filter"></i> {{ $hasFilter ? 'Filter Aktif' : 'Filter' }}
                            </button>
                        </div>
                        <div class="view-tab-group" style="font-size: 1.2em;">
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'list'])) }}" class="view-tab {{ !request('new') && request('view', 'list') !== 'cal' ? 'active' : '' }}">
                                <i class="fa fa-list"></i> List
                            </a>
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['new' => 1, 'view' => 'list'])) }}" class="view-tab {{ request('new') ? 'active' : '' }}">
                                <i class="fa fa-star"></i> Reservasi Baru
                            </a>
                            <a href="{{ route('rawreservation', array_merge(request()->except(['new', 'view']), ['view' => 'cal'])) }}" class="view-tab {{ request('view') === 'cal' ? 'active' : '' }}">
                                <i class="fa fa-calendar"></i> Kalender
                            </a>
                        </div>
                    </div>

                    {{-- ── Form filter (Collapsible) ── --}}
                    <div class="collapse {{ $hasFilter ? 'show' : '' }}" id="filterCollapse">
                        <div class="card card-body mb-3 border-0" style="background-color: #f8f9fa; border-radius: 8px;">
                            <form method="GET" action="{{ route('rawreservation') }}" id="filterForm" class="mb-0">
                                <input type="hidden" name="view" value="{{ request('view', 'list') }}">
                                @if(request('new'))
                                <input type="hidden" name="new" value="1">
                                @endif
                                <div class="row" style="row-gap:12px;">
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Pencarian</label>
                                        <input type="text" class="form-control form-control-sm" name="search" placeholder="Nama / No. HP..." value="{{ $filterSearch }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Cabang</label>
                                        <select class="form-control form-control-sm" name="branch_id">
                                            <option value="">Semua Cabang</option>
                                            @foreach ($branches as $b)
                                                <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Dokter</label>
                                        <select class="form-control form-control-sm" name="doctor_id">
                                            <option value="">Semua Dokter</option>
                                            @foreach ($doctors as $d)
                                                <option value="{{ $d->id }}" {{ $filterDoctor == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Status</label>
                                        <select class="form-control form-control-sm" name="status">
                                            <option value="">Semua Status</option>
                                            @foreach ($statuses as $k => $opt)
                                                <option value="{{ $k }}" {{ $filterStatus == $k ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Tanggal Mulai</label>
                                        <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $filterDateFrom }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Tanggal Akhir</label>
                                        <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $filterDateTo }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Promo</label>
                                        <select class="form-control form-control-sm" name="promo_id">
                                            <option value="">Semua Promo</option>
                                            @foreach ($promos as $p)
                                                <option value="{{ $p->id }}" {{ $filterPromo == $p->id ? 'selected' : '' }}>{{ $p->promo_code }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="small mb-1 font-weight-bold" style="color: #461500;">Affiliate</label>
                                        <select class="form-control form-control-sm" name="affiliate_id">
                                            <option value="">Semua Affiliate</option>
                                            @foreach ($affiliates as $a)
                                                <option value="{{ $a->id }}" {{ $filterAffiliate == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-sm btn-primary w-100 mr-1">
                                            Filter
                                        </button>
                                        <a href="{{ route('rawreservation') }}" class="btn btn-sm btn-outline-danger" title="Reset Filter">
                                            <i class="fa fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ── Info hasil filter ── --}}
                    @if ($hasFilter)
                        <div class="mb-3" style="font-size: 1.15rem; color:#666">
                            <i class="fa fa-filter text-primary"></i>
                            Menampilkan <strong>{{ $reservations->total() }}</strong> reservasi
                            @if ($filterSearch) · cari "<em>{{ $filterSearch }}</em>" @endif
                            @if ($filterBranch) · cabang: <em>{{ $branches->firstWhere('id', $filterBranch)?->name }}</em> @endif
                            @if ($filterDoctor) · dokter: <em>{{ $doctors->firstWhere('id', $filterDoctor)?->name }}</em> @endif
                            @if ($filterStatus) · status: <em>{{ $statuses[$filterStatus]['label'] ?? $filterStatus }}</em> @endif
                            @if ($filterDateFrom || $filterDateTo) · tanggal: <em>{{ $filterDateFrom ?: '...' }} — {{ $filterDateTo ?: '...' }}</em> @endif
                            @if ($filterPromo) · promo: <em>{{ $promos->firstWhere('id', $filterPromo)?->promo_code }}</em> @endif
                            @if ($filterAffiliate) · affiliate: <em>{{ $affiliates->firstWhere('id', $filterAffiliate)?->name }}</em> @endif
                            @if (request('new')) · <span class="badge badge-success">Reservasi Baru</span> @endif
                        </div>
                    @endif

                    {{-- ══════════════════════════════════════
                         TAB: LIST
                    ══════════════════════════════════════ --}}
                    <div id="viewList" style="{{ request('view', 'list') === 'cal' ? 'display:none;' : '' }}">
                        <div class="table-responsive">
                        <table class="table table-bordered table-hover" cellspacing="0" style="font-size:0.85rem; white-space: nowrap;">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>No Telepon</th>
                                    <th>Dokter</th>
                                    <th>Cabang</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Perawatan</th>
                                    <th>Keterangan</th>
                                    <th>Promo</th>
                                    <th>Klaim</th>
                                    <th>Status</th>
                                    <th>FU H-1</th>
                                    <th>FU H</th>
                                    <th>Kehadiran</th>
                                    <th width="5%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reservations as $key => $row)
                                    @php 
                                        $si = $row->status_info; 
                                        // Cek apakah data ini dibuat kurang dari 6 jam yang lalu
                                        $isNew = false;
                                        if (!empty($row->created_at)) {
                                            $isNew = \Carbon\Carbon::parse($row->created_at)->diffInHours(\Carbon\Carbon::now()) < 6;
                                        }
                                    @endphp
                                    <tr style="{{ $isNew ?'background-color: #e6f7ff !important;' : '' }}" {!! $isNew ? 'title="Data baru masuk"' : '' !!}>
                                        <td style="white-space: normal; min-width: 150px;">
                                            {{ $row->customer->name ?? '-' }}
                                            @if($isNew)
                                                <span class="badge badge-success ml-1" style="font-size:0.75rem;"><i class="fa fa-star"></i> Baru</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->customer->phone ?? '-' }}</td>
                                        <td>{{ $row->doctor->name ?? '-' }}</td>
                                        <td>{{ $row->branch->name ?? '-' }}</td>
                                        <td>{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</td>
                                        <td nowrap>{{ \Carbon\Carbon::parse($row->start_hour)->format('H:i') }} - {{ \Carbon\Carbon::parse($row->end_hour)->format('H:i') }}</td>
                                        <td style="white-space: normal; min-width: 220px;">
                                        @php
                                            $uniqueServiceNames = $row->services->map(function($svc) {
                                                return $svc->docService->name ?? null;
                                            })->filter()->unique();
                                        @endphp
                                        @foreach ($uniqueServiceNames as $serviceName)
                                            <span class="badge badge-light border" style="font-size:0.75rem;margin:1px">
                                                {{ $serviceName }}
                                            </span>
                                        @endforeach
                                        @if($row->lainnya_text)
                                            <div style="margin-top:4px;">
                                                <span class="badge badge-warning" style="font-size:0.72rem; white-space:normal; text-align:left; padding:3px 6px;">
                                                    <i class="fa fa-comment-dots"></i>
                                                    Keluhan: {{ $row->lainnya_text }}
                                                </span>
                                            </div>
                                        @endif
                                        @if($uniqueServiceNames->isEmpty() && !$row->lainnya_text)
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td style="white-space: normal; min-width: 150px;">
                                        <textarea class="form-control form-control-sm inline-update" 
                                            data-id="{{ $row->id }}" data-field="keterangan" 
                                            rows="2" placeholder="Isi keterangan...">{{ $row->keterangan }}</textarea>
                                    </td>
                                        <td>
                                            @if($row->promo)
                                                <span class="badge badge-info" style="font-size:0.75rem">{{ $row->promo->promo_code }}</span>
                                            @else
                                                <span class="text-muted" style="font-size:0.8rem">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->raw_promo_id)
                                                @if($row->promo_klaim)
                                                    {{-- Sudah diklaim --}}
                                                    <span class="badge badge-success">
                                                        <i class="fa fa-check"></i> Diklaim
                                                    </span>
                                                    <small class="text-muted d-block" style="font-size:0.7rem">
                                                        {{ $row->promo_klaim_by ? 'oleh: ' . (\App\Models\Users::find($row->promo_klaim_by)->name ?? '-') : '' }}
                                                    </small>
                                                @else
                                                    {{-- Belum diklaim — tampilkan tombol --}}
                                                    <form method="POST"
                                                        action="{{ route('rawreservation.klaim', $row->id) }}"
                                                        onsubmit="return confirm('Konfirmasi klaim promo untuk reservasi ini?')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm btn-warning"
                                                            style="padding:2px 8px; font-size:0.75rem">
                                                            <i class="fa fa-tag"></i> Klaim
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="text-muted" style="font-size:0.8rem">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm inline-update" style="min-width:130px"
                                                data-id="{{ $row->id }}" data-field="status">
                                                @foreach (\App\Models\RawCusReservation::statusOptions() as $k => $opt)
                                                    <option value="{{ $k }}" {{ $row->status == $k ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm inline-update" style="min-width:110px"
                                                data-id="{{ $row->id }}" data-field="follow_up_h_min_1">
                                                <option value="">-- --</option>
                                                <option value="confirm"    {{ $row->follow_up_h_min_1 == 'confirm'    ? 'selected' : '' }}>✅ Confirm</option>
                                                <option value="reschedule" {{ $row->follow_up_h_min_1 == 'reschedule' ? 'selected' : '' }}>🔄 Reschedule</option>
                                                <option value="cancel"     {{ $row->follow_up_h_min_1 == 'cancel'     ? 'selected' : '' }}>❌ Cancel</option>
                                                <option value="no_respon"  {{ $row->follow_up_h_min_1 == 'no_respon'  ? 'selected' : '' }}>📵 No Respon</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm inline-update" style="min-width:110px"
                                                data-id="{{ $row->id }}" data-field="follow_up_h">
                                                <option value="">-- --</option>
                                                <option value="confirm"    {{ $row->follow_up_h == 'confirm'    ? 'selected' : '' }}>✅ Confirm</option>
                                                <option value="reschedule" {{ $row->follow_up_h == 'reschedule' ? 'selected' : '' }}>🔄 Reschedule</option>
                                                <option value="cancel"     {{ $row->follow_up_h == 'cancel'     ? 'selected' : '' }}>❌ Cancel</option>
                                                <option value="no_respon"  {{ $row->follow_up_h == 'no_respon'  ? 'selected' : '' }}>📵 No Respon</option>
                                            </select>
                                        </td>
                                        <td>
                                            <select class="form-control form-control-sm inline-update" style="min-width:110px"
                                                data-id="{{ $row->id }}" data-field="keterangan_kehadiran">
                                                <option value="">-- --</option>
                                                <option value="hadir"       {{ $row->keterangan_kehadiran == 'hadir'       ? 'selected' : '' }}>✅ Hadir</option>
                                                <option value="tidak_hadir" {{ $row->keterangan_kehadiran == 'tidak_hadir' ? 'selected' : '' }}>❌ Tidak Hadir</option>
                                                <option value="reschedule"  {{ $row->keterangan_kehadiran == 'reschedule'  ? 'selected' : '' }}>🔄 Reschedule</option>
                                                <option value="cancel"      {{ $row->keterangan_kehadiran == 'cancel'      ? 'selected' : '' }}>❌ Cancel</option>
                                            </select>
                                        </td>
                                        <td nowrap class="text-center">
                                            <a href="{{ route('rawreservation.edit', $row->id) }}"
                                                class="btn btn-info" style="padding:2px 6px" title="Detail">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                        {{ $reservations->links() }}
                    </div>

                    {{-- ══════════════════════════════════════
                         TAB: CALENDAR
                    ══════════════════════════════════════ --}}
                    <div id="viewCal" style="{{ request('view', 'list') === 'cal' ? '' : 'display:none;' }}">
                        <div class="d-flex align-items-center justify-content-center mb-3" style="gap:8px;">
                            <button class="btn btn-sm btn-warning" id="prevMonth"><i class="fa fa-chevron-left"></i></button>
                            <span id="monthLabel" style="font-weight:700; font-size:1.50rem; color:rgb(70,21,0); min-width:160px; text-align:center;"></span>
                            <button class="btn btn-sm btn-warning" id="nextMonth"><i class="fa fa-chevron-right"></i></button>
                            <button class="btn btn-sm btn-primary ml-2" id="todayBtn" style="font-size: 1.25    rem;">Hari Ini </button>
                        </div>
                        <div class="cal-grid cal-header">
                            @foreach(['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $d)
                                <div class="cal-dow">{{ $d }}</div>
                            @endforeach
                        </div>
                        <div class="cal-grid cal-body" id="calBody"></div>
                        <div class="cal-legend mt-2" style="font-size: 2.50rem;">
                            <span class="cal-legend-dot" style="background:rgb(243,199,77)"></span><span class="cal-legend-txt">Menunggu Konfirmasi</span>
                            <span class="cal-legend-dot" style="background:rgb(30,202,184)"></span><span class="cal-legend-txt">DIkonfirmasi</span>
                            <span class="cal-legend-dot" style="background:#7c3aed"></span><span class="cal-legend-txt">Waiting List</span>
                            <span class="cal-legend-dot" style="background:rgb(77,121,246)"></span><span class="cal-legend-txt">Berlangsung</span>
                            <span class="cal-legend-dot" style="background:rgb(241,100,108)"></span><span class="cal-legend-txt">Dibatalkan</span>
                            <span class="cal-legend-dot" style="background:#2e7d32"></span><span class="cal-legend-txt">Selesai</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:12px; border:none; overflow:hidden;">
            <div class="modal-header" style="background:rgb(70,21,0); border:none;">
                <h5 class="modal-title" style="color:#fff; font-weight:700;">
                    <i class="fa fa-calendar-check mr-2"></i> Detail Reservasi
                </h5>
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:1; font-size:1.4rem;">&times;</button>
            </div>
            <div class="modal-body" style="padding:16px 24px;">
                <table class="table table-sm mb-0" style="font-size:0.88rem;">
                    <tbody id="modalBody"></tbody>
                </table>
            </div>
            <div class="modal-footer" style="border-top:1px solid rgb(208,196,191); background:rgb(250,245,243);">
                <a href="#" id="modalDetailBtn" class="btn btn-primary btn-sm">
                    <i class="fa fa-eye"></i> Lihat Pasien
                </a>
                <button type="button" class="btn btn-warning btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
/* ── Tab toggle ── */
.view-tab-group { display:flex; border:1px solid rgb(208,196,191); border-radius:6px; overflow:hidden; }
.view-tab { padding:5px 16px; font-size:0.82rem; font-weight:600; background:#fff; color:rgb(70,21,0); border:none; cursor:pointer; transition:background .15s,color .15s; outline:none; }
.view-tab:not(:last-child) { border-right:1px solid rgb(208,196,191); }
.view-tab.active { background:rgb(70,21,0); color:#fff; }
.view-tab:not(.active):hover { background:rgb(250,245,243); }

/* ── Calendar grid ── */
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); border-left:1px solid rgb(208,196,191); border-top:1px solid rgb(208,196,191); }
.cal-header .cal-dow { background:rgb(70,21,0); color:#fff; text-align:center; font-weight:700; font-size:1rem; padding:8px 0; border-right:1px solid rgba(255,255,255,0.2); border-bottom:1px solid rgb(208,196,191); letter-spacing:.04em; }
.cal-day { border-right:1px solid rgb(208,196,191); border-bottom:1px solid rgb(208,196,191); min-height:130px; padding:4px 4px 6px; background:#fff; position:relative; transition:background .15s; }
.cal-day:hover { background:rgb(250,245,243); }
.cal-day.other-month { background:rgb(250,245,243); opacity:.6; }
.cal-day.today { background:rgb(255,248,245); }
.cal-day.today .cal-date { background:rgb(70,21,0); color:#fff; border-radius:50%; width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; }
.cal-date { font-size:0.95rem; font-weight:700; color:rgb(70,21,0); margin-bottom:4px; display:inline-block; line-height:1; }
.other-month .cal-date { color:rgb(180,150,140); }

/* ── Event card (new design) ── */
.cal-event-wrapper { margin-bottom:3px; }
.cal-event-card {
    border-radius: 5px;
    cursor: pointer;
    overflow: hidden;
    border-left: 4px solid transparent;
    transition: filter .15s, transform .1s;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.10);
}
.cal-event-card:hover { filter:brightness(.93); transform:translateX(1px); }

/* Top row: jam | nama | dokter */
.cal-event-top {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding: 6px 8px;
    font-weight: 600;
    color: rgb(40,20,10);
}
.cal-event-top .ev-time   { color:rgb(70,21,0); font-weight:700; font-size: 1.05rem; display: flex; align-items: flex-start; }
.cal-event-top .ev-sep    { display: none; }
.cal-event-top .ev-name   { font-size: 1.05rem; color: rgb(80,50,30); line-height: 1.2; display: flex; align-items: flex-start; }
.cal-event-top .ev-doctor { font-size: 0.95rem; color: rgb(120,80,60); display: flex; align-items: flex-start; margin-top: 2px; }
    
.cal-event-top i {
    font-size: 1rem !important;
    margin-right: 5px;
    width: 16px;
    text-align: center;
    flex-shrink: 0;
    margin-top: 3px;
}

.cal-event-top .ev-header { display: flex; justify-content: space-between; align-items: center; width: 100%; }
.cal-event-top .ev-icons { display: flex; gap: 6px; }
.cal-event-top .ev-icon { font-size: 0.9rem; color: #888; }
.cal-event-top .ev-icon i { margin-right: 2px; }

/* Bottom: status bar */
.cal-event-status {
    font-size: 0.85rem;
    font-weight: 700;
    text-align: center;
    padding: 2px 4px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

/* Status variants */
.ev-pending   > .cal-event-card { border-color:rgb(243,199,77); }
.ev-pending   .cal-event-status { background:rgb(255,243,205); color:rgb(110,70,0); }
.ev-confirmed > .cal-event-card { border-color:rgb(30,202,184); }
.ev-confirmed .cal-event-status { background:rgb(195,248,240); color:rgb(0,80,65); }
.ev-cancelled > .cal-event-card { border-color:rgb(241,100,108); opacity: 0.6; }
.ev-cancelled .cal-event-top { text-decoration: line-through; }
.ev-cancelled .cal-event-status { background:rgb(255,205,210); color:rgb(140,20,30); }
.ev-done      > .cal-event-card { border-color:#2e7d32;  }
.ev-done      .cal-event-status { background:#e8f5e9; color:#2e7d32; }
.ev-ongoing   > .cal-event-card { border-color:rgb(77,121,246) }
.ev-ongoing   .cal-event-status { background:rgb(210,225,255); color:rgb(20,50,160) }
.ev-waitlist  > .cal-event-card { border-color:#7c3aed; }
.ev-waitlist  .cal-event-status { background:#ede9fe; color:#7c3aed; }
.ev-default   > .cal-event-card { border-color:rgb(180,160,150); }
.ev-default   .cal-event-status { background:rgb(225,215,210); color:rgb(70,21,0); }

.ev-past > .cal-event-card { opacity: 0.45; filter: grayscale(0.3); }

.cal-more { font-size:0.68rem; color:rgb(105,56,35); cursor:pointer; font-weight:600; padding:1px 4px; display:block; }
.cal-more:hover { text-decoration:underline; }

/* ── Legend ── */
.cal-legend { display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:8px; }
.cal-legend-dot { width:10px; height:10px; border-radius:50%; display:inline-block; flex-shrink:0; }
.cal-legend-txt { font-size:0.72rem; font-weight:600; color:rgb(70,21,0); margin-right:8px; }

/* ── Modal ── */
#modalBody tr td:first-child { font-weight:600; color:rgb(70,21,0); width:30%; white-space:nowrap; }
#modalBody tr td { border-color:rgb(208,196,191) !important; vertical-align:middle; }
.modal-hover-row:hover td { background-color: rgb(250,245,243) !important; }
</style>
@endsection

@section('js')
<script>

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

function createSelect(options, field, selectedValue, reservationId) {
    let html = `<select class="form-control form-control-sm inline-update" data-id="${reservationId}" data-field="${field}" style="min-width:120px;">`;
    for (const key in options) {
        const label = typeof options[key] === 'object' ? options[key].label : options[key];
        const isSelected = key == selectedValue ? 'selected' : '';
        html += `<option value="${key}" ${isSelected}>${label}</option>`;
    }
    html += `</select>`;
    return html;
}

const STATUS_CFG = {
    pending:   { label:'Menunggu Konfirmasi',      cls:'ev-pending',   style: 'background:rgb(255,243,205); color:rgb(110,70,0);' },
    confirmed: { label:'DIkonfirmasi', cls:'ev-confirmed', style: 'background:rgb(195,248,240); color:rgb(0,80,65);' },
    waitlist:  { label:'Waiting List', cls:'ev-waitlist',  style: 'background:#ede9fe; color:#7c3aed;' },
    ongoing:   { label:'Berlangsung',   cls:'ev-ongoing',   style: 'background:rgb(210,225,255); color:rgb(20,50,160);' },
    cancelled: { label:'Dibatalkan',    cls:'ev-cancelled', style: 'background:rgb(255,205,210); color:rgb(140,20,30);' },
    done:      { label:'Selesai',       cls:'ev-done',      style: 'background:#e8f5e9; color:#2e7d32;' },
    completed: { label:'Selesai',       cls:'ev-done',      style: 'background:#e8f5e9; color:#2e7d32;' },
    selesai:   { label:'Selesai',       cls:'ev-done',      style: 'background:#e8f5e9; color:#2e7d32;' },
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

    const cfg    = STATUS_CFG[ev.status] || { label: ev.status||'-', cls:'ev-default' };
    const jam    = (ev.start_hour||'').substring(0, 5);
    const jamSelesai = (ev.end_hour||'').substring(0, 5);
    const nama   = ev.customer ? ev.customer.name : '-';
    const dokter = ev.doctor ? ev.doctor.name : '-';

    let servicesHTML = '';
    if (ev.services && ev.services.length > 0) {
        const serviceNames = ev.services.map(s => s.doc_service ? s.doc_service.name : '').filter(Boolean).join(', ');
        servicesHTML = `<span class="ev-icon" title="${serviceNames}"><i class="fa fa-stethoscope"></i></span>`;
    } else if (ev.lainnya_text) {
        servicesHTML = `<span class="ev-icon" title="Keluhan: ${ev.lainnya_text}"><i class="fa fa-comment-dots"></i></span>`;
    }

    let promoHTML = '';
    if (ev.promo) {
        promoHTML = `<span class="ev-icon" title="Promo: ${ev.promo.promo_code}"><i class="fa fa-tag"></i></span>`;
    }

    // Deteksi apakah waktu sudah terlewat (Hari ini lewat jam selesai, atau memang sudah lewat hari)
    let isPast = false;
    if (ev.date < nowDate) {
        isPast = true;
    } else if (ev.date === nowDate && jamSelesai && jamSelesai < nowTime) {
        isPast = true;
    }

    let newBadgeHtml = '';
    let newBgStyle = '';
    if (ev.is_new) {
        newBadgeHtml = `<span class="badge badge-success ml-1" style="font-size:0.85rem; padding: 2px 4px; vertical-align: middle;"><i class="fa fa-star"></i> Baru</span>`;
        newBgStyle = 'background-color: #e6f7ff !important;';
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'cal-event-wrapper ' + cfg.cls + (isPast ? ' ev-past' : '');

    const card = document.createElement('div');
    card.className = 'cal-event-card';
    card.innerHTML = `
        <div class="cal-event-top" style="${newBgStyle}">
            <div class="ev-header">
                <span class="ev-time"><i class="fa fa-clock"></i>${jam}</span>
                <div class="ev-icons">
                    ${servicesHTML}
                    ${promoHTML}
                </div>
            </div>
            <span class="ev-name"><i class="fa fa-user"></i>${nama} ${newBadgeHtml}</span>
            <span class="ev-doctor"><i class="fa fa-user-md"></i>${dokter}</span>
        </div>
        <div class="cal-event-status">${cfg.label}</div>
    `;
    card.addEventListener('click', () => showModal(ev));
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
        cell.className = 'cal-day';
        let dayNum, dateObj;
        if (i < firstDay) {
            dayNum  = daysInPrev - firstDay + 1 + i;
            dateObj = new Date(year, month-1, dayNum);
            cell.classList.add('other-month');
        } else if (i >= firstDay + daysInMonth) {
            dayNum  = i - firstDay - daysInMonth + 1;
            dateObj = new Date(year, month+1, dayNum);
            cell.classList.add('other-month');
        } else {
            dayNum  = i - firstDay + 1;
            dateObj = new Date(year, month, dayNum);
        }
        if (dateObj.toDateString() === today.toDateString()) cell.classList.add('today');

        const dateDiv = document.createElement('div');
        dateDiv.innerHTML = `<span class="cal-date">${dayNum}</span>`;
        cell.appendChild(dateDiv);

        const yy  = dateObj.getFullYear();
        const mm  = String(dateObj.getMonth()+1).padStart(2,'0');
        const dd  = String(dateObj.getDate()).padStart(2,'0');
        const key = `${yy}-${mm}-${dd}`;
        const events = eventMap[key] || [];

        events.slice(0, 2).forEach(ev => cell.appendChild(makeEventCard(ev)));

        if (events.length > 2) {
            const more = document.createElement('span');
            more.className   = 'cal-more';
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
        return `<span class="badge badge-light border" style="font-size:0.75rem;margin:1px">${svc.doc_service ? svc.doc_service.name : '-'}</span>`;
    }).join(' ');
    if (ev.lainnya_text) {
        servicesHtml += `<div style="margin-top:4px;">
            <span class="badge badge-warning" style="font-size:0.72rem; white-space:normal; text-align:left; padding:3px 6px;">
                <i class="fa fa-comment-dots"></i> Keluhan: ${ev.lainnya_text}
            </span>
        </div>`;
    }
    if (!servicesHtml) servicesHtml = '<span class="text-muted">-</span>';

    let promoHtml = ev.promo ? `<span class="badge badge-info" style="font-size:0.75rem">${ev.promo.promo_code}</span>` : '<span class="text-muted">-</span>';

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

    document.getElementById('modalBody').innerHTML = `
        <tr><td>Customer</td><td>${ev.customer ? ev.customer.name : '-'}</td></tr>
        <tr><td>Dokter</td><td>${ev.doctor ? ev.doctor.name : '-'}</td></tr>
        <tr><td>Cabang</td><td>${ev.branch ? ev.branch.name : '-'}</td></tr>
        <tr><td>Tanggal</td><td>${tgl}</td></tr>
        <tr><td>Jam</td><td>${jam}</td></tr>
        <tr style="border-top: 2px solid #ddd;"><td valign="top">Perawatan</td><td style="white-space:normal;">${servicesHtml}</td></tr>
        <tr><td>Promo</td><td>${promoHtml}</td></tr>
        <tr><td>Klaim</td><td>${klaimHtml}</td></tr>
        <tr style="border-top: 2px solid #ddd;"><td>Status</td><td>${statusSelect}</td></tr>
        <tr><td>FU H-1</td><td>${fu1Select}</td></tr>
        <tr><td>FU H</td><td>${fuHSelect}</td></tr>
        <tr><td>Kehadiran</td><td>${kehadiranSelect}</td></tr>
    `;
    const detailRoute = '{{ route("rawreservation.edit", ["id" => ":id"]) }}'.replace(':id', ev.id);
    document.getElementById('modalDetailBtn').href = detailRoute;
    document.getElementById('modalDetailBtn').innerHTML = '<i class="fa fa-edit"></i> Detail / Edit Reservasi';
    document.getElementById('modalDetailBtn').style.display = '';
    $('#eventModal').modal('show');
}

function showAllModal(dateKey, events) {
    const tgl = dateKey.split('-').reverse().join('/');
    const header = `
        <tr style="background:rgb(250,245,243);">
            <th colspan="4" style="border-top:none; color:rgb(70,21,0); font-weight:700; font-size:0.95rem;">Reservasi Tanggal: ${tgl}</th>
        </tr>
        <tr style="background:#f1f1f1; font-weight:bold;">
            <th style="border-top:1px solid #dee2e6;">Jam</th>
            <th style="border-top:1px solid #dee2e6;">Nama Customer</th>
            <th style="border-top:1px solid #dee2e6;">Nama Dokter</th>
            <th style="border-top:1px solid #dee2e6;">Status</th>
        </tr>
    `;
    const rows = events.map(ev => {
        const cfg = STATUS_CFG[ev.status] || { label: ev.status || '-', style: 'background:#eee;color:#333' };
        
        let newBadgeHtml = '';
        let newBgStyle = '';
        if (ev.is_new) {
            newBadgeHtml = `<span class="badge badge-success ml-1" style="font-size:0.65rem;"><i class="fa fa-star"></i> Baru</span>`;
            newBgStyle = 'background-color: #e6f7ff !important;';
        }
        
        return `<tr class="modal-hover-row" style="cursor:pointer; ${newBgStyle}" onclick="openSingleModal('${ev.id}')" title="Klik untuk melihat detail / ubah status">
            <td>${(ev.start_hour||'').substring(0,5)} - ${(ev.end_hour||'').substring(0,5)}</td>
            <td>${ev.customer ? ev.customer.name : '-'}${newBadgeHtml}</td>
            <td>${ev.doctor ? ev.doctor.name : '-'}</td>
            <td><span class="badge" style="${cfg.style} font-size:0.75rem; padding:4px 8px;">${cfg.label}</span></td>
        </tr>`;
    }).join('');
    document.getElementById('modalBody').innerHTML = header + rows;
    document.getElementById('modalDetailBtn').style.display = 'none';
    $('#eventModal').modal('show');
}

function openSingleModal(id) {
    const ev = RAW_RESERVATIONS.find(r => r.id === id);
    if (ev) {
        showModal(ev);
    }
}

document.getElementById('prevMonth').addEventListener('click', () => { curMonth--; if (curMonth<0){curMonth=11;curYear--;} renderCalendar(curYear,curMonth); });
document.getElementById('nextMonth').addEventListener('click', () => { curMonth++; if (curMonth>11){curMonth=0;curYear++;} renderCalendar(curYear,curMonth); });
document.getElementById('todayBtn').addEventListener('click', () => { curYear=today.getFullYear(); curMonth=today.getMonth(); renderCalendar(curYear,curMonth); });

$(document).on('change', '.inline-update', function () {
    const id = $(this).data('id'), field = $(this).data('field'), value = $(this).val(), $sel = $(this);
    $sel.prop('disabled', true);
    $.ajax({
        url: '/rawreservation/' + id + '/inline', method: 'PATCH',
        data: { _token: '{{ csrf_token() }}', field, value },
        success:  () => { $sel.css('border-color','#28a745'); setTimeout(() => $sel.css('border-color',''), 1500); },
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
                    message += `<br>Terbaru dari: <strong>${latest.customer.name}</strong> di cabang <strong>${latest.branch ? latest.branch.name : ''}</strong>`;
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

</script>
@endsection