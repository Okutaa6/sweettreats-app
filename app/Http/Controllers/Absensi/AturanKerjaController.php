<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRuleLog;
use App\Models\AttUserRule;
use App\Models\AttWorkRule;
use App\Models\AttWorkRuleDay;
use App\Models\AttWorkRuleException;
use App\Models\RawBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aturan Kerja — UI kelola jam kerja (Tab 1), penugasan aturan per orang
 * (Tab 2), kalender libur (Tab 3). docs/rencana/rencana-aturan-kerja.md.
 *
 * Fitur ini CUMA MENULIS ke tabel yang mesin baca (att_work_rule / _day /
 * _exception / att_user_rule). Mesin rekonsiliasi (AttendanceReconciler,
 * att_day) TIDAK disentuh — dia membaca aturan saat compute, sudah benar.
 *
 * Aturan keras (jangan dilanggar tanpa baca rencana):
 *  • Ganti penugasan = TUTUP baris lama + baris BARU ber-effective_from.
 *    BUKAN update rule_id baris lama — riwayat = jejak gaji.
 *  • Berlaku ke depan saja (>= hari ini). att_day itu materialized; masa
 *    lalu beku, koreksinya lewat pengajuan koreksi yang sudah ada.
 *    JANGAN pernah memicu re-reconcile massal dari sini.
 *  • Libur nasional = baris att_work_rule_exception (raw_branch_id NULL =
 *    semua cabang) — pola SQL Lebaran, bukan mekanisme baru.
 *  • Setiap perubahan dicatat ke att_rule_log (siapa, kapan, dari→ke).
 */
class AturanKerjaController extends Controller
{
    /**
     * Ini menentukan gaji — bukan setting sembarangan. Gate via role di
     * controller (rencana §3); hasMenuAccess cuma nemu node level 3, jangan
     * diandalkan sebagai gerbang.
     */
    /**
     * Daftar role yang boleh membuka layar ini. Sengaja KONSTANTA PUBLIK, bukan
     * array inline: layar ini punya beberapa tab yang gerbangnya harus sama
     * persis, dan salinan daftar yang tersebar pasti berselisih cepat atau
     * lambat. `att:audit-role` juga membacanya lewat refleksi supaya
     * detektornya tak memelihara cermin yang bisa basi diam-diam.
     *
     * Konsultan Finance/IT ikut (2026-08-22) karena menunya dikloning bulat
     * dari role induknya dan node `absensi/aturan` ikut terbawa — dibiarkan di
     * luar daftar ini, menunya tampil lalu 403.
     * ⚠️ Layar ini MENENTUKAN GAJI. Kalau Ekky menilai itu kelewat jauh untuk
     * orang luar, cabut dua nama ini DAN buang node `absensi/aturan` dari
     * group_access kedua role konsultan — dua-duanya, jangan satu saja.
     */
    public const BOLEH = ['Supervisor', 'Head IT', 'Finance', 'Super Admin',
                          'Konsultan Finance', 'Konsultan IT'];

