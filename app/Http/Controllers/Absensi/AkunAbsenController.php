<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttHpBukti;
use App\Models\AttBranchIp;
use App\Models\RawBranch;
use App\Models\UserDevice;
use App\Services\AttendanceHpAbsen;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Halaman "Absen" (selfie) — zona pribadi, segment(1)='akun' (grant sudah
 * dipegang semua karyawan lewat node Akun Saya, nol perubahan access path).
 * Logika di AttendanceHpAbsen; controller cuma: validasi, simpan foto, jawab.
 * Lihat docs/rencana/rencana-absen-hp.md §7 layar 1.
 */
class AkunAbsenController extends Controller
{
    /** Batas foto setelah decode (bytes) — client kompres ~640px JPEG ±50 KB. */
    private const MAX_FOTO_BYTES = 500 * 1024;

    public function index(Request $request, AttendanceHpAbsen $svc)
    {
        $userId   = (string) session('id');
        $now      = Carbon::now();
        $workDate = $svc->workDate($now);

        // Status hari kerja ini (buat header "kamu belum absen masuk" dst)
        // half-open [06:00, besok 06:00) — samakan reconciler & service
        [$from, $to] = $svc->rentangWorkDate($workDate);
        $buktiHariIni = AttHpBukti::where('user_id', $userId)
            ->where('taken_at', '>=', $from)->where('taken_at', '<', $to)
            ->orderBy('taken_at')->get();

        // Hint pencocokan IP saat halaman dibuka (IP GET ≈ IP POST) — nentuin
        // picker cabang perlu tampil atau nggak. POST tetap mencocokkan ulang.
        $match    = AttBranchIp::matchBranch($request->ip());
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);

        // PersonalHeader (pola Absensi Saya)
        $user = \App\Models\Users::with('branch')->find($userId);
        $nama = optional($user)->nama ?? session('name') ?? '';
        $me = [
            'nama'    => $nama,
            'inisial' => mb_strtoupper(mb_substr(trim($nama), 0, 1)),
            'jabatan' => session('group_name') ?: 'Karyawan',
            'cabang'  => optional(optional($user)->branch)->name,
        ];

        // Pengingat "kemarin lupa absen pulang" → ajakan Koreksi Absensi
        $kemarin = Carbon::parse($workDate)->subDay()->toDateString();
        $lupaKemarin = \Illuminate\Support\Facades\DB::table('att_day')
            ->where('user_id', $userId)->where('work_date', $kemarin)
            ->where('single_scan', 1)->whereIn('status', ['hadir', 'telat'])
            ->exists();

