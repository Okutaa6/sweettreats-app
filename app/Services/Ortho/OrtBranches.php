<?php

namespace App\Services\Ortho;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Peta cabang untuk modul Tracking Ortho.
 *
 * Modul ini menyimpan cabang sebagai `place_id` Omnicare (29957…30941), bukan
 * `raw_branch.id`. Tiga alasan:
 *   1. Sumber kolom Datang adalah omnicare_sales_detail.place_id.
 *   2. Seluruh filter cabang sisi Omnicare di repo ini sudah memakai place_id.
 *   3. `JOIN raw_branch` adalah PERSIS join yang meledak di prod:
 *        #1267 Illegal mix of collations
 *        (utf8mb4_unicode_ci vs utf8mb4_uca1400_ai_ci)
 *      DB lokal tidak kena, jadi kueri yang mulus di sini bisa gagal di sana.
 *
 * Karena itu kelas ini TIDAK PERNAH melakukan join. Ia mengambil lima baris
 * cabang sekali, menyimpannya di cache, dan seluruh halaman menerjemahkan
 * place_id → nama dari peta itu. Pola yang sama dipakai
 * ReconciliationController::resolveBranch() (baris 217–227).
 *
 * Nama cabang selalu ditulis PENUH di layar ("Bintaro", bukan "BIN") — aturan
 * domain no. 17 di handoff desain.
 */
class OrtBranches
{
    private const CACHE_KEY = 'ort_branches_map';
    private const CACHE_TTL = 1800; // 30 menit

    /**
     * Peta lengkap: place_id => ['place_id','branch_id','nama'].
     *
     * Hanya cabang yang aktif di config('omnicare.active_branches') dan punya
     * omnicare_place_id. ABG sengaja tidak ikut (placeholder, 0 dokter).
     */
    /**
     * Nomor WhatsApp resmi tiap cabang, untuk tombol "Ingatkan".
     *
     * ⚠ INI TEMPAT SEMENTARA. Rumah yang benar adalah kolom di raw_branch,
     *   supaya bisa diubah tanpa deploy dan dipakai modul lain juga. Ditaruh di
     *   sini karena menambah kolom ke raw_branch menyentuh tabel bersama yang
     *   dipakai banyak modul — keputusan tersendiri, bukan efek samping satu
     *   tombol. Kalau kolomnya sudah ada, baca dari sana dan hapus peta ini.
     *
     * Nomor tanpa tanda plus dan tanpa spasi, format wa.me (62…).
     */
    private const WA_CABANG = [
        '29957' => '6287880007760',  // Bintaro
        '29958' => '6281384232233',  // Sunter
        '29959' => '6281350004403',  // Kalideres
        '30439' => '6281290007261',  // Cinere
        '30941' => '6287888260252',  // Pamulang
    ];

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $aktif = (array) config('omnicare.active_branches', []);

            if (empty($aktif)) {
                return [];
            }

            // Satu kueri, satu tabel, tanpa join. Sengaja.
            $rows = DB::table('raw_branch')
                ->whereIn('omnicare_place_id', $aktif)
                ->orderBy('name')
                ->get(['id', 'name', 'omnicare_place_id']);

            $peta = [];
            foreach ($rows as $r) {
                $peta[(string) $r->omnicare_place_id] = [
                    'place_id'  => (string) $r->omnicare_place_id,
                    'branch_id' => (string) $r->id,
                    'nama'      => (string) $r->name,
                    'wa'        => self::WA_CABANG[(string) $r->omnicare_place_id] ?? null,
                ];
            }

