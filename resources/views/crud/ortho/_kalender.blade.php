{{--
    Tab Kalender — brief §12 / screenshot 11.

    Grid bulanan berisi JADWAL KONTROL (sch_date), bukan kunjungan. Gunanya
    melihat beban minggu depan dan menyisir yang terlewat.

    ⚠ SATU-SATUNYA TEMPAT YANG MASIH MENEMPATKAN PASIEN DI TANGGAL TERTENTU,
      dan itu disengaja. Di tab List, kolom Jadwal sudah berhenti menyebut
      tanggal dan berganti perkiraan ("± Nov", "Pekan ini") karena tanggalnya
      tebakan — irama pasien sendiri meleset 14 hari, dan tanggal pastinya baru
      lahir saat reservasi dibuat.

      Di sini tanggal tetap dipakai karena papan ini memang alat MENGHITUNG
      BEBAN: "berapa orang jatuh tempo minggu depan". Mengganti kotak tanggal
      dengan perkiraan membuat gridnya kehilangan seluruh gunanya. Yang dilihat
      di sini sebaran, bukan janji — dan perbedaan itu yang membuatnya tetap
      jujur meski bertanggal.

    Yang harus terasa:
      • Tanggal yang punya jadwal TERLEWAT diberi latar merah muda — supaya
        langsung kelihatan saat menggulir bulan ke belakang.
      • Chip = nama depan saja, warna mengikuti status. Maksimal 3, sisanya
        "+N lainnya" dalam warna pudar.
      • Klik DI MANA PUN di sel yang berisi membuka drawer harian, bukan
        drawer pasien.

    ⚠ Brief §22: TIDAK ADA teks "N jadwal kontrol di bulan ini" di kepala.
--}}
@php
    $kal    = $kalender;
    $bulan  = $kal['bulan'];
    $prev   = $bulan->copy()->subMonth()->format('Y-m');
    $next   = $bulan->copy()->addMonth()->format('Y-m');
    $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /* Ditulis tangan, sama seperti $namaHari di atasnya, BUKAN
       translatedFormat('M') — berkas ini sudah memilih jalan itu untuk nama
       hari, dan dua sumber nama bulan di satu halaman pasti berbeda suatu
       hari. Yang ditiru pemilih di halaman Kehadiran, jadi singkatannya
       disamakan dengan yang dipakai di sana. */
    $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                  'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

    $warnaChip = [
        'menunggu' => 'menunggu', 'usulan' => 'usulan', 'sudah_fu' => 'fu', 'ada_reservasi' => 'rsv',
        'reservasi_batal' => 'batal', 'terlewat' => 'telat', 'terlewat_parah' => 'telatlama',
    ];

    // Status per chip dihitung dari kolom mentah — tidak ada model di sini.
    $kunci = function ($r) use ($hariIni) {
        $telat = $r->sch_date < $hariIni->toDateString();
        return match (true) {
            $r->rsv_status === 'cancelled' => 'reservasi_batal',
            (bool) $r->is_proposal         => 'usulan',
            $telat                         => \Carbon\Carbon::parse($r->sch_date)->diffInDays($hariIni) > 14 ? 'terlewat_parah' : 'terlewat',
            (bool) $r->reservation_id      => 'ada_reservasi',
            (bool) $r->fu_date             => 'sudah_fu',
            default                        => 'menunggu',
        };
    };
@endphp

