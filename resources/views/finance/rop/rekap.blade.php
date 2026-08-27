{{--
    Rekap Pengeluaran — Finance & Executive (zona burgundy).

    Periode dihitung dari TANGGAL BAYAR, bukan tanggal diajukan: pengajuan
    akhir Juli yang ditransfer Agustus adalah pengeluaran Agustus, sama seperti
    pembukuan. Kalau dihitung dari tanggal pengajuan, angka di sini tidak akan
    pernah cocok dengan Accurate.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $totalCc = $perCostCentre->sum('total');
    $totalAkun = $perAkun->sum('total');
@endphp

<div class="rop-fin">

    <div class="rop-fin__head">
        <div>
            <h1>Rekap Pengajuan</h1>
            <div class="rop-sub">
                {{ $periode->translatedFormat('F Y') }} &middot; dihitung dari tanggal pembayaran
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop') }}">
                @include('rop._ikon', ['n' => 'kembali'])
                Antrian
            </a>
            {{-- Layar sebelah, bukan versi lain dari halaman ini: yang di sini
                 rekap AKUNTANSI bulanan, yang di sana dokumen minta uang. --}}
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop.lembar') }}">
                @include('rop._ikon', ['n' => 'kirim'])
                Lembar Bayar
            </a>
            <a class="rop-btn rop-btn--ghost"
               href="{{ route('finance.rop.rekap.export', ['bulan' => $periode->format('Y-m'), 'cc' => $cc]) }}">
                @include('rop._ikon', ['n' => 'unduh'])
                Export CSV
            </a>
            {{-- Ditandai "draf" di tombolnya sendiri, bukan cuma di catatan di
                 bawah: tombol yang tampak siap pakai akan dipakai, dan catatan
                 sepanjang apa pun tidak menahan orang yang sedang buru-buru. --}}
            <a class="rop-btn rop-btn--ghost"
               href="{{ route('finance.rop.export.accurate', ['bulan' => $periode->format('Y-m'), 'cc' => $cc]) }}">
                @include('rop._ikon', ['n' => 'unduh'])
                Draf untuk Accurate
            </a>
        </div>
    </div>

    @include('layouts.notif')

    <form class="rop-toolbar" method="GET">
        <input class="rop-input" type="month" name="bulan" value="{{ $periode->format('Y-m') }}">
        <select class="rop-select" name="cc">
            <option value="">Semua cost centre</option>
            @foreach ($costCentres as $c)
                <option value="{{ $c->kode }}" {{ $cc === $c->kode ? 'selected' : '' }}>{{ $c->nama }}</option>
            @endforeach
        </select>
        <button class="rop-btn rop-btn--ghost" style="min-height:42px">Tampilkan</button>
    </form>

    {{-- Header CSV Accurate belum diverifikasi terhadap template resmi —
         dikatakan terang-terangan supaya tidak dipakai impor produksi dengan
         asumsi yang salah. --}}
    <div class="rop-note rop-note--kuning" style="margin-bottom:14px">
        <b>"Draf untuk Accurate" belum siap dipakai impor.</b>
        Datanya lengkap, tapi susunan kolomnya belum dicocokkan dengan berkas template
        impor resmi Accurate — jangan diimpor dulu.
        <div style="margin-top:6px">
            Justru sebaliknya: unduh berkas ini dan bawa ke Finance untuk dicocokkan
            dengan template mereka. Begitu susunan kolomnya diketahui, tinggal
            disesuaikan sekali dan catatan ini hilang.
        </div>
    </div>

    <div class="rop-fin-stats">
        <div class="rop-fin-stat rop-fin-stat--utama">
            <span class="rop-label">Total dibayar</span>
            <b class="rop-num">{{ $rupiah($ringkas['total_dibayar']) }}</b>
            <small>{{ $periode->translatedFormat('F Y') }}</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Jumlah pengajuan</span>
            <b class="rop-num">{{ $ringkas['jumlah'] }}</b>
            <small>sudah dibayar</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Rata-rata lama proses</span>
            <b class="rop-num">{{ $ringkas['rata_hari'] === null ? '—' : $ringkas['rata_hari'] . ' hari' }}</b>
            <small>diajukan sampai dibayar</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Masih antre</span>
            <b class="rop-num">{{ $rupiah($ringkas['masih_antre']) }}</b>
            <small>belum dibayar</small>
        </div>
    </div>

    <div class="rop-rekap-grid" style="margin-bottom:14px">
        <div class="rop-tabel-wrap">
            <table class="rop-tabel rop-tabel--ringkas">
                <thead>
                    <tr>
                        <th>Cost centre</th>
                        <th class="rop-td-kanan">Jumlah</th>
                        <th class="rop-td-kanan">Total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($perCostCentre as $row)
                    <tr>
                        <td>{{ $row->cost_centre }}</td>
                        <td class="rop-td-kanan rop-num">{{ $row->jumlah }}</td>
                        <td class="rop-td-kanan">
                            <div class="rop-num">{{ $rupiah($row->total) }}</div>
                            <div class="rop-bar" style="width: {{ $totalCc > 0 ? round($row->total / $totalCc * 100) : 0 }}%"></div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--rop-muted);padding:26px">
                        Belum ada pembayaran di periode ini.
                    </td></tr>
                @endforelse
                </tbody>
                @if ($perCostCentre->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="rop-td-kanan rop-num">{{ $perCostCentre->sum('jumlah') }}</td>
                            <td class="rop-td-kanan rop-num">{{ $rupiah($totalCc) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="rop-tabel-wrap">
            <table class="rop-tabel rop-tabel--ringkas">
                <thead>
                    <tr>
                        <th>Akun beban</th>
                        <th class="rop-td-kanan">Jumlah</th>
                        <th class="rop-td-kanan">Total</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($perAkun as $row)
                    <tr>
                        <td>
                            <span class="rop-num">{{ $row->coa_no }}</span>
                            <div class="rop-mini">{{ $row->coa_name }}</div>
                        </td>
                        <td class="rop-td-kanan rop-num">{{ $row->jumlah }}</td>
                        <td class="rop-td-kanan rop-num">{{ $rupiah($row->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--rop-muted);padding:26px">
                        Belum ada data.
                    </td></tr>
                @endforelse
                </tbody>
                @if ($perAkun->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="rop-td-kanan rop-num">{{ $perAkun->sum('jumlah') }}</td>
                            <td class="rop-td-kanan rop-num">{{ $rupiah($totalAkun) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="rop-tabel-wrap">
        <table class="rop-tabel">
            <colgroup>
                <col style="width:17%"><col style="width:12%"><col style="width:16%">
                <col style="width:25%"><col style="width:10%"><col style="width:12%">
                <col style="width:8%">
            </colgroup>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Tanggal bayar</th>
                    <th>Pengaju</th>
                    <th>Keterangan</th>
                    <th>Akun</th>
                    <th class="rop-td-kanan">Nominal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($transaksi as $t)
                <tr>
                    <td class="rop-num">{{ $t->number }}</td>
                    <td class="rop-num">{{ $t->paid_at?->translatedFormat('d M Y') }}</td>
                    <td>
                        {{ $t->requester?->nama }}
                        <div class="rop-mini">{{ $t->costCentre?->nama ?: $t->cost_centre }}</div>
                    </td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($t->description, 60) }}
                        <div class="rop-mini">{{ $t->namaPenerima() }}</div>
                    </td>
                    <td class="rop-num">{{ $t->coa_no }}</td>
                    <td class="rop-td-kanan rop-num" style="font-weight:700">{{ $rupiah($t->amount) }}</td>
                    <td>@include('rop._pill', ['status' => $t->status, 'pendek' => true])</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:var(--rop-muted);padding:40px">
                    Belum ada transaksi di periode ini.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
