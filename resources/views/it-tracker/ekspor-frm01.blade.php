{{--
    Ekspor FRM-IT-01 — Requirement Request, bentuk templat resmi.

    ⚠️ Bagian A (Identitas Pemohon) tampil TERISI walau layar pengisian tak
    pernah menanyakannya: kertas bertanya karena kertas tak tahu, sistem sudah
    tahu — tapi Konsultan IT & Management harus mengenali dokumennya
    (rencana §7 / design brief §3). Nama bagian mengikuti templat, jangan
    "dimanusiakan" di sini; yang dimanusiakan itu layar pengisiannya.

    Layout bare + @media print: yang tercetak hanya dokumennya.
--}}
@extends('layouts.bare')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="itk-doc-wrap">

    <div class="itk-doc-bar">
        <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.show', $t->id) }}">‹ Kembali ke tiket</a>
        <button type="button" class="itk-btn itk-btn-grafit" onclick="window.print()">Cetak / Simpan PDF</button>
        <span class="itk-doc-bar-note">Bagian A terisi otomatis dari akun pemohon — layar pengisian tidak menanyakannya.</span>
    </div>

    <div class="itk-doc">
        <div class="itk-doc-head">
            <div class="itk-doc-logo">ST</div>
            <div style="flex:1">
                <div class="itk-doc-title">FRM-IT-01 — REQUIREMENT REQUEST</div>
                <div class="itk-doc-sub">Sweet Treats Dental Clinic · IT &amp; Management</div>
            </div>
            <div class="itk-doc-no num">{{ $t->nomor }}</div>
        </div>

        <div class="itk-doc-sec">
            <span>A. IDENTITAS PEMOHON</span>
            <em class="itk-doc-auto">terisi otomatis dari akun — tidak pernah ditanyakan di layar</em>
        </div>
        <div class="itk-doc-tbl">
            @foreach ($ident as $k => $v)
                <div class="itk-doc-row"><span class="k">{{ $k }}</span><span class="v">{{ $v }}</span></div>
            @endforeach
        </div>

        <div class="itk-doc-sec"><span>B. DETAIL KEBUTUHAN</span></div>
        <div class="itk-doc-tbl">
            @foreach ($detail as $k => $v)
                <div class="itk-doc-row"><span class="k">{{ $k }}</span><span class="v">{{ $v }}</span></div>
            @endforeach
        </div>

        <div class="itk-doc-sec"><span>C. REVIEW AWAL (DIISI IT)</span></div>
        <div class="itk-doc-tbl">
            <div class="itk-doc-row" style="grid-template-columns:1fr">
                <span class="v">
                    {{ $diterima ? '☑' : '☐' }} Diterima untuk dianalisis &nbsp;&nbsp;
                    ☐ Perlu dilengkapi &nbsp;&nbsp;
                    {{ in_array($t->fase, ['DITUNDA', 'DITOLAK'], true) ? '☑' : '☐' }} Ditunda / Ditolak
                    @if ($t->alasan_parkir)
                        <br><span style="color:#55534E">Catatan: {{ $t->alasan_parkir }}</span>
                    @elseif (! $diterima)
                        <br><span style="color:#55534E">Catatan: menunggu triase IT</span>
                    @else
                        <br><span style="color:#55534E">Jenjang {{ $t->jenjang }} · bobot {{ $t->bobot }} · prioritas IT {{ $t->prioritas_it }}</span>
                    @endif
                </span>
            </div>
        </div>

        <div class="itk-doc-ttd">
            <div>
                <div class="garis"></div>
                <div class="lbl">Pemohon — {{ $t->pemohon->nama ?? '—' }} (otomatis saat kirim)</div>
            </div>
            <div>
                <div class="garis"></div>
                <div class="lbl">IT — terima / tolak{{ $t->pic ? ' · ' . $t->pic->nama : '' }}</div>
            </div>
        </div>

        <div class="itk-doc-kaki">
            Tampilan ekspor mengikuti templat resmi — layar pengisian tidak menanyakan bagian A.
            Dicetak {{ now()->translatedFormat('j F Y H:i') }}.
        </div>
    </div>
</div>
@endsection
