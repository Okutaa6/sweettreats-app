@php
    $ap = request()->path();   // mis. 'absensi/recap'

    // Tiap tab menanyakan pertanyaan yang SAMA dengan halaman tujuannya.
    //
    // Sampai 9 Sep 2026 seluruh strip ini dirender tanpa syarat, karena satu-satunya
    // yang bisa sampai ke sini memang canSeeAllBranches(). Begitu Executive boleh
    // membuka Kehadiran (canSeeKehadiran), anggapan itu batal: tanpa penyaringan,
    // Dani akan melihat tab Upload Data, Mapping, Koreksi Absen, dan Periode Gaji —
    // empat pintu yang semuanya berujung 403, dan tiga di antaranya MENGUBAH data
    // absensi. Menu yang tampil lalu menolak adalah kegagalan senyap; berkas ini
    // sudah menuliskan pelajaran itu untuk Lembar Gaji di bawah.
    $bolehLintas = canSeeAllBranches();

    $finTabs = [];
    if ($bolehLintas) {
        $finTabs[] = ['label' => 'Rekap Persetujuan', 'url' => url('absensi/recap'), 'on' => str_starts_with($ap, 'absensi/recap')];
    }
    if (canSeeKehadiran()) {
        $finTabs[] = ['label' => 'Kehadiran', 'url' => url('absensi/kehadiran'), 'on' => str_starts_with($ap, 'absensi/kehadiran')];
    }
    if ($bolehLintas) {
        $finTabs[] = ['label' => 'Mapping Karyawan', 'url' => url('absensi/mapping'), 'on' => str_starts_with($ap, 'absensi/mapping')];
        $finTabs[] = ['label' => 'Upload Data',      'url' => url('absensi'),         'on' => $ap === 'absensi'];
        $finTabs[] = ['label' => 'Koreksi Absen',    'url' => url('absensi/koreksi'), 'on' => str_starts_with($ap, 'absensi/koreksi')];
        $finTabs[] = ['label' => 'Periode Gaji',     'url' => url('absensi/periode'), 'on' => str_starts_with($ap, 'absensi/periode')];
    }

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
