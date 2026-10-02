{{--
    Tabel daftar kerja harian — satu baris = satu jadwal kontrol.

    Tujuh kolom + toggle. DATANG dan CABANG sengaja tidak ada: baris yang tampil
    selalu kontrol yang belum dikunjungi (isinya akan selalu X), dan di tab
    cabang sendiri cabangnya selalu sama. Keduanya lengkap di drawer detail.

    ⚠ ATURAN DARI DESIGN BRIEF §22 — JANGAN DILANGGAR LAGI:
      • TIDAK ADA baris RM/bracket di baris List. Kolom PASIEN maksimal tiga
        baris: nama · WhatsApp+telepon · badge (opsional). RM hanya muncul di
        panel yang dibuka, antrean, tab cabang lain, dan drawer.
      • TIDAK ADA teks instruksi "Klik baris untuk buka ringkas…".
      • TIDAK ADA kepala kelompok di tab List — "Semua" hanya DIURUTKAN
        Terlewat → Hari ini/Minggu ini → Mendatang, bukan dikelompokkan.
        Kepala kelompok hanya ada di antrean penjadwalan (§10).
--}}
@php
    $chipLabel = [
        'menunggu'        => ['Menunggu',        'menunggu'],
        'sudah_fu'        => ['Sudah di-FU',     'fu'],
        'ada_reservasi'   => ['Ada reservasi',   'rsv'],
        'reservasi_batal' => ['Reservasi batal', 'batal'],
    ];

    $tombolLabel = [
        'follow_up'      => 'Catat Follow-up',
        'buat_reservasi' => 'Buat Reservasi',
    ];
@endphp

