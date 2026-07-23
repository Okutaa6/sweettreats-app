<?php

namespace App\Console\Commands;

use App\Models\RawBranch;
use App\Services\AttendanceCsvImporter;
use Illuminate\Console\Command;

/**
 * Import file absen fingerprint ke att_scan (CLI / tes).
 * Cabang dipilih eksplisit — kolom Departemen di file BUKAN cabang.
 *
 *   php artisan att:import Bintaro "C:\path\file.csv"
 */
class AttImport extends Command
{
    protected $signature = 'att:import {branch : Nama cabang (Bintaro/Sunter/...)} {file : Path file CSV/XLS} {--by= : user_id yang upload}';
    protected $description = 'Import export mesin fingerprint ke att_scan (idempoten)';

    public function handle(AttendanceCsvImporter $importer): int
    {
        $branchName = $this->argument('branch');
        $file = $this->argument('file');

        $branch = RawBranch::whereRaw('LOWER(name) = ?', [strtolower($branchName)])->first();
        if (!$branch) {
            $this->error("Cabang '$branchName' tidak ditemukan. Pilihan: " . RawBranch::pluck('name')->implode(', '));
            return self::FAILURE;
        }
        if (!is_file($file)) {
            $this->error("File tidak ada: $file");
            return self::FAILURE;
        }

        $batch = $importer->import($file, $branch->id, $this->option('by'), basename($file));
        $d = $importer->lastDiagnostic;

        $this->info("Import selesai — cabang {$branch->name}");
        $this->table(['metrik', 'nilai'], [
            ['format', $d['format']],
            ['periode', ($batch->periode_from?->toDateString() ?? '?') . ' s/d ' . ($batch->periode_to?->toDateString() ?? '?')],
            ['total scan di file', $batch->total_rows],
            ['baru masuk', $batch->new_rows],
            ['duplikat (sudah ada)', $batch->dup_rows],
            ['scan belum ter-mapping', $batch->unmapped_rows],
            ['batch id', $batch->id],
        ]);
        if (!empty($d['warnings'])) {
            foreach ($d['warnings'] as $w) $this->warn("  ! $w");
        }

        return self::SUCCESS;
    }
}
