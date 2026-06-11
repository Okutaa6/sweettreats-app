{{--
    Petty Cash / Transport — modal "Kelola Saldo Sumber Dana".
    Modal ini terbuka saat user klik salah satu card sumber dana di
    breakdown. Source asal di-populate via JS.

    Punya 2 aksi (tab switcher):
      1. Transfer ke sumber lain — dana pindah antar source, total tetap.
      2. Tambah saldo (top up) langsung ke source ini — uang masuk dari
         luar, source.balance bertambah. Mempersingkat alur agar user
         tidak perlu pakai tombol global "Top up saldo" dan re-pilih
         sumber dana.

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
@endphp
<div class="pc-modal" id="pcModalTransfer" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">

        {{-- Header (static, di luar form) --}}
        <div class="pc-modal__header">
            <div>
                <h5 class="pc-modal__title">
                    <i class="fa fa-wallet" style="color:#7c2d12;"></i> Kelola Saldo Sumber Dana
                </h5>
                <small class="pc-muted">Transfer ke sumber lain, tambah saldo langsung, atau catat pengeluaran</small>
            </div>
            <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
        </div>

        <div class="pc-modal__body">
            {{-- Preview source asal (shared antara kedua tab) --}}
            <div class="pc-transfer-from" id="transferFromPreview">
                <div class="pc-transfer-from__label">Sumber dana</div>
                <div class="pc-transfer-from__row">
                    <div class="fs-badge fs-badge--md" id="transferFromBadge" style="background:#64748b">
                        <span class="fs-badge__text" id="transferFromBadgeText">?</span>
                    </div>
                    <div class="pc-transfer-from__info">
                        <strong id="transferFromName">—</strong>
                        <span class="pc-transfer-from__bal">
                            Saldo saat ini:
                            <strong id="transferFromBalance">Rp 0</strong>
                        </span>
                        <span class="pc-transfer-from__account" id="transferFromAccount" hidden>
                            <span class="pc-transfer-from__account-label" id="transferFromAccountLabel">No. Rek</span>
                            <span class="pc-transfer-from__account-value" id="transferFromAccountValue">—</span>
                            <button type="button" class="pc-copy-btn" id="transferFromAccountCopy"
                                    title="Salin nomor" aria-label="Salin nomor">
                                <i class="fa fa-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Tab switcher --}}
            <div class="pc-tab-switcher" role="tablist">
                <button type="button" class="pc-tab-switcher__btn is-active"
                        data-tab-target="transfer" role="tab" aria-selected="true">
                    <i class="fa fa-right-left"></i> Transfer
                </button>
                <button type="button" class="pc-tab-switcher__btn"
                        data-tab-target="topup" role="tab" aria-selected="false">
                    <i class="fa fa-arrow-down-up-across-line"></i> Tambah Saldo
                </button>
                <button type="button" class="pc-tab-switcher__btn"
                        data-tab-target="expense" role="tab" aria-selected="false">
                    <i class="fa fa-minus"></i> Pengeluaran
                </button>
            </div>

            {{-- ─── FORM TRANSFER ─── --}}
            <form method="POST" action="{{ route('pettycash.transfer', ['tab' => $kind]) }}"
                  data-tab-content="transfer" class="pc-tab-content">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
                @if(!session('raw_branch_id') && $period->raw_branch_id)
                    <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                @endif
                <input type="hidden" name="from_source_id" id="transferFromSourceId">

                <div class="pc-tab-hint" id="transferTabHint">
                    <i class="fa fa-right-left" id="transferTabHintIcon" style="color:#2563eb;"></i>
                    <span id="transferTabHintText"><strong>Transfer:</strong> pindahkan saldo dari sumber ini ke sumber dana lain. Total saldo periode <em>tidak berubah</em> — hanya redistribusi antar wallet (mis: tarik tunai dari bank, isi e-wallet dari cash).</span>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label id="transferToLabel">Transfer ke <span class="text-danger">*</span></label>
                    <select name="to_source_id" required class="pc-input pc-source-select" id="transferToSelect">
                        <option value="">— Pilih sumber tujuan —</option>
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
                    <label data-pc-transfer-to-account-label>No. Rekening <small class="pc-muted">(opsional)</small></label>
                    <input type="text" name="to_account_number" maxlength="50"
                           class="pc-input"
                           data-pc-transfer-to-account-input
                           placeholder="Mis: 1234567890">
                    <small class="pc-muted">Update nomor untuk sumber tujuan.</small>
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="transferAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="transferAmount" required>
                    <small class="pc-muted">
                        Maks: Rp <span id="transferMaxHint">0</span>
                    </small>
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                    <input type="text" name="description" maxlength="200"
                           placeholder="Mis: konsolidasi saldo, isi e-wallet, dll"
                           class="pc-input">
                </div>

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="st-btn-brand">
                        <i class="fa fa-right-left"></i> Transfer Saldo
                    </button>
                </div>
            </form>

            {{-- ─── TAB TAMBAH SALDO — 2 VARIAN ───
                 Wrapper utama bertanda data-tab-content="topup". Di
                 dalamnya ada 2 form yang di-swap JS berdasarkan kategori
                 source asal:
                   - variant=external : dana baru dari luar (untuk
                     Bank / E-Wallet — setoran, pencairan, refund).
                   - variant=from-source : tarik dari sumber lain (untuk
                     Cash — total periode tidak berubah, hanya pindah
                     saldo dari Bank/E-Wallet ke Cash). Submit sebagai
                     transfer biasa, target = Cash di card. --}}
            <div data-tab-content="topup" class="pc-tab-content" hidden>
                {{-- VARIANT 1: external (Bank / E-Wallet) --}}
                <form method="POST" action="{{ route('pettycash.topup', ['tab' => $kind]) }}"
                      data-pc-topup-variant="external">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    <input type="hidden" name="fund_source_id" id="topupSourceFundSourceId">

                    <div class="pc-tab-hint">
                        <i class="fa fa-arrow-down-up-across-line" style="color:#16a34a;"></i>
                        <span><strong>Tambah Saldo:</strong> catat <em>dana baru masuk</em> ke sumber ini dari luar (mis: setoran tunai, pencairan, refund). Saldo source &amp; total periode keduanya <em>bertambah</em>.</span>
                    </div>

                    <div class="pc-form-row">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    </div>

                    <div class="pc-form-row">
                        <label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric"
                               class="pc-input pc-amount-input"
                               data-target="topupSourceAmount"
                               placeholder="0">
                        <input type="hidden" name="amount" id="topupSourceAmount" required>
                    </div>

                    <div class="pc-form-row">
                        <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="255"
                               placeholder="Mis: setoran tunai, pencairan, dll"
                               class="pc-input">
                    </div>

                    {{-- Account input — di-show oleh JS hanya kalau source asal
                         berkategori bank / ewallet. Label & placeholder ikut
                         berubah. --}}
                    <div class="pc-form-row" data-pc-topup-src-account-row hidden>
                        <label data-pc-topup-src-account-label>No. Rekening <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="account_number" maxlength="50"
                               class="pc-input"
                               data-pc-topup-src-account-input
                               placeholder="Mis: 1234567890">
                        <small class="pc-muted">Update nomor untuk sumber dana ini.</small>
                    </div>

                    <div class="pc-form-actions">
                        <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                        <button type="submit" class="pc-btn-topup-solid">
                            <i class="fa fa-arrow-down-up-across-line"></i> Tambah Saldo
                        </button>
                    </div>
                </form>

                {{-- VARIANT 2: from-source (khusus Cash).
                     2 mode:
                       a) Sumber dipilih → tarik dari Bank/E-Wallet ke Cash,
                          submit ke endpoint transfer (pasangan OUT+IN, total tetap).
                       b) Sumber dikosongkan → setor cash langsung (saldo awal / migrasi),
                          submit ke endpoint topup dengan fund_source_id = Cash. Total
                          saldo periode BERTAMBAH. JS handle swap action & field name. --}}
                <form method="POST" action="{{ route('pettycash.transfer', ['tab' => $kind]) }}"
                      data-pc-topup-variant="from-source"
                      data-action-transfer="{{ route('pettycash.transfer', ['tab' => $kind]) }}"
                      data-action-topup="{{ route('pettycash.topup', ['tab' => $kind]) }}"
                      id="topupCashForm"
                      hidden>
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    <input type="hidden" name="to_source_id" id="topupCashToSourceId">

                    <div class="pc-tab-hint">
                        <i class="fa fa-piggy-bank" style="color:#16a34a;"></i>
                        <span><strong>Tambah Saldo Cash:</strong> pilih sumber kalau tarik dari Bank / E-Wallet, atau kosongkan kalau cash sudah di tangan (saldo awal / migrasi).</span>
                    </div>

                    <div class="pc-form-row">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    </div>

                    <div class="pc-form-row">
                        <label>Tarik dari <small class="pc-muted">(opsional)</small></label>
                        <select name="from_source_id" class="pc-input" id="topupCashFromSelect">
                            <option value="">— Kosongkan untuk setor cash langsung —</option>
                            @foreach($sourceBalances as $sb)
                                @if($sb->id && $sb->category !== \App\Models\FundSource::CAT_CASH && (float) $sb->balance > 0)
                                    <option value="{{ $sb->id }}"
                                            data-balance="{{ $sb->balance }}"
                                            data-name="{{ $sb->name }}">
                                        {{ $sb->name }} — Rp {{ number_format((float) $sb->balance, 0, ',', '.') }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                        <small class="pc-muted">
                            Kosongkan kalau cash sudah di tangan (saldo awal / migrasi) — tidak perlu pilih sumber.
                        </small>
                    </div>

                    {{-- Nominal row: selalu tampil. Hint berubah sesuai mode. --}}
                    <div class="pc-form-row" data-pc-topup-cash-amount-row>
                        <label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric"
                               class="pc-input pc-amount-input"
                               data-target="topupCashAmount"
                               placeholder="0">
                        <input type="hidden" name="amount" id="topupCashAmount" required>
                        <small class="pc-muted" id="topupCashHint">
                            Setor langsung — input nominal cash yang sudah di tangan.
                        </small>
                    </div>

                    <div class="pc-form-row">
                        <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="200"
                               placeholder="Mis: tarik tunai dari bank"
                               class="pc-input">
                    </div>

                    <div class="pc-form-actions">
                        <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                        <button type="submit" class="pc-btn-topup-solid" id="topupCashSubmitBtn" disabled>
                            <i class="fa fa-piggy-bank"></i> Tarik Tunai
                        </button>
                    </div>
                </form>
            </div>

            {{-- ─── FORM PENGELUARAN DARI SOURCE INI ─── --}}
            <form method="POST" action="{{ route('pettycash.expense', ['tab' => $kind]) }}"
                  data-tab-content="expense" class="pc-tab-content" hidden>
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
                @if(!session('raw_branch_id') && $period->raw_branch_id)
                    <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                @endif
                <input type="hidden" name="fund_source_id" id="expenseSourceFundSourceId">

                <div class="pc-tab-hint">
                    <i class="fa fa-minus" style="color:#dc2626;"></i>
                    <span><strong>Pengeluaran:</strong> catat <em>dana keluar</em> dari sumber ini (mis: belanja operasional, biaya transport). Saldo source &amp; total periode keduanya <em>berkurang</em>.</span>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="expenseSourceAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="expenseSourceAmount" required>
                    <small class="pc-muted">
                        Maks: Rp <span id="expenseSourceMaxHint">0</span>
                    </small>
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <span class="text-danger">*</span></label>
                    <input type="text" name="description" required maxlength="255"
                           placeholder="Isi keterangan pengeluaran"
                           class="pc-input">
                </div>

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="pc-btn-expense">
                        <i class="fa fa-minus"></i> Simpan Pengeluaran
                    </button>
                </div>
            </form>

            {{-- ─── DANGER ZONE: hapus seluruh data source ini di periode ───
                 Hapus semua transaksi (cash in, cash out, transfer pair) + reset
                 saldo awal source di periode aktif. Tidak menghapus master
                 fund source. --}}
            <div class="pc-source-danger">
                <hr class="pc-source-danger__divider">
                <div class="pc-source-danger__warn">
                    <i class="fa fa-triangle-exclamation"></i>
                    <span>Hapus semua transaksi &amp; saldo awal sumber dana ini di periode aktif. <strong>Aksi tidak bisa di-undo.</strong></span>
                </div>
                <form method="POST" action="{{ route('pettycash.source.destroy', ['tab' => $kind]) }}"
                      id="pcSourceDestroyForm"
                      onsubmit="return confirm('Yakin hapus SEMUA data sumber dana ini di periode aktif?\n\n• Saldo awal source ini akan di-reset\n• Semua transaksi (cash in, cash out, transfer) yg pakai source ini akan dihapus\n• Aksi TIDAK bisa di-undo.\n\nLanjut?');">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    <input type="hidden" name="fund_source_id" id="pcSourceDestroyId">
                    <button type="submit" class="pc-source-danger__btn">
                        <i class="fa fa-trash"></i> Hapus Data Sumber Dana Ini
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.pc-source-danger {
    margin-top: 18px;
}
.pc-source-danger__divider {
    border: none;
    border-top: 1px solid var(--divider-soft, #e5e7eb);
    margin: 0 0 14px;
}
.pc-source-danger__warn {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 10px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    color: #991b1b;
    font-size: 12.5px;
    line-height: 1.5;
    margin-bottom: 10px;
}
.pc-source-danger__warn i { color: #dc2626; margin-top: 2px; flex-shrink: 0; }
.pc-source-danger__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #fff;
    color: #dc2626;
    border: 1.5px solid #dc2626;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
    transition: background .15s;
}
.pc-source-danger__btn:hover {
    background: #dc2626;
    color: #fff;
}
</style>
