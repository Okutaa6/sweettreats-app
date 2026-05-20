<?php

namespace App\Services;

use App\Models\EpKontenImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

/**
 * EpKontenImageService
 *
 * Service untuk handle upload foto ke ep_konten:
 *   - Generate 3 versi WebP: orig (2000w / q90), large (1600w / q82), thumb (400w / q75)
 *   - Auto-create folder per bulan: uploads/ep-konten/YYYY/MM/{size}/
 *   - Strip EXIF & auto-orient (default behavior Intervention Image v3)
 *   - Simpan path relative ke public/ supaya kompatibel dengan asset() helper
 *
 * Hard delete: helper delete() unlink 3 file dari disk lalu hapus row DB.
 */
class EpKontenImageService
{
    private const SIZES = [
        'orig'  => ['width' => 2000, 'quality' => 90],
        'large' => ['width' => 1600, 'quality' => 82],
        'thumb' => ['width' => 400,  'quality' => 75],
    ];

    private ImageManager $manager;

    public function __construct()
    {
        // GD driver — paling umum tersedia. Imagick bisa di-swap di sini kalau perlu.
        $this->manager = new ImageManager(new GdDriver());
    }

    /**
     * Upload 1 file → generate 3 versi WebP → insert row ke ep_konten_image.
     * Throws Exception kalau write disk gagal.
     */
    public function handleUpload(string $kontenId, UploadedFile $file, ?string $userId): EpKontenImage
    {
        $imageId  = (string) Str::uuid();
        $shortId  = substr($imageId, 0, 8);
        $ts       = time();
        $year     = date('Y');
        $month    = date('m');
        // Disimpan di public/assets/uploads/... karena di setup Hostinger,
        // hanya folder /assets/ yang web-accessible (sama lokasi style.sweettreats.css).
        $baseDir  = "assets/uploads/ep-konten/{$year}/{$month}";

        // Read image dengan Intervention untuk dapat dimensi original.
        $original = $this->manager->read($file->getPathname());
        $origWidth  = $original->width();
        $origHeight = $original->height();

        $paths = [];
        foreach (self::SIZES as $sizeKey => $cfg) {
            $relativeDir  = "{$baseDir}/{$sizeKey}";
            $absoluteDir  = public_path($relativeDir);

            if (!is_dir($absoluteDir)) {
                // Pattern dari DoctorController:73-74 — auto-create dengan permission 755 recursive.
                @mkdir($absoluteDir, 0755, true);
            }

            $filename     = "{$shortId}_{$ts}.webp";
            $relativePath = "{$relativeDir}/{$filename}";
            $absolutePath = "{$absoluteDir}/{$filename}";

            // Resize down (preserve aspect ratio, jangan upscale kalau original sudah lebih kecil).
            $img = $this->manager->read($file->getPathname())
                ->scaleDown(width: $cfg['width']);

            // Encode ke WebP & tulis ke disk.
            $encoded = $img->toWebp($cfg['quality']);
            if (file_put_contents($absolutePath, (string) $encoded) === false) {
                // Cleanup file yang sudah berhasil sebelum throw.
                foreach ($paths as $p) {
                    @unlink(public_path($p));
                }
                throw new \RuntimeException("Gagal menulis file: {$relativePath}");
            }

            $paths[$sizeKey] = $relativePath;
        }

        return EpKontenImage::create([
            'id'             => $imageId,
            'ep_konten_id'   => $kontenId,
            'path_original'  => $paths['orig'],
            'path_large'     => $paths['large'],
            'path_thumb'     => $paths['thumb'],
            'original_name'  => $file->getClientOriginalName(),
            'mime_type'      => 'image/webp',
            'size_bytes'     => filesize(public_path($paths['orig'])),
            'width'          => $origWidth,
            'height'         => $origHeight,
            'sort_order'     => 0,
            'review_status'  => EpKontenImage::STATUS_PENDING,
            'created_by'     => $userId,
            'created_at'     => now(),
        ]);
    }

    /**
     * Hard delete: unlink 3 file fisik lalu hapus row DB.
     * Pakai @unlink supaya tidak warning kalau file sudah missing.
     */
    public function delete(EpKontenImage $img): void
    {
        foreach ([$img->path_original, $img->path_large, $img->path_thumb] as $relPath) {
            if ($relPath) {
                @unlink(public_path($relPath));
            }
        }
        $img->delete();
    }
}
