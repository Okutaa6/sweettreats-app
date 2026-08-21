{{--
    Petty Cash — modal "Catat Pengembalian Pinjaman".
    Variables: $period, $kind, $openLoans, $rupiah.

    Mencatat uang pinjaman yang dikembalikan sebagai KAS MASUK berkategori
    'pinjaman' (is_topup=0) yang MENUNJUK ke baris pinjaman aslinya lewat
    settles_transaction_id. Efek: status pinjaman itu jadi LUNAS/SEBAGIAN,
    saldo fisik wallet naik, tidak menambah "Total top up". Hanya pinjaman
    yang masih punya sisa > 0 yang tampil di dropdown.
--}}
@php
    $kind = $kind ?? 'petty_cash';
    // Daftar pinjaman yang masih terbuka (sisa > 0), dari buildRows().
    $loans = collect($openLoans ?? [])->values();
@endphp
<div class="pc-modal" id="pcModalLoanReturn" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.loanReturn', ['tab' => $kind]) }}">
            @csrf
            @include('crud.pettycash._filter_hidden')
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
                @if($loans->isEmpty())
                    <div class="pc-suggest-box">
                        <i class="fa fa-info-circle"></i>
                        Belum ada pinjaman yang perlu dilunasi.
                    </div>
                @else
                    <div class="pc-form-row">
                        <label>Pinjaman yang dilunasi <span class="text-danger">*</span></label>
                        <select name="settles_transaction_id" required class="pc-input" data-pc-loan-select>
                            @foreach($loans as $ln)
                                <option value="{{ $ln->id }}"
                                        data-sisa="{{ round((float) $ln->sisa, 2) }}"
                                        data-source="{{ $ln->source_id }}"
                                        data-borrower="{{ $ln->borrower }}">
                                    {{ $ln->borrower ?: \Illuminate\Support\Str::limit($ln->description, 30) }}
                                    — sisa {{ $rupiah($ln->sisa) }}
                                    @if($ln->paid > 0) (dari {{ $rupiah($ln->amount) }}) @endif
                                    · {{ $ln->source_name }}
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
                        <small class="pc-muted">Kosongkan? Isi sesuai jumlah yang dikembalikan. Maks = sisa pinjaman.</small>
                    </div>

                    <div class="pc-form-row">
                        <label>Catatan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="255"
                               placeholder="Mis: dibayar tunai ke kasir"
                               class="pc-input">
                    </div>
                @endif
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                @if($loans->isNotEmpty())
                    <button type="submit" class="pc-btn-topup-solid" style="background:#047857;">
                        <i class="fa fa-hand-holding-usd"></i> Simpan Pengembalian
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
