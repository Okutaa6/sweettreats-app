{{--
    FRM-IT-05 — UAT (Kelas A, pemohon saja). Fase 2b.

    Untuk pemohon: kartu "Della, apakah ini yang kamu minta?" — bukan formulir.
    Skenario ditulis IT dari sudut pandang pemohon (coba X · seharusnya Y);
    pemohon menjawab Sesuai / Tidak (+ catatan saat Tidak) + Masukan End User,
    lalu memutuskan: Disetujui Go Live · Disetujui dengan Catatan (mati sampai
    ada catatan; catatan → daftar tindak lanjut) · Perlu Perbaikan (kembali ke
    DEV + konfirmasi FRM-IT-03 direset). IT melihat versi baca + banner tunggu.

    ⚠️ Jawaban dan TIGA tombol keputusan berada dalam SATU formulir (route jawab
    menerima `keputusan` opsional): pemohon yang langsung menekan "Disetujui…"
    tanpa "Simpan jawabanku" tak kehilangan tanda & catatannya.
    ⚠️ Kalau pemohon dan IT adalah orang yang sama (tiket tanpa pemohon → Head IT),
    formulir JAWAB yang menang — dua formulir tak boleh saling membungkus.

    Butuh: $t, $v, $wait, $peran, $bolehSkenario, $bolehJawabUat
--}}
@php
    $F   = $v['fr05'] ?? [];
    $sk  = $F['skenario'] ?? [];
    $decided = \App\Services\ItTracker\ItTrackerService::uatSudahDiputuskan($F);
    $now = $t->fase === 'UAT';
    $mine = $bolehJawabUat && ! $decided;                 // pemohon di UAT, belum diputuskan
    $skForm = $bolehSkenario && ! $decided && ! $mine;   // IT menyiapkan skenario (bukan saat ia sendiri menjawab)
    $hasCat = ! empty($F['masukan']) || count(array_filter($sk, fn ($s) => trim((string) ($s['catatan'] ?? '')) !== ''));
    $tl = $F['tindakLanjut'] ?? [];
    $lalu = $F['putaranLalu'] ?? [];
    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
    $namaPemohon = $t->pemohon->nama ?? 'Pemohon';
@endphp

