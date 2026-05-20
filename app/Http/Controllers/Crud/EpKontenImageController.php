<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpKonten;
use App\Models\EpKontenImage;
use App\Services\EpKontenImageService;
use Illuminate\Http\Request;

/**
 * EpKontenImageController
 *
 * Endpoint CRUD untuk foto/gambar yang attach ke EP Konten.
 *
 * Routes:
 *   POST   /ep-konten/{id}/images          → upload 1+ file
 *   GET    /ep-konten/{id}/images          → list semua image untuk konten
 *   PATCH  /ep-konten-image/{id}/review    → update review_status & review_note
 *   DELETE /ep-konten-image/{id}           → hard delete + unlink file
 *   GET    /ep-konten-image/{id}/download  → download original
 *
 * Authorization: middleware AuthGuard sudah handle login check. Semua user
 * yang bisa edit EP Konten otomatis bisa upload, review, & delete (sesuai
 * keputusan: no extra role gating).
 */
class EpKontenImageController extends Controller
{
    public function __construct(private EpKontenImageService $service) {}

    /**
     * Upload 1+ image ke ep_konten tertentu.
     * Returns JSON: { images: [{id, thumb_url, large_url, original_url, ...}] }
     */
    public function store(Request $request, string $kontenId)
    {
        // Log untuk debug — bisa dihapus setelah upload stabil.
        \Log::debug('[ep-konten-image] Upload attempt', [
            'konten_id'    => $kontenId,
            'has_files'    => $request->hasFile('images'),
            'files_count'  => is_array($request->file('images')) ? count($request->file('images')) : 0,
            'files_meta'   => collect($request->file('images') ?? [])->map(fn($f) => [
                'name'  => $f?->getClientOriginalName(),
                'mime'  => $f?->getMimeType(),
                'cmime' => $f?->getClientMimeType(),
                'size'  => $f?->getSize(),
                'ext'   => $f?->getClientOriginalExtension(),
                'ok'    => $f?->isValid(),
                'err'   => $f?->getError(),
            ])->toArray(),
        ]);

        $request->validate([
            'images'   => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // Pastikan konten exists supaya FK tidak fail dengan error generic
        $konten = EpKonten::findOrFail($kontenId);

        $userId   = session('id');
        $created  = [];
        $errors   = [];

        foreach ($request->file('images') as $idx => $file) {
            try {
                $img = $this->service->handleUpload($konten->id, $file, $userId);
                $created[] = $this->transform($img);
            } catch (\Throwable $e) {
                $errors[] = "File #{$idx} ({$file->getClientOriginalName()}): " . $e->getMessage();
            }
        }

        return response()->json([
            'images' => $created,
            'errors' => $errors,
        ], $created ? 200 : 422);
    }

    /**
     * List image untuk 1 konten.
     */
    public function index(string $kontenId)
    {
        $konten = EpKonten::findOrFail($kontenId);
        $images = $konten->images()->get()->map(fn($img) => $this->transform($img));

        return response()->json(['images' => $images]);
    }

    /**
     * Update review_status & review_note untuk 1 image.
     * Body: { status: 'approved'|'revisi', note: string|null }
     */
    public function review(Request $request, string $id)
    {
        $validated = $request->validate([
            // 'pending' = reset status (klik X di badge); approved/revisi = aksi normal.
            'status' => 'required|in:approved,revisi,pending',
            'note'   => 'nullable|string|max:1000',
        ]);

        $img = EpKontenImage::findOrFail($id);

        if ($validated['status'] === EpKontenImage::STATUS_PENDING) {
            // Reset: clear audit trail supaya tidak misleading.
            $img->review_status = EpKontenImage::STATUS_PENDING;
            $img->review_note   = null;
            $img->reviewed_by   = null;
            $img->reviewed_at   = null;
        } else {
            $img->review_status = $validated['status'];
            // Note disimpan baik untuk approved maupun revisi — reviewer kadang
            // ingin kasih komentar pada approval (mis: "ok, tapi perhatikan lighting").
            $img->review_note   = $validated['note'] ?? null;
            $img->reviewed_by   = session('id');
            $img->reviewed_at   = now();
        }
        $img->save();

        return response()->json(['image' => $this->transform($img)]);
    }

    /**
     * Hard delete: hapus file fisik + row DB.
     */
    public function destroy(string $id)
    {
        $img = EpKontenImage::findOrFail($id);
        $this->service->delete($img);

        return response()->json(['ok' => true]);
    }

    /**
     * Download original file (versi orig WebP).
     */
    public function download(string $id)
    {
        $img = EpKontenImage::findOrFail($id);
        $absolutePath = public_path($img->path_original);

        if (!is_file($absolutePath)) {
            abort(404, 'File tidak ditemukan di disk.');
        }

        // Ganti extension jadi .webp di nama download (file di-encode webp meski original jpg/png).
        $downloadName = pathinfo($img->original_name, PATHINFO_FILENAME) . '.webp';

        return response()->download($absolutePath, $downloadName);
    }

    /**
     * Format model jadi shape JSON yang frontend butuhkan.
     */
    private function transform(EpKontenImage $img): array
    {
        $statusCfg = EpKontenImage::statusOptions()[$img->review_status] ?? null;

        return [
            'id'            => $img->id,
            'ep_konten_id'  => $img->ep_konten_id,
            'thumb_url'     => $img->thumb_url,
            'large_url'     => $img->large_url,
            'original_url'  => $img->original_url,
            'original_name' => $img->original_name,
            'size_bytes'    => $img->size_bytes,
            'size_kb'       => (int) round($img->size_bytes / 1024),
            'width'         => $img->width,
            'height'        => $img->height,
            'label'         => $img->label,
            'review_status' => $img->review_status,
            'review_note'   => $img->review_note,
            'status_label'  => $statusCfg['label'] ?? $img->review_status,
            'status_color'  => $statusCfg['color'] ?? 'warning',
            'reviewed_at'   => $img->reviewed_at?->translatedFormat('d M Y H:i'),
            'created_at'    => $img->created_at?->translatedFormat('d M Y H:i'),
            'created_ago'   => $img->created_at?->diffForHumans(),
            'download_url'  => route('ep-konten-image.download', $img->id),
        ];
    }
}
