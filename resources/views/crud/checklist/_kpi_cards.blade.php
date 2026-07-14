{{--
    PARTIAL: 4 KPI card dashboard checklist.
    Variable: $kpis dengan key: total_active, submitted, pending, approved, rejected, excused, not_submitted
--}}
@php
    $pctSubmit = $kpis['total_active'] > 0
        ? (int) round($kpis['submitted'] / $kpis['total_active'] * 100)
        : 0;
    // Precompute — hindari nested parens di dalam @if (bikin directive Blade bocor jadi teks).
    $excusedCount = (int) ($kpis['excused'] ?? 0);
@endphp

<div class="cl-kpi-row">
    {{-- Total karyawan aktif --}}
    <div class="cl-kpi cl-kpi--neutral">
        <span class="cl-kpi__icon"><i class="fas fa-users"></i></span>
        <div class="cl-kpi__body">
            <div class="cl-kpi__label">Karyawan Aktif</div>
            <div class="cl-kpi__value">{{ $kpis['total_active'] }}</div>
            <div class="cl-kpi__sub">divisi aktif hari ini</div>
        </div>
    </div>

    {{-- Sudah submit --}}
    <div class="cl-kpi cl-kpi--good">
        <span class="cl-kpi__icon"><i class="fas fa-check-circle"></i></span>
        <div class="cl-kpi__body">
            <div class="cl-kpi__label">Sudah Submit</div>
            <div class="cl-kpi__value">{{ $kpis['submitted'] }}<small>({{ $pctSubmit }}%)</small></div>
            <div class="cl-kpi__sub">dari {{ $kpis['total_active'] }} karyawan@if($excusedCount > 0) · {{ $excusedCount }} dikecualikan@endif</div>
        </div>
    </div>

    {{-- Menunggu approval (clickable → review queue) --}}
    <a href="{{ route('checklist.review', ['status' => 'submitted']) }}" class="cl-kpi cl-kpi--warn">
        <span class="cl-kpi__icon"><i class="fas fa-clock"></i></span>
        <div class="cl-kpi__body">
            <div class="cl-kpi__label">Menunggu Approval</div>
            <div class="cl-kpi__value">{{ $kpis['pending'] }}</div>
            <div class="cl-kpi__sub cl-kpi__cta">Klik untuk review &nbsp;→</div>
        </div>
    </a>

    {{-- Belum diisi / ditolak --}}
    <div class="cl-kpi cl-kpi--bad">
        <span class="cl-kpi__icon"><i class="fas fa-exclamation-triangle"></i></span>
        <div class="cl-kpi__body">
            <div class="cl-kpi__label">Belum Diisi / Ditolak</div>
            <div class="cl-kpi__value">{{ $kpis['not_submitted'] + $kpis['rejected'] }}</div>
            <div class="cl-kpi__sub">{{ $kpis['not_submitted'] }} belum · {{ $kpis['rejected'] }} ditolak</div>
        </div>
    </div>
</div>
