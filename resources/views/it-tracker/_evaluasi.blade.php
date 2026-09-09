{{--
    FRM-IT-07 — Evaluation & Enhancement (Kelas A, evaluator). Fase 2b.
    Sebelumnya tak punya rumah sama sekali (§14.11); dirancang di ronde 2.

    Evaluator: pemohon (default) / dialihkan Head IT ke Executive/Konsultan.
    Lima aspek skala 1-5, rata-rata terhitung. Temuan Bug/Enhancement.
    Rencana Tindak Lanjut → "Jadikan tiket" — MEKANISME YANG SAMA dengan
    catatan UAT (spawnTicket): tiket lahir di Triase, pemohon sama, induk
    tercatat. Inilah yang menutup lingkaran.

    Butuh: $t, $v, $peran, $bolehEvaluasi, $evOpsi, $wait
--}}
@php
    $F     = $v['fr07'] ?? [];
    $aspek = $F['aspek'] ?? [];
    $tm    = $F['temuan'] ?? [];
    $rtl   = $F['rtl'] ?? [];
    $ed    = $bolehEvaluasi;
    $now   = $t->fase === 'EVALUASI';
    $ASPEK = \App\Services\ItTracker\PhaseEngine::EVAL_ASPEK;
    $nA    = count(array_filter($ASPEK, fn ($a) => ! empty($aspek[$a['k']])));
    $avg   = $nA ? number_format(array_sum(array_map(fn ($a) => (int) ($aspek[$a['k']] ?? 0), $ASPEK)) / $nA, 1) : '—';
    $evNama = $t->evaluator->nama ?? ($t->pemohon->nama ?? 'Head IT');
    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
@endphp

