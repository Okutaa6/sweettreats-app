{{--
    Skrip panel baris — DIPAKAI DUA TAB.

    Daftar kerja (_tabel) dan "Pasien di cabang lain" (_cabang_lain) memakai
    panel yang sama persis; sebelum ini skripnya tinggal di _tabel dan tab
    kedua tidak punya cara membuka apa pun.

    ⚠ Di-@include dari PARTIAL TABELNYA, bukan dari kerangka halaman, dan itu
      bukan selera: JS menukar fragmen tabel tiap kali follow-up disimpan, lalu
      menjalankan ulang <script> yang ikut di dalam fragmen itu. Kalau skrip ini
      hidup di kerangka, baris hasil tukar tidak pernah mendapat pendengar dan
      panelnya mati diam-diam.

    ⚠ Kedua tab TIDAK PERNAH tampil bersamaan (lihat percabangan di
      list.blade.php), jadi tidak ada pendengar ganda.
--}}
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

        if (!jadiBuka) return;                 // ini penutupan — tidak perlu memuat apa pun

        dari.classList.add('ort-tr--open');
        var tombol = dari.querySelector('[data-ort-toggle]');
        if (tombol) tombol.classList.add('is-open');

        /* Isi panel dimuat SEKALI, saat pertama kali dibuka. Sesudah itu ia
           tinggal di DOM dan membuka ulang tidak menyentuh jaringan.

           ⚠ Cangkangnya ditampilkan LEBIH DULU, sebelum jawabannya datang.
             Menunggu sampai isinya siap membuat baris terasa tidak merespons
             klik; dengan cangkang tampil, yang terlihat adalah panel yang
             sedang mengisi dirinya. */
        panel.hidden = false;

        if (!panel.hasAttribute('data-ort-kosong')) return;
        if (panel.dataset.ortSibuk) return;    // klik ganda saat masih memuat
        panel.dataset.ortSibuk = '1';

        panel.innerHTML = '<div class="ort-p2__memuat">Memuat…</div>';

        var url = new URL(window.location.href);
        url.searchParams.set('frag', 'panel');
        url.searchParams.set('kasus', panel.dataset.ortKasus || id);
        url.searchParams.delete('sheet');
        url.searchParams.delete('kontrol');
        url.searchParams.delete('page');

        fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('panel gagal');
            return r.text();
        }).then(function (html) {
            var kotak = document.createElement('div');
            kotak.innerHTML = html;
            var isi = kotak.querySelector('[data-ort-expand]');
            if (!isi) throw new Error('panel kosong');

            panel.innerHTML = isi.innerHTML;
            panel.removeAttribute('data-ort-kosong');
            delete panel.dataset.ortSibuk;
        }).catch(function () {
            /* Gagal memuat TIDAK boleh meninggalkan panel kosong tanpa kabar.
               Cangkangnya dikembalikan supaya klik berikutnya mencoba lagi. */
            panel.innerHTML = '<div class="ort-p2__memuat">Gagal memuat ringkas pasien. Klik barisnya lagi untuk mencoba ulang.</div>';
            delete panel.dataset.ortSibuk;
        });
    }

    /* ── Lipatan per butir riwayat kontrol ──────────────────────────────────
       Tiap butir menyimpan keadaan reservasinya sendiri. Dilipat supaya daftar
       kembali terbaca sebagai riwayat: di pasien lama, lima baris "Tidak
       tertaut reservasi" yang berdiri terbuka membuat kolomnya dua kali lebih
       tinggi tanpa menambah satu pun informasi baru.

       ⚠ MENUMPUK, tidak saling menutup. Beda dari panel baris di atas yang
         memang eksklusif: di sini Sales sering membandingkan dua kontrol, dan
         menutup yang satu begitu yang lain dibuka membuat perbandingan itu
         mustahil. */
    function lipat(id, tombol) {
        var isi = document.querySelector('[data-ort-kpanel="' + id + '"]');
        if (!isi) return;
        isi.hidden = !isi.hidden;
        tombol.classList.toggle('is-open', !isi.hidden);
    }

    /* ── Pendaftaran listener ───────────────────────────────────────────────
       DUA blok di bawah menyapu DOM sekali, saat skrip ini dijalankan. Itu sah
       UNTUK KEDUANYA karena sasarannya — baris tabel — sudah ada di HTML yang
       membawa skrip ini, dan ikut tertukar bersamanya tiap fragmen diperbarui.

       ⚠ Jangan pindahkan keduanya ke dalam buka(). Kalau dipanggil tiap baris
       dibuka, listener-nya menumpuk (satu klik = banyak handler) dan pemulihan
       tampilan terakhir di bawah tidak pernah jalan. Sudah pernah kejadian.

       ⚠ Dan jangan menambah blok ketiga dengan pola yang sama untuk apa pun
       yang hidup DI DALAM panel baris: isi panel datang dari fetch, lama
       sesudah sapuan ini selesai. Lihat lipatan per butir di bawahnya. */
    document.querySelectorAll('[data-ort-row]').forEach(function (row) {
        row.addEventListener('click', function () { buka(row.dataset.ortRow, row); });
    });

    document.querySelectorAll('[data-ort-toggle]').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.stopPropagation();
            buka(b.dataset.ortToggle, b.closest('[data-ort-row]'));
        });
    });

    /* ── Lipatan per butir: DIDELEGASIKAN ke document, bukan dipasang per
          tombol. ──────────────────────────────────────────────────────────

       ⚠ Versi pertama menyapu penandanya dengan querySelectorAll seperti dua
         blok di atas, dan itu MATI TOTAL begitu panel baris jadi dimuat
         belakangan (6 Okt 2026): tombol chevron-nya lahir dari fetch di buka(),
         jauh sesudah blok ini selesai menyapu DOM, jadi tidak satu pun dari
         mereka pernah mendapat pendengar. Yang terlihat di layar: chevron yang
         bisa diklik tapi tidak melakukan apa-apa — persis seperti kontrol yang
         tidak punya reservasi, padahal keterangannya ada dan siap ditampilkan.

       ⚠ Dan pagar sekali-pasangnya WAJIB. Berkas ini di-include dari partial
         TABEL (tanpa tanda "at" — ditulis di komentar JS, Blade TETAP
         mengompilasinya jadi directive dan halamannya ParseError; sudah kena),
         dan JS menukar fragmen tabel tiap follow-up disimpan lalu
         menjalankan ulang <script> di dalamnya. Pendengar pada SIMPUL yang
         ikut tertukar mati bersama simpulnya — tapi pendengar pada `document`
         tidak: tanpa pagar ini, tukar fragmen kedua membuat satu klik
         memanggil lipat() dua kali, yang membuka lalu langsung menutup
         kembali. Gejalanya sama persis dengan bug yang baru saja diperbaiki. */
    if (!document.documentElement.dataset.ortKdetailPasang) {
        document.documentElement.dataset.ortKdetailPasang = '1';

        document.addEventListener('click', function (e) {
            var b = e.target && e.target.closest
                ? e.target.closest('[data-ort-kdetail]') : null;
            if (!b) return;

            /* Panel baris BUKAN anak dari [data-ort-row] (ia dirender sebagai
               saudara sesudahnya), jadi klik di sini sebetulnya tidak pernah
               sampai ke pembuka baris. stopPropagation tetap dipasang sebagai
               pagar: begitu suatu hari strukturnya berubah, tanpa ini membuka
               satu butir akan MENUTUP seluruh panelnya. */
            e.stopPropagation();
            lipat(b.dataset.ortKdetail, b);
        });
    }
})();
</script>
