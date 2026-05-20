{{--
    Petty Cash / Transport — modal tambah pengeluaran (cash out).
    Variables: $period, $kind.
--}}
@php $kind = $kind ?? 'petty_cash'; @endphp
<div class="pc-modal" id="pcModalExpense" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.expense', ['tab' => $kind]) }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="{{ $kind }}">

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-plus" style="color:#13928e;"></i> Tambah Pengeluaran
                    </h5>
                    <small class="pc-muted">Cash out dari {{ $kind === 'transport' ? 'kas transportasi' : 'kas kecil' }}</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <span class="text-danger">*</span></label>
                    <input type="text" name="description" required maxlength="255"
                           placeholder="Isi keterangan"
                           class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="expenseAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="expenseAmount" required>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Simpan Pengeluaran
                </button>
            </div>
        </form>
    </div>
</div>
