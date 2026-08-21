{{--
    Petty Cash — modal "Pindah Cabang" (Super Admin).

    Pindahkan / TUKAR seluruh periode (transaksi + rincian saldo) ke cabang
    lain untuk membereskan salah cabang akibat rolling — mis. Della (home
    Cinere) yang rolling ke Sunter tapi pengeluaran + saldonya masih tercatat
    di Cinere. Semua transaksi menempel ke periode via period_id, jadi ganti
    raw_branch_id periode → seluruh ledger ikut pindah.

    Variables: $period, $kind, $branches, $branchId, $activeBranch,
               $monthName, $year.
--}}
@php
    $kind         = $kind ?? 'petty_cash';
    $branches     = $branches ?? collect();
    $branchId     = $branchId ?? null;
    $activeBranch = $activeBranch ?? null;
    $sourceName   = $activeBranch->name ?? 'cabang ini';
@endphp
<div class="pc-modal" id="pcModalMovePeriod" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.period.move', $period->id) }}">
            @csrf
            @include('crud.pettycash._filter_hidden')
            <input type="hidden" name="tab" value="{{ $kind }}">

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-exchange-alt" style="color:#b45309;"></i> Pindah Cabang
                    </h5>
                    <small class="pc-muted">Pindahkan / tukar periode ke cabang lain</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-move-info">
                    <i class="fa fa-info-circle"></i>
                    <div>
                        Seluruh transaksi &amp; rincian saldo periode
                        <strong>{{ $monthName }} {{ $year }} — {{ $sourceName }}</strong>
                        akan dipindahkan ke cabang yang dipilih.
                        Kalau cabang tujuan sudah punya periode bulan ini, keduanya akan
                        <strong>ditukar</strong>.
                    </div>
                </div>

                <div class="pc-form-row">
                    <label>Pindahkan ke cabang <span class="text-danger">*</span></label>
                    <select name="target_branch_id" required class="pc-input">
                        <option value="">— Pilih cabang tujuan —</option>
                        @foreach($branches as $b)
                            @if((string) $b->id !== (string) $branchId)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>

                <div class="pc-move-warn">
                    <i class="fa fa-exclamation-triangle"></i>
                    Aksi ini mengubah kepemilikan data keuangan cabang. Pastikan cabang tujuan benar.
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand"
                        onclick="return confirm('Pindahkan periode {{ $monthName }} {{ $year }} — {{ $sourceName }} ke cabang yang dipilih?\n\nKalau cabang tujuan sudah punya periode bulan ini, keduanya akan DITUKAR.');">
                    <i class="fa fa-exchange-alt"></i> Pindahkan
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    #pcModalMovePeriod .pc-move-info {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #eff6ff;
        border-left: 4px solid #3b82f6;
        color: #1e3a5f;
        padding: 11px 13px;
        border-radius: 8px;
        font-size: 13px;
        line-height: 1.55;
        margin-bottom: 16px;
    }
    #pcModalMovePeriod .pc-move-info i { color: #2563eb; margin-top: 2px; flex-shrink: 0; }
    #pcModalMovePeriod .pc-move-warn {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        background: #fff7ed;
        border-left: 4px solid #f97316;
        color: #7c2d12;
        padding: 9px 12px;
        border-radius: 8px;
        font-size: 12.5px;
        line-height: 1.5;
        margin-top: 4px;
    }
    #pcModalMovePeriod .pc-move-warn i { color: #ea580c; margin-top: 2px; flex-shrink: 0; }
</style>
