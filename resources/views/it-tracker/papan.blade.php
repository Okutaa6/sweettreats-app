{{--
    IT Request Tracker — Papan Antrean (Fase 1).

    Sumbu papan = FASE (7 tahap templat) dikelompokkan Masuk/Rancang/Kerjakan/
    Rilis; "menunggu siapa" = strip di kartu + filter Macet, BUKAN kolom
    (keputusan design brief §3). Panel beban di atas menjawab pertanyaan
    Executive dalam 5 detik.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="itk-surface">

    <div class="itk-head">
        <h1>IT Request Tracker</h1>
        <span class="itk-badge-zona">ZONA IT</span>
        <div class="itk-head-nav">
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.permintaan') }}">
                {{ in_array($peran, ['head_it','it_dev']) ? 'Catat Inisiatif / Insiden' : 'Permintaanku' }}
            </a>
        </div>
        <div class="itk-head-sub">Dipikul 2 orang IT · 5 cabang · warna strip memanas hanya bila penunggunya di luar IT</div>
    </div>

    @include('layouts.notif')

    {{-- Tautan filter: tiap chip MEMPERTAHANKAN filter lain yang aktif --}}
    @php
        $fAktif = array_filter([
            'q'       => $filter['q'] !== '' ? $filter['q'] : null,
            'macet'   => $filter['macet'] ? 1 : null,
            'sumber'  => $filter['sumber'],
            'jenjang' => $filter['jenjang'],
        ]);
        $fLink = fn (array $ubah) => route('it-tracker.index', array_filter(array_merge($fAktif, $ubah)));
    @endphp

    {{-- Panel Executive: terbaca 5 detik (brief §5b no. 1-3) --}}
    <div class="itk-beban">
        <div class="itk-beban-bar"><b>BEBAN IT SAAT INI</b><span class="itk-badge-fase">FASE 1</span></div>
        <div class="itk-beban-grid">
            <div>
                <span class="itk-big num">{{ $beban['aktif'] }}</span><span class="itk-big-lbl">item aktif · 2 orang IT</span>
                @php $tot = max(1, $beban['aktif']); @endphp
                <div class="itk-splitbar">
                    <div class="perm" style="width:{{ round($beban['permintaan'] / $tot * 100) }}%"></div>
                    <div class="init" style="width:{{ round($beban['inisiatif'] / $tot * 100) }}%"></div>
                </div>
                <div class="itk-split-leg">
                    <span><i style="background:#3A3A36"></i>{{ $beban['permintaan'] }} dari formulir permintaan</span>
                    <span><i style="background:#A8481B"></i>{{ $beban['inisiatif'] }} inisiatif IT sendiri</span>
                </div>
            </div>
            <div>
                <span class="itk-big num" style="color:{{ $beban['macet'] ? '#C0392B' : '#2F8E55' }}">{{ $beban['macet'] }}</span>
                <span class="itk-big-lbl">macet menunggu di luar IT</span>
                <div style="margin-top:12px">
                    <a class="itk-chip {{ $filter['macet'] ? 'aktif' : '' }}"
                       href="{{ $fLink(['macet' => $filter['macet'] ? null : 1]) }}">Lihat yang macet</a>
                </div>
            </div>
            <div>
                {{-- Kalimat konsekuensi — inilah yang mengubah refleks jadi keputusan sadar --}}
                <div class="itk-konsekuensi">Tambah satu permintaan sekarang →
                    <strong>antre di belakang {{ $beban['diTangan'] }}</strong> yang sudah di tangan IT.</div>
            </div>
        </div>
    </div>

    {{-- Filter — bisa digabung; $fLink didefinisikan di atas panel beban --}}
    <div class="itk-filter">
        <form method="get" action="{{ route('it-tracker.index') }}" style="display:inline">
            @foreach ($fAktif as $k => $vv)
                @if ($k !== 'q')<input type="hidden" name="{{ $k }}" value="{{ $vv }}">@endif
            @endforeach
            <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="IT-2026-005 / accurate...">
        </form>
        <a class="itk-chip {{ $filter['sumber'] === 'permintaan' ? 'aktif' : '' }}"
           href="{{ $fLink(['sumber' => $filter['sumber'] === 'permintaan' ? null : 'permintaan']) }}">Permintaan</a>
        <a class="itk-chip {{ $filter['sumber'] === 'inisiatif' ? 'aktif' : '' }}"
           href="{{ $fLink(['sumber' => $filter['sumber'] === 'inisiatif' ? null : 'inisiatif']) }}">Inisiatif IT</a>
        @foreach (['S', 'M', 'L'] as $j)
            <a class="itk-chip {{ $filter['jenjang'] === $j ? 'aktif' : '' }}"
               href="{{ $fLink(['jenjang' => $filter['jenjang'] === $j ? null : $j]) }}">{{ $j }}</a>
        @endforeach
        @if ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
            <a class="itk-chip" href="{{ route('it-tracker.index') }}">bersihkan</a>
        @endif
        @if ($parkir->isNotEmpty())
            <a class="itk-chip" style="margin-left:auto" href="#itk-parkiran">Ditunda / ditolak / selesai ({{ $parkir->count() }}) ▾</a>
        @endif
    </div>

    {{-- Papan --}}
    @php $totalKartu = collect($groups)->flatten(1)->sum(fn ($f) => $f['cards']->count()); @endphp
    @if ($totalKartu === 0)
        <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;padding:44px;text-align:center;margin-bottom:14px">
            <div style="font-size:15px;font-weight:800;color:#5A3A22">
                {{ ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
                    ? 'Tidak ada tiket yang cocok dengan filter ini.'
                    : 'Papan masih kosong.' }}
            </div>
            <div style="font-size:13px;color:#9A7256;margin-top:6px">
                {{ ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
                    ? 'Bersihkan filternya untuk melihat semua tiket aktif.'
                    : 'Permintaan dari Head masuk lewat formulir; kerjaan IT sendiri dicatat lewat tombol "Catat Inisiatif / Insiden" di kanan atas.' }}
            </div>
        </div>
    @endif
    <div class="itk-board">
        @foreach ($groups as $nama => $fases)
            <div class="itk-kolom">
                <div class="itk-kolom-hd"><b>{{ $nama }}</b>
                    <span class="n num">{{ collect($fases)->sum(fn ($f) => $f['cards']->count()) }}</span></div>
                @foreach ($fases as $f)
                    <div class="itk-fase-hd {{ $f['cards']->isEmpty() ? 'kosong' : '' }}">
                        {{ $f['label'] }} <span class="n num">{{ $f['cards']->count() }}</span></div>
                    @foreach ($f['cards'] as $c)
                        @php $t = $c['t']; $w = $c['wait']; @endphp
                        {{-- div ber-onclick, bukan <a>: kartu memuat form/tombol aksi
                             dan form di dalam anchor itu HTML tidak valid --}}
                        <div class="itk-card" style="cursor:pointer"
                             onclick="window.location='{{ route('it-tracker.show', $t->id) }}'">
                            <div class="itk-card-top">
                                <span class="itk-no num">{{ $t->nomor }}</span>
                                <span class="itk-src {{ $t->sumber }}">
                                    @if ($t->sumber === 'inisiatif') INISIATIF IT
                                    @elseif ($t->sumber === 'insiden') INSIDEN
                                    @else {{ strtoupper($t->pemohon->nama ?? 'PERMINTAAN') }}
                                    @endif
                                </span>
                                <span class="itk-jenjang {{ $t->jenjang ?? '' }}">{{ $t->jenjang ?? '?' }}</span>
                            </div>
                            <div class="itk-card-title">{{ $t->judul }}</div>
                            <div class="itk-card-meta">
                                <span class="itk-prog num">{{ $c['prog']['label'] ?? 'Menunggu triase' }}</span>
                                @if ($t->prioritas_it)<span class="itk-prio {{ $t->prioritas_it }}">{{ $t->prioritas_it }}</span>@endif
                                @if ($c['anak'])<span class="itk-anak">{{ $c['anak'] }} fitur di dalam</span>@endif
                                {{-- Ronde 2: ketergantungan yang belum selesai & tiket asal --}}
                                @if ($c['deps'])<span class="itk-dep-chip num" title="menunggu tiket lain"><i class="fa fa-link" aria-hidden="true"></i> menunggu {{ implode(', ', $c['deps']) }}</span>@endif
                                @if ($c['parent'])<span class="itk-parent-chip num" title="lahir dari {{ $c['parent']['id'] }} · {{ $c['parent']['via'] }}">↳ dari {{ $c['parent']['id'] }}</span>@endif
                            </div>
                            <div class="itk-wait {{ \App\Services\ItTracker\PhaseEngine::waitCls($w, $t->fase) }}">
                                <i></i>
                                <span class="num">
                                    @if ($t->fase === 'ANTRE') Antre di belakang {{ $c['antre'] }} item
                                    @else {{ $w['label'] }} · {{ $w['days'] }} hr
                                    @endif
                                </span>
                            </div>
                            @if ($c['tombol'])
                                @if ($c['tombol']['jenis'] === 'triase')
                                    <button type="button" class="itk-btn itk-btn-grafit itk-card-btn"
                                        onclick="event.stopPropagation(); itkBukaTriase('{{ $t->id }}', '{{ $t->nomor }}', @js($t->judul), @js($t->prioritas_pemohon))">
                                        {{ $c['tombol']['label'] }}</button>
                                @else
                                    <form method="post" action="{{ route('it-tracker.maju', $t->id) }}"
                                          onclick="event.stopPropagation()">
                                        @csrf
                                        <button type="submit" class="itk-btn itk-btn-grafit itk-card-btn">{{ $c['tombol']['label'] }}</button>
                                    </form>
                                @endif
                            @elseif ($c['blokir'])
                                <div class="itk-blokir">{{ $c['blokir'] }}</div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Parkiran: ditunda tetap tampil dengan alasannya — ditunda bukan berarti hilang --}}
    @if ($parkir->isNotEmpty())
        <div id="itk-parkiran" style="margin-top:16px">
            <div class="itk-fase-hd" style="margin-bottom:8px"><b>PARKIRAN — DITUNDA · DITOLAK · SELESAI</b></div>
            <div class="itk-parkir">
                @foreach ($parkir as $p)
                    <a class="itk-parkir-card" href="{{ route('it-tracker.show', $p->id) }}">
                        <div class="itk-card-top">
                            <span class="itk-no num">{{ $p->nomor }}</span>
                            <span class="itk-st {{ $p->fase }}">{{ $p->fase }}</span>
                        </div>
                        <div class="itk-card-title" style="color:#5A3A22">{{ $p->judul }}</div>
                        @if ($p->alasan_parkir)
                            <div style="font-size:12px;color:#7A5B43;margin-top:5px;line-height:1.45">{{ $p->alasan_parkir }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="itk-legenda">Legenda badge: <strong>Fase 1</strong> = formulir masuk + triase + papan ini ·
        <strong>Fase 2</strong> = tujuh fase + gerbang antar-fase · <strong>Fase 3</strong> = sambungan Project
        Tracker + evaluasi terjadwal. Warna strip menunggu memanas hanya bila penunggunya
        <strong>di luar IT</strong> (≥3 hari kuning, ≥7 merah).</div>
</div>

{{-- Modal triase (Head IT) — jenjang menentukan fase yang dilewati --}}
@if ($peran === 'head_it')
<div class="itk-modal-scrim" id="itkModalTriase" onclick="if (event.target === this) itkTutupTriase()">
    <div class="itk-modal">
        <div class="itk-modal-hd"><b>TRIASE</b><span class="itk-badge-fase">FASE 1</span>
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
                    <label><input class="itk-radio" type="radio" name="jenjang" value="S" required><span><b>S</b> · bug/tweak — 2 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="M"><span><b>M</b> · fitur — 5 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="L"><span><b>L</b> · modul — 7 fase</span></label>
                </div>
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
                <button type="submit" class="itk-btn itk-btn-grafit" style="width:100%">Simpan triase → Antre</button>
            </form>
            <div class="itk-sekat"></div>
            <form method="post" id="itkParkirForm">
                @csrf
                <label class="itk-lbl">ATAU TUNDA / TOLAK — ALASANNYA WAJIB</label>
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
@endif
@endsection
