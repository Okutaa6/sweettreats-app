@extends('layouts.app')

@section('content')
@php
    $statusClass = $type === 'weekly' ? \App\Models\ChecklistWeeklySubmission::class : \App\Models\ChecklistSubmission::class;
    $opt = $statusClass::statusOptions()[$submission->status] ?? ['label' => $submission->status, 'color' => 'neutral'];
    $pct = $submission->progressPct();
    if ($type === 'weekly') {
        $ws = $submission->week_start_date;
        $dateLabel = $ws->translatedFormat('d M') . ' – ' . $ws->copy()->addDays(6)->translatedFormat('d M Y');
    } else {
        $dateLabel = $submission->submission_date->translatedFormat('l, d F Y');
    }
    $isRejected = $submission->status === $statusClass::STATUS_REJECTED;
    $backUrl = route('mychecklist.history', array_filter(['type' => $type, 'month' => $month]));
@endphp

<style>
    .rvh-wrap { max-width: 900px; margin: 0 auto; }

    /* Header wrapper (hero + catatan menyatu jadi satu unit) */
    .rvh-headerwrap { border-radius: 18px; overflow: hidden; box-shadow: 0 8px 24px rgba(71,21,0,.18); margin-bottom: 14px; }
    /* Hero (coklat, menonjol dari background) */
    .rvh-hero { position: relative; overflow: hidden; background: linear-gradient(135deg,#5C2E15 0%,#471500 100%); color: #fff; padding: 22px 24px; }
    .rvh-hero::before { content: ""; position: absolute; top: -55px; right: -45px; width: 190px; height: 190px; border-radius: 50%; background: rgba(255,255,255,.05); pointer-events: none; }
    .rvh-hero__row { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
    .rvh-hero__left { display: flex; gap: 14px; min-width: 0; }
    .rvh-hero__icon { width: 50px; height: 50px; border-radius: 14px; background: rgba(255,255,255,.15); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #FFE9C7; flex-shrink: 0; }
    .rvh-hero__type { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: rgba(255,233,199,.85); }
    .rvh-hero__title { font-size: 20px; font-weight: 800; color: #fff; margin: 3px 0 0; line-height: 1.2; }
    .rvh-hero__meta { margin-top: 9px; display: flex; gap: 7px; flex-wrap: wrap; }
    .rvh-hero__chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; background: rgba(255,255,255,.14); color: #fff; padding: 4px 11px; border-radius: 999px; }
    .rvh-hero__status { flex-shrink: 0; background: #fff; font-size: 12px; font-weight: 800; padding: 6px 14px; border-radius: 999px; box-shadow: 0 2px 8px rgba(0,0,0,.14); }
    .rvh-hero__progress { position: relative; z-index: 1; margin-top: 18px; }
    .rvh-hero__plabel { display: flex; justify-content: space-between; gap: 10px; font-size: 12.5px; margin-bottom: 6px; color: rgba(255,255,255,.9); }
    .rvh-hero__plabel b { color: #fff; }
    .rvh-hero__bar { background: rgba(255,255,255,.18); height: 10px; border-radius: 5px; overflow: hidden; }
    .rvh-hero__bar i { display: block; height: 100%; background: #FFE9C7; border-radius: 5px; }
    .rvh-hero__times { position: relative; z-index: 1; margin-top: 14px; padding-top: 13px; border-top: 1px solid rgba(255,255,255,.14); display: flex; gap: 18px; flex-wrap: wrap; font-size: 12px; color: rgba(255,255,255,.88); }
    .rvh-hero__times b { color: #fff; font-weight: 700; }

    /* Catatan — nempel di bawah hero, gaya callout (ikon badge + latar lembut) */
    .rvh-notes { background: #FBF6EF; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
    .rvh-noterow { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: var(--nt-bg, #F6EFE6); border: 1px solid var(--nt-bd, #E7D6C2); }
    .rvh-noteicon { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; background: var(--nt-c, #9B8578); box-shadow: 0 2px 6px rgba(45,24,16,.12); }
    .rvh-noterow__lbl { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; color: var(--nt-c, #6B4423); margin-bottom: 2px; }
    .rvh-noterow__txt { font-size: 13.5px; color: #2D1810; line-height: 1.55; }

    /* Kartu grup (mirror my-checklist, read-only) */
    .rvh-list { display: flex; flex-direction: column; gap: 12px; }
    .rvh-card { background: #FFFFFF; border: 1px solid #E0D2C1; border-radius: 16px; box-shadow: 0 2px 8px rgba(45,24,16,.04); overflow: hidden; }
    .rvh-card__head { display: flex; align-items: center; gap: 12px; padding: 13px 16px; background: linear-gradient(180deg,#FFFFFF 0%, #FAF4EC 100%); border-bottom: 1px solid #F0E8DC; cursor: pointer; user-select: none; }
    .rvh-card__caret { flex-shrink: 0; margin-left: 2px; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #E0D2C1; color: #5C2E15; font-size: 12px; transition: transform .3s cubic-bezier(.4,0,.2,1), background .15s, color .15s, border-color .15s; }
    .rvh-card__head:hover .rvh-card__caret { background: #5C2E15; border-color: #5C2E15; color: #fff; }
    .rvh-card.is-collapsed .rvh-card__caret { transform: rotate(-90deg); }
    .rvh-card.is-collapsed .rvh-card__head { border-bottom: none; }
    .rvh-card__body { display: grid; grid-template-rows: 1fr; transition: grid-template-rows .3s cubic-bezier(.4,0,.2,1); }
    .rvh-card.is-collapsed .rvh-card__body { grid-template-rows: 0fr; }
    .rvh-card__body-inner { overflow: hidden; min-height: 0; }

    /* Weekly → kotak angka hari (mirror Form Mingguan) */
    .rvh-daynum { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg,#9B8578,#6B5944); color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0; box-shadow: 0 4px 10px rgba(107,89,68,.20); }
    .rvh-daynum strong { font-size: 17px; font-weight: 800; line-height: 1; }
    .rvh-daynum small { font-size: 10px; opacity: .85; margin-top: 2px; }
    .rvh-dayinfo { flex: 1; min-width: 0; }
    .rvh-dayinfo__title { font-size: 15px; font-weight: 700; color: #2D1810; line-height: 1.2; }
    .rvh-dayinfo__date { font-size: 12px; color: #6B4423; margin-top: 3px; }

    /* Daily → badge nomor kategori (mirror Harian) */
    .rvh-catnum { width: 34px; height: 34px; border-radius: 9px; background: linear-gradient(135deg,#1F6E5F,#155148); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; flex-shrink:0; box-shadow: 0 3px 8px rgba(31,110,95,.22); }
    .rvh-card__title { flex: 1; min-width: 0; font-size: 14.5px; font-weight: 700; color: #2D1810; }

    .rvh-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 700; white-space: nowrap; }
    .rvh-chip--good { background: rgba(31,110,95,.12); color: #1F6E5F; }
    .rvh-chip--warn { background: rgba(166,96,25,.12); color: #A66019; }

    /* Task rows (read-only) */
    .rvh-task { display: flex; align-items: flex-start; gap: 12px; padding: 13px 16px; border-bottom: 1px solid #F5EBE0; font-size: 13px; }
    .rvh-task:last-child { border-bottom: none; }
    .rvh-task__ck { width: 24px; height: 24px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; margin-top: 1px; }
    .rvh-task__ck--done { background: linear-gradient(135deg,#5C2E15,#471500); border: 2px solid #471500; color: #fff; box-shadow: 0 2px 6px rgba(71,21,0,.22); }
    .rvh-task__ck--todo { background: #FFFFFF; border: 2px solid #C9B69A; color: transparent; }
    .rvh-task__body { flex: 1; min-width: 0; }
    .rvh-task__ttl { font-size: 13.5px; color: #2D1810; line-height: 1.5; }
    .rvh-task.is-done .rvh-task__ttl { color: #9B8578; text-decoration: line-through; text-decoration-thickness: 1px; }
    .rvh-task__note { font-size: 11.5px; color: #5A3A22; margin-top: 5px; padding: 7px 10px; background: #FFFDF9; border: 1px solid #EFE1CF; border-radius: 8px; border-left: 3px solid #9B5E2C; line-height: 1.5; }
    .rvh-task__time { font-size: 10.5px; color: #9B8578; flex-shrink: 0; margin-top: 4px; }
    .rvh-empty { padding: 22px 16px; text-align: center; font-size: 12.5px; color: #9B8578; font-style: italic; }
</style>

<div class="container-fluid">
    <div class="row"><div class="col-sm-12"><div class="page-title-box"><h4 class="page-title">Detail Riwayat</h4></div></div></div>

    @include('layouts.notif')

    <div class="rvh-wrap">
        <a href="{{ $backUrl }}" class="st-btn-outline-brown" style="display:inline-flex; align-items:center; gap:7px; margin-bottom:14px;">
            <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
        </a>

        @php
            $isMissed = $submission->status === $statusClass::STATUS_MISSED;
            $accent = [
                'success' => '#1F6E5F', 'warning' => '#A66019', 'danger' => '#A32D2D',
                'neutral' => '#6B5944', 'coffee' => '#8A5A2B', 'info' => '#1E5A8C', 'slate' => '#5F5A52',
            ][$opt['color']] ?? '#6B5944';
            $hasNotes = ($type === 'weekly' && $submission->submit_note)
                || $submission->review_notes
                || ($submission->status === $statusClass::STATUS_EXCUSED && $submission->excuse_reason);
        @endphp

        {{-- HEADER: hero coklat + catatan menyatu di bawahnya --}}
        <div class="rvh-headerwrap">
        <div class="rvh-hero">
            <div class="rvh-hero__row">
                <div class="rvh-hero__left">
                    <div class="rvh-hero__icon"><i class="fas fa-{{ $type === 'weekly' ? 'calendar-week' : 'sun' }}"></i></div>
                    <div style="min-width:0;">
                        <div class="rvh-hero__type">{{ $type === 'weekly' ? 'Form Mingguan' : 'Checklist Harian' }}</div>
                        <h5 class="rvh-hero__title">{{ $dateLabel }}</h5>
                        <div class="rvh-hero__meta">
                            <span class="rvh-hero__chip"><i class="fas fa-id-badge" style="font-size:10px;"></i> {{ $submission->division?->name ?? $submission->division_id }}</span>
                            @if($submission->branch)<span class="rvh-hero__chip"><i class="fas fa-building" style="font-size:10px;"></i> {{ $submission->branch->name }}</span>@endif
                        </div>
                    </div>
                </div>
                <span class="rvh-hero__status" style="color:{{ $accent }};"><i class="fas fa-circle" style="font-size:7px; vertical-align:middle;"></i> {{ $opt['label'] }}</span>
            </div>

            <div class="rvh-hero__progress">
                <div class="rvh-hero__plabel">
                    <span>Progres pengisian</span>
                    <span><b>{{ $submission->done_tasks }} / {{ $submission->total_tasks }}</b> tugas ({{ $pct }}%)</span>
                </div>
                <div class="rvh-hero__bar"><i style="width:{{ $pct }}%"></i></div>
            </div>

            {{-- Waktu submit/review — disembunyikan kalau status "Tidak Diisi" --}}
            @if(! $isMissed && ($submission->submitted_at || $submission->reviewed_at))
                <div class="rvh-hero__times">
                    @if($submission->submitted_at)
                        <span><i class="fas fa-paper-plane" style="font-size:10px;"></i> Disubmit <b>{{ $submission->submitted_at->translatedFormat('d M Y · H:i') }}</b></span>
                    @endif
                    @if($submission->reviewed_at)
                        <span><i class="fas fa-gavel" style="font-size:10px;"></i> Direview <b>{{ $submission->reviewed_at->translatedFormat('d M Y · H:i') }}</b></span>
                    @endif
                </div>
            @endif
        </div>{{-- /rvh-hero --}}

        @if($hasNotes)
            <div class="rvh-notes">
                @if($type === 'weekly' && $submission->submit_note)
                    <div class="rvh-noterow" style="--nt-c:#9B5E2C; --nt-bg:#FBF3E9; --nt-bd:#EAD9C4;">
                        <span class="rvh-noteicon"><i class="fas fa-pen"></i></span>
                        <div style="flex:1; min-width:0;">
                            <div class="rvh-noterow__lbl">Catatan Anda</div>
                            <div class="rvh-noterow__txt">{{ $submission->submit_note }}</div>
                        </div>
                    </div>
                @endif
                @if($submission->review_notes)
                    <div class="rvh-noterow" @if($isRejected) style="--nt-c:#A32D2D; --nt-bg:#FCEFEF; --nt-bd:#F0D2D2;" @else style="--nt-c:#1F6E5F; --nt-bg:#EEF7F4; --nt-bd:#CDE8E0;" @endif>
                        <span class="rvh-noteicon"><i class="fas fa-comment-dots"></i></span>
                        <div style="flex:1; min-width:0;">
                            <div class="rvh-noterow__lbl">{{ $isRejected ? 'Catatan Penolakan' : 'Catatan Supervisor' }}</div>
                            <div class="rvh-noterow__txt">{{ $submission->review_notes }}</div>
                        </div>
                    </div>
                @endif
                @if($submission->status === $statusClass::STATUS_EXCUSED && $submission->excuse_reason)
                    <div class="rvh-noterow" style="--nt-c:#A66019; --nt-bg:#FDF3E6; --nt-bd:#F0DCC0;">
                        <span class="rvh-noteicon"><i class="fas fa-mug-hot"></i></span>
                        <div style="flex:1; min-width:0;">
                            <div class="rvh-noterow__lbl">Dikecualikan — alasan</div>
                            <div class="rvh-noterow__txt">{{ $submission->excuse_reason }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
        </div>{{-- /rvh-headerwrap --}}

        {{-- TASK LIST (mirror my-checklist) --}}
        @if($grouped->isEmpty())
            <div class="rvh-card"><div class="rvh-empty">Tidak ada item pada pengisian ini.</div></div>
        @else
            <div class="rvh-list">
                @foreach($grouped as $groupKey => $items)
                    @php
                        $catDone = $items->where('is_done', true)->count();
                        $catTotal = $items->count();
                        $catComplete = $catDone === $catTotal;
                    @endphp
                    <div class="rvh-card {{ ! $loop->first ? 'is-collapsed' : '' }}">
                        <div class="rvh-card__head" data-rvh-toggle>
                            @if($type === 'weekly')
                                @php
                                    $dayDate  = $ws->copy()->addDays(((int) $groupKey) - 1);
                                    $dayName  = \App\Models\ChecklistTask::dayLabels()[$groupKey] ?? 'Hari lain';
                                    $dayShort = \App\Models\ChecklistTask::dayShortLabels()[$groupKey] ?? '';
                                @endphp
                                <span class="rvh-daynum">
                                    <strong>{{ $dayDate->format('d') }}</strong>
                                    <small>{{ $dayShort }}</small>
                                </span>
                                <div class="rvh-dayinfo">
                                    <div class="rvh-dayinfo__title">{{ $dayName }}</div>
                                    <div class="rvh-dayinfo__date">{{ $dayDate->translatedFormat('d F Y') }}</div>
                                </div>
                            @else
                                <span class="rvh-catnum">{{ $loop->iteration }}</span>
                                <span class="rvh-card__title">{{ $groupKey }}</span>
                            @endif
                            <span class="rvh-chip rvh-chip--{{ $catComplete ? 'good' : 'warn' }}">
                                @if($catComplete)<i class="fas fa-check" style="font-size:9px;"></i>@endif {{ $catDone }}/{{ $catTotal }}
                            </span>
                            <i class="fas fa-chevron-down rvh-card__caret"></i>
                        </div>
                        <div class="rvh-card__body"><div class="rvh-card__body-inner">
                        @foreach($items as $item)
                            <div class="rvh-task {{ $item->is_done ? 'is-done' : '' }}">
                                <span class="rvh-task__ck {{ $item->is_done ? 'rvh-task__ck--done' : 'rvh-task__ck--todo' }}">@if($item->is_done)✓@endif</span>
                                <div class="rvh-task__body">
                                    <div class="rvh-task__ttl">{{ $item->task_title_snapshot }}</div>
                                    @if($item->note)
                                        <div class="rvh-task__note"><strong>Catatan:</strong> {{ $item->note }}</div>
                                    @endif
                                </div>
                                @if($item->done_at)
                                    <span class="rvh-task__time">{{ $item->done_at->format('H:i') }}</span>
                                @endif
                            </div>
                        @endforeach
                        </div></div>{{-- /body-inner /body --}}
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-rvh-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var card = head.closest('.rvh-card');
            if (card) card.classList.toggle('is-collapsed');
        });
    });
})();
</script>
@endsection
