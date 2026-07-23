<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRequest;
use App\Services\AttendanceReconciler;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        'cuti'   => 'Cuti',
        'izin'   => 'Izin',
        'libur'  => 'Libur / Tukar Hari',
        'lembur' => 'Lembur',
        'wfh'    => 'Kerja dari Rumah',
    ];

    private function userId(): string
    {
        $id = (string) session('id');
        if (! $id) abort(403);
        return $id;
    }

    public function create(Request $request)
    {
        $this->userId();

        $tipeList = self::TIPE;
        // pra-isi tipe kalau datang dari tombol tipe tertentu (mis. ?tipe=lembur)
        $tipe = $request->input('tipe');
        if ($tipe && ! isset($tipeList[$tipe])) $tipe = null;

        return view('absensi.akun.pengajuan', compact('tipeList', 'tipe'));
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
            'tipe'         => 'required|in:cuti,izin,libur,lembur,wfh',
            'sub_tipe'     => 'nullable|in:jam,tambah_hari',
            'tanggal_from' => 'required|date',
            'tanggal_to'   => 'required|date|after_or_equal:tanggal_from',
            'jam_from'     => 'nullable|date_format:H:i',
            'jam_to'       => 'nullable|date_format:H:i|after:jam_from',
            'alasan'       => 'required|string|max:2000',
            'foto'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'tanggal_to.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'jam_to.after'              => 'Jam selesai harus setelah jam mulai.',
            'alasan.required'           => 'Alasan wajib diisi — approver butuh konteksnya.',
        ]);

        // Kuota WFH 2×/bulan (§3.3) — dihitung dari form approved, TAPI pending juga
        // dihitung di sini biar tak bisa over-submit 3 sekaligus lalu semua di-approve.
        // Form ke-3 ditolak otomatis: dicegah sebelum dibuat.
        if ($data['tipe'] === 'wfh') {
            $anchor = Carbon::parse($data['tanggal_from']);
            $used = AttRequest::where('user_id', $userId)
                ->where('tipe', 'wfh')
                ->whereIn('status', ['approved', 'pending_l1', 'pending_l2'])
                ->whereBetween('tanggal_from', [
                    $anchor->copy()->startOfMonth()->toDateString(),
                    $anchor->copy()->endOfMonth()->toDateString(),
                ])
                ->count();
            if ($used >= 2) {
                return back()->withInput()->with('error',
                    'Kuota WFH ' . $anchor->translatedFormat('F Y') . ' sudah penuh (maksimal 2× per bulan). Pengajuan tidak dibuat.');
            }
        }

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

        $req = AttRequest::create([
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

        // serahkan ke workflow: hitung rantai approver → pending_l1 / auto-approved
        $req = (new AttendanceRequestWorkflow())->submit($req);

        $pesan = $req->status === 'approved'
            ? 'Pengajuan tercatat dan langsung disetujui (tak ada approver di atas kamu).'
            : 'Pengajuan terkirim, menunggu persetujuan atasan.';

        return redirect()->route('akun.absensi')->with('success', $pesan);
    }
}
