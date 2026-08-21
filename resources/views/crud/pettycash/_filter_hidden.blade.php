{{--
    Hidden input pembawa state filter aktif (kategori, cari, wallet, rentang
    tanggal, bulan/tahun) ke dalam POST action petty cash.

    Tujuan: setelah submit (pengeluaran, top up, transfer, edit, hapus, dll)
    redirect balik TIDAK mereset filter — controller (preserveParams) membaca
    nilai ini dan mempertahankannya. Filter hanya hilang kalau user hapus
    manual (tombol Reset / "Semua kategori").

    Dibaca langsung dari request() supaya tidak perlu diteruskan variabel ke
    tiap modal.
--}}
@php
    $pcKeep = [
        'start_date'     => request('start_date'),
        'end_date'       => request('end_date'),
        'bulan'          => request('bulan'),
        'tahun'          => request('tahun'),
        'fund_source_id' => request('fund_source_id'),
        'q'              => request('q'),
    ];
@endphp
@foreach($pcKeep as $pcKey => $pcVal)
    @if($pcVal !== null && $pcVal !== '')
        <input type="hidden" name="{{ $pcKey }}" value="{{ $pcVal }}">
    @endif
@endforeach
@foreach((array) request('categories', []) as $pcCat)
    @if($pcCat !== null && $pcCat !== '')
        <input type="hidden" name="categories[]" value="{{ $pcCat }}">
    @endif
@endforeach
