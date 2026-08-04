{{--
    Field form Tambah / Edit Unit AC (dipakai dua modal di monitor.blade.php).
    Param: $branches (collection, kosong kalau supervisor branch-locked), $branchId
--}}
 @if($branches->count())
    <label class="ac-form__field">
        <span>Cabang</span>
        <select name="raw_branch_id" required>
            <option value="">— Pilih Cabang —</option>
             @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
             @endforeach
        </select>
    </label>
 @else
    <input type="hidden" name="raw_branch_id" value="{{ session('raw_branch_id') }}">
 @endif

<label class="ac-form__field">
    <span>Nama ruangan / titik AC</span>
    <input type="text" name="room_name" maxlength="255" required placeholder="mis. DEPAN FO, RUANG TUNGGU, POLI 1">
</label>

<div class="ac-form__grid">
    <label class="ac-form__field">
        <span>Kapasitas (PK)</span>
        <input type="number" name="capacity_pk" min="0.25" max="10" step="0.25" placeholder="mis. 1.5">
    </label>
    <label class="ac-form__field">
        <span>Interval cuci (hari)</span>
        <input type="number" name="wash_interval_days" min="7" max="365" step="1" value="60" required>
    </label>
</div>

<div class="ac-form__grid">
    <label class="ac-form__field">
        <span>Urutan tampil</span>
        <input type="number" name="sort_order" min="0" step="1" value="0">
    </label>
    <label class="ac-form__field">
        <span>Catatan (opsional)</span>
        <input type="text" name="notes" maxlength="1000" placeholder="mis. unit lama, sering bunyi">
    </label>
</div>
