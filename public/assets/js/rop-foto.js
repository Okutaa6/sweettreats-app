/* ═══════════════════════════════════════════════════════════════════════════
   Kompresi foto lampiran ROP di browser sebelum dikirim.

   Alasannya nyata: foto invoice dari kamera HP 4–6 MB, dan pengaju sering
   berada di cabang dengan sinyal seadanya. Setelah dikecilkan tinggal
   ratusan KB, jadi kirimannya tidak gagal di tengah jalan.

   Pola sama dengan absen HP selfie (docs/rencana/rencana-absen-hp.md), dengan
   dua beda: sisi terpanjang 1280px (bukan 640) karena tulisan di invoice harus
   tetap terbaca, dan PDF dilewatkan apa adanya — tidak ada yang bisa
   dikompresi dari PDF di sini.

   Cara pakai di blade:
     <input type="file" name="lampiran" accept="image/*,application/pdf">
     <input type="hidden" name="lampiran_base64" data-rop-b64="lampiran">
     <div data-rop-preview="lampiran"></div>
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    var SISI_MAKS = 1280;
    var MUTU = 0.75;

    function siapkan(hidden) {
        var nama = hidden.getAttribute('data-rop-b64');
        var input = document.querySelector('[data-rop-file="' + nama + '"]')
                 || document.querySelector('input[type=file][name="' + nama + '"]');
        var preview = document.querySelector('[data-rop-preview="' + nama + '"]');
        if (!input) return;

        // Tombol Kamera / Galeri memakai input yang sama; bedanya cuma atribut
        // `capture`. Dengan `capture`, HP langsung membuka kamera alih-alih
        // menampilkan pemilih berkas — itu yang bikin memotret invoice jadi
        // satu ketukan, bukan tiga.
        var tombol = document.querySelectorAll('[data-rop-ambil][data-rop-untuk="' + nama + '"]');
        tombol.forEach(function (b) {
            b.addEventListener('click', function () {
                if (b.getAttribute('data-rop-ambil') === 'kamera') {
                    input.setAttribute('capture', 'environment');
                } else {
                    input.removeAttribute('capture');
                }
                input.click();
            });
        });

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            hidden.value = '';
            if (preview) preview.innerHTML = '';
            if (!file) return;

            // PDF (atau apa pun yang bukan gambar) dikirim sebagai berkas biasa.
            if (file.type.indexOf('image/') !== 0) {
                if (preview) {
                    preview.innerHTML = '<span class="rop-thumb"><span>' +
                        file.name.replace(/[&<>"]/g, '') + '</span></span>';
                }
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    var skala = Math.min(1, SISI_MAKS / Math.max(img.width, img.height));
                    var c = document.createElement('canvas');
                    c.width = Math.round(img.width * skala);
                    c.height = Math.round(img.height * skala);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);

                    hidden.value = c.toDataURL('image/jpeg', MUTU);
                    // Berkas asli dikosongkan supaya yang terkirim hanya versi
                    // ringan — kalau keduanya ikut, server memilih file besar.
                    input.value = '';

                    if (preview) {
                        var kb = Math.round(hidden.value.length * 0.75 / 1024);
                        preview.innerHTML =
                            '<span class="rop-thumb"><img src="' + hidden.value + '" alt="">' +
                            '<span>Foto siap dikirim &middot; ' + kb + ' KB</span></span>';
                    }
                };
                img.onerror = function () {
                    if (preview) preview.textContent = 'Foto tidak terbaca, coba ambil ulang.';
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    document.querySelectorAll('[data-rop-b64]').forEach(siapkan);
})();
