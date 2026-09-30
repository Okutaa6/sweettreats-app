<?php

namespace App\Observers;

use App\Models\OrtControl;
use App\Models\RawCusReservation;
use App\Services\Ortho\OrtRollup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Menjaga kolom RESERVASI di Tracking Ortho tetap jujur.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * MASALAH YANG DISELESAIKAN
 * ════════════════════════════════════════════════════════════════════════════
 * ort_control menyimpan SNAPSHOT reservasi (rsv_date, rsv_status), bukan
 * menariknya lewat join — collation prod campur, jadi JOIN ke raw_* dilarang di
 * seluruh modul ini. Konsekuensinya: begitu reservasi diubah atau dibatalkan
 * dari modul Reservasi, snapshot itu jadi BOHONG tanpa ada yang tahu. Sales
 * melihat "24 Sep · dikonfirmasi" padahal pasiennya sudah membatalkan.
 *
 * Observer ini yang menutup lubang itu.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * KENAPA OBSERVER, BUKAN KAIT DI CONTROLLER
 * ════════════════════════════════════════════════════════════════════════════
 * Reservasi ditulis dari BANYAK tempat, bukan satu: ReservationController
 * (update, updateInline, destroy), ReservasiCabangController,
 * LandingReservationController, ReservationAutoProcessor, ReservationPromoter,
 * dan DashboardController. Memasang kait di masing-masing berarti enam tempat
 * yang harus diingat selamanya — dan yang ketujuh pasti lupa.
 *
 * Sudah disapu ulang (bukan mengandalkan catatan lama):
 *   • `DB::table('raw_cus_reservation')->update/delete/insert` → NOL.
 *   • `RawCusReservation::where(...)->update(...)`             → NOL.
 *   • Semua penulis memakai INSTANCE model (::create, ->save(), ->update()),
 *     jadi event pasti terpicu.
 *
 * ⚠ SATU pengecualian yang ditemukan, dan sengaja dibiarkan:
 *   CustomerController:650 `$customer->reservations()->update([...])` adalah
 *   mass-update lewat relasi — ia TIDAK memicu event. Tapi yang disentuhnya
 *   hanya `raw_customer_id` (melepas pasien dari reservasi), bukan tanggal atau
 *   status, jadi snapshot ortho tidak terpengaruh. Kalau suatu hari baris itu
 *   ikut mengubah date/status, observer ini akan terlewat — catat di sini.
 *
 * ════════════════════════════════════════════════════════════════════════════
 * ATURAN KERAS
 * ════════════════════════════════════════════════════════════════════════════
 * 1. TIDAK PERNAH menggagalkan operasi reservasi. Modul Reservasi dipakai tiap
 *    hari oleh semua cabang; modul Ortho baru satu cabang. Seluruh badan
 *    dibungkus try/catch dan kegagalan hanya dicatat ke log.
 * 2. TIDAK PERNAH menyentuh jadwal (sch_date), kunjungan (visit_date), atau
 *    penutupan (closed_at). Observer ini hanya menyalin ulang snapshot.
 * 3. Berhenti diam-diam kalau tabel ort_control belum ada — environment yang
 *    belum menjalankan SQL-nya harus tetap bisa membuat reservasi.
 */
class OrtReservationObserver
{
    public function __construct(private OrtRollup $rollup)
    {
    }

    /**
     * Dipanggil untuk created + updated sekaligus.
     *
     * `created` ikut diawasi karena ReservationController::store() menulis
     * tautan ortho SETELAH reservasi dibuat — urutannya bisa terbalik pada
     * jalur lain, dan menyamakan snapshot dua kali jauh lebih murah daripada
     * snapshot yang meleset.
     */
    public function saved(RawCusReservation $reservation): void
    {
        $this->jaga(function () use ($reservation) {
            $ctl = $this->kontrolTertaut($reservation->id);
            if (! $ctl) {
                return;
            }

            $tanggal = $reservation->date
                ? $reservation->date->toDateString()
                : null;   // waitlist boleh tanpa tanggal

            // Tidak ada yang berubah → jangan menulis. Menyentuh baris tanpa
            // perlu membuat updated_at bergerak dan memicu rollup sia-sia.
            $sama = (string) $ctl->rsv_date?->toDateString() === (string) $tanggal
                 && (string) $ctl->rsv_status === (string) $reservation->status;

            if ($sama) {
                return;
            }

            $ctl->fill([
                'rsv_date'      => $tanggal,
                'rsv_status'    => $reservation->status,
                'rsv_synced_at' => now(),
                'updated_by'    => 'observer',
            ])->save();

            $this->rollup->refresh($ctl->ort_case_id);
        }, 'saved', $reservation->id);
    }

    /**
     * Reservasi dihapus — putuskan tautannya.
     *
     * Baris kontrolnya SENGAJA dipertahankan: jadwal kontrolnya sendiri masih
     * berlaku, yang hilang cuma reservasinya. Di halaman, baris itu kembali
     * menampilkan tombol "Buat Reservasi" — persis yang diinginkan.
     */
    public function deleted(RawCusReservation $reservation): void
    {
        $this->jaga(function () use ($reservation) {
            $ctl = $this->kontrolTertaut($reservation->id);
            if (! $ctl) {
                return;
            }

            $ctl->fill([
                'reservation_id' => null,
                'rsv_date'       => null,
                'rsv_status'     => null,
                'rsv_synced_at'  => now(),
                'updated_by'     => 'observer',
            ])->save();

            $this->rollup->refresh($ctl->ort_case_id);
        }, 'deleted', $reservation->id);
    }

    /**
     * Satu kontrol per reservasi — idx_ort_control_rsv (reservation_id).
     * Dibatasi ke yang MASIH TERBUKA: kontrol yang sudah ditutup adalah
     * riwayat, dan riwayat tidak boleh berubah karena reservasinya diedit
     * belakangan.
     */
    private function kontrolTertaut(?string $reservationId): ?OrtControl
    {
        if (! $reservationId || ! Schema::hasTable('ort_control')) {
            return null;
        }

        return OrtControl::where('reservation_id', $reservationId)
            ->whereNull('closed_at')
            ->first();
    }

    /**
     * Pembungkus yang menjamin aturan keras no. 1: apa pun yang terjadi di
     * dalam, operasi reservasi tetap berhasil.
     */
    private function jaga(callable $kerja, string $peristiwa, ?string $reservationId): void
    {
        try {
            $kerja();
        } catch (\Throwable $e) {
            Log::warning('OrtReservationObserver gagal — reservasi tetap tersimpan', [
                'peristiwa'      => $peristiwa,
                'reservation_id' => $reservationId,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
