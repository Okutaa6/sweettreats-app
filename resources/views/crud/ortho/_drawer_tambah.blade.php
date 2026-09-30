{{--
    Drawer Tambah Pasien Ortho 470px — brief §16 / screenshot 18, 19, 20.

    Ini JALUR PENGECUALIAN: pasien biasanya datang dari penyemaian rekam medis.
    Drawer ini untuk yang tidak tertangkap — pasang di klinik lain, pindahan,
    pasien lama yang datanya tidak lengkap.

    ⚠ NOMOR RM TIDAK PERNAH DIKARANG. Ia ikut terbawa dari pasien yang dipilih
      (teks mati). Belum tertaut → peringatan amber + "Tautkan sekarang".

    Dibuka lewat ?tambah=1. Pencarian pasien memakai endpoint select2 yang
    sudah ada (api.customer.search, nama/telepon, cross-cabang). Tombol simpan
    masih inert (Tahap 3).
--}}
@php
    $tutup = $tautan(['tambah' => null]);
    $default1 = $hariIni->copy()->addDays(30);
@endphp

<a href="{{ $tutup }}" class="ort-scrim" aria-label="Tutup"></a>

<aside class="ort-drawer" style="width:470px;" role="dialog" aria-label="Tambah Pasien Ortho">
    <div class="ort-drawer__head">
        <a href="{{ $tutup }}" class="ort-drawer__close" title="Tutup"><i class="fas fa-times"></i></a>
        <div class="ort-drawer__kicker">Pasien behel baru</div>
        <div class="ort-drawer__name" style="font-size:19px;">Tambah Pasien Ortho</div>
    </div>

    <form class="ort-drawer__body" style="gap:15px;" onsubmit="return false;">

        <div>
            <label class="ort-lbl">Pasien</label>
            <span class="ort-search">
                <i class="fas fa-search"></i>
                <input type="text" class="ort-inp" id="ortCariPasien" placeholder="Ketik nama atau nomor HP..." autocomplete="off">
            </span>
            <div class="ort-results" id="ortHasilPasien" hidden></div>

            {{-- Kartu pasien terpilih + baris RM / peringatan. Diisi JS. --}}
            <div id="ortPasienTerpilih" hidden>
                <div class="ort-picked">
                    <span class="ort-avatar" style="width:30px;height:30px;font-size:12px;" data-ort-inisial></span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:800;color:#3B1500;" data-ort-nama></div>
                        <div style="font-size:11.5px;color:#5A7A63;" data-ort-telp></div>
                    </div>
                    <button type="button" class="ort-picked__x" data-ort-lepas title="Ganti pasien"><i class="fas fa-times"></i></button>
                </div>
                <div class="ort-rmrow" data-ort-rm-ada hidden>
                    <span class="ort-lbl" style="margin:0;">No. rekam medis</span>
                    <b class="tabular" data-ort-rm></b>
                    <span style="font-size:10.5px;color:#9A7256;">dari rekam medis · tidak bisa diubah</span>
                </div>
                <div class="ort-rmwarn" data-ort-rm-kosong hidden>
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        Pasien ini belum tertaut ke rekam medis. Riwayat kunjungannya tidak akan terbaca.
                        <a href="#" onclick="return false;">Tautkan sekarang</a>
                    </div>
                </div>
            </div>

            <a href="{{ route('rawcustomer.new') }}" target="_blank" class="ort-link-sm">+ Pasien belum terdaftar</a>
        </div>

        <div class="ort-2col">
            <div>
                <label class="ort-lbl">Tanggal pasang behel</label>
                <input type="date" class="ort-inp" value="{{ $hariIni->toDateString() }}" data-ort-pasang>
            </div>
            <div>
                <label class="ort-lbl">Cabang pasang</label>
                <select class="ort-inp">
                    @foreach ($peta as $pid => $b)
                        <option value="{{ $pid }}" {{ (string) $pid === (string) $placeId ? 'selected' : '' }}>{{ $b['nama'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="ort-2col">
            <div>
                <label class="ort-lbl">Jenis bracket</label>
                <select class="ort-inp">
                    @foreach (['Metal', 'Ceramic', 'Sapphire', 'Self Ligating', 'Damon Quantum'] as $b)
                        <option>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ort-lbl">Dokter ortho</label>
                <select class="ort-inp">
                    @foreach ($dokterPilihan as $id => $nama)
                        <option value="{{ $id }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="ort-lbl">Rahang</label>
            <div class="ort-segbig">
                <label><input type="radio" name="rahang" value="atas"><span>Atas</span></label>
                <label><input type="radio" name="rahang" value="bawah"><span>Bawah</span></label>
                <label><input type="radio" name="rahang" value="keduanya" checked><span>Keduanya</span></label>
            </div>
        </div>

        <div class="ort-divider"></div>

        <div class="ort-nested">
            <div style="font-size:12.5px;font-weight:800;color:#3B1500;margin-bottom:10px;">Jadwal kontrol pertama</div>
            <label class="ort-lbl">Tanggal kontrol #1</label>
            <input type="date" class="ort-inp" value="{{ $default1->toDateString() }}" data-ort-kontrol1>
            <div class="ort-hint">Default tanggal pasang + 30 hari · <span data-ort-kontrol1-teks>{{ $default1->translatedFormat("j M 'y") }}</span></div>
            <label class="ort-lbl" style="margin-top:12px;">Catatan (opsional)</label>
            <input type="text" class="ort-inp" placeholder="mis. minta kontrol pagi">
        </div>
    </form>

    <div class="ort-sheet__foot" style="flex-direction:column;align-items:stretch;gap:8px;">
        <span class="ort-btn ort-btn--mati" style="justify-content:center;padding:12px;" id="ortSimpanTambah"
              title="Pilih pasien dulu">Simpan &amp; buat jadwal #1</span>
        <div style="font-size:11.5px;color:#9A7256;text-align:center;">
            Jadwal berikutnya dibuat nanti, setiap kali pasien selesai kontrol.
        </div>
    </div>
</aside>

<script>
(function () {
    var inp = document.getElementById('ortCariPasien');
    var box = document.getElementById('ortHasilPasien');
    var pick= document.getElementById('ortPasienTerpilih');
    var btn = document.getElementById('ortSimpanTambah');
    if (!inp) return;
    var t = null;

    inp.addEventListener('input', function () {
        clearTimeout(t);
        var q = inp.value.trim();
        if (q.length < 2) { box.hidden = true; return; }
        t = setTimeout(function () {
            fetch('{{ route('api.customer.search') }}?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
              .then(function (r) { return r.json(); })
              .then(function (d) {
                  var rows = (d.results || d || []).slice(0, 8);
                  box.innerHTML = rows.map(function (r) {
                      var label = r.text || r.name || '';
                      var ini = label.trim().charAt(0).toUpperCase();
                      return '<button type="button" class="ort-result" data-id="' + r.id + '" data-label="' + label.replace(/"/g,'&quot;') + '">'
                           + '<span class="ort-result__ini">' + ini + '</span><span>' + label + '</span></button>';
                  }).join('') || '<div class="ort-result ort-result--none">Tidak ketemu — coba nama atau nomor lain.</div>';
                  box.hidden = false;
              }).catch(function () { box.hidden = true; });
        }, 250);
    });

    box.addEventListener('click', function (e) {
        var b = e.target.closest('.ort-result[data-id]');
        if (!b) return;
        var label = b.dataset.label;
        pick.querySelector('[data-ort-inisial]').textContent = label.trim().charAt(0).toUpperCase();
        pick.querySelector('[data-ort-nama]').textContent = label.split('(')[0].trim();
        pick.querySelector('[data-ort-telp]').textContent = (label.match(/\(([^)]+)\)/) || ['',''])[1];
        /* Prototipe: RM belum bisa dicek ke rekam medis dari endpoint ini —
           tampilkan peringatan "belum tertaut" sebagai keadaan default yang jujur. */
        pick.querySelector('[data-ort-rm-ada]').hidden = true;
        pick.querySelector('[data-ort-rm-kosong]').hidden = false;
        pick.hidden = false; box.hidden = true; inp.value = '';
        btn.classList.remove('ort-btn--mati'); btn.title = 'Aksi tulis menyusul (Tahap 3)';
    });

    pick.querySelector('[data-ort-lepas]').addEventListener('click', function () {
        pick.hidden = true; btn.classList.add('ort-btn--mati'); btn.title = 'Pilih pasien dulu';
    });

    var pasang = document.querySelector('[data-ort-pasang]');
    var k1 = document.querySelector('[data-ort-kontrol1]');
    pasang.addEventListener('change', function () {
        var d = new Date(pasang.value); if (isNaN(d)) return;
        d.setDate(d.getDate() + 30);
        k1.value = d.toISOString().slice(0, 10);
        document.querySelector('[data-ort-kontrol1-teks]').textContent =
            d.toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: '2-digit'});
    });
})();
</script>
