@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">KOL Management</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── HEADER ─── --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #c4ebe9; background:#fff;">
                        <div>
                            <h5 style="margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-0.01em; color:#0a4f4d; display:flex; align-items:center; gap:8px;">
                                <i class="fa fa-archive" style="font-size:16px; color:#13928e;"></i>
                                KOL Existing
                            </h5>
                            <small style="color:#5a7572; font-size:13px;">
                                Arsip KOL dari periode bulan-bulan sebelumnya
                                — total <strong style="color:#0a4f4d;">{{ $totalCount }}</strong> KOL
                            </small>
                        </div>
                        <div>
                            <button type="button" class="st-btn-brand"
                                    data-toggle="modal" data-target="#kolImportCsvModal"
                                    title="Import KOL dari file CSV">
                                <i class="fa fa-upload"></i> Import CSV
                            </button>
                        </div>
                    </div>

                    {{-- ─── TAB NAVIGATION ─── --}}
                    @php $activeTab = 'existing'; @endphp
                    @include('crud.kol._tab_nav', ['activeTab' => $activeTab])

                    {{-- ─── BRANCH SUMMARY CARDS ─── --}}
                    @include('crud.kol._summary_cards', [
                        'branches'     => $branches,
                        'filterBranch' => $filterBranch,
                        'totalCount'   => $totalCount,
                        'routeName'    => 'kol.existing',
                    ])

                </div> {{-- end .st-card__body (top card) --}}
            </div> {{-- end .st-card (top card) --}}

            {{-- ═══ CARD KEDUA: Monthly View (accordion, default-collapsed) ═══ --}}
            @include('crud.kol._existing_monthly', [
                'kolsByMonth'   => $kolsByMonth,
                'monthlyTotal'  => $monthlyTotal,
                'monthlyCapped' => $monthlyCapped,
            ])

            {{-- ═══ CARD KETIGA: Filter + Table ═══ --}}
            <div class="st-card mt-3">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── FILTER BAR ─── --}}
                    <form method="GET" action="{{ route('kol.existing') }}" class="st-filter-bar" style="border-bottom:1px solid #c4ebe9; padding:10px 16px;">
                        <input type="hidden" name="branch" value="{{ $filterBranch }}">

                        @if($activeBranch)
                            <span class="st-active-branch">
                                <i class="fa fa-store"></i> {{ $activeBranch->name }}
                                <a href="{{ route('kol.existing', array_merge(request()->except(['branch', 'page']), ['branch' => 'all'])) }}" 
                                   style="color:#0a4f4d; text-decoration:none; margin-left:4px;">
                                    <i class="fa fa-times-circle"></i>
                                </a>
                            </span>
                        @endif

                        <label>Tahun</label>
                        <select name="tahun" onchange="this.form.submit();">
                            <option value="all" {{ $filterTahun === 'all' ? 'selected' : '' }}>Semua Tahun</option>
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" {{ (string) $filterTahun === (string) $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>

                        <label>Bulan</label>
                        <select name="bulan" onchange="this.form.submit();">
                            <option value="all" {{ $filterBulan === 'all' ? 'selected' : '' }}>Semua Bulan</option>
                            @php
                                $bulanList = [
                                    '1' => 'Januari',  '2' => 'Februari', '3' => 'Maret',
                                    '4' => 'April',    '5' => 'Mei',      '6' => 'Juni',
                                    '7' => 'Juli',     '8' => 'Agustus',  '9' => 'September',
                                    '10' => 'Oktober', '11' => 'November','12' => 'Desember',
                                ];
                            @endphp
                            @foreach($bulanList as $num => $label)
                                <option value="{{ $num }}" {{ (string) $filterBulan === (string) $num ? 'selected' : '' }}>
                                    {{ $label }}
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
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama / username...">
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>

                        @if($search || $filterTahun !== 'all' || $filterBulan !== 'all' || $filterPlatform !== 'all')
                            <a href="{{ route('kol.existing', ['branch' => $filterBranch]) }}" class="st-btn-outline-brown">
                                <i class="fa fa-undo"></i> Reset
                            </a>
                        @endif

                        <div class="ml-auto st-muted" style="font-size:12px;">
                            Menampilkan <strong class="st-primary">{{ $kols->count() }}</strong> dari <strong class="st-primary">{{ $kols->total() }}</strong> KOL
                        </div>
                    </form>

                    {{-- ─── TABLE ─── --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Username / Nama</th>
                                    <th>Cabang</th>
                                    <th>Followers</th>
                                    <th>Platform</th>
                                    <th>Contact Person</th>
                                    <th>Tgl Approach</th>
                                    <th>Status Akhir</th>
                                    <th width="18%">Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kols as $key => $row)
                                    <tr data-href="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}" style="cursor:pointer;">
                                        <td class="st-num">{{ $kols->firstItem() + $key }}</td>

                                        {{-- Username / Nama --}}
                                        <td>
                                            <span class="st-primary" style="font-weight:500;">
                                                @if($row->social_instagram)
                                                    {{ '@' . $row->social_instagram }}
                                                @elseif($row->social_tiktok)
                                                    {{ '@' . $row->social_tiktok }}
                                                @elseif($row->social_username)
                                                    {{ '@' . $row->social_username }}
                                                @else
                                                    {{ $row->name }}
                                                @endif
                                            </span>
                                            @if($row->name && ($row->social_instagram || $row->social_tiktok || $row->social_username))
                                                <br><small style="color:#7a9290; font-size:11px;">{{ $row->name }}</small>
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

                                        {{-- Followers --}}
                                        <td class="st-tnum">
                                            @if($row->followers_count)
                                                <strong>{{ number_format($row->followers_count, 0, ',', '.') }}</strong>
                                            @else
                                                <span style="color:#bbb;">—</span>
                                            @endif
                                        </td>

                                        {{-- Platform --}}
                                        <td>
                                            @php
                                                $platforms = [];
                                                if($row->social_instagram) $platforms[] = 'Instagram';
                                                if($row->social_tiktok)    $platforms[] = 'TikTok';
                                                if($row->social_youtube)   $platforms[] = 'YouTube';
                                            @endphp
                                            @if(count($platforms) > 0)
                                                @foreach($platforms as $p)
                                                    <span class="st-pill st-pill--info" style="font-size:10px; margin-right:2px;">{{ $p }}</span>
                                                @endforeach
                                            @elseif($row->social_platform)
                                                <span class="st-pill st-pill--info" style="font-size:10px;">
                                                    {{ \App\Models\RawAffiliate::socialPlatforms()[$row->social_platform] ?? $row->social_platform }}
                                                </span>
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Contact Person --}}
                                        <td style="color:#7a9290; font-size:11px;">
                                            @if($row->contact_person)
                                                {{ $row->contact_person }}
                                            @elseif($row->phone)
                                                {{ $row->phone }}
                                            @else
                                                <span style="color:#bbb;">—</span>
                                            @endif
                                        </td>

                                        {{-- Tanggal Approach --}}
                                        <td style="color:#7a9290;">
                                            @if($row->tanggal_approach)
                                                {{ $row->tanggal_approach->translatedFormat('d M Y') }}
                                            @endif
                                        </td>

                                        {{-- Status Akhir --}}
                                        <td>
                                            @if($row->kol_progress === 'done')
                                                <span class="st-pill st-pill--plain st-pill--success">Done</span>
                                            @elseif($row->kol_progress === 'no_respon')
                                                <span class="st-pill st-pill--plain st-pill--danger">No Respon</span>
                                            @else
                                                <span class="st-pill st-pill--plain" style="background:#f1efe8; color:#5f5e5a;">
                                                    Tidak diketahui
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Feedback --}}
                                        <td style="color:#5f5e5a; font-size:12px; white-space:normal;">
                                            @if($row->feedback)
                                                {{ $row->feedback }}
                                            @else
                                                <span style="color:#bbb;">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9">
                                            <div class="st-empty-state">
                                                <i class="fa fa-folder-open" aria-hidden="true"></i>
                                                <div class="st-empty-state__title">Belum ada KOL existing</div>
                                                <div class="st-empty-state__desc">
                                                    @if($search || $filterTahun !== 'all' || $filterBulan !== 'all' || $filterPlatform !== 'all' || $filterBranch !== 'all')
                                                        Coba ubah filter atau kata kunci pencarian.
                                                    @else
                                                        Data KOL existing akan muncul di sini setelah selesai migrasi dari Notion 
                                                        atau saat KOL bulan ini bertransisi ke periode berikutnya.
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
                    @if($kols->hasPages())
                        <div style="padding:12px 20px; border-top:1px solid #c4ebe9; background:#f4faf9;">
                            {{ $kols->links() }}
                        </div>
                    @endif

                </div>
            </div>

        </div>
    </div>