<div class="itk-panel" style="margin-bottom:12px">
    <div class="itk-beban-bar"><b>FRM-IT-07 — EVALUATION &amp; ENHANCEMENT</b>
        <span class="itk-badge-fase">KELAS A · diisi pemohon</span></div>

    <div class="itk-panel-body">
        {{-- evaluator --}}
        <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
            <span class="itk-lbl" style="margin:0">EVALUATOR</span>
            @if ($peran === 'head_it' && $now)
                <form method="post" action="{{ route('it-tracker.evaluator', $t->id) }}" style="display:flex;gap:6px;align-items:center">@csrf
                    <select name="evaluator_user_id" class="itk-dep-sel" style="width:auto;border-style:solid">
                        <option value="">{{ $t->pemohon->nama ?? 'Head IT' }} (pemohon — default)</option>
                        @foreach ($evOpsi as $o)<option value="{{ $o->id }}" {{ $t->evaluator_user_id === $o->id ? 'selected' : '' }}>{{ $o->nama }} · {{ $o->role }}</option>@endforeach
                    </select>
                    <button type="submit" class="itk-btn itk-btn-putih" style="padding:5px 10px;font-size:11px">Alihkan</button>
                </form>
            @else
                <span class="itk-ro-b" style="font-size:12.5px">{{ $evNama }}{{ $t->evaluator_user_id ? '' : ' (pemohon — default)' }}</span>
            @endif
            <span class="itk-fase3-tag">FASE 3 · dijadwalkan otomatis 14 hari pasca Go-Live</span>
            @if ($now)<span style="margin-left:auto;font-size:11px;color:#7A5B43;font-weight:700">{{ $ed ? 'Giliranmu menilai' : 'Menunggu ' . $evNama . ' menilai · ' . $wait['days'] . ' hari' }}</span>@endif
        </div>

        @if ($ed)
            <div class="itk-tanya">
                <div class="k">SETELAH DIPAKAI BEBERAPA MINGGU</div>
                <div class="q">{{ session('name') }}, bagaimana rasanya memakai ini?</div>
                <div class="s">Lima aspek, satu klik masing-masing. Kalau ada yang mengganjal, catat — bisa langsung jadi tiket baru.</div>
            </div>
        @endif

        @if ($ed)<form method="post" action="{{ route('it-tracker.evaluasi', $t->id) }}" id="itkEvForm">@csrf @endif
        {{-- lima aspek --}}
        <div class="itk-tbl">
            @foreach ($ASPEK as $a)
                @php $val = (int) ($aspek[$a['k']] ?? 0); @endphp
                <div class="itk-aspek">
                    <span class="itk-ro-b">{{ $a['t'] }}</span>
                    <span class="itk-skala">
                        @foreach (range(1, 5) as $n)
                            @if ($ed)
                                <label class="itk-skala-btn {{ $val === $n ? 'on' : ($val && $n < $val ? 'lewat' : '') }}"><input type="radio" name="aspek[{{ $a['k'] }}]" value="{{ $n }}" {{ $val === $n ? 'checked' : '' }}>{{ $n }}</label>
                            @else
                                <span class="itk-skala-btn {{ $val === $n ? 'on' : ($val && $n < $val ? 'lewat' : '') }}">{{ $n }}</span>
                            @endif
                        @endforeach
                    </span>
                </div>
            @endforeach
            <div class="itk-tbl-kaki"><span class="num" style="color:#5A3A22;font-weight:800">{{ $nA }}/5 dinilai · rata-rata {{ $avg }}</span> &nbsp; 1 = jauh dari harapan · 5 = melebihi harapan</div>
        </div>

        <div class="itk-grid2">
            {{-- temuan bug / enhancement --}}
            <div>
                <span class="itk-lbl">TEMUAN</span>
                <div style="display:flex;flex-direction:column;gap:6px" id="itkEvTm">
                    @forelse ($tm as $i => $e)
                        <div class="itk-temuan" data-ev-tm>
                            @if ($ed)
                                <select name="temuan[{{ $i }}][jenis]" class="itk-jenis-sel {{ $e['jenis'] }}"><option {{ $e['jenis'] === 'Bug' ? 'selected' : '' }}>Bug</option><option {{ $e['jenis'] === 'Enhancement' ? 'selected' : '' }}>Enhancement</option></select>
                                <input class="itk-cell itk-cell-b" name="temuan[{{ $i }}][txt]" value="{{ $e['txt'] }}" maxlength="500" style="flex:1">
                                <button type="button" class="itk-x" data-ev-del>✕</button>
                            @else
                                <span class="itk-tingkat {{ $e['jenis'] === 'Bug' ? 'Major' : 'Minor' }}" style="width:auto">{{ $e['jenis'] }}</span>
                                <span class="itk-ro itk-ro-b" style="flex:1">{{ $e['txt'] }}</span>
                            @endif
                        </div>
                    @empty
                        @unless ($ed)<span class="itk-hint" style="margin:0">Belum ada temuan.</span>@endunless
                    @endforelse
                    @if ($ed)
                        <div class="itk-temuan itk-tbl-ghost" data-ev-tm data-ghost>
                            <select name="temuan[{{ count($tm) }}][jenis]" class="itk-jenis-sel Bug"><option selected>Bug</option><option>Enhancement</option></select>
                            <input class="itk-cell itk-cell-b" name="temuan[{{ count($tm) }}][txt]" maxlength="500" placeholder="bug atau ide perbaikan… (kosong = diabaikan)" style="flex:1">
                            <span></span>
                        </div>
                    @endif
                </div>
            </div>
            {{-- rencana tindak lanjut → tiket --}}
            <div>
                <span class="itk-lbl">RENCANA TINDAK LANJUT — bisa jadi tiket</span>
                <div style="display:flex;flex-direction:column;gap:6px" id="itkEvRtl">
                    @forelse ($rtl as $i => $x)
                        <div class="itk-tl" data-ev-rtl>
                            @if ($ed && empty($x['ticketId']))
                                <input type="hidden" name="rtl[{{ $i }}][id]" value="{{ $x['id'] }}">
                                <input class="itk-cell itk-cell-b" name="rtl[{{ $i }}][txt]" value="{{ $x['txt'] }}" maxlength="500" style="flex:1">
                                <button type="button" class="itk-x" data-ev-del>✕</button>
                            @else
                                @if ($ed)<input type="hidden" name="rtl[{{ $i }}][id]" value="{{ $x['id'] }}"><input type="hidden" name="rtl[{{ $i }}][txt]" value="{{ $x['txt'] }}">@endif
                                <span class="itk-tl-txt">{{ $x['txt'] }}</span>
                            @endif
                            @if (! empty($x['ticketId']))
                                <span class="itk-tl-done num">→ {{ $x['ticketId'] }}</span>
                            @endif
                        </div>
                    @empty
                        @unless ($ed)<span class="itk-hint" style="margin:0">Tidak ada — modul dianggap tuntas.</span>@endunless
                    @endforelse
                    @if ($ed)
                        <div class="itk-tl itk-tbl-ghost" data-ev-rtl data-ghost>
                            <input class="itk-cell itk-cell-b" name="rtl[{{ count($rtl) }}][txt]" maxlength="500" placeholder="apa yang sebaiknya dikerjakan berikutnya… (kosong = diabaikan)" style="flex:1">
                            <span></span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($ed)
            <button type="submit" class="itk-btn itk-btn-grafit" style="align-self:flex-start;padding:7px 13px;font-size:12px">Simpan evaluasi</button>
            </form>
        @endif

        {{-- "Jadikan tiket" per butir RTL — tim IT, satu rute dengan catatan UAT --}}
        @if ($adalahIt && count(array_filter($rtl, fn ($x) => empty($x['ticketId']))))
            <div class="itk-tbl-kaki" style="border:1px dashed #DCC4A8;border-radius:10px">
                <span class="itk-lbl" style="margin:0 8px 0 0">JADIKAN TIKET:</span>
                @foreach ($rtl as $i => $x)
                    @if (empty($x['ticketId']))
                        <form method="post" action="{{ route('it-tracker.spawn', $t->id) }}" style="display:inline-block;margin:2px 4px 2px 0">@csrf<input type="hidden" name="dari" value="evaluasi"><input type="hidden" name="indeks" value="{{ $i }}">
                            <button type="submit" class="itk-btn itk-btn-grafit" style="padding:4px 9px;font-size:10.5px" title="{{ $x['txt'] }}">{{ $x['id'] }} → tiket</button></form>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="itk-paraf">
            <span class="itk-lbl" style="margin:0">PARAF</span>
            <span class="itk-paraf-it">Evaluator saja — sesudah lima aspek dinilai, tombol <strong>Tutup → Selesai</strong> di "Langkah berikutnya" (kanan) adalah parafnya; IT boleh menutupkan. Tiket yang lahir dari tindak lanjut menutup lingkaran.</span>
        </div>
    </div>
