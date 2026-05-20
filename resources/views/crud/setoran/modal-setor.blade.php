{{--
    Setoran — modal setor tunai (cash out, is_setor=1).
    Variables: $period, $summary.

    Saran nominal otomatis = saldo sekarang (kembalikan saldo ke 0).
--}}
@php
    $suggested = (int) round($summary['closing']);
@endphp
<div class="sd-modal" id="sdModalSetor" role="dialog" aria-modal="true">
    <div class="sd-modal__dialog">
        <form method="POST" action="{{ route('setoran.setor') }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">

            <div class="sd-modal__header">
                <div>
                    <h5 class="sd-modal__title">
                        <i class="fa fa-money-bill-transfer" style="color:#b45309;"></i> Setor Tunai
                    </h5>
                    <small class="sd-muted">Setorkan saldo terkumpul ke kantor pusat</small>
                </div>
                <button type="button" class="sd-modal__close" data-sd-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="sd-modal__body">
                <div class="sd-suggest-box">
                    <i class="fa fa-info-circle"></i>
                    Saldo saat ini: <strong>Rp {{ number_format($summary['closing'], 0, ',', '.') }}</strong>
                </div>

                <div class="sd-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="sd-input">
                </div>

                <div class="sd-form-row">
                    <label>Keterangan <small class="sd-muted">(opsional)</small></label>
                    <input type="text" name="description" maxlength="255"
                           placeholder="Default: SETOR TUNAI"
                           class="sd-input">
                </div>

                <div class="sd-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="sd-input sd-amount-input"
                           data-target="setorAmount"
                           value="{{ $suggested > 0 ? number_format($suggested, 0, ',', '.') : '' }}"
                           placeholder="0">
                    <input type="hidden" name="amount" id="setorAmount" value="{{ $suggested > 0 ? $suggested : '' }}" required>
                </div>
            </div>

            <div class="sd-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-sd-modal-close>Batal</button>
                <button type="submit" class="sd-btn-setor-solid">
                    <i class="fa fa-money-bill-transfer"></i> Setor Tunai
                </button>
            </div>
        </form>
    </div>
</div>
