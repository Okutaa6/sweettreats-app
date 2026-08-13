<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom penghubung akun ↔ dokter (docs/rencana/rencana-akun-dokter.md §3).
 *
 * Sebelum ini `users` dan `raw_doctor` sama sekali tidak bertaut: users tak
 * punya raw_doctor_id, raw_doctor tak punya user_id. Akibatnya akun dokter yang
 * dibuat sekarang adalah akun KOSONG — dunia dokter (103 jadwal praktik, 57
 * jadwal khusus, 4.759 reservasi) sudah lengkap di database tapi tak ada satu
 * pun cara menjawab "user yang login ini dokter yang mana".
 *
 * Arahnya sengaja users → raw_doctor, bukan sebaliknya:
 *   • yang bertanya selalu sisi user ("saya ini dokter siapa"), dan itu satu
 *     kolom di baris yang sudah dimuat sesi login — nol join tambahan;
 *   • `raw_doctor` adalah tabel data operasional (dipakai reservasi publik,
 *     importir CSV, Open API). Menambahkan kolom akun di sana menyeret urusan
 *     otentikasi ke tabel yang tak ada hubungannya dengan itu.
 *
 * UNIQUE, bukan index biasa: satu akun = satu dokter, dan satu dokter tak boleh
 * dipegang dua akun (jadwal & reservasi yang sama tampil di dua tempat, absen
 * jadi ambigu). NULL boleh berulang — MySQL tidak menghitung NULL di UNIQUE —
 * jadi ~40 akun karyawan yang bukan dokter tetap aman.
 *
 * TANPA foreign key, sengaja. Mengikuti `users.raw_branch_id` yang juga varchar
 * polos: FK menuntut collation dua tabel persis sama, dan collation di sini
 * sudah terbukti melenceng antar environment (lihat COLLATE eksplisit di query
 * att_approval_rule ↔ group_path). FK yang gagal pasang saat deploy jauh lebih
 * mahal daripada yatim-piatu yang bisa dicek query.
 */
class AddRawDoctorIdToUsersTable extends Migration
{
    /**
     * Sengaja IDEMPOTEN, tak seperti kebanyakan migration di repo ini.
     *
     * Sebabnya: perubahan ini punya kembaran SQL
     * (database/sql/2026_08_13_akun_dokter_prasyarat.sql) karena sebagian
     * environment dibereskan lewat berkas SQL, bukan `artisan migrate`. Kalau
     * SQL-nya kebetulan jalan lebih dulu, `ADD COLUMN` polos akan meledak
     * "Duplicate column name" — dan di Laravel satu migration yang gagal
     * MEMBLOKIR seluruh sisa batch, termasuk migration lain yang tak ada
     * hubungannya. Ongkos memeriksa dulu jauh lebih murah daripada deploy yang
     * berhenti di tengah.
     */
    public function up()
    {
        if (! Schema::hasColumn('users', 'raw_doctor_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('raw_doctor_id', 255)->nullable()->after('raw_branch_id');
            });
        }

        if (! $this->punyaIndex('users_raw_doctor_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('raw_doctor_id', 'users_raw_doctor_id_unique');
            });
        }
    }

    public function down()
    {
        if ($this->punyaIndex('users_raw_doctor_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_raw_doctor_id_unique');
            });
        }

        if (Schema::hasColumn('users', 'raw_doctor_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('raw_doctor_id');
            });
        }
    }

    /**
     * Cek index lewat information_schema, bukan Doctrine DBAL — paket itu tidak
     * terpasang di proyek ini (`artisan db:show` menawarkan memasangnya), jadi
     * Schema::getIndexes()/hasIndex tak bisa dipakai.
     */
    private function punyaIndex(string $nama): bool
    {
        return \Illuminate\Support\Facades\DB::selectOne(
            'SELECT 1 AS ada FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
              LIMIT 1',
            ['users', $nama]
        ) !== null;
    }
}
