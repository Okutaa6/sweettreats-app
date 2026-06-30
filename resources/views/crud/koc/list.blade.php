@extends('layouts.app')

@php
    // Akses dropdown editor Dokter & Perawatan (sementara/dev review) — Super Admin saja.
    // Role lain hanya bisa lihat nama dokter & perawatan tanpa bisa mengubah.
    $isSuperAdmin = session('group_name') === 'Super Admin';
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">KOC Management</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── HEADER (judul + tombol action) ─── --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #c4ebe9; background:#fff;">
                        <div>
                            <h5 style="margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-0.01em; color:#0a4f4d; display:flex; align-items:center; gap:8px;">
                                <i class="fa fa-users" style="font-size:16px; color:#13928e;"></i>
                                Daftar KOC
                            </h5>
                            <small style="color:#5a7572; font-size:13px;">
                                Daftar pasien yang di-approach sebagai partner/affiliate
                                — total <strong style="color:#0a4f4d;">{{ $totalCount }}</strong> KOC
                            </small>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="st-btn-outline-brown"
                                    data-toggle="modal"
                                    data-target="#kocImportCsvModal"
                                    title="Import KOC dari file CSV">
                                <i class="fa fa-upload"></i> Import CSV
                            </button>
                            <a href="{{ route('rawaffiliate.new') }}?type=koc" class="st-btn-brand">
                                <i class="fa fa-plus"></i> Tambah KOC
                            </a>
                        </div>
                    </div>

                    {{-- ─── TAB FILTER (Semua / Confirmed / Sudah Submit Konten) ─── --}}
                    @include('crud.koc._filter_tabs', [
                        'filterTab' => $filterTab,
                        'counts'    => $counts,
                    ])

                    {{-- ─── BRANCH SUMMARY CARDS ─── --}}
                    @include('crud.koc._summary_cards', [
                        'branches'     => $branches,
                        'filterBranch' => $filterBranch,
                        'totalCount'   => $totalCount,
                    ])

                </div> {{-- end .st-card__body (top card) --}}
            </div> {{-- end .st-card (top card) --}}

            {{-- ═══ CARD KEDUA: Filter + Table ═══ --}}
            <div class="st-card mt-3">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── FILTER BAR ─── --}}
                    <form method="GET" action="{{ route('koc') }}" class="st-filter-bar" style="border-bottom:1px solid #c4ebe9; padding:10px 16px;">
                        <input type="hidden" name="tab" value="{{ $filterTab }}">
                        <input type="hidden" name="branch" value="{{ $filterBranch }}">

                        @if($activeBranch)
                            <span class="st-active-branch">
                                <i class="fa fa-store"></i> {{ $activeBranch->name }}
                                <a href="{{ route('koc', array_merge(request()->except(['branch', 'page']), ['branch' => 'all'])) }}" 
                                   style="color:#0a4f4d; text-decoration:none; margin-left:4px;" 
                                   title="Reset cabang">
                                    <i class="fa fa-times-circle"></i>
                                </a>
                            </span>
                        @endif

                        <label>Dokter</label>
                        <select name="doctor" onchange="this.form.submit();">
                            <option value="all" {{ $filterDoctor === 'all' ? 'selected' : '' }}>Semua Dokter</option>
                            @foreach($doctors as $doc)
                                <option value="{{ $doc->id }}" {{ $filterDoctor === $doc->id ? 'selected' : '' }}>
                                    {{ $doc->name }}
                                </option>
                            @endforeach
                        </select>

                        <label>Platform</label>
                        <select name="platform" onchange="this.form.submit();">
                            <option value="all" {{ $filterPlatform === 'all' ? 'selected' : '' }}>Semua Platform</option>
                            @foreach(\App\Models\RawAffiliate::socialPlatforms() as $key => $label)
                                <option value="{{ $key }}" {{ $filterPlatform === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        <label>Cari</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama / username / phone...">

                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>

                        @if($search || $filterDoctor !== 'all' || $filterPlatform !== 'all')
                            <a href="{{ route('koc', ['tab' => $filterTab, 'branch' => $filterBranch]) }}" class="st-btn-outline-brown">
                                <i class="fa fa-undo"></i> Reset
                            </a>
                        @endif

                        <div class="ml-auto st-muted" style="font-size:12px;">
                            Menampilkan <strong class="st-primary">{{ $kocs->count() }}</strong> dari <strong class="st-primary">{{ $kocs->total() }}</strong> KOC
                        </div>
                    </form>

                    {{-- ─── TABLE ─── --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Nama / Username</th>
                                    <th>Cabang</th>
                                    <th>Dokter</th>
                                    <th>Perawatan</th>
                                    <th>Platform</th>
                                    <th>Contact</th>
                                    <th width="14%">Feedback</th>
                                    @if($filterTab !== 'all')
                                        <th width="13%" style="text-align:center;">Confirmed</th>
                                    @endif
                                    <th width="10%" style="text-align:center;">Konten</th>
                                    <th width="10%" style="text-align:center;">Kode Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kocs as $key => $row)
                                    <tr data-href="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}" style="cursor:pointer;">
                                        <td class="st-num">{{ $kocs->firstItem() + $key }}</td>

                                        {{-- Nama / Username --}}
                                        <td>
                                            <div class="st-primary" style="font-weight:500;">{{ $row->name ?: '—' }}</div>
                                            @php
                                                $username = null;
                                                $platformLabel = null;
                                                if ($row->social_tiktok) {
                                                    $username = '@' . $row->social_tiktok;
                                                    $platformLabel = 'TikTok';
                                                } elseif ($row->social_instagram) {
                                                    $username = '@' . $row->social_instagram;
                                                    $platformLabel = 'Instagram';
                                                } elseif ($row->social_username) {
                                                    $username = '@' . $row->social_username;
                                                }
                                            @endphp
                                            @if($username)
                                                <small style="color:#7a9290; font-size:11px;">
                                                    {{ $username }}
                                                    @if($platformLabel) · {{ $platformLabel }} @endif
                                                </small>
                                            @endif
                                        </td>

                                        {{-- Cabang --}}
                                        <td>
                                            @if($row->branch)
                                                @php $branchSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $row->branch->name)); @endphp
                                                <span class="st-pill st-pill--branch-{{ $branchSlug }}" 
                                                      style="border:1px solid;">
                                                    {{ $row->branch->name }}
                                                </span>
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Dokter — Super Admin: dropdown editor (sementara). Role lain: read-only nama --}}
                                        @if($isSuperAdmin)
                                            <td onclick="event.stopPropagation();">
                                                <select class="koc-doctor-select"
                                                        data-id="{{ $row->id }}"
                                                        data-original="{{ $row->raw_doctor_id ?? '' }}"
                                                        onchange="changeKocDoctor(this)"
                                                        onclick="event.stopPropagation();">
                                                    <option value="" {{ !$row->raw_doctor_id ? 'selected' : '' }}>— Belum di-set —</option>
                                                    @foreach($doctors as $doc)
                                                        <option value="{{ $doc->id }}" {{ $row->raw_doctor_id === $doc->id ? 'selected' : '' }}>
                                                            {{ $doc->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @else
                                            <td>
                                                @if($row->doctor)
                                                    {{ $row->doctor->name }}
                                                @else
                                                    <span style="color:#bbb; font-size:11px;">—</span>
                                                @endif
                                            </td>
                                        @endif

                                        {{-- Perawatan — sumber: RawAffiliate::behelTypes(). Super Admin: dropdown editor. Role lain: pill teks --}}
                                        @if($isSuperAdmin)
                                            <td onclick="event.stopPropagation();">
                                                <select class="koc-behel-select"
                                                        data-id="{{ $row->id }}"
                                                        data-original="{{ $row->behel_type ?? '' }}"
                                                        onchange="changeKocBehelType(this)"
                                                        onclick="event.stopPropagation();">
                                                    <option value="" {{ !$row->behel_type ? 'selected' : '' }}>— Belum di-set —</option>
                                                    @foreach(\App\Models\RawAffiliate::behelTypes() as $key => $label)
                                                        <option value="{{ $key }}" {{ $row->behel_type === $key ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        @else
                                            <td>
                                                @if($row->behel_type)
                                                    <span class="st-pill st-pill--info">
                                                        {{ \App\Models\RawAffiliate::behelTypes()[$row->behel_type] ?? $row->behel_type }}
                                                    </span>
                                                @else
                                                    <span style="color:#bbb; font-size:11px;">—</span>
                                                @endif
                                            </td>
                                        @endif

                                        {{-- Platform — pakai social_platform kalau ada, fallback ke kolom social_<platform> --}}
                                        <td>
                                            @php
                                                $platforms = [];
                                                if ($row->social_platform) {
                                                    $platforms[] = \App\Models\RawAffiliate::socialPlatforms()[$row->social_platform] ?? $row->social_platform;
                                                } else {
                                                    if ($row->social_instagram) $platforms[] = 'Instagram';
                                                    if ($row->social_tiktok)    $platforms[] = 'TikTok';
                                                    if ($row->social_youtube)   $platforms[] = 'YouTube';
                                                }
                                            @endphp
                                            @if(count($platforms) > 0)
                                                @foreach($platforms as $p)
                                                    <span class="st-pill st-pill--info" style="font-size:10px; margin-right:2px;">{{ $p }}</span>
                                                @endforeach
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Contact — prioritas phone, fallback ke contact_person --}}
                                        <td style="color:#7a9290; font-size:11px;">
                                            @if($row->phone)
                                                {{ $row->phone }}
                                            @elseif($row->contact_person)
                                                {{ $row->contact_person }}
                                            @else
                                                <span style="color:#bbb;">—</span>
                                            @endif
                                        </td>

                                        {{-- Feedback (textarea inline auto-save) --}}
                                        <td onclick="event.stopPropagation();">
                                            <textarea class="koc-feedback-input"
                                                      data-id="{{ $row->id }}"
                                                      data-original="{{ $row->feedback }}"
                                                      rows="2"
                                                      maxlength="5000"
                                                      placeholder="Catatan feedback…"
                                                      oninput="saveKocFeedback(this)"
                                                      onclick="event.stopPropagation();">{{ $row->feedback }}</textarea>
                                            <small class="koc-feedback-status" data-id="{{ $row->id }}" style="color:#7a9290; font-size:10px;"></small>
                                        </td>

                                        {{-- Sosmed Confirmed (inline checkbox) — hidden di tab 'Semua KOC' --}}
                                        @if($filterTab !== 'all')
                                        <td style="text-align:center;" onclick="event.stopPropagation();">
                                            <label class="koc-sosmed-toggle {{ $row->koc_sosmed_confirmed ? 'koc-sosmed-toggle--checked' : '' }}"
                                                   data-id="{{ $row->id }}">
                                                <input type="checkbox"
                                                       {{ $row->koc_sosmed_confirmed ? 'checked' : '' }}
                                                       onchange="toggleKocSosmed(this)">
                                                <span class="koc-sosmed-toggle__label">
                                                    {{ $row->koc_sosmed_confirmed ? 'Confirmed' : 'Confirm' }}
                                                </span>
                                            </label>
                                        </td>
                                        @endif

                                        {{-- Konten count (klik → modal list konten) --}}
                                        <td style="text-align:center;">
                                            @if($row->approved_content_count > 0)
                                                <button type="button"
                                                        class="koc-content-pill koc-content-trigger"
                                                        data-id="{{ $row->id }}"
                                                        data-name="{{ $row->name }}"
                                                        title="Lihat detail konten">
                                                    <i class="fa fa-eye"></i>
                                                    {{ $row->approved_content_count }} posting
                                                </button>
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Kode Referral --}}
                                        <td style="text-align:center;">
                                            @if($row->promos->isNotEmpty())
                                                @foreach($row->promos as $promo)
                                                    <span class="st-pill st-pill--info st-pill--plain" style="font-size:10.5px; margin:1px 2px;">
                                                        {{ $promo->promo_code }}
                                                    </span>
                                                @endforeach
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $filterTab !== 'all' ? 11 : 10 }}">
                                            <div class="st-empty-state">
                                                <i class="fa fa-users-slash" aria-hidden="true"></i>
                                                <div class="st-empty-state__title">
                                                    @if($filterTab === 'confirmed')
                                                        Belum ada KOC yang confirmed sosmed
                                                    @elseif($filterTab === 'submitted')
                                                        Belum ada KOC yang submit konten approved
                                                    @else
                                                        Belum ada data KOC
                                                    @endif
                                                </div>
                                                <div class="st-empty-state__desc">
                                                    @if($search || $filterBranch !== 'all' || $filterDoctor !== 'all' || $filterPlatform !== 'all')
                                                        Coba ubah filter atau kata kunci pencarian.
                                                    @elseif($filterTab !== 'all')
                                                        Cek tab "Semua KOC" untuk melihat seluruh data.
                                                    @else
                                                        Klik tombol <strong>Tambah KOC</strong> di kanan atas untuk mulai input.
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- ─── PAGINATION ─── --}}
                    @if($kocs->hasPages())
                        <div style="padding:12px 20px; border-top:1px solid #c4ebe9; background:#f4faf9;">
                            {{ $kocs->links() }}
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ─── MODAL: Detail Konten KOC ─── --}}
<div class="koc-content-modal-backdrop" id="kocContentModalBackdrop">
    <div class="koc-content-modal">
        <button type="button" class="koc-content-modal-close" onclick="closeKocContentModal()">&times;</button>

        <div class="koc-content-modal-header">
            <div class="koc-content-modal-icon"><i class="fa fa-photo-video"></i></div>
            <div style="flex:1; min-width:0;">
                <div class="koc-content-modal-title" id="kocContentModalTitle">Konten KOC</div>
                <div class="koc-content-modal-sub" id="kocContentModalSub">—</div>
            </div>
        </div>

        <div class="koc-content-modal-filters">
            <div class="koc-content-filter-group">
                <label>Platform</label>
                <select id="kocContentFilterPlatform">
                    <option value="all">Semua</option>
                    <option value="tiktok">TikTok</option>
                    <option value="instagram">Instagram</option>
                    <option value="youtube">YouTube</option>
                </select>
            </div>
            <div class="koc-content-filter-group">
                <label>Status</label>
                <select id="kocContentFilterStatus">
                    <option value="all">Semua</option>
                    <option value="approved">Disetujui</option>
                    <option value="pending">Menunggu</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
            <div class="koc-content-filter-result" id="kocContentFilterResult"></div>
        </div>

        <div class="koc-content-modal-body">
            <div id="kocContentModalLoading" class="koc-content-loading">
                <i class="fa fa-spinner fa-spin"></i> Memuat konten...
            </div>
            <div id="kocContentModalEmpty" class="koc-content-empty" style="display:none;">
                <i class="fa fa-photo-video" style="font-size:32px; color:#c4ebe9;"></i>
                <div style="margin-top:8px; color:#7a9290;">Belum ada konten yang di-submit.</div>
            </div>
            <ul id="kocContentModalList" class="koc-content-list" style="display:none;"></ul>
        </div>

        <div class="koc-content-modal-footer">
            <button type="button" class="st-btn-outline-brown" onclick="closeKocContentModal()">Tutup</button>
            <a href="#" id="kocContentModalReviewUrl" class="st-btn-brand" target="_blank">
                <i class="fa fa-external-link-alt"></i> Buka di Content Review
            </a>
        </div>
    </div>
