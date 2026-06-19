{{--
    Petty Cash / Transport — modal "Edit Pengeluaran".

    Variables:
      $period, $kind

    Window edit di-enforce di controller (24 jam sejak created_at, dan
    hanya untuk pengeluaran biasa). Modal ini di-trigger dari tombol pensil
    di _table_rows.blade.php — JS akan prefill form via data-* attributes.

    Field editable: amount, description, category.
    Field di-lock (read-only display saja, dikirim hidden): trx_date, wallet.
--}}
@php
    $editCategories = $kind === 'transport'
        ? \App\Models\TransportCostTransaction::CATEGORIES
        : \App\Models\PettyCashTransaction::CATEGORIES;
@endphp
<div class="pc-modal" id="pcModalEditExpense" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" id="pcEditExpenseForm" data-action-tpl="{{ url('pettycash/transaction') }}/__ID__?tab={{ $kind }}">
            @csrf
            @method('PATCH')
            @if(!session('raw_branch_id') && $period->raw_branch_id)
                <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
            @endif

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-pen" style="color:#7c2d12;"></i> Edit Pengeluaran
                    </h5>
                    <small class="pc-muted">
                        Boleh edit nominal, kategori, dan keterangan (dalam 24 jam sejak input).
                    </small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                {{-- Info read-only: tanggal & wallet, supaya user paham field
                     ini di-lock & tahu konteks transaksi mana yang sedang diedit. --}}
                <div class="pc-edit-context">
                    <div class="pc-edit-context__item">
                        <span class="pc-edit-context__label">Tanggal</span>
                        <span class="pc-edit-context__value" id="pcEditTrxDate">—</span>
                    </div>
                    <div class="pc-edit-context__item">
                        <span class="pc-edit-context__label">Wallet</span>
                        <span class="pc-edit-context__value" id="pcEditWallet">—</span>
                    </div>
                </div>
                <small class="pc-muted pc-edit-locked-hint">
                    <i class="fa fa-lock"></i>
                    Tanggal &amp; wallet di-lock. Untuk ubah, hapus transaksi ini lalu buat ulang.
                </small>

                <div class="pc-form-row">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <select name="category" id="pcEditCategory" required class="pc-input">
                        <option value="">— Pilih kategori —</option>
                        @foreach($editCategories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           id="pcEditAmountVisible"
                           data-target="pcEditAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="pcEditAmount" required>
                    <small class="pc-muted">
                        Maks: Rp <span id="pcEditAmountMaxHint">0</span>
                        <span class="pc-muted">(saldo wallet + nominal asli)</span>
                    </small>
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <span class="text-danger">*</span></label>
                    <input type="text" name="description" id="pcEditDescription"
                           required maxlength="255"
                           placeholder="Isi keterangan pengeluaran"
                           class="pc-input">
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<style>
    .pc-edit-context{display:flex;gap:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;margin-bottom:6px;}
    .pc-edit-context__item{display:flex;flex-direction:column;gap:2px;}
    .pc-edit-context__label{font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#64748b;font-weight:600;}
    .pc-edit-context__value{font-size:14px;color:#0f172a;font-weight:600;}
    .pc-edit-locked-hint{display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8;margin:0 0 12px;}
    .pc-edit-locked-hint i{font-size:10px;}
    .pc-pill-edited{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:50px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;border:1px solid #fde68a;}
</style>
