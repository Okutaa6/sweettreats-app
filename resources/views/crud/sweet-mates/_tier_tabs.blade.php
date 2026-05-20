{{-- 
    PARTIAL: Tab filter tier untuk halaman Sweet Mates.
    
    Variable:
      $filterTier  → 'all' | 'bronze' | 'silver' | 'gold' | 'eligible'
      $counts      → ['all' => int, 'bronze' => int, 'silver' => int, 'gold' => int, 'eligible' => int]
--}}

<div class="sm-tier-tabs">
    <a href="{{ route('sweetmates', array_merge(request()->except(['tier', 'page']), ['tier' => 'all'])) }}"
       class="sm-tier-tab {{ $filterTier === 'all' ? 'is-active' : '' }}">
        <i class="fa fa-list"></i> Semua
    </a>

    <a href="{{ route('sweetmates', array_merge(request()->except(['tier', 'page']), ['tier' => 'bronze'])) }}"
       class="sm-tier-tab {{ $filterTier === 'bronze' ? 'is-active' : '' }}">
        <i class="fas fa-medal"></i> Bronze
    </a>

    <a href="{{ route('sweetmates', array_merge(request()->except(['tier', 'page']), ['tier' => 'silver'])) }}"
       class="sm-tier-tab {{ $filterTier === 'silver' ? 'is-active' : '' }}">
        <i class="fas fa-award"></i> Silver
    </a>

    <a href="{{ route('sweetmates', array_merge(request()->except(['tier', 'page']), ['tier' => 'gold'])) }}"
       class="sm-tier-tab {{ $filterTier === 'gold' ? 'is-active' : '' }}">
        <i class="fas fa-crown"></i> Gold
    </a>

    <a href="{{ route('sweetmates', array_merge(request()->except(['tier', 'page']), ['tier' => 'eligible'])) }}"
       class="sm-tier-tab sm-tier-tab--special {{ $filterTier === 'eligible' ? 'is-active' : '' }}"
       title="Member yang sudah memenuhi target konten tapi belum klaim reward">
        <i class="fas fa-trophy"></i> Eligible Klaim
        <span class="sm-tier-tab__count">{{ $counts['eligible'] }}</span>
    </a>
</div>
