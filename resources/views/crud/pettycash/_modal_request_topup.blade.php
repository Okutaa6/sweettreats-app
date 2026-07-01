{{--
    Modal: Ajukan Top Up (Sales → Finance).
    Variables: $fundSourcesGrouped (collection groupBy category), $period (PettyCashPeriod aktif)

    Bukan top-up langsung — submit ke endpoint requestTopup yang insert
    ke tabel topup_requests (status pending) lalu notify Finance.
--}}
@php
    $fundSourcesGrouped = $fundSourcesGrouped ?? collect();
    $branchId           = $branchId ?? null;
    $activeBranch       = $activeBranch ?? null;
    // Hanya Bank — top-up dari Finance ke cabang selalu via transfer bank.
    // Cash & E-Wallet tidak ditampilkan untuk pengajuan.
    $categoryLabels     = [
        \App\Models\FundSource::CAT_BANK => 'Bank',
    ];
@endphp
<div class="pc-modal" id="pcModalRequestTopup" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('pettycash.requestTopup') }}">
            @csrf
            {{-- Admin (canSeeAllBranches) tidak punya raw_branch_id di session,
                 jadi kirim branch_id eksplisit dari halaman aktif. Sales tetap
                 di-validate via session di controller. --}}
            <input type="hidden" name="branch_id" value="{{ $branchId }}">

            <div class="pc-modal__header pc-modal__header--wallet">
                <span class="pc-modal__icon-box" aria-hidden="true">
                    <i class="fa fa-paper-plane"></i>
                </span>
                <div class="pc-modal__heading">
                    <h5 class="pc-modal__title">Ajukan Top Up</h5>
                    <small class="pc-muted">
                        Kirim permintaan ke Finance
                        @if($activeBranch)
                            &middot; cabang <strong>{{ $activeBranch->name }}</strong>
                        @endif
                    </small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-tab-hint pc-tab-hint--deposit">
                    <span>Form ini mengirim pengajuan ke Finance. Setelah disetujui, top-up otomatis tercatat di kas cabang ini.</span>
                </div>

                <div class="pc-form-row">
                    <label>Wallet tujuan</label>
                    <select name="fund_source_id" required class="pc-input">
                        <option value="">Pilih wallet…</option>
                        @foreach($categoryLabels as $catKey => $catLabel)
                            @if($fundSourcesGrouped->has($catKey))
                                <optgroup label="{{ $catLabel }}">
                                    @foreach($fundSourcesGrouped[$catKey] as $fs)
                                        <option value="{{ $fs->id }}">{{ $fs->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    <small class="pc-muted">Wallet yang ingin di-top-up. Finance bisa mengubah saat approval.</small>
                </div>

                <div class="pc-form-row">
                    <label>Nominal yang diajukan</label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           data-target="requestTopupAmount"
                           placeholder="Rp 0">
                    <input type="hidden" name="amount" id="requestTopupAmount" required>
                    <small class="pc-muted">Finance mungkin menyetujui dalam nominal berbeda.</small>
                </div>

                <div class="pc-form-row">
                    <label>Alasan / keterangan <small class="pc-muted">— opsional</small></label>
                    <textarea name="description" rows="2" maxlength="255"
                              placeholder="Contoh: kas hampir habis, banyak pesanan"
                              class="pc-input"></textarea>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-paper-plane"></i> Kirim Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>
