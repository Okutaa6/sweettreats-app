{{--
    Header halaman — dipakai layar daftar & layar detail.

    Variabel:
      $judul   : judul besar
      $subJudul: baris kecil di bawah judul (cabang · rentang · jumlah hari)
      $crumb   : breadcrumb kecil di atas judul (opsional, layar detail)
      $backUrl : URL tombol kembali (opsional, layar detail)
      $sinkron : array dari KomisiDokterController::infoSinkron()

    Pill status sinkron sengaja duduk di header, bukan di catatan kaki: data
    komisi masuk dari Omnicare tiap hari 02:00, dan Finance tidak akan percaya
    angka di halaman ini kalau tidak tahu kapan data terakhir masuk.
--}}
<div class="kd__head">
    <div class="kd__title">
        @if (! empty($backUrl))
            <a class="kd__back noprint" href="{{ $backUrl }}" title="Kembali ke daftar dokter">
                @include('finance.komisi._ikon', ['n' => 'kembali', 's' => 18])
            </a>
        @endif

        <div>
            @if (! empty($crumb))
                <div class="kd__crumb">{{ $crumb }}</div>
            @endif
            <h1>{{ $judul }}</h1>
            <div class="kd__sub">{{ $subJudul }}</div>
        </div>
    </div>

    <div class="noprint">
        <span class="kd-sync kd-sync--{{ $sinkron['nada'] }}"
              @if ($sinkron['detail']) title="{{ $sinkron['detail'] }}" @endif>
            @include('finance.komisi._ikon', ['n' => $sinkron['nada'] === 'baik' ? 'waktu' : 'awas', 's' => 15])
            {{ $sinkron['teks'] }}
            @if ($sinkron['detail'])
                <span class="kd-sync__detail">&middot; {{ $sinkron['detail'] }}</span>
            @endif
        </span>
    </div>
</div>
