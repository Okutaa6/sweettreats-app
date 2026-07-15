{{--
    Form karyawan mengajukan posisi. Divisi OTOMATIS dari role (dikunci) —
    karyawan cukup pilih cabang. Var: $roleDivision, $branches, $submitLabel, $pending.
--}}
@php $pd = $pending ?? null; @endphp

@if($errors->any())
    <div style="background:#FCEBEB; border:1px solid #E7B7B7; color:#7C2929; border-radius:10px; padding:10px 14px; font-size:12.5px; margin-bottom:12px;">
        <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
    </div>
@endif

<form method="POST" action="{{ route('mychecklist.position.propose') }}"
      onsubmit="return confirm('Ajukan posisi ini ke supervisor untuk dikonfirmasi?');">
    @csrf
    <div class="form-group" style="margin-bottom:12px;">
        <label style="font-size:12px; font-weight:700; color:#6B4423; display:block; margin-bottom:5px;">Divisi</label>
        <div class="form-control" style="display:flex; align-items:center; gap:7px; background:#F6EFE6; color:#5C2E15; font-weight:700;">
            <i class="fas fa-lock" style="font-size:11px; color:#9B5E2C;"></i>
            {{ optional($roleDivision)->name ?? '—' }}
            <span style="font-weight:500; font-size:11.5px; color:#9B5E2C;">(otomatis dari role Anda)</span>
        </div>
    </div>
    <div class="form-group" style="margin-bottom:16px;">
        <label style="font-size:12px; font-weight:700; color:#6B4423; display:block; margin-bottom:5px;">Cabang <span style="color:#A32D2D;">*</span></label>
        <select name="raw_branch_id" class="form-control" required>
            <option value="">— Pilih cabang —</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ $pd && $pd->raw_branch_id === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="st-btn-brand" style="width:100%; justify-content:center; padding:11px; font-size:13.5px; font-weight:700;">
        <i class="fas fa-paper-plane"></i> {{ $submitLabel ?? 'Ajukan posisi' }}
    </button>
</form>
