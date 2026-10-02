{{--
    Panel ringkas yang muncul saat baris diklik — brief §9.4, screenshot 04–06.

    Kiri  — delapan fakta pasien yang dibutuhkan sebelum menelepon.
    Kanan — empat kontrol terakhir dengan SEGMENTED CONTROL TIGA ARAH:

      Semua            #N · tanggal · FU/RSV/cabang · chip status
      Follow-up        FU <tgl> · oleh <operator> · WhatsApp  → seluruh baris
                       jadi tautan WhatsApp (kecuali nomornya tidak valid)
      Tanggal reservasi  <tgl> · jam · cabang            → seluruh baris jadi
                       tautan masuk ke modul Reservasi

    Tiga tampilan itu menjawab tiga pertanyaan berbeda tanpa membuka drawer:
    "pola bolongnya bagaimana", "sudah dihubungi belum", "reservasinya jadi
    atau batal". Pilihannya diingat PER BARIS (sessionStorage) supaya Sales
    yang terbiasa satu tampilan tidak perlu memilih ulang tiap membuka baris.
--}}
@php
    $mini = $riwayat[$r->ort_case_id] ?? [];
    $kehadiran = ($r->control_count ?? 0) > 0
        ? $r->visit_count . ' dari ' . $r->control_count . ' kontrol'
        : 'belum ada kontrol';
@endphp

