@extends('layouts.app')

@section('css')
{{-- Font Awesome 6 (full) — supaya icon brands (Instagram/TikTok) render, sama seperti EP Konten --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Konten Pillar</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ───────── TAB BAR ───────── --}}
    @php $activeTab = $activeTab ?? 'pillar'; @endphp
    <div class="gp-tabs mb-3">
        <button type="button" class="gp-tab {{ $activeTab === 'pillar' ? 'is-active' : '' }}" data-tab="pillar">
            <i class="fa fa-layer-group"></i> Pillar
        </button>
        <button type="button" class="gp-tab {{ $activeTab === 'gp-doctor' ? 'is-active' : '' }}" data-tab="gp-doctor">
            <i class="fa fa-user-md"></i> Dokter Branding GP
            <span class="st-pill st-pill--neutral ml-1">{{ $gpDoctors->count() }}</span>
        </button>
    </div>

    {{-- ═════════ PANE: PILLAR ═════════ --}}
    <div class="gp-pane {{ $activeTab === 'pillar' ? 'is-active' : '' }}" data-pane="pillar">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:12px;">
            <div class="text-muted" style="font-size:13px;">
                <span class="ml-1 st-pill st-pill--success">Aktif: {{ $totalActive }}</span>
                <span class="ml-2">·</span>
                <span class="ml-2 st-pill st-pill--neutral">Nonaktif: {{ $totalInactive }}</span>
            </div>
            <a href="{{ route('kontenpillar.new') }}" class="st-btn-brand">
                <i class="fa fa-plus"></i> Tambah Pillar
            </a>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="st-card mb-3">
                    <div class="st-card__body">
                        <form method="GET" class="row g-2">
                            <div class="col-md-6">
                                <label class="text-muted" style="font-size:12px;">Cari</label>
                                <input type="text" name="search" class="form-control" placeholder="Nama / slug pillar..." value="{{ $search }}">
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted" style="font-size:12px;">Status</label>
                                <select name="status" class="form-control">
                                    <option value="all"      {{ $filterStatus === 'all' ? 'selected' : '' }}>Semua</option>
                                    <option value="active"   {{ $filterStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="inactive" {{ $filterStatus === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end" style="gap:4px;">
                                <button type="submit" class="st-btn-brand"><i class="fa fa-filter"></i> Filter</button>
                                <a href="{{ route('kontenpillar') }}" class="st-btn-outline-brown"><i class="fa fa-times"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="st-card">
                    <div class="st-card__body">
                        <p class="text-muted" style="font-size:13px;">
                            <i class="fa fa-info-circle"></i>
                            Pillar konten dipakai di EP Bulanan/Mingguan dan Breakdown Konten — bisa multi-pillar per konten.
                            Tambah/hapus pillar di sini supaya tim creative bisa pakai sesuai strategi.
                        </p>

                        <div class="st-table-wrap--scroll">
                            <table class="st-table st-table--clickable">
                                <thead>
                                    <tr>
                                        <th width="4%">#</th>
                                        <th>Nama</th>
                                        <th>Slug</th>
                                        <th>Deskripsi</th>
                                        <th width="10%">Urutan</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pillars as $key => $row)
                                        <tr data-href="{{ route('kontenpillar.edit', $row->id) }}">
                                            <td class="st-num">{{ $pillars->firstItem() + $key }}</td>
                                            <td class="st-primary">
                                                @if($row->color)
                                                    <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $row->color }}; margin-right:6px; vertical-align:middle;"></span>
                                                @endif
                                                {{ $row->nama }}
                                            </td>
                                            <td><code style="background:#f5e6d3; padding:2px 8px; border-radius:4px; font-size:11px; color:#7d4429;">{{ $row->slug }}</code></td>
                                            <td>{{ $row->deskripsi ?? '-' }}</td>
                                            <td class="st-num">{{ $row->sort_order }}</td>
                                            <td>
                                                @if ($row->is_active)
                                                    <span class="st-pill st-pill--success">Aktif</span>
                                                @else
                                                    <span class="st-pill st-pill--neutral">Nonaktif</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if ($pillars->isEmpty())
                                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pillar.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        {{ $pillars->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═════════ PANE: DOKTER BRANDING GP ═════════ --}}
    <div class="gp-pane {{ $activeTab === 'gp-doctor' ? 'is-active' : '' }}" data-pane="gp-doctor">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:12px;">
            <h5 class="mb-0" style="font-weight:800; color:#0a4f4d;">
                <i class="fa fa-user-md" style="color:#0e6e6b;"></i> Dokter Branding GP
                <span class="st-pill st-pill--neutral ml-1">{{ $gpDoctors->count() }}</span>
            </h5>
            <button type="button" class="st-btn-brand" id="gpDoctorAddOpen">
                <i class="fa fa-plus"></i> Tambah Dokter Branding GP
            </button>
        </div>

        <div class="gp-info mb-3">
            <span class="gp-info__icon"><i class="fa fa-info-circle"></i></span>
            <div>
                Daftar dokter yang ikut konten video <strong>Branding GP</strong>. Dokter di sini otomatis muncul
                sebagai pilihan <strong>Dokter GP</strong> saat bikin konten kategori Branding GP di EP Konten.
            </div>
        </div>

        @php
            // Meta platform sosial: ikon FA (brands) + warna brand
            $gpSocialMeta = [
                'instagram' => ['icon' => 'fa-brands fa-instagram', 'color' => '#E4405F'],
                'tiktok'    => ['icon' => 'fa-brands fa-tiktok',    'color' => '#010101'],
                'facebook'  => ['icon' => 'fa-brands fa-facebook',  'color' => '#1877F2'],
            ];
            $gpAvatarColors = ['#8A3A12', '#A8481B', '#2F8E55', '#3C5FA8', '#B5701A', '#7A3415', '#5A6B2E', '#8E2419'];
        @endphp
        <div class="gp-list-card">
            @if ($gpDoctors->isEmpty())
                <div class="gp-list-empty" id="gpDoctorEmpty">
                    Belum ada dokter Branding GP. Klik <strong>Tambah Dokter Branding GP</strong> di atas.
                </div>
            @else
                <table class="gp-list">
                    <thead>
                        <tr>
                            <th style="width:52px;">No</th>
                            <th>Nama</th>
                            <th style="width:200px;">Media Sosial</th>
                            <th style="width:60px;"></th>
                            <th style="width:26px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($gpDoctors as $i => $g)
                            @php
                                $gName   = $g->doctor->name;
                                $initial = mb_strtoupper(mb_substr(preg_replace('/^drg\.?\s*/i', '', trim($gName)) ?: '?', 0, 1));
                                $avColor = $gpAvatarColors[crc32((string) $g->id) % count($gpAvatarColors)];
                            @endphp
                            <tr class="gp-list-row gp-doctor-detail" data-id="{{ $g->id }}"
                                data-name="{{ $gName }}"
                                data-raw="{{ $g->raw_doctor_id }}"
                                data-socials='@json($g->social_media ?? [])'>
                                <td class="gp-list-num">{{ $i + 1 }}</td>
                                <td>
                                    <div class="gp-list-name">
                                        <div class="gp-list-ava" style="background:{{ $avColor }};">
                                            @if ($g->doctor->photo_url)
                                                <img src="{{ $g->doctor->photo_url }}" alt="{{ $gName }}">
                                            @else
                                                {{ $initial }}
                                            @endif
                                        </div>
                                        <div class="gp-list-name__txt">{{ $gName }}</div>
                                    </div>
                                </td>
                                <td>
                                    @if (!empty($g->social_media))
                                        <div class="gp-list-socials">
                                            @foreach ($g->social_media as $s)
                                                @php
                                                    $meta = $gpSocialMeta[$s['platform'] ?? ''] ?? null;
                                                    $url  = \App\Models\RawCustomer::socialProfileUrl($s['platform'] ?? null, $s['username'] ?? null);
                                                @endphp
                                                @if ($meta)
                                                    <a href="{{ $url ?: '#' }}" @if($url) target="_blank" rel="noopener" @endif
                                                       class="gp-social-badge" style="background:{{ $meta['color'] }};"
                                                       title="{{ ucfirst($s['platform']).': @'.$s['username'] }}"
                                                       onclick="event.stopPropagation()">
                                                        <i class="{{ $meta['icon'] }}"></i>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="gp-list-nosos">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button" class="gp-list-del gp-doctor-del"
                                            data-id="{{ $g->id }}" data-name="{{ $gName }}"
                                            title="Hapus dari daftar" onclick="event.stopPropagation()">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                                <td><i class="fa fa-chevron-right gp-list-chev"></i></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>

{{-- ───────── MODAL TAMBAH DOKTER BRANDING GP ───────── --}}
<div class="modal fade" id="gpDoctorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="gpDoctorAddForm">
            <div class="modal-content" style="border-radius:14px; border:1px solid #c4ebe9;">
                <div class="modal-header" style="background:linear-gradient(135deg,#f4faf9,#e6f5f4); border-radius:14px 14px 0 0; border-bottom:1px solid #c4ebe9;">
                    <h5 class="modal-title" style="color:#0e6e6b; font-weight:800;">
                        <i class="fa fa-user-md mr-2"></i> Tambah Dokter Branding GP
                    </h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Dokter</label>
                        <select id="gpDoctorSelect" name="raw_doctor_id" class="form-control" style="width:100%;">
                            <option value="">— Pilih dokter —</option>
                            @foreach ($availableDoctors as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Media sosial (repeatable: platform + username) — pola sama dengan detail pasien --}}
                    <div class="gp-social form-group mb-0">
                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; color:#0a4f4d;">Media Sosial <span class="text-muted" style="text-transform:none; letter-spacing:0;">(opsional)</span></label>
                        <div class="gp-social__list" id="gpSocialList"></div>
                        <button type="button" class="gp-social__add" id="gpSocialAdd"><i class="fa fa-plus"></i> Tambah akun</button>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #c4ebe9;">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand" id="gpDoctorAddBtn">
                        <i class="fa fa-plus"></i> Tambah ke Branding GP
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="gpSocialTpl">
    <div class="gp-social__row" data-social-row>
        <select class="form-control gp-social__platform" name="social_media[__IDX__][platform]">
            <option value="">— Platform —</option>
            <option value="instagram">Instagram</option>
            <option value="tiktok">TikTok</option>
            <option value="facebook">Facebook</option>
        </select>
        <input class="form-control gp-social__username" type="text" name="social_media[__IDX__][username]" placeholder="username (tanpa @)">
        <button type="button" class="gp-social__del" data-social-del title="Hapus akun"><i class="fa fa-trash"></i></button>
    </div>
