@extends('layouts.app')

{{--
    Jadwal Cuci AC — sisi GS cabang. Desain mengikuti handoff hifi
    "design_handoff_jadwal_cuci_ac" (mobile-first):
      - Kartu unit diurutkan Terlambat → Segera → Aman (urgency first)
      - Chip status di hero = quick-filter (klik untuk memfilter grid)
      - Form Catat Servis inline: rincian biaya repeatable + total live,
        nominal auto-format ribuan, simpan disabled sampai valid
      - Modal riwayat per unit dengan konfirmasi hapus inline
      - Toast bawah-tengah untuk flash sukses
    Style .ac-* ada di public/assets/css/checklist.sweettreats.css.
--}}

@section('content')
<div class="container-fluid">

    <div class="ac-wrap">

        {{-- ─── NAV ROW ─── --}}
        <div class="ac-nav">
            <a href="{{ route('mychecklist') }}" class="ac-nav-back">
                <i class="fas fa-chevron-left"></i> Checklist Saya
            </a>
            <span class="ac-nav-active"><i class="fas fa-snowflake"></i> Jadwal Cuci AC</span>
        </div>

        {{-- ─── HERO + CHIP FILTER ─── --}}
        <div class="ac-hero">
            <div class="ac-hero__icon"><i class="fas fa-snowflake"></i></div>
            <div class="ac-hero__body">
                <div class="ac-hero__title">Jadwal Cuci AC — {{ $branch?->name ?? 'Cabang Anda' }}</div>
                <div class="ac-hero__sub">Catat setiap cuci AC &amp; isi freon. Jadwal berikutnya dihitung otomatis dari tanggal cuci terakhir.</div>
            </div>
            <div class="ac-hero__chips">
                <button type="button" class="ac-chipbtn ac-chipbtn--aman" data-ac-chip="aman" title="Klik untuk memfilter">
                    <span class="ac-chipbtn__dot"></span>{{ $statusCounts[\App\Models\AcUnit::STATUS_AMAN] }} Aman
                </button>
                <button type="button" class="ac-chipbtn ac-chipbtn--segera" data-ac-chip="segera" title="Klik untuk memfilter">
                    <span class="ac-chipbtn__dot"></span>{{ $statusCounts[\App\Models\AcUnit::STATUS_SEGERA] }} Segera
                </button>
                <button type="button" class="ac-chipbtn ac-chipbtn--terlambat" data-ac-chip="terlambat" title="Klik untuk memfilter">
                    <span class="ac-chipbtn__dot"></span>{{ $statusCounts[\App\Models\AcUnit::STATUS_TERLAMBAT] }} Terlambat
                </button>
                <button type="button" class="ac-chipbtn ac-chipbtn--belum" data-ac-chip="belum" title="Klik untuk memfilter">
                    <span class="ac-chipbtn__dot"></span>{{ $statusCounts[\App\Models\AcUnit::STATUS_BELUM] }} Belum Dicuci
                </button>
            </div>
        </div>

         @if($errors->any())
            <div class="ac-errors">
                 @foreach($errors->all() as $err)
                    <div><i class="fas fa-exclamation-circle"></i> {{ $err }}</div>
                 @endforeach
            </div>
         @endif

        {{-- ─── FILTER NOTE ─── --}}
        <div class="ac-filternote" data-ac-filternote hidden>
            Menampilkan <b data-ac-shown>0</b> unit — filter: <b data-ac-filterlabel></b>
            <button type="button" data-ac-reset>Reset</button>
        </div>

        {{-- ─── GRID KARTU UNIT ─── --}}
         @if($units->isEmpty())
            <div class="ac-empty">
                <div class="ac-empty__icon"><i class="fas fa-snowflake"></i></div>
                <h5>Belum ada unit AC terdaftar</h5>
                <p>Hubungi supervisor untuk menambahkan daftar ruangan &amp; unit AC.</p>
            </div>
         @else
            <div class="ac-grid" data-ac-grid>
                 @foreach($units as $unit)
                    @include('crud.checklist.ac._unit_card', ['unit' => $unit, 'mode' => 'gs', 'today' => $today])
                 @endforeach
            </div>
         @endif
    </div>
