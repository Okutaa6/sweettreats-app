<?php

namespace App\Services\ItTracker;

use App\Models\ItForm;
use App\Models\ItFr;
use App\Models\ItTemuan;
use App\Models\ItTestCase;
use App\Models\ItTicket;
use App\Models\ItTicketFase;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alur tiket IT Request Tracker — Fase 1 (docs/rencana/rencana-it-tracker.md §14).
 *
 * Aturan gerbang & pemanasan hidup di PhaseEngine (terjemahan it-store.js);
 * kelas ini yang menyentuh DB: nomor tiket, riwayat fase, dan siapa boleh apa.
 */
class ItTrackerService
{
    /** Triase & kendali penuh papan. Rencana §2: hanya Ekky (Head IT). */
    public const ROLE_HEAD_IT = ['Head IT', 'Super Admin'];

    /** IT pelaksana (Reza): pindah status tiket yang dia PIC, di DEV/UJI saja. */
    public const ROLE_IT_DEV = ['IT'];

    /** Melihat semua tiket; ACC Go-Live (Fase 2). Konsultan = Executive (keputusan Robi). */
    public const ROLE_PENGAWAS = ['Executive', 'Consultant', 'Konsultan IT', 'Konsultan Finance', 'Konsultan Marketing', 'Admin General', 'Admin Backup', 'Admin'];

    /**
     * Boleh MENTRIASE selain Head IT — keputusan Ekky 9 Sep 2026.
     *
     * Sengaja BUKAN dengan memasukkan mereka ke ROLE_HEAD_IT: paraf Business
     * Owner (bolehParafBo) eksklusif milik peran `pengawas`, dan merekalah
     * pemegang utamanya. Menaikkan Executive ke head_it justru MENCABUT hak
     * parafnya — gerbang ANALISIS lalu tak bisa dilewati siapa pun — dan orang
     * yang sama akan memegang tombol MELEWATI paraf sekaligus MEMBERI paraf.
     * Karena itu peran() tetap 'pengawas'; yang terbuka hanya satu predikat.
     *
     * Yang ikut terbuka: aksi triase (jenjang + bobot + prioritas IT), modal
     * triase, pilihan modul di dalamnya. Yang TETAP head_it: pelewatan paraf,
     * penetapan PIC, formulir analisis, dependensi, evaluator, FRM-IT-02,
     * tunda/tolak, buka-lagi.
     */
    public const ROLE_TRIASE_TAMBAHAN = ['Executive', 'Konsultan IT'];

    /**
     * WAKIL Head IT — keputusan Ekky 10 Sep 2026, alasan kelangsungan kerja.
     *
     * Head IT tidak selalu ada. Kalau ia sakit seminggu, yang berhenti BUKAN
     * pengembangan (IT pelaksana bisa menarik tiketnya sendiri dari Antre dan
     * mendorongnya sampai Go Live), melainkan PENILAIAN Head IT: menulis
     * analisis FRM-IT-02, tunda/tolak, menunjuk PIC, menaut modul, dependensi,
     * melewati paraf Business Owner. Tanpa itu semua tiket M/L berhenti tepat
     * di pintu Analisis.
     *
     * Konsultan IT memegang seluruh paket itu. Ia AMAN memegangnya karena ia
     * bukan Business Owner: tak ada kekuasaan yang menumpuk pada satu orang.
     * ⛔ Executive TIDAK ikut (keputusan Ekky yang sama): kalau Business Owner
     * menulis analisis lalu memaraf analisisnya sendiri, parafnya berhenti
     * berarti apa-apa dan sistem kehilangan satu-satunya pemeriksaan silangnya.
     * Executive tetap memegang penilaian awal, paraf BO, dan ACC Go Live.
     *
     * Seperti bolehTriase(), ini predikat TERPISAH: peran() tidak disentuh, jadi
     * Konsultan IT tetap 'pengawas' dan tidak kehilangan hak paraf BO-nya.
     */
    public const ROLE_WAKIL_HEAD_IT = ['Konsultan IT'];

    public const FASE_PARKIR = ['DITUNDA', 'DITOLAK'];

    /**
     * Nama jenjang yang DIBACA orang. Huruf S/M/L cuma singkatan internal IT;
     * di layar ia mubazir sesudah papan dibagi per jenis permintaan, dan "?"
     * untuk yang belum ditriase justru menyembunyikan satu-satunya keadaan yang
     * menuntut tindakan. Satu tempat supaya papan, parkiran, dan detail sepakat.
     */
    public const JENJANG_LABEL = ['S' => 'Support', 'M' => 'Fitur', 'L' => 'Modul'];

    public static function jenjangLabel(?string $j): string
    {
        return self::JENJANG_LABEL[$j] ?? 'belum dinilai';
    }

    /**
     * Unit usaha yang dilayani. SATU papan untuk keduanya (keputusan Ekky:
     * "task RB digabung, warnanya dibedain") — ini penanda, bukan pemisah.
     */
    public const UNIT = ['sweettreats' => 'Sweet Treats', 'rblas' => 'RB Las'];

    public static function unitLabel(?string $u): string
    {
        return self::UNIT[$u] ?? self::UNIT['sweettreats'];
    }

    /**
     * Catatan "sudah sampai mana" — ditulis IT kapan saja di fase aktif, tampil
     * di kartu papan. Siapa & kapan ikut disimpan: catatan kemajuan tanpa
     * tanggal berbohong pelan-pelan (kelihatan terurus padahal tiga minggu basi).
     * Kosong = catatan dihapus, bukan disimpan sebagai string kosong.
     */
    public function setKemajuan(ItTicket $t, ?string $txt, string $userId): void
    {
        if (! isset(PhaseEngine::RANK[$t->fase])) {
            throw new DomainException('Tiket ' . strtolower($t->fase) . ' tidak menerima catatan kemajuan — buka lagi dulu kalau memang masih berjalan.');
        }

        $txt = mb_substr(self::teksBersih(trim(preg_replace('/\s+/u', ' ', (string) $txt))), 0, 300);
        $t->kemajuan      = $txt !== '' ? $txt : null;
        $t->kemajuan_at   = $txt !== '' ? now() : null;
        $t->kemajuan_oleh = $txt !== '' ? $userId : null;
        $t->save();
    }

