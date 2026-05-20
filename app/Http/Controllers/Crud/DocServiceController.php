<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;

class DocServiceController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;


        $services = RawDocService::withCount('reservationServices')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        // new: kirim daftar kategori ke view buat label
        $categories = RawDocService::categories();

        return view('crud.docservice.list', compact('services', 'categories'));
    }

    public function create()
    {
        // new: kirim daftar kategori ke view buat dropdown
        $categories = RawDocService::categories();

        return view('crud.docservice.new', compact('categories'));
    }

    public function store()
    {
        request()->validate([
            'name'             => 'required|max:255',
            'category' => 'required|in:' . implode(',', array_keys(RawDocService::categories())),'sort_order'=> 'nullable|integer|min:0',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        RawDocService::create([
            'name'             => request('name'),
            'category'         => request('category'), // new: simpan kategori
            'sort_order'       => request('sort_order', 0),
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'created_at'       => now(),
            'created_by'       => session('id'),
        ]);

        return redirect()->route('rawdocservice')->with('success', 'Layanan berhasil ditambahkan');
    }

    public function edit($id)
    {
        $service = RawDocService::with('allDetails')->findOrFail($id);

        // new: kirim daftar kategori ke view buat dropdown
        $categories = RawDocService::categories();

        return view('crud.docservice.edit', compact('service', 'categories'));
    }

    public function update($id)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'category' => 'required|in:' . implode(',', array_keys(RawDocService::categories())),
            'sort_order'       => 'nullable|integer|min:0',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        $service = RawDocService::findOrFail($id);
        $service->update([
            'name'             => request('name'),
            // KATEGORI - BARU: update category & sort_order
            'category'         => request('category'),
            'sort_order'       => request('sort_order', 0),
            // ================================================
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'updated_at'       => now(),
            'updated_by'       => session('id'),
        ]);

        return redirect()->route('rawdocservice.edit', $id)->with('success', 'Layanan berhasil diubah');
    }

    public function destroy($id)
    {
        $service = RawDocService::findOrFail($id);

        if ($service->reservationServices()->count() > 0) {
            return redirect()->route('rawdocservice')->with('error', 'Data tidak dapat dihapus karena masih digunakan di reservasi');
        }

        $service->delete();
        return redirect()->route('rawdocservice')->with('success', 'Layanan berhasil dihapus');
    }

    //  Sub-detail CRUD 
    public function detailStore($serviceId)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'code_service'     => 'nullable|max:100',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        RawDocServiceDetail::create([
            'raw_doc_service_id' => $serviceId,
            'name'               => request('name'),
            'code_service'       => request('code_service') ?: null,
            'duration_minutes'   => request('duration_minutes'),
            'price'              => request('price'),
            'sort_order'         => request('sort_order', 0),
            'is_active'          => 1,
            'created_at'         => now(),
            'created_by'         => session('id'),
        ]);

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil ditambahkan');
    }

    public function detailUpdate($serviceId, $detailId)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'code_service'     => 'nullable|max:100',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        $detail = RawDocServiceDetail::where('raw_doc_service_id', $serviceId)->findOrFail($detailId);
        $detail->update([
            'name'             => request('name'),
            'code_service'     => request('code_service') ?: null,
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'sort_order'       => request('sort_order', $detail->sort_order),
            'is_active'        => request('is_active', 1),
            'updated_at'       => now(),
            'updated_by'       => session('id'),
        ]);

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil diubah');
    }

    public function detailDestroy($serviceId, $detailId)
    {
        $detail = RawDocServiceDetail::where('raw_doc_service_id', $serviceId)->findOrFail($detailId);
        $detail->delete();

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil dihapus');
    }

    public function detailReorder($serviceId)
    {
        $order = request()->input('order', []);
        if (! is_array($order) || empty($order)) {
            return response()->json(['status' => 'error', 'message' => 'Parameter order wajib berupa array berisi id detail.'], 422);
        }

        $detailIds = RawDocServiceDetail::where('raw_doc_service_id', $serviceId)->pluck('id')->all();

        $position = 1;
        foreach ($order as $detailId) {
            if (! in_array($detailId, $detailIds, true)) {
                continue;
            }
            RawDocServiceDetail::where('raw_doc_service_id', $serviceId)
                ->where('id', $detailId)
                ->update([
                    'sort_order' => $position,
                    'updated_at' => now(),
                    'updated_by' => session('id'),
                ]);
            $position++;
        }

        return response()->json(['status' => 'success']);
    }
}