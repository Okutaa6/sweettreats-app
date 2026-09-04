<?php

namespace App\Console\Commands;

use App\Models\ItForm;
use App\Models\ItModul;
use App\Models\ItTicket;
use App\Models\ItTicketFase;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * it:seed-antrian — mengisi IT Request Tracker dengan antrian kerjaan NYATA
 * dari berkas PSV (rencana §14.21–14.22). Dijalankan di dev untuk demo,
 * lalu DIULANG di prod sesudah tracker naik.
 *
 * Aturan yang menentukan bentuknya:
 * - IDEMPOTEN by `urut` → it_ticket.seed_urut (unik). Jalan ulang memperbarui,
 *   tidak menggandakan. Nomor tiket tetap dari it_counter — tak pernah hardcode.
 * - Pemohon dicari by email, fallback nama; tak ketemu → BERHENTI menyebut baris.
 * - `masuk_at` PERSIS dari berkas ke it_ticket_fase + created_at — itulah yang
 *   membuat "berapa hari menunggu" jujur. Bukan now().
 * - Tiket di ANALISIS/UAT/EVALUASI TIDAK diisi FR/paraf/skenario palsu: lampu
 *   gerbangnya mati apa adanya — memang dibangun tanpa formulir.
 * - status=ditunda → parkir() yang sudah ada (tercatat ke audit_log).
 * - Jalan ulang TIDAK memundurkan fase tiket yang sudah bergerak di layar.
 */
class ItSeedAntrian extends Command
{
    protected $signature = 'it:seed-antrian {berkas : path berkas .psv (pipe-separated, 17 kolom)}
                            {--env-guard= : local | dev | prod — WAJIB cocok dengan lingkungan ini}';

    protected $description = 'Isi IT Request Tracker dengan antrian nyata dari berkas PSV (idempoten by seed_urut)';

    private const KOLOM = ['urut', 'judul', 'sumber', 'pemohon_email', 'pemohon_nama', 'pic_email', 'jenjang', 'bobot',
        'prioritas_pemohon', 'prioritas_it', 'fase', 'masuk_at', 'status', 'alasan_tunda', 'parent_urut', 'deps_urut', 'latar',
        // kolom ke-18 (§14.26): nama modul katalog, dicocokkan PERSIS ke it_modul.nama.
        // Boleh kosong — tiket tanpa modul memang sah dan masuk baris "Tanpa modul".
        'modul'];

    public function __construct(private ItTrackerService $svc)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // ── 0. gerbang lingkungan ──────────────────────────────────────────
        $minta = (string) $this->option('env-guard');
        $nyata = self::lingkunganNyata();
        if ($minta === '' || $minta !== $nyata) {
            $this->gagal("DITOLAK: --env-guard=" . ($minta ?: '(kosong)') . " tetapi lingkungan ini terbaca '{$nyata}' "
                . '(APP_ENV=' . config('app.env') . ', APP_URL=' . config('app.url') . '). Sebut lingkungan yang benar secara eksplisit.');
            return self::FAILURE;
        }

