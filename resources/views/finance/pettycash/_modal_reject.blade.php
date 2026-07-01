{{--
    Finance: modal Reject top-up request.
    Prefill via JS dari tombol Reject di pending queue.
--}}
<div class="pc-modal" id="fpcModalReject" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" id="fpcRejectForm" action="">
            @csrf

            <div class="pc-modal__header pc-modal__header--wallet">
                <span class="pc-modal__icon-box" aria-hidden="true" style="background:#dc2626;">
                    <i class="fa fa-times"></i>
                </span>
                <div class="pc-modal__heading">
                    <h5 class="pc-modal__title">Tolak Pengajuan</h5>
                    <small class="pc-muted">Sales akan menerima notifikasi alasan penolakan</small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-tab-hint pc-tab-hint--expense">
                    <span id="fpcRejectSummary">—</span>
                </div>

                <div class="pc-form-row">
                    <label>Alasan penolakan</label>
                    <textarea name="rejected_reason" id="fpcRejectReason"
                              rows="3" maxlength="500" required
                              placeholder="Contoh: saldo masih cukup, ajukan ulang minggu depan"
                              class="pc-input"></textarea>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="fpc-btn fpc-btn--reject" style="padding:9px 18px;">
                    <i class="fa fa-times"></i> Tolak Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>