</div>

{{-- ─── MODAL: RIWAYAT SERVIS PER UNIT ─── --}}
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

{{-- ─── MODAL: KONFIRMASI SIMPAN CATATAN SERVIS ─── --}}
<div class="ac-modal" id="acConfirmModal" hidden>
    <div class="ac-modal__backdrop" data-ac-confirm-cancel></div>
    <div class="ac-modal__box" role="dialog" aria-modal="true" aria-label="Konfirmasi simpan catatan servis" style="max-width:430px;">
        <div class="ac-modal__head">
            <i class="fas fa-question-circle" id="acConfirmIcon"></i>
            <div class="ac-modal__title" id="acConfirmTitle">Konfirmasi</div>
            <button type="button" class="ac-modal__x" data-ac-confirm-cancel>✕</button>
        </div>
        <div class="ac-modal__body">
            <p id="acConfirmMsg" style="margin:0;font-size:13.5px;line-height:1.65;color:#5A4632;"></p>
            <div id="acConfirmTotal" style="margin-top:13px;padding:11px 15px;background:#FBF6EE;border-radius:11px;align-items:center;justify-content:space-between;display:none;">
                <span style="font-size:12px;font-weight:700;color:#9A7256;">Total biaya kunjungan</span>
                <span id="acConfirmTotalVal" style="font-size:19px;font-weight:800;color:#5A3A22;"></span>
            </div>
        </div>
        <div class="ac-modal__foot">
            <button type="button" class="ac-btn-outline" id="acConfirmCancel" data-ac-confirm-cancel>Batal</button>
            <button type="button" id="acConfirmOk"
                    style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#1F7A5A,#14624a);color:#fff;border:none;border-radius:11px;padding:11px 20px;font-weight:800;font-size:13px;cursor:pointer;font-family:inherit;">
                <i class="fas fa-check"></i> Ya, Simpan
            </button>
        </div>
    </div>
</div>

{{-- ─── TOAST (flash session) ─── --}}
 @if(session('success'))
    <div class="ac-toast" data-ac-toast><i class="fas fa-check" style="color:#7FD3A8;"></i> {{ session('success') }}</div>
 @endif
 @if(session('error'))
    <div class="ac-toast" data-ac-toast><i class="fas fa-exclamation-circle" style="color:#F0A8A8;"></i> {{ session('error') }}</div>
 @endif

