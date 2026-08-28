{{--
    Komisi Dokter — layar daftar (Finance, zona burgundy).

    Layar yang dibuka Finance tiap awal bulan untuk menyiapkan pembayaran fee.
    Read-only: tidak ada tandai-lunas, tidak ada upload bukti transfer, tidak ada
    pengaturan skema komisi (semua itu di Omnicare / halaman lain).

    Periode default = BULAN LALU, bukan bulan berjalan. Lihat alasannya di
    KomisiDokterController::resolveContext().
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet"
      href="{{ asset('assets/css/komisi-dokter.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/komisi-dokter.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $aksi   = route('komisi.index');
    $hidden = array_filter(['urut' => $urut !== 'komisi' ? $urut : null]);

    $paramUnduh = array_filter([
        'date_from' => $start,
        'date_to'   => $end,
        'place'     => $branchLocked ? null : $placeId,
    ]);

    $totalKomisi = $dokter->sum('komisi');
@endphp

<div class="kd">

    @include('finance.komisi._header', [
        'judul'    => 'Komisi Dokter',
        'subJudul' => $branchLabel . ' · '
                      . \Carbon\Carbon::parse($start)->translatedFormat('j M Y') . ' – '
                      . \Carbon\Carbon::parse($end)->translatedFormat('j M Y')
                      . ' · ' . $hari . ' hari',
    ])

    @include('layouts.notif')

    @include('finance.komisi._filterbar', [
        'aksi'     => $aksi,
        'hidden'   => $hidden,
        'unduhUrl' => route('komisi.export') . '?' . http_build_query($paramUnduh),
    ])

    @if ($belumFinal)
        @include('finance.komisi._banner', ['aksi' => $aksi, 'hidden' => $hidden])
    @endif

    @if ($dokter->isEmpty())
        <div class="kd-card">
            @include('finance.komisi._kosong')
        </div>
    @else
        @include('finance.komisi._ringkas', ['kartu' => $kartu])

        <div class="kd-toolbar noprint">
            <div class="kd-search">
                @include('finance.komisi._ikon', ['n' => 'cari', 's' => 16])
                <input class="kd-input" type="search" id="kd-cari"
                       placeholder="Cari nama dokter…" autocomplete="off"
                       aria-label="Cari nama dokter">
            </div>

            <span class="kd-toolbar__label">Urutkan</span>
            <div class="kd-presets">
                @foreach (['komisi' => 'Komisi Tertinggi', 'pasien' => 'Jumlah Pasien', 'nama' => 'Nama'] as $key => $label)
                    @php
                        $paramUrut = array_filter([
                            'date_from' => $start, 'date_to' => $end,
                            'place'     => $branchLocked ? null : $placeId,
                            'urut'      => $key !== 'komisi' ? $key : null,
                        ]);
                    @endphp
                    <a class="kd-preset {{ $urut === $key ? 'kd-preset--on' : '' }}"
                       href="{{ $aksi }}?{{ http_build_query($paramUrut) }}">{{ $label }}</a>
                @endforeach
            </div>

            <span class="kd-filter__spacer"></span>

            <button type="button" class="kd-btn" id="kd-lipat"
                    data-target="kd-ringkas" data-buka="Tampilkan ringkasan"
                    data-tutup="Sembunyikan ringkasan">
                @include('finance.komisi._ikon', ['n' => 'turun', 's' => 15])
                <span>Sembunyikan ringkasan</span>
            </button>
        </div>

        <div class="kd-tabel-wrap">
            <table class="kd-tabel kd-tabel__klik" id="kd-tabel-dokter">
                <thead>
                    <tr>
                        <th>Dokter</th>
                        <th>Cabang</th>
                        <th class="num">Pasien</th>
                        <th class="num">Kunjungan</th>
                        <th class="num">Tindakan</th>
                        <th class="num">Komisi</th>
                        <th class="num">Vs Periode Lalu</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dokter as $d)
                        @include('finance.komisi._baris_dokter', ['d' => $d])
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Angka penutup yang bisa dicocokkan ke spreadsheet. Tombol salin ada
             karena inilah angka yang paling sering dipindahkan Finance. --}}
        <div class="kd-total">
            <span class="kd-total__label">Total periode</span>
            <span class="kd-total__n">{{ $dokter->count() }} dokter</span>
            <span style="color:var(--kd-head-ink)">&middot;</span>
            <span class="kd-total__val">Rp {{ number_format($totalKomisi, 0, ',', '.') }}</span>
            @if ($belumFinal)
                <span class="kd-final">BELUM FINAL</span>
            @endif
            <span class="kd-total__aksi noprint">
                <button type="button" class="kd-btn kd-btn--sm kd-salin"
                        data-nilai="{{ number_format($totalKomisi, 0, ',', '.') }}">
                    @include('finance.komisi._ikon', ['n' => 'salin', 's' => 14])
                    Salin total
                </button>
            </span>
        </div>

        {{-- Selisih dengan Accurate dijelaskan dengan ANGKA, bukan peringatan
             abstrak. Jurnal "Hutang Fee Dokter" menjumlahkan seluruh peran; kalau
             Finance membandingkan dua angka ini tanpa tahu sebabnya, mereka akan
             mengira salah satunya rusak. --}}
        @if ($asisten > 0)
            <div class="kd-nota">
                Halaman ini hanya komisi <strong>dokter</strong>. Jurnal
                &ldquo;Hutang Fee Dokter&rdquo; di Accurate menjumlahkan seluruh peran &mdash;
                komisi asisten periode ini sebesar
                <strong>Rp {{ number_format($asisten, 0, ',', '.') }}</strong> tidak termasuk di sini.
            </div>
        @endif
    @endif

    <div class="kd-toast" id="kd-toast"></div>
</div>
@endsection

@section('js')
<script src="{{ asset('assets/js/komisi-dokter.js') }}?v={{ @filemtime(public_path('assets/js/komisi-dokter.js')) ?: '1' }}"></script>
@endsection
