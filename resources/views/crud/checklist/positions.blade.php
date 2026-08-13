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

    {{-- ─── TABS ─── --}}
    @php
        $allRolls = $rows->flatMap(function ($r) {
            return $r->rolls->map(fn ($roll) => (object) ['roll' => $roll, 'user' => $r->user]);
        })->sortBy(fn ($x) => optional($x->roll->effective_from)->timestamp)->values();
    @endphp
    <div class="cl-panel" style="padding:0;">
        <div class="cl-postab">
            <button type="button" class="cl-postab__btn is-active" data-postab="home">
                <i class="fas fa-user-tag"></i> Posisi (Cabang Asal)
            </button>
            <button type="button" class="cl-postab__btn" data-postab="rolling">
                <i class="fas fa-people-arrows"></i> Rolling (Sementara)
                @if($allRolls->count())<span class="cl-postab__count">{{ $allRolls->count() }}</span>@endif
            </button>
            <button type="button" class="cl-postab__btn" data-postab="swap">
                <i class="fas fa-exchange-alt"></i> Tukar (Swap)
            </button>
        </div>

    {{-- ═══ PANE: POSISI (HOME) ═══ --}}
    <div data-postab-pane="home">
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
    </div>{{-- /pane home --}}

    {{-- ═══ PANE: ROLLING (SEMENTARA) ═══ --}}
    <div data-postab-pane="rolling" hidden>
        <div style="padding:16px 18px;">
            <p style="font-size:12.5px; color:var(--cl-muted); margin:0 0 12px;">
                Tempatkan karyawan ke <strong>cabang lain</strong> untuk rentang tanggal tertentu. Selama periode itu
                checklist-nya mengikuti cabang tujuan; setelah tanggal berakhir <strong>otomatis kembali</strong> ke cabang asal.
                Divisi/role tidak berubah — hanya cabang. Untuk pindah permanen, pakai tab <em>Posisi</em>.
            </p>

            <form method="POST" action="{{ route('checklist.positions.rolling') }}"
                  class="cl-roll-form" style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap; margin-bottom:16px;">
                @csrf
                <div>
                    <label class="cl-roll-lbl">Divisi</label>
                    <select class="cl-inline-select" style="min-width:150px;" data-emp-filter="#roll-emp">
                        <option value="">Semua divisi</option>
                        @foreach($divisions as $div)
                            <option value="{{ $div->id }}">{{ $div->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Karyawan</label>
                    <select name="user_id" id="roll-emp" required class="cl-inline-select" style="min-width:200px;">
                        <option value="">— Pilih karyawan —</option>
                        @foreach($rows as $r)
                            <option value="{{ $r->user->id }}" data-div="{{ optional($r->roleDivision)->id }}">{{ $r->user->nama }} @if($r->roleDivision)· {{ $r->roleDivision->name }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Cabang tujuan</label>
                    <select name="raw_branch_id" required class="cl-inline-select" style="min-width:160px;">
                        <option value="">— Cabang —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Mulai</label>
                    <input type="date" name="effective_from" required class="cl-inline-select" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="cl-roll-lbl">Selesai</label>
                    <input type="date" name="effective_to" required class="cl-inline-select" min="{{ now()->toDateString() }}">
                </div>
                <div style="flex:1; min-width:160px;">
                    <label class="cl-roll-lbl">Alasan (opsional)</label>
                    <input type="text" name="note" maxlength="500" class="cl-inline-select" style="width:100%;" placeholder="mis. gantikan rekan cuti">
                </div>
                <button type="submit" class="st-btn-brand" style="white-space:nowrap;">
                    <i class="fas fa-people-arrows"></i> Tetapkan
                </button>
            </form>

            @if($allRolls->isEmpty())
                <div class="cl-empty" style="padding:24px;">
                    <span class="cl-empty__icon"><i class="fas fa-people-arrows"></i></span>
                    <div class="cl-empty__title">Belum ada rolling aktif</div>
                    <div class="cl-empty__desc">Penempatan sementara yang sedang/akan berjalan akan muncul di sini.</div>
                </div>
            @else
                <div class="st-table-wrap st-table-wrap--scroll">
                    <table class="st-table">
                        <thead><tr>
                            <th>Karyawan</th>
                            <th style="width:180px;">Cabang tujuan</th>
                            <th style="width:200px;">Periode</th>
                            <th>Alasan</th>
                            <th style="width:90px; text-align:center;">Aksi</th>
                        </tr></thead>
                        <tbody>
                            @foreach($allRolls as $x)
                                <tr>
                                    <td style="font-weight:600; color:var(--cl-ink);">{{ $x->user->nama }}</td>
                                    <td><span class="st-pill st-pill--warning">{{ $x->roll->branch?->name ?? $x->roll->raw_branch_id }}</span></td>
                                    <td style="font-size:12.5px; color:var(--cl-muted);">
                                        {{ optional($x->roll->effective_from)->translatedFormat('d M Y') }} –
                                        {{ $x->roll->effective_to
                                            ? $x->roll->effective_to->translatedFormat('d M Y')
                                            : 'terbuka' }}
                                        @if($x->roll->effective_from && $x->roll->effective_from->gt(now()))
                                            <span class="st-pill st-pill--neutral" style="margin-left:4px;">akan datang</span>
                                        @endif
                                    </td>
                                    <td style="font-size:12px; color:var(--cl-subtle);">{{ $x->roll->note ?: '—' }}</td>
                                    <td style="text-align:center;">
                                        <form method="POST" action="{{ route('checklist.positions.rolling.remove', $x->roll->id) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Akhiri rolling {{ $x->user->nama }} ke {{ $x->roll->branch?->name ?? '-' }}? Karyawan kembali ke cabang asal.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="st-icon-btn" title="Akhiri rolling"
                                                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>{{-- /pane rolling --}}

    {{-- ═══ PANE: TUKAR (SWAP) ═══ --}}
    <div data-postab-pane="swap" hidden>
        <div style="padding:16px 18px;">
            <p style="font-size:12.5px; color:var(--cl-muted); margin:0 0 12px;">
                Tukar dua karyawan sekaligus untuk satu rentang tanggal — masing-masing ditempatkan ke cabang tujuannya.
                Ini hanya membuat <strong>dua rolling</strong> sekaligus (bisa juga dibuat satu per satu di tab Rolling).
            </p>
            <form method="POST" action="{{ route('checklist.positions.rolling.swap') }}"
                  style="display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                @csrf
                <div>
                    <label class="cl-roll-lbl">Divisi</label>
                    <select class="cl-inline-select" style="min-width:150px;" data-emp-filter="#swap-a,#swap-b">
                        <option value="">Semua divisi</option>
                        @foreach($divisions as $div)
                            <option value="{{ $div->id }}">{{ $div->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Karyawan A</label>
                    <select name="user_a" id="swap-a" required class="cl-inline-select" style="min-width:180px;">
                        <option value="">— Pilih —</option>
                        @foreach($rows as $r)
                            <option value="{{ $r->user->id }}" data-div="{{ optional($r->roleDivision)->id }}">{{ $r->user->nama }} @if($r->roleDivision)· {{ $r->roleDivision->name }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">A → cabang</label>
                    <select name="branch_a" required class="cl-inline-select" style="min-width:150px;">
                        <option value="">— Cabang —</option>
                        @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Karyawan B</label>
                    <select name="user_b" id="swap-b" required class="cl-inline-select" style="min-width:180px;">
                        <option value="">— Pilih —</option>
                        @foreach($rows as $r)
                            <option value="{{ $r->user->id }}" data-div="{{ optional($r->roleDivision)->id }}">{{ $r->user->nama }} @if($r->roleDivision)· {{ $r->roleDivision->name }}@endif</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">B → cabang</label>
                    <select name="branch_b" required class="cl-inline-select" style="min-width:150px;">
                        <option value="">— Cabang —</option>
                        @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="cl-roll-lbl">Mulai</label>
                    <input type="date" name="effective_from" required class="cl-inline-select" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="cl-roll-lbl">Selesai</label>
                    <input type="date" name="effective_to" required class="cl-inline-select" min="{{ now()->toDateString() }}">
                </div>
                <button type="submit" class="st-btn-brand" style="white-space:nowrap;">
                    <i class="fas fa-exchange-alt"></i> Tukar
                </button>
            </form>
        </div>
    </div>{{-- /pane swap --}}
    </div>{{-- /cl-panel --}}
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

    .cl-roll-lbl {
        display: block; font-size: 11px; font-weight: 700; color: var(--cl-muted);
        margin-bottom: 4px; text-transform: uppercase; letter-spacing: .02em;
    }
    input.cl-inline-select { line-height: normal; }

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

    // Filter dropdown karyawan berdasarkan divisi yang dipilih.
    document.querySelectorAll('[data-emp-filter]').forEach(function (divSel) {
        var targets = divSel.dataset.empFilter.split(',')
            .map(function (s) { return document.querySelector(s.trim()); })
            .filter(Boolean);
        divSel.addEventListener('change', function () {
            var div = divSel.value;
            targets.forEach(function (emp) {
                var selHidden = false;
                Array.prototype.forEach.call(emp.options, function (opt) {
                    if (!opt.value) return; // placeholder selalu tampil
                    var show = !div || opt.dataset.div === div;
                    opt.hidden = !show;
                    opt.disabled = !show;
                    if (!show && opt.selected) selHidden = true;
                });
                if (selHidden) emp.value = ''; // reset kalau pilihan lama kini tersembunyi
            });
        });
    });
})();
</script>
@endsection
