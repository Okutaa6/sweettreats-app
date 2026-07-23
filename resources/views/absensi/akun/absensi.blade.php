@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
@endsection

@php
    $statusLabel = [
        'hadir' => 'Hadir', 'telat' => 'Telat', 'alpa' => 'Alpa', 'cuti' => 'Cuti',
        'izin' => 'Izin', 'wfh' => 'WFH', 'libur' => 'Libur',
    ];
    $hari = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
    $hm = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->format('H:i') : null;
    // Status pengajuan dalam bahasa awam (bukan pending_l1 dll).
    $reqLabel = [
        'draft' => 'Draf', 'pending_l1' => 'Menunggu', 'pending_l2' => 'Menunggu',
        'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan',
    ];
@endphp

@section('content')
<div class="absk-wrap">
    @include('layouts.notif')

    <div class="absk-grid">

    {{-- ══════ KIRI: identitas + ringkasan + Ajukan ══════ --}}
    <div class="absk-col-a">

        {{-- PersonalHeader (mobile full-bleed, desktop kartu) --}}
        <div class="absk-head">
            <div class="absk-idrow">
                <div class="absk-avatar">{{ $me['inisial'] }}</div>
                <div class="absk-idmeta">
                    <div class="absk-name">{{ $me['nama'] }}</div>
                    <div class="absk-sub">{{ $me['jabatan'] ?? 'Karyawan' }}@if($me['cabang']) · Cabang {{ $me['cabang'] }}@endif</div>
                </div>
                <a href="#" class="absk-bell" title="Notifikasi">
                    <i class="fa fa-bell"></i>
                    @if($notifCount)<span class="absk-badge num">{{ $notifCount > 9 ? '9+' : $notifCount }}</span>@endif
                </a>
            </div>
            <div class="absk-monthnav">
                <a class="absk-navbtn" href="{{ route('akun.absensi', ['m' => $anchor->copy()->subMonth()->format('Y-m')]) }}">‹</a>
                <div class="absk-mlabel">{{ $anchor->translatedFormat('F Y') }}</div>
                <a class="absk-navbtn" href="{{ route('akun.absensi', ['m' => $anchor->copy()->addMonth()->format('Y-m')]) }}">›</a>
            </div>
        </div>

        {{-- Ringkasan bulan --}}
        <div class="absk-chips">
            @foreach(['hadir'=>'HADIR','telat'=>'TELAT','alpa'=>'ALPA','cuti'=>'IZIN/CUTI'] as $k => $lbl)
                <div class="absk-chip is-{{ $k }}"><div class="n num">{{ $rekap[$k] }}</div><div class="l">{{ $lbl }}</div></div>
            @endforeach
        </div>
        <div class="absk-tiles">
            <div class="absk-tile telat"><div class="k">TOTAL TELAT</div><div class="v num">{{ $rekap['telat_menit'] }} mnt</div></div>
            <div class="absk-tile lembur"><div class="k">LEMBUR DISETUJUI</div><div class="v num">{{ $rekap['lembur_menit'] ? round($rekap['lembur_menit']/60, 1) : 0 }} jam</div></div>
        </div>

        {{-- Hari Ini --}}
        @if($today)
            <div class="absk-today">
                <div class="absk-today-top">
                    <div class="absk-today-lbl">HARI INI · {{ \Illuminate\Support\Carbon::today()->translatedFormat('d M') }}</div>
                    <span class="absk-pill s-{{ $today->status }}">{{ $statusLabel[$today->status] ?? ucfirst($today->status) }}</span>
                </div>
                <div class="absk-today-body">
                    <div><div class="absk-today-k">MASUK</div><div class="absk-today-v num">{{ $hm($today->scan_first) ?? '—' }}</div></div>
                    <div class="absk-today-arrow">→</div>
                    <div><div class="absk-today-k">PULANG</div><div class="absk-today-v num">{{ $hm($today->scan_last) ?? '—' }}</div></div>
                    @if($today->telat_menit)<span class="absk-today-late num">telat {{ $today->telat_menit }} mnt</span>@endif
                </div>
            </div>
        @endif

        <button type="button" class="absk-fab" data-open-ajukan><span class="plus">+</span> Ajukan Permohonan</button>
    </div>

    {{-- ══════ KANAN: toggle + daftar hari + riwayat ══════ --}}
    <div class="absk-col-b">

        <div class="absk-seg">
            <button type="button" class="active" data-aview="harian">Harian</button>
            <button type="button" data-aview="kalender">Kalender</button>
        </div>

        {{-- ══ View: Harian ══ --}}
        <div class="absk-view" data-view="harian">
            <div class="absk-corrhint"><span>⚑</span> Ada absen yang keliru? Klik harinya untuk lapor koreksi.</div>
            @if($days->isEmpty())
                <div class="absk-empty">Belum ada data absen bulan ini.</div>
            @else
                <div class="absk-days">
                    {{-- terbaru dulu (desain: empDays di-reverse) — kalender tetap kronologis --}}
                    @foreach($days->reverse() as $tgl => $d)
                        @php $c = \Illuminate\Support\Carbon::parse($tgl); @endphp
                        @php
                            $dJam = $d->scan_first ? ($hm($d->scan_first) . ' – ' . ($hm($d->scan_last) ?? '—')) : null;
                            $dLbl = $statusLabel[$d->status] ?? ucfirst($d->status);
                        @endphp
                        <div class="absk-dayrow s-{{ $d->status }}" role="button" tabindex="0"
                             data-koreksi="{{ $tgl }}"
                             data-kor-label="{{ $c->translatedFormat('d M Y') }}"
                             data-kor-status="{{ $dLbl }}"
                             data-kor-klas="s-{{ $d->status }}"
                             data-kor-jam="{{ $dJam ?: '—' }}"
                             title="Ketuk untuk lapor koreksi">
                            <div class="absk-date"><div class="d num">{{ $c->format('d') }}</div><div class="dow">{{ $hari[$c->dayOfWeek] }}</div></div>
                            <div class="absk-daymid">
                                <span class="absk-pill s-{{ $d->status }}">{{ $dLbl }}</span>
                                @if($d->telat_menit)<span class="absk-daylate num">telat {{ $d->telat_menit }} mnt</span>@endif
                            </div>
                            <div class="absk-dayjam num">{{ $dJam ?: '—' }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══ View: Kalender (grid bulanan + legend) ══ --}}
        @php
            $calFirst   = $anchor->copy()->startOfMonth();
            $calLead    = (int) $calFirst->dayOfWeek;        // 0 = Minggu
            $calCount   = (int) $anchor->daysInMonth;
            $calDow     = ['Mg','Sn','Sl','Rb','Km','Jm','Sb'];
        @endphp
        <div class="absk-view" data-view="kalender" hidden>
            <div class="absk-cal">
                @foreach($calDow as $dl)<div class="absk-cal-dow">{{ $dl }}</div>@endforeach
                @for($i = 0; $i < $calLead; $i++)<div class="absk-cal-cell empty"></div>@endfor
                @for($d = 1; $d <= $calCount; $d++)
                    @php
                        $ds = $anchor->format('Y-m') . '-' . sprintf('%02d', $d);
                        $cd = $days[$ds] ?? null;
                        $st = $cd->status ?? null;
                    @endphp
                    <div class="absk-cal-cell @if($st) s-{{ $st }} @endif" title="{{ $st ? ($statusLabel[$st] ?? $st) : '' }}">
                        <span class="absk-cal-num">{{ $d }}</span>
                        @if($st)<span class="absk-cal-dot s-{{ $st }}"></span>@endif
                    </div>
                @endfor
            </div>
            <div class="absk-cal-legend">
                @foreach(['hadir'=>'Hadir','telat'=>'Telat','alpa'=>'Alpa','cuti'=>'Cuti','izin'=>'Izin','libur'=>'Libur','wfh'=>'WFH'] as $sk => $sl)
                    <span class="absk-leg"><span class="absk-leg-dot s-{{ $sk }}"></span>{{ $sl }}</span>
                @endforeach
            </div>
        </div>

        {{-- Riwayat permohonan (grid di desktop) --}}
        <div class="absk-sechead">RIWAYAT PERMOHONAN</div>
        @if($requests->isEmpty())
            <div class="absk-empty">Belum ada pengajuan.</div>
        @else
            <div class="absk-reqs">
                @foreach($requests as $r)
                    <div class="absk-reqcard" data-detail="{{ route('akun.pengajuan.show', $r->id) }}" role="button" tabindex="0">
                        <span class="absk-type">{{ $r->tipe }}</span>
                        <div class="absk-reqmid">
                            <div class="absk-reqdate num">
                                {{ \Illuminate\Support\Carbon::parse($r->tanggal_from)->translatedFormat('d M') }}@if($r->tanggal_to != $r->tanggal_from) – {{ \Illuminate\Support\Carbon::parse($r->tanggal_to)->translatedFormat('d M') }}@endif
                            </div>
                            @if($r->alasan)<div class="absk-reqreason">{{ $r->alasan }}</div>@endif
                        </div>
                        <span class="absk-pill s-{{ in_array($r->status, ['approved']) ? 'hadir' : (in_array($r->status, ['rejected','cancelled']) ? 'alpa' : 'telat') }}">{{ $reqLabel[$r->status] ?? $r->status }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    </div>{{-- .absk-grid --}}
</div>

{{-- ── Drawer Ajukan Permohonan (slide dari kanan) ── --}}
<div class="absk-ajscrim" id="ajScrim"></div>
<aside class="absk-ajdrawer" id="ajDrawer" aria-hidden="true">
    <div class="absk-ajhead">
        <div class="absk-ajhead-icon">+</div>
        <div class="absk-ajhead-tx">
            <div class="absk-ajhead-sub">{{ strtoupper($me['nama']) }}@if($me['cabang']) · {{ strtoupper($me['cabang']) }}@endif</div>
            <div class="absk-ajhead-title">Ajukan Permohonan</div>
        </div>
        <button type="button" class="absk-ajclose" data-close-ajukan aria-label="Tutup">✕</button>
    </div>
    @include('absensi.akun._pengajuan_form', ['tipeList' => $tipeList, 'tipe' => $ajTipe, 'inDrawer' => true, 'wfhUsed' => $wfhUsed])
</aside>

{{-- ── Drawer detail riwayat (populate via fetch) ── --}}
<div class="absk-dtscrim" id="dtScrim"></div>
<aside class="absk-dtdrawer" id="dtDrawer" aria-hidden="true">
    <div class="absk-ajhead">
        <div class="absk-ajhead-icon"><i class="fa fa-file-alt"></i></div>
        <div class="absk-ajhead-tx">
            <div class="absk-ajhead-sub">RIWAYAT</div>
            <div class="absk-ajhead-title">Detail Pengajuan</div>
        </div>
        <button type="button" class="absk-ajclose" data-close-detail aria-label="Tutup">✕</button>
    </div>
    <div class="absk-dtbody" id="dtBody"><div class="absk-empty">Memuat…</div></div>
</aside>

{{-- ── Modal Koreksi Absensi (dibuka dgn mengetuk satu hari) ── --}}
<div class="absk-korwrap" id="korWrap" role="dialog" aria-modal="true" aria-hidden="true">
    @include('absensi.akun._koreksi_form', ['korAlasan' => $korAlasan])
</div>

<script>
(function () {
    var body = document.body;
    var scrim = document.getElementById('ajScrim');

    // ── Drawer detail riwayat ──
    var dtBody = document.getElementById('dtBody');
    function openDt()  { body.classList.add('absk-dtopen'); }
    function closeDt() { body.classList.remove('absk-dtopen'); }
    document.querySelectorAll('[data-detail]').forEach(function (card) {
        card.addEventListener('click', function () {
            var url = card.getAttribute('data-detail');
            dtBody.innerHTML = '<div class="absk-empty">Memuat…</div>';
            openDt();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text(); })
                .then(function (html) { dtBody.innerHTML = html; })
                .catch(function () { dtBody.innerHTML = '<div class="absk-empty">Gagal memuat detail.</div>'; });
        });
    });
    document.querySelectorAll('[data-close-detail]').forEach(function (b) { b.addEventListener('click', closeDt); });
    document.getElementById('dtScrim').addEventListener('click', closeDt);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDt(); });
    function openAj()  { body.classList.add('absk-ajopen'); }
    function closeAj() { body.classList.remove('absk-ajopen'); }
    document.querySelectorAll('[data-open-ajukan]').forEach(function (b) { b.addEventListener('click', openAj); });
    document.querySelectorAll('[data-close-ajukan]').forEach(function (b) { b.addEventListener('click', closeAj); });
    if (scrim) scrim.addEventListener('click', closeAj);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAj(); });

    // ── Modal Koreksi Absensi: ketuk satu hari ──
    var korWrap = document.getElementById('korWrap');
    var korForm = document.getElementById('korForm');
    var korSubmit = document.getElementById('korSubmit');
    function openKor()  { body.classList.add('absk-koropen'); }
    function closeKor() { body.classList.remove('absk-koropen'); }

    document.querySelectorAll('[data-koreksi]').forEach(function (row) {
        function go() {
            korForm.reset();
            korSubmit.disabled = true;                    // wajib pilih alasan dulu
            document.getElementById('korFotoZone').classList.remove('is-on');
            document.querySelector('.absk-korfoto-tick').hidden = true;
            document.querySelector('.absk-korfoto-lbl').textContent = 'Lampirkan foto (opsional)';

            document.getElementById('korTanggal').value = row.getAttribute('data-koreksi');
            document.getElementById('korTglLabel').textContent = row.getAttribute('data-kor-label');
            var pill = document.getElementById('korStatusPill');
            pill.textContent = row.getAttribute('data-kor-status');
            pill.className = 'absk-pill ' + row.getAttribute('data-kor-klas');
            document.getElementById('korJam').textContent = 'tercatat ' + row.getAttribute('data-kor-jam');
            openKor();
        }
        row.addEventListener('click', go);
        row.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });

    korForm.querySelectorAll('input[name="koreksi_alasan"]').forEach(function (r) {
        r.addEventListener('change', function () { korSubmit.disabled = false; });
    });
    document.getElementById('korFotoInput').addEventListener('change', function () {
        var ada = this.files && this.files.length;
        document.getElementById('korFotoZone').classList.toggle('is-on', !!ada);
        document.querySelector('.absk-korfoto-tick').hidden = !ada;
        document.querySelector('.absk-korfoto-lbl').textContent = ada ? 'Foto bukti terlampir' : 'Lampirkan foto (opsional)';
    });

    document.querySelectorAll('[data-close-koreksi]').forEach(function (b) { b.addEventListener('click', closeKor); });
    korWrap.addEventListener('click', function (e) { if (e.target === korWrap) closeKor(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeKor(); });

    // Toggle Harian / Kalender
    document.querySelectorAll('.absk-seg [data-aview]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var view = btn.getAttribute('data-aview');
            document.querySelectorAll('.absk-seg [data-aview]').forEach(function (b) { b.classList.toggle('active', b === btn); });
            document.querySelectorAll('.absk-view').forEach(function (v) { v.hidden = (v.getAttribute('data-view') !== view); });
        });
    });

    @if(old('tipe')) openAj(); @endif
})();
</script>
@endsection
