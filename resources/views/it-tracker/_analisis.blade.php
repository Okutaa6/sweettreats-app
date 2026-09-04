{{--
    FRM-IT-02 — Analysis & Design (Kelas C, Fase 2b). Acuan: handoff r2.

    DUA KEDALAMAN, SATU PERMUKAAN: banner "02 RINGKAS · M" / "02 PENUH · L".
    ⛔ Blok Termasuk / Tidak termasuk / Desain teknis HANYA dirender untuk L —
    M tak pernah melihatnya (uji terima: kendali negatif). Naik ke L lewat
    "naikkan jenjang" (fase tak berubah), bukan dengan membuka field L di M.

    Tabel FR + baris hantu (tanpa tombol Tambah). Kriteria Diterima = kolom
    TERLEBAR, selnya membesar saat diketik, Tab berpindah antar sel (DOM order
    row-major + Enter pindah baris). Paraf BO = baris berstempel waktu.
    Mengubah isi sesudah diparaf MENGHAPUS parafnya (T2) — di layanan.

    Butuh: $t, $v, $peran, $bolehAnalisis, $bolehParafBo, $depOpsi, $depTiket, $gate
--}}
@php
    $F     = $v['fr02'] ?? [];
    $fr    = $F['fr'] ?? [];
    $isL   = $t->jenjang === 'L';
    $ed    = $bolehAnalisis;                       // Head IT di fase ANALISIS
    $adaKr = count(array_filter($fr, fn ($r) => trim((string) ($r['kriteria'] ?? '')) !== ''));
    $komp  = $F['komponen'] ?? [];
    $deps  = $v['deps'] ?? [];
    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
    $paraf = $F['paraf'] ?? null;
    $PRIO  = ['Tinggi', 'Sedang', 'Rendah'];
    $labelFaseDep = fn ($f) => in_array($f, ['TRIASE','ANTRE','SELESAI','DITUNDA','DITOLAK'], true)
        ? ucfirst(strtolower($f)) : \App\Services\ItTracker\PhaseEngine::phShort(\App\Services\ItTracker\PhaseEngine::phaseOf($f));
@endphp

