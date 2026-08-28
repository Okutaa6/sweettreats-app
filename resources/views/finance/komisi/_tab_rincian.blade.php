{{--
    Tab Rincian — bukti baris per baris.

    Inilah alat yang dipakai Finance saat dokter bertanya "kok segini?". Karena
    itu semuanya server-side: pencarian, urutan, dan pagination harus bekerja
    atas SELURUH hasil filter, bukan atas 25 baris yang kebetulan tampil.

    Footer menampilkan DUA angka: subtotal halaman ini dan total seluruh hasil
    filter. Tanpa pembedaan itu, orang akan menyalin subtotal 25 baris dan
    mengira itu total komisi dokternya.
--}}
@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

    $urlTab = function (array $over) use ($start, $end, $placeId, $branchLocked, $docId) {
        $p = array_filter(array_merge([
            'date_from' => $start,
            'date_to'   => $end,
            'place'     => $branchLocked ? null : $placeId,
            'tab'       => 'rincian',
            'code'      => request('code'),
            'q'         => request('q'),
            'sort'      => request('sort'),
            'dir'       => request('dir'),
            'per'       => request('per'),
        ], $over), fn ($v) => $v !== null && $v !== '');

        return route('komisi.show', $docId) . '?' . http_build_query($p);
    };

    $sortKini = request('sort', 'tanggal');
    $dirKini  = request('dir', 'desc') === 'asc' ? 'asc' : 'desc';

    $th = function (string $key, string $label, bool $kanan = false, string $kelas = '')
          use ($urlTab, $sortKini, $dirKini) {
        $aktif = $sortKini === $key;
        $arah  = ($aktif && $dirKini === 'desc') ? 'asc' : 'desc';
        $panah = $aktif ? ($dirKini === 'desc' ? ' ↓' : ' ↑') : '';

        return '<th class="' . trim(($kanan ? 'num ' : '') . $kelas) . '">'
             . '<a class="' . ($aktif ? 'on' : '') . '" href="'
             // Ganti filter/urutan = kembali ke halaman 1. Tetap di halaman 4
             // setelah menyaring adalah cara tercepat melihat "tidak ada hasil"
             // padahal hasilnya ada.
             . e($urlTab(['sort' => $key, 'dir' => $arah, 'page' => null])) . '">'
             . e($label) . $panah . '</a></th>';
    };

    $subtotalHalaman = collect($baris->items())->sum('commission_amount');
@endphp

