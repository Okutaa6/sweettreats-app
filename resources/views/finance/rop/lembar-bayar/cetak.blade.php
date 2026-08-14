{{--
    Lembar Bayar — HALAMAN CETAK.

    ⚠️ Blade ini BERDIRI SENDIRI dan sengaja TIDAK @extends('layouts.app'):
    sidebar, header, dan seluruh chrome aplikasi akan ikut tercetak. Ini juga
    pemakai @media print pertama di repo ini — kalau nanti ada halaman cetak
    lain, tiru berkas ini, jangan menempel print-CSS ke stylesheet aplikasi.

    Gaya ditulis INLINE, bukan lewat public/assets: berkas yang harus tercetak
    benar tidak boleh bergantung pada aset yang bisa 404 (public_html prod itu
    salinan manual, bukan symlink — CSS baru pernah tidak ikut ter-rsync). Font
    pun dari sistem, tanpa Google Fonts: dokumen cetak jangan menunggu jaringan.

    ⚠️ pay_to_text WAJIB tercetak apa adanya dan menonjol. Kolom bank di tabel
    vendors semuanya NULL dan vendor_id selalu NULL pada ROP nyata — orang
    menaruh tujuan transfer di kolom teks bebas itu ("VA MANDIRI - 896
    085888064313"). Itulah nomor rekening yang dipakai bos. Terpotong = uang
    salah alamat, jadi tidak ada Str::limit di halaman ini.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lembar->number }} — Rekap Permintaan Pembayaran</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1D1D1B;
            background: #EFE7DE;
            font-size: 12px;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .num { font-variant-numeric: tabular-nums; }

        /* ── Bilah alat: hanya di layar ───────────────────────────────────── */
        .bar {
            position: sticky; top: 0; z-index: 5;
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            padding: 12px 16px; background: #3A1100; color: #FBEADB;
        }
        .bar b { font-size: 13px; font-weight: 800; }
        .bar span { font-size: 12px; color: #E0C4AE; }
        .bar a, .bar button {
            font: inherit; font-weight: 700; cursor: pointer;
            padding: 8px 15px; border-radius: 9px; border: 1px solid transparent;
            text-decoration: none;
        }
        .bar button { background: #F4ECE2; color: #3A1100; }
        .bar a { background: transparent; color: #FBEADB; border-color: rgba(255,255,255,.32); }

        /* ── Halaman ──────────────────────────────────────────────────────── */
        .page {
            width: 210mm; min-height: 297mm;
            margin: 16px auto; padding: 16mm 14mm;
            background: #fff;
            box-shadow: 0 6px 24px rgba(0,0,0,.14);
        }

        /* ── Kepala ───────────────────────────────────────────────────────── */
        .kop {
            display: flex; gap: 14px; align-items: flex-start;
            padding-bottom: 12px; border-bottom: 2px solid #3A1100;
        }
        .kop img { height: 46px; width: auto; flex: none; }
        .kop__id { flex: 1; }
        .kop__id b { display: block; font-size: 15px; font-weight: 800; letter-spacing: -.01em; }
        .kop__id span { font-size: 11.5px; color: #6B5647; }
        .kop__doc { text-align: right; flex: none; }
        .kop__doc b { display: block; font-size: 14px; font-weight: 800; letter-spacing: .04em; }
        .kop__doc .no { margin-top: 3px; font-size: 13px; font-weight: 800; }
        .kop__doc .tgl { font-size: 11.5px; color: #6B5647; }

        /* ── Tabel daftar ─────────────────────────────────────────────────── */
        table.daftar { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.daftar th {
            background: #F1E7DC; text-align: left;
            font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em;
            padding: 7px 6px; border: 1px solid #C9B9A8;
        }
        table.daftar td { padding: 7px 6px; border: 1px solid #D8CBBC; vertical-align: top; }
        table.daftar .kanan, table.daftar th.kanan { text-align: right; }
        table.daftar .tengah, table.daftar th.tengah { text-align: center; }

        /* Nominal & nomor ROP TIDAK boleh membungkus. "Rp" di satu baris dan
           "2.100.000" di baris berikutnya bikin angka jadi ambigu sekilas — di
           dokumen yang dipakai mengeluarkan uang itu tidak bisa diterima.
           (Ketahuan dari cetakan sungguhan, bukan dari uji otomatis.) */
        table.daftar td.kanan, table.daftar td.nowrap { white-space: nowrap; }

        /* Tujuan transfer: bagian paling mahal kalau salah. Boleh melebar,
           boleh membungkus, tidak boleh terpotong. */
        .rek { font-weight: 800; font-size: 12px; word-break: break-word; }

        .ket { color: #40352C; }
        .lamp { font-size: 10px; font-weight: 700; white-space: nowrap; }
        .lamp--pdf { color: #2B4C86; }
        .lamp--nihil { color: #A0341F; }
        .lamp--foto { color: #1F6B41; }

        tr.total td {
            border-top: 2px solid #3A1100; background: #F7F1EA;
            font-size: 13px; font-weight: 800; padding: 9px 6px;
        }
        tr.total td.jml { font-size: 15px; white-space: nowrap; }

        .terbilang {
            margin-top: 10px; padding: 9px 11px;
            background: #F7F1EA; border: 1px solid #D8CBBC; border-radius: 6px;
            font-size: 11.5px;
        }
        .terbilang b { text-transform: uppercase; font-size: 9.5px; letter-spacing: .06em; color: #6B5647; }
        .terbilang i { display: block; margin-top: 2px; font-size: 12.5px; font-weight: 700; font-style: italic; }

        .catatan { margin-top: 10px; font-size: 11.5px; }
        .catatan b { font-size: 9.5px; text-transform: uppercase; letter-spacing: .06em; color: #6B5647; }

        .awas {
            margin-top: 10px; padding: 9px 11px;
            border: 1px solid #D8B9A6; border-left: 3px solid #A0341F;
            background: #FBF2EE; border-radius: 4px; font-size: 11px;
        }
        .awas b { display: block; margin-bottom: 2px; }
        .awas ul { margin: 4px 0 0; padding-left: 18px; }

        /* ── Tanda tangan ─────────────────────────────────────────────────── */
        .ttd { margin-top: 26px; display: flex; gap: 40px; }
        .ttd__kol { flex: 1; }
        .ttd__kol span { font-size: 11px; color: #6B5647; }
        .ttd__garis { margin-top: 46px; border-top: 1px solid #6B5647; padding-top: 4px; }
        .ttd__garis b { font-size: 12px; font-weight: 700; }
        .ttd__garis i { display: block; font-size: 10.5px; color: #6B5647; font-style: normal; }

        .kaki { margin-top: 18px; font-size: 9.5px; color: #8B7867; text-align: center; }

        /* ── Halaman lampiran ─────────────────────────────────────────────── */
        .lampiran-kop {
            display: flex; gap: 12px; justify-content: space-between; align-items: baseline;
            padding-bottom: 8px; margin-bottom: 12px; border-bottom: 1px solid #C9B9A8;
        }
        .lampiran-kop b { font-size: 13px; font-weight: 800; }
        .lampiran-kop .kecil { font-size: 11px; color: #6B5647; }
        .lampiran-kop .nom { font-size: 14px; font-weight: 800; white-space: nowrap; }
        .lampiran-gbr { text-align: center; }
        .lampiran-gbr img { max-width: 100%; max-height: 225mm; height: auto; }

        /* ── Cetak ────────────────────────────────────────────────────────── */
        @media print {
            body { background: #fff; font-size: 11px; }
            .no-print { display: none !important; }
            .page {
                width: auto; min-height: 0;
                margin: 0; padding: 0;
                box-shadow: none;
                /* Tiap halaman lampiran mulai di lembar baru. break-after
                   modern + page-break-after lama: Chrome mencetak lewat yang
                   pertama, sebagian PDF-writer lama masih baca yang kedua. */
                break-after: page; page-break-after: always;
            }
            .page:last-child { break-after: auto; page-break-after: auto; }
            table.daftar { break-inside: auto; }
            table.daftar tr { break-inside: avoid; page-break-inside: avoid; }
            thead { display: table-header-group; }   /* kepala tabel ikut di halaman lanjutan */
            .ttd { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    // Dua daftar yang harus diketahui bos & Finance sebelum berkas dikirim.
    $pdfTerpisah = [];
    $tanpaLampiran = [];
    foreach ($lembar->items as $it) {
        $j = $lampiran[$it->rop_id]['jenis'] ?? 'kosong';
        if ($j === 'pdf')    $pdfTerpisah[]   = $it->rop?->number;
        if ($j === 'kosong') $tanpaLampiran[] = $it->rop?->number;
    }
    $halamanLampiran = $lembar->items->filter(
        fn ($it) => ($lampiran[$it->rop_id]['jenis'] ?? null) === 'gambar'
    );
@endphp

{{-- Bilah ini tidak pernah ikut tercetak (.no-print). Sengaja TIDAK
     window.print() otomatis: lembar ini harus diperiksa mata dulu — nominal,
     nomor rekening, dan kelengkapan lampiran — sebelum jadi PDF. --}}
<div class="bar no-print">
    <b>{{ $lembar->number }}</b>
    <span>{{ $lembar->jumlah_item }} item &middot; {{ $rupiah($lembar->total) }}
        @if ($halamanLampiran->isNotEmpty())
            &middot; {{ $halamanLampiran->count() }} halaman lampiran
        @endif
    </span>
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    <span>atau tekan Ctrl+P &middot; pilih "Save as PDF"</span>
    <a href="{{ route('finance.rop.lembar.show', $lembar->id) }}">Kembali</a>
</div>

{{-- ══════════════ Halaman 1 — daftar ══════════════ --}}
<div class="page">
    <div class="kop">
        @if ($profil?->syslogo)
            <img src="{{ asset('sysprofile/' . $profil->syslogo) }}" alt="">
        @endif
        {{-- `?->` di kedua baris: kalau sys_profile kosong, dokumen tetap
             tercetak dengan nama cadangan — bukan halaman galat. --}}
        <div class="kop__id">
            <b>{{ $profil?->sysname ?: 'Sweet Treats' }}</b>
            <span>{{ $profil?->systitle ?: '' }}</span>
        </div>
        <div class="kop__doc">
            <b>REKAP PERMINTAAN PEMBAYARAN</b>
            <div class="no num">{{ $lembar->number }}</div>
            <div class="tgl num">{{ $lembar->dibuat_at?->translatedFormat('d F Y') }}
                &middot; {{ $lembar->jumlah_item }} item</div>
        </div>
    </div>

    <table class="daftar">
        <thead>
            <tr>
                <th class="tengah" style="width:24px">No</th>
                <th style="width:84px">No. ROP</th>
                {{-- "Departemen", bukan "Unit" — masukan Dela (Finance) 14 Agu:
                     bersebelahan dengan kolom Jumlah, "Unit" terbaca sebagai
                     satuan barang (pcs/box), padahal isinya cost centre. Di
                     dokumen pembayaran, kolom yang salah dibaca itu mahal.
                     Lebarnya ikut naik supaya "Kantor Pusat" tidak terpotong. --}}
                <th style="width:82px">Departemen</th>
                <th style="width:136px">Dibayarkan kepada</th>
                <th>Keterangan</th>
                <th class="kanan" style="width:96px">Jumlah</th>
                <th class="tengah" style="width:48px">Lampiran</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($lembar->items as $item)
            @php
                $r = $item->rop;
                $j = $lampiran[$item->rop_id]['jenis'] ?? 'kosong';
            @endphp
            <tr>
                <td class="tengah num">{{ $item->urutan ?: $loop->iteration }}</td>
                <td class="num nowrap">{{ $r?->number ?: '—' }}</td>
                <td>{{ $r?->costCentre?->nama ?: $r?->cost_centre }}</td>
                {{-- Nomor rekening apa adanya — tanpa dipotong, tanpa dipecah. --}}
                <td><span class="rek">{{ $r?->namaPenerima() ?: '—' }}</span></td>
                <td class="ket">{{ $r?->description }}</td>
                <td class="kanan num" style="font-weight:700">{{ $rupiah($item->amount) }}</td>
                <td class="tengah">
                    @if ($j === 'gambar')
                        <span class="lamp lamp--foto">terlampir</span>
                    @elseif ($j === 'pdf')
                        <span class="lamp lamp--pdf">PDF*</span>
                    @else
                        <span class="lamp lamp--nihil">tidak ada</span>
                    @endif
                </td>
            </tr>
        @endforeach
            <tr class="total">
                <td colspan="5">TOTAL {{ $lembar->jumlah_item }} PERMINTAAN</td>
                <td class="kanan num jml">{{ $rupiah($lembar->total) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="terbilang">
        <b>Terbilang</b>
        <i>{{ ucfirst($terbilang) }}</i>
    </div>

    @if ($lembar->catatan)
        <div class="catatan">
            <b>Catatan</b>
            <div>{{ $lembar->catatan }}</div>
        </div>
    @endif

    @if ($pdfTerpisah || $tanpaLampiran)
        <div class="awas">
            @if ($pdfTerpisah)
                <b>* Lampiran PDF dikirim terpisah</b>
                <div>Lampiran berformat PDF tidak bisa ditempel di lembar ini, jadi berkasnya
                    disertakan sebagai kiriman tersendiri:
                    {{ implode(', ', array_filter($pdfTerpisah)) }}.</div>
            @endif
            @if ($tanpaLampiran)
                <b style="{{ $pdfTerpisah ? 'margin-top:6px' : '' }}">Tanpa lampiran</b>
                <div>Permintaan berikut tidak menyertakan penawaran/invoice:
                    {{ implode(', ', array_filter($tanpaLampiran)) }}.</div>
            @endif
        </div>
    @endif

    <div class="ttd">
        <div class="ttd__kol">
            <span>Dibuat oleh</span>
            <div class="ttd__garis">
                <b>{{ $lembar->pembuat?->nama ?: '' }}</b>
                <i>Finance</i>
            </div>
        </div>
        <div class="ttd__kol">
            <span>Disetujui oleh</span>
            <div class="ttd__garis">
                <b>&nbsp;</b>
                <i>Director</i>
            </div>
        </div>
    </div>

    <div class="kaki">
        {{ $lembar->number }} &middot; dicetak {{ now()->translatedFormat('d F Y, H:i') }}
        @if ($halamanLampiran->isNotEmpty())
            &middot; diikuti {{ $halamanLampiran->count() }} halaman lampiran
        @endif
    </div>
</div>

{{-- ══════════════ Halaman lampiran — satu per halaman ══════════════ --}}
@foreach ($halamanLampiran as $item)
    @php $r = $item->rop; @endphp
    <div class="page">
        <div class="lampiran-kop">
            <div>
                <b class="num">{{ $r?->number }}</b>
                <div class="kecil">{{ $r?->namaPenerima() }} &middot; {{ $r?->description }}</div>
            </div>
            <div class="nom num">{{ $rupiah($item->amount) }}</div>
        </div>
        <div class="lampiran-gbr">
            <img src="{{ asset($lampiran[$item->rop_id]['path']) }}"
                 alt="Lampiran {{ $r?->number }}">
        </div>
    </div>
@endforeach

</body>
</html>
