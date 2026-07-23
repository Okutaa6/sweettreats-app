@extends('layouts.app')

@section('content')
@php
    $opt = \App\Models\ChecklistSubmission::statusOptions()[$submission->status] ?? ['label'=>$submission->status,'color'=>'neutral'];
    $pct = $submission->progressPct();
    $isPending = $submission->status === \App\Models\ChecklistSubmission::STATUS_SUBMITTED;
    $isFinal   = in_array($submission->status, [
        \App\Models\ChecklistSubmission::STATUS_APPROVED,
        \App\Models\ChecklistSubmission::STATUS_REJECTED,
    ], true);
@endphp

<style>
    /* Review — kartu kategori accordion */
    .rv-cat { background:#fff; border:1px solid #E7D6C2; border-radius:14px; overflow:hidden; margin-bottom:12px; box-shadow:0 2px 8px rgba(45,24,16,.05); }
    .rv-cat:last-child { margin-bottom:0; }
    .rv-cat__head { display:flex; align-items:center; gap:12px; padding:13px 16px; cursor:pointer; user-select:none; background:linear-gradient(180deg,#FFFFFF 0%, #FAF4EC 100%); }
    .rv-cat__num { width:30px; height:30px; border-radius:8px; background:linear-gradient(135deg,#9B5E2C,#5C2E15); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; box-shadow:0 2px 4px rgba(71,21,0,.2); }
    .rv-cat__title { flex:1; min-width:0; font-size:14px; font-weight:700; color:#2D1810; }
    .rv-cat__pill { font-size:11.5px; font-weight:700; padding:3px 10px; border-radius:999px; white-space:nowrap; }
    .rv-cat__pill--good { background:rgba(31,110,95,.12); color:#1F6E5F; }
    .rv-cat__pill--warn { background:rgba(166,96,25,.12); color:#A66019; }
    .rv-cat__caret { width:26px; height:26px; border-radius:50%; background:#fff; border:1px solid #E0D2C1; color:#5C2E15; font-size:12px; display:flex; align-items:center; justify-content:center; transition:transform .25s ease; flex-shrink:0; }
    .rv-cat.is-collapsed .rv-cat__caret { transform:rotate(-90deg); }
    .rv-cat__body { display:grid; grid-template-rows:1fr; transition:grid-template-rows .3s ease; }
    .rv-cat.is-collapsed .rv-cat__body { grid-template-rows:0fr; }
    .rv-cat__body-inner { overflow:hidden; min-height:0; border-top:1px solid #F0E8DC; }
    .rv-cat.is-collapsed .rv-cat__body-inner { border-top:none; }

    .rv-task { display:flex; align-items:flex-start; gap:11px; padding:12px 16px; border-bottom:1px solid #F5EBE0; font-size:13px; }
    .rv-task:last-child { border-bottom:none; }
    .rv-task__check { width:22px; height:22px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; }
    .rv-task__check--done { background:#1F6E5F; color:#fff; }
    .rv-task__check--todo { background:#F5F0EA; color:#9B8578; border:1px solid #E0D2C1; }
    .rv-task__title { color:#2D1810; line-height:1.5; }
    .rv-task.is-todo .rv-task__title { color:#9B8578; }
    .rv-task__note { font-size:11.5px; color:#5A3A22; margin-top:5px; padding:7px 10px; background:#FFFDF9; border:1px solid #EFE1CF; border-radius:8px; border-left:3px solid #9B5E2C; line-height:1.5; }
    .rv-task__time { font-size:10.5px; color:#9B8578; flex-shrink:0; margin-top:2px; }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Detail Checklist</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:0;">

                    {{-- HEADER --}}
                    <div style="padding:20px 22px; border-bottom:1px solid #E0D2C1; background:linear-gradient(180deg,#FFFFFF 0%, #FAF4EC 100%);">
                        <a href="{{ route('checklist.review') }}" class="st-btn-outline-brown" style="display:inline-flex; align-items:center; gap:7px; margin-bottom:16px;">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
                            <div style="display:flex; align-items:center; gap:14px; min-width:0;">
                                <div style="width:50px; height:50px; border-radius:50%; background:#5C2E15; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px; flex-shrink:0;">
                                    {{ strtoupper(mb_substr($submission->user?->nama ?? '?', 0, 1)) }}
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-size:18px; font-weight:700; color:#2D1810;">{{ $submission->user?->nama ?? '—' }}</div>
                                    <div style="font-size:12.5px; color:#6B4423; margin-top:2px;">
                                        <span class="st-pill st-pill--neutral">{{ $submission->division?->name ?? $submission->division_id }}</span>
                                        @if($submission->branch)
                                            <span style="margin-left:6px;">{{ $submission->branch->name }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <span class="st-pill st-pill--{{ $opt['color'] }}" style="font-size:12px; padding:5px 12px;">{{ $opt['label'] }}</span>
                                <div style="font-size:13px; font-weight:600; color:#2D1810; margin-top:6px;">
                                    {{ $submission->submission_date->translatedFormat('l, d F Y') }}
                                </div>
                            </div>
                        </div>

                        {{-- Progress bar --}}
                        <div style="margin-top:16px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; font-size:12.5px;">
                                <span style="font-weight:600; color:#6B4423;">Progres pengisian</span>
                                <span style="color:#2D1810; font-weight:700;">{{ $submission->done_tasks }} / {{ $submission->total_tasks }} tugas ({{ $pct }}%)</span>
                            </div>
                            <div style="background:#F5F0EA; height:10px; border-radius:5px; overflow:hidden;">
                                <div style="height:100%; width:{{ $pct }}%; background:linear-gradient(90deg,#9B5E2C,#5C2E15); border-radius:5px;"></div>
                            </div>
                        </div>

                        {{-- Metadata --}}
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-top:14px; font-size:12px;">
                            @if($submission->submitted_at)
                                <div>
                                    <div style="font-size:11.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Disubmit</div>
                                    <div style="color:#2D1810; font-weight:600;">{{ $submission->submitted_at->format('d M Y · H:i') }}</div>
                                </div>
                            @endif
                            @if($submission->reviewed_at)
                                <div>
                                    <div style="font-size:11.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Direview</div>
                                    <div style="color:#2D1810; font-weight:600;">{{ $submission->reviewed_at->format('d M Y · H:i') }}</div>
                                </div>
                            @endif
                            @if($submission->reviewer)
                                <div>
                                    <div style="font-size:11.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Reviewer</div>
                                    <div style="color:#2D1810; font-weight:600;">{{ $submission->reviewer->nama }}</div>
                                </div>
                            @endif
                        </div>

                        @if($submission->review_notes)
                            <div style="margin-top:14px; padding:12px 14px; background:#FFF; border:1px solid #E0D2C1; border-left:4px solid {{ $submission->status === 'rejected' ? '#A32D2D' : '#1F6E5F' }}; border-radius:8px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:{{ $submission->status === 'rejected' ? '#A32D2D' : '#1F6E5F' }}; margin-bottom:4px;">
                                    Catatan supervisor
                                </div>
                                <div style="font-size:13px; color:#2D1810; line-height:1.5;">{{ $submission->review_notes }}</div>
                            </div>
                        @endif

                        @if($submission->status === \App\Models\ChecklistSubmission::STATUS_EXCUSED && $submission->excuse_reason)
                            <div style="margin-top:14px; padding:12px 14px; background:#FFF; border:1px solid #E0D2C1; border-left:4px solid #A66019; border-radius:8px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#A66019; margin-bottom:4px;">
                                    <i class="fas fa-mug-hot"></i> Dikecualikan — alasan karyawan
                                </div>
                                <div style="font-size:13px; color:#2D1810; line-height:1.5;">{{ $submission->excuse_reason }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- ITEM LIST per kategori --}}
                    <div style="padding:18px 22px; background:#FAF4EC;">
                        @if($grouped->isEmpty())
                            <div class="st-empty">
                                <i class="st-empty__icon fa fa-clipboard"></i>
                                <div class="st-empty__title">Tidak ada item</div>
                                <div class="st-empty__desc">Submission ini tidak memiliki item checklist.</div>
                            </div>
                        @else
                            @foreach($grouped as $catTitle => $items)
                                @php
                                    $catDone  = $items->where('is_done', true)->count();
                                    $catTotal = $items->count();
                                    $allDone  = $catDone === $catTotal;
                                @endphp
                                <div class="rv-cat is-collapsed">
                                    <div class="rv-cat__head" data-rv-toggle>
                                        <span class="rv-cat__num">{{ $loop->iteration }}</span>
                                        <span class="rv-cat__title">{{ $catTitle }}</span>
                                        <span class="rv-cat__pill rv-cat__pill--{{ $allDone ? 'good' : 'warn' }}">
                                            @if($allDone)<i class="fas fa-check" style="font-size:9px;"></i> @endif{{ $catDone }}/{{ $catTotal }}
                                        </span>
                                        <i class="fas fa-chevron-down rv-cat__caret"></i>
                                    </div>
                                    <div class="rv-cat__body">
                                        <div class="rv-cat__body-inner">
                                            @foreach($items as $item)
                                                <div class="rv-task {{ $item->is_done ? '' : 'is-todo' }}">
                                                    <span class="rv-task__check {{ $item->is_done ? 'rv-task__check--done' : 'rv-task__check--todo' }}">@if($item->is_done)✓@endif</span>
                                                    <div style="flex:1; min-width:0;">
                                                        <div class="rv-task__title">{{ $item->task_title_snapshot }}</div>
                                                        @if($item->note)
                                                            <div class="rv-task__note"><strong>Catatan:</strong> {{ $item->note }}</div>
                                                        @endif
                                                    </div>
                                                    @if($item->done_at)
                                                        <span class="rv-task__time">{{ $item->done_at->format('H:i') }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    {{-- ACTION FOOTER --}}
                    @if($isPending || $isFinal)
                        <div style="padding:18px 22px; border-top:1px solid #E0D2C1; background:#FFFFFF; position:sticky; bottom:0; z-index:10;">
                            @if($isPending)
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#6B4423; margin-bottom:4px; display:block;">
                                        Catatan Supervisor <small style="color:#9B8578; font-weight:400; text-transform:none; letter-spacing:0;">(wajib jika menolak)</small>
                                    </label>
                                    <textarea id="reviewNotes" rows="2" class="form-control" placeholder="Mis: lengkapi tugas sterilisasi, sertakan foto bukti..."></textarea>
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('checklist.review.approve', $submission->id) }}" style="display:inline;" onsubmit="this.querySelector('input[name=review_notes]').value = document.getElementById('reviewNotes').value; return confirm('Setujui checklist ini?');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-brand">
                                            <i class="fas fa-check-circle"></i> Setujui
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('checklist.review.reject', $submission->id) }}" style="display:inline;" onsubmit="var n=document.getElementById('reviewNotes').value.trim(); if(!n){alert('Catatan wajib diisi saat menolak.'); return false;} this.querySelector('input[name=review_notes]').value = n; return confirm('Yakin tolak checklist ini? Karyawan akan diberitahu untuk perbaiki.');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-outline-brown" style="color:#A32D2D; border-color:#A32D2D;">
                                            <i class="fas fa-times-circle"></i> Tolak
                                        </button>
                                    </form>
                                </div>
                            @elseif($isFinal)
                                <form method="POST" action="{{ route('checklist.review.reopen', $submission->id) }}" onsubmit="return confirm('Reopen checklist ini? Status akan kembali ke Menunggu Approval.');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="st-btn-outline-brown">
                                        <i class="fas fa-undo"></i> Reopen
                                    </button>
                                    <small style="color:#6B4423; margin-left:8px;">
                                        Reopen untuk mengubah keputusan review jika ada kesalahan.
                                    </small>
                                </form>
                            @endif
                        </div>
                    @endif

                    @if($submission->status === \App\Models\ChecklistSubmission::STATUS_EXCUSED)
                        <div style="padding:18px 22px; border-top:1px solid #E0D2C1; background:#FFFFFF;">
                            <form method="POST" action="{{ route('checklist.review.excuse.revoke', $submission->id) }}"
                                  onsubmit="return confirm('Batalkan pengecualian ini? Karyawan akan diminta mengisi checklist.');">
                                @csrf @method('PATCH')
                                <button type="submit" class="st-btn-outline-brown" style="color:#A32D2D; border-color:#A32D2D;">
                                    <i class="fas fa-undo"></i> Batalkan Pengecualian
                                </button>
                                <small style="color:#6B4423; margin-left:8px;">
                                    Karyawan menandai hari ini "tidak perlu diisi". Batalkan bila tidak valid.
                                </small>
                            </form>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var cats = Array.prototype.slice.call(document.querySelectorAll('.rv-cat'));
    // Default: hanya kategori pertama terbuka.
    cats.forEach(function (c, i) { c.classList.toggle('is-collapsed', i !== 0); });

    document.querySelectorAll('.rv-cat__head[data-rv-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var cat = head.closest('.rv-cat');
            if (! cat) return;
            var willOpen = cat.classList.contains('is-collapsed');
            cats.forEach(function (c) { c.classList.add('is-collapsed'); });
            if (willOpen) cat.classList.remove('is-collapsed');
        });
    });
})();
</script>
@endsection
