{{--
    Detail pengajuan — halaman penuh (dipakai di HP; di layar lebar isinya
    sama persis, tampil di panel kanan halaman daftar).
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="rop-surface">

    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <a class="rop-btn rop-btn--ghost" style="min-height:40px;padding:8px 12px"
           href="{{ route('rop.pengajuan.index') }}">
            @include('rop._ikon', ['n' => 'kembali'])
        </a>
        <b class="rop-num" style="font-size:16px">{{ $rop->number }}</b>
    </div>

    @include('layouts.notif')

    {{-- Layar sukses: muncul sekali setelah kirim. Isinya menenangkan
         ("Finance lihat ini hari ini") karena kecemasan yang mau dihapus fitur
         ini adalah "pengajuanku hilang, tidak ada yang tahu". --}}
    @if (session('rop_sukses'))
        <div class="rop-card rop-note--hijau" style="padding:22px;text-align:center;margin-bottom:12px;border-radius:16px">
            <div style="width:52px;height:52px;margin:0 auto 12px;border-radius:50%;
                        background:#2F8E55;color:#fff;display:grid;place-items:center">
                @include('rop._ikon', ['n' => 'cek', 's' => 26])
            </div>
            <div class="rop-num" style="font-size:19px;font-weight:800">{{ session('rop_sukses') }}</div>
            <div style="margin:8px 0">@include('rop._pill', ['status' => 'diajukan'])</div>
            <p style="font-size:13.5px;margin:0">
                Finance melihat ini hari ini. Kamu dapat notifikasi begitu diputuskan.
            </p>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:14px;flex-wrap:wrap">
                <a class="rop-chip" href="{{ route('rop.pengajuan.index') }}">Lihat pengajuanku</a>
                <a class="rop-chip" href="{{ route('rop.pengajuan.create') }}">Ajukan lagi</a>
            </div>
        </div>
    @endif

    @include('rop.pengajuan._detail', [
        'rop'  => $rop,
        'logs' => $logs,
        'milikSendiri' => $milikSendiri,
    ])
</div>
@endsection

@section('js')
<script src="{{ asset('assets/js/rop-foto.js') }}?v={{ @filemtime(public_path('assets/js/rop-foto.js')) ?: '1' }}"></script>
@endsection
