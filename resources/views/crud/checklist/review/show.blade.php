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
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box" style="display:flex; justify-content:space-between; align-items:center;">
                <h4 class="page-title">Detail Checklist</h4>
                <a href="{{ url()->previous() }}" class="st-btn-outline-brown">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
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
                                    <div style="font-size:10.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Disubmit</div>
                                    <div style="color:#2D1810; font-weight:600;">{{ $submission->submitted_at->format('d M Y · H:i') }}</div>
                                </div>
                            @endif
                            @if($submission->reviewed_at)
                                <div>
                                    <div style="font-size:10.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Direview</div>
                                    <div style="color:#2D1810; font-weight:600;">{{ $submission->reviewed_at->format('d M Y · H:i') }}</div>
                                </div>
                            @endif
                            @if($submission->reviewer)
                                <div>
                                    <div style="font-size:10.5px; color:#9B8578; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Reviewer</div>
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
                                @endphp
                                <div class="st-card" style="margin-bottom:12px;">
                                    <div class="st-card__body" style="padding:0;">
                                        <div style="padding:10px 14px; background:#FFFFFF; border-bottom:1px solid #E0D2C1; display:flex; justify-content:space-between; align-items:center;">
                                            <h6 style="margin:0; font-size:13px; font-weight:700; color:#2D1810;">
                                                <i class="fas fa-folder" style="color:#9B5E2C; margin-right:6px; font-size:11px;"></i>
                                                {{ $catTitle }}
                                            </h6>
                                            <span class="st-pill st-pill--{{ $catDone === $catTotal ? 'success' : 'warning' }}">
                                                {{ $catDone }}/{{ $catTotal }}
                                            </span>
                                        </div>
                                        <div>
                                            @foreach($items as $item)
                                                <div style="display:flex; align-items:flex-start; gap:10px; padding:10px 14px; border-bottom:1px solid #F0E8DC; font-size:13px;">
                                                    <span style="width:22px; height:22px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; background:{{ $item->is_done ? '#1F6E5F' : '#F5F0EA' }}; color:{{ $item->is_done ? '#fff' : '#9B8578' }};">
                                                        @if($item->is_done) ✓ @endif
                                                    </span>
                                                    <div style="flex:1; min-width:0;">
                                                        <div style="color:#2D1810; {{ $item->is_done ? '' : 'opacity:.7;' }}">{{ $item->task_title_snapshot }}</div>
                                                        @if($item->note)
                                                            <div style="font-size:11.5px; color:#6B4423; margin-top:3px; padding:6px 8px; background:#FFF; border-radius:6px; border-left:3px solid #9B5E2C;">
                                                                <i class="fas fa-pen-to-square" style="font-size:10px; margin-right:4px;"></i>
                                                                {{ $item->note }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if($item->done_at)
                                                        <span style="font-size:10.5px; color:#9B8578; flex-shrink:0;">{{ $item->done_at->format('H:i') }}</span>
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
                                    <form method="POST" action="{{ route('checklist.review.approve', $submission->id) }}" style="display:inline;" onsubmit="this.querySelector('input[name=review_notes]').value = document.getElementById('reviewNotes').value;">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-brand">
                                            <i class="fas fa-circle-check"></i> Setujui
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('checklist.review.reject', $submission->id) }}" style="display:inline;" onsubmit="var n=document.getElementById('reviewNotes').value.trim(); if(!n){alert('Catatan wajib diisi saat menolak.'); return false;} this.querySelector('input[name=review_notes]').value = n; return confirm('Yakin tolak checklist ini? Karyawan akan diberitahu untuk perbaiki.');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-outline-brown" style="color:#A32D2D; border-color:#A32D2D;">
                                            <i class="fas fa-circle-xmark"></i> Tolak
                                        </button>
                                    </form>
                                </div>
                            @elseif($isFinal)
                                <form method="POST" action="{{ route('checklist.review.reopen', $submission->id) }}" onsubmit="return confirm('Reopen checklist ini? Status akan kembali ke Menunggu Approval.');">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="st-btn-outline-brown">
                                        <i class="fas fa-rotate-left"></i> Reopen
                                    </button>
                                    <small style="color:#9B8578; margin-left:8px;">
                                        Reopen untuk mengubah keputusan review jika ada kesalahan.
                                    </small>
                                </form>
                            @endif
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
