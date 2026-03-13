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

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="table-responsive">
                <div class="card-body" style="zoom: 0.65;">

                    {{-- ── Form filter ── --}}
                    <form method="GET" action="{{ route('rawreservation') }}" id="filterForm">
                        <input type="hidden" name="view" id="filterView" value="{{ request('view', 'list') }}">
                        <div class="row mb-3 flex-nowrap" style="row-gap:8px; min-width: 1400px;">
                            <div class="col-3" style="min-width: 250px;">
                                <input type="text" class="form-control form-control-sm"
                                    name="search" placeholder="Cari nama / no. HP..."
                                    value="{{ $filterSearch }}">
                            </div>
                            <div class="col-2" style="min-width: 150px;">
                                <select class="form-control form-control-sm" name="branch_id">
                                    <option value="">Semua Cabang</option>
                                    @foreach ($branches as $b)
                                        <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>
                                            {{ $b->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-2" style="min-width: 150px;">
                                <select class="form-control form-control-sm" name="doctor_id">
                                    <option value="">Semua Dokter</option>
                                    @foreach ($doctors as $d)
                                        <option value="{{ $d->id }}" {{ $filterDoctor == $d->id ? 'selected' : '' }}>
                                            {{ $d->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-2" style="min-width: 150px;">
                                <select class="form-control form-control-sm" name="status">
                                    <option value="">Semua Status</option>
                                    @foreach ($statuses as $k => $opt)
                                        <option value="{{ $k }}" {{ $filterStatus == $k ? 'selected' : '' }}>
                                            {{ $opt['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-1" style="min-width:160px">
                                <input type="date" class="form-control form-control-sm"
                                    name="date_from" value="{{ $filterDateFrom }}">
                            </div>
                            <div class="col-1" style="min-width:160px">
                                <input type="date" class="form-control form-control-sm"
                                    name="date_to" value="{{ $filterDateTo }}">
                            </div>
                            <div class="col-1 d-flex" style="min-width:120px">
                                <button type="submit" class="btn btn-sm btn-primary mr-1 flex-fill">
                                    <i class="fa fa-search"></i> Filter
                                </button>
                                <a href="{{ route('rawreservation') }}" class="btn btn-sm btn-warning" title="Reset Filter">
                                    <i class="fa fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </form>

                    {{-- ── Info hasil filter ── --}}
                    @if ($filterBranch || $filterDoctor || $filterStatus || $filterDateFrom || $filterDateTo || $filterSearch)
                        <div class="mb-2" style="font-size 1.25rem; color:#666">
                            <i class="fa fa-filter text-primary"></i>
                            Menampilkan <strong>{{ $reservations->total() }}</strong> reservasi
                            @if ($filterSearch) · cari "<em>{{ $filterSearch }}</em>" @endif
                            @if ($filterBranch) · cabang: <em>{{ $branches->firstWhere('id', $filterBranch)?->name }}</em> @endif
                            @if ($filterDoctor) · dokter: <em>{{ $doctors->firstWhere('id', $filterDoctor)?->name }}</em> @endif
                            @if ($filterStatus) · status: <em>{{ $statuses[$filterStatus]['label'] ?? $filterStatus }}</em> @endif
                            @if ($filterDateFrom || $filterDateTo) · tanggal: <em>{{ $filterDateFrom ?: '...' }} — {{ $filterDateTo ?: '...' }}</em> @endif
                        </div>
                    @endif

                    {{-- ── Toolbar: Tambah + Tab toggle ── --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">
                        <a href="{{ route('rawreservation.new') }}" class="btn btn-sm btn-primary">
                            <i class="fa fa-plus"></i> Tambah
                        </a>
                        <div class="view-tab-group">
                            <button class="view-tab active" id="tabList" onclick="switchTab('list')">
                                <i class="fa fa-list"></i> List
                            </button>
                            <button class="view-tab" id="tabCal" onclick="switchTab('cal')">
                                <i class="fa fa-calendar"></i> Kalender
                            </button>
                        </div>
                    </div>

                    {{-- ══════════════════════════════════════
                         TAB: LIST
                    ══════════════════════════════════════ --}}
                    <div id="viewList">
                        <table class="table table-bordered table-hover" cellspacing="0" style="font-size:0.85rem; white-space: nowrap;">
                            <thead>
                                <tr>
                                    <th width="3%">No</th>
                                    <th>Customer</th>
                                    <th>Dokter</th>
                                    <th>Cabang</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Perawatan</th>
                                    <th>Promo</th>
                                    <th>Status</th>
                                    <th>FU H-1</th>
                                    <th>FU H</th>
                                    <th>FU -1 Jam</th>
                                    <th>Kehadiran</th>
                                    <th width="5%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reservations as $key => $row)
                                    @php $si = $row->status_info; @endphp
                                    <tr>
                                        <td>{{ $reservations->firstItem() + $key }}</td>
                                        <td>{{ $row->customer->name ?? '-' }}</td>
                                        <td>{{ $row->doctor->name ?? '-' }}</td>
                                        <td>{{ $row->branch->name ?? '-' }}</td>
                                        <td>{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</td>
                                        <td nowrap>{{ \Carbon\Carbon::parse($row->start_hour)->format('H:i') }} - {{ \Carbon\Carbon::parse($row->end_hour)->format('H:i') }}</td>
                                        <td style="white-space: normal; min-width: 200px;">
                                            @forelse ($row->services as $svc)
                                                <span class="badge badge-light border" style="font-size:0.75rem;margin:1px">
                                                    {{ $svc->docService->name ?? '-' }}
                                                </span>
                                            @empty
                                                <span class="text-muted">-</span>
                                            @endforelse
                                        </td>
                                        <td>
                                            @if($row->promo)
                                                <span class="badge badge-info" style="font-size:0.75rem">{{ $row->promo->promo_code }}</span>
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
                                                data-id="{{ $row->id }}" data-field="follow_up_h_min_1_jam">
                                                <option value="">-- --</option>
                                                <option value="confirm"    {{ $row->follow_up_h_min_1_jam == 'confirm'    ? 'selected' : '' }}>✅ Confirm</option>
                                                <option value="reschedule" {{ $row->follow_up_h_min_1_jam == 'reschedule' ? 'selected' : '' }}>🔄 Reschedule</option>
                                                <option value="cancel"     {{ $row->follow_up_h_min_1_jam == 'cancel'     ? 'selected' : '' }}>❌ Cancel</option>
                                                <option value="no_respon"  {{ $row->follow_up_h_min_1_jam == 'no_respon'  ? 'selected' : '' }}>📵 No Respon</option>
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
                        {{ $reservations->links() }}
                    </div>

                    {{-- ══════════════════════════════════════
                         TAB: CALENDAR
                    ══════════════════════════════════════ --}}
                    <div id="viewCal" style="display:none;">
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
            <div class="modal-body" style="padding:24px;">
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
.cal-event-top .ev-doctor { font-size: 1.05rem; color:rgb(80,50,30); display: flex; align-items: flex-start; }
    
.cal-event-top i {
    font-size: 1rem !important;
    margin-right: 5px;
    width: 16px;
    text-align: center;
    flex-shrink: 0;
    margin-top: 3px;
}

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
#modalBody tr td:first-child { font-weight:600; color:rgb(70,21,0); width:38%; white-space:nowrap; }
#modalBody tr td { border-color:rgb(208,196,191) !important; vertical-align:middle; }
</style>
@endsection

@section('js')
<script>
function switchTab(tab) {
    const isCal = tab === 'cal';
    document.getElementById('viewList').style.display = isCal ? 'none' : '';
    document.getElementById('viewCal').style.display  = isCal ? ''     : 'none';
    document.getElementById('tabList').classList.toggle('active', !isCal);
    document.getElementById('tabCal').classList.toggle('active',  isCal);
    const viewInput = document.getElementById('filterView');
    if (viewInput) viewInput.value = tab;
    if (isCal) {
        curYear = today.getFullYear();
        curMonth = today.getMonth();
        renderCalendar(curYear, curMonth);
    }
}

const RAW_RESERVATIONS = @json($calendarReservations).map(r => {
    if (r.date) {
        const d = new Date(r.date);
        r.date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    return r;
});

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
    const jam    = (ev.start_hour||'').substring(0, 5) + ' - ' + (ev.end_hour||'').substring(0, 5);
    const jamSelesai = (ev.end_hour||'').substring(0, 5);
    const nama   = ev.customer ? ev.customer.name : '-';
    const dokter = ev.doctor   ? ev.doctor.name   : '-';

    // Deteksi apakah waktu sudah terlewat (Hari ini lewat jam selesai, atau memang sudah lewat hari)
    let isPast = false;
    if (ev.date < nowDate) {
        isPast = true;
    } else if (ev.date === nowDate && jamSelesai && jamSelesai < nowTime) {
        isPast = true;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'cal-event-wrapper ' + cfg.cls + (isPast ? ' ev-past' : '');

    const card = document.createElement('div');
    card.className = 'cal-event-card';
    card.innerHTML = `
        <div class="cal-event-top">
            <span class="ev-time"><i class="fa fa-clock"></i>${jam}</span>
            <span class="ev-sep">|</span>
            <span class="ev-name"><i class="fa fa-user"></i>${nama}</span>
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

// ── Fungsi modal tidak diubah dari versi sebelumnya ──

function showModal(ev) {
    const cfg = STATUS_CFG[ev.status] || { label: ev.status || '-', style: 'background:#eee;color:#333' };
    const tgl = ev.date ? ev.date.substring(0,10).split('-').reverse().join('/') : '-';
    const jam = (ev.start_hour||'').substring(0,5) + ' – ' + (ev.end_hour||'').substring(0,5);
    document.getElementById('modalBody').innerHTML = `
        <tr><td>Customer</td><td>${ev.customer ? ev.customer.name : '-'}</td></tr>
        <tr><td>Dokter</td><td>${ev.doctor ? ev.doctor.name : '-'}</td></tr>
        <tr><td>Cabang</td><td>${ev.branch ? ev.branch.name : '-'}</td></tr>
        <tr><td>Tanggal</td><td>${tgl}</td></tr>
        <tr><td>Jam</td><td>${jam}</td></tr>
        <tr><td>Status</td><td><span class="badge" style="${cfg.style} font-size:0.85rem; padding:5px 10px;">${cfg.label}</span></td></tr>
    `;
    const searchName = ev.customer ? encodeURIComponent(ev.customer.name) : '';
    document.getElementById('modalDetailBtn').href = '{{ route("rawreservation") }}?search=' + searchName;
    document.getElementById('modalDetailBtn').style.display = '';
    $('#eventModal').modal('show');
}

function showAllModal(dateKey, events) {
    const tgl = dateKey.split('-').reverse().join('/');
    const header = `
        <tr style="background:rgb(250,245,243);">
            <th colspan="5" style="border-top:none; color:rgb(70,21,0); font-weight:700; font-size:0.95rem;">Reservasi Tanggal: ${tgl}</th>
        </tr>
        <tr style="background:#f1f1f1; font-weight:bold;">
            <th style="border-top:1px solid #dee2e6;">Jam</th>
            <th style="border-top:1px solid #dee2e6;">Nama Customer</th>
            <th style="border-top:1px solid #dee2e6;">Nama Dokter</th>
            <th style="border-top:1px solid #dee2e6;">Status</th>
            <th style="border-top:1px solid #dee2e6;" class="text-center">Lihat Pasien</th>
        </tr>
    `;
    const rows = events.map(ev => {
        const cfg = STATUS_CFG[ev.status] || { label: ev.status || '-', style: 'background:#eee;color:#333' };
        const searchName = ev.customer ? encodeURIComponent(ev.customer.name) : '';
        return `<tr>
            <td>${(ev.start_hour||'').substring(0,5)} - ${(ev.end_hour||'').substring(0,5)}</td>
            <td>${ev.customer ? ev.customer.name : '-'}</td>
            <td>${ev.doctor ? ev.doctor.name : '-'}</td>
            <td><span class="badge" style="${cfg.style} font-size:0.75rem; padding:4px 8px;">${cfg.label}</span></td>
            <td class="text-center"><a href="{{ route('rawreservation') }}?search=${searchName}" class="btn btn-info btn-sm" style="padding:2px 6px;" title="Lihat Pasien"><i class="fa fa-eye"></i></a></td>
        </tr>`;
    }).join('');
    document.getElementById('modalBody').innerHTML = header + rows;
    document.getElementById('modalDetailBtn').style.display = 'none';
    $('#eventModal').modal('show');
}

document.getElementById('prevMonth').addEventListener('click', () => { curMonth--; if (curMonth<0){curMonth=11;curYear--;} renderCalendar(curYear,curMonth); });
document.getElementById('nextMonth').addEventListener('click', () => { curMonth++; if (curMonth>11){curMonth=0;curYear++;} renderCalendar(curYear,curMonth); });
document.getElementById('todayBtn').addEventListener('click', () => { curYear=today.getFullYear(); curMonth=today.getMonth(); renderCalendar(curYear,curMonth); });

$(document).ready(function() {
    switchTab('{{ request('view', 'list') }}');
});

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
</script>
@endsection