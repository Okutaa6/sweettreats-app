@extends('layouts.app')

@section('css')
<style>
    .wkr-day {
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 14px;
        margin-bottom: 12px;
        overflow: hidden;
    }
    .wkr-day__head {
        padding: 12px 16px;
        background: linear-gradient(180deg, #FFFFFF 0%, #FAF4EC 100%);
        border-bottom: 1px solid #F0E8DC;
        display: flex; justify-content: space-between; align-items: center;
        gap: 10px; flex-wrap: wrap;
    }
    .wkr-day__head-left { display: flex; align-items: center; gap: 12px; }
    .wkr-day__num {
        width: 44px; height: 44px;
        border-radius: 10px;
        background: linear-gradient(135deg, #5C2E15, #471500);
        color: #FFFFFF;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        flex-shrink: 0; box-shadow: 0 4px 10px rgba(71,21,0,.22);
    }
    .wkr-day__num strong { font-size: 16px; font-weight: 800; line-height: 1; }
    .wkr-day__num small { font-size: 9.5px; opacity: .85; margin-top: 2px; }
    .wkr-day__title { font-size: 14px; font-weight: 700; color: #2D1810; margin: 0; }
    .wkr-day__date { font-size: 11.5px; color: #6B4423; margin-top: 2px; }

    .wkr-cat-head {
        padding: 8px 16px;
        background: #FAFAF8;
        border-bottom: 1px solid #F0E8DC;
        display: flex; justify-content: space-between; align-items: center;
        font-size: 11.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.4px;
        color: #9B5E2C;
    }
    .wkr-task {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 9px 16px;
        border-bottom: 1px solid #F5EBE0;
        font-size: 13px;
    }
    .wkr-task:last-child { border-bottom: none; }
    .wkr-task__check {
        width: 22px; height: 22px; border-radius: 50%;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 700;
        background: #F5F0EA; color: #9B8578;
        margin-top: 1px;
    }
    .wkr-task__check.is-done { background: #1F6E5F; color: #FFFFFF; }
    .wkr-task__name { flex: 1; min-width: 0; color: #2D1810; line-height: 1.4; }
    .wkr-task__check.is-done + .wkr-task__name { color: #9B8578; }
    .wkr-task__time { font-size: 10.5px; color: #9B8578; flex-shrink: 0; margin-top: 3px; }
    .wkr-task__note {
        font-size: 11.5px; color: #6B4423; margin-top: 3px;
        padding: 6px 8px; background: #FAF4EC; border-radius: 6px;
        border-left: 2px solid #C9B69A;
    }

    /* ── Foto before/after / bukti — pill ringkas, gambar di modal ── */
    .wkr-ba { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
    .wkr-ba__pill {
        display: inline-flex; align-items: center; gap: 7px;
        border: 1px solid #A66019; background: #FFF7EE; color: #5C2E15;
        font-size: 12px; font-weight: 700; padding: 8px 14px; border-radius: 9px;
        cursor: pointer; transition: .15s; line-height: 1;
        box-shadow: 0 1px 2px rgba(45,24,16,.08);
    }
    .wkr-ba__pill i { font-size: 13px; }
    .wkr-ba__pill-cap { text-transform: uppercase; letter-spacing: .5px; font-size: 10px; font-weight: 800; }
    /* Ada foto → tombol solid brand (jelas bisa diklik) */
    .wkr-ba__pill.has {
        border-color: transparent;
        background: linear-gradient(180deg, #6B3A1C, #4A1A05);
        color: #FFFFFF; box-shadow: 0 2px 6px rgba(71,21,0,.28);
    }
    .wkr-ba__pill.has i { color: #F7C77E; }
    .wkr-ba__pill.has:hover { transform: translateY(-1px); box-shadow: 0 6px 15px rgba(71,21,0,.34); }
    .wkr-ba__pill.has:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(71,21,0,.28); }
    .wkr-ba__pill.has .wkr-ba__pill-cap {
        color: #FBE4BE; padding-right: 9px; margin-right: 1px;
        border-right: 1px solid rgba(255,236,205,.30);
    }
    .wkr-ba__pill.has > span:last-child { color: #FFFFFF; font-weight: 800; }
    /* Kosong → chip redup, tak bisa diklik */
    .wkr-ba__pill.empty {
        border: 1px dashed #D8C4AB; background: #FAF4EC; color: #B08D66;
        cursor: default; box-shadow: none; font-weight: 700;
    }
    .wkr-ba__pill.empty .wkr-ba__pill-cap { color: #BFA684; }
    .wkr-ba__warn { margin-top: 6px; font-size: 11px; color: #A32D2D; font-weight: 600; }

    /* Modal lihat foto */
    .wkr-photo-modal { position: fixed; inset: 0; z-index: 1080; display: flex; align-items: center; justify-content: center; }
    .wkr-photo-modal[hidden] { display: none; }
    .wkr-photo-modal__backdrop { position: absolute; inset: 0; background: rgba(30,15,5,.72); }
    .wkr-photo-modal__box { position: relative; z-index: 1; max-width: 92vw; max-height: 88vh; }
    .wkr-photo-modal__box img { max-width: 92vw; max-height: 88vh; border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,.4); display: block; }
    .wkr-photo-modal__close {
        position: absolute; top: -14px; right: -14px; width: 36px; height: 36px;
        border-radius: 50%; border: none; background: #fff; color: #5C2E15;
        font-size: 15px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.25);
        display: flex; align-items: center; justify-content: center;
    }
</style>
@endsection

@section('content')
@php
    $opt = \App\Models\ChecklistWeeklySubmission::statusOptions()[$submission->status] ?? ['label'=>$submission->status,'color'=>'neutral'];
    $pct = $submission->progressPct();
    $isPending = $submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_SUBMITTED;
    $isFinal   = in_array($submission->status, [
        \App\Models\ChecklistWeeklySubmission::STATUS_APPROVED,
        \App\Models\ChecklistWeeklySubmission::STATUS_REJECTED,
    ], true);
    $dayLabels = \App\Models\ChecklistTask::dayLabels();
    $dayShorts = \App\Models\ChecklistTask::dayShortLabels();
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Detail Form Mingguan</h4>
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
                        <a href="{{ route('checklist.review.weekly') }}" class="st-btn-outline-brown" style="display:inline-flex; align-items:center; gap:7px; margin-bottom:16px;">
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
                                    <i class="fas fa-calendar-week" style="color:#9B5E2C; font-size:11px;"></i>
                                    Minggu {{ $submission->weekLabel() }}
                                </div>
                                <div style="font-size:11px; color:#9B8578; margin-top:2px;">{{ $submission->year_week }}</div>
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

                        {{-- Metadata grid --}}
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

                        @if($submission->submit_note)
                            <div style="margin-top:14px; padding:12px 14px; background:#FFF; border:1px solid #E0D2C1; border-left:4px solid #9B5E2C; border-radius:8px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#9B5E2C; margin-bottom:4px;">
                                    <i class="fas fa-pen" style="font-size:10px;"></i> Catatan karyawan
                                </div>
                                <div style="font-size:13px; color:#2D1810; line-height:1.5;">{{ $submission->submit_note }}</div>
                            </div>
                        @endif

                        @if($submission->review_notes)
                            <div style="margin-top:14px; padding:12px 14px; background:#FFF; border:1px solid #E0D2C1; border-left:4px solid {{ $submission->status === 'rejected' ? '#A32D2D' : '#1F6E5F' }}; border-radius:8px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:{{ $submission->status === 'rejected' ? '#A32D2D' : '#1F6E5F' }}; margin-bottom:4px;">
                                    Catatan supervisor
                                </div>
                                <div style="font-size:13px; color:#2D1810; line-height:1.5;">{{ $submission->review_notes }}</div>
                            </div>
                        @endif

                        @if($submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_EXCUSED && $submission->excuse_reason)
                            <div style="margin-top:14px; padding:12px 14px; background:#FFF; border:1px solid #E0D2C1; border-left:4px solid #A66019; border-radius:8px;">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px; color:#A66019; margin-bottom:4px;">
                                    <i class="fas fa-mug-hot"></i> Dikecualikan — alasan karyawan
                                </div>
                                <div style="font-size:13px; color:#2D1810; line-height:1.5;">{{ $submission->excuse_reason }}</div>
                            </div>
                        @endif
                    </div>

                    {{-- DAY BREAKDOWN (mirroring paper form: Selasa, Kamis, dst) --}}
                    <div style="padding:18px 22px; background:#FAF4EC;">
                        @if($grouped->isEmpty())
                            <div class="st-empty">
                                <i class="st-empty__icon fa fa-clipboard"></i>
                                <div class="st-empty__title">Tidak ada item</div>
                                <div class="st-empty__desc">Form Mingguan ini tidak memiliki item.</div>
                            </div>
                        @else
                            @php
                                // Hari operasional Selasa(2)–Minggu(7); Senin(1) klinik tutup.
                                // Iterate hanya key yang punya label (2–7) supaya urut & aman
                                // dari data lama yang day_of_week-nya di luar rentang.
                                $orderedDays = [];
                                foreach ($dayLabels as $dowKey => $dowLabel) {
                                    if ($grouped->has($dowKey)) $orderedDays[$dowKey] = $grouped->get($dowKey);
                                }
                            @endphp
                            @foreach($orderedDays as $dow => $dayItems)
                                @php
                                    $dayDate = $submission->week_start_date->copy()->addDays($dow - 1);
                                    $totalDay = $dayItems->count();
                                    $doneDay  = $dayItems->where('is_done', true)->count();
                                    $dayExcuseReason = $excusedDays[$dow] ?? null;
                                @endphp
                                <div class="wkr-day">
                                    <div class="wkr-day__head">
                                        <div class="wkr-day__head-left">
                                            <div class="wkr-day__num">
                                                <strong>{{ $dayDate->format('d') }}</strong>
                                                <small>{{ $dayShorts[$dow] ?? '—' }}</small>
                                            </div>
                                            <div>
                                                <h6 class="wkr-day__title">{{ $dayLabels[$dow] ?? 'Hari lain' }}</h6>
                                                <div class="wkr-day__date">{{ $dayDate->translatedFormat('d F Y') }}</div>
                                            </div>
                                        </div>
                                        @if($dayExcuseReason)
                                            <span class="st-pill st-pill--neutral"><i class="fas fa-mug-hot"></i> Dikecualikan</span>
                                        @else
                                            <span class="st-pill st-pill--{{ $doneDay === $totalDay ? 'success' : 'warning' }}">
                                                {{ $doneDay }}/{{ $totalDay }} tugas
                                            </span>
                                        @endif
                                    </div>
                                    @if($dayExcuseReason)
                                        <div style="margin:2px 0 8px; padding:9px 12px; background:#FCF4E7; border:1px solid #E7D3B8; border-left:3px solid #A66019; border-radius:8px; font-size:12.5px; color:#6B4423;">
                                            <strong>Hari ini ditandai tidak perlu diisi.</strong> Alasan: {{ $dayExcuseReason }}
                                        </div>
                                    @endif
                                    @foreach($dayItems as $item)
                                            <div class="wkr-task">
                                                <span class="wkr-task__check {{ $item->is_done ? 'is-done' : '' }}">
                                                    @if($item->is_done) ✓ @endif
                                                </span>
                                                <div class="wkr-task__name">
                                                    {{ $item->task_title_snapshot }}
                                                    @if($item->note)
                                                        <div class="wkr-task__note">
                                                            <strong style="color:#9B5E2C;">Catatan:</strong>
                                                            {{ $item->note }}
                                                        </div>
                                                    @endif
                                                    @php $isBa = (bool) $item->photo_before_after; @endphp
                                                    @if($isBa)
                                                        {{-- Before/After (divisi GS): pill ringkas, gambar di modal --}}
                                                        <div class="wkr-ba">
                                                            @foreach(['before' => 'Before', 'after' => 'After'] as $slot => $slotLabel)
                                                                @php $baPath = $item->{'photo_' . $slot . '_path'}; @endphp
                                                                @if($baPath)
                                                                    <button type="button" class="wkr-ba__pill has" data-photo-view data-url="{{ asset($baPath) }}">
                                                                        <i class="fas fa-image"></i>
                                                                        <span class="wkr-ba__pill-cap">{{ $slotLabel }}</span>
                                                                        <span>Lihat</span>
                                                                    </button>
                                                                @else
                                                                    <span class="wkr-ba__pill empty">
                                                                        <i class="fas fa-ban"></i>
                                                                        <span class="wkr-ba__pill-cap">{{ $slotLabel }}</span>
                                                                        <span>Tidak ada</span>
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    @elseif($item->requires_photo)
                                                        {{-- Foto tunggal (non-GS) --}}
                                                        @if($item->photo_path)
                                                            <div class="wkr-ba">
                                                                <button type="button" class="wkr-ba__pill has" data-photo-view data-url="{{ asset($item->photo_path) }}">
                                                                    <i class="fas fa-image"></i>
                                                                    <span class="wkr-ba__pill-cap">Foto</span>
                                                                    <span>Lihat</span>
                                                                </button>
                                                            </div>
                                                        @else
                                                            <div class="wkr-ba__warn">
                                                                <i class="fas fa-camera" style="font-size:10px; margin-right:3px;"></i> Perlu foto — belum diunggah
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                                @if($item->done_at)
                                                    <span class="wkr-task__time">{{ $item->done_at->format('H:i') }}</span>
                                                @endif
                                            </div>
                                        @endforeach
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
                                    <textarea id="reviewNotes" rows="2" class="form-control" placeholder="Mis: tugas Kamis belum lengkap, mohon dilengkapi..."></textarea>
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="POST" action="{{ route('checklist.review.weekly.approve', $submission->id) }}" style="display:inline;" onsubmit="this.querySelector('input[name=review_notes]').value = document.getElementById('reviewNotes').value; return confirm('Setujui Form Mingguan ini?');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-brand">
                                            <i class="fas fa-check-circle"></i> Setujui
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('checklist.review.weekly.reject', $submission->id) }}" style="display:inline;" onsubmit="var n=document.getElementById('reviewNotes').value.trim(); if(!n){alert('Catatan wajib diisi saat menolak.'); return false;} this.querySelector('input[name=review_notes]').value = n; return confirm('Yakin tolak Form Mingguan ini? Karyawan akan diberitahu untuk perbaiki.');">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="review_notes" value="">
                                        <button type="submit" class="st-btn-outline-brown" style="color:#A32D2D; border-color:#A32D2D;">
                                            <i class="fas fa-times-circle"></i> Tolak
                                        </button>
                                    </form>
                                </div>
                            @elseif($isFinal)
                                <form method="POST" action="{{ route('checklist.review.weekly.reopen', $submission->id) }}" onsubmit="return confirm('Reopen Form Mingguan ini? Status akan kembali ke Menunggu Approval.');">
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

                    @if($submission->status === \App\Models\ChecklistWeeklySubmission::STATUS_EXCUSED)
                        <div style="padding:18px 22px; border-top:1px solid #E0D2C1; background:#FFFFFF;">
                            <form method="POST" action="{{ route('checklist.review.weekly.excuse.revoke', $submission->id) }}"
                                  onsubmit="return confirm('Batalkan pengecualian ini? Karyawan akan diminta mengisi Form Mingguan.');">
                                @csrf @method('PATCH')
                                <button type="submit" class="st-btn-outline-brown" style="color:#A32D2D; border-color:#A32D2D;">
                                    <i class="fas fa-undo"></i> Batalkan Pengecualian
                                </button>
                                <small style="color:#6B4423; margin-left:8px;">
                                    Karyawan menandai minggu ini "tidak perlu diisi". Batalkan bila tidak valid.
                                </small>
                            </form>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal lihat foto (before/after & bukti) --}}
<div class="wkr-photo-modal" id="wkrPhotoModal" hidden>
    <div class="wkr-photo-modal__backdrop" data-modal-close></div>
    <div class="wkr-photo-modal__box">
        <button type="button" class="wkr-photo-modal__close" data-modal-close aria-label="Tutup"><i class="fas fa-times"></i></button>
        <img id="wkrPhotoModalImg" src="" alt="Foto bukti">
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('wkrPhotoModal');
    var img   = document.getElementById('wkrPhotoModalImg');
    function open(url) { if (! modal || ! img) return; img.src = url; modal.hidden = false; document.body.style.overflow = 'hidden'; }
    function close() { if (! modal) return; modal.hidden = true; if (img) img.src = ''; document.body.style.overflow = ''; }
    document.querySelectorAll('[data-photo-view]').forEach(function (btn) {
        btn.addEventListener('click', function () { if (btn.dataset.url) open(btn.dataset.url); });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endsection
