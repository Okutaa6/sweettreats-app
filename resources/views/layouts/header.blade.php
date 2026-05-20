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

    // ─── Dynamic greeting subtitle berdasarkan waktu ─────────────────────
    $stNowMinutes = (int) (\Carbon\Carbon::now()->hour * 60 + \Carbon\Carbon::now()->minute);
    if ($stNowMinutes >= 300 && $stNowMinutes < 720) {          // 05:00–11:59
        $stGreetPrefix = 'Selamat pagi,';
        $stGreetSuffix = 'Semoga harimu menyenangkan!';
    } elseif ($stNowMinutes >= 720 && $stNowMinutes < 800) {    // 12:00–13:19
        $stGreetPrefix = 'Selamat siang,';
        $stGreetSuffix = 'Jangan lupa istirahat ya!';
    } elseif ($stNowMinutes >= 800 && $stNowMinutes < 900) {    // 13:20–14:59
        $stGreetPrefix = 'Selamat siang,';
        $stGreetSuffix = 'Yuk Lanjut Kerja!';
    } elseif ($stNowMinutes >= 900 && $stNowMinutes < 1080) {   // 15:00–17:59
        $stGreetPrefix = 'Selamat sore,';
        $stGreetSuffix = 'Sudah minum air putih belum?';
    } else {                                                     // 18:00–04:59
        $stGreetPrefix = 'Selamat malam,';
        $stGreetSuffix = 'Terima kasih sudah kerja keras hari ini!';
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
            <div class="st-greeting-text">
                <div class="st-greeting-title">
                    Selamat Datang di <span class="st-greeting-accent">Data Center</span>
                    <span class="st-greeting-wave">👋</span>
                </div>
                <div class="st-greeting-sub" id="stGreetingSub" data-username="{{ $stUserName }}">
                    {{ $stGreetPrefix }} <strong>{{ $stUserName }}</strong>. {{ $stGreetSuffix }}
                </div>
            </div>
        </div>
        <script>
        (function () {
            var el = document.getElementById('stGreetingSub');
            if (!el) return;
            var userName = el.dataset.username || 'User';

            function escUser(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
                                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function renderGreeting() {
                var now  = new Date();
                var mins = now.getHours() * 60 + now.getMinutes();
                var prefix, suffix;
                if      (mins >= 300 && mins < 720)  { prefix = 'Selamat pagi,';  suffix = 'Semoga harimu menyenangkan!'; }
                else if (mins >= 720 && mins < 800)  { prefix = 'Selamat siang,'; suffix = 'Jangan lupa istirahat ya!'; }
                else if (mins >= 800 && mins < 900)  { prefix = 'Selamat siang,'; suffix = 'Yuk Lanjut Kerja!'; }
                else if (mins >= 900 && mins < 1080) { prefix = 'Selamat sore,';  suffix = 'Sudah minum air putih belum?'; }
                else                                  { prefix = 'Selamat malam,'; suffix = 'Terima kasih sudah kerja keras hari ini!'; }
                el.innerHTML = prefix + ' <strong>' + escUser(userName) + '</strong>. ' + suffix;
            }

            renderGreeting();              // sinkronkan ke jam browser saat load
            setInterval(renderGreeting, 60000); // refresh tiap 60 detik
        })();
        </script>

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