<div class="itk-panel" style="margin-bottom:12px">
    <div class="itk-beban-bar"><b>FRM-IT-02 — ANALYSIS &amp; DESIGN</b>
        <span class="itk-badge-fase">KELAS C · dipikirkan bersama</span></div>

    <div class="itk-panel-body">
        {{-- kedalaman --}}
        <div class="itk-depth">
            <span class="itk-depth-tag {{ $isL ? 'L' : 'M' }}">{{ $isL ? '02 PENUH · L' : '02 RINGKAS · ' . ($t->jenjang ?: 'M') }}</span>
            <span class="itk-depth-txt">{{ $isL
                ? 'Modul besar: FR + kriteria, komponen, ruang lingkup termasuk/tidak, desain teknis, catatan dapur.'
                : 'Cukup tabel FR + Kriteria Diterima + komponen terdampak. Tidak ada field lain yang diminta — sengaja.' }}</span>
            @if (! $isL && $ed)
                <details class="itk-naik">
                    <summary>butuh detail L? naikkan jenjang</summary>
                    <form method="post" action="{{ route('it-tracker.jenjang', $t->id) }}" class="itk-naik-form">
                        @csrf
                        <div class="itk-hint" style="margin:0 0 6px">Mode ubah-jenjang: fase <strong>tidak</strong> berubah. Paraf BO yang ada dihapus kalau jenjang berubah. (Kalau ternyata pekerjaan kecil/S: tunda tiketnya lalu buka lagi lewat triase.)</div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                            @foreach (['M','L'] as $j)
                                <label><input class="itk-radio" type="radio" name="jenjang" value="{{ $j }}" {{ $t->jenjang === $j ? 'checked' : '' }} required><span>{{ $j }}</span></label>
                            @endforeach
                            <span class="itk-lbl" style="margin:0 2px 0 8px">BOBOT</span>
                            @foreach (range(1,5) as $b)
                                <label><input class="itk-radio" type="radio" name="bobot" value="{{ $b }}" {{ (int) $t->bobot === $b ? 'checked' : '' }} required><span>{{ $b }}</span></label>
                            @endforeach
                            <span class="itk-lbl" style="margin:0 2px 0 8px">PRIO</span>
                            @foreach (['P1','P2','P3'] as $p)
                                <label><input class="itk-radio" type="radio" name="prioritas_it" value="{{ $p }}" {{ $t->prioritas_it === $p ? 'checked' : '' }} required><span>{{ $p }}</span></label>
                            @endforeach
                            <button type="submit" class="itk-btn itk-btn-grafit" style="padding:6px 12px;font-size:11.5px">Simpan jenjang</button>
                        </div>
                    </form>
                </details>
            @endif
        </div>

        {{-- Tabel FR --}}
        <form method="post" action="{{ route('it-tracker.fr', $t->id) }}" id="itkFrForm">
            @csrf
            <div class="itk-tbl-hd">
                <span class="itk-lbl" style="margin:0">KEBUTUHAN FUNGSIONAL</span>
                <span class="itk-tbl-sum num">{{ count($fr) }} FR · {{ $adaKr }} punya kriteria</span>
            </div>
            @if (! $fr && $ed)
                <div class="itk-kosong-fr">Belum ada FR. Mulai dari yang paling penting: <strong>apa yang harus bisa dilakukan</strong> —
                    lalu tulis di kolom kanan <strong>kapan itu dianggap selesai</strong>. Satu baris dengan kriteria sudah cukup membuka gerbang.</div>
            @elseif (! $fr)
                <div class="itk-kosong-fr">Belum ada FR — Head IT belum mengisi analisisnya. Paraf Business Owner menunggu isinya.</div>
            @endif
            <div class="itk-tbl itk-tbl-fr">
                <div class="itk-tbl-row itk-tbl-head">
                    <span></span><span>FR</span><span>KEBUTUHAN / FUNGSI</span><span>PRIO</span>
                    <span class="itk-tbl-key">KRITERIA DITERIMA — gerbangnya</span><span></span>
                </div>
                @foreach ($fr as $i => $r)
                    @php $lit = trim((string) ($r['kriteria'] ?? '')) !== ''; @endphp
                    <div class="itk-tbl-row" data-fr-row>
                        <span class="itk-dotwrap"><span class="itk-dot {{ $lit ? 'on' : '' }}"></span></span>
                        <span class="itk-tbl-no num">{{ $r['id'] }}</span>
                        <span>@if ($ed)<input class="itk-cell itk-cell-b" name="baris[{{ $i }}][kebutuhan]" value="{{ $r['desc'] }}" maxlength="500">@else<span class="itk-ro itk-ro-b">{{ $r['desc'] }}</span>@endif</span>
                        <span>@if ($ed)<select class="itk-cell itk-prio-sel" name="baris[{{ $i }}][prioritas]">@foreach ($PRIO as $p)<option {{ ($r['prio'] ?? 'Sedang') === $p ? 'selected' : '' }}>{{ $p }}</option>@endforeach</select>@else<span class="itk-prio-chip {{ $r['prio'] ?? 'Sedang' }}">{{ $r['prio'] ?? 'Sedang' }}</span>@endif</span>
                        <span>@if ($ed)<textarea class="itk-cell itk-cell-grow" name="baris[{{ $i }}][kriteria]" rows="1" maxlength="1000" placeholder="kapan ini dianggap SELESAI?">{{ $r['kriteria'] }}</textarea>@else<span class="itk-ro">{{ $r['kriteria'] ?: '—' }}</span>@endif</span>
                        <span class="itk-dotwrap">@if ($ed)<button type="button" class="itk-x" title="Hapus baris" data-fr-del tabindex="-1">✕</button>@endif</span>
                    </div>
                @endforeach
                @if ($ed)
                    {{-- baris hantu: ketik + Enter/Tab menambah; tanpa tombol Tambah --}}
                    <div class="itk-tbl-row itk-tbl-ghost" data-fr-row data-ghost>
                        <span></span>
                        <span class="itk-tbl-no num" data-fr-no>FR-{{ str_pad(count($fr) + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span><input class="itk-cell itk-cell-b" name="baris[{{ count($fr) }}][kebutuhan]" maxlength="500" placeholder="ketik kebutuhan berikutnya di sini, Enter untuk menambah…"></span>
                        <span><select class="itk-cell itk-prio-sel" name="baris[{{ count($fr) }}][prioritas]"><option>Tinggi</option><option selected>Sedang</option><option>Rendah</option></select></span>
                        <span><textarea class="itk-cell itk-cell-grow" name="baris[{{ count($fr) }}][kriteria]" rows="1" maxlength="1000" placeholder="kapan ini dianggap SELESAI?"></textarea></span>
                        <span></span>
                    </div>
                @endif
            </div>
            @if ($ed)
                <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
                    <button type="submit" class="itk-btn itk-btn-grafit" style="padding:7px 13px;font-size:12px">Simpan tabel FR</button>
                    <span class="itk-hint" style="margin:0">Tab berpindah antar sel · Enter di Kriteria melompat ke baris berikut · sel Kriteria membesar sendiri.
                        @if ($paraf) <strong>Mengubah isi akan menghapus paraf BO.</strong> @endif</span>
                </div>
            @endif
        </form>

        {{-- Komponen + Ketergantungan --}}
        <div class="itk-grid2">
            <div>
                <span class="itk-lbl">KOMPONEN TERDAMPAK</span>
                @if ($ed)
                    <form method="post" action="{{ route('it-tracker.komponen', $t->id) }}" style="display:flex;gap:7px;flex-wrap:wrap;align-items:center">
                        @csrf
                        @foreach (\App\Services\ItTracker\ItTrackerService::KOMPONEN as $k)
                            <label><input class="itk-radio" type="checkbox" name="komponen[]" value="{{ $k }}" {{ in_array($k, $komp, true) ? 'checked' : '' }}><span>{{ $k }}</span></label>
                        @endforeach
                        <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Simpan</button>
                    </form>
                @else
                    <div style="display:flex;gap:7px;flex-wrap:wrap">
                        @forelse ($komp as $k)<span class="itk-chip aktif" style="cursor:default">{{ $k }}</span>@empty<span class="itk-hint" style="margin:0">Belum dipilih.</span>@endforelse
                    </div>
                @endif
            </div>
            <div>
                <span class="itk-lbl">MENUNGGU TIKET LAIN</span>
                <div style="display:flex;flex-direction:column;gap:6px">
                    @foreach ($deps as $n)
                        @php $d = $depTiket[$n] ?? null; @endphp
                        <div class="itk-dep">
                            <span class="num itk-dep-no">{{ $n }}</span>
                            {{-- Head departemen lain hanya melihat tiketnya sendiri — judul tiket orang lain tak bocor lewat sini --}}
                            <span class="itk-dep-judul">{{ $d ? ($peran === 'head' ? 'tiket lain (' . $labelFaseDep($d->fase) . ')' : $d->judul) : '(tiket tak ditemukan)' }}</span>
                            @if ($d)<span class="itk-st {{ $d->fase === 'SELESAI' ? 'SELESAI' : (isset(\App\Services\ItTracker\PhaseEngine::RANK[$d->fase]) ? 'AKTIF' : 'DITUNDA') }}">{{ $labelFaseDep($d->fase) }}</span>@endif
                            @if ($ed)
                                <form method="post" action="{{ route('it-tracker.dep', $t->id) }}">@csrf<input type="hidden" name="nomor" value="{{ $n }}"><input type="hidden" name="aksi" value="hapus"><button type="submit" class="itk-x">✕</button></form>
                            @endif
                        </div>
                    @endforeach
                    @if ($ed)
                        <form method="post" action="{{ route('it-tracker.dep', $t->id) }}">
                            @csrf
                            <select name="nomor" class="itk-dep-sel" onchange="this.form.submit()">
                                <option value="">+ pilih tiket yang ditunggu…</option>
                                @foreach ($depOpsi as $o)
                                    @if (! in_array($o->nomor, $deps, true))
                                        <option value="{{ $o->nomor }}">{{ $o->nomor }} — {{ $o->judul }} · {{ $labelFaseDep($o->fase) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </form>
                    @elseif (! $deps)
                        <span class="itk-hint" style="margin:0">Tidak bergantung pada tiket lain.</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- 02 PENUH — HANYA L. M tidak pernah melihat blok ini. --}}
        @if ($isL)
            <div class="itk-penuh">
                <div class="itk-penuh-hd"><span class="itk-penuh-tag">02 PENUH</span>
                    <span>Khusus jenjang L — modul besar butuh batas yang tegas sebelum koding.</span></div>
                <form method="post" action="{{ route('it-tracker.lingkup', $t->id) }}" class="itk-penuh-body">
                    @csrf
                    <div><label class="itk-lbl" style="color:#2F8E55">TERMASUK</label>
                        @if ($ed)<textarea name="lingkup" rows="3" maxlength="2000" placeholder="apa saja yang masuk hitungan">{{ $F['lingkup'] ?? '' }}</textarea>@else<div class="itk-ro-blok">{{ $F['lingkup'] ?? '—' }}</div>@endif</div>
                    <div><label class="itk-lbl" style="color:#C0392B">TIDAK TERMASUK</label>
                        @if ($ed)<textarea name="luar" rows="3" maxlength="2000" placeholder="yang sengaja ditunda / di luar tiket ini">{{ $F['luar'] ?? '' }}</textarea>@else<div class="itk-ro-blok">{{ $F['luar'] ?? '—' }}</div>@endif</div>
                    <div style="grid-column:1/-1"><label class="itk-lbl">DESAIN TEKNIS (opsional)</label>
                        @if ($ed)<textarea name="desain" rows="2" maxlength="3000" placeholder="tabel, endpoint, integrasi — cukup poin-poin">{{ $F['desain'] ?? '' }}</textarea>@else<div class="itk-ro-blok">{{ $F['desain'] ?? '—' }}</div>@endif</div>
                    @if ($ed)<div style="grid-column:1/-1"><button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Simpan ruang lingkup</button></div>@endif
                </form>
            </div>
        @endif

        {{-- Catatan dapur — internal IT. Non-IT: DINYATAKAN ada, bukan disembunyikan diam-diam. --}}
        @if ($adalahIt)
            <form method="post" action="{{ route('it-tracker.dapur', $t->id) }}" class="itk-dapur">
                @csrf
                <div class="itk-dapur-hd"><span>CATATAN DAPUR — INTERNAL IT</span><em>tidak tampil ke Executive &amp; pemohon</em></div>
                <div class="itk-dapur-body">
                    @if ($ed)
                        <input name="estimasi" value="{{ $F['estimasi'] ?? '' }}" maxlength="120" placeholder="estimasi, mis. 5 hari kerja">
                        <input name="risiko" value="{{ $F['risiko'] ?? '' }}" maxlength="500" placeholder="risiko yang kepikiran">
                        <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Simpan</button>
                    @else
                        <span>{{ $F['estimasi'] ?? '—' }}</span><span>{{ $F['risiko'] ?? '—' }}</span>
                    @endif
                </div>
            </form>
        @elseif (! empty($F['estimasi']) || ! empty($F['risiko']))
            <div class="itk-dapur-hidden">Ada catatan dapur IT (estimasi &amp; risiko) — internal, tidak ditampilkan di sini.</div>
        @endif

        {{-- Bahan rancangan (revisi pasca-demo 5 Sep): mockup, alur proses, contoh
             keluaran. Sengaja TERPISAH dari lampiran FRM-IT-01 (bukti masalah dari
             pemohon) dan TIDAK menggugurkan paraf BO — ia bahan bantu, bukan isi
             yang disetujui. --}}
        @php
            $lampAn = $F['lampiran'] ?? [];
            $ukAn = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
        @endphp
        @if ($lampAn || $ed)
            <div>
                <span class="itk-lbl">BAHAN RANCANGAN <span style="font-weight:600;color:#8A857C">(mockup · alur proses · contoh keluaran)</span></span>
                @if ($lampAn)
                    <div class="itk-lamp-list">
                        @foreach ($lampAn as $x)
                            <a class="itk-lamp {{ str_starts_with((string) ($x['mime'] ?? ''), 'image/') ? 'img' : 'doc' }}"
                               href="{{ asset($x['path']) }}" target="_blank" rel="noopener" title="{{ $x['nama'] }} · {{ $ukAn($x['ukuran'] ?? 0) }}">
                                @if (str_starts_with((string) ($x['mime'] ?? ''), 'image/'))
                                    <img src="{{ asset($x['path']) }}" alt="{{ $x['nama'] }}" loading="lazy">
                                @else
                                    <span class="itk-lamp-ext">{{ strtoupper(pathinfo($x['path'], PATHINFO_EXTENSION) ?: 'file') }}</span>
                                @endif
                                <span class="itk-lamp-nama">{{ $x['nama'] }}</span>
                                <span class="itk-lamp-meta num">{{ $ukAn($x['ukuran'] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>
                @elseif (! $ed)
                    <span class="itk-hint" style="margin:0">Belum ada bahan rancangan.</span>
                @endif
                @if ($ed && count($lampAn) < \App\Services\ItTracker\ItTrackerService::LAMPIRAN_MAKS)
                    <form method="post" action="{{ route('it-tracker.lampiran', $t->id) }}" enctype="multipart/form-data"
                          style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:7px">
                        @csrf<input type="hidden" name="kode" value="FRM-IT-02">
                        <input class="itk-in itk-file" type="file" name="lampiran[]" multiple required style="max-width:340px"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.docx,.doc,.csv">
                        <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Tempelkan</button>
                        <span class="itk-hint" style="margin:0;flex-basis:100%">Diterima: {{ \App\Services\ItTracker\ItTrackerService::lampiranDiterima() }}.
                            Bahan rancangan tidak menggugurkan paraf Business Owner.</span>
                    </form>
                @elseif ($ed)
                    <span class="itk-hint" style="margin:0">Sudah {{ count($lampAn) }} berkas — batas per formulir tercapai.</span>
                @endif
            </div>
        @endif

        {{-- Paraf: IT + satu Business Owner --}}
        <div class="itk-paraf">
            <span class="itk-lbl" style="margin:0">PARAF</span>
            <span class="itk-paraf-it">IT — tombol gerbang di "Langkah berikutnya" (kanan) adalah parafnya</span>
            @php $lewat = $F['diteruskan'] ?? null; @endphp
            @if ($lewat)
                {{-- PELEWATAN bertanda (§14.16) — bukan paraf, dan tak boleh tampak seperti paraf.
                     Tetap tampil walau BO memparaf sesudahnya (keduanya tersimpan). --}}
                <span class="itk-paraf-lewat num"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                    Diteruskan {{ $lewat['oleh'] }} (Head IT) <strong>tanpa paraf Business Owner</strong> · {{ \Carbon\Carbon::parse($lewat['at'])->translatedFormat('j M H:i') }} · {{ $lewat['alasan'] }}</span>
            @endif
            @if ($paraf)
                <span class="itk-paraf-ok num">✓ Diparaf {{ $paraf['oleh'] }} (Business Owner) · {{ \Carbon\Carbon::parse($paraf['at'])->translatedFormat('j M H:i') }}</span>
            @elseif ($lewat && $bolehParafBo)
                <form method="post" action="{{ route('it-tracker.paraf', $t->id) }}">@csrf
                    <button type="submit" class="itk-btn itk-btn-hijau" style="padding:6px 12px;font-size:11.5px">Paraf sebagai Business Owner (menyusul)</button></form>
            @elseif ($lewat)
            @elseif ($bolehParafBo)
                @php $kurangParaf = array_values(array_map(fn ($c) => $c['t'], array_filter($gate['checks'] ?? [], fn ($c) => ! $c['ok'] && $c['t'] !== 'paraf satu Business Owner'))); @endphp
                @if ($kurangParaf)
                    {{-- tombol mati yang bilang kenapa (P3): paraf mengikuti isi, bukan mendahuluinya --}}
                    <button type="button" class="itk-btn itk-btn-hijau" style="padding:6px 12px;font-size:11.5px" disabled>Paraf sebagai Business Owner</button>
                    <span class="itk-paraf-wait">Belum bisa diparaf — Head IT belum melengkapi: {{ implode(' · ', $kurangParaf) }}.</span>
                @else
                    <form method="post" action="{{ route('it-tracker.paraf', $t->id) }}">@csrf
                        <button type="submit" class="itk-btn itk-btn-hijau" style="padding:6px 12px;font-size:11.5px">Paraf sebagai Business Owner</button></form>
                @endif
            @elseif ($t->fase === 'ANALISIS')
                <span class="itk-paraf-wait">Menunggu paraf satu Business Owner (Executive / Konsultan)</span>
            @endif
        </div>

        @if ($bolehTeruskan ?? false)
            {{-- Jalan pintas BERTANDA (§14.16): BO lambat → Head IT boleh MELEWATI, tapi
                 tercatat & terlihat sampai Go Live + ekspor. Bukan tombol paraf. Alasan wajib. --}}
            <details class="itk-naik itk-lewat-box">
                <summary>Business Owner lambat merespons? Teruskan tanpa paraf BO — tercatat</summary>
                <form method="post" action="{{ route('it-tracker.teruskan', $t->id) }}" class="itk-naik-form" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    @csrf<input type="hidden" name="gerbang" value="02">
                    <input class="itk-cell" name="alasan" required minlength="10" maxlength="300" style="flex:1;min-width:260px"
                           placeholder="alasan satu baris, wajib — mis. Robi cuti sampai 12 Sep, pemohon menunggu">
                    <button type="submit" class="itk-btn itk-btn-oranye" style="padding:6px 12px;font-size:11.5px">Teruskan tanpa paraf BO</button>
                    <span class="itk-hint" style="margin:0;flex-basis:100%">Ini <strong>pelewatan, bukan paraf</strong>: penandanya (siapa · kapan · alasan) ikut sampai Go Live, tampil di papan, dan tercetak di ekspor. BO masih bisa memparaf menyusul — jejaknya tetap.</span>
                </form>
            </details>
        @endif
    </div>
</div>

@if ($ed)
<script>
(function () {
    var form = document.getElementById('itkFrForm'); if (!form) return;
    var tbl  = form.querySelector('.itk-tbl-fr');
    function grow(el) { el.style.height = 'auto'; el.style.height = (el.scrollHeight + 2) + 'px'; }
    function renum() {
        var rows = tbl.querySelectorAll('[data-fr-row]:not(.itk-tbl-head)'), n = 0;
        rows.forEach(function (r) {
            var no = r.querySelector('.itk-tbl-no'); if (no) no.textContent = 'FR-' + String(n + 1).padStart(2, '0');
            r.querySelectorAll('[name]').forEach(function (f) { f.name = f.name.replace(/baris\[\d+\]/, 'baris[' + n + ']'); });
            n++;
        });
    }
    function ghostRow() { return tbl.querySelector('[data-ghost]'); }
    function spawnGhost() {
        var g = ghostRow(); if (!g) return;
        g.removeAttribute('data-ghost'); g.classList.remove('itk-tbl-ghost');
        var d = document.createElement('span'); d.className = 'itk-dotwrap';
        d.innerHTML = '<button type="button" class="itk-x" title="Hapus baris" data-fr-del tabindex="-1">✕</button>';
        g.replaceChild(d, g.lastElementChild);
        g.firstElementChild.innerHTML = '<span class="itk-dot"></span>'; g.firstElementChild.className = 'itk-dotwrap';
        var ng = g.cloneNode(true);
        ng.setAttribute('data-ghost', ''); ng.classList.add('itk-tbl-ghost');
        ng.querySelectorAll('input,textarea').forEach(function (f) { f.value = ''; });
        ng.querySelector('select').value = 'Sedang';
        ng.firstElementChild.innerHTML = ''; ng.lastElementChild.innerHTML = '';
        tbl.appendChild(ng); renum(); bind();
    }
    function bind() {
        tbl.querySelectorAll('.itk-cell-grow').forEach(function (ta) { grow(ta); ta.oninput = function () { grow(ta); }; });
        tbl.querySelectorAll('[data-fr-del]').forEach(function (b) { b.onclick = function () { b.closest('[data-fr-row]').remove(); renum(); }; });
        tbl.querySelectorAll('input.itk-cell, textarea.itk-cell').forEach(function (f) {
            f.onkeydown = function (e) {
                if (e.key !== 'Enter' || e.shiftKey) return;
                e.preventDefault();
                var row = f.closest('[data-fr-row]');
                if (row.hasAttribute('data-ghost')) { if (row.querySelector('input.itk-cell-b').value.trim()) spawnGhost(); }
                var next = row.nextElementSibling; if (next) { var t = next.querySelector('input.itk-cell-b'); if (t) t.focus(); }
            };
        });
        var gi = ghostRow() && ghostRow().querySelector('input.itk-cell-b');
        if (gi) gi.onblur = function () { if (gi.value.trim() && ghostRow() === gi.closest('[data-fr-row]')) spawnGhost(); };
    }
    bind();

    // Beberapa formulir dalam satu panel FRM-IT-02 (FR · komponen · ketergantungan ·
    // lingkup · dapur): menyimpan satu memuat ulang halaman dan MEMBUANG ketikan
    // di formulir lain — beri tahu dulu, jangan hilang diam-diam.
    var panel = form.closest('.itk-panel'), kotor = [];
    if (panel) panel.querySelectorAll('form').forEach(function (fm) {
        function tandai() { if (kotor.indexOf(fm) < 0) kotor.push(fm); }
        fm.addEventListener('input', tandai); fm.addEventListener('change', tandai);
        fm.addEventListener('submit', function (e) {
            var lain = kotor.filter(function (x) { return x !== fm; }).length;
            if (lain && !confirm('Ada ketikan di bagian lain FRM-IT-02 yang belum disimpan dan akan hilang.\nLanjut menyimpan bagian ini saja?')) e.preventDefault();
        });
    });
})();
</script>
@endif
