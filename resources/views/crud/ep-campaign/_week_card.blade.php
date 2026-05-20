{{-- Single week card untuk campaign breakdown --}}
@php
    $num = $idx + 1;
    $week = $week ?? ['fokus' => '', 'deliverables' => ''];
@endphp
<div class="cmp-week-card" data-idx="{{ $idx }}">
    <div class="cmp-week-card-head">
        <div class="cmp-week-card-num">
            <span class="cmp-week-num-pill">{{ $num }}</span>
            <span class="cmp-week-num-text">Minggu {{ $num }}</span>
        </div>
        @if ($num > 4)
            <button type="button" class="cmp-week-remove-btn" onclick="removeWeek(this)" title="Hapus minggu ini">
                <i class="fa fa-times"></i>
            </button>
        @endif
    </div>
    <div class="cmp-week-row">
        <label class="cmp-week-label">Fokus minggu ini</label>
        <input type="text" name="weeks[{{ $idx }}][fokus]" class="cmp-week-input"
               value="{{ old('weeks.' . $idx . '.fokus', $week['fokus'] ?? '') }}"
               placeholder="Mis. Teaser & build hype">
    </div>
    <div class="cmp-week-row">
        <label class="cmp-week-label">Deliverables</label>
        <textarea name="weeks[{{ $idx }}][deliverables]" rows="2" class="cmp-week-input"
                  placeholder="Apa saja yang harus dideliver minggu ini...">{{ old('weeks.' . $idx . '.deliverables', $week['deliverables'] ?? '') }}</textarea>
    </div>
</div>
