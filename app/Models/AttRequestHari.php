<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu tanggal milik sebuah pengajuan, berikut perannya.
 *
 * Dipakai Tukar Hari: satu pengajuan memuat beberapa tanggal 'masuk' dan
 * beberapa tanggal 'libur' pengganti — daftar, bukan rentang, karena
 * tanggalnya bisa terpisah-pisah (masuk Minggu, libur Rabu).
 *
 * Pengajuan lain (cuti/izin/wfh) BOLEH tidak punya baris di sini; mesin
 * rekonsiliasi jatuh kembali ke rentang tanggal_from..tanggal_to seperti
 * semula. Jadi tabel ini menambah kemampuan tanpa mengubah yang lama.
 *
 * Append-only bersama induknya: baris ditulis sekali saat pengajuan dibuat.
 */
class AttRequestHari extends Model
{
    protected $table = 'att_request_hari';

    /** created_at diisi DB (CURRENT_TIMESTAMP), tak ada updated_at. */
    public $timestamps = false;

    protected $fillable = ['request_id', 'tanggal', 'peran'];

    protected $casts = ['tanggal' => 'date'];

    public const PERAN_MASUK = 'masuk';
    public const PERAN_LIBUR = 'libur';

    public function request()
    {
        return $this->belongsTo(AttRequest::class, 'request_id', 'id');
    }
}
