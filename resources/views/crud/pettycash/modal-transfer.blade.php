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
                <input type="hidden" name="from_source_id" id="transferFromSourceId">

                <div class="pc-tab-hint">
                    <i class="fa fa-right-left" style="color:#2563eb;"></i>
                    <span><strong>Transfer:</strong> pindahkan saldo dari sumber ini ke sumber dana lain. Total saldo periode <em>tidak berubah</em> — hanya redistribusi antar wallet (mis: tarik tunai dari bank, isi e-wallet dari cash).</span>
                </div>

                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Transfer ke <span class="text-danger">*</span></label>
                    <select name="to_source_id" required class="pc-input pc-source-select" id="transferToSelect">
                        <option value="">— Pilih sumber tujuan —</option>
                        @foreach($categoryLabels as $catKey => $catLabel)
                            @if($fundSourcesGrouped->has($catKey))
                                <optgroup label="{{ $catLabel }}">
                                    @foreach($fundSourcesGrouped[$catKey] as $fs)
                                        <option value="{{ $fs->id }}"
                                                data-color="{{ $fs->effectiveBrandColor() }}"
                                                data-short="{{ $fs->effectiveShortLabel() }}"
                                                data-logo="{{ $fs->logo_url }}">{{ $fs->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <div class="pc-source-preview" data-pc-source-preview></div>
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

            {{-- ─── FORM TOP UP LANGSUNG KE SOURCE INI ─── --}}
            <form method="POST" action="{{ route('pettycash.topup', ['tab' => $kind]) }}"
                  data-tab-content="topup" class="pc-tab-content" hidden>
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
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

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="pc-btn-topup-solid">
                        <i class="fa fa-arrow-down-up-across-line"></i> Tambah Saldo
                    </button>
                </div>
            </form>

            {{-- ─── FORM PENGELUARAN DARI SOURCE INI ─── --}}
            <form method="POST" action="{{ route('pettycash.expense', ['tab' => $kind]) }}"
                  data-tab-content="expense" class="pc-tab-content" hidden>
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
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
                    <label>Keterangan <span class="text-danger">*</span></label>
                    <input type="text" name="description" required maxlength="255"
                           placeholder="Isi keterangan pengeluaran"
                           class="pc-input">
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

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="pc-btn-expense">
                        <i class="fa fa-minus"></i> Simpan Pengeluaran
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
