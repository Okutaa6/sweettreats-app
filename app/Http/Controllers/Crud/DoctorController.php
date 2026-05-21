<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use App\Models\RawBranch;

class DoctorController extends Controller
{
    public function index()
    {
        $perPage = $this->perPage;

        $filterSearch = request('search');
        $filterBranch = request('branch_id');

        $sessionBranch = session('raw_branch_id');
        $query = RawDoctor::withCount('schedules', 'reservations')->orderBy('name');

        if ($sessionBranch) {
            $query->whereHas('schedules', function ($q) use ($sessionBranch) {
                $q->where('raw_branch_id', $sessionBranch);
            });
        } elseif ($filterBranch) {
            $query->whereHas('schedules', function ($q) use ($filterBranch) {
                $q->where('raw_branch_id', $filterBranch);
            });
        }

        if ($filterSearch) {
            $query->where('name', 'like', "%{$filterSearch}%");
        }

        $doctors = $query->paginate($perPage)->withQueryString();

        $branches = $sessionBranch ? collect() : RawBranch::orderBy('name')->get();

        return view('crud.doctor.list', compact('doctors', 'branches', 'filterSearch', 'filterBranch'));
    }

    public function create()
    {
        return view('crud.doctor.new');
    }

    public function store()
    {
        request()->validate([
            'name'  => 'required|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $doctor = RawDoctor::create([
            'name' => request('name'),
        ]);

        if (request()->hasFile('photo')) {
            $filename = $this->saveDoctorPhoto(request()->file('photo'), $doctor->id);
            $doctor->update(['photo' => $filename]);
        }

        return redirect()->route('rawdoctor')->with('success', 'Doctor berhasil ditambahkan');
    }

    private function saveDoctorPhoto($file, string $doctorId): string
    {
        $uploadDir = public_path('uploads/doctors');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $doctorId . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $filename);

