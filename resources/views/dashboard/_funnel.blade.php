{{-- Funnel mengalir. Var: $funnel, $fid (id unik gradient), $title, $sub, $icon, $tint, $color. --}}
@php $f = $funnel; @endphp
<div class="mds-card">
    <div class="mds-sec mds-sec--m0">
        <span class="mds-sec__ic" style="background:{{ $tint }};color:{{ $color }};"><i class="fa {{ $icon }}"></i></span>
        {{ $title }} <span class="mds-sec__sub">— {{ $sub }}</span>
        @if ($f['stages'][0]['count'] > 0)
            <span class="mds-funnel__rate" style="color:{{ $f['accent'] }};">{{ $f['rate'] }}%</span>
        @endif
    </div>
    @if ($f['stages'][0]['count'] > 0)
        <div class="mds-funnel">
            <svg viewBox="0 0 {{ $f['width'] }} {{ $f['height'] }}" class="mds-funnel__svg" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="fg-{{ $fid }}" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0" stop-color="{{ $f['accent'] }}" stop-opacity=".82"/>
                        <stop offset="1" stop-color="{{ $f['accent'] }}" stop-opacity=".26"/>
                    </linearGradient>
                </defs>
                <path d="{{ $f['path'] }}" fill="url(#fg-{{ $fid }})"/>
                @foreach ($f['stages'] as $i => $s)
                    @if ($i > 0)
                        <line x1="{{ round($f['col_w'] * $i, 1) }}" y1="4" x2="{{ round($f['col_w'] * $i, 1) }}" y2="{{ $f['height'] - 30 }}" stroke="#EFE2D6" stroke-width="1"/>
                    @endif
                    <text x="{{ $s['cx'] }}" y="20" text-anchor="middle" class="mds-funnel__n">{{ number_format($s['count'], 0, ',', '.') }}</text>
                    <rect x="{{ round($s['cx'] - 23, 1) }}" y="{{ $f['mid_y'] - 12 }}" width="46" height="24" rx="12" fill="#fff" stroke="#F0E2D6"/>
                    <text x="{{ $s['cx'] }}" y="{{ $f['mid_y'] + 4 }}" text-anchor="middle" class="mds-funnel__pct">{{ $s['pct'] }}%</text>
                    <text x="{{ $s['cx'] }}" y="{{ $f['height'] - 14 }}" text-anchor="middle" class="mds-funnel__lbl">{{ $s['label'] }}</text>
                    <text x="{{ $s['cx'] }}" y="{{ $f['height'] - 3 }}" text-anchor="middle" class="mds-funnel__sub">{{ $s['sub'] }}</text>
                @endforeach
            </svg>
        </div>
        @if (! empty($note))
            <div class="mds-funnel__note"><i class="fa fa-info-circle"></i> {{ $note }}</div>
        @endif
    @else
        <div class="mds-empty">Belum ada data untuk cabang ini.</div>
    @endif
</div>
