@php
    $pillar   = $pillar   ?? null;
    $nextSort = $nextSort ?? 0;
@endphp

<div class="st-card mb-3">
    <div class="st-card__head">
        <div class="st-card__title-wrap">
            <span class="st-card__icon"><i class="fa fa-tags"></i></span>
            <h4 class="st-card__title">Detail Pillar</h4>
        </div>
    </div>
    <div class="st-card__body">
        <div class="row">
            <div class="col-md-6 form-group">
                <label>Nama Pillar <span class="text-danger">*</span></label>
                <input type="text" name="nama" class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                       value="{{ old('nama', $pillar->nama ?? '') }}"
                       placeholder="Mis. Promo, Testimoni, Awareness Beauty" required>
                @if($errors->has('nama'))<div class="invalid-feedback">{{ $errors->first('nama') }}</div>@endif
                <small class="text-muted">Slug akan di-generate otomatis dari nama (lowercase + dash).</small>
            </div>
            <div class="col-md-3 form-group">
                <label>Urutan <span class="text-danger">*</span></label>
                <input type="number" name="sort_order" min="0" class="form-control"
                       value="{{ old('sort_order', $pillar->sort_order ?? $nextSort) }}" required>
                <small class="text-muted">Urutan tampil (smaller = first).</small>
            </div>
            <div class="col-md-3 form-group">
                <label>Aktif</label>
                <div class="custom-control custom-switch mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="custom-control-input" id="isActive" name="is_active" value="1"
                        {{ old('is_active', $pillar->is_active ?? 1) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="isActive">Tampilkan di pilihan EP</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Deskripsi</label>
            <input type="text" name="deskripsi" class="form-control"
                   value="{{ old('deskripsi', $pillar->deskripsi ?? '') }}"
                   placeholder="Mis. Konten yang membahas insight industri & sains gigi">
        </div>
        <div class="row">
            <div class="col-md-4 form-group mb-0">
                <label>Warna (opsional)</label>
                <div class="input-group">
                    <input type="text" name="color" class="form-control"
                           value="{{ old('color', $pillar->color ?? '') }}"
                           placeholder="#c4683f" pattern="^#[0-9A-Fa-f]{6}$"
                           id="colorInput">
                    <div class="input-group-append">
                        <span class="input-group-text" id="colorSwatch"
                              style="width:40px; background:{{ old('color', $pillar->color ?? '#ffffff') }};"></span>
                    </div>
                </div>
                <small class="text-muted">Optional — untuk pill colored di EP. Format hex (#xxxxxx).</small>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const inp = document.getElementById('colorInput');
        const sw  = document.getElementById('colorSwatch');
        if (!inp || !sw) return;
        inp.addEventListener('input', function () {
            const v = inp.value.trim();
            if (/^#[0-9A-Fa-f]{6}$/.test(v)) sw.style.background = v;
        });
    })();
</script>
