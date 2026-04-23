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
    $sessionBranch = session('raw_branch_id');

    // Summary cards
    $todayCount = RawCusReservation::where('date', $today)->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))->count();
    $pendingCount = RawCusReservation::where('status', RawCusReservation::STATUS_PENDING)->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))->count();
    $waitlistToday = RawCusReservation::where('date', $today)->where('status', RawCusReservation::STATUS_WAITLIST)->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))->count();
    $confirmedToday = RawCusReservation::where('date', $today)->where('status', RawCusReservation::STATUS_CONFIRMED)->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))->count();

    // Chart: tren 7 hari terakhir
    $trendData = RawCusReservation::select(DB::raw('DATE(date) as tgl'), DB::raw('COUNT(*) as total'))
      ->where('date', '>=', $today->copy()->subDays(6))
      ->where('date', '<=', $today)
      ->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))
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
      ->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))
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

    // Tabel reservasi hari ini (group by dokter, lalu jam)
    $todayReservations = RawCusReservation::with(['customer', 'doctor', 'branch', 'services.docService'])
      ->where('date', $today)
      ->when($sessionBranch, fn($q) => $q->where('raw_branch_id', $sessionBranch))
      ->orderBy('raw_doctor_id')
      ->orderBy('start_hour')
      ->get();

    return view('dashboard.index', compact(
      'todayCount',
      'pendingCount',
      'waitlistToday',
      'confirmedToday',
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

    $sessionBranch = session('raw_branch_id');
    if ($sessionBranch) {
      $query->where('raw_branch_id', $sessionBranch);
      request()->merge(['branch' => $sessionBranch]); // Kunci filter agar tampilan terselect otomatis
    }

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

    $reservations = $query->orderByDesc('date')->orderBy('raw_doctor_id')->orderBy('start_hour')->paginate(20)->withQueryString();

    $doctors = RawDoctor::orderBy('name')->get();
    $branches = $sessionBranch
        ? RawBranch::where('id', $sessionBranch)->get()
        : RawBranch::orderBy('name')->get();

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
      ->when(session('raw_branch_id'), fn($q) => $q->where('raw_branch_id', session('raw_branch_id')))
      ->orderBy('raw_doctor_id')
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
      ->where('status', RawCusReservation::STATUS_CONFIRMED)
      ->when(session('raw_branch_id'), fn($q) => $q->where('raw_branch_id', session('raw_branch_id')))
      ->orderBy('raw_doctor_id')
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
      ->when(session('raw_branch_id'), fn($q) => $q->where('raw_branch_id', session('raw_branch_id')))
      ->orderByDesc('date')
      ->orderBy('raw_doctor_id')
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

    $oldStatus = $reservation->status;

    $reservation->update([
      'status' => $newStatus,
      'updated_at' => now(),
      'updated_by' => session('id'),
    ]);

    \App\Models\AuditLog::record('status_change', "Reservasi status: {$oldStatus} → {$newStatus}", $reservation, ['status' => $oldStatus]);

    // Push notification for status change
    $customerName = $reservation->customer->name ?? 'Unknown';
    \App\Models\AdminNotification::notify(
      'status_change',
      "Status Reservasi: {$oldStatus} → {$newStatus}",
      "Reservasi {$customerName} diubah oleh " . session('name'),
      url("crud/reservation/{$reservation->id}/edit"),
      $reservation->raw_branch_id
    );

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
