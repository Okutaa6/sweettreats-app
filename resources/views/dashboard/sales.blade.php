@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <form class="form-inline" method="GET">
                        <input type="date" name="date" value="{{ $dateYmd }}" class="form-control form-control-sm mr-1">
                        <button class="st-btn-brand"><i class="fa fa-filter"></i> Lihat</button>
                    </form>
                </div>
                <h4 class="page-title">
                    <i class="fa fa-handshake mr-2" style="color:var(--brown)"></i>
                    Sales Dashboard
                    <span style="font-size:0.85rem; color:var(--text-subtle); font-weight:400;">
                        — {{ $dayLabel }}{{ $isToday ? ' (Hari Ini)' : '' }}
                    </span>
                </h4>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row mb-3">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-calendar-day mr-1"></i> Reservasi</p>
                <p class="st-dash-card__value">{{ $summary['total_reservasi'] }}</p>
                <small style="color:var(--text-subtle)">
                    {{ $summary['reservasi_status']->get('pending', 0) }} pending ·
                    {{ $summary['reservasi_status']->get('confirmed', 0) }} confirmed
                </small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-calendar-check mr-1"></i> Pemasukan (Plan)</p>
                <p class="st-dash-card__value" style="color:#1E5A8C">Rp {{ number_format($summary['planned_income'], 0, ',', '.') }}</p>
                <small style="color:var(--text-subtle)">dari reservasi SweetTreats</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-coins mr-1"></i> Pemasukan (Aktual)</p>
                <p class="st-dash-card__value" style="color:#2E7D32">Rp {{ number_format($summary['actual_income'], 0, ',', '.') }}</p>
                <small style="color:var(--text-subtle)">{{ $summary['actual_visits'] }} kunjungan · data Omnicare</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="st-dash-card st-exec-summary">
                <p class="st-dash-card__label"><i class="fa fa-user-plus mr-1"></i> Pasien Baru</p>
                <p class="st-dash-card__value">{{ $summary['pasien_baru'] }}</p>
                <small style="color:var(--text-subtle)">reservasi pasien baru</small>
            </div>
        </div>
    </div>

    {{-- Stale Branch Alert --}}
    @if($staleBranches->isNotEmpty())
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card" style="border-left:4px solid #B23A3A; background:#FEF2F2;">
                <div class="st-card__body" style="padding:0.85rem 1.25rem;">
                    <strong style="color:#B23A3A;"><i class="fa fa-exclamation-triangle mr-1"></i> Perhatian — Cabang tanpa data Omnicare ≥ 7 hari:</strong>
                    <span style="color:#7A2E2E;">
                        @foreach($staleBranches as $sb)
                            {{ $sb->name }} ({{ $sb->days_since === null ? 'belum ada data' : round($sb->days_since) . ' hari lalu' }}){{ !$loop->last ? ' · ' : '' }}
                        @endforeach
                    </span>
                    <br><small style="color:#9A5858;">Cek kemungkinan staff belum input ke Omnicare atau cabang ada kendala operasional.</small>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Reservasi per Cabang --}}
    <div class="row mb-3">
        <div class="col-12">
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-building"></i> Reservasi per Cabang</h6>
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th>Cabang</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Pending</th>
                                    <th class="text-center">Confirmed</th>
                                    <th class="text-center">Selesai</th>
                                    <th class="text-center">Batal</th>
                                    <th class="text-right">Nilai Reservasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reservasiPerBranch as $br)
                                <tr>
                                    <td>{{ $br->branch_name }}</td>
                                    <td class="text-center st-tnum"><strong>{{ $br->total }}</strong></td>
                                    <td class="text-center st-tnum" style="color:#B26B00">{{ $br->pending }}</td>
                                    <td class="text-center st-tnum" style="color:#1E5A8C">{{ $br->confirmed }}</td>
                                    <td class="text-center st-tnum" style="color:#2E7D32">{{ $br->completed }}</td>
                                    <td class="text-center st-tnum" style="color:#B23A3A">{{ $br->cancelled }}</td>
                                    <td class="text-right st-tnum">Rp {{ number_format($br->revenue, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center st-muted">Belum ada cabang</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        {{-- Jadwal Dokter --}}
        <div class="col-lg-7 mb-3">
            <div class="st-card h-100">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-user-md"></i> Jadwal Dokter Praktik</h6>
                    @forelse($doctorSchedule as $branchName => $docs)
                        <div class="mb-2">
                            <div style="font-weight:600; font-size:0.85rem; color:var(--brown); margin-bottom:4px;">
                                <i class="fa fa-map-marker-alt"></i> {{ $branchName }}
                            </div>
                            @foreach($docs as $doc)
                                <div class="d-flex justify-content-between align-items-center" style="padding:3px 0 3px 16px; font-size:0.83rem;">
                                    <span>
                                        {{ $doc->doctor_name }}
                                        @if($doc->on_leave)
                                            <span class="st-pill" style="background:#FEF2F2; color:#B23A3A; margin-left:4px;">CUTI</span>
                                        @endif
                                    </span>
                                    <span class="st-tnum" style="color:{{ $doc->on_leave ? '#B23A3A' : 'var(--text-subtle)' }}; {{ $doc->on_leave ? 'text-decoration:line-through;' : '' }}">
                                        {{ $doc->start_hm }}–{{ $doc->end_hm }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="st-muted text-center">Tidak ada dokter praktik pada {{ $dayLabel }}</p>
                    @endforelse

                    @if($scheduleExceptions->isNotEmpty())
                        <hr style="border-color:var(--divider-soft)">
                        <div style="font-weight:600; font-size:0.8rem; color:#B26B00; margin-bottom:4px;">
                            <i class="fa fa-calendar-plus"></i> Perubahan Jadwal Hari Ini
                        </div>
                        @foreach($scheduleExceptions as $ex)
                            <div style="padding:2px 0 2px 16px; font-size:0.8rem;">
                                <span class="st-pill st-pill--light">{{ strtoupper($ex->type) }}</span>
                                {{ $ex->doctor_name }} @ {{ $ex->branch_name }} —
                                {{ substr($ex->start_hour, 0, 5) }}–{{ substr($ex->end_hour, 0, 5) }}
                                @if($ex->reason)<span style="color:var(--text-subtle)">({{ $ex->reason }})</span>@endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Promo Aktif + KOL --}}
        <div class="col-lg-5 mb-3">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-tags"></i> Promo Aktif</h6>
                    @forelse($activePromos as $promo)
                        <div style="padding:6px 0; border-bottom:1px solid var(--divider-soft);">
                            <div style="font-weight:600; font-size:0.85rem;">{{ $promo->nama_promo }}</div>
                            <div style="font-size:0.78rem; color:var(--text-subtle);">
                                <span class="st-pill st-pill--light">{{ str_replace('_', ' ', $promo->campaign_type) }}</span>
                                @if($promo->harga_promo)
                                    Rp {{ number_format($promo->harga_promo, 0, ',', '.') }}
                                    @if($promo->harga_normal)
                                        <s style="color:#B23A3A;">Rp {{ number_format($promo->harga_normal, 0, ',', '.') }}</s>
                                    @endif
                                @endif
                                @if($promo->periode_end)
                                    · s/d {{ \Carbon\Carbon::parse($promo->periode_end)->format('d M Y') }}
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="st-muted text-center">Tidak ada promo aktif</p>
                    @endforelse
                </div>
            </div>
            <div class="st-card">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-star"></i> Visit KOL</h6>
                    @forelse($kolVisits as $branchName => $visits)
                        <div style="font-weight:600; font-size:0.82rem; color:var(--brown);">{{ $branchName }}</div>
                        @foreach($visits as $v)
                            <div style="padding:2px 0 2px 16px; font-size:0.8rem;">
                                {{ $v->kol_name }}
                                <span style="color:var(--text-subtle)">
                                    @if($v->kol_kategori)({{ str_replace('_', ' ', $v->kol_kategori) }})@endif
                                    · {{ substr($v->start_hour, 0, 5) }}
                                </span>
                            </div>
                        @endforeach
                    @empty
                        <p class="st-muted text-center">Tidak ada visit KOL terjadwal</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
