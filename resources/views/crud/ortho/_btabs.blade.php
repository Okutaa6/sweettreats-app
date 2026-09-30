{{--
    Tab cabang — berlaku di KETIGA tab (List, Kalender, Pasien Ortho).

    DUA PERAN BERBEDA, tergantung siapa yang login:

    • User TERKUNCI cabang (mayoritas Sales). Pil berbintang = cabang akunnya,
      tidak bisa diganti. Tab lain berarti "pasien KITA yang sedang dipegang
      cabang itu", isinya lihat-saja.

    • Role LINTAS CABANG (Super Admin, Admin General, Admin Backup, Finance —
      lihat canSeeAllBranches()). Pil berbintang jadi PEMILIH: klik, pilih
      cabang mana yang mau dilihat sebagai cabang utama. Jalurnya `?utama=`,
      yang sudah diterima OrthoController::resolveBranch() sejak awal — yang
      selama ini belum ada cuma kontrolnya di layar, jadi satu-satunya cara
      berpindah adalah mengetik URL sendiri.

    Controller mewujudkan mode lihat-saja lewat $lihatPlace / $asalPasien;
    semua endpoint tulis tetap memakai cabang akun, jadi tab cabang lain tidak
    bisa dipakai menulis ke data cabang lain.

    ── Kenapa cabang lain DILIPAT ──────────────────────────────────────────
    Angkanya kecil dan jarang dipakai, tapi dulu memakan satu baris penuh di
    atas pekerjaan utama. Sekarang duduk di balik satu tombol dan membuka
    MENDATAR. Cabang utama TIDAK pernah ikut terlipat — itu jangkar halaman.
    Keadaan buka/tutup diingat per-peramban (localStorage).
--}}
@php
    // Pemilih cabang utama hanya untuk role lintas cabang.
    $bolehPilihCabang = ! ($terkunci ?? true);
@endphp

{{-- Dirender kalau ada pasien di cabang lain ATAU kalau penggunanya boleh
     berpindah cabang. Tanpa syarat kedua, admin lintas cabang yang sedang di
     cabang yang kebetulan tidak menitipkan pasien ke mana pun kehilangan
     pemilihnya sama sekali. --}}
