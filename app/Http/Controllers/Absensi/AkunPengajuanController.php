<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRequest;
use App\Services\AttendanceReconciler;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Akun Saya → Ajukan" — form pengajuan absen (MOBILE, self-service).
 *
 * Pengaju SELALU dirinya sendiri (session id) — sisi-GUA, tak ada gating role/cabang.
 * Route di segment(1) = 'akun' (node sudah di-grant semua role di AbsensiAccessPathSeeder),
 * jadi tak butuh access node / rebuild baru.
 *
 * Lima jenis form (§5.2): cuti | izin | libur | lembur | wfh. Deklarasi manusia =
 * kebenaran tertinggi (§4). Setelah create, alur diserahkan ke AttendanceRequestWorkflow::
 * submit() → rantai approver (att_approver) menentukan pending_l1 / langsung approved.
 */
class AkunPengajuanController extends Controller
{
    /** Jenis form + apakah butuh rentang jam (lembur/izin sebagian). */
    public const TIPE = [
        'cuti'    => 'Cuti',
        'izin'    => 'Izin',
        'libur'   => 'Libur / Tukar Hari',
        'lembur'  => 'Lembur',
        'wfh'     => 'Kerja dari Rumah',
        'koreksi' => 'Koreksi Absen',
    ];

    /** Jatah kerja dari rumah per bulan kalender, dalam HARI (§3.3). */
    public const WFH_JATAH_BULANAN = 2;

    /** Pilihan "apa yang keliru?" di form koreksi (ikut handoff). */
    public const KOREKSI_ALASAN = [
        'Lupa scan masuk',
        'Lupa scan pulang',
        'Sidik jari gagal terbaca',
        'Jam tercatat keliru',
        'Lainnya',
    ];

    private function userId(): string
    {
        $id = (string) session('id');
        if (! $id) abort(403);
        return $id;
    }

    public function create(Request $request)
    {
        $userId = $this->userId();

        $tipeList = self::TIPE;
        // pra-isi tipe kalau datang dari tombol tipe tertentu (mis. ?tipe=lembur)
        $tipe = $request->input('tipe');
        if ($tipe && ! isset($tipeList[$tipe])) $tipe = null;

        // Halaman penuh ini dulu tak mengirim wfhUsed sama sekali, jadi partial
        // menambalnya dgn 0 → banner selalu berbunyi "sisa 2 lagi" walau jatahnya
        // habis, lalu server menolak. Salah info, bukan sekadar info hilang.
        $wfhUsed = self::hariWfhTerpakaiBulan($userId, Carbon::now());

        return view('absensi.akun.pengajuan', compact('tipeList', 'tipe', 'wfhUsed'));
    }

    /** Detail satu pengajuan + rantai persetujuan (buat drawer riwayat). Owner-scoped. */
    public function show(Request $request, $id)
    {
        $userId = $this->userId();
        $req = AttRequest::where('id', $id)->where('user_id', $userId)->firstOrFail();

        $wf = new AttendanceRequestWorkflow();
        $levels    = $wf->chainFor($req->user_id, $req->tipe);        // [[uid],...]
        $approvals = $req->approvals()->get()->keyBy('level');        // level(1-based) => record
        $curLvl    = $req->status === 'pending_l2' ? 2 : ($req->status === 'pending_l1' ? 1 : null);

        $timeline = [];
        foreach ($levels as $i => $uids) {
            $lvl  = $i + 1;
            $act  = $approvals[$lvl] ?? null;
            $names = collect($uids)->map(fn ($u) => optional(\App\Models\Users::find($u))->nama)->filter()->implode(' / ');

            if ($act) {
                $state = $act->action === 'approve' ? 'disetujui' : 'ditolak';
            } elseif ($req->status === 'rejected') {
                $state = 'batal';
            } elseif ($curLvl === $lvl) {
                $state = 'menunggu';
            } elseif ($curLvl && $lvl < $curLvl) {
                $state = 'disetujui';
            } elseif ($req->status === 'approved') {
                $state = 'disetujui';
            } else {
                $state = 'belum';
            }

            $timeline[] = [
                'level'   => $lvl,
                'names'   => $names ?: '—',
                'state'   => $state,
                'by'      => $act ? (optional(\App\Models\Users::find($act->approver_user_id))->nama) : null,
                'at'      => $act?->acted_at,
                'catatan' => $act?->catatan,
            ];
        }

        return view('absensi.akun._riwayat_detail', compact('req', 'timeline'));
    }

