<?php

namespace App\Services;

use App\Models\AttDeviceUser;
use App\Models\AttImportBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parse export mesin fingerprint → att_scan (mentah, idempoten).
 *
 * Menangani TIGA bentuk export (satu mesin bisa keluarin format beda):
 *   - 'log'       : Bintaro. 1 baris = 1 scan. TSV/CSV, kolom No.ID + Tgl/Waktu.
 *   - 'matriks_a' : "Lap. Detail Absensi" (Cinere/Kalideres/Sunter). 1 baris/orang,
 *                   kolom = tanggal, jam didempet tanpa pemisah ("09:5020:23").
 *   - 'matriks_b' : "Catatan Kehadiran Karyawan" (Pamulang). Sama, tapi identitas
 *                   pakai "User ID.：" / "Nama：" (titik-dua fullwidth) & jam
 *                   dipisah newline DI DALAM sel ber-kutip → wajib parser CSV betulan.
 *
 * Idempoten: hash = sha1(raw_branch_id|device_user_id|scanned_at). Upload ulang
 * (export mesin kumulatif) tidak menggandakan. Format matriks buang detik → dua
 * scan di menit sama otomatis kolaps lewat hash (memang tak terbedakan).
 *
 * Lihat docs/rencana-rekonsiliasi-absensi.md §2.2, §5.3.
 */
class AttendanceCsvImporter
{
    /** Diisi setelah parse(): ['format'=>, 'periode'=>[from,to], 'warnings'=>[]]. */
    public ?array $lastDiagnostic = null;

    private const DT_FORMATS = ['d/m/Y H:i:s', 'd/m/Y H:i'];

    // ── Publik ────────────────────────────────────────────────────────────────

    /**
     * Parse file → daftar scan mentah.
     *
     * @return array<int, array{device_user_id:string, scanned_at:Carbon, raw:array}>
     */
    public function parse(string $path): array
    {
        $rows = $this->readRows($path);
        $format = $this->detectFormat($rows);
        $this->lastDiagnostic = ['format' => $format, 'periode' => [null, null], 'warnings' => []];

        return $format === 'log'
            ? $this->parseLog($rows)
            : $this->parseMatriks($rows);
    }

    /**
     * Parse + simpan ke att_scan (idempoten) + catat batch.
     * $branchId dipilih manual saat upload (kolom Departemen di file BUKAN cabang).
     */
    public function import(string $path, string $branchId, ?string $uploadedBy = null, ?string $fileName = null): AttImportBatch
    {
        $scans = $this->parse($path);

        // preload mapping device -> user untuk cabang ini (satu query)
        $map = AttDeviceUser::where('raw_branch_id', $branchId)
            ->pluck('user_id', 'device_user_id')->all();

        // Atomik: kalau gagal di tengah, batch + scan di-rollback bareng —
        // tak ada batch yatim dgn counter 0 atau scan setengah masuk.
        return DB::transaction(function () use ($scans, $branchId, $uploadedBy, $fileName, $path, $map) {
            $new = 0; $dup = 0; $unmapped = 0;
            $minDate = null; $maxDate = null;

            $batch = AttImportBatch::create([
                'raw_branch_id' => $branchId,
                'uploaded_by' => $uploadedBy,
                'file_name' => $fileName ?? basename($path),
                'format' => $this->lastDiagnostic['format'],
                'total_rows' => count($scans),
                'new_rows' => 0, 'dup_rows' => 0, 'unmapped_rows' => 0,
                'diagnostic' => $this->lastDiagnostic,
                'uploaded_at' => Carbon::now(),
            ]);

            foreach ($scans as $s) {
                $dev = $s['device_user_id'];
                $at = $s['scanned_at']->format('Y-m-d H:i:s');
                $hash = sha1($branchId . '|' . $dev . '|' . $at);
                $userId = $map[$dev] ?? null;
                if ($userId === null) $unmapped++;

                $inserted = DB::table('att_scan')->insertOrIgnore([
                    'batch_id' => $batch->id,
                    'raw_branch_id' => $branchId,
                    'device_user_id' => $dev,
                    'user_id' => $userId,
                    'scanned_at' => $at,
                    'source' => 'fingerprint',
                    'raw_row' => json_encode($s['raw'], JSON_UNESCAPED_UNICODE),
                    'hash' => $hash,
                    'created_at' => Carbon::now(),
                ]);
                $inserted ? $new++ : $dup++;

                $d = $s['scanned_at']->toDateString();
                if ($minDate === null || $d < $minDate) $minDate = $d;
                if ($maxDate === null || $d > $maxDate) $maxDate = $d;
            }

            $batch->update([
                'new_rows' => $new, 'dup_rows' => $dup, 'unmapped_rows' => $unmapped,
                'periode_from' => $minDate, 'periode_to' => $maxDate,
            ]);

            return $batch->refresh();
        });
    }

    // ── Baca file ───────────────────────────────────────────────────────────

