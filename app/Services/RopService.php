<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\RopCostCentre;
use App\Models\RopRequest;
use App\Models\RopStatusLog;
use Illuminate\Support\Facades\DB;

/**
 * RopService
 *
 * Satu-satunya pintu perubahan status pengajuan ROP. Controller TIDAK boleh
 * meng-update kolom `status` sendiri — lewat sini semua, supaya:
 *   1. rop_status_log selalu ikut tertulis (timeline & audit tidak pernah bolong),
 *   2. transisi ilegal tertolak di satu tempat (peta TRANSISI di bawah),
 *   3. notifikasi tidak lupa dikirim.
 *
 * Rujukan alur: docs/rencana/rencana-pengajuan-rop.md §3.
 */
class RopService
{
    /**
     * Role yang meng-ACC. Notifikasi pengajuan baru dikirim ke sini.
     *
     * DAFTAR sejak 2026-08-21 (dulu satu teks). `Konsultan Finance` ikut karena
     * keputusan Ekky: konsultan = bidangnya UTUH, dan menyetujui ROP itu memang
     * tugas Finance — bukan wewenang tambahan. Larangan "konsultan tak boleh
     * ACC" berlaku untuk PENGAJUAN ORANG (cuti/lembur/izin), dan itu ditegakkan
     * di tempat lain: nol baris di att_approval_rule.
     *
     * AdminNotification::notifyRole() menerima SATU nama role dan mencocokkannya
     * dengan session('group_name'), jadi pemanggilnya harus mengulang per nama —
     * bukan mengoper array. Lihat pemakaiannya di bawah dan di RopSapu.
     */
    public const ROLE_PENYETUJU = ['Finance', 'Konsultan Finance'];

    /**
     * Role yang boleh membuka layar antrian Finance, di luar admin lintas-cabang
     * (canSeeAllBranches: Super Admin, Admin General, Admin Backup, Finance).
     *
     * IT ikut karena merekalah yang memasang & memperbaiki alur ini — dan
     * karena node menunya memang di-grant ke IT di
     * database/sql/2026_07_31_rop_access.sql. Kalau daftar ini dipersempit,
     * PERSEMPIT JUGA grant di SQL itu: menu yang tampil tapi berujung 403
     * adalah kegagalan senyap, persis yang diburu detektor Pusat Akun.
     */
    public const ROLE_ANTRIAN = ['Head IT', 'IT', 'Konsultan IT'];

    /** Rekap boleh dibaca Executive juga (rencana §9) — hanya membaca. */
    public const ROLE_REKAP = ['Head IT', 'IT', 'Executive', 'Konsultan IT'];

    /** Pengajuan diam selama ini (hari) → dikadaluarsakan otomatis. */
    public const HARI_KADALUARSA = 30;

    /** Antrian menunggu lebih lama dari ini (hari) → Finance diingatkan. */
    public const HARI_REMINDER = 3;

    /**
     * Jendela "Urungkan" setelah ACC (detik). Toast di layar hidup ~5 detik;
     * di sini sengaja lebih longgar supaya Finance yang sadar salah klik
     * sedetik setelah toast hilang masih tertolong — yang menjaga bukan
     * sempitnya waktu, tapi syarat statusnya masih `disetujui`.
     */
    public const DETIK_URUNG = 120;

    /**
     * Di bawah nominal ini, pengeluaran biasanya lewat petty cash cabang
     * (tunai, langsung dicatat kasir) — bukan ROP yang dibayar transfer.
     * Dipakai sebagai SARAN di form, bukan larangan: batasnya aturan
     * operasional yang bisa bergeser, dan pengaju yang tahu konteksnya.
     */
    public const AMBANG_PETTY_CASH = 300000;

