{{--
    Side sheet 410px — brief §15 / screenshot 16, 17, 21.
    Satu berkas, empat isi: reservasi · pindahkan · geser · jadwalkan.

    Dibuka lewat ?sheet=<jenis>&kontrol=<id> (atau &kasus=<id>) — bukan JS —
    supaya tiap keadaan punya URL dan tombol Kembali browser bekerja.

    Tombol simpan MASIH INERT (aksi tulis = Tahap 3). Form-nya sudah lengkap
    supaya tampilannya bisa dinilai sekarang.

    Keputusan yang sudah diambil pemilik produk dan berlaku di sini:
      • Lembar reservasi TIDAK punya form jam sendiri — ia membuka form
        Reservasi yang sudah ada, ter-prefill. Jadi isinya cuma tanggal +
        kotak penjelasan, dan tombolnya "Lanjutkan ke modul Reservasi".
      • Alasan pindah WAJIB. Kotak "kontrol sekali saja" = transfer sementara.
--}}
@php
    $k   = $sheetKontrol;   // OrtControl|null
    $c   = $sheetKasus;     // OrtCase
    $tutup = $tautan(['sheet' => null, 'kontrol' => null, 'kasus' => null]);
    $namaOwn = $peta[$c->owner_place_id]['nama'] ?? $c->owner_place_id;
    $sub = \Illuminate\Support\Str::title($c->patient_name)
         . ' · ' . ($c->omnicare_pid ?: 'belum tertaut RM')
         . ($k ? ' · kontrol ke-' . $k->seq : '');

    $judul = match ($sheet) {
        'reservasi'  => ['Buat reservasi',       'Buat Reservasi',                        'Lanjutkan ke modul Reservasi'],
        'pindahkan'  => ['Pindahkan pasien',     'Pindahkan ' . \Illuminate\Support\Str::title($c->patient_name), 'Pindahkan'],
        'geser'      => ['Geser usulan',         'Geser tanggal usulan',                  'Simpan usulan'],
        'jadwalkan'  => ['Jadwal baru',          'Jadwalkan kontrol berikutnya',          'Simpan jadwal'],
        /* Varian ke-5 (4 Okt 2026). Dulu "Catat Follow-up" sekali klik tanpa
           form — mencatat tanggal + kanal bawaan WA, HASILNYA tidak pernah
           tercatat. Sekarang lembar: hasil (pilihan tetap), kanal, tanggal,
           catatan. Mewarisi submit realtime, toast, dan Urungkan 90 detik
           dari mesin lembar yang sama. */
        'follow-up'  => ['Catat follow-up',      'Catat hasil follow-up',                 'Simpan follow-up'],
    };

    $hariIniStr = $hariIni->toDateString();
    $minTgl     = $hariIni->copy()->addDays(\App\Models\OrtControl::MIN_MAJU_HARI)->toDateString();
    /* Irama pasien ini sendiri, sama seperti yang dipakai mesin usulan
       (Tahap C). Angka 30 tetap jadi cadangan untuk pasien yang riwayatnya
       belum cukup — dan saat itu terjadi, teks bantuan di bawah mengatakannya
       apa adanya alih-alih mengaku tahu. */
    $iramaHari = $c->median_gap_days
        ? max(\App\Models\OrtCase::IRAMA_MIN_HARI, min(\App\Models\OrtCase::IRAMA_MAX_HARI, $c->median_gap_days))
        : null;
    $majuHari  = $iramaHari ?? \App\Models\OrtControl::USULAN_MAJU_HARI;

    $defaultBaru = $c->last_visit_date
        ? max($c->last_visit_date->copy()->addDays($majuHari), $hariIni->copy()->addDays(3))
        : $hariIni->copy()->addDays(3);
    $lamaSejak = $c->last_visit_date ? $c->last_visit_date->diffInDays($hariIni) : null;

@endphp

<a href="{{ $tutup }}" class="ort-scrim ort-scrim--sheet" aria-label="Tutup"></a>

