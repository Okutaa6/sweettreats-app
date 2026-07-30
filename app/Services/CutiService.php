<?php

namespace App\Services;

use App\Models\AttRequest;
use App\Models\CutiMutasi;
use App\Models\CutiPeriode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mesin jatah cuti per kontrak 12 bulan — docs/rencana/rencana-jatah-cuti.md.
 *
 * Prinsip: user TANPA periode berjalan = di luar sistem cuti (eksekutif) —
 * semua method diam-diam no-op untuk mereka, tidak pernah error.
 * Saldo boleh minus (= "ganti hari"); sistem tidak memblokir apa pun.
 */
class CutiService
{
    public const JATAH_KONTRAK = 12.0;

    /** Periode aktif user, atau null (= di luar sistem cuti). */
    public function periodeBerjalan(string $userId): ?CutiPeriode
    {
        return CutiPeriode::berjalan()->where('user_id', $userId)->first();
    }

    /** Sisa cuti user, atau null kalau di luar sistem (tampilkan "—"). */
    public function sisa(string $userId): ?float
    {
        return $this->periodeBerjalan($userId)?->sisa();
    }

    /**
     * Pengajuan cuti di-ACC final → potong jatah, 1 mutasi per tanggal.
     * Dipanggil dari AttendanceRequestWorkflow saat status jadi 'approved'.
     *
     * v1: setiap hari kalender dalam rentang = 1 hari potong (belum melihat
     * jadwal kerja/hari libur — batasan sadar, lihat rencana §Batas).
     * Idempotent: UNIQUE (sumber, ref_id, tanggal) + guard exists — ACC ulang
     * / race tidak dobel-potong.
     */
    public function potongDariPengajuan(AttRequest $req): void
    {
        if ($req->tipe !== 'cuti' || $req->status !== 'approved') return;

        $periode = $this->periodeBerjalan($req->user_id);
        if (! $periode) return; // eksekutif / belum di-import — jangan meledak

        $from = Carbon::parse($req->tanggal_from)->startOfDay();
        $to   = Carbon::parse($req->tanggal_to ?? $req->tanggal_from)->startOfDay();
        if ($to->lt($from)) return;

        for ($t = $from->copy(); $t->lte($to); $t->addDay()) {
            $sudah = CutiMutasi::where('sumber', 'pengajuan')
                ->where('ref_id', (string) $req->id)
                ->whereDate('tanggal', $t)->exists();
            if ($sudah) continue;

            try {
                CutiMutasi::create([
                    'periode_id' => $periode->id,
                    'user_id'    => $req->user_id,
                    'tanggal'    => $t->toDateString(),
                    'hari'       => 1,
                    'jenis'      => 'cuti',
                    'sumber'     => 'pengajuan',
                    'ref_id'     => (string) $req->id,
                    'keterangan' => 'Pengajuan cuti di-ACC',
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // 23000 = kalah race dgn proses lain yang menulis tanggal sama
                // untuk pengajuan sama — hasil akhirnya benar, lanjut saja.
                if (($e->errorInfo[0] ?? null) !== '23000') throw $e;
            }
        }
    }

    /**
     * Cuti bersama: potong 1 hari SEMUA pemegang periode berjalan, kecuali
     * $kecualiUserIds (kasus ganti hari dll). Return jumlah orang terpotong.
     * Idempotent per (tanggal, orang): sudah pernah dipotong cuti_bersama di
     * tanggal itu → dilewati.
     */
    public function cutiBersama(string $tanggal, string $label, array $kecualiUserIds = [], ?string $olehUserId = null, float $hari = 1.0, ?string $batchRef = null): int
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $n = 0;

        $periodes = CutiPeriode::berjalan()
            ->when($kecualiUserIds, fn ($q) => $q->whereNotIn('user_id', $kecualiUserIds))
            ->get();

        foreach ($periodes as $p) {
            $sudah = CutiMutasi::where('user_id', $p->user_id)
                ->where('jenis', 'cuti_bersama')->whereDate('tanggal', $tanggal)->exists();
            if ($sudah) continue;

            try {
                CutiMutasi::create([
                    'periode_id' => $p->id,
                    'user_id'    => $p->user_id,
                    'tanggal'    => $tanggal,
                    'hari'       => $hari,
                    'jenis'      => 'cuti_bersama',
                    'sumber'     => 'massal',
                    // ref batch → tombol Urungkan menghapus TEPAT batch ini,
                    // bukan batch lain yang kebetulan tanggal+label sama; plus
                    // UNIQUE (sumber,ref,user,tanggal) menjaga dobel-klik.
                    'ref_id'     => $batchRef,
                    'keterangan' => $label,
                    'dibuat_oleh'=> $olehUserId,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if (($e->errorInfo[0] ?? null) !== '23000') throw $e;
                continue; // kalah race dobel-submit — orangnya sudah terpotong
            }
            $n++;
        }

        return $n;
    }

    /**
     * Koreksi per orang (mis. ganti hari atas cuti bersama): hari NEGATIF
     * mengembalikan jatah.
     */
    public function koreksi(string $userId, string $tanggal, float $hari, string $keterangan, ?string $olehUserId = null): ?CutiMutasi
    {
        $periode = $this->periodeBerjalan($userId);
        if (! $periode) return null;

        return CutiMutasi::create([
            'periode_id' => $periode->id,
            'user_id'    => $userId,
            'tanggal'    => Carbon::parse($tanggal)->toDateString(),
            'hari'       => $hari,
            'jenis'      => $hari < 0 ? 'ganti_hari' : 'koreksi',
            'sumber'     => 'manual',
            'keterangan' => $keterangan,
            'dibuat_oleh'=> $olehUserId,
        ]);
    }

    /**
     * Tutup periode aktif & buka kontrak baru. Aturan reset (ACC 2026-07-25):
     * sisa positif HANGUS (mulai lagi dari 12), utang (minus) KEBAWA
     * (saldo awal baru = 12 + sisa yang negatif).
     * $mulai null = tanggal kontrak belum diketahui (reset manual by Finance).
     */
    public function mulaiKontrakBaru(string $userId, ?string $mulai = null): ?CutiPeriode
    {
        return DB::transaction(function () use ($userId, $mulai) {
            $lama = $this->periodeBerjalan($userId);
            if (! $lama) return null; // di luar sistem cuti

            $sisaLama = $lama->sisa();
            $lama->update(['berjalan' => null]);

            $mulaiC = $mulai ? Carbon::parse($mulai) : null;

            return CutiPeriode::create([
                'user_id'       => $userId,
                'saldo_awal'    => self::JATAH_KONTRAK + min($sisaLama, 0),
                'kontrak_mulai' => $mulaiC?->toDateString(),
                'kontrak_akhir' => $mulaiC?->copy()->addMonths(12)->subDay()->toDateString(),
                'berjalan'      => 1,
            ]);
        });
    }

    /**
     * Scheduler harian: periode ber-tanggal yang sudah lewat kontrak_akhir →
     * reset otomatis (kontrak baru mulai sehari setelah akhir lama).
     * Periode tanpa tanggal tidak pernah tersentuh (dorman by design).
     * Return jumlah karyawan yang di-reset.
     */
    public function resetKontrakKedaluwarsa(): int
    {
        $n = 0;
        $jatuhTempo = CutiPeriode::berjalan()
            ->whereNotNull('kontrak_akhir')
            ->whereDate('kontrak_akhir', '<', Carbon::today())
            ->get();

        foreach ($jatuhTempo as $p) {
            $this->mulaiKontrakBaru(
                $p->user_id,
                Carbon::parse($p->kontrak_akhir)->addDay()->toDateString()
            );
            $n++;
        }

        return $n;
    }
}