    /**
     * Transisi yang diizinkan. Kunci = status sekarang, isi = tujuan yang sah.
     * Status akhir (selesai) sengaja tidak punya tujuan.
     */
    private const TRANSISI = [
        RopRequest::STATUS_DIAJUKAN   => [RopRequest::STATUS_DISETUJUI, RopRequest::STATUS_DITOLAK, RopRequest::STATUS_KADALUARSA],
        RopRequest::STATUS_DISETUJUI  => [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_DITOLAK],
        RopRequest::STATUS_DIBAYAR    => [RopRequest::STATUS_SELESAI],
        RopRequest::STATUS_DITOLAK    => [],
        RopRequest::STATUS_SELESAI    => [],
        RopRequest::STATUS_KADALUARSA => [],
    ];

    /* ═════════════════════ Penomoran ═════════════════════ */

    /**
     * Ambil nomor berikutnya untuk sebuah cost centre: ROP/{CC}/{YYMM}/{NNN}.
     *
     * Atomik lewat `LAST_INSERT_ID(last_no + 1)` — dua orang menekan Kirim di
     * detik yang sama tetap dapat nomor berbeda. Jangan diganti MAX(number)+1.
     * Sekuens reset sendiri tiap bulan karena `periode` bagian dari primary key.
     */
    public function nomorBaru(string $costCentre, ?\DateTimeInterface $saat = null): string
    {
        $saat    = $saat ?: now();
        $periode = $saat->format('ym');

        // LAST_INSERT_ID(1) di VALUES itu WAJIB, bukan gaya-gayaan: tabel ini
        // tanpa AUTO_INCREMENT, jadi INSERT baris baru tidak menyentuh
        // LAST_INSERT_ID() dan nilai sisa pemanggilan sebelumnya akan terbaca
        // (cost centre baru sempat dapat nomor 003, bukan 001).
        DB::statement(
            'INSERT INTO rop_counter (cost_centre, periode, last_no, updated_at)
             VALUES (?, ?, LAST_INSERT_ID(1), NOW())
             ON DUPLICATE KEY UPDATE last_no = LAST_INSERT_ID(last_no + 1), updated_at = NOW()',
            [$costCentre, $periode]
        );

        $row = DB::selectOne('SELECT LAST_INSERT_ID() AS no');
        $urut = (int) ($row->no ?? 0);

        if ($urut < 1) { // jaring pengaman kalau driver tidak meneruskan nilainya
            $urut = (int) DB::table('rop_counter')
                ->where('cost_centre', $costCentre)->where('periode', $periode)
                ->value('last_no') ?: 1;
        }

        return sprintf('ROP/%s/%s/%03d', $costCentre, $periode, $urut);
    }

    /**
     * Nomor yang AKAN didapat, untuk ditampilkan di header form — hanya
     * membaca, tidak menaikkan sekuens. Nomor sungguhan tetap diterbitkan saat
     * simpan; kalau ada yang mengirim duluan, yang tampil di layar bisa meleset
     * satu dan itu memang konsekuensi yang diterima (menyetok nomor di depan
     * akan meninggalkan lubang tiap kali form ditinggalkan).
     */
    public function nomorBerikutnya(string $costCentre, ?\DateTimeInterface $saat = null): string
    {
        $saat    = $saat ?: now();
        $periode = $saat->format('ym');

        $urut = (int) DB::table('rop_counter')
            ->where('cost_centre', $costCentre)
            ->where('periode', $periode)
            ->value('last_no');

        return sprintf('ROP/%s/%s/%03d', $costCentre, $periode, $urut + 1);
    }

    /* ═════════════════════ Membuat pengajuan ═════════════════════ */

