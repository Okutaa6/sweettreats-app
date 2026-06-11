{{--
    Petty Cash / Transport — modal "Setoran Awal / Migrasi".

    User pilih sumber dana satu per satu via tombol "+ Tambah sumber dana".
    Setiap baris yang ditambahkan punya: dropdown source + input nominal +
    tombol hapus. Sumber yang sudah dipilih di salah satu baris otomatis
    di-disable di dropdown baris lain (no duplicate).

    Submit ke pettycash.opening dengan body balances[fund_source_id] = nominal.
    Tidak membuat transaksi — langsung update source_opening_balances JSON.

    Variables: $period, $kind, $fundSourcesGrouped (semua source aktif).
--}}
@php
    $kind               = $kind ?? 'petty_cash';
    $fundSourcesGrouped = $fundSourcesGrouped ?? collect();
    $branchAccountMap   = $branchAccountMap ?? [];
    $categoryLabels     = [
        \App\Models\FundSource::CAT_CASH    => 'Cash / Tunai',
        \App\Models\FundSource::CAT_BANK    => 'Bank',
        \App\Models\FundSource::CAT_EWALLET => 'E-Wallet',
    ];
@endphp
<div class="pc-modal" id="pcModalOpening" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog pc-modal__dialog--wide">
        <form method="POST" action="{{ route('pettycash.opening', ['tab' => $kind]) }}" id="pcOpeningForm">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="{{ $kind }}">
            @if(!session('raw_branch_id') && $period->raw_branch_id)
                <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
            @endif

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-piggy-bank" style="color:#7c2d12;"></i> Setoran Awal / Migrasi
                    </h5>
                    <small class="pc-muted">Rinci budget per sumber dana yang sudah berjalan — tidak masuk ke tabel transaksi.</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-tab-hint">
                    <i class="fa fa-info-circle" style="color:#2563eb;"></i>
                    <span><strong>Setoran Awal:</strong> tambahkan sumber dana yang sudah punya saldo, lalu input nominalnya. Klik <strong>+ Tambah Sumber Dana</strong> untuk mulai. Tidak akan muncul sebagai baris transaksi.</span>
                </div>

                {{-- Container untuk baris yang ditambahkan user --}}
                <div id="pcOpeningRows" class="pc-opening-rows">
                    {{-- Empty hint saat belum ada row --}}
                    <div class="pc-opening-empty" id="pcOpeningEmpty">
                        <i class="fa fa-hand-pointer"></i>
                        Belum ada sumber dana ditambahkan. Klik tombol di bawah untuk mulai.
                    </div>
                </div>

                <button type="button" class="pc-btn-add-source" id="pcOpeningAddBtn">
                    <i class="fa fa-plus"></i> Tambah Sumber Dana
                </button>

                <div class="pc-opening-total">
                    <span>Total saldo awal:</span>
                    <strong id="pcOpeningTotalDisplay">Rp 0</strong>
                </div>

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="pc-btn-topup-solid" id="pcOpeningSubmitBtn" disabled>
                        <i class="fa fa-save"></i> Simpan Setoran Awal
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Template baris (di-clone via JS).
     Account number per source di-encode di data-account-* mapping pada wrapper,
     biar JS bisa pre-fill saat user pilih source. --}}
