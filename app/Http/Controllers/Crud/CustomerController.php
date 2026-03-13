<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawCustomer;

class CustomerController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $customers = RawCustomer::withCount('reservations')
            ->orderBy('name')
            ->paginate($perPage);

        return view('crud.customer.list', compact('customers'));
    }

    public function create()
    {
        return view('crud.customer.new');
    }

    public function store()
    {
        request()->validate([
            'name' => 'required|max:255',
            'phone' => 'nullable|max:20',
            'source_app' => 'nullable|max:255',
            'channel' => 'nullable|max:255',
        ]);

        RawCustomer::create([
            'name' => request('name'),
            'phone' => request('phone'),
            'source_app' => request('source_app'),
            'channel' => request('channel'),
        ]);

        return redirect()->route('rawcustomer')->with('success', 'Customer berhasil ditambahkan');
    }

    public function edit($id)
    {
        $customer = RawCustomer::findOrFail($id);

        return view('crud.customer.edit', compact('customer'));
    }

    public function update($id)
    {
        request()->validate([
            'name' => 'required|max:255',
            'phone' => 'nullable|max:20',
            'source_app' => 'nullable|max:255',
            'channel' => 'nullable|max:255',
        ]);

        $customer = RawCustomer::findOrFail($id);
        $customer->update([
            'name'          => request('name'),
            'phone'         => request('phone'),
            'email'         => request('email'),
            'jenis_kelamin' => request('jenis_kelamin'), //new
            'tanggal_lahir' => request('tanggal_lahir'), //new
            'nik'           => request('nik'), //new
            'tempat_lahir'  => request('tempat_lahir'), //new
            'alamat'        => request('alamat'), //new
            'source_app'    => request('source_app'),
            'channel'       => request('channel'),
        ]);

        return redirect()->route('rawcustomer')->with('success', 'Customer berhasil diubah');
    }

    public function destroy($id)
    {
        $customer = RawCustomer::findOrFail($id);

        if ($customer->reservations()->count() > 0) {
            return redirect()->route('rawcustomer')->with('error', 'Data tidak dapat dihapus karena masih memiliki reservasi');
        }

        if ($customer->journeys()->count() > 0) {
            return redirect()->route('rawcustomer')->with('error', 'Data tidak dapat dihapus karena masih memiliki journey');
        }

        $customer->delete();
        return redirect()->route('rawcustomer')->with('success', 'Customer berhasil dihapus');
    }
}
