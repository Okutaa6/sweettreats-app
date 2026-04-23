@php
    $stUserName  = session('name') ?? 'User';
    $stUserRole  = optional(\App\Models\GroupPath::find(session('group_id')))->nama ?? 'User';
    $stInitial   = strtoupper(mb_substr($stUserName, 0, 1));
    $stProfile   = \App\Models\SysProfile::first();
    $stSysName   = $stProfile->systitle ?? 'Sweet Treats';   // Dashboard Title
    $stSysTitle  = $stProfile->sysname  ?? 'Dental Clinic';  // Institution Name/Title

    $stIsComplaintAdmin = in_array(session('group_name'), ['Super Admin', 'Admin General'], true);
    $stIsComplaintSales = session('group_name') === 'Sales';

    if ($stIsComplaintAdmin) {
        // Admin: badge = jumlah keluhan yang belum ditangani (status != handled)
        $stComplaintBadge = \App\Models\Complaint::where('status', '!=', \App\Models\Complaint::STATUS_HANDLED)->count();
        $stComplaintTitle = 'Keluhan Sales';
    } elseif ($stIsComplaintSales) {
        // Sales: badge = jumlah keluhan miliknya yang belum ditangani
        $stComplaintBadge = \App\Models\Complaint::where('sender_user_id', session('id'))
            ->where('status', '!=', \App\Models\Complaint::STATUS_HANDLED)
            ->count();
        $stComplaintTitle = 'Riwayat Keluhan Saya';
    } else {
        $stComplaintBadge = 0;
        $stComplaintTitle = null;
    }
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
                <div class="st-brand-sub">{{ $stSysTitle }}</div>
            </div>
        </a>

        {{-- Greeting --}}
        <div class="st-greeting">
            <button type="button" id="stLoveBtn" class="st-love-icon" title="Suka">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>
            <script>
            (function () {
                const btn = document.getElementById('stLoveBtn');
                if (!btn) return;
                if (localStorage.getItem('stLoveActive') === '1') btn.classList.add('is-active');
                btn.addEventListener('click', function () {
                    const active = btn.classList.toggle('is-active');
                    localStorage.setItem('stLoveActive', active ? '1' : '0');
                });
            })();
            </script>
            <span>Semangat ya kerjanya, <strong>{{ $stUserName }}</strong></span>
        </div>

        {{-- Right actions --}}
        <div class="st-actions">

            {{-- Complaint inbox (Admin: semua keluhan; Sales: riwayat miliknya) --}}
            @if ($stIsComplaintAdmin || $stIsComplaintSales)
                <a href="{{ route('complaint.index') }}" class="st-icon-btn" title="{{ $stComplaintTitle }}" style="position:relative;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    @if ($stComplaintBadge > 0)
                        <span class="st-icon-badge">{{ $stComplaintBadge > 99 ? '99+' : $stComplaintBadge }}</span>
                    @endif
                </a>
            @endif

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

            {{-- User profile --}}
            <div class="dropdown st-user-wrap">
                <a href="#" class="st-user dropdown-toggle no-caret" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <div class="st-user-avatar">{{ $stInitial }}</div>
                    <div class="st-user-meta">
                        <div class="st-user-name">{{ $stUserName }}</div>
                        <div class="st-user-role">{{ $stUserRole }}</div>
                    </div>
                    <i class="mdi mdi-chevron-down st-user-caret"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right st-user-menu">
                    <a class="st-user-menu-item" href="{{ route('signout') }}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Logout
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