</template>

{{-- ───────── MODAL DETAIL DOKTER ───────── --}}
<div class="modal fade" id="gpDoctorDetailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content gpd-modal">
            <div class="modal-header gpd-head">
                <div class="gpd-head-left">
                    <span class="gpd-ava"><i class="fa fa-user-md"></i></span>
                    <span class="gpd-title" id="gpDetailName">Detail Dokter</span>
                </div>
                <button type="button" class="close gpd-close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body gpd-body">
                <form id="gpDetailForm">
                    <div class="gp-social">
                        <div class="gpd-section-label">Media Sosial</div>
                        <div class="gp-social__list" id="gpDetailSocialList"></div>
                        <div class="gpd-empty-hint" id="gpDetailEmptyHint">Belum ada akun sosial media.</div>
                        <button type="button" class="gp-social__add" id="gpDetailSocialAdd"><i class="fa fa-plus"></i> Tambah akun</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer gpd-foot">
                <button type="button" class="gpd-del-link" id="gpDetailDelBtn">
                    <i class="fa fa-trash"></i> Hapus dari daftar
                </button>
                <div class="gpd-foot-right">
                    <a href="#" id="gpDetailEpLink" class="st-btn-outline-brown gpd-btn">
                        <i class="fa fa-calendar-alt"></i> EP Konten
                    </a>
                    <button type="button" class="st-btn-brand gpd-btn" id="gpDetailSaveBtn">
                        <i class="fa fa-save"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .gp-tabs { display:flex; gap:6px; border-bottom:2px solid #e6f5f4; }
    .gp-tab {
        display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border:none; background:transparent;
        font-size:14px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:3px solid transparent;
        margin-bottom:-2px; transition:all .15s;
    }
    .gp-tab:hover { color:#0e6e6b; }
    .gp-tab.is-active { color:#0e6e6b; border-bottom-color:#0e6e6b; }
    .gp-pane { display:none; }
    .gp-pane.is-active { display:block; }

    /* Info callout */
    .gp-info {
        display:flex; align-items:flex-start; gap:12px; padding:14px 16px;
        background:linear-gradient(135deg,#f4faf9,#e9f6f4); border:1px solid #cfeae7;
        border-left:4px solid #0e6e6b; border-radius:12px; color:#0a4f4d; font-size:13.5px; font-weight:600; line-height:1.55;
    }
    .gp-info__icon {
        flex:0 0 auto; width:30px; height:30px; border-radius:50%; background:#0e6e6b; color:#fff;
        display:inline-flex; align-items:center; justify-content:center; font-size:14px; margin-top:1px;
    }
    .gp-info strong { color:#0a4f4d; }

    /* List dokter GP */
    .gp-list-card { background:#fff; border:1px solid #e6ecec; border-radius:14px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.04); }
    .gp-list { width:100%; border-collapse:collapse; }
    .gp-list thead th {
        text-align:left; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase;
        color:#eafaf8; padding:13px 16px; border-bottom:none;
        background:linear-gradient(180deg, #13928e 0%, #0a4f4d 100%);
    }
    .gp-list tbody td { padding:12px 16px; border-bottom:1px solid #f1f5f5; vertical-align:middle; }
    .gp-list tbody tr:last-child td { border-bottom:none; }
    .gp-list-row { cursor:pointer; transition:background .12s; }
    .gp-list-row:hover { background:#f7fbfb; }
    .gp-list-num { color:#9db1af; font-weight:600; }
    .gp-list-name { display:flex; align-items:center; gap:12px; min-width:0; }
    .gp-list-ava {
        flex:0 0 auto; width:40px; height:40px; border-radius:50%; overflow:hidden; color:#fff;
        font-weight:700; font-size:15px; display:inline-flex; align-items:center; justify-content:center;
    }
    .gp-list-ava img { width:100%; height:100%; object-fit:cover; }
    .gp-list-name__txt { font-weight:700; color:#0a4f4d; font-size:14px; }
    .gp-list-socials { display:flex; gap:6px; flex-wrap:wrap; }
    .gp-list-nosos { color:#c2d0cf; }
    .gp-list-del {
        width:32px; height:32px; padding:0; border:1px solid #ecdcd4; background:#fff; color:#b45c34;
        border-radius:8px; font-size:12px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:.15s;
    }
    .gp-list-del:hover { background:#fbeee7; border-color:#c86b3c; color:#8a3d1e; }
    .gp-list-chev { color:#c2d0cf; font-size:13px; }
    .gp-list-empty { text-align:center; color:#8aa3a1; padding:34px 16px; font-size:13.5px; }

    /* Logo sosial (badge bulat) */
    .gp-social-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:13px; text-decoration:none; transition:transform .12s; }
    .gp-social-badge:hover { transform:translateY(-2px); color:#fff; }

    /* Input media sosial di form tambah */
    .gp-social__list { display:flex; flex-direction:column; gap:8px; margin-top:6px; }
    .gp-social__row { display:flex; gap:8px; align-items:center; }
    .gp-social__platform { flex:0 0 150px; max-width:150px; }
    .gp-social__username { flex:1 1 auto; min-width:0; }
    .gp-social__del { flex:0 0 auto; width:40px; height:38px; border:1px solid #e3d6c4; background:#fff; color:#a3572e; border-radius:8px; cursor:pointer; transition:.15s; }
    .gp-social__del:hover { background:#fbebe4; border-color:#c86b3c; }
    .gp-social__add { margin-top:8px; border:1px dashed #9cc6c2; background:#f4faf9; color:#0e6e6b; font-weight:600; font-size:13px; padding:8px 14px; border-radius:9px; cursor:pointer; display:inline-flex; align-items:center; gap:7px; transition:.15s; }
    .gp-social__add:hover { background:#e6f5f4; border-color:#0e6e6b; }
    @media (max-width:560px) { .gp-social__platform { flex-basis:116px; max-width:116px; } }

    /* ── Modal detail dokter ── */
    .gpd-modal { border-radius:16px; border:1px solid #c4ebe9; overflow:hidden; }
    .gpd-head { background:linear-gradient(135deg,#f4faf9,#e6f5f4); border-bottom:1px solid #d3ece9; padding:16px 18px; align-items:center; }
    .gpd-head-left { display:flex; align-items:center; gap:11px; min-width:0; }
    .gpd-ava { flex:0 0 auto; width:36px; height:36px; border-radius:50%; background:#0e6e6b; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:15px; }
    .gpd-title { font-weight:800; color:#0a4f4d; font-size:15.5px; line-height:1.25; }
    .gpd-close { opacity:.55; text-shadow:none; font-size:22px; }
    .gpd-close:hover { opacity:1; }
    .gpd-body { padding:18px; }
    .gpd-section-label { font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.6px; color:#0a4f4d; margin-bottom:8px; }
    .gpd-empty-hint { font-size:12.5px; color:#94a3a1; font-style:italic; padding:2px 0 2px; }
    .gpd-foot { border-top:1px solid #eef4f3; padding:13px 18px; display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:nowrap; }
    .gpd-foot-right { display:flex; gap:8px; }
    .gpd-btn { white-space:nowrap; }
    .gpd-del-link { background:none; border:none; color:#c0392b; font-weight:600; font-size:12.5px; cursor:pointer; padding:6px 2px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap; transition:.15s; }
    .gpd-del-link:hover { color:#8e2318; text-decoration:underline; }
    @media (max-width:520px) {
        .gpd-foot { flex-wrap:wrap; }
        .gpd-foot-right { width:100%; justify-content:flex-end; }
    }
</style>

@endsection

@section('js')
<script>
(function () {
    // ─── Tab switching (client-side, sync ke ?tab= tanpa reload) ───
    const tabs  = document.querySelectorAll('.gp-tabs .gp-tab');
    const panes = document.querySelectorAll('.gp-pane');
    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            const target = tab.dataset.tab;
            tabs.forEach(t => t.classList.toggle('is-active', t.dataset.tab === target));
            panes.forEach(p => p.classList.toggle('is-active', p.dataset.pane === target));
            const url = new URL(window.location);
            url.searchParams.set('tab', target);
            window.history.replaceState({}, '', url);
        });
    });

    // ─── Select2 untuk dropdown tambah dokter (di dalam modal) ───
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('#gpDoctorSelect').select2({
            placeholder: '— Pilih dokter —',
            allowClear: true,
            width: '100%',
            dropdownParent: jQuery('#gpDoctorModal'),
        });
    }

    // ─── Buka modal + reset isi ───
    function resetGpDoctorForm() {
        jQuery('#gpDoctorSelect').val('').trigger('change');
        const list = document.getElementById('gpSocialList');
        if (list) list.innerHTML = '';
    }
    document.getElementById('gpDoctorAddOpen')?.addEventListener('click', function () {
        resetGpDoctorForm();
        jQuery('#gpDoctorModal').modal('show');
    });
    jQuery('#gpDoctorModal').on('hidden.bs.modal', resetGpDoctorForm);

    const ADD_URL   = "{{ route('gpdoctor.store') }}";
    const CSRF      = "{{ csrf_token() }}";
    const DEL_BASE  = "{{ url('konten-pillar/gp-doctor') }}";
    const EP_BASE   = "{{ route('epkonten') }}";

    // ─── Media sosial: helper tambah baris (dipakai modal tambah & detail) ───
    let _socialIdx = 0;
    const socialTpl = document.getElementById('gpSocialTpl');
    function addSocialRow(container, platform, username) {
        const html = socialTpl.innerHTML.replace(/__IDX__/g, _socialIdx++);
        container.insertAdjacentHTML('beforeend', html);
        const row = container.lastElementChild;
        if (platform) row.querySelector('.gp-social__platform').value = platform;
        if (username) row.querySelector('.gp-social__username').value = username;
        return row;
    }
    function updateDetailHint() {
        const list = document.getElementById('gpDetailSocialList');
        const hint = document.getElementById('gpDetailEmptyHint');
        if (list && hint) hint.style.display = list.children.length ? 'none' : '';
    }
    document.getElementById('gpSocialAdd')?.addEventListener('click', function () {
        addSocialRow(document.getElementById('gpSocialList'));
    });
    document.getElementById('gpDetailSocialAdd')?.addEventListener('click', function () {
        addSocialRow(document.getElementById('gpDetailSocialList'));
        updateDetailHint();
    });
    // Hapus baris (delegasi global — berlaku di kedua modal)
    document.addEventListener('click', function (e) {
        const del = e.target.closest('[data-social-del]');
        if (del) { e.preventDefault(); del.closest('[data-social-row]')?.remove(); updateDetailHint(); }
    });

    // ─── Tambah dokter (AJAX) ───
    const addForm = document.getElementById('gpDoctorAddForm');
    addForm && addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const rawDoctorId = jQuery('#gpDoctorSelect').val();
        if (!rawDoctorId) {
            Swal.fire({ title: 'Pilih dokter dulu', type: 'warning', timer: 1200, showConfirmButton: false });
            return;
        }
        const $btn = jQuery('#gpDoctorAddBtn').prop('disabled', true);
        // Serialize seluruh form (dokter + social_media[]) + token
        const payload = jQuery(addForm).serialize() + '&_token=' + encodeURIComponent(CSRF);
        jQuery.ajax({
            url: ADD_URL, method: 'POST',
            data: payload,
            success: function (res) {
                $btn.prop('disabled', false);
                Swal.fire({ title: 'Berhasil', text: res.message, type: 'success', timer: 1200, showConfirmButton: false });
                // Reload biar dropdown available + grid konsisten
                setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'gp-doctor');
                    window.location.href = url.toString();
                }, 700);
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                const msg = xhr.responseJSON?.message || 'Gagal menambahkan dokter.';
                Swal.fire({ title: 'Gagal', text: msg, type: 'error' });
            }
        });
    });

    // ─── Hapus dokter (AJAX, reusable) ───
    function deleteGpDoctor(id, name, onDone) {
        Swal.fire({
            title: 'Hapus dokter?',
            text: name + ' akan dihapus dari daftar Branding GP. Konten lama tetap aman.',
            type: 'warning', showCancelButton: true,
            confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
        }).then(function (r) {
            if (!r.value) return;
            jQuery.ajax({
                url: DEL_BASE + '/' + id, method: 'POST',
                data: { _method: 'DELETE', _token: CSRF },
                success: function (res) {
                    const row = document.querySelector('.gp-list-row[data-id="' + id + '"]');
                    row && row.remove();
                    Swal.fire({ title: 'Terhapus', text: res.message, type: 'success', timer: 1000, showConfirmButton: false });
                    if (onDone) onDone();
                },
                error: function (xhr) {
                    Swal.fire({ title: 'Gagal', text: xhr.responseJSON?.message || 'Gagal menghapus.', type: 'error' });
                }
            });
        });
    }
    // Trash pojok kartu
    document.querySelectorAll('.gp-doctor-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            deleteGpDoctor(btn.dataset.id, btn.dataset.name);
        });
    });

    // ─── Modal Detail Dokter ───
    let _detailId = null;
    document.querySelectorAll('.gp-doctor-detail').forEach(function (btn) {
        btn.addEventListener('click', function () {
            _detailId = btn.dataset.id;
            const name = btn.dataset.name, raw = btn.dataset.raw;
            let socials = [];
            try { socials = JSON.parse(btn.dataset.socials || '[]') || []; } catch (e) {}

            document.getElementById('gpDetailName').textContent = name;
            document.getElementById('gpDetailEpLink').href =
                EP_BASE + '?platform=gp&gp_doctor=' + encodeURIComponent(raw);

            const list = document.getElementById('gpDetailSocialList');
            list.innerHTML = '';
            socials.forEach(s => addSocialRow(list, s.platform, s.username));
            updateDetailHint();

            jQuery('#gpDoctorDetailModal').modal('show');
        });
    });
    // Simpan perubahan media sosial
    document.getElementById('gpDetailSaveBtn')?.addEventListener('click', function () {
        if (!_detailId) return;
        const $btn = jQuery(this).prop('disabled', true);
        const payload = jQuery('#gpDetailForm').serialize() + '&_method=PUT&_token=' + encodeURIComponent(CSRF);
        jQuery.ajax({
            url: DEL_BASE + '/' + _detailId, method: 'POST', data: payload,
            success: function (res) {
                $btn.prop('disabled', false);
                Swal.fire({ title: 'Tersimpan', text: res.message, type: 'success', timer: 1000, showConfirmButton: false });
                setTimeout(() => {
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'gp-doctor');
                    window.location.href = url.toString();
                }, 700);
            },
            error: function (xhr) {
                $btn.prop('disabled', false);
                Swal.fire({ title: 'Gagal', text: xhr.responseJSON?.message || 'Gagal menyimpan.', type: 'error' });
            }
        });
    });
    // Hapus dari dalam modal detail
    document.getElementById('gpDetailDelBtn')?.addEventListener('click', function () {
        if (!_detailId) return;
        const name = document.getElementById('gpDetailName').textContent;
        deleteGpDoctor(_detailId, name, function () {
            jQuery('#gpDoctorDetailModal').modal('hide');
        });
    });
})();
</script>
@endsection
