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
        'sakit'   => 'Sakit',
        'tukar'   => 'Tukar Hari',
        'lembur'  => 'Lembur',
        'wfh'     => 'Kerja dari Rumah',
        'koreksi' => 'Koreksi Absen',
    ];

    /**
     * Jenis yang HANYA berlaku untuk aturan kerja fleksibel (ABG/office +
     * penyelia). Keputusan Ekky 2026-08-05.
     *
     * Alasannya berbeda untuk keduanya, tapi ujungnya sama — menawarkannya ke
     * staf lantai klinik adalah pintu yang tak pernah boleh dilewati:
     *   • Tukar Hari — kebijakan resminya memang cuma untuk orang kantor.
     *   • Kerja dari Rumah — pekerjaan FO/Asdent/GS tak bisa dikerjakan
     *     dari rumah.
     *
     * Ini BUKAN sekadar menyembunyikan tombol: store() menolak jenis yang
     * tidak berlaku, karena tombol yang disembunyikan tetap bisa dikirim
     * lewat form buatan sendiri.
     *
     * `libur` (jenis lama) sengaja dipertahankan supaya riwayat pengajuan lama
     * tetap terbaca — lihat TIPE_RIWAYAT.
     */
    public const TIPE_KHUSUS_FLEKSIBEL = ['tukar', 'wfh'];

    /**
     * Label untuk MENAMPILKAN riwayat, termasuk jenis yang tak lagi ditawarkan.
     * Pengajuan lama bertipe 'libur' harus tetap punya nama di layar; kalau
     * dibuang dari peta, riwayatnya berubah jadi kode mentah "libur".
     */
    public const TIPE_RIWAYAT = self::TIPE + ['libur' => 'Libur / Tukar Hari'];

    /** Jatah kerja dari rumah per bulan kalender, dalam HARI (§3.3). */
    public const WFH_JATAH_BULANAN = 2;

    /**
     * Pilihan "apa yang keliru?" di form koreksi.
     *
     * Diperbarui 2026-08-05 (keputusan Ekky): absen sekarang lewat HP/selfie,
     * sedangkan daftar lama masih berbahasa mesin sidik jari ("scan", "sidik
     * jari gagal terbaca"). Isi baru diturunkan dari kegagalan yang BENAR-BENAR
     * terjadi di absen HP hari pertama.
     *
     * "Salah pilih cabang" sengaja ada meski terdengar sepele: WiFi cabang
     * sering tak dikenali sehingga orang memilih cabang manual, dan salah pilih
     * jadi nyata. Selain memperbaiki datanya, pilihan ini juga jadi ISYARAT
     * bagi SPV bahwa pendaftaran WiFi cabang itu belum beres.
     *
     * Mesin sidik jari TIDAK dipensiunkan — dia cadangan kalau sistem tak bisa
     * dipakai (keputusan Ekky). Kegagalan mesin memang tak diberi pilihan
     * sendiri karena langka; "Lainnya" + catatan sudah menampungnya, dan
     * daftarnya tetap bersih untuk kejadian sehari-hari.
     *
     * ⚠️ Nilai di sini disimpan APA ADANYA ke att_request.alasan. Mengubah teks
     * berarti pengajuan lama tetap menyimpan teks lama — itu memang disengaja
     * (riwayat tak boleh berubah surut), jadi jangan "rapikan" data lama.
     */
    public const KOREKSI_ALASAN = [
        'Lupa absen masuk',
        'Lupa absen pulang',
        'HP mati / rusak',
        'Absen gagal terkirim (sinyal atau kamera)',
        'Salah pilih cabang',
        'Jam tercatat keliru',
        'Lainnya',
    ];

    private function userId(): string
    {
        $id = (string) session('id');
        if (! $id) abort(403);
        return $id;
    }

    /**
     * Jenis pengajuan yang BOLEH dipakai orang ini, sesuai aturan kerjanya.
     *
     * Dipakai dua arah: menyusun pilihan di layar DAN menolak kiriman yang
     * tak berlaku di store(). Satu fungsi untuk keduanya supaya tak mungkin
     * bergeser — layar yang menawarkan sesuatu yang lalu ditolak server itu
     * lebih membingungkan daripada tak menawarkannya sama sekali.
     *
     * Aturan kerja belum diatur (null) → diperlakukan seperti baku: yang
     * khusus fleksibel tidak ditawarkan. Lebih baik kurang menawarkan
     * daripada memberi hak yang belum tentu dia punya.
     */
    public static function jenisUntuk(?string $userId = null): array
    {
        if (aturanKerjaFleksibel($userId)) return self::TIPE;

        return array_diff_key(self::TIPE, array_flip(self::TIPE_KHUSUS_FLEKSIBEL));
    }

    public function create(Request $request)
    {
        $userId = $this->userId();

        $tipeList = self::jenisUntuk($userId);
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
    /**
     * Sajikan lampiran pengajuan (termasuk SURAT DOKTER) di balik login.
     *
     * Boleh dilihat: pemiliknya sendiri · Finance/Admin lintas cabang · dan
     * orang yang memang berada di rantai persetujuannya. Selain itu 403 —
     * lampiran sakit itu data kesehatan, bukan bacaan sekantor.
     *
     * Fallback ke public/uploads dipertahankan supaya lampiran LAMA (yang
     * terlanjur tersimpan di sana) tetap terbaca. Setelah berkas lama
     * dipindah ke storage, fallback ini boleh dicabut.
     */
    public function berkas(Request $request, $id)
    {
        $userId = $this->userId();
        $req = AttRequest::findOrFail($id);
        abort_unless($req->foto, 404);

        $boleh = $req->user_id === $userId || canSeeAllBranches();
        if (! $boleh) {
            $rantai = collect((new AttendanceRequestWorkflow())->chainFor($req->user_id, $req->tipe))
                ->flatten()->map(fn ($u) => (string) $u);
            $boleh = $rantai->contains($userId);
        }
        abort_unless($boleh, 403, 'Lampiran ini bukan untukmu.');

        // nama berkas dari DB, tapi tetap dikunci ke basename — jangan sampai
        // baris DB yang aneh (mis. "../.env") jadi jalan keluar dari folder.
        $nama = basename((string) $req->foto);
        foreach ([storage_path('app/att_request/' . $nama), public_path('uploads/att_request/' . $nama)] as $path) {
            if (is_file($path)) {
                return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
            }
        }
        abort(404);
    }

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

        // Tukar Hari punya bentuk sendiri (dua daftar tanggal, bukan rentang),
        // jadi jalurnya dipisah sejak validasi. Tetap lewat store() yang sama
        // supaya alur sesudahnya — persetujuan, notifikasi, riwayat — satu.
        if ($request->input('tipe') === 'tukar') {
            return $this->simpanTukarHari($request, $userId);
        }

        $data = $request->validate([
            // 'libur' masih diterima demi kiriman lama yang masih terbuka di
            // tab seseorang; jenis ini tak lagi ditawarkan di layar.
            'tipe'           => 'required|in:cuti,izin,sakit,libur,lembur,wfh,koreksi',
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

        // Jenis yang tak berlaku untuk aturan kerjanya ditolak DI SERVER —
        // tombol yang disembunyikan tetap bisa dikirim lewat form buatan
        // sendiri, dan diam-diam menerimanya berarti kebijakan cuma hiasan.
        if (! isset(self::jenisUntuk($userId)[$data['tipe']]) && $data['tipe'] !== 'libur') {
            return back()->withInput()->with('error',
                'Jenis pengajuan ini tidak berlaku untuk jam kerjamu.');
        }

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

        // Sakit ≥3 hari berturut → surat dokter WAJIB (keputusan Ekky 2026-08-11).
        // Ditegakkan DI SERVER, bukan cuma lewat atribut `required` di form:
        // input file yang disembunyikan tetap bisa dilewati form buatan sendiri.
        // 1–2 hari sengaja tanpa surat — menuntutnya untuk demam semalam justru
        // mendorong orang masuk kerja sambil sakit.
        if ($data['tipe'] === 'sakit') {
            $hari = Carbon::parse($data['tanggal_from'])
                ->diffInDays(Carbon::parse($data['tanggal_to'])) + 1;
            if ($hari >= AttRequest::SAKIT_HARI_WAJIB_SURAT && ! $request->hasFile('foto')) {
                return back()->withInput()->with('error',
                    'Sakit ' . $hari . ' hari perlu surat dokter — unggah fotonya dulu. '
                    . '(Sakit 1–2 hari tidak perlu surat.)');
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
        //
        // ⚠️ DISIMPAN DI storage/, BUKAN public/. Sejak jenis "sakit" ada, lampiran
        // ini bisa berupa SURAT DOKTER — data kesehatan. Folder public/ bisa
        // diambil siapa pun yang punya URL-nya, tanpa login (terukur: 200).
        // Alasannya sama persis dengan foto selfie yang dulu dipindah ke storage:
        // "wajah karyawan bukan aset publik" — surat dokter apalagi.
        // Berkas LAMA tetap di public/ dan tetap terbaca lewat jalur fallback di
        // berkas() — tak ada yang rusak, tapi unggahan baru tak lagi telanjang.
        $fotoName = null;
        if ($request->hasFile('foto')) {
            $dir = storage_path('app/att_request');
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
     * Tukar Hari — satu pengajuan, dua sisi: hari yang saya masuk ↔ hari libur
     * penggantinya. Keputusan Ekky 2026-08-05.
     *
     * Kenapa satu pengajuan, bukan dua: kalau sisi masuk dan sisi libur bisa
     * diputus terpisah, orang bisa memperoleh libur pengganti tanpa hari
     * masuknya — persis lubang yang membuat fitur ini ada. Satu kesepakatan =
     * satu keputusan atasan.
     *
     * Aturan yang ditegakkan di sini (semuanya juga tercermin di layar):
     *   • hanya untuk aturan kerja fleksibel;
     *   • jumlah hari masuk HARUS sama dengan jumlah hari libur pengganti;
     *   • satu tanggal tak boleh muncul dua kali, termasuk lintas sisi;
     *   • semuanya dalam SATU periode (lihat batasPeriode()).
     */
    private function simpanTukarHari(Request $request, string $userId)
    {
        if (! aturanKerjaFleksibel($userId)) {
            return back()->withInput()->with('error',
                'Tukar hari hanya berlaku untuk jam kerja fleksibel (kantor/ABG).');
        }

        $data = $request->validate([
            'masuk'   => 'required|array|min:1|max:31',
            'masuk.*' => 'required|date',
            'libur'   => 'required|array|min:1|max:31',
            'libur.*' => 'required|date',
            'alasan'  => 'required|string|max:2000',
        ], [
            'masuk.required'  => 'Isi dulu hari yang kamu masuk.',
            'libur.required'  => 'Isi dulu hari libur penggantinya.',
            'alasan.required' => 'Alasan wajib diisi — yang menyetujui butuh konteksnya.',
        ]);

        $masuk = array_values(array_unique(array_map(
            fn ($d) => Carbon::parse($d)->toDateString(), $data['masuk'])));
        $libur = array_values(array_unique(array_map(
            fn ($d) => Carbon::parse($d)->toDateString(), $data['libur'])));

        if (count($masuk) !== count($libur)) {
            return back()->withInput()->with('error', sprintf(
                'Jumlahnya belum seimbang: %d hari masuk, %d hari libur pengganti. '
                . 'Tukar hari harus satu-lawan-satu.', count($masuk), count($libur)));
        }

        $bentrok = array_intersect($masuk, $libur);
        if ($bentrok) {
            return back()->withInput()->with('error',
                'Tanggal ' . Carbon::parse(reset($bentrok))->translatedFormat('d M Y')
                . ' ditulis sebagai hari masuk sekaligus hari libur. Pilih salah satu.');
        }

        // Satu periode: kalau Finance sudah membuat periode gaji yang memuat
        // tanggal-tanggal ini, itu yang dipakai; kalau belum, jatuh ke bulan
        // kalender. Dua-duanya dipikirkan supaya fitur ini tak pernah macet
        // hanya karena Finance belum sempat membuka periode (keputusan Ekky).
        $semua = array_merge($masuk, $libur);
        sort($semua);
        [$awal, $akhir, $labelPeriode] = $this->batasPeriode($semua[0]);
        foreach ($semua as $t) {
            if ($t < $awal || $t > $akhir) {
                return back()->withInput()->with('error',
                    'Semua tanggal harus berada dalam periode yang sama (' . $labelPeriode
                    . '). Tanggal ' . Carbon::parse($t)->translatedFormat('d M Y') . ' di luar itu.');
            }
        }

        try {
            $req = DB::transaction(function () use ($masuk, $libur, $semua, $userId, $data) {
                $r = AttRequest::create([
                    'user_id'       => $userId,
                    'raw_branch_id' => session('raw_branch_id') ?: null,
                    'tipe'          => 'tukar',
                    // Rentang tetap diisi (paling awal–paling akhir) supaya
                    // penyaringan rentang di query lain tetap bekerja; tanggal
                    // yang sebenarnya berlaku ada di att_request_hari.
                    'tanggal_from'  => $semua[0],
                    'tanggal_to'    => end($semua),
                    'alasan'        => $data['alasan'],
                    'status'        => 'draft',
                ]);

                foreach ($masuk as $t) {
                    \App\Models\AttRequestHari::create([
                        'request_id' => $r->id, 'tanggal' => $t,
                        'peran' => \App\Models\AttRequestHari::PERAN_MASUK,
                    ]);
                }
                foreach ($libur as $t) {
                    \App\Models\AttRequestHari::create([
                        'request_id' => $r->id, 'tanggal' => $t,
                        'peran' => \App\Models\AttRequestHari::PERAN_LIBUR,
                    ]);
                }

                return (new AttendanceRequestWorkflow())->submit($r);
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $pesan = $req->status === 'approved'
            ? 'Tukar hari tercatat dan langsung disetujui (tak ada atasan di atas kamu).'
            : 'Tukar hari terkirim, menunggu persetujuan atasan.';

        return redirect()->route('akun.absensi')->with('success', $pesan);
    }

    /**
     * Batas periode yang memuat sebuah tanggal: [awal, akhir, label].
     *
     * Periode gaji Finance menang kalau ada — itu satuan yang dipakai saat
     * menutup buku, jadi tukar hari tak boleh melintasinya. Kalau Finance
     * belum membuat periodenya, jatuh ke bulan kalender: fitur ini tak boleh
     * bergantung diam-diam pada pekerjaan orang lain.
     */
    private function batasPeriode(string $tanggal): array
    {
        // Kolomnya `periode_from`/`periode_to`, dan periode bisa berlaku global
        // (raw_branch_id NULL) atau khusus satu cabang — pola yang sama dipakai
        // AttendanceReconciler::isDateLocked. Yang khusus cabang didahulukan.
        if (\Illuminate\Support\Facades\Schema::hasTable('att_period')) {
            $cabang = session('raw_branch_id') ?: null;

            $p = DB::table('att_period')
                ->where('periode_from', '<=', $tanggal)
                ->where('periode_to', '>=', $tanggal)
                ->where(function ($q) use ($cabang) {
                    $q->whereNull('raw_branch_id');
                    if ($cabang) $q->orWhere('raw_branch_id', $cabang);
                })
                ->orderByRaw('raw_branch_id IS NULL')   // yang spesifik cabang duluan
                ->first(['periode_from', 'periode_to']);

            if ($p) {
                return [
                    substr((string) $p->periode_from, 0, 10),
                    substr((string) $p->periode_to, 0, 10),
                    Carbon::parse($p->periode_from)->translatedFormat('d M')
                        . ' – ' . Carbon::parse($p->periode_to)->translatedFormat('d M Y'),
                ];
            }
        }

        $c = Carbon::parse($tanggal);
        return [
            $c->copy()->startOfMonth()->toDateString(),
            $c->copy()->endOfMonth()->toDateString(),
            $c->translatedFormat('F Y'),
        ];
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
