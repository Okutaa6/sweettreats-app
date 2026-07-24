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
            <span class="cl-kpi__icon"><i class="fas fa-check-circle"></i></span>
            <div class="cl-kpi__body">
                <div class="cl-kpi__label">Posisi Aktif</div>
                <div class="cl-kpi__value">{{ $stats['assigned'] }}</div>
                <div class="cl-kpi__sub">otomatis dari role &amp; cabang</div>
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

        @php $activeRows = $rows->values(); @endphp

        @php
            $tableHead = '<thead><tr>'
                . '<th>Karyawan</th>'
                . '<th style="width:140px;">Role</th>'
                . '<th style="width:160px;">Divisi</th>'
                . '<th style="width:180px;">Cabang</th>'
                . '<th style="width:420px;">Set / Ubah Posisi</th>'
                . '<th style="width:100px; text-align:center;">Aksi</th>'
                . '</tr></thead>';
        @endphp

        @if($activeRows->isEmpty())
            <div class="cl-empty">
                <span class="cl-empty__icon"><i class="fas fa-user-slash"></i></span>
                <div class="cl-empty__title">Belum ada karyawan operasional</div>
                <div class="cl-empty__desc">
                    Karyawan muncul di sini otomatis setelah admin membuat akunnya dengan role operasional (FO / GS / Asdent) dan cabang. Posisi checklist langsung aktif — tanpa perlu diajukan atau dikonfirmasi.
                </div>
            </div>
        @else
            <div class="st-table-wrap st-table-wrap--scroll" style="padding-top:6px;">
                <table class="st-table">
                    {!! $tableHead !!}
                    <tbody>
                        @foreach($activeRows as $row)
                            @include('crud.checklist._position_row', ['row' => $row])
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

    /* Tabs posisi */
    .cl-postab { display: flex; gap: 6px; padding: 14px 18px 0; flex-wrap: wrap; }
    .cl-postab__btn {
        display: inline-flex; align-items: center; gap: 8px;
        border: 1px solid var(--cl-line); border-bottom: none;
        background: #fff; color: var(--cl-muted);
        font-size: 13px; font-weight: 700; padding: 9px 16px;
        border-radius: 10px 10px 0 0; cursor: pointer; transition: .15s;
    }
    .cl-postab__btn:hover { background: var(--cl-cream-1); color: var(--cl-ink); }
    .cl-postab__btn.is-active { background: var(--cl-brown); color: #fff; border-color: var(--cl-brown); }
    .cl-postab__count {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 20px; height: 20px; padding: 0 6px; border-radius: 999px;
        font-size: 11px; font-weight: 800; background: var(--cl-cream-2); color: var(--cl-ink);
    }
    .cl-postab__btn.is-active .cl-postab__count { background: rgba(255,255,255,.25); color: #fff; }
    .cl-postab__pane[hidden] { display: none; }
</style>

<script>
(function () {
    var btns  = document.querySelectorAll('.cl-postab__btn');
    var panes = document.querySelectorAll('[data-postab-pane]');
    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.dataset.postab;
            btns.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            panes.forEach(function (p) { p.hidden = (p.dataset.postabPane !== tab); });
        });
    });
})();
</script>
@endsection
