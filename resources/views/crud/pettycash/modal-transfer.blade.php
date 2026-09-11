{{--
    Petty Cash / Transport — modal "Kelola Saldo Sumber Dana".
    Modal ini terbuka saat user klik salah satu card sumber dana di
    breakdown. Source asal di-populate via JS.

    Punya 2 aksi (tab switcher):
      1. Pindah Saldo — dana pindah ke wallet lain, total saldo tetap.
         Satu form untuk semua arah (Cash → Bank maupun Bank → Cash);
         dulu dipecah jadi "Mutasi" / "Deposit" / "Tarik Tunai" padahal
         perbuatannya sama.
      2. Pengeluaran dari wallet ini.

    Variables: $period, $kind, $fundSourcesGrouped (full list — semua source aktif).
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
    // Kategori expense untuk form Pengeluaran. Hanya kategori aktif.
    $expenseCategories = \App\Models\PettyCashTransaction::activeCategoryOptions();

    // Batas tanggal = bulan periode yang sedang dibuka. Transaksi yang
    // tanggalnya keluar dari sini akan tersimpan di periode yang salah:
    // uangnya mengurangi saldo bulan ini, tapi barisnya tak muncul di bulan
    // mana pun. Server memagari hal yang sama (abortIfDateOutsidePeriod).
    $pcMonthStart = sprintf('%04d-%02d-01', (int) $period->period_year, (int) $period->period_month);
    $pcMonthEnd   = date('Y-m-t', strtotime($pcMonthStart));
    $pcDefaultDate = min(max(date('Y-m-d'), $pcMonthStart), $pcMonthEnd);
@endphp
<div class="pc-modal" id="pcModalTransfer" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">

        {{-- Header (static, di luar form) --}}
        <div class="pc-modal__header pc-modal__header--wallet">
            <span class="pc-modal__icon-box" aria-hidden="true">
                <i class="fa fa-wallet"></i>
            </span>
            <div class="pc-modal__heading">
                <h5 class="pc-modal__title">Kelola Wallet</h5>
                <small class="pc-muted">Pilih aksi untuk wallet ini</small>
            </div>
            <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
        </div>

        <div class="pc-modal__body">
            {{-- Preview source asal (shared antara kedua tab) --}}
            <div class="pc-transfer-from" id="transferFromPreview">
                <div class="pc-transfer-from__row">
                    <div class="fs-badge fs-badge--md" id="transferFromBadge" style="background:#64748b">
                        <span class="fs-badge__text" id="transferFromBadgeText">?</span>
                    </div>
                    <div class="pc-transfer-from__info">
                        <div class="pc-transfer-from__label">Sumber dana</div>
                        <strong class="pc-transfer-from__name" id="transferFromName">—</strong>
                        <span class="pc-transfer-from__bal">
                            Saldo saat ini <span class="pc-transfer-from__sep">·</span>
                            <strong id="transferFromBalance">Rp 0</strong>
                        </span>
                        <span class="pc-transfer-from__account" id="transferFromAccount" hidden>
                            <span class="pc-transfer-from__account-label" id="transferFromAccountLabel">No. Rek</span>
                            <span class="pc-transfer-from__account-value" id="transferFromAccountValue">—</span>
                            <button type="button" class="pc-copy-btn" id="transferFromAccountCopy"
                                    title="Salin nomor" aria-label="Salin nomor">
                                <i class="fa fa-copy"></i>
                            </button>
                            <button type="button" class="pc-copy-btn pc-copy-btn--edit"
                                    id="transferFromAccountEdit"
                                    title="Ubah nomor" aria-label="Ubah nomor">
                                <i class="fa fa-pen"></i>
                            </button>
                        </span>
                    </div>

                    {{-- Inline add: muncul kalau no rek/HP belum diisi & source = Bank/E-Wallet.
                         Dipindah ke sisi kanan agar match desain (button "+ No. Rek" di pojok). --}}
                    <div class="pc-transfer-from__account-add" id="transferFromAccountAdd" hidden>
                        <button type="button" class="pc-transfer-from__add-btn"
                                id="transferFromAccountAddBtn"
                                title="Tambahkan nomor rekening / telepon">
                            <i class="fa fa-plus"></i>
                            <span id="transferFromAccountAddLabel">No. Rek</span>
                        </button>
                        <span class="pc-transfer-from__add-form" id="transferFromAccountAddForm" hidden>
                            <input type="text" maxlength="50"
                                   inputmode="numeric"
                                   pattern="[0-9]*"
                                   class="pc-input pc-input--sm"
                                   id="transferFromAccountAddInput"
                                   placeholder="Mis: 1234567890"
                                   autocomplete="off">
                            <button type="button" class="pc-transfer-from__save-btn"
                                    id="transferFromAccountSaveBtn"
                                    title="Simpan">
                                <i class="fa fa-check"></i>
                            </button>
                            <button type="button" class="pc-transfer-from__cancel-btn"
                                    id="transferFromAccountCancelBtn"
                                    title="Batal">
                                <i class="fa fa-times"></i>
                            </button>
                        </span>
                        <span class="pc-transfer-from__add-error" id="transferFromAccountAddError" hidden></span>
                    </div>
                </div>
            </div>

            {{-- Tab switcher --}}
            <div class="pc-tab-switcher" role="tablist">
                <button type="button" class="pc-tab-switcher__btn is-active"
                        data-tab-target="transfer" role="tab" aria-selected="true">
                    <i class="fa fa-exchange-alt"></i> Pindah Saldo
                </button>
                <button type="button" class="pc-tab-switcher__btn"
                        data-tab-target="expense" role="tab" aria-selected="false">
                    <i class="fa fa-minus"></i> Pengeluaran
                </button>
            </div>

            {{-- ─── FORM TRANSFER ─── --}}
            {{-- Pindah saldo = pergerakan internal antar wallet milik cabang
                 sendiri, tidak ada pihak luar yang perlu dibuktikan. Jadi tidak
                 ada upload bukti di sini (form Pengeluaran tetap ada). --}}
            <form method="POST" action="{{ route('pettycash.transfer', ['tab' => $kind]) }}" data-kunci-kirim
                  data-tab-content="transfer" class="pc-tab-content">
                @csrf
                @include('crud.pettycash._filter_hidden')
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
                @if(!session('raw_branch_id') && $period->raw_branch_id)
                    <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                @endif
                <input type="hidden" name="from_source_id" id="transferFromSourceId">

                <div class="pc-tab-hint pc-tab-hint--transfer" id="transferTabHint">
                    <span id="transferTabHintText">Pindahkan saldo antar wallet. Total saldo periode tetap.</span>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal</label>
                    <input type="date" name="trx_date" value="{{ $pcDefaultDate }}" required class="pc-input"
                           min="{{ $pcMonthStart }}" max="{{ $pcMonthEnd }}">
                </div>

                <div class="pc-form-row">
                    <label id="transferToLabel">Pindah ke</label>
                    <select name="to_source_id" required class="pc-input pc-source-select" id="transferToSelect">
                        <option value="">Pilih wallet tujuan…</option>
                        @foreach($categoryLabels as $catKey => $catLabel)
                            @if($fundSourcesGrouped->has($catKey))
                                <optgroup label="{{ $catLabel }}">
                                    @foreach($fundSourcesGrouped[$catKey] as $fs)
                                        <option value="{{ $fs->id }}"
                                                data-color="{{ $fs->effectiveBrandColor() }}"
                                                data-short="{{ $fs->effectiveShortLabel() }}"
                                                data-logo="{{ $fs->logo_url }}"
                                                data-category="{{ $fs->category }}"
                                                data-account="{{ $branchAccountMap[$fs->id] ?? '' }}">{{ $fs->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <div class="pc-source-preview" data-pc-source-preview></div>
                </div>

                {{-- Input nomor rekening / telepon untuk SUMBER TUJUAN —
                     toggled JS berdasarkan kategori. Cash → hidden. --}}
                <div class="pc-form-row" data-pc-transfer-to-account-row hidden>
                    <label data-pc-transfer-to-account-label>No. Rekening tujuan <small class="pc-muted">— opsional</small></label>
                    <input type="text" name="to_account_number" maxlength="50"
                           class="pc-input"
                           data-pc-transfer-to-account-input
                           placeholder="Contoh: 1234567890">
                </div>

                <div class="pc-form-row">
                    <label>Nominal</label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="transferAmount"
                           placeholder="Rp 0">
                    <input type="hidden" name="amount" id="transferAmount" required>
                    <small class="pc-muted">
                        Saldo tersedia: Rp <span id="transferMaxHint">0</span>
                    </small>
                </div>

                <div class="pc-form-actions pc-form-actions--friendly">
                    <button type="submit" class="st-btn-brand pc-btn-primary-friendly">
                        Simpan Pindah Saldo
                    </button>
                    <button type="button" class="pc-btn-text" data-pc-modal-close>Batal</button>
                </div>
            </form>

            {{-- ─── FORM PENGELUARAN DARI SOURCE INI ─── --}}
            <form method="POST" action="{{ route('pettycash.expense', ['tab' => $kind]) }}" data-kunci-kirim
                  data-tab-content="expense" class="pc-tab-content"
                  enctype="multipart/form-data"
                  hidden>
                @csrf
                @include('crud.pettycash._filter_hidden')
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
                @if(!session('raw_branch_id') && $period->raw_branch_id)
                    <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                @endif
                <input type="hidden" name="fund_source_id" id="expenseSourceFundSourceId">

                <div class="pc-tab-hint pc-tab-hint--expense">
                    <span>Catat pengeluaran yang dibayar pakai wallet ini. Saldo wallet &amp; total periode berkurang.</span>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal</label>
                    <input type="date" name="trx_date" value="{{ $pcDefaultDate }}" required class="pc-input"
                           min="{{ $pcMonthStart }}" max="{{ $pcMonthEnd }}">
                </div>

                <div class="pc-form-row">
                    <label>Kategori</label>
                    <select name="category" required class="pc-input" data-pc-expense-category>
                        <option value="">Pilih kategori…</option>
                        @foreach($expenseCategories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="pc-form-row">
                    <label>Nominal</label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="expenseSourceAmount"
                           placeholder="Rp 0">
                    <input type="hidden" name="amount" id="expenseSourceAmount" required>
                    <small class="pc-muted">
                        Saldo tersedia: Rp <span id="expenseSourceMaxHint">0</span>
                    </small>
                </div>

                <div class="pc-form-row">
                    <label>Keterangan</label>
                    <input type="text" name="description" required maxlength="255"
                           placeholder="Contoh: beli galon, parkir, dll"
                           class="pc-input">
                </div>

                {{-- Upload bukti pengeluaran (struk/bon/transfer). Opsional.
                     Accept jpg/png/webp/pdf, max 5MB (validasi server-side juga). --}}
                <div class="pc-form-row">
                    <label>Bukti <small class="pc-muted">— opsional</small></label>
                    <div class="pc-attach-zone" id="expenseAttachZone">
                        <input type="file" name="attachment"
                               id="expenseAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="expenseAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Tap untuk pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">Maks 5 MB</span>
                        </label>
                        <div class="pc-attach-preview" id="expenseAttachPreview" hidden>
                            <div class="pc-attach-preview__thumb" id="expenseAttachThumb">
                                <i class="fa fa-file"></i>
                            </div>
                            <div class="pc-attach-preview__meta">
                                <div class="pc-attach-preview__name" id="expenseAttachName">—</div>
                                <div class="pc-attach-preview__size" id="expenseAttachSize">—</div>
                            </div>
                            <button type="button" class="pc-attach-preview__remove"
                                    id="expenseAttachRemove"
                                    title="Hapus file">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pc-form-actions pc-form-actions--friendly">
                    <button type="submit" class="pc-btn-expense pc-btn-primary-friendly">
                        Simpan Pengeluaran
                    </button>
                    <button type="button" class="pc-btn-text" data-pc-modal-close>Batal</button>
                </div>
            </form>

            {{-- ─── DANGER ZONE: hapus seluruh data source ini di periode ───
                 Hapus semua transaksi (cash in, cash out, transfer pair) + reset
                 saldo awal source di periode aktif. Tidak menghapus master
                 fund source.
                 ACCESS: hanya Super Admin & Admin General. Role lain dapat versi
                 locked (button disabled + form onsubmit return false). --}}
            @php $canHardDelete = isSuperAdmin() || isAdminGeneral(); @endphp
            @if($canHardDelete)
                <div class="pc-source-danger">
                    <hr class="pc-source-danger__divider">
                    <form method="POST" action="{{ route('pettycash.source.destroy', ['tab' => $kind]) }}"
                          id="pcSourceDestroyForm"
                          onsubmit="return confirm('Yakin hapus SEMUA data wallet ini di periode aktif?\n\n• Saldo awal wallet ini akan di-reset\n• Semua transaksi (cash in, cash out, transfer) yg pakai wallet ini akan dihapus\n• TERMASUK transaksi yang dicatat ORANG LAIN di periode ini — bukan hanya milikmu\n• Tidak bisa di-undo dari layar ini\n\nJumlahnya akan disebutkan sesudah dihapus, dan namamu tercatat.\n\nLanjut?');">
                        @csrf
                        <input type="hidden" name="period_id" value="{{ $period->id }}">
                        <input type="hidden" name="tab" value="{{ $kind }}">
                        @if(!session('raw_branch_id') && $period->raw_branch_id)
                            <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                        @endif
                        <input type="hidden" name="fund_source_id" id="pcSourceDestroyId">
                        <button type="submit" class="pc-source-danger__link"
                                title="Reset semua transaksi & saldo awal wallet ini (admin only)">
                            <i class="fa fa-trash"></i> Reset data wallet di periode ini
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>

<style>
/* ─── Friendlier modal — scoped ke #pcModalTransfer ─── */

/* Header: title sedikit lebih besar & subtitle lebih lembut */
#pcModalTransfer .pc-modal__title { font-size: 16px; font-weight: 800; }
#pcModalTransfer .pc-modal__header--wallet .pc-muted { font-size: 12px; }

/* Hint banner: pakai border-left strip warna + bg lebih lembut, kurang noise */
#pcModalTransfer .pc-tab-hint {
    border-radius: 8px;
    border: none;
    border-left: 4px solid #cbd5e1;
    padding: 10px 12px;
    font-size: 12.5px;
    line-height: 1.5;
    color: #334155;
}
#pcModalTransfer .pc-tab-hint--transfer { background: #eff6ff; border-left-color: #3b82f6; }
#pcModalTransfer .pc-tab-hint--expense  { background: #fef2f2; border-left-color: #ef4444; }

/* Form row spacing sedikit dilonggarin */
#pcModalTransfer .pc-form-row { margin-bottom: 16px; gap: 6px; }
#pcModalTransfer .pc-form-row label {
    font-weight: 600;
    color: var(--brown);
    font-size: 13px;
}
#pcModalTransfer .pc-form-row label .pc-muted { font-weight: 400; color: #94a3b8; font-size: 11.5px; }

/* Input default: padding lebih lapang, border radius lebih lembut */
#pcModalTransfer .pc-input {
    padding: 11px 12px;
    font-size: 14px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    transition: border-color .15s, box-shadow .15s;
}
#pcModalTransfer .pc-input:focus {
    border-color: var(--brown);
    box-shadow: 0 0 0 3px rgba(70, 21, 0, 0.08);
    outline: none;
}

/* Amount input: bigger so user tahu ini "the important field" */
#pcModalTransfer .pc-amount-input {
    font-size: 17px;
    font-weight: 700;
    padding: 12px 14px;
    color: #0f172a;
}

