@extends('layouts.app')

{{-- Lembar Gaji — rekap per karyawan per bulan untuk dasar hitung gaji manual
     Finance. Angka = ringkasan att_day (sudah rekonsiliasi). Bukan rupiah. --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .lg-wrap { font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px 26px 30px; display: flex; flex-direction: column; gap: 16px; color: #3B1500; }
    .lg-wrap .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }
    .lg-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .lg-title { font-size: 20px; font-weight: 800; margin: 0; }
    .lg-sub { font-size: 12.5px; font-weight: 500; color: #9A7256; margin-top: 2px; }
    .lg-head-btns { display: flex; gap: 10px; align-items: center; }
    .lg-btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 11px; padding: 10px 15px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: inherit; border: 1px solid transparent; text-decoration: none; }
    .lg-btn-pri { background: linear-gradient(135deg, #6B2400, #461500); color: #fff; box-shadow: 0 4px 12px rgba(20,20,18,.22); }
    .lg-btn-pri:hover { filter: brightness(1.12); color: #fff; }
    .lg-monthnav { display: inline-flex; align-items: center; gap: 4px; }
    .lg-navbtn { width: 34px; height: 34px; display: grid; place-items: center; border: 1px solid #E7D3BE; border-radius: 9px; background: #fff; color: #5A3A22; text-decoration: none; font-size: 16px; }
    .lg-navbtn:hover { background: #FBF4EC; color: #5A3A22; }
    .lg-mlabel { min-width: 130px; text-align: center; font-weight: 800; font-size: 14px; }
    .lg-select { border: 1px solid #E7D3BE; border-radius: 11px; background: #fff; padding: 9px 12px; font-family: inherit; font-size: 13px; color: #3B1500; }

    .lg-badge-lock { display: inline-flex; align-items: center; gap: 7px; background: #F1E9DF; color: #8A6A52; border-radius: 9px; padding: 6px 12px; font-size: 12px; font-weight: 700; }
    .lg-badge-lock i { width: 7px; height: 7px; border-radius: 50%; background: #8A6A52; }

    .lg-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; }
    @media (max-width: 1300px) { .lg-stats { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 1100px) { .lg-stats { grid-template-columns: repeat(2, 1fr); } }
    .lg-stat { background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; padding: 15px 18px; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .lg-stat .lbl { font-size: 10.5px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: #A9866A; }
    .lg-stat .val { font-size: 28px; font-weight: 800; line-height: 1; margin: 6px 0 3px; }
    .lg-stat .hint { font-size: 11.5px; color: #9A7256; }
    .lg-stat.alpa .val { color: #C0392B; }
    .lg-stat.lembur .val { color: #2F8E55; }
    /* DODO sengaja berwarna sendiri, bukan hijau seperti lembur biasa:
       tarifnya beda, jadi mata tak boleh menyamakan keduanya sekilas. */
    .lg-stat.dodo .val { color: #8A3A12; }
    .lg-dodo { color: #8A3A12; font-weight: 800; }
    .lg-curiga { display: flex; gap: 10px; align-items: flex-start; background: #FBEFD9; color: #96601A;
                 border-radius: 12px; padding: 12px 15px; font-size: 12.5px; font-weight: 600; margin-bottom: 14px; }
    .lg-curiga ul { margin: 6px 0 0; padding-left: 18px; font-weight: 500; }
    .lg-curiga li { margin: 2px 0; }

    .lg-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow-x: auto; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    table.lg-table { border-collapse: separate; border-spacing: 0; width: 100%; min-width: 900px; }
    /* gradient dipasang di baris thead (BUKAN tiap th) → satu band mulus lintas
       seluruh lebar, tak belang walau kolom banyak. th transparan. */
    .lg-table thead tr { background: linear-gradient(120deg, #5A2410, #3A1100); }
    .lg-table thead th { background: transparent; color: #FBEADB; font-size: 11px; font-weight: 800; letter-spacing: .4px; padding: 12px 12px; text-align: right; white-space: nowrap; }
    .lg-table thead th:first-child, .lg-table thead th:nth-child(2), .lg-table thead th:nth-child(3) { text-align: left; }
    .lg-table tbody td { padding: 11px 12px; border-bottom: 1px solid #F4EADD; font-size: 13px; text-align: right; white-space: nowrap; }
    .lg-table tbody td.kiri { text-align: left; }
    .lg-table tbody tr:hover { background: #FBF4EC; }
    .lg-nama { font-weight: 700; color: #3B1500; }
    .lg-rc { font-size: 11.5px; color: #A9866A; }
    .lg-muted { color: #C9AE94; }
    .lg-red { color: #C0392B; font-weight: 700; }
    .lg-green { color: #2F8E55; font-weight: 700; }
    .lg-amber { color: #B5701A; font-weight: 700; }
    .lg-foot td { background: #FCF7F0; font-weight: 800; font-size: 12.5px; padding: 11px 12px; text-align: right; border-top: 1px solid #EFE0CE; }
    .lg-foot td.kiri { text-align: left; color: #7A5B43; }
    .lg-note { font-size: 12px; color: #9A7256; padding: 2px 2px; }
</style>
@endsection

@section('content')
@php
    $monthLabel = $anchor->translatedFormat('F Y');
    $prev = $anchor->copy()->subMonth()->format('Y-m');
    $next = $anchor->copy()->addMonth()->format('Y-m');
    $qBranch = $branch ? ['branch' => $branch] : [];
    $fmtJam = fn ($j) => rtrim(rtrim(number_format($j, 1, ',', '.'), '0'), ',');
@endphp

<div class="lg-wrap">
    <div class="lg-head">
        <div>
            <h1 class="lg-title">Lembar Gaji</h1>
            <div class="lg-sub">Ringkasan kehadiran &amp; lembur per karyawan — dasar hitung gaji manual. Bukan nominal rupiah.</div>
        </div>
        <div class="lg-head-btns">
            @if($terkunci)
                <span class="lg-badge-lock"><i></i>Periode terkunci</span>
            @endif
            <div class="lg-monthnav">
                <a class="lg-navbtn" href="{{ route('absensi.lembargaji', array_merge(['m' => $prev], $qBranch)) }}">‹</a>
                <div class="lg-mlabel">{{ $monthLabel }}</div>
                <a class="lg-navbtn" href="{{ route('absensi.lembargaji', array_merge(['m' => $next], $qBranch)) }}">›</a>
            </div>
            <form method="GET" action="{{ route('absensi.lembargaji') }}" style="margin:0">
                <input type="hidden" name="m" value="{{ $anchor->format('Y-m') }}">
                <select class="lg-select" name="branch" onchange="this.form.submit()">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </form>
            <a class="lg-btn lg-btn-pri" href="{{ route('absensi.lembargaji.export', array_merge(['m' => $anchor->format('Y-m')], $qBranch)) }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Ekspor CSV
            </a>
        </div>
    </div>

    <div class="lg-stats">
        <div class="lg-stat"><div class="lbl">Karyawan</div><div class="val num">{{ $sum['orang'] }}</div><div class="hint">baris di bulan ini</div></div>
        <div class="lg-stat"><div class="lbl">Total hari hadir</div><div class="val num">{{ $sum['hadir'] }}</div><div class="hint">termasuk yang telat</div></div>
        <div class="lg-stat lembur"><div class="lbl">Lembur biasa</div><div class="val num">{{ $fmtJam($sum['biasa_jam']) }}</div><div class="hint">jam, sudah di-ACC</div></div>
        <div class="lg-stat dodo"><div class="lbl">DODO</div><div class="val num">{{ $fmtJam($sum['dodo_jam']) }}</div><div class="hint">jam, masuk di hari libur</div></div>
        <div class="lg-stat alpa"><div class="lbl">Total alpa</div><div class="val num">{{ $sum['alpa'] }}</div><div class="hint">hari tanpa keterangan</div></div>
    </div>

    {{-- Chip yang kemungkinan salah pilih = salah TARIF. Ditaruh sebelum tabel
         karena inilah layar terakhir sebelum gaji dihitung — kalau lewat sini,
         yang ketahuan belakangan cuma lewat komplain orangnya. --}}
    @if(!empty($curiga))
    <div class="lg-curiga">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="flex:none;margin-top:1px"><path d="M12 9v4.5M12 17v.1M10.3 3.9 2.6 17.4A2 2 0 0 0 4.3 20.4h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
            <strong>{{ count($curiga) }} pengajuan lembur perlu dicek jenisnya</strong> —
            jenis yang dipilih tak cocok dengan hari libur orangnya. Betulkan sebelum gaji dihitung.
            <ul>
                @foreach($curiga as $c)
                    <li><strong>{{ $c['nama'] }}</strong> · {{ $c['tanggal'] }} ·
                        {{ round($c['menit'] / 60, 1) }} jam — {{ $c['masalah'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    <div class="lg-card">
        <table class="lg-table">
            <thead>
                <tr>
                    <th>KARYAWAN</th><th>ROLE</th><th>CABANG</th>
                    <th>HADIR</th><th>TELAT (HARI)</th><th>TELAT (MENIT)</th><th>LEMBUR BIASA (JAM)</th><th>DODO (JAM)</th>
                    <th>ALPA</th><th>CUTI</th><th>IZIN</th><th>SAKIT</th><th>LIBUR</th><th>WFH</th><th>SISA CUTI</th>
                </tr>
            </thead>
            <tbody>
                @forelse($baris as $b)
                    <tr>
                        <td class="kiri"><span class="lg-nama">{{ $b['nama'] }}</span></td>
                        <td class="kiri lg-rc">{{ $b['role'] }}</td>
                        <td class="kiri lg-rc">{{ $b['cabang'] }}</td>
                        <td class="num">{{ $b['hadir'] }}</td>
                        <td class="num">{{ $b['telat_hari'] ?: '' }}</td>
                        <td class="num {{ $b['telat_menit'] ? 'lg-amber' : 'lg-muted' }}">{{ $b['telat_menit'] ?: '—' }}</td>
                        <td class="num {{ $b['biasa_jam'] ? 'lg-green' : 'lg-muted' }}">{{ $b['biasa_jam'] ? $fmtJam($b['biasa_jam']) : '—' }}</td>
                        <td class="num {{ $b['dodo_jam'] ? 'lg-dodo' : 'lg-muted' }}">{{ $b['dodo_jam'] ? $fmtJam($b['dodo_jam']) : '—' }}</td>
                        <td class="num {{ $b['alpa'] ? 'lg-red' : 'lg-muted' }}">{{ $b['alpa'] ?: '—' }}</td>
                        <td class="num">{{ $b['cuti'] ?: '' }}</td>
                        <td class="num">{{ $b['izin'] ?: '' }}</td>
                        <td class="num">{{ $b['sakit'] ?: '' }}</td>
                        <td class="num">{{ $b['libur'] ?: '' }}</td>
                        <td class="num">{{ $b['wfh'] ?: '' }}</td>
                        <td class="num {{ $b['sisa_cuti'] === null ? 'lg-muted' : ($b['sisa_cuti'] < 0 ? 'lg-red' : '') }}">{{ $b['sisa_cuti'] === null ? '—' : $fmtJam($b['sisa_cuti']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="15" style="text-align:center;padding:30px;color:#9A7256">Belum ada data untuk bulan ini.</td></tr>
                @endforelse
            </tbody>
            @if($baris->count())
                <tfoot>
                    <tr class="lg-foot">
                        <td class="kiri" colspan="3">Total {{ $sum['orang'] }} karyawan</td>
                        <td class="num">{{ $sum['hadir'] }}</td>
                        <td></td>
                        <td class="num">{{ $sum['telat_menit'] }}</td>
                        <td class="num">{{ $fmtJam($sum['biasa_jam']) }}</td>
                        <td class="num">{{ $fmtJam($sum['dodo_jam']) }}</td>
                        <td class="num">{{ $sum['alpa'] }}</td>
                        <td colspan="6"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="lg-note">Angka diambil dari hasil rekonsiliasi (att_day) yang sudah menyerap pengajuan di-ACC. Untuk mengunci angka sebelum bayar gaji, tutup periode di menu Periode Gaji.</div>
</div>
@endsection
