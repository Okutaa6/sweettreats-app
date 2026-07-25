<?php

namespace App\Console\Commands;

use App\Models\CutiPeriode;
use App\Models\Users;
use Illuminate\Console\Command;

/**
 * Import saldo pembuka cuti dari Excel roster user (2026-07-25) — sekali
 * jalan per environment. Default DRY-RUN; pakai --terapkan untuk menulis.
 *
 * Sumber: kolom "Sisa Cuti" sheet cabang + HQ. Robi & Ira (eksekutif) sengaja
 * TIDAK ada di daftar = di luar sistem cuti. Tanggal kontrak belum diisi
 * (menyusul) — periode dibuka tanpa tanggal, reset otomatis dorman.
 *
 * Idempotent: user yang sudah punya periode berjalan dilewati.
 */
class CutiSaldoAwal extends Command
{
    protected $signature = 'cuti:saldo-awal {--terapkan : Tulis beneran (tanpa ini cuma pratinjau)}';
    protected $description = 'Buka periode cuti pertama per karyawan dengan saldo pembuka dari Excel roster';

    /** NAMA (uppercase, sesuai Excel) => sisa cuti saat ini. */
    private const SALDO = [
        'RIZKA NOVIYANTI'            => 6,
        'AFRINA APRILIANI'           => 1,
        'YANWAR FAUZI'               => 0,
        'ALPAJRI SUHENDAR AL QHUSNI' => 3,
        'CICI NURJANAH'              => 7,
        'RIVANTI NADIANA'            => 4,
        'RAMDAN RAMDHANI'            => 4,
        'DELLA RAQISIAH'             => 6,
        'ANGGITA'                    => 10,
        'WAHYUDIN'                   => 0,
        'FIRDA MARETA NASKAH'        => 6,
        'ASIH WULAN SARI'            => 7,
        'VERINI SELVIA NASKAH'       => 0,
        'AHMAD SAIPULLOH'            => 2,
        'ALVIONA ZUNKA PRASETYO'     => 2,
        'MUTIARA NURUL FAJRI'        => 7,
        'PUTRI SYAFIRA'              => 7,
        'ROKHMATUN'                  => 0,
        'ANISA AULIA'                => 4,
        'YOVANKA RIMAMILLENIA'       => 5,
        'IRMA ISLAMIATI'             => 0,
        'MADALENA'                   => 5,
        'MUHAMMAD IKHSAN'            => 8,
    ];

    public function handle(): int
    {
        $terapkan = (bool) $this->option('terapkan');
        $users = Users::where('status', 1)->get(['id', 'nama']);

        $dibuka = $dilewati = 0;
        $takKetemu = [];

        foreach (self::SALDO as $nama => $saldo) {
            $user = $this->cocokkan($users, $nama);
            if (! $user) {
                $takKetemu[] = $nama;
                continue;
            }

            if (CutiPeriode::berjalan()->where('user_id', $user->id)->exists()) {
                $this->line("  = {$nama} → {$user->nama}: sudah punya periode, dilewati");
                $dilewati++;
                continue;
            }

            $this->info(sprintf('  + %-28s → %-25s saldo %s', $nama, $user->nama, $saldo));
            if ($terapkan) {
                CutiPeriode::create([
                    'user_id'    => $user->id,
                    'saldo_awal' => $saldo,
                    'berjalan'   => 1,
                ]);
            }
            $dibuka++;
        }

        foreach ($takKetemu as $nama) {
            $this->warn("  ? {$nama}: tidak ketemu di users — cocokkan manual");
        }

        $this->newLine();
        $this->info(($terapkan ? 'DITERAPKAN' : 'DRY-RUN (pakai --terapkan untuk menulis)')
            . " — dibuka: {$dibuka}, dilewati: {$dilewati}, tak ketemu: " . count($takKetemu));

        return self::SUCCESS;
    }

    /** Cocokkan nama Excel ke users.nama: persis dulu, lalu awalan/2-kata. */
    private function cocokkan($users, string $namaExcel): ?Users
    {
        $n = $this->norm($namaExcel);

        $persis = $users->first(fn ($u) => $this->norm($u->nama) === $n);
        if ($persis) return $persis;

        // Awalan dua-arah (Excel "MUHAMMAD IKHSAN" vs sistem "Muhamad Ikhsan"
        // tak akan persis — coba token pertama+terakhir sama).
        $tok = explode(' ', $n);
        $kandidat = $users->filter(function ($u) use ($tok) {
            $t = explode(' ', $this->norm($u->nama));
            return ($t[0] ?? '') === ($tok[0] ?? '') || str_starts_with(implode(' ', $t), implode(' ', array_slice($tok, 0, 2)));
        });

        if ($kandidat->count() === 1) return $kandidat->first();

        // Fallback ejaan (ALPAJRI↔APAJRI, MUHAMMAD↔MUHAMAD): token terakhir
        // persis + token pertama beda maksimal 2 huruf, kandidat harus unik.
        $akhir = end($tok);
        $mirip = $users->filter(function ($u) use ($tok, $akhir) {
            $t = explode(' ', $this->norm($u->nama));
            return end($t) === $akhir && levenshtein($t[0], $tok[0]) <= 2;
        });

        return $mirip->count() === 1 ? $mirip->first() : null;
    }

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtoupper($s)));
    }
}
