{{--
    Tab cabang — berlaku di KETIGA tab (List, Kalender, Pasien Ortho).

    Artinya bukan "ganti cabang": cabang tetap terkunci ke akun. Tab selain
    yang berbintang berarti "pasien KITA yang sedang dipegang cabang itu",
    dan isinya lihat-saja. Controller mewujudkannya lewat sepasang nilai
    $lihatPlace / $asalPasien; semua endpoint tulis tetap memakai cabang akun,
    jadi mode ini tidak bisa dipakai menulis ke data cabang lain.

    Angka di tab berbintang = jumlah pasien aktif cabang sendiri. Angka di tab
    lain = jumlah pasien kita yang dipegang di sana (hitungPerCabangLain).

    ── Kenapa cabang lain DILIPAT ──────────────────────────────────────────
    Angka-angka itu kecil dan jarang dipakai, tapi dulu memakan satu baris
    penuh di atas pekerjaan utama: 3 pasien yang dipegang cabang lain mendapat
    ruang sebanyak 31 pasien cabang sendiri. Sekarang mereka duduk di balik
    satu tombol dan membuka MENDATAR.

    Cabang sendiri TIDAK pernah ikut terlipat — itu jangkar halaman.

    Keadaan buka/tutup diingat per-peramban (localStorage). Halaman ini sering
    memuat ulang (filter, sub-tab, paginasi); strip yang menutup sendiri tiap
    kali akan terasa melawan.
--}}
@if (! empty($perCabangLain))
    @php
        // Angka di tombol = jumlah PASIEN, bukan jumlah cabang. Yang ditagih
        // Sales orangnya.
        $totalLain = array_sum($perCabangLain);

        // Sedang melihat cabang lain → strip WAJIB terbuka apa pun isi
        // localStorage, kalau tidak tab yang sedang aktif justru tersembunyi.
        $paksaBuka = $cabangDilihat !== null;
    @endphp

    {{-- is-preload mematikan transisi sampai keadaan awal terpasang — tanpa
         itu strip terlihat "melar sendiri" tiap halaman dimuat. Dilepas satu
         frame kemudian oleh skrip di bawah. --}}
    <div class="ort-branchtabs is-preload {{ $paksaBuka ? 'is-open' : '' }}" data-ort-btabs>
        <a href="{{ $tautan(['cabang' => null]) }}"
           class="ort-btab {{ $cabangDilihat === null ? 'is-active' : '' }}"
           title="Cabang utama Anda · {{ $namaCabang }}">
            <i class="fas fa-star ort-btab__star"></i>
            {{ $namaCabang }}
            <span class="ort-btab__count tabular">{{ $kartu['aktif'] }}</span>
        </a>

        <button type="button" class="ort-btab ort-btab--more"
                data-ort-btabs-toggle
                aria-expanded="{{ $paksaBuka ? 'true' : 'false' }}"
                aria-controls="ort-btabs-lain"
                title="Pasien {{ $namaCabang }} yang sedang dipegang cabang lain — hanya bisa dilihat">
            <i class="far fa-eye ort-btab__eye"></i>
            {{-- Nama cabang ikut disebut supaya kepemilikannya tegas: ini
                 pasien KITA yang sedang dipegang orang lain, bukan pasien
                 cabang lain. Ikut akun yang login — di Sunter berbunyi
                 "Pasien Sunter di cabang lain". --}}
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
                     1366px ke bawah — padahal tab-tabnya sendiri cuma butuh
                     663px dari 965px yang tersedia. Yang tidak boleh hilang
                     cuma batasannya; "pasien siapa di mana" sudah terbaca dari
                     label tombolnya. Versi utuhnya ada di title. --}}
                <span class="ort-branchtabs__hint"
                      title="Pasien {{ $namaCabang }} yang sedang dipegang cabang lain. {{ $namaCabang }} bisa memantau, tapi cabang itu yang menghubungi pasien dan menjadwalkan kontrolnya.">
                    hanya bisa dilihat
                </span>
            </div>
        </div>
    </div>

    <script>
    /* Buka/tutup strip cabang lain. Pola yang sama dengan panel filter
       (_filter.blade.php): markup + skrip kecil di partial-nya sendiri,
       keadaan awal ditentukan server. Bedanya memakai KELAS, bukan atribut
       `hidden` — `hidden` tidak bisa dianimasikan, dan ini harus bergerak
       mendatar. */
    (function () {
        var strip  = document.querySelector('[data-ort-btabs]');
        var tombol = document.querySelector('[data-ort-btabs-toggle]');
        if (! strip || ! tombol) { return; }

        var KUNCI = 'ort.btabs.lain';
        var paksa = @json($paksaBuka);

        /* localStorage bisa MELEMPAR galat, bukan cuma kosong (jendela privat,
           site data diblokir). Dibungkus supaya halaman tetap benar tanpa
           penyimpanan — bawaannya tertutup. */
        function baca() {
            try { return localStorage.getItem(KUNCI) === '1'; } catch (e) { return false; }
        }
        function tulis(buka) {
            try { localStorage.setItem(KUNCI, buka ? '1' : '0'); } catch (e) { /* diabaikan */ }
        }

        function pasang(buka) {
            strip.classList.toggle('is-open', buka);
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        }

        // Keadaan awal: paksa menang atas ingatan.
        pasang(paksa || baca());

        // Transisi baru dinyalakan setelah keadaan awal terpasang dan satu
        // frame berlalu — inilah yang mencegah animasi melar saat muat halaman.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () { strip.classList.remove('is-preload'); });
        });

        tombol.addEventListener('click', function () {
            var buka = ! strip.classList.contains('is-open');
            pasang(buka);
            tulis(buka);
        });
    })();
    </script>
@endif
