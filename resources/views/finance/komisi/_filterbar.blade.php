{{--
    Bar filter — satu baris: rentang tanggal, preset cepat, cabang, tombol.

    Variabel:
      $aksi     : URL action form (route halaman saat ini)
      $hidden   : array field tersembunyi yang harus ikut terbawa (tab, sort, dsb)
      $unduhUrl : URL Unduh CSV
      $cetakUrl : URL Cetak Rincian (opsional — hanya layar detail)

    Preset TIDAK menyimpan state sendiri: dia menyala kalau rentang tanggal yang
    sedang aktif persis sama dengan presetnya. Kalau dibuat state terpisah,
    tombol bisa menyala sambil menampilkan rentang lain — dan Finance akan
    memakai angka dari periode yang tidak dia kira.
--}}
@php
    $hidden = $hidden ?? [];
@endphp

<form class="kd-card kd-filter noprint" method="GET" action="{{ $aksi }}">
    @foreach ($hidden as $k => $v)
        @if ($v !== null && $v !== '')
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach

    <div class="kd-filter__dates">
        @include('finance.komisi._ikon', ['n' => 'kalender', 's' => 17])
        <input class="kd-input" type="date" name="date_from" value="{{ $start }}"
               onchange="this.form.submit()" aria-label="Tanggal mulai">
        <span style="color:var(--kd-faint)">&ndash;</span>
        <input class="kd-input" type="date" name="date_to" value="{{ $end }}"
               onchange="this.form.submit()" aria-label="Tanggal akhir">
    </div>

    <div class="kd-presets">
        @foreach ($presets as $label => $rentang)
            @php
                $aktif = ($start === $rentang[0] && $end === $rentang[1]);
                $paramPreset = array_merge($hidden, [
                    'date_from' => $rentang[0],
                    'date_to'   => $rentang[1],
                ]);
                if (! $branchLocked && $placeId) { $paramPreset['place'] = $placeId; }
            @endphp
            <a class="kd-preset {{ $aktif ? 'kd-preset--on' : '' }}"
               href="{{ $aksi }}?{{ http_build_query($paramPreset) }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($branchLocked)
        {{-- User ter-scope ke satu cabang: dropdown diganti chip terkunci.
             ?place= diabaikan di controller, jadi tidak ada jalan memutar. --}}
        <span class="kd-lock" title="Akses Anda terbatas pada cabang ini">
            @include('finance.komisi._ikon', ['n' => 'gembok', 's' => 15])
            {{ $branchLabel }}
        </span>
    @else
        <select class="kd-select" name="place" onchange="this.form.submit()" aria-label="Cabang">
            <option value="">Semua Cabang</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" {{ $placeId === $b->id ? 'selected' : '' }}>
                    {{ \App\Http\Controllers\Finance\KomisiDokterController::labelCabang($b->name) }}
                </option>
            @endforeach
        </select>
    @endif

    <span class="kd-filter__spacer"></span>

    @if (! empty($cetakUrl))
        <a class="kd-btn" href="{{ $cetakUrl }}" target="_blank" rel="noopener">
            @include('finance.komisi._ikon', ['n' => 'cetak', 's' => 16])
            Cetak Rincian
        </a>
    @endif

    <a class="kd-btn kd-btn--primary" href="{{ $unduhUrl }}">
        @include('finance.komisi._ikon', ['n' => 'unduh', 's' => 16])
        Unduh CSV
    </a>
</form>