<aside class="ort-sheet" role="dialog" aria-label="{{ $judul[1] }}">
    <div class="ort-sheet__head">
        <a href="{{ $tutup }}" class="ort-drawer__close" style="width:30px;height:30px;" title="Tutup"><i class="fas fa-times"></i></a>
        <div class="ort-drawer__kicker">{{ $judul[0] }}</div>
        <div class="ort-sheet__title">{{ $judul[1] }}</div>
        <div class="ort-drawer__meta ort-drawer__meta--dim">{{ $sub }}</div>
    </div>

    {{-- Varian "reservasi" adalah SATU-SATUNYA yang sudah punya aksi: ia
         mengoper ort_control_id + tanggal ke form Reservasi yang sudah ada
         (ReservationController::create, bridge ?ort_control_id). Tiga varian
         lain masih inert. --}}
    @if ($sheet === 'reservasi' && $k)
        {{-- Sasarannya HALAMAN DAFTAR reservasi, bukan /rawreservation/new:
             di modul itu form hidup sebagai side-drawer. Halaman daftar membaca
             ?ort_control_id lalu membuka drawer-nya (lihat ekor
             crud/reservation/list.blade.php). Menuju /new langsung memberi
             halaman penuh — bentuk yang bukan miliknya. --}}
        <form id="ortSheetForm" method="GET" action="{{ route('rawreservation') }}" class="ort-sheet__body">
            <input type="hidden" name="ort_control_id" value="{{ $k->id }}">
    @elseif ($sheet === 'pindahkan')
        <form id="ortSheetForm" method="POST" action="{{ route('ortho.pindahkan', $c->id) }}" class="ort-sheet__body">
            @csrf
    @elseif ($sheet === 'jadwalkan')
        <form id="ortSheetForm" method="POST" action="{{ route('ortho.jadwalkan', $c->id) }}" class="ort-sheet__body">
            @csrf
    @elseif ($sheet === 'follow-up' && $k)
        {{-- Action-nya berakhiran /follow-up, jadi tercegat POLA di list.blade.php:
             submit lewat fetch, tabel ditukar, toast muncul, lembar ditutup —
             tanpa muat ulang. Tanpa JS tetap bekerja sebagai form biasa. --}}
        <form id="ortSheetForm" method="POST" action="{{ route('ortho.followup', $k->id) }}" class="ort-sheet__body">
            @csrf
    @elseif ($sheet === 'geser' && $k)
        {{-- Menggeser SEKALIGUS mengonfirmasi: begitu Sales mengetik tanggal
             sendiri, itu bukan usulan mesin lagi. --}}
        <form id="ortSheetForm" method="POST" action="{{ route('ortho.geser', $k->id) }}" class="ort-sheet__body">
            @csrf
    @else
        <form class="ort-sheet__body" onsubmit="return false;">
    @endif

        {{-- Pesan gagal harus muncul DI DALAM lembar. layouts.notif merender
             di halaman belakang, tertutup scrim — Sales tidak akan pernah
             melihatnya, dan tombol simpan terlihat seperti tidak bereaksi. --}}
        @if ($errors->any() || session('error'))
            <div class="ort-sheet__galat">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ $errors->first() ?: session('error') }}</span>
            </div>
        @endif

        @if ($sheet === 'reservasi')
            @php
                /* Hari praktik dokter pasien INI, beserta jam dan seberapa
                   penuh. Dihitung mesin slot milik modul Reservasi (trait
                   ResolvesDoctorAvailability) — bukan rumus kedua di sini,
                   supaya angkanya tidak pernah berbeda dari grid slot yang
                   akan dilihat Sales satu layar kemudian.

                   ⚠ Dua dari tiga kasus di DB pengembangan dokternya TIDAK
                     terpetakan. Jadi daftar kosong bukan keadaan pinggiran:
                     lembarnya wajib tetap berguna, dan alasannya dikatakan. */
                $jd     = app(\App\Services\Ortho\OrtJadwalDokter::class);
                $jadwal = $jd->untuk($c, (string) $c->owner_place_id, $hariIni);
                $alasan = $jd->penjelasan($jadwal['sebab'], $jadwal['dokter']);

                $rsvDefault = $k?->sch_date && $k->sch_date->lt($hariIni)
                    ? $hariIniStr
                    : $k?->sch_date?->toDateString();
            @endphp

            <div class="ort-callfacts">
                @if ($jadwal['dokter'])
                    <span>{{ $jadwal['dokter'] }} · {{ $namaOwn }}</span>
                @else
                    <span>{{ $namaOwn }}</span>
                @endif
                @if ($c->median_gap_days)
                    <span>· pasien ini biasanya kontrol tiap <strong class="tabular">{{ $c->median_gap_days }}</strong> hari</span>
                @endif
            </div>

            @if ($jadwal['hari'])
                <div>
                    <label class="ort-lbl">Hari praktik dokter</label>
                    {{-- Keluaran mesin slot Reservasi: jam sudah dipotong
                         istirahat, cuti, dan pengecualian per tanggal. --}}
                    <div class="ort-hdok">
                        @foreach ($jadwal['hari'] as $h)
                            @php $d = \Carbon\Carbon::parse($h['tanggal']); @endphp
                            {{-- Hari penuh ditandai `disabled` saja. Kelas `is-penuh` pernah
                                 dipasang di sini tapi tidak pernah ada di CSS — seluruh gaya
                                 "penuh" datang dari `:disabled` (ort-tracking:1504). Hook mati
                                 itu dibuang supaya tidak ada yang mengira ia mengatur sesuatu. --}}
                            <button type="button"
                                    class="ort-hdok__row"
                                    data-ort-tgl="{{ $h['tanggal'] }}"
                                    {{ $h['penuh'] ? 'disabled' : '' }}
                                    title="{{ $h['penuh'] ? 'Semua slot hari ini sudah terisi' : 'Pilih hari ini, jam persisnya dipilih di form Reservasi' }}">
                                <span class="ort-hdok__hari">{{ $d->translatedFormat('D, j M') }}</span>
                                <span class="ort-hdok__jam tabular">{{ $h['jam'] ?? '—' }}</span>
                                <span class="ort-hdok__isi tabular">
                                    @if ($h['penuh'])
                                        penuh
                                    @elseif ($h['total'] > 0)
                                        {{ $h['terisi'] }}/{{ $h['total'] }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif ($alasan)
                <div class="ort-hint">{{ $alasan }} Isi tanggalnya sendiri di bawah.</div>
            @endif

            <div>
                <label class="ort-lbl">Tanggal reservasi</label>
                <input type="date" name="tanggal" class="ort-inp" required
                       data-ort-tgl-input value="{{ $rsvDefault }}" min="{{ $hariIniStr }}">
                <div class="ort-hint">
                    @if ($k?->sch_date && $k->sch_date->lt($hariIni))
                        Jatuh temponya {{ $k->sch_date->translatedFormat("j M 'y") }} — sudah terlewat,
                        jadi default dimajukan ke hari ini.
                    @else
                        Default mengikuti jatuh tempo {{ $k?->sch_date?->translatedFormat("j M 'y") }}
                    @endif
                </div>
            </div>

            <div class="ort-infobox">
                <div class="ort-infobox__k">Jamnya dipilih di modul Reservasi</div>
                <div>
                    Form Reservasi terbuka ter-isi nama {{ \Illuminate\Support\Str::title($c->patient_name) }},
                    cabang, dokter, dan tanggal di atas. Grid slot jam ada di sana, lengkap dengan
                    slot yang sudah terisi — dan aturan anti-bentrok juga dijaga di sana, bukan di sini.
                </div>
            </div>

            <div>
                <label class="ort-lbl">Catatan (opsional)</label>
                <input type="text" name="catatan" class="ort-inp" placeholder="mis. minta jadwal sore" maxlength="120">
            </div>

            {{-- Pemilih hari cuma MENGISI kotak tanggal di atas, bukan medan
                 tersendiri. Jadi tanpa JavaScript lembarnya tetap bekerja
                 penuh: kotak tanggalnya sudah terisi default dan bisa diketik.
                 Itu juga sebabnya tidak ada nama medan baru yang harus
                 dimengerti ReservationController. --}}
            <script>
            (function () {
                var kotak = document.querySelector('[data-ort-tgl-input]');
                if (!kotak) return;
                document.querySelectorAll('[data-ort-tgl]').forEach(function (b) {
                    b.addEventListener('click', function () {
                        kotak.value = b.getAttribute('data-ort-tgl');
                        document.querySelectorAll('[data-ort-tgl]').forEach(function (x) {
                            x.classList.remove('is-pilih');
                        });
                        b.classList.add('is-pilih');
                    });
                });
            })();
            </script>

        @elseif ($sheet === 'pindahkan')
            <div>
                <label class="ort-lbl">Cabang tujuan</label>
                <select name="tujuan" class="ort-inp" required>
                    {{-- (string) wajib: PHP mengubah kunci array '29957' jadi int,
                         dan int === string selalu false — cabang sendiri lolos masuk daftar. --}}
                    @foreach ($peta as $pid => $b)
                        @continue((string) $pid === (string) $c->owner_place_id)
                        <option value="{{ $pid }}">{{ $b['nama'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ort-lbl">Alasan</label>
                <select name="alasan" class="ort-inp" required>
                    <option value="">Pilih alasan...</option>
                    @foreach (\App\Models\OrtTransfer::REASONS as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
                <div class="ort-hint">Wajib — tanpa alasan tercatat, perpindahan pasien tidak bisa ditelusuri belakangan.</div>
            </div>
            <div>
                <label class="ort-lbl">Catatan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" class="ort-inp"
                       placeholder="mis. pasien pindah kerja ke daerah situ">
            </div>
            <label class="ort-checkcard">
                <input type="checkbox" name="sementara" value="1">
                <span class="ort-checkcard__box"><i class="fas fa-check"></i></span>
                <span>Kontrol sekali saja di sana, setelah itu kembali ke {{ $namaOwn }}</span>
            </label>
            <div class="ort-infobox ort-infobox--brown">
                <i class="fas fa-info-circle"></i>
                <div>
                    Pasien akan dipindahkan ke cabang tujuan. Cabang tujuan yang akan menghubungi
                    pasien untuk kontrol berikutnya — {{ $namaOwn }} tetap bisa melihat
                    perkembangannya dari tab cabang itu.
                </div>
            </div>

        @elseif ($sheet === 'geser')
            <div>
                <label class="ort-lbl">Tanggal usulan</label>
                @php
                    /* value tidak boleh lebih tua dari min — input jadi tidak sah dan
                       form diam saja saat dikirim, tanpa pesan apa pun. Usulan yang
                       sudah lewat dimajukan ke batas paling cepat. */
                    $geserDefault = $k?->sch_date && $k->sch_date->toDateString() < $minTgl
                        ? $minTgl : $k?->sch_date?->toDateString();
                @endphp
                <input type="date" name="tanggal" class="ort-inp" required
                       value="{{ $geserDefault }}" min="{{ $minTgl }}">
                {{-- Angka pembandingnya irama pasien INI, bukan rentang baku.
                     "Jarak wajar 28-45 hari" benar sebagai rata-rata klinik tapi
                     tidak membantu di layar ini: yang perlu diketahui Sales saat
                     menggeser adalah kebiasaan orang yang sedang ditelepon. --}}
                <div class="ort-hint">
                    Geser usulan sistem ke tanggal yang sudah disepakati dengan pasien.
                    @if ($iramaHari)
                        Pasien ini biasanya kontrol tiap <span class="tabular">{{ $iramaHari }}</span> hari.
                    @else
                        Riwayatnya belum cukup untuk tahu kebiasaannya — jarak wajar 28–45 hari.
                    @endif
                </div>
            </div>
            <div>
                <label class="ort-lbl">Catatan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" class="ort-inp" placeholder="mis. pasien minta setelah tanggal gajian">
            </div>

        @elseif ($sheet === 'follow-up')
            <div>
                <label class="ort-lbl">Hasil</label>
                {{-- Pilihan tetap, bukan teks bebas: supaya "berapa pasien tidak
                     respon bulan ini" bisa dijawab. Kamusnya satu-satunya di
                     OrtControl::FU_HASIL — jangan ditulis ulang di sini. --}}
                <select name="hasil" class="ort-inp" required>
                    <option value="">Pilih hasil...</option>
                    @foreach (\App\Models\OrtControl::FU_HASIL as $val => $label)
                        <option value="{{ $val }}" {{ old('hasil') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ort-2col">
                <div>
                    <label class="ort-lbl">Kanal</label>
                    <select name="kanal" class="ort-inp">
                        <option value="wa">WhatsApp</option>
                        <option value="telepon">Telepon</option>
                        <option value="langsung">Langsung (di klinik)</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="ort-lbl">Tanggal</label>
                    {{-- max = hari ini: follow-up dicatat SESUDAH terjadi, bukan direncanakan. --}}
                    <input type="date" name="tanggal" class="ort-inp"
                           value="{{ $hariIniStr }}" max="{{ $hariIniStr }}">
                </div>
            </div>
            <div>
                <label class="ort-lbl">Catatan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" class="ort-inp"
                       placeholder="mis. minta dihubungi lagi minggu depan sore">
            </div>
            <div class="ort-infobox">
                <i class="fas fa-info-circle"></i>
                <div>
                    Mencatat follow-up tidak membuat reservasi. Kalau pasien setuju tanggal,
                    anak tangga berikutnya adalah <b>Buat Reservasi</b> — tombolnya muncul
                    sendiri sesudah ini tersimpan.
                </div>
            </div>
        @else {{-- jadwalkan --}}
            <div>
                <label class="ort-lbl">Tanggal kontrol berikutnya</label>
                <input type="date" name="tanggal" class="ort-inp" required
                       value="{{ $defaultBaru->toDateString() }}" min="{{ $minTgl }}">
                <div class="ort-hint">
                    @if ($lamaSejak !== null && $lamaSejak > 60)
                        Kontrol terakhir {{ $lamaSejak }} hari lalu — dijadwalkan dari hari ini, bukan dari kontrol terakhir.
                    @else
                        @if ($iramaHari)
                            <span class="tabular">{{ $iramaHari }}</span> hari dari kontrol terakhir · irama pasien ini
                        @else
                            30 hari dari kontrol terakhir · riwayatnya belum cukup untuk tahu kebiasaannya
                        @endif
                    @endif
                </div>
            </div>
            <div>
                <label class="ort-lbl">Catatan (opsional)</label>
                <input type="text" name="catatan" maxlength="255" class="ort-inp" placeholder="mis. minta kontrol pagi">
            </div>
        @endif

    </form>

    <div class="ort-sheet__foot">
        @if (in_array($sheet, ['jadwalkan', 'pindahkan'], true) || (in_array($sheet, ['reservasi', 'geser', 'follow-up'], true) && $k))
            {{-- Di luar <form>, jadi disambungkan lewat atribut form="". --}}
            <button type="submit" form="ortSheetForm" class="ort-btn"
                    style="flex:1;justify-content:center;padding:12px;">{{ $judul[2] }}</button>
        @else
            <span class="ort-btn ort-btn--mati" style="flex:1;justify-content:center;padding:12px;"
                  aria-disabled="true" title="Aksi tulis menyusul (Tahap 3)">{{ $judul[2] }}</span>
        @endif
        <a href="{{ $tutup }}" class="ort-obtn">Batal</a>
    </div>
</aside>
