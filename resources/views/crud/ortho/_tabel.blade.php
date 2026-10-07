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
      • TIDAK ADA kepala kelompok di tab List — daftarnya DIURUTKAN, bukan
        dikelompokkan. Kepala kelompok hanya ada di antrean penjadwalan (§10)
        dan satu pita pemisah Inactive di kaki daftar.
        ⚠ Kunci urutnya berubah 6 Okt 2026: dulu `sch_date` (Terlewat → Hari
          ini → Mendatang), sekarang KEMENDESAKAN relatif terhadap irama tiap
          pasien. Sebabnya ada di OrtQuery::daftar() — singkatnya, sejak
          `sch_date` tidak lagi punya kolom, mengurutkan padanya membuat
          daftar terbaca seperti diurutkan abjad.
--}}
{{-- Bilah di atas tabel: KENAPA urutannya begini, lalu saringan Status Pasien.

     Kalimat sebabnya di sini — sekali untuk seluruh tabel — bukan tanggal
     jatuh tempo kecil di tiap baris. Yang kedua menjawab pertanyaan yang sama
     lima puluh kali dan tetap tidak terbaca.

     ⚠ Kalimatnya IKUT TAB. Tab "Semua" memang diurutkan jatuh tempo terdekat
       (aturan brief di kepala berkas ini), sub-tab yang lain diurutkan
       kemendesakan. Satu kalimat tetap untuk keduanya akan salah di salah
       satunya, dan salah dengan percaya diri. --}}
