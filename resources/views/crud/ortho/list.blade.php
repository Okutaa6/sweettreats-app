{{--
    Tracking Pasien Ortho — halaman daftar (Sales, zona cokelat/operasional).
    Partial-nya semua di folder yang sama, berawalan garis bawah.

    Tiga keadaan dirender dari berkas ini:
      • daftar kerja harian          (kartu 1/2 atau tanpa kartu)
      • antrean penjadwalan          (kartu ke-3 dipilih)
      • tab cabang lain, lihat-saja  (?cabang=<place_id>)

    ⚠ DESIGN BRIEF §22 — yang TIDAK BOLEH muncul di halaman ini:
      kode cabang (BIN/SUN), tombol "Catat Kunjungan", nomor RM di baris List
      maupun di placeholder pencarian, chip "Aktif" di kartu pasien, FILTER
      CABANG (cabang dikunci akun, jadi tidak pernah jadi chip filter), teks
      "N jadwal kontrol di bulan ini" di kalender, teks instruksi "klik baris
      untuk…", emoji, dan dialog konfirmasi.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet"
      href="{{ asset('assets/css/ort-tracking.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/ort-tracking.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $tabs = [
        'list'     => ['label' => 'List',         'ikon' => 'fa-list-ul'],
        'kalender' => ['label' => 'Kalender',     'ikon' => 'fa-calendar-alt'],
        'pasien'   => ['label' => 'Pasien Ortho', 'ikon' => 'fa-user-friends'],
    ];

    $urgensiTabs = [
        'semua'      => ['Semua',      null],
        'terlewat'   => ['Terlewat',   '#C0392B'],
        'hari-ini'   => ['Hari ini',   '#C9962F'],
        'minggu-ini' => ['Minggu ini', '#B5701A'],
        'mendatang'  => ['Mendatang',  '#A9866A'],
    ];

    $tautan = fn (array $ubah = []) => route('ortho.index', array_merge(
        request()->except(['page', 'frag']), $ubah
    ));

    /* Tautan ke drawer detail pasien. WAJIB membawa keadaan daftar yang sedang
       dilihat — sub-tab kegentingan, tab cabang, kartu antrean, pencarian,
       halaman ke berapa. Tanpa ini drawer memang terbuka, tapi daftar di
       BELAKANGNYA ter-reset ke "Semua halaman 1", dan tombol tutup (yang
       memakai request()->query()) memulangkan Sales ke tempat yang salah.
       Kunci sekali-pakai dibuang supaya lembar/drawer lain tidak ikut terbuka
       menumpuk di atas drawer ini. */
    $tautanDetail = function (string $id) {
        $sisa = request()->except(['sheet', 'kasus', 'kontrol', 'tambah', 'hari', 'frag']);
        return route('ortho.detail', $id) . ($sisa ? '?' . http_build_query($sisa) : '');
    };

    $adaFilter   = ! empty($filter);
    $modeAntrean = ($kartuAktif ?? null) === 'backlog';
    $modeSelesai = $tab === 'pasien' && ($rawat ?? 'aktif') === 'selesai';

    $labelFilter = ['q' => 'Cari', 'dokter' => 'Dokter', 'dari' => 'Dari', 'sampai' => 'Sampai'];
@endphp

<div class="ort">

    @include('layouts.notif')
    @include('crud.ortho._toast')

    {{-- ── Aksi kanan. Tab tingkat atas TIDAK di sini — lihat _navrow,
         yang duduk tepat di atas isi tab supaya dekat dengan yang diubahnya. --}}
    <div class="ort-toprow ort-toprow--aksi">
        <div class="ort-topact">
            <button type="button" class="ort-obtn {{ $adaFilter ? 'is-on' : '' }}" data-ort-filter>
                <i class="fas fa-filter" style="font-size:10px;"></i> Filter
                @if ($adaFilter)
                    <span class="ort-obtn__n tabular">{{ count($filter) }}</span>
                @endif
            </button>
            {{-- Tombol "+ Tambah Pasien Ortho" dicabut. Drawer-nya SENGAJA
                 dibiarkan utuh (_drawer_tambah + fragmen ?tambah=1): tombol
                 Simpannya memang belum tersambung, dan menghidupkannya adalah
                 salah satu pekerjaan yang masih tersisa. Yang dicabut cuma
                 pintunya, bukan ruangannya. --}}
        </div>
    </div>

    @include('crud.ortho._filter', ['tautan' => $tautan])

    {{-- ── Chip filter aktif. TIDAK ADA chip cabang di sini — cabang dikunci
         akun dan bukan filter (brief §6). ──────────────────────────────── --}}
    @if ($adaFilter)
        <div class="ort-chips">
            <span class="ort-chips__label">Filter aktif:</span>
            @foreach ($filter as $k => $v)
                <a href="{{ $tautan([$k => null]) }}" class="ort-chipf">
                    {{ $labelFilter[$k] ?? $k }}: {{ $k === 'dokter' ? ($dokterPilihan[$v] ?? $v) : $v }}
                    <i class="fas fa-times"></i>
                </a>
            @endforeach
        </div>
    @endif

    {{-- ── Spanduk hari pertama (§18): pasien sudah dimuat, belum ada satu pun
         jadwal. Kartu Terlewat berbunyi nol dan halaman terlihat tenang —
         spanduk ini yang menjelaskan kenapa, dan mengarahkan ke antrean. ── --}}
    @if (($hariPertama ?? false) && $tab === 'list' && ! $hanyaLihat)
        <div class="ort-banner">
            <span class="ort-banner__icon"><i class="fas fa-exclamation-triangle"></i></span>
            <div style="flex:1;min-width:0;">
                <div class="ort-banner__title">Hari pertama — pasien sudah dimuat, jadwalnya belum ada</div>
                <div class="ort-banner__text">
                    <span class="tabular">{{ number_format($kartu['aktif'], 0, ',', '.') }}</span> pasien behel di
                    {{ $namaCabang }} baru dimuat dari rekam medis dan belum punya jadwal kontrol. Kartu
                    "Terlewat" masih nol karena belum ada jadwal yang bisa terlewat — antrean sesungguhnya
                    ada di "Belum punya jadwal", terurut dari yang paling genting.
                </div>
            </div>
            <a href="{{ $tautan(['kartu' => 'backlog', 'tab' => null]) }}" class="ort-btn">Buka antrean penjadwalan</a>
        </div>
    @endif

    {{-- ── Kendali yang berlaku di SEMUA tab ───────────────────────────
         Kartu ringkas, tab cabang, lalu pita (sub-tab kegentingan + tab
         utama). Ketiganya sengaja dirender di luar percabangan tab: tab
         cabang dan sub-tab kegentingan menyaring isi tab MANA PUN, jadi
         kalau ia hilang saat pindah tab, pengguna kehilangan jejak
         cabang dan rentang yang sedang dilihatnya.

         Kecualinya cuma antrean penjadwalan: isinya justru pasien yang
         BELUM punya jadwal, jadi menyaringnya per rentang tanggal tidak
         berarti apa-apa. --}}
    @include('crud.ortho._cards', ['tautan' => $tautan])
    @include('crud.ortho._btabs')
    {{-- Sisi kiri pita. Daftar kerja memakai sub-tab tanggal; antrean
         penjadwalan memakai tingkat kegentingannya sendiri. Bentuk dan
         tempatnya sama, jadi tab-nya tidak terasa hilang saat berpindah.

         Satu-satunya yang kosong: Pasien Ortho → Selesai. Pasien yang
         perawatannya tuntas tidak punya jadwal, jadi "terlewat / hari ini /
         minggu ini" tidak punya arti — menampilkannya berarti angka nol
         yang membingungkan. --}}
    @include('crud.ortho._navrow', [
        'kiri' => $modeAntrean ? 'crud.ortho._nav_antrean' : ($modeSelesai ? null : 'crud.ortho._nav_urgensi'),
    ])

    @if ($tab === 'pasien')

        @include('crud.ortho._roster', ['tautan' => $tautan])

    @elseif ($tab === 'kalender')

        @include('crud.ortho._kalender', ['tautan' => $tautan])
        @if ($hariDrawer ?? null)
            @include('crud.ortho._drawer_hari', ['tautan' => $tautan])
        @endif

    @elseif ($tab !== 'list')

        {{-- JANGAN memulangkan lebih awal dari dalam blok php Blade: `return`
             menghentikan seluruh section, sehingga kerangka layout ikut hilang.

             Nama directive-nya SENGAJA tidak ditulis di sini. compileStatements
             jalan SEBELUM compileComments, jadi directive di dalam komentar pun
             tetap dikompilasi — versi lama komentar ini berisi `return` hidup
             yang cuma selamat karena cabang @elseif di atasnya tak pernah kena
             (controller mengunci tab ke list/kalender/pasien). --}}
        <div class="st-card" style="margin-top:13px;">
            <div class="st-card__body">
                <div class="st-empty">
                    <div class="st-empty__icon"><i class="fas {{ $tabs[$tab]['ikon'] }}"></i></div>
                    <div class="st-empty__title">Tab {{ $tabs[$tab]['label'] }} menyusul</div>
                    <div class="st-empty__text">
                        Tab List sudah berjalan penuh — daftar kerja harian, antrean
                        penjadwalan, dan tab cabang lain semuanya sudah bisa dipakai.
                    </div>
                    <div style="margin-top:14px;">
                        <a href="{{ $tautan(['tab' => 'list']) }}" class="ort-obtn">
                            <i class="fas fa-list-ul" style="font-size:10px;"></i> Kembali ke List
                        </a>
                    </div>
                </div>
            </div>
        </div>

    @else

        @if ($hanyaLihat)
            @include('crud.ortho._cabang_lain')
        @elseif ($modeAntrean)
            @include('crud.ortho._antrean')
        @else
            @include('crud.ortho._tabel')
        @endif

    @endif {{-- tab === 'list' --}}

    {{-- Drawer detail: lapisan di atas List, bukan halaman terpisah. --}}
    @if ($detail ?? null)
        @include('crud.ortho._drawer_detail')
    @endif

    {{-- Side sheet (§15) — boleh terbuka di atas drawer detail. --}}
    @if (($sheet ?? null) && ($sheetKasus ?? null))
        @include('crud.ortho._sheet', ['tautan' => $tautan])
    @endif

    {{-- Drawer Tambah Pasien Ortho (§16). --}}
    @if ($tambah ?? false)
        @include('crud.ortho._drawer_tambah', ['tautan' => $tautan])
    @endif

    {{-- Tempat drawer disuntikkan oleh JS (dibiarkan kosong tanpa JS). --}}
    {{-- Tempat lapisan disuntikkan JS. Dua host karena lembar boleh berdiri
         DI ATAS drawer detail; kalau satu host, yang satu menimpa yang lain. --}}
    <div id="ortDrawerHost"></div>
    <div id="ortSheetHost"></div>
</div>

<script>
/* ════════════════════════════════════════════════════════════════════════
   Lapisan Tracking Ortho dibuka TANPA memuat ulang halaman.

   Empat lapisan memakai satu mesin yang sama:
     • drawer detail pasien   /tracking-ortho/{id}
     • lembar 410px           ?sheet=reservasi|pindahkan|geser|jadwalkan
     • drawer Tambah Pasien   ?tambah=1
     • drawer harian kalender ?tab=kalender&hari=YYYY-MM-DD

   Peningkatan bertahap, bukan syarat: tanpa JS (atau kalau fetch gagal)
   semuanya tetap tautan biasa dan halaman penuh yang dirender. URL tetap
   berubah lewat pushState, jadi tiap lapisan masih bisa dibagikan dan tombol
   Kembali browser tetap bekerja.

   Rangka kosong dipasang SEKETIKA saat diklik lalu diisi begitu jawabannya
   tiba — tanpa itu, klik terasa beku selama server merakit panelnya.
   ════════════════════════════════════════════════════════════════════════ */
(function () {
    var hostD = document.getElementById('ortDrawerHost');   /* detail · tambah · hari */
    var hostS = document.getElementById('ortSheetHost');    /* lembar, boleh di atas drawer */
    if (!hostD || !hostS || !window.fetch || !window.history.pushState) return;

    var POLA_DETAIL = /\/tracking-ortho\/[0-9a-f-]{36}/i;
    var urlList = location.pathname + location.search;   /* tempat pulang terakhir */
    var urlSebelumLembar = null;
    var sibuk = false;

    /* ── Kunci gulir TANPA menyentuh overflow ──────────────────────────────
       ⚠ Jangan pernah memasang overflow:hidden di <html>/<body> di sini.
         Sidebar aplikasi memakai position:sticky (shell-menu.sweettreats.css:24),
         dan sticky MATI begitu scrollport-nya berhenti menggulir — sidebar
         langsung terjun sejauh posisi gulir (terukur: -352px) dan terlihat
         "terangkat". Membuang scrollbar juga menggeser seluruh halaman 15px.
         Jadi yang ditahan cuma peristiwanya, bukan tata letaknya. */
    function tahanGulir(e) {
        if (e.target && e.target.closest && e.target.closest('.ort-drawer, .ort-sheet')) return;
        e.preventDefault();
    }

    function kunciGulir(nyala) {
        var m = nyala ? 'addEventListener' : 'removeEventListener';
        window[m]('wheel', tahanGulir, { passive: false });
        window[m]('touchmove', tahanGulir, { passive: false });
    }

    function adaLapisan() {
        return !!document.querySelector('.ort-drawer, .ort-sheet');
    }

    /* Halaman ini SUDAH membawa lapisan di badannya (dibuka dari URL langsung).
       Panel itu bukan milik host mana pun — biarkan tombol tutupnya bekerja
       sebagai tautan biasa, dan catat tempat pulangnya dari situ. */
    var bawaan = document.querySelector('.ort > .ort-drawer, .ort > .ort-sheet');
    if (bawaan) {
        var tutupBawaan = document.querySelector('.ort > .ort-scrim');
        urlList = (tutupBawaan && tutupBawaan.getAttribute('href')) || '/tracking-ortho';
        kunciGulir(true);
    }

    /* Jenis lapisan dibaca dari URL-nya — satu tempat, dipakai klik maupun
       tombol Kembali. */
    function jenis(href) {
        if (/[?&]sheet=(reservasi|pindahkan|geser|jadwalkan)/.test(href)) return 'sheet';
        if (/[?&]tambah=1/.test(href)) return 'tambah';
        if (/[?&]hari=\d{4}-\d{2}-\d{2}/.test(href)) return 'hari';
        if (POLA_DETAIL.test(href)) return 'drawer';
        return null;
    }

    function hostUntuk(t) { return t === 'sheet' ? hostS : hostD; }

    function bar(nyala) {
        var b = document.querySelector('.ort-loadbar');
        if (nyala && !b) {
            b = document.createElement('div');
            b.className = 'ort-loadbar';
            /* Ke dalam .ort, BUKAN <body> — sama alasannya dengan toast:
               seluruh token --ort-* di-scope ke .ort, jadi di luar sana
               `background: var(--ort-accent)` resolve ke kosong dan garisnya
               tidak terlihat sama sekali. .ort tidak punya transform/filter,
               jadi position:fixed tetap mengacu ke viewport. */
            (document.querySelector('.ort') || document.body).appendChild(b);
        } else if (!nyala && b) {
            b.remove();
        }
    }

    function rangka(t, hrefTutup) {
        var lembar = t === 'sheet';
        var judul = t === 'tambah' ? 'Tambah Pasien Ortho'
                  : t === 'hari' ? 'Jadwal hari itu'
                  : 'Memuat…';
        hostUntuk(t).innerHTML =
            '<a href="' + hrefTutup + '" class="ort-scrim' + (lembar ? ' ort-scrim--sheet' : '') +
                '" aria-label="Tutup"></a>' +
            '<aside class="' + (lembar ? 'ort-sheet' : 'ort-drawer') + '" tabindex="-1" role="dialog"' +
                ' aria-busy="true" aria-label="Memuat">' +
              '<div class="' + (lembar ? 'ort-sheet__head' : 'ort-drawer__head') + '">' +
                '<div class="ort-drawer__kicker">Tracking Pasien Ortho</div>' +
                '<div class="' + (lembar ? 'ort-sheet__title' : 'ort-drawer__name') + '">' + judul + '</div>' +
              '</div>' +
              '<div class="ort-memuat"><i class="fas fa-spinner fa-spin"></i> Memuat…</div>' +
            '</aside>';
    }

    function buka(url, t, dorong) {
        if (sibuk) return;
        sibuk = true;

        var host = hostUntuk(t);
        /* Lembar pulang ke keadaan SEBELUM ia dibuka — bisa jadi drawer detail
           yang masih terbuka di belakangnya, bukan daftar. */
        if (t === 'sheet') urlSebelumLembar = location.pathname + location.search;

        bar(true);
        rangka(t, t === 'sheet' ? (urlSebelumLembar || urlList) : urlList);
        kunciGulir(true);
        if (dorong) history.pushState({ ortLapis: t, ortUrl: url }, '', url);

        var pisah = url.indexOf('?') === -1 ? '?' : '&';

        fetch(url + pisah + 'frag=' + t, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.text();
            })
            .then(function (html) {
                /* Isi panel yang SUDAH terpasang, jangan ganti elemennya:
                   mengganti <aside> memicu animasi masuk untuk kedua kalinya,
                   dan panelnya terlihat meluncur dua kali. */
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var baru = doc.querySelector('.ort-drawer, .ort-sheet');
                var scrim = doc.querySelector('.ort-scrim');
                var panel = host.querySelector('.ort-drawer, .ort-sheet');

                if (baru && panel) {
                    panel.innerHTML = baru.innerHTML;
                    panel.className = baru.className;
                    panel.removeAttribute('aria-busy');
                    panel.setAttribute('aria-label', baru.getAttribute('aria-label') || 'Detail');
                    var sc = host.querySelector('.ort-scrim');
                    if (sc && scrim) sc.setAttribute('href', scrim.getAttribute('href'));
                } else {
                    host.innerHTML = html;
                }

                var p = host.querySelector('.ort-drawer, .ort-sheet');
                if (p) p.focus({ preventScroll: true });
            })
            .catch(function () {
                /* Apa pun yang gagal — jaringan, 403/404, HTML aneh — jatuhkan
                   ke navigasi biasa. Sales tidak boleh berakhir di layar diam. */
                host.innerHTML = '';
                if (!adaLapisan()) kunciGulir(false);
                location.href = url;
            })
            .then(function () { sibuk = false; bar(false); });
    }

    function tutup(host, urlPulang, dorong) {
        var panel = host.querySelector('.ort-drawer, .ort-sheet');
        var scrim = host.querySelector('.ort-scrim');
        if (!panel) return;

        panel.classList.add('is-closing');
        if (scrim) scrim.classList.add('is-closing');
        if (dorong && urlPulang) history.pushState({}, '', urlPulang);

        var selesai = false;
        var beres = function () {
            if (selesai) return;
            selesai = true;
            host.innerHTML = '';
            if (!adaLapisan()) kunciGulir(false);
        };
        panel.addEventListener('animationend', beres);
        setTimeout(beres, 320);   /* jaring pengaman kalau animasi tidak jalan */
    }

    function tutupTeratas(dorong) {
        if (hostS.querySelector('.ort-sheet')) {
            tutup(hostS, urlSebelumLembar || urlList, dorong);
        } else {
            tutup(hostD, urlList, dorong);
        }
    }

    /* ── Klik tautan mana pun di halaman ───────────────────────────────────
       Fase CAPTURE, bukan bubbling. Tombol aksi di baris memakai
       onclick="event.stopPropagation()" supaya klik tidak ikut membuka panel
       ringkas barisnya — dan itu juga menghentikan klik sebelum sampai ke
       pendengar di document. Di fase capture kita sudah dapat duluan.
       Sudah kena: "Buat Reservasi" memuat ulang halaman sementara tiga
       lapisan lain mulus. */
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        if (a.target && a.target !== '_self') return;

        var href = a.getAttribute('href') || '';
        var diLapisan = hostD.contains(a) || hostS.contains(a);

        /* Tutup: silang, scrim, dan tombol Batal milik lapisan yang KITA pasang. */
        if (diLapisan && (a.classList.contains('ort-drawer__close') ||
                          a.classList.contains('ort-scrim') ||
                          (a.classList.contains('ort-obtn') && /tracking-ortho/.test(href) &&
                           !jenis(href)))) {
            e.preventDefault();
            tutupTeratas(true);
            return;
        }

        var t = jenis(href);
        if (!t || !/tracking-ortho/.test(href)) return;

        e.preventDefault();
        e.stopPropagation();
        buka(href, t, true);
    }, true);

    /* ── Esc menutup lapisan teratas ───────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (hostS.querySelector('.ort-sheet') || hostD.querySelector('.ort-drawer')) {
            tutupTeratas(true);
        }
    });

    /* ── Tombol Kembali/Maju browser ───────────────────────────────────── */
    window.addEventListener('popstate', function (e) {
        var url = (e.state && e.state.ortUrl) || null;
        var t = (e.state && e.state.ortLapis) || null;

        if (url && t) {
            if (t !== 'sheet' && hostS.querySelector('.ort-sheet')) tutup(hostS, null, false);
            buka(url, t, false);
            return;
        }
        if (hostS.querySelector('.ort-sheet')) { tutup(hostS, null, false); return; }
        if (hostD.querySelector('.ort-drawer')) { tutup(hostD, null, false); return; }
        if (bawaan) location.reload();
    });
})();
</script>

