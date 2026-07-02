<?php

namespace App\Services;

use App\Models\RawCusReservation;
use App\Models\RawCusReservationStaging;
use App\Models\RawDoctor;
use App\Models\RawReservationImportBatch;
use App\Support\Phone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

/**
 * Parse CSV/XLSX jadwal reservasi sheets cabang → masukin ke staging.
 *
 * Format CSV Bintaro (header di baris ke-5, tapi auto-detect via header
 * yang mengandung "Nama Pasien" + "Tanggal treatment"):
 *   - Nama yang masukin jadwal (PIC)
 *   - Tanggal reservasi (date input)
 *   - Tanggal treatment ("Jumat, 02 Januari 2026")
 *   - Nama Pasien, Email, No.Telp, NIK
 *   - kolom kosong (kadang notes)
 *   - pasien rata (jam "10:00" / "10.00")
 *   - Dokter ("drg. Panji" / "drg. Yasnelly Sp. Ort")
 *   - Follow Up H-1 (CONFRIM/CANCEL/RESCHEDULE)
 *   - 3 kolom follow-up (mostly kosong)
 *   - Keterangan Kehadiran
 *   - Tindakan Reservasi ("PSA" / "SCALING")
 *   - Total Reservasi ("4.999.000")
 *
 * Noise rows (di-skip otomatis):
 *   - LIBUR ..., JADWAL DOK ..., DRG PANJI CUTI, DRG. NELLY CUTI,
 *     TIDAK LIBUR ..., DOK PANJI BARU MULAI JAM ..., DRG DITO SAMPE JAM ...
 */
class ReservationCsvImporter
{
    /**
     * Diagnostic: kolom header yang ke-detect & mapping kanonik. Diisi setelah
     * import() jalan supaya controller bisa kasih warning kalau kolom kunci
     * (mis. start_hour) tidak ke-detect.
     *
     * @var array{headers: array<int, string>, mapping: array<string, int>}|null
     */
    public ?array $lastDiagnostic = null;

    /** Pola noise di kolom Nama Pasien — kalau match, skip baris. */
    private const NOISE_PATTERNS = [
        '/^libur\b/i',
        '/^jadwal\s+dok/i',
        '/^drg\.?\s+\w+\s+cuti\b/i',
        '/^drg\.?\s+\w+\s+izin\b/i',
        '/^tidak\s+libur\b/i',
        '/^dok\s+\w+\s+(baru\s+mulai|sampe|sampai|datang)/i',
        '/^drg\.?\s+\w+\s+(baru\s+mulai|sampe|sampai|datang)/i',
        '/^cuti\b/i',
        '/^off\b/i',
    ];

    /** Map kolom header (lowercase + trimmed) → key kanonik internal. */
    private const HEADER_MAP = [
        'nama yang masukin jadwal' => 'pic',
        'pic' => 'pic',
        'tanggal reservasi' => 'tanggal_reservasi',
        'tanggal treatment' => 'tanggal_treatment',
        'tanggal' => 'tanggal_treatment',
        'nama pasien' => 'patient_name',
        'pasien' => 'patient_name',
        'alamat email' => 'patient_email',
        'email' => 'patient_email',
        'no.telp' => 'patient_phone',
        'no telp' => 'patient_phone',
        'no. telp' => 'patient_phone',
        'nomor telp' => 'patient_phone',
        'phone' => 'patient_phone',
        'nik' => 'patient_nik',
        // Jam mulai praktek — banyak varian di sheet cabang.
        'pasien rata' => 'start_hour',
        'pasien data' => 'start_hour',
        'jam' => 'start_hour',
        'jam mulai' => 'start_hour',
        'jam mulai praktek' => 'start_hour',
        'jam praktek' => 'start_hour',
        'mulai praktek' => 'start_hour',
        'waktu' => 'start_hour',
        'waktu mulai' => 'start_hour',
        'time' => 'start_hour',
        'start hour' => 'start_hour',
        'start_hour' => 'start_hour',
        'dokter' => 'doctor',
        'follow up h-1' => 'status_raw',
        'follow up h -1' => 'status_raw',
        'follow up' => 'follow_up_extra',
        'konfirmasi' => 'status_raw',
        'keterangan kehadiran' => 'keterangan_kehadiran',
        'tindakan reservasi' => 'tindakan',
        'tindakan' => 'tindakan',
        'total reservasi' => 'total',
        'total' => 'total',
    ];