<div class="st-card" style="margin-top:13px;" data-ort-tabel>
    <div class="ort-tablewrap">

        <div class="ort-thead">
            {{-- TUJUH kolom — prompt_jadwal_kontrol.md Phase 1 menetapkan
                 urutannya. Yang berubah dari delapan kolom sebelumnya:

                   · "Jadwal" (jatuh tempo) → jadi SUB-BARIS di bawah Last Visit.
                     Tidak dibuang, karena ia kunci urut yang terlihat: tanpanya
                     Sales melihat baris berurutan tanpa petunjuk mengapa.
                   · "Status" → chip-nya pindah ke panel baris (tanda ⌄).
                   · "Tindakan berikutnya" → MELEBUR ke kolom Follow Up dan
                     Reservasi, karena prompt memang menetapkan keduanya sebagai
                     TOMBOL ("Bentuk: button"), bukan tampilan tanggal. Jadi
                     tombolnya tidak hilang dari tabel — ia pindah ke kolom yang
                     memang miliknya.

                 Satu pengecualian yang disengaja: tanda eskalasi (≥2 siklus
                 terlewat) tetap tampil sebagai badge di kolom Pasien. Itu
                 satu-satunya isi kolom Status yang tidak bisa disimpulkan dari
                 enam kolom lain, dan ia persis hal yang modul ini dibangun untuk
                 menampakkan — satu klik lebih jauh berarti tidak terlihat. --}}
            <div class="ort-grid">
                <div>Nama</div>
                <div>Dokter</div>
                <div>Last Visit</div>
                {{-- Satu kalimat yang menutup ambiguitas paling mahal di modul ini:
                     tanggal jatuh tempo itu PENGINGAT, bukan janji. Tanpa
                     penjelasan ini, ia pernah dibaca sebagai kesepakatan dengan
                     pasien, dan dari salah baca itu lahir jepitan yang
                     menyembunyikan 26 dari 39 pasien terlambat. Kalimatnya ikut
                     pindah bersama tanggalnya ke sub-baris Last Visit. --}}
                <div title="Hari praktik dokter pasien ini dalam 7 hari ke depan, beserta seberapa penuh tiap harinya. Muncul mulai H-3 sebelum jatuh tempo kontrol."
                     style="cursor:help;">Jadwal Dokter</div>
                <div>Follow Up</div>
                <div>Reservasi</div>
                <div></div>
            </div>
        </div>

        @php $pitaPasif = false; @endphp

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

                /* $bolehBatalFu DIBUANG 7 Okt 2026. Ia dihitung di sini "supaya
                   tooltip tanggal, tooltip tanda ⌄, dan tombol di panel tidak
                   bisa berbeda pendapat" — dan ketiga pembaca itu sudah tidak
                   ada: dua tooltipnya dibersihkan dan tombolnya dibuang bersama
                   "Batalkan follow-up".

                   Dibuang, bukan dibiarkan, karena komentar di bawah tentang
                   chip STATUS sudah menamai bentuk ini sendiri: kode mati yang
                   paling mahal adalah yang terlihat seperti sedang menjaga
                   sesuatu. */

                /* Chip STATUS dibuang seluruhnya 6 Okt 2026.

                   Kolomnya hilang 3 Okt (tujuh kolom), lalu chip-nya pindah ke
                   panel baris, lalu panel itu dibuat berdiri sendiri supaya bisa
                   dimuat belakangan — dan chip ini satu-satunya yang mengikatnya
                   ke loop ini. Jadi ia dibuang, bukan dipindah lagi.

                   ⚠ Yang DIBAWANYA tidak ikut hilang, dan itu yang penting:
                     · "Terlewat N hari" → tooltip tanggal Last Visit, diukur
                       dari sch_date seperti semula;
                     · "N siklus" (eskalasi) → badge di kolom Nama, terlihat
                       TANPA membuka apa pun, plus ringkasan pola di panel;
                     · "Sudah di-FU" → kolom Follow Up, yang memang tombolnya;
                     · "Ada reservasi" / "Reservasi batal" → kolom Reservasi.

                   Selama satu commit ia dihitung di sini tanpa pernah dirender.
                   Itu persis bentuk kode mati yang paling mahal: ia terlihat
                   seperti sedang menjaga sesuatu. */

                /* Tangga tindakan DIBUANG dari sini (3 Okt 2026).
                   Dulu ia memilih SATU tombol untuk kolom "Tindakan berikutnya".
                   Kolom itu sudah tidak ada: prompt_jadwal_kontrol.md menetapkan
                   Follow Up dan Reservasi sebagai KOLOM TOMBOL masing-masing,
                   jadi keduanya kini bisa hadir bersamaan — dan memang harus,
                   karena pasien yang sudah punya reservasi tetap boleh ditelepon.

                   Keadaan tiap tombol dibaca langsung di selnya: Follow Up dari
                   fu_date == hari ini, Reservasi dari rsv_status/rsv_date. Tidak
                   ada lagi satu variabel yang memutuskan untuk keduanya. */

                $wa      = \App\Support\Phone::toWa($r->patient_phone);
                $masuk   = $masukDari[$r->ort_case_id] ?? null;
                $selesai = $usulanSelesai[$r->id] ?? null;

                /* Dihitung DI SINI, sebelum markup barisnya — pita pemisah
                   "Inactive" di bawah perlu tahu keadaan ini sebelum <div>
                   barisnya dibuka. */
                $pasif = isset($r->sales_active) && ! $r->sales_active;
            @endphp

            {{-- Pita pemisah, muncul SEKALI tepat sebelum baris Inactive pertama.
                 Urutan sudah menaruh mereka di bawah; pita ini yang membuat
                 turunnya terbaca sebagai keputusan, bukan seperti daftar yang
                 tiba-tiba berubah sifat di tengah. Tanpa penanda, pembaca yang
                 menggulir hanya melihat baris-baris yang anehnya sangat
                 terlambat dan menyimpulkan datanya rusak. --}}
            @if ($pasif && ! $pitaPasif)
                @php $pitaPasif = true; @endphp
                <div class="ort-group" style="background:var(--ort-strip);">
                    <span class="ort-group__dot" style="background:var(--ort-faint);"></span>
                    <span class="ort-group__label">Inactive</span>
                    @if (($salesHitung['pasif'] ?? 0) > 0)
                        <span class="ort-group__n">{{ $salesHitung['pasif'] }}</span>
                    @endif
                    <span class="ort-group__hint">jarang kontrol atau menghilang · ditandai manual · tetap ditampilkan</span>
                </div>
            @endif

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

                        @php
                            /* Dua penanda yang sebelumnya hidup di kolom Status.
                               Keduanya TIDAK bisa disimpulkan dari kolom lain:
                                 · eskalasi — "Terlewat 45 hari" terbaca sama untuk
                                   pasien berinterval 21 (sudah 2 siklus) dan 60
                                   (belum satu). Rumusnya statis di OrtCase karena
                                   $r di sini stdClass, bukan model.
                                 · Inactive — penilaian Sales, dan baris ini memang
                                   masih ditampilkan (turun ke bawah, bukan hilang). */
                            $siklusBaris = \App\Models\OrtCase::hitungSiklusTerlewat(
                                $r->last_visit_date,
                                $r->median_gap_days !== null ? (int) $r->median_gap_days : null,
                                $hariIniStr
                            );
                            $eskalasi = $siklusBaris >= \App\Models\OrtCase::SIKLUS_ESKALASI;
                        @endphp

                        @if ($masuk || $r->unscheduled || $eskalasi || $pasif)
                            <div class="ort-badges">
                                @if ($pasif)
                                    <span class="ort-chip ort-chip--inactive"
                                          title="Ditandai Inactive — tetap ditampilkan, tapi turun ke bawah daftar. Ubah lewat panel baris (tanda ⌄).">Inactive</span>
                                @endif
                                @if ($eskalasi)
                                    <span class="ort-badge ort-badge--transfer"
                                          title="Sudah {{ $siklusBaris }} interval kontrol lewat sejak kunjungan terakhir — perlu pendekatan berbeda, bukan sekadar telepon ulang.">
                                        <i class="fas fa-exclamation-circle" style="font-size:8px;"></i>
                                        Terlewat {{ $siklusBaris }} siklus
                                    </span>
                                @endif
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

                         ⚠ Kejujurannya dipindah, bukan dibuang — tapi SELURUHNYA ke
                           tooltip, termasuk penanda "masih hitungan mesin". Garis
                           putus `.ort-proposal` TIDAK dipakai di kolom ini: ia
                           menerangkan sch_date, dan yang tampil di sini
                           last_visit_date. Menempelkannya ke tanggal kunjungan
                           membuat fakta keras terbaca sebagai tebakan mesin. --}}
                    {{-- data-ort-jadwal: penanda untuk uji. Sebelumnya uji menangkap
                         sel ini lewat regex `<div class="ort-cell">teks</div>` yang
                         putus begitu selnya punya elemen anak — dan "tidak ketemu"
                         terbaca sama dengan "isinya salah". --}}
                    {{-- LAST VISIT — tanggal kontrol terakhir + umurnya.

                         Ambang warnanya BUKAN angka tetap. prompt_jadwal_kontrol.md
                         minta hijau <=30 / kuning 31–50 / merah >50, dan pada pasien
                         berirama 30 hari rumus di OrtCase::nadaLastVisit() menghasilkan
                         tepat 30 dan 50. Bedanya: pasien yang dokternya minta 45 hari
                         tidak lagi ditandai MERAH PALSU justru saat ia tepat waktu.

                         Sub-baris jatuh tempo ikut di sini — ia dulu kolom tersendiri.
                         Dipertahankan karena ia KUNCI URUT YANG TERLIHAT; tanpanya
                         baris tampak berurutan tanpa alasan yang bisa dibaca. --}}
                    <div class="ort-lv" data-ort-jadwal>
                        @php
                            $lvNada  = \App\Models\OrtCase::nadaLastVisit(
                                $r->last_visit_date,
                                $r->median_gap_days !== null ? (int) $r->median_gap_days : null,
                                $hariIniStr
                            );
                            $lvUmur = $r->last_visit_date
                                ? \Carbon\Carbon::parse($r->last_visit_date)->startOfDay()->diffInDays($hariIni)
                                : null;
                        @endphp

                        @php
                            /* Jatuh tempo pindah ke SINI (tooltip), bukan baris sendiri.
                               Kalimat "pengingat, bukan janji" ikut pindah — berkas ini
                               sendiri menyebutnya penutup ambiguitas termahal di modul
                               ini, jadi ia tidak boleh hilang bersama barisnya. */
                            /* ⚠ "Terlewat N hari" DIUKUR DARI sch_date, bukan dari
                               umur kunjungan di badge. Keduanya sering sama angka —
                               jatuh tempo memang biasanya last_visit + irama — tapi
                               berbeda begitu Sales menggeser tanggalnya sendiri, dan
                               yang dijanjikan kalimat ini adalah yang pertama.

                               Ia dulu chip di kolom Status. Kolom itu dibuang, dan
                               untuk satu commit kalimatnya ikut hilang dari layar
                               sementara kodenya masih menghitungnya diam-diam. */
                            $tipJatuhTempo = 'Jatuh tempo '
                                . \Carbon\Carbon::parse($r->sch_date)->translatedFormat("j M 'y")
                                . ' · ' . \App\Models\OrtControl::perkiraanJatuhTempo(\Carbon\Carbon::parse($r->sch_date), $hariIni)
                                . ($telat ? ' · Terlewat ' . $hariLewat . ' hari' : '')
                                . ($telat && $siklusBaris >= \App\Models\OrtCase::SIKLUS_ESKALASI
                                    ? ' · ' . $siklusBaris . ' siklus' : '')
                                . ' — ini PENGINGAT, bukan janji. Janji yang sudah disepakati ada di kolom Reservasi.'
                                . ($r->is_proposal ? ' · tanggalnya masih hitungan sistem, belum disentuh Sales.' : '');
                        @endphp

                        @if ($r->last_visit_date)
                            {{-- ⚠ TANPA `.ort-proposal`, dan itu koreksi 6 Okt 2026.
                                 Garis putus amber itu berarti "tanggal ini masih
                                 hitungan mesin, belum disentuh Sales" — sifat milik
                                 `sch_date`, bukan milik tanggal ini. `last_visit_date`
                                 adalah KUNJUNGAN YANG SUDAH TERJADI, diisi tarikan
                                 malam dari rekam medis; ia tidak pernah jadi usulan.

                                 Kelasnya ikut terbawa saat kolom Jadwal dilebur ke
                                 sini, dan akibatnya fakta keras ditandai seolah tebakan
                                 mesin — persis kebalikan artinya. Penanda usulan tetap
                                 hidup di tempat yang benar: matriks drawer, pada
                                 kolom sch_date-nya. Di sini ia cukup disebut di
                                 tooltip. --}}
                            <span class="ort-lv__tgl tabular"
                                  title="{{ $tipJatuhTempo }}">{{ \Carbon\Carbon::parse($r->last_visit_date)->translatedFormat("j M Y") }}</span>
                            <span class="ort-lv__umur ort-lv__umur--{{ $lvNada }} tabular">{{ $lvUmur }} hari</span>
                        @else
                            {{-- Bracket terpasang, nol kunjungan tercatat. Bukan kabar
                                 baik, jadi bukan hijau — dan ia naik ke ATAS urutan
                                 kegentingan, bukan turun. --}}
                            <span class="ort-lv__tgl" title="{{ $tipJatuhTempo }}">belum pernah datang</span>
                            <span class="ort-lv__umur ort-lv__umur--faint">tak ada dasar</span>
                        @endif
                    </div>

                    {{-- JADWAL DOKTER — hari praktik dokter pasien ini, 7 hari ke depan.

                         Isinya datang dari $jadwalDokter yang dihitung SEKALI untuk
                         seluruh halaman (OrtJadwalDokter::untukBanyak, 5 kueri).
                         ⚠ JANGAN memanggil service-nya dari dalam @foreach ini —
                           itu mengembalikan N+1 yang sudah dibayar mahal untuk dihapus.

                         Baris yang BELUM masuk H-3 tidak ada di map sama sekali, jadi
                         `?? null` membedakan "belum waktunya" dari "sudah waktunya tapi
                         tidak ada dokternya". Dua keadaan itu beda, dan "-" untuk
                         keduanya menyembunyikan bedanya. --}}
                    <div class="ort-cell">
                        @php $jd = $jadwalDokter[$r->ort_case_id] ?? null; @endphp

                        @if ($jd === null)
                            <span class="ort-dok__belum"
                                  title="Hari praktik dokter muncul mulai H-{{ \App\Models\OrtCase::HARI_PRAKTIK_MUNCUL }} sebelum jatuh tempo kontrol.">—</span>
                        @elseif ($jd['sebab'])
                            {{-- Empat sebab, empat kalimat. `tak_terpetakan` diperbaiki
                                 tim IT, `tak_praktik` diperbaiki operasional — satu teks
                                 untuk keduanya mengirim orang mengejar perbaikan yang
                                 salah. Diukur di DB pengembangan, dua dari tiga baris
                                 jatuh ke sini; kolom ini yang membuat kesenjangan itu
                                 terlihat tiap hari alih-alih tersembunyi di balik "-". --}}
                            @php
                                $sebabTeks = [
                                    'tanpa_dokter'       => 'Belum ada dokter',
                                    'tak_terpetakan'     => 'Dokter belum terpetakan',
                                    'tak_praktik'        => 'Belum ada jadwal praktik',
                                    'tak_ada_hari_pekan' => 'Tak ada hari pekan ini',
                                ][$jd['sebab']] ?? $jd['sebab'];
                            @endphp
                            <span class="ort-dok__sebab"
                                  title="{{ app(\App\Services\Ortho\OrtJadwalDokter::class)->penjelasan($jd['sebab'], $jd['dokter']) }}">{{ $sebabTeks }}</span>
                        @else
                            {{-- Teks berkoma, bukan pil berlatar. Tiga pil memberi
                                 tanggal bobot visual setara tombol di sebelahnya, dan
                                 di baris padat itu menghentikan mata di tempat yang
                                 salah. Warnanya tetap, bobotnya kembali ke tempatnya. --}}
                            @php $pil = array_slice($jd['hari'], 0, \App\Services\Ortho\OrtJadwalDokter::PIL_MAKS); @endphp
                            <div class="ort-dok tabular">
                                {{-- Koma MENEMPEL di ujung tanggal sebelumnya, bukan di
                                     baris sendiri: Blade mengubah pergantian baris jadi
                                     spasi, dan versi pertama merender "4 Okt , 5 Okt"
                                     dengan spasi menggantung sebelum koma. --}}
                                @foreach ($pil as $i => $h)
                                    <span class="ort-dok__d--{{ $h['cuti'] ? 'cuti' : $h['nada'] }}"
                                          title="{{ $h['cuti']
                                              ? ($jd['dokter'] ?: 'Dokter ini') . ' cuti ' . \Carbon\Carbon::parse($h['tanggal'])->translatedFormat('j M')
                                              : $namaCabang . ' · ' . ($h['total'] > 0
                                                  ? 'sisa ' . ($h['total'] - $h['terisi']) . ' dari ' . $h['total'] . ' slot'
                                                  : 'slot belum terbentuk')
                                                . ($h['jam'] ? ' · ' . $h['jam'] : '') }}">{{ \Carbon\Carbon::parse($h['tanggal'])->translatedFormat('j M') }}</span>@if (! $loop->last || count($jd['hari']) > \App\Services\Ortho\OrtJadwalDokter::PIL_MAKS)<span class="ort-dok__sep">,</span>@endif
                                @endforeach

                                @if (count($jd['hari']) > \App\Services\Ortho\OrtJadwalDokter::PIL_MAKS)
                                    <span class="ort-dok__more"
                                          title="{{ collect(array_slice($jd['hari'], \App\Services\Ortho\OrtJadwalDokter::PIL_MAKS))->map(fn ($h) => \Carbon\Carbon::parse($h['tanggal'])->translatedFormat('j M') . ($h['cuti'] ? ' (cuti)' : ''))->implode(' · ') }}">+{{ count($jd['hari']) - \App\Services\Ortho\OrtJadwalDokter::PIL_MAKS }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- FOLLOW UP — TOMBOL, bukan tampilan tanggal.

                         prompt_jadwal_kontrol.md: "Bentuk: button. Status belum
                         diklik hari ini: hijau, bisa diklik. Setelah diklik: abu
                         abu, disabled. Besok harinya button otomatis hijau lagi."

                         Keadaannya dibaca dari TANGGAL, bukan dari penanda
                         tersendiri — prompt memintanya begitu ("Jangan pakai cron
                         reset, cukup cek tanggal"), dan `fu_date` sudah ada di
                         baris ini, jadi nol kueri tambahan.

                         ⚠ Bedanya dari prompt, dan ini keputusan Reza 3 Okt 2026:
                           tombolnya MEMBUKA LEMBAR hasil follow-up (dihubungi /
                           tidak respon / minta jadwal ulang / menolak / salah
                           nomor + catatan), bukan sekali-klik-langsung-simpan.
                           Alasannya: hasil telepon adalah satu-satunya tempat
                           sistem tahu pasien MENOLAK vs TIDAK DIANGKAT, dan tanpa
                           itu pertanyaan "berapa pasien tidak respon bulan ini"
                           tidak bisa dijawab dari data. Riwayat hariannya ditulis
                           ke ort_followup, satu baris per pasien per hari. --}}
                    <div class="ort-actions">
                        @php
                            $fuHariIni = $r->fu_date
                                && \Carbon\Carbon::parse($r->fu_date)->toDateString() === $hariIniStr;

                            $fuHasil = $r->fu_result
                                ? (\App\Models\OrtControl::FU_HASIL[$r->fu_result] ?? $r->fu_result)
                                : null;

                            /* ⚠ `· WhatsApp` dulu DI-HARDCODE di sini, padahal
                               kanalnya bisa apa saja. Yang tersisa ditampilkan
                               hanya kalau barisnya memang punya nilainya. */
                            $fuTip = $r->fu_date
                                ? 'Dicatat ' . \Carbon\Carbon::parse($r->fu_date)->translatedFormat("j M 'y")
                                  . ' oleh ' . ($r->fu_by ?: 'Sales')
                                  . ($r->fu_channel ? ' · ' . (\App\Models\OrtControl::FU_KANAL[$r->fu_channel] ?? $r->fu_channel) : '')
                                  . ($fuHasil ? ' · ' . $fuHasil : '')
                                  . ($r->fu_note ? ' — ' . $r->fu_note : '')
                                  /* Kalimat "· untuk membatalkan, buka panel baris dengan
                                     tanda ⌄" DIBUANG 7 Okt 2026 bersama tombolnya. Tooltip
                                     yang menunjuk ke tombol yang sudah tidak ada lebih buruk
                                     daripada tooltip yang diam: ia mengirim orang mencari.

                                     ⚠ Komentar PHP, BUKAN {{-- --}}. Baris ini di tengah
                                       rangkaian ekspresi di dalam @php; komentar Blade
                                       memutus rantai `.` dan membuat SELURUH halaman
                                       ParseError. Sudah kena, 52 uji merah sekaligus. */
                                : null;
                        @endphp

                        @if ($fuHariIni)
                            {{-- Sudah ditelepon hari ini: abu, tidak bisa diklik, TAPI
                                 tetap berbentuk dan selebar tombol aslinya — supaya
                                 kolomnya tidak berkedut antar baris dan mata langsung
                                 tahu baris ini sudah beres. Besok hijau lagi, dibaca
                                 dari tanggal, tanpa cron. --}}
                            <span class="ort-btn ort-btn--diam" title="{{ $fuTip }}">
                                {{-- Label dibungkus <span> supaya ellipsis-nya punya
                                     sasaran: text-overflow tidak berlaku pada wadah
                                     flex, dan `.ort-btn` itu inline-flex. --}}
                                <span>Sudah FU hari ini</span>
                                @if ($fuHasil && $r->fu_result !== \App\Models\OrtControl::FU_DIHUBUNGI)
                                    {{-- Titik kecil menandai hasil yang BUKAN "bersedia",
                                         supaya Sales tahu ada sesuatu di tooltip tanpa
                                         harus menyapu semua baris. --}}
                                    <i class="fas fa-circle" style="font-size:4px;vertical-align:middle;"></i>
                                @endif
                            </span>
                        @else
                            <a href="{{ $tautan(['sheet' => 'follow-up', 'kontrol' => $r->id]) }}"
                               class="ort-btn ort-btn--go" onclick="event.stopPropagation()"
                               title="{{ $r->fu_date
                                   ? 'Terakhir di-FU ' . \Carbon\Carbon::parse($r->fu_date)->translatedFormat("j M 'y") . ' — catat follow-up hari ini'
                                   : 'Catat hasil follow-up pasien ini' }}">Follow Up</a>
                        @endif

                        {{-- Tombol WhatsApp SENGAJA terpisah dari tombol Follow Up.
                             Sempat digabung (satu klik = buka WA + catat) lalu
                             dibatalkan: mencatat follow-up dan menghubungi pasien
                             adalah dua keputusan berbeda, dan menggabungkannya
                             memaksa WhatsApp terbuka setiap kali Sales cuma ingin
                             menandai. Prompt juga memintanya begitu: "Klik WA TIDAK
                             otomatis tercatat sebagai follow up." --}}
                        @if ($wa)
                            <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                               class="ort-btn--wa" title="Buka WhatsApp {{ $r->patient_name }}"
                               onclick="event.stopPropagation()"><i class="fab fa-whatsapp"></i></a>
                        @else
                            <span class="ort-btn--wa ort-btn--wa-bad"
                                  title="Nomor tidak valid, tidak bisa dibuka di WhatsApp"><i class="fas fa-exclamation-triangle"></i></span>
                        @endif
                    </div>

                    {{-- RESERVASI — tombol kalau belum ada, keadaannya kalau sudah.

                         Tombolnya pergi ke form Reservasi yang sudah ada lewat
                         jembatan ?ort_control_id: drawer "Tambah Reservasi" terbuka
                         TERISI (pasien, cabang, dokter, tanggal, tindakan kontrol),
                         dan store() menulis reservation_id balik ke baris ini.
                         Keduanya sudah terpasang — lihat ReservationController. --}}
                    <div class="ort-cell">
                        @if ($r->rsv_status === 'cancelled')
                            <span class="ort-struck tabular">{{ $r->rsv_date ? \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') : '—' }}</span>
                            <span class="ort-cell--sub ort-sub-red">
                                dibatalkan {{ \Carbon\Carbon::parse($r->rsv_synced_at ?? $r->sch_date)->translatedFormat('j M') }}
                            </span>
                            {{-- Reservasi batal = tugasnya kembali. Tombolnya muncul
                                 lagi — reservasi batal berarti tugasnya kembali. --}}
                            <a href="{{ $tautan(['sheet' => 'reservasi', 'kontrol' => $r->id]) }}"
                               class="ort-btn ort-btn--rsv ort-btn--sm" onclick="event.stopPropagation()"
                               style="margin-top:6px;"
                               title="Buat reservasi pengganti"><i class="far fa-calendar-alt"></i> Reservasi</a>
                        @elseif ($rsvWaitlist)
                            <span class="ort-waitlist">Waitlist</span>
                            <span class="ort-cell--sub"
                                  title="Masuk waitlist, tanggalnya belum ada — tidak ada tugas Sales sampai jadwalnya keluar.">menunggu jadwal</span>
                        @elseif ($rsvLewat)
                            {{-- Reservasinya lewat tapi kunjungannya belum masuk. Ini
                                 BUKAN "tidak ada tugas" — janjinya meleset, dan seseorang
                                 harus memutuskan. Tidak diberi tombol: keputusannya
                                 (hadir / tidak hadir / jadwal ulang) tidak bisa ditebak
                                 dari sini, dan ketiganya sudah ada di panel ⌄. --}}
                            <span class="tabular">{{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') }}</span>
                            <span class="ort-cell--sub ort-sub-red"
                                  title="Reservasi {{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat("j M 'y") }} sudah lewat tapi kunjungannya belum masuk. Tarikan malam mengisinya sendiri kalau pasien memang datang; kalau tidak, tandai tidak hadir lewat panel ⌄.">cek kedatangan</span>
                        @elseif ($r->rsv_date)
                            <span class="tabular">{{ \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') }}</span>
                            <span class="ort-cell--sub">{{ $r->rsv_status === 'confirmed' ? 'dikonfirmasi' : 'menunggu' }}</span>
                        @else
                            <a href="{{ $tautan(['sheet' => 'reservasi', 'kontrol' => $r->id]) }}"
                               class="ort-btn ort-btn--rsv" onclick="event.stopPropagation()"
                               title="Buka form Reservasi dengan data pasien ini sudah terisi"><i class="far fa-calendar-alt"></i> Reservasi</a>
                        @endif
                    </div>

                    {{-- Toggle bundar --}}
                    <div style="display:flex;justify-content:center;">
                        <button type="button" class="ort-toggle" data-ort-toggle="{{ $r->id }}"
                                title="Ringkas pasien, 4 kontrol terakhir, dan aksi lain — pindahkan cabang, tandai tidak hadir"><i class="fas fa-chevron-down"></i></button>
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
                     data-ort-done="{{ $r->id }}"
                     title="{{ \Illuminate\Support\Str::title($r->patient_name) }} — {{ $selesai['teks'] }}{{ $selesai['catatan'] ? ' · ' . $selesai['catatan'] : '' }}">
                    <i class="fas {{ $selesai['absen'] ? 'fa-exclamation-circle' : 'fa-check' }}"></i>
                    <span>{{ $selesai['teks'] }}</span>
                    <span class="ort-done__note">{{ $selesai['catatan'] }}</span>

                    {{-- Tutup (7 Okt 2026, permintaan Reza). Ingatannya di
                         localStorage, bukan di DB: ini pengakuan "sudah saya
                         baca" milik satu orang di satu peramban, bukan fakta
                         tentang pasiennya — menyimpannya di ort_control akan
                         membuat satu Sales menyembunyikan keterangan dari
                         seluruh cabang. Lihat skrip di kaki berkas ini. --}}
                    <button type="button" class="ort-done__x" data-ort-done-x="{{ $r->id }}"
                            title="Sembunyikan keterangan ini" aria-label="Sembunyikan keterangan ini">&times;</button>
                </div>
            @endif

            {{-- CANGKANG KOSONG. Panelnya dimuat saat barisnya dibuka
                 (?frag=panel&kontrol=<id>), bukan dirender di muka.

                 prompt_jadwal_kontrol.md Phase 2 memintanya sejak awal — "Data
                 detail di-load via AJAX saat dibuka (lazy load), supaya list
                 utama tetap ringan" — dan versi pertama panel tiga kolom
                 mengabaikannya.

                 ⚠ Harganya terukur: fragmen tabel yang ditukar JS tiap kali
                   follow-up disimpan berukuran 454 KB, dan 95% DI ANTARANYA
                   (23 panel × 18,7 KB) adalah panel yang paling banyak SATU
                   yang pernah terbuka. Menukar DOM sebesar itu memang tidak
                   bisa terasa ringan — dan itulah "kok seperti memuat ulang
                   halaman" yang dilaporkan. --}}
            {{-- `data-ort-expand` = kunci DOM (id kontrol, unik per baris);
                 `data-ort-kasus` = yang dikirim ke server. Keduanya dipisah
                 karena tab "Pasien di cabang lain" memakai cangkang yang sama
                 tapi barisnya KASUS — di sana banyak pasien belum punya kontrol
                 terbuka sama sekali, jadi kunci kontrol tidak tersedia. --}}
            <div class="ort-expand" data-ort-expand="{{ $r->id }}"
                 data-ort-kasus="{{ $r->ort_case_id }}" data-ort-kosong hidden></div>

        @empty
            <div style="padding:14px 0;">
                @include('crud.ortho._kosong', ['adaFilter' => $adaFilter])
            </div>
        @endforelse

    </div>

    {{-- Keterangan warna kolom Jadwal Dokter DIBUANG 7 Okt 2026 atas permintaan
         Reza. Empat warnanya kini hanya diterangkan tooltip tiap pilnya
         ("Sunter · sisa 2 dari 10 slot"), yang memang lebih tepat: ia menyebut
         angka baris ITU, bukan arti warnanya secara umum.

         ⚠ DUA keputusan lama ikut tersimpan di sini supaya tidak hilang
           bersama blok yang dibuang:

           1. "Simulasikan tarikan malam ini" dari lembar rancangan SENGAJA
              tidak pernah dibuat. Di rancangan ia cuma span peraga — tanpa
              form, tanpa route. Membuatnya sungguhan berarti menaruh tombol
              yang MENULIS data rekam medis di halaman kerja harian Sales;
              tarikan malam yang sebenarnya dijadwalkan di Kernel pukul 04.00,
              dan kalau perlu dipicu manual, tempatnya baris perintah.

           2. .ort-legend-dot TIDAK ikut dihapus dari CSS — tab Kalender
              memakainya untuk legendanya sendiri, yang tetap ada. --}}

    @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'jadwal', 'perHal' => $perHal])
</div>

{{-- JS panel & lipatan dipindah ke partial bersama (6 Okt 2026): tab
     "Pasien di cabang lain" memakai panel yang sama dan butuh skrip yang sama.
     Tetap di-@include DARI SINI, bukan dari kerangka halaman — supaya ia ikut
     terbawa saat JS menukar fragmen tabel dan baris barunya tetap punya
     pendengar. --}}
@include('crud.ortho._js_panel')

<script>
/* ── Sembunyikan pita "kontrol #N selesai" ──────────────────────────────────
   Ingatannya di localStorage, BUKAN di DB. Yang disimpan bukan fakta tentang
   pasien melainkan pengakuan "sudah saya baca" milik satu orang; ditaruh di
   ort_control, satu Sales akan menyembunyikan keterangan itu dari seluruh
   cabang. Harganya: tidak ikut berpindah peramban, dan itu memang benar.

   ⚠ Pengikatnya querySelectorAll biasa, dan di sini itu SAH — beda dari
     lipatan di panel baris, yang wajib didelegasikan. Tombol-tombol ini hidup
     di markup TABEL, berkas yang sama yang membawa skrip ini, jadi keduanya
     ikut tertukar bersamaan tiap fragmen diperbarui dan sapuan ini selalu
     menemukan tombol yang baru.

   ⚠ TIDAK ADA satu pun literal `data-ort-...="` di skrip ini, dan itu WAJIB
     dijaga. OrtoPerHalamanTest dan OrtoTahapCHalamanTest mencacah
     penanda baris di SELURUH halaman untuk menghitung baris; versi pertama
     skrip ini menyusun pemilihnya sebagai string dan membuat ketujuh uji itu
     merah — bukan karena barisnya salah, tapi karena mereka ikut menghitung
     kalimat di dalam skrip. Makanya barisnya dicari lewat
     previousElementSibling, bukan lewat id.

     Dan ya: KOMENTAR INI pun tidak boleh menyebut penandanya lengkap dengan
     tanda sama-dengan dan kutip. Komentar JS ikut terkirim ke halaman, jadi
     menyebutnya di sini mengulang bug yang sama persis — sudah kena.

   ⚠ Kelas ort-tr--nb WAJIB ikut dilepas dari baris di atasnya. Kelas itu
     membuang garis bawah baris DAN memangkas padding-nya menjadi 7px, justru
     karena pita ini menempel di bawahnya. Disembunyikan tanpa melepasnya,
     barisnya berakhir tanpa garis dan lebih pendek dari tetangganya. */
(function () {
    var KUNCI = 'ort-done-sembunyi';
    var BATAS = 400;              /* kunci lama dibuang; id kontrol tak pernah dipakai ulang */

    /* localStorage bisa MELEMPAR, bukan cuma kosong: mode privat Safari dan
       peramban yang memblokir data situs. Fitur ini boleh gagal diam-diam —
       yang terjadi cuma pita tetap tampil. */
    function baca() {
        try { return JSON.parse(localStorage.getItem(KUNCI) || '[]'); } catch (e) { return []; }
    }
    function tulis(daftar) {
        try { localStorage.setItem(KUNCI, JSON.stringify(daftar.slice(-BATAS))); } catch (e) {}
    }

    function sembunyikan(pita) {
        if (!pita) return;
        pita.hidden = true;

        /* Pita selalu dirender TEPAT sesudah barisnya — lihat markup di atas.
           Dicek kelasnya, bukan dipercaya begitu saja. */
        var baris = pita.previousElementSibling;
        if (baris) baris.classList.remove('ort-tr--nb');
    }

    var tersimpan = baca();

    document.querySelectorAll('[data-ort-done]').forEach(function (pita) {
        if (tersimpan.indexOf(pita.dataset.ortDone) !== -1) sembunyikan(pita);
    });

    document.querySelectorAll('[data-ort-done-x]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();

            var pita = b.closest('[data-ort-done]');
            sembunyikan(pita);

            var id = b.dataset.ortDoneX;
            var daftar = baca();
            if (daftar.indexOf(id) === -1) { daftar.push(id); tulis(daftar); }
        });
    });
})();
</script>