<template id="pcOpeningRowTpl">
    <div class="pc-opening-row-wrap" data-pc-opening-row>
        <div class="pc-opening-row">
            <div class="pc-opening-row__src">
                <select class="pc-input pc-opening-source-select" data-pc-opening-source>
                    <option value="">— Pilih sumber dana —</option>
                    @foreach($categoryLabels as $catKey => $catLabel)
                        @if(isset($fundSourcesGrouped[$catKey]) && count($fundSourcesGrouped[$catKey]) > 0)
                            <optgroup label="{{ $catLabel }}">
                                @foreach($fundSourcesGrouped[$catKey] as $fs)
                                    <option value="{{ $fs->id }}"
                                            data-color="{{ $fs->effectiveBrandColor() }}"
                                            data-short="{{ $fs->effectiveShortLabel() }}"
                                            data-logo="{{ $fs->logo_url }}"
                                            data-name="{{ $fs->name }}"
                                            data-category="{{ $fs->category }}"
                                            data-account="{{ $branchAccountMap[$fs->id] ?? '' }}">
                                        {{ $fs->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="pc-opening-row__input">
                <span class="pc-opening-row__currency">Rp</span>
                <input type="text" inputmode="numeric"
                       class="pc-input pc-opening-amount"
                       placeholder="0"
                       autocomplete="off"
                       data-pc-opening-amount>
            </div>
            <button type="button" class="pc-opening-row__remove" data-pc-opening-remove title="Hapus">
                <i class="fa fa-times"></i>
            </button>
        </div>
        {{-- Sub-row account: muncul saat source kategorinya Bank atau E-Wallet --}}
        <div class="pc-opening-row__account" data-pc-opening-account-row hidden>
            <label data-pc-opening-account-label>No. Rekening <small class="pc-muted">(opsional)</small></label>
            <input type="text" maxlength="50"
                   class="pc-input"
                   placeholder="Mis: 1234567890"
                   autocomplete="off"
                   data-pc-opening-account>
        </div>
    </div>
</template>

<style>
.pc-modal__dialog--wide { max-width: 640px; }

.pc-opening-rows {
    margin: 12px 0;
    box-sizing: border-box;
}
.pc-opening-empty {
    text-align: center;
    padding: 28px 12px;
    color: #6b7280;
    background: #fafafa;
    border: 1.5px dashed #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
}
.pc-opening-empty i {
    display: block;
    font-size: 28px;
    margin-bottom: 8px;
    color: #9ca3af;
}

.pc-opening-row-wrap {
    padding: 8px 0;
    box-sizing: border-box;
    width: 100%;
}
.pc-opening-row-wrap:not(:last-child) {
    border-bottom: 1px dashed #f1f5f9;
}
.pc-opening-row {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}
.pc-opening-row__account {
    margin-top: 8px;
    padding-left: 4px;
}
.pc-opening-row__account label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-bottom: 4px;
}
.pc-opening-row__account .pc-input {
    width: 100%;
    box-sizing: border-box;
}
.pc-opening-row__src {
    flex: 1;
    min-width: 0;
}
.pc-opening-row__src .pc-input {
    width: 100%;
    box-sizing: border-box;
}
.pc-opening-row__input {
    position: relative;
    display: flex;
    align-items: center;
    width: 180px;
    max-width: 45%;
    flex-shrink: 0;
}
.pc-opening-row__input .pc-input {
    width: 100%;
    box-sizing: border-box;
    padding-left: 32px !important;
    text-align: right;
    font-variant-numeric: tabular-nums;
}
.pc-opening-row__currency {
    position: absolute;
    left: 10px;
    color: #6b7280;
    font-size: 13px;
    pointer-events: none;
}
.pc-opening-row__remove {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    border: none;
    background: #fef2f2;
    color: #dc2626;
    border-radius: 6px;
    cursor: pointer;
    transition: background .15s;
}
.pc-opening-row__remove:hover {
    background: #fee2e2;
}

.pc-btn-add-source {
    width: 100%;
    padding: 10px 16px;
    background: #fff;
    border: 1.5px dashed var(--brown-2);
    border-radius: 8px;
    color: var(--brown);
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    box-sizing: border-box;
}
.pc-btn-add-source:hover {
    background: #fff8f3;
}
.pc-btn-add-source:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pc-opening-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    margin: 14px 0;
    background: #fff8f3;
    border: 1px solid var(--brown-2);
    border-radius: 8px;
    font-size: 15px;
    color: var(--brown);
    font-weight: 600;
}
.pc-opening-total strong {
    color: var(--brown);
    font-size: 1.1rem;
    font-weight: 700;
}
</style>

