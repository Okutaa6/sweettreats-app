@extends('layouts.app')

{{-- Pusat Akun → Setting — re-skin ringan (handoff "Pusat Akun Setting"):
     kartu Identitas Sistem (logo + nama) + kartu Operasional (cabang read-only).
     HANYA field yang ada di sys_profile (systitle/sysname/syslogo) — field handoff
     lain (tagline/zona waktu/Mode Pemeliharaan) TIDAK dibangun: belum diputuskan. --}}

@section('css')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .st2 { font-family: 'Plus Jakarta Sans', sans-serif; color: #3B1500; padding: 20px 26px 30px; max-width: 760px; margin: 0 auto; }
    .st2 h1 { font-size: 20px; font-weight: 800; margin: 0 0 2px; }
    .st2 .sub { color: #9A7256; font-size: 13px; margin-bottom: 18px; }
    .st2-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; padding: 20px 22px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    .st2-h6 { font-size: 11px; color: #7A5B43; text-transform: uppercase; letter-spacing: .5px; font-weight: 800; margin: 0 0 14px; }
    .st2-logo-row { display: flex; align-items: center; gap: 16px; margin-bottom: 18px; }
    .st2-logo { width: 84px; height: 84px; border-radius: 20px; object-fit: contain; background: #FCF7F0; border: 1px solid #EFE0CE; padding: 8px; }
    .st2-up { display: inline-flex; align-items: center; gap: 7px; background: #fff; color: #461500; border: 1.5px solid #E7D3BE; border-radius: 10px; padding: 9px 15px; font-weight: 700; font-size: 13px; cursor: pointer; }
    .st2-lbl { display: block; font-size: 11px; color: #9A7256; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 12px 0 5px; }
    .st2-in { width: 100%; border: 1px solid #E7D3BE; border-radius: 11px; padding: 11px 13px; font-size: 13.5px; font-family: inherit; color: #3B1500; }
    .st2-branch { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #F4EADD; font-size: 13.5px; font-weight: 700; color: #3B1500; }
    .st2-branch:last-child { border-bottom: 0; }
    .st2-branch .d { width: 8px; height: 8px; border-radius: 3px; background: #2e8e7a; flex: none; }
    .st2-branch span { color: #9A7256; font-size: 12px; font-weight: 500; margin-left: auto; }
    .st2-foot { display: flex; align-items: center; gap: 12px; }
    .st2-save { background: linear-gradient(135deg,#3A3A36,#242422); color: #fff; border: 0; border-radius: 12px; padding: 12px 24px; font-weight: 800; font-size: 14px; cursor: pointer; }
    .st2-note { color: #9A7256; font-size: 12px; }
</style>
@endsection

@section('content')
@php $branches = \App\Models\RawBranch::orderBy('name')->get(['name']); @endphp
<div class="st2">
    @include('layouts.notif')

    <h1>Setting</h1>
    <div class="sub">Pusat Akun · Profil sistem</div>

    <form method="POST" action="{{ url('setsystemprofile/' . $sysprofile->id) }}">
        @csrf @method('PUT')
        <div class="st2-card">
            <div class="st2-h6">Identitas Sistem</div>
            <div class="st2-logo-row">
                <img class="st2-logo" src="{{ url('sysprofile/' . $sysprofile->syslogo) }}" alt="Logo">
                <button type="button" class="st2-up" data-toggle="modal" data-target="#uploadLogoModal">
                    <i class="fa fa-upload"></i> Unggah logo
                </button>
            </div>
            <label class="st2-lbl">Judul dashboard</label>
            <input type="text" name="systitle" class="st2-in" value="{{ $sysprofile->systitle }}" required>
            <label class="st2-lbl">Nama institusi</label>
            <input type="text" name="sysname" class="st2-in" value="{{ $sysprofile->sysname }}" required>
        </div>

        <div class="st2-card">
            <div class="st2-h6">Operasional</div>
            @foreach($branches as $b)
                <div class="st2-branch"><span class="d"></span>Sweet Treats {{ $b->name }}<span>cabang aktif</span></div>
            @endforeach
        </div>

        <div class="st2-foot">
            <button type="submit" class="st2-save">Simpan perubahan</button>
            <span class="st2-note">Perubahan langsung berlaku untuk semua user.</span>
        </div>
    </form>
</div>

{{-- Upload Logo Modal — mekanisme lama dipertahankan (route + PUT sama) --}}
<div class="modal fade" id="uploadLogoModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ url('setsystemprofile/upload/' . $sysprofile->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-content" style="border-radius:16px;font-family:'Plus Jakarta Sans',sans-serif">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-weight:800;color:#3B1500">Unggah logo baru</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" name="logo" accept="image/*" required>
                    <small style="color:#9A7256">PNG/JPG/SVG, maks 2 MB.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st2-save" style="padding:9px 18px">Unggah</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
