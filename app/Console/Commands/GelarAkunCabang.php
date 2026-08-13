<?php

namespace App\Console\Commands;

use App\Models\AttUserRule;
use App\Models\AttWorkRule;
use App\Models\AuditLog;
use App\Models\RawBranch;
use App\Models\UserPosition;
use App\Models\Users;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Buat akun karyawan untuk penggelaran cabang bertahap.
 *
 * KENAPA PERINTAH, BUKAN SQL: kata sandi harus di-hash, dan pembuatan akun di
 * aplikasi ini punya ekor yang gampang terlupa kalau ditulis manual —
 * baris posisi (`sys_user_position`), aturan kerja, dan periode cuti. Perintah
 * ini memakai model yang sama dengan menu admin, jadi akun yang lahir di sini
 * bentuknya identik dengan yang dibuat lewat layar.
 *
 * ⚠️ Ketiga role operasional (Front Office / Assistant Dentist / General
 * Services) BELUM punya `att_default_rule_id`, jadi akun yang dibuat lewat menu
 * admin lahir TANPA aturan kerja — dan orang tanpa aturan tak pernah muncul di
 * rekap absensi (kasus Alpajri, Agustus). Perintah ini menetapkan aturannya
 * secara eksplisit supaya lubang itu tak terulang di sini.
 *
 * IDEMPOTEN: orang yang sudah punya akun dilewati, bukan dibuat dua kali.
 * KERING (--dry): tampilkan rencana tanpa menulis apa pun.
 *
 * --sandi=X : semua akun memakai kata sandi yang sama, DAN akun yang sudah ada
 * ikut disetel ulang ke sandi itu. Dipakai untuk penggelaran cabang, karena
 * membagikan satu sandi lisan ke satu ruangan jauh lebih praktis daripada
 * membacakan sandi acak satu per satu. Aman karena `must_change_password`
 * tetap menyala: sandi seragam ini cuma pintu masuk sekali, bukan sandi mereka.
 *
 * Penyetelan ulang itu MELEWATI orang yang sudah mengganti sandinya sendiri —
 * sandi yang sudah jadi miliknya tak boleh dicabut oleh gelombang cabang
 * berikutnya. Lihat alasan lengkapnya di handle().
 */
class GelarAkunCabang extends Command
{
    protected $signature = 'att:gelar-akun
        {--dry : tampilkan rencana tanpa menulis}
        {--sandi= : pakai kata sandi ini utk semua (juga MENYETEL ulang yang sudah ada); kosong = acak per orang}';

    protected $description = 'Buat akun karyawan cabang (penggelaran bertahap) lengkap dgn aturan kerja, posisi, dan jatah cuti';

