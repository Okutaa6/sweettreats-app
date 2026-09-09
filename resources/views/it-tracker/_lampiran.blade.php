{{--
    Lampiran FRM-IT-01 (rencana §7 B / §14.11) — daftar + formulir tambah.
    Metadata di payload it_form FRM-IT-01: [{path, nama, mime, ukuran, oleh, at}].
    Gambar tampil sebagai pratinjau; lainnya sebagai tautan nama berkas.

    Butuh: $t, $form01, $bolehLampiran
--}}
@php
    $lamp = $form01['lampiran'] ?? [];
    $ukuran = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    $gambar = fn ($m) => str_starts_with((string) $m, 'image/');
@endphp
<div class="itk-row" style="align-items:flex-start">
    <b>LAMPIRAN</b>
    <span style="display:flex;flex-direction:column;gap:8px;min-width:0">
        @if ($lamp)
            <div class="itk-lamp-list">
                @foreach ($lamp as $x)
                    <a class="itk-lamp {{ $gambar($x['mime'] ?? '') ? 'img' : 'doc' }}" href="{{ asset($x['path']) }}" target="_blank" rel="noopener" title="{{ $x['nama'] }} · {{ $ukuran($x['ukuran'] ?? 0) }}">
                        @if ($gambar($x['mime'] ?? ''))
                            <img src="{{ asset($x['path']) }}" alt="{{ $x['nama'] }}" loading="lazy">
                        @else
                            <span class="itk-lamp-ext">{{ strtoupper(pathinfo($x['path'], PATHINFO_EXTENSION) ?: 'file') }}</span>
                        @endif
                        <span class="itk-lamp-nama">{{ $x['nama'] }}</span>
                        <span class="itk-lamp-meta num">{{ $ukuran($x['ukuran'] ?? 0) }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <span class="itk-hint" style="margin:0">Tidak ada lampiran.</span>
        @endif

        @if ($bolehLampiran && count($lamp) < \App\Services\ItTracker\ItTrackerService::LAMPIRAN_MAKS)
            <form method="post" action="{{ route('it-tracker.lampiran', $t->id) }}" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                @csrf
                <input class="itk-in itk-file" type="file" name="lampiran[]" multiple required style="max-width:340px"
                       accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.docx,.doc,.csv">
                <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Tempelkan</button>
                <span class="itk-hint" style="margin:0;flex-basis:100%">Diterima: {{ \App\Services\ItTracker\ItTrackerService::lampiranDiterima() }}.
                    {{ $t->fase === 'TRIASE' ? 'Pemohon bisa menambah sampai tiket ditriase; sesudahnya lewat Head IT.' : '' }}</span>
            </form>
        @elseif ($bolehLampiran)
            <span class="itk-hint" style="margin:0">Sudah {{ count($lamp) }} lampiran — batas per tiket tercapai.</span>
        @endif
    </span>
</div>
