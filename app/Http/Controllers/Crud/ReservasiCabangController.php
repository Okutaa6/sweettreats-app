<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawCusReservation;
use App\Models\Users;

/**
 * Daftar reservasi yang DISARING ke cabang si pengguna. BACA SAJA.
 *
 * ⛔ CRUD-nya SENGAJA DIHAPUS (2026-09-11) — jangan dikembalikan.
 *
 * Kelas ini dulu memuat create/store/edit/update/destroy/serviceStore/
 * serviceDestroy. Tak satu pun pernah punya rute: sejak commit pertama
 * `routes/web.php` hanya mendaftarkan `GET reservasicabang` → index().
 * Semuanya salinan basi dari ReservationController yang sudah menyimpang jauh:
 *
 *   - `store()` tak mengisi `created_by`, tak memanggil `slotConflict()`, tak
 *     menyetel `status`, dan tak menulis satu pun baris perawatan.
 *   - `update()` tak punya pagar anti-tabrakan, tak mewajibkan alasan batal,
 *     dan tak menulis riwayat ke `audit_log`.
 *   - `destroy()` tak membereskan komisi (ReservationController menghapusnya dulu).
 *   - TAK SATU PUN memanggil `abortIfViewOnly()` — pengguna view-only pun bisa menulis.
 *   - `create()` bahkan sudah pasti fatal: ia merender `crud.reservation.new`
 *     dengan dua variabel saja, padahal view itu kini butuh $customers,
 *     $services, $promos, $marketingPromos, $mode, dst.
 *
 * Selama tak ada rutenya ia diam, tapi satu baris `Route::post('reservasicabang/…')`
 * sudah cukup untuk melahirkan jalur pembuatan reservasi tanpa jejak DAN tanpa
 * pagar tabrakan — dan "0 tindihan dalam 5.621 reservasi" berhenti dijamin
 * apa pun. Dihapus supaya percobaan menghidupkannya GAGAL KERAS (metodenya
 * tak ada) alih-alih diam-diam berhasil tanpa pengaman.
 *
 * Kalau suatu saat cabang memang perlu membuat/menyunting reservasi sendiri:
 * arahkan ke `ReservationController` (store/update/destroy) yang sudah punya
 * validasi, slotConflict(), created_by/created_via, alasan batal, riwayat
 * audit_log, layanan, dan komisi. JANGAN menyalin ulang logikanya ke sini —
 * dua salinan jalur pembuatan adalah persis cara lubang ini lahir.
 */
class ReservasiCabangController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        // Ambil user yang sedang login
        $userId = session('id');
        $user = Users::find($userId);

        $query = RawCusReservation::with('branch', 'customer', 'doctor')
            ->withCount('services')
            ->orderBy('date', 'desc')
            ->orderBy('raw_doctor_id')
            ->orderBy('start_hour', 'desc');

        // Jika user punya cabang, filter by cabang tersebut
        if (!empty($user->raw_branch_id)) {
            $query->where('raw_branch_id', $user->raw_branch_id);
        }

        $reservations = $query->paginate($perPage);

        return view('crud.reservasicabang.list', compact('reservations'));
    }
}