    /**
     * Sumber kebenaran: sheet HR 12 Agu 2026 + keadaan dev yang sudah
     * diverifikasi. sisa = jatah − penyesuaian.
     *
     * penyesuaian = selisih dari jatah 12 saat sistem mulai mencatat. Ditulis
     * sebagai baris `koreksi` terpisah supaya hak 12 harinya tetap terbaca —
     * bukan dengan mengecilkan saldo awal.
     *
     * Verini bersaldo 0 karena BELUM BERHAK (kontrak pertama) — bukan karena
     * habis. Karena itu jatahnya 0 dan tak diberi baris penyesuaian sama sekali.
     */
    private const ORANG = [
        ['nama' => 'Anggita Sarah Aprillia', 'email' => 'anggita@sweettreats.id',  'role' => 'Assistant Dentist', 'cabang' => 'Cinere',    'aturan' => 'FO-Asdent', 'jatah' => 12.0, 'sesuai' => 2.0,  'kontrak' => '2027-04-21'],
        ['nama' => 'Della Raqisiah',         'email' => 'della@sweettreats.id',     'role' => 'Front Office',      'cabang' => 'Cinere',    'aturan' => 'FO-Asdent', 'jatah' => 12.0, 'sesuai' => 6.0,  'kontrak' => '2026-11-28'],
        ['nama' => 'Wahyudin',               'email' => 'wahyudin@sweettreats.id',  'role' => 'General Services',  'cabang' => 'Cinere',    'aturan' => 'GS',        'jatah' => 12.0, 'sesuai' => 1.0,  'kontrak' => '2027-05-06'],
        ['nama' => 'Alviona Zunka Prasetyo', 'email' => 'alviona@sweettreats.id',   'role' => 'Assistant Dentist', 'cabang' => 'Pamulang',  'aturan' => 'FO-Asdent', 'jatah' => 12.0, 'sesuai' => 10.0, 'kontrak' => '2026-10-21'],
        ['nama' => 'Mutiara Nurul Fajri',    'email' => 'mutiara@sweettreats.id',   'role' => 'Front Office',      'cabang' => 'Pamulang',  'aturan' => 'FO-Asdent', 'jatah' => 12.0, 'sesuai' => 5.0,  'kontrak' => '2026-11-07'],
        // Dua orang di bawah cabang ASALNYA belum digelar — mereka ikut karena
        // sedang DITEMPATKAN di Cinere/Pamulang. Cabang asal TIDAK diubah
        // (identitas roster); penempatan diurus baris rolling, lihat --rolling.
        ['nama' => 'Ramdan Ramdhani',        'email' => 'ramdan@sweettreats.id',    'role' => 'General Services',  'cabang' => 'Kalideres', 'aturan' => 'GS',        'jatah' => 12.0, 'sesuai' => 0.0,  'kontrak' => '2027-07-24'],
        ['nama' => 'Verini Selvia Naskah',   'email' => 'verini@sweettreats.id',    'role' => 'Front Office',      'cabang' => 'Sunter',    'aturan' => 'FO-Asdent', 'jatah' => 0.0,  'sesuai' => 0.0,  'kontrak' => '2026-11-07'],

        // ── SUNTER, 13 Agu 2026 ────────────────────────────────────────────
        // Verini (di atas) sudah lebih dulu digelar karena penempatannya di
        // Cinere; keempat orang ini melengkapi rosternya.
        //
        // Hanya Putri yang berjatah. Ketiga lainnya bersaldo 0 karena BELUM
        // BERHAK — kontrak pertama belum genap sekali perpanjangan — sama
        // seperti Verini, bukan karena cutinya habis. Karena itu jatahnya
        // ditulis 0 tanpa baris penyesuaian; menulis 12 lalu memotong 12 akan
        // terbaca seolah mereka sudah memakai seluruh haknya.
        //
        // ⚠️ Kontrak Rokhmatun HABIS 11 Agu 2026 — sudah lewat saat baris ini
        // ditulis. Sengaja dimasukkan apa adanya: akunnya tetap perlu ada, dan
        // tanggal yang jujur membuat perpanjangannya terlihat, bukan tenggelam.
        ['nama' => 'Putri Syafira',            'email' => 'putri@sweettreats.id',     'role' => 'Assistant Dentist', 'cabang' => 'Sunter',    'aturan' => 'FO-Asdent', 'jatah' => 12.0, 'sesuai' => 5.0,  'kontrak' => '2027-01-01'],
        ['nama' => 'Rokhmatun',                'email' => 'rokhmatun@sweettreats.id', 'role' => 'Assistant Dentist', 'cabang' => 'Sunter',    'aturan' => 'FO-Asdent', 'jatah' => 0.0,  'sesuai' => 0.0,  'kontrak' => '2026-08-11'],
        ['nama' => 'Tito Muhammad Taqiyuddin', 'email' => 'tito@sweettreats.id',      'role' => 'General Services',  'cabang' => 'Sunter',    'aturan' => 'GS',        'jatah' => 0.0,  'sesuai' => 0.0,  'kontrak' => '2027-05-11'],
        ['nama' => 'Melani Safrida',           'email' => 'melani@sweettreats.id',    'role' => 'Front Office',      'cabang' => 'Sunter',    'aturan' => 'FO-Asdent', 'jatah' => 0.0,  'sesuai' => 0.0,  'kontrak' => '2027-05-11'],
    ];

    /** Penempatan sementara: email => cabang tempat dia bekerja sekarang. */
    private const ROLLING = [
        'ramdan@sweettreats.id' => 'Pamulang',
        'verini@sweettreats.id' => 'Cinere',
    ];

