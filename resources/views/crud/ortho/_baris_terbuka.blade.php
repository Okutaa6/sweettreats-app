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

                @isset($chipTeks)
                    <div class="ort-p2f">
                        <div class="ort-p2f__k">Status jadwal</div>
                        <div style="margin-top:4px;">
                            <span class="ort-chip ort-chip--{{ $chipKelas }}">{{ $chipTeks }}</span>
                        </div>
                    </div>
                @endisset

                <hr class="ort-p2__hr">

                {{-- Transfer: memindahkan kepemilikan pasien. Lembarnya yang
                     sudah ada (sheet=pindahkan) meminta cabang tujuan, alasan,
                     dan pilihan "sekali saja di sana". --}}
                <a href="{{ $tautan(['sheet' => 'pindahkan', 'kasus' => $r->ort_case_id]) }}"
                   class="ort-obtn" style="color:#6B2400;border-color:#E4C7AC;"
                   onclick="event.stopPropagation()"
                   title="Pindahkan pasien ini ke cabang lain — alasannya wajib, supaya perpindahannya bisa ditelusuri belakangan.">
                    <i class="fas fa-exchange-alt"></i> Transfer Pasien
                </a>

                <div class="ort-p2f" style="margin-top:15px;">
                    <div class="ort-p2f__k">Status kontrol</div>
                    {{-- ⚠ SUMBU LAIN dari chip "Status jadwal" di atas. Chip itu
                         keadaan jadwalnya (menunggu / terlewat / ada reservasi);
                         yang ini keputusan MANUSIA tentang apakah pasien masih
                         dikejar. Dan keduanya juga bukan `ort_case.status`, yang
                         fakta klinis dan diisi tarikan malam. Tiga sumbu, tiga
                         tempat, sengaja tidak dilebur. --}}
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
                </div>
            </div>
        </div>

        {{-- ════════ KONTROL ════════════════════════════════════════════ --}}
        <div class="ort-p2__col">
            <div class="ort-p2__head">
                Kontrol
                @if (count($kunjungan))<span class="ort-p2__n tabular">{{ count($kunjungan) }}</span>@endif
            </div>
            <div class="ort-p2__body">

                {{-- Tiga angka yang menerangkan POLA kedatangan, berdiri tepat
                     di atas daftar yang membentuknya. Di panel lama ketiganya
                     tersebar di antara delapan fakta lain dan kehilangan
                     hubungannya dengan daftar ini. --}}
                <div class="ort-nlist__dim" style="margin-bottom:10px;font-size:11.5px;">
                    {{ $kehadiran }}
                    @if ($r->median_gap_days)
                        · irama {{ $r->median_gap_days }} hari
                        @if ($r->gap_spread_pct !== null)
                            <span title="{{ $r->gap_spread_pct > \App\Models\OrtCase::IRAMA_RAPAT_PCT
                                ? 'Selangnya jauh berbeda-beda (sebaran ' . $r->gap_spread_pct . '% dari median) — tanyakan tanggalnya lewat telepon, jangan dipegang dari angka ini.'
                                : 'Selangnya konsisten (sebaran ' . $r->gap_spread_pct . '% dari median) — tanggal usulan untuk pasien ini bisa dipegang.' }}"
                                  style="cursor:help;">· {{ $r->gap_spread_pct > \App\Models\OrtCase::IRAMA_RAPAT_PCT ? 'tidak teratur' : 'teratur' }}</span>
                        @endif
                    @endif
                    @if ($siklusTerlewat >= \App\Models\OrtCase::SIKLUS_ESKALASI)
                        · <span style="color:var(--ort-bad);font-weight:700;"
                                title="Sudah {{ $siklusTerlewat }} interval kontrol lewat sejak kunjungan terakhir — perlu pendekatan berbeda, bukan sekadar telepon ulang.">terlewat {{ $siklusTerlewat }} siklus</span>
                    @endif
                </div>

                @if (count($kunjungan))
                    <div class="ort-nlist__urut">terbaru di atas · # = kontrol ke-berapa</div>
                    <div class="ort-nlist">
                        @foreach ($kunjungan as $i => $k)
                            @php
                                $kCabang = $peta[$k->place_id]['nama'] ?? $k->place_id;
                                /* Satu rumah untuk klaim reservasi — OrtControl::keadaanReservasi().
                                   Kalimatnya TIDAK disusun di sini, dan itu pokoknya: klaim yang
                                   sama pernah hidup di empat tempat lalu berbeda pendapat tentang
                                   baris yang sama. */
                                $kRsv = $k->keadaanReservasi($kCabang);

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
                                        {{-- Keadaan reservasi, apa adanya. Baris yang belum
                                             pernah dijodohkan berbunyi "Tidak tertaut
                                             reservasi" — BUKAN "tidak ada reservasi":
                                             reservasi hidup di tabel Sweet Treats sendiri dan
                                             hampir seluruhnya belum pernah dicek, jadi klaim
                                             kedua mengaku tahu yang tidak diketahui. --}}
                                        <div class="ort-nlist__dim" data-ort-rsv>
                                            @unless ($k->visit_date)
                                                <span class="ort-chip ort-chip--{{ $kRsv['nada'] }}"
                                                      style="margin-right:5px;">{{ $kRsv['label'] }}</span>
                                            @endunless
                                            {{ $kRsv['teks'] }}
                                        </div>

                                        @if ($kRsvUrl)
                                            {{-- Tab baru, bukan pindah halaman: Sales sedang di
                                                 tengah daftar kerja dengan saringan dan halaman
                                                 tertentu, dan menukarnya berarti ia harus
                                                 membangun ulang keadaan itu saat kembali. --}}
                                            <a href="{{ $kRsvUrl }}" target="_blank" rel="noopener"
                                               class="ort-obtn ort-obtn--xs" onclick="event.stopPropagation()"
                                               title="Buka reservasi pasien ini di modul Reservasi">
                                                <i class="far fa-calendar-alt"></i> Buka di Reservasi
                                                <i class="fas fa-external-link-alt" style="font-size:8px;"></i>
                                            </a>
                                        @endif
                                    </div>
                                </span>
                            </div>
                        @endforeach
                    </div>
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
            <span class="ort-p2__kaki-k">Aksi lain</span>

            <a href="{{ $tautanDetail($r->ort_case_id) }}" class="ort-obtn">
                Buka detail pasien <i class="fas fa-chevron-right" style="font-size:9px;"></i>
            </a>

            {{-- Menggeser PENGINGAT, bukan janji. Tanggal jatuh tempo tidak
                 pernah diberitahukan ke pasien; yang disepakati hidup di
                 kolom Reservasi. --}}
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
        </div>
    </div>
</div>
