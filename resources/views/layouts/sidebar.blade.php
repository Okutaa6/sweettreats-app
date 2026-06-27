

@php
    // Identitas user untuk chip akun di footer sidebar (dipindah dari header).
    $sbUserName = session('name') ?? 'User';
    $sbUserRole = optional(\App\Models\GroupPath::find(session('group_id')))->nama ?? 'User';
    $sbInitial  = strtoupper(mb_substr($sbUserName, 0, 1));
@endphp

<div class="left-sidenav {{ hasMultipleParents() ? '' : 'no-icon-bar' }}">
    @if(hasMultipleParents())
    <div class="main-icon-menu">
        <nav class="nav">
            {!! getSidebarParent() !!}
        </nav>
    </div>
    @endif

    <div class="main-menu-inner" id="mainMenuInner">
        <div class="menu-body slimscroll">
            {!! getSidebarChild() !!}
        </div>

        {{-- Chip akun (satu-satunya tempat identitas user; ikut design) --}}
        <div class="st-side-account">
            <div class="st-side-avatar">{{ $sbInitial }}</div>
            <div class="st-side-acc-meta">
                <div class="st-side-acc-name">{{ $sbUserName }}</div>
                <div class="st-side-acc-role">{{ $sbUserRole }}</div>
            </div>
            <a href="{{ route('signout') }}" class="st-side-logout" title="Logout" aria-label="Logout">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </a>
        </div>
    </div>

    <!-- Tombol Collapse -->
    <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Toggle Sidebar">
        <i class="fas fa-chevron-left"></i>
    </button>
</div>

<script>
    (function () {
        const btn = document.getElementById('sidebarCollapseBtn');
        const menu = document.getElementById('mainMenuInner');
        const body = document.body;

        // Untuk user single-parent (no-icon-bar), skip state 'collapsed' karena icon bar sudah tidak ada
        // Collapse selalu ke icon-only (≈74px, ala design) — berlaku utk icon-bar
        // (rail tersisa) maupun no-icon-bar (kolom menyusut, label disembunyikan).
        const STATES = ['normal', 'collapsed'];

        function applyState(state) {
            menu.classList.toggle('collapsed', state === 'collapsed' || state === 'hidden');
            body.classList.toggle('sidebar-collapsed', state === 'collapsed');
            body.classList.toggle('sidebar-hidden', state === 'hidden');
            btn.classList.toggle('is-collapsed', state === 'collapsed');
            localStorage.setItem('sidebarState', state);
        }

        let current = localStorage.getItem('sidebarState');
        if (!STATES.includes(current)) {
            // backward compat: lama menyimpan sidebarCollapsed=true/false
            current = localStorage.getItem('sidebarCollapsed') === 'true' ? 'collapsed' : 'normal';
        }
        applyState(current);

        function toggle() {
            // Saat sudah hidden, klik lagi = kembali ke normal (sidebar muncul)
            if (current === 'hidden') {
                current = 'normal';
            } else {
                const idx = STATES.indexOf(current);
                current = STATES[(idx + 1) % STATES.length];
            }
            applyState(current);
        }
        btn.addEventListener('click', toggle);

        // Hamburger di header memicu toggle yang sama
        const hamburger = document.getElementById('headerHamburger');
        if (hamburger) hamburger.addEventListener('click', toggle);
    })();

    // Parent switcher: klik icon parent → ganti pane child yang ditampilkan
    (function () {
        const parentLinks = document.querySelectorAll('.main-icon-menu .nav-link[data-parent-target]');
        if (!parentLinks.length) return;

        parentLinks.forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = link.getAttribute('data-parent-target');

                parentLinks.forEach(function (l) { l.classList.remove('active'); });
                link.classList.add('active');

                document.querySelectorAll('.main-menu-inner .main-icon-menu-pane')
                    .forEach(function (pane) { pane.classList.remove('active'); });
                const target = document.getElementById(targetId);
                if (target) target.classList.add('active');
            });
        });
    })();
</script>