    private function guard(): void
    {
        if (! in_array(session('group_name'), self::BOLEH, true)) {
            abort(403, 'Halaman ini khusus Supervisor / Head IT / Finance.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        $today = now()->toDateString();

        $rules = AttWorkRule::with('days')->withCount('penugasanAktif')->orderBy('id')->get();

        // ── Tab 2: karyawan aktif + aturan yang efektif hari ini + riwayat ──
        $karyawan = DB::table('users as u')
            ->leftJoin('group_path as g', 'g.id', '=', 'u.group_id')
            ->leftJoin('raw_branch as b', 'b.id', '=', 'u.raw_branch_id')
            ->where('u.status', 1)
            ->orderBy('u.nama')
            ->get(['u.id', 'u.nama', 'g.nama as role_nama', 'b.name as cabang']);

        $riwayat = AttUserRule::with('rule')
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->get()
            ->groupBy('user_id');

        $aktif = [];   // user_id => AttUserRule yang efektif hari ini
        foreach ($riwayat as $uid => $rows) {
            foreach ($rows as $r) {
                if ($r->effective_from <= $today && ($r->effective_to === null || $r->effective_to >= $today)) {
                    $aktif[$uid] = $r;
                    break;
                }
            }
        }

        $roles = DB::table('group_path')->orderBy('nama')->get(['id', 'nama', 'att_default_rule_id']);

        // ── Tab 3: kalender libur ──
        $exceptions = AttWorkRuleException::with('branch')->orderByDesc('tgl_from')->get();
        $mendatang  = $exceptions->filter(fn ($e) => $e->tgl_to >= $today)->sortBy('tgl_from')->values();
        $lampau     = $exceptions->filter(fn ($e) => $e->tgl_to < $today)->take(20)->values();

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $logs     = AttRuleLog::orderByDesc('id')->limit(30)->get();

        return view('absensi.aturan', [
            'rules'     => $rules,
            'karyawan'  => $karyawan,
            'riwayat'   => $riwayat,
            'aktif'     => $aktif,
            'roles'     => $roles,
            'mendatang' => $mendatang,
            'lampau'    => $lampau,
            'branches'  => $branches,
            'logs'      => $logs,
            'today'     => $today,
            'tab'       => $request->query('tab', 'aturan'),
        ]);
    }

    // ═════════════════════════════ Tab 1 — Aturan ═════════════════════════════

    public function storeRule(Request $request)
    {
        $this->guard();

        $data = $this->validateRule($request);

        $rule = AttWorkRule::create([
            'nama'       => $data['nama'],
            'tipe'       => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);
        $this->simpanHari($rule, $data);

        AttRuleLog::catat('rule.buat', $rule->nama, ['tipe' => $rule->tipe]);

        return redirect()->route('absensi.aturan', ['tab' => 'aturan'])
            ->with('success', 'Aturan "' . $rule->nama . '" dibuat.');
    }

    public function updateRule(Request $request, $id)
    {
        $this->guard();

        $rule = AttWorkRule::with('days')->findOrFail($id);
        $data = $this->validateRule($request, $rule->id);

        // Snapshot sebelum — buat jejak dari→ke di log.
        $sebelum = [
            'nama' => $rule->nama,
            'tipe' => $rule->tipe,
            'hari' => $rule->days->map(fn ($d) => [
                'day' => $d->day, 'libur' => (int) $d->libur,
                'masuk' => [$d->jam_masuk_awal, $d->jam_masuk_akhir],
                'pulang' => $d->jam_pulang, 'lembur' => $d->jam_lembur_mulai,
            ])->values()->all(),
        ];

        $rule->update([
            'nama'       => $data['nama'],
            'tipe'       => $data['tipe'],
            'keterangan' => $data['keterangan'] ?? null,
        ]);
        $this->simpanHari($rule, $data);

        $n = $rule->penugasanAktif()->count();
        AttRuleLog::catat('rule.ubah', $rule->nama, [
            'terdampak' => $n . ' karyawan',
            'dari'      => $sebelum,
            'ke'        => [
                'nama' => $rule->nama,
                'tipe' => $rule->tipe,
                'hari' => $rule->days()->orderBy('day')->get()->map(fn ($d) => [
                    'day' => $d->day, 'libur' => (int) $d->libur,
                    'masuk' => [$d->jam_masuk_awal, $d->jam_masuk_akhir],
                    'pulang' => $d->jam_pulang, 'lembur' => $d->jam_lembur_mulai,
                ])->values()->all(),
            ],
        ]);

        return redirect()->route('absensi.aturan', ['tab' => 'aturan'])
            ->with('success', 'Aturan "' . $rule->nama . '" disimpan — dipakai ' . $n .
                ' karyawan, jam baru dinilai mulai reconcile besok pagi. Masa lalu tidak dihitung ulang.');
    }

    /**
     * Hapus HANYA kalau aturan tak pernah dipakai — baris att_user_rule atau
     * snapshot att_day yang menunjuk ke sini adalah jejak gaji, jangan yatim.
     */
    public function deleteRule($id)
    {
        $this->guard();

        $rule = AttWorkRule::findOrFail($id);

        $dipakai = AttUserRule::where('rule_id', $rule->id)->count();
        $jejak   = DB::table('att_day')->where('rule_id', $rule->id)->count();
        if ($dipakai || $jejak) {
            return back()->with('error', 'Aturan "' . $rule->nama . '" tidak bisa dihapus — pernah/'
                . 'sedang dipakai (' . $dipakai . ' penugasan, ' . $jejak . ' hari ternilai). '
                . 'Pindahkan dulu semua karyawannya ke aturan lain; riwayat lama tetap tersimpan.');
        }

        // Lepas default-role yang menunjuk ke sini — kolomnya soft reference
        // (tanpa FK); kalau dibiarkan menggantung, pembuatan AKUN BARU dengan
        // role itu meledak saat insert att_user_rule (FK rule_id).
        $dilepas = DB::table('group_path')->where('att_default_rule_id', $rule->id)
            ->update(['att_default_rule_id' => null]);

        $nama = $rule->nama;
        $rule->delete();   // att_work_rule_day ikut (FK CASCADE)

        AttRuleLog::catat('rule.hapus', $nama, $dilepas ? ['default_role_dilepas' => $dilepas] : []);

        return redirect()->route('absensi.aturan', ['tab' => 'aturan'])
            ->with('success', 'Aturan "' . $nama . '" dihapus.'
                . ($dilepas ? ' Default aturan di ' . $dilepas . ' role ikut dilepas (jadi "tidak otomatis").' : ''));
    }

    private function validateRule(Request $request, $ignoreId = null): array
    {
        $data = $request->validate([
            'nama'       => 'required|string|max:100|unique:att_work_rule,nama' . ($ignoreId ? ',' . $ignoreId : ''),
            'tipe'       => 'required|in:baku,fleksibel',
            'keterangan' => 'nullable|string|max:255',
            'hari'       => 'array',
            'hari.*.libur'            => 'nullable',
            'hari.*.jam_masuk_awal'   => 'nullable|date_format:H:i',
            'hari.*.jam_masuk_akhir'  => 'nullable|date_format:H:i',
            'hari.*.jam_pulang'       => 'nullable|date_format:H:i',
            'hari.*.jam_lembur_mulai' => 'nullable|date_format:H:i',
        ]);

        if ($data['tipe'] === 'baku') {
            foreach (range(0, 6) as $d) {
                $h = $data['hari'][$d] ?? [];
                if (! empty($h['libur'])) continue;
                if (empty($h['jam_masuk_awal']) || empty($h['jam_masuk_akhir']) || empty($h['jam_pulang'])) {
                    $this->tolak('Hari kerja pada aturan baku wajib punya jam masuk (awal & akhir) dan '
                        . 'jam pulang — atau centang libur. Cek hari ' . $this->namaHari($d) . '.');
                }
                if ($h['jam_masuk_awal'] > $h['jam_masuk_akhir']) {
                    $this->tolak('Jam masuk awal harus <= jam masuk akhir (hari ' . $this->namaHari($d) . ').');
                }
            }
        }

        return $data;
    }

    /** Gagalkan request dengan flash error + kembali ke form (input tetap terisi). */
    private function tolak(string $pesan): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            back()->withInput()->with('error', $pesan)
        );
    }

    /** Upsert 7 baris att_work_rule_day (day 0=Minggu..6=Sabtu, ikut Carbon). */
    private function simpanHari(AttWorkRule $rule, array $data): void
    {
        if ($data['tipe'] !== 'baku') {
            // Fleksibel tak punya baris jam — engine tak membacanya (jam bebas,
            // scan tak dinilai). Baris lama dibersihkan supaya tak menyesatkan.
            AttWorkRuleDay::where('rule_id', $rule->id)->delete();
            return;
        }

        foreach (range(0, 6) as $d) {
            $h = $data['hari'][$d] ?? [];
            $libur = ! empty($h['libur']);
            AttWorkRuleDay::updateOrCreate(
                ['rule_id' => $rule->id, 'day' => $d],
                [
                    'libur'            => $libur ? 1 : 0,
                    'jam_masuk_awal'   => $libur ? null : ($h['jam_masuk_awal'] ?? null),
                    'jam_masuk_akhir'  => $libur ? null : ($h['jam_masuk_akhir'] ?? null),
                    'jam_pulang'       => $libur ? null : ($h['jam_pulang'] ?? null),
                    'jam_lembur_mulai' => $libur ? null : ($h['jam_lembur_mulai'] ?? null),
                ]
            );
        }
    }

    private function namaHari(int $d): string
    {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][$d] ?? (string) $d;
    }

