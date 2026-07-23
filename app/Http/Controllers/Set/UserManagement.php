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
        $perPage = $this->perPage;

        $users = Users::with('group')->with('approvals')->paginate($perPage);

        $data = [
            'users' => $users
        ];
        return view('set.usermanagement.list', $data);
    }

    public function create()
    {
        $groups = GroupPath::orderBy('nama')->get();
         $branches = \App\Models\RawBranch::orderBy('name')->get(); // branch

        return view('set.usermanagement.new', compact('groups', 'branches'));
    }

    public function store()
    {
        request()->validate([
            'nama' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'confirmNewPass' => 'required|same:password',
            'group_id' => 'required'
        ]);

        $user = Users::create([
            'nama'          => request('nama'),
            'email'         => request('email'),
            'password'      => bcrypt(request('password')),
            'group_id'      => request('group_id'),
            'raw_branch_id' => request('raw_branch_id'),
            'status'        => 1,
        ]);

        // Role operasional + cabang sudah lengkap → posisi checklist otomatis
        // aktif, tanpa perlu karyawan mengajukan / supervisor konfirmasi.
        try { \App\Models\UserPosition::syncFromUser($user, session('id')); } catch (\Throwable $e) {}

        return redirect()->route('setusermanagement')->with('success', 'User berhasil ditambahkan');
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
        $user->update([
            'nama'          => request('nama'),
            'email'         => request('email'),
            'group_id'      => request('group_id'),
            'raw_branch_id' => request('raw_branch_id'),
            'status'        => request('status', $user->status),
        ]);

        // Sinkronkan posisi checklist mengikuti role/cabang terbaru (auto-aktif).
        try { \App\Models\UserPosition::syncFromUser($user->fresh(), session('id')); } catch (\Throwable $e) {}

        return redirect()->route('setusermanagement')->with('success', 'User berhasil diubah');
    }

    public function destroy($id)
    {
        $user = Users::find($id);
        $approvals = $user->approvals;

        if ($approvals->count() > 0) {
            return redirect()->route('setusermanagement')->with('error', 'Data sudah digunakan di approval');
        }

        $user->delete();
        return redirect()->route('setusermanagement')->with('success', 'User berhasil dihapus');
    }

    public function reset($id)
    {
        $user = Users::find($id);

        return view('set.usermanagement.reset', compact('user'));
    }

    public function resetProcess($id)
    {
        request()->validate([
            'password' => 'required',
            'password_confirmation' => 'required|same:password'
        ]);

        $user = Users::find($id);
        $user->update([
            'password' => bcrypt(request('password')),
        ]);

        return redirect()->route('setusermanagement')->with('success', 'Password berhasil diubah');
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
