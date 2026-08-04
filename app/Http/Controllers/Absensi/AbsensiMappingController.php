<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Absensi → Mapping Karyawan" (Fase A, §7 A2). Finance/admin sambungin
 * No.ID mesin → user. Tanpa ini, scan device tak-dikenal = user_id NULL →
 * absennya INVISIBLE (tak masuk att_day, tak kesanding lembur).
 *
 * Kunci (raw_branch_id, device_user_id) — §2.3 (No.ID bentrok antar cabang).
 * Assign → backfill att_scan.user_id yg sudah masuk. Guard canSeeAllBranches.
 */
class AbsensiMappingController extends Controller
{
    private function guard(): void
    {
        if (! canSeeAllBranches()) abort(403, 'Mapping absensi hanya untuk Finance / Admin.');
    }

    public function index(Request $request)
    {
        $this->guard();
        $branchFilter = $request->input('branch');

        // device dgn scan NULL-user (belum ke-map), kecuali yg ditandai 'ignored'.
        // Urut scan TERBARU dulu → device aktif (mis. Pamulang Juli) nongol atas,
        // noise historis (dump 5-thn Bintaro) turun ke bawah.
        $q = DB::table('att_scan as s')
            ->whereNull('s.user_id')
            ->leftJoin('att_device_user as m', function ($j) {
                $j->on('m.raw_branch_id', '=', 's.raw_branch_id')
                  ->on('m.device_user_id', '=', 's.device_user_id');
            })
            ->leftJoin('raw_branch as b', 'b.id', '=', 's.raw_branch_id')
            ->where(function ($w) { $w->whereNull('m.status')->orWhere('m.status', '!=', 'ignored'); });
        if ($branchFilter) $q->where('s.raw_branch_id', $branchFilter);

        $rows = $q->select('s.raw_branch_id', 'b.name as branch_name', 's.device_user_id',
                'm.nama_di_mesin',
                DB::raw('COUNT(*) as scan_count'),
                DB::raw('MIN(s.scanned_at) as first_scan'),
                DB::raw('MAX(s.scanned_at) as last_scan'))
            ->groupBy('s.raw_branch_id', 'b.name', 's.device_user_id', 'm.nama_di_mesin')
            ->orderByDesc(DB::raw('MAX(s.scanned_at)'))
            ->limit(200)
            ->get();

        $totalUnmapped = DB::table('att_scan')->whereNull('user_id')->count();
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $users = Users::where('status', 1)->orderBy('nama')->get(['id', 'nama']);

        return view('absensi.mapping', compact('rows', 'branches', 'branchFilter', 'users', 'totalUnmapped'));
    }

    public function assign(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'raw_branch_id'  => 'required|string',
            'device_user_id' => 'required|string',
            'user_id'        => 'required|string|exists:users,id',
            'nama_di_mesin'  => 'nullable|string|max:255',
        ]);

        $this->upsertDevice($data['raw_branch_id'], $data['device_user_id'], [
            'user_id'       => $data['user_id'],
            'nama_di_mesin' => $data['nama_di_mesin'] ?? null,
            'status'        => 'mapped',
        ]);

        // backfill scan yg sudah masuk (idempoten, cuma yg NULL)
        $n = DB::table('att_scan')
            ->where('raw_branch_id', $data['raw_branch_id'])
            ->where('device_user_id', $data['device_user_id'])
            ->whereNull('user_id')
            ->update(['user_id' => $data['user_id']]);

        $nm = Users::whereKey($data['user_id'])->value('nama');
        return back()->with('success', "Dipetakan ke {$nm}. {$n} scan disambungkan (jalankan reconcile untuk update att_day).");
    }

    public function ignore(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'raw_branch_id'  => 'required|string',
            'device_user_id' => 'required|string',
        ]);

        $this->upsertDevice($data['raw_branch_id'], $data['device_user_id'], ['status' => 'ignored']);
        return back()->with('success', 'Ditandai diabaikan (dokter / enrollment tes / sudah keluar).');
    }

    /** Upsert att_device_user by (raw_branch_id, device_user_id) tanpa nyentuh PK saat update. */
    private function upsertDevice(string $branchId, string $deviceId, array $attrs): void
    {
        $existing = DB::table('att_device_user')
            ->where('raw_branch_id', $branchId)->where('device_user_id', $deviceId)->first();
        if ($existing) {
            DB::table('att_device_user')->where('id', $existing->id)
                ->update($attrs + ['updated_at' => Carbon::now()]);
        } else {
            // id = bigint AUTO_INCREMENT → jangan di-set (biar DB yang isi)
            DB::table('att_device_user')->insert($attrs + [
                'raw_branch_id' => $branchId, 'device_user_id' => $deviceId,
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        }
    }
}
