@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="absk-wrap">
    <div class="absk-formhead">
        <a href="{{ route('akun.absensi') }}" class="absk-back" title="Kembali">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M11 4l-5 5 5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <div class="absk-formtitle">Ajukan Permohonan</div>
    </div>

    @include('layouts.notif')

    <div class="absk-card">
        @include('absensi.akun._pengajuan_form', ['tipeList' => $tipeList, 'tipe' => $tipe, 'inDrawer' => false])
    </div>
</div>
@endsection
