{{--
    Kartu ringkasan periode.

    Variabel:
      $kartu      : array dari kartuRingkas()/kartuDetail()
      $belumFinal : tandai kartu komisi dengan badge BELUM FINAL
      $lima       : true → grid 5 kolom (layar detail)

    Tiap kartu WAJIB membawa pembandingnya. Audiens halaman ini Finance: angka
    tanpa konteks pembanding tidak bisa dinilai — "Rp 486 juta" baru berarti
    setelah tahu bulan lalu berapa.
--}}
@php
    $lima = $lima ?? false;
    $fmt = function ($kartu) {
        $v = $kartu['nilai'];
        return match ($kartu['tipe']) {
            'rupiah' => 'Rp ' . number_format((float) $v, 0, ',', '.'),
            'orang'  => number_format((float) $v, 0, ',', '.') . ' orang',
            'dokter' => number_format((float) $v, 0, ',', '.') . ' dokter',
            default  => number_format((float) $v, 0, ',', '.'),
        };
    };
@endphp

<div class="kd-stats {{ $lima ? 'kd-stats--5' : '' }}" id="kd-ringkas">
    @foreach ($kartu as $k)
        <div class="kd-stat {{ ! empty($k['utama']) ? 'kd-stat--utama' : '' }}">
            <div class="kd-stat__label">
                {{ $k['label'] }}
                @if ($k['key'] === 'komisi' && $belumFinal)
                    <span class="kd-final">BELUM FINAL</span>
                @endif
            </div>
            <div class="kd-stat__val">{{ $fmt($k) }}</div>
            <div class="kd-delta kd-delta--{{ $k['delta']['tone'] }}">
                {{ $k['delta']['txt'] }}
                <span class="kd-delta__sub">{{ $k['delta']['sub'] }}</span>
            </div>
        </div>
    @endforeach
</div>