    // ═══════════════════════════ Tab 2 — Penugasan ═══════════════════════════

    /**
     * Ganti aturan seseorang: tutup baris lama (effective_to = H-1), insert
     * baris baru ber-effective_from. Forward-only: mulai >= hari ini.
     */
    public function assign(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'rule_id'        => 'required|exists:att_work_rule,id',
            'effective_from' => 'required|date',
        ]);

        $from  = Carbon::parse($data['effective_from'])->toDateString();
        $today = now()->toDateString();
        if ($from < $today) {
            return back()->with('error', 'Penugasan hanya berlaku ke depan — tanggal mulai minimal hari ini. '
                . 'Masa lalu sudah ternilai (jejak gaji); koreksi lewat pengajuan koreksi.');
        }

        $user = DB::table('users')->where('id', $data['user_id'])->first(['id', 'nama']);
        $rule = AttWorkRule::findOrFail($data['rule_id']);

        $pesan = null;
        DB::transaction(function () use ($data, $from, $user, $rule, &$pesan) {
            $rows = AttUserRule::where('user_id', $data['user_id'])
                ->orderByDesc('effective_from')->lockForUpdate()->get();

            // Bentrok dengan penugasan yang mulainya >= tanggal baru
            // (termasuk penugasan masa depan yang sudah diketik duluan).
            if ($rows->first() && $rows->first()->effective_from >= $from) {
                $pesan = 'Sudah ada penugasan yang mulai ' . $rows->first()->effective_from .
                    ' — tanggal mulai baru harus setelah itu.';
                return;
            }

            $berjalan = $rows->first(fn ($r) => $r->effective_from <= $from
                && ($r->effective_to === null || $r->effective_to >= $from));

            if ($berjalan && (int) $berjalan->rule_id === (int) $rule->id) {
                $pesan = $user->nama . ' sudah memakai aturan "' . $rule->nama . '".';
                return;
            }

            // TUTUP baris lama — jangan pernah update rule_id-nya (jejak gaji).
            if ($berjalan) {
                $berjalan->update(['effective_to' => Carbon::parse($from)->subDay()->toDateString()]);
            }

            AttUserRule::create([
                'user_id'        => $data['user_id'],
                'rule_id'        => $rule->id,
                'effective_from' => $from,
            ]);

            AttRuleLog::catat('penugasan', $user->nama, [
                'dari'  => $berjalan ? optional(AttWorkRule::find($berjalan->rule_id))->nama : '(belum ada aturan)',
                'ke'    => $rule->nama,
                'mulai' => $from,
            ]);
        });

        if ($pesan) {
            return back()->with('error', $pesan);
        }

        return redirect()->route('absensi.aturan', ['tab' => 'penugasan'])
            ->with('success', $user->nama . ' → "' . $rule->nama . '" mulai ' . $from .
                '. Riwayat lama tersimpan; hari-hari sebelum tanggal itu tetap dinilai aturan lama.');
    }

    /** Default aturan per role — dipakai saat akun baru dibuat di Pusat Akun. */
    public function defaultRole(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'group_id' => 'required|exists:group_path,id',
            'rule_id'  => 'nullable|exists:att_work_rule,id',
        ]);

        $role = DB::table('group_path')->where('id', $data['group_id'])->first(['id', 'nama', 'att_default_rule_id']);
        $lama = $role->att_default_rule_id ? optional(AttWorkRule::find($role->att_default_rule_id))->nama : '(tidak otomatis)';
        $baru = ! empty($data['rule_id']) ? AttWorkRule::findOrFail($data['rule_id'])->nama : '(tidak otomatis)';

        DB::table('group_path')->where('id', $role->id)
            ->update(['att_default_rule_id' => ($data['rule_id'] ?? null) ?: null]);

        AttRuleLog::catat('penugasan.default-role', $role->nama, ['dari' => $lama, 'ke' => $baru]);

        return redirect()->route('absensi.aturan', ['tab' => 'penugasan'])
            ->with('success', 'Aturan role "' . $role->nama . '": ' . $baru .
                '. Berlaku untuk akun baru. Untuk karyawan yang sudah ada, tekan "Terapkan".');
    }

    /**
     * Terapkan aturan default sebuah ROLE ke karyawan aktif yang memegang role
     * itu — mulai HARI INI.
     *
     * Ada supaya pemetaan aturan kerja bisa diurus lewat ROLE dan diatur dari
     * halaman ini (permintaan Ekky 2026-08-06), bukan lewat daftar nama orang
     * yang dikunci di dalam SQL/kode. Menaruh nama di kode berarti tiap
     * perubahan orang menuntut deploy, dan cepat atau lambat ada yang lupa.
     *
     * Tiga penjaga, semuanya sengaja:
     *   • MULAI HARI INI, tak pernah mundur — masa lalu sudah ternilai dan
     *     dipakai hitungan gaji; koreksi masa lalu jalurnya pengajuan koreksi.
     *   • Orang yang sudah punya penugasan mulai hari ini/ke depan DILEWATI,
     *     supaya rencana yang sudah diketik manual tak tertimpa diam-diam.
     *   • Yang aturannya sudah sesuai tidak disentuh — tak perlu baris baru
     *     yang isinya sama.
     * Yang dilewati DILAPORKAN, tidak dibuang diam-diam.
     */
    public function terapkanDefaultRole(Request $request)
    {
        $this->guard();

        $data = $request->validate(['group_id' => 'required|exists:group_path,id']);

        $role = DB::table('group_path')->where('id', $data['group_id'])
            ->first(['id', 'nama', 'att_default_rule_id']);

        if (! $role->att_default_rule_id) {
            return back()->with('error',
                'Role "' . $role->nama . '" belum punya aturan default — pilih dulu lalu Simpan.');
        }

        $rule  = AttWorkRule::findOrFail($role->att_default_rule_id);
        $hari  = now()->toDateString();
        $ubah  = 0;
        $lewat = [];
        $sudah = 0;

        DB::transaction(function () use ($role, $rule, $hari, &$ubah, &$lewat, &$sudah) {
            $orang = DB::table('users')->where('group_id', $role->id)->where('status', 1)
                ->get(['id', 'nama']);

            foreach ($orang as $u) {
                $rows = AttUserRule::where('user_id', $u->id)
                    ->orderByDesc('effective_from')->lockForUpdate()->get();

                if ($rows->first() && $rows->first()->effective_from >= $hari) {
                    $lewat[] = $u->nama;                       // ada rencana ke depan
                    continue;
                }
                if ($rows->first() && (int) $rows->first()->rule_id === (int) $rule->id) {
                    $sudah++;                                   // sudah sesuai
                    continue;
                }

                AttUserRule::create([
                    'user_id'        => $u->id,
                    'rule_id'        => $rule->id,
                    'effective_from' => $hari,
                ]);
                $ubah++;
            }

            AttRuleLog::catat('penugasan.terapkan-role', $role->nama, [
                'aturan' => $rule->nama, 'diubah' => $ubah, 'sudah_sesuai' => $sudah,
                'dilewati' => $lewat,
            ]);
        });

        $pesan = $ubah . ' karyawan ber-role "' . $role->nama . '" kini memakai aturan "'
            . $rule->nama . '" mulai ' . Carbon::parse($hari)->translatedFormat('d M Y') . '.';
        if ($sudah)  $pesan .= ' ' . $sudah . ' orang sudah sesuai sebelumnya.';
        if ($lewat)  $pesan .= ' Dilewati karena sudah punya penugasan terjadwal: ' . implode(', ', $lewat) . '.';

        return redirect()->route('absensi.aturan', ['tab' => 'penugasan'])->with('success', $pesan);
    }

    // ═════════════════════════ Tab 3 — Kalender Libur ═════════════════════════

    /**
     * Libur nasional / tanggal khusus = baris att_work_rule_exception,
     * raw_branch_id NULL = semua cabang (persis pola SQL Lebaran). Engine
     * (resolveDay) sudah membacanya — nol mekanisme baru.
     */
    public function storeException(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'alasan'        => 'required|string|max:255',
            'tgl_from'      => 'required|date',
            'tgl_to'        => 'nullable|date',
            'raw_branch_id' => 'nullable|exists:raw_branch,id',
        ]);

        $from  = Carbon::parse($data['tgl_from'])->toDateString();
        $to    = Carbon::parse(($data['tgl_to'] ?? null) ?: $data['tgl_from'])->toDateString();
        $today = now()->toDateString();

        if ($from < $today) {
            return back()->with('error', 'Kalender libur hanya untuk tanggal ke depan — hari yang sudah '
                . 'lewat telah ternilai. (Libur lampau seperti Lebaran di-seed lewat SQL deploy.)');
        }
        if ($to < $from) {
            return back()->with('error', 'Tanggal selesai harus >= tanggal mulai.');
        }

        $tumpang = AttWorkRuleException::where('tgl_from', '<=', $to)->where('tgl_to', '>=', $from)
            ->where(function ($q) use ($data) {
                empty($data['raw_branch_id'])
                    ? $q->whereRaw('1=1')                                   // nasional menimpa semua
                    : $q->whereNull('raw_branch_id')->orWhere('raw_branch_id', $data['raw_branch_id']);
            })->first();
        if ($tumpang) {
            return back()->with('error', 'Rentang ini tumpang tindih dengan "' . $tumpang->alasan .
                '" (' . $tumpang->tgl_from . ' – ' . $tumpang->tgl_to . '). Hapus/sesuaikan dulu yang itu.');
        }

        $exc = AttWorkRuleException::create([
            'raw_branch_id' => ($data['raw_branch_id'] ?? null) ?: null,
            'tgl_from'      => $from,
            'tgl_to'        => $to,
            'libur'         => 1,
            'alasan'        => $data['alasan'],
            'dibuat_oleh'   => (string) session('id') ?: null,
        ]);

        AttRuleLog::catat('libur.buat', $exc->alasan, [
            'tanggal' => $from === $to ? $from : $from . ' – ' . $to,
            'cabang'  => $exc->raw_branch_id ? optional($exc->branch)->name : 'semua cabang',
        ]);

        return redirect()->route('absensi.aturan', ['tab' => 'libur'])
            ->with('success', 'Libur "' . $exc->alasan . '" tercatat (' . $from .
                ($to !== $from ? ' – ' . $to : '') . '). Berlaku ke semua aturan baku; office/fleksibel memang tak dinilai.');
    }

    /** Hapus hanya yang BELUM mulai — yang sudah berjalan/lewat itu jejak penilaian. */
    public function deleteException($id)
    {
        $this->guard();

        $exc   = AttWorkRuleException::findOrFail($id);
        $today = now()->toDateString();

        if ($exc->tgl_from <= $today) {
            return back()->with('error', 'Libur "' . $exc->alasan . '" sudah mulai/lewat — tidak bisa dihapus, '
                . 'hari-harinya sudah/sedang dinilai sebagai libur.');
        }

        $detail = ['tanggal' => $exc->tgl_from . ' – ' . $exc->tgl_to,
                   'cabang'  => $exc->raw_branch_id ? optional($exc->branch)->name : 'semua cabang'];
        $alasan = $exc->alasan;
        $exc->delete();

        AttRuleLog::catat('libur.hapus', $alasan, $detail);

        return redirect()->route('absensi.aturan', ['tab' => 'libur'])
            ->with('success', 'Libur "' . $alasan . '" dibatalkan.');
    }
}
