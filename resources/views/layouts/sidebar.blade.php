

<div class="left-sidenav">
    <div class="main-icon-menu">
        <nav class="nav">
                </button>
            {!! getSidebarParent() !!}
         <button class="nav-link button-menu-mobile waves-effect waves-light">
        <i class="dripicons-menu"></i>
        </nav>
    </div>

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

        // Simpan state ke localStorage agar persisten
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            menu.classList.add('collapsed');
            btn.classList.add('is-collapsed');
            body.classList.add('sidebar-collapsed');
        }

        btn.addEventListener('click', function () {
            const collapsed = menu.classList.toggle('collapsed');
            btn.classList.toggle('is-collapsed', collapsed);
            body.classList.toggle('sidebar-collapsed', collapsed);
            localStorage.setItem('sidebarCollapsed', collapsed);
        });
    })();
</script>