<div class="st-card" style="margin-top:13px;">

    {{-- Pindah bulan tinggal DI DALAM kartu kalender, bukan di pita
         navigasi: pita sudah dipakai sub-tab kegentingan yang berlaku
         di semua tab, dan bulan hanya berarti untuk kalender. --}}
    {{-- Bentuknya disamakan dengan navigasi kalender halaman Kehadiran
         (7 Okt 2026, permintaan Reza): satu pil putih berisi panah-bulan-panah,
         label bulannya sendiri sebuah tombol yang membuka pemilih 12 bulan.

         ⚠ Kelas .kh2-* di sana TIDAK dipakai ulang, dan itu bukan pilihan:
           CSS-nya hidup di dalam blok style halaman itu sendiri, jadi tidak
           pernah ikut terkirim ke halaman ini. Yang disalin bentuknya; nilainya
           diambil dari token --ort-* supaya tetap satu keluarga warna dengan
           tabelnya.

         ⚠ Pemilihnya TAUTAN biasa, bukan grid yang dibangun JS seperti aslinya.
           Kalender ini dirender server dan sudah punya ?bulan= — JS cuma
           membuka-tutup panelnya, jadi tanpa JS pun navigasinya tetap jalan. --}}
    <div class="ort-cal-bar">
        <div class="ort-cal-nav">
            <a href="{{ $tautan(['bulan' => $prev, 'hari' => null]) }}" class="ort-cal-btn" title="Bulan sebelumnya"><i class="fas fa-chevron-left"></i></a>

            <button type="button" class="ort-cal-month" data-ort-bulan
                    aria-expanded="false" aria-haspopup="true" title="Pilih bulan">
                {{ $bulan->translatedFormat('F Y') }}
                <i class="fas fa-chevron-down ort-cal-month__v"></i>
            </button>

            <a href="{{ $tautan(['bulan' => $next, 'hari' => null]) }}" class="ort-cal-btn" title="Bulan berikutnya"><i class="fas fa-chevron-right"></i></a>

            <div class="ort-cal-dp" data-ort-bulan-panel hidden>
                {{-- Tahunnya LABEL, bukan pemilih — sama dengan aslinya. Pindah
                     tahun tetap bisa lewat panah bulan, yang memang melintasi
                     batas Desember-Januari. --}}
                <div class="ort-cal-dp__th tabular">{{ $bulan->year }}</div>

                <div class="ort-cal-dp__grid">
                    @foreach ($namaBulan as $i => $nb)
                        @php $ym = sprintf('%04d-%02d', $bulan->year, $i + 1); @endphp
                        <a href="{{ $tautan(['bulan' => $ym, 'hari' => null]) }}"
                           class="ort-cal-dp__m {{ $bulan->month === $i + 1 ? 'is-on' : '' }}">{{ $nb }}</a>
                    @endforeach
                </div>

                <a href="{{ $tautan(['bulan' => null, 'hari' => null]) }}" class="ort-cal-dp__kini">Lompat ke hari ini</a>
            </div>
        </div>

        <a href="{{ $tautan(['bulan' => null, 'hari' => null]) }}" class="ort-btn"><i class="fas fa-dot-circle"></i> Hari Ini</a>
    </div>

    <div class="ort-cal-dow">
        @foreach ($namaHari as $h)<div>{{ $h }}</div>@endforeach
    </div>

    <div class="ort-cal-grid">
        @for ($i = 0; $i < 42; $i++)
            @php
                $tgl     = $kal['awalGrid']->copy()->addDays($i);
                $key     = $tgl->toDateString();
                $isi     = $kal['perHari'][$key] ?? [];
                $luar    = $tgl->month !== $bulan->month;
                $hariIniY= $key === $hariIni->toDateString();
                $adaTelat= ! empty($isi) && $key < $hariIni->toDateString();
                $tampil  = array_slice($isi, 0, 3);
                $sisa    = count($isi) - count($tampil);
            @endphp
            @if (! empty($isi))
                <a href="{{ $tautan(['hari' => $key]) }}"
                   class="ort-cal-cell {{ $luar ? 'is-out' : '' }} {{ $adaTelat ? 'is-late' : '' }} {{ $hariIniY ? 'is-today' : '' }}">
            @else
                <div class="ort-cal-cell {{ $luar ? 'is-out' : '' }} {{ $hariIniY ? 'is-today' : '' }}">
            @endif
                <div class="ort-cal-date tabular"><span>{{ $tgl->day }}</span></div>
                @foreach ($tampil as $r)
                    <span class="ort-cal-chip ort-chip--{{ $warnaChip[$kunci($r)] ?? 'menunggu' }}">
                        <i></i>{{ \Illuminate\Support\Str::title(explode(' ', trim($r->patient_name))[0]) }}
                    </span>
                @endforeach
                @if ($sisa > 0)
                    <span class="ort-cal-more tabular">+{{ $sisa }} lainnya</span>
                @endif
            @if (! empty($isi))</a>@else</div>@endif
        @endfor
    </div>

    {{-- Legenda ini DIBETULKAN 7 Okt 2026, dan ia salah ke dua arah sekaligus.

         Ia memasang 'hadir' => 'Hadir' dan 'absen' => 'Tidak hadir' — dua
         keadaan yang MUSTAHIL muncul di grid ini: $warnaChip di kepala berkas
         tidak punya kedua kunci itu, dan OrtQuery::kalender() menyaring
         `closed_at IS NULL` sehingga kunjungan tak pernah masuk. Sementara tiga
         keadaan yang BENAR-BENAR muncul — usulan, batal, telat — tidak
         didaftarkan sama sekali.

         Legenda yang menjanjikan warna yang tak pernah ada lebih buruk daripada
         tidak ada legenda: ia membuat orang menunggu sesuatu yang tidak akan
         datang, lalu mengira datanya yang salah.

         ⚠ Kuncinya WAJIB cocok dengan nilai $warnaChip (kepala berkas ini),
           bukan dengan statusKey(). Keduanya kamus yang berbeda. Labelnya
           disamakan dengan _drawer_hari.blade.php:37-41 supaya satu keadaan
           tidak punya dua nama di dua layar. --}}
    <div class="ort-cal-legend">
        @foreach ([
            'menunggu'  => 'Menunggu',
            'usulan'    => 'Usulan jadwal',
            'fu'        => 'Sudah di-FU',
            'rsv'       => 'Ada reservasi',
            'batal'     => 'Reservasi batal',
            'telat'     => 'Terlewat',
            'telatlama' => 'Terlewat lebih dari 14 hari',
        ] as $k => $l)
            <span><i class="ort-legend-dot ort-chip--{{ $k }}"></i>{{ $l }}</span>
        @endforeach
    </div>
</div>

<script>
/* Buka-tutup pemilih bulan. Mengikuti pola [data-ort-filter] di _filter.blade
   - satu pendengar, tanpa pustaka.

   Yang ditambah dibanding pola itu: menutup saat klik di luar dan saat Escape.
   Panel ini MENGAMBANG di atas grid kalender, jadi dibiarkan terbuka ia
   menutupi tanggal - sementara panel filter mendorong isi ke bawah dan tidak
   pernah menghalangi apa pun. */
(function () {
    var tombol = document.querySelector('[data-ort-bulan]');
    var panel  = document.querySelector('[data-ort-bulan-panel]');
    if (!tombol || !panel) return;

    function setel(buka) {
        panel.hidden = !buka;
        tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
    }

    tombol.addEventListener('click', function (e) {
        e.stopPropagation();
        setel(panel.hidden);
    });

    document.addEventListener('click', function (e) {
        if (!panel.hidden && !panel.contains(e.target)) setel(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { setel(false); tombol.focus(); }
    });
})();
</script>
