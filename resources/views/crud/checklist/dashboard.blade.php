@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Dashboard Checklist Akreditasi</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO HEADER ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-clipboard-check"></i></span>
                Monitoring Checklist Hari Ini
            </h5>
            <p class="cl-hero__subtitle">
                Periode <strong>{{ $today->translatedFormat('l, d F Y') }}</strong>
                — pantau pengisian checklist akreditasi seluruh karyawan secara real-time.
            </p>
        </div>
        @if(canSeeAllBranches() && $branches->count())
            <form method="GET" action="{{ route('checklist') }}" class="cl-hero__actions">
                <label class="cl-hero__picker-label">Cabang</label>
                <select name="branch" onchange="this.form.submit();" class="cl-hero__picker">
                    <option value="">— Semua Cabang —</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- ─── KPI CARDS ─── --}}
    @include('crud.checklist._kpi_cards', ['kpis' => $kpis])

    {{-- ─── 2-PANEL SPLIT ─── --}}
    <div class="cl-grid-2">
        {{-- Progres per divisi --}}
        <div class="cl-panel">
            <div class="cl-panel__head">
                <h6 class="cl-panel__title"><i class="fas fa-chart-pie"></i> Progres per Divisi</h6>
            </div>
            <div class="cl-panel__body">
                @if($perDivision->isEmpty() || $perDivision->sum('total') === 0)
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-users"></i></span>
                        <div class="cl-empty__title">Belum ada karyawan aktif</div>
                        <div class="cl-empty__desc">Set posisi karyawan via menu User Management terlebih dahulu untuk mulai monitoring.</div>
                    </div>
                @else
                    <div class="cl-divprog">
                        @foreach($perDivision as $row)
                            @php
                                $pctClass = $row->pct >= 80 ? 'good' : ($row->pct >= 50 ? 'warn' : 'bad');
                            @endphp
                            <div class="cl-divprog__row">
                                <div class="cl-divprog__head">
                                    <span class="cl-divprog__name">
                                        @if($row->division->id === 'FO')<i class="fas fa-headset" style="color:var(--cl-brown-2); font-size:11px;"></i>
                                        @elseif($row->division->id === 'GS')<i class="fas fa-broom" style="color:var(--cl-brown-2); font-size:11px;"></i>
                                        @else<i class="fas fa-user-nurse" style="color:var(--cl-brown-2); font-size:11px;"></i>
                                        @endif
                                        {{ $row->division->name }}
                                    </span>
                                    <span class="cl-divprog__count">
                                        <strong style="color:var(--cl-ink);">{{ $row->submitted }}</strong>/{{ $row->total }} submit
                                        <span class="cl-divprog__pct cl-divprog__pct--{{ $pctClass }}">{{ $row->pct }}%</span>
                                    </span>
                                </div>
                                <div class="cl-bar"><i style="width:{{ $row->pct }}%"></i></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Aktivitas terbaru --}}
        <div class="cl-panel">
            <div class="cl-panel__head">
                <h6 class="cl-panel__title"><i class="fas fa-history"></i> Aktivitas Terbaru</h6>
                <a href="{{ route('checklist.review') }}" class="cl-panel__link">
                    Lihat semua <i class="fas fa-arrow-right" style="font-size:10px;"></i>
                </a>
            </div>
            <div class="cl-panel__body">
                @if($recent->isEmpty())
                    <div class="cl-empty">
                        <span class="cl-empty__icon"><i class="fas fa-inbox"></i></span>
                        <div class="cl-empty__title">Belum ada aktivitas</div>
                        <div class="cl-empty__desc">Pengisian checklist karyawan akan muncul di sini secara real-time.</div>
                    </div>
                @else
                    <div class="cl-feed">
                        @foreach($recent as $r)
                            @php $opt = \App\Models\ChecklistSubmission::statusOptions()[$r->status] ?? ['label'=>$r->status,'color'=>'neutral']; @endphp
                            <a href="{{ route('checklist.review.show', $r->id) }}" class="cl-feed__item">
                                <div class="cl-feed__avatar">{{ strtoupper(mb_substr($r->user?->nama ?? '?', 0, 1)) }}</div>
                                <div class="cl-feed__main">
                                    <div class="cl-feed__name">
                                        {{ $r->user?->nama ?? '—' }}
                                        <span>· {{ $r->division?->name ?? $r->division_id }}</span>
                                    </div>
                                    <div class="cl-feed__meta">
                                        {{ $r->submission_date->translatedFormat('d M Y') }}
                                        · {{ $r->done_tasks }}/{{ $r->total_tasks }} tugas
                                        @if($r->branch) · {{ $r->branch->name }} @endif
                                    </div>
                                </div>
                                <span class="st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ─── QUICK ACTIONS ─── --}}
    <div class="cl-action-row" style="grid-template-columns: repeat(3, 1fr);">
        <a href="{{ route('checklist.positions') }}" class="cl-action">
            <span class="cl-action__icon"><i class="fas fa-user-tag"></i></span>
            <div class="cl-action__main">
                <div class="cl-action__title">Posisi Karyawan</div>
                <div class="cl-action__desc">Assign karyawan ke divisi FO / GS / Asdent + cabang</div>
            </div>
            <i class="fas fa-arrow-right cl-action__arrow"></i>
        </a>
        <a href="{{ route('checklist.setup') }}" class="cl-action">
            <span class="cl-action__icon"><i class="fas fa-list-check"></i></span>
            <div class="cl-action__main">
                <div class="cl-action__title">Setup Tugas Checklist</div>
                <div class="cl-action__desc">Atur kategori & task per divisi (FO / GS / Asdent)</div>
            </div>
            <i class="fas fa-arrow-right cl-action__arrow"></i>
        </a>
        <a href="{{ route('checklist.audit') }}" class="cl-action">
            <span class="cl-action__icon"><i class="fas fa-shield-halved"></i></span>
            <div class="cl-action__main">
                <div class="cl-action__title">Audit Trail</div>
                <div class="cl-action__desc">Log lengkap semua aksi — dipakai saat audit akreditasi</div>
            </div>
            <i class="fas fa-arrow-right cl-action__arrow"></i>
        </a>
    </div>

    <style>
        @media (max-width: 992px) {
            .cl-action-row { grid-template-columns: 1fr 1fr !important; }
        }
        @media (max-width: 600px) {
            .cl-action-row { grid-template-columns: 1fr !important; }
        }
    </style>

</div>
@endsection
