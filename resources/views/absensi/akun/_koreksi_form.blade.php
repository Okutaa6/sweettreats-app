{{--
    "Koreksi Absensi" — modal di tengah (ikut handoff_absensi), dibuka dgn mengetuk
    satu hari. Tanggal, status & jam tercatat diisi JS dari baris yang diketuk.
    Kirim ke route pengajuan yang sama dengan tipe=koreksi.
--}}
<form action="{{ route('akun.pengajuan.store') }}" method="POST" enctype="multipart/form-data" class="absk-korcard" id="korForm">
    @csrf
    <input type="hidden" name="tipe" value="koreksi">
    <input type="hidden" name="tanggal_from" id="korTanggal" value="">

    <div class="absk-korhead">
        <div class="absk-korhead-lbl">KOREKSI ABSENSI</div>
        <div class="absk-korhead-tgl num" id="korTglLabel">—</div>
        <div class="absk-korhead-now">
            <span class="absk-pill" id="korStatusPill">—</span>
            <span class="absk-korhead-jam num" id="korJam">tercatat —</span>
        </div>
    </div>

    <div class="absk-korbody">
        <label class="absk-korlbl">APA YANG KELIRU? <span class="absk-req">*</span></label>
        <div class="absk-korreasons">
            @foreach($korAlasan as $a)
                <label class="absk-korchip">
                    <input type="radio" name="koreksi_alasan" value="{{ $a }}" required>
                    <span>{{ $a }}</span>
                </label>
            @endforeach
        </div>

        <label class="absk-korlbl mt">CATATAN</label>
        <textarea name="alasan" class="absk-kortextarea" rows="3"
                  placeholder="Jelaskan singkat (mis. saya masuk 09:00 tapi lupa scan)…"></textarea>

        <label class="absk-korfoto" id="korFotoZone">
            <input type="file" name="foto" accept="image/*" id="korFotoInput" hidden>
            <span class="absk-korfoto-tick" hidden>✓</span>
            <span class="absk-korfoto-lbl">Lampirkan foto (opsional)</span>
        </label>

        <div class="absk-korbtns">
            {{-- Label tombolnya sendiri yang menjelaskan kenapa dia mati
                 (pola-interaksi P3) — berubah jadi "Kirim Koreksi" begitu
                 alasan dipilih. --}}
            <button type="submit" class="absk-korsubmit" id="korSubmit" disabled>Pilih dulu apa yang keliru</button>
            <button type="button" class="absk-korbatal" data-close-koreksi>Batal</button>
        </div>
    </div>
</form>
<script>
    // Umpan balik kirim (pola-interaksi P2): tombol bilang sedang bekerja +
    // kebal klik dobel. Dinonaktifkan DI DALAM handler submit — pengiriman
    // yang sudah berjalan tidak ikut terhenti.
    document.getElementById('korForm').addEventListener('submit', function () {
        var b = document.getElementById('korSubmit');
        b.disabled = true;
        b.textContent = 'Mengirim…';
    });
    // P3: begitu alasan dipilih, label kembali jadi ajakan kirim. (Halaman
    // pemanggil yang meng-enable tombolnya; teksnya diurus di sini supaya
    // partial ini utuh sendiri.)
    document.querySelectorAll('#korForm input[name="koreksi_alasan"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('korSubmit').textContent = 'Kirim Koreksi';
        });
    });
</script>
