<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawBranchTarget;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * Aturan validasi slug URL publik (dipakai form Pasien Baru per cabang).
     * Kosong = biarkan di-generate otomatis dari nama.
     */
    private function slugRules(?string $ignoreId = null): array
    {
        return [
            'slug' => [
                'nullable', 'string', 'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('raw_branch', 'slug')->ignore($ignoreId),
            ],
        ];
    }

    /** @return array<string,string> */
    private function slugMessages(): array
    {
        return [
            'slug.regex'  => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (mis. bintaro-2).',
            'slug.unique' => 'Slug ini sudah dipakai cabang lain.',
        ];
    }

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
        request()->validate(
            ['name' => 'required|max:255'] + $this->slugRules(),
            $this->slugMessages()
        );

        RawBranch::create([
            'name' => request('name'),
            'slug' => request('slug') ?: RawBranch::makeUniqueSlug(request('name')),
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
        request()->validate(
            ['name' => 'required|max:255'] + $this->slugRules($id),
            $this->slugMessages()
        );

        $branch = RawBranch::findOrFail($id);
        $branch->update([
            'name' => request('name'),
            'slug' => request('slug') ?: RawBranch::makeUniqueSlug(request('name'), $id),
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