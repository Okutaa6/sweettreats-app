@php
    $ap = request()->path();   // mis. 'absensi/recap'
    $finTabs = [
        ['label' => 'Rekap Persetujuan', 'url' => url('absensi/recap'),   'on' => str_starts_with($ap, 'absensi/recap')],
        ['label' => 'Kehadiran',         'url' => url('absensi/kehadiran'), 'on' => str_starts_with($ap, 'absensi/kehadiran')],
        ['label' => 'Mapping Karyawan',  'url' => url('absensi/mapping'),  'on' => str_starts_with($ap, 'absensi/mapping')],
        ['label' => 'Upload Data',       'url' => url('absensi'),          'on' => $ap === 'absensi'],
        ['label' => 'Koreksi Absen',     'url' => url('absensi/koreksi'),  'on' => str_starts_with($ap, 'absensi/koreksi')],
        ['label' => 'Periode Gaji',      'url' => url('absensi/periode'),  'on' => str_starts_with($ap, 'absensi/periode')],
    ];
@endphp
<div class="fin-tabs">
    @foreach($finTabs as $t)
        @if($t['url'])
            <a href="{{ $t['url'] }}" class="fin-tab {{ $t['on'] ? 'active' : '' }}">{{ $t['label'] }}</a>
        @else
            <span class="fin-tab disabled" title="Segera hadir">{{ $t['label'] }}</span>
        @endif
    @endforeach
</div>
