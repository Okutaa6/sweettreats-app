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
                                    <span class="ort-badge ort-badge--walkin">Datang tanpa reservasi</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- DOKTER --}}
                    <div class="ort-cell ort-clamp2" title="{{ $r->doctor_name }}">{{ $r->doctor_name ?: '—' }}</div>

                    {{-- JADWAL --}}
                    <div class="ort-cell {{ $telat ? 'ort-late' : '' }}">
                        @if ($r->is_proposal)
                            @php
                                /* Seberapa bisa dipegang tanggal jatuh tempo ini.

                                   Ukurannya SEBARAN irama, bukan banyaknya selang.
                                   Versi pertama memakai `$basisN >= 6` dan itu salah
                                   ukur: pasien berselang 20·90·25·110 punya lebih
                                   banyak selang daripada pasien 28·28·28, tapi
                                   iramanya jauh lebih tidak bisa ditebak — yang liar
                                   justru dinilai paling meyakinkan.

                                   Ini KETERANGAN, bukan gerbang tombol. Tombolnya
                                   sama untuk semua baris; yang berbeda cuma seberapa
                                   yakin Sales boleh menyebut tanggal ini di telepon.

                                   sch_basis NULL = baris lama dari sebelum irama
                                   dipakai; waktu itu memang +30, dan label itu tetap
                                   jujur. */
                                $basis  = $r->sch_basis ?? null;
                                $kuat   = $basis === \App\Models\OrtCase::BASIS_RAPAT;

                                /* Angkanya dari irama pasien, BUKAN dari selisih
                                   sch_date dengan baris ini: baris ini belum punya
                                   visit_date, jadi selisihnya nol. */
                                $iramaHari = (int) ($r->median_gap_days ?? 0);
                                $sebarPct  = $r->gap_spread_pct ?? null;

                                [$usulanTeks, $usulanJudul] = match (true) {
                                    $kuat => [
                                        'tiap ' . $iramaHari . ' hari, teratur',
                                        'Pasien ini kontrolnya teratur (sebaran ' . $sebarPct . '% dari mediannya), '
                                            . 'jadi jatuh tempo ini bisa dipegang.',
                                    ],
                                    $basis === \App\Models\OrtCase::BASIS_LONGGAR => [
                                        $iramaHari ? 'biasanya ' . $iramaHari . ' hari, tidak teratur' : 'tidak teratur',
                                        'Pasien ini kontrolnya tidak teratur (sebaran ' . $sebarPct . '% dari mediannya), '
                                            . 'jadi jatuh tempo ini perkiraan kasar. Tanyakan lewat telepon.',
                                    ],
                                    default => [
                                        'jarak baku 30 hari',
                                        'Riwayatnya belum cukup untuk tahu kebiasaan pasien ini, jadi dipakai jarak baku 30 hari.',
                                    ],
                                };
                            @endphp
                            <a href="{{ $tautan(['sheet' => 'geser', 'kontrol' => $r->id]) }}"
                               class="ort-proposal tabular {{ $kuat ? '' : 'ort-proposal--kira' }}"
                               onclick="event.stopPropagation()"
                               title="{{ $usulanJudul }} — klik untuk menggeser pengingatnya.">{{ \Carbon\Carbon::parse($r->sch_date)->translatedFormat("j M 'y") }}</a>
                            <span class="ort-cell--sub {{ $kuat ? 'ort-sub-yakin' : 'ort-sub-amber' }}"
                                  title="{{ $usulanJudul }}">jatuh tempo · {{ $usulanTeks }}</span>
                        @else
                            <span class="tabular">{{ \Carbon\Carbon::parse($r->sch_date)->translatedFormat("j M 'y") }}</span>
                            @if ($telat)
                                <span class="ort-cell--sub tabular">{{ $hariLewat }} hari lalu</span>
                            @endif
                        @endif
                    </div>

                    {{-- FOLLOW-UP --}}
                    <div class="ort-cell">
                        @if ($r->fu_date)
                            {{-- Tanggalnya saja. Tombol batalnya SENGAJA tidak di sini:
                                 kolom ini sempit dan langsung bersebelahan dengan kolom
                                 Reservasi, jadi kancing tambahan terbaca menumpuk. Jalan
                                 pulangnya ada di panel baris (tanda ⌄), dan tooltip di
                                 bawah ini yang menunjukkan jalannya. --}}
                            <span class="ort-fu"
                                  title="Dicatat {{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat("j M 'y") }} oleh {{ $r->fu_by ?: 'Sales' }} · WhatsApp{{ $bolehBatalFu ? ' — untuk membatalkan, buka panel baris dengan tanda ⌄ di ujung kanan' : '' }}">
                                <i class="far fa-comment"></i>
                                <span class="tabular">{{ \Carbon\Carbon::parse($r->fu_date)->translatedFormat('j M') }}</span>
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
                            <form method="POST" action="{{ route('ortho.followup', $r->id) }}"
                                  style="flex:1;display:flex;" onclick="event.stopPropagation()">
                                @csrf
                                <button type="submit" class="ort-btn" style="flex:1;"
                                        title="Catat bahwa pasien ini sudah di-follow-up hari ini">{{ $tombolLabel[$aksi] }}</button>
                            </form>
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
    @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'jadwal'])
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
