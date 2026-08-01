{{--
    Ikon SVG garis 1.5px, stroke currentColor — sesuai design handoff
    (tanpa emoji, dan tanpa bergantung pada kelengkapan FontAwesome 5.8).

    $n : nama ikon · $s : ukuran piksel (default 18)
--}}
@php $s = $s ?? 18; @endphp
<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false" style="flex:none;vertical-align:-.15em">
@switch($n)
    @case('plus')     <path d="M12 5v14M5 12h14"/> @break
    @case('kirim')    <path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/> @break
    @case('kembali')  <path d="M15 18l-6-6 6-6"/> @break
    @case('lanjut')   <path d="M9 18l6-6-6-6"/> @break
    @case('cek')      <path d="M20 6 9 17l-5-5"/> @break
    @case('waktu')    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
    @case('awas')     <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/> @break
    @case('kamera')   <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/> @break
    @case('klip')     <path d="M21.4 11.05 12.25 20.2a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/> @break
    @case('unduh')    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/> @break
    @case('cari')     <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/> @break
    @case('dompet')   <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4z"/> @break
    @case('grafik')   <path d="M21.2 15.9A10 10 0 1 1 8.1 2.8"/><path d="M22 12A10 10 0 0 0 12 2v10z"/> @break
    @case('tutup')    <path d="M18 6 6 18M6 6l12 12"/> @break
    @case('ulang')    <path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 8"/> @break
    @case('sinar')    <path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/><circle cx="12" cy="12" r="4"/> @break
    @default          <circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
