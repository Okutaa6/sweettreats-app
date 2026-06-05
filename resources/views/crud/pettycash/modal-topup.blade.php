{{--
    Petty Cash / Transport — modal top up saldo (cash in, is_topup=1).
    Variables: $period, $summary, $kind.

    Saran nominal otomatis = float_amount - saldo sekarang (kembalikan ke imprest).
--}}
@php
    $suggested          = (int) round($summary['suggested_topup']);
    $kind               = $kind ?? 'petty_cash';
    $fundSourcesGrouped = $fundSourcesGrouped ?? collect();
    // Top up imprest hanya boleh ke sumber Cash — uang baru selalu masuk
    // sebagai cash dulu. Pindah ke Bank/E-Wallet dilakukan via Transfer.
    $cashSources = $fundSourcesGrouped->get(\App\Models\FundSource::CAT_CASH, collect());
@endphp
<div class="pc-modal" id="pcModalTopup" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.topup', ['tab' => $kind]) }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="{{ $kind }}">

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-arrow-down-up-across-line" style="color:#16a34a;"></i> Top up Saldo
                    </h5>
                    <small class="pc-muted">Isi kembali {{ $kind === 'transport' ? 'kas transportasi' : 'kas kecil' }} ke dana tetap (imprest)</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-suggest-box">
                    <i class="fa fa-info-circle"></i>
                    Saldo saat ini: <strong>Rp {{ number_format($summary['closing'], 0, ',', '.') }}</strong>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                    <input type="text" name="description" maxlength="255"
                           placeholder="Default: Top up kasir"
                           class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="topupAmount"
                           value="{{ number_format($suggested, 0, ',', '.') }}"
                           placeholder="0">
                    <input type="hidden" name="amount" id="topupAmount" value="{{ $suggested }}" required>
                </div>

                <div class="pc-form-row">
                    <label>Simpan ke <span class="text-danger">*</span></label>
                    <select name="fund_source_id" required class="pc-input pc-source-select">
                        <option value="">— Pilih sumber dana —</option>
                        @foreach($cashSources as $fs)
                            <option value="{{ $fs->id }}"
                                    data-color="{{ $fs->effectiveBrandColor() }}"
                                    data-short="{{ $fs->effectiveShortLabel() }}"
                                    data-logo="{{ $fs->logo_url }}">{{ $fs->name }}</option>
                        @endforeach
                    </select>
                    <small class="pc-muted">
                        Top up hanya ke sumber <strong>Cash</strong>. Untuk isi saldo Bank / E-Wallet, transfer dari Cash di rincian saldo.
                    </small>
                    <div class="pc-source-preview" data-pc-source-preview></div>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="pc-btn-topup-solid">
                    <i class="fa fa-arrow-down-up-across-line"></i> Top up Saldo
                </button>
            </div>
        </form>
    </div>
</div>