</div>

@if ($ed)
<script>
(function () {
    function setup(sel, prefix) {
        var box = document.getElementById(sel); if (!box) return;
        function renum() { var n = 0; box.querySelectorAll('[data-ev-tm],[data-ev-rtl]').forEach(function (r) { r.querySelectorAll('[name]').forEach(function (f) { f.name = f.name.replace(new RegExp(prefix + '\\[\\d+\\]'), prefix + '[' + n + ']'); }); n++; }); }
        function bind() {
            box.querySelectorAll('[data-ev-del]').forEach(function (b) { b.onclick = function () { b.parentElement.remove(); renum(); }; });
            var g = box.querySelector('[data-ghost]'); if (!g) return;
            var inp = g.querySelector('input.itk-cell-b');
            inp.onkeydown = function (e) {
                if (e.key !== 'Enter') return; e.preventDefault();
                if (!inp.value.trim()) return;
                g.removeAttribute('data-ghost'); g.classList.remove('itk-tbl-ghost');
                g.lastElementChild.outerHTML = '<button type="button" class="itk-x" data-ev-del>✕</button>';
                var ng = g.cloneNode(true); ng.setAttribute('data-ghost', ''); ng.classList.add('itk-tbl-ghost');
                ng.querySelectorAll('input').forEach(function (x) { x.value = ''; });
                ng.lastElementChild.outerHTML = '<span></span>';
                box.appendChild(ng); renum(); bind(); ng.querySelector('input.itk-cell-b').focus();
            };
        }
        bind();
    }
    setup('itkEvTm', 'temuan'); setup('itkEvRtl', 'rtl');
})();
</script>
@endif
