{{--
    Finance: modal Approve top-up request.
    Variables: $fundSources (collection<FundSource>) — wallet override list
    Prefill via JS dari tombol Approve di pending queue.
--}}
<div class="pc-modal" id="fpcModalApprove" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" id="fpcApproveForm" action="" enctype="multipart/form-data">
            @csrf

            <div class="pc-modal__header pc-modal__header--wallet">
                <span class="pc-modal__icon-box" aria-hidden="true" style="background:#16a34a;">
                    <i class="fa fa-check"></i>
                </span>
                <div class="pc-modal__heading">
                    <h5 class="pc-modal__title">Approve Pengajuan Top Up</h5>
                    <small class="pc-muted">Transaksi akan dicatat di kas cabang setelah Approve</small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-tab-hint pc-tab-hint--withdraw">
                    <span id="fpcApproveSummary">—</span>
                </div>

                <div class="pc-form-row">
                    <label>Nominal yang disetujui</label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="fpcApproveAmount"
                           id="fpcApproveAmountVisible"
                           placeholder="Rp 0">
                    <input type="hidden" name="amount_approved" id="fpcApproveAmount" required>
                    <small class="pc-muted">Boleh beda dari nominal yang diajukan.</small>
                </div>

                <div class="pc-form-row">
                    <label>Wallet tujuan</label>
                    <select name="fund_source_id" id="fpcApproveFundSource" required class="pc-input">
                        @foreach($fundSources as $fs)
                            <option value="{{ $fs->id }}">{{ $fs->name }}</option>
                        @endforeach
                    </select>
                    <small class="pc-muted">Boleh ganti kalau lebih praktis transfer ke wallet lain.</small>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal transaksi</label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    <small class="pc-muted">Periode petty cash mengikuti bulan tanggal ini.</small>
                </div>

                <div class="pc-form-row">
                    <label>Catatan internal <small class="pc-muted">— opsional</small></label>
                    <input type="text" name="approval_note" id="fpcApproveNote" maxlength="255"
                           placeholder="Contoh: transfer via BCA"
                           class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Bukti foto transfer <small class="pc-muted">— opsional</small></label>
                    <div class="pc-attach-zone" id="fpcApproveAttachZone">
                        <input type="file" name="attachment"
                               id="fpcApproveAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="fpcApproveAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Tap untuk pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">Screenshot transfer / struk · Maks 5 MB</span>
                        </label>
                        <div class="pc-attach-preview" hidden>
                            <div class="pc-attach-preview__thumb"><i class="fa fa-file"></i></div>
                            <div class="pc-attach-preview__meta">
                                <div class="pc-attach-preview__name">—</div>
                                <div class="pc-attach-preview__size">—</div>
                            </div>
                            <button type="button" class="pc-attach-preview__remove" title="Hapus file">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="fpc-btn fpc-btn--approve" style="padding:9px 18px;">
                    <i class="fa fa-check"></i> Approve & Catat Top Up
                </button>
            </div>
        </form>
    </div>
</div>
