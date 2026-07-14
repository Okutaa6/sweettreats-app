@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
@php
    $statusColors = \App\Models\EpKonten::statusColors();
    $totalCampaigns = $campaigns->flatten()->count();
@endphp
<div class="container-fluid">

    {{-- Header --}}
    <div class="ep-cal-header">
        <div>
            <h4 class="page-title mb-1">Campaign Framework</h4>
            <div class="ep-cal-meta">
                <span class="ep-cal-month-pill"><i class="fa fa-calendar"></i> Tahun {{ $filterTahun }}</span>
                <span class="ep-cal-meta-sep">·</span>
                <span class="ep-cal-meta-text"><strong>{{ $totalCampaigns }}</strong> campaign</span>
            </div>
        </div>
        <div class="ep-cal-actions">
            <select class="ep-cal-year-select" onchange="window.location.href='{{ route('epcampaign') }}?tahun=' + this.value">
                @foreach ($availableYears as $y)
                    <option value="{{ $y }}" {{ $filterTahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
            <a href="{{ route('epcampaign.new') }}" class="st-btn-brand">
                <i class="fa fa-plus"></i> Tambah Campaign
            </a>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Months grid (12 bulan) --}}
    <div class="cmp-year-grid">
        @foreach ($bulanOpt as $bulanNum => $bulanLabel)
            @php $monthCampaigns = $campaigns[$bulanNum] ?? collect(); @endphp
            <div class="cmp-month {{ $monthCampaigns->isEmpty() ? 'is-empty' : '' }}">
                <div class="cmp-month-head">
                    <span class="cmp-month-name">{{ $bulanLabel }}</span>
                    @if ($monthCampaigns->isNotEmpty())
                        <span class="cmp-month-count">{{ $monthCampaigns->count() }}</span>
                    @endif
                </div>

                @forelse ($monthCampaigns as $c)
                    @php
                        $sCfg = $statusColors[$c->status] ?? $statusColors['draft'] ?? null;
                    @endphp
                    <a href="{{ route('epcampaign.edit', $c->id) }}" class="cmp-card"
                       style="border-left:3px solid {{ $sCfg['dot'] ?? '#9ca3af' }};">
                        <div class="cmp-card-name">{{ $c->campaign_name }}</div>
                        @if ($c->big_idea)
                            <div class="cmp-card-idea">{{ Str::limit($c->big_idea, 60) }}</div>
                        @endif
                        <div class="cmp-card-meta">
                            @if ($sCfg)
                                <span class="cmp-card-status" style="background:{{ $sCfg['bg'] }}; color:{{ $sCfg['text'] }};">
                                    {{ $statuses[$c->status]['label'] ?? $c->status }}
                                </span>
                            @endif
                            @if ($c->weeks->count() > 0)
                                <span class="cmp-card-weeks"><i class="fa fa-th-list"></i> {{ $c->weeks->count() }} minggu</span>
                            @endif
                            @if ($c->kontens_count > 0)
                                <span class="cmp-card-kontens"><i class="fa fa-link"></i> {{ $c->kontens_count }}</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <a href="{{ route('epcampaign.new') }}?bulan={{ $bulanNum }}&tahun={{ $filterTahun }}" class="cmp-month-add">
                        <i class="fa fa-plus"></i> Tambah campaign
                    </a>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

@endsection
