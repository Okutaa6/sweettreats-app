@extends('layouts.app')

{{-- Pusat Akun → Akses — hi-fi dari "Pusat Akun Akses.dc.html".
     LANGKAH 1: pohon + penanda + panel. LANGKAH 2: grant/cabut role + banner rebuild.
     Hapus/tambah node + guardrail = langkah berikutnya. --}}

@section('css')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .ak { font-family: 'Plus Jakarta Sans', sans-serif; color: #3B1500; padding: 20px 26px 30px; }
    .ak-num { font-variant-numeric: tabular-nums; }
    .ak-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
    .ak-top h1 { font-size: 20px; font-weight: 800; margin: 0; }
    .ak-top .sub { color: #9A7256; font-size: 13px; margin-top: 2px; }
    .ak-rebuild { color: #9A7256; font-size: 12.5px; }
    /* banner rebuild */
    .ak-banner { display: flex; align-items: center; gap: 16px; background: linear-gradient(120deg,#5A3A0E,#7A4E12); border-left: 5px solid #E8A24A; border-radius: 14px; padding: 15px 18px; margin-bottom: 16px; }
    .ak-banner .bi { width: 42px; height: 42px; border-radius: 11px; background: rgba(255,255,255,.14); color: #F3D6A6; display: flex; align-items: center; justify-content: center; flex: none; }
    .ak-banner b { color: #FDF3E7; font-size: 14.5px; }
    .ak-banner p { color: #E9D3B0; font-size: 12.5px; margin: 2px 0 0; }
    .ak-banner button { margin-left: auto; background: #E8A24A; color: #3B1500; border: 0; border-radius: 11px; padding: 11px 18px; font-weight: 800; font-size: 13.5px; cursor: pointer; flex: none; }
    .ak-legend { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; font-size: 12px; color: #9A7256; }
    .ak-legend .lg { display: inline-flex; align-items: center; gap: 6px; }
    .ak-badge { font-size: 10px; font-weight: 800; border-radius: 6px; padding: 2px 7px; }
    .ak-badge.yatim { background: #EFE7E1; color: #8A6A52; }
    .ak-badge.dobel { background: #FBEFD9; color: #9A6614; }
    .ak-badge.parent { background: #FBE3DE; color: #C0392B; }
    .ak-grid { display: grid; grid-template-columns: 1.35fr 1fr; gap: 16px; align-items: start; }
    @media (max-width: 1000px) { .ak-grid { grid-template-columns: 1fr; } }
    .ak-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    .ak-cardhead { background: linear-gradient(120deg,#2C2C29,#3A3A36); color: #E3D8C9; padding: 12px 16px; font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
    .ak-tree { padding: 6px 0; max-height: calc(100vh - 260px); overflow-y: auto; }
    .ak-node { display: flex; align-items: center; gap: 10px; padding: 9px 16px; cursor: pointer; border-left: 3px solid transparent; }
    .ak-node:hover { background: #FBF4EC; }
    .ak-node.sel { background: #FBF4EC; border-left-color: #461500; }
    .ak-node .ico { width: 26px; height: 26px; border-radius: 8px; background: #F1E9DF; color: #5A3A22; display: flex; align-items: center; justify-content: center; flex: none; font-size: 13px; }
    .ak-node .nm { font-weight: 700; font-size: 13.5px; color: #3B1500; }
    .ak-node .lnk { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 11.5px; color: #9A7256; }
    .ak-node .rc { margin-left: auto; font-size: 11.5px; color: #9A7256; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; flex: none; }
    .ak-node .rc.zero { color: #C9B39C; }
    .ak-panel { position: sticky; top: 12px; }
    .ak-p-empty { text-align: center; color: #b7a595; padding: 50px 24px; }
    .ak-p-empty .ic { width: 74px; height: 74px; border-radius: 22px; background: #F6ECDF; color: #B99A7C; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; }
    .ak-p-body { padding: 18px; }
    .ak-p-head { display: flex; align-items: center; gap: 11px; margin-bottom: 6px; }
    .ak-p-head .ico { width: 40px; height: 40px; border-radius: 11px; background: #F1E9DF; color: #5A3A22; display: flex; align-items: center; justify-content: center; flex: none; }
    .ak-p-head .nm { font-size: 17px; font-weight: 800; color: #3B1500; }
    .ak-p-head .lnk { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; color: #9A7256; }
    .ak-p-badges { display: flex; gap: 6px; flex-wrap: wrap; margin: 8px 0 14px; }
    .ak-h6 { font-size: 11px; color: #7A5B43; text-transform: uppercase; letter-spacing: .5px; font-weight: 800; margin: 0 0 10px; }
    .ak-chips { display: flex; flex-wrap: wrap; gap: 7px; }
    .ak-chip { display: inline-flex; align-items: center; gap: 6px; background: #F1E9DF; color: #6b4a2f; border-radius: 8px; padding: 5px 9px 5px 11px; font-size: 12.5px; font-weight: 700; }
    .ak-chip button { border: 0; background: none; color: #b08a6a; cursor: pointer; font-size: 14px; line-height: 1; padding: 0; }
    .ak-chip button:hover { color: #C0392B; }
    .ak-p-empty2 { color: #b7a595; font-size: 12.5px; font-style: italic; }
    .ak-grantrow { display: flex; gap: 8px; margin-top: 14px; }
    .ak-grantrow select { flex: 1; border: 1px solid #E7D3BE; border-radius: 10px; padding: 9px 11px; font-family: inherit; font-size: 13px; color: #3B1500; background: #fff; }
    .ak-grantrow button { background: #2F8E55; color: #fff; border: 0; border-radius: 10px; padding: 9px 16px; font-weight: 800; font-size: 13px; cursor: pointer; }
    .ak-lbl { display: block; font-size: 11px; color: #9A7256; font-weight: 600; margin: 8px 0 4px; }
    .ak-input { width: 100%; border: 1px solid #E7D3BE; border-radius: 10px; padding: 9px 11px; font-size: 13px; font-family: inherit; color: #3B1500; }
    .ak-btn-save { background: #2F8E55; color: #fff; border: 0; border-radius: 10px; padding: 9px 16px; font-weight: 800; font-size: 13px; cursor: pointer; }
    .ak-btn-cancel { background: #fff; color: #7A5B43; border: 1px solid #E7D3BE; border-radius: 10px; padding: 9px 14px; font-weight: 700; font-size: 13px; cursor: pointer; }
    .ak-footer { display: flex; gap: 8px; margin-top: 16px; border-top: 1px solid #F4EADD; padding-top: 14px; }
    .ak-btn-ubah { flex: 1; background: #fff; color: #461500; border: 1.5px solid #E7D3BE; border-radius: 11px; padding: 10px; font-weight: 700; font-size: 13px; cursor: pointer; }
    .ak-btn-hapus { flex: none; background: #fff; color: #C0392B; border: 1.5px solid #f0c4c4; border-radius: 11px; padding: 10px 18px; font-weight: 700; font-size: 13px; cursor: pointer; }
    /* dialog guardrail (amber) */
    .ak-dscrim { position: fixed; inset: 0; background: rgba(30,12,0,.4); z-index: 1090; display: none; align-items: center; justify-content: center; padding: 20px; }
    .ak-dscrim.on { display: flex; }
    .ak-dialog { width: 440px; max-width: 100%; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(30,12,0,.4); }
    .ak-dhead { display: flex; align-items: center; gap: 12px; padding: 18px 20px 6px; }
    .ak-dhead .di { width: 42px; height: 42px; border-radius: 11px; background: #FBEFD9; color: #B5701A; display: flex; align-items: center; justify-content: center; flex: none; }
    .ak-dhead h3 { margin: 0; font-size: 17px; font-weight: 800; color: #3B1500; }
    .ak-dbody { padding: 4px 20px 20px; }
    .ak-dbody .impact { color: #5A3A22; font-size: 13.5px; margin: 0 0 14px; }
    .ak-lose { background: #FBEFD9; border: 1px solid #f0dca8; border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
    .ak-lose .h { font-size: 10.5px; font-weight: 800; letter-spacing: .4px; color: #9A6614; text-transform: uppercase; margin-bottom: 8px; }
    .ak-lose .chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .ak-lose .chips span { background: #fff; color: #9A6614; border: 1px solid #f0dca8; border-radius: 8px; padding: 3px 9px; font-size: 12px; font-weight: 700; }
    .ak-dbtns { display: flex; gap: 10px; }
    .ak-dbtns .batal { flex: 1; background: #fff; color: #7A5B43; border: 1px solid #E7D3BE; border-radius: 11px; padding: 12px; font-weight: 700; font-size: 13.5px; cursor: pointer; }
    .ak-dbtns .hapus { flex: 1; background: #B5701A; color: #fff; border: 0; border-radius: 11px; padding: 12px; font-weight: 800; font-size: 13.5px; cursor: pointer; }
    .ak-addbtn { background: rgba(255,255,255,.14); color: #fff; border: 0; border-radius: 9px; padding: 6px 13px; font-weight: 700; font-size: 12px; cursor: pointer; }
    /* drawer tambah node */
    .ak-adscrim { position: fixed; inset: 0; background: rgba(40,12,0,.32); z-index: 1080; opacity: 0; pointer-events: none; transition: opacity .18s; }
    .ak-adscrim.on { opacity: 1; pointer-events: auto; }
    .ak-drawer { position: fixed; top: 0; right: 0; height: 100vh; width: 480px; max-width: 94vw; background: #FBF5EE; z-index: 1081; box-shadow: -12px 0 40px rgba(40,12,0,.28); transform: translateX(100%); transition: transform .26s cubic-bezier(.2,.8,.2,1); }
    .ak-drawer.on { transform: none; }
    .ak-drawer form { display: flex; flex-direction: column; height: 100%; }
    .ak-dh2 { background: linear-gradient(120deg,#2C2C29,#3A3A36); color: #fff; padding: 18px 22px; display: flex; align-items: center; gap: 12px; }
    .ak-dh2 .k { font-size: 10.5px; font-weight: 800; letter-spacing: .5px; opacity: .8; }
    .ak-dh2 h3 { margin: 2px 0 0; font-size: 18px; font-weight: 800; }
    .ak-dh2 .x { margin-left: auto; width: 34px; height: 34px; border-radius: 50%; border: 0; background: rgba(255,255,255,.2); color: #fff; font-size: 18px; cursor: pointer; }
    .ak-db2 { flex: 1; overflow-y: auto; padding: 18px 22px; }
    .ak-db2 select, .ak-db2 input[type=text] { width: 100%; border: 1px solid #E7D3BE; border-radius: 10px; padding: 10px 12px; font-size: 13.5px; font-family: inherit; color: #3B1500; background: #fff; }
    .ak-warn2 { display: flex; align-items: center; gap: 7px; background: #FBEFD9; border: 1px solid #f0dca8; color: #9A6614; border-radius: 10px; padding: 9px 12px; font-size: 12px; margin-top: 8px; }
    .ak-warn2.red { background: #FBE3DE; border-color: #f0c4c4; color: #C0392B; }
    .ak-chipwrap { display: flex; flex-wrap: wrap; gap: 7px; }
    .ak-chiptoggle { display: inline-flex; align-items: center; border: 1.5px solid #E7D3BE; background: #fff; color: #6b4a2f; border-radius: 8px; padding: 6px 11px; font-size: 12.5px; font-weight: 700; cursor: pointer; }
    .ak-chiptoggle:has(input:checked) { background: #461500; border-color: #461500; color: #fff; }
    .ak-dfoot { padding: 14px 22px; border-top: 1px solid #EDDFCE; background: #fff; }
    .ak-dfoot button { width: 100%; background: linear-gradient(135deg,#3A3A36,#242422); color: #fff; border: 0; border-radius: 12px; padding: 13px; font-weight: 800; font-size: 14px; cursor: pointer; }
    .ak-hidden { display: none; }
</style>
@endsection

@section('content')
<div class="ak">
    @include('layouts.notif')

    <div class="ak-top">
        <div>
            <h1>Akses</h1>
            <div class="sub">Pusat Akun · Pohon menu &amp; hak akses — area paling rawan, edit dengan hati-hati</div>
        </div>
        <div class="ak-rebuild">Rebuild terakhir <strong>{{ $lastRebuild }}</strong></div>
    </div>

    @if($pending > 0)
        <div class="ak-banner">
            <span class="bi"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 12a8 8 0 0 1 13.7-5.6L20 8M20 4v4h-4M20 12a8 8 0 0 1-13.7 5.6L4 16M4 20v-4h4"/></svg></span>
            <div>
                <b>Perubahan belum berlaku</b>
                <p>{{ $pending }} perubahan grant/cabut belum di-rebuild — belum aktif untuk user sampai diterapkan.</p>
            </div>
            <form method="POST" action="{{ route('setaccess.rebuild') }}">@csrf
                <button type="submit">Terapkan sekarang (rebuild)</button>
            </form>
        </div>
    @endif

    <div class="ak-legend">
        <span style="font-weight:700;color:#7A5B43">Penanda:</span>
        <span class="lg"><span class="ak-badge yatim">tak dipakai</span> node yatim (tak di-grant)</span>
        <span class="lg"><span class="ak-badge dobel">link dobel</span> link sama terdaftar ≥2×</span>
        <span class="lg"><span class="ak-badge parent">parent tak di-grant</span> gugur dari menu</span>
    </div>

    <div class="ak-grid">
        <div class="ak-card">
            <div class="ak-cardhead">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M8 12h12M12 18h8"/></svg>
                Pohon Node Akses <span style="font-weight:700;opacity:.8">· {{ count($flat) }} node</span>
                <button type="button" class="ak-addbtn" onclick="akAdd(true)" style="margin-left:auto">+ Node baru</button>
            </div>
            <div class="ak-tree">
                @foreach($flat as $n)
                    <div class="ak-node" data-id="{{ $n['id'] }}" onclick="akSelect('{{ $n['id'] }}')" style="padding-left:{{ 16 + $n['depth'] * 22 }}px">
                        <span class="ico"><i class="{{ $n['icon'] ?: 'dripicons-document' }}"></i></span>
                        <span>
                            <span class="nm">{{ $n['nama'] }}</span>
                            @if($n['link'])<span class="lnk">/{{ $n['link'] }}</span>@endif
                            @if($n['yatim'])<span class="ak-badge yatim">tak dipakai</span>@endif
                            @if($n['dobel'])<span class="ak-badge dobel">link dobel</span>@endif
                            @if($n['parent_ungranted'])<span class="ak-badge parent">parent tak di-grant</span>@endif
                        </span>
                        <span class="rc {{ count($n['roles']) === 0 ? 'zero' : '' }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.2"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M16 6.5a3 3 0 0 1 0 6M21 19a5 5 0 0 0-3.5-4.8"/></svg>
                            {{ count($n['roles']) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="ak-panel">
            <div class="ak-card">
                <div id="ak-p-empty" class="ak-p-empty">
                    <div class="ic"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M8 12h12M12 18h8"/></svg></div>
                    <b style="color:#461500">Pilih node</b>
                    <div style="font-size:12.5px;margin-top:4px">Klik node di pohon kiri untuk lihat &amp; atur role yang punya akses.</div>
                </div>
                <div id="ak-p-isi" class="ak-p-body ak-hidden">
                    <div class="ak-p-head">
                        <span class="ico"><i id="ak-p-ico"></i></span>
                        <div>
                            <div class="nm" id="ak-p-nm"></div>
                            <div class="lnk" id="ak-p-lnk"></div>
                        </div>
                    </div>
                    <div class="ak-p-badges" id="ak-p-badges"></div>
                    <div class="ak-h6">Role yang punya akses</div>
                    <div id="ak-p-roles"></div>

                    {{-- grant ke role --}}
                    <form id="ak-grant-form" method="POST" action="">
                        @csrf
                        <div class="ak-h6" style="margin-top:16px">Tambah akses</div>
                        <div class="ak-grantrow">
                            <select name="group_id" id="ak-grant-sel" required></select>
                            <button type="submit">Grant</button>
                        </div>
                    </form>

                    {{-- form ubah node (inline, tersembunyi) --}}
                    <form id="ak-edit-form" method="POST" action="" class="ak-hidden" style="margin-top:16px;border-top:1px solid #F4EADD;padding-top:14px">
                        @csrf
                        <div class="ak-h6">Ubah node</div>
                        <label class="ak-lbl">Nama</label>
                        <input type="text" name="nama" id="ak-edit-nama" class="ak-input" required>
                        <label class="ak-lbl">Link (segment URL, kosongkan untuk node induk)</label>
                        <input type="text" name="link" id="ak-edit-link" class="ak-input">
                        <label class="ak-lbl">Ikon (class FA5/dripicons)</label>
                        <input type="text" name="icon" id="ak-edit-icon" class="ak-input">
                        <div style="display:flex;gap:8px;margin-top:6px">
                            <button type="submit" class="ak-btn-save">Simpan perubahan</button>
                            <button type="button" class="ak-btn-cancel" onclick="akEdit(false)">Batal</button>
                        </div>
                    </form>

                    {{-- footer aksi --}}
                    <div class="ak-footer">
                        <button type="button" class="ak-btn-ubah" onclick="akEdit(true)">Ubah node</button>
                        <button type="button" class="ak-btn-hapus" onclick="akHapus()">Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- drawer tambah node --}}
<div id="ak-adscrim" class="ak-adscrim" onclick="akAdd(false)"></div>
<div id="ak-drawer" class="ak-drawer">
    <form method="POST" action="{{ route('setaccess.store-node') }}">
        @csrf
        <div class="ak-dh2">
            <div><div class="k">PUSAT AKUN · AKSES</div><h3>Node baru</h3></div>
            <button type="button" class="x" onclick="akAdd(false)">&times;</button>
        </div>
        <div class="ak-db2">
            <div class="ak-h6" style="margin-top:0">Induk (parent)</div>
            <select name="pid" id="ak-add-pid" onchange="akAddParentWarn()">
                <option value="">— tanpa induk (node paling atas) —</option>
                @foreach($flat as $n)
                    <option value="{{ $n['id'] }}">{{ str_repeat('— ', $n['depth']) }}{{ $n['nama'] }}</option>
                @endforeach
            </select>
            <div id="ak-add-parentwarn" class="ak-warn2 red ak-hidden">Induk belum di-grant ke role mana pun — node ini akan gugur dari menu sampai induknya di-grant.</div>

            <div class="ak-h6" style="margin-top:16px">Nama <span style="color:#C0392B">*</span></div>
            <input type="text" name="nama" required placeholder="mis. Laporan Bulanan">

            <div class="ak-h6" style="margin-top:16px">Link (segment URL) — kosongkan untuk node induk</div>
            <input type="text" name="link" id="ak-add-link" onkeyup="akAddLinkWarn()" placeholder="mis. laporan-bulanan">
            <div id="ak-add-linkwarn" class="ak-warn2 ak-hidden">Link ini sudah dipakai node lain — akan jadi "link dobel".</div>

            <div class="ak-h6" style="margin-top:16px">Ikon (class FontAwesome 5 / dripicons)</div>
            <input type="text" name="icon" placeholder="dripicons-document">

            <div class="ak-h6" style="margin-top:16px">Grant awal ke role (opsional)</div>
            <div class="ak-chipwrap">
                @foreach($allRoles as $r)
                    <label class="ak-chiptoggle"><input type="checkbox" name="roles[]" value="{{ $r->id }}" hidden>{{ $r->nama }}</label>
                @endforeach
            </div>
        </div>
        <div class="ak-dfoot"><button type="submit">Simpan node</button></div>
    </form>
</div>

{{-- dialog guardrail hapus node --}}
<div id="ak-dscrim" class="ak-dscrim">
    <div class="ak-dialog">
        <div class="ak-dhead">
            <span class="di"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/></svg></span>
            <h3 id="ak-d-judul">Hapus node?</h3>
        </div>
        <div class="ak-dbody">
            <p class="impact" id="ak-d-impact"></p>
            <div class="ak-lose" id="ak-d-lose">
                <div class="h">Role yang akan kehilangan menu ini</div>
                <div class="chips" id="ak-d-chips"></div>
            </div>
            <div class="ak-dbtns">
                <button type="button" class="batal" onclick="akDialog(false)">Batal</button>
                <form id="ak-del-form" method="POST" action="" style="flex:1">@csrf
                    <button type="submit" class="hapus" style="width:100%">Ya, hapus node</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    var AK = {!! collect($flat)->map(fn ($n) => [
        'id' => $n['id'], 'nama' => $n['nama'], 'link' => $n['link'], 'icon' => $n['icon'] ?: 'dripicons-document',
        'roles' => array_map(fn ($r) => ['id' => $r['id'], 'nama' => $r['nama']], $n['roles']),
        'yatim' => $n['yatim'], 'dobel' => $n['dobel'], 'parent' => $n['parent_ungranted'],
        'anak' => $n['punyaAnak'],
    ])->keyBy('id')->toJson() !!};
    var AK_CUR = null;
    var AK_ROLES = {!! $allRoles->map(fn ($r) => ['id' => $r->id, 'nama' => $r->nama])->toJson() !!};
    var AK_CSRF = document.querySelector('meta[name="csrf-token"]').content;
    var AK_BASE = "{{ url('setaccess') }}";
    var AK_SEL  = @json(session('ak_sel'));

    function akSelect(id) {
        var n = AK[id]; if (!n) return;
        AK_CUR = id;
        akEdit(false);
        document.querySelectorAll('.ak-node').forEach(function (el) { el.classList.toggle('sel', el.dataset.id === id); });
        document.getElementById('ak-p-empty').classList.add('ak-hidden');
        document.getElementById('ak-p-isi').classList.remove('ak-hidden');
        document.getElementById('ak-p-ico').className = n.icon;
        document.getElementById('ak-p-nm').textContent = n.nama;
        document.getElementById('ak-p-lnk').textContent = n.link ? '/' + n.link : '(tanpa link · node induk)';

        var b = '';
        if (n.yatim)  b += '<span class="ak-badge yatim">tak dipakai</span>';
        if (n.dobel)  b += '<span class="ak-badge dobel">link dobel</span>';
        if (n.parent) b += '<span class="ak-badge parent">parent tak di-grant</span>';
        document.getElementById('ak-p-badges').innerHTML = b;

        // chips role + tombol cabut
        var r = document.getElementById('ak-p-roles');
        if (n.roles.length === 0) {
            r.innerHTML = '<div class="ak-p-empty2">Node yatim — belum di-grant ke role mana pun.</div>';
        } else {
            var wrap = document.createElement('div'); wrap.className = 'ak-chips';
            n.roles.forEach(function (role) {
                var chip = document.createElement('span'); chip.className = 'ak-chip';
                var nm = document.createElement('span'); nm.textContent = role.nama; chip.appendChild(nm);
                var f = document.createElement('form'); f.method = 'POST'; f.style.display = 'inline';
                f.action = AK_BASE + '/' + id + '/revoke/' + role.id;
                f.onsubmit = function () { return confirm('Cabut akses "' + n.nama + '" dari role ' + role.nama + '?'); };
                f.innerHTML = '<input type="hidden" name="_token" value="' + AK_CSRF + '">';
                var x = document.createElement('button'); x.type = 'submit'; x.textContent = '×'; x.title = 'Cabut'; f.appendChild(x);
                chip.appendChild(f); wrap.appendChild(chip);
            });
            r.innerHTML = ''; r.appendChild(wrap);
        }

        // dropdown grant: role yang BELUM punya akses
        var punya = {}; n.roles.forEach(function (x) { punya[x.id] = 1; });
        var sel = document.getElementById('ak-grant-sel');
        sel.innerHTML = '<option value="">— pilih role —</option>';
        AK_ROLES.forEach(function (role) {
            if (!punya[role.id]) { var o = document.createElement('option'); o.value = role.id; o.textContent = role.nama; sel.appendChild(o); }
        });
        document.getElementById('ak-grant-form').action = AK_BASE + '/' + id + '/grant';

        // form ubah node
        var ef = document.getElementById('ak-edit-form');
        ef.action = AK_BASE + '/' + id + '/update-node';
        document.getElementById('ak-edit-nama').value = n.nama;
        document.getElementById('ak-edit-link').value = n.link || '';
        document.getElementById('ak-edit-icon').value = n.icon || '';
    }

    // toggle form ubah node
    function akEdit(show) {
        document.getElementById('ak-edit-form').classList.toggle('ak-hidden', !show);
    }

    // hapus node — logika berlapis (guardrail)
    function akHapus() {
        var n = AK[AK_CUR]; if (!n) return;
        if (n.anak) {
            alert('Node "' + n.nama + '" masih punya sub-node. Hapus atau pindahkan anaknya dulu.');
            return;
        }
        document.getElementById('ak-del-form').action = AK_BASE + '/' + AK_CUR + '/delete-node';
        if (n.roles.length > 0) {
            // ber-grant → dialog guardrail, sebut role yang kehilangan
            document.getElementById('ak-d-judul').textContent = 'Hapus node "' + n.nama + '"?';
            document.getElementById('ak-d-impact').textContent = 'Menu ini akan hilang untuk role di bawah. Perubahan berlaku setelah di-rebuild.';
            document.getElementById('ak-d-lose').style.display = '';
            document.getElementById('ak-d-chips').innerHTML = n.roles.map(function (r) {
                var s = document.createElement('span'); s.textContent = r.nama; return s.outerHTML;
            }).join('');
            akDialog(true);
        } else {
            // tak ber-grant & tak beranak → konfirmasi ringan
            document.getElementById('ak-d-judul').textContent = 'Hapus node "' + n.nama + '"?';
            document.getElementById('ak-d-impact').textContent = 'Node ini tidak di-grant ke role mana pun, aman dihapus.';
            document.getElementById('ak-d-lose').style.display = 'none';
            akDialog(true);
        }
    }
    function akDialog(show) { document.getElementById('ak-dscrim').classList.toggle('on', show); }

    // drawer tambah node
    function akAdd(show) {
        document.getElementById('ak-drawer').classList.toggle('on', show);
        document.getElementById('ak-adscrim').classList.toggle('on', show);
    }
    // peringatan live: link dobel
    function akAddLinkWarn() {
        var v = document.getElementById('ak-add-link').value.trim().toLowerCase();
        var dobel = false;
        for (var k in AK) if (AK[k].link && AK[k].link.toLowerCase() === v && v) { dobel = true; break; }
        document.getElementById('ak-add-linkwarn').classList.toggle('ak-hidden', !dobel);
    }
    // peringatan live: induk belum di-grant
    function akAddParentWarn() {
        var pid = document.getElementById('ak-add-pid').value;
        var warn = pid && AK[pid] && AK[pid].roles.length === 0;
        document.getElementById('ak-add-parentwarn').classList.toggle('ak-hidden', !warn);
    }

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { akDialog(false); akAdd(false); } });

    // auto-buka node terpilih setelah grant/cabut/ubah
    if (AK_SEL && AK[AK_SEL]) akSelect(AK_SEL);
</script>
@endsection