    /** Prefill jam lembur dari scan (§7 Fase C). JSON buat form. */
    public function prefillLembur(Request $request)
    {
        $userId = $this->userId();
        $date = $request->input('tanggal');
        if (! $date) return response()->json(['ok' => false]);

        $s = (new AttendanceReconciler())->suggestLembur($userId, $date);
        if (! $s) {
            return response()->json(['ok' => false, 'reason' => 'Tidak ada data absen mesin yang cocok di tanggal ini — isi manual ya.']);
        }
        return response()->json(['ok' => true] + $s);
    }

    public function store(Request $request)
    {
        $userId = $this->userId();

        $data = $request->validate([
            'tipe'           => 'required|in:cuti,izin,libur,lembur,wfh,koreksi',
            'sub_tipe'       => 'nullable|in:jam,tambah_hari',
            'tanggal_from'   => 'required|date',
            'tanggal_to'     => 'required_unless:tipe,koreksi|nullable|date|after_or_equal:tanggal_from',
            'jam_from'       => 'nullable|date_format:H:i',
            'jam_to'         => 'nullable|date_format:H:i|after:jam_from',
            'alasan'         => 'required_unless:tipe,koreksi|nullable|string|max:2000',
            'koreksi_alasan' => 'required_if:tipe,koreksi|nullable|string|max:100',
            'foto'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'tanggal_to.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'jam_to.after'              => 'Jam selesai harus setelah jam mulai.',
            'alasan.required_unless'    => 'Alasan wajib diisi — yang menyetujui butuh konteksnya.',
            'koreksi_alasan.required_if' => 'Pilih dulu apa yang keliru.',
        ]);

        // Lembur SELALU 1 hari. claimed_menit dihitung dari SATU pasang jam
        // (jam_from–jam_to), jadi rentang banyak hari tak punya arti: menitnya
        // akan dihitung ULANG untuk tiap tanggal di rentang itu. Klaim 2 jam
        // untuk 1–3 Agustus jadi 6 jam dibayar, dan rekap Finance tetap
        // menampilkan 2 jam sehingga selisihnya tak terlihat sebelum tutup buku.
        if ($data['tipe'] === 'lembur') {
            $data['tanggal_to'] = $data['tanggal_from'];
        }

        // Koreksi absen: selalu 1 hari, alasan dirakit dari pilihan + catatan,
        // dan tak boleh dobel untuk tanggal yang sama (biar antrean tak banjir).
        if ($data['tipe'] === 'koreksi') {
            if (! in_array($data['koreksi_alasan'], self::KOREKSI_ALASAN, true)) {
                return back()->withInput()->with('error', 'Pilihan koreksi tidak dikenal.');
            }
            $data['tanggal_to'] = $data['tanggal_from'];

            $catatan = trim((string) ($data['alasan'] ?? ''));
            $data['alasan'] = $data['koreksi_alasan'] . ($catatan !== '' ? ' — ' . $catatan : '');

            $dobel = AttRequest::where('user_id', $userId)
                ->where('tipe', 'koreksi')
                ->where('tanggal_from', $data['tanggal_from'])
                ->whereIn('status', ['approved', 'pending_l1', 'pending_l2'])
                ->exists();
            if ($dobel) {
                return back()->with('error',
                    'Koreksi untuk tanggal ' . Carbon::parse($data['tanggal_from'])->translatedFormat('d M Y')
                    . ' sudah pernah dikirim dan masih diproses.');
            }
        }

        // Jatah kerja dari rumah: 2 HARI per bulan (§3.3). Cek dipindah ke dalam
        // transaksi store() supaya tak bisa dijebol dua kiriman berbarengan.

        // lembur: sub_tipe default 'jam', hitung menit dari rentang jam kalau ada,
        // lalu sanding klaim vs scan (INFORMASI buat approver, §2.11 — bukan vonis).
        $subTipe = null;
        $claimedMenit = null;
        $verifikasi = null;
        $scanEvidence = null;
        if ($data['tipe'] === 'lembur') {
            $subTipe = $data['sub_tipe'] ?? 'jam';
            if (! empty($data['jam_from']) && ! empty($data['jam_to'])) {
                $from = Carbon::createFromFormat('H:i', $data['jam_from']);
                $to   = Carbon::createFromFormat('H:i', $data['jam_to']);
                $claimedMenit = max(0, $to->diffInMinutes($from));
            }
            [$verifikasi, $scanEvidence] = (new AttendanceReconciler())
                ->checkLemburClaim($userId, $data['tanggal_from'], $claimedMenit, $subTipe);
        }

        // foto bukti — wajib secara SOP untuk lembur/wfh yg tak ada scan, tapi tak
        // dipaksa di sini (biar tak nge-block; approver yang menilai kelengkapan).
        $fotoName = null;
        if ($request->hasFile('foto')) {
            $dir = public_path('uploads/att_request');
            if (! is_dir($dir)) mkdir($dir, 0755, true);
            $file = $request->file('foto');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $fotoName = $userId . '_' . time() . '.' . $ext;
            $file->move($dir, $fotoName);
        }

        // Satu transaksi: kalau workflow menolak (mis. alur persetujuan belum
        // diatur), baris pengajuan ikut dibatalkan. Tanpa ini baris nyangkut
        // selamanya di status 'draft' — tak terlihat pengaju maupun atasan.
        try {
            $req = DB::transaction(function () use ($data, $userId, $subTipe, $claimedMenit, $scanEvidence, $verifikasi, $fotoName) {
                if ($data['tipe'] === 'wfh') {
                    $this->pastikanJatahWfhCukup($userId, $data['tanggal_from'], $data['tanggal_to']);
                }
                $r = $this->buatPengajuan($data, $userId, $subTipe, $claimedMenit, $scanEvidence, $verifikasi, $fotoName);
                return (new AttendanceRequestWorkflow())->submit($r);
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $pesan = $req->status === 'approved'
            ? 'Pengajuan tercatat dan langsung disetujui (tak ada atasan di atas kamu).'
            : 'Pengajuan terkirim, menunggu persetujuan atasan.';

        return redirect()->route('akun.absensi')->with('success', $pesan);
    }

    /**
     * Jatah kerja dari rumah = 2 HARI per bulan kalender.
     *
     * Versi lama menghitung JUMLAH BARIS FORM, bukan hari, dan cuma berpatokan
     * pada tanggal_from. Dua celah nyata:
     *   - satu form "1–31 Agustus" = 1 baris → lolos cek → 31 hari jadi WFH;
     *   - form "31 Juli–30 Agustus" memakan jatah JULI tapi memberi 30 hari di
     *     AGUSTUS, dan Agustus tetap terbaca 0 terpakai.
     *
     * Sekarang: hari yang diminta dipecah per bulan, lalu tiap bulan yang
     * tersentuh diperiksa sendiri-sendiri terhadap pemakaian yang sudah ada
     * (approved maupun masih menunggu). Dipanggil DI DALAM transaksi + lock
     * supaya dua kiriman berbarengan tak sama-sama lolos.
     *
     * @throws \RuntimeException bila jatah bulan mana pun terlampaui.
     */
    private function pastikanJatahWfhCukup(string $userId, string $dari, string $sampai): void
    {
        $mulai   = Carbon::parse($dari)->startOfDay();
        $selesai = Carbon::parse($sampai)->startOfDay();

        // hari yang diminta, dikelompokkan per bulan
        $diminta = [];
        for ($d = $mulai->copy(); $d->lte($selesai); $d->addDay()) {
            $bulan = $d->format('Y-m');
            $diminta[$bulan] = ($diminta[$bulan] ?? 0) + 1;
        }

        foreach ($diminta as $bulan => $jumlahDiminta) {
            $awal  = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfMonth();
            $akhir = $awal->copy()->endOfMonth();

            $terpakai = $this->hariWfhTerpakai($userId, $awal, $akhir, true);

            if ($terpakai + $jumlahDiminta > self::WFH_JATAH_BULANAN) {
                $sisa = max(0, self::WFH_JATAH_BULANAN - $terpakai);
                throw new \RuntimeException(
                    'Jatah kerja dari rumah ' . $awal->translatedFormat('F Y') . ' tidak cukup: '
                    . 'sisa ' . $sisa . ' hari, kamu minta ' . $jumlahDiminta . ' hari. Pengajuan tidak dibuat.'
                );
            }
        }
    }

    /**
     * Versi publik untuk dipakai layar lain (kartu akun / banner form), supaya
     * angka yang DITAMPILKAN dihitung dengan aturan yang sama persis dengan
     * yang DITEGAKKAN saat kirim — jangan sampai layar bilang "sisa 2" lalu
     * server menolak.
     */
    public static function hariWfhTerpakaiBulan(string $userId, Carbon $dalamBulan): int
    {
        return (new self())->hariWfhTerpakai(
            $userId,
            $dalamBulan->copy()->startOfMonth(),
            $dalamBulan->copy()->endOfMonth()
        );
    }

    /**
     * Berapa HARI kerja-dari-rumah yang sudah dipakai dalam satu bulan.
     * Menghitung irisan tiap form dengan bulan itu, jadi form yang melintasi
     * batas bulan dihitung proporsional di masing-masing bulan.
     */
    private function hariWfhTerpakai(string $userId, Carbon $awal, Carbon $akhir, bool $kunci = false): int
    {
        $q = AttRequest::where('user_id', $userId)
            ->where('tipe', 'wfh')
            ->whereIn('status', ['approved', 'pending_l1', 'pending_l2'])
            ->where('tanggal_from', '<=', $akhir->toDateString())
            ->where('tanggal_to', '>=', $awal->toDateString());

        if ($kunci) $q->lockForUpdate();

        $hari = 0;
        foreach ($q->get(['tanggal_from', 'tanggal_to']) as $r) {
            $a = Carbon::parse($r->tanggal_from)->startOfDay();
            $b = Carbon::parse($r->tanggal_to)->startOfDay();
            if ($a->lt($awal))  $a = $awal->copy();
            if ($b->gt($akhir)) $b = $akhir->copy();
            $hari += $a->diffInDays($b) + 1;
        }

        return $hari;
    }

    /** Baris att_request mentah (status draft) — dipanggil di dalam transaksi store(). */
    private function buatPengajuan(array $data, string $userId, ?string $subTipe, ?int $claimedMenit, $scanEvidence, ?string $verifikasi, ?string $fotoName): AttRequest
    {
        return AttRequest::create([
            'user_id'       => $userId,
            'raw_branch_id' => session('raw_branch_id') ?: null,
            'tipe'          => $data['tipe'],
            'sub_tipe'      => $subTipe,
            'tanggal_from'  => $data['tanggal_from'],
            'tanggal_to'    => $data['tanggal_to'],
            'jam_from'      => $data['jam_from'] ?? null,
            'jam_to'        => $data['jam_to'] ?? null,
            'alasan'        => $data['alasan'],
            'foto'          => $fotoName,
            'claimed_menit' => $claimedMenit,
            'scan_evidence' => $scanEvidence,
            'verifikasi'    => $verifikasi,
            'status'        => 'draft',
        ]);
    }
}
