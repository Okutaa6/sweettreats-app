{{-- 
    PARTIAL: Tab navigation untuk halaman KOL.
    Dipakai di: list.blade.php, list-existing.blade.php (& future: dashboard, per-cabang)
    
    Variable yang dibutuhkan:
      $activeTab  → string: 'bulan-ini' | 'existing' | 'per-cabang' | 'dashboard'
--}}

<div class="st-page-tabs">
    <a href="{{ route('kol') }}" 
       class="st-page-tab {{ ($activeTab ?? '') === 'bulan-ini' ? 'active' : '' }}">
        <i class="fa fa-calendar-day"></i> KOL Bulan Ini
    </a>
    
    <a href="{{ route('kol.existing') }}"
       class="st-page-tab {{ ($activeTab ?? '') === 'existing' ? 'active' : '' }}">
        <i class="fa fa-archive"></i> KOL Existing
    </a>
</div>