/* Action bar: primary button dominan (full width), Batal jadi text-link */
.pc-form-actions--friendly {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 4px;
    padding-top: 14px;
    margin-top: 10px;
    border-top: 1px solid var(--divider-soft);
}
.pc-form-actions--friendly .pc-btn-primary-friendly {
    width: 100%;
    padding: 13px 18px;
    font-size: 14.5px;
    font-weight: 700;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(70, 21, 0, 0.12);
}
.pc-form-actions--friendly .pc-btn-primary-friendly:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(70, 21, 0, 0.18);
}
.pc-form-actions--friendly .pc-btn-primary-friendly:disabled {
    opacity: 0.55;
    cursor: not-allowed;
    box-shadow: none;
}
.pc-btn-text {
    background: transparent;
    border: none;
    color: #94a3b8;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border-radius: 6px;
    transition: color .15s, background .15s;
    width: 100%;
}
.pc-btn-text:hover { color: var(--brown); background: var(--cream-header, #f4ece2); }

/* Tab switcher: tombol sedikit lebih besar + spacing */
#pcModalTransfer .pc-tab-switcher__btn { padding: 11px 12px; font-size: 13px; }

/* Source preview di dropdown tujuan: spacing lebih lapang */
#pcModalTransfer .pc-source-preview { margin-top: 8px; }

/* Danger zone: subtle text-link di footer modal (tidak loud) */
.pc-source-danger {
    margin-top: 14px;
    text-align: center;
}
.pc-source-danger__divider {
    border: none;
    border-top: 1px solid var(--divider-soft, #e5e7eb);
    margin: 0 0 10px;
}
.pc-source-danger__link {
    background: transparent;
    border: none;
    color: #b91c1c;
    font-size: 12px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: background .15s, color .15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    opacity: 0.7;
}
.pc-source-danger__link:hover { background: #fef2f2; color: #991b1b; opacity: 1; }
.pc-source-danger__link i { font-size: 11px; }
</style>
