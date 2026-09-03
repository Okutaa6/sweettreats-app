<?php

namespace App\Console\Commands;

use App\Models\ItGitRef;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Process;

/**
 * Isi `it_git_ref` dari riwayat git — jantung Kelas B (rencana §14.3).
 *
 * FRM-IT-03 harus TERISI SENDIRI: IT sudah menulis nomor tiket di commit
 * message, jadi layar tak boleh meminta orang mengetik ulang branch/commit.
 * Perintah ini yang memindahkannya ke tabel; layar hanya membaca.
 *
 * ⛔ Sengaja perintah artisan, BUKAN dipanggil saat merender halaman.
 * Dijadwalkan harian di Kernel; tombol "segarkan" di layar memanggil perintah
 * ini untuk SATU tiket (argumen tervalidasi, argv array — tak lewat shell).
 *
 * Idempoten: unik (ticket_nomor, commit_sha) — dijalankan 2x tak menggandakan.
 */
class ItSyncGit extends Command
{
    protected $signature = 'it:sync-git
        {--ticket= : batasi ke satu nomor tiket, mis. IT-2026-004}
        {--since= : batasi umur commit, mis. "6 months ago"}
        {--repo= : direktori repo (default: root aplikasi) — dipakai uji}';

    protected $description = 'Tarik commit ber-nomor-tiket dari git ke it_git_ref (Kelas B FRM-IT-03)';

    /** Pola nomor tiket — rencana §14.3. */
    private const POLA = 'IT-20[0-9][0-9]-[0-9]+';

    public function handle(): int
    {
        $ticket = $this->option('ticket');

        if ($ticket !== null && ! preg_match('/^IT-20\d{2}-\d+$/', $ticket)) {
            $this->error('Nomor tiket tidak berbentuk IT-YYYY-NNN.');
            return self::FAILURE;
        }

        // Pola grep dirakit dari KONSTANTA + nomor tervalidasi, tak pernah dari
        // masukan bebas. Process dipanggil dengan argv array → tanpa shell.
        $grep = $ticket !== null ? preg_quote($ticket, '') : self::POLA;

        $args = ['git', 'log', '--all', '--source', '-E', '--grep=' . $grep,
            '--format=%H%x1f%S%x1f%aI%x1f%s'];

        if ($since = $this->option('since')) {
            $args[] = '--since=' . $since;
        }

        $repo = $this->option("repo") ?: base_path();
        if (! is_dir($repo)) {
            $this->error("Direktori repo tidak ada: " . $repo);
            return self::FAILURE;
        }

        $p = new Process($args, $repo);
        $p->setTimeout(120);
        $p->run();

        if (! $p->isSuccessful()) {
            // Bukan repo git / git tak terpasang: BILANG, jangan diam. Kelas B
            // yang sunyi lebih berbahaya daripada Kelas B yang gagal berisik.
            $this->error('git tidak bisa dibaca di ' . base_path() . ' — ' . trim($p->getErrorOutput() ?: 'tak ada keluaran'));
            return self::FAILURE;
        }

        $baru = 0;
        $baris = 0;
        $lewat = 0; // nomor cuma di body — sengaja tak dicatat, tapi TIDAK disembunyikan

        foreach (preg_split('/\R/', trim($p->getOutput())) as $l) {
            if ($l === '') continue;
            $baris++;

            [$sha, $ref, $tgl, $subject] = array_pad(explode("\x1f", $l, 4), 4, '');

            // Satu commit boleh menyebut BEBERAPA tiket — semuanya dicatat,
            // kalau tidak tiket kedua kehilangan jejaknya diam-diam.
            if (! preg_match_all('/IT-20\d{2}-\d+/', $subject, $m)) { $lewat++; continue; }

            foreach (array_unique($m[0]) as $nomor) {
                $row = ItGitRef::firstOrNew(['ticket_nomor' => $nomor, 'commit_sha' => $sha]);
                $adaSebelumnya = $row->exists;

                $row->branch     = $this->rapikanRef($ref);
                $row->subject    = mb_substr($subject, 0, 500);
                $row->commit_at  = $tgl ? Carbon::parse($tgl) : null;
                $row->created_at = $row->created_at ?: now();
                $row->save();

                if (! $adaSebelumnya) $baru++;
            }
        }

        $this->info(sprintf('it:sync-git — %d commit dibaca, %d jejak baru, %d total di tabel%s.',
            $baris, $baru, ItGitRef::count(), $ticket ? " (tiket $ticket)" : ''));

        if ($lewat) {
            // Selisih ini WAJIB terlihat: kalau tidak, commit yang menyebut nomor
            // di BODY diam-diam hilang dan orang menyangka jejaknya lengkap.
            $this->warn(sprintf('%d commit menyebut nomor tiket hanya di BODY — tak dicatat. '
                . 'Tulis nomornya di baris pertama commit message supaya terlacak.', $lewat));
        }

        return self::SUCCESS;
    }

    /** refs/remotes/origin/feat/x → feat/x ; refs/heads/main → main */
    private function rapikanRef(string $ref): string
    {
        return preg_replace('#^refs/(heads/|remotes/[^/]+/|tags/)?#', '', $ref) ?: $ref;
    }
}