<script>
/* ── Batalkan follow-up tanpa memuat ulang halaman ─────────────────────────
   Pendengarnya di KERANGKA halaman, bukan di _tabel: tabelnya sendiri yang
   nanti ditukar, jadi pendengar yang menempel di sana akan ikut hilang.
   Delegasi di document + fase capture, sejalan dengan penyadap tautan di atas.

   Yang dirender ulang server, bukan ditambal JS: status baris, tombol
   tindakan berikutnya, dan syarat munculnya tombol Batalkan diturunkan dari
   beberapa kolom sekaligus. Menirunya di sini berarti dua sumber kebenaran.

   Tanpa JS (atau kalau jaringan gagal) form-nya terkirim seperti biasa dan
   halaman dimuat ulang — jalur lama tidak pernah dicabut. */
(function () {
    var POLA = /\/batal-follow-up$/;

    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || f.tagName !== 'FORM') return;
        if (!POLA.test((f.getAttribute('action') || '').split('?')[0])) return;

        e.preventDefault();
        if (f.dataset.sibuk) return;          // klik ganda
        f.dataset.sibuk = '1';

        var tombol = f.querySelector('button');
        if (tombol) tombol.disabled = true;

        /* redirect:'manual' WAJIB. Kalau redirect-nya diikuti, permintaan itu
           yang memakan flash session, dan toast-nya tidak pernah sampai ke
           permintaan fragmen di bawah. */
        fetch(f.action, {
            method: 'POST',
            body: new FormData(f),
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            redirect: 'manual',
            credentials: 'same-origin',
        }).then(function (res) {
            // opaqueredirect = 302 yang sengaja tidak diikuti: itu jalur sukses.
            if (res.type !== 'opaqueredirect' && !res.ok) throw new Error('gagal');
            return segarkan();
        }).catch(function () {
            delete f.dataset.sibuk;
            if (tombol) tombol.disabled = false;
            f.submit();                        // jatuh ke cara lama
        });
    }, true);

    function segarkan() {
        var url = new URL(window.location.href);
        url.searchParams.set('frag', 'tabel');
        url.searchParams.delete('sheet');
        url.searchParams.delete('kontrol');
        url.searchParams.delete('kasus');

        return fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('fragmen gagal');
            return r.text();
        }).then(function (html) {
            var kotak = document.createElement('div');
            kotak.innerHTML = html;

            var lama = document.querySelector('[data-ort-tabel]');
            var baru = kotak.querySelector('[data-ort-tabel]');
            if (!lama || !baru) throw new Error('tabel tidak ketemu');
            lama.replaceWith(baru);

            /* Toast HARUS masuk ke dalam .ort, bukan ke <body>. Seluruh token
               --ort-* di-scope ke .ort; di luar scope itu gradien cokelatnya
               resolve ke kosong dan toast tampil putih pucat hampir tak
               terlihat. (Sudah kena sekali — jangan dipindah ke body lagi.) */
            var toastLama = document.querySelector('[data-ort-toast]');
            if (toastLama) toastLama.remove();
            var toastBaru = kotak.querySelector('[data-ort-toast]');
            var sarang = document.querySelector('.ort');
            if (toastBaru && sarang) sarang.appendChild(toastBaru);

            /* <script> yang masuk lewat innerHTML tidak pernah dijalankan
               peramban. Tanpa menyalinnya ulang, baris baru kehilangan panel
               ringkas dan segmennya, dan toast-nya tidak pernah pergi. */
            kotak.querySelectorAll('script').forEach(function (lamaS) {
                var baruS = document.createElement('script');
                baruS.textContent = lamaS.textContent;
                document.body.appendChild(baruS).remove();
            });
        });
    }
})();
</script>
@endsection