<script>
(function () {
    const modal = document.getElementById('pcModalOpening');
    if (!modal) return;
    const form     = document.getElementById('pcOpeningForm');
    const rowsCont = document.getElementById('pcOpeningRows');
    const emptyEl  = document.getElementById('pcOpeningEmpty');
    const addBtn   = document.getElementById('pcOpeningAddBtn');
    const submit   = document.getElementById('pcOpeningSubmitBtn');
    const totalEl  = document.getElementById('pcOpeningTotalDisplay');
    const tpl      = document.getElementById('pcOpeningRowTpl');

    // Total source aktif (semua option valid di template).
    const totalSources = tpl.content.querySelectorAll('option[value]:not([value=""])').length;

    function getRows() {
        return rowsCont.querySelectorAll('[data-pc-opening-row]');
    }
    function getPickedIds() {
        return Array.from(getRows())
            .map(r => r.querySelector('[data-pc-opening-source]').value)
            .filter(v => v);
    }

    function refreshDropdowns() {
        // Disable option yang sudah dipilih di baris lain.
        const picked = getPickedIds();
        getRows().forEach(function (row) {
            const sel = row.querySelector('[data-pc-opening-source]');
            const cur = sel.value;
            sel.querySelectorAll('option[value]:not([value=""])').forEach(function (opt) {
                if (opt.value === cur) {
                    opt.disabled = false;
                } else {
                    opt.disabled = picked.includes(opt.value);
                }
            });
        });
    }

    function refreshAddBtn() {
        const rowCount = getRows().length;
        addBtn.disabled = rowCount >= totalSources;
        if (emptyEl) emptyEl.style.display = rowCount === 0 ? '' : 'none';
    }

    function refreshTotal() {
        let total = 0;
        let validRows = 0;
        getRows().forEach(function (row) {
            const sid = row.querySelector('[data-pc-opening-source]').value;
            const raw = parseInt((row.querySelector('[data-pc-opening-amount]').value || '').replace(/\D/g, ''), 10);
            if (sid && !isNaN(raw) && raw > 0) {
                total += raw;
                validRows++;
            }
        });
        if (totalEl) totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
        if (submit) submit.disabled = validRows === 0;
    }

    function addRow() {
        if (getRows().length >= totalSources) return;
        const node = tpl.content.firstElementChild.cloneNode(true);
        rowsCont.appendChild(node);
        attachRowEvents(node);
        refreshDropdowns();
        refreshAddBtn();
        refreshTotal();
        // Auto-focus dropdown baru.
        const sel = node.querySelector('[data-pc-opening-source]');
        if (sel) sel.focus();
    }

    function removeRow(row) {
        row.remove();
        refreshDropdowns();
        refreshAddBtn();
        refreshTotal();
    }

    function syncAccountRow(row) {
        const sel    = row.querySelector('[data-pc-opening-source]');
        const acctRw = row.querySelector('[data-pc-opening-account-row]');
        const acctIn = row.querySelector('[data-pc-opening-account]');
        const acctLb = row.querySelector('[data-pc-opening-account-label]');
        if (!sel || !acctRw || !acctIn || !acctLb) return;
        const opt = sel.options[sel.selectedIndex];
        const cat = (opt && opt.dataset.category) || '';
        if (cat === 'bank') {
            acctRw.hidden = false;
            acctLb.innerHTML = 'No. Rekening <small class="pc-muted">(opsional)</small>';
            acctIn.placeholder = 'Mis: 1234567890';
            if (! acctIn.value) acctIn.value = (opt && opt.dataset.account) || '';
        } else if (cat === 'ewallet') {
            acctRw.hidden = false;
            acctLb.innerHTML = 'No. HP <small class="pc-muted">(opsional)</small>';
            acctIn.placeholder = 'Mis: 08123456789';
            if (! acctIn.value) acctIn.value = (opt && opt.dataset.account) || '';
        } else {
            acctRw.hidden = true;
            acctIn.value = '';
        }
    }

    function attachRowEvents(row) {
        const sel = row.querySelector('[data-pc-opening-source]');
        const amt = row.querySelector('[data-pc-opening-amount]');
        const rm  = row.querySelector('[data-pc-opening-remove]');

        sel.addEventListener('change', function () {
            syncAccountRow(row);
            refreshDropdowns();
            refreshTotal();
        });

        amt.addEventListener('input', function () {
            const raw = amt.value.replace(/\D/g, '');
            amt.value = raw ? parseInt(raw, 10).toLocaleString('id-ID') : '';
            refreshTotal();
        });

        rm.addEventListener('click', function () {
            removeRow(row);
        });
    }

    addBtn.addEventListener('click', addRow);

    // Saat submit: set name pada input amount = balances[<source_id>]
    // dan input account = accounts[<source_id>] untuk row yang valid.
    // Row tanpa source / nominal 0 di-skip.
    form.addEventListener('submit', function (e) {
        getRows().forEach(function (row) {
            const sid  = row.querySelector('[data-pc-opening-source]').value;
            const amt  = row.querySelector('[data-pc-opening-amount]');
            const acct = row.querySelector('[data-pc-opening-account]');
            if (sid && amt.value) {
                amt.name = 'balances[' + sid + ']';
                if (acct && acct.value.trim() !== '') {
                    acct.name = 'accounts[' + sid + ']';
                } else if (acct) {
                    acct.removeAttribute('name');
                }
            } else {
                amt.removeAttribute('name');
                if (acct) acct.removeAttribute('name');
            }
        });
    });

    // Reset modal saat dibuka ulang.
    modal.addEventListener('click', function (e) {
        if (e.target.matches('[data-pc-modal-close]') || e.target === modal) {
            // Bersihkan rows supaya next open clean.
            setTimeout(function () {
                getRows().forEach(r => r.remove());
                refreshAddBtn();
                refreshTotal();
            }, 200);
        }
    });
})();
</script>
