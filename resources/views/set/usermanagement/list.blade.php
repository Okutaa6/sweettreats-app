@extends('layouts.app')

{{-- Pusat Akun → User (list) — hi-fi PRESISI dari "Pusat Akun User.dc.html":
     toolbar (cari + Role + Cabang + Status + toggle Perlu perhatian + Akun baru),
     chip detektor kanan-atas, kolom CABANG, status pill ber-dot, ⚠ per baris. --}}

@section('css')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .um { font-family: 'Plus Jakarta Sans', sans-serif; color: #3B1500; padding: 20px 26px 30px; }
    .um-num { font-variant-numeric: tabular-nums; }
    .um-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
    .um-top h1 { font-size: 20px; font-weight: 800; margin: 0; }
    .um-top .sub { color: #9A7256; font-size: 13px; margin-top: 2px; }
    .um-chip-det { display: inline-flex; align-items: center; gap: 8px; background: #FDF0EA; border: 1px solid #F0D3C8; color: #C0392B; border-radius: 12px; padding: 9px 15px; font-weight: 700; font-size: 13px; text-decoration: none; }
    .um-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
    .um-toolbar input[type=text], .um-toolbar select { border: 1px solid #E7D3BE; border-radius: 11px; padding: 11px 13px; font-size: 13px; font-family: inherit; color: #3B1500; background: #fff; }
    .um-toolbar input[type=text] { min-width: 250px; }
    .um-flagbtn { display: inline-flex; align-items: center; gap: 7px; border: 1px solid #E7D3BE; background: #fff; border-radius: 11px; padding: 11px 15px; font-weight: 700; font-size: 13px; color: #7A5B43; cursor: pointer; font-family: inherit; }
    .um-flagbtn.on { background: #FDF0EA; border-color: #F0D3C8; color: #C0392B; }
    .um-add { margin-left: auto; display: inline-flex; align-items: center; gap: 7px; background: linear-gradient(135deg,#3A3A36,#242422); color: #fff; border: 0; border-radius: 11px; padding: 11px 18px; font-weight: 700; font-size: 13.5px; text-decoration: none; box-shadow: 0 4px 12px rgba(20,20,18,.2); }
    .um-count { display: flex; align-items: center; gap: 7px; color: #9A7256; font-size: 12.5px; margin-bottom: 8px; font-weight: 600; }
    .um-tablewrap { background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    /* GRID (bukan <table>) — persis prototype: header div grafit + baris grid,
       lebar kolom pasti. Div nggak kena reset tabel bootstrap. */
    .um-grid-cols { display: grid; grid-template-columns: minmax(190px,1.5fr) minmax(150px,1.3fr) 132px 108px 156px 92px 116px 22px; align-items: center; gap: 10px; }
    .um-head { padding: 13px 18px; background: linear-gradient(120deg,#2C2C29,#3A3A36); color: #E3D8C9; }
    .um-head span { font-size: 10.5px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; }
    .um-rowlink { padding: 12px 18px; border-bottom: 1px solid #F4EADD; text-decoration: none; color: inherit; cursor: pointer; }
    .um-rowlink:hover { background: #FBF4EC; }
    .um-rowlink:last-child { border-bottom: 0; }
    .um-nama { display: flex; align-items: center; gap: 12px; }
    .um-av { width: 38px; height: 38px; border-radius: 50%; background: #B4562E; color: #fff; font-weight: 800; font-size: 15px; display: flex; align-items: center; justify-content: center; flex: none; }
    .um-nama .n { font-weight: 700; color: #3B1500; }
    .um-nama .r { font-size: 11.5px; color: #9A7256; }
    .um-warn { display: inline-flex; color: #C0392B; margin-left: 5px; vertical-align: -2px; }
    .um-mono { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; color: #5A3A22; }
    .um-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; border-radius: 20px; padding: 4px 12px; }
    .um-pill.role { background: #F1E9DF; color: #6b4a2f; }
    .um-pill.dev { background: #F4E7D6; color: #9A6614; }
    .um-pill.on { background: #E3F4E8; color: #2F8E55; } .um-pill.off { background: #EFE7E1; color: #8A6A52; }
    .um-pill .d { width: 6px; height: 6px; border-radius: 50%; }
    .um-pill.on .d { background: #2F8E55; } .um-pill.off .d { background: #8A6A52; }
    .um-akt { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; color: #6b4a2f; white-space: nowrap; }
    .um-dot { width: 8px; height: 8px; border-radius: 50%; flex: none; }
    .um-row-off { opacity: .55; }
    .um-foot { padding: 12px 16px; background: #FCF7F0; }
    .um-empty { padding: 26px; text-align: center; color: #b7a595; font-style: italic; }
</style>
@endsection

@section('content')
@php
    // avatar warna-warni per orang (palette prototype), konsisten via hash id
    $avPal = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avColor = fn ($seed) => $avPal[hexdec(substr(md5((string) $seed), 0, 6)) % 8];
    $aktivitas = function ($u) {
        $t = $u->last_seen_at ?? $u->last_login;
        if (! $t) return ['#C7AC92', 'Belum pernah'];
        $c = \Carbon\Carbon::parse($t);
        if ($c->diffInMinutes(now()) < 10) return ['#2F8E55', 'Baru saja'];
        if ($c->isToday())                 return ['#C9962F', 'Hari ini ' . $c->format('H.i')];
        $h = (int) $c->diffInDays(now());
        return [$h >= 30 ? '#C0392B' : '#B79A82', $h . ' hari lalu'];
    };
    $total = method_exists($users, 'total') ? $users->total() : $users->count();
    $nFlag = count($flagged);
@endphp
<div class="um">
    @include('layouts.notif')

    <div class="um-top">
        <div>
            <h1>User</h1>
            <div class="sub">Pusat Akun · {{ $total }} akun di 5 cabang + kantor pusat</div>
        </div>
        @if($nFlag)
            <a href="{{ route('setkesehatan') }}" class="um-chip-det">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg>
                <span class="um-num">{{ $nFlag }}</span>&nbsp;user kena detektor
            </a>
        @endif
    </div>

    <div class="um-toolbar">
        <input type="text" id="um-cari" placeholder="Cari nama atau email…" onkeyup="umFilter()">
        <select id="um-role" onchange="umFilter()"><option value="">Semua Role</option>
            @foreach($users->pluck('group.nama')->filter()->unique()->sort() as $r)<option>{{ $r }}</option>@endforeach
        </select>
        <select id="um-cabang" onchange="umFilter()"><option value="">Semua Cabang</option>
            @foreach($branches as $b)<option>{{ $b->name }}</option>@endforeach
        </select>
        <select id="um-status" onchange="umFilter()"><option value="">Semua Status</option><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
        <button type="button" id="um-flagonly" class="um-flagbtn" onclick="this.classList.toggle('on');umFilter()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg>
            Perlu perhatian
        </button>
        <a href="{{ route('setusermanagement.new') }}" class="um-add"><span style="font-size:16px;line-height:1">+</span> Akun baru</a>
    </div>

    <div class="um-count">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 7h16M7 12h10M10 17h4"/></svg>
        Menampilkan <b class="um-num" id="um-n">{{ $users->count() }}</b>&nbsp;user
    </div>

    <div class="um-tablewrap">
        <div class="um-grid-cols um-head">
            <span>Nama</span><span>Email</span><span>Role</span><span>Cabang</span><span>Aktivitas</span><span>Perangkat</span><span>Status</span><span></span>
        </div>
        <div id="um-body">
            @foreach ($users as $row)
                @php [$dot, $akt] = $aktivitas($row); $nDev = $deviceCounts[$row->id] ?? 0; $isFlag = isset($flagged[$row->id]); @endphp
                <a href="{{ route('setusermanagement.detail', $row->id) }}"
                   class="um-grid-cols um-rowlink {{ $row->status != 1 ? 'um-row-off' : '' }}"
                   data-cari="{{ strtolower($row->nama . ' ' . $row->email) }}"
                   data-role="{{ $row->group->nama ?? '' }}" data-cabang="{{ optional($row->branch)->name ?? '' }}"
                   data-status="{{ $row->status }}" data-flag="{{ $isFlag ? 1 : 0 }}">
                    <div class="um-nama">
                        <div class="um-av" style="background:{{ $avColor($row->id) }}">{{ mb_strtoupper(mb_substr(trim($row->nama), 0, 1)) }}</div>
                        <div style="min-width:0">
                            <span class="n">{{ $row->nama }}</span>
                            @if($isFlag)<span class="um-warn" title="kena detektor Kesehatan"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg></span>@endif
                            <div class="r">{{ $row->group->nama ?? '—' }}</div>
                        </div>
                    </div>
                    <span class="um-mono" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $row->email }}</span>
                    <span><span class="um-pill role">{{ $row->group->nama ?? '—' }}</span></span>
                    <span style="font-size:13px">{{ optional($row->branch)->name ?? '—' }}</span>
                    <span class="um-akt"><span class="um-dot" style="background:{{ $dot }}"></span>{{ $akt }}</span>
                    <span>@if($nDev)<span class="um-pill dev um-num">{{ $nDev }}</span>@else <span style="color:#c9b6a4">—</span>@endif</span>
                    <span><span class="um-pill {{ $row->status == 1 ? 'on' : 'off' }}"><span class="d"></span>{{ $row->status == 1 ? 'Aktif' : 'Nonaktif' }}</span></span>
                    <span style="color:#c9b6a4;text-align:right">›</span>
                </a>
            @endforeach
        </div>
        <div id="um-kosong" class="um-empty" style="display:none">Tidak ada user yang cocok dengan filter.</div>
        <div class="um-foot">{{ $users->links() }}</div>
    </div>
</div>
@endsection

@section('js')
<script>
    function umFilter() {
        var q = document.getElementById('um-cari').value.toLowerCase();
        var role = document.getElementById('um-role').value;
        var cab = document.getElementById('um-cabang').value;
        var st = document.getElementById('um-status').value;
        var fl = document.getElementById('um-flagonly').classList.contains('on');
        var n = 0;
        document.querySelectorAll('#um-body .um-rowlink').forEach(function (r) {
            var ok = (!q || r.dataset.cari.indexOf(q) > -1)
                  && (!role || r.dataset.role === role)
                  && (!cab || r.dataset.cabang === cab)
                  && (st === '' || r.dataset.status === st)
                  && (!fl || r.dataset.flag === '1');
            r.style.display = ok ? 'grid' : 'none';
            if (ok) n++;
        });
        document.getElementById('um-n').textContent = n;
        document.getElementById('um-kosong').style.display = n ? 'none' : '';
    }
</script>
@endsection
