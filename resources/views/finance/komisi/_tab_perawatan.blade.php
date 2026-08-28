{{--
    Tab Per Perawatan — menjawab "komisi dari perawatan apa saja".

    Tabel terpenting di halaman ini, jadi diberi ruang paling besar.

    Kolom SKEMA punya perilaku khusus: kalau satu perawatan dihitung dengan
    cara berbeda antar cabang, yang ditampilkan adalah RENTANG, bukan salah satu
    angkanya. Menampilkan satu angka saja membuat Finance menjelaskan rate yang
    salah ke dokter yang bertanya.
--}}
@php
    $urlTab = function (array $over) use ($start, $end, $placeId, $branchLocked, $docId) {
        $p = array_filter(array_merge([
            'date_from' => $start,
            'date_to'   => $end,
            'place'     => $branchLocked ? null : $placeId,
            'tab'       => 'perawatan',
        ], $over), fn ($v) => $v !== null && $v !== '');

        return route('komisi.show', $docId) . '?' . http_build_query($p);
    };

    $sortKini = request('sort', 'komisi');
    $dirKini  = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';

    $th = function (string $key, string $label, bool $kanan = false)
          use ($urlTab, $sortKini, $dirKini) {
        $aktif = $sortKini === $key;
        $arah  = ($aktif && $dirKini === 'desc') ? 'asc' : 'desc';
        $panah = $aktif ? ($dirKini === 'desc' ? ' ↓' : ' ↑') : '';

        return '<th class="' . ($kanan ? 'num' : '') . '">'
             . '<a class="' . ($aktif ? 'on' : '') . '" href="'
             . e($urlTab(['sort' => $key, 'dir' => $arah])) . '">'
             . e($label) . $panah . '</a></th>';
    };

    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%';
    $rp  = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

    $totalKomisi = $perawatan->sum('komisi');
@endphp

<div class="kd-tabel-wrap">
    <div class="kd-blok__head">
        <span class="kd-blok__judul">Komisi per perawatan</span>
        <span class="kd-blok__ket">&mdash; skema hitung per tindakan</span>
    </div>

    @if ($perawatan->isEmpty())
        @include('finance.komisi._kosong', [
            'judul'    => 'Tidak ada tindakan berkomisi di periode ini',
            'ket'      => 'Dokter ini tidak punya baris tindakan pada rentang tanggal dan cabang yang sedang dipilih.',
            'penyebab' => false,
            'kecil'    => true,
        ])
    @else
        <table class="kd-tabel kd-tabel__klik">
            <thead>
                <tr>
                    {!! $th('kode', 'Kode') !!}
                    {!! $th('nama', 'Nama Tindakan') !!}
                    {!! $th('jumlah', 'Jumlah', true) !!}
                    {!! $th('omzet', 'Omzet Dikomisikan', true) !!}
                    <th>Skema</th>
                    {!! $th('komisi', 'Komisi', true) !!}
                    <th class="num">Porsi</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perawatan as $r)
                    @php
                        $porsi = $totalKomisi > 0 ? $r->komisi / $totalKomisi * 100 : 0;
                        $adaPct  = $r->pct_min !== null;
                        $adaFlat = $r->flat_min !== null;
                        $beragam = (int) $r->n_skema > 1;

                        if (! $beragam) {
                            $labelSkema = $adaPct ? $pct($r->pct_min) : $rp($r->flat_min) . ' flat';
                        } elseif ($adaPct && $adaFlat) {
                            $labelSkema = $pct($r->pct_min) . ' / ' . $rp($r->flat_min) . ' flat';
                        } elseif ($adaPct) {
                            $labelSkema = $pct($r->pct_min) . ' – ' . $pct($r->pct_max);
                        } else {
                            $labelSkema = $rp($r->flat_min) . ' – ' . $rp($r->flat_max) . ' flat';
                        }

                        // Tooltip berisi daftar skema per cabang — supaya Finance
                        // bisa menjawab "kenapa beda" tanpa membuka tab lain.
                        $rincianSkema = ($skema[$r->item_code] ?? collect())
                            ->map(fn ($s) => $s->branch_name . ' '
                                . ($s->ctype === '%' ? $pct($s->commission_rate)
                                                     : $rp($s->commission_rate) . ' flat')
                                . ' (' . number_format($s->n_baris, 0, ',', '.') . ' baris)')
                            ->implode(' · ');
                    @endphp
                    @php $urlBukti = $urlTab(['tab' => 'rincian', 'code' => $r->item_code]); @endphp
                    <tr class="kd-row" onclick="location.href='{{ $urlBukti }}'"
                        title="Lihat bukti baris per baris untuk {{ $r->item_name }}">
                        <td style="color:var(--kd-brand-solid);font-weight:800">{{ $r->item_code }}</td>
                        {{-- Nama tindakan dibuat tautan sungguhan supaya baris ini
                             juga terjangkau keyboard, bukan hanya mouse. --}}
                        <td class="kuat"><a href="{{ $urlBukti }}">{{ $r->item_name }}</a></td>
                        <td class="num">{{ number_format($r->jumlah, 0, ',', '.') }}</td>
                        <td class="num">{{ $rp($r->omzet) }}</td>
                        <td>
                            <span class="kd-skema {{ $beragam ? 'kd-skema--awas' : '' }}"
                                  @if ($beragam) title="{{ $rincianSkema }}" @endif>
                                @if ($beragam)
                                    @include('finance.komisi._ikon', ['n' => 'awas', 's' => 13])
                                @endif
                                {{ $labelSkema }}
                            </span>
                        </td>
                        <td class="num kuat">{{ $rp($r->komisi) }}</td>
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
                    <td colspan="2">Total {{ $perawatan->count() }} perawatan</td>
                    <td class="num">{{ number_format($perawatan->sum('jumlah'), 0, ',', '.') }}</td>
                    <td class="num">{{ $rp($perawatan->sum('omzet')) }}</td>
                    <td></td>
                    <td class="num">{{ $rp($totalKomisi) }}</td>
                    <td class="num">100,0%</td>
                    {{-- Baris total bukan tautan — tanpa chevron, sama seperti
                         baris total di tabel per cabang. --}}
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