</div>

{{-- ─── MODAL: Import CSV KOC ─── --}}
<div class="modal fade" id="kocImportCsvModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('koc.import-csv') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header" style="background:#f4faf9; border-bottom:1px solid #c4ebe9;">
                    <h5 class="modal-title" style="color:#0a4f4d; font-weight:700;">
                        <i class="fa fa-upload"></i> Import KOC dari CSV
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info" style="background:#e6f5f4; border:1px solid #c4ebe9; color:#0a4f4d; font-size:13px;">
                        <strong><i class="fa fa-info-circle"></i> Format CSV:</strong>
                        kolom diharapkan (header baris pertama):
                        <code style="font-size:11px; color:#0e6e6b;">
                            name, branch_name, doctor_name, phone, email, feedback,
                            social_platform, social_username, social_tiktok, social_instagram,
                            behel_type, reward_program, kode_referral,
                            referral_scope, is_active
                        </code>
                        <div style="margin-top:8px;">
                            Belum punya template?
                            <a href="{{ route('koc.csv-template') }}" class="st-primary" style="font-weight:700;">
                                <i class="fa fa-download"></i> Download Template CSV
                            </a>
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="font-weight:600;">File CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" accept=".csv,text/csv" required class="form-control-file">
                        @error('csv_file')
                            <small class="text-danger d-block mt-1">{{ $message }}</small>
                        @enderror
                        <small class="text-muted d-block mt-1">
                            Max 5 MB. Hanya kolom <code>name</code> yang wajib — kolom lain optional.
                            Enum value invalid akan di-set null (tidak error). type='koc' &amp; role='pasien' di-set otomatis.
                        </small>
                    </div>

                    <div style="font-size:12px; color:#7a9290; border-top:1px dashed #c4ebe9; padding-top:10px;">
                        <strong>Catatan enum:</strong>
                        <ul style="margin:6px 0 0 18px; padding:0;">
                            <li><code>social_platform</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::socialPlatforms())) }}</li>
                            <li><code>reward_program</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::rewardPrograms())) }}</li>
                            <li><code>referral_scope</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::referralScopes())) }}</li>
                            <li><code>behel_type</code>: terima enum key atau label bebas (mis. "Signature Metal SP", "Deluxe Metal"). SP/GP ambigu → default GP.</li>
                            <li><code>branch_name</code> &amp; <code>doctor_name</code>: match case-insensitive dengan nama di database. Tidak ketemu → kosong.</li>
                            <li><code>kode_referral</code>: kalau diisi &amp; belum ada di Promo, otomatis dibuat (discount nominal sesuai scope).</li>
                            <li><code>is_active</code>: 1/0, true/false, yes/no. Default 1 (aktif).</li>
                        </ul>
                    </div>
                </div>

                <div class="modal-footer" style="background:#f4faf9; border-top:1px solid #c4ebe9;">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand">
                        <i class="fa fa-upload"></i> Upload &amp; Import
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('css')
<style>
    /* ─── Dokter & Perawatan inline select (sementara, Super Admin) ── */
    .koc-doctor-select,
    .koc-behel-select {
        border: 1px solid #c4ebe9;
        padding: 5px 26px 5px 10px;
        border-radius: 8px;
        font-size: 12px;
        background: #fff;
        cursor: pointer;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23495057' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        max-width: 100%;
        transition: opacity .15s, border-color .15s;
    }
    .koc-doctor-select:hover,
    .koc-behel-select:hover    { border-color: #0a4f4d; }
    .koc-doctor-select:disabled,
    .koc-behel-select:disabled { opacity: .5; cursor: wait; }
    .koc-doctor-select.is-saved,
    .koc-behel-select.is-saved { border-color: #28a745; background-color: #f2fbf5; }
    .koc-doctor-select.is-error,
    .koc-behel-select.is-error { border-color: #dc3545; }

    /* ─── Feedback inline textarea (auto-save) ─────────────────────── */
    .koc-feedback-input {
        width: 100%;
        min-height: 44px;
        padding: 6px 8px;
        font-size: 12px;
        line-height: 1.35;
        color: #212529;
        background: #fff;
        border: 1px solid #c4ebe9;
        border-radius: 6px;
        resize: vertical;
        font-family: inherit;
        transition: border-color .15s, box-shadow .15s;
    }
    .koc-feedback-input:focus {
        outline: none;
        border-color: #0a4f4d;
        box-shadow: 0 0 0 2px rgba(10, 79, 77, .15);
    }
    .koc-feedback-input.is-saving { border-color: #f0ad4e; }
    .koc-feedback-input.is-saved  { border-color: #28a745; }
    .koc-feedback-input.is-error  { border-color: #dc3545; }
    .koc-feedback-status { display:block; margin-top:2px; }

    /* ─── Modal "Lihat Konten KOC" ─────────────────────────────────── */
    .koc-content-modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,0.55);
        display: none; align-items: center; justify-content: center;
        z-index: 9999; padding: 20px;
    }
    .koc-content-modal-backdrop.is-open { display: flex; }
    .koc-content-modal {
        background: #fff; border-radius: 14px; max-width: 620px; width: 100%;
        max-height: 90vh; display: flex; flex-direction: column; position: relative;
        box-shadow: 0 12px 48px rgba(0,0,0,0.30);
        font-family: 'Montserrat', sans-serif;
    }
    .koc-content-modal-close {
        position: absolute; top: 10px; right: 14px;
        background: rgba(255,255,255,0.85); border: none;
        width: 28px; height: 28px; border-radius: 50%;
        font-size: 18px; color: #495057; cursor: pointer; line-height: 1;
        display: flex; align-items: center; justify-content: center;
        z-index: 5;
    }
    .koc-content-modal-close:hover { background: #fff; color: #0a4f4d; }

    .koc-content-modal-header {
        padding: 22px 28px 18px; border-bottom: 1px solid #e6f0ef;
        background: linear-gradient(180deg, #f4faf9 0%, #ffffff 100%);
        border-radius: 14px 14px 0 0;
        display: flex; gap: 14px; align-items: center;
    }
    .koc-content-modal-icon {
        width: 48px; height: 48px; border-radius: 10px;
        background: #e6f5f4; color: #13928e;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 22px; flex-shrink: 0;
    }
    .koc-content-modal-title { font-size: 16px; font-weight: 800; color: #0a4f4d; }
    .koc-content-modal-sub { font-size: 12px; color: #7a9290; margin-top: 2px; }

    /* Filter bar di dalam modal */
    .koc-content-modal-filters {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        padding: 10px 22px; border-bottom: 1px solid #e6f0ef;
        background: #f9fcfb;
    }
    .koc-content-filter-group { display: flex; align-items: center; gap: 6px; }
    .koc-content-filter-group label {
        font-size: 11px; font-weight: 700; color: #7a9290;
        text-transform: uppercase; letter-spacing: 0.4px; margin: 0;
    }
    .koc-content-filter-group select {
        font-size: 12px; padding: 5px 28px 5px 10px;
        border: 1px solid #c4ebe9; border-radius: 6px;
        background: #fff; color: #0a4f4d; font-weight: 600;
        cursor: pointer; appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%230a4f4d' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 8px center;
    }
    .koc-content-filter-result {
        margin-left: auto; font-size: 11px; color: #7a9290; font-weight: 600;
    }

    .koc-content-modal-body {
        padding: 14px 22px; overflow-y: auto; flex: 1;
    }
    .koc-content-loading, .koc-content-empty {
        text-align: center; padding: 28px 12px; color: #7a9290; font-size: 13px;
    }

    /* List item — clean card layout */
    .koc-content-list { list-style: none; margin: 0; padding: 0; }
    .koc-content-list li {
        padding: 12px 14px; border: 1px solid #e6f0ef; border-radius: 10px;
        background: #fff; margin-bottom: 10px;
        transition: border-color .15s, box-shadow .15s;
    }
    .koc-content-list li:hover {
        border-color: #c4ebe9;
        box-shadow: 0 2px 8px rgba(19, 146, 142, 0.06);
    }

    .koc-content-row { display: flex; gap: 12px; align-items: center; }
    .koc-content-platform-icon {
        width: 38px; height: 38px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 17px; color: #fff; flex-shrink: 0;
    }
    .koc-content-platform-icon--tiktok    { background: #000000; }
    .koc-content-platform-icon--instagram { background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); }
    .koc-content-platform-icon--youtube   { background: #ff0000; }
    .koc-content-platform-icon--default   { background: #6c757d; }

    .koc-content-info { flex: 1; min-width: 0; }
    .koc-content-platform-name {
        font-size: 11px; font-weight: 700; color: #0a4f4d;
        text-transform: uppercase; letter-spacing: 0.5px;
        display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    }
    .koc-content-type-badge {
        background: #e6f5f4; color: #0e6e6b;
        font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 3px;
        text-transform: uppercase;
    }
    .koc-content-domain {
        font-size: 12.5px; color: #495057; font-weight: 500;
        margin-top: 3px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .koc-content-meta {
        margin-top: 6px; display: flex; gap: 10px; align-items: center;
        font-size: 11px; color: #7a9290; flex-wrap: wrap;
    }
    .koc-content-meta i { color: #adb5bd; }
    .koc-content-status {
        display: inline-block; padding: 2px 8px; border-radius: 4px;
        font-size: 10px; font-weight: 700; text-transform: uppercase;
    }
    .koc-content-status--success { background: #d4edda; color: #155724; }
    .koc-content-status--warning { background: #fff3cd; color: #856404; }
    .koc-content-status--danger  { background: #f8d7da; color: #721c24; }
    .koc-content-status--neutral { background: #e9ecef; color: #495057; }

    /* Action buttons (Buka link + Review) */
    .koc-content-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .koc-content-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 11px; border-radius: 6px;
        font-size: 11.5px; font-weight: 700; text-decoration: none;
        border: 1px solid; cursor: pointer; line-height: 1;
        white-space: nowrap; transition: all .15s;
    }
    .koc-content-btn--primary {
        background: #13928e; color: #fff; border-color: #0e6e6b;
    }
    .koc-content-btn--primary:hover {
        background: #0a4f4d; color: #fff; text-decoration: none;
    }
    .koc-content-btn--ghost {
        background: #fff; color: #0e6e6b; border-color: #c4ebe9;
    }
    .koc-content-btn--ghost:hover {
        background: #f4faf9; color: #0a4f4d; text-decoration: none; border-color: #13928e;
    }
    .koc-content-btn--disabled {
        background: #f1f3f5; color: #adb5bd; border-color: #dee2e6; cursor: not-allowed;
    }

    .koc-content-modal-footer {
        padding: 14px 22px; border-top: 1px solid #e6f0ef;
        display: flex; justify-content: flex-end; gap: 8px;
        background: #f4faf9; border-radius: 0 0 14px 14px;
    }
</style>
@endsection

@section('js')
<script>
    // ─── Klik row → navigasi ke halaman edit ──────────────────────────
    document.querySelectorAll('.st-table--clickable tr[data-href]').forEach(function(row) {
        row.addEventListener('click', function(e) {
            // Jangan trigger kalau klik di link/button/input/textarea/select dalam row
            if (e.target.closest('a, button, input, textarea, select, label, .st-pill, .koc-sosmed-toggle, .koc-content-pill')) return;
            window.location = row.dataset.href;
        });
    });

    // ─── Feedback inline textarea — auto-save debounce 700ms ──────────
    var kocFeedbackTimers = {};
    function saveKocFeedback(el) {
        const id = el.dataset.id;
        const statusEl = document.querySelector('.koc-feedback-status[data-id="' + id + '"]');
        clearTimeout(kocFeedbackTimers[id]);

        el.classList.remove('is-saved', 'is-error');
        el.classList.add('is-saving');
        if (statusEl) statusEl.textContent = 'Menyimpan…';

        kocFeedbackTimers[id] = setTimeout(function () {
            const val = el.value;
            if (val === el.dataset.original) {
                el.classList.remove('is-saving');
                if (statusEl) statusEl.textContent = '';
                return;
            }
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch(`/koc/${id}/feedback`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ feedback: val }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(res => {
                el.dataset.original = res.feedback || '';
                el.classList.remove('is-saving');
                el.classList.add('is-saved');
                if (statusEl) statusEl.textContent = 'Tersimpan';
                setTimeout(function () {
                    el.classList.remove('is-saved');
                    if (statusEl) statusEl.textContent = '';
                }, 1500);
            })
            .catch(() => {
                el.classList.remove('is-saving');
                el.classList.add('is-error');
                if (statusEl) statusEl.textContent = 'Gagal — coba lagi';
            });
        }, 700);
    }

    // ─── Generic inline-select auto-save (sementara, Super Admin) ─────
    function saveKocInlineSelect(el, opts) {
        const id      = el.dataset.id;
        const newVal  = el.value || '';
        const origVal = el.dataset.original || '';
        if (newVal === origVal) return;

        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        el.classList.remove('is-saved', 'is-error');
        el.disabled = true;

        const payload = {};
        payload[opts.field] = newVal;

        fetch(`/koc/${id}/${opts.urlSuffix}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(r => r.ok ? r.json() : Promise.reject(r))
        .then(res => {
            el.dataset.original = res[opts.field] || '';
            el.disabled = false;
            el.classList.add('is-saved');
            setTimeout(() => el.classList.remove('is-saved'), 1500);
        })
        .catch(() => {
            el.value = origVal;
            el.disabled = false;
            el.classList.add('is-error');
            if (typeof Swal !== 'undefined') {
                Swal.fire({ title: 'Gagal', text: 'Gagal menyimpan perubahan. Coba lagi.', type: 'error' });
            }
        });
    }

    function changeKocDoctor(el)    { saveKocInlineSelect(el, { field: 'raw_doctor_id', urlSuffix: 'doctor' }); }
    function changeKocBehelType(el) { saveKocInlineSelect(el, { field: 'behel_type',    urlSuffix: 'behel-type' }); }

    // ─── Inline toggle: KOC Confirmed Sosmed ──────────────────────────
    function toggleKocSosmed(checkbox) {
        const wrapper = checkbox.closest('.koc-sosmed-toggle');
        const id = wrapper.dataset.id;
        const newValue = checkbox.checked;
        const labelEl = wrapper.querySelector('.koc-sosmed-toggle__label');
        const originalChecked = !newValue; // state sebelum klik (untuk rollback kalau gagal)

        // Visual loading state
        wrapper.classList.add('is-saving');
        labelEl.textContent = 'Menyimpan...';

        // CSRF token dari meta tag (Laravel default)
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`/koc/${id}/confirm-sosmed`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ confirmed: newValue }),
        })
        .then(response => {
            if (!response.ok) throw new Error('Gagal menyimpan');
            return response.json();
        })
        .then(data => {
            // Update visual state
            wrapper.classList.remove('is-saving');
            labelEl.textContent = data.confirmed ? 'Confirmed' : 'Confirm';
            if (data.confirmed) {
                wrapper.classList.add('koc-sosmed-toggle--checked');
            } else {
                wrapper.classList.remove('koc-sosmed-toggle--checked');
            }
        })
        .catch(error => {
            // Rollback checkbox state
            console.error(error);
            checkbox.checked = originalChecked;
            wrapper.classList.remove('is-saving');
            labelEl.textContent = originalChecked ? 'Confirmed' : 'Confirm';
            alert('Gagal menyimpan perubahan. Coba lagi.');
        });
    }

    /* ─── Modal "Lihat Konten KOC" ────────────────────────────────────── */
    var kocContentCache = []; // semua konten yang sudah di-fetch (untuk filter client-side)

    function escKoc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function shortDomain(url) {
        if (!url) return '';
        try {
            var u = new URL(url);
            // Ambil host + path pertama, max 50 char
            var s = u.hostname.replace(/^www\./, '') + u.pathname;
            return s.length > 50 ? s.substring(0, 50) + '…' : s;
        } catch (e) {
            return url.length > 50 ? url.substring(0, 50) + '…' : url;
        }
    }

    function renderKocContentList() {
        var listEl  = document.getElementById('kocContentModalList');
        var emptyEl = document.getElementById('kocContentModalEmpty');
        var resultEl = document.getElementById('kocContentFilterResult');

        var fPlatform = document.getElementById('kocContentFilterPlatform').value;
        var fStatus   = document.getElementById('kocContentFilterStatus').value;

        var filtered = kocContentCache.filter(function (c) {
            if (fPlatform !== 'all' && c.platform !== fPlatform) return false;
            if (fStatus   !== 'all' && c.status   !== fStatus)   return false;
            return true;
        });

        resultEl.textContent = filtered.length + ' dari ' + kocContentCache.length + ' konten';

        if (!filtered.length) {
            listEl.style.display  = 'none';
            listEl.innerHTML      = '';
            emptyEl.style.display = '';
            emptyEl.innerHTML = '<i class="fa fa-filter" style="font-size:28px; color:#c4ebe9;"></i>'
                + '<div style="margin-top:8px; color:#7a9290;">Tidak ada konten yang sesuai filter.</div>';
            return;
        }

        emptyEl.style.display = 'none';

        var html = '';
        filtered.forEach(function (c) {
            var iconClass = c.platform === 'tiktok'    ? 'fab fa-tiktok'
                          : c.platform === 'instagram' ? 'fab fa-instagram'
                          : c.platform === 'youtube'   ? 'fab fa-youtube'
                          : 'fa fa-share-alt';
            var iconBg    = 'koc-content-platform-icon--' + (c.platform || 'default');
            var statusCls = 'koc-content-status--' + (c.status_color || 'neutral');
            var domain    = shortDomain(c.content_url);

            html += '<li>';
            html += '  <div class="koc-content-row">';
            html += '    <span class="koc-content-platform-icon ' + iconBg + '"><i class="' + iconClass + '"></i></span>';
            html += '    <div class="koc-content-info">';
            html += '      <div class="koc-content-platform-name">' + escKoc(c.platform_lbl);
            if (c.content_type) html += '<span class="koc-content-type-badge">' + escKoc(c.content_type) + '</span>';
            html += '</div>';
            html += '      <div class="koc-content-domain" title="' + escKoc(c.content_url || '') + '">'
                  + (domain ? escKoc(domain) : '<span style="color:#bbb;">(tanpa link)</span>') + '</div>';
            html += '      <div class="koc-content-meta">';
            html += '        <span class="koc-content-status ' + statusCls + '">' + escKoc(c.status_lbl) + '</span>';
            if (c.created_at) html += '<span><i class="fa fa-calendar"></i> ' + escKoc(c.created_at) + '</span>';
            html += '      </div>';
            html += '    </div>';
            html += '    <div class="koc-content-actions">';
            if (c.content_url) {
                html += '<a href="' + escKoc(c.content_url) + '" target="_blank" rel="noopener" class="koc-content-btn koc-content-btn--primary">'
                      + '<i class="fa fa-external-link-alt"></i> Buka</a>';
            } else {
                html += '<span class="koc-content-btn koc-content-btn--disabled"><i class="fa fa-link"></i> No link</span>';
            }
            html += '<a href="' + escKoc(c.edit_url) + '" target="_blank" class="koc-content-btn koc-content-btn--ghost">'
                  + '<i class="fa fa-edit"></i> Review</a>';
            html += '    </div>';
            html += '  </div>';
            html += '</li>';
        });
        listEl.innerHTML = html;
        listEl.style.display = '';
    }

    function openKocContentModal(btn) {
        var id   = btn.dataset.id;
        var name = btn.dataset.name || 'KOC';

        // Reset state
        document.getElementById('kocContentModalTitle').textContent = 'Konten ' + name;
        document.getElementById('kocContentModalSub').textContent   = 'Memuat...';
        document.getElementById('kocContentModalLoading').style.display = '';
        document.getElementById('kocContentModalEmpty').style.display   = 'none';
        document.getElementById('kocContentFilterResult').textContent   = '';
        document.getElementById('kocContentFilterPlatform').value = 'all';
        document.getElementById('kocContentFilterStatus').value   = 'all';
        var listEl = document.getElementById('kocContentModalList');
        listEl.style.display = 'none';
        listEl.innerHTML = '';
        document.getElementById('kocContentModalReviewUrl').href = '#';
        kocContentCache = [];

        document.getElementById('kocContentModalBackdrop').classList.add('is-open');

        $.get('/koc/' + id + '/contents', function (data) {
            document.getElementById('kocContentModalLoading').style.display = 'none';
            document.getElementById('kocContentModalReviewUrl').href = data.review_url || '#';
            document.getElementById('kocContentModalSub').textContent =
                (data.contents.length || 0) + ' konten total';

            kocContentCache = data.contents || [];

            if (!kocContentCache.length) {
                document.getElementById('kocContentModalEmpty').style.display = '';
                document.getElementById('kocContentFilterResult').textContent = '0 konten';
                return;
            }
            renderKocContentList();
        }).fail(function () {
            document.getElementById('kocContentModalLoading').style.display = 'none';
            document.getElementById('kocContentModalEmpty').style.display = '';
            document.getElementById('kocContentModalEmpty').innerHTML =
                '<i class="fa fa-exclamation-triangle" style="color:#dc3545;"></i> Gagal memuat konten.';
        });
    }

    function closeKocContentModal() {
        document.getElementById('kocContentModalBackdrop').classList.remove('is-open');
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.koc-content-trigger');
        if (trigger) { e.preventDefault(); openKocContentModal(trigger); return; }
        if (e.target.id === 'kocContentModalBackdrop') closeKocContentModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeKocContentModal();
    });

    // Bind filter dropdowns
    document.getElementById('kocContentFilterPlatform').addEventListener('change', renderKocContentList);
    document.getElementById('kocContentFilterStatus').addEventListener('change', renderKocContentList);
</script>
@endsection
