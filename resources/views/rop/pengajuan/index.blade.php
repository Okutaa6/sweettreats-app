{{--
    Pengajuanku — daftar milik pengaju.

    Satu berkas, dua bentuk (design handoff layar 1 & 2):
      • HP      : daftar kartu + tombol "Pengajuan Baru" menempel di bawah.
      • ≥992px  : master–detail, panel kanan langsung menampilkan detail
                  tanpa pindah halaman.
    Bukan "versi HP yang dilebarkan" — di layar lebar ruang kosongnya dipakai
    untuk detail, bukan untuk kartu yang melar.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.'); @endphp

<div class="rop-surface rop-surface--lebar">

    <div class="rop-head">
        <h1>Pengajuanku</h1>
        <div class="rop-sub">
            {{-- Nama user ada di session('name') — aplikasi ini memakai session
                 manual, bukan Auth guard Laravel, jadi auth()->user() selalu null. --}}
            {{ session('name') ?: 'Pengaju' }}{{ session('group_name') ? ' · ' . session('group_name') : '' }}
        </div>
    </div>

    @include('layouts.notif')

    {{-- Tiga angka yang sekaligus jadi filter — sekali lihat tahu ada apa,
         sekali klik langsung tersaring. --}}
    <div class="rop-stat-row">
        <a class="rop-stat rop-stat--kuning {{ $status === 'diajukan' ? 'rop-stat--aktif' : '' }}"
           href="{{ route('rop.pengajuan.index', ['status' => 'diajukan']) }}">
            <b class="rop-num">{{ $ringkas['menunggu'] }}</b>
            <span>menunggu ACC</span>
        </a>
        <a class="rop-stat rop-stat--hijau {{ $status === 'dibayar' ? 'rop-stat--aktif' : '' }}"
           href="{{ route('rop.pengajuan.index', ['status' => 'dibayar']) }}">
            <b class="rop-num">{{ $ringkas['nota'] }}</b>
            <span>perlu upload nota</span>
        </a>
        <a class="rop-stat rop-stat--merah {{ $status === 'ditolak' ? 'rop-stat--aktif' : '' }}"
           href="{{ route('rop.pengajuan.index', ['status' => 'ditolak']) }}">
            <b class="rop-num">{{ $ringkas['ditolak'] }}</b>
            <span>ditolak</span>
        </a>
    </div>

    <div class="rop-chip-row" style="margin-bottom:14px">
        <a class="rop-chip {{ $status ? '' : 'rop-chip--aktif' }}"
           href="{{ route('rop.pengajuan.index') }}">Semua</a>
        @foreach (['diajukan' => 'Menunggu', 'disetujui' => 'Disetujui', 'dibayar' => 'Dibayar',
                   'selesai' => 'Selesai', 'ditolak' => 'Ditolak'] as $kode => $label)
            <a class="rop-chip {{ $status === $kode ? 'rop-chip--aktif' : '' }}"
               href="{{ route('rop.pengajuan.index', ['status' => $kode]) }}">
                {{ $label }}@if (($jumlah[$kode] ?? 0) > 0) ({{ $jumlah[$kode] }})@endif
            </a>
        @endforeach
    </div>

    @if ($daftar->isEmpty())
        <div class="rop-kosong">
            <h3>Belum ada pengajuan</h3>
            <p>Buat yang pertama — pengajuan berikutnya bisa disalin dari sini,<br>tak perlu isi dari nol.</p>
            <a class="rop-btn rop-btn--primary" href="{{ route('rop.pengajuan.create') }}">
                @include('rop._ikon', ['n' => 'plus'])
                Pengajuan Baru
            </a>
        </div>
    @else
        <div class="rop-md">
            <div class="rop-md__list">
                @foreach ($daftar as $r)
                    @include('rop.pengajuan._kartu', ['r' => $r, 'aktif' => $aktif && $aktif->id === $r->id])
                @endforeach

                @if ($daftar->hasPages())
                    <div style="margin-top:14px">{{ $daftar->links() }}</div>
                @endif
            </div>

            {{-- Panel detail: hanya muncul di layar lebar. Di HP, kartu di kiri
                 adalah tautan biasa ke halaman detail. --}}
            <div class="rop-md__panel d-none d-lg-block">
                @if ($aktif)
                    @include('rop.pengajuan._detail', [
                        'rop'  => $aktif,
                        'logs' => $logs,
                        'milikSendiri' => true,
                    ])
                @endif
            </div>
        </div>

        <div class="rop-dock">
            <a class="rop-btn rop-btn--primary rop-btn--block" href="{{ route('rop.pengajuan.create') }}">
                @include('rop._ikon', ['n' => 'plus'])
                Pengajuan Baru
            </a>
        </div>
    @endif
</div>
@endsection

@section('js')
{{-- Panel detail di kanan memuat form upload nota, jadi butuh kompresi foto
     yang sama seperti halaman detail. --}}
<script src="{{ asset('assets/js/rop-foto.js') }}?v={{ @filemtime(public_path('assets/js/rop-foto.js')) ?: '1' }}"></script>
<script>
// Di layar lebar, klik kartu memperbarui panel kanan tanpa pindah halaman.
// Di HP tautannya dibiarkan bekerja normal (panel kanan memang tidak ada).
(function () {
    if (!window.matchMedia('(min-width: 992px)').matches) return;
    document.querySelectorAll('[data-rop-lihat]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var url = new URL(window.location.href);
            url.searchParams.set('lihat', a.getAttribute('data-rop-lihat'));
            window.location.href = url.toString();
        });
    });
})();
</script>
@endsection
