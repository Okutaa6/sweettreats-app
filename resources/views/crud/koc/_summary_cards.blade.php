{{-- 
    PARTIAL: Kartu cabang untuk halaman KOC.
    Reuse pattern dari KOL — sama persis, hanya routeName berbeda.
    
    Variable yang dibutuhkan:
      $branches      → Collection of RawBranch (dengan kol_count attribute = jumlah KOC)
      $filterBranch  → string: ID cabang aktif atau 'all'
      $totalCount    → int: total semua KOC
--}}

<div class="st-branch-grid">
    {{-- Kartu "Semua" --}}
    <a href="{{ route('koc', array_merge(request()->except(['branch', 'page']), ['branch' => 'all'])) }}" 
       class="st-branch-card {{ $filterBranch === 'all' ? 'is-active' : '' }}">
        <div class="st-branch-card__label">
            <i class="fa fa-layer-group" style="margin-right:4px;"></i> Semua Cabang
        </div>
        <div class="st-branch-card__value">{{ $totalCount }}</div>
    </a>
    
    {{-- Kartu per cabang --}}
    @foreach($branches as $branch)
        <a href="{{ route('koc', array_merge(request()->except(['branch', 'page']), ['branch' => $branch->id])) }}" 
           class="st-branch-card {{ $filterBranch === $branch->id ? 'is-active' : '' }}">
            <div class="st-branch-card__label">
                @php $branchSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $branch->name)); @endphp
                <span class="st-pill st-pill--branch-{{ $branchSlug }}" 
                      style="display:inline-block; padding:1px 7px; border-radius:999px; font-size:10px; border:1px solid; line-height:1.4;">
                    {{ $branch->name }}
                </span>
            </div>
            <div class="st-branch-card__value">{{ $branch->kol_count ?? 0 }}</div>
        </a>
    @endforeach
</div>
