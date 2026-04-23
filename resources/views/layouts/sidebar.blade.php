

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
        const hasIconBar = !document.querySelector('.left-sidenav.no-icon-bar');
        const STATES = hasIconBar
            ? ['normal', 'hidden']
            : ['normal', 'hidden'];

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

        btn.addEventListener('click', function () {
            // Saat sudah hidden, klik lagi = kembali ke normal (sidebar muncul)
            if (current === 'hidden') {
                current = 'normal';
            } else {
                const idx = STATES.indexOf(current);
                current = STATES[(idx + 1) % STATES.length];
            }
            applyState(current);
        });
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