    /** Substring fallback — kalau exact match gagal, scan substring di header. */
    private const HEADER_SUBSTRING_MAP = [
        'start_hour' => ['pasien rata', 'jam mulai', 'jam praktek', 'mulai praktek', 'waktu mulai'],
        'doctor' => ['dokter', 'doctor'],
        'patient_name' => ['nama pasien'],
        'tanggal_treatment' => ['tanggal treatment', 'tgl treatment', 'tgl tindakan'],
        'patient_phone' => ['no.telp', 'no telp', 'nomor telp', 'phone', 'hp', 'wa'],
        'tindakan' => ['tindakan'],
        'total' => ['total reservasi', 'total bayar', 'biaya'],
    ];

    private const BULAN_ID = [
        'januari' => 1, 'jan' => 1,
        'februari' => 2, 'feb' => 2, 'pebruari' => 2,
        'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agust' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nov' => 11, 'nop' => 11,
        'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    /**
     * Import CSV/XLSX ke staging.
     *
     * @param  array{column_mapping?:array<string,int>, header_row_index?:int}  $options
     *         - column_mapping: override mapping kanonik → index kolom (mis.
     *           ['patient_name' => 3, 'tanggal_treatment' => 2, ...]). Kalau
     *           di-set, skip auto-detect header.
     *         - header_row_index: skip N baris sebelum header. Kalau di-set
     *           bareng column_mapping, header row di-ambil dari index ini
     *           (untuk display) tapi mapping pakai override.
     */
    public function import(UploadedFile $file, string $branchId, ?string $userId, array $options = []): RawReservationImportBatch
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $reader = $ext === 'xlsx' || $ext === 'xls'
            ? new XlsxReader()
            : new CsvReader();

        if ($reader instanceof CsvReader) {
            $reader->setDelimiter(',');
            $reader->setEnclosure('"');
            $reader->setInputEncoding('UTF-8');
        }
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        // ── Resolve header row + column mapping ──
        $manualMapping = $options['column_mapping'] ?? null;
        $manualHeaderIndex = $options['header_row_index'] ?? null;

        if (is_array($manualMapping) && ! empty($manualMapping)) {
            // Override mode: user define mapping via wizard
            $headerIndex = is_int($manualHeaderIndex) ? $manualHeaderIndex : -1;
            $headerRow = ($headerIndex >= 0 && isset($rows[$headerIndex])) ? $rows[$headerIndex] : [];
            // Sanitize: cuma simpan canonicals yg valid + indeks integer
            $colMap = [];
            foreach ($manualMapping as $canon => $idx) {
                if (is_string($canon) && is_numeric($idx) && (int) $idx >= 0) {
                    $colMap[$canon] = (int) $idx;
                }
            }
        } else {
            // Auto-detect mode (default)
            $headerIndex = $this->findHeaderRow($rows);
            if ($headerIndex === null) {
                throw new \RuntimeException('Header CSV tidak ketemu. Pastikan kolom "Nama Pasien" dan "Tanggal treatment" ada di salah satu baris awal — atau pakai Wizard untuk pilih kolom manual.');
            }
            $headerRow = $rows[$headerIndex];
            $colMap = $this->buildColMap($headerRow);

            // Fallback heuristic: kalau header detection gagal nge-map start_hour,
            // scan baris data — kolom yg isinya didominasi format HH:MM kita anggap
            // sbg kolom jam.
            if (! isset($colMap['start_hour'])) {
                $detectedHourCol = $this->detectStartHourByValues($rows, $headerIndex, $colMap);
                if ($detectedHourCol !== null) {
                    $colMap['start_hour'] = $detectedHourCol;
                }
            }
        }

        // Simpan diagnostic supaya controller bisa lapor ke user kalau ada
        // kolom kunci yg tidak ke-detect (mis. start_hour gak ada → jam blank).
        $this->lastDiagnostic = [
            'headers' => array_map(fn ($v) => trim((string) $v), $headerRow),
            'mapping' => $colMap,
        ];

        $batch = new RawReservationImportBatch();
        $batch->id = (string) Str::uuid();
        $batch->raw_branch_id = $branchId;
        $batch->uploaded_by = $userId;
        $batch->file_name = $file->getClientOriginalName();
        $batch->uploaded_at = now();
        $batch->total_rows = 0;
        $batch->parsed_rows = 0;
        $batch->skipped_rows = 0;
        $batch->promoted_rows = 0;
        $batch->save();

        $total = 0;
        $parsed = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $headerIndex, $colMap, $batch, $branchId, &$total, &$parsed, &$skipped) {
            $stagingRows = [];

            foreach ($rows as $i => $row) {
                if ($i <= $headerIndex) continue;

                // Skip baris benar-benar kosong
                $hasAny = false;
                foreach ($row as $v) {
                    if (trim((string) $v) !== '') { $hasAny = true; break; }
                }
                if (! $hasAny) continue;

                $total++;

                $rawName = trim((string) $this->getCol($row, $colMap, 'patient_name'));

                // Noise filter
                if ($this->isNoiseRow($rawName, $row, $colMap)) {
                    $skipped++;
                    continue;
                }

                $parsed++;
                $stagingRows[] = $this->buildStagingRow($row, $colMap, $batch->id, $branchId, $i);
            }

            // Insert batch — chunk supaya tidak ledak kalau ratusan baris
            foreach (array_chunk($stagingRows, 100) as $chunk) {
                RawCusReservationStaging::insert($chunk);
            }
        });

        $batch->total_rows = $total;
        $batch->parsed_rows = $parsed;
        $batch->skipped_rows = $skipped;
        $batch->save();

        return $batch->fresh();
    }

    private function findHeaderRow(array $rows): ?int
    {
        $limit = min(15, count($rows));

        // Pass 1: spesifik — "nama pasien" + "tanggal treatment"
        for ($i = 0; $i < $limit; $i++) {
            $joined = mb_strtolower(implode(' | ', array_map(fn ($v) => (string) $v, $rows[$i])));
            if (str_contains($joined, 'nama pasien') && str_contains($joined, 'tanggal treatment')) {
                return $i;
            }
        }
        // Pass 2: pasien + (dokter | tanggal | tindakan) — kombinasi yg umum
        for ($i = 0; $i < $limit; $i++) {
            $joined = mb_strtolower(implode(' | ', array_map(fn ($v) => (string) $v, $rows[$i])));
            if (str_contains($joined, 'pasien')
                && (str_contains($joined, 'dokter')
                    || str_contains($joined, 'tanggal')
                    || str_contains($joined, 'tindakan'))) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Scan baris data setelah header — return index kolom yang isinya didominasi
     * format jam (HH:MM atau Excel serial 0..1). Threshold 50% non-empty cell
     * harus parse-able sebagai jam. Tujuan: handle CSV dgn header non-standar
     * untuk kolom jam (mis. "Mulai", "Slot Time", header kosong, dst).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, int>  $alreadyMapped  kolom yg sudah di-claim canonicals
     *                                              lain (tanggal, pasien, dll) — skip
     */
    private function detectStartHourByValues(array $rows, int $headerIndex, array $alreadyMapped): ?int
    {
        // Kolom yg sudah di-claim canonicals lain → skip supaya tidak double-map.
        $usedCols = array_flip(array_values($alreadyMapped));

        // Sample 30 baris pertama data — cukup untuk inferensi tanpa bikin O(N*M).
        $sampleLimit = 30;
        $sampled = [];
        foreach ($rows as $i => $r) {
            if ($i <= $headerIndex) continue;
            $hasAny = false;
            foreach ($r as $v) {
                if (trim((string) $v) !== '') { $hasAny = true; break; }
            }
            if (! $hasAny) continue;
            $sampled[] = $r;
            if (count($sampled) >= $sampleLimit) break;
        }
        if (! $sampled) return null;

        // Hitung max kolom dari header row + sample rows.
        $maxCols = max(
            count($rows[$headerIndex] ?? []),
            ...array_map('count', $sampled),
        );

        $bestCol = null;
        $bestRatio = 0.0;

        for ($col = 0; $col < $maxCols; $col++) {
            if (isset($usedCols[$col])) continue;
            $nonEmpty = 0;
            $hourLike = 0;
            foreach ($sampled as $row) {
                $val = isset($row[$col]) ? trim((string) $row[$col]) : '';
                if ($val === '') continue;
                $nonEmpty++;
                if ($this->parseTime($val) !== null) {
                    $hourLike++;
                }
            }
            // Butuh minimal 3 non-empty cell biar inferensinya valid.
            if ($nonEmpty < 3) continue;
            $ratio = $hourLike / $nonEmpty;
            if ($ratio >= 0.5 && $ratio > $bestRatio) {
                $bestRatio = $ratio;
                $bestCol = $col;
            }
        }
        return $bestCol;
    }

    private function buildColMap(array $headerRow): array
    {
        $map = [];

        // Pass 1: exact match (lowercase + trimmed)
        foreach ($headerRow as $idx => $val) {
            $key = mb_strtolower(trim((string) $val));
            // Normalize multiple spaces (newlines, double-spaces dari merged cells)
            $key = preg_replace('/\s+/u', ' ', $key);
            if ($key === '') continue;
            if (isset(self::HEADER_MAP[$key])) {
                $canon = self::HEADER_MAP[$key];
                if (! isset($map[$canon])) {
                    $map[$canon] = $idx;
                }
            }
        }

        // Pass 2: substring fallback untuk canonicals yang masih kosong.
        foreach (self::HEADER_SUBSTRING_MAP as $canon => $needles) {
            if (isset($map[$canon])) continue;
            foreach ($headerRow as $idx => $val) {
                $key = mb_strtolower(trim((string) $val));
                $key = preg_replace('/\s+/u', ' ', $key);
                if ($key === '') continue;
                foreach ($needles as $needle) {
                    if (str_contains($key, $needle)) {
                        $map[$canon] = $idx;
                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    private function getCol(array $row, array $colMap, string $canon): ?string
    {
        $idx = $colMap[$canon] ?? null;
        if ($idx === null) return null;
        return isset($row[$idx]) ? (string) $row[$idx] : null;
    }

    private function isNoiseRow(string $nameRaw, array $row, array $colMap): bool
    {
        if ($nameRaw === '') {
            // Kalau jam + dokter + tanggal semua kosong → noise.
            // Kalau ada minimal salah satu → kemungkinan waiting list dengan nama lupa → tetap parse.
            $hour = trim((string) $this->getCol($row, $colMap, 'start_hour'));
            $doctor = trim((string) $this->getCol($row, $colMap, 'doctor'));
            $date = trim((string) $this->getCol($row, $colMap, 'tanggal_treatment'));
            return $hour === '' && $doctor === '' && $date === '';
        }
        foreach (self::NOISE_PATTERNS as $pat) {
            if (preg_match($pat, $nameRaw)) {
                return true;
            }
        }
        return false;
    }

    private function buildStagingRow(array $row, array $colMap, string $batchId, string $branchId, int $sourceRowIndex): array
    {
        $warnings = [];

        $rawDate = trim((string) $this->getCol($row, $colMap, 'tanggal_treatment'));
        $parsedDate = $this->parseIndoDate($rawDate);
        if ($rawDate !== '' && ! $parsedDate) {
            $warnings[] = 'date_unparseable';
        }

        $rawHour = trim((string) $this->getCol($row, $colMap, 'start_hour'));
        $parsedHour = $this->parseTime($rawHour);
        if ($rawHour !== '' && ! $parsedHour) {
            $warnings[] = 'time_unparseable';
        }

        $rawDoctor = trim((string) $this->getCol($row, $colMap, 'doctor'));
        $resolvedDoctorId = null;
        if ($rawDoctor !== '') {
            $doc = RawDoctor::resolveByIdOrName($rawDoctor);
            if ($doc) {
                $resolvedDoctorId = $doc->id;
            } else {
                $warnings[] = 'doctor_not_found';
            }
        } else {
            $warnings[] = 'doctor_missing';
        }

        $rawStatus = trim((string) $this->getCol($row, $colMap, 'status_raw'));
        $mappedStatus = $this->mapStatus($rawStatus);

        $rawTotal = trim((string) $this->getCol($row, $colMap, 'total'));
        $parsedTotal = $this->parseCurrency($rawTotal);

        $patientName = trim((string) $this->getCol($row, $colMap, 'patient_name'));
        $patientPhone = Phone::normalize(trim((string) $this->getCol($row, $colMap, 'patient_phone')));
        $patientEmail = trim((string) $this->getCol($row, $colMap, 'patient_email'));
        $patientNik = trim((string) $this->getCol($row, $colMap, 'patient_nik'));

        // Beberapa CSV pakai kolom Email buat catatan random (bukan email valid).
        // Kalau bukan format email → simpan sebagai patient_note.
        $patientNote = null;
        if ($patientEmail !== '' && ! filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            $patientNote = $patientEmail;
            $patientEmail = null;
        }

        // Sheet Bintaro sering pakai kolom NIK utk catatan request pasien
        // (mis. "bilang jam 16.20" = pasien minta jam 16:20 padahal yg
        // di-assign 16:40 di kolom "pasien rata"). Kalau NIK bukan angka,
        // pindah ke patient_note supaya tetap kelihatan di staging table
        // dan NIK tidak ke-set salah.
        if ($patientNik !== '' && ! preg_match('/^\d{6,18}$/', preg_replace('/\s+/', '', $patientNik))) {
            $patientNote = $patientNote ? ($patientNote . ' | ' . $patientNik) : $patientNik;
            $patientNik = '';
        }

        // Fallback: kalau "pasien rata" kosong tapi catatan punya "bilang jam X"
        // (atau pola serupa), extract jam itu sebagai start_hour. Ini adalah
        // jam yg pasien REQUEST — bisa di-edit user sebelum promote kalau
        // clinic ternyata assign jam berbeda. Tanpa fallback ini, kolom Jam
        // di staging table tampak kosong padahal info jam ADA di catatan.
        if (! $parsedHour && $patientNote) {
            $hourFromNote = $this->extractHourFromNote($patientNote);
            if ($hourFromNote) {
                $parsedHour = $hourFromNote;
                $warnings[] = 'time_from_note';
                // Hilangkan flag time_unparseable kalau sebelumnya di-set
                // (raw cell kosong, bukan unparseable).
                $warnings = array_diff($warnings, ['time_unparseable']);
            }
        }

        $tindakan = trim((string) $this->getCol($row, $colMap, 'tindakan'));
        $keteranganKehadiran = trim((string) $this->getCol($row, $colMap, 'keterangan_kehadiran'));
        if ($keteranganKehadiran !== '') {
            $tindakan = $tindakan !== '' ? ($tindakan . ' — ' . $keteranganKehadiran) : $keteranganKehadiran;
        }

        $isWaiting = $parsedDate ? 0 : 1;

        // Determine state: error kalau pasien & tanggal & jam fatal kosong.
        $state = RawCusReservationStaging::STATE_PENDING;
        if (! $patientName) {
            $warnings[] = 'patient_name_missing';
        }

        return [
            'id' => (string) Str::uuid(),
            'batch_id' => $batchId,
            'raw_branch_id' => $branchId,
            'source_row_index' => $sourceRowIndex + 1,
            'patient_name' => $patientName ?: null,
            'patient_phone' => $patientPhone,
            'patient_email' => $patientEmail ?: null,
            'patient_nik' => $patientNik ?: null,
            'patient_note' => $patientNote,
            'treatment_date' => $parsedDate?->format('Y-m-d'),
            'start_hour' => $parsedHour,
            'doctor_name_raw' => $rawDoctor ?: null,
            'resolved_raw_doctor_id' => $resolvedDoctorId,
            'status_raw' => $rawStatus ?: null,
            'mapped_status' => $mappedStatus,
            'tindakan_text' => $tindakan ?: null,
            'total_reservasi' => $parsedTotal,
            'pic_name' => trim((string) $this->getCol($row, $colMap, 'pic')) ?: null,
            'tanggal_reservasi_raw' => trim((string) $this->getCol($row, $colMap, 'tanggal_reservasi')) ?: null,
            'is_waiting' => $isWaiting,
            'state' => $state,
            'promoted_to_id' => null,
            'parse_warnings' => $warnings ? json_encode(array_values(array_unique($warnings))) : null,
            'raw_row' => json_encode(array_values($row), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => null,
            'updated_by' => null,
        ];
    }

    public function parseIndoDate(?string $raw): ?Carbon
    {
        if (! $raw) return null;
        $raw = trim($raw);
        if ($raw === '') return null;

        // Coba parsing standar dulu: 2026-01-02, 02/01/2026, dst.
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            // Fallthrough
        }

        // Format "Jumat, 02 Januari 2026" / "02 Januari 2026" / "Senin 5 Mei 26"
        $clean = preg_replace('/^[a-z\.\s]+,\s*/i', '', $raw);
        if (preg_match('/(\d{1,2})\s+([a-zA-Z]+)\s+(\d{2,4})/u', $clean, $m)) {
            $d = (int) $m[1];
            $monthRaw = mb_strtolower($m[2]);
            $y = (int) $m[3];
            if ($y < 100) $y += 2000;
            $month = self::BULAN_ID[$monthRaw] ?? null;
            if ($month && $d >= 1 && $d <= 31) {
                try {
                    return Carbon::create($y, $month, $d)->startOfDay();
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }
        return null;
    }

    /**
     * Cari pola jam di catatan bebas — mis. "bilang jam 15.30",
     * "minta jam 16:00", "request 14h45", "pasien mau jam 17.20".
     * Return string H:i:s atau null kalau gak nemu.
     */
    public function extractHourFromNote(?string $note): ?string
    {
        if (! $note) return null;
        // Coba pola "<kata kunci> ... <jam>" dulu (lebih spesifik).
        if (preg_match('/(?:bilang|minta|mau|request|req|requested|pengen|maunya|pasien)\s*(?:jam)?\s*(\d{1,2})[.:hH\s]?(\d{1,2})?/iu', $note, $m)) {
            $h = (int) $m[1];
            $i = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            // Kalau menit cuma 1 digit (mis. "15.3"), interpretasikan sbg puluhan
            // (sheets ID umum pakai 15.3 buat 15:30).
            if (isset($m[2]) && strlen($m[2]) === 1) {
                $i = $i * 10;
            }
            if ($h <= 23 && $i <= 59) {
                return sprintf('%02d:%02d:00', $h, $i);
            }
        }
        // Fallback: pola "jam HH.MM" tanpa kata kunci di depan.
        if (preg_match('/\bjam\s+(\d{1,2})[.:hH\s](\d{1,2})\b/iu', $note, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            if (strlen($m[2]) === 1) $i = $i * 10;
            if ($h <= 23 && $i <= 59) {
                return sprintf('%02d:%02d:00', $h, $i);
            }
        }
        return null;
    }

    public function parseTime(?string $raw): ?string
    {
        if ($raw === null) return null;
        $raw = trim((string) $raw);
        if ($raw === '') return null;

        // Case 0: Excel/XLSX serial time fraction. Sel jam di file .xlsx
        // sering disimpan sbg float 0..1 = fraksi hari (0.6944 ≈ 16:40).
        // PhpSpreadsheet bisa balikin format ini kalau format mask sel
        // tidak terdeteksi sbg "time".
        if (is_numeric($raw)) {
            $f = (float) $raw;
            if ($f > 0 && $f < 1) {
                $totalSeconds = (int) round($f * 86400);
                $h = intdiv($totalSeconds, 3600);
                $m = intdiv($totalSeconds % 3600, 60);
                if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                    return sprintf('%02d:%02d:00', $h, $m);
                }
            }
        }

        // Case 1: PhpSpreadsheet sering coerce "11.00" jadi 11 (drop trailing zero)
        // atau "10.00" jadi "10". Terima hour-only (00–23).
        if (preg_match('/^(\d{1,2})(?:\.0+)?$/', $raw, $m)) {
            $h = (int) $m[1];
            if ($h >= 0 && $h <= 23) return sprintf('%02d:00:00', $h);
        }

        // Case 2: format dgn separator (: . spasi h) — "10:00", "10.30", "10 30", "10h30"
        $clean = preg_replace('/[\s.h]+/i', ':', $raw);
        if (preg_match('/^(\d{1,2}):(\d{1,2})/', $clean, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            // "10.3" dari coercion CSV → m[2]="3" — interpretasikan sbg 30 menit
            // (sheets Indonesia umum pakai 10.30/10.45 buat half-hour slot).
            if (strlen($m[2]) === 1) {
                $i = $i * 10;
            }
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
                return sprintf('%02d:%02d:00', $h, $i);
            }
        }

        // Case 3: HHMM tanpa separator — "1030"
        if (preg_match('/^(\d{1,2})(\d{2})$/', $raw, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
                return sprintf('%02d:%02d:00', $h, $i);
            }
        }

        return null;
    }

    public function parseCurrency(?string $raw): ?float
    {
        if (! $raw) return null;
        $raw = trim($raw);
        if ($raw === '') return null;
        // Hilangkan Rp, spasi, dan titik (separator ribuan ID).
        // Koma → titik desimal.
        $clean = str_ireplace('rp', '', $raw);
        $clean = preg_replace('/\s+/', '', $clean);
        // Jika ada koma, anggap itu desimal
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace('.', '', $clean);
        }
        if (! is_numeric($clean)) return null;
        return (float) $clean;
    }

    public function mapStatus(?string $raw): string
    {
        $r = mb_strtolower(trim((string) $raw));
        if ($r === '') return RawCusReservation::STATUS_PENDING;
        if (str_contains($r, 'cancel') || str_contains($r, 'batal')) return RawCusReservation::STATUS_CANCELLED;
        if (str_contains($r, 'confirm') || str_contains($r, 'confrim') || str_contains($r, 'konfirm')) return RawCusReservation::STATUS_CONFIRMED;
        if (str_contains($r, 'reschedule') || str_contains($r, 'jadwal ulang')) return RawCusReservation::STATUS_PENDING;
        if (str_contains($r, 'selesai') || str_contains($r, 'done')) return RawCusReservation::STATUS_COMPLETED;
        return RawCusReservation::STATUS_PENDING;
    }
}
