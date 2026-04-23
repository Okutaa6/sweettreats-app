@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Report Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">

                    {{-- Filter --}}
                    <form method="GET" action="{{ route('report.referral') }}" class="st-filter-bar mb-3" style="flex-wrap:wrap; gap:8px;">
                        <select class="form-control form-control-sm" name="affiliate_id" style="min-width:200px; flex:1;">
                            <option value="">Semua Affiliate</option>
                            @foreach($affiliates as $a)
                                <option value="{{ $a->id }}" {{ $filterAffiliate == $a->id ? 'selected' : '' }}>
                                    {{ $a->name }} @if($a->role)({{ ucfirst($a->role) }})@endif
                                </option>
                            @endforeach
                        </select>

                        <select class="form-control form-control-sm" name="branch_id" style="min-width:160px; flex:1;">
                            <option value="">Semua Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>

                        <input type="date" class="form-control form-control-sm" name="date_from" value="{{ $filterDateFrom }}" style="min-width:150px; flex:1;">
                        <input type="date" class="form-control form-control-sm" name="date_to" value="{{ $filterDateTo }}" style="min-width:150px; flex:1;">

                        <select class="form-control form-control-sm" name="klaim" style="min-width:150px; flex:1;">
                            <option value="">Semua Status Klaim</option>
                            <option value="1" {{ $filterKlaim === '1' ? 'selected' : '' }}>Sudah Diklaim</option>
                            <option value="0" {{ $filterKlaim === '0' ? 'selected' : '' }}>Belum Diklaim</option>
                        </select>

                        <div class="d-flex" style="gap:4px;">
                            <button type="submit" class="st-btn-brand"><i class="fa fa-search"></i> Filter</button>
                            <a href="{{ route('report.referral') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
                        </div>
                    </form>

                    {{-- Summary Cards --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-confirmed-bg); color:var(--st-confirmed-text);"><i class="fa fa-calendar-check"></i></div>
                                <div>
                                    <div class="st-dash-card__value">{{ $reservations->total() }}</div>
                                    <div class="st-dash-card__label">Total Reservasi</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-completed-bg); color:var(--st-completed-text);"><i class="fa fa-check-circle"></i></div>
                                <div>
                                    <div class="st-dash-card__value">{{ $reservations->getCollection()->where('promo_klaim', 1)->count() }}</div>
                                    <div class="st-dash-card__label">Sudah Diklaim</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-pending-bg); color:var(--st-pending-text);"><i class="fa fa-clock"></i></div>
                                <div>
                                    <div class="st-dash-card__value">{{ $reservations->getCollection()->where('promo_klaim', 0)->count() }}</div>
                                    <div class="st-dash-card__label">Belum Diklaim</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-confirmed-bg); color:var(--st-confirmed-text);"><i class="fa fa-coins"></i></div>
                                <div>
                                    <div class="st-dash-card__value" style="font-size:1.1rem;">Rp {{ number_format($totalKomisi, 0, ',', '.') }}</div>
                                    <div class="st-dash-card__label">Total Komisi</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Affiliate</th>
                                    <th>Role</th>
                                    <th>Kode Referral</th>
                                    <th>Pasien</th>
                                    <th>Cabang</th>
                                    <th>Tanggal</th>
                                    <th style="min-width:150px">Perawatan</th>
                                    <th>Komisi</th>
                                    <th>Klaim</th>
                                    <th>Klaim Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reservations as $key => $row)
                                    @php
                                        $affiliate  = $row->promo->affiliate ?? null;
                                        $commission = $row->commission ?? null;
                                        $klaimBy    = $row->promo_klaim_by ? \App\Models\Users::find($row->promo_klaim_by) : null;
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $reservations->firstItem() + $key }}</td>
                                        <td>
                                            @if($affiliate)
                                                <span class="st-primary">{{ $affiliate->name }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($affiliate && $affiliate->role)
                                                @php
                                                    $rolePill = ['pasien' => 'st-pill--info', 'influencer' => 'st-pill--warning', 'dokter' => 'st-pill--success'][$affiliate->role] ?? 'st-pill--neutral';
                                                @endphp
                                                <span class="st-pill st-pill--plain {{ $rolePill }}">{{ ucfirst($affiliate->role) }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->promo)
                                                <span class="st-pill st-pill--plain st-pill--primary">{{ $row->promo->promo_code }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->customer->name ?? '-' }}</td>
                                        <td>{{ $row->branch->name ?? '-' }}</td>
                                        <td><span class="st-date">{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</span></td>
                                        <td style="white-space:normal;">
                                            @foreach($row->services as $svc)
                                                <span class="st-pill st-pill--plain st-pill--light" style="font-size:0.72rem; margin:1px;">
                                                    {{ $svc->docService->name ?? '-' }}
                                                </span>
                                            @endforeach
                                            @if($row->lainnya_text)
                                                <span class="st-pill st-pill--plain st-pill--warning" style="font-size:0.72rem; margin:1px;">
                                                    {{ $row->lainnya_text }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($commission)
                                                <strong style="color:var(--st-completed-text);">
                                                    Rp {{ number_format($commission->amount, 0, ',', '.') }}
                                                </strong>
                                                <br>
                                                @php
                                                    $commOpt   = \App\Models\RawCommission::statusOptions()[$commission->status] ?? [];
                                                    $commPills = ['success' => 'st-pill--success', 'warning' => 'st-pill--warning', 'danger' => 'st-pill--danger', 'info' => 'st-pill--info'];
                                                    $commPill  = $commPills[$commOpt['color'] ?? ''] ?? 'st-pill--neutral';
                                                @endphp
                                                <span class="st-pill {{ $commPill }}" style="font-size:0.7rem;">
                                                    {{ $commOpt['label'] ?? $commission->status }}
                                                </span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="st-center">
                                            @if($row->promo_klaim)
                                                <span class="st-pill st-pill--success"><i class="fa fa-check"></i> Diklaim</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Belum</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->promo_klaim && $klaimBy)
                                                {{ $klaimBy->name ?? '-' }}
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4">
                                            <div class="st-muted">
                                                <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                                Tidak ada data referral ditemukan
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($reservations->count() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="8" class="text-right" style="font-weight:700; background:var(--cream-header);">
                                        Total Komisi (halaman ini):
                                    </td>
                                    <td style="font-weight:700; background:var(--cream-header); color:var(--st-completed-text);">
                                        Rp {{ number_format(
                                            $reservations->getCollection()->sum(fn($r) => $r->commission->amount ?? 0),
                                            0, ',', '.'
                                        ) }}
                                    </td>
                                    <td colspan="2" style="background:var(--cream-header);"></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                    {{ $reservations->links() }}

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