        return view('absensi.akun.absen', [
            'buktiHariIni' => $buktiHariIni,
            'ipCocok'      => $match ? $match->branch : null,
            'branches'     => $branches,
            'workDate'     => $workDate,
            'me'           => $me,
            'lupaKemarin'  => $lupaKemarin,
        ]);
    }

    public function store(Request $request, AttendanceHpAbsen $svc)
    {
        $userId = (string) session('id');

        $request->validate([
            'jenis'         => 'required|in:masuk,pulang',
            // batas panjang string base64 ≈ 500KB binary + header — payload
            // raksasa ditolak di validasi, bukan bikin PHP megap-megap
            'foto_base64'   => 'required|string|max:900000',
            'branch_manual' => 'nullable|exists:raw_branch,id',
        ]);

        $jenis = $request->input('jenis');
        $ip    = $request->ip();

        // Tolak jenis yang sudah tercatat hari ini — tab lama yang masih
        // kebuka bisa ngirim "masuk" kedua di sore hari; UI bukan penjaga
        // satu-satunya. Pesan jujur + status terkini, bukan error misterius.
        [$from, $to] = $svc->rentangWorkDate($svc->workDate(\Carbon\Carbon::now()));
        $sudahAda = AttHpBukti::where('user_id', $userId)->where('jenis', $jenis)
            ->where('taken_at', '>=', $from)->where('taken_at', '<', $to)
            ->orderBy('taken_at')->first();
        if ($sudahAda) {
            return response()->json([
                'ok'    => false,
                'pesan' => 'Kamu sudah absen ' . $jenis . ' hari ini (' . $sudahAda->taken_at->format('H.i') .
                           '). Muat ulang halaman ini ya.',
            ], 422);
        }

        // IP tak dikenal DAN tak ada pilihan cabang → minta UI munculin picker.
        // Bukan penolakan absen — cuma butuh satu info lagi.
        if (!AttBranchIp::matchBranch($ip) && !$request->input('branch_manual')) {
            return response()->json([
                'ok'          => false,
                'need_branch' => true,
                'pesan'       => 'WiFi tidak dikenali — pilih cabangmu dulu ya.',
            ], 422);
        }

        $fotoPath = $this->simpanFoto($userId, $jenis, $request->input('foto_base64'));
        if (!$fotoPath) {
            return response()->json(['ok' => false, 'pesan' => 'Foto tidak terbaca. Coba ulangi selfienya.'], 422);
        }

        $device = UserDevice::findLive($request->cookie(UserDevice::COOKIE_NAME));

        try {
            $hasil = $svc->absen($userId, $jenis, $fotoPath, $ip, $request->input('branch_manual'), $device, $request->userAgent());
        } catch (\Throwable $e) {
            @unlink(storage_path('app/' . $fotoPath));
            \Log::error('[absen-hp] gagal: ' . $e->getMessage(), ['user' => $userId]);
            return response()->json(['ok' => false, 'pesan' => 'Absen gagal tersimpan. Coba lagi ya.'], 500);
        }

        $this->segarkanHariIni($userId, $svc);

        $branchName = $hasil['branch_id']
            ? optional(RawBranch::find($hasil['branch_id']))->name
            : null;

        return response()->json([
            'ok'     => true,
            'jenis'  => $jenis,
            'jam'    => Carbon::parse($hasil['taken_at'])->format('H.i'),
            'cabang' => $branchName,
            'cocok'  => $hasil['cocok'],
            // flags sengaja TIDAK dikirim ke staff — review urusan SPV, bukan
            // buat bikin orang panik. Cukup nada netral kalau IP tak dikenal.
        ]);
    }

    /**
     * Setelah absen tersimpan, hitung ulang att_day HARI INI untuk orang ini.
     *
     * Absen menulis att_scan + att_hp_bukti, tapi SELURUH layar absensi membaca
     * att_day — dan att_day hanya lahir dari reconcile terjadwal 06:10. Tanpa
     * ini, orang yang baru saja absen tetap dibilang "Belum absen" sampai besok
     * pagi, rekapnya "0 hari tercatat", dan SPV tak melihatnya di Kehadiran.
     * Terlihat di prod hari pertama (2026-08-05): 9 orang absen, sembilan-
     * sembilannya melihat layar yang menyangkal absen mereka — persis kelas
     * kegagalan-senyap yang bikin orang absen dua kali atau lapor "rusak".
     *
     * Aman dijalankan tengah hari: reconcile idempoten, di-scope ke satu orang
     * satu hari, melewati periode gaji yang sudah ditutup, dan TIDAK memvonis
     * alpa untuk hari berjalan (penjaga `$key >= $now` di AttendanceReconciler).
     * Vonis final tetap dari sapuan 06:10 besok — ini hanya mempercepat.
     *
     * Gagal hitung TIDAK boleh membatalkan absen yang sudah sah tersimpan:
     * cukup dicatat, sapuan besok pagi menyusul.
     */
    private function segarkanHariIni(string $userId, AttendanceHpAbsen $svc): void
    {
        try {
            $wd = Carbon::parse($svc->workDate(Carbon::now()))->startOfDay();
            (new \App\Services\AttendanceReconciler())->reconcile($wd, $wd->copy(), $userId);
        } catch (\Throwable $e) {
            \Log::warning('[absen-hp] reconcile kilat gagal: ' . $e->getMessage(), ['user' => $userId]);
        }
    }

    /**
     * Simpan selfie ke storage/app/att_selfie — SENGAJA di luar public/:
     * foto wajah karyawan tak boleh bisa dibuka siapa pun bermodal URL.
     * Disajikan ke SPV lewat route ber-auth PengawasanController::foto.
     * Return path relative dari storage/app, atau null kalau bukan JPEG valid.
     */
    private function simpanFoto(string $userId, string $jenis, string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/jpeg;base64,(.+)$#s', $dataUrl, $m)) return null;

        $bin = base64_decode($m[1], true);
        if ($bin === false || strlen($bin) > self::MAX_FOTO_BYTES) return null;
        if (substr($bin, 0, 2) !== "\xFF\xD8") return null; // magic bytes JPEG

        $dir = storage_path('app/att_selfie');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $name = date('Ymd_His') . '_' . $jenis . '_' . substr(sha1($userId), 0, 8) . '.jpg';
        if (file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $bin) === false) return null;

        return 'att_selfie/' . $name;
    }
}
