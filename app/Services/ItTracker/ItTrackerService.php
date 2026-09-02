<?php

namespace App\Services\ItTracker;

use App\Models\ItForm;
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

    public const FASE_PARKIR = ['DITUNDA', 'DITOLAK'];

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
        $fase   = ['permintaan' => 'TRIASE', 'inisiatif' => 'ANTRE', 'insiden' => 'DEV'][$sumber]
            ?? throw new DomainException('Sumber tiket tidak dikenal.');

        if ($sumber !== 'permintaan' && empty($d['jenjang'])) {
            throw new DomainException('Inisiatif/insiden IT wajib langsung diberi jenjang (S/M/L).');
        }

        return DB::transaction(function () use ($d, $sumber, $fase, $userId) {
            $now = now();

            $ticket = ItTicket::create([
                'nomor'             => $this->nomorBaru($now),
                'judul'             => $d['judul'],
                'sumber'            => $sumber,
                'pemohon_user_id'   => $sumber === 'permintaan' ? $userId : null,
                'raw_branch_id'     => $d['raw_branch_id'] ?? null,
                'jenis_permintaan'  => $d['jenis_permintaan'] ?? null,
                'jenjang'           => $sumber === 'permintaan' ? null : ($d['jenjang'] ?? null),
                'bobot'             => $sumber === 'permintaan' ? null : ($d['bobot'] ?? null),
                'prioritas_pemohon' => $sumber === 'permintaan' ? ($d['prioritas_pemohon'] ?? null) : null,
                'prioritas_it'      => $sumber === 'permintaan' ? null : ($d['prioritas_it'] ?? null),
                'pic_user_id'       => $sumber === 'permintaan' ? null : $userId,
                'fase'              => $fase,
                'fase_since'        => $now,
                'induk_id'          => $d['induk_id'] ?? null,
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
                        'target'    => $d['target'] ?? '—',
                        'latar'     => $d['latar'] ?? '',
                        'kebutuhan' => $d['kebutuhan'] ?? '',
                        'terdampak' => $d['terdampak'] ?? '—',
                        'review'    => null,
                    ],
                ]);
            }

            return $ticket;
        });
    }

    /** Triase (Head IT): jenjang + bobot + prioritas IT → ANTRE. */
    public function triase(ItTicket $t, string $jenjang, int $bobot, string $prioritasIt, string $userId): void
    {
        if ($t->fase !== 'TRIASE') {
            throw new DomainException('Tiket ini tidak sedang menunggu triase.');
        }

        DB::transaction(function () use ($t, $jenjang, $bobot, $prioritasIt, $userId) {
            $t->jenjang      = $jenjang;
            $t->bobot        = $bobot;
            $t->prioritas_it = $prioritasIt;
            $this->pindahFase($t, 'ANTRE', $userId);
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
            $t->alasan_parkir = trim($alasan);
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
            throw new DomainException('Tiket ini belum ditriase — tetapkan jenjang dulu.');
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
        if (! in_array($keputusan, ['setuju', 'perbaikan'], true)) {
            throw new DomainException('Keputusan UAT tidak dikenal.');
        }

        $ke = $keputusan === 'setuju' ? 'GOLIVE' : 'DEV';

        DB::transaction(function () use ($t, $keputusan, $ke, $userId) {
            $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-05']);
            $form->payload = array_merge($form->payload ?? [], [
                'keputusan' => $keputusan,
                'oleh'      => $userId,
                'tanggal'   => now()->toIso8601String(),
            ]);
            $form->save();

            $this->pindahFase($t, $ke, $userId);
        });

        return $ke;
    }

    /** Catatan singkat pengerjaan (jenjang S) — pengganti formulir penuh. */
    public function catatanDev(ItTicket $t, string $catatan, string $userId): void
    {
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-03']);
        $form->payload = array_merge($form->payload ?? [], ['note' => trim($catatan)]);
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
            $form->payload = array_merge($form->payload ?? [], ['note' => trim($catatan)]);
        }
        $form->dikonfirmasi_oleh = $userId;
        $form->dikonfirmasi_at   = now();
        $form->save();
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
            // Belum ada penulis evaluator_user_id di Fase 1 — di-null-kan
            // supaya tak ada Users::find() per tiket (N+1 terpasang diam-diam).
            // Fase 2: jadikan relasi belongsTo + ikutkan di muatRelasi().
            'evaluator' => null,
            'children'  => $t->anak->pluck('judul')->all(),
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
                'tc' => $t->testCases->map(fn ($r) => [
                    'id'       => $r->kode,
                    'skenario' => $r->skenario,
                    'harapan'  => $r->diharapkan,
                    'aktual'   => $r->aktual,
                    'status'   => $r->status,
                ])->all(),
                'temuan' => $t->temuan->map(fn ($r) => [
                    'desc'    => $r->deskripsi,
                    'tingkat' => $r->tingkat,
                    'status'  => $r->status,
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
        return $query->with(['pemohon', 'pic', 'forms', 'frs', 'testCases', 'temuan', 'anak']);
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
