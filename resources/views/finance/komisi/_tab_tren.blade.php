{{--
    Tab Tren Bulanan — ADAPTIF terhadap cakupan data yang benar-benar ada.

    Grafik batang DIDAMPINGI tabel, bukan menggantikannya. Finance membaca
    angkanya; grafik hanya untuk melihat bentuknya sekilas.

    Tren TIDAK mengikuti rentang tanggal di filter — dia selalu 12 bulan terakhir
    relatif ke bulan akhir periode. Filter cabang tetap berlaku.

    Kalau riwayat komisi belum 12 bulan (backfill belum dijalankan), bulan yang
    datanya belum masuk TIDAK digambar sama sekali dan diberi catatan. Bar nol
    untuk bulan tanpa data akan terbaca "dokter tidak dapat komisi" — kesimpulan
    yang salah, dan di halaman uang salah seperti itu mahal.
--}}
@php
    $rp   = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $juta = fn ($v) => $v >= 1000000
        ? rtrim(rtrim(number_format($v / 1000000, 1, ',', '.'), '0'), ',') . ' jt'
        : number_format($v / 1000, 0, ',', '.') . ' rb';

    $maks = $tren['maks'] ?: 1;
@endphp

@if (empty($tren['bulan']))
    <div class="kd-tabel-wrap">
        @include('finance.komisi._kosong', [
            'judul'    => 'Belum ada riwayat komisi',
            'ket'      => 'Tabel komisi belum berisi data sama sekali, jadi tren bulanan tidak bisa digambar.',
            'penyebab' => false,
            'kecil'    => true,
        ])
    </div>
@else
    <div class="kd-tabel-wrap">
        <div class="kd-blok__head">
            <span class="kd-blok__judul">
                Tren {{ count($tren['bulan']) }} bulan terakhir
            </span>
            <span class="kd-blok__ket">
                &mdash; basis &ldquo;setelah pembayaran&rdquo; &middot; {{ $branchLabel }}
            </span>
        </div>

        {{-- Catatan cakupan. Diletakkan DI ATAS grafik, bukan di kaki halaman:
             kalau ditaruh di bawah, grafiknya sudah terlanjur dibaca. --}}
        @if ($tren['parsial'])
            <div style="padding:0 16px 4px">
                <div class="kd-banner" style="margin-bottom:8px">
                    @include('finance.komisi._ikon', ['n' => 'info', 's' => 17])
                    <div>
                        Data komisi tersedia sejak <strong>{{ $tren['mulaiLbl'] }}</strong> &mdash;
                        tren menampilkan {{ count($tren['bulan']) }} bulan, belum 12.
                        Bulan sebelum itu tidak digambar karena datanya memang belum masuk,
                        bukan karena komisinya nol.
                    </div>
                </div>
            </div>
        @endif

        @if ($tren['tanggung'])
            <div style="padding:0 16px 4px">
                <div class="kd-banner" style="margin-bottom:8px">
                    @include('finance.komisi._ikon', ['n' => 'awas', 's' => 17])
                    <div>
                        <strong>{{ $tren['mulaiLbl'] }}</strong> tidak penuh &mdash; datanya baru
                        mulai {{ $tren['tglAwal'] }}. Jangan dibandingkan langsung dengan
                        bulan lain.
                    </div>
                </div>
            </div>
        @endif

        <div class="kd-chart">
            @foreach ($tren['bulan'] as $b)
                <div class="kd-chart__kol {{ $b->berjalan ? 'kd-chart__kol--jalan' : '' }}">
                    <span class="kd-chart__val">{{ $b->komisi > 0 ? $juta($b->komisi) : '—' }}</span>
                    <span class="kd-chart__bar {{ $b->berjalan ? 'kd-chart__bar--jalan' : '' }}"
                          style="height:{{ max(3, round($b->komisi / $maks * 140)) }}px"
                          title="{{ $b->label }} · {{ $rp($b->komisi) }}"></span>
                    <span class="kd-chart__lbl">
                        {{ \Carbon\Carbon::parse($b->ym . '-01')->translatedFormat('M y') }}
                    </span>
                </div>
            @endforeach
        </div>

        <table class="kd-tabel">
            <thead>
                <tr>
                    <th>Bulan</th>
                    <th class="num">Pasien</th>
                    <th class="num">Kunjungan</th>
                    <th class="num">Tindakan</th>
                    <th class="num">Komisi</th>
                    <th class="num">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_reverse($tren['bulan']) as $b)
                    <tr>
                        <td class="kuat">
                            {{ $b->label }}
                            @if ($b->berjalan)
                                <span class="kd-final">BELUM FINAL</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($b->pasien, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($b->kunjungan, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($b->tindakan, 0, ',', '.') }}</td>
                        <td class="num kuat">{{ $rp($b->komisi) }}</td>
                        <td class="num">
                            @if ($b->selisih === null)
                                {{-- Bulan paling awal cakupan: tidak ada pembanding.
                                     "—" jujur; "+Rp x" akan terbaca seolah naik dari nol. --}}
                                <span style="color:var(--kd-faint)">—</span>
                            @else
                                <span class="kd-delta kd-delta--{{ $b->selisih >= 0 ? 'naik' : 'turun' }}"
                                      style="margin:0">
                                    {{ $b->selisih >= 0 ? '+' : '−' }}{{ number_format(abs($b->selisih), 0, ',', '.') }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td>Total {{ count($tren['bulan']) }} bulan</td>
                    <td class="num"></td>
                    <td class="num">{{ number_format(collect($tren['bulan'])->sum('kunjungan'), 0, ',', '.') }}</td>
                    <td class="num">{{ number_format(collect($tren['bulan'])->sum('tindakan'), 0, ',', '.') }}</td>
                    <td class="num">{{ $rp(collect($tren['bulan'])->sum('komisi')) }}</td>
                    <td class="num"></td>
                </tr>
            </tfoot>
        </table>

        {{-- Kolom pasien pada baris total sengaja DIKOSONGKAN: satu pasien yang
             datang di dua bulan berbeda akan terhitung dua kali kalau kolomnya
             dijumlahkan, dan angka itu tidak punya arti apa pun. --}}
        <div class="kd-nota" style="padding:0 16px 14px">
            Kolom pasien tidak dijumlahkan antar bulan &mdash; satu pasien yang datang di
            dua bulan berbeda tetap satu orang, jadi penjumlahannya tidak bermakna.
        </div>
    </div>
@endif
