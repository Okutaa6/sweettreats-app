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
 * checklist — §5.4, keputusan 2026-07-18). Semua jenis form lewat rantai penuh sampai
 * puncak (Dani). Finance = recap/view-only approved, BUKAN gate (lihat pendingForFinance).
 *
 * Rantai contoh: GS → Nisa (L1) → Dani (L2). Head (Nisa) → Dani (L1 saja). Dani → auto.
 *
 * Semantik multi-approver dalam satu lapis (Asdent → Nisa + Irma): SATU yang approve
 * cukup untuk maju (any-one). Bisa diubah ke "semua wajib" kalau perlu.
 *
 * Konteks: docs/rencana-rekonsiliasi-absensi.md §4, §5.4.
 */
class AttendanceRequestWorkflow
{
    /**
     * Rantai approval untuk seorang pengaju: [[id approver lapis 1], [id lapis 2], ...].
     * Sudah dedup + buang self-loop. Kosong = tak ada approver (auto-approve).
     */
    public function chainFor(string $userId): array
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
        return $levels;
    }

    /** Submit form: hitung rantai, set status awal. Rantai kosong → langsung approved. */
    public function submit(AttRequest $req): AttRequest
    {
        $levels = $this->chainFor($req->user_id);
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
        $levels = $this->chainFor($req->user_id);
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
                $levels = $this->chainFor($req->user_id);
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