@if (! empty($perCabangLain) || $bolehPilihCabang)
    @php
        $totalLain = array_sum($perCabangLain ?? []);
        // Sedang melihat cabang lain → strip WAJIB terbuka apa pun isi
        // localStorage, kalau tidak tab yang sedang aktif justru tersembunyi.
        $paksaBuka = $cabangDilihat !== null;
    @endphp

    {{-- is-preload mematikan transisi sampai keadaan awal terpasang — tanpa
         itu strip terlihat "melar sendiri" tiap halaman dimuat. Dilepas satu
         frame kemudian oleh skrip di bawah. --}}
    <div class="ort-branchtabs is-preload {{ $paksaBuka ? 'is-open' : '' }}" data-ort-btabs>

        @if ($bolehPilihCabang)
            <div class="ort-btabpick" data-ort-btabpick>
                <button type="button"
                        class="ort-btab ort-btab--pick {{ $cabangDilihat === null ? 'is-active' : '' }}"
                        data-ort-btabpick-toggle aria-expanded="false" aria-haspopup="true"
                        title="Ganti cabang utama — akun Anda punya akses lintas cabang">
                    <i class="fas fa-star ort-btab__star"></i>
                    {{ $namaCabang }}
                    <span class="ort-btab__count tabular">{{ $kartu['aktif'] }}</span>
                    <i class="fas fa-chevron-down ort-btab__caret"></i>
                </button>

                {{-- `utama` diganti; `cabang` dan `page` DIBUANG. Keduanya
                     relatif terhadap cabang utama yang LAMA — membawanya serta
                     berarti membuka tab cabang yang tidak lagi ada isinya, di
                     halaman ke-4 yang tidak lagi ada. --}}
                <div class="ort-btabpick__menu" data-ort-btabpick-menu hidden>
                    <div class="ort-btabpick__head">Lihat sebagai cabang</div>
                    @foreach ($peta as $pid => $b)
                        <a href="{{ $tautan(['utama' => $pid, 'cabang' => null, 'page' => null]) }}"
                           class="ort-btabpick__item {{ (string) $placeId === (string) $pid ? 'is-active' : '' }}">
                            <i class="fas fa-star ort-btabpick__star"></i>
                            <span class="ort-btabpick__nama">{{ $b['nama'] }}</span>
                            <span class="ort-btab__count tabular">{{ $hitungPerCabang[$pid] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <a href="{{ $tautan(['cabang' => null]) }}"
               class="ort-btab {{ $cabangDilihat === null ? 'is-active' : '' }}"
               title="Cabang utama Anda · {{ $namaCabang }}">
                <i class="fas fa-star ort-btab__star"></i>
                {{ $namaCabang }}
                <span class="ort-btab__count tabular">{{ $kartu['aktif'] }}</span>
            </a>
        @endif

        @if (! empty($perCabangLain))
            <button type="button" class="ort-btab ort-btab--more"
                    data-ort-btabs-toggle
                    aria-expanded="{{ $paksaBuka ? 'true' : 'false' }}"
                    aria-controls="ort-btabs-lain"
                    title="Pasien {{ $namaCabang }} yang sedang dipegang cabang lain — hanya bisa dilihat">
                <i class="far fa-eye ort-btab__eye"></i>
                Pasien {{ $namaCabang }} di cabang lain
                <span class="ort-btab__count tabular">{{ $totalLain }}</span>
                <i class="fas fa-chevron-right ort-btab__chev"></i>
            </button>

            {{-- Dua lapis, dan keduanya perlu: yang luar YANG BERANIMASI (kolom grid
                 0fr → 1fr, melebar persis seukuran isinya), yang dalam yang
                 MEMOTONG. Saat tertutup lapis luar juga visibility:hidden — lebar 0
                 saja TIDAK mengeluarkan tautan dari urutan Tab keyboard maupun
                 pembaca layar. --}}
            <div class="ort-branchtabs__wrap" id="ort-btabs-lain">
                <div class="ort-branchtabs__more">
                    @foreach ($perCabangLain as $pid => $n)
                        <a href="{{ $tautan(['cabang' => $pid, 'kartu' => null, 'urgensi' => null]) }}"
                           {{-- (string) wajib: PHP mengubah kunci array numerik '29958'
                                jadi int, dan int === string selalu false — tanpa cast, tab
                                cabang lain TIDAK PERNAH dapat kelas is-active, jadi tidak
                                pernah berubah cokelat. --}}
                           class="ort-btab {{ (string) $cabangDilihat === (string) $pid ? 'is-active' : '' }}"
                           title="Pasien {{ $namaCabang }} yang sedang dipegang {{ $peta[$pid]['nama'] ?? $pid }} · hanya bisa dilihat">
                            {{ $peta[$pid]['nama'] ?? $pid }}
                            <span class="ort-btab__count tabular">{{ $n }}</span>
                        </a>
                    @endforeach

                    {{-- SENGAJA pendek. Kalimat panjangnya (421px) sendirian yang
                         dulu mendorong seluruh strip turun ke baris kedua di layar
                         1366px ke bawah. Versi utuhnya ada di title. --}}
                    <span class="ort-branchtabs__hint"
                          title="Pasien {{ $namaCabang }} yang sedang dipegang cabang lain. {{ $namaCabang }} bisa memantau, tapi cabang itu yang menghubungi pasien dan menjadwalkan kontrolnya.">
                        hanya bisa dilihat
                    </span>
                </div>
            </div>
        @endif
    </div>

    <script>
    /* Strip cabang lain + pemilih cabang utama. Pola yang sama dengan panel
       filter (_filter.blade.php): markup + skrip kecil di partial-nya
       sendiri, keadaan awal ditentukan server. */
    (function () {
        var strip = document.querySelector('[data-ort-btabs]');
        if (! strip) { return; }

        /* ── Strip cabang lain: mendatar, keadaannya diingat ───────────── */
        var tombol = strip.querySelector('[data-ort-btabs-toggle]');
        if (tombol) {
            var KUNCI = 'ort.btabs.lain';
            var paksa = @json($paksaBuka);

            /* localStorage bisa MELEMPAR galat, bukan cuma kosong (jendela
               privat, site data diblokir). Dibungkus supaya halaman tetap
               benar tanpa penyimpanan — bawaannya tertutup. */
            var baca  = function () { try { return localStorage.getItem(KUNCI) === '1'; } catch (e) { return false; } };
            var tulis = function (b) { try { localStorage.setItem(KUNCI, b ? '1' : '0'); } catch (e) { /* diabaikan */ } };
            var pasang = function (b) {
                strip.classList.toggle('is-open', b);
                tombol.setAttribute('aria-expanded', b ? 'true' : 'false');
            };

            pasang(paksa || baca());
            tombol.addEventListener('click', function () {
                var buka = ! strip.classList.contains('is-open');
                pasang(buka);
                tulis(buka);
            });
        }

        /* Transisi baru dinyalakan sesudah keadaan awal terpasang dan satu
           frame berlalu — inilah yang mencegah animasi melar saat memuat. */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { strip.classList.remove('is-preload'); });
        });

        /* ── Pemilih cabang utama: hanya ada untuk role lintas cabang ──── */
        var pick = strip.querySelector('[data-ort-btabpick]');
        if (! pick) { return; }
        var pickBtn  = pick.querySelector('[data-ort-btabpick-toggle]');
        var pickMenu = pick.querySelector('[data-ort-btabpick-menu]');

        function setPick(buka) {
            pickMenu.hidden = ! buka;
            pick.classList.toggle('is-open', buka);
            pickBtn.setAttribute('aria-expanded', buka ? 'true' : 'false');
        }

        pickBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            setPick(pickMenu.hidden);
        });

        /* Klik di luar dan Esc menutup. Tanpa ini menunya menggantung terbuka
           sementara perhatian sudah pindah ke tempat lain. */
        document.addEventListener('click', function (e) {
            if (! pickMenu.hidden && ! pick.contains(e.target)) { setPick(false); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && ! pickMenu.hidden) { setPick(false); pickBtn.focus(); }
        });
    })();
    </script>
@endif
