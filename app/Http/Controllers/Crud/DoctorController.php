<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawBranch;

class DoctorController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $doctors = RawDoctor::withCount('schedules', 'reservations')
            ->orderBy('name')
            ->paginate($perPage);

        return view('crud.doctor.list', compact('doctors'));
    }

    public function create()
    {
        return view('crud.doctor.new');
    }

    public function store()
    {
        request()->validate([
            'name' => 'required|max:255',
        ]);

        RawDoctor::create([
            'name' => request('name'),
        ]);

        return redirect()->route('rawdoctor')->with('success', 'Doctor berhasil ditambahkan');
    }

    public function edit($id)
    {
        $doctor = RawDoctor::findOrFail($id);
        $branches = RawBranch::orderBy('name')->get();

        $schedules = RawDoctorSchedule::with('branch')
            ->where('raw_doctor_id', $id)
            ->orderBy('day')
            ->orderBy('start_hour')
            ->get();

            //new
        $allServices     = \App\Models\RawDocService::orderBy('name')->get();
        $doctorServiceIds = \App\Models\RawDoctorService::where('raw_doctor_id', $id)
            ->pluck('raw_doc_service_id')
            ->toArray();

        return view('crud.doctor.edit', compact('doctor', 'schedules', 'branches', 'allServices', 'doctorServiceIds'));
    }

    public function servicesUpdate($doctorId)
{
    $doctor = RawDoctor::findOrFail($doctorId);

    // Hapus semua service lama
    \App\Models\RawDoctorService::where('raw_doctor_id', $doctorId)->delete();

    // Insert service baru yang diceklist
    $services = request('services', []);
    foreach ($services as $serviceId) {
        \Illuminate\Support\Facades\DB::table('raw_doctor_service')->insert([
            'id'                => \Illuminate\Support\Str::uuid()->toString(),
            'raw_doctor_id'     => $doctorId,
            'raw_doc_service_id'=> $serviceId,
            'created_by'        => session('user_id'),
            'created_at'        => now(),
        ]);
    }

    return redirect()->route('rawdoctor.edit', $doctorId)
        ->with('success', 'Service dokter berhasil diperbarui');
}

    public function update($id)
    {
        request()->validate([
            'name' => 'required|max:255',
        ]);

        $doctor = RawDoctor::findOrFail($id);
        $doctor->update([
            'name' => request('name'),
        ]);

        return redirect()->route('rawdoctor.edit', $id)->with('success', 'Doctor berhasil diubah');
    }

    public function destroy($id)
    {
        $doctor = RawDoctor::findOrFail($id);

        // Check if doctor has schedules or reservations
        if ($doctor->schedules()->count() > 0) {
            return redirect()->route('rawdoctor')->with('error', 'Data tidak dapat dihapus karena masih memiliki jadwal');
        }

        if ($doctor->reservations()->count() > 0) {
            return redirect()->route('rawdoctor')->with('error', 'Data tidak dapat dihapus karena masih memiliki reservasi');
        }

        $doctor->delete();
        return redirect()->route('rawdoctor')->with('success', 'Doctor berhasil dihapus');
    }

    // === Doctor Schedule (Child CRUD) ===

    public function scheduleStore($doctorId)
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'day' => 'required|numeric|min:1|max:7',
            'start_hour' => 'required',
            'end_hour' => 'required',
        ]);

        $doctor = RawDoctor::findOrFail($doctorId);

        RawDoctorSchedule::create([
            'raw_doctor_id' => $doctor->id,
            'raw_branch_id' => request('raw_branch_id'),
            'day' => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour' => request('end_hour'),
            'created_by' => session('user_id'),
            'created_at' => now(),
        ]);

        return redirect()->route('rawdoctor.edit', $doctorId)->with('success', 'Jadwal berhasil ditambahkan');
    }

    public function scheduleUpdate($doctorId, $scheduleId)
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'day' => 'required|numeric|min:1|max:7',
            'start_hour' => 'required',
            'end_hour' => 'required',
        ]);

        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        $schedule->update([
            'raw_branch_id' => request('raw_branch_id'),
            'day' => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour' => request('end_hour'),
            'updated_by' => session('user_id'),
            'updated_at' => now(),
        ]);

        return redirect()->route('rawdoctor.edit', $doctorId)->with('success', 'Jadwal berhasil diubah');
    }

    public function scheduleDestroy($doctorId, $scheduleId)
    {
        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        $schedule->delete();

        return redirect()->route('rawdoctor.edit', $doctorId)->with('success', 'Jadwal berhasil dihapus');
    }
}
