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
            ->orderBy('name')
            ->paginate($perPage);

        return view('crud.docservice.list', compact('services'));
    }

    public function create()
    {
        return view('crud.docservice.new');
    }

    public function store()
    {
        request()->validate([
            'name'             => 'required|max:255',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        RawDocService::create([
            'name'             => request('name'),
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'created_at'       => now(),
            'created_by'       => session('user_id'),
        ]);

        return redirect()->route('rawdocservice')->with('success', 'Layanan berhasil ditambahkan');
    }

    public function edit($id)
    {
        $service = RawDocService::with('allDetails')->findOrFail($id);

        return view('crud.docservice.edit', compact('service'));
    }

    public function update($id)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        $service = RawDocService::findOrFail($id);
        $service->update([
            'name'             => request('name'),
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'updated_at'       => now(),
            'updated_by'       => session('user_id'),
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

    // Detail CRUD (sub-service)

    public function detailStore($serviceId)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        RawDocServiceDetail::create([
            'raw_doc_service_id' => $serviceId,
            'name'               => request('name'),
            'duration_minutes'   => request('duration_minutes'),
            'price'              => request('price'),
            'sort_order'         => request('sort_order', 0),
            'is_active'          => 1,
            'created_at'         => now(),
            'created_by'         => session('user_id'),
        ]);

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil ditambahkan');
    }

    public function detailUpdate($serviceId, $detailId)
    {
        request()->validate([
            'name'             => 'required|max:255',
            'duration_minutes' => 'required|numeric|min:1',
            'price'            => 'required|numeric|min:0',
        ]);

        $detail = RawDocServiceDetail::where('raw_doc_service_id', $serviceId)->findOrFail($detailId);
        $detail->update([
            'name'             => request('name'),
            'duration_minutes' => request('duration_minutes'),
            'price'            => request('price'),
            'sort_order'       => request('sort_order', $detail->sort_order),
            'is_active'        => request('is_active', 1),
            'updated_at'       => now(),
            'updated_by'       => session('user_id'),
        ]);

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil diubah');
    }

    public function detailDestroy($serviceId, $detailId)
    {
        $detail = RawDocServiceDetail::where('raw_doc_service_id', $serviceId)->findOrFail($detailId);
        $detail->delete();

        return redirect()->route('rawdocservice.edit', $serviceId)->with('success', 'Sub-detail berhasil dihapus');
    }
}