</div>

{{-- ─── MODAL: Import CSV KOL ─── --}}
<div class="modal fade" id="kolImportCsvModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('kol.import-csv') }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header" style="background:#f4faf9; border-bottom:1px solid #c4ebe9;">
                    <h5 class="modal-title" style="color:#0a4f4d; font-weight:700;">
                        <i class="fa fa-upload"></i> Import KOL dari CSV
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
                            name, social_username, social_platform, followers_count,
                            kol_kategori, kol_progress, kol_status_relasi,
                            tanggal_approach, rate_card, contact_person, phone,
                            branch_name, feedback
                        </code>
                        <div style="margin-top:8px;">
                            Belum punya template?
                            <a href="{{ route('kol.csv-template') }}" class="st-primary" style="font-weight:700;">
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
                            Enum value invalid akan di-set null (tidak error).
                        </small>
                    </div>

                    <div style="font-size:12px; color:#7a9290; border-top:1px dashed #c4ebe9; padding-top:10px;">
                        <strong>Catatan enum:</strong>
                        <ul style="margin:6px 0 0 18px; padding:0;">
                            <li><code>social_platform</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::socialPlatforms())) }}</li>
                            <li><code>kol_progress</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::kolProgressOptions())) }}</li>
                            <li><code>kol_kategori</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::kolKategoriOptions())) }}</li>
                            <li><code>kol_status_relasi</code>: {{ implode(', ', array_keys(\App\Models\RawAffiliate::kolStatusRelasiOptions())) }}</li>
                            <li><code>branch_name</code>: match dengan nama cabang (case-insensitive). Cabang tidak ketemu → KOL tanpa cabang.</li>
                            <li><code>tanggal_approach</code>: format bebas (YYYY-MM-DD, DD/MM/YYYY, dll).</li>
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

@section('scripts')
<script>
    document.querySelectorAll('.st-table--clickable tr[data-href]').forEach(function(row) {
        row.addEventListener('click', function(e) {
            if (e.target.closest('a, button, .st-pill')) return;
            window.location = row.dataset.href;
        });
    });
</script>
@endsection
