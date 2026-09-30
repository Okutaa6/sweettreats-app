{{--
    Sisi kiri pita navigasi — sub-tab kegentingan. Berlaku di KETIGA tab.

    Dipisah dari _navrow supaya pita itu tidak perlu tahu apa-apa soal isi
    tiap tab; ia cuma menyediakan slot kiri. Lihat _navrow.blade.php.

    ⚠ Angka "Semua" SENGAJA berbeda antar tab, karena yang dihitung memang
      populasi yang berbeda:
        • List         — jumlah JADWAL terbuka di cabang ini.
        • Kalender     — jumlah jadwal yang jatuh di grid bulan yang terlihat,
                         jadi ia berubah saat bulan digeser. Dihitung dari
                         baris grid yang sama dengan yang dirender, supaya
                         angkanya mustahil menunjuk sesuatu di luar layar.
        • Pasien Ortho — jumlah PASIEN, termasuk yang belum punya jadwal.
      Perbedaan itu dijelaskan lewat title pada tab "Semua"; jangan dipaksa
      seragam, karena menyamakannya berarti salah satu tab akan berbohong.
--}}
@php
    $jelasSemua = match ($tab) {
        'kalender' => 'Semua jadwal yang jatuh di bulan yang sedang terlihat',
        'pasien'   => 'Semua pasien, termasuk yang belum punya jadwal',
        default    => 'Semua jadwal kontrol yang masih terbuka',
    };
@endphp
<div class="ort-utabs">
    @foreach ($urgensiTabs as $key => [$label, $warna])
        @php
            $n       = $hitungUrgensi[$key] ?? 0;
            $merahkn = $key === 'terlewat' && $n > 0 && $urgensi !== $key;
        @endphp
        <a href="{{ $tautan(['urgensi' => $key, 'kartu' => null]) }}"
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

{{-- Petunjuk urutan hanya ditampilkan di tab yang MEMANG mengurutkan begitu.
     Grid kalender disusun per tanggal, bukan per kegentingan — menampilkan
     kalimat ini di sana akan salah. --}}
@if ($urgensi === 'semua' && $tab !== 'kalender')
    <span class="ort-urow__hint">terlewat di paling atas</span>
@endif
