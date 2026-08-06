<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AttRequest;
use App\Models\AttRequestApproval;
use App\Models\Users;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur approval form absen: submit → pending_l1 (atasan langsung) → approved.
 *
 * SATU LAPIS untuk semua jenis form (keputusan Ekky 2026-08-05, batch rapi-rapi
 * pasca-launch): staf → atasan langsung SELESAI; pengajuan milik head/SPV/
 * koordinator → role Executive (any-one: Dani ATAU Robi, siapa cepat).
 * Status pending_l2 tinggal warisan — backlog-nya difinalkan
 * `absensi:selesaikan-l2` saat deploy (lihat selesaikanBacklogL2()).
 *
 * Rantai approver di-resolve PER-ROLE dari tabel att_approval_rule (role pengaju →
 * [role approver], any-one), BUKAN per-user (att_approver lama = DORMANT, tak dibaca).
 * Resolver jalan NAIK graf role: tiap lapis = daftar USER dari role approver di
 * frontier saat itu. Decoupled dari pohon checklist/org — §5.4, keputusan 2026-07-18/22.
 * Finance = recap/view-only approved, BUKAN gate (recapForFinance).
 *
 * Rantai contoh: GS → Nisa, selesai. Head (Nisa) → Executive, selesai.
 * Dani (Executive/apex) → tak punya rule → auto.
 *
 * Semantik multi-approver dalam satu lapis (Asdent → Coordinator Dentist + Supervisor):
 * SATU yang approve cukup untuk maju (any-one). Role approver yang KOSONG (belum ada
 * user) di-skip — chain lanjut ke atasnya, tak collapse jadi auto-approve.
 *
 * Tambah karyawan = set group_id-nya = NOL koding. Konteks: rencana §4/§5.4, POC
 * scratchpad poc_approval_byrole.php.
 */
class AttendanceRequestWorkflow
{
    /**
     * Berapa lapis approval yang diperlukan per jenis form.
     * SEMUA = 1 sejak 2026-08-05 (dulu cuti/lembur 2 lapis; ACC Ekky: atasan
     * langsung cukup, Executive tinggal jadi approver bagi para head/SPV lewat
     * grafik att_approval_rule — bukan lapis tambahan di atas semua orang).
     * Struktur map per-tipe dipertahankan: plafonnya keputusan produk yang
     * bisa berubah lagi per jenis form. Angka ini PLAFON: kalau rantai nyata
     * lebih pendek, yang dipakai tetap yang lebih pendek.
     */
    private const MAX_LEVELS = [
        'cuti' => 1, 'lembur' => 1,
        'izin' => 1, 'libur' => 1, 'wfh' => 1,
        'koreksi' => 1, 'tukar' => 1,
    ];

    /**
     * Jenis yang TIDAK terdaftar di MAX_LEVELS memakai rantai PENUH — dan itu
     * berarti persetujuan berlapis, padahal sejak 2026-08-05 semua satu lapis.
     * Lupa mendaftarkan jenis baru = pengajuannya naik ke lapis dua lalu
     * menggantung, karena tak ada lagi layar yang menampilkan lapis dua.
     * Kejadian nyata: 'tukar' saat pertama dibuat (ketahuan uji ujung-ke-ujung
     * di dev, bukan dari kode). Penjaga ini membuat kelalaian yang sama
     * berbunyi keras di lingkungan pengembangan, bukan diam sampai produksi.
     */
    private function pastikanJenisTerdaftar(string $tipe): void
    {
        if (isset(self::MAX_LEVELS[$tipe])) return;

        $pesan = "Jenis pengajuan '{$tipe}' belum terdaftar di AttendanceRequestWorkflow::MAX_LEVELS "
            . '— tambahkan dulu, kalau tidak rantai persetujuannya jadi berlapis.';
        if (! app()->environment('production')) {
            throw new \RuntimeException($pesan);
        }
        \Illuminate\Support\Facades\Log::error('[absensi] ' . $pesan);
    }

