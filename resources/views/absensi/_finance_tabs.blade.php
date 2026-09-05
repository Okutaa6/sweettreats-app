@php
    $ap = request()->path();   // mis. 'absensi/recap'
    $finTabs = [
        ['label' => 'Rekap Persetujuan', 'url' => url('absensi/recap'),   'on' => str_starts_with($ap, 'absensi/recap')],
        ['label' => 'Kehadiran',         'url' => url('absensi/kehadiran'), 'on' => str_starts_with($ap, 'absensi/kehadiran')],
        ['label' => 'Mapping Karyawan',  'url' => url('absensi/mapping'),  'on' => str_starts_with($ap, 'absensi/mapping')],
        ['label' => 'Upload Data',       'url' => url('absensi'),          'on' => $ap === 'absensi'],
        ['label' => 'Koreksi Absen',     'url' => url('absensi/koreksi'),  'on' => str_starts_with($ap, 'absensi/koreksi')],
        ['label' => 'Periode Gaji',      'url' => url('absensi/periode'),  'on' => str_starts_with($ap, 'absensi/periode')],
    ];

    // Lembar Gaji SENGAJA disaring peran, bukan ditampilkan ke semua yang lolos
    // canSeeAllBranches. Kedua gerbang itu TIDAK sama: `Admin Backup` boleh
    // melihat Kehadiran tapi TIDAK boleh membuka Lembar Gaji (akun bersama,
    // dicabut 5 Sep 2026). Menampilkan tabnya ke dia = menu yang tampil lalu
    // 403 — kegagalan senyap yang sudah berulang di proyek ini.
    //
    // Daftarnya DITANYAKAN ke controllernya, tidak disalin: salinan akan basi
    // diam-diam begitu gerbangnya berubah.
    if (in_array(session('group_name'), \App\Http\Controllers\Absensi\LembarGajiController::BOLEH, true)) {
        $finTabs[] = ['label' => 'Lembar Gaji', 'url' => url('absensi/lembar-gaji'),
            'on' => str_starts_with($ap, 'absensi/lembar-gaji')];
    }

@endphp
<div class="fin-tabs">
    @foreach($finTabs as $t)
        @if($t['url'])
            <a href="{{ $t['url'] }}" class="fin-tab {{ $t['on'] ? 'active' : '' }}">{{ $t['label'] }}</a>
        @else
            <span class="fin-tab disabled" title="Segera hadir">{{ $t['label'] }}</span>
        @endif
    @endforeach
</div>
