<?php

namespace App\Services;

use App\Models\AttRequest;
use App\Models\AttRequestApproval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur approval form absen: submit → pending_l1 (head) → pending_l2 (Dani) → approved.
 *
 * Rantai approver dari att_approver (mapping "atasan langsung", TERPISAH dari pohon
 * checklist — §5.4, keputusan 2026-07-18). Jumlah lapis TERGANTUNG jenis form
 * (MAX_LEVELS): cuti & lembur = 2 lapis; izin/libur/wfh = 1 lapis. Finance =
 * recap/view-only approved, BUKAN gate (lihat recapForFinance).
 *
 * Rantai contoh (cuti/lembur): GS → Nisa (L1) → Dani (L2). Head (Nisa) → Dani (L1 saja).
 * Dani → auto. Untuk izin/libur/wfh rantai dipangkas jadi 1 lapis (atasan langsung saja).
 *
 * Semantik multi-approver dalam satu lapis (Asdent → Nisa + Irma): SATU yang approve
 * cukup untuk maju (any-one). Bisa diubah ke "semua wajib" kalau perlu.
 *
 * Konteks: docs/rencana-rekonsiliasi-absensi.md §4, §5.4.
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
     * Sudah dedup + buang self-loop. Kosong = tak ada approver (auto-approve).
     *
     * $tipe (opsional) memangkas rantai ke jumlah lapis yang diperlukan jenis form
     * itu (MAX_LEVELS). Tanpa $tipe → rantai penuh (dipakai untuk inspeksi/debug).
     */
    public function chainFor(string $userId, ?string $tipe = null): array
    {
        $level1 = $this->directApprovers($userId);
        if (!$level1) return [];

        $levels = [$level1];
        $seen = array_flip($level1);
        $seen[$userId] = true;
        $frontier = $level1;

        while (true) {
            $next = [];
            foreach ($frontier as $a) {
                foreach ($this->directApprovers($a) as $b) {
                    if (!isset($seen[$b])) $next[$b] = true;
                }
            }
            $next = array_keys($next);
            if (!$next) break;
            $levels[] = $next;
            foreach ($next as $b) $seen[$b] = true;
            $frontier = $next;
        }

        // pangkas ke plafon lapis untuk jenis form ini
        if ($tipe !== null && isset(self::MAX_LEVELS[$tipe])) {
            $levels = array_slice($levels, 0, self::MAX_LEVELS[$tipe]);
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
            return $req;
        }
        // approve: maju ke lapis berikut, atau selesai
        $next = $idx + 1;
        $req->update(['status' => isset($levels[$next]) ? 'pending_l' . ($next + 1) : 'approved']);
        return $req;
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

    private function directApprovers(string $userId): array
    {
        return DB::table('att_approver')
            ->where('user_id', $userId)->where('level', 1)
            ->where('approver_user_id', '!=', $userId)   // buang self-loop (Dani)
            ->pluck('approver_user_id')->all();
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
