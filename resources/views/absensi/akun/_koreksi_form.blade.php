{{--
    Form "Koreksi Absen" — dipakai di drawer, dibuka dengan mengetuk satu hari.
    Tanggal & status hari itu diisi oleh JS dari baris yang diketuk.
    Kirim ke route pengajuan yang sama dengan tipe=koreksi (lihat AkunPengajuanController).
--}}
<form action="{{ route('akun.pengajuan.store') }}" method="POST" enctype="multipart/form-data" class="absk-ajform">
    @csrf
    <input type="hidden" name="tipe" value="koreksi">
    <input type="hidden" name="tanggal_from" id="korTanggal" value="">

    <div class="absk-ajfields">
        <div class="absk-korday">
            <div class="absk-korday-tgl num" id="korTglLabel">—</div>
            <div class="absk-korday-now">
                <span class="absk-pill" id="korStatusPill">—</span>
                <span class="absk-korday-jam num" id="korJam">tercatat —</span>
            </div>
        </div>

        <div class="absk-field">
            <label class="absk-lbl">APA YANG KELIRU? <span class="absk-req">*</span></label>
            <div class="absk-korreasons">
                @foreach($korAlasan as $a)
                    <label class="absk-korchip">
                        <input type="radio" name="koreksi_alasan" value="{{ $a }}" required>
                        <span>{{ $a }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="absk-field">
            <label class="absk-lbl">CATATAN <span class="absk-lbl-note">(boleh dikosongkan)</span></label>
            <textarea name="alasan" class="absk-textarea" rows="3"
                      placeholder="Jelaskan singkat — mis. saya masuk 09:00 tapi lupa scan"></textarea>
        </div>

        <div class="absk-field">
            <label class="absk-lbl">FOTO BUKTI <span class="absk-lbl-note">(kalau ada)</span></label>
            <label class="absk-dropzone">
                <input type="file" name="foto" accept="image/*" hidden>
                <span>Ketuk untuk lampirkan foto</span>
            </label>
        </div>

        <div class="absk-hint">
            Kalau disetujui, hari ini dihitung <strong>hadir</strong>. Kalau hari itu kamu memang
            cuti atau izin, pakai form permohonan biasa — bukan koreksi.
        </div>
    </div>

    <div class="absk-ajfooter">
        <button type="button" class="absk-batal" data-close-koreksi>Batal</button>
        <button type="submit" class="absk-submit">Kirim Koreksi</button>
    </div>
</form>
