<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttImportBatch;
use App\Models\RawBranch;
use App\Services\AttendanceCsvImporter;
use Illuminate\Http\Request;

/**
 * Halaman upload export mesin fingerprint (finance / admin).
 * Pola meniru ReservationImportController: guard → resolve cabang → validate →
 * delegasi ke service importer → redirect with success.
 *
 * Cabang dipilih MANUAL (kolom Departemen di file BUKAN cabang, §2.4).
 */
class AbsensiUploadController extends Controller
{
    /** Hanya finance & admin global yang boleh upload (bisa pilih cabang mana pun). */
    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Upload absensi hanya untuk Finance / Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $batches = AttImportBatch::with('branch')
            ->orderByDesc('uploaded_at')
            ->limit(15)
            ->get();

        // cabang default (kalau user ke-lock ke satu cabang)
        $lockedBranch = session('raw_branch_id') ? (string) session('raw_branch_id') : null;

        return view('absensi.upload', compact('branches', 'batches', 'lockedBranch'));
    }

    public function upload(Request $request)
    {
        $this->guard();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx|max:8192',
            'raw_branch_id' => 'required|string',
        ], [
            'raw_branch_id.required' => 'Pilih cabang dulu — file mesin tidak menyimpan nama cabang.',
        ]);

        $branchId = session('raw_branch_id') ? (string) session('raw_branch_id') : $request->input('raw_branch_id');
        if (! RawBranch::whereKey($branchId)->exists()) {
            return back()->withErrors(['raw_branch_id' => 'Cabang tidak valid.']);
        }

        // simpan sementara DENGAN ekstensi asli supaya importer bisa deteksi xls vs csv
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $dir = storage_path('app/att_import');
        if (! is_dir($dir)) @mkdir($dir, 0775, true);
        $dest = $dir . '/' . uniqid('att_') . '.' . $ext;
        $file->move($dir, basename($dest));

        try {
            $batch = (new AttendanceCsvImporter())->import(
                $dest,
                $branchId,
                (string) (session('id') ?: '') ?: null,
                $file->getClientOriginalName()
            );
        } catch (\Throwable $e) {
            @unlink($dest);
            return back()->withErrors(['file' => 'Gagal mem-parse file: ' . $e->getMessage()]);
        }
        @unlink($dest);

        $msg = "Import selesai — {$batch->new_rows} scan baru, {$batch->dup_rows} duplikat"
            . ($batch->unmapped_rows ? ", {$batch->unmapped_rows} belum ter-mapping" : '')
            . " (format {$batch->format}).";

        return redirect()->route('absensi.upload')->with('success', $msg);
    }
}
