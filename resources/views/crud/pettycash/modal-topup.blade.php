{{--
    Petty Cash / Transport — modal top up saldo (cash in, is_topup=1).
    Variables: $period, $summary, $kind.

    Saran nominal otomatis = float_amount - saldo sekarang (kembalikan ke imprest).
--}}
@php
    $suggested          = (int) round($summary['suggested_topup']);
    $kind               = $kind ?? 'petty_cash';
    $fundSourcesGrouped = $fundSourcesGrouped ?? collect();
    // Map source_id → account_number untuk cabang aktif. Dipakai untuk
    // pre-fill input nomor saat user pilih source yang sudah pernah diisi.
    $branchAccountMap   = $branchAccountMap ?? [];
    $categoryLabels     = [
        \App\Models\FundSource::CAT_CASH    => 'Cash / Tunai',
        \App\Models\FundSource::CAT_BANK    => 'Bank',
        \App\Models\FundSource::CAT_EWALLET => 'E-Wallet',
    ];
@endphp
<div class="pc-modal" id="pcModalTopup" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.topup', ['tab' => $kind]) }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period->id }}">
            <input type="hidden" name="tab" value="{{ $kind }}">
            @if(!session('raw_branch_id') && $period->raw_branch_id)
                <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
            @endif

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-arrow-down-up-across-line" style="color:#16a34a;"></i> Top up Saldo
                    </h5>
                    <small class="pc-muted">Isi kembali kas kecil</small>
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
                    <select name="fund_source_id" required class="pc-input pc-source-select"
                            data-pc-topup-source>
                        <option value="">— Pilih wallet —</option>
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

                {{-- Input nomor rekening / telepon — toggled by JS berdasarkan
                     kategori sumber dana yang dipilih. Bank → no rekening,
                     E-Wallet → no telepon, Cash → hidden. Opsional. --}}
                <div class="pc-form-row" data-pc-topup-account-row hidden>
                    <label data-pc-topup-account-label>No. Rekening <small class="pc-muted">(opsional)</small></label>
                    <input type="text" name="account_number" maxlength="50"
                           class="pc-input"
                           data-pc-topup-account-input
                           placeholder="Mis: 1234567890">
                    <small class="pc-muted" data-pc-topup-account-hint>
                        Disimpan di wallet ini dan muncul di card rincian saldo.
                    </small>
                </div>

                {{-- Upload bukti top up (struk transfer dari HQ, screenshot WA, dll). Opsional. --}}
                <div class="pc-form-row">
                    <label>Bukti top up <small class="pc-muted">(opsional)</small></label>
                    <div class="pc-attach-zone" id="topupAttachZone">
                        <input type="file" name="attachment"
                               id="topupAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="topupAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">bukti transfer / screenshot · max 5MB</span>
                        </label>
                        <div class="pc-attach-preview" id="topupAttachPreview" hidden>
                            <div class="pc-attach-preview__thumb" id="topupAttachThumb">
                                <i class="fa fa-file"></i>
                            </div>
                            <div class="pc-attach-preview__meta">
                                <div class="pc-attach-preview__name" id="topupAttachName">—</div>
                                <div class="pc-attach-preview__size" id="topupAttachSize">—</div>
                            </div>
                            <button type="button" class="pc-attach-preview__remove"
                                    id="topupAttachRemove"
                                    title="Hapus file">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
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
