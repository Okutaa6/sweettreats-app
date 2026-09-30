{{--
    Strip paginasi — salinan pola design brief §9.2.

    Laravel `links()` tidak memberi kalimat "Menampilkan 1–10 dari 19 jadwal",
    dan tombolnya tidak memakai bentuk yang diminta brief (32×32, radius 9).
    Karena itu ditulis sendiri di sini, bukan memakai paginator bawaan.

    $data   — paginator
    $satuan — kata di belakang angka: "jadwal" / "pasien"
--}}
@if ($data->total() > 0)
<div class="ort-pagin">
    <span class="ort-pagin__info">
        Menampilkan
        <span class="tabular">{{ $data->firstItem() }}–{{ $data->lastItem() }}</span>
        dari <span class="tabular">{{ $data->total() }}</span> {{ $satuan }}
    </span>

    @if ($data->hasPages())
        <span class="ort-pagin__nav">
            @if ($data->onFirstPage())
                <span class="ort-pagin__btn is-off"><i class="fas fa-chevron-left"></i></span>
            @else
                <a href="{{ $data->previousPageUrl() }}" class="ort-pagin__btn"><i class="fas fa-chevron-left"></i></a>
            @endif

            @foreach ($data->getUrlRange(max(1, $data->currentPage() - 2), min($data->lastPage(), $data->currentPage() + 2)) as $n => $url)
                <a href="{{ $url }}"
                   class="ort-pagin__btn tabular {{ $n == $data->currentPage() ? 'is-active' : '' }}">{{ $n }}</a>
            @endforeach

            @if ($data->hasMorePages())
                <a href="{{ $data->nextPageUrl() }}" class="ort-pagin__btn"><i class="fas fa-chevron-right"></i></a>
            @else
                <span class="ort-pagin__btn is-off"><i class="fas fa-chevron-right"></i></span>
            @endif
        </span>
    @endif
</div>
@endif
