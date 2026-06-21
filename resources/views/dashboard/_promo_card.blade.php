{{-- Kartu promo dashboard. Var: $p (promo), $active (bool). Pakai $ccMap/$campLabels/$rp dari parent. --}}
@php
    $cc = $ccMap[$p->campaign_type] ?? ['color' => '#7A3415', 'bg' => '#F4E7D8'];
    $diskon = ($p->harga_normal && $p->harga_promo && $p->harga_normal > $p->harga_promo)
        ? (int) round((($p->harga_normal - $p->harga_promo) / $p->harga_normal) * 100) : null;
@endphp
<div class="sds-promo" style="--cc: {{ $cc['color'] }};">
    <div class="sds-promo__camp" style="color: {{ $cc['color'] }};">{{ $campLabels[$p->campaign_type] ?? $p->campaign_type }}</div>
    <div class="sds-promo__name">{{ $p->nama_promo }}</div>
    @if ($p->harga_promo)
        <div class="sds-promo__now">{{ $rp($p->harga_promo) }}</div>
        <div class="sds-promo__pr">
            @if ($diskon)<span class="sds-promo__save">-{{ $diskon }}%</span>@endif
            @if ($p->harga_normal && $p->harga_normal > $p->harga_promo)<span class="sds-promo__old">{{ $rp($p->harga_normal) }}</span>@endif
        </div>
    @else <div class="sds-promo__now sds-muted">—</div> @endif

    @if (trim((string) ($p->detail_promo ?? '')) !== '')
        <div class="sds-promo__text"><b>Detail</b><span>{{ $p->detail_promo }}</span></div>
    @endif
    @if (trim((string) ($p->syarat_ketentuan ?? '')) !== '')
        <div class="sds-promo__text sds-promo__text--sk"><b>S&amp;K</b><span>{{ $p->syarat_ketentuan }}</span></div>
    @endif

    @if ($active)
        @if ($p->periode_end)
            <div class="sds-promo__until"><i class="fa fa-clock"></i> s/d {{ \Carbon\Carbon::parse($p->periode_end)->format('d M Y') }}</div>
        @endif
    @else
        @if ($p->periode_start)
            <div class="sds-promo__until sds-promo__until--soon"><i class="fa fa-clock"></i> mulai {{ \Carbon\Carbon::parse($p->periode_start)->format('d M Y') }}</div>
        @endif
    @endif
</div>
