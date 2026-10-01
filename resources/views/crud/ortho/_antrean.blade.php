{{--
    Antrean penjadwalan — isi kartu ke-3.

    Ini antrean yang SEBENARNYA di hari-hari awal. "Terlewat" cuma bisa ada
    kalau seseorang sudah menjadwalkan duluan; pasien yang baru dimuat dari
    rekam medis belum punya jadwal sama sekali, jadi mereka tidak akan pernah
    muncul di sana.

    Dua tingkat kegentingan, urutan tetap, paling lama menghilang di atas.
    Ambangnya definisi Sales sendiri — 180 hari untuk "Hilang", kurang dari 2
    kunjungan per 6 bulan untuk "Jarang kontrol".

    Satu tindakan saja: Jadwalkan kontrol. Begitu dijadwalkan, barisnya keluar
    dari antrean ini dan masuk alur normal — antreannya mengecil sendiri.
--}}
<div class="ort-meta-line">
    <span>
        <i class="far fa-clock" style="color:#A9866A;"></i>
        Antrean penjadwalan ·
        <strong class="tabular">{{ $baris->total() }}</strong> pasien belum punya jadwal ·
        paling genting di atas
    </span>
    <span style="color:#9A7256;">Satu tindakan per baris: tentukan tanggal kontrolnya</span>
</div>

