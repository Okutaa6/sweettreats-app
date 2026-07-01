@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Kelola Posisi Karyawan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-user-tag"></i></span>
                Posisi Karyawan Operasional
            </h5>
            <p class="cl-hero__subtitle">
                Set divisi (FO / GS / Asdent) & cabang untuk setiap karyawan. <strong>Posisi aktif</strong> dipakai untuk menentukan checklist apa yang muncul di <code>/my-checklist</code>.
            </p>
        </div>
        <div class="cl-hero__actions">
            <a href="{{ route('checklist') }}" class="st-btn-outline-brown">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </div>
    </div>

    {{-- ─── STATS ROW ─── --}}
    <div class="cl-kpi-row">
        <div class="cl-kpi cl-kpi--neutral">
            <span class="cl-kpi__icon"><i class="fas fa-users"></i></span>
            <div class="cl-kpi__body">
                <div class="cl-kpi__label">Total Karyawan</div>
                <div class="cl-kpi__value">{{ $stats['total'] }}</div>
                <div class="cl-kpi__sub">role Operasional / Karyawan</div>
            </div>
        </div>
        <div class="cl-kpi cl-kpi--good">
            <span class="cl-kpi__icon"><i class="fas fa-circle-check"></i></span>
            <div class="cl-kpi__body">
                <div class="cl-kpi__label">Sudah Di-Assign</div>
                <div class="cl-kpi__value">{{ $stats['assigned'] }}</div>
                <div class="cl-kpi__sub">punya divisi aktif</div>
            </div>
        </div>
        <div class="cl-kpi cl-kpi--warn">
            <span class="cl-kpi__icon"><i class="fas fa-user-clock"></i></span>
            <div class="cl-kpi__body">
                <div class="cl-kpi__label">Belum Di-Assign</div>
                <div class="cl-kpi__value">{{ $stats['unassigned'] }}</div>
                <div class="cl-kpi__sub">belum bisa isi checklist</div>
            </div>
        </div>
        <div class="cl-kpi cl-kpi--neutral">
            <span class="cl-kpi__icon"><i class="fas fa-sitemap"></i></span>
            <div class="cl-kpi__body">
                <div class="cl-kpi__label">Per Divisi</div>
                <div class="cl-kpi__value" style="font-size:14px; line-height:1.4;">
                    @foreach($divisions as $div)
                        {{ $div->id }}: <strong>{{ $stats['by_division'][$div->id] ?? 0 }}</strong>{{ ! $loop->last ? ' · ' : '' }}
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ─── CARD: FILTER + TABLE ─── --}}
    <div class="cl-panel" style="padding:0;">

        <form method="GET" action="{{ route('checklist.positions') }}" class="cl-filter">
            <label>Cari</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama / email karyawan...">

            <label>Divisi</label>
            <select name="division" onchange="this.form.submit();">
                <option value="all">Semua</option>
                @foreach($divisions as $div)
                    <option value="{{ $div->id }}" {{ $filterDiv === $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                @endforeach
                <option value="unassigned" {{ $filterDiv === 'unassigned' ? 'selected' : '' }}>— Belum di-assign —</option>
            </select>

            @if(canSeeAllBranches() && $branches->count())
                <label>Cabang</label>
                <select name="branch" onchange="this.form.submit();">
                    <option value="">Semua</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endif

            <button type="submit" class="st-btn-brand"><i class="fas fa-search"></i> Filter</button>

            @if($search || $filterDiv !== 'all' || $branchId)
                <a href="{{ route('checklist.positions') }}" style="font-size:12px; color:var(--cl-brown-2); text-decoration:underline;">Reset</a>
            @endif

            <div class="cl-filter__right">
                Menampilkan <strong>{{ $rows->count() }}</strong> karyawan
            </div>
        </form>

        @if($rows->isEmpty())
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-users-slash"></i></span>
                <div class="cl-empty__title">Tidak ada karyawan</div>
                <div class="cl-empty__desc">
                    Pastikan user sudah dibuat dengan role <strong>Operasional</strong> atau <strong>Karyawan</strong> via menu <em>User Management</em>.
                </div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Karyawan</th>
                            <th style="width:140px;">Role</th>
                            <th style="width:160px;">Divisi Saat Ini</th>
                            <th style="width:180px;">Cabang</th>
                            <th style="width:420px;">Set / Ubah Posisi</th>
                            <th style="width:80px; text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <div style="width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--cl-brown-2),var(--cl-brown)); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px; flex-shrink:0;">
                                            {{ strtoupper(mb_substr($row->user->nama ?? '?', 0, 1)) }}
                                        </div>
                                        <div style="min-width:0;">
                                            <div style="font-weight:600; color:var(--cl-ink);">{{ $row->user->nama ?? '—' }}</div>
                                            <div style="font-size:11px; color:var(--cl-subtle);">{{ $row->user->email ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="st-pill st-pill--neutral">{{ $row->user->group_name ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($row->division)
                                        <span class="st-pill st-pill--success">{{ $row->division->name }}</span>
                                    @else
                                        <span style="color:var(--cl-subtle); font-style:italic; font-size:12px;">Belum di-assign</span>
                                    @endif
                                </td>
                                <td style="color:var(--cl-muted); font-size:12.5px;">
                                    {{ $row->branch?->name ?? '—' }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('checklist.positions.assign') }}"
                                          style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $row->user->id }}">
                                        <select name="division_id" required class="cl-inline-select" style="flex:1; min-width:130px;">
                                            <option value="">— Divisi —</option>
                                            @foreach($divisions as $div)
                                                <option value="{{ $div->id }}" {{ optional($row->position)->division_id === $div->id ? 'selected' : '' }}>{{ $div->name }}</option>
                                            @endforeach
                                        </select>
                                        <select name="raw_branch_id" required class="cl-inline-select" style="flex:1; min-width:140px;">
                                            <option value="">— Cabang —</option>
                                            @foreach($branches as $b)
                                                <option value="{{ $b->id }}" {{ optional($row->position)->raw_branch_id === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="st-btn-brand" style="padding:7px 14px; font-size:12px; white-space:nowrap;">
                                            <i class="fas fa-save"></i> {{ $row->position ? 'Update' : 'Set' }}
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align:center;">
                                    @if($row->position)
                                        <form method="POST" action="{{ route('checklist.positions.remove', $row->position->id) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Nonaktifkan posisi {{ $row->user->nama }} dari {{ $row->division->name ?? '-' }}? Karyawan tidak akan bisa isi checklist sampai di-assign ulang.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="st-icon-btn" title="Nonaktifkan posisi"
                                                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span style="color:var(--cl-subtle); font-size:11px;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
    .cl-inline-select {
        height: 34px;
        padding: 0 24px 0 10px;
        border: 1px solid var(--cl-line);
        border-radius: 8px;
        background: #FFFFFF;
        color: var(--cl-ink);
        font-size: 12.5px;
        line-height: 34px;
        cursor: pointer;
        font-family: inherit;
    }
    .cl-inline-select:focus {
        outline: none;
        border-color: var(--cl-brown-2);
        box-shadow: 0 0 0 3px rgba(92,46,21,.10);
    }
</style>
@endsection
