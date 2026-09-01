{{--
    Komisi Dokter — layar detail satu dokter.

    Layar yang dibuka saat Finance perlu MEMBUKTIKAN angka ke dokter yang
    bertanya. Karena itu semua state (periode, cabang, tab, penyaring, halaman)
    hidup di URL — halamannya bisa dikirim apa adanya ke atasan atau ke dokter.

    Tidak ada tab "Per Cabang" di sini: pemilihan cabang sudah terjadi di layar
    daftar lewat dropdown di baris dokter, sebelum masuk ke sini.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet"
      href="{{ asset('assets/css/komisi-dokter.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/komisi-dokter.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $aksi = route('komisi.show', $docId);

    // Field yang harus ikut terbawa saat filter periode/cabang diganti —
    // supaya berpindah tanggal tidak melempar Finance kembali ke tab pertama.
    $hidden = array_filter([
        'tab'  => $tab !== 'perawatan' ? $tab : null,
        'code' => request('code'),
        'q'    => request('q'),
    ]);

    $paramDasar = array_filter([
        'date_from' => $start,
        'date_to'   => $end,
        'place'     => $branchLocked ? null : $placeId,
    ]);

    $paramUnduh = array_merge($paramDasar, array_filter([
        'code' => request('code'),
        'q'    => request('q'),
    ]));

    $kata = collect(preg_split('/\s+/', trim(preg_replace('/\b(drg|dr|Sp)\.?\s*/i', '', $dokter->nama))))
        ->filter()->values();
    $inisial = strtoupper(mb_substr($kata[0] ?? $dokter->nama, 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
@endphp

<div class="kd">

    @include('finance.komisi._header', [
        'judul'    => $dokter->nama,
        'crumb'    => 'Komisi Dokter · Rincian',
        'backUrl'  => route('komisi.index') . '?' . http_build_query($paramDasar),
        'subJudul' => $branchLabel . ' · '
                      . \Carbon\Carbon::parse($start)->translatedFormat('j M Y') . ' – '
                      . \Carbon\Carbon::parse($end)->translatedFormat('j M Y')
                      . ' · ' . $hari . ' hari',
    ])

    @include('layouts.notif')

    @include('finance.komisi._filterbar', [
        'aksi'     => $aksi,
        'hidden'   => $hidden,
        'unduhUrl' => route('komisi.export.detail', $docId) . '?' . http_build_query($paramUnduh),
        'cetakUrl' => route('komisi.cetak', $docId) . '?' . http_build_query($paramUnduh),
    ])

    @if ($belumFinal)
        @include('finance.komisi._banner', ['aksi' => $aksi, 'hidden' => $hidden])
    @endif

    <div class="kd-card kd-ident">
        <span class="kd-avatar kd-avatar--lg">
            @if ($dokter->foto)
                <img src="{{ asset('uploads/doctors/' . $dokter->foto) }}" alt="">
            @else
                {{ $inisial }}
            @endif
        </span>
        <div>
            <div class="kd-ident__nama">{{ $dokter->nama }}</div>
            <div class="kd-ident__meta">
                @include('finance.komisi._chip_spek', ['nama' => $dokter->nama])
                @if ($dokter->nonaktif)
                    <span class="kd-badge-off">Nonaktif</span>
                @endif
                @if ($dokter->cabangList)
                    <span class="kd-cabang-list">{{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($dokter->cabangList) }}</span>
                @endif
            </div>
        </div>
        <div class="kd-ident__periode">
            <div class="kd-stat__label">Periode</div>
            <div style="font-size:13px;font-weight:700;margin-top:4px">
                {{ \Carbon\Carbon::parse($start)->translatedFormat('j M Y') }} –
                {{ \Carbon\Carbon::parse($end)->translatedFormat('j M Y') }}
            </div>
        </div>
    </div>

    @if (! $dokter->ada)
        <div class="kd-card">
            @include('finance.komisi._kosong', [
                'judul' => 'Kode dokter ini tidak ditemukan di data komisi',
                'ket'   => 'Tidak ada satu pun baris komisi untuk kode ' . $docId . ', kapan pun.',
            ])
        </div>
    @else
        @include('finance.komisi._ringkas', ['kartu' => $kartu, 'lima' => true])

        <div class="kd-tabbar noprint">
            <div class="kd-tabs">
                @foreach ($tabs as $key => $label)
                    <a class="kd-tab {{ $tab === $key ? 'kd-tab--on' : '' }}"
                       href="{{ $aksi }}?{{ http_build_query(array_merge($paramDasar, array_filter([
                            'tab'  => $key,
                            'code' => request('code'),
                       ]))) }}">
                        {{ $label }}
                        <span class="kd-tab__n">{{ number_format($jumlah[$key] ?? 0, 0, ',', '.') }}</span>
                    </a>
                @endforeach
            </div>

            {{-- Chip penyaring: drill-down tidak pernah membuang konteks, dan
                 tiap penyaring bisa dilepas satu per satu. --}}
            @foreach ($chip as $c)
                @php
                    $tanpa = array_filter(array_merge($paramDasar, [
                        'tab'  => $tab,
                        'code' => $c['key'] === 'code' ? null : request('code'),
                        'q'    => $c['key'] === 'q' ? null : request('q'),
                    ]));
                @endphp
                <span class="kd-filterchip" style="margin-bottom:0">
                    Disaring: {{ $c['label'] }}
                    <a href="{{ $aksi }}?{{ http_build_query($tanpa) }}" title="Hapus penyaring ini">
                        @include('finance.komisi._ikon', ['n' => 'tutup', 's' => 13])
                    </a>
                </span>
            @endforeach
        </div>

        @if ($tab === 'perawatan')
            @include('finance.komisi._tab_perawatan')
        @elseif ($tab === 'tren')
            @include('finance.komisi._tab_tren')
        @else
            @include('finance.komisi._tab_rincian')
        @endif
    @endif

    <div class="kd-toast" id="kd-toast"></div>
</div>
@endsection

@section('js')
<script src="{{ asset('assets/js/komisi-dokter.js') }}?v={{ @filemtime(public_path('assets/js/komisi-dokter.js')) ?: '1' }}"></script>
@endsection
