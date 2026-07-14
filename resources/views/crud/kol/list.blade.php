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

                    {{-- ─── HEADER (judul + tombol action) ─── --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #c4ebe9; background:#fff; flex-wrap:wrap; gap:12px;">
                        <div>
                            <h5 style="margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-0.01em; color:#0a4f4d; display:flex; align-items:center; gap:8px;">
                                <i class="fa fa-calendar-day" style="font-size:16px; color:#13928e;"></i>
                                KOL Bulan Ini
                                @if(! $isCurrentMonth)
                                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:3px 10px; border-radius:999px; background:#fef3c7; color:#92400e; letter-spacing:0.4px; text-transform:uppercase;">
                                        <i class="fa fa-eye" style="font-size:9px;"></i> Pratinjau
                                    </span>
                                @endif
                            </h5>
                            <small style="color:#5a7572; font-size:13px;">
                                Periode <strong style="color:#0a4f4d;">{{ $selectedDate->translatedFormat('F Y') }}</strong>
                                — total <strong style="color:#0a4f4d;">{{ $totalCount }}</strong> KOL
                                {{ $isCurrentMonth ? 'aktif' : 'terjadwal' }}
                            </small>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                            {{-- Dropdown bulan: pilih bulan ini → Des tahun ini.
                                 Sembunyikan inline filter (branch/progress/kategori/q/view)
                                 sebagai hidden inputs supaya preserve saat ganti bulan. --}}
                            <form method="GET" action="{{ route('kol') }}" class="kol-month-picker">
                                @if($filterBranch !== 'all')<input type="hidden" name="branch" value="{{ $filterBranch }}">@endif
                                @if($filterProgress !== 'all')<input type="hidden" name="progress" value="{{ $filterProgress }}">@endif
                                @if($filterKategori !== 'all')<input type="hidden" name="kategori" value="{{ $filterKategori }}">@endif
                                @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                                @if($view !== 'table')<input type="hidden" name="view" value="{{ $view }}">@endif
                                <i class="fa fa-calendar-day kol-month-picker__icon"></i>
                                <select name="month" onchange="this.form.submit();" class="kol-month-picker__select" aria-label="Pilih periode bulan">
                                    @foreach($availableMonths as $m)
                                        <option value="{{ $m['key'] }}" {{ $filterMonth === $m['key'] ? 'selected' : '' }}>
                                            {{ $m['label'] }}{{ $m['key'] === \Carbon\Carbon::now()->format('Y-m') ? ' (bulan ini)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                            <a href="{{ route('rawaffiliate.new') }}?type=kol" class="st-btn-brand">
                                <i class="fa fa-plus"></i> Tambah KOL
                            </a>
                        </div>
                    </div>

                    {{-- ─── TAB NAVIGATION ─── --}}
                    @php $activeTab = 'bulan-ini'; @endphp
                    @include('crud.kol._tab_nav', ['activeTab' => $activeTab])

                    {{-- ─── BRANCH SUMMARY CARDS ─── --}}
                    @include('crud.kol._summary_cards', [
                        'branches'     => $branches,
                        'filterBranch' => $filterBranch,
                        'totalCount'   => $totalCount,
                        'routeName'    => 'kol',
                    ])

                </div> {{-- end .st-card__body (top card) --}}
            </div> {{-- end .st-card (top card) --}}

            {{-- ═══ CARD KEDUA: Monthly View (accordion, default-collapsed) ═══ --}}
            @php
                $namaBulanID = [
                    1 => 'Januari', 2 => 'Februari', 3 => 'Maret',
                    4 => 'April',   5 => 'Mei',      6 => 'Juni',
                    7 => 'Juli',    8 => 'Agustus',  9 => 'September',
                    10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                ];
            @endphp
            @include('crud.kol._existing_monthly', [
                'kolsByMonth'    => $kolsByMonth,
                'monthlyTotal'   => $monthlyTotal,
                'monthlyCapped'  => $monthlyCapped,
                'monthlyTitle'   => 'KOL Bulan ' . $namaBulanID[$selectedDate->month] . ($isCurrentMonth ? '' : ' ' . $selectedDate->year),
                'showYearFilter' => false,
            ])

            {{-- ═══ CARD KETIGA: View toggle + Filter + Table/Calendar ═══ --}}
            <div class="st-card mt-3">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── VIEW TOGGLE (Table | Kalender) ─── --}}
                    <div style="display:flex; gap:6px; padding:10px 16px; border-bottom:1px solid #c4ebe9;">
                        <a href="{{ route('kol', array_merge(request()->except(['view','week','page']), ['view' => 'table'])) }}"
                           class="kol-view-tab {{ $view === 'table' ? 'is-active' : '' }}">
                            <i class="fa fa-table"></i> Tabel
                        </a>
                        <a href="{{ route('kol', array_merge(request()->except(['view','week','page']), ['view' => 'calendar'])) }}"
                           class="kol-view-tab {{ $view === 'calendar' ? 'is-active' : '' }}">
                            <i class="fa fa-calendar-week"></i> Kalender Mingguan
                        </a>
                    </div>

                    {{-- ─── FILTER BAR ─── --}}
                    <form method="GET" action="{{ route('kol') }}" class="st-filter-bar" style="border-bottom:1px solid #c4ebe9; padding:10px 16px;">
                        <input type="hidden" name="branch" value="{{ $filterBranch }}">

                        @if($activeBranch)
                            <span class="st-active-branch">
                                <i class="fa fa-store"></i> {{ $activeBranch->name }}
                                <a href="{{ route('kol', array_merge(request()->except(['branch', 'page']), ['branch' => 'all'])) }}" 
                                   style="color:#0a4f4d; text-decoration:none; margin-left:4px;" 
                                   title="Reset cabang">
                                    <i class="fa fa-times-circle"></i>
                                </a>
                            </span>
                        @endif

                        <label>Progress</label>
                        <select name="progress" onchange="this.form.submit();">
                            <option value="all" {{ $filterProgress === 'all' ? 'selected' : '' }}>Semua Progress</option>
                            @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                                <option value="{{ $key }}" {{ $filterProgress === $key ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>

                        <label>Kategori</label>
                        <select name="kategori" onchange="this.form.submit();">
                            <option value="all" {{ $filterKategori === 'all' ? 'selected' : '' }}>Semua Kategori</option>
                            @foreach(\App\Models\RawAffiliate::kolKategoriOptions() as $key => $label)
                                <option value="{{ $key }}" {{ $filterKategori === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>

                        <label>Cari</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama / username / phone...">
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>

                        @if($search || $filterProgress !== 'all' || $filterKategori !== 'all')
                            <a href="{{ route('kol', ['branch' => $filterBranch]) }}" class="st-btn-outline-brown">
                                <i class="fa fa-undo"></i> Reset
                            </a>
                        @endif

                        <div class="ml-auto st-muted" style="font-size:12px;">
                            Menampilkan <strong class="st-primary">{{ $kols->count() }}</strong> dari <strong class="st-primary">{{ $kols->total() }}</strong> KOL
                        </div>
                    </form>

                    @if($view === 'calendar')
                        {{-- ─── CALENDAR VIEW (Mon-Sat current week) ─── --}}
                        <div class="kol-cal-wrap">
                            <div class="kol-cal-header">
                                <div>
                                    <div class="kol-cal-title"><i class="fa fa-calendar-week"></i> KOL Approach Minggu Ini</div>
                                    <div class="kol-cal-sub">{{ $weekLabel }}</div>
                                </div>
                                <div class="kol-cal-nav">
                                    <a href="{{ route('kol', array_merge(request()->except('week'), ['week' => $weekOffset - 1])) }}" class="kol-cal-nav-btn"><i class="fa fa-chevron-left"></i></a>
                                    <a href="{{ route('kol', array_merge(request()->except('week'), ['week' => 0])) }}" class="kol-cal-nav-btn">Hari Ini</a>
                                    <a href="{{ route('kol', array_merge(request()->except('week'), ['week' => $weekOffset + 1])) }}" class="kol-cal-nav-btn"><i class="fa fa-chevron-right"></i></a>
                                </div>
                            </div>

                            <div class="kol-cal-grid">
                                @foreach($weekDays as $day)
                                    <div class="kol-cal-dayhead {{ $day['is_today'] ? 'is-today' : '' }}">
                                        <span class="kol-cal-dayname">{{ $day['short_name'] }}</span>
                                        <span class="kol-cal-daynum">{{ $day['day_num'] }}</span>
                                    </div>
                                @endforeach
                                @foreach($weekDays as $day)
                                    @php $dayKols = $kolsByDay->get($day['date'], collect()); @endphp
                                    <div class="kol-cal-cell {{ $day['is_today'] ? 'is-today' : '' }}">
                                        @forelse($dayKols as $row)
                                            @php
                                                $progOpt    = $row->kol_progress    ? \App\Models\RawAffiliate::kolProgressOptions()[$row->kol_progress]    ?? null : null;
                                                $relasiOpt  = $row->kol_status_relasi ? \App\Models\RawAffiliate::kolStatusRelasiOptions()[$row->kol_status_relasi] ?? null : null;
                                                $kategori   = $row->kol_kategori    ? \App\Models\RawAffiliate::kolKategoriOptions()[$row->kol_kategori]    ?? null : null;
                                                $username   = $row->social_instagram ?: ($row->social_tiktok ?: ($row->social_username ?: $row->name));
                                                $platformLbl = $row->social_platform
                                                    ? (\App\Models\RawAffiliate::socialPlatforms()[$row->social_platform] ?? $row->social_platform)
                                                    : ($row->social_instagram ? 'Instagram' : ($row->social_tiktok ? 'TikTok' : ($row->social_youtube ? 'YouTube' : '')));
                                            @endphp
                                            <button type="button" class="kol-cal-card kol-card-trigger"
                                                    data-id="{{ $row->id }}"
                                                    data-username="{{ $username }}"
                                                    data-name="{{ $row->name }}"
                                                    data-followers="{{ $row->followers_count }}"
                                                    data-kategori="{{ $kategori }}"
                                                    data-platform="{{ $platformLbl }}"
                                                    data-feedback="{{ $row->feedback }}"
                                                    data-relasi="{{ $relasiOpt['label'] ?? '' }}"
                                                    data-relasi-color="{{ $relasiOpt['color'] ?? 'neutral' }}"
                                                    data-branch="{{ $row->branch->name ?? '' }}"
                                                    data-progress="{{ $progOpt['label'] ?? '' }}"
                                                    data-progress-key="{{ $row->kol_progress ?? '' }}"
                                                    data-progress-color="{{ $progOpt['color'] ?? 'neutral' }}"
                                                    data-phone="{{ $row->phone }}"
                                                    data-contact="{{ $row->contact_person }}"
                                                    data-rate-card="{{ $row->rate_card }}"
                                                    data-tgl-approach="{{ $row->tanggal_approach ? $row->tanggal_approach->translatedFormat('d M Y') : '' }}"
                                                    data-edit-url="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}">
                                                <div class="kol-cal-card-user">{{ '@' . $username }}</div>
                                                @if($row->followers_count)
                                                    <div class="kol-cal-card-meta">{{ number_format($row->followers_count, 0, ',', '.') }} followers</div>
                                                @endif
                                                @if($row->feedback)
                                                    <div class="kol-cal-card-desc">{{ \Illuminate\Support\Str::limit($row->feedback, 60) }}</div>
                                                @endif
                                                <div class="kol-cal-card-pills">
                                                    @if($relasiOpt)
                                                        <span class="st-pill st-pill--{{ $relasiOpt['color'] }}" style="font-size:10px;">{{ $relasiOpt['label'] }}</span>
                                                    @endif
                                                    @if($row->branch)
                                                        <span class="st-pill st-pill--info" style="font-size:10px;">{{ $row->branch->name }}</span>
                                                    @endif
                                                    <span class="st-pill st-pill--{{ $progOpt['color'] ?? 'neutral' }}"
                                                          data-role="progress"
                                                          style="font-size:10px; {{ $progOpt ? '' : 'display:none;' }}">{{ $progOpt['label'] ?? '' }}</span>
                                                </div>
                                            </button>
                                        @empty
                                            <div class="kol-cal-empty">—</div>
                                        @endforelse
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                    {{-- ─── TABLE ─── --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Username / Nama</th>
                                    <th>Kategori</th>
                                    <th width="14%">Rencana Perawatan</th>
                                    <th>Cabang</th>
                                    <th>Followers</th>
                                    <th>Status Relasi</th>
                                    <th>Tgl Approach</th>
                                    <th>Progress</th>
                                    <th>Platform</th>
                                    <th>Contact</th>
                                    <th width="14%">Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($kols as $key => $row)
                                    <tr data-href="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}" style="cursor:pointer;">
                                        <td class="st-num">{{ $kols->firstItem() + $key }}</td>

                                        {{-- Username / Nama --}}
                                        <td>
                                            @php
                                                $usernameStr = $row->social_instagram
                                                    ?: ($row->social_tiktok
                                                        ?: ($row->social_username ?: null));
                                            @endphp
                                            @if($row->name)
                                                <span class="st-primary" style="font-weight:500;">{{ $row->name }}</span>
                                                @if($usernameStr)
                                                    <br><small style="color:#7a9290; font-size:11px;">{{ '@' . $usernameStr }}</small>
                                                @endif
                                            @elseif($usernameStr)
                                                <span class="st-primary" style="font-weight:500;">{{ '@' . $usernameStr }}</span>
                                            @endif
                                        </td>

                                        {{-- Kategori --}}
                                        <td>
                                            @if($row->kol_kategori)
                                                <span class="st-pill st-pill--info">
                                                    {{ \App\Models\RawAffiliate::kolKategoriOptions()[$row->kol_kategori] ?? $row->kol_kategori }}
                                                </span>
                                            @else
                                                <span style="color:#bbb; font-size:11px;">—</span>
                                            @endif
                                        </td>

                                        {{-- Rencana Perawatan (textarea inline auto-save) --}}
                                        <td onclick="event.stopPropagation();">
                                            <textarea class="kol-rencana-input"
                                                      data-id="{{ $row->id }}"
                                                      data-original="{{ $row->rencana_perawatan }}"
                                                      rows="2"
                                                      maxlength="2000"
                                                      placeholder="Tulis rencana perawatan…"
                                                      oninput="saveKolRencana(this)"
                                                      onclick="event.stopPropagation();">{{ $row->rencana_perawatan }}</textarea>
                                            <small class="kol-rencana-status" data-id="{{ $row->id }}" style="color:#7a9290; font-size:10px;"></small>
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

                                        {{-- Status Relasi (dropdown inline auto-save) --}}
                                        <td onclick="event.stopPropagation();">
                                            <select class="kol-relasi-select"
                                                    data-id="{{ $row->id }}"
                                                    data-original="{{ $row->kol_status_relasi ?? '' }}"
                                                    onchange="changeKolStatusRelasi(this)"
                                                    onclick="event.stopPropagation();">
                                                <option value="" {{ !$row->kol_status_relasi ? 'selected' : '' }}>— Belum diset —</option>
                                                @foreach(\App\Models\RawAffiliate::kolStatusRelasiOptions() as $key => $opt)
                                                    <option value="{{ $key }}" {{ $row->kol_status_relasi === $key ? 'selected' : '' }}>
                                                        {{ $opt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>

                                        {{-- Tanggal Approach --}}
                                        <td style="color:#7a9290;">
                                            @if($row->tanggal_approach)
                                                {{ $row->tanggal_approach->translatedFormat('d M Y') }}
                                            @else
                                                <span style="color:#bbb; font-size:11px;">Belum</span>
                                            @endif
                                        </td>

                                        {{-- Progress --}}
                                        <td>
                                            <select class="kol-progress-select"
                                                    data-id="{{ $row->id }}"
                                                    data-original="{{ $row->kol_progress ?? '' }}"
                                                    data-status="{{ $row->kol_progress ?? '' }}"
                                                    onchange="changeKolProgress(this)">
                                                <option value="" {{ !$row->kol_progress ? 'selected' : '' }}>— Belum diset —</option>
                                                @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                                                    <option value="{{ $key }}" {{ $row->kol_progress === $key ? 'selected' : '' }}>
                                                        {{ $opt['label'] }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>

                                        {{-- Platform --}}
                                        <td>
                                            @if($row->social_platform)
                                                <span class="st-pill st-pill--info" style="font-size:10px;">
                                                    {{ \App\Models\RawAffiliate::socialPlatforms()[$row->social_platform] ?? $row->social_platform }}
                                                </span>
                                            @elseif($row->social_instagram)
                                                <span class="st-pill st-pill--info" style="font-size:10px;">Instagram</span>
                                            @elseif($row->social_tiktok)
                                                <span class="st-pill st-pill--info" style="font-size:10px;">TikTok</span>
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

                                        {{-- Feedback (textarea inline auto-save) --}}
                                        <td onclick="event.stopPropagation();">
                                            <textarea class="kol-feedback-input"
                                                      data-id="{{ $row->id }}"
                                                      data-original="{{ $row->feedback }}"
                                                      rows="2"
                                                      maxlength="5000"
                                                      placeholder="Catatan feedback…"
                                                      oninput="saveKolFeedback(this)"
                                                      onclick="event.stopPropagation();">{{ $row->feedback }}</textarea>
                                            <small class="kol-feedback-status" data-id="{{ $row->id }}" style="color:#7a9290; font-size:10px;"></small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12">
                                            <div class="st-empty-state">
                                                <i class="fa fa-user-slash" aria-hidden="true"></i>
                                                <div class="st-empty-state__title">Belum ada KOL bulan ini</div>
                                                <div class="st-empty-state__desc">
                                                    @if($search || $filterProgress !== 'all' || $filterKategori !== 'all' || $filterBranch !== 'all')
                                                        Coba ubah filter atau kata kunci pencarian.
                                                    @else
                                                        Klik tombol <strong>Tambah KOL</strong> di kanan atas untuk mulai input.
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
                    @endif {{-- end if($view === 'calendar') ... else ... --}}

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ─── KOL DETAIL MODAL (dipakai dari calendar view) ─── --}}
<div class="kol-modal-backdrop" id="kolModalBackdrop">
    <div class="kol-modal" id="kolModal">
        <button type="button" class="kol-modal-close" onclick="closeKolModal()">&times;</button>

        <div class="kol-modal-header">
            <div class="kol-modal-icon"><i class="fa fa-user-friends"></i></div>
            <div class="kol-modal-username" id="kolModalUsername">@username</div>
            <div class="kol-modal-name" id="kolModalName"></div>

            <div class="kol-modal-pills" id="kolModalPills">
                <div class="kol-modal-pill" id="kolModalPillBranch">
                    <span class="kol-modal-pill-label"><i class="fa fa-store"></i> Cabang</span>
                    <span class="kol-modal-pill-value kol-modal-pill-value--branch" id="kolModalPillBranchVal">—</span>
                </div>
                <div class="kol-modal-pill" id="kolModalPillKategori">
                    <span class="kol-modal-pill-label"><i class="fa fa-list-ul"></i> Kategori KOL</span>
                    <span class="kol-modal-pill-value kol-modal-pill-value--kategori" id="kolModalPillKategoriVal">—</span>
                </div>
                <div class="kol-modal-pill" id="kolModalPillPlatform">
                    <span class="kol-modal-pill-label"><i class="fa fa-share-alt"></i> Platform</span>
                    <span class="kol-modal-pill-value kol-modal-pill-value--platform" id="kolModalPillPlatformVal">—</span>
                </div>
                <div class="kol-modal-pill" id="kolModalPillRelasi">
                    <span class="kol-modal-pill-label"><i class="fa fa-user-tag"></i> Status Creator</span>
                    <span class="kol-modal-pill-value" id="kolModalPillRelasiVal">—</span>
                </div>
            </div>
        </div>

        <div class="kol-modal-body">
            <div class="kol-modal-section-title">Properties</div>

            <div class="kol-modal-row" id="kolModalFollowersRow">
                <span class="kol-modal-label"><i class="fa fa-users"></i> Followers</span>
                <span class="kol-modal-value" id="kolModalFollowers">Empty</span>
            </div>
            <div class="kol-modal-row" id="kolModalProgressRow">
                <span class="kol-modal-label"><i class="fa fa-tasks"></i> Progress</span>
                <span class="kol-modal-value">
                    <select id="kolModalProgressSelect"
                            class="kol-progress-select"
                            data-id=""
                            data-original=""
                            data-status=""
                            onchange="changeKolProgress(this)">
                        <option value="">— Belum diset —</option>
                        @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                            <option value="{{ $key }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </span>
            </div>
            <div class="kol-modal-row" id="kolModalTglRow">
                <span class="kol-modal-label"><i class="fa fa-calendar"></i> Tanggal Approach</span>
                <span class="kol-modal-value" id="kolModalTgl">Empty</span>
            </div>
            <div class="kol-modal-row" id="kolModalPhoneRow">
                <span class="kol-modal-label"><i class="fa fa-mobile-alt"></i> No HP</span>
                <span class="kol-modal-value" id="kolModalPhone">Empty</span>
            </div>
        </div>

        <div class="kol-modal-footer">
            <button type="button" class="st-btn-outline-brown" onclick="closeKolModal()">Tutup</button>
            <a href="#" id="kolModalEditUrl" class="st-btn-brand"><i class="fa fa-edit"></i> Edit Detail</a>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .kol-progress-select {
        border: 1px solid transparent;
        padding: 4px 26px 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23495057' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        transition: opacity .15s, transform .15s;
        min-width: 160px;
    }
    .kol-progress-select[data-status=""]                     { background-color: #f8f9fa; color: #6c757d; border-color: #dee2e6; }
    .kol-progress-select[data-status="akan_di_approach"]     { background-color: #e9ecef; color: #495057; border-color: #ced4da; }
    .kol-progress-select[data-status="waiting_confirmation"] { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .kol-progress-select[data-status="no_respon"]            { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .kol-progress-select[data-status="confirm"]              { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .kol-progress-select[data-status="done"]                 { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .kol-progress-select:hover    { opacity: .85; }
    .kol-progress-select:disabled { opacity: .5; cursor: wait; }

    /* ─── Status Relasi inline select ─────────────────── */
    .kol-relasi-select {
        border: 1px solid transparent;
        padding: 4px 26px 4px 12px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%23495057' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        transition: opacity .15s;
        min-width: 150px;
    }
    .kol-relasi-select[data-original=""]                  { background-color: #f8f9fa; color: #6c757d; border-color: #dee2e6; }
    .kol-relasi-select[data-original="baru"]              { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .kol-relasi-select[data-original="pernah_kerja_sama"] { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .kol-relasi-select[data-original="rekomendasi"]       { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .kol-relasi-select:hover    { opacity: .85; }
    .kol-relasi-select:disabled { opacity: .5; cursor: wait; }

    /* ─── View tab toggle ───────────────────────────── */
    .kol-view-tab {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px; border-radius: 8px;
        font-size: 13px; font-weight: 700;
        background: #f4faf9; color: #0e6e6b;
        text-decoration: none; transition: all .15s;
        border: 1px solid #c4ebe9;
    }
    .kol-view-tab:hover { background: #e6f5f4; color: #0e6e6b; text-decoration: none; }
    .kol-view-tab.is-active {
        background: linear-gradient(180deg, #13928e 0%, #0a4f4d 100%);
        color: #fff !important; border-color: #0a4f4d;
    }

    /* ─── Calendar wrap & header ──────────────────── */
    .kol-cal-wrap { padding: 16px; }
    .kol-cal-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 14px; gap: 10px; flex-wrap: wrap;
    }
    .kol-cal-title { font-size: 15px; font-weight: 800; color: #0a4f4d; }
    .kol-cal-title i { color: #13928e; margin-right: 6px; }
    .kol-cal-sub   { font-size: 12px; color: #7a9290; margin-top: 2px; }
    .kol-cal-nav   { display: flex; gap: 6px; }
    .kol-cal-nav-btn {
        padding: 6px 12px; border: 1px solid #c4ebe9; border-radius: 8px;
        background: #fff; color: #0e6e6b; font-size: 12px; font-weight: 700;
        text-decoration: none; transition: all .15s;
    }
    .kol-cal-nav-btn:hover { background: #f4faf9; color: #0e6e6b; text-decoration: none; }

    /* ─── Calendar grid (Mon-Sat = 6 cols) ───────── */
    .kol-cal-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0;
        border: 1px solid #c4ebe9;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }
    .kol-cal-dayhead {
        padding: 10px 12px;
        background: #f4faf9;
        border-right: 1px solid #c4ebe9;
        border-bottom: 2px solid #c4ebe9;
        display: flex; flex-direction: column;
    }
    .kol-cal-dayhead:last-child { border-right: none; }
    .kol-cal-dayhead.is-today { background: #e6f5f4; }
    .kol-cal-dayname {
        font-size: 11px; font-weight: 800; color: #0e6e6b;
        text-transform: uppercase; letter-spacing: 1px;
    }
    .kol-cal-daynum {
        font-size: 18px; font-weight: 800; color: #0a4f4d;
        margin-top: 2px;
    }
    .kol-cal-dayhead.is-today .kol-cal-daynum {
        color: #fff;
        background: #13928e;
        border-radius: 50%;
        width: 28px; height: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 14px;
    }
    .kol-cal-cell {
        min-height: 180px; padding: 8px;
        border-right: 1px solid #c4ebe9;
        border-bottom: 1px solid #c4ebe9;
        display: flex; flex-direction: column; gap: 6px;
    }
    .kol-cal-cell:nth-child(6n+6) { border-right: none; } /* last col of body row */
    .kol-cal-cell:last-child { border-right: none; border-bottom: none; }
    .kol-cal-cell.is-today { background: #f4faf9; }

    /* ─── KOL card di calendar ─────────────────────── */
    .kol-cal-card {
        text-align: left; cursor: pointer;
        background: #fff; border: 1px solid #c4ebe9; border-left: 3px solid #13928e;
        border-radius: 8px; padding: 8px 10px;
        font-family: 'Montserrat', sans-serif;
        transition: all .15s;
        width: 100%;
    }
    .kol-cal-card:hover {
        background: #f4faf9; transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(19,146,142,.12);
    }
    .kol-cal-card-user { font-size: 12.5px; font-weight: 800; color: #0a4f4d; }
    .kol-cal-card-meta { font-size: 11px; color: #7a9290; margin-top: 2px; }
    .kol-cal-card-desc {
        font-size: 11px; color: #495057; margin-top: 4px;
        line-height: 1.35;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .kol-cal-card-pills { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 6px; }
    .kol-cal-empty { color: #c4ebe9; font-size: 18px; text-align: center; padding-top: 20px; }

    /* ─── Modal KOL detail ──────────────────────────── */
    .kol-modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,0.55);
        display: none; align-items: center; justify-content: center;
        z-index: 9999; padding: 20px;
    }
    .kol-modal-backdrop.is-open { display: flex; }
    .kol-modal {
        background: #fff; border-radius: 14px; max-width: 520px; width: 100%;
        max-height: 90vh; overflow-y: auto; position: relative;
        box-shadow: 0 12px 48px rgba(0,0,0,0.30);
        font-family: 'Montserrat', sans-serif;
    }
    .kol-modal-close {
        position: absolute; top: 10px; right: 14px;
        background: rgba(255,255,255,0.85); border: none;
        width: 28px; height: 28px; border-radius: 50%;
        font-size: 18px; color: #495057; cursor: pointer; line-height: 1;
        display: flex; align-items: center; justify-content: center;
        z-index: 5;
    }
    .kol-modal-close:hover { background: #fff; color: #0a4f4d; }

    /* Header section — icon + username + name + pills */
    .kol-modal-header {
        padding: 28px 28px 20px;
        background: linear-gradient(180deg, #f4faf9 0%, #ffffff 100%);
        border-bottom: 1px solid #e6f0ef;
        border-radius: 14px 14px 0 0;
    }
    .kol-modal-icon {
        width: 56px; height: 56px; border-radius: 12px;
        background: #e6f5f4; color: #13928e;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 26px; margin-bottom: 12px;
    }
    .kol-modal-username { font-size: 22px; font-weight: 800; color: #0a4f4d; line-height: 1.2; }
    .kol-modal-name { font-size: 12px; color: #7a9290; margin-top: 2px; }

    /* Pill row di header (Cabang / Kategori KOL / Platform / Status Creator) */
    .kol-modal-pills {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 18px;
        margin-top: 16px;
    }
    .kol-modal-pill {
        display: flex; flex-direction: column; gap: 4px;
        min-width: 0;
    }
    .kol-modal-pill-label {
        font-size: 11px; color: #7a9290; font-weight: 600;
        display: inline-flex; align-items: center; gap: 6px;
    }
    .kol-modal-pill-label i { color: #13928e; font-size: 11px; }
    .kol-modal-pill-value {
        display: inline-flex; align-items: center;
        font-size: 12px; font-weight: 600; padding: 3px 10px;
        border-radius: 4px; width: fit-content;
        background: #f1f3f5; color: #495057;
    }
    .kol-modal-pill-value--branch   { background: #fdebee; color: #b02a37; }
    .kol-modal-pill-value--kategori { background: #d4edda; color: #155724; }
    .kol-modal-pill-value--platform { background: #e7d8fd; color: #553c9a; }

    /* Body — properties list */
    .kol-modal-body { padding: 18px 28px; }
    .kol-modal-section-title {
        font-size: 11px; color: #7a9290; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 10px;
    }
    .kol-modal-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 8px 0; gap: 14px;
        min-height: 32px;
    }
    .kol-modal-label {
        font-size: 13px; color: #6c757d; font-weight: 500;
        min-width: 170px;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .kol-modal-label i { color: #adb5bd; width: 14px; text-align: center; }
    .kol-modal-value {
        font-size: 13px; color: #212529; font-weight: 500;
        text-align: left; flex: 1;
    }
    .kol-modal-value--empty { color: #adb5bd; font-weight: 400; }

    .kol-modal-footer {
        padding: 14px 28px; border-top: 1px solid #e6f0ef;
        display: flex; justify-content: flex-end; gap: 8px;
        background: #f4faf9; border-radius: 0 0 14px 14px;
    }
    @media (max-width: 768px) {
        .kol-cal-grid { grid-template-columns: repeat(2, 1fr); }
    }

    /* Inline textarea — Rencana Perawatan & Feedback */
    .kol-rencana-input,
    .kol-feedback-input {
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
    .kol-rencana-input:focus,
    .kol-feedback-input:focus {
        outline: none;
        border-color: #0a4f4d;
        box-shadow: 0 0 0 2px rgba(10, 79, 77, .15);
    }
    .kol-rencana-input.is-saving,
    .kol-feedback-input.is-saving { border-color: #f0ad4e; }
    .kol-rencana-input.is-saved,
    .kol-feedback-input.is-saved  { border-color: #28a745; }
    .kol-rencana-input.is-error,
    .kol-feedback-input.is-error  { border-color: #dc3545; }
    .kol-rencana-status,
    .kol-feedback-status { display:block; margin-top:2px; }
</style>
@endsection

@section('js')
<script>
    var kolProgressOptions = @json(\App\Models\RawAffiliate::kolProgressOptions());

    /* Generic inline textarea auto-save (debounce 700ms) — dipakai utk Rencana Perawatan & Feedback */
    var kolFieldTimers = {};
    function saveKolInlineField(el, opts) {
        var id = el.dataset.id;
        var key = opts.field + ':' + id;
        var statusEl = opts.statusEl;
        clearTimeout(kolFieldTimers[key]);

        el.classList.remove('is-saved', 'is-error');
        el.classList.add('is-saving');
        if (statusEl) statusEl.textContent = 'Menyimpan…';

        kolFieldTimers[key] = setTimeout(function () {
            var val = el.value;
            if (val === el.dataset.original) {
                el.classList.remove('is-saving');
                if (statusEl) statusEl.textContent = '';
                return;
            }
            var payload = { _token: '{{ csrf_token() }}' };
            payload[opts.field] = val;
            $.ajax({
                url: '/kol/' + id + '/' + opts.urlSuffix,
                method: 'PATCH',
                data: payload,
                success: function (res) {
                    el.dataset.original = res[opts.field] || '';
                    el.classList.remove('is-saving');
                    el.classList.add('is-saved');
                    if (statusEl) statusEl.textContent = 'Tersimpan';
                    setTimeout(function () {
                        el.classList.remove('is-saved');
                        if (statusEl) statusEl.textContent = '';
                    }, 1500);
                },
                error: function () {
                    el.classList.remove('is-saving');
                    el.classList.add('is-error');
                    if (statusEl) statusEl.textContent = 'Gagal — coba lagi';
                }
            });
        }, 700);
    }

    function saveKolRencana(el) {
        saveKolInlineField(el, {
            field: 'rencana_perawatan',
            urlSuffix: 'rencana-perawatan',
            statusEl: document.querySelector('.kol-rencana-status[data-id="' + el.dataset.id + '"]'),
        });
    }

    function saveKolFeedback(el) {
        saveKolInlineField(el, {
            field: 'feedback',
            urlSuffix: 'feedback',
            statusEl: document.querySelector('.kol-feedback-status[data-id="' + el.dataset.id + '"]'),
        });
    }

    /* Status Relasi — inline dropdown auto-save */
    function changeKolStatusRelasi(el) {
        var id        = el.dataset.id;
        var newStatus = el.value;
        var origStatus = el.dataset.original;
        if (newStatus === origStatus) return;

        el.disabled = true;
        $.ajax({
            url: '/kol/' + id + '/status-relasi',
            method: 'PATCH',
            data: { _token: '{{ csrf_token() }}', kol_status_relasi: newStatus },
            success: function (res) {
                el.dataset.original = res.kol_status_relasi || '';
                el.disabled = false;
            },
            error: function () {
                el.value = origStatus;
                el.disabled = false;
                Swal.fire({ title: 'Gagal', text: 'Gagal update status relasi. Coba lagi.', type: 'error' });
            }
        });
    }

    function changeKolProgress(el) {
        var id          = el.dataset.id;
        var newStatus   = el.value;
        var origStatus  = el.dataset.original;
        if (newStatus === origStatus) return;
        if (!newStatus) { el.value = origStatus; return; }

        el.disabled = true;
        $.ajax({
            url: '/kol/' + id + '/progress',
            method: 'PATCH',
            data: { _token: '{{ csrf_token() }}', progress: newStatus },
            success: function (res) {
                el.dataset.original = res.progress;
                el.dataset.status   = res.progress;
                el.disabled = false;
                syncKolProgressUI(id, res.progress, res.label, res.color);
            },
            error: function () {
                el.value = origStatus;
                el.disabled = false;
                Swal.fire({ title: 'Gagal', text: 'Gagal update progress. Coba lagi.', type: 'error' });
            }
        });
    }

    /* Sinkronkan UI di mana saja yang merefer KOL id sama:
       - Dropdown lain (table row / modal) — set value, dataset
       - Card kalender — update data-* attrs + visible pill */
    function syncKolProgressUI(id, statusKey, label, color) {
        document.querySelectorAll('.kol-progress-select[data-id="' + id + '"]').forEach(function (sel) {
            sel.value = statusKey || '';
            sel.dataset.original = statusKey || '';
            sel.dataset.status   = statusKey || '';
        });

        var card = document.querySelector('.kol-card-trigger[data-id="' + id + '"]');
        if (card) {
            card.dataset.progress      = label || '';
            card.dataset.progressKey   = statusKey || '';
            card.dataset.progressColor = color || 'neutral';

            var pill = card.querySelector('.st-pill[data-role="progress"]');
            if (pill) {
                if (label) {
                    pill.className = 'st-pill st-pill--' + (color || 'neutral');
                    pill.style.cssText = 'font-size:10px;';
                    pill.textContent = label;
                } else {
                    pill.style.display = 'none';
                    pill.textContent = '';
                }
            }
        }
    }

    /* ─── Modal KOL Detail (untuk calendar view) ────────────────── */
    function openKolModal(btn) {
        var d = btn.dataset;

        document.getElementById('kolModalUsername').textContent = '@' + (d.username || '-');
        var nameEl = document.getElementById('kolModalName');
        if (d.name) { nameEl.textContent = d.name; nameEl.style.display = ''; }
        else        { nameEl.style.display = 'none'; }

        // Header pill row (Cabang | Kategori | Platform | Status Creator)
        var setHeaderPill = function(rowId, valId, val) {
            var row = document.getElementById(rowId);
            var el  = document.getElementById(valId);
            if (val && val !== '') {
                row.style.display = '';
                el.textContent = val;
            } else {
                row.style.display = 'none';
            }
        };
        setHeaderPill('kolModalPillBranch',   'kolModalPillBranchVal',   d.branch);
        setHeaderPill('kolModalPillKategori', 'kolModalPillKategoriVal', d.kategori);
        setHeaderPill('kolModalPillPlatform', 'kolModalPillPlatformVal', d.platform);
        setHeaderPill('kolModalPillRelasi',   'kolModalPillRelasiVal',   d.relasi);

        // Properties — show "Empty" placeholder when value missing (mengikuti referensi)
        var setProp = function(valId, val) {
            var el = document.getElementById(valId);
            if (val && val !== '') {
                el.textContent = val;
                el.classList.remove('kol-modal-value--empty');
            } else {
                el.textContent = 'Empty';
                el.classList.add('kol-modal-value--empty');
            }
        };
        setProp('kolModalFollowers', d.followers ? Number(d.followers).toLocaleString('id-ID') + ' followers' : '');
        setProp('kolModalTgl',       d.tglApproach);
        setProp('kolModalPhone',     d.phone);

        // Progress dropdown — sync value, data-id, data-original, data-status
        // Setelah update via changeKolProgress, table row dan modal disinkronkan oleh handler.
        var progSel = document.getElementById('kolModalProgressSelect');
        var progKey = d.progressKey || '';
        progSel.dataset.id       = d.id || '';
        progSel.value            = progKey;
        progSel.dataset.original = progKey;
        progSel.dataset.status   = progKey;
        progSel.disabled         = false;

        document.getElementById('kolModalEditUrl').href = d.editUrl || '#';
        document.getElementById('kolModalBackdrop').classList.add('is-open');
    }

    function closeKolModal() {
        document.getElementById('kolModalBackdrop').classList.remove('is-open');
    }

    document.addEventListener('click', function(e) {
        var trigger = e.target.closest('.kol-card-trigger');
        if (trigger) { e.preventDefault(); openKolModal(trigger); return; }
        // Close kalau klik backdrop (di luar modal)
        if (e.target.id === 'kolModalBackdrop') closeKolModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeKolModal();
    });
</script>
@endsection
