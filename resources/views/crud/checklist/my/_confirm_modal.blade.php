{{--
    Modal konfirmasi ber-brand (pengganti confirm() browser).
    Pakai: beri atribut data-confirm="Pesan konfirmasi" pada <form>.
    Failure-safe: submit event hanya ter-intercept setelah lolos validasi HTML5;
    kalau JS gagal, form submit normal (tidak memblokir aksi).
--}}
<div class="cl-confirm2" id="clConfirm2" hidden>
    <div class="cl-confirm2__backdrop" data-cc-close></div>
    <div class="cl-confirm2__box" role="dialog" aria-modal="true" aria-labelledby="clConfirm2Title">
        <div class="cl-confirm2__head" id="clConfirm2Title"><i class="fas fa-question-circle"></i> Konfirmasi</div>
        <div class="cl-confirm2__body"><p id="clConfirm2Msg">Lanjutkan?</p></div>
        <div class="cl-confirm2__foot">
            <button type="button" class="st-btn-outline-brown" data-cc-close style="padding:8px 16px; font-size:12.5px;">Batal</button>
            <button type="button" class="cl-confirm2__ok" id="clConfirm2Ok"><i class="fas fa-check"></i> Ya, lanjut</button>
        </div>
    </div>
</div>
<style>
    .cl-confirm2 { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; }
    .cl-confirm2[hidden] { display: none; }
    .cl-confirm2__backdrop { position: absolute; inset: 0; background: rgba(30,15,5,.55); }
    .cl-confirm2__box { position: relative; z-index: 1; width: min(92vw, 420px); background: #FAF4EC; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(45,24,16,.28); }
    .cl-confirm2__head { background: linear-gradient(135deg, #5C2E15, #471500); color: #FFE9C7; padding: 15px 20px; font-size: 15px; font-weight: 800; display: flex; align-items: center; gap: 9px; }
    .cl-confirm2__body { padding: 18px 20px; }
    .cl-confirm2__body p { margin: 0; font-size: 13.5px; color: #4A2810; line-height: 1.55; }
    .cl-confirm2__foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid #E6D9C8; }
    .cl-confirm2__ok { border: 0; background: linear-gradient(180deg,#5C2E15,#471500); color: #fff; padding: 9px 18px; border-radius: 999px; font-weight: 700; font-size: 12.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: filter .15s; }
    .cl-confirm2__ok:hover { filter: brightness(1.08); }
</style>
<script>
(function () {
    var modal = document.getElementById('clConfirm2');
    var msgEl = document.getElementById('clConfirm2Msg');
    var okBtn = document.getElementById('clConfirm2Ok');
    var pendingForm = null;
    function open(form, msg) {
        pendingForm = form;
        if (msgEl) msgEl.textContent = msg || 'Lanjutkan?';
        if (modal) modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function close() {
        if (modal) modal.hidden = true;
        pendingForm = null;
        document.body.style.overflow = '';
    }
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.ccOk === '1') { form.dataset.ccOk = ''; return; } // sudah dikonfirmasi
            e.preventDefault();
            open(form, form.getAttribute('data-confirm'));
        });
    });
    if (okBtn) okBtn.addEventListener('click', function () {
        if (pendingForm) {
            pendingForm.dataset.ccOk = '1';
            if (pendingForm.requestSubmit) pendingForm.requestSubmit(); else pendingForm.submit();
        }
        close();
    });
    document.querySelectorAll('[data-cc-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