<div class="itk-panel" style="margin-bottom:12px">
    <div class="itk-beban-bar"><b>FRM-IT-05 — UAT · USER ACCEPTANCE</b>
        <span class="itk-badge-fase">KELAS A · diisi pemohon</span></div>

    <div class="itk-panel-body">
        @if ($mine)
            <div class="itk-tanya">
                <div class="k">PERTANYAAN UNTUKMU</div>
                <div class="q">{{ session('name') }}, apakah ini yang kamu minta?</div>
                @if ($sk)
                    <div class="s">IT sudah menguji secara teknis. Coba {{ count($sk) }} hal di bawah, tandai <strong>Sesuai</strong> atau <strong>Tidak</strong>, lalu putuskan. Ini bukan dokumen — cukup jawab.</div>
                @else
                    <div class="s">IT belum menuliskan skenario yang bisa kamu coba. Kalau kamu sudah mencoba fiturnya sendiri, langsung putuskan di bawah; kalau belum, minta IT menulis skenarionya dulu.</div>
                @endif
            </div>
        @endif

        @if ($now && $lalu)
            @php $l = end($lalu); @endphp
            <div class="itk-hint" style="margin:0;padding:8px 11px;background:#FFF3F0;border-radius:9px;color:#8E3B2C">
                Putaran ke-{{ count($lalu) + 1 }}. Sebelumnya {{ count($lalu) }}× "Perlu Perbaikan"
                @if (! empty($l['tanggal'])) (terakhir {{ \Carbon\Carbon::parse($l['tanggal'])->translatedFormat('j M') }})@endif.
                @php $catLalu = array_values(array_filter(array_map(fn ($s) => trim((string) ($s['catatan'] ?? '')), $l['skenario'] ?? []))); @endphp
                @if ($catLalu) Catatan lama: "{{ implode('" · "', $catLalu) }}". @endif
                Jawaban di bawah dimulai kosong — nilai ulang kode yang sudah diperbaiki.
            </div>
        @endif

        {{-- Skenario: IT menulis (proses/harapan), pemohon menjawab (sesuai/catatan) --}}
        @if ($skForm)
            <form method="post" action="{{ route('it-tracker.skenario', $t->id) }}" id="itkSkForm">@csrf
        @elseif ($mine)
            <form method="post" action="{{ route('it-tracker.jawab', $t->id) }}" id="itkJawabForm">@csrf
        @endif
        <div class="itk-tbl itk-tbl-uat">
            <div class="itk-tbl-row itk-tbl-head"><span></span><span>COBA INI (PROSES BISNIS)</span><span>SEHARUSNYA</span><span>HASILNYA?</span><span></span></div>
            @forelse ($sk as $i => $s)
                @php $ses = $s['sesuai'] ?? null; $cat = $s['catatan'] ?? ''; @endphp
                <div class="itk-tbl-row">
                    <span class="itk-tbl-no num">{{ $i + 1 }}</span>
                    <span>@if ($skForm)<input class="itk-cell itk-cell-b" name="baris[{{ $i }}][proses]" value="{{ $s['proses'] }}" maxlength="500">@else<span class="itk-ro itk-ro-b">{{ $s['proses'] }}</span>@endif</span>
                    <span>@if ($skForm)<input class="itk-cell" name="baris[{{ $i }}][harapan]" value="{{ $s['harapan'] ?? '' }}" maxlength="500" placeholder="hasil yang seharusnya">@else<span class="itk-ro">{{ $s['harapan'] ?: '—' }}</span>@endif</span>
                    <span class="itk-seg">
                        @if ($mine)
                            <label class="itk-seg-btn pass {{ $ses === true ? 'on' : '' }}"><input type="radio" name="jawaban[{{ $i }}][sesuai]" value="1" {{ $ses === true ? 'checked' : '' }} data-uat-ok>Sesuai</label>
                            <label class="itk-seg-btn warn {{ $ses === false ? 'on' : '' }}"><input type="radio" name="jawaban[{{ $i }}][sesuai]" value="0" {{ $ses === false ? 'checked' : '' }} data-uat-no>Tidak</label>
                        @else
                            <span class="itk-seg-btn {{ $ses === true ? 'pass on' : ($ses === false ? 'warn on' : '') }}">{{ $ses === true ? 'Sesuai' : ($ses === false ? 'Tidak' : '—') }}</span>
                        @endif
                    </span>
                    <span class="itk-dotwrap">@if ($skForm)<button type="button" class="itk-x" data-sk-del title="Hapus" tabindex="-1">✕</button>@endif</span>
                    @if ($mine || trim((string) $cat) !== '')
                        <div class="itk-uat-cat" {{ $mine && $ses !== false && trim((string) $cat) === '' ? 'hidden' : '' }}>
                            @if ($mine)<input class="itk-cell" name="jawaban[{{ $i }}][catatan]" value="{{ $cat }}" maxlength="500" placeholder="apa yang beda dari yang kamu harapkan?" data-uat-cat>@else<span class="itk-ro">{{ $cat }}</span>@endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="itk-tbl-kaki" style="border-top:none">Belum ada skenario. IT menuliskannya dari sudut pandang pemohon — "coba lakukan X, seharusnya Y" — bukan dari sisi teknis.</div>
            @endforelse
            @if ($skForm)
                <div class="itk-tbl-row itk-tbl-ghost" data-sk-ghost>
                    <span class="itk-tbl-no num">{{ count($sk) + 1 }}</span>
                    <span><input class="itk-cell itk-cell-b" name="baris[{{ count($sk) }}][proses]" maxlength="500" placeholder="tulis langkah yang harus dicoba pemohon… (kosong = diabaikan)"></span>
                    <span><input class="itk-cell" name="baris[{{ count($sk) }}][harapan]" maxlength="500" placeholder="hasil yang seharusnya"></span>
                    <span></span><span></span>
                </div>
            @endif
        </div>

        @if ($skForm)
            <div style="display:flex;align-items:center;gap:10px">
                <button type="submit" class="itk-btn itk-btn-grafit" style="padding:7px 13px;font-size:12px">Simpan skenario</button>
                <span class="itk-hint" style="margin:0">Enter menambah baris. Tulis dari sudut pemohon: "coba lakukan X, seharusnya Y".</span>
            </div>
            </form>
        @endif

        {{-- Masukan end user --}}
        @if ($mine || ! empty($F['masukan']))
            <div>
                <label class="itk-lbl">MASUKAN END USER (opsional)</label>
                @if ($mine)<textarea name="masukan" rows="2" maxlength="1000" placeholder="hal kecil yang kamu ingin diubah, meski secara umum sudah sesuai" data-uat-cat>{{ $F['masukan'] ?? '' }}</textarea>
                @else<div class="itk-ro-blok">{{ $F['masukan'] }}</div>@endif
            </div>
        @endif
        @if ($mine)
            <div style="display:flex;align-items:center;gap:10px">
                <button type="submit" class="itk-btn itk-btn-putih" style="padding:7px 13px;font-size:12px">Simpan jawabanku dulu</button>
                <span class="itk-hint" style="margin:0">Opsional — menekan salah satu keputusan di bawah juga menyimpan jawabanmu.</span>
            </div>

            {{-- Tiga keputusan — di formulir yang SAMA dengan jawaban --}}
            <div class="itk-putus3">
                <button type="submit" name="keputusan" value="setuju" class="itk-putus hijau"><b>Disetujui Go Live</b><span>Semua sesuai — lanjut dipasang.</span></button>
                <button type="submit" name="keputusan" value="catatan" class="itk-putus catatan" id="itkPutusCatatan" {{ $hasCat ? '' : 'disabled' }}><b>Disetujui dengan Catatan</b><span data-putus-catatan-hint>{{ $hasCat ? 'Lanjut Go Live; catatanmu jadi daftar tindak lanjut.' : 'Tulis dulu catatan di baris "Tidak" atau Masukan — tombol ini menyala sendiri.' }}</span></button>
                <button type="submit" name="keputusan" value="perbaikan" class="itk-putus merah"><b>Perlu Perbaikan</b><span>Tiket kembali ke Development — bukan berhenti.</span></button>
            </div>
            <div class="itk-hint" style="margin:0">Paraf: pemohon saja. Didiamkan tidak berarti disetujui — hitungan harinya berjalan di kolommu.</div>
            </form>
        @endif

        @if ($decided)
            <div class="itk-decided ok num">
                ✓ {{ ($F['keputusan'] ?? '') === 'catatan' ? 'Disetujui dengan Catatan' : 'Disetujui Go Live' }} — {{ $namaPemohon }}
                @if (! empty($F['tanggal'])) · {{ \Carbon\Carbon::parse($F['tanggal'])->translatedFormat('j M H:i') }} @endif
            </div>
        @elseif (($F['keputusan'] ?? null) === 'perbaikan' && ! $now)
            <div class="itk-decided ulang num">
                ↺ Perlu Perbaikan — dikembalikan ke Development
                @if (! empty($F['tanggal'])) · {{ \Carbon\Carbon::parse($F['tanggal'])->translatedFormat('j M H:i') }} @endif
                — kembali ke UAT sesudah IT mengonfirmasi ulang FRM-IT-03.
            </div>
        @endif

        @if ($now && ! $mine && ! $decided)
            <div class="itk-banner-tunggu heat{{ $wait['heat'] }}">
                Menunggu {{ $namaPemohon }} menjawab — sudah {{ $wait['days'] }} hari. Didiamkan tidak otomatis diterima; hitungannya menyala di papan sebagai bukti bola bukan di IT.
            </div>
        @endif

        {{-- Tindak lanjut dari catatan UAT → "Jadikan tiket" per butir (IT) --}}
        @if ($tl)
            <div>
                <span class="itk-lbl">TINDAK LANJUT DARI CATATAN UAT</span>
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach ($tl as $i => $x)
                        <div class="itk-tl">
                            <span class="itk-tl-txt">{{ $x['txt'] }}</span>
                            @if (! empty($x['ticketId']))
                                <span class="itk-tl-done num">→ {{ $x['ticketId'] }}</span>
                            @elseif ($adalahIt)
                                <form method="post" action="{{ route('it-tracker.spawn', $t->id) }}">@csrf<input type="hidden" name="dari" value="uat"><input type="hidden" name="indeks" value="{{ $i }}">
                                    <button type="submit" class="itk-btn itk-btn-grafit" style="padding:5px 10px;font-size:11px">Jadikan tiket</button></form>
                            @else
                                <span class="itk-hint" style="margin:0">menunggu IT menjadikannya tiket</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@if ($skForm || $mine)
