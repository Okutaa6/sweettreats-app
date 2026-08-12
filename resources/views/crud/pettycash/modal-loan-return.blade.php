{{--
    Petty Cash — modal "Catat Pengembalian Pinjaman".
    Variables: $period, $kind, $sourceBalances, $rupiah.

    Mencatat uang pinjaman yang dikembalikan sebagai KAS MASUK berkategori
    'pinjaman' (is_topup=0). Efek: piutang wallet berkurang, saldo fisik naik,
    tidak menambah "Total top up". Hanya wallet yang punya piutang > 0 yang
    tampil di dropdown.
--}}
@php
    $kind = $kind ?? 'petty_cash';
    // Wallet yang masih punya piutang pinjaman belum kembali.
    $piutangWallets = collect($sourceBalances ?? [])
        ->filter(fn($sb) => round((float) ($sb->piutang ?? 0), 2) > 0)
        ->values();
@endphp
<div class="pc-modal" id="pcModalLoanReturn" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.loanReturn', ['tab' => $kind]) }}">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="{{ $kind }}">
            @if(!session('raw_branch_id') && $period->raw_branch_id)
                <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
            @endif

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-hand-holding-usd" style="color:#047857;"></i> Catat Pengembalian
                    </h5>
                    <small class="pc-muted">Uang pinjaman yang sudah dikembalikan</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                @if($piutangWallets->isEmpty())
                    <div class="pc-suggest-box">
                        <i class="fa fa-info-circle"></i>
                        Belum ada piutang pinjaman yang perlu dikembalikan.
                    </div>
                @else
                    <div class="pc-form-row">
                        <label>Wallet <span class="text-danger">*</span></label>
                        <select name="fund_source_id" required class="pc-input" data-pc-loan-source>
                            @foreach($piutangWallets as $sb)
                                <option value="{{ $sb->id }}"
                                        data-piutang="{{ round((float) $sb->piutang, 2) }}">
                                    {{ $sb->name }} — piutang {{ $rupiah($sb->piutang) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="pc-muted" data-pc-loan-max></small>
                    </div>

                    <div class="pc-form-row">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    </div>

                    <div class="pc-form-row">
                        <label>Jumlah dikembalikan (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric"
                               class="pc-input pc-amount-input"
                               data-target="loanReturnAmount"
                               placeholder="0">
                        <input type="hidden" name="amount" id="loanReturnAmount" required>
                    </div>

                    <div class="pc-form-row">
                        <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="255"
                               placeholder="Mis: Pelunasan pinjaman kasir"
                               class="pc-input">
                    </div>
                @endif
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                @if($piutangWallets->isNotEmpty())
                    <button type="submit" class="pc-btn-topup-solid" style="background:#047857;">
                        <i class="fa fa-hand-holding-usd"></i> Simpan Pengembalian
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