<div class="ort-expand" data-ort-expand="{{ $r->id }}" hidden>

    {{-- ── Kiri: ringkas pasien ──────────────────────────────────────── --}}
    <div>
        <div class="ort-expand__title">Ringkas pasien</div>

        <div class="ort-facts">
            <div>
                <div class="ort-fact__k">No. rekam medis</div>
                <div class="ort-fact__v tabular">{{ $r->omnicare_pid ?: 'belum tertaut' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Nomor HP</div>
                <div class="ort-fact__v tabular">{{ $r->patient_phone ?: '—' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Bracket · rahang</div>
                <div class="ort-fact__v">
                    {{ $r->bracket_type ?: '—' }}
                    @if ($r->bracket_arch) · {{ ucfirst($r->bracket_arch) }} @endif
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Dokter ortho</div>
                <div class="ort-fact__v">{{ $r->doctor_name ?: '—' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Pasang behel</div>
                <div class="ort-fact__v"
                     @if ($r->install_is_estimated ?? false) title="Tanggal pasang tidak ditemukan di rekam medis. Yang dipakai kunjungan ortho paling awal yang terlihat, jadi pemasangan sebenarnya bisa lebih lama." @endif>
                    @if ($r->install_is_estimated ?? false)<span class="ort-kira">±</span> @endif
                    {{ \Carbon\Carbon::parse($r->install_date)->translatedFormat("j M 'y") }}
                    di {{ $peta[$r->install_place_id]['nama'] ?? $r->install_place_id }}
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Kehadiran</div>
                <div class="ort-fact__v">{{ $kehadiran }}</div>
            </div>
            @php
                /* Siklus terlewat = floor((hari ini − kunjungan terakhir) ÷ interval).
                   Interval = median irama dijepit 21–60, cadangan 30 — rumus yang
                   sama dengan jatuh tempo. Dihitung di sini, bukan disimpan:
                   nilainya bergeser tiap hari walau datanya diam. */
                $siklusTerlewat = \App\Models\OrtCase::hitungSiklusTerlewat(
                    $r->last_visit_date, $r->median_gap_days !== null ? (int) $r->median_gap_days : null, $hariIni
                );
            @endphp
            <div>
                <div class="ort-fact__k">Siklus terlewat</div>
                <div class="ort-fact__v">
                    @if ($siklusTerlewat >= \App\Models\OrtCase::SIKLUS_ESKALASI)
                        <span class="ort-sub-red tabular">{{ $siklusTerlewat }}</span> siklus
                        <span class="ort-fact__dim" title="Sudah {{ $siklusTerlewat }} interval kontrol lewat sejak kunjungan terakhir — perlu pendekatan berbeda, bukan sekadar telepon ulang">· eskalasi</span>
                    @elseif ($siklusTerlewat === 1)
                        <span class="tabular">1</span> siklus
                    @else
                        <span class="ort-x">—</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Irama kontrol</div>
                <div class="ort-fact__v">
                    {{-- Median, bukan rata-rata: rata-rata tertarik pencilan, dan satu
                         kali pasien hilang lalu kembali sudah cukup melarkan angkanya
                         puluhan hari. Rata-rata tetap ditampilkan saat berbeda —
                         selisih keduanya justru yang memberi tahu pasien ini pernah
                         menghilang. --}}
                    @if ($r->median_gap_days)
                        <span class="tabular">{{ $r->median_gap_days }}</span> hari
                        {{-- Yang menolong Sales bukan angkanya saja, melainkan
                             seberapa bisa dipegang. Pasien 20/90/25/110 dan pasien
                             28/28/28 bisa punya median yang mirip, tapi cuma satu
                             yang tanggalnya pantas disebut di telepon. --}}
                        @if ($r->gap_spread_pct !== null && $r->gap_spread_pct <= \App\Models\OrtCase::IRAMA_RAPAT_PCT)
                            <span class="ort-fact__dim" title="Selangnya konsisten (sebaran {{ $r->gap_spread_pct }}% dari median) — tanggal usulan untuk pasien ini bisa dipegang.">· teratur</span>
                        @elseif ($r->gap_spread_pct !== null)
                            <span class="ort-fact__dim" title="Selangnya jauh berbeda-beda (sebaran {{ $r->gap_spread_pct }}% dari median) — tanyakan tanggalnya lewat telepon, jangan dipegang dari angka ini.">· tidak teratur</span>
                        @endif
                        @if ($r->avg_gap_days && $r->avg_gap_days !== $r->median_gap_days)
                            <span class="ort-fact__dim">(rata-rata {{ $r->avg_gap_days }})</span>
                        @endif
                    @else
                        belum cukup kunjungan
                    @endif
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Kontrol terakhir datang</div>
                <div class="ort-fact__v">
                    {{ $r->last_visit_date
                        ? \Carbon\Carbon::parse($r->last_visit_date)->translatedFormat("j M 'y")
                        : 'belum pernah' }}
                </div>
            </div>
        </div>

        <div class="ort-expand__btns">
            <a href="{{ $tautanDetail($r->ort_case_id) }}" class="ort-obtn">
                Buka detail pasien <i class="fas fa-chevron-right" style="font-size:9px;"></i>
            </a>
            {{-- Menggeser PENGINGAT. Pindah ke sini dari kolom Jadwal, yang
                 dulu tanggalnya sendiri jadi tautan: di lebar 112px, tanggal
                 bergaris putus-putus plus dua baris keterangan membuat kolom
                 itu berdesakan. Di sini ada ruang untuk menamainya terang. --}}
            @if ($r->is_proposal)
                <a href="{{ $tautan(['sheet' => 'geser', 'kontrol' => $r->id]) }}" class="ort-obtn"
                   onclick="event.stopPropagation()"
                   title="Ubah kapan pasien ini dianggap jatuh tempo kontrol. Ini pengingat, bukan janji — janjinya dibuat lewat Buat Reservasi.">
                    <i class="far fa-calendar-alt"></i> Geser pengingat
                </a>
            @endif
            <a href="{{ $tautan(['sheet' => 'pindahkan', 'kasus' => $r->ort_case_id]) }}" class="ort-obtn"
               style="color:#6B2400;border-color:#E4C7AC;" onclick="event.stopPropagation()">
                <i class="fas fa-exchange-alt"></i> Pindahkan ke cabang lain
            </a>
            {{-- Jalan pulang dari salah klik "Catat Follow-up".

                 Di sini, bukan di kolom Follow-up: kolom itu sempit dan
                 bersebelahan dengan kolom Reservasi, jadi kancing tambahan di
                 sana terbaca menumpuk. Lagipula membatalkan follow-up adalah
                 aksi JARANG — cuma saat salah klik — sementara mencatatnya
                 puluhan kali sehari. Baris utama disediakan untuk yang sering;
                 yang jarang dan bersifat korektif turun ke panel ini, satu
                 keluarga dengan "Tandai tidak hadir".

                 Untuk beberapa detik pertama sesudah salah klik, Urungkan di
                 toast tetap jalan cepatnya.

                 HILANG begitu reservasinya jadi, dan itu disengaja. Follow-up
                 adalah anak tangga MENUJU reservasi; sekali reservasinya ada,
                 mencabut follow-up membuat tangga itu mundur secara tidak
                 masuk akal — $aksi di _tabel akan jatuh kembali ke
                 'Catat Follow-up' padahal reservasinya sudah jadi, jadi baris
                 itu menyuruh mengerjakan ulang sesuatu yang sudah selesai.
                 Kalau reservasinya dibatalkan, tombol ini muncul lagi sendiri
                 karena tahapnya memang kembali ke situ. --}}
            @if ($bolehBatalFu ?? false)
                <form method="POST" action="{{ route('ortho.batalfollowup', $r->id) }}"
                      style="display:inline-flex;" onclick="event.stopPropagation()">
                    @csrf
                    <button type="submit" class="ort-obtn"
                            title="Hapus catatan follow-up {{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat('j M') }} — kolom Follow-up kembali kosong dan tombol baris kembali jadi Catat Follow-up">
                        <i class="fas fa-undo" style="font-size:10px;"></i> Batalkan follow-up
                    </button>
                </form>
            @endif

            @if ($r->reservation_id || $telat)
                <form method="POST" action="{{ route('ortho.tidakhadir', $r->id) }}"
                      style="display:inline-flex;" onclick="event.stopPropagation()">
                    @csrf
                    <button type="submit" class="ort-obtn ort-obtn--warn"
                            title="Tutup kontrol ini tanpa kunjungan — pasien masuk antrean penjadwalan">
                        Tandai tidak hadir
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ── Kanan: 4 kontrol terakhir, tiga tampilan ──────────────────── --}}
    <div>
        <div class="ort-mini-head">
            <span class="ort-expand__title" style="margin:0;">4 kontrol terakhir</span>
            <span class="ort-miniseg" data-ort-seg="{{ $r->id }}">
                <button type="button" data-v="semua"     class="is-active">Semua</button>
                <button type="button" data-v="fu">Follow-up</button>
                <button type="button" data-v="rsv">Tanggal reservasi</button>
            </span>
        </div>

        <div class="ort-mini" data-ort-mini="{{ $r->id }}">
            @forelse ($mini as $m)
                @php
                    $cabang = $peta[$m->place_id]['nama'] ?? $m->place_id;

                    // ── Tampilan "Semua"
                    $sAll = match (true) {
                        (bool) $m->visit_date          => ['Hadir', 'hadir'],
                        $m->outcome === 'tidak_hadir'  => ['Tidak hadir', 'absen'],
                        $m->rsv_status === 'cancelled' => ['Reservasi batal', 'batal'],
                        /* Chip 'usulan' dibuang: kolom Jadwal adalah jatuh
                           tempo, jadi tanggal yang dihitung sistem bukan sebuah
                           STATUS tersendiri. Garis putus-putus di tanggalnya
                           yang menandai asal-usulnya. */
                        (bool) $m->reservation_id      => ['Ada reservasi', 'rsv'],
                        (bool) $m->fu_date             => ['Sudah di-FU', 'fu'],
                        default                        => ['Menunggu', 'menunggu'],
                    };
                    $dAll = 'FU ' . ($m->fu_date ? $m->fu_date->translatedFormat('j M') : 'X')
                          . ' · RSV ' . ($m->rsv_date ? $m->rsv_date->translatedFormat('j M') : 'X')
                          . ' · ' . $cabang;

                    // ── Tampilan "Follow-up"
                    $dFu = $m->fu_date
                        ? 'FU ' . $m->fu_date->translatedFormat("j M 'y")
                          . ($m->fu_by ? ' · oleh ' . $m->fu_by : '') . ' · WhatsApp'
                        : 'Belum ada follow-up';
                    $sFu = $m->fu_date ? ['Sudah di-FU', 'fu'] : ['Belum di-FU', 'menunggu'];

                    /*
                     * ── Tampilan "Tanggal reservasi"
                     *
                     * ⚠ URUTANNYA MENENTUKAN. `rsv_date` WAJIB diperiksa SEBELUM
                     *   `unscheduled`. Dulu kebalikannya, dan akibatnya baris walk-in
                     *   yang berhasil ditautkan `ort:tautkan-reservasi` tetap berbunyi
                     *   "datang tanpa reservasi" — menyembunyikan tanggal yang baru
                     *   saja diisi, karena perintah itu tidak mereset `unscheduled`.
                     *   Tabel utama (_tabel.blade.php) memakai urutan yang benar sejak
                     *   awal, jadi dua layar berbeda pendapat tentang baris yang sama.
                     *
                     * ⚠ KITA TIDAK TAHU apakah pasien punya reservasi atau tidak.
                     *   Reservasi hidup di `raw_cus_reservation` — tabel Sweet Treats,
                     *   BUKAN dari Omnicare. Omnicare cuma tahu pasien DATANG.
                     *   `unscheduled = 1` artinya "kunjungan ini tidak cocok dengan
                     *   baris jadwal mana pun di sistem kita", bukan "pasien datang
                     *   tanpa memesan". Teks lama mengaku tahu yang kedua, lengkap
                     *   dengan chip HIJAU seolah fakta terverifikasi.
                     *
                     *   Selama `ort:tautkan-reservasi --commit` belum dijalankan,
                     *   `reservation_id` kosong untuk hampir semua baris (0 dari
                     *   35.317 di prod). "Belum pernah dicek" tidak boleh ditampilkan
                     *   seperti "sudah dicek, hasilnya nihil".
                     */
                    if ($m->rsv_status === 'cancelled') {
                        $dRsv = ($m->rsv_date ? $m->rsv_date->translatedFormat("j M 'y") : '—')
                              . ' · dibatalkan' . ($m->rsv_synced_at ? ' ' . $m->rsv_synced_at->translatedFormat('j M') : '');
                        $sRsv = ['Batal', 'batal'];
                    } elseif ($m->reservation_id && ! $m->rsv_date) {
                        $dRsv = 'Masuk daftar tunggu';
                        $sRsv = ['Waitlist', 'telat'];
                    } elseif ($m->rsv_date) {
                        $dRsv = $m->rsv_date->translatedFormat("j M 'y") . ' · ' . $cabang;
                        $sRsv = $m->visit_date ? ['Datang', 'hadir']
                              : ($m->closed_at ? ['Terlewat', 'telat'] : ['Terjadwal', 'rsv']);
                    } elseif ($m->unscheduled && $m->visit_date) {
                        $dRsv = 'Datang ' . $m->visit_date->translatedFormat("j M 'y")
                              . ' — tidak ada jadwal tercatat';
                        $sRsv = ['Tanpa jadwal', 'menunggu'];
                    } elseif ($m->isTerbuka()) {
                        $dRsv = 'Belum ada reservasi';
                        $sRsv = ['Belum dipesan', 'menunggu'];
                    } else {
                        $dRsv = 'Tidak tertaut reservasi';
                        $sRsv = ['—', 'menunggu'];
                    }

                    $waRow = $m->fu_date && $wa;
                @endphp

                {{-- Satu baris DOM, tiga isi. Menukar tampilan cuma mengganti
                     kelas induknya — tidak ada render ulang, tidak ada kedipan. --}}
                <div class="ort-mini__row" data-ort-minirow
                     data-href-fu="{{ $waRow ? 'https://wa.me/' . $wa : '' }}"
                     data-href-rsv="{{ $m->rsv_date ? route('rawreservation', ['date_from' => $m->rsv_date->toDateString(), 'date_to' => $m->rsv_date->toDateString()]) : '' }}">
                    <span class="ort-mini__n">#{{ $m->seq }}</span>
                    {{-- Riwayat bertanggal, yang terbuka jadi perkiraan — aturan
                         yang sama dengan kolom Jadwal di tab List. --}}
                    <span class="ort-mini__d tabular" @if ($m->isTerbuka() && $m->is_proposal)title="{{ \App\Models\OrtControl::perkiraanJatuhTempo($m->sch_date, $hariIni) }} · dihitung sistem"@endif>{{ $m->sch_date->translatedFormat("j M 'y") }}</span>

                    <span class="ort-mini__x" data-v="semua">{{ $dAll }}</span>
                    <span class="ort-mini__x" data-v="fu" hidden>{{ $dFu }}</span>
                    <span class="ort-mini__x" data-v="rsv" hidden>{{ $dRsv }}</span>

                    <span class="ort-chip ort-chip--{{ $sAll[1] }}" data-v="semua">{{ $sAll[0] }}</span>
                    <span class="ort-chip ort-chip--{{ $sFu[1] }}"  data-v="fu"  hidden>{{ $sFu[0] }}</span>
                    <span class="ort-chip ort-chip--{{ $sRsv[1] }}" data-v="rsv" hidden>{{ $sRsv[0] }}</span>

                    @if ($waRow)
                        <i class="fab fa-whatsapp ort-mini__go" data-v="fu" hidden
                           title="Buka WhatsApp {{ $r->patient_name }}"></i>
                    @endif
                    @if ($m->rsv_date)
                        <i class="fas fa-external-link-alt ort-mini__go ort-mini__go--rsv" data-v="rsv" hidden
                           title="Buka di modul Reservasi"></i>
                    @endif
                </div>
            @empty
                <div class="ort-mini__row">
                    <span class="ort-mini__x">Belum ada kontrol yang tercatat.</span>
                </div>
            @endforelse
        </div>
    </div>

</div>
