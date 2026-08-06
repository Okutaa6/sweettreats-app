@php
    $ajInDrawer = $inDrawer ?? false;
    $ajTipeVal  = old('tipe', $tipe ?? null);
    $ajWfhUsed  = $wfhUsed ?? 0;
@endphp

<form action="{{ route('akun.pengajuan.store') }}" method="POST" enctype="multipart/form-data"
      data-wfh-used="{{ $ajWfhUsed }}" class="absk-ajform">
    @csrf
    <input type="hidden" name="tipe" id="tipeHidden" value="{{ $ajTipeVal }}">
    <input type="hidden" name="sub_tipe" id="subHidden" value="{{ old('sub_tipe', 'jam') }}">

    <div class="absk-ajfields">

        {{-- Jenis (chip) --}}
        <div class="absk-field">
            <label class="absk-lbl">Jenis Permohonan <span class="req">*</span></label>
            <div class="absk-jchips">
                {{-- 'koreksi' SENGAJA tak ada di sini: form ini tak punya pilihan
                     "apa yang keliru?", jadi memilihnya pasti gagal validasi —
                     jalan buntu. Koreksi punya jalurnya sendiri: ketuk harinya. --}}
                @foreach(collect($tipeList)->except('koreksi') as $val => $lbl)
                    <button type="button" class="absk-jchip {{ $ajTipeVal === $val ? 'active' : '' }}" data-jenis="{{ $val }}">{{ $lbl }}</button>
                @endforeach
            </div>
            <div class="absk-hint" style="background:none;border:0;padding:6px 2px 0;margin:0">
                Mau melaporkan absen yang keliru? Tutup form ini, lalu <b>ketuk tanggalnya</b> di kalender → "Lapor koreksi".
            </div>
        </div>

        {{-- Jenis lembur (chip) --}}
        <div class="absk-field" id="grp-subtipe" style="display:none">
            <label class="absk-lbl">Jenis Lembur</label>
            <div class="absk-jchips two">
                <button type="button" class="absk-jchip" data-sub="jam">Lembur jam (hari kerja)</button>
                <button type="button" class="absk-jchip" data-sub="tambah_hari">Masuk hari libur</button>
            </div>
        </div>

        {{-- Info jatah kerja dari rumah --}}
        <div class="absk-wfhwarn" id="wfhBlocked" style="display:none">
            <span style="display:inline-flex;vertical-align:-.15em"><svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M8 2.2 14.4 13.2H1.6L8 2.2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6.4v3M8 11.2v.1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
            <div><b>Jatah kerja dari rumah bulan ini sudah habis ({{ $ajWfhUsed }}/2 hari).</b> Pilih jenis lain, atau ajukan untuk bulan depan ya.</div>
        </div>
        <div class="absk-wfhinfo" id="wfhInfo" style="display:none">
            <span>ℹ️</span>
            <div>Jatah kerja dari rumah bulan ini: <b class="num">{{ $ajWfhUsed }}/2</b> hari terpakai. Sisa <b class="num">{{ max(0, 2 - $ajWfhUsed) }}</b> hari.</div>
        </div>

        {{-- ── TUKAR HARI: dua daftar tanggal, bukan rentang ──
             Satu kesepakatan = satu pengajuan = satu ACC. Jumlah kedua sisi
             wajib sama; tombol kirim bilang sendiri kalau belum seimbang
             (pola-interaksi P3) alih-alih ditolak setelah dikirim. --}}
        <div id="grp-tukar" style="display:none">
            <div class="absk-hint" style="margin-bottom:10px">
                Masukkan hari yang <b>kamu masuk</b> dan hari <b>libur penggantinya</b>.
                Jumlahnya harus sama, dan semuanya dalam periode yang sama.
            </div>

            <div class="absk-grid2" style="align-items:start">
                <div>
                    <label class="absk-lbl">Saya masuk <span class="req">*</span></label>
                    <div id="listMasuk" class="absk-tukarlist"></div>
                    <button type="button" class="absk-prefill" data-tambah="masuk" style="margin-top:6px">+ Tambah hari masuk</button>
                </div>
                <div>
                    <label class="absk-lbl">Gantinya libur <span class="req">*</span></label>
                    <div id="listLibur" class="absk-tukarlist"></div>
                    <button type="button" class="absk-prefill" data-tambah="libur" style="margin-top:6px">+ Tambah hari libur</button>
                </div>
            </div>

            <div class="absk-durbox" id="tukarSeimbang" style="margin-top:10px">
                <span>Keseimbangan</span>
                <span class="num" id="tukarInfo">0 masuk · 0 libur</span>
            </div>
        </div>

        {{-- Tanggal + Jam (grid 2×2) --}}
        <div class="absk-grid2" id="grp-rentang">
            <div>
                <label class="absk-lbl" id="lblFrom">Tanggal Mulai <span class="req">*</span></label>
                <input type="date" name="tanggal_from" id="tglFrom" class="absk-input" value="{{ old('tanggal_from', now()->toDateString()) }}" required>
            </div>
            {{-- Lembur selalu 1 hari (menitnya dari sepasang jam), jadi kolom
                 "sampai" disembunyikan supaya tak menjanjikan rentang yg memang
                 tak didukung. Server juga memaksa tanggal_to = tanggal_from. --}}
            <div id="cellTglTo">
                <label class="absk-lbl">Tanggal Selesai <span class="req">*</span></label>
                <input type="date" name="tanggal_to" id="tglTo" class="absk-input" value="{{ old('tanggal_to', now()->toDateString()) }}" required>
            </div>
            <div class="absk-jamcell" style="display:none">
                <label class="absk-lbl">Jam Mulai</label>
                <input type="time" name="jam_from" id="jamFrom" class="absk-input" value="{{ old('jam_from') }}">
            </div>
            <div class="absk-jamcell" style="display:none">
                <label class="absk-lbl">Jam Selesai</label>
                <input type="time" name="jam_to" id="jamTo" class="absk-input" value="{{ old('jam_to') }}">
            </div>
        </div>

        {{-- Ambil jam dari mesin absen + lama lembur --}}
        <div id="grp-prefill" style="display:none">
            <button type="button" class="absk-prefill" id="btnPrefill"><i class="fa fa-magic"></i> Ambil jam dari mesin absen</button>
            <div class="absk-hint" id="prefillNote"></div>
        </div>
        <div class="absk-durbox" id="durBox" style="display:none">
            <span>Lama lembur (dihitung otomatis)</span>
            <span class="num" id="durVal">—</span>
        </div>

        {{-- Alasan --}}
        <div class="absk-field">
            <label class="absk-lbl">Alasan <span class="req">*</span></label>
            <textarea name="alasan" class="absk-textarea" rows="3" required
                      placeholder="Tulis alasannya biar atasan gampang menyetujui…">{{ old('alasan') }}</textarea>
        </div>

        {{-- Foto bukti (drop-zone) --}}
        <div class="absk-field">
            <label class="absk-lbl">Foto Bukti <span class="absk-lbl-note">(opsional — kalau lembur/kerja dari rumah tanpa absen mesin, foto ini buktinya)</span></label>
            <div class="absk-dropzone" id="fotoZone">
                <span id="fotoLabel">Klik untuk lampirkan foto</span>
            </div>
            <input type="file" name="foto" id="fotoInput" accept="image/*" hidden>
        </div>
    </div>

    <div class="absk-ajfooter">
        {{-- Label tombol menjelaskan kenapa dia mati (P3): sebelum jenis
             dipilih, dia bilang begitu — bukan diam. --}}
        <button type="submit" class="absk-submit" id="ajSubmit" disabled><i class="fa fa-paper-plane"></i> <span id="ajSubmitTeks">Pilih jenis dulu</span></button>
        @if($ajInDrawer)<button type="button" class="absk-batal" data-close-ajukan>Batal</button>@endif
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('.absk-ajform');
    if (!form || form.dataset.wired) return;
    form.dataset.wired = '1';

    // Umpan balik kirim (pola-interaksi P2): tombol bilang sedang bekerja +
    // kebal klik dobel; menonaktifkan di dalam handler submit tidak
    // menghentikan pengiriman yang sudah berjalan.
    form.addEventListener('submit', function () {
        var b = form.querySelector('#ajSubmit');
        var teks = form.querySelector('#ajSubmitTeks');
        if (b) b.disabled = true;
        if (teks) teks.textContent = 'Mengirim…';
    });

    var tipeHidden = form.querySelector('#tipeHidden');
    var subHidden  = form.querySelector('#subHidden');
    var grpSub     = form.querySelector('#grp-subtipe');
    var jamCells   = form.querySelectorAll('.absk-jamcell');
    var grpPrefill = form.querySelector('#grp-prefill');
    var durBox     = form.querySelector('#durBox');
    var durVal     = form.querySelector('#durVal');
    var wfhBlocked = form.querySelector('#wfhBlocked');
    var wfhInfo    = form.querySelector('#wfhInfo');
    var submitBtn  = form.querySelector('#ajSubmit');
    var wfhUsed    = parseInt(form.getAttribute('data-wfh-used') || '0', 10);

    function showCells(show) { jamCells.forEach(function (c) { c.style.display = show ? '' : 'none'; }); }

    function sync() {
        var t = tipeHidden.value;
        var isLembur = t === 'lembur';
        var isTukar  = t === 'tukar';

        // Tukar hari memakai dua DAFTAR tanggal; rentang mulai–selesai tak
        // punya arti di sana, jadi bagian itu disingkirkan seluruhnya (termasuk
        // atribut required-nya, kalau tidak form tak bisa dikirim karena
        // mengeluh pada kolom yang tak terlihat).
        var grpTukar  = form.querySelector('#grp-tukar');
        var grpRent   = form.querySelector('#grp-rentang');
        if (grpTukar) grpTukar.style.display = isTukar ? '' : 'none';
        if (grpRent)  grpRent.style.display  = isTukar ? 'none' : '';
        ['tglFrom', 'tglTo'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.required = !isTukar; el.disabled = isTukar; }
        });

        grpSub.style.display = isLembur ? '' : 'none';
        showCells(isLembur || t === 'izin');
        grpPrefill.style.display = isLembur ? '' : 'none';
        durBox.style.display = isLembur ? '' : 'none';
        wfhBlocked.style.display = (t === 'wfh' && wfhUsed >= 2) ? '' : 'none';
        wfhInfo.style.display    = (t === 'wfh' && wfhUsed < 2) ? '' : 'none';

        // Lembur = satu hari saja: sembunyikan "Tanggal Selesai" dan samakan
        // nilainya, biar tak ada rentang yang tak akan dihormati server.
        var cellTo = document.getElementById('cellTglTo');
        var tglTo  = document.getElementById('tglTo');
        var tglFrom = document.getElementById('tglFrom');
        if (cellTo && tglTo && tglFrom) {
            cellTo.style.display = isLembur ? 'none' : '';
            document.getElementById('lblFrom').innerHTML = isLembur
                ? 'Tanggal <span class="req">*</span>'
                : 'Tanggal Mulai <span class="req">*</span>';
            if (isLembur) tglTo.value = tglFrom.value;
        }

        var ajTeks = form.querySelector('#ajSubmitTeks');
        if (isTukar) {
            syncTukar();                       // dia sendiri yang mengatur tombol
        } else {
            submitBtn.disabled = !t;
            if (ajTeks) ajTeks.textContent = t ? 'Kirim Permohonan' : 'Pilih jenis dulu';
        }
        if (isLembur) calcDur();
    }

    // ── Tukar hari: dua daftar tanggal yang harus seimbang ──
    function barisTanggal(sisi) {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex;gap:6px;margin-bottom:6px';
        var inp = document.createElement('input');
        inp.type = 'date'; inp.name = sisi + '[]'; inp.className = 'absk-input';
        inp.style.flex = '1';
        inp.addEventListener('change', syncTukar);
        var del = document.createElement('button');
        del.type = 'button'; del.className = 'absk-batal';
        del.style.cssText = 'padding:0 12px;min-height:0';
        del.textContent = '✕';
        del.setAttribute('aria-label', 'Hapus tanggal ini');
        del.addEventListener('click', function () { row.remove(); syncTukar(); });
        row.appendChild(inp); row.appendChild(del);
        return row;
    }

    function isiTanggal(sisi) {
        var box = form.querySelector(sisi === 'masuk' ? '#listMasuk' : '#listLibur');
        if (!box) return [];
        return Array.prototype.slice.call(box.querySelectorAll('input[type=date]'))
            .map(function (i) { return i.value; }).filter(Boolean);
    }

    function syncTukar() {
        if (tipeHidden.value !== 'tukar') return;
        var m = isiTanggal('masuk'), l = isiTanggal('libur');
        var info = form.querySelector('#tukarInfo');
        if (info) info.textContent = m.length + ' masuk · ' + l.length + ' libur';

        var teks = form.querySelector('#ajSubmitTeks');
        var pesan = null;
        if (!m.length && !l.length)      pesan = 'Isi tanggalnya dulu';
        else if (m.length !== l.length)  pesan = 'Jumlahnya belum seimbang';
        else {
            // Tanggal kembar lintas sisi juga ditolak server; dibilang di sini
            // supaya orang tak perlu mengirim dulu untuk tahu.
            var kembar = m.filter(function (d) { return l.indexOf(d) !== -1; });
            if (kembar.length) pesan = 'Ada tanggal yang sama di dua sisi';
        }
        submitBtn.disabled = !!pesan;
        if (teks) teks.textContent = pesan || 'Kirim Tukar Hari';
    }

    form.querySelectorAll('[data-tambah]').forEach(function (b) {
        b.addEventListener('click', function () {
            var sisi = b.getAttribute('data-tambah');
            form.querySelector(sisi === 'masuk' ? '#listMasuk' : '#listLibur')
                .appendChild(barisTanggal(sisi));
            syncTukar();
        });
    });

    // Chip Jenis
    form.querySelectorAll('.absk-jchip[data-jenis]').forEach(function (b) {
        b.addEventListener('click', function () {
            form.querySelectorAll('.absk-jchip[data-jenis]').forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            tipeHidden.value = b.getAttribute('data-jenis');
            sync();
        });
    });
    // Chip sub-jenis lembur
    var subChips = form.querySelectorAll('.absk-jchip[data-sub]');
    subChips.forEach(function (b) {
        if (b.getAttribute('data-sub') === subHidden.value) b.classList.add('active');
        b.addEventListener('click', function () {
            subChips.forEach(function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            subHidden.value = b.getAttribute('data-sub');
        });
    });

    // Durasi lembur (jam_to - jam_from)
    function calcDur() {
        var a = form.querySelector('#jamFrom').value, z = form.querySelector('#jamTo').value;
        if (!a || !z) { durVal.textContent = '—'; return; }
        var pa = a.split(':'), pz = z.split(':');
        var m = (pz[0] * 60 + (+pz[1])) - (pa[0] * 60 + (+pa[1]));
        if (m <= 0) { durVal.textContent = '—'; return; }
        durVal.textContent = Math.floor(m / 60) + ' jam ' + (m % 60) + ' mnt';
    }
    form.querySelector('#jamFrom').addEventListener('change', calcDur);
    form.querySelector('#jamTo').addEventListener('change', calcDur);

    // saat lembur, "sampai" ikut tanggal mulai (kolomnya tersembunyi)
    document.getElementById('tglFrom').addEventListener('change', function () {
        if (tipeHidden.value === 'lembur') document.getElementById('tglTo').value = this.value;
    });

    // Foto drop-zone
    var fotoInput = form.querySelector('#fotoInput'), fotoZone = form.querySelector('#fotoZone'), fotoLabel = form.querySelector('#fotoLabel');
    fotoZone.addEventListener('click', function () { fotoInput.click(); });
    fotoInput.addEventListener('change', function () {
        if (fotoInput.files.length) { fotoZone.classList.add('has'); fotoLabel.textContent = '✓ ' + fotoInput.files[0].name; }
        else { fotoZone.classList.remove('has'); fotoLabel.textContent = 'Klik untuk lampirkan foto'; }
    });

    // Prefill
    var btn = form.querySelector('#btnPrefill'), note = form.querySelector('#prefillNote');
    if (btn) btn.addEventListener('click', function () {
        var tgl = form.querySelector('[name="tanggal_from"]').value;
        if (!tgl) { note.textContent = 'Pilih tanggal dulu ya.'; return; }
        note.textContent = 'Mengambil data absen…';
        fetch('{{ route('akun.pengajuan.prefill') }}?tanggal=' + encodeURIComponent(tgl), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    form.querySelector('#jamFrom').value = d.jam_from;
                    form.querySelector('#jamTo').value = d.jam_to;
                    calcDur();
                    note.textContent = 'Terisi dari mesin absen (pulang ' + d.scan_last + '). Cek & ganti kalau perlu.';
                } else { note.textContent = d.reason || 'Tidak bisa ambil dari mesin absen.'; }
            })
            .catch(function () { note.textContent = 'Gagal ambil data absen.'; });
    });

    sync();
})();
</script>
