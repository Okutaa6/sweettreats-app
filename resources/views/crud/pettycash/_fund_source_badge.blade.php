{{--
    Reusable badge sumber dana.
    Variables:
      $color  : warna brand (hex), mis. "#0060af"
      $short  : teks pendek (mis. "BCA"), dipakai kalau $logo kosong
      $logo   : path file logo (opsional). Kalau diisi → render <img>
      $name   : nama lengkap (opsional, untuk title/aria-label)
      $size   : 'sm' | 'md' (default 'md')
--}}
@php
    $color = $color ?? '#64748b';
    $short = $short ?? '?';
    $logo  = $logo  ?? null;
    $name  = $name  ?? '';
    $size  = $size  ?? 'md';
@endphp
<span class="fs-badge fs-badge--{{ $size }}"
      style="background:{{ $color }}"
      @if($name) title="{{ $name }}" aria-label="{{ $name }}" @endif>
    @if($logo)
        <img src="{{ $logo }}" alt="{{ $name }}" class="fs-badge__img">
    @else
        <span class="fs-badge__text">{{ $short }}</span>
    @endif
</span>
