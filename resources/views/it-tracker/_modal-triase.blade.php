{{--
    Modal triase (Head IT) — dipakai papan DAN detail tiket.

    Dulu hanya ada di papan, sehingga Head IT yang sedang membaca satu tiket
    harus dilempar balik ke papan (`?q=nomor`) untuk mentriasenya. Formulirnya
    sama persis; yang berbeda cuma dari mana ia dibuka.

    Butuh: $picOpsi. Buka lewat itkBukaTriase(id, nomor, judul, prioritasPemohon).
--}}
<div class="itk-modal-scrim" id="itkModalTriase" onclick="if (event.target === this) itkTutupTriase()">
    <div class="itk-modal">
        <div class="itk-modal-hd"><b>TRIASE — DIISI IT, BUKAN PEMOHON</b><span class="itk-badge-fase">FASE 1</span>
            <button type="button" onclick="itkTutupTriase()">✕</button></div>
        <div class="itk-modal-body">
            <div>
                <span class="itk-no num" id="itkTriaseNo"></span>
                <div style="font-size:15px;font-weight:800" id="itkTriaseJudul"></div>
                <div class="itk-hint" id="itkTriasePrioP"></div>
            </div>
            <form method="post" id="itkTriaseForm">
                @csrf
                <label class="itk-lbl">JENJANG — MENENTUKAN FASE YANG DILEWATI</label>
                <div style="display:flex;gap:7px;margin-bottom:13px">
                    <label><input class="itk-radio" type="radio" name="jenjang" value="S" required><span><b>Support</b> · bug/tweak — 2 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="M"><span><b>Fitur</b> · 5 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="L"><span><b>Modul</b> · 7 fase</span></label>
                </div>
                <div class="itk-hint" style="margin:-8px 0 13px">Pilihan ini juga menentukan tabnya: <strong>Support</strong> masuk tab Support;
                    <strong>Fitur</strong> &amp; <strong>Modul</strong> masuk tab Fitur / Modul.</div>
                @if ($picOpsi->count() > 1)
                    <div style="margin-bottom:13px">
                        <label class="itk-lbl">DIKERJAKAN SIAPA (PIC)</label>
                        <div style="display:flex;gap:7px;flex-wrap:wrap">
                            @foreach ($picOpsi as $p)
                                <label><input class="itk-radio" type="radio" name="pic_user_id" value="{{ $p->id }}"><span>{{ $p->nama }}</span></label>
                            @endforeach
                        </div>
                        <div class="itk-hint">Opsional — boleh ditentukan nanti; tanpa PIC, yang pertama menarik tiketnya yang jadi pengerja.</div>
                    </div>
                @endif
                <div class="itk-grid2" style="margin-bottom:13px">
                    <div>
                        <label class="itk-lbl">BOBOT (1–5)</label>
                        <div style="display:flex;gap:6px">
                            @foreach (range(1, 5) as $b)
                                <label><input class="itk-radio" type="radio" name="bobot" value="{{ $b }}" required><span>{{ $b }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="itk-lbl">PRIORITAS IT</label>
                        <div style="display:flex;gap:6px">
                            @foreach (['P1', 'P2', 'P3'] as $p)
                                <label><input class="itk-radio" type="radio" name="prioritas_it" value="{{ $p }}" required><span>{{ $p }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button type="submit" class="itk-btn itk-btn-grafit" style="width:100%">Simpan &amp; masukkan antrean</button>
            </form>
            <div class="itk-sekat"></div>
            <form method="post" id="itkParkirForm">
                @csrf
                <label class="itk-lbl">ATAU TUNDA / TOLAK — ALASANNYA WAJIB, SUPAYA YANG DITUNDA TIDAK HILANG TANPA JEJAK</label>
                <input class="itk-in" type="text" name="alasan" required maxlength="500"
                       placeholder="mis. ditunda sampai komisi 70% selesai">
                <div style="display:flex;gap:8px;margin-top:9px">
                    <button type="submit" name="ke" value="DITUNDA" class="itk-btn itk-btn-oranye">Tunda</button>
                    <button type="submit" name="ke" value="DITOLAK" class="itk-btn itk-btn-putih">Tolak…</button>
                </div>
                <div class="itk-hint">Ditunda tetap tampil di parkiran papan dengan alasannya — ditunda bukan berarti hilang.</div>
            </form>
        </div>
    </div>
</div>
<script>
function itkBukaTriase(id, nomor, judul, prioP) {
    document.getElementById('itkTriaseNo').textContent = nomor;
    document.getElementById('itkTriaseJudul').textContent = judul;
    document.getElementById('itkTriasePrioP').textContent = prioP
        ? 'Prioritas menurut pemohon: ' + prioP + ' — masukan, bukan keputusan. Kolomnya terpisah.'
        : '';
    document.getElementById('itkTriaseForm').action = '{{ url('it-tracker/tiket') }}/' + id + '/triase';
    document.getElementById('itkParkirForm').action = '{{ url('it-tracker/tiket') }}/' + id + '/parkir';
    document.getElementById('itkModalTriase').classList.add('buka');
}
function itkTutupTriase() {
    document.getElementById('itkModalTriase').classList.remove('buka');
}
</script>
