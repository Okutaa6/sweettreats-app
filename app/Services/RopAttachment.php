<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * RopAttachment
 *
 * Penyimpan lampiran ROP: foto penawaran/invoice dari pengaju, bukti transfer
 * dari Finance, dan nota pertanggungjawaban saat pengajuan ditutup.
 *
 * Menerima DUA bentuk kiriman untuk field yang sama:
 *   1. file biasa (<input type="file">) — dari desktop Finance,
 *   2. data URL base64 hasil kompresi di browser — dari kamera HP pengaju,
 *      pola yang sama dengan absen HP selfie (docs/rencana/rencana-absen-hp.md).
 *
 * Bedanya dengan selfie absen: berkas ini TIDAK sensitif (nota & invoice, bukan
 * wajah), jadi disimpan di public/uploads seperti bukti petty cash — bukan di
 * storage/app yang butuh route ber-auth.
 */
class RopAttachment
{
    /** Batas panjang string base64 (~1,5 MB berkas) — kompresi browser jauh di bawah ini. */
    private const MAX_BASE64 = 2_000_000;

    /**
     * Simpan lampiran kalau ada. Return path relatif dari public/, atau NULL
     * kalau tidak ada kiriman sama sekali.
     *
     * @param string $field nama field file; base64-nya diharapkan di "{$field}_base64"
     */
    public function simpan(Request $request, string $field, string $jenis): ?string
    {
        if ($request->hasFile($field)) {
            return $this->dariFile($request, $field, $jenis);
        }
        if ($request->filled($field . '_base64')) {
            return $this->dariBase64((string) $request->input($field . '_base64'), $jenis);
        }
        return null;
    }

    private function dariFile(Request $request, string $field, string $jenis): string
    {
        $file = $request->file($field);
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        [$relDir, $absDir] = $this->folder($jenis);
        $nama = Str::uuid() . '.' . $ext;
        $file->move($absDir, $nama);
        return "{$relDir}/{$nama}";
    }

    /**
     * Foto hasil kompresi kamera HP. Divalidasi sampai magic bytes — jangan
     * percaya prefix data URL saja, itu cuma teks yang gampang dipalsukan.
     */
    private function dariBase64(string $dataUrl, string $jenis): string
    {
        if (strlen($dataUrl) > self::MAX_BASE64) {
            abort(422, 'Foto terlalu besar. Coba foto ulang.');
        }
        if (! preg_match('#^data:image/(jpeg|png);base64,(.+)$#', $dataUrl, $m)) {
            abort(422, 'Format foto tidak dikenali.');
        }

        $biner = base64_decode($m[2], true);
        if ($biner === false || $biner === '') {
            abort(422, 'Foto gagal dibaca. Coba foto ulang.');
        }

        // Isinya diperiksa sebagai gambar sungguhan, bukan ditebak dari panjang
        // atau dari prefix data URL yang gampang dipalsukan. Ambang "minimal
        // sekian byte" sempat dipakai dan salah: PNG kecil yang sah ikut
        // tertolak.
        $info = @getimagesizefromstring($biner);
        if ($info === false || ! in_array($info[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            abort(422, 'Berkas yang dikirim bukan foto.');
        }

        [$relDir, $absDir] = $this->folder($jenis);
        $nama = Str::uuid() . ($info[2] === IMAGETYPE_JPEG ? '.jpg' : '.png');
        if (file_put_contents($absDir . DIRECTORY_SEPARATOR . $nama, $biner) === false) {
            abort(500, 'Foto gagal disimpan.');
        }
        return "{$relDir}/{$nama}";
    }

    /**
     * public/uploads/rop/{jenis}/{YYYY}/{MM} — dipisah per jenis supaya jelas
     * mana lampiran pengaju, mana bukti Finance, mana nota penutup.
     */
    private function folder(string $jenis): array
    {
        $relDir = 'uploads/rop/' . $jenis . '/' . date('Y') . '/' . date('m');
        $absDir = public_path($relDir);
        if (! is_dir($absDir)) {
            @mkdir($absDir, 0775, true);
        }
        return [$relDir, $absDir];
    }
}
