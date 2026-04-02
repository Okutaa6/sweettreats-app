<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawBranchTarget;

class BranchController extends Controller
{
    public function index()
    {
        $perPage  = $this->perPage;
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

        $tahun = request('tahun', now()->year);

        $targets = RawBranchTarget::where('cabang_id', $id)
            ->where('tahun', $tahun)
            ->get()
            ->keyBy('bulan'); // key by bulan agar mudah diakses di view

        $tahunList = range(now()->year - 5, now()->year + 2);

        return view('crud.branch.edit', compact('branch', 'targets', 'tahun', 'tahunList'));
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

        return redirect()->route('rawbranch.edit', $id)->with('success', 'Cabang berhasil diubah');
    }


    public function saveTarget($id)
    {
        request()->validate([
            'tahun'    => 'required|integer|min:2020|max:2100',
            'nominal'  => 'required|array',
            'nominal.*'=> 'nullable|numeric|min:0',
        ]);

        $tahun = request('tahun');

        foreach (request('nominal') as $bulan => $nominal) {
            if (!in_array($bulan, range(1, 12))) continue;

            RawBranchTarget::updateOrCreate(
                [
                    'cabang_id' => $id,
                    'tahun'     => $tahun,
                    'bulan'     => $bulan,
                ],
                [
                    'nominal' => $nominal ?: 0,
                ]
            );
        }

        return redirect()
            ->route('rawbranch.edit', ['id' => $id, 'tahun' => $tahun])
            ->with('success', 'Target bulanan tahun ' . $tahun . ' berhasil disimpan');
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