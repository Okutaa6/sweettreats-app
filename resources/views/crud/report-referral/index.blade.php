@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>Report</a></li>
                        <li class="breadcrumb-item active">Report Referral</li>
                    </ol>
                </div>
                <h4 class="page-title">Report Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body" style="zoom:0.85">

                    {{-- ── Filter ── --}}
                    <form method="GET" action="{{ route('report.referral') }}" class="mb-3">
                        <div class="row" style="gap:8px; row-gap:8px;">

                            {{-- Filter Affiliate --}}
                            <div class="col" style="min-width:200px">
                                <select class="form-control form-control-sm" name="affiliate_id">
                                    <option value="">Semua Affiliate</option>
                                    @foreach($affiliates as $a)
                                        <option value="{{ $a->id }}"
                                            {{ $filterAffiliate == $a->id ? 'selected' : '' }}>
                                            {{ $a->name }}
                                            @if($a->role)
                                                ({{ ucfirst($a->role) }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Filter Cabang --}}
                            <div class="col" style="min-width:160px">
                                <select class="form-control form-control-sm" name="branch_id">
                                    <option value="">Semua Cabang</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}"
                                            {{ $filterBranch == $b->id ? 'selected' : '' }}>
                                            {{ $b->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Filter Tanggal --}}
                            <div class="col" style="min-width:150px">
                                <input type="date" class="form-control form-control-sm"
                                    name="date_from" value="{{ $filterDateFrom }}"
                                    placeholder="Dari tanggal">
                            </div>
                            <div class="col" style="min-width:150px">
                                <input type="date" class="form-control form-control-sm"
                                    name="date_to" value="{{ $filterDateTo }}"
                                    placeholder="Sampai tanggal">
                            </div>

                            {{-- Filter Klaim --}}
                            <div class="col" style="min-width:150px">
                                <select class="form-control form-control-sm" name="klaim">
                                    <option value="">Semua Status Klaim</option>
                                    <option value="1" {{ $filterKlaim === '1' ? 'selected' : '' }}>
                                        ✅ Sudah Diklaim
                                    </option>
                                    <option value="0" {{ $filterKlaim === '0' ? 'selected' : '' }}>
                                        ⬜ Belum Diklaim
                                    </option>
                                </select>
                            </div>

                            {{-- Tombol --}}
                            <div class="col d-flex" style="min-width:120px; gap:4px">
                                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                                    <i class="fa fa-search"></i> Filter
                                </button>
                                <a href="{{ route('report.referral') }}" class="btn btn-sm btn-warning"
                                    title="Reset">
                                    <i class="fa fa-times"></i>
                                </a>
                            </div>

                        </div>
                    </form>

                    {{-- ── Summary Cards ── --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white mb-0">
                                <div class="card-body py-2 px-3">
                                    <div style="font-size:0.8rem">Total Reservasi</div>
                                    <div style="font-size:1.4rem; font-weight:700">
                                        {{ $reservations->total() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white mb-0">
                                <div class="card-body py-2 px-3">
                                    <div style="font-size:0.8rem">Sudah Diklaim</div>
                                    <div style="font-size:1.4rem; font-weight:700">
                                        {{ $reservations->getCollection()->where('promo_klaim', 1)->count() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white mb-0">
                                <div class="card-body py-2 px-3">
                                    <div style="font-size:0.8rem">Belum Diklaim</div>
                                    <div style="font-size:1.4rem; font-weight:700">
                                        {{ $reservations->getCollection()->where('promo_klaim', 0)->count() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white mb-0">
                                <div class="card-body py-2 px-3">
                                    <div style="font-size:0.8rem">Total Komisi</div>
                                    <div style="font-size:1.1rem; font-weight:700">
                                        Rp {{ number_format($totalKomisi, 0, ',', '.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ── Tabel ── --}}
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover"
                            style="font-size:0.85rem; white-space:nowrap;">
                            <thead class="thead-dark">
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Affiliate</th>
                                    <th>Role</th>
                                    <th>Kode Referral</th>
                                    <th>Pasien</th>
                                    <th>Cabang</th>
                                    <th>Tanggal</th>
                                    <th style="white-space:normal; min-width:150px">Perawatan</th>
                                    <th>Komisi</th>
                                    <th>Klaim</th>
                                    <th>Klaim Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reservations as $key => $row)
                                    @php
                                        $affiliate = $row->promo->affiliate ?? null;
                                        $commission = $row->commission ?? null;
                                        $klaimBy = null;
                                        if ($row->promo_klaim_by) {
                                            $klaimBy = \App\Models\Users::find($row->promo_klaim_by);
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $reservations->firstItem() + $key }}</td>

                                        {{-- Affiliate --}}
                                        <td>
                                            @if($affiliate)
                                                <strong>{{ $affiliate->name }}</strong>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- Role --}}
                                        <td>
                                            @if($affiliate && $affiliate->role)
                                                @php
                                                    $roleColor = [
                                                        'pasien'     => 'info',
                                                        'influencer' => 'warning',
                                                        'dokter'     => 'success',
                                                    ][$affiliate->role] ?? 'secondary';
                                                @endphp
                                                <span class="badge badge-{{ $roleColor }}">
                                                    {{ ucfirst($affiliate->role) }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- Kode Referral --}}
                                        <td>
                                            @if($row->promo)
                                                <span class="badge badge-primary" style="font-size:0.8rem">
                                                    {{ $row->promo->promo_code }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- Pasien --}}
                                        <td>{{ $row->customer->name ?? '-' }}</td>

                                        {{-- Cabang --}}
                                        <td>{{ $row->branch->name ?? '-' }}</td>

                                        {{-- Tanggal --}}
                                        <td>
                                            {{ $row->date ? $row->date->format('d/m/Y') : '-' }}
                                        </td>

                                        {{-- Perawatan --}}
                                        <td style="white-space:normal">
                                            @foreach($row->services as $svc)
                                                <span class="badge badge-light border"
                                                    style="font-size:0.72rem; margin:1px">
                                                    {{ $svc->docService->name ?? '-' }}
                                                </span>
                                            @endforeach
                                            @if($row->lainnya_text)
                                                <span class="badge badge-warning"
                                                    style="font-size:0.72rem; margin:1px; white-space:normal">
                                                    {{ $row->lainnya_text }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Komisi --}}
                                        <td>
                                            @if($commission)
                                                <strong style="color:#28a745">
                                                    Rp {{ number_format($commission->amount, 0, ',', '.') }}
                                                </strong>
                                                <br>
                                                @php
                                                    $commOpt = \App\Models\RawCommission::statusOptions()[$commission->status] ?? [];
                                                @endphp
                                                <span class="badge badge-{{ $commOpt['color'] ?? 'secondary' }}"
                                                    style="font-size:0.7rem">
                                                    {{ $commOpt['label'] ?? $commission->status }}
                                                </span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>

                                        {{-- Status Klaim --}}
                                        <td class="text-center">
                                            @if($row->promo_klaim)
                                                <span class="badge badge-success">
                                                    <i class="fa fa-check"></i> Diklaim
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">
                                                    Belum
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Klaim Oleh --}}
                                        <td>
                                            @if($row->promo_klaim && $klaimBy)
                                                {{ $klaimBy->name ?? '-' }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">
                                            <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                            Tidak ada data referral ditemukan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            {{-- Total Row --}}
                            @if($reservations->count() > 0)
                            <tfoot>
                                <tr class="table-info font-weight-bold">
                                    <td colspan="8" class="text-right">
                                        Total Komisi (halaman ini):
                                    </td>
                                    <td>
                                        Rp {{ number_format(
                                            $reservations->getCollection()->sum(fn($r) => $r->commission->amount ?? 0),
                                            0, ',', '.'
                                        ) }}
                                    </td>
                                    <td colspan="2"></td>
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