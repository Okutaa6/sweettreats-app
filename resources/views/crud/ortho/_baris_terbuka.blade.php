{{--
    Panel yang muncul saat baris diklik — bentuk TIGA KOLOM dari
    prompt_jadwal_kontrol.md Phase 2.

      Profile    siapa pasien ini, lalu dua kendali: Transfer & Active/Inactive
      Kontrol    riwayat KUNJUNGAN bernomor — tanggal, cabang, dokter
      Follow Up  riwayat telepon bernomor — tanggal, oleh siapa

    Ketiganya menjawab pertanyaan yang berbeda dan tidak saling menggantikan:
    "siapa pasien ini", "pola kedatangannya bagaimana", "sudah berapa kali
    dikejar dan oleh siapa".

    ────────────────────────────────────────────────────────────────────────────
    YANG BERUBAH DARI PANEL SEBELUMNYA, DAN KE MANA PERGINYA
    ────────────────────────────────────────────────────────────────────────────
    Panel lama berisi delapan fakta + garis waktu mini tiga tampilan. Tiga
    fakta paling menentukan TIDAK dibuang, cuma pindah tempat:

      · Kehadiran, irama, siklus terlewat → satu baris ringkas di kepala kolom
        KONTROL. Di situlah tempatnya: ketiganya menerangkan POLA kedatangan,
        dan sekarang berdiri tepat di atas daftar kunjungan yang membentuknya.
      · Chip status jadwal → bidang di kolom Profile.

    ⚠ Dan lima AKSI yang tidak ada di rancangan tiga kolom tetap dipertahankan
      di kaki panel — bukan karena enggan membuang, tapi karena "Tandai tidak
      hadir", "Batalkan follow-up", dan "Geser pengingat" TIDAK PUNYA JALAN LAIN
      di seluruh layar. Membuangnya berarti membuat tiga keputusan sah jadi
      mustahil, diam-diam.

    ⚠ Riwayatnya datang dari $riwayatVisit / $riwayatFu yang dihitung SEKALI
      untuk seluruh halaman di controller. Jangan memanggil kueri dari sini —
      panel ini dirender untuk SETIAP baris, termasuk yang tidak pernah dibuka.
--}}
@php
    /* ⚠ Panel ini dirender DUA JALAN: di-@include dari _tabel (dulu), dan
       sejak 6 Okt 2026 sebagai fragmen tersendiri saat barisnya dibuka.
       Karena itu ia tidak boleh bergantung pada apa pun yang lahir di loop
       _tabel — semuanya dihitung ulang di sini dari $r.

       Rumusnya SENGAJA kembar dengan _tabel untuk tiga nilai di bawah. Kembar
       itu diterima karena ketiganya satu baris dan diturunkan dari kolom yang
       sama; yang TIDAK boleh kembar adalah kalimat (lihat
       OrtControl::keadaanReservasi). */
    $telat        = $telat        ?? (! $r->closed_at && $r->sch_date < $hariIni->toDateString());
    $wa           = $wa           ?? \App\Support\Phone::toWa($r->patient_phone);
    $bolehBatalFu = $bolehBatalFu ?? ($r->fu_date && (! $r->reservation_id || $r->rsv_status === 'cancelled'));

    $kunjungan = ($riwayatVisit ?? [])[$r->ort_case_id] ?? [];
    $fuRiwayat = ($riwayatFu ?? [])[$r->ort_case_id] ?? [];

    $kehadiran = ($r->control_count ?? 0) > 0
        ? $r->visit_count . ' dari ' . $r->control_count . ' kontrol'
        : 'belum ada kontrol';

    $siklusTerlewat = \App\Models\OrtCase::hitungSiklusTerlewat(
        $r->last_visit_date,
        $r->median_gap_days !== null ? (int) $r->median_gap_days : null,
        $hariIni->toDateString()
    );

    $pasifPanel = isset($r->sales_active) && ! $r->sales_active;

    /* LIHAT-SAJA untuk pasien yang cabang pasangnya kita tapi sedang DIPEGANG
       cabang lain (tab "Pasien di cabang lain"). Diputuskan di controller —
       lihat OrthoController::panelBaris() — karena gerbangnya sama dengan yang
       memutuskan panel ini boleh dibuka sama sekali.

       ⚠ Tombolnya TIDAK DIRENDER, bukan dinonaktifkan. Tombol mati tetap
         mengundang klik, dan setiap endpoint tulis modul ini toh menjawab 403
         (pastikanMilikCabang / abort_unless owner) — jadi satu-satunya hasil
         yang mungkin adalah Sales mencoba lalu ditolak tanpa penjelasan. */
    $lihatSaja = $hanyaLihat ?? false;
