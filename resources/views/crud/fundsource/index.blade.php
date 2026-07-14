@extends('layouts.app')

@section('content')
@php
    $grouped       = $sources->groupBy('category');
    $catIcon       = [
        'cash'    => 'fa-money-bill-wave',
        'bank'    => 'fa-university',
        'ewallet' => 'fa-mobile-alt',
    ];
@endphp
<div class="container-fluid">

    <div class="pc-page-header">
        <div>
            <h4 class="page-title mb-1">Kelola Wallet</h4>
            <div class="pc-page-sub">
                Master wallet (Cash, Bank, E-Wallet) untuk Petty Cash.
                Atur logo, warna brand, dan label.
            </div>
        </div>
        <div class="pc-page-header__actions">
            <a href="{{ route('pettycash') }}" class="st-btn-outline-brown">
                <i class="fa fa-arrow-left"></i> Kembali ke Petty Cash
            </a>
            <button type="button" class="st-btn-brand" data-pc-modal="fsModalCreate">
                <i class="fa fa-plus"></i> Tambah Wallet
            </button>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── SECTIONS: Wallet per Kategori ─── --}}
    @foreach(['cash', 'bank', 'ewallet'] as $cat)
        @if($grouped->has($cat))
            <div class="fs-section">
                <div class="fs-section__head">
                    <h5 class="fs-section__title">
                        <i class="fa {{ $catIcon[$cat] ?? 'fa-wallet' }}"></i>
                        {{ $categoryLabels[$cat] }}
                        <span class="fs-section__count">{{ $grouped[$cat]->count() }}</span>
                    </h5>
                </div>
                <div class="fs-grid">
                    @foreach($grouped[$cat] as $fs)
                        @php
                            $color = $fs->effectiveBrandColor();
                            $short = $fs->effectiveShortLabel();
                        @endphp
                        <div class="fs-card {{ $fs->is_active ? '' : 'is-inactive' }}">
                            {{-- Summary kompak: badge + nama + code + status --}}
                            <div class="fs-card__head">
                                @include('crud.pettycash._fund_source_badge', [
                                    'color' => $color,
                                    'short' => $short,
                                    'logo'  => $fs->logo_url,
                                    'name'  => $fs->name,
                                    'size'  => 'md',
                                ])
                                <div class="fs-card__title">
                                    <strong>{{ $fs->name }}</strong>
                                    <small>{{ $fs->code }}</small>
                                </div>
                                @if(!$fs->is_active)
                                    <span class="fs-pill fs-pill--inactive">Nonaktif</span>
                                @endif
                            </div>

                            {{-- Meta singkat (read-only) --}}
                            <div class="fs-card__meta">
                                <span class="fs-card__chip" style="background:{{ $color }}1a; color:{{ $color }};">
                                    <i class="fa fa-circle" style="font-size:6px;"></i> {{ $short }}
                                </span>
                                <span class="fs-card__chip fs-card__chip--muted">Urutan {{ $fs->sort_order }}</span>
                            </div>

                            {{-- Action row: Edit toggle + on/off --}}
                            <div class="fs-card__actions">
                                <button type="button" class="fs-btn fs-btn--edit"
                                        data-fs-toggle-edit
                                        aria-expanded="false">
                                    <i class="fa fa-pen"></i> Edit
                                </button>
                                <form method="POST"
                                      action="{{ route('fundsource.toggle', ['id' => $fs->id]) }}"
                                      class="fs-card__toggle"
                                      onsubmit="return confirm('{{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }} wallet ini?')">
                                    @csrf
                                    <button type="submit"
                                            class="fs-btn {{ $fs->is_active ? 'fs-btn--off' : 'fs-btn--on' }}"
                                            title="{{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="fa {{ $fs->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                        {{ $fs->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>

                            {{-- Edit panel — collapsed default --}}
                            <form method="POST"
                                  action="{{ route('fundsource.update', ['id' => $fs->id]) }}"
                                  enctype="multipart/form-data"
                                  class="fs-card__edit"
                                  hidden>
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

                                <div class="fs-card__edit-actions">
                                    <button type="button" class="st-btn-outline-brown" data-fs-toggle-edit>Batal</button>
                                    <button type="submit" class="st-btn-brand">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- Modal: Tambah wallet baru --}}
<div class="pc-modal" id="fsModalCreate" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" action="{{ route('fundsource.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="pc-modal__header pc-modal__header--wallet">
                <span class="pc-modal__icon-box" aria-hidden="true">
                    <i class="fa fa-plus"></i>
                </span>
                <div class="pc-modal__heading">
                    <h5 class="pc-modal__title">Tambah Wallet</h5>
                    <small class="pc-muted">Daftarkan Cash / Bank / E-Wallet baru</small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
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
                        <input type="color" name="brand_color" value="#461500" class="fs-color-input">
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
                    <i class="fa fa-save"></i> Tambah Wallet
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ─── Inline CSS: section akses + polish layout ─── --}}
<style>
.pc-page-header__actions { display: flex; gap: 8px; }

/* Section header umum — judul + count + hint subtle */
.fs-section { margin-bottom: 28px; }
.fs-section__head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--divider-soft);
}
.fs-section__title {
    margin: 0;
    font-size: 15px;
    font-weight: 800;
    color: var(--brown);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.fs-section__title i { font-size: 14px; color: var(--brown-2); }
.fs-section__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 22px;
    padding: 0 7px;
    border-radius: 11px;
    background: var(--brown);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    margin-left: 4px;
}
.fs-section__hint {
    color: var(--text-muted);
    font-size: 12px;
    line-height: 1.4;
}

/* Compact card grid */
.fs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 12px;
}
.fs-card {
    background: #fff;
    border: 1px solid var(--divider-soft);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    transition: box-shadow .15s, border-color .15s;
}
.fs-card:hover { box-shadow: 0 4px 12px rgba(70,21,0,0.06); border-color: #e7d4c0; }
.fs-card.is-inactive { opacity: 0.7; }

/* Summary head: badge + name/code + status pill */
.fs-card__head {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}
.fs-card__title { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; flex: 1; }
.fs-card__title strong {
    color: var(--brown);
    font-size: 13.5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fs-card__title small {
    color: var(--text-muted);
    font-size: 11px;
    font-family: ui-monospace, Menlo, Consolas, monospace;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.fs-pill {
    flex: 0 0 auto;
    padding: 2px 8px;
    border-radius: 50px;
    font-size: 9.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.fs-pill--inactive { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

/* Meta chips — quick read of brand color + sort */
.fs-card__meta {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.fs-card__chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
}
.fs-card__chip--muted {
    background: var(--cream-header);
    color: var(--text-muted);
    border: 1px solid var(--divider-soft);
}

/* Action row — 2 buttons sejajar */
.fs-card__actions {
    display: flex;
    gap: 8px;
    margin-top: 2px;
}
.fs-card__actions > .fs-card__toggle { margin: 0; flex: 1; }
.fs-card__actions > .fs-card__toggle button { width: 100%; }
.fs-btn {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    font-size: 12px;
    font-weight: 700;
    border-radius: 8px;
    cursor: pointer;
    transition: all .15s;
    border: 1px solid transparent;
    line-height: 1;
}
.fs-btn--edit {
    background: #fff7ed;
    border-color: #fed7aa;
    color: #c2410c;
}
.fs-btn--edit:hover { background: #ffedd5; border-color: #f97316; }
.fs-btn--off {
    background: #fff;
    border-color: var(--divider-soft);
    color: var(--text-muted);
}
.fs-btn--off:hover { background: var(--cream-header); color: var(--brown); border-color: #d6c4a8; }
.fs-btn--on {
    background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    border-color: #15803d;
}
.fs-btn--on:hover { filter: brightness(1.08); }

/* Edit panel — collapsible, shown when [hidden] attr removed by JS */
.fs-card__edit {
    margin-top: 4px;
    padding-top: 10px;
    border-top: 1px dashed var(--divider-soft);
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.fs-card__edit[hidden] { display: none; }
.fs-card__edit-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 4px;
}

.fs-form-row { display: flex; flex-direction: column; gap: 4px; }
.fs-form-row label { font-size: 12px; font-weight: 600; color: var(--brown); }
.fs-form-row--inline { display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: end; }
.fs-color-input { width: 44px; height: 36px; padding: 2px; border: 1px solid var(--divider-soft); border-radius: 6px; cursor: pointer; }
.fs-current-logo {
    margin-top: 6px;
    padding: 6px 8px;
    background: var(--cream-header);
    border-radius: 6px;
    font-size: 11.5px;
    color: var(--text-muted);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.fs-current-logo code { background: #fff; padding: 1px 5px; border-radius: 4px; font-size: 11px; word-break: break-all; }
.fs-remove-logo { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; color: #b45309; cursor: pointer; }

@media (max-width: 600px) {
    .fs-form-row--inline { grid-template-columns: 1fr 1fr; }
}
</style>

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

    // Toggle edit panel per card (Edit / Batal pakai selector yang sama).
    document.querySelectorAll('[data-fs-toggle-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const card  = btn.closest('.fs-card');
            if (!card) return;
            const panel = card.querySelector('.fs-card__edit');
            const editBtn = card.querySelector('.fs-btn--edit');
            if (!panel || !editBtn) return;
            const opening = panel.hidden;
            panel.hidden = !opening;
            editBtn.setAttribute('aria-expanded', opening ? 'true' : 'false');
            editBtn.innerHTML = opening
                ? '<i class="fa fa-times"></i> Tutup'
                : '<i class="fa fa-pen"></i> Edit';
        });
    });
})();
</script>
@endsection