    /**
     * Buat pengajuan baru (status: diajukan) + notifikasi ke Finance.
     *
     * $data: cost_centre, vendor_id|pay_to_text, description, amount,
     *        needed_by, is_urgent, attachment_path, resubmit_of
     */
    public function buat(array $data, string $requesterId): RopRequest
    {
        return DB::transaction(function () use ($data, $requesterId) {
            $cc = RopCostCentre::findOrFail($data['cost_centre']);

            $rop = RopRequest::create([
                'number'          => $this->nomorBaru($cc->kode),
                'requester_id'    => $requesterId,
                'cost_centre'     => $cc->kode,
                'raw_branch_id'   => $cc->raw_branch_id,
                'vendor_id'       => $data['vendor_id'] ?? null,
                'pay_to_text'     => $data['pay_to_text'] ?? null,
                'description'     => $data['description'],
                'amount'          => $data['amount'],
                'needed_by'       => $data['needed_by'] ?? null,
                'is_urgent'       => ! empty($data['is_urgent']),
                'attachment_path' => $data['attachment_path'] ?? null,
                'resubmit_of'     => $data['resubmit_of'] ?? null,
                'status'          => RopRequest::STATUS_DIAJUKAN,
            ]);

            $this->catat($rop, null, RopRequest::STATUS_DIAJUKAN, $requesterId,
                $rop->resubmit_of ? 'Pengajuan ulang dari #' . $rop->asal?->number : null);

            foreach (self::ROLE_PENYETUJU as $role) {
                AdminNotification::notifyRole(
                    $role,
                    'rop_baru',
                    'Pengajuan baru: ' . $rop->number,
                    $this->ringkasan($rop) . ' — dari ' . ($rop->requester?->nama ?: 'pengaju'),
                    url('finance/rop'),
                );
            }

            return $rop;
        });
    }

    /**
     * Cari pengajuan lain yang mirip & masih berjalan — peringatan anti bayar dobel.
     *
     * Mirip = penerima sama (vendor terdaftar atau teks, tanpa peduli besar-kecil
     * huruf) DAN nominal berdekatan: selisih ≤ 15% ATAU ≤ Rp 50.000. Dua ambang
     * dipakai bersamaan karena persentase saja terlalu ketat untuk nominal kecil
     * (galon Rp 340rb vs Rp 360rb) dan terlalu longgar untuk nominal besar.
     *
     * Hanya melihat 30 hari terakhir supaya belanja rutin bulanan tidak
     * selamanya dianggap dobel.
     */
    public function cariSerupa(array $data, ?int $kecualiId = null): ?RopRequest
    {
        $q = RopRequest::query()
            ->whereIn('status', [RopRequest::STATUS_DIAJUKAN, RopRequest::STATUS_DISETUJUI])
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['requester', 'vendor']);

        if (! empty($data['vendor_id'])) {
            $q->where('vendor_id', $data['vendor_id']);
        } elseif (! empty($data['pay_to_text'])) {
            $teks = trim($data['pay_to_text']);
            $q->where(function ($w) use ($teks) {
                $w->where('pay_to_text', 'like', '%' . $teks . '%')
                  ->orWhereHas('vendor', fn ($v) => $v->where('nama', 'like', '%' . $teks . '%'));
            });
        } else {
            return null;
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount > 0) {
            $toleransi = max($amount * 0.15, 50000);
            $q->whereBetween('amount', [$amount - $toleransi, $amount + $toleransi]);
        }
        if ($kecualiId) {
            $q->where('id', '!=', $kecualiId);
        }