    /** @return array<int, array<int, string>> */
    private function readRows(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            // Sel ber-format tanggal (mis. log fingerprint .xls) tersimpan sebagai SERIAL
            // NUMBER Excel (46219.61…). toArray(formatData=false) mengembalikan angka mentah
            // → parseDate gagal (0 scan). Iterasi sel: sel tanggal → konversi ke 'd/m/Y H:i:s'
            // (format DT_FORMATS); sel teks (jam matriks "09:5021:10") tak tersentuh.
            $sheet = IOFactory::load($path)->getActiveSheet();
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $it = $row->getCellIterator();
                $it->setIterateOnlyExistingCells(false);
                $cells = [];
                foreach ($it as $cell) {
                    $v = $cell->getValue();
                    if (is_numeric($v) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                        $v = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('d/m/Y H:i:s');
                    }
                    $cells[] = trim((string) $v);
                }
                $rows[] = $cells;
            }
            return $rows;
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path)); // buang BOM
        $delim = $this->sniffDelimiter($raw);
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim)) !== false) {   // fgetcsv hormati newline dalam kutip (Pamulang)
            $rows[] = array_map(fn ($c) => trim((string) $c), $r);
        }
        fclose($fh);
        return $rows;
    }

    private function sniffDelimiter(string $raw): string
    {
        $head = substr($raw, 0, 4096);
        $count = ['	' => substr_count($head, "\t"), ';' => substr_count($head, ';'), ',' => substr_count($head, ',')];
        arsort($count);
        return array_key_first($count);
    }

    // ── Deteksi format ────────────────────────────────────────────────────────

    private function detectFormat(array $rows): string
    {
        $head = strtolower(implode(' ', array_slice(array_map(fn ($r) => implode(' ', $r), $rows), 0, 5)));
        if (str_contains($head, 'lap. detail absensi')) return 'matriks_a';
        if (str_contains($head, 'catatan kehadiran')) return 'matriks_b';
        return 'log';
    }

    // ── Parser: log (1 baris = 1 scan) ─────────────────────────────────────────

    private function parseLog(array $rows): array
    {
        $idCol = $dtCol = null;
        $start = 0;
        foreach ($rows as $i => $r) {
            foreach ($r as $c => $v) {
                if (preg_match('/^no\.?\s*id$/i', $v)) $idCol = $c;
                if (preg_match('/tgl.*(waktu|jam)/i', $v)) $dtCol = $c;
            }
            if ($idCol !== null && $dtCol !== null) { $start = $i + 1; break; }
        }
        if ($idCol === null || $dtCol === null) {
            $this->lastDiagnostic['warnings'][] = 'Header log (No.ID / Tgl) tidak ke-detect';
            return [];
        }

        $out = [];
        for ($i = $start; $i < count($rows); $i++) {
            $r = $rows[$i];
            $dev = trim($r[$idCol] ?? '');
            $dt = $this->parseDate($r[$dtCol] ?? '');
            if ($dev === '' || !$dt) continue;
            $out[] = ['device_user_id' => $dev, 'scanned_at' => $dt, 'raw' => $r];
        }
        return $out;
    }

    // ── Parser: matriks (unpivot) ──────────────────────────────────────────────

    private function parseMatriks(array $rows): array
    {
        $ym = $this->detectYearMonth($rows);
        if (!$ym) {
            $this->lastDiagnostic['warnings'][] = 'Periode (YYYY-MM) tidak ke-detect di header matriks';
            return [];
        }
        [$year, $month] = $ym;

        $dayMap = [];   // colIndex => day
        $cur = null;    // device_user_id aktif
        $out = [];
        foreach ($rows as $r) {
            // baris header hari: >= 8 sel berisi angka menaik berurutan
            $cand = [];
            foreach ($r as $c => $v) if (preg_match('/^\d{1,2}$/', $v)) $cand[$c] = (int) $v;
            if (count($cand) >= 8) {
                $vals = array_values($cand);
                $asc = true;
                for ($k = 1; $k < count($vals); $k++) if ($vals[$k] !== $vals[$k - 1] + 1) { $asc = false; break; }
                if ($asc) { $dayMap = $cand; continue; }
            }
            // baris identitas: "ID:" (matriks_a) atau "User ID.：" (matriks_b)
            $join = implode(';', $r);
            if (preg_match('/User ?ID|ID:/u', $join)) {
                $id = null;
                foreach ($r as $c => $v) {
                    if (($v === 'ID:' || preg_match('/User ?ID/u', $v)) && $id === null) {
                        $id = $this->nextNonEmpty($r, $c);
                        break;
                    }
                }
                if ($id !== null) $cur = $id;
                continue;
            }
            // baris data
            if ($cur !== null && $dayMap) {
                foreach ($dayMap as $c => $day) {
                    if (!preg_match_all('/(\d{2}):(\d{2})/', $r[$c] ?? '', $m, PREG_SET_ORDER)) continue;
                    foreach ($m as $t) {
                        $dt = Carbon::create($year, $month, $day, (int) $t[1], (int) $t[2], 0);
                        if (!$dt) continue;
                        $out[] = ['device_user_id' => $cur, 'scanned_at' => $dt, 'raw' => [$cur, "$day/$month/$year", $t[0]]];
                    }
                }
            }
        }
        return $out;
    }

    private function detectYearMonth(array $rows): ?array
    {
        foreach ($rows as $r) {
            foreach ($r as $v) {
                if (preg_match('/(\d{4})-(\d{2})-\d{2}\s*~/', $v, $m)) return [(int) $m[1], (int) $m[2]];
            }
        }
        return null;
    }

    // ── Util ──────────────────────────────────────────────────────────────────

    private function parseDate(string $s): ?Carbon
    {
        $s = trim($s);
        foreach (self::DT_FORMATS as $f) {
            // DateTime native RETURN false saat tak cocok — Carbon::createFromFormat MELEMPAR
            // exception, yang bikin format ke-2 ('d/m/Y H:i' tanpa detik) tak pernah dicoba.
            $d = \DateTime::createFromFormat($f, $s);
            if ($d !== false && $d->format($f) === $s) return Carbon::instance($d);
        }
        return null;
    }

    private function nextNonEmpty(array $r, int $from): ?string
    {
        for ($j = $from + 1; $j < count($r); $j++) if (trim($r[$j]) !== '') return trim($r[$j]);
        return null;
    }
}
