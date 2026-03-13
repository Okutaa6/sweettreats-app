<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;

class BranchController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $branches = RawBranch::withCount('doctorSchedules', 'reservations')
            ->orderBy('name')
            ->paginate($perPage);

        return view('crud.branch.list', compact('branches'));
    }

    public function create()
    {
        return view('crud.branch.new');
    }

    public function store()
    {
        request()->validate([
            'name' => 'required|max:255',
        ]);

        RawBranch::create([
            'name' => request('name'),
        ]);

        return redirect()->route('rawbranch')->with('success', 'Cabang berhasil ditambahkan');
    }

    public function edit($id)
    {
        $branch = RawBranch::findOrFail($id);

        return view('crud.branch.edit', compact('branch'));
    }

    public function update($id)
    {
        request()->validate([
            'name' => 'required|max:255',
        ]);

        $branch = RawBranch::findOrFail($id);
        $branch->update([
            'name' => request('name'),
        ]);

        return redirect()->route('rawbranch')->with('success', 'Cabang berhasil diubah');
    }

    public function destroy($id)
    {
        $branch = RawBranch::findOrFail($id);

        if ($branch->doctorSchedules()->count() > 0) {
            return redirect()->route('rawbranch')->with('error', 'Data tidak dapat dihapus karena masih memiliki jadwal dokter');
        }

        if ($branch->reservations()->count() > 0) {
            return redirect()->route('rawbranch')->with('error', 'Data tidak dapat dihapus karena masih memiliki reservasi');
        }

        $branch->delete();
        return redirect()->route('rawbranch')->with('success', 'Cabang berhasil dihapus');
    }
}
