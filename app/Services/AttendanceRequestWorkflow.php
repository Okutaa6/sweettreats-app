<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\AttRequest;
use App\Models\AttRequestApproval;
use App\Models\Users;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur approval form absen: submit → pending_l1 (head) → pending_l2 (Dani) → approved.
 *
 * Rantai approver di-resolve PER-ROLE dari tabel att_approval_rule (role pengaju →
 * [role approver], any-one), BUKAN per-user (att_approver lama = DORMANT, tak dibaca).
 * Resolver jalan NAIK graf role: tiap lapis = daftar USER dari role approver di
 * frontier saat itu. Decoupled dari pohon checklist/org — §5.4, keputusan 2026-07-18/22.
 * Jumlah lapis TERGANTUNG jenis form (MAX_LEVELS): cuti & lembur = 2 lapis;
 * izin/libur/wfh = 1 lapis. Finance = recap/view-only approved, BUKAN gate (recapForFinance).
 *
 * Rantai contoh (cuti/lembur): GS → Nisa (L1) → Dani (L2). Head (Nisa) → Dani (L1 saja).
 * Dani (Executive/apex) → tak punya rule → auto. izin/libur/wfh dipangkas jadi 1 lapis.
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
     * Berapa lapis approval yang diperlukan per jenis form (§5.4):
     *   cuti & lembur = 2 lapis (atasan langsung → finance/management/Dani);
     *   izin, libur, wfh = cukup 1 lapis (atasan langsung).
     * Angka ini PLAFON: kalau rantai nyata lebih pendek (mis. head → Dani saja),
     * yang dipakai tetap yang lebih pendek.
     */
    private const MAX_LEVELS = [
        'cuti' => 2, 'lembur' => 2,
        'izin' => 1, 'libur' => 1, 'wfh' => 1,
    ];

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

    /** Submit form: hitung rantai, set status awal. Rantai kosong → langsung approved. */
    public function submit(AttRequest $req): AttRequest
    {
        $levels = $this->chainFor($req->user_id, $req->tipe);
        if (!$levels) {
            $req->update(['status' => 'approved']);
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
            $this->notifyRequester($req, 'disetujui', $approverUserId);
        }
        return $req;
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
