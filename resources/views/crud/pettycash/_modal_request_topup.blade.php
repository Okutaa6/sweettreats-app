{{--
    Modal: Ajukan Top Up (Sales → Finance) — bergaya form "Pengajuan" ROP.
    Variables:
      $fundSourcesGrouped (collection groupBy category)
      $branchId, $activeBranch
      $branchAccountMap   (array: fund_source_id => no. rekening cabang ini)
      $requesterName      (prefill nama pemohon, boleh diedit)

    Bukan top-up langsung — submit ke endpoint requestTopup yang insert
    ke tabel topup_requests (status pending) lalu notify Finance.
--}}
@php
    $fundSourcesGrouped = $fundSourcesGrouped ?? collect();
    $branchId           = $branchId ?? null;
    $activeBranch       = $activeBranch ?? null;
    $branchAccountMap   = $branchAccountMap ?? [];
    $requesterName      = $requesterName ?? '';
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
                    <small class="pc-muted rtu-num">
                        Kirim permintaan ke Finance
                        @if($activeBranch)
                            &middot; cabang <strong>{{ $activeBranch->name }}</strong>
                        @endif
                    </small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body rtu-body">
                <div class="pc-tab-hint pc-tab-hint--deposit">
                    <span>Form ini mengirim pengajuan ke Finance. Setelah disetujui, top-up otomatis tercatat di kas cabang ini.</span>
                </div>

                {{-- Nama pemohon — prefilled dari user login, boleh diedit --}}
                <div class="rtu-field">
                    <label class="rtu-label" for="rtuNama">Nama pemohon</label>
                    <input type="text" class="rtu-input" id="rtuNama" name="requester_name"
                           value="{{ old('requester_name', $requesterName) }}"
                           maxlength="255" autocomplete="off"
                           placeholder="Nama yang mengajukan">
                </div>

                {{-- Wallet tujuan — dropdown; pilih wallet → no. rekening muncul --}}
                <div class="rtu-field">
                    <label class="rtu-label" for="rtuWallet">Wallet tujuan</label>
                    <select name="fund_source_id" id="rtuWallet" required class="rtu-select" data-rtu-wallet>
                        <option value="">Pilih wallet…</option>
                        @foreach($categoryLabels as $catKey => $catLabel)
                            @if($fundSourcesGrouped->has($catKey))
                                <optgroup label="{{ $catLabel }}">
                                    @foreach($fundSourcesGrouped[$catKey] as $fs)
                                        <option value="{{ $fs->id }}"
                                                data-account="{{ $branchAccountMap[$fs->id] ?? '' }}">
                                            {{ $fs->name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>
                    {{-- No. rekening tujuan — terisi via JS saat wallet dipilih --}}
                    <div class="rtu-norek" data-rtu-norek hidden>
                        <i class="fa fa-credit-card"></i>
                        <span class="rtu-norek__label">No. Rek</span>
                        <span class="rtu-norek__num" data-rtu-norek-num></span>
                        <button type="button" class="rtu-copy" data-rtu-copy title="Salin nomor rekening" aria-label="Salin nomor rekening">
                            <i class="fa fa-copy"></i>
                        </button>
                    </div>
                    <div class="rtu-hint">Finance bisa mengubah wallet saat approval.</div>
                </div>

                {{-- Nominal — input rupiah gaya pengajuan (tanpa chip cepat) --}}
                <div class="rtu-field">
                    <label class="rtu-label" for="rtuNominal">Nominal yang diajukan</label>
                    <div class="rtu-uang">
                        <span>Rp</span>
                        <input type="text" inputmode="numeric" id="rtuNominal"
                               class="pc-amount-input" data-target="requestTopupAmount"
                               placeholder="0" autocomplete="off">
                    </div>
                    <input type="hidden" name="amount" id="requestTopupAmount" required>
                    <div class="rtu-hint">Finance mungkin menyetujui dalam nominal berbeda.</div>
                </div>

                {{-- Butuh kapan — chip preset + date --}}
                <div class="rtu-field">
                    <label class="rtu-label" for="rtuNeededBy">Butuh kapan</label>
                    <div class="rtu-chip-row">
                        <button type="button" class="rtu-chip" data-rtu-hari="0">Hari ini</button>
                        <button type="button" class="rtu-chip" data-rtu-hari="1">Besok</button>
                        <button type="button" class="rtu-chip" data-rtu-hari="3">3 hari</button>
                        <button type="button" class="rtu-chip" data-rtu-hari="7">1 minggu</button>
                    </div>
                    <input type="date" class="rtu-input" id="rtuNeededBy" name="needed_by"
                           value="{{ old('needed_by') }}">
                </div>

                {{-- Keterangan / Alasan --}}
                <div class="rtu-field">
                    <label class="rtu-label" for="rtuKet">Keterangan / Alasan <small class="pc-muted">— opsional</small></label>
                    <textarea class="rtu-textarea" id="rtuKet" name="description" rows="2" maxlength="255"
                              placeholder="Contoh: kas hampir habis, banyak pesanan">{{ old('description') }}</textarea>
                </div>

                {{-- Darurat — flag prioritas untuk Finance --}}
                <label class="rtu-toggle" for="rtuUrgent">
                    <input type="checkbox" id="rtuUrgent" name="is_urgent" value="1" {{ old('is_urgent') ? 'checked' : '' }}>
                    <span>
                        <b>Darurat</b>
                        <span class="rtu-hint">Pakai seperlunya — kalau semua darurat, tidak ada yang darurat.</span>
                    </span>
                </label>
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

<style>
/* ── Modal "Ajukan Top Up" — meniru format form Pengajuan (rop.sweettreats) ── */
#pcModalRequestTopup .rtu-num { font-variant-numeric: tabular-nums; }
#pcModalRequestTopup .rtu-body { display: block; }
#pcModalRequestTopup .rtu-field { margin-bottom: 14px; }
#pcModalRequestTopup .rtu-label {
    display: block; margin-bottom: 7px;
    font-size: 11.5px; font-weight: 800; letter-spacing: .06em;
    text-transform: uppercase; color: #9A7256;
}
#pcModalRequestTopup .rtu-input,
#pcModalRequestTopup .rtu-select,
#pcModalRequestTopup .rtu-textarea {
    width: 100%; min-height: 46px; padding: 12px 14px;
    background: #fff; border: 1px solid #EFE0CE; border-radius: 12px;
    font-family: inherit; font-size: 15px; color: #3B1500;
}
#pcModalRequestTopup .rtu-textarea { min-height: 84px; resize: vertical; }
#pcModalRequestTopup .rtu-input:focus,
#pcModalRequestTopup .rtu-select:focus,
#pcModalRequestTopup .rtu-textarea:focus {
    outline: none; border-color: #8A5A2B; box-shadow: 0 0 0 3px rgba(138,90,43,.12);
}
#pcModalRequestTopup .rtu-hint { margin-top: 6px; font-size: 12.5px; color: #9A7256; }

