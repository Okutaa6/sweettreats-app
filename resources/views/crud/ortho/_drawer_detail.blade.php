{{--
    Drawer detail satu pasien — design brief §14 / screenshot 15 (bisa-ubah)
    dan 10 (lihat-saja).

    Inilah tabel yang digambar di papan tulis: # · Jadwal · Follow-up ·
    Reservasi · Datang · Cabang · Status · Aksi. Kolomnya dijaga persis.

    Tiga hal yang memang harus terasa di sini:
      • Kolom DATANG hijau dengan ikon unduh — itu tanda "diisi mesin", supaya
        Sales percaya angkanya bukan ketikan siapa pun.
      • Pemisah "⇄ transfer ke Sunter · 15 Nov" di antara baris yang cabangnya
        berbeda — riwayat perpindahan terbaca sebagai cerita, bukan tebakan.
      • Kartu kaki "Jadwalkan kontrol berikutnya" punya EMPAT keadaan, dan
        tombol matinya selalu menyebutkan alasannya.

    Varian lihat-saja ($detailLihat): tanpa Tandai Selesai, tanpa Pindahkan,
    kolom AKSI selalu "—", dan kartu kaki diganti "Dipegang <Cabang>".
--}}
@php
    $c        = $detail;
    $ctl      = $c->controls;                       // sudah terurut seq
    $terbuka  = $ctl->firstWhere(fn ($x) => $x->closed_at === null);

    /* Perawatan sudah ditutup (Freedom / Lepas Bracket di rekam medis, atau
       Tandai Selesai). Sejak tab Pasien Ortho → Selesai ada, drawer ini bisa
       dibuka untuk pasien begini — dan tidak boleh menawarkan tugas apa pun:
       server memang menolaknya (OrthoController::tandaiSelesai), tapi tombol
       yang cuma menghasilkan pesan galat tetap tombol yang bohong. */
    $detailSelesai = $c->status !== \App\Models\OrtCase::STATUS_AKTIF;
    $labelTutup    = [
        \App\Models\OrtCase::STATUS_SELESAI       => 'Perawatan selesai — behel sudah dilepas',
        \App\Models\OrtCase::STATUS_PUTUS         => 'Perawatan putus di tengah',
        \App\Models\OrtCase::STATUS_PINDAH_KLINIK => 'Pasien pindah klinik',
    ][$c->status] ?? 'Perawatan sudah ditutup';
    $tertutup = $ctl->filter(fn ($x) => $x->closed_at !== null);
    $hadir    = $ctl->whereNotNull('visit_date')->count();
    $selesaiN = $tertutup->count();
    $pctHadir = $selesaiN > 0 ? (int) round($hadir / $selesaiN * 100) : null;

    /* frag=drawer cuma perintah untuk SATU permintaan (ambil panelnya saja).
       Kalau ikut diwariskan ke tautan di dalam panel, klik Pindahkan/Jadwalkan
       akan memuat fragmen telanjang tanpa kerangka halaman. Buang di sini. */
    /* Kunci SEKALI-PAKAI dibuang. `$qs` dipakai tombol tutup dan scrim untuk
       memulangkan Sales ke daftar; kalau `sheet`/`kontrol`/`kasus`/`tambah`/
       `hari` ikut terbawa, menutup drawer justru MEMBUKA KEMBALI lembar yang
       barusan dibuka dari dalamnya — Buat Reservasi lalu tutup, dan lembar
       reservasinya muncul lagi di atas halaman daftar. Daftar pengecualian
       yang sama dipakai $tautanDetail di list.blade.php; kalau salah satu
       berubah, ubah keduanya.
       Tautan yang justru INGIN membuka lembar tetap benar: semuanya memakai
       array_merge($qs, ['sheet' => ...]) yang menimpa kuncinya sendiri. */
    $qs       = request()->except(['sheet', 'kasus', 'kontrol', 'tambah', 'hari', 'frag']);

    $wa       = \App\Support\Phone::toWa($c->patient_phone);
    $namaOwn  = $peta[$c->owner_place_id]['nama']   ?? $c->owner_place_id;
    $namaIns  = $peta[$c->install_place_id]['nama'] ?? $c->install_place_id;
    $pindah   = $c->owner_place_id !== $c->install_place_id;

    $lama = $c->install_date ? $c->install_date->diff($hariIni) : null;
    $lamaTeks = $lama
        ? trim(($lama->y ? $lama->y . ' tahun ' : '') . ($lama->m ? $lama->m . ' bulan' : ($lama->y ? '' : $lama->d . ' hari')))
        : '—';

    $chip = [
        'menunggu' => ['Menunggu', 'menunggu'],
        'sudah_fu' => ['Sudah di-FU', 'fu'],     'ada_reservasi' => ['Ada reservasi', 'rsv'],
        'hadir' => ['Hadir', 'hadir'],           'tidak_hadir' => ['Tidak hadir', 'absen'],
        'reservasi_batal' => ['Reservasi batal', 'batal'],
        'terlewat' => ['Terlewat', 'telat'],     'terlewat_parah' => ['Terlewat', 'telatlama'],
    ];

    $seqBerikut = ($ctl->max('seq') ?? 0) + 1;

    // Empat keadaan kartu kaki (brief §14).
    $kaki = match (true) {
        $ctl->isEmpty() => [
            'tombol' => 'Jadwalkan kontrol #1', 'aktif' => true,
            'hint'   => 'Pasien ini dimuat dari rekam medis dan belum pernah dijadwalkan. Tentukan tanggal kontrol pertamanya.',
        ],
        /* Dulu berbunyi "Sistem mengusulkan 28 Okt '26 — Konfirmasi kalau sudah
           disepakati". Dua hal salah di situ: tombol Konfirmasi sudah dibuang,
           dan tanggalnya tebakan yang tidak pantas disebut sepasti itu. */
        $terbuka && $terbuka->is_proposal => [
            'tombol' => 'Jadwalkan kontrol #' . $seqBerikut, 'aktif' => false,
            'hint'   => 'Kontrol #' . $terbuka->seq . ' jatuh tempo '
                      . \Illuminate\Support\Str::lcfirst(\App\Models\OrtControl::perkiraanJatuhTempo($terbuka->sch_date, $hariIni))
                      . ' — dihitung dari kontrol sebelumnya. Tanggal pastinya ditentukan saat membuat reservasi, dari hari praktik dokternya.',
        ],
        (bool) $terbuka => [
            'tombol' => 'Jadwalkan kontrol #' . $seqBerikut, 'aktif' => false,
            'hint'   => 'Kontrol #' . $terbuka->seq . ' masih berjalan. Kolom Datang terisi sendiri dari rekam medis tiap malam — usulan jadwal berikutnya muncul otomatis setelah kunjungannya masuk.',
        ],
        default => [
            'tombol' => 'Jadwalkan kontrol #' . $seqBerikut, 'aktif' => true,
            'hint'   => 'Kontrol #' . ($seqBerikut - 1) . ' sudah ditutup. Tentukan tanggal kontrol berikutnya — jarak wajar 28–45 hari.',
        ],
    };
