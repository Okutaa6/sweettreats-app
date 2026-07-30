{{-- JS form "Catat Servis": toggle, validasi, rincian, & modal konfirmasi.
     Self-contained (IIFE sendiri) — dipakai halaman GS & supervisor. Butuh
     partial _service_confirm_modal di halaman yang sama. --}}
<script>
(function () {
    // ── Toggle form (satu form terbuka pada satu waktu) ──
    var toggles = document.querySelectorAll('[data-ac-form-toggle]');
    function setToggleState(btn, open) {
        btn.classList.toggle('is-open', open);
        var span = btn.querySelector('span');
        if (span) span.textContent = btn.getAttribute(open ? 'data-label-close' : 'data-label-open');
    }
    function closeAllForms(except) {
        toggles.forEach(function (btn) {
            var el = document.getElementById(btn.getAttribute('data-ac-form-toggle'));
            if (el && el !== except) { el.hidden = true; setToggleState(btn, false); }
        });
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

    // ── Helper nominal ──
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

    // ── Modal konfirmasi sebelum simpan ──
    var acConfirmForm  = null;
    var acConfirmModal = document.getElementById('acConfirmModal');
    function openAcConfirm(form) {
        acConfirmForm = form;
        var total     = computeTotal(form);
        var icon      = document.getElementById('acConfirmIcon');
        var title     = document.getElementById('acConfirmTitle');
        var msg       = document.getElementById('acConfirmMsg');
        var totalBox  = document.getElementById('acConfirmTotal');
        var totalVal  = document.getElementById('acConfirmTotalVal');
        var okBtn     = document.getElementById('acConfirmOk');
        var cancelBtn = document.getElementById('acConfirmCancel');
        if (total <= 0) {
            icon.className = 'fas fa-exclamation-triangle'; icon.style.color = '#A66019';
            title.textContent = 'Nominal belum diisi';
            msg.innerHTML = 'Isi nominal biaya servisnya dulu sebelum menyimpan.';
            totalBox.style.display = 'none';
            okBtn.style.display = 'none';
            cancelBtn.textContent = 'Oke, isi dulu';
        } else {
            icon.className = 'fas fa-question-circle'; icon.style.color = '#8A3A12';
            title.textContent = 'Cek nominal dulu';
            msg.innerHTML = 'Pastikan rincian & nominalnya sudah benar sebelum disimpan.';
            totalVal.textContent = 'Rp ' + fmtDots(String(total));
            totalBox.style.display = 'flex';
            okBtn.style.display = 'inline-flex';
            okBtn.innerHTML = '<i class="fas fa-check"></i> Ya, Simpan';
            cancelBtn.textContent = 'Batal';
        }
        if (acConfirmModal) acConfirmModal.hidden = false;
    }
    function closeAcConfirm() { if (acConfirmModal) acConfirmModal.hidden = true; acConfirmForm = null; }

    document.querySelectorAll('form[data-ac-service-form]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            e.preventDefault(); e.stopImmediatePropagation();
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
            var f = acConfirmForm; if (! f) return;
            f.querySelectorAll('[data-ac-amount]').forEach(function (amt) {
                if (! amt.disabled) amt.value = String(rawVal(amt));
            });
            var b = f.querySelector('.ac-btn-save');
            if (b) { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan…'; }
            if (acConfirmModal) acConfirmModal.hidden = true;
            f.submit();
        });
    }

    // ── Validasi & rincian per form ──
    document.querySelectorAll('[data-ac-service-form]').forEach(function (form) {
        var wrap    = form.querySelector('[data-ac-items]');
        var addBtn  = form.querySelector('[data-ac-add-item]');
        var totalEl = form.querySelector('[data-ac-total]');
        var saveBtn = form.querySelector('.ac-btn-save');
        var dateInp = form.querySelector('[name=service_date]');
        var types   = Array.prototype.slice.call(form.querySelectorAll('[data-ac-type]'));
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
        types.forEach(function (t) {
            var cb = t.querySelector('[data-ac-type-toggle]');
            var amt = t.querySelector('[data-ac-amount]');
            bindAmount(amt);
            cb.addEventListener('change', function () {
                t.classList.toggle('is-on', cb.checked);
                amt.disabled = ! cb.checked;
                if (! cb.checked) amt.value = ''; else amt.focus();
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
        var cancel = form.querySelector('[data-ac-cancel]');
        if (cancel) cancel.addEventListener('click', function () {
            var panel = form.closest('.ac-form');
            if (! panel) return;
            panel.hidden = true;
            toggles.forEach(function (btn) {
                if (btn.getAttribute('data-ac-form-toggle') === panel.id) setToggleState(btn, false);
            });
        });
    });
})();
</script>
