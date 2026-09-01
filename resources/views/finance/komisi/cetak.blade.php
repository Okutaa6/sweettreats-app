{{--
    Halaman cetak rincian komisi — untuk diserahkan ke dokter.

    Pakai layouts.bare (tanpa sidebar/header/JS shell) daripada mengandalkan
    kelas .noprint di layouts.app: shell aplikasi tidak punya aturan @media print
    sendiri, jadi mencetak dari halaman biasa akan ikut membawa navigasi.

    Tanpa pagination — seluruh hasil filter dicetak apa adanya, karena inilah
    lembar yang dipakai membuktikan angka.
--}}
@extends('layouts.bare')

@section('css')
<link rel="stylesheet"
      href="{{ asset('assets/css/komisi-dokter.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/komisi-dokter.sweettreats.css')) ?: '1' }}">
<style>
    body { background: #fff; margin: 0; }
    .kd { background: #fff; padding: 24px 28px; }
    .kd-cetak__kop { display: flex; align-items: flex-start; justify-content: space-between;
                     gap: 20px; border-bottom: 2px solid #7A2739; padding-bottom: 12px; margin-bottom: 16px; }
    .kd-cetak__kop h1 { font-size: 19px; font-weight: 800; margin: 0 0 4px; }
    .kd-cetak__meta { font-size: 12px; font-weight: 600; color: #5A3A22; line-height: 1.6; }
    .kd-cetak__ttd { margin-top: 34px; display: flex; gap: 60px; font-size: 12px; font-weight: 600; color: #5A3A22; }
    .kd-cetak__ttd div { min-width: 200px; }
    .kd-cetak__garis { margin-top: 52px; border-top: 1px solid #B79A82; padding-top: 5px; }
    @media print { @page { size: A4 landscape; margin: 12mm; } }
</style>
@endsection

@section('content')
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $totalKomisi = $baris->sum('commission_amount');
@endphp

<div class="kd">
    <div class="kd-cetak__kop">
        <div>
            <h1>Rincian Komisi &mdash; {{ $dokter->nama }}</h1>
            <div class="kd-cetak__meta">
                Periode: {{ \Carbon\Carbon::parse($start)->translatedFormat('j F Y') }} &ndash;
                {{ \Carbon\Carbon::parse($end)->translatedFormat('j F Y') }} ({{ $hari }} hari)<br>
                Cabang: {{ $branchName }}<br>
                @if (request('code'))
                    Disaring pada kode tindakan: {{ request('code') }}<br>
                @endif
                Dicetak: {{ \Carbon\Carbon::now()->translatedFormat('j F Y, H:i') }}
            </div>
        </div>
        <div style="text-align:right">
            <div class="kd-stat__label">Total Komisi</div>
            <div style="font-size:23px;font-weight:800;letter-spacing:-.4px;margin-top:4px">
                {{ $rp($totalKomisi) }}
            </div>
            <div style="font-size:11.5px;font-weight:600;color:#9A7256;margin-top:3px">
                {{ number_format($baris->count(), 0, ',', '.') }} baris tindakan &middot;
                {{ number_format($ringkas->pasien, 0, ',', '.') }} pasien
            </div>
        </div>
    </div>

    @if ($baris->isEmpty())
        <p style="font-size:13px;font-weight:600;color:#5A3A22">
            Tidak ada baris tindakan pada periode dan cabang ini.
        </p>
    @else
        <table class="kd-tabel">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pasien</th>
                    <th>No. Invoice</th>
                    <th>Tindakan</th>
                    <th class="num">Jumlah</th>
                    <th class="num">Omzet</th>
                    <th>Skema</th>
                    <th class="num">Komisi</th>
                    <th>Cabang</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $r)
                    <tr>
                        <td style="white-space:nowrap">
                            {{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('j M Y') }}
                        </td>
                        <td class="kuat">{{ $r->p_name ?: '—' }}</td>
                        <td style="white-space:nowrap">{{ $r->invoice_num }}</td>
                        <td>{{ $r->item_name }} <span style="color:#B79A82">{{ $r->item_code }}</span></td>
                        <td class="num">{{ number_format($r->qty_sold, 0, ',', '.') }}</td>
                        <td class="num">{{ $rp($r->net_sell_price) }}</td>
                        <td style="white-space:nowrap">
                            {{ \App\Http\Controllers\Finance\KomisiDokterController::labelSkema($r->commission_type, (float) $r->commission_rate) }}
                        </td>
                        <td class="num kuat">{{ $rp($r->commission_amount) }}</td>
                        <td>{{ $r->branch_name }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7">Total</td>
                    <td class="num">{{ $rp($totalKomisi) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="kd-nota">
        Komisi dihitung atas dasar pembayaran yang sudah masuk
        (&ldquo;setelah pembayaran&rdquo;), bukan atas faktur yang belum lunas. Angka pada
        periode yang belum selesai masih dapat bertambah.
    </div>

    <div class="kd-cetak__ttd">
        <div><div class="kd-cetak__garis">Finance</div></div>
        <div><div class="kd-cetak__garis">{{ $dokter->nama }}</div></div>
    </div>
</div>
@endsection
