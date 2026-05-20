{{--
    Setoran — modal tambah pembayaran pasien (cash in).
    Variables: $period.

    Dropdown pasien: Select2 + AJAX ke /api/customer/search
    (otomatis filter ke cabang user sesuai session('raw_branch_id') di backend).
--}}
<div class="sd-modal" id="sdModalPayment" role="dialog" aria-modal="true">
    <div class="sd-modal__dialog">
        <form method="POST" action="{{ route('setoran.payment') }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">

            <div class="sd-modal__header">
                <div>
                    <h5 class="sd-modal__title">
                        <i class="fa fa-plus" style="color:#13928e;"></i> Pembayaran Pasien
                    </h5>
                    <small class="sd-muted">Cash in dari pasien — saldo bertambah</small>
                </div>
                <button type="button" class="sd-modal__close" data-sd-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="sd-modal__body">
                <div class="sd-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="sd-input">
                </div>

                <div class="sd-form-row">
                    <label>Pasien <span class="text-danger">*</span></label>
                    <select name="raw_customer_id" id="sdCustomerSelect" required class="sd-input">
                        <option value="">- Cari Pasien -</option>
                    </select>
                    <small class="sd-muted">Ketik minimal 2 huruf untuk mencari nama / nomor HP pasien.</small>
                </div>

                <div class="sd-form-row">
                    <label>Keterangan <small class="sd-muted">(opsional)</small></label>
                    <input type="text" name="description" maxlength="255"
                           placeholder="mis. DP behel, lunas cabut gigi, dll"
                           class="sd-input">
                </div>

                <div class="sd-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="sd-input sd-amount-input"
                           data-target="paymentAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="paymentAmount" required>
                </div>
            </div>

            <div class="sd-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-sd-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Select2 init di-handle oleh index.blade.php section 'js' (setelah lib Select2 loaded di footer layout). --}}