@endphp

<div class="ort-expand" data-ort-expand="{{ $r->id }}" hidden>
    <div class="ort-p2">

        {{-- ════════ PROFILE ════════════════════════════════════════════ --}}
        <div class="ort-p2__col">
            <div class="ort-p2__head">Profile</div>
            <div class="ort-p2__body">

                <div class="ort-p2f">
                    <div class="ort-p2f__k">No. Rekam Medis</div>
                    <div class="ort-p2f__v tabular">{{ $r->omnicare_pid ?: 'belum tertaut' }}</div>
                </div>

                <div class="ort-p2f">
                    <div class="ort-p2f__k">Bracket · Rahang</div>
                    {{-- Keduanya SERING kosong, dan itu disengaja: OrtItemRules
                         memilih null daripada menebak ("null lebih jujur
                         daripada menebak keduanya"). Jadi "—" di sini bukan
                         data hilang, melainkan katalog yang memang tidak
                         menyebutkannya. --}}
                    <div class="ort-p2f__v">
                        {{ $r->bracket_type ?: '—' }}
                        @if ($r->bracket_arch)
                            · {{ ['atas' => 'Atas', 'bawah' => 'Bawah', 'keduanya' => 'Atas & Bawah'][$r->bracket_arch] ?? $r->bracket_arch }}
                        @endif
                    </div>
                </div>

                <div class="ort-p2f">
                    <div class="ort-p2f__k">Tanggal Pasang Behel</div>
                    {{-- Penanda ± wajib ada: 45,7% kasus prod tanggalnya
                         DIPERKIRAKAN penyemai dari kunjungan ortho pertama yang
                         terlihat, bukan dari baris pemasangan. Menampilkannya
                         sama persis dengan tanggal yang pasti akan membuat
                         hampir separuh data terbaca lebih yakin dari yang
                         sebenarnya. --}}
                    <div class="ort-p2f__v tabular">
                        @if ($r->install_is_estimated)
                            <span class="ort-kira" title="Tanggal pemasangan tidak tercatat di rekam medis. Ini perkiraan dari kunjungan ortho pertama yang terlihat.">±</span>
                        @endif
                        {{ \Carbon\Carbon::parse($r->install_date)->translatedFormat('j M Y') }}
                    </div>
                </div>

                {{-- Chip "Status jadwal" DIBUANG 6 Okt 2026. Dua alasan, dan
                     keduanya nyata:

                     · Ia sudah tidak menambah apa-apa. Isinya (menunggu /
                       sudah di-FU / ada reservasi / terlewat N hari) kini
                       terbaca langsung dari tiga kolom tabel yang memang
                       menampilkan ketiganya. Itu sebabnya rancangan tiga kolom
                       tidak memuatnya sejak awal.
                     · Ia satu-satunya yang memaksa panel ini bergantung pada
                       nilai yang lahir di loop _tabel — dan ketergantungan itu
                       yang membuat panel tidak bisa dimuat sendiri.

                     Tanda ESKALASI tidak ikut hilang: ia tetap badge di kolom
                     Nama (terlihat TANPA membuka panel) dan tetap disebut di
                     ringkasan pola kolom Kontrol di bawah. --}}

                <hr class="ort-p2__hr">

                {{-- Transfer: memindahkan kepemilikan pasien. Lembarnya yang
                     sudah ada (sheet=pindahkan) meminta cabang tujuan, alasan,
                     dan pilihan "sekali saja di sana".

                     Tidak dirender dalam mode lihat-saja: memindahkan pasien yang
                     sedang dipegang cabang lain bukan keputusan cabang ini. --}}
                @unless ($lihatSaja)
                    <a href="{{ $tautan(['sheet' => 'pindahkan', 'kasus' => $r->ort_case_id]) }}"
                       class="ort-obtn" style="color:#6B2400;border-color:#E4C7AC;"
                       onclick="event.stopPropagation()"
                       title="Pindahkan pasien ini ke cabang lain — alasannya wajib, supaya perpindahannya bisa ditelusuri belakangan.">
                        <i class="fas fa-exchange-alt"></i> Transfer Pasien
                    </a>
                @endunless

                <div class="ort-p2f" @unless ($lihatSaja) style="margin-top:15px;" @endunless>
                    <div class="ort-p2f__k">Status kontrol</div>
                    {{-- ⚠ SUMBU LAIN dari chip "Status jadwal" di atas. Chip itu
                         keadaan jadwalnya (menunggu / terlewat / ada reservasi);
                         yang ini keputusan MANUSIA tentang apakah pasien masih
                         dikejar. Dan keduanya juga bukan `ort_case.status`, yang
                         fakta klinis dan diisi tarikan malam. Tiga sumbu, tiga
                         tempat, sengaja tidak dilebur. --}}
                    @if ($lihatSaja)
                        {{-- Keadaannya tetap DIKATAKAN, cuma tidak bisa diubah.
                             Menyembunyikannya sama sekali akan membuat pembaca
                             mengira pasien ini tidak punya status — padahal
                             punya, dan itu keputusan cabang pemegangnya. --}}
                        <div class="ort-p2f__v" style="margin-top:3px;">
                            {{ $pasifPanel ? 'Inactive' : 'Active' }}
                            <span class="ort-nlist__dim" style="font-weight:500;">· ditentukan cabang pemegang</span>
                        </div>
                    @else
                    <div class="ort-seg" style="margin-top:6px;" onclick="event.stopPropagation()">
                        @foreach ([1 => 'Active', 0 => 'Inactive'] as $nilai => $label)
                            @if ((int) ! $pasifPanel === $nilai)
                                <button type="button" class="ort-seg__b is-on"
                                        title="{{ $nilai
                                            ? 'Pasien ini masih dikejar kontrolnya.'
                                            : 'Pasien ini ditandai Inactive — tetap tampil, tapi turun ke bawah daftar.' }}">{{ $label }}</button>
                            @else
                                <form method="POST" action="{{ route('ortho.salesaktif', $r->ort_case_id) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm(@js($nilai
                                          ? 'Tandai pasien ini Active? Ia kembali masuk urutan kerja normal.'
                                          : 'Tandai pasien ini Inactive? Ia TETAP tampil di daftar, tapi turun ke paling bawah.'))">
                                    @csrf
                                    <input type="hidden" name="aktif" value="{{ $nilai }}">
                                    <button type="submit" class="ort-seg__b"
                                            title="{{ $nilai
                                                ? 'Kembalikan pasien ke urutan kerja normal.'
                                                : 'Tandai Inactive — ia tetap ditampilkan, hanya turun ke bawah daftar.' }}">{{ $label }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ════════ KONTROL ════════════════════════════════════════════ --}}
        <div class="ort-p2__col">
            <div class="ort-p2__head">
                {{-- "dan Reservasi" ditambahkan 7 Okt 2026: sejak lipatan tiap
                     butir menyatakan DUA sumber, kolom ini tidak lagi cuma
                     tentang kontrol. Judul yang tidak menyebut setengah isinya
                     membuat setengah itu tidak dicari orang. --}}
                Kontrol dan Reservasi
                {{-- Angka di kepala ini jumlah KONTROL yang sebenarnya (kolom
                     rollup), bukan jumlah reservasi dan bukan jumlah yang
                     terambil. Daftarnya dipotong 20 terbaru dan
                     bergulir; menampilkan "20" untuk pasien yang punya 26
                     kontrol akan terbaca seperti enam kontrolnya hilang. --}}
                @if (($r->control_count ?? 0) > 0)<span class="ort-p2__n tabular">{{ $r->control_count }}</span>@endif
            </div>
            <div class="ort-p2__body">

                {{-- Tiga angka yang menerangkan POLA kedatangan, berdiri tepat
                     di atas daftar yang membentuknya. Di panel lama ketiganya
                     tersebar di antara delapan fakta lain dan kehilangan
                     hubungannya dengan daftar ini. --}}
                <div class="ort-nlist__urut">
                    {{ $kehadiran }}
                    {{-- "irama N hari · teratur" DIBUANG dari baris ini 6 Okt 2026
                         atas permintaan Reza.

                         ⚠ Angkanya sendiri TIDAK dibuang dan tidak boleh dibuang:
                           median_gap_days menggerakkan hampir seluruh modul —
                           ambang warna badge Last Visit, urutan daftar, gerbang
                           H-3 kolom Jadwal Dokter, dan tanggal usulan. Yang hilang
                           cuma tampilannya di sini.

                         Ia tetap terbaca di drawer detail (KPI "Irama kontrol",
                         _drawer_detail:208) lengkap dengan sebaran dan
                         keterangannya, jadi pertanyaan "pasien ini iramanya
                         berapa" masih punya jawaban di layar — satu klik lebih
                         jauh, di tempat yang memang untuk menelaah satu pasien. --}}
                    @if ($siklusTerlewat >= \App\Models\OrtCase::SIKLUS_ESKALASI)
                        · <span style="color:var(--ort-bad);font-weight:700;"
                                title="Sudah {{ $siklusTerlewat }} interval kontrol lewat sejak kunjungan terakhir — perlu pendekatan berbeda, bukan sekadar telepon ulang.">terlewat {{ $siklusTerlewat }} siklus</span>
                    @endif
                    {{-- Digabung ke baris yang sama (6 Okt 2026). Dua baris redup
                         bertumpuk membuat butir pertama ketiga kolom tidak pernah
                         sejajar, dan itu yang paling kelihatan di panel tiga kolom.

                         "# = kontrol ke-berapa" turun jadi tooltip: sesudah
                         nomornya memakai `seq`, deretnya menurun 6·5·4·3·2·1 dari
                         atas — itu sudah menunjukkan sendiri bahwa ia bukan urutan
                         baris. Kalimatnya tetap ada untuk yang ragu. --}}
                    @if (count($kunjungan))
                        <span title="Nomornya kontrol ke-berapa pasien ini, bukan urutan baris — daftarnya terbaru di atas.">· terbaru di atas</span>
                    @endif
                </div>

                @if (count($kunjungan))
                    <div class="ort-nlist">
                        @foreach ($kunjungan as $i => $k)
                            @php
                                $kCabang = $peta[$k->place_id]['nama'] ?? $k->place_id;
                                /* Satu rumah untuk klaim reservasi — OrtControl::keadaanReservasi().
                                   Kalimatnya TIDAK disusun di sini, dan itu pokoknya: klaim yang
                                   sama pernah hidup di empat tempat lalu berbeda pendapat tentang
                                   baris yang sama. */
                                $kRsv   = $k->keadaanReservasi($kCabang);
                                $kVisit = $k->keadaanKunjungan();

                                /* Tautan ke RESERVASI PASIEN ITU, bukan sekadar ke tanggalnya.
                                   `?reservation_id=` menyaring daftar Reservasi ke satu baris
                                   persis (ReservationController:128). Versi lama modul ini
                                   mengirim date_from/date_to — itu mendaratkan Sales di SELURUH
                                   reservasi hari itu dan menyuruhnya mencari sendiri.

                                   ⚠ Waitlist butuh `view=waitlist`: tab bawaan 'list' sengaja
                                     MENGECUALIKAN status itu, jadi tanpa parameter ini tautannya
                                     mendarat di daftar kosong — tampak seperti reservasinya hilang. */
                                $kRsvUrl = $k->reservation_id
                                    ? route('rawreservation', array_filter([
                                        'reservation_id' => $k->reservation_id,
                                        'view' => $k->rsv_status === \App\Models\RawCusReservation::STATUS_WAITLIST
                                            ? 'waitlist' : null,
                                      ]))
                                    : null;
                            @endphp
                            <div class="ort-nlist__i">
                                {{-- ⚠ Nomornya `seq`, BUKAN urutan baris. Versi pertama
                                     menomori 1·2·3 dari atas, dan karena daftarnya
                                     terbaru-di-atas, kontrol PERTAMA pasien muncul
                                     sebagai "3" — terbaca persis terbalik: seolah itu
                                     kontrol terakhirnya. Reza membacanya begitu, dan
                                     wajar.

                                     `ort_control.seq` memang sudah "Nomor # di layar,
                                     per kasus" (lihat COMMENT kolomnya), dan matriks
                                     drawer sudah merendernya begitu sejak awal. Panel
                                     ini sempat mengarang penomoran kedua. --}}
                                <span class="ort-nlist__n tabular">#{{ $k->seq }}</span>
                                <span class="ort-nlist__t">
                                    <span class="ort-nlist__judul">
                                        <span>
                                            <span class="tabular">{{ ($k->visit_date ?: $k->sch_date)->translatedFormat('j M Y') }}</span>,
                                            {{ $kCabang }},
                                            {{-- Dokter PER KUNJUNGAN, bukan dokter kasus. Baris
                                                 yang belum sempat diisi backfill
                                                 (`ort:pull-visits --isi-dokter`) menulis "—":
                                                 menyalin dokter kasus ke sini akan membuat semua
                                                 baris terlihat ditangani orang yang sama padahal
                                                 tidak diketahui. --}}
                                            @if ($k->doctor_name)
                                                {{ $k->doctor_name }}
                                            @else
                                                <span class="ort-nlist__dim"
                                                      title="Dokter kunjungan ini belum tercatat. Jalankan `php artisan ort:pull-visits --isi-dokter` untuk mengisinya dari rekam medis.">—</span>
                                            @endif
                                        </span>

                                        <button type="button" class="ort-nlist__tgl"
                                                data-ort-kdetail="{{ $k->id }}"
                                                title="Lihat keadaan reservasi kontrol ini"><i class="fas fa-chevron-down"></i></button>
                                    </span>

                                    {{-- Dilipat sejak 6 Okt 2026 atas permintaan Reza.
                                         Sebelumnya keadaan reservasi berdiri terbuka di tiap
                                         butir, dan di pasien lama daftarnya jadi dua kali lebih
                                         tinggi oleh keterangan yang sama berulang-ulang
                                         ("Tidak tertaut reservasi" × 5). Dilipat, daftarnya
                                         kembali terbaca sebagai riwayat; yang butuh detail
                                         membukanya satu per satu. --}}
                                    <div class="ort-nlist__d" data-ort-kpanel="{{ $k->id }}" hidden>
                                        {{-- DUA baris, DUA sumber (7 Okt 2026, permintaan Reza).

                                             Sebelumnya lipatan ini cuma menyebut reservasi,
                                             sementara tanggal di baris judul di atasnya datang
                                             dari visit_date. Keduanya sering sama — dan saat
                                             sama, layar terbaca seperti mengulang satu fakta;
                                             saat BEDA, tidak ada petunjuk mana yang mana.

                                             Memisahkannya menjawab pertanyaan yang memang
                                             berbeda: "pasiennya datang belum?" (rekam medis)
                                             dan "dia dipesankan kapan?" (reservasi).

                                             ⚠ RESERVASI di atas, rekam medis di bawah — urutan
                                               ini diminta dan bukan kebetulan: tombol "Buka di
                                               Reservasi" berdiri tepat di bawah blok ini, jadi
                                               menaruh barisnya paling jauh dari tombolnya
                                               memaksa mata melompati satu baris yang tidak ada
                                               hubungannya. --}}

                                        {{-- Baris yang belum pernah dijodohkan berbunyi "Tidak
                                             tertaut reservasi" — BUKAN "tidak ada reservasi":
                                             reservasi hidup di tabel Sweet Treats sendiri dan
                                             hampir seluruhnya belum pernah dicek, jadi klaim
                                             kedua mengaku tahu yang tidak diketahui.

                                             ⚠ data-ort-rsv tetap menempel PERSIS di depan
                                               chip-dan-teksnya. OrtoTeksReservasiTest mencocokkan
                                               penanda itu diikuti langsung oleh tanggalnya;
                                               menyisipkan apa pun di antaranya memutus uji yang
                                               menjaga kedua layar tetap sepakat. --}}
                                        <div class="ort-nlist__f">
                                            <span class="ort-nlist__fk">Status reservasi</span>
                                            <span class="ort-nlist__fv ort-nlist__dim" data-ort-rsv>
                                                @unless ($k->visit_date)
                                                    <span class="ort-chip ort-chip--{{ $kRsv['nada'] }}"
                                                          style="margin-right:5px;">{{ $kRsv['label'] }}</span>
                                                @endunless
                                                {{ $kRsv['teks'] }}
                                            </span>

                                            {{-- Pintu ke modul Reservasi, kini IKON saja dan
                                                 duduk di ujung baris yang diterangkannya
                                                 (7 Okt 2026, permintaan Reza). Sebelumnya ia
                                                 tombol berteks penuh yang berdiri sendiri di
                                                 bawah kedua baris — lebar, dan jauh dari
                                                 kalimat yang menjadi alasannya ada.

                                                 ⚠ Teksnya TIDAK hilang, pindah ke aria-label.
                                                   Tombol ikon tanpa nama tidak terbaca pembaca
                                                   layar, dan dua uji memang mencocokkan kalimat
                                                   itu — keduanya tetap hijau justru karena
                                                   namanya masih ada, bukan karena kebetulan.

                                                 Tab baru, bukan pindah halaman: Sales sedang di
                                                 tengah daftar kerja dengan saringan dan halaman
                                                 tertentu, dan menukarnya berarti ia harus
                                                 membangun ulang keadaan itu saat kembali. --}}
                                            @if ($kRsvUrl)
                                                <a href="{{ $kRsvUrl }}" target="_blank" rel="noopener"
                                                   class="ort-rsvgo" onclick="event.stopPropagation()"
                                                   aria-label="Buka di Reservasi"
                                                   title="Buka di Reservasi — reservasi pasien ini, di tab baru">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            @endif
                                        </div>

                                        <div class="ort-nlist__f">
                                            <span class="ort-nlist__fk">Dari rekam medis</span>
                                            <span class="ort-nlist__fv">
                                                <span class="ort-chip ort-chip--{{ $kVisit['nada'] }}"
                                                      style="margin-right:5px;">{{ $kVisit['label'] }}</span>
                                                {{ $kVisit['teks'] }}
                                                @if ($kVisit['catatan'])
                                                    <span class="ort-nlist__dim">· {{ $kVisit['catatan'] }}</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @if (($r->control_count ?? 0) > count($kunjungan))
                        {{-- Dikatakan, bukan dibiarkan ditebak: daftar yang
                             terpotong diam-diam terbaca seperti data yang hilang. --}}
                        <div class="ort-nlist__urut" style="margin:7px 0 0;">
                            menampilkan {{ count($kunjungan) }} terbaru dari {{ $r->control_count }}
                        </div>
                    @endif
                @else
                    <div class="ort-nlist__kosong">Belum ada kontrol yang tercatat.</div>
                @endif
            </div>
        </div>

        {{-- ════════ FOLLOW UP ══════════════════════════════════════════ --}}
        <div class="ort-p2__col">
            <div class="ort-p2__head">
                Follow Up
                @if (count($fuRiwayat))<span class="ort-p2__n tabular">{{ count($fuRiwayat) }}</span>@endif
            </div>
            <div class="ort-p2__body">
                @if (count($fuRiwayat))
                    {{-- Follow-up tidak punya `seq` — penomorannya memang posisi.
                         Karena itu urutannya DIKATAKAN, tidak diserahkan ke tebakan:
                         penomoran yang menurun dari atas gampang terbaca sebagai
                         kronologi, dan di kolom sebelah salah baca itu sudah terjadi. --}}
                    <div class="ort-nlist__urut">terbaru di atas</div>
                    <div class="ort-nlist">
                        @foreach ($fuRiwayat as $i => $f)
                            <div class="ort-nlist__i">
                                <span class="ort-nlist__n tabular">{{ $i + 1 }}.</span>
                                <span class="ort-nlist__t">
                                    <span class="tabular">{{ $f->fu_date->translatedFormat('j M Y') }}</span>,
                                    oleh {{ $f->fu_by ?: 'Sales' }}
                                    @if ($f->fu_result)
                                        <span class="ort-nlist__dim">
                                            · {{ \App\Models\OrtControl::FU_HASIL[$f->fu_result] ?? $f->fu_result }}
                                        </span>
                                    @endif
                                    @if ($f->fu_note)
                                        <div class="ort-nlist__dim" style="font-size:11.5px;">{{ $f->fu_note }}</div>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- Riwayat memang MULAI dari kosong, dan itu disengaja:
                         ort_control.fu_date hanya menyimpan follow-up terakhir,
                         jadi membangun daftar ini darinya akan membuat pasien
                         yang ditelepon enam kali terlihat ditelepon sekali.
                         Riwayat yang salah lebih buruk daripada yang kosong,
                         karena ia tidak bisa dibedakan dari yang benar. --}}
                    <div class="ort-nlist__kosong">
                        Belum ada follow-up tercatat.
                        @if ($r->fu_date)
                            <div style="margin-top:4px;">
                                Terakhir dihubungi {{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat('j M Y') }} —
                                sebelum riwayat per-hari mulai dicatat.
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ════════ KAKI — aksi yang tidak punya tempat lain ═══════════ --}}
        <div class="ort-p2__kaki" onclick="event.stopPropagation()">
            <span class="ort-p2__kaki-k">{{ $lihatSaja ? 'Lihat saja' : 'Aksi lain' }}</span>

            {{-- Satu-satunya yang tetap ada dalam mode lihat-saja: ia navigasi,
                 bukan tulisan. Drawer detail punya gerbangnya sendiri yang sama. --}}
            <a href="{{ $tautanDetail($r->ort_case_id) }}" class="ort-obtn">
                Buka detail pasien <i class="fas fa-chevron-right" style="font-size:9px;"></i>
            </a>

            @if ($lihatSaja)
                <span class="ort-nlist__dim" style="font-size:11px;">
                    Pasien ini sedang dipegang cabang lain — tindakannya ada di sana.
                </span>
            @endif

            {{-- Menggeser PENGINGAT, bukan janji. Tanggal jatuh tempo tidak
                 pernah diberitahukan ke pasien; yang disepakati hidup di
                 kolom Reservasi. --}}
            {{-- `$r->id` ikut disyaratkan: panel bisa dibangun dari KASUS yang
                 belum punya kontrol terbuka (barisKasus pakai LEFT JOIN), dan
                 ketiga aksi di bawah semuanya ber-route pada id KONTROL. Tanpa
                 pagar ini route() meledak dengan "Missing parameter: id". --}}
            @unless ($lihatSaja || ! $r->id)
            @if ($r->is_proposal)
                <a href="{{ $tautan(['sheet' => 'geser', 'kontrol' => $r->id]) }}" class="ort-obtn"
                   title="Ubah kapan pasien ini dianggap jatuh tempo kontrol. Ini pengingat, bukan janji — janjinya dibuat lewat Reservasi.">
                    <i class="far fa-calendar-alt"></i> Geser pengingat
                </a>
            @endif

            {{-- Jalan pulang dari salah klik Follow Up. Di sini, bukan di baris:
                 membatalkan follow-up adalah aksi JARANG — cuma saat salah klik
                 — sementara mencatatnya puluhan kali sehari. Baris utama untuk
                 yang sering; yang korektif turun ke sini.

                 HILANG begitu reservasinya jadi, dan itu disengaja: follow-up
                 adalah anak tangga MENUJU reservasi, jadi mencabutnya sesudah
                 reservasi ada membuat tangga itu mundur tanpa alasan. Kalau
                 reservasinya dibatalkan, tombol ini muncul lagi sendiri. --}}
            @if ($bolehBatalFu ?? false)
                <form method="POST" action="{{ route('ortho.batalfollowup', $r->id) }}" style="display:inline-flex;">
                    @csrf
                    <button type="submit" class="ort-obtn"
                            title="Hapus catatan follow-up {{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat('j M') }} — baris riwayatnya ikut dibuang, dan tombol baris kembali hijau">
                        <i class="fas fa-undo" style="font-size:10px;"></i> Batalkan follow-up
                    </button>
                </form>
            @endif

            @if ($r->reservation_id || $telat)
                <form method="POST" action="{{ route('ortho.tidakhadir', $r->id) }}" style="display:inline-flex;">
                    @csrf
                    <button type="submit" class="ort-obtn ort-obtn--warn"
                            title="Tutup kontrol ini tanpa kunjungan — pasien masuk antrean penjadwalan">
                        Tandai tidak hadir
                    </button>
                </form>
            @endif
            @endunless
        </div>
    </div>
</div>
