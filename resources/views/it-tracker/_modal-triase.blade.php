{{--
    Modal penilaian awal (fase 'TRIASE') — dipakai papan DAN detail tiket.
    Sejak 9 Sep 2026 dibuka juga untuk Executive & Konsultan IT (bolehTriase),
    tanpa menaikkan peran mereka: bagian PIC hanya tampil bila $picOpsi terisi
    (Head IT), dan formulir Tunda/Tolak hanya bila $bolehParkir (Head IT).

    Dulu hanya ada di papan, sehingga Head IT yang sedang membaca satu tiket
    harus dilempar balik ke papan (`?q=nomor`) untuk menilainya. Formulirnya
    sama persis; yang berbeda cuma dari mana ia dibuka.

    Butuh: $picOpsi, $bolehParkir. Buka lewat itkBukaTriase(id, nomor, judul, prioritasPemohon).
--}}
<div class="itk-modal-scrim" id="itkModalTriase" onclick="if (event.target === this) itkTutupTriase()">
    <div class="itk-modal">
        <div class="itk-modal-hd"><b>PENILAIAN AWAL — KEPUTUSAN PENILAI, BUKAN PEMOHON</b><span class="itk-badge-fase">FASE 1</span>
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
                {{-- Modul katalog: satu pertanyaan yang menyambungkan papan & katalog
                     (rencana §14.24). BOLEH kosong — tiket tanpa modul masuk baris
                     "Tanpa modul", dan itu jujur; tebakan dari judul tidak. --}}
                @if (($modulOpsi ?? collect())->isNotEmpty())
                    <div style="margin-bottom:13px">
                        <label class="itk-lbl">MODUL YANG DISENTUH — KATALOG</label>
                        <select name="modul_id" class="itk-dep-sel" style="border-style:solid">
                            <option value="">— belum ditentukan (masuk "Tanpa modul") —</option>
                            @foreach ($modulOpsi->groupBy('departemen') as $dept => $grup)
                                <optgroup label="{{ $dept }}">
                                    @foreach ($grup as $mo)
                                        <option value="{{ $mo->id }}">{{ $mo->nama }}{{ $mo->status !== 'hidup' ? ' · ' . \App\Services\ItTracker\KatalogModul::statusLabel($mo->status) : '' }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="itk-hint">Modul baru? Buat entrinya dulu di <a href="{{ route('it-tracker.katalog') }}">Katalog Modul</a> — begitulah modul lahir, dari tiket.</div>
                    </div>
                @endif
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
                {{-- Dua jalan keluar, dua-duanya jujur:
                     · Antre  = sudah dinilai, menunggu ada yang memegang (tombolnya "Tarik")
                     · Analisis = penilainya sekaligus penganalisisnya, jadi tak perlu singgah
                     Tombol kedua hanya untuk Head IT / wakilnya, dan MATI untuk jenjang S
                     yang memang tak punya fase Analysis & Design. --}}
                <button type="submit" class="itk-btn itk-btn-grafit" style="width:100%">Simpan &amp; masukkan antrean</button>
                @if ($bolehKerjaIt ?? false)
                    <button type="submit" name="lanjut" value="analisis" id="itkTriaseLanjut"
                            class="itk-btn itk-btn-putih" style="width:100%;margin-top:7px">Simpan &amp; langsung mulai Analysis &amp; Design</button>
                    <div class="itk-hint" id="itkTriaseLanjutKet">Melewati Antre — pilih ini kalau kamu yang akan menganalisisnya sekarang.</div>
                @endif
            </form>
            @if ($bolehParkir ?? false)
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
            @endif
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
    var parkir = document.getElementById('itkParkirForm');
    if (parkir) parkir.action = '{{ url('it-tracker/tiket') }}/' + id + '/parkir';
    document.getElementById('itkModalTriase').classList.add('buka');
}
// Jenjang S tak punya fase Analysis & Design — tombolnya dimatikan DAN menyebut
// alasannya, bukan diam-diam hilang (pola P: tombol mati bilang kenapa).
(function () {
    var btn = document.getElementById('itkTriaseLanjut');
    if (! btn) return;
    var ket = document.getElementById('itkTriaseLanjutKet');
    document.getElementsByName('jenjang').forEach(function (r) {
        r.addEventListener('change', function () {
            var s = r.value === 'S' && r.checked;
            btn.disabled = s;
            btn.style.opacity = s ? '.45' : '';
            btn.style.cursor = s ? 'not-allowed' : '';
            ket.textContent = s
                ? 'Jenjang Support tak melewati Analysis & Design — tiketnya masuk antrean, lalu ditarik saat mulai dikerjakan.'
                : 'Melewati Antre — pilih ini kalau kamu yang akan menganalisisnya sekarang.';
        });
    });
})();
function itkTutupTriase() {
    document.getElementById('itkModalTriase').classList.remove('buka');
}
</script>
