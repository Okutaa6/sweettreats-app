@extends('layouts.app')

@section('content')
@php
    $stMap = [
        'submitted' => ['label' => 'Menunggu Approval', 'c' => '#B5701A', 'bg' => '#FBEFD9'],
        'approved'  => ['label' => 'Disetujui',          'c' => '#2F8E55', 'bg' => '#E3F4E8'],
        'rejected'  => ['label' => 'Ditolak',            'c' => '#C0392B', 'bg' => '#FBE3DE'],
        'excused'   => ['label' => 'Dikecualikan',       'c' => '#8A5A2B', 'bg' => '#F2E7D8'],
        'missed'    => ['label' => 'Tidak Diisi',        'c' => '#9B8578', 'bg' => '#EFE7DE'],
        'draft'     => ['label' => 'Belum Dikirim',     'c' => '#9B8578', 'bg' => '#EFE7DE'],
    ];
    $st  = $stMap[$submission->status] ?? ['label' => $submission->status, 'c' => '#9B8578', 'bg' => '#EFE7DE'];
    // "beres" = dicentang ATAU dikecualikan → progres 100% saat semua terpenuhi.
    $satisfied = $submission->satisfiedCount();
    $exceptedN = $submission->exceptedCount();
    $pct = $submission->satisfiedPct();
    $barColor = $submission->status === 'excused'
        ? '#C7AC92'
        : ($pct >= 80 ? '#2F8E55' : ($pct >= 50 ? '#C9962F' : '#C0392B'));
    $isPending = $submission->status === \App\Models\ChecklistSubmission::STATUS_SUBMITTED;
    $isFinal   = in_array($submission->status, [
        \App\Models\ChecklistSubmission::STATUS_APPROVED,
        \App\Models\ChecklistSubmission::STATUS_REJECTED,
    ], true);
    $isExcused = $submission->status === \App\Models\ChecklistSubmission::STATUS_EXCUSED;

    // Avatar 2 huruf + warna per orang (palet handoff).
    $avPalette  = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avName     = trim($submission->user?->nama ?? '');
    $avParts    = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
    $avInitials = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
    $avColor    = $avPalette[abs(crc32($avName !== '' ? $avName : '?')) % 8];

    // Meta line "Disubmit … · Direview … · Reviewer …"
    $metaParts = [];
    if ($submission->submitted_at) $metaParts[] = 'Disubmit ' . $submission->submitted_at->format('d M · H:i');
    if ($submission->reviewed_at)  $metaParts[] = 'Direview ' . $submission->reviewed_at->format('d M · H:i');
    if ($submission->reviewer)     $metaParts[] = 'Reviewer ' . $submission->reviewer->nama;
@endphp

