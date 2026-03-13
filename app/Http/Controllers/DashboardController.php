<?php

namespace App\Http\Controllers;

use App\Models\RawCusReservation;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
  /**
   * Ringkasan — summary cards + charts + tabel hari ini
   */
  public function index()
  {
    $today = Carbon::today();

    // Summary cards
    $todayCount = RawCusReservation::where('date', $today)->count();
    $pendingCount = RawCusReservation::where('status', RawCusReservation::STATUS_PENDING)->count();
    $waitlistToday = RawCusReservation::where('date', $today)->where('status', RawCusReservation::STATUS_WAITLIST)->count();
    $ongoingToday = RawCusReservation::where('date', $today)->where('status', RawCusReservation::STATUS_ONGOING)->count();

    // Chart: tren 7 hari terakhir
    $trendData = RawCusReservation::select(DB::raw('DATE(date) as tgl'), DB::raw('COUNT(*) as total'))
      ->where('date', '>=', $today->copy()->subDays(6))
      ->where('date', '<=', $today)
      ->groupBy('tgl')
      ->orderBy('tgl')
      ->get();

    // Fill missing dates with 0
    $trendLabels = [];
    $trendValues = [];
    for ($i = 6; $i >= 0; $i--) {
      $d = $today->copy()->subDays($i)->format('Y-m-d');
      $trendLabels[] = Carbon::parse($d)->format('d M');
      $found = $trendData->firstWhere('tgl', $d);
      $trendValues[] = $found ? $found->total : 0;
    }

    // Chart: donut status hari ini
    $statusData = RawCusReservation::select('status', DB::raw('COUNT(*) as total'))
      ->where('date', $today)
      ->groupBy('status')
      ->get();

    $statusOptions = RawCusReservation::statusOptions();
    $donutLabels = [];
    $donutValues = [];
    $donutColors = [];
    foreach ($statusData as $row) {
      $info = $statusOptions[$row->status] ?? null;
      if ($info) {
        $donutLabels[] = $info['label'];
        $donutValues[] = $row->total;
        $donutColors[] = $info['color'];
      }
    }

    // Tabel reservasi hari ini
    $todayReservations = RawCusReservation::with(['customer', 'doctor', 'branch', 'services.docService'])
      ->where('date', $today)
      ->orderBy('start_hour')
      ->get();

    return view('dashboard.index', compact(
      'todayCount',
      'pendingCount',
      'waitlistToday',
      'ongoingToday',
      'trendLabels',
      'trendValues',
      'donutLabels',
      'donutValues',
      'donutColors',
      'todayReservations'
    ));
  }

  /**
   * Semua Reservasi — dengan filter
   */
  public function reservasi()
  {
    $query = RawCusReservation::with(['customer', 'doctor', 'branch'])
      ->withCount('services');

    // Filters
    if (request('status')) {
      $query->where('status', request('status'));
    }
    if (request('doctor')) {
      $query->where('raw_doctor_id', request('doctor'));
    }
    if (request('branch')) {
      $query->where('raw_branch_id', request('branch'));
    }
    if (request('date_from')) {
      $query->where('date', '>=', request('date_from'));
    }
    if (request('date_to')) {
      $query->where('date', '<=', request('date_to'));
    }

    $reservations = $query->orderByDesc('date')->orderBy('start_hour')->paginate(20)->withQueryString();

    $doctors = RawDoctor::orderBy('name')->get();
    $branches = RawBranch::orderBy('name')->get();

    return view('dashboard.reservasi', compact('reservations', 'doctors', 'branches'));
  }

  /**
   * Waiting List Hari Ini
   */
  public function waitingList()
  {
    $reservations = RawCusReservation::with(['customer', 'doctor', 'branch', 'services.docService'])
      ->where('date', Carbon::today())
      ->where('status', RawCusReservation::STATUS_WAITLIST)
      ->orderBy('start_hour')
      ->get();

    return view('dashboard.waiting-list', compact('reservations'));
  }

  /**
   * Sedang Berlangsung Hari Ini
   */
  public function berlangsung()
  {
    $reservations = RawCusReservation::with(['customer', 'doctor', 'branch', 'services.docService'])
      ->where('date', Carbon::today())
      ->where('status', RawCusReservation::STATUS_ONGOING)
      ->orderBy('start_hour')
      ->get();

    return view('dashboard.berlangsung', compact('reservations'));
  }

  /**
   * Menunggu Konfirmasi — semua tanggal
   */
  public function menungguKonfirmasi()
  {
    $reservations = RawCusReservation::with(['customer', 'doctor', 'branch', 'services.docService'])
      ->where('status', RawCusReservation::STATUS_PENDING)
      ->orderByDesc('date')
      ->orderBy('start_hour')
      ->get();

    return view('dashboard.menunggu', compact('reservations'));
  }

  /**
   * Quick-action: update status via AJAX
   */
  public function updateStatus($id)
  {
    $reservation = RawCusReservation::findOrFail($id);

    $newStatus = request('status');
    $validStatuses = array_keys(RawCusReservation::statusOptions());

    if (!in_array($newStatus, $validStatuses)) {
      return response()->json(['error' => 'Status tidak valid'], 422);
    }

    $reservation->update([
      'status' => $newStatus,
      'updated_at' => now(),
      'updated_by' => session('user_id'),
    ]);

    $si = $reservation->status_info;

    return response()->json([
      'success' => true,
      'status' => $newStatus,
      'label' => $si['label'],
      'icon' => $si['icon'],
      'color' => $si['color'],
      'bg' => $si['bg'],
    ]);
  }
}
