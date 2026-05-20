@extends('layouts.app')

@section('content')
@php
    $totalAll = array_sum($summary ?? []);
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Promo</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Header bar: summary + tombol Tambah --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap: 12px;">
        <div style="font-size: 13px; color: var(--text-muted);">
            Total: <strong>{{ $totalAll }}</strong> promo
            @foreach ($statuses as $key => $opt)
                <span class="ml-2">·</span>
                <span class="ml-1 st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}: {{ $summary[$key] ?? 0 }}</span>
            @endforeach
        </div>
        <a href="{{ route('marketingpromo.new') }}" class="st-btn-brand">
            <i class="fa fa-plus"></i> Tambah Promo
        </a>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    {{-- ─── Quick periode chips ─── --}}
                    <div class="d-flex align-items-center flex-wrap mb-3" style="gap: 8px;">
                        <span style="font-size: 13px; font-weight: 600; color: var(--text-muted);">
                            <i class="fa fa-bolt"></i> Lihat promo:
                        </span>
                        @php
                            $chips = [
                                ''           => 'Semua',
                                'today'      => 'Hari Ini',
                                'this_month' => 'Bulan Ini',
                                'next_month' => 'Bulan Depan',
                            ];
                            $qs = function ($overrides = []) {
                                return http_build_query(array_filter(array_merge(request()->all(), $overrides), fn($v) => $v !== null && $v !== ''));
                            };
                        @endphp
                        @foreach ($chips as $key => $label)
                            @php $isActive = ($filterPeriode ?? '') === $key && !$filterAktifPada; @endphp
                            <a href="{{ route('marketingpromo') }}?{{ $qs(['periode' => $key, 'aktif_pada' => null]) }}"
                               class="st-pill st-pill--{{ $isActive ? 'success' : 'neutral' }}"
                               style="cursor: pointer; text-decoration: none;">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    {{-- ─── Filter form ─── --}}
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <label style="font-size: 12px;">Cari</label>
                            <input type="text" name="search" class="form-control" placeholder="Nama promo..." value="{{ $filterSearch }}">
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 12px;">Campaign</label>
                            <select name="campaign_type" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($campaigns as $key => $label)
                                    <option value="{{ $key }}" {{ $filterCampaign == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 12px;">Status</label>
                            <select name="status" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($statuses as $key => $opt)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 12px;">Cabang</label>
                            <select name="branch_id" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($branches as $b)
                                    <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label style="font-size: 12px;">Aktif pada tgl</label>
                            <input type="date" name="aktif_pada" class="form-control" value="{{ $filterAktifPada }}">
                        </div>
                        <div class="col-md-1 d-flex align-items-end" style="gap: 4px;">
                            <button type="submit" class="st-btn-brand" title="Terapkan filter"><i class="fa fa-filter"></i></button>
                            <a href="{{ route('marketingpromo') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="st-card">
                <div class="st-card__body">
                    <div class="st-table-wrap--scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Nama Promo</th>
                                    <th>Campaign</th>
                                    <th>Periode</th>
                                    <th>Cabang</th>
                                    <th class="text-right">Harga Promo</th>
                                    <th class="text-right">Margin</th>
                                    <th>Status</th>
                                    <th width="60">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promos as $key => $row)
                                    @php
                                        $statusOpt = $statuses[$row->status] ?? ['label' => $row->status, 'color' => 'neutral'];
                                        $isWithinPeriode = $row->isWithinPeriode();
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $promos->firstItem() + $key }}</td>
                                        <td class="st-primary">
                                            <a href="{{ route('marketingpromo.edit', $row->id) }}" style="color: inherit; text-decoration: none;">
                                                {{ $row->nama_promo }}
                                            </a>
                                        </td>
                                        <td>{{ $campaigns[$row->campaign_type] ?? $row->campaign_type }}</td>
                                        <td>
                                            @if ($row->periode_start || $row->periode_end)
                                                <span class="st-date">
                                                    {{ $row->periode_start?->format('d M Y') ?? '...' }} – {{ $row->periode_end?->format('d M Y') ?? '...' }}
                                                </span>
                                                @if (!$isWithinPeriode)
                                                    <br><span class="st-pill st-pill--neutral mt-1" title="Tidak dalam periode aktif saat ini">Di luar periode</span>
                                                @endif
                                            @else
                                                <span class="st-muted">Tanpa periode</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($row->branches->count() === 0)
                                                <span class="st-pill st-pill--neutral">Semua Cabang</span>
                                            @else
                                                @foreach ($row->branches as $b)
                                                    <span class="st-pill st-pill--info st-pill--plain">{{ $b->name }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="text-right st-tnum">
                                            @if ($row->harga_promo)
                                                Rp {{ number_format($row->harga_promo, 0, ',', '.') }}
                                                @if ($row->harga_normal && $row->harga_normal > $row->harga_promo)
                                                    <br><small class="text-muted text-decoration-line-through">Rp {{ number_format($row->harga_normal, 0, ',', '.') }}</small>
                                                @endif
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-right st-tnum">
                                            @if ($row->margin_pct !== null)
                                                <span style="color: {{ $row->margin_rp < 0 ? '#c1272d' : 'inherit' }};">
                                                    {{ number_format($row->margin_pct, 2) }}%
                                                </span>
                                                <br><small class="text-muted">Rp {{ number_format($row->margin_rp, 0, ',', '.') }}</small>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <select class="st-status-select" data-id="{{ $row->id }}"
                                                    data-original="{{ $row->status }}" data-status="{{ $row->status }}"
                                                    onchange="changePromoStatus(this)">
                                                @foreach ($statuses as $key => $opt)
                                                    <option value="{{ $key }}" {{ $row->status === $key ? 'selected' : '' }}>
                                                        {{ $opt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @if (!$row->is_active)
                                                <br><span class="st-pill st-pill--neutral mt-1">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('marketingpromo.edit', $row->id) }}" class="st-btn-outline-brown btn-sm" title="Edit">
                                                <i class="fa fa-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($promos->isEmpty())
                                    <tr><td colspan="9" class="text-center text-muted py-4">
                                        Tidak ada promo yang cocok dengan filter ini.
                                        <br><a href="{{ route('marketingpromo') }}" class="text-primary">Reset filter</a>
                                    </td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    {{ $promos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .st-status-select {
        border: 1px solid transparent;
        padding: 4px 26px 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23461500' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        transition: opacity .15s, transform .15s;
    }
    .st-status-select[data-status="aktif"]    { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .st-status-select[data-status="planning"] { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .st-status-select[data-status="selesai"]  { background-color: #e9ecef; color: #495057; border-color: #ced4da; }
    .st-status-select:hover    { opacity: .85; }
    .st-status-select:disabled { opacity: .5; cursor: wait; }
</style>
@endsection

@section('js')
<script>
    var promoStatusOptions = @json(\App\Models\MarketingPromo::statusOptions());

    function changePromoStatus(el) {
        var id          = el.dataset.id;
        var newStatus   = el.value;
        var origStatus  = el.dataset.original;
        if (newStatus === origStatus) return;

        Swal.fire({
            title: 'Ubah status promo?',
            text: 'Status akan diubah ke "' + (promoStatusOptions[newStatus]?.label ?? newStatus) + '"',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, ubah',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.value) {
                el.value = origStatus;
                return;
            }
            el.disabled = true;
            $.ajax({
                url: '/marketing-promo/' + id + '/status',
                method: 'PATCH',
                data: { _token: '{{ csrf_token() }}', status: newStatus },
                success: function (res) {
                    el.dataset.original = res.status;
                    el.dataset.status   = res.status;
                    el.disabled = false;
                    Swal.fire({ title: 'Berhasil', text: 'Status diubah ke "' + res.label + '"', type: 'success', timer: 1500, showConfirmButton: false });
                },
                error: function (xhr) {
                    el.value = origStatus;
                    el.disabled = false;
                    Swal.fire({ title: 'Gagal', text: xhr.responseJSON?.message || 'Terjadi kesalahan', type: 'error' });
                }
            });
        });
    }
</script>
@endsection
