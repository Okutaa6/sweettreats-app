{{--
    Kartu satu unit AC — desain mengikuti handoff "Jadwal Cuci AC" (hifi).
    Mode:
      - 'gs'         : + form inline "Catat Servis" (rincian biaya repeatable)
      - 'supervisor' : + tombol Riwayat / Edit / Hapus
    Prasyarat: $unit di-load lewat AcUnit::withServiceSummary() supaya
    accessor next_due_date / due_status jalan.
    Param: $unit, $mode, $today, $showBranch (opsional, default false)
--}}
@php
    $meta      = \App\Models\AcUnit::statusMeta()[$unit->due_status];
    $attrs     = $unit->getAttributes();
    $lastWash  = ! empty($attrs['last_wash_date']) ? \Illuminate\Support\Carbon::parse($attrs['last_wash_date']) : null;
    $lastFreon = ! empty($attrs['last_freon_date']) ? \Illuminate\Support\Carbon::parse($attrs['last_freon_date']) : null;
    $due       = $unit->next_due_date;

    $dueLate = false;
    if ($due === null) {
        $dueSub  = '(Belum pernah dicuci)';
        $dueLate = true;
    } elseif ($due->lt($today)) {
        $dueSub  = '(Terlambat ' . $due->diffInDays($today) . ' hari)';
        $dueLate = true;
    } elseif ($due->isSameDay($today)) {
        $dueSub = '(Jatuh tempo hari ini)';
    } else {
        $dueSub = '(' . $today->diffInDays($due) . ' hari lagi)';
    }

    $pkLabel = $unit->capacity_pk ? rtrim(rtrim(number_format($unit->capacity_pk, 2, ',', '.'), '0'), ',') . ' PK' : null;
    $showBranch = $showBranch ?? false;
@endphp
<div class="ac-card" data-ac-status="{{ $unit->due_status }}">
    <div class="ac-card__head">
        <div class="ac-card__title">
            <div class="ac-card__room">{{ $unit->room_name }}</div>
            <div class="ac-card__meta">
                 @if($pkLabel)
                    <span class="ac-chip"><i class="fas fa-fan"></i> {{ $pkLabel }}</span>
                 @endif
                 @if($showBranch && $unit->branch)
                    <span class="ac-chip"><i class="fas fa-building"></i> {{ $unit->branch->name }}</span>
                 @endif
                 @php $pendingN = (int) ($attrs['pending_count'] ?? 0); @endphp
                 @if($pendingN > 0)
                    <span class="ac-chip" style="background:#FAEEDA;color:#A66019;border-color:#F1D9B4;"><i class="fas fa-hourglass-half"></i> {{ $pendingN }} menunggu approval</span>
                 @endif
            </div>
        </div>
        <span class="ac-pill {{ $meta['class'] }}"><span class="ac-pill__dot"></span>{{ $unit->due_status_label }}</span>
    </div>

    <div class="ac-card__info">
        <div class="ac-card__row">
            <span class="ac-card__row-label">Cuci terakhir</span>
            <span class="ac-card__row-val">{{ $lastWash ? $lastWash->translatedFormat('d M Y') : '—' }}</span>
        </div>
        <div class="ac-card__row">
            <span class="ac-card__row-label">Isi freon terakhir</span>
            <span class="ac-card__row-val">{{ $lastFreon ? $lastFreon->translatedFormat('d M Y') : '—' }}</span>
        </div>
        <div class="ac-card__row">
            <span class="ac-card__row-label">Jatuh tempo cuci</span>
            <span class="ac-card__row-val">
                {{ $due ? $due->translatedFormat('d M Y') : '—' }}
                <small class="{{ $dueLate ? 'is-late' : '' }}">{{ $dueSub }}</small>
            </span>
        </div>
    </div>

     @if($mode === 'gs')
        <div class="ac-card__foot">
            <button type="button" class="ac-btn-record" data-ac-form-toggle="acForm-{{ $unit->id }}"
                data-label-open="Catat Servis" data-label-close="Tutup Form">
                <i class="fas fa-plus"></i> <span>Catat Servis</span>
            </button>
            <button type="button" class="ac-btn-outline"
                data-ac-edit="{{ $unit->id }}"
                data-room="{{ $unit->room_name }}"
                data-branch="{{ $unit->raw_branch_id }}"
                data-pk="{{ $unit->capacity_pk }}"
                data-interval="{{ $unit->wash_interval_days }}"
                data-sort="{{ $unit->sort_order }}"
                data-notes="{{ $unit->notes }}"
                data-updated="{{ $unit->updated_at ? $unit->updated_at->toIso8601String() : '' }}">
                <i class="fas fa-pen"></i> Edit
            </button>
            <button type="button" class="ac-btn-outline" data-ac-history="{{ $unit->id }}" data-room="{{ $unit->room_name }}">
                <i class="fas fa-history"></i> Riwayat
            </button>
        </div>
        @include('crud.checklist.ac._service_form', ['action' => route('mychecklist.ac.service.store')])
     @else
        <div class="ac-card__foot ac-card__foot--grid">
            <button type="button" class="ac-btn-outline" data-ac-service-open="{{ $unit->id }}" data-room="{{ $unit->room_name }}">
                <i class="fas fa-plus"></i> Catat Servis
            </button>
            <button type="button" class="ac-btn-outline"
                data-ac-edit="{{ $unit->id }}"
                data-room="{{ $unit->room_name }}"
                data-branch="{{ $unit->raw_branch_id }}"
                data-pk="{{ $unit->capacity_pk }}"
                data-interval="{{ $unit->wash_interval_days }}"
                data-sort="{{ $unit->sort_order }}"
                data-notes="{{ $unit->notes }}"
                data-updated="{{ $unit->updated_at ? $unit->updated_at->toIso8601String() : '' }}">
                <i class="fas fa-pen"></i> Edit
            </button>
            <button type="button" class="ac-btn-outline" data-ac-history="{{ $unit->id }}" data-room="{{ $unit->room_name }}">
                <i class="fas fa-history"></i> Riwayat
            </button>
            <form method="POST" action="{{ route('checklist.ac.unit.destroy', $unit->id) }}"
                  data-confirm="Hapus unit AC {{ $unit->room_name }}? Riwayat servisnya tetap tersimpan.">
                @csrf
                @method('DELETE')
                <button type="submit" class="ac-btn-outline ac-btn-outline--danger"><i class="fas fa-trash-alt"></i> Hapus</button>
            </form>
        </div>
     @endif
</div>