<div class="st-card" style="margin-top:13px;" data-ort-tabel>
    <div class="ort-tablewrap">

        <div class="ort-thead">
            <div class="ort-grid">
                <div>Pasien</div>
                <div>Dokter</div>
                {{-- Satu kalimat yang menutup ambiguitas paling mahal di modul ini:
                     kolom ini PENGINGAT, bukan janji. Tanpa penjelasan ini,
                     tanggal di sini pernah dibaca sebagai kesepakatan dengan
                     pasien, dan dari salah baca itu lahir jepitan yang
                     menyembunyikan 26 dari 39 pasien terlambat. --}}
                <div title="Kapan pasien ini sudah waktunya kontrol, dihitung dari kontrol terakhir ditambah irama kontrolnya. Ini pengingat, bukan janji — janji yang sudah disepakati ada di kolom Reservasi."
                     style="cursor:help;">Jadwal</div>
                <div>Follow-up</div>
                <div>Reservasi</div>
                <div>Status</div>
                <div>Tindakan berikutnya</div>
                <div></div>
            </div>
        </div>

        @forelse ($baris as $r)
            @php
                $telat     = ! $r->closed_at && $r->sch_date < $hariIni->toDateString();
                $hariLewat = $telat ? \Carbon\Carbon::parse($r->sch_date)->diffInDays($hariIni) : 0;
                $parah     = $hariLewat > 14;

                /* Keadaan reservasi, dihitung SEKALI karena dipakai kolom Status
                   dan kolom Tindakan sekaligus.

                   Bedanya penting: reservasi yang tanggalnya belum lewat berarti
                   keterlambatan jadwal SEDANG DITANGANI — pasien sudah punya
                   janji. Reservasi yang tanggalnya sudah lewat sementara
                   kunjungannya belum masuk berarti janji itu meleset, dan itu
                   justru masalah, bukan penyelesaian.

                   Waitlist (punya reservation_id tapi tanpa tanggal) tidak
                   menangani apa pun — belum ada janji yang bisa ditunggu. */
                $hariIniStr   = $hariIni->toDateString();
                $rsvAktif     = $r->reservation_id && $r->rsv_status !== 'cancelled';
                $rsvMenangani = $rsvAktif && $r->rsv_date && $r->rsv_date >= $hariIniStr;
                $rsvLewat     = $rsvAktif && $r->rsv_date && $r->rsv_date <  $hariIniStr;
                $rsvWaitlist  = $rsvAktif && ! $r->rsv_date;

                /* Follow-up hanya boleh dibatalkan selama reservasinya BELUM
                   jadi — lihat alasannya di _baris_terbuka. Dihitung di sini
                   supaya tooltip tanggal, tooltip tanda ⌄, dan tombol di panel
                   tidak bisa berbeda pendapat. */
                $bolehBatalFu = $r->fu_date && (! $r->reservation_id || $r->rsv_status === 'cancelled');

                // Status DIHITUNG. Derajat keterlambatan datang dari UMUR baris,
                // bukan dari chipnya — baris "Reservasi batal" yang 39 hari lewat
                // tetap harus beraksen merah dan terhitung di ">14 hari".
                $key = match (true) {
                    /* ⬇ Chip 'usulan' DIBUANG dari tangga ini.

                         Dulu ia berdiri paling atas, jadi jatuh tempo yang sudah
                         lewat tetap berbunyi "Usulan jadwal" alih-alih "Terlewat".
                         Kolom Jadwal adalah jatuh tempo, dan jatuh tempo yang
                         lewat berarti pasien telat — siapa pun yang menuliskan
                         tanggalnya. Bersama lantai MIN_MAJU_HARI yang juga sudah
                         dibuang, chip inilah lapis kedua yang menyembunyikan 26
                         dari 39 pasien terlambat di DB pengembangan. */
                    $r->rsv_status === 'cancelled' => 'reservasi_batal',
                    /* ⬇ Sengaja DI ATAS $telat. Begitu pasien punya janji yang
                         belum lewat, "Terlewat 7 hari" salah baca: jadwalnya
                         memang lewat, tapi sudah ada penanganannya, dan Sales
                         tidak punya tugas apa pun atas baris ini. Reservasi yang
                         tanggalnya SUDAH lewat sengaja tidak ikut — ia jatuh ke
                         $telat di bawah, karena janjinya meleset. */
                    $rsvMenangani                  => 'ada_reservasi',
                    $telat                         => $parah ? 'telatlama' : 'telat',
                    (bool) $r->reservation_id      => 'ada_reservasi',
                    (bool) $r->fu_date             => 'sudah_fu',
                    default                        => 'menunggu',
                };

                if ($key === 'telat' || $key === 'telatlama') {
                    $chipTeks  = 'Terlewat ' . $hariLewat . ' hari';
                    $chipKelas = $key;
                    /* Siklus terlewat ≥ SIKLUS_ESKALASI ikut disebut: "Terlewat 45
                       hari" terbaca sama untuk pasien berinterval 21 (sudah 2 siklus)
                       dan 60 (belum satu). Rumusnya statis di OrtCase — $r di sini
                       stdClass dari query builder, bukan model. */
                    $siklus = \App\Models\OrtCase::hitungSiklusTerlewat(
                        $r->last_visit_date, $r->median_gap_days !== null ? (int) $r->median_gap_days : null, $hariIniStr
                    );
                    if ($siklus >= \App\Models\OrtCase::SIKLUS_ESKALASI) {
                        $chipTeks .= ' · ' . $siklus . ' siklus';
                    }
                } elseif ($key === 'ada_reservasi' && $r->rsv_date === $hariIniStr) {
                    // Hari-H lebih berguna daripada label umum.
                    $chipTeks  = 'Reservasi hari ini';
                    $chipKelas = 'rsv';
                } else {
                    [$chipTeks, $chipKelas] = $chipLabel[$key];
                }

                /* Tangga tindakan: tepat satu tombol, selalu anak tangga
                   berikutnya. KEMBARAN OrtControl::tindakanBerikutnya() —
                   keduanya wajib sama persis.

                   "Konfirmasi jadwal" dibuang. Tidak ada yang perlu
                   dikonfirmasi dari sebuah pengingat: menyetujui tanggal jatuh
                   tempo tidak mengubah apa pun dan tidak memberi tahu pasien.
                   Kesepakatan yang sebenarnya tercatat saat reservasi dibuat,
                   dan itu anak tangga kedua di bawah. */
                $aksi = match (true) {
                    ! $r->fu_date                                          => 'follow_up',
                    ! $r->reservation_id || $r->rsv_status === 'cancelled' => 'buat_reservasi',
                    default                                                => null,
                };

                $wa      = \App\Support\Phone::toWa($r->patient_phone);
                $masuk   = $masukDari[$r->ort_case_id] ?? null;
                $selesai = $usulanSelesai[$r->id] ?? null;
            @endphp

            <div class="ort-tr {{ $parah ? 'ort-tr--bad' : ($telat ? 'ort-tr--warn' : '') }}
                        {{ $selesai ? 'ort-tr--nb' : '' }}"
                 data-ort-row="{{ $r->id }}">
                <div class="ort-grid">

                    {{-- PASIEN — nama, WhatsApp, badge. Tanpa RM, tanpa bracket. --}}
                    <div style="min-width:0;">
                        <div class="ort-name">{{ \Illuminate\Support\Str::title($r->patient_name) }}</div>

                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                               class="ort-wa" onclick="event.stopPropagation()">
                                <i class="fab fa-whatsapp"></i> {{ $r->patient_phone }}
                            </a>
                        @else
                            <span class="ort-wa ort-wa--bad"
                                  title="Nomor tidak valid, tidak bisa dibuka di WhatsApp">
                                <i class="fas fa-exclamation-triangle"></i> {{ $r->patient_phone ?: 'tanpa nomor' }}
                            </span>
                        @endif

                        @if ($masuk || $r->unscheduled)
                            <div class="ort-badges">
                                @if ($masuk)
                                    <span class="ort-badge ort-badge--transfer">
                                        <i class="fas fa-exchange-alt" style="font-size:8px;"></i>
                                        Baru masuk dari {{ $peta[$masuk]['nama'] ?? $masuk }}
                                    </span>
                                @endif
                                @if ($r->unscheduled)
                                    {{-- `unscheduled` = kunjungan ini tidak cocok dengan baris
                                         jadwal mana pun di sistem kita. Ia TIDAK mengatakan apa
                                         pun soal reservasi — reservasi hidup di tabel lain
                                         (`raw_cus_reservation`, milik Sweet Treats, bukan
                                         Omnicare) dan hampir seluruhnya belum pernah dijodohkan.
                                         Teks lama "Datang tanpa reservasi" mengaku tahu hal yang
                                         tidak kita ketahui. --}}
                                    <span class="ort-badge ort-badge--walkin"
                                          title="Kunjungan ini tidak cocok dengan jadwal mana pun di sistem. Bukan berarti pasien tidak memesan.">Tanpa jadwal tercatat</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- DOKTER --}}
                    <div class="ort-cell ort-clamp2" title="{{ $r->doctor_name }}">{{ $r->doctor_name ?: '—' }}</div>

                    {{-- JADWAL — TANGGAL jatuh tempo (keputusan Reza, 5 Okt 2026).

                         Sempat ditampilkan sebagai PERKIRAAN ("Pekan ini",
                         "± 3 pekan lagi", "Sudah waktunya") supaya tidak memberi
                         ketepatan yang tidak kita punya. Itu dibatalkan karena
                         efeknya di layar kerja justru merusak:

                           masa depan dapat EMPAT tingkat — Hari ini, Pekan ini,
                           ± N pekan lagi, ± Nov — tapi masa lalu cuma SATU ember,
                           "Sudah waktunya".

                         Daftar ini diurutkan dari jatuh tempo terdekat, jadi semua
                         yang terlambat menumpuk di paling atas — dan di situ
                         persis mereka kehilangan pembeda. Terukur di DB ini:
                         enam baris teratas semuanya berbunyi "Sudah waktunya",
                         padahal yang satu telat 32 hari dan lima sisanya 2–3 hari.
                         Enam belas kali lebih genting, tampil sama persis.

                         Itu penyakit yang sama dengan lantai MIN_MAJU_HARI yang
                         sudah dicabut: urutan kegentingan hilang tepat di baris
                         yang paling perlu dibedakan. Yang satu meratakan DATANYA,
                         yang satu lagi meratakan TAMPILANNYA.

                         Tanggal membedakan sendiri tanpa perlu tingkatan.

                         ⚠ Kejujurannya dipindah, bukan dibuang:
                           · `.ort-proposal` — garis putus amber untuk tanggal yang
                             masih hitungan mesin (is_proposal). Begitu Sales
                             menyentuhnya, garis itu hilang.
                           · tooltip membawa kalimat perkiraannya, jadi "kira-kira
                             kapan" tetap terbaca tanpa memakan lebar kolom. --}}
                    {{-- data-ort-jadwal: penanda untuk uji. Sebelumnya uji menangkap
                         sel ini lewat regex `<div class="ort-cell">teks</div>` yang
                         putus begitu selnya punya elemen anak — dan "tidak ketemu"
                         terbaca sama dengan "isinya salah". --}}
                    <div class="ort-cell {{ $telat ? 'ort-late' : '' }}" data-ort-jadwal>
                        <span class="tabular {{ $r->is_proposal ? 'ort-proposal' : '' }}"
                              title="{{ \App\Models\OrtControl::perkiraanJatuhTempo(\Carbon\Carbon::parse($r->sch_date), $hariIni) }}{{ $r->is_proposal ? ' · dihitung sistem, belum disentuh Sales' : '' }}">{{ \Carbon\Carbon::parse($r->sch_date)->translatedFormat("j M 'y") }}</span>
                    </div>

                    {{-- FOLLOW-UP --}}
                    <div class="ort-cell">
                        @if ($r->fu_date)
                            {{-- Tanggalnya saja. Tombol batalnya SENGAJA tidak di sini:
                                 kolom ini sempit dan langsung bersebelahan dengan kolom
                                 Reservasi, jadi kancing tambahan terbaca menumpuk. Jalan
                                 pulangnya ada di panel baris (tanda ⌄), dan tooltip di
                                 bawah ini yang menunjukkan jalannya. --}}
                            @php
                                /* ⚠ `· WhatsApp` dulu DI-HARDCODE di sini, padahal
                                   kanalnya bisa apa saja. Dropdown kanal sudah
                                   dibuang (5 Okt 2026); yang tersisa ditampilkan
                                   hanya kalau barisnya memang punya nilainya.
                                   HASIL follow-up ikut masuk tooltip — kolomnya
                                   112px, tidak muat teks, tapi menyimpannya tanpa
                                   pernah menampilkannya sama saja membuangnya. */
                                $fuHasil = $r->fu_result
                                    ? (\App\Models\OrtControl::FU_HASIL[$r->fu_result] ?? $r->fu_result)
                                    : null;
                                $fuTip = 'Dicatat ' . \Carbon\Carbon::parse($r->fu_date)->translatedFormat("j M 'y")
                                       . ' oleh ' . ($r->fu_by ?: 'Sales')
                                       . ($r->fu_channel ? ' · ' . (\App\Models\OrtControl::FU_KANAL[$r->fu_channel] ?? $r->fu_channel) : '')
                                       . ($fuHasil ? ' · ' . $fuHasil : '')
                                       . ($r->fu_note ? ' — ' . $r->fu_note : '')
                                       . ($bolehBatalFu ? ' · untuk membatalkan, buka panel baris dengan tanda ⌄ di ujung kanan' : '');
                            @endphp
                            <span class="ort-fu" title="{{ $fuTip }}">
                                <i class="far fa-comment"></i>
                                <span class="tabular">{{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat('j M') }}</span>
                                {{-- Titik kecil menandai baris yang hasilnya BUKAN
                                     "sudah dihubungi, bersedia" — supaya Sales tahu
                                     ada sesuatu di tooltip tanpa harus menyapu semua. --}}
                                @if ($fuHasil && $r->fu_result !== \App\Models\OrtControl::FU_DIHUBUNGI)
                                    <i class="fas fa-circle" style="font-size:4px;vertical-align:middle;"></i>
                                @endif
                            </span>
                        @else
                            <span class="ort-x">X</span>
                        @endif
                    </div>

                    {{-- RESERVASI --}}
                    <div class="ort-cell">
                        @if ($r->rsv_status === 'cancelled')
                            <span class="ort-struck tabular">{{ $r->rsv_date ? \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') : '—' }}</span>
                            <span class="ort-cell--sub ort-sub-red">
                                dibatalkan {{ \Carbon\Carbon::parse($r->rsv_synced_at ?? $r->sch_date)->translatedFormat('j M') }}
                            </span>
                        @elseif ($r->reservation_id && ! $r->rsv_date)
                            <span class="ort-waitlist">Waitlist</span>
                        @elseif ($r->rsv_date)
                            <span class="tabular">{{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') }}</span>
                            <span class="ort-cell--sub">{{ $r->rsv_status === 'confirmed' ? 'dikonfirmasi' : 'menunggu' }}</span>
                        @else
                            <span class="ort-x">X</span>
                        @endif
                    </div>

                    {{-- STATUS --}}
                    <div><span class="ort-chip ort-chip--{{ $chipKelas }}">{{ $chipTeks }}</span></div>

                    {{-- TINDAKAN BERIKUTNYA — tepat satu tombol + WhatsApp --}}
                    <div class="ort-actions">
                        @if ($aksi === 'buat_reservasi')
                            <a href="{{ $tautan(['sheet' => 'reservasi', 'kontrol' => $r->id]) }}"
                               class="ort-btn" style="flex:1;" onclick="event.stopPropagation()">{{ $tombolLabel[$aksi] }}</a>
                        @elseif ($aksi === 'follow_up')
                            {{-- Sekali klik, langsung simpan (brief §11). Setelah ini baris
                                 naik sendiri ke 'Buat Reservasi' karena fu_date sudah terisi.

                                 Tombol ini SENGAJA tidak membuka WhatsApp. Sempat dicoba
                                 (satu klik = buka WA + catat) lalu dibatalkan: mencatat
                                 follow-up dan menghubungi pasien adalah dua keputusan yang
                                 berbeda, dan menggabungkannya memaksa WhatsApp terbuka
                                 setiap kali Sales cuma ingin menandai. Tombol WhatsApp
                                 bundar di sebelah kanan tetap jadi jalan menghubungi. --}}
                            {{-- Sejak 4 Okt 2026 bukan lagi submit sekali klik: membuka
                                 lembar ?sheet=follow-up yang mencatat HASIL (pilihan tetap),
                                 kanal, tanggal, dan catatan. Pola yang sama dengan Buat
                                 Reservasi di atas — jenis() di list.blade.php mengenali
                                 href ini sebagai lembar. --}}
                            <a href="{{ $tautan(['sheet' => 'follow-up', 'kontrol' => $r->id]) }}"
                               class="ort-btn" style="flex:1;" onclick="event.stopPropagation()"
                               title="Catat hasil follow-up pasien ini">{{ $tombolLabel[$aksi] }}</a>
                        @elseif ($rsvLewat)
                            {{-- Reservasinya lewat tapi kunjungannya belum masuk.
                                 Ini BUKAN "tidak ada tugas" — janjinya meleset, dan
                                 seseorang harus memutuskan. Tidak diberi tombol:
                                 keputusannya (hadir / tidak hadir / jadwal ulang)
                                 tidak bisa ditebak dari sini, dan ketiganya sudah
                                 tersedia di panel ⌄. --}}
                            <span class="ort-nodo ort-nodo--warn"
                                  title="Reservasi {{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat("j M 'y") }} sudah lewat tapi kunjungannya belum masuk. Tarikan malam mengisinya sendiri kalau pasien memang datang; kalau tidak, tandai tidak hadir lewat panel ⌄.">
                                <i class="fas fa-exclamation-circle"></i>
                                <span class="ort-nodo__t">Cek kedatangan</span>
                            </span>
                        @elseif ($rsvWaitlist)
                            <span class="ort-nodo"
                                  title="Masuk waitlist, tanggalnya belum ada — tidak ada tugas Sales sampai jadwalnya keluar.">
                                <i class="far fa-clock"></i>
                                <span class="ort-nodo__t">Menunggu jadwal</span>
                            </span>
                        @elseif ($rsvMenangani)
                            {{-- Strip telanjang dulu dipakai di sini dan tidak
                                 memberi tahu apa-apa: pembacanya tidak bisa
                                 membedakan "tidak ada tugas" dari "belum sempat
                                 diisi". Keadaannya sekarang diberi nama. --}}
                            <span class="ort-nodo"
                                  title="Reservasi {{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat("j M 'y") }} · tidak ada tugas Sales — kolom Datang terisi sendiri dari rekam medis tiap malam.">
                                <i class="far fa-clock"></i>
                                {{-- "Menunggu pasien", bukan "Menunggu kedatangan":
                                     yang panjang butuh 163px sementara kolomnya cuma
                                     menyediakan 151px, jadi ekornya mengalir ke bawah
                                     tombol WhatsApp. Artinya tidak berubah — chip STATUS
                                     di sebelahnya sudah berbunyi "Ada reservasi". --}}
                                <span class="ort-nodo__t">{{ $r->rsv_date === $hariIniStr ? 'Datang hari ini' : 'Menunggu pasien' }}</span>
                            </span>
                        @else
                            <span class="ort-nodo"
                                  title="Tidak ada tugas Sales atas baris ini.">
                                <i class="far fa-clock"></i>
                                <span class="ort-nodo__t">Tidak ada tugas</span>
                            </span>
                        @endif

                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                               class="ort-btn--wa" title="Buka WhatsApp {{ $r->patient_name }}"
                               onclick="event.stopPropagation()"><i class="fab fa-whatsapp"></i></a>
                        @else
                            <span class="ort-btn--wa ort-btn--wa-bad"
                                  title="Nomor tidak valid, tidak bisa dibuka di WhatsApp"><i class="fas fa-exclamation-triangle"></i></span>
                        @endif
                    </div>

                    {{-- Toggle bundar --}}
                    <div style="display:flex;justify-content:center;">
                        <button type="button" class="ort-toggle" data-ort-toggle="{{ $r->id }}"
                                title="Ringkas pasien, 4 kontrol terakhir, dan aksi lain — pindahkan cabang, tandai tidak hadir{{ $bolehBatalFu ? ', batalkan follow-up' : '' }}"><i class="fas fa-chevron-down"></i></button>
                    </div>
                </div>
            </div>

            {{-- Baris penutup usulan — menempel pada baris di atasnya, dibaca
                 sebagai satu blok. Itu sebabnya baris di atas kehilangan
                 border-bottom-nya (kelas ort-tr--nb). --}}
            @if ($selesai)
                {{-- title menyebut pasiennya: penyatuan visual sudah cukup untuk
                     mata, tapi ini pengaman kalau baris di atasnya kebetulan
                     tergulir keluar layar. --}}
                <div class="ort-done {{ $selesai['absen'] ? 'ort-done--amber' : '' }}"
                     title="{{ \Illuminate\Support\Str::title($r->patient_name) }} — {{ $selesai['teks'] }}{{ $selesai['catatan'] ? ' · ' . $selesai['catatan'] : '' }}">
                    <i class="fas {{ $selesai['absen'] ? 'fa-exclamation-circle' : 'fa-check' }}"></i>
                    <span>{{ $selesai['teks'] }}</span>
                    <span class="ort-done__note">{{ $selesai['catatan'] }}</span>
                </div>
            @endif

            @include('crud.ortho._baris_terbuka', ['r' => $r, 'wa' => $wa, 'telat' => $telat, 'bolehBatalFu' => $bolehBatalFu])

        @empty
            <div style="padding:14px 0;">
                @include('crud.ortho._kosong', ['adaFilter' => $adaFilter])
            </div>
        @endforelse

    </div>

    {{-- Pita kaki dibuang. Kalimatnya menerangkan cara kerja mesin, bukan
         keadaan daftar ini — dan "Simulasikan tarikan malam ini" cuma <span>
         peraga sisa serah-terima desain: tidak pernah punya form maupun route,
         jadi mengkliknya tak melakukan apa-apa. Tarikan malam yang sebenarnya
         dijadwalkan di Kernel pukul 04.00. --}}
    @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'jadwal', 'perHal' => $perHal])
</div>

<script>
/* Klik baris atau toggle → buka/tutup panel ringkas. Tautan di dalam baris
   sudah stopPropagation sendiri, jadi klik WhatsApp tidak ikut membuka. */
(function () {
    /* ── Panel ringkas: buka/tutup ──────────────────────────────────────────
       Klik baris atau toggle bundar. Tautan di dalam baris sudah
       stopPropagation sendiri, jadi klik WhatsApp tidak ikut membuka. */
    function buka(id, dari) {
        var panel = document.querySelector('[data-ort-expand="' + id + '"]');
        if (!panel) return;
        var jadiBuka = panel.hasAttribute('hidden');

        document.querySelectorAll('[data-ort-expand]').forEach(function (p) { p.hidden = true; });
        document.querySelectorAll('[data-ort-row]').forEach(function (t) { t.classList.remove('ort-tr--open'); });
        document.querySelectorAll('[data-ort-toggle]').forEach(function (b) { b.classList.remove('is-open'); });

        panel.hidden = !jadiBuka;
        if (jadiBuka) {
            dari.classList.add('ort-tr--open');
            var tombol = dari.querySelector('[data-ort-toggle]');
            if (tombol) tombol.classList.add('is-open');
        }
    }

    /* ── Segmented control 3 arah di panel yang dibuka ──────────────────────
       Menukar tampilan hanya mengganti elemen mana yang tersembunyi — tidak ada
       permintaan ke server, tidak ada render ulang. Pilihannya diingat per
       baris supaya Sales yang terbiasa satu tampilan tidak memilih ulang. */
    function pakaiTampilan(id, v) {
        var mini = document.querySelector('[data-ort-mini="' + id + '"]');
        var seg  = document.querySelector('[data-ort-seg="' + id + '"]');
        if (!mini || !seg) return;

        seg.querySelectorAll('button').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.v === v);
        });

        mini.querySelectorAll('[data-v]').forEach(function (el) {
            el.hidden = el.dataset.v !== v;
        });

        mini.querySelectorAll('[data-ort-minirow]').forEach(function (row) {
            var href = v === 'fu' ? row.dataset.hrefFu : (v === 'rsv' ? row.dataset.hrefRsv : '');
            row.classList.toggle('is-link', !!href);
            row.dataset.aktif = href || '';
        });

        try { sessionStorage.setItem('ortMiniView', v); } catch (e) {}
    }

    /* ── Pendaftaran listener: SEKALI saja, saat halaman dimuat ─────────────
       ⚠ Jangan pindahkan blok ini ke dalam buka(). Kalau dipanggil tiap baris
       dibuka, listener-nya menumpuk (satu klik = banyak handler) dan pemulihan
       tampilan terakhir di bawah tidak pernah jalan. Sudah pernah kejadian. */
    document.querySelectorAll('[data-ort-row]').forEach(function (row) {
        row.addEventListener('click', function () { buka(row.dataset.ortRow, row); });
    });

    document.querySelectorAll('[data-ort-toggle]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            buka(b.dataset.ortToggle, b.closest('[data-ort-row]'));
        });
    });

    document.querySelectorAll('[data-ort-seg]').forEach(function (seg) {
        seg.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-v]');
            if (!b) return;
            e.stopPropagation();
            pakaiTampilan(seg.dataset.ortSeg, b.dataset.v);
        });
    });

    document.querySelectorAll('[data-ort-mini]').forEach(function (mini) {
        mini.addEventListener('click', function (e) {
            var row = e.target.closest('[data-ort-minirow]');
            if (!row || !row.dataset.aktif) return;
            e.stopPropagation();
            window.open(row.dataset.aktif, '_blank', 'noopener');
        });
    });

    /* Kembalikan tampilan terakhir yang dipilih Sales. */
    var ingat = null;
    try { ingat = sessionStorage.getItem('ortMiniView'); } catch (e) {}
    if (ingat && ingat !== 'semua') {
        document.querySelectorAll('[data-ort-seg]').forEach(function (seg) {
            pakaiTampilan(seg.dataset.ortSeg, ingat);
        });
    }
})();
</script>