            return $peta;
        });
    }

    /** Nama penuh cabang. Fallback ke place_id supaya tidak pernah kosong di layar. */
    public function nama(?string $placeId): string
    {
        if ($placeId === null || $placeId === '') {
            return '—';
        }

        return $this->all()[$placeId]['nama'] ?? $placeId;
    }

    /** raw_branch.id — dibutuhkan saat membuat reservasi di modul Reservasi. */
    public function branchId(?string $placeId): ?string
    {
        return $placeId === null ? null : ($this->all()[$placeId]['branch_id'] ?? null);
    }

    /**
     * Kebalikannya: raw_branch.id → place_id. Dipakai saat menerjemahkan
     * session('raw_branch_id') milik user jadi cabang modul ini.
     */
    /**
     * Pesan follow-up untuk PASIEN — beda samasekali dari linkWaIngatkan(),
     * yang ditujukan ke rekan Sales di cabang lain. Yang membaca ini orang
     * yang sedang dibicarakan, jadi nadanya sopan, menyebut cabang, dan
     * TIDAK pernah menyebut nomor rekam medis.
     *
     * Teksnya cuma DIISIKAN ke kolom WhatsApp, tidak dikirim — Sales masih
     * membacanya dan boleh mengubahnya sebelum menekan kirim. Itu disengaja:
     * pesan otomatis ke pasien yang salah konteks lebih buruk daripada
     * mengetik sendiri.
     *
     * Mengembalikan null kalau nomornya tidak valid; pemanggil harus siap
     * dengan keadaan itu dan jangan membuat tautan wa.me yang patah.
     */
    public function linkWaPasien(
        ?string $nomorPasien,
        string $namaPasien,
        ?string $placeIdCabang = null,
        ?string $tanggalKontrol = null,
        int $terlewatHari = 0,
    ): ?string {
        $wa = \App\Support\Phone::toWa($nomorPasien);
        if (! $wa) {
            return null;
        }

        $cabang = $placeIdCabang ? $this->nama($placeIdCabang) : null;
        $sapaan = 'Halo ' . \Illuminate\Support\Str::title($namaPasien) . ', ';
        $pesan  = $sapaan . 'saya dari Sweet Treats Dental'
            . ($cabang ? ' ' . $cabang : '') . '.' . "\n\n";

        $pesan .= match (true) {
            $terlewatHari > 0 && $tanggalKontrol !== null =>
                'Jadwal kontrol behel Anda tanggal ' . $tanggalKontrol . ' sudah terlewat '
                . $terlewatHari . ' hari. Boleh kami bantu jadwalkan ulang?',
            $tanggalKontrol !== null =>
                'Mengingatkan jadwal kontrol behel Anda tanggal ' . $tanggalKontrol
                . '. Apakah bisa hadir?',
            default =>
                'Kami ingin menanyakan kelanjutan kontrol behel Anda. '
                . 'Boleh kami bantu jadwalkan?',
        };

        return 'https://wa.me/' . $wa . '?text=' . rawurlencode($pesan);
    }

    /** Nomor WhatsApp cabang, atau null kalau belum terdaftar. */
    public function wa(?string $placeId): ?string
    {
        return $placeId === null ? null : ($this->all()[$placeId]['wa'] ?? null);
    }

    /**
     * Tautan WhatsApp siap-kirim ke sebuah cabang.
     *
     * Pesannya disusun di SATU tempat supaya nadanya seragam dari mana pun
     * tombol Ingatkan ditekan. Ini pesan ANTAR CABANG, bukan pesan untuk
     * pasien: yang membacanya rekan Sales di cabang tujuan, bukan orang yang
     * sedang dibicarakan.
     *
     * Mengembalikan null kalau nomor cabangnya belum ada — pemanggil harus
     * menyiapkan keadaan itu, jangan membuat tautan wa.me yang patah.
     */
    public function linkWaIngatkan(
        ?string $placeIdTujuan,
        string $namaPasien,
        ?string $noRekamMedis = null,
        ?string $namaCabangKita = null,
        ?int $terlewatHari = null,
    ): ?string {
        $nomor = $this->wa($placeIdTujuan);
        if (! $nomor) {
            return null;
        }

        $pesan = 'Halo Sweet Treats ' . $this->nama($placeIdTujuan) . ', ini dari '
            . ($namaCabangKita ?: 'cabang lain') . '.' . "\n\n"
            . 'Pasien ortho kami ' . \Illuminate\Support\Str::title($namaPasien)
            . ($noRekamMedis ? ' (' . $noRekamMedis . ')' : '')
            . ' sedang kontrol di sana';

        $pesan .= $terlewatHari !== null && $terlewatHari > 0
            ? ', dan jadwalnya sudah lewat ' . $terlewatHari . ' hari.'
            : '.';

        $pesan .= "\n" . 'Mohon dibantu dihubungi untuk kontrol berikutnya ya. Terima kasih.';

        return 'https://wa.me/' . $nomor . '?text=' . rawurlencode($pesan);
    }

    public function placeIdDariBranchId(?string $branchId): ?string
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        foreach ($this->all() as $placeId => $b) {
            if ($b['branch_id'] === $branchId) {
                return $placeId;
            }
        }

        return null;
    }

    /** Untuk dropdown: [place_id => nama], terurut nama. */
    public function pilihan(): array
    {
        return array_map(static fn ($b) => $b['nama'], $this->all());
    }

    /** Semua place_id kecuali satu — isi dropdown "Cabang tujuan" di lembar Pindahkan. */
    public function selain(string $placeId): array
    {
        $peta = $this->pilihan();
        unset($peta[$placeId]);

        return $peta;
    }

    public function ada(?string $placeId): bool
    {
        return $placeId !== null && isset($this->all()[$placeId]);
    }

    /** Dipanggil kalau daftar cabang berubah (jarang). */
    public function lupakanCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
