@php
    $ap = request()->path();   // mis. 'absensi/recap'
    $finTabs = [
        ['label' => 'Rekap Persetujuan', 'url' => url('absensi/recap'),   'on' => str_starts_with($ap, 'absensi/recap')],
        ['label' => 'Kehadiran',         'url' => null,                    'on' => false],
        ['label' => 'Mapping Karyawan',  'url' => url('absensi/mapping'),  'on' => str_starts_with($ap, 'absensi/mapping')],
        ['label' => 'Upload Data',       'url' => url('absensi'),          'on' => $ap === 'absensi'],
        ['label' => 'Periode Gaji',      'url' => url('absensi/periode'),  'on' => str_starts_with($ap, 'absensi/periode')],
        ['sep' => true],
        ['label' => 'Petty Cash',        'url' => url('finance/pettycash'),'on' => str_starts_with($ap, 'finance/pettycash')],
    ];
@endphp
<div class="fin-tabs">
    @foreach($finTabs as $t)
        @if($t['sep'] ?? false)
            <span class="fin-tab-sep" aria-hidden="true"></span>
        @elseif($t['url'])
            <a href="{{ $t['url'] }}" class="fin-tab {{ $t['on'] ? 'active' : '' }}">{{ $t['label'] }}</a>
        @else
            <span class="fin-tab disabled" title="Segera hadir">{{ $t['label'] }}</span>
        @endif
    @endforeach
</div>
