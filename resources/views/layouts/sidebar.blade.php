@php
    $sbUserName = session('name') ?? 'User';
    $sbUserRole = optional(\App\Models\GroupPath::find(session('group_id')))->nama ?? 'User';
    $sbInitial  = strtoupper(mb_substr($sbUserName, 0, 1));
    $sbBranch   = session('raw_branch_id') ? optional(\App\Models\RawBranch::find(session('raw_branch_id')))->name : null;
    $sbBrandSub = $sbBranch ? ('Cabang ' . $sbBranch) : 'Dental · 5 Cabang';

    $sbSections = shellMenuSections();
    $sbAccount  = shellAccountLinks();
@endphp

<aside class="left-sidenav sbx">

    {{-- Brand --}}
    <div class="sbx-brand">
        <div class="sbx-brand-logo"><img src="{{ asset('sysprofile/' . session('syslogo')) }}" alt="logo"></div>
        <div class="sbx-brand-tx">
            <div class="sbx-brand-name">Sweet Treats</div>
            <div class="sbx-brand-sub">{{ $sbBrandSub }}</div>
        </div>
    </div>

    {{-- Menu (per-role, scroll) --}}
    <nav class="sbx-menu">
        @forelse($sbSections as $sec)
            <div class="sbx-sec-label">{{ $sec['label'] }}</div>
            @foreach($sec['items'] as $it)
                @php
                    // Badge Kesehatan (Pusat Akun) — jumlah temuan detektor, cached 10 mnt.
                    $sbBadge = ($it['link'] ?? '') === 'setkesehatan'
                        ? \App\Http\Controllers\Set\KesehatanController::totalCached() : 0;
                @endphp
                <a class="sbx-item {{ $it['active'] ? 'active' : '' }}" href="{{ url($it['link']) }}" title="{{ $it['nama'] }}">
                    <span class="sbx-item-ic">@if($it['icon'])<i class="{{ $it['icon'] }}"></i>@endif</span>
                    <span class="sbx-item-tx">{{ $it['nama'] }}</span>
                    @if($sbBadge)<span class="sbx-drop-badge">{{ $sbBadge > 99 ? '99+' : $sbBadge }}</span>@endif
                </a>
            @endforeach
        @empty
        @endforelse
    </nav>

    {{-- Akun + drop-up --}}
    <div class="sbx-account-wrap">
        <div class="sbx-dropup" id="sbxDropup" hidden>
            @foreach($sbAccount as $a)
                <a class="sbx-drop-item" href="{{ url($a['link']) }}">
                    <span class="sbx-drop-ic"><i class="{{ $a['icon'] }}"></i></span>
                    <span class="sbx-drop-tx">{{ $a['label'] }}</span>
                    @if($a['badge'])<span class="sbx-drop-badge">{{ $a['badge'] > 9 ? '9+' : $a['badge'] }}</span>@endif
                </a>
            @endforeach
            @if(count($sbAccount))<div class="sbx-drop-div"></div>@endif
            <a class="sbx-drop-item" href="{{ url('ganti-password') }}"><span class="sbx-drop-ic"><i class="fas fa-key"></i></span><span class="sbx-drop-tx">Ganti kata sandi</span></a>
            <a class="sbx-drop-item sbx-drop-danger" href="{{ route('signout') }}"><span class="sbx-drop-ic"><i class="fas fa-sign-out-alt"></i></span><span class="sbx-drop-tx">Keluar</span></a>
        </div>

        <button type="button" class="sbx-account" id="sbxAccountBtn" aria-haspopup="true" aria-expanded="false">
            <span class="sbx-avatar">{{ $sbInitial }}</span>
            <span class="sbx-acc-meta">
                <span class="sbx-acc-name">{{ $sbUserName }}</span>
                <span class="sbx-acc-role">{{ $sbUserRole }}</span>
            </span>
            <span class="sbx-acc-caret"><i class="fas fa-chevron-up"></i></span>
        </button>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var body   = document.body;
    var scrim  = document.getElementById('sbxScrim');
    var accBtn = document.getElementById('sbxAccountBtn');
    var dropup = document.getElementById('sbxDropup');
    var toggles = document.querySelectorAll('.js-sbtoggle');
    var mq     = window.matchMedia('(max-width:1024px)');
    var isMobile = function () { return mq.matches; };

    function closeDrawer() { body.classList.remove('sbx-drawer-open'); body.style.overflow = ''; }
    function openDrawer()  { body.classList.add('sbx-drawer-open'); body.style.overflow = 'hidden'; }
    function applyCollapse(on) { body.classList.toggle('sbx-collapsed', on); localStorage.setItem('sbxCollapsed', on ? '1' : '0'); }
    applyCollapse(localStorage.getItem('sbxCollapsed') === '1');

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            if (isMobile()) { body.classList.contains('sbx-drawer-open') ? closeDrawer() : openDrawer(); }
            else { applyCollapse(!body.classList.contains('sbx-collapsed')); }
        });
    });
    if (scrim) scrim.addEventListener('click', closeDrawer);

    function closeDrop() { if (dropup) { dropup.hidden = true; accBtn.classList.remove('open'); accBtn.setAttribute('aria-expanded', 'false'); } }
    if (accBtn) accBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = dropup.hidden;
        dropup.hidden = !willOpen;
        accBtn.classList.toggle('open', willOpen);
        accBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (dropup && !dropup.hidden && !dropup.contains(e.target) && !accBtn.contains(e.target)) closeDrop();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); closeDrop(); } });
    mq.addEventListener('change', function () { closeDrawer(); closeDrop(); });
});
</script>
