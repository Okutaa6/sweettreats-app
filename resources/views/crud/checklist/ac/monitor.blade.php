@extends('layouts.app')

{{--
    Monitoring Jadwal Cuci AC — sisi Supervisor.
    Tab cabang + KPI + grid kartu unit + kelola unit (modal) + riwayat (modal).
    Style .ac-* ada di public/assets/css/checklist.sweettreats.css.
--}}

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Monitoring Jadwal Cuci AC</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div style="display:flex;flex-direction:column;gap:16px;max-width:1240px;width:100%;margin:0 auto">

        {{-- ─── HERO ─── --}}
        <div class="ac-hero">
            <div class="ac-hero__icon"><i class="fas fa-snowflake"></i></div>
            <div class="ac-hero__body">
                <div class="ac-hero__title">Monitoring Jadwal Cuci AC</div>
                <div class="ac-hero__sub">Pantau jadwal cuci &amp; isi freon seluruh cabang. Data diinput GS tiap cabang; jatuh tempo dihitung otomatis dari cuci terakhir.</div>
            </div>
            <div class="ac-hero__chips">
                <a href="{{ route('checklist') }}" class="ac-btn-outline"><i class="fas fa-arrow-left"></i> Dashboard</a>
                <button type="button" class="ac-btn-submit" data-ac-modal-open="acModalCreate" style="width:auto;">
                    <i class="fas fa-plus"></i> Tambah Unit AC
                </button>
            </div>
        </div>

        {{-- ─── TAB CABANG ─── --}}
         @if($branches->count())
            <div class="ac-btabs">
                 @foreach($branches as $b)
                    <a href="{{ route('checklist.ac', ['branch' => $b->id]) }}"
                       class="ac-btabs__tab {{ $branchId === $b->id ? 'is-active' : '' }}">{{ $b->name }}</a>
                 @endforeach
            </div>
         @endif

        <style>
            .ac-visit__status { display:inline-block; margin-left:6px; padding:2px 9px; border-radius:999px; font-size:10.5px; font-weight:800; vertical-align:middle; }
            .ac-visit__rnote { margin-top:7px; font-size:11.5px; color:#A32D2D; background:#FCEBEB; border-radius:8px; padding:6px 10px; }
            .ac-visit__review { display:flex; gap:8px; margin-top:9px; }
            .ac-visit__ok, .ac-visit__no { border:none; border-radius:9px; padding:7px 14px; font-weight:800; font-size:12px; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; gap:6px; }
            .ac-visit__ok { background:#1F6E5F; color:#fff; }
            .ac-visit__no { background:#FCEBEB; color:#A32D2D; }
            .ac-visit__ok:disabled, .ac-visit__no:disabled { opacity:.6; cursor:not-allowed; }
            .ac-rejectbar { display:flex; gap:7px; margin-top:8px; flex-wrap:wrap; align-items:center; }
            .ac-rejectbar__input { flex:1; min-width:160px; border:1px solid #E7D3BE; border-radius:9px; padding:7px 11px; font-size:12.5px; font-family:inherit; }
            .ac-rejectbar__send { background:#8A3A12; color:#fff; border:none; border-radius:9px; padding:7px 14px; font-weight:800; font-size:12px; cursor:pointer; }
            .ac-rejectbar__cancel { background:#F1E7DA; color:#6B4423; border:none; border-radius:9px; padding:7px 12px; font-weight:700; font-size:12px; cursor:pointer; }
        </style>

        {{-- ─── KPI ─── --}}
        <div class="ac-kpis">
            <div class="ac-kpi">
                <div class="ac-kpi__icon" style="background:#F4E7D6;color:#8A3A12;"><i class="fas fa-snowflake"></i></div>
                <div class="ac-kpi__num">{{ $units->count() }}</div>
                <div class="ac-kpi__label">Total Unit AC</div>
            </div>
            <div class="ac-kpi">
                <div class="ac-kpi__icon" style="background:#FAEEDA;color:#A66019;"><i class="fas fa-clock"></i></div>
                <div class="ac-kpi__num">{{ $statusCounts[\App\Models\AcUnit::STATUS_SEGERA] }}</div>
                <div class="ac-kpi__label">Segera Dicuci (≤ {{ \App\Models\AcUnit::DUE_SOON_DAYS }} hari)</div>
            </div>
            <div class="ac-kpi">
                <div class="ac-kpi__icon" style="background:#FCEBEB;color:#A32D2D;"><i class="fas fa-exclamation-circle"></i></div>
                <div class="ac-kpi__num">{{ $statusCounts[\App\Models\AcUnit::STATUS_TERLAMBAT] }}</div>
                <div class="ac-kpi__label">Terlambat</div>
            </div>
            <div class="ac-kpi">
                <div class="ac-kpi__icon" style="background:#FAEEDA;color:#A66019;"><i class="fas fa-hourglass-half"></i></div>
                <div class="ac-kpi__num">{{ $pendingCount }}</div>
                <div class="ac-kpi__label">Menunggu Approval</div>
            </div>
        </div>

        {{-- ─── GRID KARTU UNIT ─── --}}
         @if($units->isEmpty())
            <div class="ac-empty">
                <div class="ac-empty__icon"><i class="fas fa-snowflake"></i></div>
                <h5>Belum ada unit AC</h5>
                <p>Tambahkan unit AC per ruangan lewat tombol "Tambah Unit AC" di atas — GS cabang langsung bisa mulai mencatat servis.</p>
            </div>
         @else
            <div class="ac-grid">
                 @foreach($units as $unit)
                    @include('crud.checklist.ac._unit_card', ['unit' => $unit, 'mode' => 'supervisor', 'today' => $today, 'showBranch' => ! $branchId])
                 @endforeach
            </div>
         @endif
    </div>
</div>

{{-- ─── MODAL: TAMBAH UNIT ─── --}}
<div class="ac-modal" id="acModalCreate" hidden>
    <div class="ac-modal__backdrop" data-ac-modal-close></div>
    <div class="ac-modal__box" role="dialog" aria-modal="true" aria-label="Tambah unit AC">
        <div class="ac-modal__head">
            <i class="fas fa-plus-circle"></i>
            <div class="ac-modal__title">Tambah Unit AC</div>
            <button type="button" class="ac-modal__x" data-ac-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('checklist.ac.unit.store') }}">
            @csrf
            <div class="ac-modal__body">
                @include('crud.checklist.ac._unit_fields', ['branches' => $branches, 'branchId' => $branchId])
            </div>
            <div class="ac-modal__foot">
                <button type="button" class="ac-btn-outline" data-ac-modal-close>Batal</button>
                <button type="submit" class="ac-btn-submit" style="width:auto;"><i class="fas fa-check"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ─── MODAL: EDIT UNIT ─── --}}
<div class="ac-modal" id="acModalEdit" hidden>
    <div class="ac-modal__backdrop" data-ac-modal-close></div>
    <div class="ac-modal__box" role="dialog" aria-modal="true" aria-label="Edit unit AC">
        <div class="ac-modal__head">
            <i class="fas fa-pen"></i>
            <div class="ac-modal__title">Edit Unit AC</div>
            <button type="button" class="ac-modal__x" data-ac-modal-close>✕</button>
        </div>
        <form method="POST" action="" id="acEditForm">
            @csrf
            @method('PUT')
            {{-- Optimistic locking: nilai updated_at saat form dibuka; dibandingkan
                 server agar edit dari 2 tab/orang tidak saling menimpa diam-diam. --}}
            <input type="hidden" name="expected_updated_at" id="acEditUpdatedAt" value="">
            <div class="ac-modal__body">
                @include('crud.checklist.ac._unit_fields', ['branches' => $branches, 'branchId' => $branchId])
            </div>
            <div class="ac-modal__foot">
                <button type="button" class="ac-btn-outline" data-ac-modal-close>Batal</button>
                <button type="submit" class="ac-btn-submit" style="width:auto;"><i class="fas fa-check"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

{{-- ─── MODAL: RIWAYAT SERVIS ─── --}}
<div class="ac-modal" id="acModalHistory" hidden>
    <div class="ac-modal__backdrop" data-ac-modal-close></div>
    <div class="ac-modal__box" role="dialog" aria-modal="true" aria-label="Riwayat servis unit AC">
        <div class="ac-modal__head">
            <i class="fas fa-history"></i>
            <div class="ac-modal__title">Riwayat Servis — <span id="acHistoryRoom"></span></div>
            <button type="button" class="ac-modal__x" data-ac-modal-close>✕</button>
        </div>
        <div class="ac-modal__body" id="acHistoryBody">
            <div class="ac-modal__loading">Memuat…</div>
        </div>
        <div class="ac-modal__foot">
            <button type="button" class="ac-btn-outline" data-ac-modal-close>Tutup</button>
        </div>
    </div>
</div>

@include('crud.checklist.my._confirm_modal')

{{-- ─── MODAL: CATAT SERVIS (supervisor → langsung approved) ─── --}}
<div class="ac-modal" id="acServiceModal" hidden>
    <div class="ac-modal__backdrop" data-ac-modal-close></div>
    <div class="ac-modal__box" role="dialog" aria-modal="true" aria-label="Catat servis AC" style="max-width:440px;">
        <div class="ac-modal__head">
            <i class="fas fa-broom"></i>
            <div class="ac-modal__title">Catat Servis — <span id="acServiceRoom"></span></div>
            <button type="button" class="ac-modal__x" data-ac-modal-close>✕</button>
        </div>
        <form method="POST" action="{{ route('checklist.ac.service.store') }}" id="acServiceForm" data-ac-service-form>
            @csrf
            <input type="hidden" name="ac_unit_id" value="">
            <div class="ac-modal__body">
                <div>
                    <label class="ac-form__lbl">Tanggal Servis</label>
                    <input type="date" name="service_date" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required class="ac-inp">
                </div>
                <div style="margin-top:12px;">
                    <label class="ac-form__lbl">Jenis Servis</label>
                    <div class="ac-types">
                        <div class="ac-type is-on" data-ac-type>
                            <label class="ac-type__check">
                                <input type="checkbox" name="wash" value="1" data-ac-type-toggle checked>
                                <i class="fas fa-broom"></i> Cuci AC
                            </label>
                            <div class="ac-amt"><span>Rp</span>
                                <input type="text" name="wash_amount" inputmode="numeric" placeholder="0" class="ac-inp" data-ac-amount></div>
                        </div>
                        <div class="ac-type" data-ac-type>
                            <label class="ac-type__check">
                                <input type="checkbox" name="freon" value="1" data-ac-type-toggle>
                                <i class="fas fa-tint"></i> Isi Freon
                            </label>
                            <div class="ac-amt"><span>Rp</span>
                                <input type="text" name="freon_amount" inputmode="numeric" placeholder="0" class="ac-inp" data-ac-amount disabled></div>
                        </div>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label class="ac-form__lbl">Rincian Biaya Lainnya <span class="ac-form__opt">(opsional)</span></label>
                    <div class="ac-items" data-ac-items></div>
                    <button type="button" class="ac-btn-add" data-ac-add-item>+ Tambah Rincian</button>
                </div>
                <div class="ac-total">
                    <span>Total kunjungan</span>
                    <b data-ac-total>Rp 0</b>
                </div>
            </div>
            <div class="ac-modal__foot">
                <button type="button" class="ac-btn-outline" data-ac-modal-close>Batal</button>
                <button type="submit" class="ac-btn-save" disabled><i class="fas fa-check"></i> Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

@include('crud.checklist.ac._service_confirm_modal')

<script>
(function () {
    var historyUrl = @json(route('checklist.ac.unit.history', ['id' => '__ID__']));
    var serviceDestroyUrl = @json(route('checklist.ac.service.destroy', ['id' => '__ID__']));
    var serviceApproveUrl = @json(route('checklist.ac.service.approve', ['id' => '__ID__']));
    var serviceRejectUrl  = @json(route('checklist.ac.service.reject', ['id' => '__ID__']));
    var unitUpdateUrl = @json(route('checklist.ac.unit.update', ['id' => '__ID__']));
    var csrf = @json(csrf_token());

    function openModal(id) {
        var m = document.getElementById(id);
        if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; }
    }
    function closeModals() {
        document.querySelectorAll('.ac-modal').forEach(function (m) { m.hidden = true; });
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-ac-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () { openModal(btn.getAttribute('data-ac-modal-open')); });
    });
    document.querySelectorAll('[data-ac-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModals);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModals(); });

    // ── Edit unit: isi form dari data-attribute kartu ──
    document.querySelectorAll('[data-ac-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = document.getElementById('acEditForm');
            form.action = unitUpdateUrl.replace('__ID__', btn.getAttribute('data-ac-edit'));
            form.querySelector('[name=room_name]').value          = btn.getAttribute('data-room') || '';
            form.querySelector('[name=capacity_pk]').value        = btn.getAttribute('data-pk') || '';
            form.querySelector('[name=wash_interval_days]').value = btn.getAttribute('data-interval') || 60;
            form.querySelector('[name=sort_order]').value         = btn.getAttribute('data-sort') || 0;
            form.querySelector('[name=notes]').value              = btn.getAttribute('data-notes') || '';
            form.querySelector('#acEditUpdatedAt').value          = btn.getAttribute('data-updated') || '';
            var branchField = form.querySelector('[name=raw_branch_id]');
            if (branchField) branchField.value = btn.getAttribute('data-branch') || '';
            openModal('acModalEdit');
        });
    });

    // ── Riwayat servis: fetch JSON, render visit card + konfirmasi inline ──
    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    function postForm(action, extra) {
        var f = document.createElement('form');
        f.method = 'POST'; f.action = action;
        var h = '<input type="hidden" name="_token" value="' + csrf + '">';
        Object.keys(extra || {}).forEach(function (k) {
            h += '<input type="hidden" name="' + k + '" value="' + esc(extra[k]) + '">';
        });
        f.innerHTML = h;
        document.body.appendChild(f); f.submit();
    }

    function visitHtml(rec) {
        var linesHtml = '';
        (rec.items || []).forEach(function (it) {
            linesHtml += '<div class="ac-visit__line"><span>' + esc(it.description) + '</span><b>' + esc(it.amount_fmt) + '</b></div>';
        });
        var delHtml = rec.can_delete
            ? '<button type="button" class="ac-visit__del" data-del-id="' + esc(rec.id) + '" title="Hapus catatan (koreksi)"><i class="fas fa-trash-alt"></i></button>'
            : '';
        var pill = '<span class="ac-visit__status" style="background:' + esc(rec.status_bg) + ';color:' + esc(rec.status_color) + ';">' + esc(rec.status_label) + '</span>';
        var noteHtml = (rec.status === 'rejected' && rec.review_note)
            ? '<div class="ac-visit__rnote"><i class="fas fa-comment-dots"></i> Alasan ditolak: ' + esc(rec.review_note) + '</div>'
            : '';
        var reviewHtml = rec.can_review
            ? '<div class="ac-visit__review">'
            +   '<button type="button" class="ac-visit__ok" data-approve-id="' + esc(rec.id) + '"><i class="fas fa-check"></i> Setujui</button>'
            +   '<button type="button" class="ac-visit__no" data-reject-id="' + esc(rec.id) + '"><i class="fas fa-times"></i> Tolak</button>'
            + '</div>'
            + '<div class="ac-rejectbar" hidden>'
            +   '<input type="text" class="ac-rejectbar__input" placeholder="Alasan penolakan (min. 3 huruf)…" maxlength="500">'
            +   '<button type="button" class="ac-rejectbar__send" data-reject-yes="' + esc(rec.id) + '">Kirim</button>'
            +   '<button type="button" class="ac-rejectbar__cancel">Batal</button>'
            + '</div>'
            : '';
        return '<div class="ac-visit">'
            + '<div class="ac-visit__top">'
            +   '<span class="ac-visit__icon"><i class="fas fa-broom"></i></span>'
            +   '<div class="ac-visit__sum"><b>' + esc(rec.date || '—') + '</b> · Total <b>' + esc(rec.total_fmt) + '</b>'
            +     (rec.creator ? ' · ' + esc(rec.creator) : '') + ' ' + pill + '</div>'
            +   delHtml
            + '</div>'
            + '<div class="ac-visit__lines">' + linesHtml + '</div>'
            + noteHtml
            + reviewHtml
            + '<div class="ac-confirmbar" hidden>'
            +   '<span>Hapus catatan ini?</span>'
            +   '<button type="button" class="ac-confirmbar__yes" data-del-yes="' + esc(rec.id) + '">Ya, hapus</button>'
            +   '<button type="button" class="ac-confirmbar__no">Batal</button>'
            + '</div>'
            + '</div>';
    }

    function bindVisitActions(scope) {
        scope.querySelectorAll('[data-del-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                scope.querySelectorAll('.ac-confirmbar').forEach(function (cb) { cb.hidden = true; });
                btn.closest('.ac-visit').querySelector('.ac-confirmbar').hidden = false;
            });
        });
        scope.querySelectorAll('.ac-confirmbar__no').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('.ac-confirmbar').hidden = true; });
        });
        scope.querySelectorAll('[data-del-yes]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                postForm(serviceDestroyUrl.replace('__ID__', btn.getAttribute('data-del-yes')), { _method: 'DELETE' });
            });
        });

        // ── Approve / Reject (supervisor) ──
        scope.querySelectorAll('[data-approve-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                postForm(serviceApproveUrl.replace('__ID__', btn.getAttribute('data-approve-id')), {});
            });
        });
        scope.querySelectorAll('[data-reject-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var bar = btn.closest('.ac-visit').querySelector('.ac-rejectbar');
                if (bar) { bar.hidden = false; bar.querySelector('.ac-rejectbar__input').focus(); }
            });
        });
        scope.querySelectorAll('.ac-rejectbar__cancel').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('.ac-rejectbar').hidden = true; });
        });
        scope.querySelectorAll('[data-reject-yes]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var bar = btn.closest('.ac-rejectbar');
                var reason = (bar.querySelector('.ac-rejectbar__input').value || '').trim();
                if (reason.length < 3) { bar.querySelector('.ac-rejectbar__input').focus(); return; }
                btn.disabled = true;
                postForm(serviceRejectUrl.replace('__ID__', btn.getAttribute('data-reject-yes')), { reason: reason });
            });
        });
    }

    function renderVisits(body, data) {
        var records = data.records || [];
        if (! records.length) {
            body.innerHTML = '<div class="ac-modal__loading">Belum ada catatan servis untuk unit ini.</div>';
            return;
        }

        // Dropdown bulan diisi dari bulan yang benar-benar ada di riwayat.
        var months = [], seen = {};
        records.forEach(function (r) { if (r.ym && ! seen[r.ym]) { seen[r.ym] = 1; months.push(r); } });
        var monthOpts = '<option value="">Semua bulan</option>';
        months.forEach(function (r) { monthOpts += '<option value="' + esc(r.ym) + '">' + esc(r.month_label) + '</option>'; });

        body.innerHTML =
            '<div class="ac-hist-filter">'
            + '<select class="ac-inp ac-hist-month" data-hist-month aria-label="Filter bulan">' + monthOpts + '</select>'
            + '<input type="date" class="ac-inp ac-hist-date" data-hist-date aria-label="Filter tanggal">'
            + '<button type="button" class="ac-hist-reset" data-hist-reset hidden>Reset</button>'
            + '</div>'
            + '<div class="ac-hist-list" data-hist-list></div>';

        var listEl = body.querySelector('[data-hist-list]');
        var monthSel = body.querySelector('[data-hist-month]');
        var dateInp = body.querySelector('[data-hist-date]');
        var resetBtn = body.querySelector('[data-hist-reset]');

        function paint() {
            var ym = monthSel.value, d = dateInp.value;
            var filtered = records.filter(function (r) {
                return (! ym || r.ym === ym) && (! d || r.iso === d);
            });
            resetBtn.hidden = ! (ym || d);
            if (! filtered.length) {
                listEl.innerHTML = '<div class="ac-modal__loading">Tidak ada catatan pada rentang ini.</div>';
                return;
            }
            listEl.innerHTML = filtered.map(visitHtml).join('');
            bindVisitActions(listEl);
        }
        monthSel.addEventListener('change', paint);
        dateInp.addEventListener('change', paint);
        resetBtn.addEventListener('click', function () { monthSel.value = ''; dateInp.value = ''; paint(); });
        paint();
    }

    document.querySelectorAll('[data-ac-history]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.getElementById('acHistoryBody');
            document.getElementById('acHistoryRoom').textContent = btn.getAttribute('data-room') || '';
            body.innerHTML = '<div class="ac-modal__loading">Memuat…</div>';
            openModal('acModalHistory');

            fetch(historyUrl.replace('__ID__', btn.getAttribute('data-ac-history')), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { renderVisits(body, data); })
            .catch(function () {
                body.innerHTML = '<div class="ac-modal__loading">Gagal memuat riwayat. Coba lagi.</div>';
            });
        });
    });

    // ── Catat Servis (supervisor): buka modal + set unit + reset isi ──
    var acServiceForm = document.getElementById('acServiceForm');
    document.querySelectorAll('[data-ac-service-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (! acServiceForm) return;
            acServiceForm.reset();
            var items = acServiceForm.querySelector('[data-ac-items]');
            if (items) items.innerHTML = '';
            acServiceForm.querySelector('[name=ac_unit_id]').value = btn.getAttribute('data-ac-service-open');
            document.getElementById('acServiceRoom').textContent = btn.getAttribute('data-room') || '';
            // Sinkron ulang state (is-on / disable / total / tombol) yang dikelola _service_form_js.
            acServiceForm.querySelectorAll('[data-ac-type-toggle]').forEach(function (cb) { cb.dispatchEvent(new Event('change')); });
            var d = acServiceForm.querySelector('[name=service_date]');
            if (d) d.dispatchEvent(new Event('input'));
            openModal('acServiceModal');
        });
    });
})();
</script>
@include('crud.checklist.ac._service_form_js')
@endsection
