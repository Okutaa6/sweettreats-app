{{-- Field "Jadwal tugas": tugas muncul sama tiap minggu, atau ganti tergantung minggu ke-berapa.
     $selected = array int minggu terpilih; kosong = sama tiap minggu. Server (normalizeWeeksOfMonth)
     jadi sumber kebenaran — JS di index hanya show/hide chip minggu (progressive enhancement). --}}
@php
    $wsel  = $selected ?? [];
    $wmode = empty($wsel) ? 'all' : 'specific';
@endphp
<div class="form-group cl-wk">
    <label>Jadwal Tugas <small class="text-muted">— tiap minggu sama, atau ganti-ganti?</small></label>

    <div class="cl-wk__modes">
        <label class="cl-wk__mode">
            <input type="radio" name="weeks_mode" value="all" class="cl-wk-mode" {{ $wmode === 'all' ? 'checked' : '' }}>
            <span>
                <strong>Sama tiap minggu</strong>
                <small>Muncul di semua minggu</small>
            </span>
        </label>
        <label class="cl-wk__mode">
            <input type="radio" name="weeks_mode" value="specific" class="cl-wk-mode" {{ $wmode === 'specific' ? 'checked' : '' }}>
            <span>
                <strong>Ganti tiap minggu</strong>
                <small>Pilih minggu ke berapa saja</small>
            </span>
        </label>
    </div>

    <div class="cl-wk__weeks {{ $wmode === 'specific' ? '' : 'is-hidden' }}">
        @for($w = 1; $w <= 5; $w++)
            <label class="cl-wk__chip">
                <input type="checkbox" name="weeks_of_month[]" value="{{ $w }}"
                       {{ in_array($w, $wsel, true) ? 'checked' : '' }}
                       {{ $wmode === 'specific' ? '' : 'disabled' }}>
                <span>Minggu {{ $w }}</span>
            </label>
        @endfor
    </div>
</div>
