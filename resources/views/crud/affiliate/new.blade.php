@extends('layouts.app')

@section('content')
@php
    $defaultType = request('type') === 'kol' ? 'kol' : (old('type') ?: 'koc');
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Tambah Affiliate Manual</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-lg-9">
            <form method="POST" action="{{ route('rawaffiliate.store') }}">
                @csrf

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <a href="{{ route('rawaffiliate') }}" class="st-btn-outline-brown">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>
                </div>

                {{-- ─── Card 1: Pilih Tipe ─── --}}
                <div class="st-card mb-3">
                    <div class="st-card__head">
                        <div class="st-card__title-wrap">
                            <span class="st-card__icon"><i class="fa fa-user-tag"></i></span>
                            <h4 class="st-card__title">Tipe Affiliate</h4>
                        </div>
                    </div>
                    <div class="st-card__body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="d-block" style="cursor:pointer;">
                                    <input type="radio" name="type" value="koc"
                                        {{ $defaultType === 'koc' ? 'checked' : '' }}
                                        id="typeKoc" style="display:none;">
                                    <div class="st-type-card" data-for="typeKoc">
                                        <i class="fa fa-user" style="font-size:24px; color:#461500;"></i>
                                        <div>
                                            <strong style="display:block; color:#461500;">KOC</strong>
                                            <small class="text-muted">Pasien-yang-jadi-affiliate, biasanya self-register lewat link publik</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="d-block" style="cursor:pointer;">
                                    <input type="radio" name="type" value="kol"
                                        {{ $defaultType === 'kol' ? 'checked' : '' }}
                                        id="typeKol" style="display:none;">
                                    <div class="st-type-card" data-for="typeKol">
                                        <i class="fa fa-star" style="font-size:24px; color:#c45200;"></i>
                                        <div>
                                            <strong style="display:block; color:#461500;">KOL</strong>
                                            <small class="text-muted">Influencer yang kami approach, di-input manual</small>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6 form-group mb-0">
                                <label>Cabang</label>
                                <select class="form-control" name="raw_branch_id">
                                    <option value="">- Pilih Cabang -</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('raw_branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Cabang tempat KOC/KOL ini berafiliasi.</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Card 2: Data Diri ─── --}}
                <div class="st-card mb-3">
                    <div class="st-card__head">
                        <div class="st-card__title-wrap">
                            <span class="st-card__icon"><i class="fa fa-id-card"></i></span>
                            <h4 class="st-card__title">Data Diri</h4>
                        </div>
                    </div>
                    <div class="st-card__body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Nama <span class="text-danger">*</span></label>
                                <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                    type="text" name="name" value="{{ old('name') }}" required>
                                @if($errors->has('name'))<div class="invalid-feedback">{{ $errors->first('name') }}</div>@endif
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <select class="form-control" name="role">
                                    @foreach(\App\Models\RawAffiliate::roles() as $key => $label)
                                        <option value="{{ $key }}" {{ old('role', 'pasien') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Referral Scope</label>
                                <select class="form-control" name="referral_scope">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::referralScopes() as $key => $cfg)
                                        <option value="{{ $key }}" {{ old('referral_scope') == $key ? 'selected' : '' }}>{{ $cfg['short'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>No. WhatsApp</label>
                                <input class="form-control" type="text" name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Email</label>
                                <input class="form-control" type="email" name="email" value="{{ old('email') }}">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Info Rekening Bank</label>
                                <input class="form-control" type="text" name="bank_account_info" value="{{ old('bank_account_info') }}" placeholder="BCA 12345 a/n Nama">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Card 3: Sosial Media (untuk KOL terutama) ─── --}}
                <div class="st-card mb-3">
                    <div class="st-card__head">
                        <div class="st-card__title-wrap">
                            <span class="st-card__icon"><i class="fa fa-share-alt"></i></span>
                            <h4 class="st-card__title">Sosial Media</h4>
                        </div>
                    </div>
                    <div class="st-card__body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Platform Utama</label>
                                <select class="form-control" name="social_platform">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::socialPlatforms() as $key => $label)
                                        <option value="{{ $key }}" {{ old('social_platform') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 form-group">
                                <label>Username</label>
                                <input class="form-control" type="text" name="social_username" value="{{ old('social_username') }}" placeholder="@username">
                            </div>
                            <div class="col-md-3 form-group" data-kol-only>
                                <label>Followers</label>
                                <input class="form-control" type="number" name="followers_count" value="{{ old('followers_count') }}" min="0" placeholder="Mis. 50000">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Card 4: Detail KOL (visible kalau type=kol) ─── --}}
                <div class="st-card mb-3" data-kol-only>
                    <div class="st-card__head">
                        <div class="st-card__title-wrap">
                            <span class="st-card__icon"><i class="fa fa-star"></i></span>
                            <h4 class="st-card__title">Detail KOL</h4>
                        </div>
                    </div>
                    <div class="st-card__body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Kategori KOL</label>
                                <select class="form-control" name="kol_kategori">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolKategoriOptions() as $key => $label)
                                        <option value="{{ $key }}" {{ old('kol_kategori') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Progress Approach</label>
                                <select class="form-control" name="kol_progress">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ old('kol_progress', 'akan_di_approach') == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Status Relasi</label>
                                <select class="form-control" name="kol_status_relasi">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolStatusRelasiOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ old('kol_status_relasi', 'baru') == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Rate Card</label>
                                <input class="form-control" type="text" name="rate_card" value="{{ old('rate_card') }}" placeholder="Mis. Rp 5.000.000 / 5-10jt">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Contact Person</label>
                                <input class="form-control" type="text" name="contact_person" value="{{ old('contact_person') }}" placeholder="Manager / agency">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Tanggal Approach</label>
                                <input class="form-control" type="date" name="tanggal_approach" value="{{ old('tanggal_approach') }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ─── Card 5: Catatan & Status ─── --}}
                <div class="st-card mb-3">
                    <div class="st-card__head">
                        <div class="st-card__title-wrap">
                            <span class="st-card__icon"><i class="fa fa-clipboard"></i></span>
                            <h4 class="st-card__title">Catatan & Status</h4>
                        </div>
                    </div>
                    <div class="st-card__body">
                        <div class="form-group">
                            <label>Feedback / Catatan Internal</label>
                            <textarea class="form-control" name="feedback" rows="3" placeholder="Mis. responsif, follower aktif, suka konten edukasi, dll">{{ old('feedback') }}</textarea>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" class="custom-control-input" id="isActive" name="is_active" value="1"
                                {{ old('is_active', 1) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="isActive">Aktif</label>
                        </div>
                    </div>
                </div>

                <div class="st-card">
                    <div class="st-card__body d-flex justify-content-end" style="gap: 8px;">
                        <a href="{{ route('rawaffiliate') }}" class="st-btn-outline-brown">Batal</a>
                        <button class="st-btn-brand" type="submit">
                            <i class="fa fa-save"></i> Simpan Affiliate
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .st-type-card {
        display: flex; align-items: center; gap: 14px;
        padding: 18px 20px; border: 2px solid rgba(70,21,0,0.1);
        border-radius: 14px; background: #fffaf5;
        transition: all 0.2s;
    }
    .st-type-card:hover { border-color: rgba(70,21,0,0.3); }
    input[type="radio"]:checked + .st-type-card {
        border-color: #461500; background: #fff;
        box-shadow: 0 8px 22px rgba(70,21,0,0.12);
    }
</style>

<script>
    (function () {
        const typeRadios = document.querySelectorAll('input[name="type"]');
        const kolOnlyEls = document.querySelectorAll('[data-kol-only]');

        function applyType() {
            const checked = document.querySelector('input[name="type"]:checked');
            const isKol = checked && checked.value === 'kol';
            kolOnlyEls.forEach(el => el.style.display = isKol ? '' : 'none');
        }

        typeRadios.forEach(r => r.addEventListener('change', applyType));
        applyType();
    })();
</script>
@endsection