<script>
(function () {
    var STATUS_LABEL = { aman: 'Aman', segera: 'Segera Dicuci', terlambat: 'Terlambat', belum: 'Belum Dicuci' };

    // ── Toast auto-dismiss ──
    document.querySelectorAll('[data-ac-toast]').forEach(function (t) {
        setTimeout(function () { t.style.opacity = '0'; }, 2600);
        setTimeout(function () { t.remove(); }, 3100);
    });

    // ── Chip quick-filter ──
    var chips = document.querySelectorAll('[data-ac-chip]');
    var cards = document.querySelectorAll('[data-ac-status]');
    var note = document.querySelector('[data-ac-filternote]');
    var activeFilter = '';

    function applyFilter() {
        var shown = 0;
        cards.forEach(function (c) {
            var match = ! activeFilter || c.getAttribute('data-ac-status') === activeFilter;
            c.hidden = ! match;
            if (match) shown++;
        });
        chips.forEach(function (ch) {
            ch.classList.toggle('is-active', ch.getAttribute('data-ac-chip') === activeFilter);
        });
        if (note) {
            note.hidden = ! activeFilter;
            if (activeFilter) {
                note.querySelector('[data-ac-shown]').textContent = shown;
                note.querySelector('[data-ac-filterlabel]').textContent = STATUS_LABEL[activeFilter] || '';
            }
        }
    }
    chips.forEach(function (ch) {
        ch.addEventListener('click', function () {
            var k = ch.getAttribute('data-ac-chip');
            activeFilter = activeFilter === k ? '' : k;
            applyFilter();
        });
    });
    var resetBtn = document.querySelector('[data-ac-reset]');
    if (resetBtn) resetBtn.addEventListener('click', function () { activeFilter = ''; applyFilter(); });

    // ── Form Catat Servis: satu form terbuka pada satu waktu ──
    var toggles = document.querySelectorAll('[data-ac-form-toggle]');
    function closeAllForms(except) {
        toggles.forEach(function (btn) {
            var el = document.getElementById(btn.getAttribute('data-ac-form-toggle'));
            if (el && el !== except) {
                el.hidden = true;
                setToggleState(btn, false);
            }
        });
    }
    function setToggleState(btn, open) {
        btn.classList.toggle('is-open', open);
        btn.querySelector('span').textContent = btn.getAttribute(open ? 'data-label-close' : 'data-label-open');
    }
    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.getElementById(btn.getAttribute('data-ac-form-toggle'));
            if (! el) return;
            var willOpen = el.hidden;
            closeAllForms(willOpen ? el : null);
            el.hidden = ! willOpen;
            setToggleState(btn, willOpen);
        });
    });

    // ── Konfirmasi (modal) sebelum simpan catatan servis ──
    // Dua varian: nominal Rp 0 (peringatan "belum diisi") vs terisi (cek angka).
    // Submit ditahan → tampil modal; hanya lanjut kalau ditekan "Ya, Simpan".
    var acConfirmForm  = null;
    var acConfirmModal = document.getElementById('acConfirmModal');

    function openAcConfirm(form) {
        acConfirmForm = form;
        var total    = computeTotal(form);
        var icon     = document.getElementById('acConfirmIcon');
        var title    = document.getElementById('acConfirmTitle');
        var msg      = document.getElementById('acConfirmMsg');
        var totalBox = document.getElementById('acConfirmTotal');
        var totalVal = document.getElementById('acConfirmTotalVal');
        var okBtn    = document.getElementById('acConfirmOk');
        var cancelBtn = document.getElementById('acConfirmCancel');

        if (total <= 0) {
            // Nominal wajib — blokir, tidak ada opsi tetap simpan.
            icon.className = 'fas fa-exclamation-triangle';
            icon.style.color = '#A66019';
            title.textContent = 'Nominal belum diisi';
            msg.innerHTML = 'Isi nominal biaya servisnya dulu sebelum menyimpan.';
            totalBox.style.display = 'none';
            okBtn.style.display = 'none';
            cancelBtn.textContent = 'Oke, isi dulu';
        } else {
            icon.className = 'fas fa-question-circle';
            icon.style.color = '#8A3A12';
            title.textContent = 'Cek nominal dulu';
            msg.innerHTML = 'Pastikan nominalnya sudah benar. Setelah simpan, catatan <b>menunggu approval supervisor</b>.';
            totalVal.textContent = 'Rp ' + fmtDots(String(total));
            totalBox.style.display = 'flex';
            okBtn.style.display = 'inline-flex';
            okBtn.innerHTML = '<i class="fas fa-check"></i> Ya, Simpan';
            cancelBtn.textContent = 'Batal';
        }
        acConfirmModal.hidden = false;
    }
    function closeAcConfirm() { if (acConfirmModal) acConfirmModal.hidden = true; acConfirmForm = null; }

    document.querySelectorAll('form[data-ac-service-form]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault();
            e.stopImmediatePropagation();   // tahan submit sampai dikonfirmasi lewat modal
            openAcConfirm(f);
        });
    });
    document.querySelectorAll('[data-ac-confirm-cancel]').forEach(function (el) {
        el.addEventListener('click', closeAcConfirm);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && acConfirmModal && ! acConfirmModal.hidden) closeAcConfirm();
    });
    if (document.getElementById('acConfirmOk')) {
        document.getElementById('acConfirmOk').addEventListener('click', function () {
            var f = acConfirmForm;
            if (! f) return;
            // Normalisasi nominal → angka polos (buang titik ribuan) sebelum submit,
            // karena f.submit() tidak memicu listener normalisasi lain.
            f.querySelectorAll('[data-ac-amount]').forEach(function (amt) {
                if (! amt.disabled) amt.value = String(rawVal(amt));
            });
            var b = f.querySelector('.ac-btn-save');
            if (b) { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…'; }
            acConfirmModal.hidden = true;
            f.submit();
        });
    }

    // ── Form servis: jenis (Cuci AC / Isi Freon) + rincian lainnya ──
    var fmtDots = function (digits) {
        return digits ? String(parseInt(digits, 10)).replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '';
    };
    var rawVal = function (input) {
        return parseInt((input.value || '').replace(/\D/g, '') || '0', 10) || 0;
    };
    var otherRowHtml = function (idx) {
        return '<div class="ac-item-row">'
            + '<input type="text" name="others[' + idx + '][description]" maxlength="500" placeholder="mis. Ganti kapasitor" class="ac-inp">'
            + '<div class="ac-amt"><span>Rp</span>'
            +   '<input type="text" name="others[' + idx + '][amount]" inputmode="numeric" placeholder="0" class="ac-inp" data-ac-amount></div>'
            + '<button type="button" class="ac-item-row__remove" data-ac-remove-item title="Hapus baris">✕</button>'
            + '</div>';
    };
    // Total biaya form (jenis servis tercentang + baris "lainnya" yang lengkap).
    function computeTotal(form) {
        var total = 0;
        form.querySelectorAll('[data-ac-type]').forEach(function (t) {
            var cb = t.querySelector('[data-ac-type-toggle]');
            if (cb && cb.checked) total += rawVal(t.querySelector('[data-ac-amount]'));
        });
        form.querySelectorAll('.ac-item-row').forEach(function (row) {
            var d = (row.querySelector('input[type=text]:not([data-ac-amount])').value || '').trim();
            var a = rawVal(row.querySelector('[data-ac-amount]'));
            if (d && a > 0) total += a;
        });
        return total;
    }

    document.querySelectorAll('[data-ac-service-form]').forEach(function (form) {
        var wrap = form.querySelector('[data-ac-items]');
        var addBtn = form.querySelector('[data-ac-add-item]');
        var totalEl = form.querySelector('[data-ac-total]');
        var saveBtn = form.querySelector('.ac-btn-save');
        var dateInp = form.querySelector('[name=service_date]');
        var types = Array.prototype.slice.call(form.querySelectorAll('[data-ac-type]'));
        var idx = 0;

        function otherRows() { return Array.prototype.slice.call(wrap.querySelectorAll('.ac-item-row')); }

        function refresh() {
            var total = 0, any = false, invalid = false;
            types.forEach(function (t) {
                var cb = t.querySelector('[data-ac-type-toggle]');
                if (cb.checked) { any = true; total += rawVal(t.querySelector('[data-ac-amount]')); }
            });
            otherRows().forEach(function (row) {
                var d = (row.querySelector('input[type=text]:not([data-ac-amount])').value || '').trim();
                var a = rawVal(row.querySelector('[data-ac-amount]'));
                if (d && a > 0) { any = true; total += a; }
                else if (d || a > 0) { invalid = true; }
            });
            totalEl.textContent = 'Rp ' + (fmtDots(String(total)) || '0');
            var dateOk = dateInp.value && (! dateInp.max || dateInp.value <= dateInp.max);
            saveBtn.disabled = ! (any && ! invalid && dateOk);
        }

        function bindAmount(amt) {
            amt.addEventListener('input', function () {
                amt.value = fmtDots(amt.value.replace(/\D/g, '').slice(0, 10));
                refresh();
            });
        }

        // Toggle jenis servis: aktif → aktifkan input harga; nonaktif → kosong + disable.
        types.forEach(function (t) {
            var cb = t.querySelector('[data-ac-type-toggle]');
            var amt = t.querySelector('[data-ac-amount]');
            bindAmount(amt);
            cb.addEventListener('change', function () {
                t.classList.toggle('is-on', cb.checked);
                amt.disabled = ! cb.checked;
                if (! cb.checked) amt.value = '';
                else amt.focus();
                refresh();
            });
        });

        function bindOther(row) {
            var desc = row.querySelector('input[type=text]:not([data-ac-amount])');
            var amt = row.querySelector('[data-ac-amount]');
            desc.addEventListener('input', refresh);
            bindAmount(amt);
            var rm = row.querySelector('[data-ac-remove-item]');
            if (rm) rm.addEventListener('click', function () { row.remove(); refresh(); });
        }

        addBtn.addEventListener('click', function () {
            var holder = document.createElement('div');
            holder.innerHTML = otherRowHtml(idx++);
            var row = holder.firstChild;
            wrap.appendChild(row);
            bindOther(row);
            refresh();
            row.querySelector('input[type=text]').focus();
        });

        dateInp.addEventListener('input', refresh);
        refresh();

        // Sebelum submit: nominal (yang aktif) kembali jadi angka polos.
        form.addEventListener('submit', function () {
            form.querySelectorAll('[data-ac-amount]').forEach(function (amt) {
                if (! amt.disabled) amt.value = String(rawVal(amt));
            });
        });

        var cancel = form.querySelector('[data-ac-cancel]');
        cancel.addEventListener('click', function () {
            var panel = form.closest('.ac-form');
            panel.hidden = true;
            toggles.forEach(function (btn) {
                if (btn.getAttribute('data-ac-form-toggle') === panel.id) setToggleState(btn, false);
            });
        });
    });

    // ── Modal riwayat per unit ──
    var historyUrl = @json(route('mychecklist.ac.unit.history', ['id' => '__ID__']));
    var serviceDestroyUrl = @json(route('mychecklist.ac.service.destroy', ['id' => '__ID__']));
    var csrf = @json(csrf_token());

    function openModal(id) {
        var m = document.getElementById(id);
        if (m) { m.hidden = false; document.body.style.overflow = 'hidden'; }
    }
    function closeModals() {
        document.querySelectorAll('.ac-modal').forEach(function (m) { m.hidden = true; });
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-ac-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModals);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModals(); });

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };

    function visitHtml(rec) {
        var linesHtml = '';
        (rec.items || []).forEach(function (it) {
            linesHtml += '<div class="ac-visit__line"><span>' + esc(it.description) + '</span><b>' + esc(it.amount_fmt) + '</b></div>';
        });
        var delHtml = rec.can_delete
            ? '<button type="button" class="ac-visit__del" data-del-id="' + esc(rec.id) + '" title="Hapus catatan (koreksi salah input)"><i class="fas fa-trash-alt"></i></button>'
            : '';
        var pill = '<span style="display:inline-block;margin-left:6px;padding:2px 9px;border-radius:999px;font-size:10.5px;font-weight:800;background:' + esc(rec.status_bg) + ';color:' + esc(rec.status_color) + ';">' + esc(rec.status_label) + '</span>';
        var noteHtml = (rec.status === 'rejected' && rec.review_note)
            ? '<div style="margin-top:7px;font-size:11.5px;color:#A32D2D;background:#FCEBEB;border-radius:8px;padding:6px 10px;"><i class="fas fa-comment-dots"></i> Alasan ditolak: ' + esc(rec.review_note) + '</div>'
            : '';
        return '<div class="ac-visit" data-visit="' + esc(rec.id) + '">'
            + '<div class="ac-visit__top">'
            +   '<span class="ac-visit__icon"><i class="fas fa-broom"></i></span>'
            +   '<div class="ac-visit__sum"><b>' + esc(rec.date || '—') + '</b> · Total <b>' + esc(rec.total_fmt) + '</b>'
            +     (rec.creator ? ' · ' + esc(rec.creator) : '') + ' ' + pill + '</div>'
            +   delHtml
            + '</div>'
            + '<div class="ac-visit__lines">' + linesHtml + '</div>'
            + noteHtml
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
                var f = document.createElement('form');
                f.method = 'POST';
                f.action = serviceDestroyUrl.replace('__ID__', btn.getAttribute('data-del-yes'));
                f.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '">'
                    + '<input type="hidden" name="_method" value="DELETE">';
                document.body.appendChild(f);
                f.submit();
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
            + '<div class="ac-hist-list" data-hist-list></div>'
            + '<div class="ac-modal__hint">Catatan hanya bisa dihapus oleh pembuatnya, dalam 24 jam setelah dicatat (koreksi salah input).</div>';

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
})();
</script>
@endsection
