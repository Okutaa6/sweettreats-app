<?php

namespace App\Http\Controllers\Set;

use App\Http\Controllers\Controller;
use App\Models\GroupPath;
use App\Models\UserApproval;
use App\Models\Users;
use Illuminate\Support\Facades\DB;

class UserManagement extends Controller
{
    public function index()
    {
        // 50/halaman: filter & cari di halaman ini CLIENT-SIDE — kalau paginate
        // kecil, cari nama di halaman 2 = "tidak ketemu" palsu. 36 akun muat.
        $users = Users::with('group', 'branch', 'approvals')->paginate(50);

        // Jumlah perangkat "diingat" hidup per user (buat kolom Perangkat).
        $deviceCounts = DB::table('user_device')->whereNull('revoked_at')
            ->select('user_id', DB::raw('COUNT(*) as n'))
            ->groupBy('user_id')->pluck('n', 'user_id');

        $flagged = $this->flaggedUserIds();

        return view('set.usermanagement.list', [
            'users'        => $users,
            'deviceCounts' => $deviceCounts,
            'flagged'      => $flagged,
            'branches'     => \App\Models\RawBranch::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * User yang kena detektor Kesehatan (versi ringkas buat badge ⚠ di list &
     * flag-notice di detail): role landing-terkunci → semua penggunanya;
     * akun tak bertuan; pemilik perangkat menggantung. Sumber query sama
     * dengan Set\KesehatanController — jangan ubah salah satu saja.
     */
    private function flaggedUserIds(): array
    {
        $skip = "'" . implode("','", \App\Http\Middleware\AuthGuard::SKIP_RBAC) . "'";
        $ids = [];

        // Urutan penulisan = PRIORITAS TERBALIK (yang terakhir menimpa):
        // bertuan < perangkat < landing — landing paling kritis, harus menang
        // saat satu user kena lebih dari satu detektor.
        $batas = now()->subDays(30)->toDateTimeString();
        foreach (DB::select("
            SELECT id FROM users WHERE status = 1
              AND (last_login IS NULL OR last_login < ?) AND (created_at IS NULL OR created_at < ?)
        ", [$batas, $batas]) as $r) $ids[$r->id] = 'bertuan';

        foreach (DB::select("
            SELECT DISTINCT d.user_id AS id FROM user_device d JOIN users u ON u.id = d.user_id
            WHERE d.revoked_at IS NULL AND u.status <> 1
        ") as $r) $ids[$r->id] = 'perangkat';

        foreach (DB::select("
            SELECT u.id FROM users u JOIN group_path g ON g.id = u.group_id
            WHERE u.status = 1 AND g.landing_page IS NOT NULL AND g.landing_page <> ''
              AND SUBSTRING_INDEX(TRIM(LEADING '/' FROM g.landing_page),'/',1) NOT IN ($skip)
              AND NOT EXISTS (SELECT 1 FROM group_access ga JOIN access_path ap ON ap.id = ga.access_id
                              WHERE ga.group_id = g.id AND ap.link = SUBSTRING_INDEX(TRIM(LEADING '/' FROM g.landing_page),'/',1))
        ") as $r) $ids[$r->id] = 'landing';

        return $ids;
    }

    /** Aktif ⇄ nonaktif akun (aksi cepat dari detail; nonaktif ikut cabut perangkat). */
    public function toggleStatus($id)
    {
        $user = Users::findOrFail($id);
        $jadiAktif = (int) $user->status !== 1;
        $user->update(['status' => $jadiAktif ? 1 : 0]
            // Nonaktif WAJIB menendang sesi yang lagi jalan — AuthGuard tidak
            // mengecek ulang status per-request, jadi tanpa stempel ini karyawan
            // resign tetap bisa pakai sistem sampai idle >1 jam.
            + ($jadiAktif ? [] : ['force_logout_at' => now()]));

        if (! $jadiAktif) {
            app(\App\Services\DeviceAuth::class)->revokeAllFor($id);
            // Tutup posisi checklist-nya — tanpa ini cron mark-missed terus
            // mencetak "Tidak Diisi" untuk akun yang sudah nonaktif.
            try {
                \App\Models\UserPosition::query()->activeOn(\Carbon\Carbon::today())->where('user_id', $id)
                    ->update(['effective_to' => now()->subDay()->toDateString(), 'updated_at' => now()]);
            } catch (\Throwable $e) {}
        } else {
            // Diaktifkan lagi → buka kembali posisi checklist dari data user-nya.
            try { \App\Models\UserPosition::syncFromUser($user->fresh(), session('id')); } catch (\Throwable $e) {}
        }
        \App\Models\AuditLog::record('status_change', ($jadiAktif ? 'Aktifkan akun: ' : 'Nonaktifkan akun: ') . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $id)
            ->with('success', 'Akun ' . $user->nama . ' sekarang ' . ($jadiAktif ? 'AKTIF' : 'NONAKTIF') . '.');
    }

    /** Cabut SEMUA perangkat "diingat" user. */
    public function revokeAllDevices($id)
    {
        $user = Users::findOrFail($id);
        app(\App\Services\DeviceAuth::class)->revokeAllFor($id);
        \App\Models\AuditLog::record('update', 'Cabut semua perangkat: ' . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $id)->with('success', 'Semua perangkat ' . $user->nama . ' dicabut.');
    }

    /** Detail user — identitas + perangkat + jejak + aksi (Pusat Akun §4b). */
    public function detail($id)
    {
        $user = Users::with('group', 'branch')->findOrFail($id);
        $flagJenis = $this->flaggedUserIds()[$id] ?? null;

        $devices = DB::table('user_device')->where('user_id', $id)
            ->orderByRaw('revoked_at IS NULL DESC')->orderByDesc('last_used_at')
            ->get();

        // Jejak: aktivitas user itu sendiri (user_id) + tindakan atas dia (model_id).
        $trail = DB::table('audit_log')
            ->where('user_id', $id)
            ->orWhere(fn ($q) => $q->where('model_type', 'Users')->where('model_id', $id))
            ->orderByDesc('created_at')->limit(20)->get();

        return view('set.usermanagement.detail', compact('user', 'devices', 'trail', 'flagJenis'));
    }

    public function create()
    {
        $groups = GroupPath::orderBy('nama')->get();
         $branches = \App\Models\RawBranch::orderBy('name')->get(); // branch

        return view('set.usermanagement.new', compact('groups', 'branches'));
    }

    /**
     * Buat akun baru. Pusat Akun: TIDAK kirim undangan email (mailbox
     * @sweettreats.id kemungkinan tak ada — lihat kejadian lupa-password).
     * Ganti: generate password sementara ACAK, tampil SEKALI di layar (IT
     * kasih langsung ke orangnya), wajib diganti di login pertama.
     */
    public function store()
    {
        request()->validate([
            'nama'     => 'required',
            'email'    => 'required|email|unique:users,email',
            'group_id' => 'required|exists:group_path,id',
        ]);

        $temp = $this->generateTempPassword();
        $user = Users::create([
            'nama'                 => request('nama'),
            'email'                => request('email'),
            'password'             => password_hash($temp, PASSWORD_DEFAULT),
            'group_id'             => request('group_id'),
            'raw_branch_id'        => request('raw_branch_id'),
            'status'               => 1,
            'must_change_password' => 1,
            // model $timestamps=false — isi manual biar masa-tenggang detektor
            // "akun tak bertuan" bekerja (akun baru jangan langsung dicap)
            'created_at'           => now(),
        ]);

        // Role operasional + cabang sudah lengkap → posisi checklist otomatis
        // aktif, tanpa perlu karyawan mengajukan / supervisor konfirmasi.
        try { \App\Models\UserPosition::syncFromUser($user, session('id')); } catch (\Throwable $e) {}

        \App\Models\AuditLog::record('create', 'Buat akun: ' . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $user->id)
            ->with('reset_temp_pw', $temp)
            ->with('reset_user', $user->nama)
            ->with('success', 'Akun ' . $user->nama . ' dibuat. Kasih password sementara di bawah ke orangnya.');
    }

    public function edit($id)
    {
        $user = Users::find($id);
        $groups = GroupPath::orderBy('nama')->get();
        $branches = \App\Models\RawBranch::orderBy('name')->get(); // branch

        return view('set.usermanagement.edit', compact('user', 'groups', 'branches'));
    }

    public function update($id)
    {
        request()->validate([
            'nama' => 'required',
            'email' => 'required|email|unique:users,email,' . $id,
            'group_id' => 'required'
        ]);

        $user = Users::find($id);
        $statusLama = (int) $user->status;
        $asli = $user->getOriginal();
        $user->update([
            'nama'          => request('nama'),
            'email'         => request('email'),
            'group_id'      => request('group_id'),
            'raw_branch_id' => request('raw_branch_id'),
            'status'        => request('status', $user->status),
        ]);

        if ($user->getChanges()) {
            \App\Models\AuditLog::record('update', 'Ubah akun: ' . $user->nama, $user,
                array_intersect_key($asli, $user->getChanges()));
        }

        // Akun dinonaktifkan → perangkat "Ingat perangkat ini" dicabut + sesi
        // aktif ditendang (AuthGuard tak cek ulang status per-request).
        if ($statusLama === 1 && (int) $user->status !== 1) {
            $user->update(['force_logout_at' => now()]);
            app(\App\Services\DeviceAuth::class)->revokeAllFor($id);
        }

        // Sinkronkan posisi checklist mengikuti role/cabang terbaru (auto-aktif).
        try { \App\Models\UserPosition::syncFromUser($user->fresh(), session('id')); } catch (\Throwable $e) {}

        return redirect()->route('setusermanagement')->with('success', 'User berhasil diubah');
    }

    public function destroy($id)
    {
        $user = Users::findOrFail($id);

        if ($user->approvals->count() > 0) {
            return redirect()->route('setusermanagement')->with('error', 'Data sudah digunakan di approval');
        }

        \App\Models\AuditLog::record('delete', 'Hapus akun: ' . $user->nama . ' (' . $user->email . ')');
        // Tutup posisi checklist yang masih terbuka sebelum user hilang.
        try {
            \App\Models\UserPosition::query()->activeOn(\Carbon\Carbon::today())->where('user_id', $id)
                ->update(['effective_to' => now()->subDay()->toDateString(), 'updated_at' => now()]);
        } catch (\Throwable $e) {}
        $user->delete();
        return redirect()->route('setusermanagement')->with('success', 'User berhasil dihapus');
    }

    /**
     * Halaman reset LAMA (form ketik password) sudah pensiun — reset sekarang
     * generate acak dari halaman detail. Redirect ke sana; kalau dibiarkan,
     * IT bisa mengetik password yang lalu DIABAIKAN resetProcess → user
     * dikasih password yang tak pernah berlaku (kegagalan-senyap baru).
     */
    public function reset($id)
    {
        return redirect()->route('setusermanagement.detail', $id);
    }

    /**
     * Reset password oleh IT (Pusat Akun §3.3): generate pw sementara ACAK —
     * IT tidak pernah mengetik/tahu password pilihan orang. must_change_password
     * dinyalakan (login berikutnya wajib ganti) + semua perangkat "diingat"
     * dicabut. Password tampil SEKALI lewat flash (tak disimpan plaintext).
     */
    public function resetProcess($id)
    {
        $user = Users::findOrFail($id);
        $temp = $this->generateTempPassword();

        $user->update([
            'password'             => password_hash($temp, PASSWORD_DEFAULT),
            'must_change_password' => 1,
        ]);
        app(\App\Services\DeviceAuth::class)->revokeAllFor($id);

        \App\Models\AuditLog::record('update', 'Reset password (IT) untuk: ' . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $id)
            ->with('reset_temp_pw', $temp)
            ->with('reset_user', $user->nama);
    }

    /** Paksa logout: tendang semua sesi aktif user (HP hilang / akun disusupi). */
    public function forceLogout($id)
    {
        $user = Users::findOrFail($id);
        $user->update(['force_logout_at' => now()]);
        app(\App\Services\DeviceAuth::class)->revokeAllFor($id);

        \App\Models\AuditLog::record('update', 'Paksa logout: ' . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $id)
            ->with('success', 'Semua sesi ' . $user->nama . ' diakhiri (berlaku dalam ≤1 menit). Perangkat "diingat" juga dicabut.');
    }

    /** Cabut satu perangkat "diingat". */
    public function revokeDevice($id, $deviceId)
    {
        $user = Users::findOrFail($id);
        $device = DB::table('user_device')->where('id', $deviceId)->where('user_id', $id)->first();
        if (! $device) {
            return redirect()->route('setusermanagement.detail', $id)->with('error', 'Perangkat tidak ditemukan.');
        }
        // whereNull: jangan timpa waktu cabut asli kalau sudah pernah dicabut
        DB::table('user_device')->where('id', $deviceId)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        // $user dilewatkan sebagai model → model_id terisi → muncul di Riwayat detail
        \App\Models\AuditLog::record('update', 'Cabut perangkat (' . ($device->label ?: 'Perangkat') . '): ' . $user->nama, $user);

        return redirect()->route('setusermanagement.detail', $id)->with('success', 'Perangkat dicabut.');
    }

    /** Password sementara: acak, tanpa karakter ambigu (I/L/O/0/1), berkelompok. */
    private function generateTempPassword(): string
    {
        $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $s = '';
        for ($i = 0; $i < 9; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        return substr($s, 0, 3) . '-' . substr($s, 3, 3) . '-' . substr($s, 6, 3);
    }

    public function approval($id)
    {
        $user = Users::find($id);
        $users = Users::with('group')->whereNotIn('id', [$id])->get();
        $approvals = UserApproval::where('approved_by', $id)->get();
        $slcuser = [];

        if ($approvals) foreach ($approvals as $approval) {
            $slcuser[] = $approval->user_id;
        }

        return view('set.usermanagement.approval', compact('user', 'users', 'slcuser'));
    }

    public function approvalProcess($id)
    {
        $users_id = request()->input('users_id');

        DB::beginTransaction();

        try {
            UserApproval::where('approved_by', $id)->delete();

            if ($users_id) {
                foreach ($users_id as $user_id) {
                    UserApproval::create([
                        'user_id' => $user_id,
                        'approved_by' => $id
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('setusermanagement')->with('success', 'Approval berhasil disimpan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('setusermanagement')->with('error', 'Terjadi kesalahan saat menyimpan approval', $e->getMessage());
        }
    }
}
