@extends('layouts.app')

@section('content')
@php
    /* ─────────────────────────────────────────────────────────────────────
     * Detail Review Form Mingguan — mengikuti handoff desain
     * "design_handoff_review_checklist" (gelombang 1: tampilan + panel
     * temuan; toast/Urungkan dan pintasan keyboard SENGAJA belum dikerjakan).
     *
     * Bahasa visualnya sengaja disamakan dengan Review Checklist Harian —
     * sebelum ini dua halaman ini lahir dari dua generasi desain dan
     * berdampingan terasa seperti dua aplikasi berbeda.
     *
     * Yang khas mingguan dan tidak boleh hilang:
     *   1. Keputusan diambil PER HARI, minggu cuma wadahnya. Senin klinik tutup.
     *   2. Foto bukti (Before/After divisi GS, atau foto tunggal) — foto wajib
     *      yang kosong adalah peringatan keras, bukan catatan kecil.
     *   3. Rail hari di atas = tulang punggung halaman: sisa pekerjaan seminggu
     *      harus terbaca sekali lihat tanpa scroll.
     * ───────────────────────────────────────────────────────────────────── */

    $stMap = [
        'menunggu'     => ['label' => 'Menunggu review', 'short' => 'Menunggu',     'c' => '#B5701A', 'bg' => '#FBEFD9'],
        'disetujui'    => ['label' => 'Disetujui',        'short' => 'Disetujui',    'c' => '#2F8E55', 'bg' => '#E3F4E8'],
        'ditolak'      => ['label' => 'Ditolak',          'short' => 'Ditolak',      'c' => '#C0392B', 'bg' => '#FBE3DE'],
        'dikecualikan' => ['label' => 'Dikecualikan',     'short' => 'Dikecualikan', 'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
        'tidak-diisi'  => ['label' => 'Tidak Diisi',      'short' => 'Tidak Diisi',  'c' => '#9B8578', 'bg' => '#EFE7DE'],
        'belum-kirim'  => ['label' => 'Belum Dikirim',    'short' => 'Belum Dikirim','c' => '#9B8578', 'bg' => '#EFE7DE'],
    ];

    $tal        = $submission->tally();
    $miss       = $submission->missingPhotos();
    $dayLabels  = \App\Models\ChecklistTask::dayLabels();
    $dayShorts  = \App\Models\ChecklistTask::dayShortLabels();
    $today      = \Illuminate\Support\Carbon::today();

    $pct = $tal['total'] ? (int) round($tal['beres'] / $tal['total'] * 100) : 0;

    // Avatar 2 huruf + warna per orang (palet peta-tema §3) — disamakan dengan
    // halaman harian; sebelumnya di sini cuma 1 huruf warna tunggal.
    $avPalette  = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avName     = trim($submission->user?->nama ?? '');
    $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
    $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
    $avColor    = $avPalette[abs(crc32($avName !== '' ? $avName : '?')) % 8];

    $returnUser  = request('return_user');
    $returnMonth = request('return_month');
    $backUrl     = $returnUser
        ? route('checklist.review.weekly.employee', array_filter(['user' => $returnUser, 'month' => $returnMonth]))
        : route('checklist.review.weekly');

    /* ── Susun hari sekali jalan: status, hitungan, tugas, temuan ── */
    $days = [];
    foreach ($dayLabels as $iso => $label) {
        if (! $grouped->has($iso)) continue;

        $items    = $grouped->get($iso);
        $date     = $submission->week_start_date->copy()->addDays($iso - 1);
        $exReason = $excusedDays[$iso] ?? null;
        $dayRev   = $dayReviews[$iso] ?? null;
        $revSt    = $dayRev->review_status ?? null;

        if ($exReason)                        { $status = 'dikecualikan'; }
        elseif ($revSt === 'approved')        { $status = 'disetujui'; }
        elseif ($revSt === 'pending')         { $status = 'menunggu'; }
        elseif ($revSt === 'rejected')        { $status = 'ditolak'; }
        elseif ($date->lt($today))            { $status = 'tidak-diisi'; }
        else                                  { $status = 'belum-kirim'; }

        $dt   = $submission->dayTally($iso);
        $pat  = $submission->dayPattern($iso);
        $rows = [];
        $ti   = 0;
        foreach ($items as $item) {
            $isX = (bool) $item->is_excepted || (bool) $exReason;
            $isC = ! $isX && (bool) $item->is_done;
            $jam = ($isC && $item->done_at) ? $item->done_at->format('H:i') : null;

            // Foto bukti: Before/After (GS) atau foto satuan. Sejak multi-foto,
            // tiap slot bisa berisi sampai 5 foto → 'urls' (array), bukan 'url'.
            $photos  = [];
            $warn    = null;
            if ($item->photo_before_after) {
                foreach (['before' => 'BEFORE', 'after' => 'AFTER'] as $slot => $cap) {
                    $urls = $item->photosFor($slot)->map(fn ($p) => asset($p->path))->all();
                    $photos[] = ['cap' => $cap, 'urls' => $urls];
                    if (! $urls && ! $isX) $warn = 'Perlu foto ' . $cap . ' — belum diunggah';
                }
            } elseif ($item->requires_photo) {
                $urls = $item->photosFor('single')->map(fn ($p) => asset($p->path))->all();
                $photos[] = ['cap' => 'FOTO', 'urls' => $urls];
                if (! $urls && ! $isX) $warn = 'Perlu foto — belum diunggah';
            }

            $rows[] = [
                'key'       => $iso . '-' . $ti,
                'item'      => $item,
                'isC'       => $isC,
                'isX'       => $isX,
                'jam'       => $jam,
                'inCluster' => $pat && $jam !== null && $jam === $pat['time'],
                'photos'    => $photos,
                'warn'      => $warn,
            ];
            $ti++;
        }

        $days[] = [
            'iso'      => $iso,
            'label'    => $label,
            'short'    => $dayShorts[$iso] ?? '—',
            'date'     => $date,
            'status'   => $status,
            'st'       => $stMap[$status],
            'tally'    => $dt,
            'pattern'  => $pat,
            'rows'     => $rows,
            'exReason' => $exReason,
            'rev'      => $dayRev,
            'missCount'=> count(array_filter($miss, fn ($m) => $m['iso'] === $iso)),
        ];
    }

    $pendingDays  = array_values(array_filter($days, fn ($d) => $d['status'] === 'menunggu'));
    $approvedDays = array_values(array_filter($days, fn ($d) => $d['status'] === 'disetujui'));
    $notSentDays  = array_values(array_filter($days, fn ($d) => $d['status'] === 'tidak-diisi'));
    $patternDays  = array_values(array_filter($days, fn ($d) => $d['pattern'] !== null));

    $isExcusedWeek = $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_EXCUSED;
    $canReopenWeek = $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_APPROVED;

    // Status minggu diturunkan dari status hari-harinya, bukan dibaca mentah
    // dari kolom header — supaya pill di atas tidak pernah bertentangan dengan
    // apa yang terlihat di kartu hari.
    $weekStKey = $isExcusedWeek ? 'dikecualikan'
        : (count($pendingDays) ? 'menunggu'
        : (count(array_filter($days, fn ($d) => $d['status'] === 'ditolak')) ? 'ditolak' : 'disetujui'));
    $weekSt = $stMap[$weekStKey];

    /* ── YANG PERLU DILIHAT — urutan prioritas tetap ── */
    $findings = [];

    if (count($pendingDays)) {
        $findings[] = [
            'dot'    => '#B5701A',
            'label'  => count($pendingDays) . ' hari menunggu review',
            'sub'    => implode(' · ', array_map(fn ($d) => $d['label'] . ' ' . $d['date']->format('d'), $pendingDays)),
            'target' => 'hari-' . $pendingDays[0]['iso'],
        ];
    }

    if (count($miss)) {
        $detail = array_map(
            fn ($m) => ($dayShorts[$m['iso']] ?? '') . ' ' . $submission->week_start_date->copy()->addDays($m['iso'] - 1)->format('d')
                . ' · ' . $m['which'] . ' — ' . $m['item']->task_title_snapshot,
            array_slice($miss, 0, 3)
        );
        $findings[] = [
            'dot'    => '#C0392B',
            'label'  => count($miss) . ' foto wajib belum diunggah',
            'sub'    => implode(' · ', $detail) . (count($miss) > 3 ? ' · dan ' . (count($miss) - 3) . ' lagi' : ''),
            'target' => 'hari-' . $miss[0]['iso'],
        ];
    }

    if (count($notSentDays)) {
        $findings[] = [
            'dot'    => '#9B8578',
            'label'  => count($notSentDays) . ' hari operasional lewat tanpa pengisian',
            'sub'    => implode(' · ', array_map(fn ($d) => $d['label'] . ' ' . $d['date']->format('d'), $notSentDays)) . ' — tidak pernah dikirim karyawan.',
            'target' => 'hari-' . $notSentDays[0]['iso'],
        ];
    }

    if (count($patternDays)) {
        $findings[] = [
            'dot'    => '#C9962F',
            'label'  => 'Pola pengisian: ' . count($patternDays) . ' hari dicentang menumpuk di satu menit',
            'sub'    => implode(' · ', array_map(
                    fn ($d) => $d['short'] . ' ' . $d['date']->format('d') . ' — ' . $d['pattern']['count'] . ' tugas jam ' . $d['pattern']['time'],
                    $patternDays
                )) . '. Informasi saja, supervisor yang menilai.',
            'target' => 'hari-' . $patternDays[0]['iso'],
        ];
    }

    $bersih = empty($findings);
    if ($bersih) {
        $findings[] = [
            'dot'    => '#2F8E55',
            'label'  => $isExcusedWeek ? 'Minggu ini dikecualikan — tidak ada yang perlu dicek.' : 'Tidak ada yang perlu dicek.',
            'sub'    => $isExcusedWeek
                ? 'Semua hari ditandai tidak perlu diisi.'
                : 'Semua hari sudah diputuskan, foto lengkap, dan jamnya wajar. Tidak ada pekerjaan tersisa di minggu ini.',
            'target' => null,
        ];
    }
    $findings = array_slice($findings, 0, 4);
    $findMeta = $bersih ? 'bersih' : count($findings) . ' hal';
@endphp

<style>
    /* ── Detail review mingguan — handoff design_handoff_review_checklist ── */
    .wkr-wrap { width:100%; margin:0 auto; display:flex; flex-direction:column; gap:14px; }
    .wkr-card { background:#fff; border:1px solid #EFE0CE; border-radius:18px; box-shadow:0 1px 2px rgba(70,21,0,.04); }
    .wkr-num  { font-variant-numeric:tabular-nums; font-feature-settings:"tnum" 1; }

    .wkr-back { display:inline-flex; align-items:center; gap:8px; height:38px; padding:0 15px 0 12px; border-radius:11px; border:1px solid #E7D3BE; background:#fff; color:#5A3A22; font-size:13px; font-weight:700; text-decoration:none; transition:background .12s ease, border-color .12s ease; }
    .wkr-back:hover { background:#FBF4EC; border-color:#D9C3AC; color:#5A3A22; }

    /* Rail hari — menempel di bawah header aplikasi (tinggi ±69px). */
    .wkr-rail { position:sticky; top:69px; z-index:29; margin:0 -15px 0; padding:11px 15px; background:rgba(250,244,236,.94); backdrop-filter:blur(8px); border-bottom:1px solid #EFE0CE; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    @media (max-width:1024px) { .wkr-rail { top:65px; } }
    .wkr-rail__tutup { display:inline-flex; align-items:center; background:#F1E6D9; color:#9B8578; font-size:11px; font-weight:700; padding:8px 11px; border-radius:12px; white-space:nowrap; }
    .wkr-chip { display:flex; align-items:center; gap:9px; border-radius:12px; padding:6px 11px; font-family:inherit; cursor:pointer; color:#3B1500; text-align:left; border:1px solid #EFE0CE; background:#FAF3EA; opacity:.66; transition:filter .12s ease; }
    .wkr-chip:hover { filter:brightness(.97); }
    /* Hari yang masih menunggu keputusan menonjol; yang sudah selesai meredup —
       sisa pekerjaan seminggu terbaca tanpa membaca satu kata pun. */
    .wkr-chip--on { border-color:#E0C09A; background:#fff; opacity:1; box-shadow:0 3px 10px rgba(70,21,0,.10); }
    .wkr-chip__pill { font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:20px; white-space:nowrap; }

    /* Panel "Yang perlu dilihat" */
    .wkr-panel__head { display:flex; align-items:center; gap:10px; padding:14px 20px; background:linear-gradient(120deg,#5A2410,#3A1100); color:#FBEADB; }
    .wkr-find { display:flex; align-items:flex-start; gap:12px; padding:14px 20px; border-bottom:1px solid #F4EADD; background:#fff; transition:background .12s ease; }
    .wkr-find:last-child { border-bottom:none; }
    .wkr-find--ok { background:#F4FAF6; }
    .wkr-find--jump { cursor:pointer; }
    .wkr-find--jump:hover { background:#FBF4EC; }
    .wkr-find--jump:hover .wkr-chev { opacity:1; transform:translateX(2px); }
    .wkr-dot { width:9px; height:9px; flex:none; border-radius:50%; margin-top:5px; }
    .wkr-chev { opacity:.8; flex:none; transition:opacity .12s ease, transform .12s ease; }

    /* Kartu hari */
    .wkr-day { background:#fff; border:1px solid #EFE0CE; border-radius:18px; overflow:hidden; box-shadow:0 1px 2px rgba(70,21,0,.04); opacity:.92; }
    .wkr-day--on { border-color:#E0C09A; opacity:1; }
    .wkr-day__head { display:flex; align-items:center; gap:14px; padding:14px 18px; cursor:pointer; user-select:none; transition:background .12s ease; }
    .wkr-day__head:hover { background:#FBF4EC; }
    .wkr-day__head:hover .wkr-chev { opacity:1; transform:translateX(2px); }
    .wkr-day__date { width:48px; height:48px; flex:none; border-radius:14px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:1px; color:#FBEADB; background:linear-gradient(135deg,#8A6B57,#6E5342); }
    .wkr-day--on .wkr-day__date { background:linear-gradient(135deg,#6B2400,#3A1100); }
    .wkr-day__caret { transition:transform .18s ease; transform:rotate(90deg); }
    .wkr-day.is-collapsed .wkr-day__caret { transform:rotate(0deg); }
    .wkr-day.is-collapsed .wkr-day__body { display:none; }
    .wkr-day__pill { font-size:11.5px; font-weight:800; padding:5px 12px; border-radius:20px; white-space:nowrap; }

    /* Baris tugas — sama persis dengan halaman harian */
    .wkr-task { padding:13px 18px; border-top:1px solid #F6EEE1; }
    .wkr-task--todo { background:#FFFDFA; }
    .wkr-task__row { display:flex; align-items:flex-start; gap:12px; }
    .wkr-task__box { width:22px; height:22px; flex:none; margin-top:1px; border-radius:7px; display:flex; align-items:center; justify-content:center; }
    .wkr-task__box--done { background:#2F8E55; border:1px solid #2F8E55; }
    .wkr-task__box--xcpt { background:#8A5A2B; border:1px solid #8A5A2B; }
    .wkr-task__box--todo { background:#fff; border:1.5px solid #D8C3AC; }
    .wkr-task__title { flex:1; min-width:0; font-size:13.5px; font-weight:600; line-height:1.5; color:#A9866A; }
    .wkr-task__title--done { color:#3B2A1E; }
    .wkr-task__time { font-size:12px; font-weight:700; padding:5px 9px; border-radius:8px; white-space:nowrap; color:#7A5B43; background:#F4E7D6; font-variant-numeric:tabular-nums; }
    .wkr-task__time--cluster { color:#B5701A; background:#FBEFD9; }
    .wkr-task__pill { font-size:11px; font-weight:800; padding:4px 10px; border-radius:20px; white-space:nowrap; }
    .wkr-sub { margin:9px 0 0 34px; border-radius:11px; padding:9px 12px; }
    .wkr-sub--note { background:#FBF5EE; border:1px solid #F0E2D2; }
    .wkr-sub--xcpt { background:#F7EFE4; border-left:3px solid #8A5A2B; border-radius:0 11px 11px 0; }
    .wkr-sub__cap { font-size:10.5px; font-weight:800; letter-spacing:.5px; }
    .wkr-sub__body { font-size:12.5px; color:#5A3A22; margin-top:4px; line-height:1.5; }

    /* Foto bukti: ada = grid thumbnail; kosong = chip putus-putus yang jelas mati */
    .wkr-photos { margin:10px 0 0 34px; display:flex; align-items:flex-start; gap:16px; flex-wrap:wrap; }
    .wkr-photo { display:inline-flex; align-items:center; gap:7px; border-radius:11px; padding:8px 12px; font-family:inherit; font-size:12px; font-weight:800; background:#fff; color:#5A3A22; border:1px solid #E7D3BE; cursor:pointer; transition:filter .12s ease; }
    .wkr-photo:hover { filter:brightness(.96); }
    .wkr-photo--empty { background:#FBF5EE; color:#B79A82; border:1.5px dashed #DCC9B2; cursor:default; }
    .wkr-photo--empty:hover { filter:none; }
    .wkr-photogrp { display:flex; flex-direction:column; gap:6px; }
    .wkr-photogrp__cap { font-size:10.5px; font-weight:800; letter-spacing:.7px; color:#9A7256; }
    .wkr-grid { display:flex; flex-wrap:wrap; gap:7px; max-width:460px; }
    .wkr-thumb { width:72px; height:72px; padding:0; border:1px solid #E7D3BE; border-radius:11px; overflow:hidden; background:#EFE0CE; cursor:pointer; flex:0 0 auto; transition:filter .12s ease; }
    .wkr-thumb:hover { filter:brightness(.94); }
    .wkr-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .wkr-warn { margin:9px 0 0 34px; display:inline-flex; align-items:center; gap:8px; background:#FBE3DE; border-radius:11px; padding:8px 12px; font-size:12px; font-weight:800; color:#C0392B; }

    /* Aksi per hari */
    .wkr-act { padding:14px 18px; background:#FCF7F0; border-top:1px solid #F1E6D7; }
    .wkr-trail { display:flex; align-items:center; gap:12px; padding:14px 18px; flex-wrap:wrap; }
    .wkr-trail--ok  { background:#E3F4E8; border-top:1px solid #CBE7D5; }
    .wkr-trail--bad { background:#FBE3DE; border-top:1px solid #F0CFC6; }
    .wkr-trail--off { background:#EFE7DE; border-top:1px solid #E3D6C8; }
    .wkr-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:11px; padding:11px 17px; font-weight:800; font-size:13.5px; font-family:inherit; cursor:pointer; border:1px solid transparent; transition:filter .12s ease; }
    .wkr-btn:hover { filter:brightness(.96); }
    .wkr-btn--ok { background:#2F8E55; color:#fff; }
    .wkr-btn--bad { background:#fff; color:#C0392B; border-color:#F0CFC6; }
    .wkr-btn--ghost { background:#fff; color:#5A3A22; border-color:#E7D3BE; border-radius:10px; padding:9px 14px; font-size:12.5px; font-weight:700; }
    .wkr-btn--brand { background:linear-gradient(135deg,#6B2400,#461500); color:#FFEAD6; padding:12px 18px; }
    .wkr-btn--xcpt { background:#8A5A2B; color:#FFF3E6; padding:12px 18px; }
    /* Konfirmasi tolak: selama alasan kosong tombolnya BILANG kenapa. */
    .wkr-btn--tolak { background:#FBF5EE; color:#A9866A; border-color:#E7D3BE; }
    .wkr-btn--tolak.is-armed { background:#C0392B; color:#fff; border-color:transparent; }
    .wkr-ta { width:100%; box-sizing:border-box; margin-top:6px; border:1px solid #E7D3BE; border-radius:11px; padding:10px 12px; font-family:inherit; font-size:13px; color:#3B1500; background:#FFFCF8; resize:vertical; line-height:1.5; transition:border-color .12s ease, background .12s ease; }
    .wkr-ta:focus { outline:none; border-color:#8A5A2B; background:#fff; }
    .wkr-ta::placeholder { color:#C7AC92; }

    /* Kaki halaman menempel */
    .wkr-foot { position:sticky; bottom:14px; z-index:20; background:#fff; border:1px solid #E7D3BE; border-radius:18px; padding:16px 18px; box-shadow:0 -2px 4px rgba(70,21,0,.03),0 14px 34px rgba(70,21,0,.16); }

    .wkr-tile { background:#FBF5EE; border:1px solid #EFE0CE; border-radius:12px; padding:10px 14px; min-width:96px; }
    .wkr-tile__n { font-size:17px; font-weight:800; font-variant-numeric:tabular-nums; }
    .wkr-tile__l { font-size:11px; font-weight:700; color:#9A7256; margin-top:2px; }

    /* Modal foto */
    .wkr-modal { position:fixed; inset:0; background:rgba(24,8,0,.78); z-index:1080; display:flex; align-items:center; justify-content:center; padding:34px; animation:wkrFade .16s ease; }
    .wkr-modal[hidden] { display:none; }
    .wkr-modal__box { width:100%; max-width:820px; background:#FBF5EE; border-radius:18px; overflow:hidden; box-shadow:0 26px 70px rgba(0,0,0,.45); }
    .wkr-modal__head { display:flex; align-items:center; gap:12px; padding:15px 18px; background:linear-gradient(120deg,#5A2410,#3A1100); color:#FBEADB; }
    .wkr-modal__x { width:32px; height:32px; flex:none; border-radius:9px; background:rgba(255,255,255,.12); border:none; color:#FBEADB; font-size:15px; font-family:inherit; cursor:pointer; }
    .wkr-modal__img { width:100%; max-height:60vh; object-fit:contain; border-radius:14px; background:#EFE0CE; display:block; }
    .wkr-modal__stage { position:relative; }
    .wkr-nav { position:absolute; top:50%; transform:translateY(-50%); width:40px; height:40px; border-radius:50%; background:rgba(24,8,0,.62); color:#FBEADB; border:none; font-size:18px; font-family:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .wkr-nav:hover { background:rgba(24,8,0,.84); }
    .wkr-nav[hidden] { display:none; }
    .wkr-nav--prev { left:10px; }
    .wkr-nav--next { right:10px; }

    @keyframes wkrFade { from { opacity:0; } to { opacity:1; } }
    @keyframes wkrFlash { 0% { background:#FBEFD9; } 100% { background:transparent; } }
    .wkr-flash { animation:wkrFlash 2.4s ease-out; }

    @media (prefers-reduced-motion: reduce) {
        .wkr-flash, .wkr-modal { animation:none; }
        .wkr-day__caret, .wkr-chev, .wkr-btn { transition:none; }
    }
</style>

<div class="container-fluid">

    @include('layouts.notif')

    <div class="wkr-wrap">

    {{-- ─── KEMBALI ─── --}}
    <div>
        <a href="{{ $backUrl }}" class="wkr-back">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M10 3.5 5.5 8l4.5 4.5" stroke="#7A5B43" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Kembali
        </a>
    </div>

    {{-- ─── RAIL HARI ─── --}}
    <div class="wkr-rail">
        <span class="wkr-rail__tutup">Sen · klinik tutup</span>
        @foreach($days as $d)
            <button type="button" class="wkr-chip {{ $d['status'] === 'menunggu' ? 'wkr-chip--on' : '' }}" data-wkr-jump="hari-{{ $d['iso'] }}">
                <span style="display:flex;flex-direction:column;align-items:flex-start;line-height:1.25">
                    <span class="wkr-num" style="font-size:12.5px;font-weight:800">{{ $d['short'] }} {{ $d['date']->format('d') }}</span>
                    <span class="wkr-num" style="font-size:10.5px;font-weight:600;opacity:.8">{{ $d['tally']['beres'] }}/{{ $d['tally']['total'] }} tugas</span>
                </span>
                <span class="wkr-chip__pill" style="color:{{ $d['st']['c'] }};background:{{ $d['st']['bg'] }}">{{ $d['st']['short'] }}</span>
            </button>
        @endforeach
        <span class="wkr-num" style="margin-left:auto;font-size:12px;color:#9A7256;font-weight:600">{{ count($pendingDays) }} hari menunggu · {{ count($approvedDays) }} disetujui</span>
    </div>

    {{-- ─── IDENTITAS + ANGKA PROGRES ─── --}}
    <div class="wkr-card" style="padding:20px 22px">
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
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex:none">
                <span style="display:inline-flex;align-items:center;font-size:11.5px;font-weight:800;padding:5px 12px;border-radius:20px;white-space:nowrap;background:{{ $weekSt['bg'] }};color:{{ $weekSt['c'] }}">{{ $weekSt['label'] }}</span>
                <span style="font-size:15px;font-weight:800;color:#3B1500">Minggu {{ $submission->weekLabel() }}</span>
                <span class="wkr-num" style="font-size:11.5px;font-weight:600;color:#9A7256">{{ $submission->year_week }} · Minggu ke-{{ $submission->weekOfMonth() }} bulan ini</span>
            </div>
        </div>

        <div class="wkr-num" style="font-size:11.5px;color:#A9866A;font-weight:600;margin-top:14px">
            {{ $submission->submitted_at ? 'Disubmit ' . $submission->submitted_at->format('d M · H:i') : 'Belum pernah dikirim' }} · {{ count($pendingDays) }} dari {{ count($days) }} hari menunggu keputusan
        </div>

        @if($submission->submit_note)
            <div style="margin-top:12px;background:#FBF5EE;border:1px solid #F0E2D2;border-radius:12px;padding:11px 14px">
                <div class="wkr-sub__cap" style="color:#A9866A">CATATAN KARYAWAN SAAT MENGIRIM</div>
                <div class="wkr-sub__body">{{ $submission->submit_note }}</div>
            </div>
        @endif

        <div style="height:1px;background:#F4EADD;margin:16px 0 15px"></div>

        <div style="display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap">
            <div style="flex:1;min-width:240px">
                <div style="height:10px;border-radius:6px;background:#F1E6D9;overflow:hidden">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $isExcusedWeek ? '#C7AC92' : ($pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B')) }};border-radius:6px;transition:width .3s ease"></div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap">
                    <span class="wkr-num" style="font-size:24px;font-weight:800;color:#3B1500;line-height:1">{{ $tal['beres'] }} / {{ $tal['total'] }} tugas</span>
                    <span style="font-size:12px;font-weight:700;color:#9A7256;letter-spacing:.3px">BERES</span>
                </div>
                <div class="wkr-num" style="font-size:12.5px;color:#7A5B43;font-weight:600;margin-top:6px">
                    {{ $tal['checked'] }} dicentang · {{ $tal['excluded'] }} dikecualikan @if($tal['unchecked'] > 0) · {{ $tal['unchecked'] }} belum dicentang @endif
                </div>
            </div>
            <div style="display:flex;gap:9px;flex-wrap:wrap">
                <div class="wkr-tile">
                    <div class="wkr-tile__n" style="color:{{ count($pendingDays) ? '#B5701A' : '#B79A82' }}">{{ count($pendingDays) }}</div>
                    <div class="wkr-tile__l">Hari menunggu</div>
                </div>
                <div class="wkr-tile">
                    <div class="wkr-tile__n" style="color:{{ count($miss) ? '#C0392B' : '#B79A82' }}">{{ count($miss) }}</div>
                    <div class="wkr-tile__l">Foto belum ada</div>
                </div>
                <div class="wkr-tile">
                    <div class="wkr-tile__n" style="color:#2F8E55">{{ count($approvedDays) }}</div>
                    <div class="wkr-tile__l">Hari disetujui</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─── YANG PERLU DILIHAT ─── --}}
    <div class="wkr-card" style="overflow:hidden">
        <div class="wkr-panel__head">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="#FBEADB" stroke-width="1.6"/><path d="M8 5.2v.1M8 7.4v3.4" stroke="#FBEADB" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span style="font-size:13px;font-weight:800;letter-spacing:.4px">YANG PERLU DILIHAT</span>
            <span style="margin-left:auto;font-size:11.5px;color:#D6A878;font-weight:600">{{ $findMeta }}</span>
        </div>
        @foreach($findings as $f)
            <div class="wkr-find {{ $bersih ? 'wkr-find--ok' : '' }} {{ $f['target'] ? 'wkr-find--jump' : '' }}"
                 @if($f['target']) data-wkr-jump="{{ $f['target'] }}" @endif>
                <span class="wkr-dot" style="background:{{ $f['dot'] }}"></span>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13.5px;font-weight:700;color:#3B1500">{{ $f['label'] }}</div>
                    <div style="font-size:12px;color:#7A5B43;margin-top:3px;line-height:1.5">{{ $f['sub'] }}</div>
                </div>
                @if($f['target'])
                    <svg class="wkr-chev" width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="#C9AE94" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
            </div>
        @endforeach
    </div>

    {{-- ─── BANNER: MINGGU DIKECUALIKAN ─── --}}
    @if($isExcusedWeek && $submission->excuse_reason)
        <div style="background:#F2E7D8;border:1px solid #E3D0B7;border-left:4px solid #8A5A2B;border-radius:16px;padding:16px 18px">
            <div style="display:flex;align-items:center;gap:9px">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.2" stroke="#8A5A2B" stroke-width="1.6"/><path d="M4.4 11.6 11.6 4.4" stroke="#8A5A2B" stroke-width="1.6" stroke-linecap="round"/></svg>
                <span style="font-size:13.5px;font-weight:800;color:#8A5A2B">Minggu ini ditandai tidak perlu diisi</span>
            </div>
            <div style="font-size:13px;color:#5A3A22;margin-top:9px;line-height:1.55;font-style:italic">“{{ $submission->excuse_reason }}”</div>
        </div>
    @endif

    {{-- ─── CATATAN SUPERVISOR TINGKAT MINGGU ─── --}}
    @if($submission->review_notes)
        @php
            $wOk  = $submission->status !== \App\Models\ChecklistWeeklySubmission::STATUS_REJECTED;
            $wCol = $wOk ? '#2F8E55' : '#C0392B';
        @endphp
        <div style="border-radius:16px;padding:15px 18px;background:{{ $wOk ? '#E3F4E8' : '#FBE3DE' }};border:1px solid {{ $wOk ? '#CBE7D5' : '#F0CFC6' }}">
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                <span style="width:10px;height:10px;border-radius:50%;flex:none;background:{{ $wCol }}"></span>
                <span style="font-size:13.5px;font-weight:800;color:{{ $wCol }}">Catatan supervisor</span>
                @if($submission->reviewer)
                    <span class="wkr-num" style="font-size:12px;color:#7A5B43;font-weight:600">· {{ $submission->reviewer->nama }}{{ $submission->reviewed_at ? ' · ' . $submission->reviewed_at->format('d M, H:i') : '' }}</span>
                @endif
            </div>
            <div style="font-size:13px;color:#3B1500;margin-top:9px;line-height:1.55">{{ $submission->review_notes }}</div>
        </div>
    @endif

    {{-- ─── KARTU PER HARI ─── --}}
    @if(empty($days))
        <div class="wkr-card" style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
            Form Mingguan ini tidak memiliki item.
        </div>
    @else
        @foreach($days as $d)
            @php $isPendingDay = $d['status'] === 'menunggu'; @endphp
            {{-- Terbuka dari awal HANYA hari yang menunggu keputusan — halaman
                 langsung membuka di pekerjaan yang tersisa. --}}
            <div class="wkr-day {{ $isPendingDay ? 'wkr-day--on' : 'is-collapsed' }}" id="hari-{{ $d['iso'] }}">
                <div class="wkr-day__head" data-wkr-toggle>
                    <div class="wkr-day__date">
                        <span class="wkr-num" style="font-size:18px;font-weight:800;line-height:1">{{ $d['date']->format('d') }}</span>
                        <span style="font-size:10px;font-weight:700;letter-spacing:.5px;opacity:.85">{{ strtoupper($d['short']) }}</span>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:14.5px;font-weight:800;color:#3B1500">{{ $d['label'] }}</div>
                        <div class="wkr-num" style="font-size:11.5px;color:#9A7256;font-weight:600;margin-top:2px">
                            {{ $d['date']->translatedFormat('d F Y') }}@if($d['pattern']) · centang menumpuk jam {{ $d['pattern']['time'] }} @endif
                        </div>
                    </div>
                    <span class="wkr-num wkr-day__pill" style="{{ $d['tally']['beres'] === $d['tally']['total'] ? 'color:#2F8E55;background:#E3F4E8' : 'color:#B5701A;background:#FBEFD9' }}">{{ $d['tally']['beres'] }}/{{ $d['tally']['total'] }} tugas</span>
                    <span class="wkr-day__pill" style="color:{{ $d['st']['c'] }};background:{{ $d['st']['bg'] }}">{{ $d['st']['label'] }}</span>
                    <svg class="wkr-chev wkr-day__caret" width="17" height="17" viewBox="0 0 16 16" fill="none"><path d="M6 3l5 5-5 5" stroke="#C9AE94" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>

                <div class="wkr-day__body" style="border-top:1px solid #F1E6D7">

                    @if($d['exReason'])
                        <div style="margin:14px 18px 0;background:#F2E7D8;border-left:3px solid #8A5A2B;border-radius:0 12px 12px 0;padding:11px 14px">
                            <div style="font-size:12.5px;font-weight:800;color:#8A5A2B">Hari ini ditandai tidak perlu diisi</div>
                            <div class="wkr-sub__body">{{ $d['exReason'] }}</div>
                        </div>
                    @endif

                    @foreach($d['rows'] as $r)
                        @php $it = $r['item']; @endphp
                        <div class="wkr-task {{ $r['isC'] ? '' : 'wkr-task--todo' }}" data-wkr-row="{{ $r['key'] }}">
                            <div class="wkr-task__row">
                                <span class="wkr-task__box {{ $r['isX'] ? 'wkr-task__box--xcpt' : ($r['isC'] ? 'wkr-task__box--done' : 'wkr-task__box--todo') }}">
                                    @if($r['isX'])
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="#fff" stroke-width="1.8"/><path d="M3.5 10.5 10.5 3.5" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg>
                                    @elseif($r['isC'])
                                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none"><path d="M2.5 7.5 5.5 10.5 11.5 4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                </span>
                                <span class="wkr-task__title {{ $r['isC'] ? 'wkr-task__title--done' : '' }}">{{ $it->task_title_snapshot }}</span>
                                <div style="flex:none;display:flex;align-items:center;gap:7px">
                                    @if($r['jam'])
                                        <span class="wkr-task__time {{ $r['inCluster'] ? 'wkr-task__time--cluster' : '' }}">{{ $r['jam'] }}</span>
                                    @endif
                                    @if($r['isX'])
                                        <span class="wkr-task__pill" style="background:#F2E7D8;color:#8A5A2B">Dikecualikan</span>
                                    @elseif(! $r['isC'])
                                        <span class="wkr-task__pill" style="background:#EFE7DE;color:#9B8578">Belum dicentang</span>
                                    @endif
                                </div>
                            </div>

                            @if(count($r['photos']))
                                <div class="wkr-photos">
                                    @foreach($r['photos'] as $p)
                                        @if(count($p['urls']))
                                            {{-- Satu grup = satu slot. Galeri modal dibaca dari data-wkr-gallery. --}}
                                            <div class="wkr-photogrp">
                                                <span class="wkr-photogrp__cap">{{ $p['cap'] }} · {{ count($p['urls']) }} foto</span>
                                                <div class="wkr-grid" data-wkr-gallery="{{ json_encode($p['urls']) }}"
                                                     data-cap="{{ $p['cap'] }}"
                                                     data-title="{{ $it->task_title_snapshot }}"
                                                     data-meta="{{ $d['label'] }}, {{ $d['date']->translatedFormat('d F Y') }}{{ $r['jam'] ? ' · diunggah ' . $r['jam'] : '' }}">
                                                    @foreach($p['urls'] as $i => $u)
                                                        <button type="button" class="wkr-thumb" data-wkr-photo data-index="{{ $i }}">
                                                            <img src="{{ $u }}" alt="{{ $p['cap'] }} {{ $i + 1 }}" loading="lazy">
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @else
                                            <span class="wkr-photo wkr-photo--empty">
                                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><rect x="2" y="3.2" width="12" height="9.6" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3 13 13 3.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                                                {{ $p['cap'] }} · Tidak ada
                                            </span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            @if($r['warn'])
                                <div class="wkr-warn">
                                    <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 2.6 14.4 13.4H1.6L8 2.6Z" stroke="#C0392B" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 6.6v2.6M8 11.2v.1" stroke="#C0392B" stroke-width="1.6" stroke-linecap="round"/></svg>
                                    {{ $r['warn'] }}
                                </div>
                            @endif

                            @if($it->note)
                                <div class="wkr-sub wkr-sub--note">
                                    <div class="wkr-sub__cap" style="color:#A9866A">CATATAN KARYAWAN</div>
                                    <div class="wkr-sub__body">{{ $it->note }}</div>
                                </div>
                            @endif

                            @if($it->is_excepted && $it->exception_reason)
                                <div class="wkr-sub wkr-sub--xcpt">
                                    <div class="wkr-sub__cap" style="color:#8A5A2B">ALASAN DIKECUALIKAN</div>
                                    <div class="wkr-sub__body">{{ $it->exception_reason }}</div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- ── AKSI PER HARI ── --}}
                    @if($d['status'] === 'menunggu')
                        <div class="wkr-act">
                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap" data-wkr-idle>
                                <form method="POST" action="{{ route('checklist.review.weekly.day.approve', ['id' => $submission->id, 'iso' => $d['iso']]) }}"
                                      onsubmit="return confirm('Setujui {{ $d['label'] }}?');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="wkr-btn wkr-btn--ok">
                                        <svg width="15" height="15" viewBox="0 0 14 14" fill="none"><path d="M2.5 7.5 5.5 10.5 11.5 4" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Setujui hari ini
                                    </button>
                                </form>
                                <button type="button" class="wkr-btn wkr-btn--bad" data-wkr-reject-open>Tolak</button>
                                @if($d['missCount'] > 0)
                                    <span style="font-size:11.5px;color:#A9866A;font-weight:600">{{ $d['missCount'] }} foto wajib belum ada di hari ini</span>
                                @elseif($d['pattern'])
                                    <span style="font-size:11.5px;color:#A9866A;font-weight:600">Centang menumpuk jam {{ $d['pattern']['time'] }}</span>
                                @endif
                            </div>

                            {{-- Alasan menolak ditulis DI SEBELAH harinya, bukan di kotak
                                 global yang jauh di bawah. --}}
                            <form method="POST" action="{{ route('checklist.review.weekly.day.reject', ['id' => $submission->id, 'iso' => $d['iso']]) }}"
                                  data-wkr-reject-box hidden
                                  onsubmit="var t=this.querySelector('textarea'); if(!t.value.trim()){t.focus();return false;} return confirm('Tolak {{ $d['label'] }}? Karyawan diminta perbaiki hari ini.');">
                                @csrf @method('PATCH')
                                <label class="wkr-sub__cap" style="color:#A9866A">ALASAN MENOLAK {{ strtoupper($d['label']) }} (WAJIB)</label>
                                <textarea name="review_notes" rows="2" class="wkr-ta" data-wkr-reason
                                          placeholder="Mis. foto sesudah untuk kaca depan belum diunggah."></textarea>
                                <div style="display:flex;gap:9px;margin-top:10px;flex-wrap:wrap">
                                    <button type="submit" class="wkr-btn wkr-btn--tolak" data-wkr-confirm>Tulis alasan dulu</button>
                                    <button type="button" class="wkr-btn wkr-btn--ghost" style="padding:11px 16px;font-size:13px" data-wkr-reject-cancel>Batal</button>
                                </div>
                            </form>
                        </div>
                    @elseif($d['status'] === 'disetujui')
                        <div class="wkr-trail wkr-trail--ok">
                            <svg width="16" height="16" viewBox="0 0 14 14" fill="none" style="flex:none"><path d="M2.5 7.5 5.5 10.5 11.5 4" stroke="#2F8E55" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span style="flex:1;min-width:200px;font-size:13px;font-weight:700;color:#2F8E55">
                                Disetujui @if($d['rev']?->reviewer) oleh {{ $d['rev']->reviewer->nama }} @endif @if($d['rev']?->reviewed_at) · {{ $d['rev']->reviewed_at->format('d M, H:i') }} @endif
                            </span>
                            <form method="POST" action="{{ route('checklist.review.weekly.day.reopen', ['id' => $submission->id, 'iso' => $d['iso']]) }}" style="flex:none"
                                  onsubmit="return confirm('Buka lagi review {{ $d['label'] }}? Kembali menunggu approval.');">
                                @csrf @method('PATCH')
                                <button type="submit" class="wkr-btn wkr-btn--ghost" style="border-color:#C6DFCE">Buka lagi</button>
                            </form>
                        </div>
                    @elseif($d['status'] === 'ditolak')
                        <div class="wkr-trail wkr-trail--bad" style="display:block">
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                                <span style="flex:1;min-width:200px;font-size:13px;font-weight:800;color:#C0392B">
                                    Ditolak @if($d['rev']?->reviewer) oleh {{ $d['rev']->reviewer->nama }} @endif @if($d['rev']?->reviewed_at) · {{ $d['rev']->reviewed_at->format('d M, H:i') }} @endif
                                </span>
                                <form method="POST" action="{{ route('checklist.review.weekly.day.reopen', ['id' => $submission->id, 'iso' => $d['iso']]) }}" style="flex:none"
                                      onsubmit="return confirm('Buka lagi review {{ $d['label'] }}? Kembali menunggu approval.');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="wkr-btn wkr-btn--ghost" style="border-color:#F0CFC6">Buka lagi</button>
                                </form>
                            </div>
                            @if($d['rev']?->review_notes)
                                <div style="font-size:12.5px;color:#3B1500;margin-top:7px;line-height:1.55">{{ $d['rev']->review_notes }}</div>
                            @endif
                            <div style="font-size:12px;color:#8A4A3E;margin-top:6px;font-weight:600">Menunggu karyawan mengirim ulang hari ini.</div>
                        </div>
                    @elseif($d['status'] === 'tidak-diisi')
                        <div class="wkr-trail wkr-trail--off">
                            <span style="flex:1;min-width:200px;font-size:12.5px;font-weight:700;color:#9B8578">Hari operasional ini lewat tanpa pengisian — tidak ada yang bisa disetujui.</span>
                        </div>
                    @endif

                </div>
            </div>
        @endforeach
    @endif

    {{-- ─── KAKI HALAMAN MENEMPEL ─── --}}
    @if(count($pendingDays) || $canReopenWeek || $isExcusedWeek)
        <div class="wkr-foot">
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                @if(count($pendingDays))
                    <div style="flex:1;min-width:250px">
                        <div style="font-size:13.5px;font-weight:800;color:#3B1500">Masih ada {{ count($pendingDays) }} hari yang menunggu review</div>
                        <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Atau setujui / tolak <b>per hari</b> di tiap kartu di atas.</div>
                    </div>
                    <form method="POST" action="{{ route('checklist.review.weekly.approve-all', $submission->id) }}" style="flex:none"
                          onsubmit="return confirm('Setujui semua {{ count($pendingDays) }} hari yang menunggu review?');">
                        @csrf @method('PATCH')
                        <button type="submit" class="wkr-btn wkr-btn--ok" style="padding:12px 18px">
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M1.5 8.5 4.5 11.5 10 5M8 11.5 14 5" stroke="#fff" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Setujui semua hari menunggu ({{ count($pendingDays) }})
                        </button>
                    </form>
                @elseif($isExcusedWeek)
                    <div style="flex:1;min-width:250px">
                        <div style="font-size:13.5px;font-weight:800;color:#3B1500">Minggu ini ditandai tidak perlu diisi</div>
                        <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Batalkan pengecualian kalau minggu itu seharusnya tetap diisi — karyawan akan diminta mengisi Form Mingguan.</div>
                    </div>
                    <form method="POST" action="{{ route('checklist.review.weekly.excuse.revoke', $submission->id) }}" style="flex:none"
                          onsubmit="return confirm('Batalkan pengecualian ini? Karyawan akan diminta mengisi Form Mingguan.');">
                        @csrf @method('PATCH')
                        <button type="submit" class="wkr-btn wkr-btn--xcpt">Batalkan Pengecualian</button>
                    </form>
                @else
                    <div style="flex:1;min-width:250px">
                        <div style="font-size:13.5px;font-weight:800;color:#3B1500">Seluruh minggu sudah diputuskan</div>
                        <div style="font-size:12px;color:#7A5B43;margin-top:4px;line-height:1.5">Semua hari yang sudah disetujui kembali ke Menunggu Approval kalau Anda membukanya lagi.</div>
                    </div>
                    <form method="POST" action="{{ route('checklist.review.weekly.reopen', $submission->id) }}" style="flex:none"
                          onsubmit="return confirm('Reopen Form Mingguan ini? Semua hari kembali menunggu approval.');">
                        @csrf @method('PATCH')
                        <button type="submit" class="wkr-btn wkr-btn--brand">
                            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2 8a6 6 0 0 1 10-4.5L14 5M14 8a6 6 0 0 1-10 4.5L2 11M14 2v3h-3M2 14v-3h3" stroke="#FFEAD6" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Reopen (buka semua hari)
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    </div>{{-- /wkr-wrap --}}
</div>

{{-- Modal foto bukti --}}
<div class="wkr-modal" id="wkrPhotoModal" hidden>
    <div class="wkr-modal__box" id="wkrPhotoBox">
        <div class="wkr-modal__head">
            <span style="font-size:11px;font-weight:800;letter-spacing:.7px;color:#D6A878" id="wkrPhotoCap">FOTO</span>
            <span style="flex:1;min-width:0;font-size:14px;font-weight:800" id="wkrPhotoTitle"></span>
            <button type="button" class="wkr-modal__x" data-wkr-close aria-label="Tutup">✕</button>
        </div>
        <div style="padding:18px">
            <div class="wkr-modal__stage">
                <img class="wkr-modal__img" id="wkrPhotoImg" src="" alt="Foto bukti">
                <button type="button" class="wkr-nav wkr-nav--prev" id="wkrPhotoPrev" aria-label="Foto sebelumnya" hidden>‹</button>
                <button type="button" class="wkr-nav wkr-nav--next" id="wkrPhotoNext" aria-label="Foto berikutnya" hidden>›</button>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:8px">
                <span class="wkr-num" style="font-size:12px;color:#7A5B43;font-weight:600" id="wkrPhotoMeta"></span>
                <span style="font-size:11.5px;color:#A9866A;font-weight:600" id="wkrPhotoPos"></span>
                <span style="font-size:11.5px;color:#A9866A;font-weight:600">Esc menutup, ← → menggeser</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // Lipat / buka kartu hari.
    document.querySelectorAll('[data-wkr-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var day = head.closest('.wkr-day');
            if (day) day.classList.toggle('is-collapsed');
        });
    });

    // Lompat dari rail hari / temuan ke kartu harinya: buka kartunya, geser ke
    // bawah bilah menempel, lalu kedipkan. Satu idiom navigasi untuk semuanya.
    document.querySelectorAll('[data-wkr-jump]').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = document.getElementById(el.getAttribute('data-wkr-jump'));
            if (!target) return;
            target.classList.remove('is-collapsed');
            window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 168, behavior: 'smooth' });
            target.classList.remove('wkr-flash');
            void target.offsetWidth;
            target.classList.add('wkr-flash');
            setTimeout(function () { target.classList.remove('wkr-flash'); }, 2500);
        });
    });

    // Kotak alasan tolak per hari + tombol yang bicara sendiri.
    document.querySelectorAll('[data-wkr-reject-open]').forEach(function (btn) {
        var act = btn.closest('.wkr-act');
        if (!act) return;
        var box  = act.querySelector('[data-wkr-reject-box]');
        var idle = act.querySelector('[data-wkr-idle]');
        var ta   = act.querySelector('[data-wkr-reason]');
        var ok   = act.querySelector('[data-wkr-confirm]');
        var cx   = act.querySelector('[data-wkr-reject-cancel]');
        if (!box || !ta || !ok) return;

        var sync = function () {
            var armed = ta.value.trim().length > 0;
            ok.classList.toggle('is-armed', armed);
            ok.textContent = armed ? 'Konfirmasi tolak' : 'Tulis alasan dulu';
        };
        btn.addEventListener('click', function () {
            box.hidden = false;
            if (idle) idle.hidden = true;
            sync();
            ta.focus();
        });
        if (cx) cx.addEventListener('click', function () {
            box.hidden = true;
            if (idle) idle.hidden = false;
            ta.value = '';
            sync();
        });
        ta.addEventListener('input', sync);
        sync();
    });

    // Modal foto bukti — galeri: satu slot bisa berisi sampai 5 foto.
    var modal = document.getElementById('wkrPhotoModal');
    var img   = document.getElementById('wkrPhotoImg');
    var cap   = document.getElementById('wkrPhotoCap');
    var ttl   = document.getElementById('wkrPhotoTitle');
    var meta  = document.getElementById('wkrPhotoMeta');
    var pos   = document.getElementById('wkrPhotoPos');
    var navP  = document.getElementById('wkrPhotoPrev');
    var navN  = document.getElementById('wkrPhotoNext');
    var gal = [], gi = 0;

    function show(i) {
        if (!img || !gal.length) return;
        gi = (i + gal.length) % gal.length;          // wrap-around
        img.src = gal[gi];
        if (pos)  pos.textContent = gal.length > 1 ? (gi + 1) + ' dari ' + gal.length : '';
        if (navP) navP.hidden = gal.length < 2;
        if (navN) navN.hidden = gal.length < 2;
        if (gal.length > 1) new Image().src = gal[(gi + 1) % gal.length];  // prefetch
    }
    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        if (img) img.src = '';
        gal = [];
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-wkr-photo]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var grid = btn.closest('[data-wkr-gallery]');
            if (!modal || !img || !grid) return;
            try { gal = JSON.parse(grid.dataset.wkrGallery || '[]'); }
            catch (e) { gal = []; }
            if (!gal.length) return;
            if (cap)  cap.textContent  = grid.dataset.cap || 'FOTO';
            if (ttl)  ttl.textContent  = grid.dataset.title || '';
            if (meta) meta.textContent = grid.dataset.meta || '';
            show(parseInt(btn.dataset.index || '0', 10));
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        });
    });
    if (navP) navP.addEventListener('click', function () { show(gi - 1); });
    if (navN) navN.addEventListener('click', function () { show(gi + 1); });
    document.querySelectorAll('[data-wkr-close]').forEach(function (b) { b.addEventListener('click', closeModal); });
    if (modal) modal.addEventListener('click', function (ev) { if (ev.target === modal) closeModal(); });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closeModal(); return; }
        if (!modal || modal.hidden || gal.length < 2) return;
        if (ev.key === 'ArrowLeft')  show(gi - 1);
        if (ev.key === 'ArrowRight') show(gi + 1);
    });
})();
</script>
@endsection
