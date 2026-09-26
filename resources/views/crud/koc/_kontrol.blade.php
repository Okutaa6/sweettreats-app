{{-- Sel "jumlah kontrol" — dipakai KOC, Review Konten, SweetMates.
     Butuh: $k (satu entri KocPasien::ringkasanKontrol, boleh null), $affiliateId.
     Hanya TAMPILAN: kelayakan reward tetap murni dari jumlah video.
     Belum tertaut → "perlu dipilih", BUKAN angka tebakan. --}}
@php $urlPasien = route('koc.pasien', ['id' => $affiliateId, 'return' => request()->fullUrl()]); @endphp
@if(!$k || $k['status'] === 'perlu_dipilih')
    <a href="{{ $urlPasien }}" onclick="event.stopPropagation();"
       title="Rekam pasien belum dipilih — nomor HP cocok ke lebih dari satu pasien, atau tak cocok sama sekali"
       style="font-size:11px; color:#b2641b; text-decoration:underline dotted;">perlu dipilih</a>
@else
    <a href="{{ $urlPasien }}" onclick="event.stopPropagation();" title="Lihat / ganti rekam pasien"
       style="color:inherit; text-decoration:none;">
        <strong>{{ $k['jumlah'] }}×</strong>
        @if($k['terakhir'])
            <br><small style="color:#7a9290; font-size:10.5px;">terakhir {{ \Carbon\Carbon::parse($k['terakhir'])->format('j M') }}</small>
        @endif
    </a>
@endif
