<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RawContact;
use App\Models\RawCustomer;
use App\Support\Phone;
use Illuminate\Database\QueryException;

/**
 * Resolusi "jangkar nomor HP" (raw_contact) untuk model 1-nomor-banyak-pasien.
 *
 * Dipakai bersama oleh LandingReservationController (form publik) dan
 * Crud\ReservationController (admin "tambah pasien di nomor ini") supaya
 * logikanya satu sumber.
 *
 * Inti: 1 raw_contact per nomor ter-normalisasi; banyak raw_customer
 * (pasien) boleh menempel ke contact yang sama. resolveContact() bersifat
 * SELF-HEALING — saat sebuah nomor disentuh, semua raw_customer ber-nomor
 * sama yang belum punya raw_contact_id (legacy belum ter-backfill / buatan
 * Cekat) otomatis diadopsi ke contact tsb. Jadi sistem tetap benar walau
 * backfill SQL belum/parsial dijalankan.
 */
trait ResolvesPatientContact
{
    /**
     * Variasi format nomor yang mungkin tersimpan di data lama
     * (08xx / 8xx / 62xx / 0062xx). Dipakai untuk mencocokkan
     * raw_contact & raw_customer lintas-format tanpa REGEXP di query.
     *
     * @return array<int,string>
     */
    protected function phoneVariants(?string $phone): array
    {
        $norm = Phone::normalize($phone);
        if ($norm === null) {
            $raw = trim((string) $phone);
            return $raw === '' ? [] : [$raw];
        }

        $bare = ltrim($norm, '0'); // 8xxxxxxxxx
        return array_values(array_unique([
            $norm,                 // 08xxxxxxxxx
            $bare,                 // 8xxxxxxxxx
            '62' . $bare,          // 628xxxxxxxxx
            '0062' . $bare,        // 00628xxxxxxxxx
        ]));
    }

    /**
     * Cari / buat raw_contact untuk nomor ini, lalu adopsi pasien lama
     * ber-nomor sama yang belum punya contact. WAJIB dipanggil di dalam
     * DB transaction milik pemanggil.
     *
     * @param string|null $phone   Nomor mentah dari form / data customer.
     * @param string|null $name    Nama utk contact baru (opsional).
     * @param string      $source  source_app contact baru.
     * @param string      $channel channel contact baru.
     */
    protected function resolveContact(
        ?string $phone,
        ?string $name = null,
        string $source = 'website',
        string $channel = 'reservation'
    ): RawContact {
        $variants = $this->phoneVariants($phone);

        if (empty($variants)) {
            // Tidak ada nomor valid sama sekali — buat contact "yatim"
            // supaya alur tetap jalan; tidak ada yang bisa diadopsi.
            return RawContact::create([
                'source_app' => $source,
                'name'       => $name,
                'phone'      => null,
                'channel'    => $channel,
            ]);
        }

        $stored = Phone::normalize($phone) ?? trim((string) $phone);

        $contact = RawContact::whereIn('phone', $variants)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if (! $contact) {
            try {
                RawContact::create([
                    'source_app' => $source,
                    'name'       => $name,
                    'phone'      => $stored,
                    'channel'    => $channel,
                ]);
            } catch (QueryException $e) {
                // Balapan insert (kalau kelak ada unique index) — abaikan,
                // ambil ulang di bawah.
            }

            // Ambil ulang & pilih yang TERLAMA secara deterministik supaya
            // dua request balapan tetap konvergen ke contact yang sama.
            $contact = RawContact::whereIn('phone', $variants)
                ->orderBy('created_at')
                ->orderBy('id')
                ->firstOrFail();
        }

        // Self-healing: tarik semua pasien ber-nomor sama yang belum
        // ter-anchor (legacy belum ter-backfill / buatan Cekat).
        RawCustomer::whereNull('raw_contact_id')
            ->whereIn('phone', $variants)
            ->update(['raw_contact_id' => $contact->id]);

        return $contact;
    }
}
