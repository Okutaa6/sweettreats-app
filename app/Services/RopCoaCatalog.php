<?php

namespace App\Services;

use App\Models\RopCostCentre;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RopCoaCatalog
 *
 * Daftar akun beban (chart of account) yang dipilih Finance saat menyetujui
 * pengajuan. Sumbernya tabel staging Accurate — hasil tarik integrasi, bukan
 * data yang diketik ulang di sini.
 *
 * ⚠️ Akun diambil PER BUKU (slug), bukan gabungan semua cabang. Tiap cabang
 * punya database Accurate sendiri dan nomor akun yang sama bisa berarti lain:
 * 600010 = "Beban Tunjangan Karyawan" di bintaro, "Beban THR" di cinere,
 * "Beban Training" di holding. Menggabungkan daftarnya membuat Finance
 * memilih arti yang salah tanpa sadar, dan angka yang dikirim ke Accurate
 * nanti mendarat di akun yang keliru.
 *
 * Buku mana untuk cost centre mana ditentukan `rop_cost_centre.accurate_slug`.
 */
class RopCoaCatalog
{
    /** Jenis akun yang boleh dipilih untuk pengeluaran. */
    private const TIPE = ['EXPENSE', 'OTHER_EXPENSE'];

    private const TTL = 3600; // 1 jam — staging di-resync harian, tidak perlu real-time

    /**
     * Daftar akun untuk satu cost centre: [['no' => '600102', 'name' => '...'], …]
     * Kosong (bukan error) kalau staging Accurate belum ada di environment ini —
     * layar tetap hidup, Finance tinggal tidak punya pilihan akun.
     */
    public function untukCostCentre(string $kodeCc): array
    {
        $slug = RopCostCentre::find($kodeCc)?->accurate_slug;
        return $slug ? $this->untukSlug($slug) : [];
    }

    public function untukSlug(string $slug): array
    {
        return Cache::remember('rop_coa_' . $slug, self::TTL, function () use ($slug) {
            if (! Schema::hasTable('accurate_staging_glaccount')) {
                return [];
            }

            return DB::table('accurate_staging_glaccount')
                ->select('no', 'name')
                ->where('slug', $slug)
                ->whereIn('account_type', self::TIPE)
                ->where(function ($q) {
                    $q->where('suspended', 0)->orWhereNull('suspended');
                })
                ->orderBy('no')
                ->get()
                ->map(fn ($r) => ['no' => (string) $r->no, 'name' => (string) $r->name])
                ->unique('no')
                ->values()
                ->all();
        });
    }

    /**
     * Cari nama akun di buku sebuah cost centre. Dipakai saat Finance menyetujui
     * — nama di-snapshot ke rop_requests.coa_name supaya riwayat tidak berubah
     * arti kalau staging di-resync.
     */
    public function namaAkun(string $kodeCc, string $no): ?string
    {
        foreach ($this->untukCostCentre($kodeCc) as $akun) {
            if ($akun['no'] === $no) {
                return $akun['name'];
            }
        }
        return null;
    }

    public function kosongkanCache(): void
    {
        foreach (RopCostCentre::whereNotNull('accurate_slug')->pluck('accurate_slug')->unique() as $slug) {
            Cache::forget('rop_coa_' . $slug);
        }
    }
}