<div class="st-card" style="margin-top:10px;">
    <div class="ort-tablewrap">

        <div class="ort-thead">
            <div class="ort-grid ort-grid--antrean">
                <div>Pasien</div>
                <div>Dokter</div>
                <div>Kontrol terakhir</div>
                <div>Riwayat 6 bulan</div>
                <div>Cabang</div>
                {{-- Disebut "Status", sama seperti kolom sejenis di daftar
                     kerja. Satu hal yang sama tidak boleh punya dua nama. --}}
                <div>Status</div>
                <div></div>
            </div>
        </div>

        @php $adaBaris = false; @endphp

        @foreach ($kelompok as $key => $grup)
            @continue(empty($grup['baris']))
            @php $adaBaris = true; @endphp

            {{-- Judul kelompok hanya berguna saat KEDUA tingkat tampil
                 bersama. Begitu satu tingkat dipilih lewat sub-tab, judulnya
                 cuma mengulang tab yang sedang menyala. --}}
            <div class="ort-group" @if (($urgensi ?? 'semua') !== 'semua') hidden @endif>
                <span class="ort-group__dot" style="background:{{ $grup['warna'] }}"></span>
                <span class="ort-group__label">{{ $grup['label'] }}</span>
                <span class="ort-group__n tabular">{{ count($grup['baris']) }}</span>
                <span class="ort-group__hint">{{ $grup['hint'] }}</span>
            </div>

            @foreach ($grup['baris'] as $c)
                @php
                    $hari   = $c->last_visit_date ? $c->last_visit_date->diffInDays($hariIni) : null;
                    $bulan  = $hari !== null ? (int) floor($hari / 30) : null;
                    $merah  = $key === 'hilang';
                    $baru   = $key === 'baru';
                    $wa     = \App\Support\Phone::toWa($c->patient_phone);

                    // Chip mengikuti KELOMPOKNYA, bukan menebak sendiri. Dulu
                    // binernya cuma hilang/jarang, jadi begitu kelompok ketiga
                    // kembali, pasien "baru selesai kontrol" berchip "Jarang
                    // kontrol" — bertentangan dengan judul di atasnya sendiri.
                    [$chipTeks, $chipKelas] = match (true) {
                        $merah => [$bulan !== null ? 'Hilang ' . $bulan . ' bulan' : 'Belum pernah datang',
                                   'ort-chip--telatlama'],
                        $baru  => ['Baru selesai kontrol', 'ort-chip--hadir'],
                        default => ['Jarang kontrol', 'ort-chip--telat'],
                    };
                @endphp

                <div class="ort-tr {{ $merah ? 'ort-tr--bad' : ($baru ? '' : 'ort-tr--warn') }}"
                     data-ort-row="{{ $c->id }}">
                    <div class="ort-grid ort-grid--antrean">

                        <div style="min-width:0;">
                            <div class="ort-name">{{ \Illuminate\Support\Str::title($c->patient_name) }}</div>

                            {{-- Nomor telepon persis seperti di daftar kerja: Sales
                                 menghubungi dari sini juga, jadi nomornya harus ada
                                 tanpa perlu membuka baris. --}}
                            @if ($wa)
                                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                                   class="ort-wa" onclick="event.stopPropagation()">
                                    <i class="fab fa-whatsapp"></i> {{ $c->patient_phone }}
                                </a>
                            @elseif ($c->patient_phone)
                                <span class="ort-wa" style="color:var(--ort-warn);"
                                      title="Nomor tidak valid, tidak bisa dibuka di WhatsApp">
                                    <i class="fas fa-exclamation-triangle"></i> {{ $c->patient_phone }}
                                </span>
                            @endif
{{-- Baris meta "pid · bracket · dokter · kontrol ke-N" DIBUANG dari
                                 sini. Diklem dua baris pun ia tetap sesak, dan tak satu
                                 pun isinya dibutuhkan untuk memutuskan "jadwalkan atau
                                 tidak". Semuanya sekarang ada di panel ⌄. --}}
                        </div>

                        <div class="ort-cell">{{ $c->doctor_name ?: '—' }}</div>

                        <div class="ort-cell {{ $merah ? 'ort-late' : '' }}">
                            @if ($c->last_visit_date)
                                <span class="tabular">{{ $c->last_visit_date->translatedFormat("j M 'y") }}</span>
                                <span class="ort-cell--sub tabular">{{ $hari }} hari lalu</span>
                            @else
                                <span class="ort-x">belum pernah datang</span>
                            @endif
                        </div>

                        <div>
                            <span class="ort-chip {{ $c->isJarangKontrol() ? 'ort-chip--telat' : 'ort-chip--menunggu' }}">
                                {{ $c->visit_count_180d }} kontrol dalam 6 bulan
                            </span>
                        </div>

                        <div class="ort-cell">{{ $peta[$c->owner_place_id]['nama'] ?? $c->owner_place_id }}</div>

                        <div>
                            <span class="ort-chip {{ $chipKelas }}">{{ $chipTeks }}</span>
                        </div>

                        <div class="ort-actions">
                            <a href="{{ $tautan(['sheet' => 'jadwalkan', 'kasus' => $c->id]) }}" class="ort-btn">
                                <i class="fas fa-calendar-plus"></i> Jadwalkan kontrol
                            </a>
                            @if ($wa)
                                <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"
                                   class="ort-btn--wa" title="Buka WhatsApp {{ $c->patient_name }}">
                                    <i class="fab fa-whatsapp"></i></a>
                            @endif
                            {{-- Toggle panel, bukan tautan ke drawer. "Buka detail
                                 pasien" tetap ada — di dalam panelnya. --}}
                            <button type="button" class="ort-toggle" data-ort-toggle="{{ $c->id }}"
                                    title="Lihat ringkas pasien dan 4 kontrol terakhir">
                                <i class="fas fa-chevron-down"></i></button>
                        </div>

                    </div>
                </div>

                @include('crud.ortho._antrean_terbuka', ['c' => $c])
            @endforeach
        @endforeach

        @unless ($adaBaris)
            <div style="padding:8px 0;">
                @include('crud.ortho._kosong', ['adaFilter' => $adaFilter, 'antrean' => true])
            </div>
        @endunless

    </div>

    <div class="ort-foot">
        <span>Pasien dimuat dari rekam medis — Sales hanya menentukan jadwalnya</span>
        <span class="tabular">{{ $baris->total() }} dari {{ $kartu['aktif'] }} pasien ortho aktif</span>
    </div>

    @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'pasien'])
</div>

<script>
/* Buka/tutup panel ringkas — perilaku sama dengan tabel daftar kerja, tapi
   skripnya berdiri sendiri karena _tabel.blade.php tidak dirender di mode
   antrean. Sengaja tidak dijadikan satu berkas bersama: keduanya cuma
   ~20 baris, dan menyatukannya berarti satu berkas lagi yang harus dimuat
   di halaman yang tidak memakainya. */
(function () {
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

    document.querySelectorAll('[data-ort-row]').forEach(function (row) {
        row.addEventListener('click', function () { buka(row.dataset.ortRow, row); });
    });

    document.querySelectorAll('[data-ort-toggle]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            var row = b.closest('[data-ort-row]');
            if (row) buka(b.dataset.ortToggle, row);
        });
    });
})();
</script>
