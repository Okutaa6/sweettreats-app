@php
    $stUserName  = session('name') ?? 'User';
    $stInitial   = strtoupper(mb_substr($stUserName, 0, 1));

    $stBranchName = session('raw_branch_id') ? optional(\App\Models\RawBranch::find(session('raw_branch_id')))->name : null;

    $stPageTitle = getActivePageName();
    $hariList    = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $stNow       = \Carbon\Carbon::now();
    $stDateLabel = $hariList[$stNow->dayOfWeek] . ', ' . $stNow->format('d M Y');
    $stPageSub   = ($stBranchName ? ('Cabang ' . $stBranchName . ' · ') : '') . $stDateLabel . ' · Hari Ini';
@endphp

<header class="app-header">

    {{-- Toggle: mobile → drawer (hamburger), desktop → collapse (chevron) --}}
    <button type="button" class="apphd-toggle js-sbtoggle" id="apphdToggle" aria-label="Tampilkan/sembunyikan menu" title="Menu">
        <svg class="ic-ham" width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M3 5h12M3 9h12M3 13h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
        <svg class="ic-chev" width="18" height="18" viewBox="0 0 18 18" fill="none">
            <path d="M12 4l-6 5 6 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15.5 3.5v11" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
        </svg>
    </button>

    {{-- Judul + subtitle --}}
    <div class="apphd-titles">
        <div class="apphd-title">{{ $stPageTitle }}</div>
        <div class="apphd-sub">{{ $stPageSub }}</div>
    </div>

    {{-- Aksi kanan --}}
    <div class="apphd-actions">
        <div class="dropdown" id="notifBell">
            <button type="button" class="apphd-bell dropdown-toggle no-caret" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <path d="M9 2.5a4 4 0 0 0-4 4c0 3.6-1.5 5-1.5 5h11S13 10.1 13 6.5a4 4 0 0 0-4-4ZM7.3 14a1.9 1.9 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
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

        <div class="apphd-avatar">{{ $stInitial }}</div>
    </div>
</header>