<script>
(function () {
    // IT: baris hantu skenario — Enter menambah baris; hapus baris
    var f = document.getElementById('itkSkForm');
    if (f) {
        var tbl = f.querySelector('.itk-tbl-uat');
        function renum() { var n = 0; tbl.querySelectorAll('.itk-tbl-row:not(.itk-tbl-head)').forEach(function (r) { var no = r.querySelector('.itk-tbl-no'); if (no) no.textContent = String(n + 1); r.querySelectorAll('[name]').forEach(function (x) { x.name = x.name.replace(/baris\[\d+\]/, 'baris[' + n + ']'); }); n++; }); }
        function bind() {
            tbl.querySelectorAll('[data-sk-del]').forEach(function (b) { b.onclick = function () { b.closest('.itk-tbl-row').remove(); renum(); }; });
            var g = tbl.querySelector('[data-sk-ghost]'); if (!g) return;
            g.querySelectorAll('input.itk-cell').forEach(function (inp) { inp.onkeydown = function (e) {
                if (e.key !== 'Enter') return; e.preventDefault();
                if (!g.querySelector('input.itk-cell-b').value.trim()) return;
                g.removeAttribute('data-sk-ghost'); g.classList.remove('itk-tbl-ghost');
                g.lastElementChild.innerHTML = '<button type="button" class="itk-x" data-sk-del title="Hapus" tabindex="-1">✕</button>';
                var ng = g.cloneNode(true); ng.setAttribute('data-sk-ghost', ''); ng.classList.add('itk-tbl-ghost');
                ng.querySelectorAll('input').forEach(function (x) { x.value = ''; }); ng.lastElementChild.innerHTML = '';
                tbl.appendChild(ng); renum(); bind(); ng.querySelector('input').focus();
            }; });
        }
        bind();
    }
    // Pemohon: memilih "Tidak" membuka kotak catatan
    document.querySelectorAll('[data-uat-no],[data-uat-ok]').forEach(function (r) {
        r.addEventListener('change', function () {
            var cat = r.closest('.itk-tbl-row').querySelector('.itk-uat-cat'); if (!cat) return;
            if (r.hasAttribute('data-uat-no')) { cat.hidden = false; var i = cat.querySelector('input'); if (i) i.focus(); }
            else if (!cat.querySelector('input').value.trim()) cat.hidden = true;
        });
    });
    // "Disetujui dengan Catatan" menyala sendiri begitu ada catatan/masukan diketik (tombol mati bilang kenapa — P3)
    var btn = document.getElementById('itkPutusCatatan');
    if (btn) {
        var hint = btn.querySelector('[data-putus-catatan-hint]');
        function cek() {
            var ada = false;
            document.querySelectorAll('[data-uat-cat]').forEach(function (x) { if (x.value.trim()) ada = true; });
            btn.disabled = !ada;
            if (hint) hint.textContent = ada ? 'Lanjut Go Live; catatanmu jadi daftar tindak lanjut.' : 'Tulis dulu catatan di baris "Tidak" atau Masukan — tombol ini menyala sendiri.';
        }
        document.querySelectorAll('[data-uat-cat]').forEach(function (x) { x.addEventListener('input', cek); });
        cek();
    }
})();
</script>
@endif
