@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
@php
    $totalAll = array_sum($summary ?? []);
    $platformOptions = \App\Models\EpKonten::platformOptions();
    $platformColors  = \App\Models\EpKonten::platformColors();
@endphp
<div class="container-fluid">

    {{-- Header --}}
    <div class="ep-cal-header">
        <div>
            <h4 class="page-title mb-1">Breakdown Konten <span class="bd-khusus-label">khusus</span></h4>
            <div class="ep-cal-meta">
                <span class="ep-cal-meta-text">Total <strong>{{ $totalAll }}</strong> konten khusus</span>
                @foreach ($statuses as $key => $opt)
                    @if(($summary[$key] ?? 0) > 0)
                        <span class="ep-cal-meta-sep">·</span>
                        <span class="ep-cal-meta-text">{{ $opt['label'] }}: <strong>{{ $summary[$key] ?? 0 }}</strong></span>
                    @endif
                @endforeach
            </div>
        </div>
        <a href="{{ route('epbreakdown.new') }}" class="st-btn-brand">
            <i class="fa fa-plus"></i> Tambah Breakdown
        </a>
    </div>

    @include('layouts.notif')

    <div class="bd-info-banner">
        <i class="fa fa-info-circle"></i>
        <div>
            <strong>Breakdown</strong> adalah konten EP khusus untuk Promo, Event, atau Project tertentu — punya field tambahan (lokasi, ACC).
            Otomatis muncul di <a href="{{ route('epkonten') }}">Kalender EP</a> dengan badge <span class="bd-khusus-mini-pill">🎯 Khusus</span> di tanggal upload.
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="ep-cal-toolbar">
        <form method="GET" class="ep-cal-filter-row" style="width:100%;">
            <div class="ep-search-wrap">
                <i class="fa fa-search ep-search-icon"></i>
                <input type="text" name="search" class="ep-search-input"
                       placeholder="Cari judul..." value="{{ $filterSearch }}" onchange="this.form.submit()">
            </div>
            <select name="status" class="ep-search-input" style="padding-left:14px; min-width:160px;" onchange="this.form.submit()">
                <option value="">Status: Semua</option>
                @foreach ($statuses as $key => $opt)
                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            @if ($filterStatus || $filterSearch)
                <a href="{{ route('epbreakdown') }}" class="ep-cal-nav-btn"><i class="fa fa-undo"></i> Reset</a>
            @endif
        </form>
    </div>

    {{-- Card grid --}}
    <div class="bd-grid">
        @forelse ($breakdowns as $b)
            @php
                $sCfg = $statusColors[$b->status] ?? $statusColors['draft'];
            @endphp
            <a href="{{ route('epbreakdown.edit', $b->id) }}" class="bd-card"
               style="border-left:4px solid {{ $sCfg['dot'] }};">
                <div class="bd-card-head">
                    <div class="bd-card-status" style="background:{{ $sCfg['bg'] }}; color:{{ $sCfg['text'] }};">
                        <span class="status-dot" style="background:{{ $sCfg['dot'] }};"></span>
                        {{ $statuses[$b->status]['label'] ?? $b->status }}
                    </div>
                    <span class="bd-khusus-pill">🎯 Khusus</span>
                </div>

                <div class="bd-card-title">{{ $b->judul }}</div>

                <div class="bd-card-meta">
                    @if ($b->tanggal_upload)
                        <span><i class="fa fa-calendar-day"></i> {{ $b->tanggal_upload->translatedFormat('d M Y') }}</span>
                    @endif
                    @if ($b->lokasi)
                        <span><i class="fa fa-map-marker-alt"></i> {{ $b->lokasi }}</span>
                    @endif
                    @if ($b->acc_by)
                        <span><i class="fa fa-user-check"></i> ACC: {{ $b->acc_by }}</span>
                    @endif
                </div>

                @if (!empty($b->platforms))
                    <div class="bd-card-platforms">
                        @foreach ($b->platforms as $plat)
                            @php $pc = $platformColors[$plat] ?? null; @endphp
                            <span class="bd-platform-pill" style="background:{{ $pc['bg'] ?? '#e6f5f4' }}; color:{{ $pc['text'] ?? '#0a4f4d' }};">
                                {{ $platformOptions[$plat] ?? $plat }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($b->pillars->isNotEmpty())
                    <div class="bd-card-pillars">
                        @foreach ($b->pillars->take(5) as $p)
                            @php $pColor = $p->color ?: '#13928e'; @endphp
                            <span class="bd-pillar-pill" style="background:{{ $pColor }};">{{ $p->nama }}</span>
                        @endforeach
                        @if ($b->pillars->count() > 5)
                            <small class="text-muted">+{{ $b->pillars->count() - 5 }}</small>
                        @endif
                    </div>
                @endif
            </a>
        @empty
            <div class="bd-empty">
                <div class="bd-empty__icon-wrap">
                    <i class="fa fa-bullseye"></i>
                </div>
                <div class="bd-empty__title">Belum ada breakdown khusus</div>
                <div class="bd-empty__desc">Mulai dengan konten khusus pertama (Promo, Event, atau Project) — semua field tambahan otomatis tersedia.</div>
                <a href="{{ route('epbreakdown.new') }}" class="st-btn-brand bd-empty__cta">
                    <i class="fa fa-plus"></i> Tambah Breakdown
                </a>
            </div>
        @endforelse
    </div>

    @if ($breakdowns->hasPages())
        <div class="mt-3">{{ $breakdowns->links() }}</div>
    @endif
</div>

@endsection
