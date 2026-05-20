{{-- ─────────── EP KONTEN: FOTO / GAMBAR SECTION ───────────
     Section ini di-include dari _form.blade.php (di atas field "Deskripsi Singkat").
     Variable $konten harus tersedia di scope (EpKonten instance).
     Section tidak muncul saat $konten belum punya id (mode create) — upload
     butuh konten id; tampilkan placeholder di-create dulu baru bisa upload.
--}}
@once
    <link rel="stylesheet" href="{{ asset('assets/css/ep-konten-image.css') }}?v={{ @filemtime(public_path('assets/css/ep-konten-image.css')) ?: '1' }}">
@endonce

<div class="ep-img-section">
    <div class="ep-img-section__header">
        <i class="fa-solid fa-paperclip"></i>
        <span>FOTO / GAMBAR KONTEN</span>
    </div>  

    @if(!isset($konten) || empty($konten->id))
        <div class="ep-img-empty-state">
            <i class="fa fa-info-circle"></i>
            Simpan konten terlebih dulu untuk bisa upload foto.
        </div>
    @else
        {{-- Dropzone --}}
        <div class="ep-img-dropzone" id="epImgDropzone" tabindex="0">
            <i class="fa fa-cloud-upload-alt ep-img-dropzone__icon"></i>
            <p class="ep-img-dropzone__title">
                Drag foto kesini atau <a href="#" id="epImgPickBtn">klik untuk pilih</a>
            </p>
            <small class="ep-img-dropzone__hint">
                JPG, PNG, WebP &middot; Max 5 MB per file &middot; Akan dikompres otomatis (3 ukuran WebP)
            </small>
            <input type="file" id="epImgInput" multiple accept="image/jpeg,image/png,image/webp" hidden>
        </div>

        {{-- Progress saat upload --}}
        <div class="ep-img-progress" id="epImgProgress" hidden>
            <div class="ep-img-progress__bar">
                <div class="ep-img-progress__fill" id="epImgProgressFill"></div>
            </div>
            <span class="ep-img-progress__text" id="epImgProgressText">Mengunggah &amp; mengompres...</span>
        </div>

        {{-- Carousel Instagram-style: 1 image = card biasa, >1 = slide dengan nav.
             Nav (prev/next/dots) di-show/hide via CSS berdasarkan jumlah slide. --}}
        <div class="ep-img-carousel" id="epImgCarousel" data-current="0">
            <div class="ep-img-list" id="epImgList">
                @foreach($konten->images as $img)
                    @include('crud.ep-konten._image_row', ['img' => $img])
                @endforeach
                @if($konten->images->isEmpty())
                    <div class="ep-img-empty-list" id="epImgEmptyHint">
                        <i class="fa fa-image"></i> Belum ada foto. Drop atau pilih file untuk upload.
                    </div>
                @endif
            </div>

            <button type="button" class="ep-img-nav ep-img-nav--prev" id="epImgNavPrev" aria-label="Sebelumnya">
                <i class="fa fa-chevron-left"></i>
            </button>
            <button type="button" class="ep-img-nav ep-img-nav--next" id="epImgNavNext" aria-label="Berikutnya">
                <i class="fa fa-chevron-right"></i>
            </button>
            <div class="ep-img-dots" id="epImgDots"></div>
            <div class="ep-img-counter" id="epImgCounter"></div>
        </div>
    @endif
</div>

{{-- ─── Lightbox modal untuk preview (eye icon) ─── --}}
<div class="ep-img-lightbox" id="epImgLightbox" hidden>
    <button type="button" class="ep-img-lightbox__close" id="epImgLightboxClose">
        <i class="fa fa-times"></i>
    </button>
    <img id="epImgLightboxImg" src="" alt="Preview">
</div>

@if(isset($konten) && !empty($konten->id))
    <script>
        // Pakai path relatif (tanpa origin) supaya browser resolve ke same-origin.
        // url()/route() Laravel kadang return absolute URL berbeda (APP_URL vs 127.0.0.1:8000)
        // yang menyebabkan fetch nyasar ke server lain.
        window.EP_KONTEN_IMG_CONFIG = {
            kontenId: @json($konten->id),
            csrfToken: @json(csrf_token()),
            urls: {
                upload:   '/ep-konten/' + @json($konten->id) + '/images',
                review:   '/ep-konten/image/{id}/review',
                destroy:  '/ep-konten/image/{id}',
                download: '/ep-konten/image/{id}/download',
            },
        };
    </script>
    <script src="{{ asset('assets/js/ep-konten-image.js') }}?v={{ @filemtime(public_path('assets/js/ep-konten-image.js')) ?: '1' }}" defer></script>
@endif
