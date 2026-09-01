/* ═══════════════════════════════════════════════════════════════════════════
   Komisi Dokter — interaksi layar daftar & detail.

   Vanilla JS: repo ini tidak memakai Alpine/Vue/React. Semua state yang perlu
   bisa dibagikan (periode, cabang, tab, filter, urutan) hidup di URL dan
   di-render server-side — file ini hanya menangani hal yang murni tampilan:
   accordion, pencarian cepat, melipat ringkasan, dan salin ke clipboard.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var akar = document.querySelector('.kd');
    if (!akar) return;

    // ── Toast ────────────────────────────────────────────────────────────────
    var toast = document.getElementById('kd-toast');
    var toastTimer = null;

    function pesan(teks) {
        if (!toast) return;
        toast.textContent = teks;
        toast.classList.add('kd-toast--on');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            toast.classList.remove('kd-toast--on');
        }, 2200);
    }

    // ── Accordion beban per cabang ───────────────────────────────────────────
    // Hanya satu dokter terbuka pada satu waktu: dropdown ini dipakai untuk
    // MEMBANDINGKAN cabang milik satu dokter, bukan untuk membaca banyak dokter
    // sekaligus. Membiarkan semua terbuka justru mengubur tabel utamanya.
    function tutupSemua(kecuali) {
        akar.querySelectorAll('.kd-row--buka').forEach(function (tr) {
            if (tr === kecuali) return;
            tr.classList.remove('kd-row--buka');
            tr.setAttribute('aria-expanded', 'false');
            var d = document.getElementById(tr.dataset.drop);
            if (d) d.hidden = true;
        });
    }

    akar.addEventListener('click', function (ev) {
        var tr = ev.target.closest('.kd-row[data-drop]');
        if (!tr || !akar.contains(tr)) return;
        // Klik pada tautan/tombol di dalam baris tidak boleh ikut membuka.
        if (ev.target.closest('a, button')) return;

        var drop = document.getElementById(tr.dataset.drop);
        if (!drop) return;

        var buka = drop.hidden;
        tutupSemua(tr);
        drop.hidden = !buka;
        tr.classList.toggle('kd-row--buka', buka);
        tr.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });

    // Baris punya tabindex — keyboard harus bisa melakukan hal yang sama.
    akar.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var tr = ev.target.closest('.kd-row[data-drop]');
        if (!tr) return;
        ev.preventDefault();
        tr.click();
    });

    // ── Pencarian nama dokter (live, tanpa reload) ───────────────────────────
    var cari = document.getElementById('kd-cari');
    var tabel = document.getElementById('kd-tabel-dokter');

    if (cari && tabel) {
        cari.addEventListener('input', function () {
            var kunci = cari.value.trim().toLowerCase();

            tabel.querySelectorAll('tbody tr.kd-row').forEach(function (tr) {
                var nama = (tr.querySelector('.kd-doc__nama') || {}).textContent || '';
                var cocok = !kunci || nama.toLowerCase().indexOf(kunci) !== -1;

                tr.style.display = cocok ? '' : 'none';

                // Baris dropdown pasangannya ikut disembunyikan, kalau tidak
                // rincian cabang akan menggantung tanpa dokternya.
                var drop = document.getElementById(tr.dataset.drop);
                if (drop && !cocok) {
                    drop.hidden = true;
                    drop.style.display = 'none';
                    tr.classList.remove('kd-row--buka');
                    tr.setAttribute('aria-expanded', 'false');
                } else if (drop) {
                    drop.style.display = '';
                }
            });
        });
    }

    // ── Lipat ringkasan ──────────────────────────────────────────────────────
    // Supaya tabel bisa naik ke atas lipatan layar saat Finance sudah tahu
    // ringkasannya dan hanya ingin membaca daftar dokter.
    var lipat = document.getElementById('kd-lipat');
    if (lipat) {
        lipat.addEventListener('click', function () {
            var blok = document.getElementById(lipat.dataset.target);
            if (!blok) return;

            var tersembunyi = blok.style.display === 'none';
            blok.style.display = tersembunyi ? '' : 'none';

            var label = lipat.querySelector('span');
            if (label) {
                label.textContent = tersembunyi ? lipat.dataset.tutup : lipat.dataset.buka;
            }
            lipat.classList.toggle('kd-btn--aktif', !tersembunyi);
        });
    }

    // ── Salin total ──────────────────────────────────────────────────────────
    akar.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.kd-salin');
        if (!btn) return;

        var nilai = btn.dataset.nilai || '';

        var selesai = function () { pesan('Total disalin: Rp ' + nilai); };
        var gagal = function () { pesan('Tidak bisa menyalin — blokir clipboard di browser?'); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(nilai).then(selesai).catch(gagal);
            return;
        }

        // Halaman internal sering diakses lewat http:// (bukan https), dan di
        // konteks itu navigator.clipboard tidak tersedia sama sekali.
        try {
            var ta = document.createElement('textarea');
            ta.value = nilai;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            selesai();
        } catch (e) {
            gagal();
        }
    });

    // ── Kontrol "baris per halaman" di tab Rincian ───────────────────────────
    var per = document.getElementById('kd-per');
    if (per) {
        per.addEventListener('change', function () {
            location.href = per.value;
        });
    }
})();