        return $q->latest('id')->first();
    }

    /**
     * Bahan agar Finance jarang mengetik saat memilih akun beban:
     *   - `saran`   : akun yang terakhir dipakai untuk penerima yang SAMA
     *                 (paling sering benar — belanja berulang ke vendor itu),
     *   - `terakhir`: 4 akun terakhir yang dipakai lintas pengajuan.
     * Keduanya hanya dari buku cost centre yang sama, supaya tidak menyarankan
     * akun milik buku lain yang nomornya kebetulan sama.
     */
    public function bahanAkun(RopRequest $rop): array
    {
        $sebuku = fn () => RopRequest::query()
            ->where('cost_centre', $rop->cost_centre)
            ->whereNotNull('coa_no')
            ->where('id', '!=', $rop->id);

        $saran = (clone $sebuku())
            ->when(
                $rop->vendor_id,
                fn ($q) => $q->where('vendor_id', $rop->vendor_id),
                fn ($q) => $q->where('pay_to_text', $rop->pay_to_text)
            )
            ->latest('reviewed_at')
            ->first(['coa_no', 'coa_name', 'amount', 'paid_at', 'number']);

        $terakhir = (clone $sebuku())
            ->latest('reviewed_at')
            ->get(['coa_no', 'coa_name'])
            ->unique('coa_no')
            ->take(4)
            ->values();

        return [
            'saran' => $saran ? [
                'no'      => $saran->coa_no,
                'name'    => $saran->coa_name,
                'nomor'   => $saran->number,
                'nominal' => (float) $saran->amount,
                'tanggal' => $saran->paid_at?->format('d M Y'),
            ] : null,
            'terakhir' => $terakhir->map(fn ($r) => ['no' => $r->coa_no, 'name' => $r->coa_name])->all(),
        ];
    }

    /* ═════════════════════ Transisi status ═════════════════════ */

    /**
     * Finance menyetujui + menetapkan akun beban (coa_no wajib, di-snapshot
     * bersama namanya karena staging Accurate bisa berubah saat resync).
     */
    public function setujui(RopRequest $rop, array $data, string $actorId): RopRequest
    {
        return $this->pindah($rop, RopRequest::STATUS_DISETUJUI, $actorId, $data['catatan'] ?? null, [
            'coa_no'      => $data['coa_no'],
            'coa_name'    => $data['coa_name'] ?? null,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
        ], 'rop_disetujui', 'Pengajuan disetujui', function (RopRequest $r) {
            return $r->number . ' (' . $this->rupiah($r->amount) . ') disetujui, menunggu pembayaran.';
        });
    }

    public function tolak(RopRequest $rop, string $alasan, string $actorId): RopRequest
    {
        return $this->pindah($rop, RopRequest::STATUS_DITOLAK, $actorId, $alasan, [
            'reject_reason' => $alasan,
            'reviewed_by'   => $actorId,
            'reviewed_at'   => now(),
        ], 'rop_ditolak', 'Pengajuan ditolak', function (RopRequest $r) use ($alasan) {
            return $r->number . ' ditolak. Alasan: ' . $alasan;
        });
    }

    /**
     * Finance menandai sudah ditransfer. paid_at boleh mundur dari hari input
     * (transfer kemarin, dicatat hari ini).
     */
    public function tandaiDibayar(RopRequest $rop, array $data, string $actorId): RopRequest
    {
        return $this->pindah($rop, RopRequest::STATUS_DIBAYAR, $actorId, $data['catatan'] ?? null, [
            'paid_by'         => $actorId,
            'paid_at'         => $data['paid_at'] ?? now(),
            'paid_proof_path' => $data['paid_proof_path'] ?? null,
        ], 'rop_dibayar', 'Pengajuan sudah dibayar', function (RopRequest $r) {
            return $r->number . ' (' . $this->rupiah($r->amount) . ') sudah ditransfer. '
                 . 'Konfirmasi kalau barang/dana sudah diterima.';
        });
    }

    /**
     * Pengaju konfirmasi barang/dana diterima (pengganti kolom "Received by").
     * Boleh juga ditutup Finance — $actorId yang menentukan siapa.
     */
    public function tandaiSelesai(RopRequest $rop, array $data, string $actorId): RopRequest
    {
        return $this->pindah($rop, RopRequest::STATUS_SELESAI, $actorId, $data['catatan'] ?? null, [
            'completed_by' => $actorId,
            'completed_at' => now(),
            'nota_path'    => $data['nota_path'] ?? $rop->nota_path,
        ], null, null, null);
    }

    /**
     * Dikadaluarsakan oleh `rop:sapu` — actor NULL karena pelakunya sistem.
     */
    public function kadaluarsakan(RopRequest $rop): RopRequest
    {
        return $this->pindah($rop, RopRequest::STATUS_KADALUARSA, null,
            'Otomatis: tidak ada tindakan selama ' . self::HARI_KADALUARSA . ' hari', [
                'expired_at' => now(),
            ], 'rop_kadaluarsa', 'Pengajuan kadaluarsa', function (RopRequest $r) {
                return $r->number . ' kadaluarsa karena belum ditindak '
                     . self::HARI_KADALUARSA . ' hari. Ajukan ulang kalau masih dibutuhkan.';
            });
    }

    /**
     * Urungkan persetujuan yang baru saja dilakukan (tombol "Urungkan" di toast).
     *
     * Ini SATU-SATUNYA transisi mundur di sistem, dan sengaja tidak dimasukkan
     * ke peta TRANSISI: kalau disetujui→diajukan jadi transisi biasa, ia bisa
     * dipanggil dari mana saja kapan saja. Di sini syaratnya ketat:
     *   • status harus masih `disetujui` — sudah dibayar berarti uang keluar,
     *     tidak ada lagi yang bisa diurungkan dari layar,
     *   • dilakukan oleh orang yang menyetujui,
     *   • masih dalam jendela waktu.
     *
     * Jejaknya TIDAK dihapus. Baris log lama tetap ada dan ditambah baris baru
     * "dibatalkan" — audit yang jujur lebih berguna daripada riwayat yang
     * kelihatan rapi.
     */
    public function urungkanPersetujuan(RopRequest $rop, string $actorId): RopRequest
    {
        return DB::transaction(function () use ($rop, $actorId) {
            /** @var RopRequest $segar */
            $segar = RopRequest::lockForUpdate()->findOrFail($rop->id);

            if ($segar->status !== RopRequest::STATUS_DISETUJUI) {
                abort(409, $segar->status === RopRequest::STATUS_DIBAYAR
                    ? 'Pengajuan ini sudah ditandai dibayar — tidak bisa diurungkan dari sini.'
                    : 'Pengajuan ini sudah tidak berstatus disetujui.');
            }
            if ((string) $segar->reviewed_by !== $actorId) {
                abort(403, 'Hanya yang menyetujui yang bisa mengurungkan.');
            }
            if (! $segar->reviewed_at || $segar->reviewed_at->diffInSeconds(now()) > self::DETIK_URUNG) {
                abort(409, 'Waktu untuk mengurungkan sudah lewat. Kalau perlu, tolak pengajuannya dengan alasan.');
            }

            $segar->update([
                'status'      => RopRequest::STATUS_DIAJUKAN,
                'coa_no'      => null,
                'coa_name'    => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $this->catat($segar, RopRequest::STATUS_DISETUJUI, RopRequest::STATUS_DIAJUKAN,
                $actorId, 'Persetujuan diurungkan');

            // Pengaju sudah terlanjur diberi tahu "disetujui" — dikoreksi
            // terang-terangan, bukan didiamkan.
            if ($segar->requester_id) {
                AdminNotification::notify(
                    'rop_urung',
                    'Persetujuan ditarik kembali',
                    $segar->number . ' kembali menunggu keputusan Finance.',
                    url('pengajuan/' . $segar->id),
                    null,
                    (string) $segar->requester_id,
                );
            }

            return $segar->fresh();
        });
    }

    /**
     * Urungkan PENOLAKAN yang baru saja dilakukan — cermin dari
     * urungkanPersetujuan(), syarat ketat yang sama: masih `ditolak`,
     * pelakunya orang yang menolak, masih dalam jendela waktu. Jejak log
     * tidak dihapus; pengaju yang terlanjur diberi tahu "ditolak" dikoreksi
     * terang-terangan.
     */
    public function urungkanPenolakan(RopRequest $rop, string $actorId): RopRequest
    {
        return DB::transaction(function () use ($rop, $actorId) {
            /** @var RopRequest $segar */
            $segar = RopRequest::lockForUpdate()->findOrFail($rop->id);

            if ($segar->status !== RopRequest::STATUS_DITOLAK) {
                abort(409, 'Pengajuan ini sudah tidak berstatus ditolak.');
            }
            if ((string) $segar->reviewed_by !== $actorId) {
                abort(403, 'Hanya yang menolak yang bisa mengurungkan.');
            }
            if (! $segar->reviewed_at || $segar->reviewed_at->diffInSeconds(now()) > self::DETIK_URUNG) {
                abort(409, 'Waktu untuk mengurungkan sudah lewat.');
            }

            $segar->update([
                'status'        => RopRequest::STATUS_DIAJUKAN,
                'reject_reason' => null,
                'reviewed_by'   => null,
                'reviewed_at'   => null,
            ]);

            $this->catat($segar, RopRequest::STATUS_DITOLAK, RopRequest::STATUS_DIAJUKAN,
                $actorId, 'Penolakan diurungkan');

            if ($segar->requester_id) {
                AdminNotification::notify(
                    'rop_urung',
                    'Penolakan ditarik kembali',
                    $segar->number . ' kembali menunggu keputusan Finance.',
                    url('pengajuan/' . $segar->id),
                    null,
                    (string) $segar->requester_id,
                );
            }

            return $segar->fresh();
        });
    }

    /**
     * Pembatalan oleh pengaju sendiri — hanya selama belum di-ACC.
     * Dipakai soft delete, bukan status baru: yang dibatalkan tidak perlu
     * ikut muncul di laporan mana pun, tapi jejaknya tetap ada di DB.
     */
    public function batalkan(RopRequest $rop, string $actorId): void
    {
        DB::transaction(function () use ($rop, $actorId) {
            if ($rop->status !== RopRequest::STATUS_DIAJUKAN) {
                abort(409, 'Pengajuan yang sudah diproses tidak bisa dibatalkan.');
            }
            $this->catat($rop, $rop->status, $rop->status, $actorId, 'Dibatalkan oleh pengaju');
            $rop->delete();
        });
    }

    /* ═════════════════════ Dapur ═════════════════════ */

    /**
     * Mesin transisi: kunci baris, validasi transisi, update kolom, tulis log,
     * kirim notifikasi ke pengaju. Semua metode status di atas lewat sini.
     *
     * $tipeNotif NULL = tidak ada notifikasi (mis. saat pengaju menutup
     * pengajuannya sendiri — tidak perlu memberi tahu dirinya sendiri).
     */
    private function pindah(
        RopRequest $rop,
        string $ke,
        ?string $actorId,
        ?string $catatan,
        array $kolom,
        ?string $tipeNotif,
        ?string $judulNotif,
        ?callable $isiNotif
    ): RopRequest {
        return DB::transaction(function () use ($rop, $ke, $actorId, $catatan, $kolom, $tipeNotif, $judulNotif, $isiNotif) {
            /** @var RopRequest $segar */
            $segar = RopRequest::lockForUpdate()->findOrFail($rop->id);
            $dari  = $segar->status;

            if (! in_array($ke, self::TRANSISI[$dari] ?? [], true)) {
                abort(409, 'Pengajuan ini sudah berstatus "' . ($segar->labelStatus()) . '", tidak bisa diubah lagi.');
            }

            $segar->update($kolom + ['status' => $ke]);
            $this->catat($segar, $dari, $ke, $actorId, $catatan);

            if ($tipeNotif && $segar->requester_id) {
                AdminNotification::notify(
                    $tipeNotif,
                    $judulNotif,
                    $isiNotif ? $isiNotif($segar) : null,
                    url('pengajuan/' . $segar->id),
                    null,
                    (string) $segar->requester_id,
                );
            }

            return $segar->fresh();
        });
    }

    private function catat(RopRequest $rop, ?string $dari, string $ke, ?string $actorId, ?string $catatan): void
    {
        RopStatusLog::create([
            'rop_request_id' => $rop->id,
            'dari_status'    => $dari,
            'ke_status'      => $ke,
            'actor_id'       => $actorId,
            'catatan'        => $catatan,
            'created_at'     => now(),
        ]);
    }

    private function ringkasan(RopRequest $rop): string
    {
        return $this->rupiah($rop->amount) . ' — ' . $rop->namaPenerima();
    }

    private function rupiah($angka): string
    {
        return 'Rp ' . number_format((float) $angka, 0, ',', '.');
    }
}