        // ── 1. baca & validasi berkas ──────────────────────────────────────
        $path = (string) $this->argument('berkas');
        if (! is_file($path)) {
            $this->gagal("Berkas tidak ditemukan: {$path}");
            return self::FAILURE;
        }
        try {
            $baris = $this->baca($path);
            $this->periksa($baris);
        } catch (\RuntimeException $e) {
            $this->gagal('BERHENTI — ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── 2. resolusi orang ─────────────────────────────────────────────
        try {
            foreach ($baris as &$b) {
                $b['_pemohon'] = $this->cariUser($b['pemohon_email'], $b['pemohon_nama'], $b['_baris'], $b['sumber'] === 'permintaan');
                $b['_pic']     = $b['pic_email'] !== '' ? $this->cariUser($b['pic_email'], '', $b['_baris'], false) : null;
                if ($b['pic_email'] !== '' && ! $b['_pic']) {
                    $this->warn("  ⚠ baris {$b['_baris']} (urut {$b['urut']}): PIC {$b['pic_email']} tidak ditemukan — dibiarkan kosong.");
                }
            }
            unset($b);
        } catch (\RuntimeException $e) {
            $this->gagal('BERHENTI — ' . $e->getMessage());
            return self::FAILURE;
        }
        // ── 2b. resolusi MODUL katalog ────────────────────────────────────
        // ⛔ Cocok PERSIS ke it_modul.nama. Tak ditemukan → BERHENTI dan sebut
        //    barisnya. JANGAN membuat entri katalog otomatis, JANGAN menebak dari
        //    judul, JANGAN melewati diam-diam: entri yang lahir sendiri membuat
        //    katalog berisi modul yang tak pernah diputuskan siapa pun, dan
        //    tebakan yang meleset tak terlihat siapa pun.
        $petaModul = ItModul::pluck('id', 'nama')->all();
        foreach ($baris as &$b) {
            $b['_modul'] = null;
            $nm = trim((string) ($b['modul'] ?? ''));
            if ($nm === '') continue;                     // kosong = sah
            if (! isset($petaModul[$nm])) {
                $this->gagal("BERHENTI — baris {$b['_baris']} (urut {$b['urut']}): modul '{$nm}' tidak ada di katalog. "
                    . 'Buat entrinya dulu lewat it:seed-katalog atau tombol "Entri modul" — seeder tidak membuat entri sendiri.');
                return self::FAILURE;
            }
            $b['_modul'] = (int) $petaModul[$nm];
        }
        unset($b);

        $aktor = $this->cariUser('ikhsan@sweettreats.id', '', 0, false) ?: 'seed-antrian';

        // ── 3. lintasan 1: tiket + fase + FRM-IT-01 ───────────────────────
        $hasil = [];
        DB::transaction(function () use ($baris, $aktor, &$hasil) {
            foreach ($baris as $b) {
                $hasil[(int) $b['urut']] = $this->terapkan($b, $aktor);
            }
            // ── lintasan 2: induk & ketergantungan (semua nomor sudah ada) ──
            foreach ($baris as $b) {
                $this->relasi($b, $hasil);
            }
        });

        // ── 4. laporan ────────────────────────────────────────────────────
        $rows = [];
        foreach ($baris as $b) {
            $h = $hasil[(int) $b['urut']];
            $rows[] = [$b['urut'], $h['nomor'], $h['aksi'], $h['fase'], $b['pemohon_nama'] ?: ($b['pemohon_email'] ?: '—'),
                $b['pic_email'] ? explode('@', $b['pic_email'])[0] : '—', $h['catatan']];
        }
        $this->table(['urut', 'nomor', 'aksi', 'fase', 'pemohon', 'pic', 'catatan'], $rows);
        $counter = (int) DB::table('it_counter')->where('periode', date('Y'))->value('last_no');
        $this->info(sprintf('%d baris diproses · %d tiket ber-seed_urut · it_counter %s = %03d · lingkungan %s',
            count($baris), ItTicket::whereNotNull('seed_urut')->count(), date('Y'), $counter, $nyata));

        return self::SUCCESS;
    }

    /** Pesan gagal SATU baris tanpa bungkus blok — error() memotong email/nama panjang jadi beberapa baris. */
    private function gagal(string $pesan): void
    {
        $this->output->writeln("<fg=red;options=bold>" . $pesan . "</>");
    }

    // ─────────────────────────────────────────────────────────────────────

    /** local (APP_ENV=local) · dev (host dev.*) · prod (host app.* / APP_ENV=production). */
    public static function lingkunganNyata(): string
    {
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if (str_starts_with($host, 'dev.')) return 'dev';
        if (in_array(config('app.env'), ['local', 'testing'], true)) return 'local'; // phpunit = testing
        if ($host === 'app.sweettreats.id' || config('app.env') === 'production') return 'prod';
        return 'tak-dikenal';
    }

    private function baca(string $path): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path));
        $head  = array_map('trim', explode('|', array_shift($lines)));
        if ($head !== self::KOLOM) {
            throw new \RuntimeException('kepala berkas tidak sesuai ' . count(self::KOLOM)
                . ' kolom yang diharapkan: ' . implode('|', $head));
        }
        $out = [];
        foreach ($lines as $i => $line) {
            $no = $i + 2;
            if (trim($line) === '') continue;
            $sel = explode('|', $line);
            if (count($sel) !== count(self::KOLOM)) {
                throw new \RuntimeException("baris {$no}: " . count($sel) . ' kolom, seharusnya ' . count(self::KOLOM) . ' (pipa kelebihan/kurang?).');
            }
            $b = array_combine(self::KOLOM, array_map('trim', $sel));
            $b['_baris'] = $no;
            $out[] = $b;
        }
        if (! $out) throw new \RuntimeException('berkas tidak berisi baris data.');
        return $out;
    }

    private function periksa(array $baris): void
    {
        $urut = [];
        foreach ($baris as $b) {
            $n = $b['_baris'];
            if (! preg_match('/^\d{1,3}$/', $b['urut'])) throw new \RuntimeException("baris {$n}: urut '{$b['urut']}' bukan angka.");
            if (isset($urut[(int) $b['urut']])) throw new \RuntimeException("baris {$n}: urut {$b['urut']} kembar.");
            $urut[(int) $b['urut']] = true;
            if ($b['judul'] === '') throw new \RuntimeException("baris {$n}: judul kosong.");
            if (! in_array($b['sumber'], ['permintaan', 'inisiatif', 'insiden'], true)) throw new \RuntimeException("baris {$n}: sumber '{$b['sumber']}' tidak dikenal.");
            if (! isset(PhaseEngine::RANK[$b['fase']])) throw new \RuntimeException("baris {$n}: fase '{$b['fase']}' bukan fase aktif (TRIASE…EVALUASI); ditunda ditulis di kolom status.");
            if (! in_array($b['status'], ['aktif', 'ditunda'], true)) throw new \RuntimeException("baris {$n}: status '{$b['status']}' harus aktif|ditunda.");
            if ($b['status'] === 'ditunda' && $b['alasan_tunda'] === '') throw new \RuntimeException("baris {$n}: status ditunda tanpa alasan_tunda.");
            if ($b['jenjang'] !== '' && ! in_array($b['jenjang'], ['S', 'M', 'L'], true)) throw new \RuntimeException("baris {$n}: jenjang '{$b['jenjang']}'.");
            if ($b['sumber'] !== 'permintaan' && $b['jenjang'] === '') throw new \RuntimeException("baris {$n}: inisiatif/insiden wajib berjenjang (aturan Fase 1).");
            if ($b['fase'] !== 'TRIASE' && $b['jenjang'] === '') throw new \RuntimeException("baris {$n}: fase {$b['fase']} tanpa jenjang — tiket lewat triase pasti berjenjang.");
            if ($b['prioritas_it'] !== '' && ! in_array($b['prioritas_it'], ['P1', 'P2', 'P3'], true)) throw new \RuntimeException("baris {$n}: prioritas_it '{$b['prioritas_it']}'.");
            if ($b['prioritas_pemohon'] !== '' && ! in_array($b['prioritas_pemohon'], ['Rendah', 'Sedang', 'Tinggi'], true)) throw new \RuntimeException("baris {$n}: prioritas_pemohon '{$b['prioritas_pemohon']}'.");
            try { Carbon::createFromFormat('Y-m-d H:i', $b['masuk_at']); } catch (\Throwable) { throw new \RuntimeException("baris {$n}: masuk_at '{$b['masuk_at']}' bukan Y-m-d H:i."); }
            if ($b['latar'] === '') throw new \RuntimeException("baris {$n}: latar kosong.");
        }
        foreach ($baris as $b) {
            foreach (array_filter(array_map('trim', explode(',', $b['parent_urut'] . ',' . $b['deps_urut']))) as $ref) {
                if (! isset($urut[(int) $ref])) throw new \RuntimeException("baris {$b['_baris']}: merujuk urut {$ref} yang tidak ada di berkas.");
            }
        }
    }

    /** users.id by email (aktif diutamakan), fallback nama persis. Wajib → tak ketemu = berhenti menyebut baris. */
    private function cariUser(string $email, string $nama, int $baris, bool $wajib): ?string
    {
        $id = null;
        if ($email !== '') {
            $id = DB::table('users')->where('email', $email)->orderByDesc('status')->value('id');
        }
        if (! $id && $nama !== '') {
            $id = DB::table('users')->where('nama', $nama)->orderByDesc('status')->value('id');
        }
        if (! $id && ($email !== '' || $nama !== '') && ($wajib || $nama !== '' || $baris === 0)) {
            // email ATAU nama disebut tapi tak ada di DB ini — jangan diam-diam
            if ($baris === 0) return null;
            throw new \RuntimeException("baris {$baris}: pemohon '" . ($email ?: $nama) . "'" . ($email && $nama ? " / '{$nama}'" : '')
                . ' tidak ditemukan di users lingkungan ini. Perbaiki email/nama di berkas atau buat akunnya dulu (ingat effective_from aturan kerja).');
        }
        if (! $id && $wajib) {
            throw new \RuntimeException("baris {$baris}: permintaan wajib punya pemohon (pemohon_email/pemohon_nama kosong).");
        }
        return $id ? (string) $id : null;
    }

    /** Lintasan 1 untuk satu baris: tiket (baru/ada) → kolom → fase & masuk_at → FRM-IT-01 → parkir. */
    private function terapkan(array $b, string $aktor): array
    {
        $masuk = Carbon::createFromFormat('Y-m-d H:i', $b['masuk_at']);
        $t = ItTicket::where('seed_urut', (int) $b['urut'])->first();
        $aksi = $t ? 'perbarui' : 'baru';
        $catatan = [];

        if (! $t) {
            $t = $this->svc->buat([
                'judul'             => $b['judul'],
                'sumber'            => $b['sumber'],
                'pemohon_user_id'   => $b['_pemohon'],
                'jenis_permintaan'  => $b['sumber'] === 'permintaan' ? 'Perubahan fitur' : null,
                'prioritas_pemohon' => $b['prioritas_pemohon'] ?: null,
                'jenjang'           => $b['jenjang'] ?: null,
                'bobot'             => $b['bobot'] !== '' ? (int) $b['bobot'] : null,
                'prioritas_it'      => $b['prioritas_it'] ?: null,
                'latar'             => $b['latar'],
            ], $b['sumber'] === 'permintaan' ? $b['_pemohon'] : $aktor);
        }

        // kolom identitas — selalu mengikuti berkas
        $t->seed_urut         = (int) $b['urut'];
        $t->judul             = ItTrackerService::teksBersih($b['judul']);
        $t->sumber            = $b['sumber'];
        $t->pemohon_user_id   = $b['_pemohon'];
        $t->pic_user_id       = $b['_pic'];
        $t->jenis_permintaan  = $b['sumber'] === 'permintaan' ? 'Perubahan fitur' : $t->jenis_permintaan;
        // TRIASE = belum ditriase: jenjang/bobot/prio IT di berkas hanya petunjuk untuk Ekky saat demo, bukan keputusan
        $triase = $b['fase'] === 'TRIASE';
        $t->jenjang           = $triase ? null : ($b['jenjang'] ?: null);
        $t->bobot             = $triase || $b['bobot'] === '' ? null : (int) $b['bobot'];
        $t->prioritas_pemohon = $b['prioritas_pemohon'] ?: null;
        $t->prioritas_it      = $triase ? null : ($b['prioritas_it'] ?: null);
        $t->evaluator_user_id = $b['fase'] === 'EVALUASI' ? $b['_pemohon'] : $t->evaluator_user_id;
        $t->modul_id          = $b['_modul'];   // NULL pun ditulis: berkas yang berkuasa
        $t->created_at        = $masuk;

        // fase & masuk_at — jujur dari berkas; TIDAK memundurkan tiket yang sudah bergerak di layar
        $faseKini = $t->fase;
        $sudahBergerak = $aksi === 'perbarui' && (
            ($faseKini === 'SELESAI') || ($faseKini === 'DITOLAK')
            || (isset(PhaseEngine::RANK[$faseKini]) && PhaseEngine::RANK[$faseKini] > PhaseEngine::RANK[$b['fase']])
            || ($faseKini === 'DITUNDA' && $b['status'] !== 'ditunda')
        );
        if ($sudahBergerak) {
            $catatan[] = "fase {$faseKini} dipertahankan (sudah bergerak di layar)";
            $t->save();
        } elseif ($faseKini === 'DITUNDA' && $b['status'] === 'ditunda') {
            $t->alasan_parkir = ItTrackerService::teksBersih($b['alasan_tunda']);
            $t->save();
            $catatan[] = 'tetap ditunda, alasan diperbarui';
        } else {
            $t->fase       = $b['fase'];
            $t->fase_since = $masuk;
            $t->alasan_parkir = null;
            $t->save();
            ItTicketFase::where('ticket_id', $t->id)->delete();
            ItTicketFase::create([
                'ticket_id' => $t->id, 'fase' => $b['fase'], 'masuk_at' => $masuk,
                'oleh_user_id' => $aktor, 'pihak_menunggu' => ItTrackerService::pihakMenunggu($b['fase']),
            ]);
            if ($b['status'] === 'ditunda') {
                // lewat parkir() yang sudah ada — tercatat ke audit_log seperti parkir manual
                $this->svc->parkir($t, 'DITUNDA', $b['alasan_tunda'], $aktor);
                $catatan[] = 'ditunda via parkir()';
            }
        }

        // FRM-IT-01 minimal: latar (penanda "[DRAF IT …]" DIBIARKAN) + jenis; field lain tak dikarang
        $form = ItForm::firstOrNew(['ticket_id' => $t->id, 'kode' => 'FRM-IT-01']);
        $p = $form->payload ?? ['target' => '—', 'kebutuhan' => '', 'terdampak' => '—', 'review' => null];
        $p['latar'] = ItTrackerService::teksBersih($b['latar']);
        $p['jenis'] = $b['sumber'] === 'permintaan' ? 'Perubahan fitur' : ($p['jenis'] ?? null);
        $form->payload = $p;
        $form->save();

        return ['nomor' => $t->nomor, 'id' => $t->id, 'aksi' => $aksi, 'fase' => $t->fresh()->fase, 'catatan' => implode('; ', $catatan)];
    }

    /** Lintasan 2: induk (it_ticket.induk_id) & ketergantungan (FRM-IT-02.deps — mekanisme 2b, ditulis langsung karena tiketnya belum tentu di ANALISIS). */
    private function relasi(array $b, array $hasil): void
    {
        $t = ItTicket::find($hasil[(int) $b['urut']]['id']);
        $t->induk_id = $b['parent_urut'] !== '' ? $hasil[(int) $b['parent_urut']]['id'] : null;
        $t->save();

        $deps = array_values(array_map(fn ($u) => $hasil[(int) trim($u)]['nomor'], array_filter(explode(',', $b['deps_urut']), fn ($x) => trim($x) !== '')));
        $form = ItForm::where('ticket_id', $t->id)->where('kode', 'FRM-IT-02')->first();
        if (! $deps && ! $form) return;
        $form ??= new ItForm(['ticket_id' => $t->id, 'kode' => 'FRM-IT-02']);
        $p = $form->payload ?? [];
        if (($p['deps'] ?? []) !== $deps) {
            $p['deps'] = $deps;
            $form->payload = $p;
            $form->save();
        }
    }
}
