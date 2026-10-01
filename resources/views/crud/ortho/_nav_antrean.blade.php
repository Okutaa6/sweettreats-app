{{--
    Sub-tab untuk antrean penjadwalan — bentuk dan tempatnya SAMA PERSIS
    dengan sub-tab kegentingan di daftar kerja (_nav_urgensi).

    Kenapa isinya berbeda: antrean ini justru berisi pasien yang BELUM punya
    jadwal, jadi "Terlewat / Hari ini / Minggu ini" tidak punya arti apa pun di
    sini — tidak ada tanggal yang bisa terlewat. Yang berarti adalah dua
    tingkat kegentingan milik antrean sendiri, dan ambangnya definisi Sales:
    180 hari untuk "Hilang", kurang dari 2 kunjungan per 6 bulan untuk
    "Jarang kontrol".

    Dulu slot ini dibiarkan kosong di mode antrean, dan itu yang bikin bingung:
    tabnya seolah hilang saat pindah dari daftar kerja ke antrean.
--}}
@php
    $tingkatTabs = [
        'semua'  => ['Semua',                    null],
        'hilang' => ['Hilang lebih dari 6 bulan', '#C0392B'],
        'jarang' => ['Jarang kontrol',            '#B5701A'],
        'baru'   => ['Baru selesai kontrol',      '#2F8E55'],
    ];
    $jelasSemua = 'Semua pasien yang belum punya jadwal kontrol';
@endphp
<div class="ort-utabs">
    @foreach ($tingkatTabs as $key => [$label, $warna])
        @php
            $n       = $hitungAntrean[$key] ?? 0;
            $merahkn = $key === 'hilang' && $n > 0 && $urgensi !== $key;
        @endphp
        <a href="{{ $tautan(['urgensi' => $key === 'semua' ? null : $key]) }}"
           class="ort-utab {{ $urgensi === $key ? 'is-active' : '' }}"
           @if ($key === 'semua') title="{{ $jelasSemua }}" @endif>
            @if ($warna)
                <span class="ort-utab__dot" style="background:{{ $warna }}"></span>
            @endif
            {{ $label }}
            <span class="ort-utab__n tabular {{ $merahkn ? 'is-bad' : '' }}">{{ $n }}</span>
        </a>
    @endforeach
</div>

@if ($urgensi === 'semua')
    <span class="ort-urow__hint">paling lama menghilang di atas</span>
@endif
