<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AttDay;
use App\Models\AttHpBukti;
use App\Models\AttRequest;
use App\Models\RawBranch;
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
        if ($month) {
            $anchor = Carbon::parse($month . '-01');
        } else {
            $anchor = Carbon::now()->startOfMonth();
            // Kalau bulan berjalan belum ada KEHADIRAN asli (biar tak nyangkut di
            // bulan yang cuma alpa-palsu), lompat ke bulan terbaru yang ada hadir/
            // telat/cuti/izin/wfh. Data sample lampau → demo selalu tampil isi.
            $real = ['hadir', 'telat', 'cuti', 'izin', 'wfh'];
            $hasCurrent = AttDay::where('user_id', $userId)
                ->whereBetween('work_date', [$anchor->toDateString(), $anchor->copy()->endOfMonth()->toDateString()])
                ->whereIn('status', $real)
                ->exists();
            if (! $hasCurrent) {
                $latest = AttDay::where('user_id', $userId)->whereIn('status', $real)->max('work_date');
                if ($latest) $anchor = Carbon::parse($latest)->startOfMonth();
            }
        }
        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();

        $days = AttDay::where('user_id', $userId)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn ($d) => $d->work_date->toDateString());

        // Foto selfie absen HP bulan ini (punya sendiri) → work_date ikut aturan
        // dini-hari (<06:00 milik hari sebelumnya), sama dengan layar Kehadiran.
        $buktiMap = [];
        $buktis = \Illuminate\Support\Facades\DB::table('att_hp_bukti')
            ->where('user_id', $userId)
            ->where('taken_at', '>=', $from->copy()->setTime(6, 0))
            ->where('taken_at', '<', $to->copy()->addDay()->setTime(6, 0))
            ->orderBy('taken_at')
            ->get(['id', 'jenis', 'taken_at']);
        foreach ($buktis as $b) {
            $t = Carbon::parse($b->taken_at);
            $mins = $t->hour * 60 + $t->minute;
            $wd = $mins < 360 ? $t->copy()->subDay()->toDateString() : $t->toDateString();
            $buktiMap[$wd][] = ['id' => $b->id, 'jenis' => $b->jenis, 'jam' => $t->format('H:i')];
        }

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

        // ── Header identitas (nama · jabatan · cabang) untuk kartu profil ──
        $branchName = session('raw_branch_id')
            ? optional(RawBranch::find(session('raw_branch_id')))->name
            : null;
        $me = [
            'nama'    => session('name') ?: 'Rekan',
            'jabatan' => session('group_name') ?: null,
            'cabang'  => $branchName,
            'inisial' => strtoupper(mb_substr((string) (session('name') ?: 'R'), 0, 1)),
        ];

        // ── Kartu "Hari Ini" — selalu hari ini betulan, tak peduli bulan yang
        //    sedang dilihat (desain handoff: kartu Hari Ini = jangkar aksi absen) ──
        $today = AttDay::where('user_id', $userId)
            ->where('work_date', Carbon::today()->toDateString())
            ->first();

        // ── Badge notifikasi (lonceng) ──
        $notifCount = AdminNotification::unreadForUser()->count();

        // ── Data untuk drawer "Ajukan Permohonan" ──
        // Jenis yang DITAWARKAN disaring per aturan kerja (Tukar Hari & WFH
        // hanya untuk yang fleksibel); jenis untuk MEMBACA riwayat tetap
        // lengkap, supaya pengajuan lama tak berubah jadi kode mentah.
        $tipeList    = \App\Http\Controllers\Absensi\AkunPengajuanController::jenisUntuk($userId);
        $tipeRiwayat = \App\Http\Controllers\Absensi\AkunPengajuanController::TIPE_RIWAYAT;
        $ajTipe   = null;
        // Kuota WFH bulan berjalan (approved+pending) — buat peringatan di drawer (2/bulan).
        // Jatah kerja dari rumah dihitung dalam HARI (bukan jumlah form) dan
        // memakai irisan tiap form dengan bulan berjalan — sama persis dengan
        // aturan yang ditegakkan saat kirim, supaya angka di layar tak berbeda
        // dari yang dipakai server menolak/menerima.
        $wfhUsed = AkunPengajuanController::hariWfhTerpakaiBulan($userId, Carbon::now());

        // pilihan "apa yang keliru?" untuk drawer Koreksi Absen
        $korAlasan = \App\Http\Controllers\Absensi\AkunPengajuanController::KOREKSI_ALASAN;

        // Sisa jatah cuti — null = di luar sistem cuti (eksekutif), tile
        // tidak dirender. docs/rencana/rencana-jatah-cuti.md.
        $cutiSisa = app(\App\Services\CutiService::class)->sisa($userId);

        return view('absensi.akun.absensi', compact(
            'days', 'rekap', 'requests', 'anchor', 'from', 'to', 'me', 'today', 'notifCount',
            'tipeList', 'tipeRiwayat', 'ajTipe', 'wfhUsed', 'korAlasan', 'cutiSisa', 'buktiMap'
        ));
    }

    /**
     * Foto selfie MILIK SENDIRI — beda dengan pengawasan.foto (rute SPV),
     * yang ini dijaga kepemilikan: hanya bukti dengan user_id == session id.
     * Foto tidak pernah publik (wajah karyawan bukan aset publik).
     */
    public function foto($id)
    {
        $userId = (string) session('id');
        if (! $userId) abort(403);

        $bukti = AttHpBukti::findOrFail($id);
        abort_unless((string) $bukti->user_id === $userId, 403);

        $path = storage_path('app/' . $bukti->photo_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }
}
