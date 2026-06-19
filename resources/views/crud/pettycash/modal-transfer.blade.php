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
    // ID sumber Cash (untuk fitur Tarik Tunai di modal Bank/E-Wallet —
    // destinasinya selalu Cash). Asumsi 1 cash source per cabang.
    $cashSourceId = optional(
        ($fundSourcesGrouped[\App\Models\FundSource::CAT_CASH] ?? collect())->first()
    )->id;
@endphp
<div class="pc-modal" id="pcModalTransfer" role="dialog" aria-modal="true"
     data-pc-cash-source-id="{{ $cashSourceId }}">
    <div class="pc-modal__dialog">

        {{-- Header (static, di luar form) --}}
        <div class="pc-modal__header">
            <div>
                <h5 class="pc-modal__title">
                    <i class="fa fa-wallet" style="color:#7c2d12;"></i> Kelola Saldo Wallet
                </h5>
                <small class="pc-muted">Transfer ke wallet lain, tambah saldo langsung, atau catat pengeluaran</small>
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
                            <button type="button" class="pc-copy-btn pc-copy-btn--edit"
                                    id="transferFromAccountEdit"
                                    title="Ubah nomor" aria-label="Ubah nomor">
                                <i class="fa fa-pen"></i>
                            </button>
                        </span>

                        {{-- Inline add: muncul kalau no rek/HP belum diisi & source = Bank/E-Wallet --}}
                        <span class="pc-transfer-from__account-add" id="transferFromAccountAdd" hidden>
                            <button type="button" class="pc-transfer-from__add-btn"
                                    id="transferFromAccountAddBtn"
                                    title="Tambahkan nomor rekening / telepon">
                                <i class="fa fa-plus-circle"></i>
                                <span id="transferFromAccountAddLabel">Tambah No. Rek</span>
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
                  data-tab-content="transfer" class="pc-tab-content"
                  enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="period_id" value="{{ $period->id }}">
                <input type="hidden" name="tab" value="{{ $kind }}">
                @if(!session('raw_branch_id') && $period->raw_branch_id)
                    <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                @endif
                <input type="hidden" name="from_source_id" id="transferFromSourceId">

                <div class="pc-tab-hint" id="transferTabHint">
                    <i class="fa fa-right-left" id="transferTabHintIcon" style="color:#2563eb;"></i>
                    <span id="transferTabHintText"><strong>Transfer:</strong> pindahkan saldo antar wallet yang <strong>Anda miliki sendiri</strong> — <em>bukan transfer ke rekening pihak lain</em>. Total saldo periode tidak berubah, hanya perpindahan internal antar wallet milik sendiri (mis: tarik tunai dari Bank ke Cash, isi E-Wallet dari Cash).</span>
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

                    {{-- Preview verb: collapsible dengan animasi smooth.
                         Auto-detect Deposit / Tarik Tunai / Transfer
                         berdasarkan kategori asal vs tujuan. Background
                         berubah sesuai mode (soft tint). --}}
                    <div class="pc-verb-preview" id="transferVerbPreview" hidden>
                        <button type="button" class="pc-verb-preview__head"
                                aria-expanded="false" aria-controls="transferVerbDesc">
                            <span class="pc-verb-preview__hint">
                                <i class="fa fa-info-circle"></i> Akan dicatat sebagai:
                            </span>
                            <span class="pc-verb-preview__badge" id="transferVerbBadge">TRANSFER</span>
                            <i class="fa fa-chevron-down pc-verb-preview__chevron"></i>
                        </button>
                        <div class="pc-verb-preview__wrap">
                            <div class="pc-verb-preview__desc" id="transferVerbDesc"></div>
                        </div>
                    </div>
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

                {{-- Bukti mutasi (struk transfer, screenshot mutasi rekening). Opsional. --}}
                <div class="pc-form-row">
                    <label>Bukti mutasi <small class="pc-muted">(opsional)</small></label>
                    <div class="pc-attach-zone">
                        <input type="file" name="attachment"
                               id="transferAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="transferAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">struk / screenshot mutasi · max 5MB</span>
                        </label>
                        <div class="pc-attach-preview" hidden>
                            <div class="pc-attach-preview__thumb"><i class="fa fa-file"></i></div>
                            <div class="pc-attach-preview__meta">
                                <div class="pc-attach-preview__name">—</div>
                                <div class="pc-attach-preview__size">—</div>
                            </div>
                            <button type="button" class="pc-attach-preview__remove" title="Hapus file">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    <button type="submit" class="st-btn-brand">
                        <i class="fa fa-right-left"></i> Transfer Saldo
                    </button>
                </div>
            </form>

            {{-- ─── TAB TENGAH — 2 VARIAN BERBEDA FUNGSI ───
                 Wrapper bertanda data-tab-content="topup" (nama dipertahankan
                 untuk kompatibilitas selector tab). Form yang ditampilkan
                 di-swap JS berdasarkan kategori source asal:
                   - variant=external    → "TARIK TUNAI" (untuk Bank/E-Wallet)
                     Cairkan saldo Bank/E-Wallet jadi cash. Submit ke endpoint
                     transfer dengan from=source ini, to=Cash. Total periode
                     tidak berubah.
                   - variant=from-source → "SETOR CASH" (khusus Cash)
                     Catat uang cash langsung masuk (mis. kembalian kasbon).
                     Submit ke endpoint topup dengan is_topup=0 — saldo Cash
                     naik tapi tidak dihitung sebagai Top Up. --}}
            <div data-tab-content="topup" class="pc-tab-content" hidden>
                {{-- VARIANT 1: TARIK TUNAI (Bank / E-Wallet → Cash) --}}
                <form method="POST" action="{{ route('pettycash.transfer', ['tab' => $kind]) }}"
                      data-pc-topup-variant="external"
                      id="tarikTunaiForm"
                      enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    {{-- from = source asal (Bank/E-Wallet), to = Cash. Diisi JS. --}}
                    <input type="hidden" name="from_source_id" id="tarikTunaiFromSourceId">
                    <input type="hidden" name="to_source_id"   id="tarikTunaiToSourceId">

                    <div class="pc-tab-hint">
                        <i class="fa fa-piggy-bank" style="color:#0891b2;"></i>
                        <span><strong>Tarik Tunai:</strong> cairkan saldo sumber ini jadi uang fisik di kas Cash. Saldo sumber asal <em>turun</em>, saldo Cash <em>naik</em>. Total saldo periode tidak berubah.</span>
                    </div>

                    {{-- Panel verb preview — info penjelasan label transaksi
                         yang akan dicatat. Default expanded supaya user tahu
                         efek aksinya. --}}
                    <div class="pc-verb-preview is-withdraw is-open" id="tarikTunaiVerbPreview">
                        <button type="button" class="pc-verb-preview__head"
                                aria-expanded="true" aria-controls="tarikTunaiVerbDesc">
                            <span class="pc-verb-preview__hint">
                                <i class="fa fa-info-circle"></i> Akan dicatat sebagai:
                            </span>
                            <span class="pc-verb-preview__badge pc-verb-preview__badge--withdraw">TARIK TUNAI</span>
                            <i class="fa fa-chevron-down pc-verb-preview__chevron"></i>
                        </button>
                        <div class="pc-verb-preview__wrap">
                            <div class="pc-verb-preview__desc" id="tarikTunaiVerbDesc">
                                Cairkan saldo <strong id="tarikTunaiVerbFromName">sumber ini</strong> jadi uang fisik di <strong>Cash / Tunai</strong>.
                            </div>
                        </div>
                    </div>

                    <div class="pc-form-row">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    </div>

                    <div class="pc-form-row">
                        <label>Nominal yang ditarik (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric"
                               class="pc-input pc-amount-input"
                               data-target="tarikTunaiAmount"
                               placeholder="0">
                        <input type="hidden" name="amount" id="tarikTunaiAmount" required>
                        <small class="pc-muted">
                            Maks: Rp <span id="tarikTunaiMaxHint">0</span>
                        </small>
                    </div>

                    <div class="pc-form-row">
                        <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="200"
                               placeholder="Mis: tarik tunai untuk operasional kasir"
                               class="pc-input">
                    </div>

                    {{-- Bukti tarik tunai (struk ATM, screenshot mutasi). Opsional. --}}
                    <div class="pc-form-row">
                        <label>Bukti tarik tunai <small class="pc-muted">(opsional)</small></label>
                        <div class="pc-attach-zone">
                            <input type="file" name="attachment"
                                   id="tarikTunaiAttachInput"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                                   class="pc-attach-input">
                            <label for="tarikTunaiAttachInput" class="pc-attach-label">
                                <i class="fa fa-camera"></i>
                                <span class="pc-attach-label__main">Pilih foto / PDF</span>
                                <span class="pc-attach-label__hint">struk ATM / screenshot · max 5MB</span>
                            </label>
                            <div class="pc-attach-preview" hidden>
                                <div class="pc-attach-preview__thumb"><i class="fa fa-file"></i></div>
                                <div class="pc-attach-preview__meta">
                                    <div class="pc-attach-preview__name">—</div>
                                    <div class="pc-attach-preview__size">—</div>
                                </div>
                                <button type="button" class="pc-attach-preview__remove" title="Hapus file">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pc-form-actions">
                        <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                        <button type="submit" class="pc-btn-topup-solid" id="tarikTunaiSubmitBtn" disabled>
                            <i class="fa fa-piggy-bank"></i> Tarik Tunai
                        </button>
                    </div>
                </form>

                {{-- VARIANT 2: SETOR CASH (khusus klik kartu Cash).
                     Form sederhana — input nominal + keterangan saja, tanpa
                     dropdown sumber. Submit ke endpoint topup dengan
                     is_topup=0 supaya saldo Cash naik tapi TIDAK dihitung di
                     "Total Top Up". Use case: kembalian kasbon karyawan,
                     koreksi cash, dll. --}}
                <form method="POST" action="{{ route('pettycash.topup', ['tab' => $kind]) }}"
                      data-pc-topup-variant="from-source"
                      id="setorCashForm"
                      enctype="multipart/form-data"
                      hidden>
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    <input type="hidden" name="fund_source_id" id="setorCashFundSourceId">
                    {{-- Flag: tandai BUKAN top up. Saldo Cash bertambah,
                         tapi tidak masuk angka "Total Top Up". --}}
                    <input type="hidden" name="is_topup" value="0">

                    <div class="pc-tab-hint">
                        <i class="fa fa-piggy-bank" style="color:#16a34a;"></i>
                        <span>
                            <strong>Setor Cash Langsung:</strong>
                            Catat uang cash yang langsung masuk ke kas (bukan dari Bank / E-Wallet).
                            Saldo Cash bertambah namun <strong>tidak dihitung sebagai Top Up</strong>.
                        </span>
                    </div>

                    <div class="pc-form-row">
                        <label>Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                    </div>

                    <div class="pc-form-row">
                        <label>Nominal (Rp) <span class="text-danger">*</span></label>
                        <input type="text" inputmode="numeric"
                               class="pc-input pc-amount-input"
                               data-target="setorCashAmount"
                               placeholder="0">
                        <input type="hidden" name="amount" id="setorCashAmount" required>
                    </div>

                    <div class="pc-form-row">
                        <label>Keterangan <small class="pc-muted">(opsional)</small></label>
                        <input type="text" name="description" maxlength="200"
                               placeholder="Mis: kembalian kasbon karyawan, koreksi cash"
                               class="pc-input">
                    </div>

                    {{-- Bukti setor cash (struk, foto uang). Opsional. --}}
                    <div class="pc-form-row">
                        <label>Bukti setoran <small class="pc-muted">(opsional)</small></label>
                        <div class="pc-attach-zone">
                            <input type="file" name="attachment"
                                   id="setorCashAttachInput"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                                   class="pc-attach-input">
                            <label for="setorCashAttachInput" class="pc-attach-label">
                                <i class="fa fa-camera"></i>
                                <span class="pc-attach-label__main">Pilih foto / PDF</span>
                                <span class="pc-attach-label__hint">struk / nota · max 5MB</span>
                            </label>
                            <div class="pc-attach-preview" hidden>
                                <div class="pc-attach-preview__thumb"><i class="fa fa-file"></i></div>
                                <div class="pc-attach-preview__meta">
                                    <div class="pc-attach-preview__name">—</div>
                                    <div class="pc-attach-preview__size">—</div>
                                </div>
                                <button type="button" class="pc-attach-preview__remove" title="Hapus file">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pc-form-actions">
                        <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                        <button type="submit" class="pc-btn-topup-solid" id="setorCashSubmitBtn" disabled>
                            <i class="fa fa-piggy-bank"></i> Setor Cash
                        </button>
                    </div>
                </form>
            </div>

            {{-- ─── FORM PENGELUARAN DARI SOURCE INI ─── --}}
            <form method="POST" action="{{ route('pettycash.expense', ['tab' => $kind]) }}"
                  data-tab-content="expense" class="pc-tab-content"
                  enctype="multipart/form-data"
                  hidden>
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

                @php
                    // Daftar kategori per modul. modal-transfer shared antara
                    // petty_cash & transport — masing-masing punya enum berbeda.
                    $expenseCategories = $kind === 'transport'
                        ? \App\Models\TransportCostTransaction::CATEGORIES
                        : \App\Models\PettyCashTransaction::CATEGORIES;
                @endphp

                <div class="pc-form-row">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" value="{{ date('Y-m-d') }}" required class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <select name="category" required class="pc-input">
                        <option value="">— Pilih kategori —</option>
                        @foreach($expenseCategories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
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

                {{-- Upload bukti pengeluaran (struk/bon/transfer). Opsional.
                     Accept jpg/png/webp/pdf, max 5MB (validasi server-side juga). --}}
                <div class="pc-form-row">
                    <label>Bukti pengeluaran <small class="pc-muted">(opsional)</small></label>
                    <div class="pc-attach-zone" id="expenseAttachZone">
                        <input type="file" name="attachment"
                               id="expenseAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="expenseAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">struk atau bukti transfer · max 5MB</span>
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
                 fund source.
                 ACCESS: hanya Super Admin & Admin General. Role lain dapat versi
                 locked (button disabled + form onsubmit return false). --}}
            @php $canHardDelete = isSuperAdmin() || isAdminGeneral(); @endphp
            <div class="pc-source-danger {{ $canHardDelete ? '' : 'pc-source-danger--locked' }}">
                <hr class="pc-source-danger__divider">
                @if($canHardDelete)
                    <div class="pc-source-danger__warn">
                        <i class="fa fa-triangle-exclamation"></i>
                        <span>Hapus semua transaksi &amp; saldo awal wallet ini di periode aktif. <strong>Aksi tidak bisa di-undo.</strong></span>
                    </div>
                @else
                    <div class="pc-source-danger__warn">
                        <i class="fa fa-lock"></i>
                        <span>Fitur hapus data wallet <strong>sementara dikunci</strong> untuk role Anda. Hubungi Super Admin / Admin General jika perlu reset data.</span>
                    </div>
                @endif
                <form method="POST" action="{{ route('pettycash.source.destroy', ['tab' => $kind]) }}"
                      id="pcSourceDestroyForm"
                      @if($canHardDelete)
                          onsubmit="return confirm('Yakin hapus SEMUA data wallet ini di periode aktif?\n\n• Saldo awal wallet ini akan di-reset\n• Semua transaksi (cash in, cash out, transfer) yg pakai wallet ini akan dihapus\n• Aksi TIDAK bisa di-undo.\n\nLanjut?');"
                      @else
                          onsubmit="return false;"
                      @endif>
                    @csrf
                    <input type="hidden" name="period_id" value="{{ $period->id }}">
                    <input type="hidden" name="tab" value="{{ $kind }}">
                    @if(!session('raw_branch_id') && $period->raw_branch_id)
                        <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
                    @endif
                    <input type="hidden" name="fund_source_id" id="pcSourceDestroyId">
                    <button type="submit" class="pc-source-danger__btn"
                            @if(!$canHardDelete) disabled title="Fitur dikunci untuk role Anda" @endif>
                        @if($canHardDelete)
                            <i class="fa fa-trash"></i> Hapus Data Wallet Ini
                        @else
                            <i class="fa fa-lock"></i> Hapus Data Wallet Ini
                        @endif
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
/* Locked state — fitur dinonaktifkan sementara */
.pc-source-danger--locked .pc-source-danger__warn {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}
.pc-source-danger--locked .pc-source-danger__warn i,
.pc-source-danger--locked .pc-source-danger__warn strong {
    color: #475569;
}
.pc-source-danger__btn:disabled {
    background: #f1f5f9;
    color: #94a3b8;
    border-color: #cbd5e1;
    cursor: not-allowed;
    opacity: 0.85;
}
.pc-source-danger__btn:disabled:hover {
    background: #f1f5f9;
    color: #94a3b8;
}
</style>
