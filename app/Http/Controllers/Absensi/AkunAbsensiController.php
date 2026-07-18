<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttDay;
use App\Models\AttRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Akun Saya → Absensi Saya" — self-service karyawan (MOBILE).
 * Tiap user lihat DATA DIRINYA SENDIRI (session id). Tak ada gating cabang/role:
 * kamu selalu boleh lihat punyamu sendiri (§ prinsip "sisi-GUA").
 */
class AkunAbsensiController extends Controller
{
    public function index(Request $request)
    {
        $userId = (string) session('id');
        if (! $userId) abort(403);

        // bulan yg dilihat (default bulan berjalan)
        $month = $request->input('m');
        $anchor = $month ? Carbon::parse($month . '-01') : Carbon::now()->startOfMonth();
        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();

        $days = AttDay::where('user_id', $userId)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($d) => $d->work_date->toDateString());

        // rekap ringkas
        $rekap = [
            'hadir' => $days->where('status', 'hadir')->count(),
            'telat' => $days->where('status', 'telat')->count(),
            'alpa'  => $days->where('status', 'alpa')->count(),
            'cuti'  => $days->whereIn('status', ['cuti', 'izin', 'wfh', 'libur'])->count(),
            'telat_menit' => $days->sum('telat_menit'),
            'lembur_menit' => $days->sum('lembur_menit_disetujui'),
        ];

        // pengajuan terakhir (semua status)
        $requests = AttRequest::where('user_id', $userId)
            ->orderByDesc('tanggal_from')
            ->limit(20)
            ->get();

        return view('absensi.akun.absensi', compact('days', 'rekap', 'requests', 'anchor', 'from', 'to'));
    }
}
