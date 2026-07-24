<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttBranchIp;
use App\Models\AttFlag;
use App\Models\AttHpBukti;
use App\Models\RawBranch;
use App\Services\AttendanceHpAbsen;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Pengawasan Absen — layar review SPV (Anisa). Prinsip: kejanggalan harus
 * kelihatan TANPA dicari (strip foto latar-seragam + antrian flag + gruping
 * serentak). Akses via node 'pengawasan' (AuthGuard, grant Supervisor/Finance/
 * admin — SQL 2026_07_24_absen_hp_access.sql).
 * Lihat docs/rencana-absen-hp.md §7 layar 2 + handoff_absen_hp.
 */
class PengawasanController extends Controller
{
    /** Flag serentak sejenis di satu cabang ≥ sekian → dianggap grup (WiFi gangguan). */
    private const GRUP_MIN = 3;

    /**
     * Halaman BOLEH dilihat semua pemegang node 'pengawasan' (termasuk Finance —
     * flag-lah yang mengunci tutup periode gaji, dia berhak tahu apa yang
     * ngeblok). Tapi AKSI (dicek/selesai/daftarkan IP) = reviewer saja:
     * Supervisor (Anisa) + admin. Keputusan user 2026-07-24.
     */
    private function guardAksi(): void
    {
        if (! in_array(session('group_name'), ['Supervisor', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true)) {
            abort(403, 'Penyelesaian flag hanya untuk Supervisor.');
        }
    }

    public function index(Request $request, AttendanceHpAbsen $svc)
    {
        $date = $request->input('tanggal') ?: $svc->workDate(Carbon::now());
        $branchFilter = $request->input('cabang');
        $statusFilter = $request->input('status');

        [$from, $to] = [Carbon::parse($date)->setTime(6, 0), Carbon::parse($date)->setTime(6, 0)->addDay()];

        // ── Banner kunci-gaji: SEMUA flag terbuka (lintas tanggal, karena
        //    yang ngeblok tutup periode bukan cuma hari ini) ──
        $openTotal = AttFlag::where('status', '!=', 'selesai')->count();
        $nBaru     = AttFlag::where('status', 'baru')->count();
        $nDicek    = AttFlag::where('status', 'dicek')->count();

        // ── Strip foto: semua selfie hari itu, per cabang ──
        // half-open [06:00, besok 06:00) — samakan reconciler & service
        $buktis = AttHpBukti::with(['user', 'branchCocok', 'branchManual'])
            ->where('taken_at', '>=', $from)->where('taken_at', '<', $to)
            ->orderBy('taken_at')
            ->get();

        // ── Antrian flag hari terpilih ──
        $flags = AttFlag::with('user')
            ->where('work_date', $date)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->orderByRaw("FIELD(status,'baru','dicek','selesai')")
            ->orderBy('created_at')
            ->get();

        // Bukti terkait flag (foto + sinyal buat panel detail)
        $buktiById = $buktis->keyBy('id');
        $refIds = $flags->where('ref_type', 'att_hp_bukti')->pluck('ref_id')->diff($buktiById->keys());
        if ($refIds->isNotEmpty()) {
            AttHpBukti::with(['user', 'branchCocok', 'branchManual'])->whereIn('id', $refIds)->get()
                ->each(fn ($b) => $buktiById->put($b->id, $b));
        }

        // Riwayat singkat per orang (flag bulan berjalan) buat panel —
        // gabungan orang di ANTRIAN + orang di STRIP: klik selfie siapa pun
        // harus dapat riwayat benar, bukan "belum ada" yang menyesatkan.
        $mulaiBulan = Carbon::parse($date)->startOfMonth()->toDateString();
        $orangTerlihat = $flags->pluck('user_id')->merge($buktis->pluck('user_id'))->filter()->unique();
        $riwayat = AttFlag::whereIn('user_id', $orangTerlihat)
            ->where('work_date', '>=', $mulaiBulan)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($g) => [
                'total'       => $g->count(),
                'lupa_pulang' => $g->where('jenis', 'single_scan')->count(),
            ]);

        // ── Gruping: ip_asing serentak per cabang-manual ≥ GRUP_MIN ──
        $cabangDariBukti = function ($flag) use ($buktiById) {
            $b = $flag->ref_type === 'att_hp_bukti' ? $buktiById->get($flag->ref_id) : null;
            if (!$b) return null;
            return $b->branch_id_cocok ?: $b->branch_id_manual;
        };

        // Filter cabang berlaku juga ke ANTRIAN (bukan cuma strip) — flag
        // tanpa bukti (mis. single_scan) jatuh ke cabang asal orangnya.
        if ($branchFilter) {
            $flags = $flags->filter(function ($f) use ($cabangDariBukti, $branchFilter) {
                $cab = $cabangDariBukti($f) ?: optional($f->user)->raw_branch_id;
                return $cab === $branchFilter;
            })->values();
        }

        $grup = [];
        $flagTunggal = [];
        foreach ($flags as $f) {
            if ($f->jenis === 'ip_asing' && $f->status !== 'selesai') {
                $key = $cabangDariBukti($f) ?: 'tanpa-cabang';
                $grup[$key][] = $f;
            } else {
                $flagTunggal[] = $f;
            }
        }
        foreach ($grup as $key => $items) {
            if (count($items) < self::GRUP_MIN) {
                foreach ($items as $f) $flagTunggal[] = $f;
                unset($grup[$key]);
            }
        }

        // Filter cabang (strip + antrian) — nama cabang buat label
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $branchName = $branches->keyBy('id')->map(fn ($b) => $b->name);

        $stripPerCabang = $buktis
            ->when($branchFilter, fn ($c) => $c->filter(fn ($b) => ($b->branch_id_cocok ?: $b->branch_id_manual) === $branchFilter))
            ->groupBy(fn ($b) => $branchName->get($b->branch_id_cocok ?: $b->branch_id_manual, 'Tak diketahui'));

        // Bukti yang orangnya punya flag terbuka hari itu → ring merah di strip
        $userFlagged = $flags->where('status', '!=', 'selesai')->pluck('user_id')->unique()->flip();

        return view('absensi.pengawasan', [
            'bolehAksi'     => in_array(session('group_name'), ['Supervisor', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true),
            'date'          => $date,
            'branchFilter'  => $branchFilter,
            'statusFilter'  => $statusFilter,
            'openTotal'     => $openTotal,
            'nBaru'         => $nBaru,
            'nDicek'        => $nDicek,
            'stripPerCabang'=> $stripPerCabang,
            'userFlagged'   => $userFlagged,
            'grup'          => $grup,
            'flagTunggal'   => $flagTunggal,
            'buktiById'     => $buktiById,
            'riwayat'       => $riwayat,
            'branches'      => $branches,
            'branchName'    => $branchName,
        ]);
    }

    /**
     * Sajikan foto selfie dari storage (BUKAN public/) — hanya bisa diakses
     * pemegang akses segment 'pengawasan' (AuthGuard). Jangan pernah pindah
     * foto ke public/: wajah karyawan bukan aset publik.
     */
    public function foto($id)
    {
        $bukti = AttHpBukti::findOrFail($id);
        $path = storage_path('app/' . $bukti->photo_path);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Tandai flag sedang dicek (dibuka SPV). */
    public function dicek($id)
    {
        $this->guardAksi();
        $flag = AttFlag::findOrFail($id);
        if ($flag->status === 'baru') {
            $flag->update(['status' => 'dicek']);
        }
        return back();
    }

    /** Penyelesaian flag — hasil WAJIB (wajar / perlu_koreksi / pelanggaran). */
    public function selesai(Request $request, $id)
    {
        $this->guardAksi();
        $data = $request->validate([
            'hasil'   => 'required|in:wajar,perlu_koreksi,pelanggaran',
            'catatan' => 'nullable|string|max:500',
        ]);

        AttFlag::findOrFail($id)->update([
            'status'        => 'selesai',
            'hasil'         => $data['hasil'],
            'catatan'       => $data['catatan'],
            'ditindak_oleh' => (string) session('id') ?: null,
            'ditindak_at'   => Carbon::now(),
        ]);

        return back()->with('success', 'Flag diselesaikan.');
    }

    /** Selesaikan grup serentak sekaligus (mis. WiFi cabang gangguan). */
    public function selesaiGrup(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'ids'     => 'required|string',
            'hasil'   => 'required|in:wajar,perlu_koreksi,pelanggaran',
            'catatan' => 'nullable|string|max:500',
        ]);

        $ids = array_filter(array_map('intval', explode(',', $data['ids'])));
        $n = AttFlag::whereIn('id', $ids)->where('status', '!=', 'selesai')->update([
            'status'        => 'selesai',
            'hasil'         => $data['hasil'],
            'catatan'       => $data['catatan'],
            'ditindak_oleh' => (string) session('id') ?: null,
            'ditindak_at'   => Carbon::now(),
        ]);

        return back()->with('success', "{$n} flag diselesaikan sekaligus.");
    }

    /**
     * "Daftarkan IP ini ke cabang…" — lingkaran belajar: jalur WiFi sah yang
     * belum terdaftar, didaftarkan dari tempat kejadian (flag), besok tak
     * ke-flag lagi.
     */
    public function daftarkanIp(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'ip'            => 'required|ip',
            'raw_branch_id' => 'required|exists:raw_branch,id',
        ]);

        $sudahAda = AttBranchIp::where('ip', $data['ip'])->first();
        if ($sudahAda) {
            return back()->with('error', 'IP ' . $data['ip'] . ' sudah terdaftar (cabang ' .
                optional($sudahAda->branch)->name . ', status ' . $sudahAda->status . ').');
        }

        AttBranchIp::create([
            'raw_branch_id' => $data['raw_branch_id'],
            'ip'            => $data['ip'],
            'label'         => 'didaftarkan dari Pengawasan',
            'status'        => 'aktif',
            'created_by'    => (string) session('id') ?: null,
        ]);

        return back()->with('success', 'IP ' . $data['ip'] . ' terdaftar ke ' .
            optional(RawBranch::find($data['raw_branch_id']))->name . '. Absen berikutnya dari jaringan ini tidak akan ke-flag.');
    }
}
