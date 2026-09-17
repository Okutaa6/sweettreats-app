@extends('layouts.app')

@section('content')
@php
    /* ─────────────────────────────────────────────────────────────────────
     * Detail Review Checklist Harian — mengikuti handoff desain
     * "design_handoff_review_checklist" (gelombang 1: tampilan + panel
     * temuan; antrian review, toast/Urungkan, dan pintasan keyboard SENGAJA
     * belum dikerjakan — lihat catatan sesi).
     *
     * Prinsip yang tidak boleh dilanggar saat mengubah file ini:
     *   1. SATU sumber angka — semuanya turun dari $submission->tally().
     *      "beres" = dicentang ATAU dikecualikan.
     *   2. Jam pengisian adalah BUKTI, bukan hiasan. Tumpukan centang di menit
     *      yang sama ditampilkan sebagai informasi, tidak pernah sebagai vonis.
     *   3. Tombol tidak boleh berbohong: Tolak berbunyi "Tulis alasan dulu"
     *      selama catatan kosong, bukan disabled diam tanpa penjelasan.
     * ───────────────────────────────────────────────────────────────────── */

    $stMap = [
        'submitted' => ['label' => 'Menunggu Approval', 'c' => '#B5701A', 'bg' => '#FBEFD9'],
        'approved'  => ['label' => 'Disetujui',          'c' => '#2F8E55', 'bg' => '#E3F4E8'],
        'rejected'  => ['label' => 'Ditolak',            'c' => '#C0392B', 'bg' => '#FBE3DE'],
        'excused'   => ['label' => 'Dikecualikan',       'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
        'missed'    => ['label' => 'Tidak Diisi',        'c' => '#9B8578', 'bg' => '#EFE7DE'],
        'draft'     => ['label' => 'Belum Dikirim',      'c' => '#9B8578', 'bg' => '#EFE7DE'],
    ];
    $st = $stMap[$submission->status] ?? ['label' => $submission->status, 'c' => '#9B8578', 'bg' => '#EFE7DE'];

    $tal     = $submission->tally();
    $pat     = $submission->timePattern();
    $cluster = $pat['cluster'];

    $isPending = $submission->status === \App\Models\ChecklistSubmission::STATUS_SUBMITTED;
    $isFinal   = in_array($submission->status, [
        \App\Models\ChecklistSubmission::STATUS_APPROVED,
        \App\Models\ChecklistSubmission::STATUS_REJECTED,
    ], true);
    $isExcused = $submission->status === \App\Models\ChecklistSubmission::STATUS_EXCUSED;

    $pct = $tal['total'] ? (int) round($tal['beres'] / $tal['total'] * 100) : 0;
    $barColor = $isExcused
        ? '#C7AC92'
        : ($pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B'));

    // Avatar 2 huruf + warna per orang (palet peta-tema §3).
    $avPalette  = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avName     = trim($submission->user?->nama ?? '');
    $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
    $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
    $avColor    = $avPalette[abs(crc32($avName !== '' ? $avName : '?')) % 8];

    // Kembali ke halaman karyawan asal kalau datang dari sana — orang datang
    // dari daftar tanggal seorang karyawan dan mau balik ke sana, bukan ke
    // daftar paling awal.
    $returnUser  = request('return_user');
    $returnMonth = request('return_month');
    $backUrl     = $returnUser
        ? route('checklist.review.employee', array_filter(['user' => $returnUser, 'month' => $returnMonth]))
        : route('checklist.review');

    $metaParts = [
        $submission->submitted_at ? 'Disubmit ' . $submission->submitted_at->format('d M · H:i') : 'Belum pernah dikirim',
        $submission->reviewed_at  ? 'Direview ' . $submission->reviewed_at->format('d M · H:i')  : 'Belum direview',
        $submission->reviewer     ? 'Reviewer ' . $submission->reviewer->nama                     : 'Reviewer —',
    ];

    /* ── Susun kategori sekali jalan: hitungan + penanda temuan + kunci lompat ── */
    $cats = [];
    $catIndex = 0;
    foreach ($grouped as $catTitle => $items) {
        $rows = [];
        $checked = 0; $excluded = 0; $unchecked = 0; $hasCluster = false;
        $taskIndex = 0;
        foreach ($items as $item) {
            $isX = (bool) $item->is_excepted;
            $isC = ! $isX && (bool) $item->is_done;
            $jam = ($isC && $item->done_at) ? $item->done_at->format('H:i') : null;
            $inCluster = $cluster && $jam !== null && $jam === $cluster['time'];

            if ($isX)      { $excluded++; }
            elseif ($isC)  { $checked++; }
            else           { $unchecked++; }
            if ($inCluster) $hasCluster = true;

            $rows[] = [
                'key'       => $catIndex . '-' . $taskIndex,
                'item'      => $item,
                'isC'       => $isC,
                'isX'       => $isX,
                'jam'       => $jam,
                'inCluster' => $inCluster,
            ];
            $taskIndex++;
        }
        $total = count($rows);
        $cats[] = [
            'i'          => $catIndex,
            'name'       => $catTitle,
            'rows'       => $rows,
            'total'      => $total,
            'beres'      => $checked + $excluded,
            'unchecked'  => $unchecked,
            'complete'   => $unchecked === 0 && $total > 0,
            'hasFinding' => $unchecked > 0 || $hasCluster,
        ];
        $catIndex++;
    }

    /* ── YANG PERLU DILIHAT — diturunkan, urutan prioritas tetap ── */
    $findings = [];

    if ($tal['unchecked'] > 0) {
        $names = []; $target = null;
        foreach ($cats as $c) {
            foreach ($c['rows'] as $r) {
                if ($r['isC'] || $r['isX']) continue;
                if ($target === null) $target = $r['key'];
                if (! in_array($c['name'], $names, true)) $names[] = $c['name'];
            }
        }
        $findings[] = [
            'dot'    => '#C0392B',
            'label'  => $tal['unchecked'] . ' tugas belum dicentang',
            'sub'    => 'Di ' . implode(' · ', $names),
            'target' => $target,
            'ribbon' => false,
        ];
    }

    if ($tal['excluded'] > 0) {
        $reasons = []; $target = null;
        foreach ($cats as $c) {
            foreach ($c['rows'] as $r) {
                if (! $r['isX']) continue;
                if ($target === null) $target = $r['key'];
                $alasan = trim((string) $r['item']->exception_reason);
                if ($alasan !== '' && count($reasons) < 2) $reasons[] = $alasan;
            }
        }
        $sisa = $tal['excluded'] - count($reasons);
        $findings[] = [
            'dot'    => '#8A5A2B',
            'label'  => $tal['excluded'] . ' tugas dikecualikan',
            'sub'    => $reasons
                ? implode(' · ', $reasons) . ($sisa > 0 ? ' · dan ' . $sisa . ' alasan lain' : '')
                : 'Alasan ada di tiap baris tugas.',
            'target' => $target,
            'ribbon' => false,
        ];
    }

    $ribbonDots = [];
    if ($cluster) {
        // Pita sebaran jam 08:00–21:00. Titik besar = tumpukan centang.
        foreach ($pat['times'] as $jam => $n) {
            $parts = explode(':', $jam);
            $mins  = ((int) ($parts[0] ?? 0)) * 60 + (int) ($parts[1] ?? 0);
            $left  = (($mins - 480) / (1260 - 480)) * 100;
            $ribbonDots[] = [
                'left' => max(1, min(98, $left)),
                'big'  => $n >= 3,
            ];
        }
        $target = null;
        foreach ($cats as $c) {
            foreach ($c['rows'] as $r) {
                if ($r['inCluster'] && $target === null) $target = $r['key'];
            }
        }
        $findings[] = [
            'dot'    => '#B5701A',
            'label'  => $cluster['count'] . ' tugas dicentang dalam 1 menit — ' . $cluster['time'],
            'sub'    => 'Informasi saja, bukan pelanggaran: sebaran jam centang sepanjang shift ada di bawah. Titik besar = tumpukan centang.',
            'target' => $target,
            'ribbon' => true,
        ];
    }

    $bersih = empty($findings);
    if ($bersih) {
        $findings[] = [
            'dot'    => '#2F8E55',
            'label'  => 'Tidak ada yang perlu dicek.',
            'sub'    => 'Semua tugas dicentang, jamnya tersebar sepanjang shift, dan tidak ada pengecualian. Bisa langsung disetujui.',
            'target' => null,
            'ribbon' => false,
        ];
    }
    $findings = array_slice($findings, 0, 4);
    $findMeta = $bersih ? 'bersih' : count($findings) . ' hal';
@endphp

<style>
    /* ── Detail review harian — handoff design_handoff_review_checklist ── */
    /* Handoff mematok 1040px, tapi diminta selebar layar — pembatasnya dicabut,
       bukan dinaikkan angkanya, supaya di monitor lebar tidak ada sisa kosong. */
    .rvd-wrap { width:100%; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
    .rvd-card { background:#fff; border:1px solid #EFE0CE; border-radius:18px; box-shadow:0 1px 2px rgba(70,21,0,.04); }
    .rvd-num  { font-variant-numeric:tabular-nums; font-feature-settings:"tnum" 1; }

    .rvd-back { display:inline-flex; align-items:center; gap:8px; height:38px; padding:0 15px 0 12px; border-radius:11px; border:1px solid #E7D3BE; background:#fff; color:#5A3A22; font-size:13px; font-weight:700; text-decoration:none; transition:background .12s ease, border-color .12s ease; }
    .rvd-back:hover { background:#FBF4EC; border-color:#D9C3AC; color:#5A3A22; }

    /* Panel "Yang perlu dilihat" */
    .rvd-panel__head { display:flex; align-items:center; gap:10px; padding:14px 20px; background:linear-gradient(120deg,#5A2410,#3A1100); color:#FBEADB; }
    .rvd-find { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-bottom:1px solid #F4EADD; background:#fff; transition:background .12s ease; }
    .rvd-find:last-child { border-bottom:none; }
    .rvd-find--ok { background:#F4FAF6; }
    .rvd-find--jump { cursor:pointer; }
    .rvd-find--jump:hover { background:#FBF4EC; }
    .rvd-find--jump:hover .rvd-chev { opacity:1; transform:translateX(2px); }
    .rvd-dot { width:9px; height:9px; flex:none; border-radius:50%; margin-top:5px; }
    .rvd-chev { opacity:.8; flex:none; transition:opacity .12s ease, transform .12s ease; }

    /* Kategori */
    .rvd-cat__head { display:flex; align-items:center; gap:12px; padding:15px 20px; cursor:pointer; user-select:none; transition:background .12s ease; }
    .rvd-cat__head:hover { background:#FBF4EC; }
    .rvd-cat__head:hover .rvd-chev { opacity:1; transform:translateX(2px); }
    .rvd-cat__marker { width:8px; height:8px; flex:none; border-radius:50%; }
    .rvd-cat__name { flex:1; min-width:0; font-size:13px; font-weight:800; letter-spacing:.5px; color:#3B1500; }
    .rvd-cat__badge { font-size:11.5px; font-weight:800; padding:4px 11px; border-radius:20px; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rvd-cat__caret { transition:transform .18s ease; transform:rotate(90deg); }
    .rvd-cat.is-collapsed .rvd-cat__caret { transform:rotate(0deg); }
    .rvd-cat.is-collapsed .rvd-cat__body { display:none; }

    /* Baris tugas */
    .rvd-task { padding:12px 20px; border-top:1px solid #F6EEE1; }
    .rvd-task--todo { background:#FFFDFA; }
    .rvd-task__row { display:flex; align-items:flex-start; gap:12px; }
    .rvd-task__box { width:22px; height:22px; flex:none; margin-top:1px; border-radius:7px; display:flex; align-items:center; justify-content:center; }
    .rvd-task__box--done { background:#2F8E55; border:1px solid #2F8E55; }
    .rvd-task__box--xcpt { background:#8A5A2B; border:1px solid #8A5A2B; }
    .rvd-task__box--todo { background:#fff; border:1.5px solid #D8C3AC; }
    .rvd-task__title { flex:1; min-width:0; font-size:13.5px; font-weight:600; line-height:1.5; color:#A9866A; }
    .rvd-task__title--done { color:#3B2A1E; }
    .rvd-task__time { font-size:12px; font-weight:700; padding:5px 9px; border-radius:8px; white-space:nowrap; color:#7A5B43; background:#F4E7D6; font-variant-numeric:tabular-nums; }
    .rvd-task__time--cluster { color:#B5701A; background:#FBEFD9; }
    .rvd-task__pill { font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px; white-space:nowrap; }
    .rvd-sub { margin:9px 0 0 34px; border-radius:11px; padding:9px 12px; }
    .rvd-sub--note { background:#FBF5EE; border:1px solid #F0E2D2; }
    .rvd-sub--xcpt { background:#F7EFE4; border-left:3px solid #8A5A2B; border-radius:0 11px 11px 0; }
    .rvd-sub__cap { font-size:10.5px; font-weight:800; letter-spacing:.5px; }
    .rvd-sub__body { font-size:12.5px; color:#5A3A22; margin-top:4px; line-height:1.5; }

    /* Kotak statistik */
    .rvd-tile { background:#FBF5EE; border:1px solid #EFE0CE; border-radius:12px; padding:10px 14px; min-width:96px; }
    .rvd-tile__n { font-size:17px; font-weight:800; font-variant-numeric:tabular-nums; }
    .rvd-tile__l { font-size:11px; font-weight:700; color:#9A7256; margin-top:2px; }

    /* Bilah aksi menempel */
    .rvd-foot { position:sticky; bottom:14px; z-index:20; background:#fff; border:1px solid #E7D3BE; border-radius:18px; padding:16px 18px; box-shadow:0 -2px 4px rgba(70,21,0,.03),0 14px 34px rgba(70,21,0,.16); }
    .rvd-ta { width:100%; box-sizing:border-box; margin-top:6px; border:1px solid #E7D3BE; border-radius:11px; padding:10px 12px; font-family:inherit; font-size:13px; color:#3B1500; background:#FFFCF8; resize:vertical; line-height:1.5; transition:border-color .12s ease, background .12s ease; }
    .rvd-ta:focus { outline:none; border-color:#8A5A2B; background:#fff; }
    .rvd-ta::placeholder { color:#C7AC92; }
    .rvd-btn { display:flex; align-items:center; justify-content:center; gap:8px; border-radius:11px; padding:12px 16px; font-weight:800; font-size:13.5px; font-family:inherit; cursor:pointer; border:1px solid transparent; transition:filter .12s ease; }
    .rvd-btn:hover { filter:brightness(.96); }
    .rvd-btn--ok { background:#2F8E55; color:#fff; }
    .rvd-btn--brand { background:linear-gradient(135deg,#6B2400,#461500); color:#FFEAD6; }
    .rvd-btn--xcpt { background:#8A5A2B; color:#FFF3E6; }
    /* Tolak: selama alasan kosong tombolnya BILANG kenapa, bukan mati diam. */
    .rvd-btn--tolak { background:#FBF5EE; color:#A9866A; border-color:#E7D3BE; }
    .rvd-btn--tolak.is-armed { background:#C0392B; color:#fff; border-color:transparent; }

    @keyframes rvdFlash { 0% { background:#FBEFD9; } 100% { background:transparent; } }
    .rvd-flash { animation:rvdFlash 2.4s ease-out; }

    @media (prefers-reduced-motion: reduce) {
        .rvd-flash { animation:none; }
        .rvd-cat__caret, .rvd-chev, .rvd-btn { transition:none; }
    }
</style>

<div class="container-fluid">

    @include('layouts.notif')

    <div class="rvd-wrap">

    {{-- ─── KEMBALI ─── --}}
    <div>
        <a href="{{ $backUrl }}" class="rvd-back">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3.5 5.5 8l4.5 4.5" stroke="#7A5B43" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali
        </a>
    </div>

    {{-- ─── IDENTITAS + ANGKA PROGRES ─── --}}
    <div class="rvd-card" style="padding:20px 22px">
        <div style="display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
            <div style="width:54px;height:54px;flex:none;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;background:{{ $avColor }};color:#FFF3E6">{{ $avInitials }}</div>
            <div style="flex:1;min-width:220px">
                <div style="font-size:21px;font-weight:800;color:#3B1500;line-height:1.2">{{ $submission->user?->nama ?? '—' }}</div>
                <div style="display:flex;align-items:center;gap:9px;margin-top:8px;flex-wrap:wrap">
                    <span style="background:#F4E7D6;color:#7A5B43;font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:20px">{{ $submission->division?->name ?? $submission->division_id }}</span>
                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:#7A5B43;font-weight:600">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M3 14V4l5-2 5 2v10M3 14h10M6.5 7.5h1M6.5 10.5h1M9.5 7.5h1M9.5 10.5h1" stroke="#A9866A" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        Cabang {{ $submission->branch?->name ?? '—' }}
                    </span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;flex:none">
                <span style="display:inline-flex;align-items:center;font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:20px;white-space:nowrap;background:{{ $st['bg'] }};color:{{ $st['c'] }}">{{ $st['label'] }}</span>
                <span style="font-size:13px;font-weight:700;color:#5A3A22">{{ $submission->submission_date->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>

        <div class="rvd-num" style="font-size:11.5px;color:#A9866A;font-weight:600;margin-top:14px">{{ implode(' · ', $metaParts) }}</div>

        <div style="height:1px;background:#F4EADD;margin:16px 0 15px"></div>

        <div style="display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <div style="height:10px;border-radius:6px;background:#F1E6D9;overflow:hidden">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:6px;transition:width .3s ease"></div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap">
                    <span class="rvd-num" style="font-size:24px;font-weight:800;color:#3B1500;line-height:1">{{ $tal['beres'] }} / {{ $tal['total'] }} tugas</span>
                    <span style="font-size:12px;font-weight:700;color:#9A7256;letter-spacing:.3px">BERES</span>
                </div>
                <div class="rvd-num" style="font-size:12.5px;color:#7A5B43;font-weight:600;margin-top:6px">
                    {{ $tal['checked'] }} dicentang · {{ $tal['excluded'] }} dikecualikan @if($tal['unchecked'] > 0) · {{ $tal['unchecked'] }} belum dicentang @endif
                </div>
            </div>
            <div style="display:flex;gap:9px;flex-wrap:wrap">
                <div class="rvd-tile">
                    <div class="rvd-tile__n" style="color:#2F8E55">{{ $tal['checked'] }}</div>
                    <div class="rvd-tile__l">Dicentang</div>
                </div>
                <div class="rvd-tile">
                    <div class="rvd-tile__n" style="color:#8A5A2B">{{ $tal['excluded'] }}</div>
                    <div class="rvd-tile__l">Dikecualikan</div>
                </div>
                <div class="rvd-tile">
                    <div class="rvd-tile__n" style="color:{{ $tal['unchecked'] ? '#C0392B' : '#B79A82' }}">{{ $tal['unchecked'] }}</div>
                    <div class="rvd-tile__l">Belum dicentang</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── YANG PERLU DILIHAT ─── --}}
    <div class="rvd-card" style="overflow:hidden">
        <div class="rvd-panel__head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="#FBEADB" stroke-width="1.6"/><path d="M8 5.2v.1M8 7.4v3.4" stroke="#FBEADB" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span style="font-size:13px;font-weight:800;letter-spacing:.4px">YANG PERLU DILIHAT</span>
            <span style="margin-left:auto;font-size:11.5px;color:#D6A878;font-weight:600">{{ $findMeta }}</span>
        </div>
        @foreach($findings as $f)
            <div class="rvd-find {{ $bersih ? 'rvd-find--ok' : '' }} {{ $f['target'] ? 'rvd-find--jump' : '' }}"
                 @if($f['target']) data-rvd-jump="{{ $f['target'] }}" @endif>
                <span class="rvd-dot" style="background:{{ $f['dot'] }}"></span>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13.5px;font-weight:700;color:#3B1500">{{ $f['label'] }}</div>
                    <div style="font-size:12px;color:#7A5B43;margin-top:3px;line-height:1.5">{{ $f['sub'] }}</div>
                    @if($f['ribbon'])
                        <div style="margin-top:11px;max-width:620px">
                            <div style="position:relative;height:24px;border-radius:8px;background:#F6EDE2;border:1px solid #EFE0CE">
                                @foreach($ribbonDots as $d)
                                    <span style="position:absolute;top:50%;left:{{ number_format($d['left'], 2, '.', '') }}%;transform:translate(-50%,-50%);width:{{ $d['big'] ? 16 : 8 }}px;height:{{ $d['big'] ? 16 : 8 }}px;border-radius:50%;background:{{ $d['big'] ? '#B5701A' : '#C7AC92' }};{{ $d['big'] ? 'box-shadow:0 0 0 4px rgba(181,112,26,.18);' : '' }}"></span>
                                @endforeach
                            </div>
                            <div class="rvd-num" style="display:flex;justify-content:space-between;margin-top:5px;font-size:10.5px;font-weight:600;color:#A9866A">
                                <span>08:00</span><span>14:00</span><span>21:00</span>
                            </div>
                        </div>
                    @endif
                </div>
                @if($f['target'])
                    <svg class="rvd-chev" width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="#C9AE94" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ─── BANNER: DIKECUALIKAN — ALASAN KARYAWAN ─── --}}
    @if($isExcused && $submission->excuse_reason)
        <div style="background:#F2E7D8;border:1px solid #E3D0B7;border-left:4px solid #8A5A2B;border-radius:16px;padding:16px 18px">
            <div style="display:flex;align-items:center;gap:9px">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="#8A5A2B" stroke-width="1.6"/><path d="M4.4 11.6 11.6 4.4" stroke="#8A5A2B" stroke-width="1.6" stroke-linecap="round"/></svg>
                <span style="font-size:13.5px;font-weight:800;color:#8A5A2B">Ditandai tidak perlu diisi</span>
            </div>
            <div style="font-size:13px;color:#5A3A22;margin-top:9px;line-height:1.55;font-style:italic">“{{ $submission->excuse_reason }}”</div>
        </div>
    @endif

    {{-- ─── BANNER: KEPUTUSAN SEBELUMNYA ─── --}}
    @if($isFinal)
        @php
            $decOk    = $submission->status === \App\Models\ChecklistSubmission::STATUS_APPROVED;
            $decCol   = $decOk ? '#2F8E55' : '#C0392B';
            $decBg    = $decOk ? '#E3F4E8' : '#FBE3DE';
            $decBrd   = $decOk ? '#CBE7D5' : '#F0CFC6';
            $decTitle = ($decOk ? 'Disetujui' : 'Ditolak') . ' oleh ' . ($submission->reviewer?->nama ?? '—');
        @endphp
        <div style="border-radius:16px;padding:15px 18px;background:{{ $decBg }};border:1px solid {{ $decBrd }}">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                <span style="width:10px;height:10px;border-radius:50%;flex:none;background:{{ $decCol }}"></span>
                <span style="font-size:13.5px;font-weight:800;color:{{ $decCol }}">{{ $decTitle }}</span>
                <span class="rvd-num" style="font-size:12px;color:#7A5B43;font-weight:600">· {{ $submission->reviewed_at ? $submission->reviewed_at->format('d M, H:i') : '—' }}</span>
            </div>
            @if($submission->review_notes)
                <div style="font-size:13px;color:#3B1500;margin-top:9px;line-height:1.55">{{ $submission->review_notes }}</div>
            @endif
        </div>
    @endif

    {{-- ─── ISI CHECKLIST ─── --}}
    @if(empty($cats))
        <div class="rvd-card" style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
            Submission ini tidak memiliki item checklist.
        </div>
    @else
        @foreach($cats as $c)
            @php
                // Terbuka dari awal: kategori pertama DAN setiap kategori yang
                // ada temuannya — supervisor tidak perlu berburu.
                $openDefault = $c['i'] === 0 || $c['hasFinding'];
                $markerColor = $c['hasFinding'] ? '#B5701A' : ($c['complete'] ? '#2F8E55' : '#C7AC92');
            @endphp
            <div class="rvd-card rvd-cat {{ $openDefault ? '' : 'is-collapsed' }}" style="overflow:hidden" data-rvd-cat="{{ $c['i'] }}">
                <div class="rvd-cat__head" data-rvd-toggle>
                    <span class="rvd-cat__marker" style="background:{{ $markerColor }}"></span>
                    <span class="rvd-cat__name">{{ $c['name'] }}</span>
                    @if($c['hasFinding'])
                        <span style="background:#FBEFD9;color:#B5701A;font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px;white-space:nowrap">Perlu dilihat</span>
                    @endif
                    <span class="rvd-cat__badge" style="{{ $c['complete'] ? 'color:#2F8E55;background:#E3F4E8' : 'color:#B5701A;background:#FBEFD9' }}">{{ $c['beres'] }}/{{ $c['total'] }}</span>
                    <svg class="rvd-chev rvd-cat__caret" width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="#C9AE94" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div class="rvd-cat__body" style="border-top:1px solid #F1E6D7">
                    @foreach($c['rows'] as $r)
                        @php $it = $r['item']; @endphp
                        <div class="rvd-task {{ $r['isC'] ? '' : 'rvd-task--todo' }}" data-rvd-row="{{ $r['key'] }}">
                            <div class="rvd-task__row">
                                <span class="rvd-task__box {{ $r['isX'] ? 'rvd-task__box--xcpt' : ($r['isC'] ? 'rvd-task__box--done' : 'rvd-task__box--todo') }}">
                                    @if($r['isX'])
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="#fff" stroke-width="1.8"/><path d="M3.5 10.5 10.5 3.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    @elseif($r['isC'])
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M2.5 7.5 5.5 10.5 11.5 4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                </span>
                                <span class="rvd-task__title {{ $r['isC'] ? 'rvd-task__title--done' : '' }}">{{ $it->task_title_snapshot }}</span>
                                <div style="flex:none;display:flex;align-items:center;gap:7px">
                                    @if($r['jam'])
                                        <span class="rvd-task__time {{ $r['inCluster'] ? 'rvd-task__time--cluster' : '' }}">{{ $r['jam'] }}</span>
                                    @endif
                                    @if($r['isX'])
                                        <span class="rvd-task__pill" style="background:#F2E7D8;color:#8A5A2B">Dikecualikan</span>
                                    @elseif(! $r['isC'])
                                        <span class="rvd-task__pill" style="background:#EFE7DE;color:#9B8578">Belum dicentang</span>
                                    @endif
                                </div>
                            </div>
                            @if($it->note)
                                <div class="rvd-sub rvd-sub--note">
                                    <div class="rvd-sub__cap" style="color:#A9866A">CATATAN KARYAWAN</div>
                                    <div class="rvd-sub__body">{{ $it->note }}</div>
                                </div>
                            @endif
                            @if($r['isX'] && $it->exception_reason)
                                <div class="rvd-sub rvd-sub--xcpt">
                                    <div class="rvd-sub__cap" style="color:#8A5A2B">ALASAN DIKECUALIKAN</div>
                                    <div class="rvd-sub__body">{{ $it->exception_reason }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    {{-- ─── BILAH AKSI MENEMPEL ─── --}}
    @if($isPending || $isFinal || $isExcused)
        <div class="rvd-foot">
            @if($isPending)
                <div style="display:flex;gap:16px;align-items:stretch;flex-wrap:wrap">
                    <div style="flex:1;min-width:280px">
                        <label style="font-size:10.5px;font-weight:800;letter-spacing:.5px;color:#A9866A">CATATAN SUPERVISOR (WAJIB JIKA MENOLAK)</label>
                        <textarea id="reviewNotes" class="rvd-ta" rows="2"
                                  placeholder="Mis. 10 tugas dicentang jam 17:21 — mohon dicentang saat tugasnya dikerjakan."></textarea>
                    </div>
                    <div style="display:flex;flex-direction:column;justify-content:flex-end;gap:9px;flex:none;min-width:230px">
                        <div style="display:flex;gap:9px">
                            <form method="POST" action="{{ route('checklist.review.approve', $submission->id) }}" style="flex:1;display:flex"
                                  onsubmit="this.querySelector('input[name=review_notes]').value = document.getElementById('reviewNotes').value; return confirm('Setujui checklist ini?');">
                                @csrf @method('PATCH')
                                <input type="hidden" name="review_notes" value="">
                                @if($returnUser)
                                    <input type="hidden" name="return_user" value="{{ $returnUser }}">
                                    <input type="hidden" name="return_month" value="{{ $returnMonth }}">
                                @endif
                                <button type="submit" class="rvd-btn rvd-btn--ok" style="flex:1">
                                    <svg width="15" height="15" viewBox="0 0 14 14" fill="none"><path d="M2.5 7.5 5.5 10.5 11.5 4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    Setujui
                                </button>
                            </form>
                            <form method="POST" action="{{ route('checklist.review.reject', $submission->id) }}" style="flex:1;display:flex"
                                  onsubmit="var ta=document.getElementById('reviewNotes'); var n=ta.value.trim(); if(!n){ta.focus();return false;} this.querySelector('input[name=review_notes]').value = n; return confirm('Yakin tolak checklist ini? Karyawan akan diberitahu untuk perbaiki.');">
                                @csrf @method('PATCH')
                                <input type="hidden" name="review_notes" value="">
                                @if($returnUser)
                                    <input type="hidden" name="return_user" value="{{ $returnUser }}">
                                    <input type="hidden" name="return_month" value="{{ $returnMonth }}">
                                @endif
                                <button type="submit" id="btnTolak" class="rvd-btn rvd-btn--tolak" style="flex:1">Tulis alasan dulu</button>
                            </form>
                        </div>
                    </div>
                </div>
            @elseif($isFinal)
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                    <div style="flex:1;min-width:260px">
                        <div style="font-size:13.5px;font-weight:800;color:#3B1500">Checklist ini sudah {{ $submission->status === \App\Models\ChecklistSubmission::STATUS_APPROVED ? 'disetujui' : 'ditolak' }}</div>
                        <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Reopen untuk mengubah keputusan review jika ada kesalahan.</div>
                    </div>
                    <form method="POST" action="{{ route('checklist.review.reopen', $submission->id) }}" style="flex:none"
                          onsubmit="return confirm('Reopen checklist ini? Status akan kembali ke Menunggu Approval.');">
                        @csrf @method('PATCH')
                        <button type="submit" class="rvd-btn rvd-btn--brand">
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 0 1 10-4.5L14 5M14 8a6 6 0 0 1-10 4.5L2 11M14 2v3h-3M2 14v-3h3" stroke="#FFEAD6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Reopen
                        </button>
                    </form>
                </div>
            @elseif($isExcused)
                @if($submission->isSystemExcused())
                    {{-- Otomatis dari cuti/izin approved. Tombol batal sengaja tidak ada:
                         pengecualiannya akan dipasang ulang oleh reconcile, jadi yang perlu
                         dikoreksi adalah pengajuannya, bukan checklist-nya. --}}
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                        <svg width="17" height="17" viewBox="0 0 16 16" fill="none" style="flex:none"><rect x="3.2" y="7" width="9.6" height="6.6" rx="1.8" stroke="#9B8578" stroke-width="1.6"/><path d="M5.6 7V5.4a2.4 2.4 0 0 1 4.8 0V7" stroke="#9B8578" stroke-width="1.6" stroke-linecap="round"/></svg>
                        <div style="flex:1;min-width:240px">
                            <div style="font-size:13.5px;font-weight:800;color:#3B1500">Tidak ada keputusan yang perlu diambil</div>
                            <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Pengecualian otomatis dari pengajuan cuti/izin yang disetujui — koreksinya lewat modul Kehadiran.</div>
                        </div>
                    </div>
                @else
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                        <div style="flex:1;min-width:260px">
                            <div style="font-size:13.5px;font-weight:800;color:#3B1500">Hari ini ditandai tidak perlu diisi oleh karyawan</div>
                            <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Batalkan pengecualian kalau hari itu seharusnya tetap diisi — karyawan akan diminta mengisi checklist.</div>
                        </div>
                        <form method="POST" action="{{ route('checklist.review.excuse.revoke', $submission->id) }}" style="flex:none"
                              onsubmit="return confirm('Batalkan pengecualian ini? Karyawan akan diminta mengisi checklist.');">
                            @csrf @method('PATCH')
                            <button type="submit" class="rvd-btn rvd-btn--xcpt">Batalkan Pengecualian</button>
                        </form>
                    </div>
                @endif
            @endif
        </div>
    @endif

    </div>{{-- /rvd-wrap --}}
</div>

<script>
(function () {
    // Lipat / buka kategori.
    document.querySelectorAll('[data-rvd-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var cat = head.closest('.rvd-cat');
            if (cat) cat.classList.toggle('is-collapsed');
        });
    });

    // Lompat dari temuan ke barisnya: buka kategorinya, geser ke bawah bilah
    // atas, lalu kedipkan barisnya. Satu-satunya idiom navigasi di halaman ini.
    document.querySelectorAll('[data-rvd-jump]').forEach(function (row) {
        row.addEventListener('click', function () {
            var key    = row.getAttribute('data-rvd-jump');
            var target = document.querySelector('[data-rvd-row="' + key + '"]');
            if (!target) return;
            var cat = target.closest('.rvd-cat');
            if (cat) cat.classList.remove('is-collapsed');
            window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 120, behavior: 'smooth' });
            target.classList.remove('rvd-flash');
            void target.offsetWidth;
            target.classList.add('rvd-flash');
            setTimeout(function () { target.classList.remove('rvd-flash'); }, 2500);
        });
    });

    // Tombol Tolak bicara sendiri: selama alasan kosong labelnya "Tulis alasan
    // dulu" — tidak ada tombol mati tanpa penjelasan, jadi tidak butuh pesan
    // error terpisah.
    var ta = document.getElementById('reviewNotes');
    var bt = document.getElementById('btnTolak');
    if (ta && bt) {
        var sync = function () {
            var armed = ta.value.trim().length > 0;
            bt.classList.toggle('is-armed', armed);
            bt.textContent = armed ? 'Tolak' : 'Tulis alasan dulu';
        };
        ta.addEventListener('input', sync);
        sync();
    }
})();
</script>
@endsection
