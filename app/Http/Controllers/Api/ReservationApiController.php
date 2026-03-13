<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorService;
use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;
use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationApiController extends Controller
{
    // FLOW BARU: Cabang → Perawatan → Dokter → Jadwal
  
    /*
     * GET /api/reservation/services
     */
    public function getServices()
    {
        $services = RawDocService::orderBy('name')
            ->get(['id', 'name', 'duration_minutes']);

        return response()->json($services);
    }

    /*
     * GET /api/reservation/doctors-by-service/{branchId}/{serviceId}
     */
    public function getDoctorsByService($branchId, $serviceId)
    {
        // Ambil doctor_id yang bisa handle service ini
        $doctorIdsWithService = RawDoctorService::where('raw_doc_service_id', $serviceId)
            ->pluck('raw_doctor_id');

        // Dari daftar tadi, filter yang punya jadwal di cabang ini
        $doctorIdsAtBranch = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->whereIn('raw_doctor_id', $doctorIdsWithService)
            ->distinct()
            ->pluck('raw_doctor_id');

        $doctors = RawDoctor::whereIn('id', $doctorIdsAtBranch)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($doctors);
    }
    /**

     * GET /api/reservation/dates/{branchId}/{doctorId}
     */
    public function getAvailableDates($branchId, $doctorId)
    {
        $scheduleDays = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->pluck('day')
            ->unique()
            ->toArray();

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $dates = [];

for ($i = 0; $i < 90; $i++) {
              $date = Carbon::today()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso; // 1=Senin ... 7=Minggu

            if (in_array($dayOfWeek, $scheduleDays)) {
                $dates[] = [
                    'date'  => $date->format('Y-m-d'),
                    'label' => $dayNames[$dayOfWeek] . ', ' . $date->format('d M Y'),
                    'day'   => $dayOfWeek,
                ];
            }
        }

        return response()->json($dates);
    }

    /**
     * GET /api/reservation/slots/{branchId}/{doctorId}/{date}
     */
    public function getTimeSlots($branchId, $doctorId, $date)
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek  = $carbonDate->dayOfWeekIso;

        $schedules = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->where('day', $dayOfWeek)
            ->orderBy('start_hour')
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([]);
        }

        // Interval slot 30 menit (fixed, tidak tergantung service)
        $slotInterval = 30;

        // Reservasi yang udah ada untuk dokter ini di tanggal ini
        // exclude_id: untuk reschedule, kecualikan reservasi yang sedang diedit
        $excludeId = request('exclude_id');

        $reservations = RawCusReservation::where('raw_doctor_id', $doctorId)
            ->where('date', $date)
            ->whereNotNull('start_hour')
            ->whereNotNull('end_hour')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();

        $bookedRanges = $reservations->map(fn($r) => [
            'start' => Carbon::parse($r->start_hour)->format('H:i'),
            'end'   => Carbon::parse($r->end_hour)->format('H:i'),
        ])->toArray();

        $slots = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($schedule->start_hour);
            $end   = Carbon::parse($schedule->end_hour);

            while ($start->copy()->addMinutes($slotInterval)->lte($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd   = $start->copy()->addMinutes($slotInterval)->format('H:i');

                // Cek apa slot ini udah terisi
                $isBooked = false;
                foreach ($bookedRanges as $range) {
                    if ($slotStart < $range['end'] && $slotEnd > $range['start']) {
                        $isBooked = true;
                        break;
                    }
                }

                // Cek apa slot udah lewat (buat hari ini)
                $isPast = $carbonDate->isToday() && Carbon::now()->format('H:i') > $slotStart;

                $slots[] = [
                    'time'      => $slotStart,
                    'end_time'  => $slotEnd,
                    'label'     => $slotStart,
                    'is_booked' => $isBooked,
                    'is_past'   => $isPast,
                    'available' => !$isBooked && !$isPast,
                ];

                $start->addMinutes($slotInterval);
            }
        }

        return response()->json([
            'slots'         => $slots,
            'slot_interval' => $slotInterval,
        ]);
    }

    /**
     * GET /api/reservation/doctors/{branchId}
     */
    public function getDoctorsByBranch($branchId)
    {
        $doctorIds = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->distinct()
            ->pluck('raw_doctor_id');

        $doctors = RawDoctor::whereIn('id', $doctorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($doctors);
    }

    /**
     * GET /api/reservation/services-by-doctor/{doctorId}
     * Kembalikan layanan yang bisa dilakukan dokter ini (via pivot raw_doctor_service)
     * Termasuk sub-detail tiap layanan
     */
    public function getServicesByDoctor($doctorId)
    {
        $serviceIds = RawDoctorService::where('raw_doctor_id', $doctorId)
            ->pluck('raw_doc_service_id');

        $services = RawDocService::whereIn('id', $serviceIds)
            ->with(['details' => function($q) {
                $q->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price']);

        return response()->json($services);
    }

    /**
     * GET /api/reservation/branches/{doctorId}
     * Kembalikan daftar cabang tempat dokter ini punya jadwal
     */
    public function getBranchesByDoctor($doctorId)
    {
        $branchIds = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->distinct()
            ->pluck('raw_branch_id');

        $branches = RawBranch::whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($branches);
    }
}