    /**
     * Lampiran FRM-IT-01 (rencana §7 bagian B: screenshot · contoh laporan ·
     * alur proses · dokumen lain). Batas = ROP (5 MB) + dokumen kantor, karena
     * "contoh laporan" hampir selalu Excel/Word. Daftar ini juga yang DISEBUT
     * di pesan penolakan (pola P3) — jangan dipisah dari aturan validasinya.
     */
    public const LAMPIRAN_MIMES  = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'xlsx', 'xls', 'docx', 'doc', 'csv'];
    public const LAMPIRAN_MAX_KB = 5120;
    public const LAMPIRAN_MAKS   = 8;

    public static function lampiranDiterima(): string
    {
        return 'gambar (JPG/PNG/WEBP), PDF, Excel (XLSX/XLS/CSV), atau Word (DOCX/DOC) — maksimal 5 MB per berkas';
    }

    /**
     * Tempelkan berkas ke FRM-IT-01. Metadata cukup di payload (JSON) — tak
     * perlu tabel baru. Berkas disimpan lewat RopAttachment (pola terbukti),
     * folder dasar uploads/it-tracker supaya tak bercampur dengan nota ROP.
     *
     * @param  \Illuminate\Http\UploadedFile[] $berkas  sudah tervalidasi mimes/max
     * @return int jumlah yang ditempelkan
     */
    public function tambahLampiran(ItTicket $t, array $berkas, string $userId, string $kode = 'FRM-IT-01'): int
    {
        $berkas = array_values(array_filter($berkas));
        if (! $berkas) return 0;
        if ($t->fase === 'DITOLAK') {
            throw new DomainException('Tiket yang ditolak tidak menerima lampiran.');
        }
        // FRM-IT-01 = bukti masalah dari pemohon · FRM-IT-02 = bahan rancangan
        // (mockup, alur, contoh keluaran) dari IT. Folder & batasnya sama.
        if (! in_array($kode, ['FRM-IT-01', 'FRM-IT-02'], true)) {
            throw new DomainException('Formulir ' . $kode . ' tidak menerima lampiran.');
        }

        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => $kode]);
        $p    = $form->payload ?? [];
        $ada  = $p['lampiran'] ?? [];
        if (count($ada) + count($berkas) > self::LAMPIRAN_MAKS) {
            throw new DomainException('Maksimal ' . self::LAMPIRAN_MAKS . ' lampiran per formulir (sudah ' . count($ada) . ').');
        }

        $penyimpan = app(\App\Services\RopAttachment::class);
        foreach ($berkas as $f) {
            // metadata DIBACA SEBELUM dipindah — sesudah move() berkas sementaranya
            // lenyap dan getSize() melempar (ditangkap uji, 4 Sep)
            $nama   = mb_substr(self::teksBersih($f->getClientOriginalName() ?: 'lampiran'), 0, 160);
            $mime   = (string) ($f->getClientMimeType() ?: 'application/octet-stream');
            $ukuran = (int) $f->getSize();
            $path   = $penyimpan->simpanBerkas($f, strtolower(str_replace('FRM-IT-', 'frm', $kode)), 'uploads/it-tracker');
            $ada[]  = [
                'path'   => $path,
                'nama'   => $nama,
                'mime'   => $mime,
                'ukuran' => $ukuran,
                'oleh'   => $userId,
                'at'     => now()->toIso8601String(),
            ];
        }
        $p['lampiran'] = $ada;
        $form->payload = $p;
        $form->save();

        return count($berkas);
    }

    /**
     * Rapikan teks bebas sebelum masuk payload JSON.
     *
     * `it_form.payload` di-cast array → json_encode, dan json_encode MENOLAK
     * byte non-UTF-8 dengan melempar — hasilnya 500 telanjang, bukan penolakan
     * sopan. Browser selalu mengirim UTF-8, tapi tempelan dari sumber lawas,
     * proksi, atau klien non-browser bisa membawa byte rusak. Byte cacatnya
     * dibuang di sini supaya kegagalan tak pernah berbentuk halaman error.
     */
    public static function teksBersih(?string $s): string
    {
        $s = (string) $s;

        return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }

    /**
     * Peran tracker dari nama role sesi: head_it | it_dev | pengawas | head.
     * Role lain yang lolos grant menu diperlakukan sebagai head (pemohon).
     */
    public static function peran(?string $groupName): string
    {
        if (in_array($groupName, self::ROLE_HEAD_IT, true))  return 'head_it';
        if (in_array($groupName, self::ROLE_IT_DEV, true))   return 'it_dev';
        if (in_array($groupName, self::ROLE_PENGAWAS, true)) return 'pengawas';
        return 'head';
    }

    /** Siapa mengerjakan pekerjaan Head IT: Head IT sendiri, atau wakilnya (lihat ROLE_WAKIL_HEAD_IT). */
    public static function bolehKerjaHeadIt(?string $groupName): bool
    {
        return self::peran($groupName) === 'head_it'
            || in_array($groupName, self::ROLE_WAKIL_HEAD_IT, true);
    }

    /** Siapa boleh mentriase: Head IT, wakilnya, atau role di ROLE_TRIASE_TAMBAHAN — tanpa mengubah peran(). */
    public static function bolehTriase(?string $groupName): bool
    {
        return self::bolehKerjaHeadIt($groupName)
            || in_array($groupName, self::ROLE_TRIASE_TAMBAHAN, true);
    }

    /**
     * Nomor tiket IT-{YYYY}-{NNN} — counter per tahun, pola atomik yang sama
     * dengan RopService::urutBaru (LAST_INSERT_ID, bukan MAX+1 yang balapan).
     */
    public function nomorBaru(?\DateTimeInterface $saat = null): string
    {
        $saat    = $saat ?: now();
        $periode = $saat->format('Y');

        DB::statement(
            'INSERT INTO it_counter (periode, last_no, updated_at)
             VALUES (?, LAST_INSERT_ID(1), NOW())
             ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1), updated_at = NOW()',
            [$periode]
        );

        $row  = DB::selectOne('SELECT LAST_INSERT_ID() AS no');
        $urut = (int) ($row->no ?? 0);

        if ($urut < 1) {
            $urut = (int) DB::table('it_counter')->where('periode', $periode)->value('last_no') ?: 1;
        }

        return sprintf('IT-%s-%03d', $periode, $urut);
    }

    /**
     * Buat tiket baru.
     *  - permintaan → TRIASE (pemohon terekam, prioritas pemohon wajib)
     *  - inisiatif  → ANTRE  (IT mengisi jenjang/bobot/prioritas sendiri)
     *  - insiden    → DEV    (masuk langsung, formulir menyusul — rencana §3)
     */
    public function buat(array $d, string $userId): ItTicket
    {
        $sumber = $d['sumber'] ?? 'permintaan';

        // Tiket yang LAHIR dari catatan UAT / RTL evaluasi (spawnTicket r2):
        // selalu lahir di TRIASE, pemohon sama dengan induknya, tanpa jenjang —
        // apa pun sumbernya. Satu jalan untuk dua asal (rencana §14.13).
        $lahir = ! empty($d['_lahir_dari_id']);

        $fase = $lahir ? 'TRIASE'
            : (['permintaan' => 'TRIASE', 'inisiatif' => 'ANTRE', 'insiden' => 'DEV'][$sumber]
                ?? throw new DomainException('Sumber tiket tidak dikenal.'));

        if (! $lahir && $sumber !== 'permintaan' && empty($d['jenjang'])) {
            throw new DomainException('Inisiatif/insiden IT wajib langsung diberi jenjang (S/M/L).');
        }

        return DB::transaction(function () use ($d, $sumber, $fase, $userId, $lahir) {
            $now = now();

            $pemohon = $lahir
                ? ($d['pemohon_user_id'] ?? null)
                : ($sumber === 'permintaan' ? $userId : null);

            $ticket = ItTicket::create([
                'nomor'             => $this->nomorBaru($now),
                'judul'             => self::teksBersih($d['judul']),
                'sumber'            => $sumber,
                'pemohon_user_id'   => $pemohon,
                'raw_branch_id'     => $d['raw_branch_id'] ?? null,
                'unit'              => isset(self::UNIT[$d['unit'] ?? '']) ? $d['unit'] : 'sweettreats',
                'jenis_permintaan'  => $d['jenis_permintaan'] ?? null,
                'jenjang'           => ($lahir || $sumber === 'permintaan') ? null : ($d['jenjang'] ?? null),
                'bobot'             => ($lahir || $sumber === 'permintaan') ? null : ($d['bobot'] ?? null),
                'prioritas_pemohon' => $lahir ? ($pemohon ? 'Sedang' : null)
                                        : ($sumber === 'permintaan' ? ($d['prioritas_pemohon'] ?? null) : null),
                'prioritas_it'      => ($lahir || $sumber === 'permintaan') ? null : ($d['prioritas_it'] ?? null),
                'pic_user_id'       => ($lahir || $sumber === 'permintaan') ? null : $userId,
                'fase'              => $fase,
                'fase_since'        => $now,
                'induk_id'          => $d['induk_id'] ?? null,
                'lahir_dari_id'     => $d['_lahir_dari_id'] ?? null,
                'lahir_via'         => $d['_lahir_via'] ?? null,
            ]);

            ItTicketFase::create([
                'ticket_id'      => $ticket->id,
                'fase'           => $fase,
                'masuk_at'       => $now,
                'oleh_user_id'   => $userId,
                'pihak_menunggu' => self::pihakMenunggu($fase),
            ]);

            // FRM-IT-01 §B — detail kebutuhan yang diketik pemohon. Identitas
            // (§A) TIDAK disimpan di sini: diambil dari akun saat ekspor.
            if ($sumber === 'permintaan' || ! empty($d['latar'])) {
                ItForm::create([
                    'ticket_id' => $ticket->id,
                    'kode'      => 'FRM-IT-01',
                    'payload'   => [
                        'jenis'     => $d['jenis_permintaan'] ?? null,
                        'target'    => self::teksBersih($d['target'] ?? '—'),
                        'latar'     => self::teksBersih($d['latar'] ?? ''),
                        'kebutuhan' => self::teksBersih($d['kebutuhan'] ?? ''),
                        'terdampak' => self::teksBersih($d['terdampak'] ?? '—'),
                        'review'    => null,
                    ],
                ]);
            }

            return $ticket;
        });
    }

    /**
     * Triase (Head IT): jenjang + bobot + prioritas IT (+ PIC opsional) → ANTRE.
     * PIC di sini penting untuk alur dua orang: tanpa penunjukan, orang pertama
     * yang menarik tiket otomatis jadi PIC — semua tiket jadi milik Ekky dan
     * Reza tak pernah bisa menggerakkan apa pun (keputusan rencana §11 no. 8).
     */
    /**
     * $keFase: tujuan sesudah dinilai. Bawaannya ANTRE — "sudah dinilai, belum ada
     * yang memegang". Penilai yang sekaligus akan menganalisis sendiri boleh
     * menyebut 'ANALISIS' supaya tiket tak singgah di Antre selama nol detik;
     * riwayat fase mencatat langkahnya apa adanya, tanpa baris kosong yang
     * masuk-keluar di detik yang sama. Pagarnya di controller (siapa & jenjang apa).
     */
    public function triase(ItTicket $t, string $jenjang, int $bobot, string $prioritasIt, string $userId, ?string $picUserId = null, ?string $keFase = null): void
    {
        if ($t->fase !== 'TRIASE') {
            throw new DomainException('Tiket ini tidak sedang menunggu penilaian awal.');
        }

        DB::transaction(function () use ($t, $jenjang, $bobot, $prioritasIt, $userId, $picUserId, $keFase) {
            $t->jenjang      = $jenjang;
            $t->bobot        = $bobot;
            $t->prioritas_it = $prioritasIt;
            if ($picUserId) {
                $t->pic_user_id = $picUserId;
            }
            $this->pindahFase($t, $keFase ?: 'ANTRE', $userId);
        });
    }

    /** Tunda / tolak — wajib beralasan; ditunda tetap tampil di parkiran papan. */
    public function parkir(ItTicket $t, string $ke, string $alasan, string $userId): void
    {
        if (! in_array($ke, self::FASE_PARKIR, true)) {
            throw new DomainException('Tujuan parkir tidak dikenal.');
        }
        // Hanya tiket AKTIF yang bisa diparkir — tanpa pagar ini, POST langsung
        // bisa mengubah DITOLAK jadi DITUNDA (alasan penolakan tertimpa) lalu
        // membangkitkannya lewat buka-lagi, atau memarkir tiket SELESAI.
        if (! isset(PhaseEngine::RANK[$t->fase])) {
            throw new DomainException('Tiket ' . strtolower($t->fase) . ' tidak bisa ditunda/ditolak lagi.');
        }
        if (trim($alasan) === '') {
            throw new DomainException('Alasan wajib diisi — ditunda/ditolak tanpa alasan tidak bisa dipertanggungjawabkan.');
        }

        DB::transaction(function () use ($t, $ke, $alasan, $userId) {
            $t->alasan_parkir = self::teksBersih(trim($alasan));
            $this->pindahFase($t, $ke, $userId);
            \App\Models\AuditLog::record('it-tracker.' . strtolower($ke),
                $t->nomor . ' — ' . trim($alasan), $t);
        });
    }

    /** Buka lagi tiket ditunda → ANTRE (atau TRIASE kalau belum pernah ditriase). */
    public function bukaLagi(ItTicket $t, string $userId): string
    {
        if ($t->fase !== 'DITUNDA') {
            throw new DomainException('Hanya tiket ditunda yang bisa dibuka lagi.');
        }

        $ke = $t->jenjang ? 'ANTRE' : 'TRIASE';
        DB::transaction(function () use ($t, $ke, $userId) {
            // Alasan tunda dihapus dari tiket aktif, tapi JANGAN hilang begitu
            // saja — jejaknya direkam ke audit_log ("kenapa dulu ditunda").
            $alasanLama = $t->alasan_parkir;
            $t->alasan_parkir = null;
            $this->pindahFase($t, $ke, $userId);
            \App\Models\AuditLog::record('it-tracker.buka-lagi',
                $t->nomor . ' dibuka lagi → ' . $ke . '. Alasan tunda sebelumnya: ' . ($alasanLama ?: '—'), $t);
        });

        return $ke;
    }

    /**
     * Majukan tiket lewat gerbangnya. Kalau gerbang belum terbuka, lempar
     * DomainException yang MENYEBUT field yang kurang (pola P3 — tombol mati
     * harus bilang kenapa), bukan diam-diam gagal.
     */
    public function majukan(ItTicket $t, string $userId): string
    {
        // pathFor(null) jatuh ke jalur L, jadi gateInfo pada ANTRE tanpa
        // jenjang akan menawarkan "Tarik → Analisis" — itu MEMOTONG triase.
        // Keadaan ini tak terjangkau lewat UI hari ini, tapi pagari di sini
        // supaya jalur kode masa depan tak bisa lewat.
        if ($t->fase === 'ANTRE' && ! $t->jenjang) {
            throw new DomainException('Tiket ini belum dinilai — tetapkan jenjang dulu.');
        }

        $g = PhaseEngine::gateInfo($this->viewTicket($t));

        if (! $g || ($g['kind'] ?? '') !== 'advance') {
            throw new DomainException('Tiket ini tidak punya gerbang maju dari fase sekarang.');
        }
        if (empty($g['ok'])) {
            throw new DomainException('Gerbang belum terbuka. Masih perlu: ' . implode(' · ', $g['missing']) . '.');
        }

        DB::transaction(function () use ($t, $g, $userId) {
            if (! $t->pic_user_id) {
                $t->pic_user_id = $userId;
            }
            $this->pindahFase($t, $g['next'], $userId);
        });

        return $g['next'];
    }

    /** Keputusan UAT oleh pemohon: setuju → GOLIVE, perbaikan → kembali DEV. */
    public function uatPutuskan(ItTicket $t, string $keputusan, string $userId): string
    {
        if ($t->fase !== 'UAT') {
            throw new DomainException('Tiket ini tidak sedang menunggu UAT.');
        }
        if (! in_array($keputusan, ['setuju', 'catatan', 'perbaikan'], true)) {
            throw new DomainException('Keputusan UAT tidak dikenal.');
        }

        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-05']);
        $p    = $form->payload ?? [];

        if (in_array($p['keputusan'] ?? null, ['setuju', 'catatan'], true)) {
            throw new DomainException('UAT tiket ini sudah diputuskan.');
        }

        // "Disetujui dengan Catatan" hanya bermakna kalau memang ada catatan —
        // dari baris "Tidak" atau Masukan End User (r2: tombolnya mati tanpa itu).
        $catatan = [];
        foreach (($p['skenario'] ?? []) as $s) {
            if (! empty($s['catatan']) && trim($s['catatan']) !== '') $catatan[] = trim($s['catatan']);
        }
        if (! empty($p['masukan']) && trim($p['masukan']) !== '') $catatan[] = trim($p['masukan']);

        if ($keputusan === 'catatan' && ! $catatan) {
            throw new DomainException('Tulis dulu catatan di baris "Tidak" atau Masukan End User sebelum "Disetujui dengan Catatan".');
        }

        $ke = $keputusan === 'perbaikan' ? 'DEV' : 'GOLIVE';

        DB::transaction(function () use ($t, $form, $p, $keputusan, $ke, $userId, $catatan) {
            $p['keputusan'] = $keputusan;
            $p['oleh']      = $userId;
            $p['tanggal']   = now()->toIso8601String();

            if ($keputusan === 'catatan') {
                // Catatan → daftar tindak lanjut di tiket (BUKAN tiket otomatis:
                // itu membanjiri papan). Tombol "Jadikan tiket" per butir.
                $ada = $p['tindakLanjut'] ?? [];
                foreach ($catatan as $txt) {
                    $ada[] = ['id' => sprintf('TL-%02d', count($ada) + 1), 'txt' => $txt, 'via' => 'UAT', 'ticketId' => null];
                }
                $p['tindakLanjut'] = $ada;
            }

            if ($keputusan === 'perbaikan') {
                // Putaran UAT berikutnya mulai BERSIH: jawaban lama diarsipkan
                // (tetap terbaca sebagai riwayat), bukan pra-terisi — "Tidak"
                // putaran lalu bukan jawaban untuk kode yang sudah diperbaiki.
                $p['putaranLalu'][] = [
                    'tanggal'  => $p['tanggal'],
                    'oleh'     => $userId,
                    'skenario' => array_map(fn ($s) => ['proses' => $s['proses'] ?? '', 'sesuai' => $s['sesuai'] ?? null, 'catatan' => $s['catatan'] ?? ''], $p['skenario'] ?? []),
                    'masukan'  => $p['masukan'] ?? '',
                ];
                $p['skenario'] = array_map(function ($s) { $s['sesuai'] = null; $s['catatan'] = ''; return $s; }, $p['skenario'] ?? []);
                $p['masukan']  = '';
            }

            $form->payload = $p;
            $form->save();

            if ($keputusan === 'perbaikan') {
                // Kembali ke Development DAN konfirmasi FRM-IT-03 DIRESET — kode
                // yang ditolak pemohon harus dikonfirmasi ulang, bukan sekadar
                // dicatat (rencana §14.14 no. 8).
                ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-03')
                    ->update(['dikonfirmasi_at' => null, 'dikonfirmasi_oleh' => null]);
            }

            $this->pindahFase($t, $ke, $userId);
        });

        return $ke;
    }

    /** Catatan singkat pengerjaan (jenjang S) — pengganti formulir penuh. */
    public function catatanDev(ItTicket $t, string $catatan, string $userId): void
    {
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-03']);
        $form->payload = array_merge($form->payload ?? [], ['note' => self::teksBersih(trim($catatan))]);
        $form->dikonfirmasi_oleh = $userId;
        $form->dikonfirmasi_at   = now();
        $form->save();
    }

    /**
     * Konfirmasi Development Record (jenjang M/L) — paraf Kelas B "Benar, ini
     * yang kukerjakan". Gerbang DEV→UJI/UAT membaca dikonfirmasi_at; di Fase 2
     * layarnya menampilkan branch/commit hasil it:sync-git, di Fase 1 IT
     * mengonfirmasi dengan catatan bebas.
     */
    public function konfirmasiDev(ItTicket $t, ?string $catatan, string $userId): void
    {
        if ($t->fase !== 'DEV') {
            throw new DomainException('Konfirmasi Development Record hanya di fase Development.');
        }

        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-03']);
        if ($catatan !== null && trim($catatan) !== '') {
            $form->payload = array_merge($form->payload ?? [], ['note' => self::teksBersih(trim($catatan))]);
        }
        $form->dikonfirmasi_oleh = $userId;
        $form->dikonfirmasi_at   = now();
        $form->save();
    }


    // ─── FRM-IT-06 Checklist Go Live (Kelas B, Fase 2a) ────────────────────

    /**
     * Baris pra-deploy yang TIDAK bisa diklik — menyala sendiri dari data yang
     * sudah ada (lihat viewTicket).
     *
     * DITURUNKAN dari PhaseEngine::PRA, bukan disalin: kalau kelak ada baris
     * otomatis baru, daftar ini ikut sendiri. Salinan tangan akan menganga
     * diam-diam — layar menandainya "otomatis" sementara layanan tetap
     * mengizinkannya diketik.
     */
    public static function praOtomatis(): array
    {
        return array_column(array_filter(PhaseEngine::PRA, fn ($p) => ! empty($p['auto'])), 'k');
    }

    /**
     * Jawab satu baris checklist pra-deploy: 'ya' atau 'na'.
     * Baris otomatis ditolak — kalau bisa diketik manusia, ia berhenti jadi
     * bukti dan kembali jadi formulir (itu yang mematikan Kelas B).
     */
    public function setPra(ItTicket $t, string $kunci, string $nilai, string $userId): void
    {
        $this->pastikanDiGoLive($t);

        if (in_array($kunci, self::praOtomatis(), true)) {
            // ⛔ SATU pengecualian, dan ia menutup kemacetan permanen:
            // 'File/script siap' menyala dari jejak git. Tiket yang memang tak
            // punya commit ber-nomor (perubahan konfigurasi murni, atau nomor
            // tiket lupa ditulis) TAK AKAN PERNAH bisa lepas dari Go Live —
            // barisnya tak menyala sendiri dan tak boleh diisi tangan.
            // Jadi N-A diizinkan, TAPI hanya kalau sistem memang tak punya apa
            // pun untuk ditunjukkan; 'Ya' tetap haram (mengaku punya bukti yang
            // tak ada persis yang membuat Kelas B berhenti dipercaya).
            $bolehNa = $kunci === 'script'
                && $nilai === 'na'
                && $t->gitRefs()->count() === 0;

            if (! $bolehNa) {
                throw new DomainException($kunci === 'script'
                    ? 'Jejak git sudah ada — baris ini menyala sendiri, tak diisi tangan.'
                    : 'Baris ini menyala sendiri dari data sistem — tidak diisi tangan.');
            }
        }
        if (! in_array($kunci, array_column(PhaseEngine::PRA, 'k'), true)) {
            throw new DomainException('Baris checklist tidak dikenal.');
        }
        if (! in_array($nilai, ['ya', 'na'], true)) {
            throw new DomainException('Jawaban hanya Ya atau N-A.');
        }

        $this->tulisFr06($t, function (array $p) use ($kunci, $nilai) {
            $p['pra'] = array_merge($p['pra'] ?? [], [$kunci => $nilai]);
            return $p;
        }, $userId);
    }

    /** Pemeriksaan pasca Go-Live: satu baris → 'pass'. */
    public function setPasca(ItTicket $t, string $kunci, string $userId): void
    {
        $this->pastikanDiGoLive($t);

        if (! in_array($kunci, array_column(PhaseEngine::PASCA, 'k'), true)) {
            throw new DomainException('Pemeriksaan tidak dikenal.');
        }

        $this->tulisFr06($t, function (array $p) use ($kunci) {
            $p['pasca'] = array_merge($p['pasca'] ?? [], [$kunci => 'pass']);
            return $p;
        }, $userId);
    }

    /** Catatan deployment bebas (FRM-IT-06 §C) — waktu/aktivitas/hasil. */
    public function setCatatanDeploy(ItTicket $t, ?string $catatan, string $userId): void
    {
        $this->pastikanDiGoLive($t);

        $this->tulisFr06($t, function (array $p) use ($catatan) {
            $p['deploy'] = self::teksBersih(trim((string) $catatan));
            return $p;
        }, $userId);
    }

    /**
     * ACC Business Owner — gerbang manusia (rencana §4). Bukan IT: kalau IT
     * bisa meng-ACC dirinya sendiri, paraf ini kehilangan seluruh artinya.
     */
    public function setujuiBusinessOwner(ItTicket $t, string $userId): void
    {
        $this->pastikanDiGoLive($t);

        $this->tulisFr06($t, function (array $p) use ($userId) {
            $p['bo']         = true;
            $p['bo_oleh']    = $userId;
            $p['bo_tanggal'] = now()->toIso8601String();
            return $p;
        }, $userId);
    }

    private function pastikanDiGoLive(ItTicket $t): void
    {
        if ($t->fase !== 'GOLIVE') {
            throw new DomainException('Checklist Go Live hanya diisi saat tiket berada di fase Go Live.');
        }
    }

    /**
     * Tulis payload FRM-IT-06. Kunci otomatis (uat/script) DIBUANG sebelum
     * disimpan: sumbernya tetap data hidup, bukan salinan yang bisa basi.
     */
    private function tulisFr06(ItTicket $t, callable $ubah, string $userId): void
    {
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-06']);
        $p    = $ubah($form->payload ?? []);

        // Nilai TURUNAN ('ya' hasil hitungan) tak boleh mengendap jadi salinan
        // yang bisa basi — sumbernya tetap data hidup di viewTicket.
        // Tapi 'na' pada baris otomatis adalah JAWABAN MANUSIA ("memang tak ada
        // script"), dan itu harus tersimpan; kalau ikut dibuang, tiket tanpa
        // commit macet permanen di Go Live.
        foreach (self::praOtomatis() as $k) {
            if (($p['pra'][$k] ?? null) !== 'na') {
                unset($p['pra'][$k]);
            }
        }

        $form->payload           = $p;
        $form->dikonfirmasi_oleh = $userId;
        $form->save();
    }
    // ═══════════════════════════════════════════════════════════════════════
    // FASE 2b — empat formulir kerja (rencana §14.13 TERKUNCI)
    // ═══════════════════════════════════════════════════════════════════════

    /** Komponen terdampak (FRM-IT-02 §C) — pilihan tetap. */
    public const KOMPONEN = ['Aplikasi', 'Database', 'API', 'Laporan', 'Hak akses'];

    // ─── FRM-IT-02 Analysis & Design (Kelas C — Ekky + paraf 1 Business Owner) ──

    private function pastikanDiAnalisis(ItTicket $t): void
    {
        if ($t->fase !== 'ANALISIS') {
            throw new DomainException('FRM-IT-02 hanya diisi saat tiket berada di fase Analisis.');
        }
    }

    /**
     * Tulis payload FRM-IT-02. Bawaan: MENGHAPUS paraf BO — temuan T2 §14.13:
     * mengubah isi yang sudah diparaf membatalkan parafnya, tanpa audit trail.
     * Pagar integritas, bukan kenyamanan; BO diminta menyetujui ulang yang berubah.
     */
    private function tulisFr02(ItTicket $t, callable $ubah, bool $hapusParaf = true): void
    {
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-02']);
        $lama = $form->payload ?? [];
        $p    = $ubah($lama);
        // Paraf gugur HANYA kalau isi yang dilihat BO benar-benar berubah —
        // menekan "Simpan" komponen/lingkup tanpa mengubah apa pun tak boleh
        // menggugurkan persetujuan (audit 3 Sep). Catatan dapur tak ikut dihitung.
        if ($hapusParaf && self::sidikIsi($lama) !== self::sidikIsi($p)) {
            // pelewatan bertanda (§14.16) gugur bersama paraf — isinya sudah bukan yang diteruskan
            unset($p['paraf'], $p['diteruskan']);
        }
        $form->payload = $p;
        $form->save();
    }

    /** Sidik isi FRM-IT-02 yang DILIHAT Business Owner — tanpa paraf/pelewatan & dapur. */
    private static function sidikIsi(array $p): string
    {
        unset($p['paraf'], $p['diteruskan'], $p['estimasi'], $p['risiko'], $p['lampiran']);
        ksort($p);

        return json_encode($p, JSON_UNESCAPED_UNICODE);
    }

    /** Hapus paraf BO (dipakai saat isi tabel FR berubah). */
    public function hapusParafBo(ItTicket $t): void
    {
        $form = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-02')->first();
        if ($form && (! empty($form->payload['paraf']) || ! empty($form->payload['diteruskan']))) {
            $p = $form->payload;
            unset($p['paraf'], $p['diteruskan']);
            $form->payload = $p;
            $form->save();
        }
    }

    /**
     * Gerbang yang boleh DILEWATI Head IT tanpa paraf BO (§14.16/§14.19).
     * '06' (ACC Go Live) SENGAJA tidak ada — menambahkannya = keputusan Ekky.
     */
    public const GERBANG_BOLEH_DITERUSKAN = ['02'];

    /**
     * Jalan pintas BERTANDA: Head IT meneruskan gerbang tanpa paraf Business
     * Owner. Ini PELEWATAN, bukan paraf — fr02.paraf TIDAK PERNAH diisi dari sini;
     * yang tersimpan terpisah: fr02.diteruskan = {oleh, oleh_id, at, alasan}.
     * Alasan satu baris WAJIB (≥10 karakter) — murah, dan membuat tindakannya
     * disengaja. Tanpa masa tunggu (Ekky menolak friksi itu): kejujuran dijaga
     * penanda yang ikut sampai Go Live & ekspor, bukan penundaan.
     */
    public function teruskanTanpaBo(ItTicket $t, string $gerbang, string $alasan, string $userId, string $nama): void
    {
        if (! in_array($gerbang, self::GERBANG_BOLEH_DITERUSKAN, true)) {
            throw new DomainException('Gerbang ' . $gerbang . ' tidak bisa diteruskan tanpa Business Owner' . ($gerbang === '06' ? ' — ACC Go Live sengaja tetap milik BO.' : '.'));
        }
        $this->pastikanDiAnalisis($t);

        $alasan = mb_substr(self::teksBersih(trim(preg_replace('/\s+/u', ' ', $alasan))), 0, 300);
        if (mb_strlen($alasan) < 10) {
            throw new DomainException('Tulis alasan meneruskan tanpa paraf BO — minimal 10 karakter (mis. "Robi cuti sampai 12 Sep, pemohon menunggu"). Alasan inilah yang tercetak di ekspor.');
        }

        $this->tulisFr02($t, function (array $p) use ($userId, $nama, $alasan) {
            $p['diteruskan'] = ['oleh' => $nama, 'oleh_id' => $userId, 'at' => now()->toIso8601String(), 'alasan' => $alasan];
            return $p;
        }, hapusParaf: false);
    }

    /**
     * Simpan SELURUH tabel FR (ganti-semua, nomor ulang FR-01..). Baris tanpa
     * kebutuhan dibuang (itulah "baris hantu"). Paraf BO dihapus HANYA kalau
     * isinya benar-benar berubah — menyimpan ulang tabel yang sama tak boleh
     * menggugurkan persetujuan.
     */
    public function simpanFr(ItTicket $t, array $baris): int
    {
        $this->pastikanDiAnalisis($t);

        $bersih = [];
        foreach ($baris as $b) {
            $keb = self::teksBersih(trim((string) ($b['kebutuhan'] ?? '')));
            if ($keb === '') continue;
            $prio = in_array($b['prioritas'] ?? '', ['Tinggi', 'Sedang', 'Rendah'], true) ? $b['prioritas'] : 'Sedang';
            $bersih[] = [
                'kebutuhan'         => mb_substr($keb, 0, 500),
                'prioritas'         => $prio,
                'kriteria_diterima' => mb_substr(self::teksBersih(trim((string) ($b['kriteria'] ?? ''))), 0, 1000),
            ];
        }

        DB::transaction(function () use ($t, $bersih) {
            $lama = ItFr::where('ticket_id', $t->id)->orderBy('urutan')->get()
                ->map(fn ($r) => [$r->kebutuhan, $r->prioritas, (string) $r->kriteria_diterima])->all();
            $baru = array_map(fn ($b) => [$b['kebutuhan'], $b['prioritas'], $b['kriteria_diterima']], $bersih);

            ItFr::where('ticket_id', $t->id)->delete();
            foreach ($bersih as $i => $b) {
                ItFr::create(['ticket_id' => $t->id, 'kode' => sprintf('FR-%02d', $i + 1), 'urutan' => $i + 1] + $b);
            }

            if ($lama !== $baru) {
                $this->hapusParafBo($t);
            }
        });

        return count($bersih);
    }

    public function setKomponen(ItTicket $t, array $komponen): void
    {
        $this->pastikanDiAnalisis($t);
        $komponen = array_values(array_intersect(self::KOMPONEN, $komponen));

        $this->tulisFr02($t, function (array $p) use ($komponen) {
            $p['komponen'] = $komponen;
            return $p;
        }, hapusParaf: true);
    }

    /**
     * 02 PENUH — ruang lingkup termasuk / tidak termasuk / desain teknis.
     * ⛔ Hanya jenjang L. M tak pernah melihat field ini; naik ke L lewat
     * triase (fase tak berubah), bukan dengan membuka field L di tiket M.
     */
    public function setLingkupL(ItTicket $t, ?string $lingkup, ?string $luar, ?string $desain): void
    {
        $this->pastikanDiAnalisis($t);
        if ($t->jenjang !== 'L') {
            throw new DomainException('Ruang lingkup & desain teknis (02 penuh) hanya untuk jenjang L. Naikkan jenjangnya dulu lewat penilaian ulang kalau memang perlu.');
        }

        $this->tulisFr02($t, function (array $p) use ($lingkup, $luar, $desain) {
            $p['lingkup'] = self::teksBersih(trim((string) $lingkup));
            $p['luar']    = self::teksBersih(trim((string) $luar));
            $p['desain']  = self::teksBersih(trim((string) $desain));
            return $p;
        });
    }

    /**
     * Catatan dapur — estimasi & risiko, internal IT. TIDAK menghapus paraf:
     * BO tak pernah melihatnya, jadi bukan bagian dari yang ia setujui.
     */
    public function setDapur(ItTicket $t, ?string $estimasi, ?string $risiko): void
    {
        $this->pastikanDiAnalisis($t);

        $this->tulisFr02($t, function (array $p) use ($estimasi, $risiko) {
            $p['estimasi'] = self::teksBersih(trim((string) $estimasi));
            $p['risiko']   = self::teksBersih(trim((string) $risiko));
            return $p;
        }, hapusParaf: false);
    }

    /**
     * Ketergantungan = RELASI ke tiket lain (nomor), bukan teks bebas.
     * Semua tiket boleh dipilih termasuk DITUNDA (justru kasus terburuk yang
     * wajib terlihat); DITOLAK dikecualikan (§14.13 no. 7).
     */
    public function tambahDep(ItTicket $t, string $nomor): void
    {
        $this->pastikanDiAnalisis($t);

        $lain = ItTicket::where('nomor', $nomor)->first();
        if (! $lain || $lain->id === $t->id) {
            throw new DomainException('Tiket yang ditunggu tidak ditemukan.');
        }
        if ($lain->fase === 'DITOLAK') {
            throw new DomainException('Tiket DITOLAK tidak bisa dijadikan ketergantungan.');
        }

        $this->tulisFr02($t, function (array $p) use ($nomor) {
            $deps = $p['deps'] ?? [];
            if (! in_array($nomor, $deps, true)) $deps[] = $nomor;
            $p['deps'] = array_values($deps);
            return $p;
        });
    }

    public function hapusDep(ItTicket $t, string $nomor): void
    {
        $this->pastikanDiAnalisis($t);

        $this->tulisFr02($t, function (array $p) use ($nomor) {
            $p['deps'] = array_values(array_filter($p['deps'] ?? [], fn ($d) => $d !== $nomor));
            return $p;
        });
    }

    /**
     * Paraf satu Business Owner = baris berstempel waktu (siapa & kapan),
     * bukan kotak tanda tangan. Business Owner = Executive ATAU Konsultan —
     * pemeriksaan perannya di controller, JANGAN patok Executive.
     */
    public function parafBo(ItTicket $t, string $userId, string $nama): void
    {
        $this->pastikanDiAnalisis($t);

        // Paraf mengikuti isi, bukan mendahuluinya: BO tak boleh memparaf
        // analisis kosong. Syaratnya = semua lampu gerbang selain paraf itu sendiri.
        $segar = $this->muatRelasi(ItTicket::query())->find($t->id) ?? $t;
        foreach (PhaseEngine::gateInfo($this->viewTicket($segar))['checks'] ?? [] as $c) {
            if (! $c['ok'] && $c['t'] !== 'paraf satu Business Owner') {
                throw new DomainException('Belum bisa diparaf — "' . $c['t'] . '" belum terpenuhi. Minta Head IT melengkapi analisisnya dulu.');
            }
        }

        $this->tulisFr02($t, function (array $p) use ($userId, $nama) {
            $p['paraf'] = ['oleh' => $nama, 'oleh_id' => $userId, 'at' => now()->toIso8601String()];
            return $p;
        }, hapusParaf: false);
    }

    /**
     * Ubah jenjang di tengah jalan (mis. M yang ternyata butuh detail L) —
     * lewat "modal triase mode ubah-jenjang": FASE TIDAK BERUBAH. Paraf BO
     * dihapus karena syarat gerbangnya ikut berubah.
     */
    public function ubahJenjang(ItTicket $t, string $jenjang, int $bobot, string $prioritasIt): void
    {
        $this->pastikanDiAnalisis($t);
        // Bukan S: tiket S tak punya panel FRM-IT-02 sedangkan gerbang Analisis
        // tetap menuntut FR+komponen+paraf → tiket terkunci tanpa jalan keluar
        // (audit 3 Sep). Pekerjaan yang ternyata kecil: tunda → buka lagi → triase.
        if (! in_array($jenjang, ['M', 'L'], true)) {
            throw new DomainException('Di tengah Analisis jenjang hanya bisa M atau L. Kalau ternyata pekerjaan kecil (S): tunda tiketnya, lalu buka lagi lewat penilaian awal.');
        }

        DB::transaction(function () use ($t, $jenjang, $bobot, $prioritasIt) {
            $berubah = $t->jenjang !== $jenjang;
            $t->jenjang      = $jenjang;
            $t->bobot        = $bobot;
            $t->prioritas_it = $prioritasIt;
            $t->save();
            // paraf gugur hanya kalau JENJANG (= syarat gerbang) berubah, bukan bobot/prio
            if ($berubah) {
                $this->hapusParafBo($t);
            }
        });
    }

    // ─── FRM-IT-04 Internal Testing (Kelas B — IT sendiri, tanpa paraf lain) ──

    private function pastikanDiUji(ItTicket $t): void
    {
        if ($t->fase !== 'UJI') {
            throw new DomainException('FRM-IT-04 hanya diisi saat tiket berada di fase Uji Internal.');
        }
    }

    /** Lima pemeriksaan tetap SELALU ada (TC-01..05, terkunci). Idempoten. */
    public function pastikanTcTetap(ItTicket $t): void
    {
        foreach (PhaseEngine::TC_TETAP as $x) {
            $row = ItTestCase::firstOrNew(['ticket_id' => $t->id, 'kode' => $x['id']]);
            if (! $row->exists) {
                $row->skenario   = $x['skenario'];
                $row->diharapkan = $x['harapan'];
            }
            $row->terkunci = true;   // tiket lama yang sudah punya TC-01.. ikut terkunci
            $row->save();
        }
    }

    /**
     * Simpan tabel TC. Baris TERKUNCI: hanya aktual & status yang boleh diubah
     * (skenario/diharapkan diabaikan diam-diam? TIDAK — ditolak eksplisit).
     * Baris baru (kode kosong) ditambah dengan nomor berikutnya.
     */
    public function simpanTc(ItTicket $t, array $baris): void
    {
        $this->pastikanDiUji($t);
        $this->pastikanTcTetap($t);

        DB::transaction(function () use ($t, $baris) {
            $ada = ItTestCase::where('ticket_id', $t->id)->get()->keyBy('kode');
            $berikut = $this->nomorTcBerikut($t);

            foreach ($baris as $b) {
                $kode   = trim((string) ($b['kode'] ?? ''));
                $status = in_array($b['status'] ?? null, ['pass', 'fail'], true) ? $b['status'] : null;
                $aktual = mb_substr(self::teksBersih(trim((string) ($b['aktual'] ?? ''))), 0, 500);

                if ($kode !== '' && $ada->has($kode)) {
                    $row = $ada[$kode];
                    if ($row->terkunci) {
                        $skenBaru = trim((string) ($b['skenario'] ?? $row->skenario));
                        if ($skenBaru !== '' && $skenBaru !== $row->skenario) {
                            throw new DomainException($kode . ' adalah pemeriksaan tetap — skenarionya tak bisa diganti nama.');
                        }
                    } else {
                        $sken = mb_substr(self::teksBersih(trim((string) ($b['skenario'] ?? ''))), 0, 500);
                        if ($sken !== '') $row->skenario = $sken;
                        $row->diharapkan = mb_substr(self::teksBersih(trim((string) ($b['diharapkan'] ?? ''))), 0, 500);
                    }
                    $row->aktual = $aktual;
                    $row->status = $status;
                    $row->save();
                    continue;
                }

                // kode terisi tapi tak dikenal = tab basi (baris sudah dihapus/dinomori
                // ulang di tempat lain) — tolak, jangan diam-diam lahir sebagai baris kembar
                if ($kode !== '') {
                    throw new DomainException('Baris ' . $kode . ' tidak dikenal lagi (mungkin sudah dihapus di tab lain) — muat ulang halaman, lalu simpan lagi.');
                }

                // baris baru (hantu) — hanya kalau skenarionya diisi
                $sken = mb_substr(self::teksBersih(trim((string) ($b['skenario'] ?? ''))), 0, 500);
                if ($sken === '') continue;
                ItTestCase::create([
                    'ticket_id' => $t->id, 'kode' => sprintf('TC-%02d', $berikut++),
                    'skenario' => $sken,
                    'diharapkan' => mb_substr(self::teksBersih(trim((string) ($b['diharapkan'] ?? ''))), 0, 500),
                    'aktual' => $aktual, 'status' => $status, 'terkunci' => false,
                ]);
            }
        });
    }

    /** TC terkunci TIDAK bisa dihapus; TC bebas dihapus lalu dinomori ulang dari TC-06. */
    public function hapusTc(ItTicket $t, string $kode): void
    {
        $this->pastikanDiUji($t);

        $row = ItTestCase::where('ticket_id', $t->id)->where('kode', $kode)->first();
        if (! $row) {
            throw new DomainException('Test case tidak ditemukan.');
        }
        if ($row->terkunci) {
            throw new DomainException($kode . ' adalah pemeriksaan tetap — tak bisa dihapus. TC-05 yang menangkap kerusakan diam-diam.');
        }

        DB::transaction(function () use ($t, $row) {
            $row->delete();
            $n = count(PhaseEngine::TC_TETAP) + 1;
            foreach (ItTestCase::where('ticket_id', $t->id)->where('terkunci', false)->orderBy('kode')->get() as $r) {
                $r->kode = sprintf('TC-%02d', $n++);
                $r->save();
            }
        });
    }

    private function nomorTcBerikut(ItTicket $t): int
    {
        $maks = ItTestCase::where('ticket_id', $t->id)->get()
            ->map(fn ($r) => (int) substr($r->kode, 3))->max() ?? 0;
        return max($maks, count(PhaseEngine::TC_TETAP)) + 1;
    }

    public function tambahTemuan(ItTicket $t, string $deskripsi, string $tingkat = 'Minor', ?string $picUserId = null): ItTemuan
    {
        $this->pastikanDiUji($t);
        $deskripsi = mb_substr(self::teksBersih(trim($deskripsi)), 0, 500);
        if ($deskripsi === '') {
            throw new DomainException('Deskripsi temuan wajib diisi.');
        }

        return ItTemuan::create([
            'ticket_id' => $t->id, 'deskripsi' => $deskripsi,
            'tingkat' => $tingkat === 'Major' ? 'Major' : 'Minor',
            'pic_user_id' => $picUserId ?: $t->pic_user_id, 'status' => 'Terbuka',
        ]);
    }

    public function ubahTemuan(ItTicket $t, int $rowId, array $d): void
    {
        $this->pastikanDiUji($t);
        $row = ItTemuan::where('ticket_id', $t->id)->where('id', $rowId)->first();
        if (! $row) {
            throw new DomainException('Temuan tidak ditemukan.');
        }

        if (array_key_exists('deskripsi', $d)) {
            $desc = mb_substr(self::teksBersih(trim((string) $d['deskripsi'])), 0, 500);
            if ($desc !== '') $row->deskripsi = $desc;
        }
        if (isset($d['tingkat']) && in_array($d['tingkat'], ['Minor', 'Major'], true)) $row->tingkat = $d['tingkat'];
        if (isset($d['status'])  && in_array($d['status'], ['Terbuka', 'Selesai'], true)) $row->status = $d['status'];
        if (array_key_exists('pic_user_id', $d)) $row->pic_user_id = $d['pic_user_id'] ?: null;
        $row->save();
    }

    public function hapusTemuan(ItTicket $t, int $rowId): void
    {
        $this->pastikanDiUji($t);
        ItTemuan::where('ticket_id', $t->id)->where('id', $rowId)->delete();
    }

    // ─── FRM-IT-05 UAT (Kelas A — pemohon saja) ──────────────────────────────

    private function tulisFr05(ItTicket $t, callable $ubah): void
    {
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-05']);
        $form->payload = $ubah($form->payload ?? []);
        $form->save();
    }

    public static function uatSudahDiputuskan(array $fr05): bool
    {
        return in_array($fr05['keputusan'] ?? null, ['setuju', 'catatan'], true);
    }

    /**
     * Skenario UAT ditulis IT dari sudut pandang pemohon ("coba X, seharusnya
     * Y") — boleh disiapkan sejak Development sampai UAT, selama belum diputuskan.
     * Jawaban pemohon (sesuai/catatan) dipertahankan per indeks.
     */
    public function simpanSkenario(ItTicket $t, array $baris): void
    {
        if (! in_array($t->fase, ['DEV', 'UJI', 'UAT'], true)) {
            throw new DomainException('Skenario UAT disiapkan di fase Development, Uji Internal, atau UAT.');
        }

        $this->tulisFr05($t, function (array $p) use ($baris) {
            if (self::uatSudahDiputuskan($p)) {
                throw new DomainException('UAT sudah diputuskan — skenarionya terkunci.');
            }
            $lama  = $p['skenario'] ?? [];
            $bersih = [];
            foreach ($baris as $i => $b) {
                $proses = mb_substr(self::teksBersih(trim((string) ($b['proses'] ?? ''))), 0, 500);
                if ($proses === '') continue;
                $bersih[] = [
                    'proses'  => $proses,
                    'harapan' => mb_substr(self::teksBersih(trim((string) ($b['harapan'] ?? ''))), 0, 500),
                    'sesuai'  => $lama[$i]['sesuai'] ?? null,
                    'catatan' => $lama[$i]['catatan'] ?? '',
                ];
            }
            $p['skenario'] = $bersih;
            return $p;
        });
    }

    /** Pemohon menjawab: Sesuai / Tidak (+ catatan saat Tidak) + Masukan End User. */
    public function jawabUat(ItTicket $t, array $jawaban, ?string $masukan): void
    {
        if ($t->fase !== 'UAT') {
            throw new DomainException('Jawaban UAT hanya saat tiket berada di fase UAT.');
        }

        $this->tulisFr05($t, function (array $p) use ($jawaban, $masukan) {
            if (self::uatSudahDiputuskan($p)) {
                throw new DomainException('UAT sudah diputuskan.');
            }
            // ⚠️ Bukan foreach-by-reference atas `($p['skenario'] ?? [])`: ekspresi
            // itu SALINAN sementara, perubahannya lenyap diam-diam (jawaban pemohon
            // tak pernah tersimpan — ditangkap uji 2b).
            $sk = $p['skenario'] ?? [];
            foreach ($sk as $i => $s) {
                if (! isset($jawaban[$i])) continue;
                $j = $jawaban[$i];
                $s['sesuai']  = isset($j['sesuai']) && $j['sesuai'] !== '' ? (bool) (int) $j['sesuai'] : null;
                $s['catatan'] = mb_substr(self::teksBersih(trim((string) ($j['catatan'] ?? ''))), 0, 500);
                $sk[$i] = $s;
            }
            $p['skenario'] = $sk;
            $p['masukan']  = mb_substr(self::teksBersih(trim((string) $masukan)), 0, 1000);
            return $p;
        });
    }

    // ─── spawnTicket — SATU mekanisme: catatan UAT & RTL evaluasi ────────────

    /**
     * Melahirkan tiket baru dari butir tindak lanjut (UAT) atau rencana
     * tindak lanjut (Evaluasi). Tiket lahir di TRIASE, pemohon sama dengan
     * induknya, induk tercatat (lahir_dari_id + lahir_via). Menutup lingkaran.
     */
    public function lahirkanTiket(ItTicket $parent, string $dari, int $indeks, string $userId): ItTicket
    {
        [$kode, $kunci, $via] = $dari === 'evaluasi'
            ? ['FRM-IT-07', 'rtl', 'Evaluasi']
            : ['FRM-IT-05', 'tindakLanjut', 'UAT'];

        if ($parent->fase === 'DITOLAK') {
            throw new DomainException('Tiket yang ditolak tidak melahirkan tiket baru.');
        }

        $f1 = optional($parent->forms->firstWhere('kode', 'FRM-IT-01'))->payload ?? [];

        return DB::transaction(function () use ($parent, $kode, $kunci, $indeks, $via, $f1, $userId) {
            // Baris formulir DIKUNCI di dalam transaksi: dua klik "Jadikan tiket"
            // beruntun tak boleh melahirkan dua tiket dari satu butir.
            $form = ItForm::where('ticket_id', $parent->id)->where('kode', $kode)->lockForUpdate()->first();
            $p    = $form ? ($form->payload ?? []) : [];
            $item = $p[$kunci][$indeks] ?? null;

            if (! $item || trim((string) ($item['txt'] ?? '')) === '') {
                throw new DomainException('Butir tindak lanjut tidak ditemukan.');
            }
            if (! empty($item['ticketId'])) {
                throw new DomainException('Butir ini sudah dijadikan tiket ' . $item['ticketId'] . '.');
            }

            $anak = $this->buat([
                // judul varchar(255); teks penuhnya tetap utuh di 'kebutuhan'
                'judul'            => mb_substr($item['txt'], 0, 255),
                'sumber'           => $parent->pemohon_user_id ? 'permintaan' : 'inisiatif',
                'pemohon_user_id'  => $parent->pemohon_user_id,
                'raw_branch_id'    => $parent->raw_branch_id,
                'jenis_permintaan' => 'Perubahan fitur',
                'target'           => '—',
                'latar'            => 'Tindak lanjut dari ' . $parent->nomor . ' (' . $via . '): ' . $parent->judul,
                'kebutuhan'        => $item['txt'],
                'terdampak'        => $f1['terdampak'] ?? '—',
                '_lahir_dari_id'   => $parent->id,
                '_lahir_via'       => $via,
            ], $userId);

            $p[$kunci][$indeks]['ticketId'] = $anak->nomor;
            $form->payload = $p;
            $form->save();

            return $anak;
        });
    }

    // ─── FRM-IT-07 Evaluation (Kelas A — evaluator) ─────────────────────────

    private function pastikanDiEvaluasi(ItTicket $t): void
    {
        if ($t->fase !== 'EVALUASI') {
            throw new DomainException('FRM-IT-07 hanya diisi saat tiket berada di fase Evaluasi.');
        }
    }

    /** Evaluator = evaluator_user_id kalau dialihkan, kalau tidak pemohon. */
    public static function evaluatorUserId(ItTicket $t): ?string
    {
        return $t->evaluator_user_id ?: $t->pemohon_user_id;
    }

    public function simpanEvaluasi(ItTicket $t, array $aspek, array $temuan, array $rtl): void
    {
        $this->pastikanDiEvaluasi($t);

        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-07']);
        $p    = $form->payload ?? [];

        $nilai = [];
        foreach (PhaseEngine::EVAL_ASPEK as $a) {
            $v = (int) ($aspek[$a['k']] ?? 0);
            if ($v >= 1 && $v <= 5) $nilai[$a['k']] = $v;
        }
        $p['aspek'] = $nilai;

        $tm = [];
        foreach ($temuan as $x) {
            $txt = mb_substr(self::teksBersih(trim((string) ($x['txt'] ?? ''))), 0, 500);
            if ($txt === '') continue;
            $tm[] = ['id' => sprintf('E-%02d', count($tm) + 1), 'jenis' => ($x['jenis'] ?? '') === 'Enhancement' ? 'Enhancement' : 'Bug', 'txt' => $txt];
        }
        $p['temuan'] = $tm;

        // RTL: ticketId yang sudah lahir DIPERTAHANKAN (dicocokkan lewat id butir).
        $lamaRtl = collect($p['rtl'] ?? [])->keyBy('id');
        $baru = [];
        foreach ($rtl as $x) {
            $txt = mb_substr(self::teksBersih(trim((string) ($x['txt'] ?? ''))), 0, 500);
            if ($txt === '') continue;
            $id  = (string) ($x['id'] ?? '');
            $tid = $id !== '' && $lamaRtl->has($id) ? ($lamaRtl[$id]['ticketId'] ?? null) : null;
            $baru[] = ['id' => $id !== '' ? $id : sprintf('R-%02d', count($baru) + 1), 'txt' => $tid ? $lamaRtl[$id]['txt'] : $txt, 'ticketId' => $tid];
        }
        // nomori ulang yang belum bertiket supaya rapi
        $n = 1;
        foreach ($baru as &$b) { $b['id'] = sprintf('R-%02d', $n++); }
        unset($b);
        $p['rtl'] = $baru;

        $form->payload = $p;
        $form->save();
    }

    /** Alihkan evaluator (default pemohon). NULL = kembali ke pemohon. */
    public function alihkanEvaluator(ItTicket $t, ?string $userId): void
    {
        $this->pastikanDiEvaluasi($t);
        // Evaluator hanya pemohon, Executive, atau Konsultan yang aktif — bukan
        // orang IT (menilai diri sendiri) atau Head departemen lain (403 di detail).
        if ($userId && $userId !== $t->pemohon_user_id) {
            $sah = DB::table('users as u')->join('group_path as g', 'g.id', '=', 'u.group_id')
                ->where('u.id', $userId)->where('u.status', 1)
                ->whereIn('g.nama', self::ROLE_PENGAWAS)->exists();
            if (! $sah) {
                throw new DomainException('Evaluator harus pemohon, Executive, atau Konsultan yang aktif.');
            }
        }
        $t->evaluator_user_id = $userId ?: null;
        $t->save();
    }

    /**
     * Pindah fase — SATU-SATUNYA jalan mengubah it_ticket.fase.
     * Tiap perpindahan: baris riwayat berjalan ditutup (keluar_at) dan TEPAT
     * SATU baris baru lahir (uji terima §14.6 no. 6).
     */
    public function pindahFase(ItTicket $t, string $fase, string $userId): void
    {
        $now = now();

        ItTicketFase::where('ticket_id', $t->id)
            ->whereNull('keluar_at')
            ->update(['keluar_at' => $now]);

        ItTicketFase::create([
            'ticket_id'      => $t->id,
            'fase'           => $fase,
            'masuk_at'       => $now,
            'oleh_user_id'   => $userId,
            'pihak_menunggu' => self::pihakMenunggu($fase),
        ]);

        $t->fase       = $fase;
        $t->fase_since = $now;
        $t->save();

        // Masuk Uji Internal → lima pemeriksaan tetap SUDAH menunggu (Kelas B:
        // layar terisi, bukan kosong). Idempoten; tiket lama pun aman.
        if ($fase === 'UJI') {
            $this->pastikanTcTetap($t);
        }
    }

    /** Penanggung fase (rencana §5) — untuk jejak audit & biaya menunggu. */
    public static function pihakMenunggu(string $fase): ?string
    {
        return match ($fase) {
            'TRIASE', 'DEV', 'UJI' => 'it',
            'ANALISIS'             => 'bersama',
            'UAT'                  => 'pemohon',
            'GOLIVE'               => 'executive',
            'EVALUASI'             => 'evaluator',
            default                => null, // ANTRE, SELESAI, DITUNDA, DITOLAK
        };
    }

    /**
     * Rakit bentuk tiket yang dibaca PhaseEngine (bentuk objek it-store.js).
     * faseSince selalu dari kolom tiket = masuk_at baris riwayat berjalan.
     */
    public function viewTicket(ItTicket $t): array
    {
        $fr02 = optional($t->forms->firstWhere('kode', 'FRM-IT-02'))->payload ?? [];
        $fr03 = optional($t->forms->firstWhere('kode', 'FRM-IT-03'))->payload ?? [];
        $fr05 = optional($t->forms->firstWhere('kode', 'FRM-IT-05'))->payload ?? [];
        $fr06 = optional($t->forms->firstWhere('kode', 'FRM-IT-06'))->payload ?? [];

        // ── Kelas B: dua baris pra-deploy MENYALA SENDIRI, tak bisa diklik ──
        // Sumbernya data yang sudah dimiliki sistem, bukan ketikan ulang:
        //   uat    ← keputusan FRM-IT-05 (ditulis saat pemohon menyetujui)
        //   script ← ada tidaknya jejak git tiket ini (it_git_ref, it:sync-git)
        // Diturunkan DI SINI supaya gerbang (PhaseEngine::gateInfo) dan layar
        // membaca angka yang sama persis — bukan dua perhitungan yang bisa
        // berselisih diam-diam.
        $fr06['pra'] = array_merge($fr06['pra'] ?? [], array_filter([
            // "Disetujui dengan Catatan" JUGA menyalakan lampu — dulu hanya 'setuju',
            // dan tiket ber-catatan macet PERMANEN di Go Live (E2E 3 Sep malam).
            'uat'    => in_array($fr05['keputusan'] ?? null, ['setuju', 'catatan'], true) ? 'ya' : null,
            'script' => $t->gitRefs->isNotEmpty() ? 'ya' : null,
        ]));
        $fr07 = optional($t->forms->firstWhere('kode', 'FRM-IT-07'))->payload ?? [];

        $frmDev = $t->forms->firstWhere('kode', 'FRM-IT-03');

        return [
            'id'        => $t->nomor,
            'title'     => $t->judul,
            'source'    => $t->sumber,
            'pemohon'   => $t->pemohon ? ['name' => $t->pemohon->nama] : null,
            'jenjang'   => $t->jenjang,
            'bobot'     => $t->bobot,
            'prioP'     => $t->prioritas_pemohon,
            'prioIT'    => $t->prioritas_it,
            'pic'       => $t->pic ? $t->pic->nama : null,
            'fase'      => $t->fase,
            'faseSince' => $t->fase_since->toIso8601String(),
            // Fase 2b: evaluator kini relasi (eager di muatRelasi) — tak ada N+1.
            'evaluator' => $t->evaluator ? $t->evaluator->nama : null,
            'children'  => $t->anak->pluck('judul')->all(),
            // Ronde 2: ketergantungan (relasi ke tiket lain, disimpan nomor) dan
            // tiket asal kalau tiket ini lahir dari catatan UAT / RTL evaluasi.
            'deps'      => array_values($fr02['deps'] ?? []),
            'parent'    => $t->lahir_dari_id
                ? ['id' => $t->lahirDari ? $t->lahirDari->nomor : ('#' . $t->lahir_dari_id), 'via' => $t->lahir_via]
                : null,
            'fr02'      => array_merge($fr02, [
                'fr' => $t->frs->map(fn ($r) => [
                    'id'       => $r->kode,
                    'desc'     => $r->kebutuhan,
                    'prio'     => $r->prioritas,
                    'kriteria' => $r->kriteria_diterima,
                ])->all(),
            ]),
            'fr03'      => array_merge($fr03, [
                'confirmed' => (bool) optional($frmDev)->dikonfirmasi_at,
            ]),
            'fr04'      => [
                'tc' => $t->testCases->sortBy('kode')->values()->map(fn ($r) => [
                    'id'       => $r->kode,
                    'skenario' => $r->skenario,
                    'harapan'  => $r->diharapkan,
                    'aktual'   => $r->aktual,
                    'status'   => $r->status,
                    'locked'   => (bool) $r->terkunci,
                ])->all(),
                'temuan' => $t->temuan->map(fn ($r) => [
                    'rowId'   => $r->id,
                    'desc'    => $r->deskripsi,
                    'tingkat' => $r->tingkat,
                    'status'  => $r->status,
                    'pic'     => $r->pic_user_id,
                ])->all(),
            ],
            'fr05'      => $fr05,
            'fr06'      => $fr06,
            'fr07'      => $fr07,
        ];
    }

    /** Muat relasi yang dibutuhkan viewTicket sekali jalan (hindari N+1). */
    public function muatRelasi($query)
    {
        return $query->with(['pemohon', 'pemohon.group', 'pic', 'evaluator', 'lahirDari', 'forms', 'frs', 'testCases', 'temuan', 'anak', 'gitRefs']);
    }

    /**
     * Panel "Beban IT saat ini" — terbaca 5 detik (brief §5b no. 1-3):
     * item aktif, pemisahan permintaan vs inisiatif, macet di luar IT, dan
     * berapa yang sudah di tangan IT (untuk kalimat konsekuensi).
     */
    public function ringkasanBeban(array $viewTickets): array
    {
        $act = array_values(array_filter($viewTickets, fn ($t) => PhaseEngine::isActive($t)));

        $macet = 0;
        foreach ($act as $t) {
            if (PhaseEngine::waitOf($t)['heat'] >= 1) $macet++;
        }

        $perm = count(array_filter($act, fn ($t) => $t['source'] === 'permintaan'));

        return [
            'aktif'      => count($act),
            'permintaan' => $perm,
            'inisiatif'  => count($act) - $perm,
            'macet'      => $macet,
            'diTangan'   => PhaseEngine::inHand($act),
        ];
    }
}