        return $filename;
    }

    public function edit($id)
    {
        $doctor = RawDoctor::findOrFail($id);
        $sessionBranch = session('raw_branch_id');
        $branches = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        $schedules = RawDoctorSchedule::with('branch')
            ->where('raw_doctor_id', $id)
            ->orderBy('day')
            ->orderBy('start_hour')
            ->get();

            //new
        $allServices     = \App\Models\RawDocService::with(['allDetails'])->orderBy('name')->get();
        // Capability (perawatan yang dilayani dokter) — UNIVERSAL, bukan per-cabang.
        $doctorServiceIds = \App\Models\RawDoctorService::where('raw_doctor_id', $id)
            ->pluck('raw_doc_service_id')->toArray();

        // Cabang tempat dokter ini punya jadwal (target editing durasi per-cabang).
        // Sales yang terkunci 1 cabang → hanya cabang itu.
        $doctorBranchIds = RawDoctorSchedule::where('raw_doctor_id', $id)
            ->distinct()->pluck('raw_branch_id')->toArray();
        if ($sessionBranch) {
            $doctorBranchIds = array_values(array_intersect($doctorBranchIds, [$sessionBranch]));
        }
        $doctorBranches = RawBranch::whereIn('id', $doctorBranchIds)->orderBy('name')->get();

        // Durasi override PER-CABANG: [branchId][serviceId] => durasi.
        $branchServiceDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')
            ->where('raw_doctor_id', $id)
            ->get()
            ->groupBy('raw_branch_id')
            ->map(fn($rows) => $rows->pluck('duration_minutes', 'raw_doc_service_id')->toArray())
            ->toArray();

        // Durasi override sub-detail PER-CABANG: [branchId][detailId] => durasi.
        $branchDetailDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')
            ->where('raw_doctor_id', $id)
            ->get()
            ->groupBy('raw_branch_id')
            ->map(fn($rows) => $rows->pluck('duration_minutes', 'raw_doc_service_detail_id')->toArray())
            ->toArray();

        $leaves = \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $id)
            ->orderByDesc('start_date')
            ->get();

        $exceptions = RawDoctorScheduleException::with('branch')
            ->where('raw_doctor_id', $id)
            ->orderByDesc('date')
            ->get();

        return view('crud.doctor.edit', compact(
            'doctor', 'schedules', 'branches',
            'allServices', 'doctorServiceIds',
            'doctorBranches', 'branchServiceDurations', 'branchDetailDurations',
            'leaves', 'exceptions'
        ));
    }

    // === Doctor Leave (Cuti) ===

    public function leaveStore($doctorId)
    {
        request()->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|max:255',
        ]);

        \Illuminate\Support\Facades\DB::table('raw_doctor_leave')->insert([
            'id'            => \Illuminate\Support\Str::uuid()->toString(),
            'raw_doctor_id' => $doctorId,
            'start_date'    => request('start_date'),
            'end_date'      => request('end_date'),
            'reason'        => request('reason'),
            'created_by'    => session('id'),
            'created_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', $doctorId)->with('success', 'Cuti berhasil ditambahkan');
    }

    public function leaveDestroy($doctorId, $leaveId)
    {
        \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $doctorId)
            ->where('id', $leaveId)
            ->delete();

        return redirect()->route('rawdoctor.edit', $doctorId)->with('success', 'Cuti berhasil dihapus');
    }

    public function servicesUpdate($doctorId)
    {
        $doctor = RawDoctor::findOrFail($doctorId);
        $userId = session('id');

        // ── 1. Capability (perawatan yang dilayani dokter) — UNIVERSAL ──
        // Tidak menyimpan durasi di sini lagi; durasi pindah ke per-cabang.
        $services = request('services', []);
        \App\Models\RawDoctorService::where('raw_doctor_id', $doctorId)->delete();
        foreach ($services as $serviceId) {
            \Illuminate\Support\Facades\DB::table('raw_doctor_service')->insert([
                'id'                => \Illuminate\Support\Str::uuid()->toString(),
                'raw_doctor_id'     => $doctorId,
                'raw_doc_service_id'=> $serviceId,
                'duration_minutes'  => null, // legacy column — durasi kini per-cabang
                'created_by'        => $userId,
                'created_at'        => now(),
            ]);
        }

        // ── 2. Durasi override PER-CABANG ──────────────────────────────
        // Format input: services_duration[$branchId][$serviceId] = '10' / ''
        //               details_duration[$branchId][$detailId]   = '15' / ''
        // Delete-then-insert HANYA untuk cabang yang dikirim di form (jadi
        // Sales yang terkunci 1 cabang tidak menghapus durasi cabang lain).
        if (config('features.flexible_scheduling')) {
            $allowedDetailIds = \App\Models\RawDocServiceDetail::whereIn('raw_doc_service_id', $services)
                ->pluck('id')->all();

            $svcDurByBranch    = request('services_duration', []);
            $detailDurByBranch = request('details_duration', []);
            $branchIds = array_unique(array_merge(
                array_keys($svcDurByBranch), array_keys($detailDurByBranch)
            ));

            foreach ($branchIds as $branchId) {
                // service-level
                \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->delete();
                foreach (($svcDurByBranch[$branchId] ?? []) as $serviceId => $rawDur) {
                    if ($rawDur === null || $rawDur === '') {
                        continue; // kosong = pakai durasi default service
                    }
                    if (!in_array($serviceId, $services, true)) {
                        continue; // skip kalau service tidak dicentang
                    }
                    \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')->insert([
                        'id'                 => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_doctor_id'      => $doctorId,
                        'raw_branch_id'      => $branchId,
                        'raw_doc_service_id' => $serviceId,
                        'duration_minutes'   => (float) $rawDur,
                        'created_by'         => $userId,
                        'created_at'         => now(),
                    ]);
                }

                // sub-detail
                \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->delete();
                foreach (($detailDurByBranch[$branchId] ?? []) as $detailId => $rawDur) {
                    if ($rawDur === null || $rawDur === '') {
                        continue;
                    }
                    if (!in_array($detailId, $allowedDetailIds, true)) {
                        continue; // skip kalau parent service tidak dicentang
                    }
                    \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')->insert([
                        'id'                       => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_doctor_id'            => $doctorId,
                        'raw_branch_id'            => $branchId,
                        'raw_doc_service_detail_id'=> $detailId,
                        'duration_minutes'         => (float) $rawDur,
                        'created_by'               => $userId,
                        'created_at'               => now(),
                    ]);
                }
            }
        }

        return redirect()->route('rawdoctor.edit', $doctorId)
            ->with('success', 'Perawatan & durasi dokter berhasil diperbarui');
    }

    // === Doctor Schedule Exception (pengecualian jadwal per tanggal) ===

    public function exceptionStore($doctorId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        request()->validate([
            'raw_branch_id' => 'required',
            'date'          => 'required|date',
            'type'          => 'required|in:replace,add',
            'start_hour'    => 'required',
            'end_hour'      => 'required|after:start_hour',
            'reason'        => 'nullable|max:255',
        ]);

        RawDoctor::findOrFail($doctorId);

        RawDoctorScheduleException::create([
            'raw_doctor_id' => $doctorId,
            'raw_branch_id' => request('raw_branch_id'),
            'date'          => request('date'),
            'type'          => request('type'),
            'start_hour'    => request('start_hour'),
            'end_hour'      => request('end_hour'),
            'reason'        => request('reason'),
            'created_by'    => session('id'),
            'created_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', $doctorId)
            ->with('success', 'Pengecualian jadwal berhasil ditambahkan');
    }

    public function exceptionUpdate($doctorId, $exceptionId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        request()->validate([
            'raw_branch_id' => 'required',
            'date'          => 'required|date',
            'type'          => 'required|in:replace,add',
            'start_hour'    => 'required',
            'end_hour'      => 'required|after:start_hour',
            'reason'        => 'nullable|max:255',
        ]);

        $exception = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
            ->findOrFail($exceptionId);

        $exception->update([
            'raw_branch_id' => request('raw_branch_id'),
            'date'          => request('date'),
            'type'          => request('type'),
            'start_hour'    => request('start_hour'),
            'end_hour'      => request('end_hour'),
            'reason'        => request('reason'),
            'updated_by'    => session('id'),
            'updated_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', $doctorId)
            ->with('success', 'Pengecualian jadwal berhasil diubah');
    }

    public function exceptionDestroy($doctorId, $exceptionId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        $exception = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
            ->findOrFail($exceptionId);

        $exception->delete();

        return redirect()->route('rawdoctor.edit', $doctorId)
            ->with('success', 'Pengecualian jadwal berhasil dihapus');
    }

    public function update($id)
    {
        $isSales = session('group_name') === 'Sales';

        request()->validate([
            'name'  => 'required|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $doctor = RawDoctor::findOrFail($id);
        $data   = ['name' => request('name')];

        // Sales tidak boleh upload/ganti foto
        if (!$isSales && request()->hasFile('photo')) {
            if ($doctor->photo) {
                $oldPath = public_path('uploads/doctors/' . $doctor->photo);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $data['photo'] = $this->saveDoctorPhoto(request()->file('photo'), $doctor->id);
        }

        $doctor->update($data);

        return redirect()->route('rawdoctor.edit', $id)->with('success', 'Doctor berhasil diubah');
    }

    public function deletePhoto($id)
    {
        if (session('group_name') === 'Sales') {
            return redirect()->route('rawdoctor.edit', $id)
                ->with('error', 'Anda tidak memiliki akses untuk menghapus foto dokter.');
        }

        $doctor = RawDoctor::findOrFail($id);

        if ($doctor->photo) {
            $path = public_path('uploads/doctors/' . $doctor->photo);
            if (is_file($path)) {
                @unlink($path);
            }
            $doctor->update(['photo' => null]);
        }

        return redirect()->route('rawdoctor.edit', $id)->with('success', 'Foto dokter berhasil dihapus');
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
            'created_by' => session('id'),
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
            'updated_by' => session('id'),
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

    public function schedulesJson($doctorId)
    {
        $schedules = RawDoctorSchedule::with('branch')
            ->where('raw_doctor_id', $doctorId)
            ->get();

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $branchColors = ['#42a5f5', '#66bb6a', '#ffa726', '#ab47bc', '#ef5350', '#26c6da'];

        $branchColorMap = [];
        $colorIdx = 0;

        $events = $schedules->map(function ($s) use ($dayNames, $branchColors, &$branchColorMap, &$colorIdx) {
            if (!isset($branchColorMap[$s->raw_branch_id])) {
                $branchColorMap[$s->raw_branch_id] = $branchColors[$colorIdx % count($branchColors)];
                $colorIdx++;
            }

            return [
                'id'        => $s->id,
                'title'     => ($s->branch->name ?? '-') . "\n" . substr($s->start_hour, 0, 5) . '-' . substr($s->end_hour, 0, 5),
                'daysOfWeek'=> [$s->day % 7], // FullCalendar: 0=Sun, 1=Mon. DB: 1=Mon, 7=Sun
                'startTime' => substr($s->start_hour, 0, 5),
                'endTime'   => substr($s->end_hour, 0, 5),
                'color'     => $branchColorMap[$s->raw_branch_id],
                'extendedProps' => [
                    'branch_id'   => $s->raw_branch_id,
                    'branch_name' => $s->branch->name ?? '-',
                    'day'         => $s->day,
                    'day_name'    => $dayNames[$s->day] ?? '-',
                ],
            ];
        });

        return response()->json($events);
    }

    public function scheduleUpdateAjax($doctorId, $scheduleId)
    {
        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        $schedule->update([
            'day'        => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour'   => request('end_hour'),
            'updated_by' => session('id'),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
