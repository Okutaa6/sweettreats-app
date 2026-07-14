@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Audit Trail Checklist</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-shield-alt"></i></span>
                Audit Trail Aktivitas
            </h5>
            <p class="cl-hero__subtitle">
                Log lengkap semua aksi di modul checklist — <strong>append-only</strong>, dipakai sebagai bukti kepatuhan saat audit akreditasi.
            </p>
        </div>
    </div>

    {{-- ─── CARD: FILTER + TABLE ─── --}}
    <div class="cl-panel" style="padding:0;">

        {{-- FILTER BAR --}}
        <form method="GET" action="{{ route('checklist.audit') }}" class="cl-filter">
            @if(canSeeAllBranches() && $branches->count())
                <label>Cabang</label>
                <select name="branch" onchange="this.form.submit();">
                    <option value="">Semua</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endif

            <label>Aksi</label>
            <select name="action" onchange="this.form.submit();">
                <option value="all">Semua</option>
                @foreach($actionOptions as $key => $opt)
                    <option value="{{ $key }}" {{ $filterAct === $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                @endforeach
            </select>

            <label>Target</label>
            <select name="target_type" onchange="this.form.submit();">
                <option value="all">Semua</option>
                <option value="submission" {{ $filterType === 'submission' ? 'selected' : '' }}>Submission</option>
                <option value="category"   {{ $filterType === 'category' ? 'selected' : '' }}>Kategori</option>
                <option value="task"       {{ $filterType === 'task' ? 'selected' : '' }}>Task</option>
            </select>

            <label>Dari</label>
            <input type="date" name="date_from" value="{{ $dateFrom }}">
            <label>Sampai</label>
            <input type="date" name="date_to" value="{{ $dateTo }}">

            <label>Aktor</label>
            <input type="text" name="actor" value="{{ $actor }}" placeholder="Nama user...">

            <button type="submit" class="st-btn-brand"><i class="fas fa-filter"></i> Filter</button>

            @if($filterAct !== 'all' || $filterType !== 'all' || $dateFrom || $dateTo || $actor || $branchId)
                <a href="{{ route('checklist.audit') }}" style="font-size:12px; color:var(--cl-brown-2); text-decoration:underline;">Reset</a>
            @endif
        </form>

        {{-- LOG TABLE --}}
        @if($logs->isEmpty())
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-shield-alt"></i></span>
                <div class="cl-empty__title">Belum ada log</div>
                <div class="cl-empty__desc">Audit trail akan terisi otomatis saat ada aktivitas di modul checklist — create/update/submit/approve/reject.</div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th style="width:170px;">Waktu</th>
                            <th style="width:160px;">Aktor</th>
                            <th style="width:120px;">Aksi</th>
                            <th style="width:110px;">Target</th>
                            <th>Detail</th>
                            <th style="width:140px;">Cabang</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $actOpt = $actionOptions[$log->action] ?? ['label' => $log->action, 'color' => 'neutral'];
                                $before = $log->before_json ? json_decode($log->before_json, true) : null;
                                $after  = $log->after_json  ? json_decode($log->after_json, true)  : null;
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight:600; color:var(--cl-ink); font-size:12.5px;">{{ $log->created_at->translatedFormat('d M Y') }}</div>
                                    <div style="font-size:11px; color:var(--cl-subtle);">
                                        <i class="fas fa-clock" style="font-size:9px;"></i> {{ $log->created_at->format('H:i:s') }}
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,var(--cl-brown-2),var(--cl-brown)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px; flex-shrink:0;">
                                            {{ strtoupper(mb_substr($log->actor?->nama ?? '?', 0, 1)) }}
                                        </div>
                                        <div style="font-weight:600; color:var(--cl-ink); font-size:12.5px;">{{ $log->actor?->nama ?? '—' }}</div>
                                    </div>
                                </td>
                                <td>
                                    <span class="st-pill st-pill--{{ $actOpt['color'] }}">{{ $actOpt['label'] }}</span>
                                </td>
                                <td>
                                    <span class="st-pill st-pill--neutral">{{ ucfirst($log->target_type) }}</span>
                                </td>
                                <td>
                                    @if($log->notes)
                                        <div style="font-size:12px; color:var(--cl-ink);">{{ \Illuminate\Support\Str::limit($log->notes, 100) }}</div>
                                    @endif
                                    @if($before || $after)
                                        <div style="font-size:11px; color:var(--cl-muted); margin-top:3px; font-family:Consolas,Monaco,monospace;">
                                            @if($before)
                                                <span style="color:var(--cl-danger);">− {{ collect($before)->map(fn($v,$k) => "$k=$v")->implode(', ') }}</span>
                                            @endif
                                            @if($after)
                                                <span style="color:var(--cl-success); {{ $before ? 'margin-left:8px;' : '' }}">+ {{ collect($after)->map(fn($v,$k) => "$k=$v")->implode(', ') }}</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if(! $log->notes && ! $before && ! $after)
                                        <span style="color:var(--cl-subtle); font-style:italic; font-size:11.5px;">—</span>
                                    @endif
                                </td>
                                <td style="color:var(--cl-muted); font-size:12px;">{{ $log->branch?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div style="padding:14px 18px; border-top:1px solid var(--cl-line); background:#FFFFFF;">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif

    </div>
</div>
@endsection