@endphp

<a href="{{ route('ortho.index', $qs) }}" class="ort-scrim" aria-label="Tutup"></a>

<aside class="ort-drawer" tabindex="-1" role="dialog" aria-label="Detail {{ $c->patient_name }}">

    {{-- ── Kepala gelap. Baris-barisnya BERTUMPUK terpisah — nama panjang
         tidak boleh menimpa telepon atau dokter. ─────────────────────── --}}
    <div class="ort-drawer__head">
        <a href="{{ route('ortho.index', $qs) }}" class="ort-drawer__close" title="Tutup">
            <i class="fas fa-times"></i>
        </a>

        <div class="ort-drawer__kicker">Tracking Pasien Ortho › {{ mb_strtoupper($c->patient_name) }}</div>

        <div class="ort-drawer__namerow">
            <div class="ort-drawer__name">{{ \Illuminate\Support\Str::title($c->patient_name) }}</div>
            @if ($pindah)
                <span class="ort-drawer__transfer">
                    <i class="fas fa-exchange-alt" style="font-size:9px;"></i> kini kontrol di {{ $namaOwn }}
                </span>
            @endif
        </div>

        <div class="ort-drawer__phone">
            @if ($wa)
                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener">
                    <i class="fab fa-whatsapp"></i> {{ $c->patient_phone }}
                </a>
            @else
                <span class="is-bad" title="Nomor tidak valid, tidak bisa dibuka di WhatsApp">
                    <i class="fas fa-exclamation-triangle"></i> {{ $c->patient_phone ?: 'tanpa nomor' }}
                </span>
            @endif
        </div>

        <div class="ort-drawer__meta">
            {{ $c->omnicare_pid ?: 'belum tertaut RM' }}
            @if ($c->bracket_type) · {{ $c->bracket_type }} @endif
            @if ($c->bracket_arch) · {{ ucfirst($c->bracket_arch) }} @endif
            @if ($c->doctor_name) · {{ $c->doctor_name }} @endif
        </div>

        {{-- 45,7% tanggal pasang di prod itu PERKIRAAN dari kunjungan ortho
             pertama yang terlihat di rekam medis, bukan dari baris pemasangan
             (brief §6.1: 132 nama item pemasangan berbeda). Sebelumnya
             keduanya ditulis sama persis, jadi drawer mengatakan "berjalan 57
             bulan" dengan yakin untuk pasien yang sebetulnya cuma "terakhir
             terlihat 5 tahun lalu". Yang perkiraan sekarang diberi ± dan
             "minimal" — arah melesetnya selalu sama: pemasangan sebenarnya
             LEBIH LAMA, tidak pernah lebih baru. --}}
        @if ($c->install_is_estimated)
            <div class="ort-drawer__meta ort-drawer__meta--dim"
                 title="Tanggal pasang tidak ditemukan di rekam medis. Yang dipakai adalah kunjungan ortho paling awal yang terlihat, jadi pemasangan sebenarnya bisa lebih lama dari ini.">
                Pasang <span class="ort-kira">± {{ $c->install_date?->translatedFormat("j M 'y") }}</span>
                di {{ $namaIns }} · berjalan minimal {{ $lamaTeks }}
            </div>
        @else
            <div class="ort-drawer__meta ort-drawer__meta--dim">
                Pasang {{ $c->install_date?->translatedFormat("j M 'y") }} di {{ $namaIns }} · berjalan {{ $lamaTeks }}
            </div>
        @endif

        @if ($detailSelesai)
            <div class="ort-drawer__meta ort-drawer__meta--dim">
                <i class="fas fa-check-circle"></i>
                {{ $labelTutup }}{{ $c->finished_at ? ' · ' . $c->finished_at->translatedFormat("j M 'y") : '' }}
            </div>
        @elseif (! $detailLihat)
            <div class="ort-drawer__btns">
                <form method="POST" action="{{ route('ortho.selesai', $c->id) }}" style="display:inline-flex;">
                    @csrf
                    {{-- --solid WAJIB ikut: tanpa itu tombol jatuh ke gaya dasar
                         (teks #E0BFA6, tanpa latar) dan terlihat pudar di atas
                         kepala drawer yang gelap. --}}
                    <button type="submit" class="ort-drawer__btn ort-drawer__btn--solid"
                            title="Behel sudah dilepas — tutup perawatan pasien ini">
                        <i class="fas fa-check"></i> Tandai Selesai (bracket dilepas)
                    </button>
                </form>
                <a href="{{ route('ortho.detail', $c->id) }}?{{ http_build_query(array_merge($qs, ['sheet' => 'pindahkan', 'kasus' => $c->id])) }}"
                   class="ort-drawer__btn">
                    <i class="fas fa-exchange-alt"></i> Pindahkan ke cabang lain
                </a>
            </div>
        @endif
    </div>

    <div class="ort-drawer__body">

        {{-- ── Tiga KPI kecil, sebaris. Bukan hero. ─────────────────────── --}}
        <div class="ort-kpi">
            <div class="ort-kpi__card">
                <div class="ort-kpi__lbl">Total kontrol</div>
                <div class="ort-kpi__val tabular">{{ $ctl->count() }}</div>
                <div class="ort-kpi__sub">{{ $selesaiN }} sudah ditutup</div>
            </div>
            <div class="ort-kpi__card">
                <div class="ort-kpi__lbl">Kehadiran</div>
                <div class="ort-kpi__val tabular">{{ $hadir }}/{{ $selesaiN }}</div>
                <div class="ort-kpi__sub">{{ $pctHadir !== null ? $pctHadir . '% hadir' : 'belum ada yang ditutup' }}</div>
            </div>
            {{-- Median di depan, rata-rata jadi pembanding kecil. Rata-rata
                 tertarik pencilan: satu kali pasien hilang 8 bulan lalu kembali
                 sudah cukup menggeser angkanya puluhan hari, dan angka itulah
                 yang dulu dibaca Sales sebagai "irama pasien ini". Rata-rata
                 tidak dibuang — saat keduanya berjauhan, justru selisihnya yang
                 memberi tahu bahwa pasien ini pernah menghilang. --}}
            <div class="ort-kpi__card">
                <div class="ort-kpi__lbl">Irama kontrol</div>
                <div class="ort-kpi__val tabular">{{ $c->median_gap_days ? $c->median_gap_days . ' hari' : '—' }}</div>
                <div class="ort-kpi__sub">
                    @if ($c->median_gap_days && $c->avg_gap_days && $c->avg_gap_days !== $c->median_gap_days)
                        rata-rata <span class="tabular">{{ $c->avg_gap_days }}</span> hari
                    @elseif ($c->median_gap_days)
                        target 30 hari
                    @else
                        belum cukup kunjungan
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Matriks kontrol — kolom persis papan tulis ───────────────── --}}
        <div class="ort-matrix">
        <div class="ort-matrix__scroll">
            <div class="ort-matrix__head">
                <div class="ort-matrix__grid">
                    <div>#</div><div>Jadwal</div><div>Follow-up</div><div>Reservasi</div>
                    <div>Datang</div><div>Cabang</div><div>Status</div><div>Aksi</div>
                </div>
            </div>

            @php $cabangSebelum = null; @endphp
            @forelse ($ctl as $k)
                @php
                    $key = $k->statusKey($hariIni);
                    [$chipTeks, $chipKelas] = $chip[$key] ?? ['—', 'menunggu'];
                    if (str_starts_with($key, 'terlewat')) {
                        $chipTeks = 'Terlewat ' . $k->hariTerlewat($hariIni) . ' hari';
                    }
                    $aksi = ($detailLihat || $detailSelesai) ? null : $k->tindakanBerikutnya();
                    $labelAksi = ['follow_up' => 'Catat Follow-up', 'buat_reservasi' => 'Buat Reservasi'];
                @endphp

                {{-- Pemisah transfer: hanya saat cabang BERUBAH dari baris sebelumnya. --}}
                @if ($cabangSebelum !== null && $cabangSebelum !== $k->place_id)
                    <div class="ort-matrix__sep">
                        <span><i class="fas fa-exchange-alt"></i>
                            transfer ke {{ $peta[$k->place_id]['nama'] ?? $k->place_id }}
                            · {{ $k->sch_date->translatedFormat('j M') }}</span>
                    </div>
                @endif
                @php $cabangSebelum = $k->place_id; @endphp

                <div class="ort-matrix__row {{ $k->isTerbuka() ? 'is-open' : '' }}">
                    <div class="ort-matrix__grid">
                        <div class="ort-matrix__n tabular">{{ $k->seq }}</div>

                        {{-- Kontrol yang sudah TERTUTUP menampilkan tanggalnya apa
                             adanya: itu riwayat, dan riwayat memang bertanggal.
                             Hanya baris yang masih terbuka yang tanggalnya hasil
                             hitungan, dan ia memakai kalimat perkiraan yang sama
                             dengan kolom Jadwal di tab List. --}}
                        <div class="tabular {{ $k->is_proposal ? 'ort-proposal' : '' }}">
                            @if ($k->isTerbuka() && $k->is_proposal)
                                {{ \App\Models\OrtControl::perkiraanJatuhTempo($k->sch_date, $hariIni) }}
                            @else
                                {{ $k->sch_date->translatedFormat("j M 'y") }}
                            @endif
                        </div>

                        <div class="tabular">{{ $k->fu_date ? $k->fu_date->translatedFormat('j M') : '' }}
                            @unless ($k->fu_date)<span class="ort-x">X</span>@endunless</div>

                        <div class="tabular">
                            @if ($k->rsv_status === 'cancelled')
                                <span class="ort-struck">{{ $k->rsv_date?->translatedFormat('j M') ?? '—' }}</span>
                            @elseif ($k->reservation_id && ! $k->rsv_date)
                                <span class="ort-waitlist">Waitlist</span>
                            @elseif ($k->rsv_date)
                                {{ $k->rsv_date->translatedFormat('j M') }}
                            @else
                                <span class="ort-x">X</span>
                            @endif
                        </div>

                        <div class="tabular">
                            @if ($k->visit_date)
                                <span class="ort-visit"
                                      title="Terisi otomatis dari rekam medis, {{ $k->visit_filled_at?->translatedFormat('j M H:i') ?? '—' }}{{ $k->unscheduled ? ' · tidak cocok dengan jadwal mana pun di sistem' : '' }}">
                                    {{ $k->visit_date->translatedFormat('j M') }}
                                    <i class="fas fa-download"></i>
                                </span>
                            @else
                                <span class="ort-x">X</span>
                            @endif
                        </div>

                        <div>{{ $peta[$k->place_id]['nama'] ?? $k->place_id }}</div>

                        <div><span class="ort-chip ort-chip--{{ $chipKelas }}">{{ $chipTeks }}</span></div>

                        <div class="ort-matrix__aksi">
                            @if ($detailSelesai)
                                <span class="ort-dash" title="{{ $labelTutup }} — tidak ada tugas lagi">—</span>
                            @elseif ($detailLihat)
                                <span class="ort-dash" title="Dipegang {{ $namaOwn }} — {{ $namaCabang }} tidak bisa mengubah">—</span>
                            @elseif ($aksi === 'follow_up')
                                {{-- Aksi yang sama dengan tombol di baris List — jangan
                                     dibiarkan mati di sini, bikin bingung. --}}
                                <form method="POST" action="{{ route('ortho.followup', $k->id) }}" style="display:inline-flex;">
                                    @csrf
                                    <button type="submit" class="ort-btn ort-btn--sm"
                                            title="Catat follow-up hari ini lewat WhatsApp">{{ $labelAksi[$aksi] }}</button>
                                </form>
                            @elseif ($aksi === 'buat_reservasi')
                                {{-- Sama dengan tombol di baris List: membuka lembar
                                     reservasi, yang lalu mengoper ke form Reservasi. --}}
                                <a href="{{ route('ortho.detail', $c->id) }}?{{ http_build_query(array_merge($qs, ['sheet' => 'reservasi', 'kontrol' => $k->id, 'kasus' => $c->id])) }}"
                                   class="ort-btn ort-btn--sm">{{ $labelAksi[$aksi] }}</a>
                            @elseif ($k->isTerbuka() && ($k->reservation_id || $k->hariTerlewat($hariIni) > 0))
                                {{-- Pasien tidak datang. Satu-satunya jalan menutup
                                     kontrol ini tanpa kunjungan — mesin TIDAK PERNAH
                                     menyimpulkan ketidakhadiran (lihat kepala
                                     OrtPullVisits), jadi kalau tombol ini tidak ada,
                                     kontrolnya menggantung terbuka selamanya dan
                                     pasiennya tidak pernah masuk antrean penjadwalan.

                                     ⚠ Dulu tombol ini bersarang di cabang
                                       "elseif aksi" — yang TIDAK PERNAH TERCAPAI:
                                       tindakanBerikutnya() cuma mengembalikan
                                       konfirmasi/follow_up/buat_reservasi/null, dan
                                       ketiga yang bukan null sudah ditangani di atas.
                                       Syaratnya sekarang disamakan dengan tombol
                                       sejenis di panel baris daftar kerja
                                       (_baris_terbuka): ada reservasi ATAU sudah
                                       terlewat. --}}
                                <form method="POST" action="{{ route('ortho.tidakhadir', $k->id) }}" style="display:inline-flex;">
                                    @csrf
                                    <button type="submit" class="ort-absen"
                                            title="Tandai tidak hadir — tutup kontrol ini tanpa kunjungan">×</button>
                                </form>
                            @else
                                <span class="ort-dash" title="{{ $k->isTerbuka() ? 'Sudah ada reservasi — tinggal menunggu pasien datang' : 'Kontrol ini sudah ditutup' }}">—</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="ort-matrix__empty">Belum ada kontrol yang tercatat.</div>
            @endforelse
        </div>
        </div>

        {{-- ── Kartu kaki ───────────────────────────────────────────────── --}}
        @if ($detailSelesai)
            {{-- Bentuk yang sama dengan kartu "Dipegang cabang lain": ini juga
                 keadaan tanpa tugas, bukan ajakan bertindak. --}}
            <div class="ort-held ort-held--selesai">
                <span class="ort-held__icon"><i class="fas fa-check"></i></span>
                <div style="flex:1;min-width:0;">
                    <div class="ort-held__title">
                        {{ $labelTutup }}{{ $c->finished_at ? ' · ' . $c->finished_at->translatedFormat("j M 'y") : '' }}
                    </div>
                    <div class="ort-held__text">
                        Tidak ada kontrol berikutnya yang perlu dijadwalkan. Riwayat kontrol di atas
                        tetap tersimpan; transaksi yang menutupnya terlihat di baris terakhirnya.
                    </div>
                </div>
            </div>
        @elseif ($detailLihat)
            <div class="ort-held">
                <span class="ort-held__icon"><i class="far fa-eye"></i></span>
                <div style="flex:1;min-width:0;">
                    <div class="ort-held__title">Dipegang {{ $namaOwn }} — {{ $namaCabang }} hanya memantau</div>
                    <div class="ort-held__text">
                        {{ $namaOwn }} yang menghubungi pasien, membuat reservasi, dan menjadwalkan
                        kontrol berikutnya. Kalau lama tidak ada kemajuan, ingatkan admin {{ $namaOwn }}.
                    </div>
                </div>
                @if ($detailIngat)
                    <span class="ort-badge ort-badge--walkin">
                        <i class="fas fa-check" style="font-size:8px;"></i>
                        Diingatkan {{ $detailIngat->reminded_at->translatedFormat('j M') }}
                    </span>
                @else
                    @php
                        /* window.open dipanggil DARI gerakan klik pengguna —
                           kalau ditunda sampai form kembali, peramban
                           memblokirnya sebagai popup. */
                        $waIngat = app(\App\Services\Ortho\OrtBranches::class)->linkWaIngatkan(
                            (string) $c->owner_place_id,
                            $c->patient_name,
                            $c->omnicare_pid,
                            $namaCabang,
                            $terbuka?->sch_date && $terbuka->sch_date->lt($hariIni)
                                ? (int) $terbuka->sch_date->diffInDays($hariIni) : null,
                        );
                    @endphp
                    <form method="POST" action="{{ route('ortho.ingatkan', $c->id) }}" style="display:inline-flex;"
                          @if ($waIngat) onsubmit="window.open(this.dataset.wa, '_blank', 'noopener')" data-wa="{{ $waIngat }}" @endif>
                        @csrf
                        <button type="submit" class="ort-obtn"
                                title="{{ $waIngat ? 'Buka WhatsApp ' . $namaOwn . ' dan catat pengingatnya' : 'Nomor WhatsApp ' . $namaOwn . ' belum terdaftar — pengingat tetap tercatat' }}">
                            <i class="fab fa-whatsapp"></i> Ingatkan {{ $namaOwn }}
                        </button>
                    </form>
                @endif
            </div>
        @else
            <div class="ort-next">
                <div style="flex:1;min-width:0;">
                    <div class="ort-next__title">Jadwalkan kontrol berikutnya</div>
                    <div class="ort-next__hint">{{ $kaki['hint'] }}</div>
                </div>
                @if ($kaki['aktif'])
                    <a href="{{ route('ortho.detail', $c->id) }}?{{ http_build_query(array_merge($qs, ['sheet' => ($terbuka && $terbuka->is_proposal) ? 'geser' : 'jadwalkan', 'kontrol' => $terbuka?->id, 'kasus' => $c->id])) }}"
                       class="ort-btn">{{ $kaki['tombol'] }}</a>
                @else
                    <span class="ort-btn ort-btn--mati" title="{{ $kaki['hint'] }}">{{ $kaki['tombol'] }}</span>
                @endif
            </div>
        @endif

    </div>
</aside>
