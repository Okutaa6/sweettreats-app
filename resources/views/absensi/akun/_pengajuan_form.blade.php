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

        {{-- Tanggal + Jam (grid 2×2) --}}
        <div class="absk-grid2">
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

        submitBtn.disabled = !t;
        var ajTeks = form.querySelector('#ajSubmitTeks');
        if (ajTeks) ajTeks.textContent = t ? 'Kirim Permohonan' : 'Pilih jenis dulu';
        if (isLembur) calcDur();
    }

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
