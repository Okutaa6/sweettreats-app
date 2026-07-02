@php
    $stUserName  = session('name') ?? 'User';
    $stUserRole  = optional(\App\Models\GroupPath::find(session('group_id')))->nama ?? 'User';
    $stInitial   = strtoupper(mb_substr($stUserName, 0, 1));
    $stProfile   = \App\Models\SysProfile::first();
    $stSysName   = $stProfile->systitle ?? 'Sweet Treats';   // Dashboard Title
    $stSysTitle  = $stProfile->sysname  ?? 'Dental Clinic';  // Institution Name/Title

    // ─── Cabang user (untuk brand sub + subtitle header) ────────────────
    // Sales dikunci ke cabangnya via session('raw_branch_id'); admin = semua cabang.
    $stBranchName = null;
    if ($stBid = session('raw_branch_id')) {
        $stBranchName = optional(\App\Models\RawBranch::find($stBid))->name;
    }
    $stBrandSub = $stBranchName ? ('Cabang ' . $stBranchName) : 'Semua Cabang';

    // ─── Judul halaman + subtitle untuk header ──────────────────────────
    $stPageTitle = getActivePageName();
    $hariList    = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $stNow       = \Carbon\Carbon::now();
    $stDateLabel = $hariList[$stNow->dayOfWeek] . ', ' . $stNow->format('d M Y');
    $stPageSub   = ($stBranchName ? ('Cabang ' . $stBranchName . ' · ') : '') . $stDateLabel . ' · Hari Ini';
@endphp

<div class="topbar st-topbar">
    <div class="st-header-card">

        {{-- Logo + brand --}}
        <a class="st-brand">
            <div class="st-brand-logo">
                <img src="{{ asset('sysprofile/' . session('syslogo')) }}" alt="logo">
            </div>
            <div class="st-brand-text">
                <div class="st-brand-name">{{ $stSysName }}</div>
                <div class="st-brand-sub">{{ $stBrandSub }}</div>
            </div>
        </a>

        {{-- Hamburger toggle sidebar --}}
        <button type="button" class="st-hamburger" id="headerHamburger" aria-label="Toggle sidebar" title="Tampilkan/sembunyikan menu">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        {{-- Judul halaman + subtitle (ganti greeting; ikut design) --}}
        <div class="st-pagehead">
            <div class="st-pagehead-title">{{ $stPageTitle }}</div>
            <div class="st-pagehead-sub">{{ $stPageSub }}</div>
        </div>

        {{-- Right actions --}}
        <div class="st-actions">

            {{-- Notification bell --}}
            <div class="dropdown" id="notifBell">
                <button type="button" class="st-icon-btn dropdown-toggle no-caret"
                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span id="notifCount" class="st-icon-badge" style="display:none;">0</span>
                </button>
                <div class="dropdown-menu dropdown-menu-right" style="width:340px;max-height:400px;overflow-y:auto;padding:0;">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="background:#FAF3E8; border-bottom:1px solid #F5E6D0;">
                        <strong style="font-size:0.85rem; color:#3D1A00;">Notifikasi</strong>
                        <a href="#" id="markAllRead" style="font-size:0.75rem;">Tandai semua dibaca</a>
                    </div>
                    <div id="notifList">
                        <div class="text-center text-muted py-3" style="font-size:0.8rem;">Memuat...</div>
                    </div>
                </div>
            </div>

            {{-- Identitas user dipindah ke footer sidebar (lihat layouts/sidebar.blade.php) --}}

        </div>
    </div>
</div>
