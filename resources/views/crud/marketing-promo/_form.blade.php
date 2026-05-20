{{--
    Shared form fields untuk Marketing Promo (new + edit).
    Variables: $promo (?MarketingPromo), $branches, $campaigns, $statuses, $selectedBranchIds (array)
--}}
@php
    $promo             = $promo ?? null;
    $selectedBranchIds = $selectedBranchIds ?? [];
@endphp

{{-- ───── Card 1: Informasi Promo ───── --}}
<div class="st-card mb-3">
    <div class="st-card__head">
        <div class="st-card__title-wrap">
            <span class="st-card__icon"><i class="fa fa-tag"></i></span>
            <h4 class="st-card__title">Informasi Promo</h4>
        </div>
    </div>
    <div class="st-card__body">
        <div class="row">
            <div class="col-md-8 form-group">
                <label>Nama Promo <span class="text-danger">*</span></label>
                <input type="text" name="nama_promo"
                       class="form-control {{ $errors->has('nama_promo') ? 'is-invalid' : '' }}"
                       value="{{ old('nama_promo', $promo->nama_promo ?? '') }}"
                       placeholder="Mis. Stellar Metal GP" required>
                @if ($errors->has('nama_promo'))
                    <div class="invalid-feedback">{{ $errors->first('nama_promo') }}</div>
                @endif
            </div>
            <div class="col-md-4 form-group">
                <label>Campaign <span class="text-danger">*</span></label>
                <select name="campaign_type" class="form-control" required>
                    @foreach ($campaigns as $key => $label)
                        <option value="{{ $key }}" {{ old('campaign_type', $promo->campaign_type ?? '') == $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Detail Promo</label>
            <textarea name="detail_promo" class="form-control" rows="2"
                      placeholder="Mis. include cetak model gigi, dan pemasangan ortho atas bawah">{{ old('detail_promo', $promo->detail_promo ?? '') }}</textarea>
        </div>

        <div class="form-group mb-0">
            <label>Syarat &amp; Ketentuan</label>
            <textarea name="syarat_ketentuan" class="form-control" rows="2"
                      placeholder="Mis. di kerjakan drg umum dan untuk 10 org tercepat">{{ old('syarat_ketentuan', $promo->syarat_ketentuan ?? '') }}</textarea>
        </div>
    </div>
</div>

{{-- ───── Card 2: Periode & Distribusi ───── --}}
<div class="st-card mb-3">
    <div class="st-card__head">
        <div class="st-card__title-wrap">
            <span class="st-card__icon"><i class="fa fa-calendar-alt"></i></span>
            <h4 class="st-card__title">Periode &amp; Distribusi</h4>
        </div>
    </div>
    <div class="st-card__body">
        <div class="row">
            <div class="col-md-4 form-group">
                <label>Periode Mulai</label>
                <input type="date" name="periode_start" class="form-control"
                       value="{{ old('periode_start', $promo?->periode_start?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Periode Selesai</label>
                <input type="date" name="periode_end" class="form-control"
                       value="{{ old('periode_end', $promo?->periode_end?->format('Y-m-d')) }}">
            </div>
            <div class="col-md-4 form-group">
                <label>Status <span class="text-danger">*</span></label>
                <select name="status" class="form-control" required>
                    @foreach ($statuses as $key => $opt)
                        <option value="{{ $key }}" {{ old('status', $promo->status ?? 'planning') == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Cabang <small class="text-muted">— kosongkan = berlaku di SEMUA cabang</small></label>
            <select name="branch_ids[]" class="select2 form-control" multiple data-placeholder="Pilih cabang...">
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}"
                        {{ in_array($b->id, old('branch_ids', $selectedBranchIds)) ? 'selected' : '' }}>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="row">
            <div class="col-md-8 form-group mb-0">
                <label>Cetakan</label>
                <input type="text" name="cetakan" class="form-control"
                       value="{{ old('cetakan', $promo->cetakan ?? '') }}"
                       placeholder="Mis. BANNER STANDING / FLAYER 50 MASING2 CABANG">
            </div>
            <div class="col-md-4 form-group mb-0">
                <label>Aktifkan</label>
                <div class="custom-control custom-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="custom-control-input" id="isActive" name="is_active" value="1"
                        {{ old('is_active', $promo->is_active ?? 1) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="isActive">Promo aktif</label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ───── Card 3: Harga & Margin ───── --}}
<div class="st-card mb-3">
    <div class="st-card__head">
        <div class="st-card__title-wrap">
            <span class="st-card__icon"><i class="fa fa-calculator"></i></span>
            <h4 class="st-card__title">Harga &amp; Margin</h4>
        </div>
    </div>
    <div class="st-card__body">
        <div class="row">
            <div class="col-md-3 form-group">
                <label>Harga Normal</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" step="0.01" min="0" name="harga_normal" class="form-control st-tnum"
                           value="{{ old('harga_normal', $promo->harga_normal ?? '') }}">
                </div>
            </div>
            <div class="col-md-3 form-group">
                <label>Harga Promo</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" step="0.01" min="0" name="harga_promo" id="hargaPromo" class="form-control st-tnum"
                           value="{{ old('harga_promo', $promo->harga_promo ?? '') }}">
                </div>
            </div>
            <div class="col-md-3 form-group">
                <label>COGS + Fee Dokter</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" step="0.01" min="0" name="cogs_fee_dokter" id="cogsFeeDokter" class="form-control st-tnum"
                           value="{{ old('cogs_fee_dokter', $promo->cogs_fee_dokter ?? '') }}">
                </div>
            </div>
            <div class="col-md-3 form-group">
                <label>Voucher Tambahan Pasien</label>
                <div class="input-group">
                    <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                    <input type="number" step="0.01" min="0" name="voucher_tambahan_pasien" class="form-control st-tnum"
                           value="{{ old('voucher_tambahan_pasien', $promo->voucher_tambahan_pasien ?? '') }}">
                </div>
            </div>
        </div>

        {{-- Margin display panel --}}
        <div class="row mt-2">
            <div class="col-12">
                <div id="marginPanel" class="st-margin-panel">
                    <div class="st-margin-panel__item">
                        <span class="st-margin-panel__label">Margin (Rp)</span>
                        <span class="st-margin-panel__value" id="marginRpDisplay">
                            {{ $promo?->margin_rp !== null ? 'Rp ' . number_format($promo->margin_rp, 0, ',', '.') : '—' }}
                        </span>
                    </div>
                    <div class="st-margin-panel__divider"></div>
                    <div class="st-margin-panel__item">
                        <span class="st-margin-panel__label">Margin (%)</span>
                        <span class="st-margin-panel__value" id="marginPctDisplay">
                            {{ $promo?->margin_pct !== null ? number_format($promo->margin_pct, 2) . '%' : '—' }}
                        </span>
                    </div>
                </div>
                <small class="text-muted d-block mt-1">
                    <i class="fa fa-info-circle"></i> Margin dihitung otomatis dari Harga Promo − COGS &amp; Fee Dokter.
                </small>
            </div>
        </div>
    </div>
</div>

<style>
    .st-margin-panel {
        display: flex;
        align-items: stretch;
        background: linear-gradient(135deg, #f9f4ee, #f1e7d8);
        border: 1px solid #e3d4be;
        border-radius: 8px;
        padding: 14px 20px;
        gap: 24px;
    }
    .st-margin-panel__item {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .st-margin-panel__label {
        font-size: 12px;
        font-weight: 600;
        color: #8a6d4a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .st-margin-panel__value {
        font-size: 22px;
        font-weight: 700;
        color: #461500;
        font-variant-numeric: tabular-nums;
    }
    .st-margin-panel__value.is-negative { color: #c1272d; }
    .st-margin-panel__divider {
        width: 1px;
        background: #d9c8aa;
    }
</style>

<script>
    (function () {
        const hargaPromo = document.getElementById('hargaPromo');
        const cogs       = document.getElementById('cogsFeeDokter');
        const dispRp     = document.getElementById('marginRpDisplay');
        const dispPct    = document.getElementById('marginPctDisplay');
        if (!hargaPromo || !cogs || !dispRp || !dispPct) return;

        function fmtRp(n) {
            const sign = n < 0 ? '-' : '';
            return sign + 'Rp ' + Math.abs(Math.round(n)).toLocaleString('id-ID');
        }
        function recalc() {
            const hp = parseFloat(hargaPromo.value) || 0;
            const cf = parseFloat(cogs.value) || 0;
            if (hp <= 0) { dispRp.textContent = '—'; dispPct.textContent = '—'; toggleNeg(false); return; }
            const m   = hp - cf;
            const pct = (m / hp) * 100;
            dispRp.textContent  = fmtRp(m);
            dispPct.textContent = pct.toFixed(2) + '%';
            toggleNeg(m < 0);
        }
        function toggleNeg(isNeg) {
            dispRp.classList.toggle('is-negative', isNeg);
            dispPct.classList.toggle('is-negative', isNeg);
        }
        hargaPromo.addEventListener('input', recalc);
        cogs.addEventListener('input', recalc);
    })();
</script>
