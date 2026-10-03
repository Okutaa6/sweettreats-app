<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat follow-up — satu baris per PASIEN per HARI.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA TABEL INI ADA, PADAHAL ort_control SUDAH PUNYA fu_date
 * ────────────────────────────────────────────────────────────────────────────
 * `ort_control` menyimpan follow-up TERAKHIR saja. Telepon kedua ke pasien
 * yang sama MENIMPA yang pertama. Jadi pertanyaan "sudah berapa kali pasien
 * ini ditelepon bulan ini, dan oleh siapa" tidak punya jawaban di data —
 * padahal itu pertanyaan yang ditanyakan tiap rapat.
 *
 * prompt_jadwal_kontrol.md (Phase 2) memintanya sebagai daftar bernomor:
 *     1. 30 Sep 2026, oleh Nama Sales
 *     2. 1 Sep 2026, oleh Nama Sales
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KUNCINYA PASIEN, BUKAN KONTROL
 * ────────────────────────────────────────────────────────────────────────────
 * Dan itu bukan pilihan gaya. Daftar kerja hanya memuat pasien yang punya
 * kontrol TERBUKA (OrtQuery::dasar() menyaring `closed_at IS NULL`). Pasien
 * tanpa kontrol terbuka pindah ke antrean "Belum punya jadwal" — mereka tetap
 * ditelepon, dan kalau riwayatnya digantung di ort_control, follow-up itu tidak
 * punya tempat untuk disimpan.
 *
 * `ort_control_id` tetap disimpan sebagai KONTEKS: kontrol mana yang sedang
 * dikejar saat telepon itu terjadi. Ia NULL-able dengan sengaja.
 *
 * ⚠ Kamus `fu_result` dan `fu_channel` TIDAK didefinisikan di sini — ia tinggal
 *   di OrtControl::FU_HASIL dan FU_KANAL. Dua kamus untuk satu nilai adalah cara
 *   dua layar mulai menampilkan label yang berbeda untuk hal yang sama.
 *
 * ⚠ TIDAK ada backfill dari ort_control.fu_date, dan itu disengaja — alasannya
 *   di database/sql/2026_10_07_ort_riwayat_followup.sql. Singkatnya: fu_date
 *   adalah follow-up TERAKHIR, dan memasukkannya sebagai satu-satunya baris
 *   akan membuat pasien yang ditelepon enam kali terlihat ditelepon sekali.
 *
 * Lihat database/sql/2026_10_07_ort_riwayat_followup.sql.
 */
class OrtFollowUp extends Model
{
    protected $table = 'ort_followup';

    public $incrementing = false;
    protected $keyType   = 'string';

    /** Tabel ini hanya punya created_at — tidak pernah diubah sesudah ditulis. */
    public $timestamps = false;

    protected $fillable = [
        'id',
        'ort_case_id', 'ort_control_id', 'place_id',
        'fu_date', 'fu_channel', 'fu_result', 'fu_note',
        'fu_by', 'created_by', 'created_at',
    ];

    protected $casts = [
        'fu_date'    => 'date',
        'created_at' => 'datetime',
    ];

    /** Riwayat satu pasien, TERBARU DI ATAS — bentuk yang diminta prompt. */
    public function scopeRiwayat($q, string $caseId)
    {
        return $q->where('ort_case_id', $caseId)->orderByDesc('fu_date');
    }

    /**
     * Sudah ada follow-up untuk pasien ini di tanggal ini?
     *
     * Inilah yang membuat tombol jadi abu hari ini dan hijau lagi besok TANPA
     * cron — prompt_jadwal_kontrol.md meminta tepat itu: "Jangan pakai cron
     * reset, cukup cek tanggal." Satu lookup ke UNIQUE (ort_case_id, fu_date).
     */
    public static function sudahHariIni(string $caseId, string $tanggal): bool
    {
        return static::where('ort_case_id', $caseId)->where('fu_date', $tanggal)->exists();
    }
}