<style>
    /* ── Review detail — gaya handoff (06-review-detail) ── */
    .rvd-cat { background:#fff; border:1px solid #EFE0CE; border-radius:16px; overflow:hidden; box-shadow:0 1px 2px rgba(70,21,0,.04); }
    .rvd-cat__head { display:flex; align-items:center; gap:11px; padding:14px 17px; cursor:pointer; user-select:none; transition:background .12s ease; }
    .rvd-cat__head:hover { background:#FBF4EC; }
    .rvd-cat__title { flex:1; min-width:0; font-size:13.5px; font-weight:800; color:#3B1500; }
    .rvd-cat__badge { font-size:11px; font-weight:800; padding:3px 10px; border-radius:20px; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .rvd-cat__badge--good { background:#E3F4E8; color:#2F8E55; }
    .rvd-cat__badge--warn { background:#FBEFD9; color:#B5701A; }
    .rvd-cat__caret { color:#9A7256; font-size:12px; flex-shrink:0; transition:transform .18s ease; }
    .rvd-cat.is-collapsed .rvd-cat__caret { transform:rotate(-90deg); }
    .rvd-cat__body { display:grid; grid-template-rows:1fr; transition:grid-template-rows .3s ease; }
    .rvd-cat.is-collapsed .rvd-cat__body { grid-template-rows:0fr; }
    .rvd-cat__body-inner { overflow:hidden; min-height:0; border-top:1px solid #F1E6D7; }
    .rvd-cat.is-collapsed .rvd-cat__body-inner { border-top:none; }

    .rvd-task { padding:11px 17px; border-top:1px solid #F6EEE1; display:flex; flex-direction:column; gap:7px; }
    .rvd-task:first-child { border-top:none; }
    .rvd-task__row { display:flex; align-items:center; gap:11px; }
    .rvd-task__ck { width:22px; height:22px; flex:none; border-radius:7px; display:flex; align-items:center; justify-content:center; }
    .rvd-task__ck--done { background:#3FA76A; color:#fff; font-size:11px; }
    .rvd-task__ck--todo { border:1.5px solid #DCC4A8; background:#fff; }
    .rvd-task__ck--excepted { background:#F0E4D3; border:1.5px solid #C9A97F; color:#8A5A2B; font-size:10px; }
    .rvd-task__title { font-size:13px; font-weight:600; color:#3B1500; flex:1; min-width:0; }
    .rvd-task.is-todo .rvd-task__title { color:#B79A82; }
    .rvd-task.is-excepted .rvd-task__title { color:#9A7256; }
    .rvd-task__time { font-size:11.5px; color:#B79A82; flex:none; font-variant-numeric:tabular-nums; }
    .rvd-task__xcpt { font-size:10.5px; font-weight:800; color:#fff; background:#8A5A2B; border-radius:20px; padding:3px 9px; flex:none; }
    .rvd-task__note { margin-left:33px; background:#FBF6EF; border-radius:9px; padding:8px 11px; font-size:12px; color:#7A5B43; line-height:1.5; }
    .rvd-task__note--xcpt { background:#FBF3E8; color:#6B4423; border-left:3px solid #8A5A2B; }
    .rvd-task__note--xcpt i { font-size:10px; margin-right:4px; color:#8A5A2B; }

    .rvd-btn-back { transition:background-color .15s ease, border-color .15s ease; }
    .rvd-btn-back:hover { background:#FBF4EC; border-color:#D9C3AC; }
    .rvd-act { transition:filter .15s ease; }
    .rvd-act:hover { filter:brightness(.94); }
</style>

<div class="container-fluid">

    @include('layouts.notif')

    <div style="max-width:1240px;margin:0 auto;display:flex;flex-direction:column;gap:14px">

    {{-- ─── KEMBALI ─── --}}
    <div>
        <a href="{{ route('checklist.review') }}" class="rvd-btn-back" style="display:inline-flex;align-items:center;gap:7px;background:#fff;color:#5A3A22;border:1px solid #E7D3BE;border-radius:11px;padding:10px 16px;font-weight:700;font-size:13px;text-decoration:none">← Kembali</a>
    </div>

    {{-- ─── HEADER CARD ─── --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;padding:20px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04);display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="width:46px;height:46px;border-radius:50%;background:{{ $avColor }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;flex-shrink:0">{{ $avInitials }}</div>
            <div style="flex:1;min-width:180px">
                <div style="font-size:17px;font-weight:800;color:#3B1500">{{ $submission->user?->nama ?? '—' }}</div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:5px;flex-wrap:wrap">
                    <span style="font-size:11px;font-weight:800;color:#8A3A12;background:#F4E7D6;border-radius:20px;padding:3px 10px">{{ $submission->division?->name ?? $submission->division_id }}</span>
                    <span style="font-size:12px;color:#9A7256">Cabang {{ $submission->branch?->name ?? '—' }}</span>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex:none">
                <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:{{ $st['bg'] }};color:{{ $st['c'] }};white-space:nowrap">{{ $st['label'] }}</span>
                <span style="font-size:12.5px;font-weight:600;color:#7A5B43;font-variant-numeric:tabular-nums">{{ $submission->submission_date->translatedFormat('l, d F Y') }}</span>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <div style="flex:1;height:9px;border-radius:6px;background:#F1E6D7;overflow:hidden"><div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:6px"></div></div>
            <span style="font-size:13px;font-weight:800;color:#3B1500;flex:none;font-variant-numeric:tabular-nums">{{ $satisfied }} / {{ $submission->total_tasks }} tugas ({{ $pct }}%)@if($exceptedN > 0) <span style="font-size:11px;font-weight:700;color:#8A5A2B">· {{ $exceptedN }} dikecualikan</span>@endif</span>
        </div>
        @if(count($metaParts))
            <div style="font-size:11.5px;color:#B79A82;font-variant-numeric:tabular-nums">{{ implode('   ·   ', $metaParts) }}</div>
        @endif
    </div>

    {{-- ─── BANNER: DIKECUALIKAN ─── --}}
    @if($isExcused && $submission->excuse_reason)
        <div style="background:#F2E7D8;border-left:4px solid #8A5A2B;border-radius:12px;padding:14px 17px;display:flex;gap:11px;align-items:flex-start">
            <i class="fas fa-mug-hot" style="color:#8A5A2B;font-size:15px;margin-top:2px;flex-shrink:0"></i>
            <div>
                <div style="font-size:12px;font-weight:800;color:#8A5A2B">Dikecualikan — alasan karyawan</div>
                <div style="font-size:13px;color:#6B4A28;margin-top:3px">"{{ $submission->excuse_reason }}"</div>
            </div>
        </div>
    @endif

    {{-- ─── BANNER: CATATAN SUPERVISOR ─── --}}
    @if($submission->review_notes)
        @php $noteCol = $submission->status === 'rejected' ? '#C0392B' : '#2F8E55'; @endphp
        <div style="background:{{ $submission->status === 'rejected' ? '#FBE3DE' : '#E3F4E8' }};border-left:4px solid {{ $noteCol }};border-radius:12px;padding:14px 17px">
            <div style="font-size:12px;font-weight:800;color:{{ $noteCol }}">Catatan Supervisor</div>
            <div style="font-size:13px;color:#5A3A22;margin-top:3px">"{{ $submission->review_notes }}"</div>
        </div>
    @endif

    {{-- ─── ISI CHECKLIST (accordion per kategori) ─── --}}
    @if($grouped->isEmpty())
        <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
            Submission ini tidak memiliki item checklist.
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:11px">
            @foreach($grouped as $catTitle => $items)
                @php
                    // "beres" = dicentang ATAU dikecualikan (tidak berlaku hari itu).
                    $catDone  = $items->filter(fn ($i) => $i->is_done || $i->is_excepted)->count();
                    $catTotal = $items->count();
                    $allDone  = $catDone === $catTotal;
                @endphp
                <div class="rvd-cat is-collapsed">
                    <div class="rvd-cat__head" data-rvd-toggle>
                        <span class="rvd-cat__title">{{ $catTitle }}</span>
                        <span class="rvd-cat__badge rvd-cat__badge--{{ $allDone ? 'good' : 'warn' }}">{{ $catDone }}/{{ $catTotal }}</span>
                        <i class="fas fa-chevron-down rvd-cat__caret"></i>
                    </div>
                    <div class="rvd-cat__body">
                        <div class="rvd-cat__body-inner">
                            @foreach($items as $item)
                                <div class="rvd-task {{ $item->is_done || $item->is_excepted ? '' : 'is-todo' }} {{ $item->is_excepted ? 'is-excepted' : '' }}">
                                    <div class="rvd-task__row">
                                        <span class="rvd-task__ck {{ $item->is_excepted ? 'rvd-task__ck--excepted' : ($item->is_done ? 'rvd-task__ck--done' : 'rvd-task__ck--todo') }}">@if($item->is_excepted)<i class="fas fa-ban"></i>@elseif($item->is_done)<i class="fas fa-check"></i>@endif</span>
                                        <span class="rvd-task__title">{{ $item->task_title_snapshot }}</span>
                                        @if($item->is_excepted)
                                            <span class="rvd-task__xcpt">Dikecualikan</span>
                                        @else
                                            <span class="rvd-task__time">{{ $item->done_at ? $item->done_at->format('H:i') : '—' }}</span>
                                        @endif
                                    </div>
                                    @if($item->is_excepted && $item->exception_reason)
                                        <div class="rvd-task__note rvd-task__note--xcpt"><i class="fas fa-ban"></i> Alasan dikecualikan: {{ $item->exception_reason }}</div>
                                    @endif
                                    @if($item->note)
                                        <div class="rvd-task__note">Catatan: {{ $item->note }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ─── STICKY ACTION BAR ─── --}}
    @if($isPending || $isFinal || $isExcused)
        <div style="position:sticky;bottom:14px;z-index:20;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:15px 18px;box-shadow:0 -6px 24px rgba(70,21,0,.1),0 2px 8px rgba(70,21,0,.06)">
            @if($isPending)
                <div style="display:flex;flex-direction:column;gap:10px">
                    <textarea id="reviewNotes" rows="2" placeholder="Catatan Supervisor (wajib jika menolak)…"
                              style="width:100%;box-sizing:border-box;border:1px solid #E7D3BE;border-radius:11px;padding:11px 13px;font-family:inherit;font-size:13px;color:#3B1500;background:#FFFCF8;resize:vertical"></textarea>
                    <span id="reviewNotesErr" style="display:none;font-size:11.5px;font-weight:700;color:#C0392B">Catatan wajib diisi saat menolak.</span>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <form method="POST" action="{{ route('checklist.review.approve', $submission->id) }}" style="flex:1;min-width:150px;display:flex"
                              onsubmit="this.querySelector('input[name=review_notes]').value = document.getElementById('reviewNotes').value; return confirm('Setujui checklist ini?');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="review_notes" value="">
                            <button type="submit" class="rvd-act" style="flex:1;display:flex;align-items:center;justify-content:center;gap:7px;background:#3FA76A;color:#fff;border:none;border-radius:11px;padding:12px;font-weight:800;font-size:13.5px;font-family:inherit;cursor:pointer">
                                <i class="fas fa-check"></i> Setujui
                            </button>
                        </form>
                        <form method="POST" action="{{ route('checklist.review.reject', $submission->id) }}" style="flex:1;min-width:150px;display:flex"
                              onsubmit="var n=document.getElementById('reviewNotes').value.trim(); var err=document.getElementById('reviewNotesErr'); var ta=document.getElementById('reviewNotes'); if(!n){err.style.display='block'; ta.style.borderColor='#C0392B'; return false;} this.querySelector('input[name=review_notes]').value = n; return confirm('Yakin tolak checklist ini? Karyawan akan diberitahu untuk perbaiki.');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="review_notes" value="">
                            <button type="submit" class="rvd-act" style="flex:1;display:flex;align-items:center;justify-content:center;gap:7px;background:#C0392B;color:#fff;border:none;border-radius:11px;padding:12px;font-weight:800;font-size:13.5px;font-family:inherit;cursor:pointer">
                                <i class="fas fa-times"></i> Tolak
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($isFinal)
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                    <form method="POST" action="{{ route('checklist.review.reopen', $submission->id) }}" style="display:inline"
                          onsubmit="return confirm('Reopen checklist ini? Status akan kembali ke Menunggu Approval.');">
                        @csrf @method('PATCH')
                        <button type="submit" class="rvd-act" style="background:#fff;color:#8A3A12;border:1.5px solid #C9885A;border-radius:11px;padding:11px 20px;font-weight:800;font-size:13px;font-family:inherit;cursor:pointer">Reopen</button>
                    </form>
                    <span style="font-size:12.5px;color:#9A7256">Reopen untuk mengubah keputusan review jika ada kesalahan.</span>
                </div>
            @elseif($isExcused)
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                    @if($submission->isSystemExcused())
                        {{-- Otomatis dari cuti/izin approved. Tombol batal sengaja tidak ada:
                             pengecualiannya akan dipasang ulang oleh reconcile, jadi yang perlu
                             dikoreksi adalah pengajuannya, bukan checklist-nya. --}}
                        <span style="font-size:12.5px;color:#9A7256">
                            <i class="fas fa-lock"></i>
                            Pengecualian otomatis dari pengajuan cuti/izin yang disetujui — koreksinya lewat modul Kehadiran.
                        </span>
                    @else
                        <form method="POST" action="{{ route('checklist.review.excuse.revoke', $submission->id) }}" style="display:inline"
                              onsubmit="return confirm('Batalkan pengecualian ini? Karyawan akan diminta mengisi checklist.');">
                            @csrf @method('PATCH')
                            <button type="submit" class="rvd-act" style="background:#fff;color:#C0392B;border:1.5px solid #E0A79D;border-radius:11px;padding:11px 20px;font-weight:800;font-size:13px;font-family:inherit;cursor:pointer">Batalkan Pengecualian</button>
                        </form>
                        <span style="font-size:12.5px;color:#9A7256">Karyawan menandai hari ini tidak perlu diisi. Batalkan bila tidak valid.</span>
                    @endif
                </div>
            @endif
        </div>
    @endif

    </div>{{-- /max-width --}}
</div>

<script>
(function () {
    var cats = Array.prototype.slice.call(document.querySelectorAll('.rvd-cat'));
    // Default: hanya kategori pertama terbuka.
    cats.forEach(function (c, i) { c.classList.toggle('is-collapsed', i !== 0); });

    document.querySelectorAll('[data-rvd-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var cat = head.closest('.rvd-cat');
            if (cat) cat.classList.toggle('is-collapsed');
        });
    });

    // Hilangkan error catatan saat mulai mengetik.
    var ta = document.getElementById('reviewNotes'), err = document.getElementById('reviewNotesErr');
    if (ta && err) ta.addEventListener('input', function () {
        err.style.display = 'none';
        ta.style.borderColor = '#E7D3BE';
    });
})();
</script>
@endsection
