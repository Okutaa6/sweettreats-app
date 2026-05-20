{{--
    PARTIAL: Tab filter untuk halaman KOC.
    Dipakai di: list.blade.php

    Variable yang dibutuhkan:
      $filterTab  → 'all' | 'confirmed' | 'submitted'
      $counts     → ['all' => int, 'confirmed' => int, 'submitted' => int]
--}}

<div class="koc-tab-filter">
    <a href="{{ route('koc', array_merge(request()->except(['tab', 'page']), ['tab' => 'all'])) }}"
       class="koc-tab-filter__btn {{ $filterTab === 'all' ? 'is-active' : '' }}">
        <i class="fa fa-list"></i> Semua KOC
        <span class="koc-tab-filter__count">{{ $counts['all'] }}</span>
    </a>

    <a href="{{ route('koc', array_merge(request()->except(['tab', 'page']), ['tab' => 'submitted'])) }}"
       class="koc-tab-filter__btn {{ $filterTab === 'submitted' ? 'is-active' : '' }}">
        <i class="fa fa-photo-video"></i> Sudah Submit Konten
        <span class="koc-tab-filter__count">{{ $counts['submitted'] }}</span>
    </a>

    <a href="{{ route('koc', array_merge(request()->except(['tab', 'page']), ['tab' => 'confirmed'])) }}"
       class="koc-tab-filter__btn {{ $filterTab === 'confirmed' ? 'is-active' : '' }}">
        <i class="fa fa-check-circle"></i> Confirmed Sosmed
        <span class="koc-tab-filter__count">{{ $counts['confirmed'] }}</span>
    </a>
</div>
