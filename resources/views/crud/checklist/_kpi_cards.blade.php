{{--
    PARTIAL: 4 KPI card dashboard checklist — inline style mengikuti handoff `01-dashboard`.
    Variable: $kpis dengan key: total_active, submitted, pending, approved, rejected, excused, not_submitted
--}}
@php
    $pctSubmit = $kpis['total_active'] > 0
        ? (int) round($kpis['submitted'] / $kpis['total_active'] * 100)
        : 0;
    $excusedCount = (int) ($kpis['excused'] ?? 0);
    $badTotal     = (int) $kpis['not_submitted'] + (int) $kpis['rejected'];
@endphp

<div class="dash-kpis">
    {{-- Karyawan aktif (neutral) --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(70,21,0,.04)">
        <div style="font-size:11px;font-weight:800;letter-spacing:.5px;color:#9A7256">KARYAWAN AKTIF</div>
        <div style="font-size:30px;font-weight:800;color:#3B1500;margin-top:6px;font-variant-numeric:tabular-nums">{{ $kpis['total_active'] }}</div>
        <div style="font-size:11.5px;color:#9A7256;margin-top:2px">divisi aktif hari ini</div>
    </div>

    {{-- Sudah submit (hijau) --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(70,21,0,.04)">
        <div style="font-size:11px;font-weight:800;letter-spacing:.5px;color:#2F8E55">SUDAH SUBMIT</div>
        <div style="display:flex;align-items:baseline;gap:8px;margin-top:6px">
            <span style="font-size:30px;font-weight:800;color:#2F8E55;font-variant-numeric:tabular-nums">{{ $kpis['submitted'] }}</span>
            <span style="font-size:13px;font-weight:800;color:#2F8E55;background:#E3F4E8;border-radius:20px;padding:2px 9px;font-variant-numeric:tabular-nums">{{ $pctSubmit }}%</span>
        </div>
        <div style="font-size:11.5px;color:#9A7256;margin-top:2px">dari {{ $kpis['total_active'] }} karyawan @if($excusedCount > 0)· {{ $excusedCount }} dikecualikan @endif</div>
    </div>

    {{-- Menunggu approval (kuning, clickable, border amber) --}}
    <a href="{{ route('checklist.review', ['status' => 'submitted']) }}" class="dash-row" style="display:block;text-decoration:none;background:#fff;border:1px solid #F0D9AE;border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(70,21,0,.04)">
        <div style="font-size:11px;font-weight:800;letter-spacing:.5px;color:#B5701A">MENUNGGU APPROVAL</div>
        <div style="font-size:30px;font-weight:800;color:#B5701A;margin-top:6px;font-variant-numeric:tabular-nums">{{ $kpis['pending'] }}</div>
        <div style="font-size:11.5px;font-weight:700;color:#B5701A;margin-top:2px">Klik untuk review →</div>
    </a>

    {{-- Belum diisi / ditolak (merah) --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:16px 18px;box-shadow:0 1px 2px rgba(70,21,0,.04)">
        <div style="font-size:11px;font-weight:800;letter-spacing:.5px;color:#C0392B">BELUM DIISI / DITOLAK</div>
        <div style="font-size:30px;font-weight:800;color:#C0392B;margin-top:6px;font-variant-numeric:tabular-nums">{{ $badTotal }}</div>
        <div style="font-size:11.5px;color:#9A7256;margin-top:2px">{{ $kpis['not_submitted'] }} belum · {{ $kpis['rejected'] }} ditolak</div>
    </div>
</div>
