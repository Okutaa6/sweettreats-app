<?php

namespace App\Console\Commands;

use App\Models\AttRequest;
use App\Models\RawBranch;
use App\Models\Users;
use App\Services\AttendanceReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import klaim lembur dari CSV Google Form ("Form Lembur ... Responses") → att_request.
 * TRANSISI: data klaim lama masuk app biar finance bisa sanding vs scan (verifikasi
 * dihitung otomatis). Ke depan staff pakai form app (/akun/pengajuan), bukan ini.
 *
 * Kolom CSV: NAMA, CABANG, POSISI, HARI, TANGGAL, MULAI JAM, SAMPAI JAM,
 *            ALASAN LEMBUR, TOTAL JAM, UPLOAD FOTO LEMBUR.
 *
 * Idempoten: skip kalau att_request lembur (user, tanggal, jam_from) sudah ada.
 */
class AttImportLembur extends Command
{
    protected $signature = 'att:import-lembur {file : Path CSV Form Lembur}
        {--from= : filter tanggal mulai (Y-m-d)}
        {--to= : filter tanggal akhir (Y-m-d)}
        {--status=approved : status att_request (approved/pending_l1)}';

    protected $description = 'Import klaim lembur Google Form → att_request (transisi, sanding vs scan)';

    public function handle(AttendanceReconciler $rec): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) { $this->error("File tak ditemukan: $file"); return 1; }
        $from = $this->option('from');
        $to   = $this->option('to');
        $status = $this->option('status');

        $branches = RawBranch::pluck('id', 'name');   // nama → id
        $branchLc = [];
        foreach ($branches as $nm => $id) $branchLc[mb_strtolower($nm)] = $id;

        $fh = fopen($file, 'r');
        $hdr = array_map('trim', fgetcsv($fh));
        $idx = array_flip($hdr);
        $need = ['NAMA', 'CABANG', 'TANGGAL', 'MULAI JAM', 'SAMPAI JAM', 'TOTAL JAM'];
        foreach ($need as $k) if (! isset($idx[$k])) { $this->error("Kolom '$k' tak ada di CSV."); return 1; }

        $imp = 0; $skipDup = 0; $skipName = 0; $skipRange = 0; $verif = ['nyambung' => 0, 'selisih' => 0, 'tidak_bisa_dicek' => 0, 'null' => 0];

        while (($r = fgetcsv($fh)) !== false) {
            $nama = trim($r[$idx['NAMA']] ?? '');
            if ($nama === '') continue;
            $tglRaw = trim($r[$idx['TANGGAL']] ?? '');
            $date = $this->parseDate($tglRaw);
            if (! $date) continue;
            if ($from && $date < $from) { $skipRange++; continue; }
            if ($to && $date > $to)     { $skipRange++; continue; }

            $user = $this->findUser($nama, $r[$idx['CABANG']] ?? '', $branchLc);
            if (! $user) { $skipName++; $this->line("  <fg=yellow>? user tak ketemu:</> $nama ($tglRaw)"); continue; }

            $jamFrom = $this->parseTime($r[$idx['MULAI JAM']] ?? '');
            $jamTo   = $this->parseTime($r[$idx['SAMPAI JAM']] ?? '');
            $claimed = $this->totalMinutes($r[$idx['TOTAL JAM']] ?? '');

            // dedup
            $exists = AttRequest::where('user_id', $user->id)->where('tipe', 'lembur')
                ->where('tanggal_from', $date)->where('jam_from', $jamFrom)->exists();
            if ($exists) { $skipDup++; continue; }

            [$v, $ev] = $rec->checkLemburClaim($user->id, $date, $claimed, 'jam');
            $verif[$v ?? 'null']++;

            AttRequest::create([
                'user_id'       => $user->id,
                'raw_branch_id' => $branchLc[mb_strtolower(trim($r[$idx['CABANG']] ?? ''))] ?? $user->raw_branch_id,
                'tipe'          => 'lembur',
                'sub_tipe'      => 'jam',
                'tanggal_from'  => $date,
                'tanggal_to'    => $date,
                'jam_from'      => $jamFrom,
                'jam_to'        => $jamTo,
                'alasan'        => trim($r[$idx['ALASAN LEMBUR']] ?? '') ?: 'Import Form Lembur',
                'foto'          => null,   // URL Drive di CSV — tak diunduh; bukti tetap ada di Drive
                'claimed_menit' => $claimed,
                'scan_evidence' => $ev,
                'verifikasi'    => $v,
                'status'        => $status,
            ]);
            $imp++;
        }
        fclose($fh);

        $this->info("Import lembur selesai.");
        $this->table(['metrik', 'nilai'], [
            ['diimpor', $imp],
            ['skip (duplikat)', $skipDup],
            ['skip (nama tak ketemu)', $skipName],
            ['skip (luar rentang)', $skipRange],
            ['verif: nyambung', $verif['nyambung']],
            ['verif: selisih', $verif['selisih']],
            ['verif: tidak_bisa_dicek', $verif['tidak_bisa_dicek']],
        ]);
        return 0;
    }

    private function findUser(string $nama, string $cabang, array $branchLc): ?Users
    {
        $first = trim(explode(' ', $nama)[0]);
        $q = Users::where('nama', 'like', $first . '%');
        $bid = $branchLc[mb_strtolower(trim($cabang))] ?? null;
        // disambiguasi pakai cabang kalau ada lebih dari satu
        if ((clone $q)->count() > 1 && $bid) {
            $byBranch = (clone $q)->where('raw_branch_id', $bid)->first();
            if ($byBranch) return $byBranch;
        }
        return $q->first();
    }

    private function parseDate(string $s): ?string
    {
        $d = \DateTime::createFromFormat('d/m/Y', trim($s));
        return $d ? $d->format('Y-m-d') : null;
    }

    private function parseTime(string $s): ?string
    {
        $s = trim($s);
        if ($s === '') return null;
        $p = array_map('intval', explode(':', $s));
        return sprintf('%02d:%02d', $p[0] ?? 0, $p[1] ?? 0);
    }

    private function totalMinutes(string $s): ?int
    {
        $s = trim($s);
        if ($s === '') return null;
        $p = array_map('intval', explode(':', $s));
        return ($p[0] ?? 0) * 60 + ($p[1] ?? 0);
    }
}
