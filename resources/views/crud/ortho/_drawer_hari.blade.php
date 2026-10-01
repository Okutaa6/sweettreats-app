{{--
    Drawer harian — brief §12.1 / screenshot 12. Dibuka dari sel kalender.

    Satu kartu per jadwal, terurut jam. Klik kartu → drawer pasien.
    Garis tepi kiri: oranye kalau masih butuh FU/reservasi, merah kalau
    terlewat/batal, tanpa garis kalau sudah beres.
--}}
@php
    $baris = $hariData['baris'];
    $jam   = $hariData['jam'];
    $sudahRsv = $baris->filter(fn ($r) => $r->reservation_id && $r->rsv_status !== 'cancelled')->count();
    $tutup = $tautan(['hari' => null]);
@endphp

<a href="{{ $tutup }}" class="ort-scrim" aria-label="Tutup"></a>

<aside class="ort-drawer ort-drawer--hari" role="dialog" aria-label="Jadwal {{ $hariDrawer->translatedFormat('j F Y') }}">
    <div class="ort-drawer__head">
        <a href="{{ $tutup }}" class="ort-drawer__close" title="Tutup"><i class="fas fa-times"></i></a>
        <div class="ort-drawer__kicker">Jadwal harian</div>
        <div class="ort-drawer__name" style="font-size:24px;">{{ $hariDrawer->translatedFormat('j F Y') }}</div>
        <div class="ort-drawer__meta ort-drawer__meta--dim">
            <span class="tabular">{{ $baris->count() }}</span> jadwal kontrol ·
            <span class="tabular">{{ $sudahRsv }}</span> sudah reservasi ·
            <span class="tabular">{{ $baris->count() - $sudahRsv }}</span> belum
        </div>
    </div>

    <div class="ort-drawer__body" style="gap:11px;">
        @forelse ($baris as $r)
            @php
                $telat = $r->sch_date < $hariIni->toDateString();
                $batal = $r->rsv_status === 'cancelled';
                $adaRsv= $r->reservation_id && ! $batal;
                $tepi  = ($telat || $batal) ? 'is-bad' : ((! $adaRsv || ! $r->fu_date) ? 'is-warn' : '');
                $chip  = match (true) {
                    $batal => ['Reservasi batal', 'batal'],
                    $telat => ['Terlewat', 'telatlama'],
                    $adaRsv => ['Ada reservasi', 'rsv'],
                    (bool) $r->fu_date => ['Sudah di-FU', 'fu'],
                    default => ['Menunggu', 'menunggu'],
                };
                $j = $jam[$r->reservation_id] ?? null;
            @endphp
            <a href="{{ $tautanDetail($r->ort_case_id) }}" class="ort-daycard {{ $tepi }}">
                <div class="ort-daycard__top">
                    <span class="ort-daycard__name">{{ \Illuminate\Support\Str::title($r->patient_name) }}</span>
                    <span class="ort-chip ort-chip--{{ $chip[1] }}">{{ $chip[0] }}</span>
                </div>
                <div class="ort-daycard__mid">
                    @if ($adaRsv && $j && $j[0])
                        <span class="ort-timepill tabular">{{ substr($j[0], 0, 5) }}{{ $j[1] ? ' – ' . substr($j[1], 0, 5) : '' }}</span>
                    @elseif ($adaRsv)
                        <span class="ort-timepill tabular">reservasi {{ $r->rsv_date ? \Carbon\Carbon::parse($r->rsv_date)->translatedFormat('j M') : '' }}</span>
                    @else
                        <span class="ort-timepill ort-timepill--none">Belum reservasi</span>
                    @endif
                    <span class="ort-daycard__doc">{{ $r->doctor_name ?: '—' }}</span>
                </div>
                <div class="ort-daycard__sub">Kontrol Behel · kontrol ke-{{ $r->seq }}</div>
            </a>
        @empty
            <div class="ort-matrix__empty">Tidak ada jadwal kontrol di tanggal ini.</div>
        @endforelse
    </div>
</aside>