    /**
     * Rantai approval untuk seorang pengaju: [[id approver lapis 1], [id lapis 2], ...].
     * Tiap lapis = daftar USER (any-one). Sudah dedup + buang pengaju sendiri.
     * Kosong = tak ada approver (auto-approve, mis. pengaju apex/Executive).
     *
     * Jalan NAIK graf role (att_approval_rule): frontier = role, tiap hop kumpulkan
     * approver-role lalu map ke user-nya. Role approver KOSONG di-skip tanpa nyita
     * jatah lapis (chain lanjut ke atasnya). $tipe memangkas ke MAX_LEVELS; tanpa
     * $tipe → rantai penuh (inspeksi/debug).
     */
    public function chainFor(string $userId, ?string $tipe = null): array
    {
        $roleId = $this->roleOf($userId);
        if ($roleId === null) return [];

        $max = ($tipe !== null && isset(self::MAX_LEVELS[$tipe]))
            ? self::MAX_LEVELS[$tipe]
            : PHP_INT_MAX;

        $levels    = [];
        $frontier  = [$roleId];
        $seenRoles = [$roleId => true];

        while (count($levels) < $max) {
            // approver-role untuk semua role di frontier (belum pernah dilihat)
            $nextRoles = DB::table('att_approval_rule')
                ->whereIn('role_id', $frontier)
                ->orderBy('sort_order')
                ->pluck('approver_role_id')
                ->unique()
                ->reject(fn ($r) => isset($seenRoles[$r]))
                ->values()
                ->all();
            if (!$nextRoles) break;

            foreach ($nextRoles as $r) $seenRoles[$r] = true;

            // map role → user aktif, buang pengaju sendiri (anti self-approval).
            // Role kosong → $users kosong → lapis tak ditambah, tapi frontier tetap
            // maju supaya atasannya kejangkau.
            $users = $this->usersOfRoles($nextRoles, $userId);
            if ($users) $levels[] = $users;

            $frontier = $nextRoles;
        }

        return $levels;
    }

    /**
     * Pembeda "apex sungguhan" vs "konfigurasi rusak".
     *
     * Rantai kosong punya DUA sebab yang hasilnya sama persis, padahal artinya
     * berlawanan:
     *   (a) pengaju memang puncak (Executive) → auto-approve itu benar;
     *   (b) att_approval_rule tak mengenali jabatannya → auto-approve itu BENCANA.
     *
     * Kasus (b) nyata: file seed approval rule berisi UUID group_path dari mesin
     * lain. Tabelnya TERISI (jadi cek "tabel kosong" tak menolong) tapi tak satu
     * pun role cocok → SEMUA cuti/lembur lolos sendiri tanpa atasan, tanpa error,
     * tanpa jejak. Gagal senyap yang baru ketahuan saat gaji dibayarkan.
     *
     * Pembedanya: jabatan yang sah dikenal grafik persetujuan pasti muncul sebagai
     * approver_role_id bagi orang lain. Kalau jabatan pengaju tak muncul di mana
     * pun dalam att_approval_rule, yang rusak itu konfigurasinya — bukan dia apex.
     * Lebih baik pengajuan GAGAL dengan pesan jelas daripada disetujui palsu.
     */
    private function pastikanApexBukanSalahKonfigurasi(string $userId): void
    {
        $roleId = $this->roleOf($userId);

        $dikenalGrafik = $roleId !== null
            && DB::table('att_approval_rule')->where('approver_role_id', $roleId)->exists();

        if (! $dikenalGrafik) {
            throw new \RuntimeException(
                'Alur persetujuan untuk jabatan ini belum diatur, jadi pengajuan tidak bisa dikirim. '
                . 'Hubungi admin untuk mengisi alur persetujuan (att_approval_rule).'
            );
        }
    }

    /** Submit form: hitung rantai, set status awal. Rantai kosong → langsung approved. */
    public function submit(AttRequest $req): AttRequest
    {
        $this->pastikanJenisTerdaftar((string) $req->tipe);

        $levels = $this->chainFor($req->user_id, $req->tipe);
        if (!$levels) {
            $this->pastikanApexBukanSalahKonfigurasi($req->user_id);
            $req->update(['status' => 'approved']);
            $this->potongJatahCuti($req);
            $this->recalcAttDay($req);
            return $req;
        }
        $req->update(['status' => 'pending_l1']);
        $this->notifyApprovers($req, $levels[0]);   // kabari approver lapis 1
        return $req;
    }