/* Nominal — input rupiah besar (gaya rop-uang) */
#pcModalRequestTopup .rtu-uang {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px; background: #fff;
    border: 1px solid #EFE0CE; border-radius: 14px;
}
#pcModalRequestTopup .rtu-uang span { font-size: 19px; font-weight: 700; color: #9A7256; }
#pcModalRequestTopup .rtu-uang input {
    flex: 1; min-width: 0; border: 0; outline: none; background: transparent;
    font-family: inherit; font-size: 26px; font-weight: 800; color: #3B1500;
    font-variant-numeric: tabular-nums;
}

/* Chip preset (butuh kapan) */
#pcModalRequestTopup .rtu-chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
#pcModalRequestTopup .rtu-chip {
    display: inline-flex; align-items: center; gap: 6px;
    min-height: 36px; padding: 7px 14px;
    background: #fff; border: 1px solid #EFE0CE; border-radius: 20px;
    font-family: inherit; font-size: 13px; font-weight: 600; color: #3B1500;
    cursor: pointer; white-space: nowrap;
}
#pcModalRequestTopup .rtu-chip:hover { background: #FFFDFA; }
#pcModalRequestTopup .rtu-chip--pilih { border-color: #C9962F; background: #FDF6E7; }

/* No. rekening tujuan — chip cream + tombol salin */
#pcModalRequestTopup .rtu-norek {
    display: flex; align-items: center; gap: 8px;
    margin-top: 8px; padding: 8px 12px;
    background: #fdf9f3; border: 1px solid #ead9c1; border-radius: 10px;
    font-size: 13px; color: #5c1f00;
}
#pcModalRequestTopup .rtu-norek > i { font-size: 11px; color: #A8481B; }
#pcModalRequestTopup .rtu-norek__label { font-weight: 700; color: #9A7256; }
#pcModalRequestTopup .rtu-norek__num {
    font-family: ui-monospace, Menlo, Consolas, monospace;
    font-weight: 700; letter-spacing: .3px; color: #5c1f00;
}
#pcModalRequestTopup .rtu-norek--missing {
    background: #fef3c7; border-color: #fde68a; color: #92400e;
}
#pcModalRequestTopup .rtu-norek--missing > i { color: #d97706; }
#pcModalRequestTopup .rtu-copy {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; margin-left: auto;
    background: #fff; color: #A8481B; border: 1px solid #ead9c1; border-radius: 50%;
    cursor: pointer; transition: background .15s, color .15s;
}
#pcModalRequestTopup .rtu-copy:hover { background: #6B2400; color: #fff; border-color: #6B2400; }
#pcModalRequestTopup .rtu-copy.is-copied { background: #16a34a; color: #fff; border-color: #16a34a; }
#pcModalRequestTopup .rtu-copy i { font-size: 10px; }

