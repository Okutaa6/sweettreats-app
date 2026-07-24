@extends('layouts.app')

@php
    // Palet warna avatar deterministik (dari nama) + inisial.
    $palette = ['#5B4FC7', '#a3641e', '#b8860b', '#2a9d8f', '#c1466b', '#3b7dd8', '#7048a8', '#c9772a'];
    $colorOf = function ($seed) use ($palette) {
        return $palette[abs(crc32((string) $seed)) % count($palette)];
    };
    $initialsOf = function ($nama) {
        $parts = preg_split('/\s+/', trim((string) $nama)) ?: [];
        $a = mb_substr($parts[0] ?? '', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);
        $ini = trim($a . $b);
        return mb_strtoupper($ini !== '' ? $ini : mb_substr((string) $nama, 0, 2));
    };

    $totalRoles = $allRoles->count();
    $totalUsers = collect($memberCount)->sum();
    $linked     = $allRoles->whereNotNull('parent_id')->count();
@endphp

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Role &amp; Struktur</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- ===== Hero ringkas ===== --}}
        <div class="rgx-hero">
            <div class="rgx-hero__text">
                <div class="rgx-hero__title">Kelola Role &amp; Struktur Organisasi</div>
                <p class="rgx-hero__sub">Role = jabatan. Atur <strong>hak akses menu</strong> <i class="fas fa-key"></i> sekaligus <strong>garis atasan-bawahan</strong> untuk struktur organisasi.</p>
                <div class="rgx-stats">
                    <div class="rgx-stat"><span class="rgx-stat__num">{{ $totalRoles }}</span><span class="rgx-stat__lbl">Role</span></div>
                    <div class="rgx-stat"><span class="rgx-stat__num">{{ $totalUsers }}</span><span class="rgx-stat__lbl">User</span></div>
                    <div class="rgx-stat"><span class="rgx-stat__num">{{ $linked }}</span><span class="rgx-stat__lbl">Terhubung</span></div>
                </div>
            </div>
            <a href="{{ route('setgroup.new') }}" class="rgx-add"><i class="fas fa-plus"></i> Tambah Role</a>
        </div>

        {{-- Tab switcher: Detail | Bagan --}}
        <div class="rs-tabs">
            <button type="button" class="rs-tab active" data-tab="detail"><i class="fas fa-th-large"></i> Detail</button>
            <button type="button" class="rs-tab" data-tab="bagan"><i class="fas fa-sitemap"></i> Bagan</button>
        </div>

        {{-- ===== TAB: DETAIL (kartu role) ===== --}}
        <div class="rs-pane active" id="pane-detail">
            <div class="rgx-grid">
                @foreach ($allRoles as $role)
                    @php
                        $cnt     = $memberCount[$role->id] ?? 0;
                        $members = collect($membersJs[$role->id] ?? []);
                        $accent  = $colorOf($role->nama);
                        $isRoot  = !$role->parent_id;
                    @endphp
                    <div class="rgx-card org-clickable" style="--accent: {{ $accent }};"
                         data-pos="{{ $role->id }}" data-pos-name="{{ $role->nama }}" title="Klik untuk lihat anggota">
                        <div class="rgx-card__accent"></div>
                        <div class="rgx-card__body">
                            <div class="rgx-card__top">
                                <div class="rgx-avatar" style="background: {{ $accent }};">{{ $initialsOf($role->nama) }}</div>
                                <div class="rgx-card__id">
                                    <div class="rgx-card__name">{{ $role->nama }}</div>
                                    <div class="rgx-card__desc">{{ $role->deskripsi ?: 'Tanpa deskripsi' }}</div>
                                </div>
                                <div class="rgx-card__actions">
                                    <a href="{{ route('setgroup.edit', $role->id) }}" class="rgx-ibtn" title="Edit / atur atasan"><i class="fas fa-pen"></i></a>
                                    <a href="{{ route('setgroup.access', $role->id) }}" class="rgx-ibtn" title="Atur akses menu"><i class="fas fa-key"></i></a>
                                    <a href="{{ route('setgroup.access', $role->id) }}" class="rgx-ibtn rgx-ibtn--add" title="Kelola Halaman"><i class="fas fa-clipboard-list"></i></a>
                                    @unless ($isRoot)
                                        <form method="POST" action="{{ route('setgroup.detach', $role->id) }}" class="rgx-detach"
                                              onsubmit="return confirm('Lepas role ini dari hierarki? Role akan menjadi puncak (tanpa atasan).');">
                                            @csrf
                                            <button type="submit" class="rgx-ibtn rgx-ibtn--detach" title="Lepas dari hierarki (jadikan puncak)"><i class="fas fa-unlink"></i></button>
                                        </form>
                                    @endunless
                                </div>
                            </div>

                            <div class="rgx-chips">
                                @unless ($isRoot)
                                    <span class="rgx-chip"><i class="fas fa-level-up-alt fa-rotate-90"></i> {{ optional($role->parent)->nama }}</span>
                                @endunless
                                <span class="rgx-chip rgx-chip--soft"><i class="fas fa-sign-in-alt"></i> {{ $role->landing_page }}</span>
                            </div>

                            <div class="rgx-card__foot">
                                <div class="rgx-avatars">
                                    @forelse ($members->take(4) as $m)
                                        <span class="rgx-mini" style="background: {{ $colorOf($m['nama']) }};" title="{{ $m['nama'] }}">{{ $initialsOf($m['nama']) }}</span>
                                    @empty
                                        <span class="rgx-empty-av"><i class="fas fa-user-slash"></i></span>
                                    @endforelse
                                    @if ($cnt > 4)
                                        <span class="rgx-mini rgx-mini--more">+{{ $cnt - 4 }}</span>
                                    @endif
                                </div>
                                <span class="rgx-count">{{ $cnt }} {{ $cnt === 1 ? 'anggota' : 'anggota' }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ===== TAB: BAGAN (org chart) ===== --}}
        <div class="rs-pane" id="pane-bagan">
            @if ($baganRoots->isEmpty())
                <div class="rs-empty">
                    <i class="fas fa-sitemap" style="font-size:34px; opacity:.35; display:block; margin-bottom:12px;"></i>
                    <div style="font-weight:700; color:#3d2410;">Bagan masih kosong</div>
                    <div class="mt-1">Buka <strong>Detail</strong>, klik <i class="fas fa-pen"></i> pada sebuah role, lalu pilih <strong>Atasan</strong>-nya.<br>Begitu ada satu hubungan atasan-bawahan, bagannya muncul di sini.</div>
                </div>
            @else
                <div class="chart-scroll">
                    <div class="orgchart" id="orgchart">
                        <ul>
                            @foreach ($baganRoots as $root)
                                @include('set.group._orgnode', ['node' => $root, 'memberCount' => $memberCount, 'divisionsByGroup' => $divisionsByGroup])
                            @endforeach
                        </ul>
                    </div>
                </div>

            @endif
        </div>
    </div>
