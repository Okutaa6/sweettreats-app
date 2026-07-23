{{-- Running text / pengumuman. Teks global dari sys_setting (checklistInfoText),
     bisa diedit admin di halaman Setup Tugas. --}}
@php $marqueeText = checklistInfoText(); @endphp
@if(trim($marqueeText) !== '')
<div class="cl-marquee" aria-label="Pengumuman">
    <span class="cl-marquee__label"><i class="fas fa-bullhorn"></i> Info</span>
    <div class="cl-marquee__viewport">
        <div class="cl-marquee__track">
            <span class="cl-marquee__item">{{ $marqueeText }}</span>
            <span class="cl-marquee__item" aria-hidden="true">{{ $marqueeText }}</span>
        </div>
    </div>
</div>
<style>
    .cl-marquee{display:flex;align-items:stretch;background:#FFF7EE;border:1px solid #E7D6C2;
        border-radius:12px;overflow:hidden;margin-bottom:14px;box-shadow:0 1px 3px rgba(45,24,16,.05);}
    .cl-marquee__label{flex:none;display:inline-flex;align-items:center;gap:7px;padding:10px 16px;
        background:linear-gradient(180deg,#5C2E15,#471500);color:#FFE9C7;font-size:12.5px;
        font-weight:800;letter-spacing:.3px;white-space:nowrap;}
    .cl-marquee__label i{font-size:13px;}
    .cl-marquee__viewport{flex:1 1 auto;min-width:0;overflow:hidden;}
    .cl-marquee__track{display:inline-flex;flex-wrap:nowrap;white-space:nowrap;
        animation:cl-marquee-move 28s linear infinite;}
    .cl-marquee:hover .cl-marquee__track{animation-play-state:paused;}
    .cl-marquee__item{flex:0 0 auto;padding:10px 34px 10px 22px;font-size:13px;font-weight:600;color:#5A3A22;}
    @keyframes cl-marquee-move{from{transform:translateX(-50%);}to{transform:translateX(0);}}
</style>
@endif