    /**
     * Approver bertindak (approve/reject) pada tahap saat ini.
     * @throws \RuntimeException kalau approver bukan untuk tahap ini / status tak valid.
     */
    public function act(AttRequest $req, string $approverUserId, string $action, ?string $catatan = null): AttRequest
    {
        $idx = $this->levelIndex($req->status);
        if ($idx === null) {
            throw new \RuntimeException("Form tidak dalam tahap approval (status {$req->status}).");
        }
        $levels = $this->chainFor($req->user_id, $req->tipe);
        $current = $levels[$idx] ?? [];
        if (!in_array($approverUserId, $current, true)) {
            throw new \RuntimeException('Anda bukan approver untuk tahap ini.');
        }

        AttRequestApproval::create([
            'request_id' => $req->id,
            'level' => $idx + 1,
            'approver_user_id' => $approverUserId,
            'action' => $action,
            'catatan' => $catatan,
            'acted_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);

        if ($action === 'reject') {
            $req->update(['status' => 'rejected']);
            $this->notifyRequester($req, 'ditolak', $approverUserId);
            return $req;
        }
        // approve: maju ke lapis berikut, atau selesai
        $next = $idx + 1;
        if (isset($levels[$next])) {
            $req->update(['status' => 'pending_l' . ($next + 1)]);
            $this->notifyApprovers($req, $levels[$next]);       // kabari approver lapis berikut
        } else {
            $req->update(['status' => 'approved']);
            $this->potongJatahCuti($req);
            $this->recalcAttDay($req);
            $this->notifyRequester($req, 'disetujui', $approverUserId);
        }
        return $req;
    }

    /**
     * ACC final pengajuan cuti → potong jatah (docs/rencana/rencana-jatah-cuti.md).
     * Gagal potong TIDAK membatalkan approval-nya (mis. skema cuti belum
     * terpasang di environment) — report() saja; saldo bisa direkonsiliasi
     * belakangan dari ledger + att_request approved.
     */
    private function potongJatahCuti(AttRequest $req): void
    {
        try {
            app(\App\Services\CutiService::class)->potongDariPengajuan($req);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * ACC pengajuan BACKDATE → hitung ulang att_day di rentang tanggalnya.
     * Tanpa ini: pengajuan cuti/izin/lembur untuk kemarin (mis. HP mati, lupa
     * absen) di-ACC hari ini TIDAK menimpa vonis alpa/lembur yang sudah dicetak
     * rekonsiliasi 06:10 — "deklarasi manusia menang atas scan" jadi bohong di
     * layar Kehadiran. reconcile() idempoten & OTOMATIS melewati tanggal yang
     * periodenya sudah ditutup Finance (data beku tak tersentuh). Gagal recalc
     * tak membatalkan approval — report() saja, sweep 06:10 besok menyusul.
     */
    private function recalcAttDay(AttRequest $req): void
    {
        try {
            $from = Carbon::parse($req->tanggal_from)->startOfDay();
            $to   = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay();
            // scope ke pengaju saja — recalc satu orang, bukan seluruh roster.
            (new AttendanceReconciler())->reconcile($from, $to, (string) $req->user_id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Kabari daftar approver (userId) bahwa ada pengajuan menunggu tindakan mereka. */
    private function notifyApprovers(AttRequest $req, array $approverIds): void
    {
        $pengaju = optional(Users::find($req->user_id))->nama ?? 'Karyawan';
        $tipe    = ucfirst($req->tipe);
        foreach ($approverIds as $uid) {
            try {
                AdminNotification::notify(
                    'absensi.pengajuan',
                    "Pengajuan {$tipe} — {$pengaju}",
                    "{$pengaju} mengajukan {$tipe}. Menunggu persetujuanmu.",
                    url('persetujuan'),
                    null,
                    $uid
                );
            } catch (\Throwable $e) {}
        }
    }

    /** Kabari pengaju bahwa pengajuannya sudah diputuskan (disetujui/ditolak). */
    private function notifyRequester(AttRequest $req, string $hasil, string $approverUserId): void
    {
        $approver = optional(Users::find($approverUserId))->nama ?? 'Atasan';
        $tipe     = ucfirst($req->tipe);
        try {
            AdminNotification::notify(
                'absensi.putusan',
                "Pengajuan {$tipe} {$hasil}",
                "Pengajuan {$tipe} kamu {$hasil} oleh {$approver}.",
                url('akun'),
                null,
                $req->user_id
            );
        } catch (\Throwable $e) {}
    }

    /**
     * Finalisasi warisan pengajuan yang tertahan di pending_l2.
     *
     * Sejak persetujuan jadi 1 lapis (2026-08-05), chainFor() hanya punya
     * lapis 1 — pengajuan yang KEBURU lolos L1 dan sedang menunggu L2 tidak
     * akan pernah muncul di inbox siapa pun (levels[1] tak ada) dan macet
     * selamanya tanpa error. Secara kebijakan baru mereka justru SUDAH
     * disetujui penuh (atasan langsung sudah ACC) → finalkan di sini lengkap
     * dengan efek samping approve normal (potong cuti + recalc att_day +
     * kabar ke pengaju). Idempoten: tak ada pending_l2 = no-op.
     * Dipanggil command `absensi:selesaikan-l2` sekali saat deploy.
     */
    public function selesaikanBacklogL2(): int
    {
        $n = 0;
        foreach (AttRequest::where('status', 'pending_l2')->get() as $req) {
            // Pakai approver L1 yang sudah bertindak sebagai "penyetuju" di
            // notifikasi — dialah manusia terakhir yang menyetujui.
            $l1 = AttRequestApproval::where('request_id', $req->id)
                ->where('action', 'approve')->orderByDesc('acted_at')->first();

            // Jejak audit: jalur act() SELALU menulis baris approval, jadi
            // finalisasi ini pun menulis — tanpa itu tak ada jawaban untuk
            // "siapa/apa yang menyelesaikan pengajuan ini?" selain notifikasi.
            // approver_user_id = approver L1 (manusia terakhir yang memutuskan);
            // catatan menyebut sebabnya supaya tak terbaca sebagai ACC manusia.
            AttRequestApproval::create([
                'request_id' => $req->id,
                'level' => 2,
                'approver_user_id' => (string) ($l1->approver_user_id ?? ''),
                'action' => 'approve',
                'catatan' => 'Diselesaikan sistem: persetujuan absensi menjadi satu lapis (2026-08-05).',
                'acted_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]);

            $req->update(['status' => 'approved']);
            $this->potongJatahCuti($req);
            $this->recalcAttDay($req);
            $this->notifyRequester($req, 'disetujui', (string) ($l1->approver_user_id ?? ''));
            $n++;
        }
        return $n;
    }

    /** Form yang menunggu tindakan approver ini (buat inbox UI). */
    public function pendingFor(string $approverUserId)
    {
        return AttRequest::whereIn('status', ['pending_l1', 'pending_l2'])
            ->get()
            ->filter(function ($req) use ($approverUserId) {
                $idx = $this->levelIndex($req->status);
                $levels = $this->chainFor($req->user_id, $req->tipe);
                return in_array($approverUserId, $levels[$idx] ?? [], true);
            })
            ->values();
    }

    /** Approved requests buat recap Finance (view-only, bukan gate). */
    public function recapForFinance(Carbon $from, Carbon $to)
    {
        return AttRequest::approved()
            ->where('tanggal_from', '<=', $to->toDateString())
            ->where('tanggal_to', '>=', $from->toDateString())
            ->orderBy('tanggal_from')
            ->get();
    }

    // ── internal ──

    /** Role (group_path.id) pengaju. Null kalau user tak ada. */
    private function roleOf(string $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('group_id');
    }

    /**
     * User AKTIF yang menempati salah satu role approver (any-one), minus pengaju
     * sendiri. Kosong kalau semua role approver belum ada penghuninya.
     */
    private function usersOfRoles(array $roleIds, string $excludeUserId): array
    {
        if (!$roleIds) return [];
        return DB::table('users')
            ->whereIn('group_id', $roleIds)
            ->where('id', '!=', $excludeUserId)
            ->where('status', 1)
            ->pluck('id')
            ->all();
    }

    private function levelIndex(string $status): ?int
    {
        return match ($status) {
            'pending_l1' => 0,
            'pending_l2' => 1,
            default => null,
        };
    }
}
