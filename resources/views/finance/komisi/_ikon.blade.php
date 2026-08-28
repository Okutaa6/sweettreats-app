{{--
    Ikon SVG garis 1.5px, stroke currentColor — sesuai design handoff
    (tanpa emoji, dan tanpa bergantung pada kelengkapan Font Awesome 5).

    $n : nama ikon · $s : ukuran piksel (default 18)
--}}
@php $s = $s ?? 18; @endphp
<svg width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false" style="flex:none;vertical-align:-.15em">
@switch($n)
    @case('kembali')  <path d="M15 18l-6-6 6-6"/> @break
    @case('lanjut')   <path d="M9 18l6-6-6-6"/> @break
    @case('unduh')    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/> @break
    @case('cari')     <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/> @break
    @case('awas')     <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/> @break
    @case('info')     <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/> @break
    @case('waktu')    <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
    @case('kalender') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/> @break
    @case('gembok')   <rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/> @break
    @case('salin')    <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/> @break
    @case('cetak')    <path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/> @break
    @case('tutup')    <path d="M18 6 6 18M6 6l12 12"/> @break
    @case('ulang')    <path d="M3 2v6h6"/><path d="M3.5 13a9 9 0 1 0 2.6-6.4L3 8"/> @break
    @case('turun')    <path d="M12 5v14M19 12l-7 7-7-7"/> @break
    @case('naik')     <path d="M12 19V5M5 12l7-7 7 7"/> @break
    @case('kosong')   <path d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7"/><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M9 12h6"/> @break
    @default          <circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
