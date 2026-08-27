<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpGpDoctor;
use App\Models\RawDoctor;
use Illuminate\Support\Str;

/**
 * Kelola daftar Dokter Branding GP. Route di-prefix "konten-pillar/" supaya
 * inherit RBAC halaman Konten Pillar (AuthGuard cek segment(1)).
 */
class EpGpDoctorController extends Controller
{
    public function store()
    {
        $data = request()->validate([
            'raw_doctor_id'            => 'required|exists:raw_doctor,id',
            'social_media'             => 'nullable|array',
            'social_media.*.platform'  => 'nullable|in:instagram,tiktok,facebook',
            'social_media.*.username'  => 'nullable|string|max:100',
        ]);

        if (EpGpDoctor::where('raw_doctor_id', $data['raw_doctor_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokter ini sudah ada di daftar Branding GP.',
            ], 422);
        }

        $maxSort = (int) EpGpDoctor::max('sort_order');

        $gp = EpGpDoctor::create([
            'id'            => (string) Str::uuid(),
            'raw_doctor_id' => $data['raw_doctor_id'],
            'social_media'  => $this->cleanSocial(request('social_media', [])),
            'is_active'     => 1,
            'sort_order'    => $maxSort + 1,
            'created_by'    => session('id'),
        ]);

        $gp->load('doctor');

        return response()->json([
            'success' => true,
            'message' => 'Dokter ditambahkan ke Branding GP.',
            'doctor'  => [
                'id'            => $gp->id,
                'raw_doctor_id' => $gp->raw_doctor_id,
                'name'          => $gp->doctor?->name,
                'photo_url'     => $gp->doctor?->photo_url,
            ],
        ]);
    }

    /**
     * Update media sosial dokter GP (dari modal detail).
     */
    public function update($id)
    {
        request()->validate([
            'social_media'             => 'nullable|array',
            'social_media.*.platform'  => 'nullable|in:instagram,tiktok,facebook',
            'social_media.*.username'  => 'nullable|string|max:100',
        ]);

        $gp = EpGpDoctor::findOrFail($id);
        $gp->update([
            'social_media' => $this->cleanSocial(request('social_media', [])),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Media sosial dokter diperbarui.',
        ]);
    }

    /**
     * Bersihkan input social_media: buang baris tanpa platform/username,
     * normalisasi username (buang @ & spasi). Return array bersih atau null.
     */
    private function cleanSocial($rows): ?array
    {
        $clean = [];
        foreach ((array) $rows as $r) {
            $platform = trim((string) ($r['platform'] ?? ''));
            $username = ltrim(trim((string) ($r['username'] ?? '')), '@');
            if ($platform === '' || $username === '') continue;
            $clean[] = ['platform' => $platform, 'username' => $username];
        }
        return empty($clean) ? null : $clean;
    }

    public function destroy($id)
    {
        $gp = EpGpDoctor::findOrFail($id);
        $gp->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Dokter dihapus dari daftar Branding GP.',
            ]);
        }

        return redirect()->route('kontenpillar', ['tab' => 'gp-doctor'])
            ->with('success', 'Dokter dihapus dari daftar Branding GP.');
    }
}