    public function handle(): int
    {
        $kering = (bool) $this->option("dry");
        $sandiTetap = trim((string) $this->option("sandi")) ?: null;
        $hariIni = Carbon::today()->toDateString();

        $peranId  = DB::table('group_path')->pluck('id', 'nama');
        $cabangId = RawBranch::pluck('id', 'name');
        $aturanId = AttWorkRule::pluck('id', 'nama');

        $sandi = [];
        $dibuat = 0; $dilewati = 0;

        foreach (self::ORANG as $o) {
            $ada = Users::where('email', $o['email'])->first();
            if ($ada) {
                // Dengan --sandi, orang yang sudah ada TIDAK cuma dilewati:
                // sandinya disetel ulang. Gunanya saat akun terlanjur dibuat
                // dengan sandi acak lalu diputuskan pakai sandi seragam —
                // tanpa ini, satu-satunya jalan adalah menghapus lalu membuat
                // ulang akun, dan itu ikut membuang riwayat absennya.
                //
                // ⚠️ TAPI JANGAN SENTUH ORANG YANG SUDAH GANTI SANDI SENDIRI.
                // Perintah ini idempoten dan dijalankan ulang tiap gelombang
                // cabang, jadi daftar ORANG makin panjang sementara isinya
                // makin banyak yang sudah aktif. Tanpa gerbang ini, menggelar
                // Kalideres akan menghapus sandi pribadi seluruh Cinere,
                // Pamulang, dan Sunter — mereka terkunci di luar tanpa tahu
                // kenapa, dan satu-satunya petunjuk cuma ada di layar orang
                // yang menjalankan perintahnya.
                //
                // `must_change_password` dipakai sbg penanda "sandinya masih
                // sandi bagian, belum jadi miliknya": bernilai 1 sejak akun
                // lahir dan baru jadi 0 setelah dia benar-benar menggantinya.
                //
                // (Nyaris kejadian 13 Agu: Alviona, Mutiara, dan Ramdan sudah
                // ganti sandi beberapa jam sebelum gelombang Sunter dijalankan.)
                $sudahMilikDia = (int) $ada->must_change_password === 0;

                if ($sandiTetap && $sudahMilikDia) {
                    $this->line("  – {$o['nama']}: sudah ganti sandi sendiri, TIDAK disentuh");
                } elseif ($sandiTetap && ! $kering) {
                    $ada->update([
                        'password'             => password_hash($sandiTetap, PASSWORD_DEFAULT),
                        'must_change_password' => 1,
                    ]);
                    AuditLog::record('update', 'Gelar cabang — setel ulang sandi sementara: ' . $ada->nama, $ada);
                    $this->line("  ~ {$o['nama']}: akun sudah ada, sandi disetel ulang");
                } elseif ($sandiTetap) {
                    $this->line("  ~ {$o['nama']}: akun sudah ada, sandi AKAN disetel ulang");
                } else {
                    $this->line("  – {$o['nama']}: sudah punya akun, dilewati");
                }
                $dilewati++;
                continue;
            }

            // Rujukan wajib ada. Lebih baik berhenti dengan pesan jelas daripada
            // membuat akun cacat yang baru ketahuan saat orangnya gagal login.
            foreach ([['role', $peranId], ['cabang', $cabangId], ['aturan', $aturanId]] as [$k, $peta]) {
                if (! isset($peta[$o[$k]])) {
                    $this->error("  ! {$o['nama']}: {$k} '{$o[$k]}' tidak ada di lingkungan ini — dihentikan.");
                    return self::FAILURE;
                }
            }

            $temp = $sandiTetap ?: $this->sandiSementara();
            $sandi[$o['nama']] = $temp;

            if ($kering) {
                $this->line("  + {$o['nama']} · {$o['role']} · {$o['cabang']} · aturan {$o['aturan']} · sisa " . ($o['jatah'] - $o['sesuai']));
                $dibuat++;
                continue;
            }

            DB::transaction(function () use ($o, $temp, $peranId, $cabangId, $aturanId, $hariIni) {
                $user = Users::create([
                    'nama'                 => $o['nama'],
                    'email'                => $o['email'],
                    'password'             => password_hash($temp, PASSWORD_DEFAULT),
                    'group_id'             => $peranId[$o['role']],
                    'raw_branch_id'        => $cabangId[$o['cabang']],
                    'status'               => 1,
                    'must_change_password' => 1,
                    'created_at'           => now(),
                ]);

                // Posisi `home` — dipakai checklist & cabang efektif.
                try { UserPosition::syncFromUser($user, null); } catch (\Throwable $e) { report($e); }

                // Aturan kerja EKSPLISIT (role belum punya bawaan).
                AttUserRule::create([
                    'user_id'        => $user->id,
                    'rule_id'        => $aturanId[$o['aturan']],
                    'effective_from' => $hariIni,
                ]);

                // Periode cuti + penyesuaian saldo.
                $periodeId = DB::table('att_cuti_periode')->insertGetId([
                    'user_id'       => $user->id,
                    'saldo_awal'    => $o['jatah'],
                    'kontrak_akhir' => $o['kontrak'],
                    'kontrak_mulai' => Carbon::parse($o['kontrak'])->subYear()->toDateString(),
                    'berjalan'      => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                if ($o['sesuai'] > 0) {
                    DB::table('att_cuti_mutasi')->insert([
                        'periode_id' => $periodeId,
                        'user_id'    => $user->id,
                        'tanggal'    => $hariIni,
                        'hari'       => $o['sesuai'],
                        'jenis'      => 'koreksi',
                        'sumber'     => 'manual',
                        'keterangan' => 'Penyesuaian saldo awal: selisih dari jatah 12 saat sistem mulai mencatat (sumber: sheet HR 12 Agu 2026)',
                        'created_at' => now(),
                    ]);
                }

                AuditLog::record('create', 'Gelar cabang — buat akun: ' . $user->nama, $user);
            });

            $this->info("  + {$o['nama']} dibuat");
            $dibuat++;
        }

        // ── Penempatan (rolling) ──
        foreach (self::ROLLING as $email => $namaCabang) {
            $u = Users::where('email', $email)->first();
            if (! $u) { $this->warn("  ? rolling {$email}: akunnya tak ada"); continue; }
            if (! isset($cabangId[$namaCabang])) { $this->warn("  ? cabang {$namaCabang} tak ada"); continue; }

            $sudah = UserPosition::where('user_id', $u->id)->where('kind', 'rolling')
                ->where('raw_branch_id', $cabangId[$namaCabang])
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $hariIni))
                ->exists();
            if ($sudah) { $this->line("  – rolling {$u->nama} → {$namaCabang}: sudah ada"); continue; }
            if ($kering) { $this->line("  + rolling {$u->nama} → {$namaCabang}"); continue; }

            $home = UserPosition::where('user_id', $u->id)->where('kind', 'home')->first();
            // ⚠️ is_primary=1 dan confirmed_at WAJIB terisi. scopeActiveOn()
            // menyaring `is_primary=1 AND confirmed_at IS NOT NULL` — baris
            // rolling tanpa keduanya tersaring keluar diam-diam: barisnya ADA
            // di tabel, tapi cabang efektif tetap cabang asal. Tak ada galat,
            // tak ada peringatan. (Kejadian nyata saat deploy 13 Agu.)
            // "primary" di sini berarti "posisi yang berlaku", bukan "yang asal";
            // rolling menang atas home lewat urutan di scope-nya, bukan flag ini.
            UserPosition::create([
                'user_id'        => $u->id,
                'division_id'    => $home->division_id ?? null,
                'raw_branch_id'  => $cabangId[$namaCabang],
                'is_primary'     => 1,
                'kind'           => 'rolling',
                'effective_from' => $hariIni,
                'confirmed_at'   => now(),
                'confirmed_by'   => $home->confirmed_by ?? null,
                'note'           => 'Penempatan sementara — penggelaran cabang ' . $namaCabang,
            ]);
            $this->info("  + rolling {$u->nama} → {$namaCabang}");
        }

        $this->newLine();
        $this->line($kering ? "RENCANA: {$dibuat} akun dibuat, {$dilewati} dilewati (tak ada yang ditulis)"
                            : "SELESAI: {$dibuat} akun dibuat, {$dilewati} dilewati");

        if (! $kering && $sandi) {
            $this->newLine();
            $this->warn('KATA SANDI SEMENTARA — catat sekarang, tak bisa dilihat lagi.');
            $this->warn('Semuanya wajib diganti saat login pertama.');
            foreach ($sandi as $nama => $s) $this->line(sprintf('  %-26s %s', $nama, $s));
        }

        return self::SUCCESS;
    }

    /** Sama persis dengan UserManagement::generateTempPassword(). */
    private function sandiSementara(): string
    {
        $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < 9; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        return substr($s, 0, 3) . '-' . substr($s, 3, 3) . '-' . substr($s, 6, 3);
    }
}
