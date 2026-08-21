<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\EpKontenPillar;
use App\Models\EpGpDoctor;
use App\Models\RawDoctor;
use Illuminate\Support\Str;

class KontenPillarController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterStatus = request('status', 'all');
        $search       = trim((string) request('search', ''));

        $query = EpKontenPillar::query()->orderBy('sort_order')->orderBy('nama');

        if ($filterStatus === 'active')   $query->where('is_active', 1);
        if ($filterStatus === 'inactive') $query->where('is_active', 0);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $pillars = $query->paginate($perPage)->withQueryString();
        $totalActive   = EpKontenPillar::where('is_active', 1)->count();
        $totalInactive = EpKontenPillar::where('is_active', 0)->count();

        // Tab "Dokter Branding GP"
        $activeTab   = request('tab', 'pillar'); // pillar | gp-doctor
        $gpDoctorIds = EpGpDoctor::pluck('raw_doctor_id')->all();
        $gpDoctors   = EpGpDoctor::with('doctor')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($g) => $g->doctor !== null); // buang membership yang dokternya sudah hilang
        // Dokter yang belum masuk daftar GP → opsi dropdown "tambah"
        $availableDoctors = RawDoctor::active()
            ->whereNotIn('id', $gpDoctorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('crud.konten-pillar.list', compact(
            'pillars', 'filterStatus', 'search',
            'totalActive', 'totalInactive',
            'activeTab', 'gpDoctors', 'availableDoctors'
        ));
    }

    public function create()
    {
        $maxSort = (int) EpKontenPillar::max('sort_order');
        return view('crud.konten-pillar.new', ['nextSort' => $maxSort + 1]);
    }

    public function store()
    {
        $data = $this->validatePillar();
        $data['id']         = (string) Str::uuid();
        $data['slug']       = $this->generateSlug($data['nama']);
        $data['created_by'] = session('id');
        $data['created_at'] = now();

        EpKontenPillar::create($data);

        if (request()->expectsJson()) {
            return response()->json([
                'success'  => true,
                'id'       => $data['id'],
                'message'  => 'Pillar ditambahkan.',
                'edit_url' => route('kontenpillar.edit', $data['id']),
            ]);
        }

        return redirect()->route('kontenpillar')->with('success', 'Pillar "' . $data['nama'] . '" ditambahkan.');
    }

    public function edit($id)
    {
        $pillar = EpKontenPillar::findOrFail($id);
        return view('crud.konten-pillar.edit', compact('pillar'));
    }

    public function update($id)
    {
        $pillar = EpKontenPillar::findOrFail($id);
        $data   = $this->validatePillar($id);

        // Slug regen kalau nama berubah (tetap unique)
        if ($data['nama'] !== $pillar->nama) {
            $data['slug'] = $this->generateSlug($data['nama'], $id);
        }

        $data['updated_at'] = now();
        $pillar->update($data);

        if (request()->expectsJson()) {
            $pillar->refresh();
            return response()->json([
                'success'          => true,
                'message'          => 'Pillar berhasil diubah.',
                'slug'             => $pillar->slug,
                'updated_at_human' => $pillar->updated_at?->diffForHumans(),
            ]);
        }

        return redirect()->route('kontenpillar.edit', $id)->with('success', 'Pillar berhasil diubah.');
    }

    public function destroy($id)
    {
        $pillar = EpKontenPillar::with('kontens')->findOrFail($id);

        if ($pillar->kontens()->count() > 0) {
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak bisa dihapus — masih dipakai di ' . $pillar->kontens()->count() . ' konten EP.',
                ], 422);
            }
            return redirect()->route('kontenpillar')
                ->with('error', 'Tidak bisa dihapus — masih dipakai di ' . $pillar->kontens()->count() . ' konten EP. Set Nonaktif saja kalau mau hide.');
        }

        $pillar->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => 'Pillar dihapus.',
                'redirect_url' => route('kontenpillar'),
            ]);
        }

        return redirect()->route('kontenpillar')->with('success', 'Pillar dihapus.');
    }

    private function validatePillar(?string $excludeId = null): array
    {
        return request()->validate([
            'nama'       => 'required|max:100',
            'deskripsi'  => 'nullable|max:255',
            'color'      => 'nullable|max:20',
            'sort_order' => 'required|integer|min:0',
            'is_active'  => 'nullable',
        ]) + [
            'is_active' => request('is_active', 0) ? 1 : 0,
        ];
    }

    private function generateSlug(string $nama, ?string $excludeId = null): string
    {
        $base = Str::slug($nama);
        $slug = $base;
        $i = 2;

        while (EpKontenPillar::where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
