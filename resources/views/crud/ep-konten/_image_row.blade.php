@php
    // Partial: render 1 image sebagai SLIDE Instagram-style.
    // Single image → tampil sendiri tanpa nav. Multiple → di-wrap carousel oleh
    // parent _image_section.blade.php.
    $statusOpt = \App\Models\EpKontenImage::statusOptions()[$img->review_status] ?? null;
    $statusLbl = $statusOpt['label'] ?? $img->review_status;
    $statusCol = $statusOpt['color'] ?? 'warning';
    $sizeKb    = (int) round($img->size_bytes / 1024);
@endphp
<div class="ep-img-slide" data-image-id="{{ $img->id }}" data-status="{{ $img->review_status }}">
    {{-- ─── Media (gambar besar Instagram-style) ─── --}}
    <div class="ep-img-slide__media">
        <img src="{{ $img->large_url }}" alt="{{ $img->original_name }}" data-action="view" loading="lazy">
    </div>

    {{-- ─── Action bar di bawah gambar ─── --}}
    <div class="ep-img-slide__bar">
        <div class="ep-img-slide__bar-left">
            <span class="ep-img-name" title="{{ $img->original_name }}">{{ $img->original_name }}</span>
            <span class="ep-img-size">{{ $sizeKb }} KB</span>
            <span class="ep-img-badge ep-img-badge--{{ $statusCol }}">
                @if($img->review_status === 'approved')
                    <i class="fa fa-check"></i> {{ strtoupper($statusLbl) }}
                @elseif($img->review_status === 'revisi')
                    <i class="fa fa-flag"></i> {{ strtoupper($statusLbl) }}
                @else
                    {{ strtoupper($statusLbl) }}
                @endif
                @if(in_array($img->review_status, ['approved', 'revisi'], true))
                    <button type="button" class="ep-img-badge__clear" data-review="reset"
                            title="Reset status ke Pending">
                        <i class="fa fa-times"></i>
                    </button>
                @endif
            </span>
        </div>
        <div class="ep-img-slide__bar-right">
            <a class="ep-img-icon-btn" href="{{ route('ep-konten-image.download', $img->id) }}" title="Download">
                <i class="fa fa-download"></i>
            </a>
            <button type="button" class="ep-img-icon-btn" data-action="flag" title="Review">
                <i class="fa fa-flag"></i>
            </button>
            <button type="button" class="ep-img-icon-btn ep-img-icon-btn--danger" data-action="delete" title="Hapus">
                <i class="fa fa-trash"></i>
            </button>
        </div>
    </div>

    {{-- ─── Sub-info: waktu upload + catatan review (approved/revisi) ─── --}}
    <div class="ep-img-slide__sub">
        <span class="ep-img-meta">{{ $img->created_at?->diffForHumans() }}</span>
        @if(in_array($img->review_status, ['approved', 'revisi'], true) && $img->review_note)
            <div class="ep-img-review-comment" title="Catatan review">
                <i class="fa fa-comment-dots"></i> {{ $img->review_note }}
            </div>
        @endif
    </div>

    {{-- ─── Panel review (inline expand) ─── --}}
    <div class="ep-img-review-panel" hidden>
        <div class="ep-img-review-panel__heading">TANDAI FILE INI SEBAGAI...</div>
        <textarea class="ep-img-review-note" rows="2"
                  placeholder="Catatan / komentar review (opsional). Mis: 'Ganti angka jadi Rp 50jt'">{{ in_array($img->review_status, ['approved', 'revisi'], true) ? $img->review_note : '' }}</textarea>
        <div class="ep-img-review-panel__actions">
            <button type="button" class="ep-img-rbtn ep-img-rbtn--cancel" data-review="cancel">Batal</button>
            <button type="button" class="ep-img-rbtn ep-img-rbtn--approve" data-review="approve">
                <i class="fa fa-check"></i> Disetujui
            </button>
            <button type="button" class="ep-img-rbtn ep-img-rbtn--revisi" data-review="revisi">
                <i class="fa fa-flag"></i> Perlu Revisi
            </button>
        </div>
    </div>
</div>
