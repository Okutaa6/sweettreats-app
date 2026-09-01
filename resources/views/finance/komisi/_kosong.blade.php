{{--
    Empty state yang mengajari, bukan sekadar "Tidak ada data".

    Kalau Finance membuka halaman ini dan kosong, pertanyaannya selalu sama:
    "salah filter, atau datanya yang belum masuk?" Panel ini menjawab ketiganya
    sekaligus dan memberi dua jalan keluar yang bisa langsung diklik.

    $judul, $ket : opsional, untuk empty state kecil per tab
    $penyebab    : false → sembunyikan daftar penyebab & tombol
--}}
@php
    $judul    = $judul ?? 'Belum ada komisi di periode ini';
    $ket      = $ket ?? null;
    $penyebab = $penyebab ?? true;
    $kecil    = $kecil ?? false;

    $paramReset = ['date_from' => $bulanLalu[0], 'date_to' => $bulanLalu[1]];
@endphp

<div class="kd-empty {{ $kecil ? 'kd-empty--kecil' : '' }}">
    <div style="color:var(--kd-faint);margin-bottom:10px">
        @include('finance.komisi._ikon', ['n' => 'kosong', 's' => 34])
    </div>

    <div class="kd-empty__judul">{{ $judul }}</div>

    @if ($ket)
        <div class="kd-empty__ket">{{ $ket }}</div>
    @endif

    @if ($penyebab)
        <div class="kd-empty__ket">Tiga hal yang biasanya jadi penyebabnya:</div>
        <ul class="kd-empty__list">
            <li>
                <strong>Rentang tanggalnya</strong> &mdash; sekarang
                {{ \Carbon\Carbon::parse($start)->translatedFormat('j M Y') }}
                s/d {{ \Carbon\Carbon::parse($end)->translatedFormat('j M Y') }}.
            </li>
            <li>
                <strong>Cabangnya</strong> &mdash; sekarang {{ $branchLabel }}.
                @if ($branchLocked)
                    Akses Anda terkunci ke cabang ini.
                @endif
            </li>
            <li>
                <strong>Sinkronisasi datanya</strong> &mdash; {{ strtolower($sinkron['teks']) }}.
                Data komisi masuk dari Omnicare tiap hari pukul 02:00.
            </li>
        </ul>
        <div class="kd-empty__aksi noprint">
            <a class="kd-btn kd-btn--primary"
               href="{{ route('komisi.index') }}?{{ http_build_query($paramReset) }}">
                Lihat {{ $bulanLaluLbl }}
            </a>
            <a class="kd-btn" href="{{ route('komisi.index') }}">
                @include('finance.komisi._ikon', ['n' => 'ulang', 's' => 15])
                Reset filter
            </a>
        </div>
    @endif
</div>