<div class="kd-tabel-wrap">
    <div class="kd-blok__head">
        <span class="kd-blok__judul">Rincian tindakan</span>
        <span class="kd-blok__ket">
            &mdash; bukti baris per baris untuk ditunjukkan ke dokter
        </span>

        <span style="margin-left:auto;display:flex;gap:10px;align-items:center" class="noprint">
            <form method="GET" action="{{ route('komisi.show', $docId) }}" class="kd-search">
                <input type="hidden" name="date_from" value="{{ $start }}">
                <input type="hidden" name="date_to" value="{{ $end }}">
                @if (! $branchLocked && $placeId)
                    <input type="hidden" name="place" value="{{ $placeId }}">
                @endif
                <input type="hidden" name="tab" value="rincian">
                @if (request('code'))
                    <input type="hidden" name="code" value="{{ request('code') }}">
                @endif
                @include('finance.komisi._ikon', ['n' => 'cari', 's' => 16])
                <input class="kd-input" type="search" name="q" value="{{ request('q') }}"
                       placeholder="Pasien, tindakan, no. invoice, kode…"
                       aria-label="Cari di rincian">
            </form>

            <span class="kd-page__info">
                {{ $baris->total() > 0 ? $baris->firstItem() . '–' . $baris->lastItem() : 0 }}
                dari {{ number_format($baris->total(), 0, ',', '.') }} baris tindakan
            </span>
        </span>
    </div>

    @if ($baris->total() === 0)
        @include('finance.komisi._kosong', [
            'judul'    => 'Tidak ada baris yang cocok',
            'ket'      => 'Coba hapus chip penyaring atau kosongkan kotak pencarian di atas.',
            'penyebab' => false,
            'kecil'    => true,
        ])
    @else
        <div class="kd-scroll">
            <table class="kd-tabel">
                <thead>
                    <tr>
                        {!! $th('tanggal', 'Tanggal', false, 'lengket-1') !!}
                        {!! $th('pasien', 'Pasien', false, 'lengket-2') !!}
                        {!! $th('invoice', 'No. Invoice') !!}
                        {!! $th('tindakan', 'Tindakan') !!}
                        <th class="num">Jumlah</th>
                        {!! $th('omzet', 'Omzet', true) !!}
                        <th>Skema</th>
                        {!! $th('komisi', 'Komisi', true) !!}
                        {!! $th('cabang', 'Cabang') !!}
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $r)
                        <tr>
                            <td class="lengket-1" style="width:104px;white-space:nowrap">
                                {{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('j M Y') }}
                            </td>
                            <td class="lengket-2 kuat" style="min-width:150px">
                                {{ $r->p_name ?: '—' }}
                            </td>
                            <td style="white-space:nowrap">{{ $r->invoice_num }}</td>
                            <td>
                                {{ $r->item_name }}
                                <span style="color:var(--kd-faint);font-size:11px">{{ $r->item_code }}</span>
                            </td>
                            <td class="num">{{ number_format($r->qty_sold, 0, ',', '.') }}</td>
                            <td class="num">{{ $rp($r->net_sell_price) }}</td>
                            <td>
                                <span class="kd-skema">
                                    {{ \App\Http\Controllers\Finance\KomisiDokterController::labelSkema($r->commission_type, (float) $r->commission_rate) }}
                                </span>
                            </td>
                            <td class="num kuat">{{ $rp($r->commission_amount) }}</td>
                            <td style="white-space:nowrap">{{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($r->branch_name) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="kd-page">
            <div>
                <div class="kd-page__info">
                    Subtotal halaman ini: {{ $rp($subtotalHalaman) }}
                </div>
                <div class="kd-page__sub">
                    Total seluruh hasil filter:
                    <span class="kd-page__total">{{ $rp($totalFilter) }}</span>
                </div>
            </div>

            <button type="button" class="kd-btn kd-btn--sm kd-salin noprint"
                    data-nilai="{{ number_format($totalFilter, 0, ',', '.') }}">
                @include('finance.komisi._ikon', ['n' => 'salin', 's' => 14])
                Salin total
            </button>

            <div class="kd-page__sisi noprint">
                <select class="kd-select" id="kd-per" aria-label="Baris per halaman"
                        style="height:32px;font-size:12px">
                    @foreach ($perPilihan as $n)
                        <option value="{{ $urlTab(['per' => $n, 'page' => null]) }}"
                                {{ $perPage === $n ? 'selected' : '' }}>{{ $n }} / halaman</option>
                    @endforeach
                </select>

                <a class="kd-btn kd-btn--sm {{ $baris->onFirstPage() ? 'kd-btn--mati' : '' }}"
                   href="{{ $baris->previousPageUrl() ?: '#' }}">
                    @include('finance.komisi._ikon', ['n' => 'kembali', 's' => 14])
                    Sebelumnya
                </a>

                <span class="kd-page__info">
                    Hal. {{ $baris->currentPage() }} / {{ $baris->lastPage() }}
                </span>

                <a class="kd-btn kd-btn--sm {{ $baris->hasMorePages() ? '' : 'kd-btn--mati' }}"
                   href="{{ $baris->nextPageUrl() ?: '#' }}">
                    Berikutnya
                    @include('finance.komisi._ikon', ['n' => 'lanjut', 's' => 14])
                </a>
            </div>
        </div>
    @endif
</div>
