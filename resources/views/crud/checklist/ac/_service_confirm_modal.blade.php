{{-- Modal konfirmasi simpan catatan servis (dipakai halaman GS & supervisor). --}}
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
