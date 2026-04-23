@extends('layouts.app')

@section('css')
<style>
    .cre-info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px 24px;
    }
    .cre-info-item .cre-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--st-muted);
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .cre-info-item .cre-value {
        font-size: 14px;
        color: #333;
        word-break: break-word;
    }
    .cre-info-item .cre-value a {
        color: var(--st-primary);
        text-decoration: underline;
    }
    .cre-caption-box {
        background: var(--st-bg, #faf5f3);
        border-left: 3px solid var(--st-accent);
        padding: 10px 14px;
        font-size: 13px;
        color: #555;
        font-style: italic;
        margin-top: 6px;
    }
    .cre-section-title {
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--st-primary);
        letter-spacing: 0.5px;
        margin: 0 0 14px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--st-accent);
    }
    .cre-status-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    .cre-status-option {
        border: 2px solid var(--st-border);
        padding: 14px 10px;
        cursor: pointer;
        text-align: center;
        transition: all .15s ease;
        background: #fff;
        margin: 0;
    }
    .cre-status-option input { display: none; }
    .cre-status-option.selected {
        border-color: var(--st-primary);
        background: var(--st-bg, #faf5f3);
    }
    .cre-status-option .cre-opt-name {
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
        margin-top: 6px;
    }
    .cre-status-option.pending .cre-opt-name  { color: var(--st-pending-text, #c08a1a); }
    .cre-status-option.approved .cre-opt-name { color: var(--st-completed-text, #2e7d32); }
    .cre-status-option.rejected .cre-opt-name { color: var(--st-cancelled-text, #c62828); }
    .cre-status-option .cre-opt-desc {
        font-size: 11px;
        color: var(--st-muted);
        margin-top: 2px;
    }

    @media (max-width: 720px) {
        .cre-info-grid,
        .cre-status-options { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="page-title">Edit Review Konten Referral</h4>
                    <small class="text-muted">
                        <a href="{{ route('content.review') }}" style="color:var(--st-muted);">Review Konten</a>
                        &rsaquo; Edit
                    </small>
                </div>
                <a href="{{ route('content.review') }}" class="st-btn-outline-brown">
                    <i class="fa fa-arrow-left mr-1"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    @if($errors->any())
        <div class="st-alert st-alert--danger mb-3">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        {{-- LEFT: CONTENT INFO --}}
        <div class="col-lg-5 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <div class="cre-section-title">Info Konten</div>

                    <div class="cre-info-grid">
                        <div class="cre-info-item">
                            <div class="cre-label">Referral</div>
                            <div class="cre-value">
                                <strong class="st-primary">{{ $content->affiliate->name ?? '-' }}</strong><br>
                                <small class="st-muted">{{ $content->affiliate->phone ?? '-' }}</small>
                            </div>
                        </div>
                        <div class="cre-info-item">
                            <div class="cre-label">Tipe</div>
                            <div class="cre-value">
                                @if($content->affiliate && $content->affiliate->type)
                                    @php
                                        $typePill = $content->affiliate->type === 'koc' ? 'st-pill--info' : 'st-pill--primary';
                                    @endphp
                                    <span class="st-pill st-pill--plain {{ $typePill }}">{{ strtoupper($content->affiliate->type) }}</span>
                                @else
                                    <span class="st-muted">-</span>
                                @endif
                            </div>
                        </div>

                        <div class="cre-info-item">
                            <div class="cre-label">Platform</div>
                            <div class="cre-value">{{ ucfirst($content->platform) }}</div>
                        </div>
                        <div class="cre-info-item">
                            <div class="cre-label">Tipe Konten</div>
                            <div class="cre-value">{{ ucfirst($content->content_type) }}</div>
                        </div>

                        <div class="cre-info-item" style="grid-column: 1 / -1;">
                            <div class="cre-label">Link Konten</div>
                            <div class="cre-value">
                                <a href="{{ $content->content_url }}" target="_blank" rel="noopener">
                                    {{ $content->content_url }}
                                    <i class="fa fa-external-link-alt ml-1" style="font-size:11px;"></i>
                                </a>
                            </div>
                        </div>

                        @if($content->caption)
                            <div class="cre-info-item" style="grid-column: 1 / -1;">
                                <div class="cre-label">Caption</div>
                                <div class="cre-caption-box">{{ $content->caption }}</div>
                            </div>
                        @endif

                        <div class="cre-info-item">
                            <div class="cre-label">Status Saat Ini</div>
                            <div class="cre-value">
                                @php
                                    $statusPillMap = [
                                        'pending'  => 'st-pill--warning',
                                        'approved' => 'st-pill--success',
                                        'rejected' => 'st-pill--danger',
                                    ];
                                    $statusPill = $statusPillMap[$content->status] ?? 'st-pill--neutral';
                                @endphp
                                <span class="st-pill {{ $statusPill }}">{{ ucfirst($content->status) }}</span>
                            </div>
                        </div>
                        <div class="cre-info-item">
                            <div class="cre-label">Dibuat</div>
                            <div class="cre-value">
                                <span class="st-date">
                                    {{ $content->created_at ? \Carbon\Carbon::parse($content->created_at)->format('d M Y H:i') : '-' }}
                                </span>
                            </div>
                        </div>

                        @if($content->reviewed_at)
                            <div class="cre-info-item">
                                <div class="cre-label">Reviewed By</div>
                                <div class="cre-value">{{ $content->reviewer->name ?? '-' }}</div>
                            </div>
                            <div class="cre-info-item">
                                <div class="cre-label">Reviewed At</div>
                                <div class="cre-value">
                                    <span class="st-date">
                                        {{ \Carbon\Carbon::parse($content->reviewed_at)->format('d M Y H:i') }}
                                    </span>
                                </div>
                            </div>
                        @endif

                        @if($content->review_notes)
                            <div class="cre-info-item" style="grid-column: 1 / -1;">
                                <div class="cre-label">Catatan Sebelumnya</div>
                                <div class="cre-caption-box">{{ $content->review_notes }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: EDIT FORM --}}
        <div class="col-lg-7 mb-3">
            <div class="st-card">
                <div class="st-card__body">
                    <div class="cre-section-title">Update Review</div>

                    <form method="POST" action="{{ route('content.review.update', $content->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label class="st-form-label">Status Keputusan</label>
                            <div class="cre-status-options">
                                @php $currentStatus = old('status', $content->status); @endphp
                                <label class="cre-status-option pending {{ $currentStatus === 'pending' ? 'selected' : '' }}"
                                       onclick="document.querySelectorAll('.cre-status-option').forEach(o=>o.classList.remove('selected'));this.classList.add('selected');toggleNotesRequired();">
                                    <input type="radio" name="status" value="pending" {{ $currentStatus === 'pending' ? 'checked' : '' }} onchange="toggleNotesRequired();">
                                    <i class="fa fa-clock" style="font-size:20px; color:var(--st-pending-text, #c08a1a);"></i>
                                    <div class="cre-opt-name">Pending</div>
                                    <div class="cre-opt-desc">Balik ke menunggu review</div>
                                </label>
                                <label class="cre-status-option approved {{ $currentStatus === 'approved' ? 'selected' : '' }}"
                                       onclick="document.querySelectorAll('.cre-status-option').forEach(o=>o.classList.remove('selected'));this.classList.add('selected');toggleNotesRequired();">
                                    <input type="radio" name="status" value="approved" {{ $currentStatus === 'approved' ? 'checked' : '' }} onchange="toggleNotesRequired();">
                                    <i class="fa fa-check-circle" style="font-size:20px; color:var(--st-completed-text, #2e7d32);"></i>
                                    <div class="cre-opt-name">Approved</div>
                                    <div class="cre-opt-desc">Setujui konten ini</div>
                                </label>
                                <label class="cre-status-option rejected {{ $currentStatus === 'rejected' ? 'selected' : '' }}"
                                       onclick="document.querySelectorAll('.cre-status-option').forEach(o=>o.classList.remove('selected'));this.classList.add('selected');toggleNotesRequired();">
                                    <input type="radio" name="status" value="rejected" {{ $currentStatus === 'rejected' ? 'checked' : '' }} onchange="toggleNotesRequired();">
                                    <i class="fa fa-times-circle" style="font-size:20px; color:var(--st-cancelled-text, #c62828);"></i>
                                    <div class="cre-opt-name">Rejected</div>
                                    <div class="cre-opt-desc">Tolak konten ini</div>
                                </label>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label class="st-form-label" for="review_notes">
                                Catatan Review
                                <span id="notes-required-hint" style="color: var(--st-cancelled-text, #c62828); display:none;">(wajib untuk reject)</span>
                            </label>
                            <textarea name="review_notes" id="review_notes" class="form-control" rows="4" maxlength="1000"
                                      placeholder="Catatan untuk referral (opsional, wajib kalau reject)...">{{ old('review_notes', $content->review_notes) }}</textarea>
                            <small class="st-muted">Max 1000 karakter. Catatan akan terlihat oleh referral di portal.</small>
                        </div>

                        <div class="d-flex" style="gap:8px; flex-wrap:wrap; margin-top:18px;">
                            <button type="submit" class="st-btn-brand">
                                <i class="fa fa-save mr-1"></i> Update Review
                            </button>
                            <a href="{{ route('content.review') }}" class="st-btn-outline-brown">
                                <i class="fa fa-times mr-1"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleNotesRequired() {
        var status = document.querySelector('input[name="status"]:checked');
        var hint = document.getElementById('notes-required-hint');
        var textarea = document.getElementById('review_notes');
        if (!status) return;
        if (status.value === 'rejected') {
            hint.style.display = 'inline';
            textarea.setAttribute('required', 'required');
        } else {
            hint.style.display = 'none';
            textarea.removeAttribute('required');
        }
    }
    document.addEventListener('DOMContentLoaded', toggleNotesRequired);
</script>
@endsection
