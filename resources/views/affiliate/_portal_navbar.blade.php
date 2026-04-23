@php
    $active = $active ?? 'dashboard';
    $currentAffiliate = $affiliate ?? null;
@endphp

<style>
    .sm-header {
        position: sticky; top: 0; z-index: 40;
        background: rgba(255,250,245,0.92);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-bottom: 1px solid rgba(70,21,0,0.08);
        box-shadow: 0 6px 20px rgba(70,21,0,0.04);
    }
    .sm-header-inner { max-width: 1100px; margin: 0 auto; padding: 0 24px; }

    .sm-header-top {
        display: flex; align-items: center; justify-content: space-between;
        height: 72px; gap: 16px;
    }

    .sm-brand {
        display: inline-flex; align-items: center; gap: 12px;
    }
    .sm-brand-mark {
        width: 44px; height: 44px; border-radius: 14px;
        background: linear-gradient(135deg, #461500, #8b3a00);
        color: #fff; display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        box-shadow: 0 6px 16px rgba(70,21,0,0.25);
    }
    .sm-brand-text { display: flex; flex-direction: column; line-height: 1; }
    .sm-brand-name {
        font-size: 1.2rem; font-weight: 900; letter-spacing: 0.3px;
        background: linear-gradient(135deg, #461500, #8b3a00);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .sm-brand-sub {
        font-size: 0.68rem; font-weight: 600; color: #8b7a6b;
        text-transform: uppercase; letter-spacing: 1.2px; margin-top: 4px;
    }

    .sm-header-right { display: flex; align-items: center; gap: 12px; }
    .sm-header-hello {
        font-size: 0.78rem; color: #6b4a30; font-weight: 600; line-height: 1.2;
        text-align: right;
    }
    .sm-header-hello strong {
        display: block; font-size: 0.88rem; font-weight: 900; color: #461500; margin-top: 2px;
    }
    .sm-header-avatar {
        width: 42px; height: 42px; border-radius: 50%;
        overflow: hidden; flex-shrink: 0;
        background: linear-gradient(135deg, #c68a56, #8b3a00);
        color: #fff; font-weight: 800; font-size: 0.95rem;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 4px 12px rgba(70,21,0,0.22);
        border: 2px solid #fff;
    }
    .sm-header-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .sm-header-logout {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 16px; border-radius: 50px;
        border: 2px solid #461500; color: #461500; background: transparent;
        font-size: 0.75rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: 0.5px;
        transition: all 0.3s;
    }
    .sm-header-logout:hover { background: #461500; color: #fff; }

    /* Tab bar attached to header */
    .sm-tabbar {
        display: flex; gap: 4px; overflow-x: auto;
        margin: 0 -6px;
        scrollbar-width: none; -ms-overflow-style: none;
        border-top: 1px solid rgba(70,21,0,0.06);
        padding-top: 4px;
    }
    .sm-tabbar::-webkit-scrollbar { display: none; }
    .sm-tab {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 14px 16px 12px; position: relative;
        color: #8b7a6b; font-size: 0.84rem; font-weight: 700;
        white-space: nowrap; flex-shrink: 0; transition: color 0.2s;
        border-bottom: 3px solid transparent;
    }
    .sm-tab:hover { color: #461500; }
    .sm-tab.active {
        color: #461500;
        border-bottom-color: #461500;
    }
    .sm-tab.active::after {
        content: ''; position: absolute; left: 16px; right: 16px; bottom: -1px;
        height: 3px; border-radius: 3px 3px 0 0;
        background: linear-gradient(135deg, #461500, #8b3a00);
    }
    .sm-tab i { font-size: 0.9rem; }

    @media (max-width: 640px) {
        .sm-header-inner { padding: 0 14px; }
        .sm-header-top { height: 60px; }
        .sm-brand-mark { width: 36px; height: 36px; border-radius: 11px; font-size: 0.95rem; }
        .sm-brand-name { font-size: 1rem; }
        .sm-brand-sub { font-size: 0.6rem; letter-spacing: 1px; }
        .sm-header-hello { display: none; }
        .sm-header-avatar { width: 36px; height: 36px; font-size: 0.85rem; }
        .sm-header-logout span { display: none; }
        .sm-header-logout { padding: 8px 10px; }
        .sm-tab { padding: 12px 12px 10px; font-size: 0.78rem; }
        .sm-tab.active::after { left: 12px; right: 12px; }
    }
</style>

<header class="sm-header">
    <div class="sm-header-inner">

        <div class="sm-header-top">
            <a href="{{ route('affiliate.dashboard') }}" class="sm-brand">
                <span class="sm-brand-mark"><i class="fa-solid fa-heart"></i></span>
                <span class="sm-brand-text">
                    <span class="sm-brand-name">Sweet Mates</span>
                    <span class="sm-brand-sub">by Sweet Treats</span>
                </span>
            </a>

            <div class="sm-header-right">
                @if($currentAffiliate)
                    <div class="sm-header-hello">
                        Halo,
                        <strong>{{ explode(' ', $currentAffiliate->name)[0] }}</strong>
                    </div>
                @endif
                <div class="sm-header-avatar">
                    @if($currentAffiliate && $currentAffiliate->photo)
                        <img src="{{ $currentAffiliate->photo_url }}" alt="avatar">
                    @elseif($currentAffiliate)
                        {{ strtoupper(substr($currentAffiliate->name, 0, 1)) }}
                    @else
                        <i class="fa-solid fa-user"></i>
                    @endif
                </div>
                <a href="{{ route('affiliate.logout') }}" class="sm-header-logout" title="Keluar">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Keluar</span>
                </a>
            </div>
        </div>

        <nav class="sm-tabbar">
            <a href="{{ route('affiliate.dashboard') }}" class="sm-tab {{ $active === 'dashboard' ? 'active' : '' }}">
                <i class="fa-solid fa-house"></i> Dashboard
            </a>
            <a href="{{ route('affiliate.content') }}" class="sm-tab {{ $active === 'content' ? 'active' : '' }}">
                <i class="fa-solid fa-video"></i> Konten
            </a>
            <a href="{{ route('affiliate.commissions') }}" class="sm-tab {{ $active === 'commissions' ? 'active' : '' }}">
                <i class="fa-solid fa-wallet"></i> Komisi
            </a>
            <a href="{{ route('affiliate.rewards') }}" class="sm-tab {{ $active === 'rewards' ? 'active' : '' }}">
                <i class="fa-solid fa-trophy"></i> Rewards
            </a>
            <a href="{{ route('affiliate.profile') }}" class="sm-tab {{ $active === 'profile' ? 'active' : '' }}">
                <i class="fa-solid fa-user"></i> Profil
            </a>
        </nav>

    </div>
</header>