@endsection

@section('css')
<style>
    /* ===== Hero ===== */
    .rgx-hero {
        display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;
        background: linear-gradient(120deg, #ffffff 0%, #fbf4ec 100%);
        border: 1px solid #efe4d6; border-radius: 18px; padding: 20px 22px; margin-bottom: 18px;
        box-shadow: 0 2px 12px rgba(70,21,0,.05);
    }
    .rgx-hero__title { font-size: 20px; font-weight: 800; color: #2f1c0d; letter-spacing: -.2px; }
    .rgx-hero__sub { font-size: 13px; color: #9a8672; margin: 4px 0 14px; max-width: 640px; }
    .rgx-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .rgx-stat {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-width: 74px; padding: 8px 14px; background: #fff; border: 1px solid #eee2d3;
        border-radius: 12px; box-shadow: 0 1px 3px rgba(70,21,0,.04);
    }
    .rgx-stat__num { font-size: 20px; font-weight: 800; color: #461500; line-height: 1; }
    .rgx-stat__lbl { font-size: 11px; color: #a5917c; margin-top: 3px; text-transform: uppercase; letter-spacing: .5px; }
    .rgx-add {
        display: inline-flex; align-items: center; gap: 7px; background: #461500; color: #fff;
        font-weight: 700; font-size: 14px; padding: 11px 20px; border-radius: 12px; text-decoration: none;
        box-shadow: 0 4px 12px rgba(70,21,0,.25); transition: transform .15s, box-shadow .15s;
    }
    .rgx-add:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(70,21,0,.32); }

    /* ===== Tabs (gaya garis-bawah) ===== */
    .rs-tabs { display: flex; gap: 6px; margin-bottom: 18px; border-bottom: 2px solid #ece0d0; }
    .rs-tab {
        border: 0; background: transparent; color: #a5917c; font-weight: 700; font-size: 14px;
        padding: 10px 18px 11px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
        border-bottom: 3px solid transparent; margin-bottom: -2px; transition: color .15s, border-color .15s;
    }
    .rs-tab i { font-size: 13px; }
    .rs-tab:hover { color: #5b3a1a; }
    .rs-tab.active { color: #461500; border-bottom-color: #461500; }
    .rs-pane { display: none; }
    .rs-pane.active { display: block; animation: rgxFade .22s ease; }
    @keyframes rgxFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    /* ===== Grid & Card ===== */
    .rgx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }
    .rgx-card {
        position: relative; background: #fff; border: 1px solid #efe6da; border-radius: 18px; overflow: hidden;
        cursor: pointer; box-shadow: 0 2px 10px rgba(70,21,0,.05); transition: transform .18s ease, box-shadow .18s ease;
    }
    .rgx-card:hover { transform: translateY(-4px); box-shadow: 0 16px 34px rgba(70,21,0,.15); }
    .rgx-card__accent { height: 6px; background: var(--accent); }
    .rgx-card__accent { background: linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #ffffff)); }
    .rgx-card__body { padding: 16px 18px 14px; }
    .rgx-card__top { display: flex; align-items: center; gap: 12px; }
    .rgx-avatar {
        width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0; color: #fff; font-weight: 800;
        font-size: 17px; display: grid; place-items: center; box-shadow: 0 4px 10px rgba(0,0,0,.12);
    }
    .rgx-card__id { min-width: 0; flex: 1; }
    .rgx-card__name { font-size: 17px; font-weight: 800; color: #2f1c0d; line-height: 1.15; letter-spacing: -.2px; }
    .rgx-card__desc { font-size: 12.5px; color: #a5917c; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rgx-card__actions { display: flex; gap: 5px; flex-shrink: 0; align-self: flex-start; }
    .rgx-detach { margin: 0; display: inline-flex; }
    .rgx-ibtn {
        width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; text-decoration: none;
        color: #7a6552; background: #f6efe6; font-size: 12px; transition: all .14s;
    }
    .rgx-ibtn:hover { background: #461500; color: #fff; transform: translateY(-1px); }
    .rgx-ibtn--add:hover { background: #5B4FC7; }
    .rgx-ibtn--detach:hover { background: #A32D2D; }

    .rgx-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 14px 0 12px; }
    .rgx-chip {
        display: inline-flex; align-items: center; gap: 5px; font-size: 11.5px; font-weight: 600;
        color: #6f5a45; background: #f4ede4; border-radius: 20px; padding: 4px 11px; max-width: 100%;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .rgx-chip i { opacity: .7; }
    .rgx-chip--root { background: #efeaff; color: #5B4FC7; }
    .rgx-chip--soft { background: #fff6ec; color: #a3641e; font-family: inherit; }

    .rgx-card__foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #f2e9dd; padding-top: 12px; }
    .rgx-avatars { display: flex; align-items: center; }
    .rgx-mini {
        width: 28px; height: 28px; border-radius: 50%; color: #fff; font-size: 10.5px; font-weight: 700;
        display: grid; place-items: center; border: 2px solid #fff; margin-left: -8px; box-shadow: 0 1px 3px rgba(0,0,0,.15);
    }
    .rgx-mini:first-child { margin-left: 0; }
    .rgx-mini--more { background: #cdbfae !important; color: #4d3b28; }
    .rgx-empty-av { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: #f2ebe1; color: #b9a48d; font-size: 11px; }
    .rgx-count { font-size: 12px; font-weight: 600; color: #9a8672; }

    .rs-empty { padding: 46px; text-align: center; color: #9a8672; background: #fff; border-radius: 18px; border: 1px dashed #e2d5c4; }

    /* ===== Org chart (bagan) ===== */
    .chart-scroll { overflow-x: auto; padding: 8px 2px 22px; text-align: center; }
    .orgchart { display: inline-block; text-align: center; }
    .orgchart ul { position: relative; padding-top: 22px; display: flex; justify-content: center; margin: 0; list-style: none; }
    .orgchart > ul { padding-top: 0; }
    .orgchart li { list-style: none; position: relative; padding: 22px 12px 0; text-align: center; }
    .orgchart li::before, .orgchart li::after {
        content: ''; position: absolute; top: 0; right: 50%;
        border-top: 2px solid #a97c48; width: 50%; height: 22px;
    }
    .orgchart li::after { right: auto; left: 50%; border-left: 2px solid #a97c48; }
    .orgchart li:only-child::after, .orgchart li:only-child::before { display: none; }
    .orgchart li:only-child { padding-top: 0; }
    .orgchart li:first-child::before, .orgchart li:last-child::after { border: 0 none; }
    .orgchart li:last-child::before { border-right: 2px solid #a97c48; border-radius: 0 6px 0 0; }
    .orgchart li:first-child::after { border-radius: 6px 0 0 0; }
    .orgchart ul ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 2px solid #a97c48; width: 0; height: 22px; }

    .oc-node {
        display: inline-flex; flex-direction: column; align-items: stretch; vertical-align: top;
        min-width: 180px; max-width: 220px; background: #fff; border: 1px solid #e6dbcd;
        border-radius: 14px; padding: 12px 12px 10px; box-shadow: 0 4px 12px rgba(70,21,0,.08);
        position: relative; text-align: center; transition: box-shadow .15s, transform .15s;
    }
    .oc-node:hover { box-shadow: 0 12px 26px rgba(70,21,0,.16); transform: translateY(-3px); }
    .oc-node.k-root  { border-top: 4px solid #5B4FC7; }
    .oc-node.k-child { border-top: 4px solid #a3641e; }
    .oc-node__name { font-weight: 800; color: #2f1c0d; font-size: 14px; margin-bottom: 5px; }
    .oc-node__holder { font-size: 12px; color: #9a8672; margin-top: 4px; }
    .oc-node__holder.empty { font-style: italic; }
    .oc-node__tools { display: flex; gap: 6px; justify-content: center; margin-top: 10px; }
    .oc-node__tools a {
        flex: 1; text-align: center; font-size: 12px; padding: 5px 6px; border-radius: 8px;
        text-decoration: none; border: 1px solid #ece0d0; color: #5b3a1a; transition: all .12s;
    }
    .oc-node__tools a:hover { background: #FBF3E9; }
    .oc-node__tools a.add { border-color: #cdbfe8; color: #5B4FC7; }

    /* ===== Drag & drop struktur ===== */
    .oc-hint {
        max-width: 720px; margin: 0 auto 14px; font-size: 12.5px; color: #8a7358; line-height: 1.6;
        background: #fff8ef; border: 1px dashed #e2d0b7; border-radius: 12px; padding: 10px 14px; text-align: center;
    }
    .oc-hint i { color: #a3641e; }
    .oc-node { cursor: grab; }
    .oc-node:active { cursor: grabbing; }
    .oc-node.dragging { opacity: .45; }
    .oc-node.drop-ok { box-shadow: 0 0 0 3px #2a9d8f, 0 10px 22px rgba(42,157,143,.25); transform: translateY(-2px); }
    .oc-node.drop-bad { box-shadow: 0 0 0 3px #c1466b; }
    .oc-rootzone {
        margin: 6px auto 0; max-width: 560px; text-align: center; font-size: 13px; font-weight: 600; color: #a5917c;
        background: #fbf4ec; border: 2px dashed #ddc9ad; border-radius: 14px; padding: 16px; transition: all .15s;
    }
    .oc-rootzone i { color: #b8860b; margin-right: 4px; }
    .oc-rootzone.drop-ok { border-color: #5B4FC7; color: #5B4FC7; background: #efeaff; }

    /* ===== Divisi checklist di bagan ===== */
    .oc-node__divs {
        display: flex; flex-wrap: wrap; gap: 4px; justify-content: center;
        margin-top: 8px; padding-top: 8px; border-top: 1px dashed #eaddcb; min-height: 20px;
    }
    .oc-div-chip {
        display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700;
        color: #1f6f66; background: #e5f4f1; border: 1px solid #bfe3dc; border-radius: 20px;
        padding: 3px 9px; cursor: grab; white-space: nowrap; transition: transform .12s, box-shadow .12s;
    }
    .oc-div-chip i { opacity: .8; }
    .oc-div-chip:hover { transform: translateY(-1px); box-shadow: 0 3px 8px rgba(31,111,102,.22); }
    .oc-div-chip:active { cursor: grabbing; }
    .oc-div-chip.dragging { opacity: .4; }
    .oc-div-chip--inline { cursor: default; padding: 1px 7px; }
    .oc-div-empty { font-size: 10.5px; color: #c3b19c; font-style: italic; }
    .oc-node.drop-div { box-shadow: 0 0 0 3px #2a9d8f, 0 10px 22px rgba(42,157,143,.25); }

    .oc-divtray {
        margin: 16px auto 0; max-width: 720px; background: #fbf4ec; border: 2px dashed #ddc9ad;
        border-radius: 14px; padding: 12px 16px; transition: all .15s;
    }
    .oc-divtray__label { font-size: 12.5px; font-weight: 700; color: #6f5a45; margin-bottom: 8px; }
    .oc-divtray__label i { color: #b8860b; margin-right: 5px; }
    .oc-divtray__label span { font-weight: 500; color: #a5917c; }
    .oc-divtray__items { display: flex; flex-wrap: wrap; gap: 6px; }
    .oc-divtray.drop-ok { border-color: #2a9d8f; background: #e9f6f3; }
</style>
@endsection

@section('js')
<script>
    (function () {
        // Tab Detail | Bagan (ingat tab aktif via hash agar tetap di Bagan setelah reload)
        var tabs  = document.querySelectorAll('.rs-tab');
        var panes = document.querySelectorAll('.rs-pane');
        function activateTab(key) {
            tabs.forEach(function (x) { x.classList.toggle('active', x.getAttribute('data-tab') === key); });
            panes.forEach(function (p) { p.classList.toggle('active', p.id === 'pane-' + key); });
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                var key = t.getAttribute('data-tab');
                activateTab(key);
                if (history.replaceState) { history.replaceState(null, '', '#' + key); } else { location.hash = key; }
            });
        });
        if (location.hash === '#bagan') { activateTab('bagan'); }

        // Klik kartu / node role → daftar user (anggota) di role itu.
        var HOLDERS = @json($membersJs);
        var PALETTE = ['#5B4FC7','#a3641e','#b8860b','#2a9d8f','#c1466b','#3b7dd8','#7048a8','#c9772a'];
        function hash(s){ var h=0; s=String(s); for(var i=0;i<s.length;i++){ h=(h*31 + s.charCodeAt(i))>>>0; } return h; }
        function initials(n){ var p=String(n).trim().split(/\s+/); var a=(p[0]||'')[0]||''; var b=(p[1]||'')[0]||''; return ((a+b) || String(n).slice(0,2)).toUpperCase(); }

        document.addEventListener('click', function (e) {
            var card = e.target.closest('.org-clickable');
            if (!card) return;
            if (e.target.closest('a, button, .oc-div-chip')) return;

            var id   = card.getAttribute('data-pos');
            var name = card.getAttribute('data-pos-name');
            var list = HOLDERS[id] || [];

            var html;
            if (list.length) {
                html = '<div style="text-align:left;">' + list.map(function (u) {
                    var c = PALETTE[hash(u.nama) % PALETTE.length];
                    var sub = [];
                    if (u.branch) sub.push('<i class="fas fa-map-marker-alt"></i> ' + u.branch);
                    if (u.email)  sub.push(u.email);
                    return '<div style="display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid #f3ece2;">'
                        + '<span style="width:34px;height:34px;border-radius:50%;flex-shrink:0;background:' + c + ';color:#fff;font-weight:700;font-size:12px;display:grid;place-items:center;">' + initials(u.nama) + '</span>'
                        + '<div><div style="font-weight:700;color:#2f1c0d;">' + u.nama + '</div>'
                        + (sub.length ? '<div style="font-size:12px;color:#9a8672;">' + sub.join(' &middot; ') + '</div>' : '') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            } else {
                html = '<em style="color:#9a8672;">Belum ada user di role ini.</em>';
            }

            Swal.fire({
                title: name + ' <span style="font-size:13px;color:#9a8672;font-weight:500;">(' + list.length + ' anggota)</span>',
                html: html,
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#461500',
                width: 440,
            });
        });

        // ===== Drag & drop di bagan: geser role ke role lain → ganti atasan. =====
        var pane    = document.getElementById('pane-bagan');
        var chart   = document.getElementById('orgchart');
        if (pane && chart) {
            var REPARENT_URL  = @json(route('setgroup.reparent'));
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var CSRF = csrfMeta ? csrfMeta.getAttribute('content') : '';
            var drag = null;   // { id: '...' }

            // Cek siklus di klien: target tak boleh dirinya sendiri / turunannya.
            function isSelfOrDescendant(candidateId, ancestorId) {
                var cur = candidateId, guard = 0;
                while (cur && guard++ < 1000) {
                    if (cur === ancestorId) return true;
                    var node = chart.querySelector('.oc-node[data-id="' + cur + '"]');
                    cur = node ? (node.getAttribute('data-parent') || null) : null;
                    if (cur === '') cur = null;
                }
                return false;
            }
            function clearDrop() {
                pane.querySelectorAll('.drop-ok, .drop-bad').forEach(function (el) {
                    el.classList.remove('drop-ok', 'drop-bad');
                });
            }

            pane.addEventListener('dragstart', function (e) {
                // Jangan mulai drag role dari tombol Edit / tambah.
                if (e.target.closest('a, button')) { e.preventDefault(); return; }
                var node = e.target.closest('.oc-node');
                if (!node) { e.preventDefault(); return; }
                drag = { id: node.getAttribute('data-id') };
                node.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', drag.id);
            });

            pane.addEventListener('dragover', function (e) {
                if (!drag) return;
                var node = e.target.closest('.oc-node');
                if (!node) return;
                var tid = node.getAttribute('data-id');
                var bad = isSelfOrDescendant(tid, drag.id);
                e.preventDefault();
                e.dataTransfer.dropEffect = bad ? 'none' : 'move';
                clearDrop();
                node.classList.add(bad ? 'drop-bad' : 'drop-ok');
            });

            pane.addEventListener('drop', function (e) {
                if (!drag) return;
                var node = e.target.closest('.oc-node');
                if (!node) return;
                e.preventDefault();
                var tid = node.getAttribute('data-id');
                if (!isSelfOrDescendant(tid, drag.id)) doReparent(drag.id, tid);
                clearDrop();
            });

            document.addEventListener('dragend', function () {
                clearDrop();
                pane.querySelectorAll('.dragging').forEach(function (el) { el.classList.remove('dragging'); });
                drag = null;
            });

            function post(url, body, failMsg) {
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify(body)
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.ok) {
                        location.hash = 'bagan';
                        Swal.fire({ icon: 'success', title: res.j.message || 'Tersimpan', timer: 1100, showConfirmButton: false });
                        setTimeout(function () { location.reload(); }, 1150);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: (res.j && res.j.message) || failMsg, confirmButtonColor: '#461500' });
                    }
                })
                .catch(function () {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: 'Koneksi bermasalah, coba lagi.', confirmButtonColor: '#461500' });
                });
            }
            function doReparent(id, parentId) { post(REPARENT_URL, { id: id, parent_id: parentId }, 'Tidak bisa memindahkan role.'); }
        }
    })();
</script>
@endsection