/* Darurat toggle */
#pcModalRequestTopup .rtu-toggle {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px; margin-bottom: 4px;
    border: 1px solid #EFE0CE; border-radius: 12px; background: #fff; cursor: pointer;
}
#pcModalRequestTopup .rtu-toggle input { margin-top: 2px; width: 18px; height: 18px; accent-color: #6B2400; flex-shrink: 0; }
#pcModalRequestTopup .rtu-toggle b { display: block; color: #3B1500; font-size: 14px; }
#pcModalRequestTopup .rtu-toggle .rtu-hint { margin-top: 2px; }
</style>

<script>
(function () {
    var modal = document.getElementById('pcModalRequestTopup');
    if (!modal || modal.dataset.rtuInit) return;
    modal.dataset.rtuInit = '1';

    // Wallet dipilih → tampilkan no. rekening cabang untuk wallet itu.
    var sel   = modal.querySelector('[data-rtu-wallet]');
    var norek = modal.querySelector('[data-rtu-norek]');
    var numEl = modal.querySelector('[data-rtu-norek-num]');
    var copyBtn = modal.querySelector('[data-rtu-copy]');
    if (sel && norek && numEl) {
        function syncNorek() {
            var opt = sel.options[sel.selectedIndex];
            var acct = (opt && opt.value) ? (opt.dataset.account || '') : '';
            if (!opt || !opt.value) {
                norek.hidden = true;
                return;
            }
            norek.hidden = false;
            if (acct) {
                norek.classList.remove('rtu-norek--missing');
                numEl.textContent = acct;
                if (copyBtn) { copyBtn.hidden = false; copyBtn.dataset.copy = acct; }
            } else {
                norek.classList.add('rtu-norek--missing');
                numEl.textContent = 'No. rek belum diisi untuk cabang ini';
                if (copyBtn) copyBtn.hidden = true;
            }
        }
        sel.addEventListener('change', syncNorek);
        syncNorek();
    }

    // Salin nomor rekening.
    if (copyBtn) {
        copyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var text = copyBtn.dataset.copy || '';
            if (!text) return;
            function flash() {
                var original = copyBtn.innerHTML;
                copyBtn.classList.add('is-copied');
                copyBtn.innerHTML = '<i class="fa fa-check"></i>';
                setTimeout(function () { copyBtn.classList.remove('is-copied'); copyBtn.innerHTML = original; }, 1200);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(flash);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); flash(); } catch (_) {}
                document.body.removeChild(ta);
            }
        });
    }

    // Chip "Butuh kapan" → set tanggal (hari ini + N) & tandai chip aktif.
    var dateInput = modal.querySelector('#rtuNeededBy');
    var chips = modal.querySelectorAll('[data-rtu-hari]');
    chips.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = new Date();
            d.setDate(d.getDate() + parseInt(btn.getAttribute('data-rtu-hari'), 10));
            var iso = d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
            if (dateInput) dateInput.value = iso;
            chips.forEach(function (x) { x.classList.remove('rtu-chip--pilih'); });
            btn.classList.add('rtu-chip--pilih');
        });
    });
    // Kalau user pilih tanggal manual, lepas highlight chip.
    if (dateInput) {
        dateInput.addEventListener('input', function () {
            chips.forEach(function (x) { x.classList.remove('rtu-chip--pilih'); });
        });
    }
})();
</script>
