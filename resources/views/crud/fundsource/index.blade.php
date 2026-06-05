@extends('layouts.app')

@section('content')
@php
    $grouped = $sources->groupBy('category');
@endphp
<div class="container-fluid">

    <div class="pc-page-header">
        <div>
            <h4 class="page-title mb-1">Kelola Sumber Dana</h4>
            <div class="pc-page-sub">
                Master sumber dana untuk Petty Cash &amp; Biaya Transport.
                Logo brand, warna, dan label pendek bisa di-customize di sini.
            </div>
        </div>
        <div>
            <a href="{{ route('pettycash') }}" class="st-btn-outline-brown">
                <i class="fa fa-arrow-left"></i> Kembali ke Petty Cash
            </a>
            <button type="button" class="st-btn-brand" data-pc-modal="fsModalCreate">
                <i class="fa fa-plus"></i> Tambah Sumber Dana
            </button>
        </div>
    </div>

    @include('layouts.notif')

    @foreach(['cash', 'bank', 'ewallet'] as $cat)
        @if($grouped->has($cat))
            <div class="fs-section">
                <h5 class="fs-section__title">{{ $categoryLabels[$cat] }}</h5>
                <div class="fs-grid">
                    @foreach($grouped[$cat] as $fs)
                        @php
                            $color = $fs->effectiveBrandColor();
                            $short = $fs->effectiveShortLabel();
                        @endphp
                        <div class="fs-card {{ $fs->is_active ? '' : 'is-inactive' }}">
                            <div class="fs-card__preview">
                                @include('crud.pettycash._fund_source_badge', [
                                    'color' => $color,
                                    'short' => $short,
                                    'logo'  => $fs->logo_url,
                                    'name'  => $fs->name,
                                    'size'  => 'lg',
                                ])
                                <div class="fs-card__title">
                                    <strong>{{ $fs->name }}</strong>
                                    <small>{{ $fs->code }}</small>
                                </div>
                                @if(!$fs->is_active)
                                    <span class="fs-pill fs-pill--inactive">Nonaktif</span>
                                @endif
                            </div>

                            <form method="POST"
                                  action="{{ route('fundsource.update', ['id' => $fs->id]) }}"
                                  enctype="multipart/form-data"
                                  class="fs-card__form">
                                @csrf

                                <div class="fs-form-row">
                                    <label>Nama tampilan</label>
                                    <input type="text" name="name" value="{{ $fs->name }}"
                                           required maxlength="100" class="pc-input">
                                </div>

                                <div class="fs-form-row fs-form-row--inline">
                                    <div>
                                        <label>Warna brand</label>
                                        <input type="color" name="brand_color"
                                               value="{{ $color }}"
                                               class="fs-color-input">
                                    </div>
                                    <div>
                                        <label>Label pendek <small class="pc-muted">(maks 10)</small></label>
                                        <input type="text" name="short_label"
                                               value="{{ $fs->short_label }}"
                                               maxlength="10" placeholder="{{ $short }}"
                                               class="pc-input pc-input--sm">
                                    </div>
                                    <div>
                                        <label>Urutan</label>
                                        <input type="number" name="sort_order"
                                               value="{{ $fs->sort_order }}"
                                               min="0" max="9999"
                                               class="pc-input pc-input--sm">
                                    </div>
                                </div>

                                <div class="fs-form-row">
                                    <label>Logo brand
                                        <small class="pc-muted">(PNG/SVG/WEBP, maks 1 MB)</small>
                                    </label>
                                    <input type="file" name="logo"
                                           accept=".png,.jpg,.jpeg,.webp,.svg"
                                           class="pc-input">
                                    @if($fs->logo_url)
                                        <div class="fs-current-logo">
                                            <span>Logo saat ini:
                                                <code>{{ $fs->logo_url }}</code>
                                            </span>
                                            <label class="fs-remove-logo">
                                                <input type="checkbox" name="remove_logo" value="1">
                                                Hapus logo (kembali ke badge inisial)
                                            </label>
                                        </div>
                                    @endif
                                </div>

                                <div class="fs-card__actions">
                                    <button type="submit" class="st-btn-brand">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                </div>
                            </form>

                            <form method="POST"
                                  action="{{ route('fundsource.toggle', ['id' => $fs->id]) }}"
                                  class="fs-card__toggle"
                                  onsubmit="return confirm('{{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }} sumber dana ini?')">
                                @csrf
                                <button type="submit"
                                        class="{{ $fs->is_active ? 'st-btn-outline-brown' : 'st-btn-brand' }}"
                                        title="{{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                    <i class="fa {{ $fs->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                    {{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- Modal: Tambah sumber dana baru --}}
<div class="pc-modal" id="fsModalCreate" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('fundsource.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-plus" style="color:#13928e;"></i> Tambah Sumber Dana
                    </h5>
                    <small class="pc-muted">Daftarkan bank / e-wallet baru</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-form-row">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <select name="category" required class="pc-input">
                        <option value="">— Pilih kategori —</option>
                        <option value="cash">Cash / Tunai</option>
                        <option value="bank">Bank</option>
                        <option value="ewallet">E-Wallet</option>
                    </select>
                </div>

                <div class="pc-form-row">
                    <label>Code unik <span class="text-danger">*</span>
                        <small class="pc-muted">(huruf kecil, angka, underscore. Mis: bank_jago, ewallet_linkaja)</small>
                    </label>
                    <input type="text" name="code" required maxlength="50"
                           pattern="[a-z0-9_]+"
                           placeholder="bank_jago" class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Nama tampilan <span class="text-danger">*</span></label>
                    <input type="text" name="name" required maxlength="100"
                           placeholder="Bank Jago" class="pc-input">
                </div>

                <div class="pc-form-row fs-form-row--inline">
                    <div>
                        <label>Warna brand</label>
                        <input type="color" name="brand_color" value="#0060af" class="fs-color-input">
                    </div>
                    <div>
                        <label>Label pendek</label>
                        <input type="text" name="short_label" maxlength="10"
                               placeholder="Jago" class="pc-input pc-input--sm">
                    </div>
                    <div>
                        <label>Urutan</label>
                        <input type="number" name="sort_order" value="50"
                               min="0" max="9999" class="pc-input pc-input--sm">
                    </div>
                </div>

                <div class="pc-form-row">
                    <label>Logo brand <small class="pc-muted">(opsional, PNG/SVG, maks 1 MB)</small></label>
                    <input type="file" name="logo"
                           accept=".png,.jpg,.jpeg,.webp,.svg" class="pc-input">
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Tambah Sumber Dana
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('js')
<script>
(function () {
    document.querySelectorAll('[data-pc-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const m = document.getElementById(btn.dataset.pcModal);
            if (m) m.classList.add('is-open');
        });
    });
    document.querySelectorAll('[data-pc-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.pc-modal').classList.remove('is-open');
        });
    });
    document.querySelectorAll('.pc-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) m.classList.remove('is-open');
        });
    });
})();
</script>
@endsection
