{{--
    Satu dokter = DUA <tr>: baris ringkas + baris dropdown beban per cabang.

    Dropdown inilah pengganti tab "Per Cabang" di layar detail — pemilihan
    cabang terjadi SEBELUM masuk detail, jadi Finance tidak perlu masuk-keluar
    halaman untuk membandingkan beban antar cabang.

    $d : objek dokter dari KomisiDokterController::dataDaftar()
--}}
@php
    // Inisial dari dua kata bermakna pertama (gelar "drg." dilewati).
    $kata = collect(preg_split('/\s+/', trim(preg_replace('/\b(drg|dr|Sp)\.?\s*/i', '', $d->nama))))
        ->filter()->values();
    $inisial = strtoupper(mb_substr($kata[0] ?? $d->nama, 0, 1) . mb_substr($kata[1] ?? '', 0, 1));

    $paramDasar = ['date_from' => $start, 'date_to' => $end];

    // Link "Semua cabang" — tanpa place. Untuk user terkunci, place tidak
    // pernah disisipkan; controller mengabaikannya, tapi URL-nya pun jangan
    // sampai menjanjikan sesuatu yang tidak terjadi.
    $urlSemua = route('komisi.show', $d->doc_id) . '?' . http_build_query(
        $branchLocked ? $paramDasar : array_filter(array_merge($paramDasar, ['place' => $placeId]))
    );

    $totalCabang = $d->cabang->sum('komisi');
@endphp

<tr class="kd-row" data-drop="drop-{{ $d->doc_id }}" tabindex="0"
    role="button" aria-expanded="false" aria-controls="drop-{{ $d->doc_id }}"
    title="Klik untuk lihat beban komisi per cabang"
    @class(['kd-row--off' => $d->nonaktif])>
    <td>
        <div class="kd-doc">
            <span class="kd-avatar">
                @if ($d->foto)
                    <img src="{{ asset('uploads/doctors/' . $d->foto) }}" alt="">
                @else
                    {{ $inisial }}
                @endif
            </span>
            <div>
                <div class="kd-doc__nama">{{ $d->nama }}</div>
                <div class="kd-doc__meta">
                    @include('finance.komisi._chip_spek', ['nama' => $d->nama])
                    {{-- Badge status HANYA untuk dokter yang benar-benar
                         ter-mapping ke master. Yang belum ter-mapping statusnya
                         memang tidak diketahui — diam lebih jujur daripada
                         menampilkan "Aktif" yang belum tentu benar. --}}
                    @if ($d->nonaktif)
                        <span class="kd-badge-off">Nonaktif</span>
                    @endif
                </div>
            </div>
        </div>
    </td>
    <td><span class="kd-cabang-list">{{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($d->cabangList) }}</span></td>
    <td class="num">{{ number_format($d->pasien, 0, ',', '.') }}</td>
    <td class="num">{{ number_format($d->kunjungan, 0, ',', '.') }}</td>
    <td class="num">{{ number_format($d->tindakan, 0, ',', '.') }}</td>
    <td class="num kuat" style="font-size:16px;font-weight:800">
        Rp {{ number_format($d->komisi, 0, ',', '.') }}
    </td>
    <td class="num">
        <span class="kd-delta kd-delta--{{ $d->delta['tone'] }}" style="margin:0">
            {{ $d->delta['txt'] }}
            <span class="kd-delta__sub">{{ $d->delta['sub'] }}</span>
        </span>
    </td>
    <td class="num" style="width:52px">
        <span class="kd-chev">@include('finance.komisi._ikon', ['n' => 'lanjut', 's' => 15])</span>
    </td>
</tr>

<tr class="kd-drop" id="drop-{{ $d->doc_id }}" hidden>
    <td colspan="8">
        <div class="kd-drop__isi">
            <div class="kd-drop__head">
                <span class="kd-drop__judul">Beban komisi per cabang</span>
                <span class="kd-drop__ket">
                    &mdash; pilih cabang untuk buka rinciannya, atau lihat gabungan semua cabang.
                </span>
                <span style="margin-left:auto">
                    <a class="kd-btn kd-btn--sm" href="{{ $urlSemua }}">
                        Semua cabang
                        @include('finance.komisi._ikon', ['n' => 'lanjut', 's' => 14])
                    </a>
                </span>
            </div>

            <table class="kd-subtabel">
                <thead>
                    <tr>
                        <th>Cabang</th>
                        <th class="num">Pasien</th>
                        <th class="num">Kunjungan</th>
                        <th class="num">Tindakan</th>
                        <th class="num">Omzet Dikomisikan</th>
                        <th class="num">Komisi</th>
                        <th class="num">Porsi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($d->cabang as $c)
                        @php
                            $porsi = $d->komisi > 0 ? $c->komisi / $d->komisi * 100 : 0;
                            $urlCabang = route('komisi.show', $d->doc_id) . '?' . http_build_query(
                                $branchLocked ? $paramDasar
                                              : array_merge($paramDasar, ['place' => $c->place_id])
                            );
                        @endphp
                        <tr onclick="location.href='{{ $urlCabang }}'"
                            title="Buka rincian {{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($c->branch_name) }}">
                            {{-- Nama cabang dibuat tautan sungguhan, bukan hanya
                                 sel ber-onclick: baris yang cuma bisa diklik
                                 mouse tidak akan pernah terjangkau keyboard. --}}
                            <td class="kuat">
                                <a href="{{ $urlCabang }}">{{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($c->branch_name) }}</a>
                            </td>
                            <td class="num">{{ number_format($c->pasien, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($c->kunjungan, 0, ',', '.') }}</td>
                            <td class="num">{{ number_format($c->tindakan, 0, ',', '.') }}</td>
                            <td class="num">Rp {{ number_format($c->omzet, 0, ',', '.') }}</td>
                            <td class="num kuat">Rp {{ number_format($c->komisi, 0, ',', '.') }}</td>
                            <td class="num">
                                <span class="kd-porsi">
                                    <span class="kd-porsi__bar">
                                        <span class="kd-porsi__isi" style="width:{{ number_format($porsi, 1, '.', '') }}%"></span>
                                    </span>
                                    <span class="kd-porsi__angka">{{ number_format($porsi, 1, ',', '.') }}%</span>
                                </span>
                            </td>
                            <td class="num" style="width:44px">
                                <span class="kd-chev kd-chev--sm">
                                    @include('finance.komisi._ikon', ['n' => 'lanjut', 's' => 13])
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total {{ $d->nCabang }} cabang</td>
                        {{-- PASIEN & KUNJUNGAN diambil dari baris dokter, BUKAN
                             menjumlahkan kolom di atasnya: satu pasien bisa
                             datang ke dua cabang dan akan terhitung dua kali. --}}
                        <td class="num">{{ number_format($d->pasien, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($d->kunjungan, 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($d->tindakan, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format($d->omzet, 0, ',', '.') }}</td>
                        <td class="num">Rp {{ number_format($totalCabang, 0, ',', '.') }}</td>
                        <td class="num">100,0%</td>
                        {{-- Baris total sengaja TANPA chevron: dia bukan tautan,
                             dan memberinya penanda yang sama akan menjanjikan
                             klik yang tidak akan terjadi. --}}
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            @if ($d->nCabang > 1)
                <div class="kd-nota">
                    Pasien &amp; kunjungan pada baris total dihitung unik lintas cabang &mdash;
                    bukan penjumlahan kolom di atasnya. Satu pasien yang berobat di dua cabang
                    tetap dihitung satu orang.
                </div>
            @endif
        </div>
    </td>
</tr>
