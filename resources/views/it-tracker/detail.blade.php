{{--
    IT Request Tracker — Detail Tiket · rupa RONDE 3a (mockup r3, "samain aja dulu").

    Kepala tiket (avatar · nomor · sumber · jenjang · kotak menunggu) + rel fase
    (dengan simpul "Selesai"), lalu SATU kolom akordeon per fase di jalur tiket:
    fase lampau = satu baris ringkasan, fase berjalan = kartu penuh berisi
    formulirnya + GERBANG (lampu + tombol yang bilang kenapa) di DALAM kartu,
    fase mendatang = baris putus-putus "belum sampai · nanti diisi X".
    Rail kanan: langkah berikutnya, tentang tiket, riwayat, sambungan Fase 3.

    Partial FRM (_analisis/_uji/_uat/_golive/_evaluasi) dipakai apa adanya —
    kulit panelnya dilepas CSS (.itk-acc-body > .itk-panel), isinya tetap.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    use App\Services\ItTracker\PhaseEngine;
    use App\Services\ItTracker\ItTrackerService;

    $adalahIt  = in_array($peran, ['head_it', 'it_dev'], true);
    $labelFase = ['TRIASE' => 'Triase', 'ANTRE' => 'Antre', 'SELESAI' => 'Selesai', 'DITUNDA' => 'Ditunda', 'DITOLAK' => 'Ditolak'];
    $dept      = $t->pemohon?->group?->nama;
    $srcTxt    = $t->sumber === 'inisiatif' ? 'INISIATIF IT' : ($t->sumber === 'insiden' ? 'INSIDEN'
                 : mb_strtoupper(($t->pemohon->nama ?? 'PERMINTAAN') . ($dept ? ' · ' . $dept : '')));
    $parkir    = in_array($t->fase, ['DITUNDA', 'DITOLAK'], true);
    $selesai   = $t->fase === 'SELESAI';
    $aktif     = isset(PhaseEngine::RANK[$t->fase]);
    $kini      = PhaseEngine::phaseOf($t->fase);
    $path      = $t->jenjang ? $jalur : ['REQ'];
    $kiniIdx   = array_search($kini, $path, true);
    $rankKini  = PhaseEngine::rankOf($t->fase);
    $fmtD      = fn ($d) => $d ? \Carbon\Carbon::parse($d)->translatedFormat('j M') : '';
    $fmtDT     = fn ($d) => $d ? \Carbon\Carbon::parse($d)->translatedFormat('j M H:i') : '';

    // keadaan tiap fase di jalur: done / now / todo
    $stateOf = function (string $k) use ($path, $kiniIdx, $selesai, $parkir) {
        $i = array_search($k, $path, true);
        if ($selesai) return 'done';
        if ($parkir)  return $i <= 0 ? 'done' : 'todo';
        if ($kiniIdx === false) return 'todo';
        return $i < $kiniIdx ? 'done' : ($i === $kiniIdx ? 'now' : 'todo');
    };
    $META = [
        'REQ'      => [1, 'Requirement',              'FRM-IT-01', 'A', 'Kelas A · pemohon'],
        'ANALISIS' => [2, 'Analysis & Design',        'FRM-IT-02', 'C', 'Kelas C · dipikirkan'],
        'DEV'      => [3, 'Development Record',       'FRM-IT-03', 'B', 'Kelas B · terisi sendiri'],
        'UJI'      => [4, 'Internal Testing',         'FRM-IT-04', 'B', 'Kelas B · terisi sendiri'],
        'UAT'      => [5, 'UAT',                      'FRM-IT-05', 'A', 'Kelas A · pemohon'],
        'GOLIVE'   => [6, 'Go Live Checklist',        'FRM-IT-06', 'B', 'Kelas B · terisi sendiri'],
        'EVALUASI' => [7, 'Evaluation & Enhancement', 'FRM-IT-07', 'A', 'Kelas A · evaluator'],
    ];
    $namaPemohon = $t->pemohon->nama ?? 'pemohon';

    // ringkasan satu baris fase lampau — "selesai 22 Agu · diparaf Robi · 4 FR"
    $ringkas = function (string $k, string $st) use ($t, $v, $fmtD, $namaPemohon) {
        if ($st === 'todo') {
            $who = ['ANALISIS' => 'Head IT + pemohon', 'DEV' => 'IT', 'UJI' => 'IT', 'UAT' => $namaPemohon,
                    'GOLIVE' => 'IT + Business Owner', 'EVALUASI' => $t->evaluator->nama ?? $namaPemohon][$k] ?? null;
            return 'belum sampai' . ($who ? ' · nanti diisi ' . $who : '');
        }
        switch ($k) {
            case 'REQ':
                return ($t->pemohon ? 'diajukan ' . $t->pemohon->nama : ($t->sumber === 'insiden' ? 'insiden' : 'inisiatif IT'))
                    . ($t->created_at ? ' · ' . $fmtD($t->created_at) : '')
                    . ($t->jenjang ? ' · triase: jenjang ' . $t->jenjang . ', ' . ($t->prioritas_it ?? '') : '');
            case 'ANALISIS':
                $F = $v['fr02'] ?? []; $n = count($F['fr'] ?? []);
                return ($n ? $n . ' FR' : 'tanpa FR')
                    . (! empty($F['komponen']) ? ' · ' . implode(', ', $F['komponen']) : '')
                    . (! empty($F['paraf']) ? ' · diparaf ' . $F['paraf']['oleh'] . ' ' . $fmtD($F['paraf']['at']) : '')
                    . (! empty($F['diteruskan']) ? ' · diteruskan tanpa paraf BO' : '');
            case 'DEV':
                $br = $t->gitRefs->pluck('branch')->filter()->unique()->values();
                return ($br->isNotEmpty() ? $br->implode(', ') : 'tanpa branch')
                    . ($t->gitRefs->count() ? ' · ' . $t->gitRefs->count() . ' commit' : '')
                    . (! empty($v['fr03']['confirmed']) ? ' · dikonfirmasi ' . ($t->pic->nama ?? 'IT') : '');
            case 'UJI':
                $tc = $v['fr04']['tc'] ?? []; $tm = $v['fr04']['temuan'] ?? [];
                $p = count(array_filter($tc, fn ($x) => ($x['status'] ?? null) === 'pass'));
                $buka = count(array_filter($tm, fn ($x) => ($x['status'] ?? '') !== 'Selesai'));
                return $p . '/' . count($tc) . ' TC pass · ' . $buka . ' temuan terbuka';
            case 'UAT':
                $F = $v['fr05'] ?? []; $kep = $F['keputusan'] ?? null;
                if ($kep === 'catatan')   return 'disetujui dengan catatan · ' . ($F['oleh'] ?? '') . ' ' . $fmtD($F['tanggal'] ?? null);
                if ($kep === 'setuju')    return 'disetujui ' . ($F['oleh'] ?? '') . ' ' . $fmtD($F['tanggal'] ?? null);
                if ($kep === 'perbaikan') return 'perlu perbaikan · ' . ($F['oleh'] ?? '') . ' ' . $fmtD($F['tanggal'] ?? null);
                return 'belum diputuskan';
            case 'GOLIVE':
                $F = $v['fr06'] ?? [];
                $pass = count(array_filter($F['pasca'] ?? [], fn ($x) => $x === 'pass'));
                return (! empty($F['bo']) ? 'ACC Business Owner' : 'tanpa ACC BO') . ' · pasca ' . $pass . '/' . count(PhaseEngine::PASCA) . ' pass';
            case 'EVALUASI':
                $F = $v['fr07'] ?? [];
                $n = count(array_filter(PhaseEngine::EVAL_ASPEK, fn ($a) => ! empty(($F['aspek'] ?? [])[$a['k']])));
                $lahir = count(array_filter($F['rtl'] ?? [], fn ($x) => ! empty($x['ticketId'])));
                return $n . '/5 aspek · ' . $lahir . ' tiket lahir';
        }
        return '';
    };

    // ── gerbang: teks tombol yang bilang KENAPA (port gateVM) ──
    $gAda = $gate && ($gate['kind'] ?? '') === 'advance' && $aktif && $t->fase !== 'TRIASE';
    $gNext = $kiniIdx === false ? null : ($path[$kiniIdx + 1] ?? 'SELESAI');
    $gAktor = $gate['actor'] ?? 'it';
    $gBtnTxt = ''; $gBtnMati = true; $gBtnForm = false; $gBtnHijau = false;
    if ($gAda) {
        if (empty($gate['ok'])) {
            $gBtnTxt = 'Masih perlu: ' . ($gate['missing'][0] ?? 'syarat gerbang');
        } elseif ($gAktor !== 'it' && ! ($t->fase === 'EVALUASI' && $bolehEvaluasi) && ! ($t->fase === 'UAT' && $bolehJawabUat)) {
            $gBtnTxt = 'Menunggu persetujuan ' . ($t->fase === 'UAT' ? $namaPemohon : ($t->evaluator->nama ?? $namaPemohon)) . ' — bukan tugas IT';
        } elseif ($t->fase === 'UAT' && $bolehJawabUat) {
            $gBtnTxt = 'Putuskan lewat tiga tombol di kartu UAT — itulah parafmu';
        } elseif ($t->fase === 'EVALUASI' && $bolehEvaluasi && ! $adalahIt) {
            $gBtnTxt = $gate['label']; $gBtnMati = false; $gBtnForm = true; $gBtnHijau = true;
        } elseif (! $bolehGerak['ok']) {
            $gBtnTxt = $adalahIt ? $bolehGerak['why'] : 'Aksi IT — kamu hanya memantau';
        } else {
            $gBtnTxt = $gate['label']; $gBtnMati = false; $gBtnForm = true; $gBtnHijau = true;
        }
    }
    $lampu = [];
    if ($gAda) {
        if (! empty($gate['checks']))  foreach ($gate['checks'] as $c)  $lampu[] = ['t' => $c['t'], 'on' => (bool) $c['ok']];
        elseif (! empty($gate['ok']))  $lampu[] = ['t' => 'syarat terpenuhi', 'on' => true];
        else                           foreach ($gate['missing'] as $m) $lampu[] = ['t' => $m, 'on' => false];
    }

    // avatar
    $avaBg  = $t->sumber === 'inisiatif' ? 'linear-gradient(135deg,#3A3A36,#242422)' : ($t->sumber === 'insiden' ? '#8E2419' : '#A8481B');
    $avaTxt = $t->sumber === 'inisiatif' ? 'IT' : ($t->sumber === 'insiden' ? '!' : mb_strtoupper(mb_substr($namaPemohon, 0, 1)));

    // kotak kanan kepala
    $boxCls = PhaseEngine::waitCls($wait, $t->fase);
    if ($selesai)                 { $box = ['lab' => 'SELESAI', 'big' => $fmtD($t->fase_since), 'sub' => 'tiket ditutup', 'st' => 'background:#E3F4E8;border:1px solid #CBE3D2;color:#2F8E55']; }
    elseif ($t->fase === 'DITUNDA') { $box = ['lab' => 'DITUNDA', 'big' => $fmtD($t->fase_since), 'sub' => 'tetap tercatat', 'st' => 'background:#FBEFD9;border:1px solid #E8C99A;color:#B5701A']; }
    elseif ($t->fase === 'DITOLAK') { $box = ['lab' => 'DITOLAK', 'big' => $fmtD($t->fase_since), 'sub' => '', 'st' => 'background:#FBE3DE;border:1px solid #F0C1B8;color:#C0392B']; }
    elseif ($t->fase === 'ANTRE')   { $box = ['lab' => 'POSISI ANTREAN', 'big' => '#' . ($antre + 1), 'sub' => 'di belakang ' . $antre . ' item', 'st' => '']; }
    else                            { $box = ['lab' => 'MENUNGGU', 'big' => $wait['days'] . ' hr', 'sub' => ($wait['label'] ?: 'IT') . ' · di fase ' . ($labelFase[$t->fase] ?? PhaseEngine::phShort($kini)), 'st' => '']; }

    // "sudah sampai mana"
    $km = $t->kemajuan; $kmHari = $t->kemajuan_at ? (int) $t->kemajuan_at->diffInDays(now()) : null; $kmBasi = $km && $kmHari !== null && $kmHari >= 7;
    $kmOleh = $picIt->firstWhere('id', $t->kemajuan_oleh)->nama ?? 'IT';
    $inisial = mb_strtoupper(mb_substr((string) session('name'), 0, 1)) ?: 'IT';
    $kembali = $peran === 'head' ? route('it-tracker.permintaan') : route('it-tracker.index');
@endphp
<div class="itk-surface itk-detail">

    <div class="itk-topbar">
        <a class="itk-crumb" href="{{ $kembali }}">‹ {{ $peran === 'head' ? 'Permintaan Saya' : 'Papan Antrean' }}</a>
        <span class="itk-badge-zona">ZONA IT</span>
        <span class="sub num">{{ $t->nomor }}</span>
        <div class="itk-topbar-nav">
            @if ($peran !== 'head')<a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.index') }}">Papan Antrean</a>@endif
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.permintaan') }}">{{ $adalahIt ? '+ Inisiatif IT' : 'Permintaan Saya' }}</a>
            <div class="itk-avatar" title="{{ session('name') }} · {{ session('group_name') }}">{{ $inisial }}</div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ═══ Kartu kepala tiket + rel fase ═══ --}}
    <div class="itk-panel {{ $t->unit === 'rblas' ? 'itk-rblas' : '' }}" style="margin-bottom:14px;{{ $t->unit === 'rblas' ? 'border-left:4px solid #D9772B' : '' }}">
        <div class="itk-detail-head">
            <div class="itk-ava" style="background:{{ $avaBg }}">{{ $avaTxt }}</div>
            <div style="flex:1;min-width:0">
                <div class="itk-card-top" style="flex-wrap:wrap;gap:8px">
                    <span class="itk-no num" style="font-size:12.5px">{{ $t->nomor }}</span>
                    <span class="itk-src {{ $t->sumber }}" style="font-size:9.5px;padding:2px 7px">{{ $srcTxt }}</span>
                    <span class="itk-jen-chip {{ $t->jenjang ?: 'baru' }}">{{ $t->jenjang ? 'Jenjang ' . $t->jenjang . ($t->bobot ? ' · bobot ' . $t->bobot : '') : 'belum ditriase' }}</span>
                </div>
                <div class="itk-detail-title">{{ $t->judul }}</div>
                <div class="itk-detail-meta">
                    <span><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.4" r="2.7" stroke="#A9866A" stroke-width="1.5"/><path d="M2.8 13.5c.8-2.5 2.8-3.8 5.2-3.8s4.4 1.3 5.2 3.8" stroke="#A9866A" stroke-width="1.5" stroke-linecap="round"/></svg>
                        {{ $t->pemohon ? 'Pemohon: ' . $t->pemohon->nama . ($dept ? ' · ' . $dept : '') : ($t->sumber === 'insiden' ? 'Insiden — tanpa pemohon' : 'Inisiatif IT — tanpa pemohon') }}</span>
                    <span><svg width="14" height="14" viewBox="0 0 17 17" fill="none"><rect x="2" y="3" width="13" height="12" rx="2.5" stroke="#A9866A" stroke-width="1.5"/><path d="M2 6.6h13M5.6 1.6v2.8M11.4 1.6v2.8" stroke="#A9866A" stroke-width="1.5" stroke-linecap="round"/></svg>
                        Masuk fase ini {{ $t->fase_since->translatedFormat('j M Y H:i') }}</span>
                    <span><i class="itk-unit-dot {{ $t->unit }}"></i>{{ ItTrackerService::unitLabel($t->unit) }}</span>
                    @if ($t->pic)<span>PIC: {{ $t->pic->nama }}</span>@endif
                </div>
                @if (! empty($v['fr02']['diteruskan']))
                    @php $lw = $v['fr02']['diteruskan']; @endphp
                    {{-- Penanda pelewatan paraf (§14.16): garis putus merah, di KEPALA supaya ikut sampai Go Live --}}
                    <div class="itk-pintas itk-lewat-banner"><b>TANPA PARAF BO</b>
                        <span>Gerbang Analisis diteruskan {{ $lw['oleh'] }} (Head IT) · {{ $fmtDT($lw['at']) }} — “{{ $lw['alasan'] }}”
                            @if (! empty($v['fr02']['paraf'])) <em class="itk-lewat-susul">BO memparaf menyusul {{ $fmtDT($v['fr02']['paraf']['at']) }}</em>@endif</span>
                    </div>
                @endif
                @if (! empty($v['parent']))
                    <div class="itk-parent">Lahir dari
                        <a class="num" href="{{ $t->lahirDari ? route('it-tracker.show', $t->lahirDari->id) : '#' }}">{{ $v['parent']['id'] }}</a>
                        · {{ $v['parent']['via'] }} — lingkaran tertutup</div>
                @endif
                @if (count($v['children']))
                    <div style="display:flex;align-items:center;gap:7px;margin-top:9px;flex-wrap:wrap">
                        <span style="font-size:11px;font-weight:800;color:#3F6BAF">{{ count($v['children']) }} fitur di dalam modul ini:</span>
                        @foreach ($v['children'] as $anak)<span class="itk-anak">{{ $anak }}</span>@endforeach
                    </div>
                @endif
                @if ($parkir)
                    <div class="itk-banner {{ $t->fase }}">
                        {{ $t->alasan_parkir }}{{ $t->fase === 'DITUNDA' ? ' — tidak sedang dikerjakan, tetap tercatat.' : '' }}
                        @if ($t->fase === 'DITUNDA' && $peran === 'head_it')
                            <form method="post" action="{{ route('it-tracker.buka', $t->id) }}" style="margin-top:8px">
                                @csrf
                                <button type="submit" class="itk-bukalagi" style="margin:0">Buka lagi → {{ $t->jenjang ? 'Antre' : 'Triase' }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            <div class="itk-waitbox {{ $box['st'] ? '' : 'itk-wait ' . $boxCls }}" style="display:block;{{ $box['st'] }}">
                <div class="lab">{{ $box['lab'] }}</div>
                <div class="big num">{{ $box['big'] }}</div>
                <div class="sub">{{ $box['sub'] }}</div>
            </div>
        </div>
        @if ($t->jenjang)
            {{-- Rel kemajuan fase — simpul terakhir "Selesai"; klik simpul membuka fasenya --}}
            @php $nodes = array_merge(array_map(fn ($k) => ['k' => $k, 'label' => PhaseEngine::phShort($k)], $path), [['k' => 'DONE', 'label' => 'Selesai']]); @endphp
            <div class="itk-rel">
                @foreach ($nodes as $i => $nd)
                    @php
                        $stx = $selesai ? 'lewat' : ($parkir || $nd['k'] === 'DONE' ? '' : ($kiniIdx !== false && $i < $kiniIdx ? 'lewat' : ($i === $kiniIdx ? 'kini' : '')));
                    @endphp
                    <div class="itk-step {{ $stx }} {{ $nd['k'] === 'DONE' ? 'selesai-node' : '' }}" @if ($nd['k'] !== 'DONE') data-rel="{{ $nd['k'] }}" title="Buka {{ $nd['label'] }}" @endif>
                        <div class="garis"></div>
                        <div class="dot">{{ $stx === 'lewat' ? '✓' : $i + 1 }}</div>
                        <span class="lbl">{{ $nd['label'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="itk-detail-grid">
        <div class="itk-detail-kiri">
            {{-- ❝ "Sudah sampai mana" — satu kalimat dari IT + siapa + kapan; basi ≥7 hari --}}
            @if ($km || ($bolehKemajuan ?? false))
                <div class="itk-km {{ $kmBasi ? 'basi' : ($km ? 'ada' : 'kosong') }}">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M3 9.5C3 6.5 4.6 4.4 7.3 3.3l.6 1.2C6.3 5.3 5.5 6.3 5.3 7.6c1.2 0 2.1.9 2.1 2.1 0 1.2-1 2.1-2.2 2.1S3 10.9 3 9.5Zm6 0c0-3 1.6-5.1 4.3-6.2l.6 1.2c-1.6.8-2.4 1.8-2.6 3.1 1.2 0 2.1.9 2.1 2.1 0 1.2-1 2.1-2.2 2.1S9 10.9 9 9.5Z"/></svg>
                    <div style="flex:1;min-width:0">
                        <div class="itk-km-hd">
                            <b>SUDAH SAMPAI MANA</b>
                            <span class="meta num">
                                @if ($km) {{ $kmOleh }} · {{ $t->kemajuan_at?->translatedFormat('j M H:i') }}{{ $kmHari === null ? '' : ($kmBasi ? ' · ' . $kmHari . ' hari lalu' : ($kmHari ? ' · ' . $kmHari . ' hr' : ' · hari ini')) }}
                                @else belum ada catatan — Executive hanya melihat strip menunggu
                                @endif
                            </span>
                            @if ($kmBasi)<span class="itk-km-basi">BASI · perbarui atau hapus</span>@endif
                        </div>
                        @if ($bolehKemajuan ?? false)
                            <form method="post" action="{{ route('it-tracker.kemajuan', $t->id) }}" id="itkKmForm">
                                @csrf
                                <input name="kemajuan" maxlength="300" value="{{ $km }}" placeholder="satu kalimat: sudah sampai mana? Enter untuk simpan">
                                <button type="submit" class="simpan">Simpan</button>
                                @if ($km)<button type="button" class="hapus" title="Hapus catatan" onclick="var f=document.getElementById('itkKmForm');f.kemajuan.value='';f.submit()">Hapus</button>@endif
                            </form>
                        @elseif ($km)
                            <div class="itk-km-txt">{{ $km }}</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ═══ Akordeon fase ═══ --}}
            @foreach ($path as $k)
                @php
                    $st = $stateOf($k); [$num, $judul, $frm, $cls, $clsTxt] = $META[$k];
                    $buka = $st === 'now';
                    $isiAda = $st !== 'todo' || ($k === 'UAT' && $rankKini >= 3);
                @endphp
                <div class="itk-acc {{ $st }} {{ $buka ? 'buka' : '' }}" id="sec-{{ $k }}" data-acc>
                    <div class="itk-acc-hd" data-acc-hd>
                        <span class="itk-acc-dot">{{ $st === 'done' ? '✓' : $num }}</span>
                        <span class="itk-acc-title">{{ $judul }}</span>
                        @if ($st === 'now')
                            <span class="itk-acc-frm">{{ $frm }}</span>
                            <span class="itk-acc-cls {{ $cls }}">{{ $clsTxt }}</span>
                            <span class="itk-acc-fase {{ $frm === 'FRM-IT-01' ? 'f1' : 'f2' }}">{{ $frm === 'FRM-IT-01' ? 'FASE 1' : 'FASE 2' }}</span>
                        @else
                            <span class="itk-acc-sum num">{{ $ringkas($k, $st) }}</span>
                        @endif
                        <span class="itk-acc-st">{{ $st === 'done' ? 'Selesai' : ($st === 'now' ? 'Berjalan' : 'Menunggu giliran') }}</span>
                        <span class="itk-acc-caret">▾</span>
                    </div>
                    <div class="itk-acc-body">
                        @if ($k === 'REQ')
                            <div class="itk-rows">
                                @if ($form01)
                                    @if ($t->pemohon)
                                        <div class="itk-row"><b>PEMOHON</b><span>{{ $t->pemohon->nama }}{{ $dept ? ' · ' . $dept : '' }}{{ $t->branch ? ' · ' . $t->branch->name : '' }} <em class="itk-auto-tag">otomatis</em></span></div>
                                    @endif
                                    <div class="itk-row"><b>JENIS PERMINTAAN</b><span>{{ $form01['jenis'] ?? '—' }}</span></div>
                                    <div class="itk-row"><b>MASALAH HARI INI</b><span>{{ $form01['latar'] ?? '—' }}</span></div>
                                    <div class="itk-row"><b>YANG DIHARAPKAN</b><span>{{ $form01['kebutuhan'] ?? '—' }}</span></div>
                                    <div class="itk-row"><b>TERDAMPAK</b><span>{{ $form01['terdampak'] ?? '—' }} · target: {{ $form01['target'] ?? '—' }}</span></div>
                                    <div class="itk-row"><b>REVIEW AWAL IT</b><span>{{ $t->fase === 'TRIASE' ? 'Belum — menunggu triase' : 'Diterima untuk dianalisis' }}</span></div>
                                @elseif ($t->sumber === 'inisiatif')
                                    <div class="itk-row"><b>ASAL</b><span>Inisiatif IT — tanpa pemohon. Kerjaan IT sendiri wajib bertiket supaya beban terlihat.</span></div>
                                @elseif ($t->sumber === 'insiden')
                                    <div class="itk-row"><b>ASAL</b><span>Insiden / hotfix — masuk langsung ke Development, formulir diisi menyusul.</span></div>
                                @else
                                    <div class="itk-row"><b>ASAL</b><span>Belum ada isi formulir untuk tiket ini.</span></div>
                                @endif
                                @if ($form01 || $bolehLampiran || ! empty($form01['lampiran']))
                                    @include('it-tracker._lampiran')
                                @endif
                            </div>
                            @if ($t->fase === 'TRIASE')
                                <div class="itk-gerbang">
                                    <div class="itk-gerbang-hd"><b>GERBANG KE ANTRE</b><span class="itk-acc-fase f2">FASE 1</span></div>
                                    <ul class="itk-missing"><li>jenjang + bobot + prioritas IT terisi (lewat triase)</li></ul>
                                    @if ($peran === 'head_it')
                                        <button type="button" class="itk-gerbang-btn"
                                            onclick="itkBukaTriase('{{ $t->id }}', '{{ $t->nomor }}', @js($t->judul), @js($t->prioritas_pemohon))">Triase — tetapkan jenjang &amp; prioritas</button>
                                    @else
                                        <span class="itk-gerbang-btn mati">Hanya Head IT yang menetapkan triase</span>
                                    @endif
                                </div>
                            @endif
                        @elseif ($k === 'ANALISIS')
                            @if ($isiAda) @include('it-tracker._analisis') @else <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div> @endif
                        @elseif ($k === 'DEV')
                            @if ($t->jenjang === 'S')
                                @if ($st === 'todo')
                                    <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div>
                                @else
                                    <span class="itk-lbl">CATATAN SINGKAT PENGERJAAN (JENJANG S) — pengganti formulir penuh</span>
                                    @if ($bolehGerak['ok'] && $t->fase === 'DEV')
                                        <form method="post" action="{{ route('it-tracker.catatan', $t->id) }}">
                                            @csrf
                                            <textarea name="catatan" rows="2" required class="itk-in"
                                                placeholder="apa yang diubah + commit — ini pengganti formulir penuh">{{ $form03['note'] ?? '' }}</textarea>
                                            <button type="submit" class="itk-btn itk-btn-putih" style="margin-top:8px">Simpan catatan</button>
                                        </form>
                                    @else
                                        <div style="font-size:13px;line-height:1.5">{{ $form03['note'] ?? '—' }}</div>
                                    @endif
                                @endif
                            @elseif ($st === 'todo')
                                <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div>
                            @else
                                @php $sudahKonfirmasi = (bool) optional($t->forms->firstWhere('kode', 'FRM-IT-03'))->dikonfirmasi_at; @endphp
                                {{-- Kelas B: TERISI SENDIRI dari git. IT tinggal mengonfirmasi — kalau ini jadi kotak kosong untuk diketik ulang, sistemnya mati. --}}
                                <div class="itk-auto-note">Terisi sendiri dari git lewat nomor tiket di commit message — IT tinggal mengonfirmasi, bukan mengetik ulang.</div>
                                <div class="itk-git">
                                    <div class="itk-git-lbl">DARI GIT — DILACAK LEWAT {{ $t->nomor }} DI COMMIT MESSAGE</div>
                                    @forelse ($t->gitRefs->groupBy('branch') as $branch => $refs)
                                        <div class="itk-git-branch">{{ $branch ?: '(tanpa branch)' }}</div>
                                        @foreach ($refs as $r)
                                            <div class="itk-git-commit">{{ substr($r->commit_sha, 0, 7) }} — {{ $r->subject }}</div>
                                            @foreach ($r->scriptDisebut() as $sq)<div class="itk-git-script">{{ $sq }}</div>@endforeach
                                        @endforeach
                                    @empty
                                        <div class="itk-git-kosong">Belum ada commit yang menyebut {{ $t->nomor }}. Tulis nomornya di baris pertama commit message, lalu segarkan.</div>
                                    @endforelse
                                </div>
                                @if ($bolehGerak['ok'])
                                    <form method="post" action="{{ route('it-tracker.syncgit', $t->id) }}">
                                        @csrf
                                        <button type="submit" class="itk-btn itk-btn-putih" style="font-size:11.5px;padding:6px 12px">Segarkan jejak git</button>
                                        <span class="itk-hint" style="margin:0 0 0 6px">Cron menariknya tiap 06:40; tombol ini untuk yang tak mau menunggu.</span>
                                    </form>
                                @endif
                                @if ($sudahKonfirmasi)
                                    <div style="font-size:11.5px;font-weight:800;color:#2F8E55;background:#E3F4E8;border-radius:8px;padding:5px 10px;align-self:flex-start">✓ Dikonfirmasi — tanpa paraf pihak lain (FRM-IT-03/04 memang begitu)</div>
                                    @if (! empty($form03['note']))<div style="font-size:13px;line-height:1.5">{{ $form03['note'] }}</div>@endif
                                @elseif ($bolehGerak['ok'] && $t->fase === 'DEV')
                                    <form method="post" action="{{ route('it-tracker.konfirmasi', $t->id) }}">
                                        @csrf
                                        <textarea name="catatan" rows="2" class="itk-in" placeholder="opsional: keterangan tambahan — branch & commit sudah diambil sendiri di atas">{{ $form03['note'] ?? '' }}</textarea>
                                        <button type="submit" class="itk-btn itk-btn-grafit" style="margin-top:8px">Benar, ini yang kukerjakan — konfirmasi</button>
                                    </form>
                                @elseif ($t->fase === 'DEV')
                                    <div class="itk-hint" style="margin:0">Menunggu konfirmasi pengerjanya ({{ $t->pic->nama ?? 'IT' }}).</div>
                                @endif
                            @endif
                        @elseif ($k === 'UJI')
                            @if ($isiAda) @include('it-tracker._uji') @else <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div> @endif
                        @elseif ($k === 'UAT')
                            @if ($isiAda) @include('it-tracker._uat') @else <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div> @endif
                        @elseif ($k === 'GOLIVE')
                            @if ($isiAda) @include('it-tracker._golive') @else <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div> @endif
                        @elseif ($k === 'EVALUASI')
                            @if ($isiAda) @include('it-tracker._evaluasi') @else <div class="itk-acc-todo-note">{{ $ringkas($k, 'todo') }}.</div> @endif
                        @endif

                        {{-- Gerbang: lampu status + tombol yang bilang kenapa — di DALAM kartu fase berjalan --}}
                        @if ($st === 'now' && $gAda)
                            <div class="itk-gerbang">
                                <div class="itk-gerbang-hd"><b>GERBANG KE {{ $gNext === 'SELESAI' ? 'SELESAI' : mb_strtoupper(PhaseEngine::phLong($gNext)) }}</b><span class="itk-acc-fase f2">FASE 2</span></div>
                                <ul class="itk-missing itk-lampu">
                                    @foreach ($lampu as $l)<li class="{{ $l['on'] ? 'on' : '' }}">{{ $l['t'] }}</li>@endforeach
                                </ul>
                                <div class="itk-hint">Lampu menyala sendiri saat field-nya terisi — bukan untuk dicentang.</div>
                                @if ($gBtnForm)
                                    <form method="post" action="{{ route('it-tracker.maju', $t->id) }}">
                                        @csrf
                                        <button type="submit" class="itk-gerbang-btn {{ $gBtnHijau ? 'hijau' : '' }}">{{ $gBtnTxt }}</button>
                                    </form>
                                @else
                                    <span class="itk-gerbang-btn mati">{{ $gBtnTxt }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            <div class="itk-legenda" style="padding:0 4px">Fase 1 = intake + papan · Fase 2 = tujuh fase + gerbang (halaman ini) · Fase 3 = sambungan Project Tracker + evaluasi terjadwal.</div>
        </div>

        {{-- Rail kanan: aksi & info — menempel saat menggulir --}}
        <div class="itk-detail-rail">
            @if ($gAda || ($t->fase === 'TRIASE' && $peran === 'head_it'))
                <div class="itk-side">
                    <div class="itk-side-hd"><i></i><b>LANGKAH BERIKUTNYA</b></div>
                    @if ($t->fase === 'TRIASE')
                        {{-- triase DI SINI: Head IT yang sedang membaca tiket tak perlu dilempar balik ke papan --}}
                        <button type="button" class="itk-gerbang-btn" style="margin-top:0;width:100%"
                            onclick="itkBukaTriase('{{ $t->id }}', '{{ $t->nomor }}', @js($t->judul), @js($t->prioritas_pemohon))">Triase — tetapkan jenjang &amp; prioritas</button>
                    @elseif ($gBtnForm)
                        <form method="post" action="{{ route('it-tracker.maju', $t->id) }}">
                            @csrf
                            <button type="submit" class="itk-gerbang-btn {{ $gBtnHijau ? 'hijau' : '' }}" style="margin-top:0;width:100%">{{ $gBtnTxt }}</button>
                        </form>
                    @else
                        <span class="itk-gerbang-btn mati" style="margin-top:0;width:100%">{{ $gBtnTxt }}</span>
                        @if ($t->fase === 'ANALISIS' && $peran === 'pengawas')
                            <div class="itk-hint"><strong>Giliranmu:</strong> paraf Business Owner ada di kartu Analysis &amp; Design, baris PARAF paling bawah — tombolnya menyala begitu analisis IT lengkap.</div>
                        @elseif ($t->fase === 'GOLIVE' && ! $adalahIt)
                            <div class="itk-hint">Checklist Go-Live ada di kartu Go Live; ACC Business Owner dibubuhkan Executive/Konsultan di kartu yang sama.</div>
                        @endif
                    @endif
                </div>
            @endif

            <div class="itk-side" style="display:flex;flex-direction:column;gap:10px">
                <div class="itk-side-hd" style="margin:0"><i></i><b>TENTANG TIKET</b></div>
                <div class="itk-duo">
                    <div class="pemohon"><div class="lab">PRIORITAS PEMOHON</div><div class="val">{{ $t->prioritas_pemohon ?? '—' }}</div></div>
                    <div class="it"><div class="lab">PRIORITAS IT</div><div class="val">{{ $t->prioritas_it ?? 'belum' }}</div></div>
                </div>
                <div class="itk-hint" style="margin:-2px 0 0">Dua kolom terpisah — masukan pemohon ≠ keputusan IT.</div>
                <div class="itk-info-row"><b>Jenjang · bobot</b><span>{{ $t->jenjang ? $t->jenjang . ' · bobot ' . ($t->bobot ?? '—') : 'belum ditriase' }}</span></div>
                <div class="itk-info-row"><b>PIC IT</b><span>{{ $t->pic->nama ?? 'belum ditarik' }}</span></div>
                <div class="itk-info-row"><b>Sumber</b><span>{{ $t->sumber === 'permintaan' ? 'Formulir permintaan' : ($t->sumber === 'insiden' ? 'Insiden / hotfix' : 'Inisiatif IT') }}</span></div>
                <div class="itk-info-row"><b>Fase saat ini</b><span>{{ $aktif ? ($labelFase[$t->fase] ?? PhaseEngine::phLong($kini)) : ($labelFase[$t->fase] ?? $t->fase) }}</span></div>
                <div class="itk-info-row"><b>Masuk fase</b><span>{{ $t->fase_since->translatedFormat('j M') }}</span></div>
                @if ($t->jenis_permintaan)<div class="itk-info-row"><b>Jenis</b><span>{{ $t->jenis_permintaan }}</span></div>@endif
                <div style="display:flex;flex-direction:column;gap:7px;border-top:1px solid #F1E5D5;padding-top:11px">
                    @if ($adalahIt && $aktif && ! $t->pic_user_id)
                        {{-- "Tarik": IT pelaksana menunjuk dirinya sendiri. Tanpa ini ia terkunci
                             di luar tiketnya sendiri sampai Head IT sempat menunjuk PIC. --}}
                        <form method="post" action="{{ route('it-tracker.tarik', $t->id) }}">
                            @csrf
                            <button type="submit" class="itk-btn itk-btn-hijau" style="width:100%">Tarik — aku yang kerjakan</button>
                        </form>
                        <div class="itk-hint" style="margin:0">Belum ada PIC. Yang menarik jadi pengerjanya; pemindahan sesudah itu lewat Head IT.</div>
                    @endif
                    @if ($form01)
                        <a class="itk-rail-btn" href="{{ route('it-tracker.ekspor', $t->id) }}">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M4 1.5h5.5L13 5v9.5H4V1.5Z" stroke="#7A5B43" stroke-width="1.4" stroke-linejoin="round"/><path d="M9.5 1.5V5H13M6 8.5h5M6 11h5" stroke="#7A5B43" stroke-width="1.4" stroke-linecap="round"/></svg>Ekspor FRM-IT-01</a>
                    @endif
                    @if ($peran === 'head_it' && $picOpsi->count() > 1 && ! in_array($t->fase, ['DITOLAK', 'SELESAI', 'TRIASE'], true))
                        <form method="post" action="{{ route('it-tracker.pic', $t->id) }}" style="display:flex;gap:8px;align-items:center">
                            @csrf
                            <label class="itk-lbl" style="margin:0;flex:none">PIC</label>
                            <select name="pic_user_id" style="flex:1;border:1px solid #E7D3BE;border-radius:9px;padding:6px 10px;font-family:inherit;font-size:12px;background:#fff">
                                @foreach ($picOpsi as $p)
                                    <option value="{{ $p->id }}" {{ $t->pic_user_id === $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Simpan</button>
                        </form>
                    @endif
                    @if ($peran === 'head_it' && ! in_array($t->fase, ['DITOLAK', 'SELESAI'], true))
                        <details>
                            <summary class="itk-rail-btn" style="list-style:none">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M5.5 3.5v9M10.5 3.5v9" stroke="#7A5B43" stroke-width="1.7" stroke-linecap="round"/></svg>Tunda / Tolak…</summary>
                            <form method="post" action="{{ route('it-tracker.parkir', $t->id) }}" style="margin-top:9px">
                                @csrf
                                <label class="itk-lbl">ALASAN — WAJIB, SUPAYA YANG DITUNDA TIDAK HILANG TANPA JEJAK</label>
                                <input class="itk-in" type="text" name="alasan" required maxlength="500" placeholder="alasannya wajib — tampil di papan">
                                <div style="display:flex;gap:8px;margin-top:9px">
                                    <button type="submit" name="ke" value="DITUNDA" class="itk-btn itk-btn-oranye">Tunda</button>
                                    <button type="submit" name="ke" value="DITOLAK" class="itk-btn itk-btn-putih">Tolak</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            {{-- Riwayat fase — jejak audit dari it_ticket_fase --}}
            <div class="itk-side">
                <div class="itk-side-hd"><i></i><b>RIWAYAT FASE</b></div>
                <div class="itk-rows" style="gap:6px">
                    @foreach ($t->riwayatFase->sortByDesc('masuk_at') as $r)
                        <div class="itk-row" style="grid-template-columns:1fr 1fr">
                            <b>{{ $labelFase[$r->fase] ?? PhaseEngine::phShort($r->fase) }}</b>
                            <span style="text-align:right;font-size:11.5px">{{ $r->masuk_at->translatedFormat('j M H:i') }}
                                {{ $r->keluar_at ? '→ ' . $r->keluar_at->translatedFormat('j M H:i') : '· berjalan' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="itk-fase3">
                <span class="tag">FASE 3</span> <b style="font-size:11px;font-weight:800;color:#7A5B43">SAMBUNGAN PROJECT TRACKER</b>
                <p>Tiket yang lolos gerbang Analisis melahirkan project di Project Tracker — fase tetap di sini, eksekusi harian di sana.</p>
            </div>
        </div>
    </div>
</div>
@if ($peran === 'head_it' && $t->fase === 'TRIASE')
    @include('it-tracker._modal-triase')
@endif
<script>
(function () {
    // akordeon: klik kepala membuka/menutup; simpul rel membuka fasenya lalu menggulir
    document.querySelectorAll('[data-acc-hd]').forEach(function (hd) {
        hd.addEventListener('click', function (e) {
            if (e.target.closest('a,button,input,select,textarea,form')) return;
            hd.parentElement.classList.toggle('buka');
        });
    });
    document.querySelectorAll('[data-rel]').forEach(function (s) {
        s.addEventListener('click', function () {
            var el = document.getElementById('sec-' + s.getAttribute('data-rel'));
            if (!el) return;
            el.classList.add('buka');
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>
@endsection
