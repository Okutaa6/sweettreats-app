@extends('layouts.app')

@section('content')
@php
    $totalAll = array_sum($summary ?? []);
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Klaim Komisi</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap: 12px;">
        <div class="text-muted" style="font-size: 13px;">
            Total: <strong>{{ $totalAll }}</strong> klaim
            @foreach ($statuses as $key => $opt)
                <span class="ml-2">·</span>
                <span class="ml-1 st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}: {{ $summary[$key] ?? 0 }}</span>
            @endforeach
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-4">
                            <label class="text-muted" style="font-size: 12px;">Cari nama affiliate</label>
                            <input type="text" name="search" class="form-control" placeholder="Nama..." value="{{ $filterSearch }}">
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted" style="font-size: 12px;">Status</label>
                            <select name="status" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($statuses as $key => $opt)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted" style="font-size: 12px;">Scope</label>
                            <select name="scope" class="form-control">
                                <option value="">- Semua -</option>
                                <option value="behel"     {{ $filterScope == 'behel' ? 'selected' : '' }}>Behel</option>
                                <option value="non_behel" {{ $filterScope == 'non_behel' ? 'selected' : '' }}>Non-Behel</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end" style="gap: 4px;">
                            <button type="submit" class="st-btn-brand"><i class="fa fa-filter"></i></button>
                            <a href="{{ route('commissionclaim') }}" class="st-btn-outline-brown"><i class="fa fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="st-card">
                <div class="st-card__body">
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Affiliate</th>
                                    <th>Scope</th>
                                    <th>Pasien</th>
                                    <th class="text-right">Total</th>
                                    <th>Diajukan</th>
                                    <th>PJ</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($batches as $key => $b)
                                    @php $opt = $statuses[$b->status] ?? ['label' => $b->status, 'color' => 'neutral']; @endphp
                                    <tr data-href="{{ route('commissionclaim.show', $b->id) }}">
                                        <td class="st-num">{{ $batches->firstItem() + $key }}</td>
                                        <td class="st-primary">
                                            {{ $b->affiliate->name ?? '-' }}
                                            @if($b->affiliate?->phone)
                                                <br><small class="text-muted">{{ $b->affiliate->phone }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="st-pill st-pill--plain {{ $b->referral_scope === 'behel' ? 'st-pill--info' : 'st-pill--success' }}">
                                                {{ $b->referral_scope === 'behel' ? 'Behel' : 'Non-Behel' }}
                                            </span>
                                        </td>
                                        <td class="st-tnum">{{ $b->patient_count }}</td>
                                        <td class="text-right st-tnum"><strong>Rp {{ number_format($b->total_amount, 0, ',', '.') }}</strong></td>
                                        <td><span class="st-date">{{ $b->requested_at->format('d M Y') }}</span></td>
                                        <td>
                                            @if($b->pj)
                                                {{ $b->pj->name ?? '-' }}
                                            @else
                                                <span class="st-muted" style="font-size:11px;">Belum diambil</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($batches->isEmpty())
                                    <tr><td colspan="8" class="text-center text-muted py-4">
                                        Belum ada klaim.
                                    </td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    {{ $batches->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
