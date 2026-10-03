<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika ketersediaan jadwal & slot dokter (recurring + exception + cuti).
 *
 * Dipisah jadi trait supaya dipakai bersama oleh ReservationApiController
 * (route /api/reservation/*) dan ApiCekatController (endpoint vendor) tanpa
 * duplikasi. Method compute* mengembalikan array MENTAH — pemanggil yang
 * membungkus jadi JSON response sesuai kebutuhannya.
 */
trait ResolvesDoctorAvailability
{
    /*
     * ══════════════════════════════════════════════════════════════════════
     * JALUR PRA-MUAT — opsional, default MATI
     * ══════════════════════════════════════════════════════════════════════
     * Metode compute* di bawah dirancang untuk SATU dokter di SATU tanggal.
     * Itu benar untuk form reservasi, tapi layar yang menampilkan hari praktik
     * untuk 50 baris sekaligus (tabel Tracking Ortho) membayar ~27 kueri per
     * baris — ~1.350 untuk satu muat halaman.
     *
     * Properti di bawah membiarkan pemanggil MENITIPKAN data yang sudah ia
     * ambil sendiri dalam beberapa kueri agregat, sehingga metode ini berhenti
     * menanyai DB per-tanggal.
     *
     * ⚠ Yang diangkat hanya I/O-nya, BUKAN aritmetikanya. subtractBreaks(),
     *   langkah slot, pengelompokan sesi, dan available_count/total_count
     *   tidak disentuh — jadi angka yang keluar tetap identik dengan yang
     *   dilihat grid slot Reservasi. Repo ini sudah punya tiga rumus kapasitas
     *   yang saling berbeda; menambah yang keempat adalah cara membuat dua
     *   layar berbeda pendapat untuk hari yang sama.
     *
     * ⚠ Semuanya DEFAULT null = perilaku lama, kueri seperti biasa. Tujuh
     *   pemanggil trait ini (ReservationApiController, ApiCekatController,
     *   ReservationController, LandingReservationController,
     *   ReservationAutoProcessor, OrtUsulan, OrtJadwalDokter::untuk) tidak
     *   mengisinya, jadi tidak satu baris pun perilakunya berubah.
     *
     * Pengisinya: OrtJadwalDokter::untukBanyak().
     */

    /** @var array<string, \Illuminate\Support\Collection>|null "dokter|cabang" => semua baris jadwal (work+break, semua hari) */
    protected ?array $praMuatJadwal = null;

    /** @var array<string, \Illuminate\Support\Collection>|null dokter => baris cuti {start_date, end_date} */
    protected ?array $praMuatCuti = null;

    /** @var array<string, \Illuminate\Support\Collection>|null "dokter|cabang" => baris pengecualian per tanggal */
    protected ?array $praMuatPengecualian = null;

    /** @var array<string, array>|null "dokter|tanggal" => [['start'=>'H:i','end'=>'H:i'], …] */
    protected ?array $praMuatTerpesan = null;

    /**
     * Batasi penjelajahan computeAvailableDates() ke ['dari','sampai'].
     * Tanpa ini ia memutar 535 hari — benar untuk form reservasi yang memang
     * menawarkan setahun ke depan, pemborosan untuk satu sel tabel.
     *
     * @var array{dari:string,sampai:string}|null
     */
    protected ?array $praMuatJendela = null;

    /**
     * Kembalikan juga tanggal yang DITUTUP cuti, bertanda, alih-alih
     * membuangnya diam-diam.
     *
     * Kenapa ini ada di trait dan bukan di pemanggil: aturan cutinya berlapis
     * — 'replace' menang penuh dan menembus cuti, 'add' membuka hari walau
     * dokter cuti, cuti hanya berlaku kalau tidak ada 'replace'. Menyalin
     * aturan itu ke luar akan melahirkan sumber kebenaran kedua, dan begitu
     * salah satu lapis keliru, satu layar akan bilang "cuti" untuk hari yang
     * layar lain tampilkan terbuka.
     */
    protected bool $praMuatSertakanTertutup = false;

    /** Kunci gabungan yang dipakai peta pra-muat. */
    private function praKunci($a, $b): string
    {
        return (string) $a . '|' . (string) $b;
    }

    /**
     * Tanggal tersedia 90 hari ke depan (min 1 Mei tahun ini), sudah
     * memperhitungkan recurring schedule, exception, dan cuti dokter.
     *
     * @return array<int, array{date:string,label:string,day:int}>
     */
    protected function computeAvailableDates($branchId, $doctorId): array
    {
        // Hanya hari yang punya WORK row yang dianggap "praktik" — hari yang
        // cuma berisi baris istirahat tidak boleh muncul sebagai bookable date.
        $praJadwal = $this->praMuatJadwal[$this->praKunci($doctorId, $branchId)] ?? null;

        $scheduleDays = $praJadwal !== null
            ? $praJadwal->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)
                        ->pluck('day')->unique()->values()->toArray()
            : RawDoctorSchedule::where('raw_branch_id', $branchId)
                ->where('raw_doctor_id', $doctorId)
                ->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)
                ->pluck('day')
                ->unique()
                ->toArray();

        // Ambil semua cuti dokter
        $leaves = $this->praMuatCuti[(string) $doctorId]
            ?? DB::table('raw_doctor_leave')
                ->where('raw_doctor_id', $doctorId)
                ->get(['start_date', 'end_date']);

        $exceptions = collect();
        if (config('features.flexible_scheduling')) {
            $praKecuali = $this->praMuatPengecualian[$this->praKunci($doctorId, $branchId)] ?? null;

            $exceptions = $praKecuali !== null
                ? $praKecuali->whereIn('type', ['add', 'replace'])->pluck('type', 'date')
                : RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->whereIn('type', ['add', 'replace'])
                    ->pluck('type', 'date');
        }

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $dates = [];

        // Batasi tanggal mulai minimal 1 Mei tahun ini
        $minDate = Carbon::create(Carbon::today()->year, 5, 1);
        $startDate = Carbon::today()->max($minDate);

        // Jendela sempit (dipakai sel tabel): mulai dari 'dari' dan berhenti
        // di 'sampai', bukan memutar 535 hari untuk mengambil tiga tanggal.
        $jumlahHari = 535;
        if ($this->praMuatJendela !== null) {
            $startDate  = Carbon::parse($this->praMuatJendela['dari'])->startOfDay();
            $jumlahHari = $startDate->diffInDays(Carbon::parse($this->praMuatJendela['sampai'])) + 1;
        }

        for ($i = 0; $i < $jumlahHari; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso; // 1=Senin ... 7=Minggu
            $dateStr = $date->format('Y-m-d');

            $hasRecurring  = in_array($dayOfWeek, $scheduleDays);
            $hasException  = $exceptions->has($dateStr);

            if (!$hasRecurring && !$hasException) {
                continue;
            }

            // Cuti menutup jadwal REGULER, tapi exception (Hari Tambahan /
            // Jam Berbeda) di tanggal yang sama tetap menang — dokter secara
            // eksplisit membuka/mengubah tanggal itu. Jadi cuti hanya dipakai
            // kalau TIDAK ada exception (konsisten dgn resolveWorkingRanges()).
            $isOnLeave = false;
            if (!$hasException) {
                foreach ($leaves as $leave) {
                    if ($date->between(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                        $isOnLeave = true;
                        break;
                    }
                }
                // Perilaku lama: hari cuti tidak pernah lahir. Pemanggil yang
                // minta sertakanTertutup mendapatkannya bertanda, supaya
                // layarnya bisa bilang "Kamis cuti" alih-alih menghilangkan
                // Kamis tanpa penjelasan.
                if ($isOnLeave && ! $this->praMuatSertakanTertutup) continue;
            }

            $baris = [
                'date'  => $dateStr,
                'label' => $dayNames[$dayOfWeek] . ', ' . $date->format('d M Y'),
                'day'   => $dayOfWeek,
            ];

            if ($this->praMuatSertakanTertutup) {
                $baris['tertutup'] = $isOnLeave ? 'cuti' : null;
            }

            $dates[] = $baris;
        }

        return $dates;
    }

    /**
     * Slot waktu untuk (cabang, dokter, tanggal) tertentu.
     *
     * $opts: service_id, duration (int explicit), exclude_id,
     *        reservation_table (default 'raw_cus_reservation' — sandbox
     *        kirim 'raw_cus_reservation_dev' supaya cek bentrok ke data dev),
     *        with_booking_detail (lihat di bawah).
     *
     * @return array{slots:array,sessions:array,slot_interval:int}
     */
    protected function computeTimeSlots($branchId, $doctorId, $date, array $opts = []): array
    {
        $carbonDate = Carbon::parse($date);

        $serviceId        = $opts['service_id'] ?? null;
        $detailId         = $opts['detail_id'] ?? null;
        $explicitDuration = (int) ($opts['duration'] ?? 0);
        $excludeId        = $opts['exclude_id'] ?? null;
        $reservationTable = $opts['reservation_table'] ?? 'raw_cus_reservation';

        // Detail booking (cabang · jam · nama pasien) MATI BY DEFAULT — cuma
        // dinyalakan endpoint admin di balik AuthGuard. Form publik /reservasi
        // memanggil route publik yang tidak pernah mengirim opsi ini, jadi
        // pasien tak pernah bisa melihat nama pasien lain. Kalau kelak ada yang
        // lupa menyalakan, yang terjadi adalah fitur tak muncul — bukan data
        // bocor. Itu arah gagal yang benar.
        $withDetail = (bool) ($opts['with_booking_detail'] ?? false);

        // === 1. Resolve working ranges untuk tanggal ini ===
        $ranges = $this->resolveWorkingRanges($branchId, $doctorId, $date);

        if ($ranges->isEmpty()) {
            $empty = [
                'slots'         => [],
                'sessions'      => [],
                'slot_interval' => 0,
            ];

            // Dokter tak praktik di cabang ini pada tanggal ini, tapi bisa jadi
            // dia praktik di cabang LAIN — spanduk harinya tetap perlu muncul.
            if ($withDetail) {
                $empty['other_branch_bookings'] = $this->otherBranchBookings(
                    $this->resolveBookedRanges($doctorId, $date, $reservationTable, $excludeId),
                    $branchId
                );
            }

            return $empty;
        }

        // === 2. Resolve durasi slot (durasi override per-cabang + sub-detail) ===
        $duration = $this->resolveDuration($doctorId, $serviceId, $explicitDuration ?: null, $branchId, $detailId);

        // === 3. Reservasi yang udah ada di tanggal ini ===
        // Satu-satunya pemanggil yang mengizinkan pra-muat: perhitungan slot
        // hanya melihat `start`/`end`, tidak pernah kolom detail.
        $bookedRanges = $this->resolveBookedRanges($doctorId, $date, $reservationTable, $excludeId, true);

        // === 4. Generate slot ===
        $slots = [];

        foreach ($ranges as $range) {
            $start = Carbon::parse($range['start_hour']);
            $end   = Carbon::parse($range['end_hour']);

            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd   = $start->copy()->addMinutes($duration)->format('H:i');

                $isBooked = false;
                $bookedBy = null;
                foreach ($bookedRanges as $r) {
                    if ($slotStart < $r['end'] && $slotEnd > $r['start']) {
                        $isBooked = true;
                        $bookedBy = $r;
                        break;
                    }
                }

                $isPast = $carbonDate->isToday() && Carbon::now()->format('H:i') > $slotStart;

                $slot = [
                    'time'      => $slotStart,
                    'end_time'  => $slotEnd,
                    'label'     => $slotStart,
                    'is_booked' => $isBooked,
                    'is_past'   => $isPast,
                    'available' => !$isBooked && !$isPast,
                ];

                // Tanpa $withDetail, bentuk slot persis seperti sebelumnya —
                // tidak ada kunci tambahan sama sekali.
                if ($withDetail && $bookedBy) {
                    $slot['booked_by'] = [
                        'branch_id' => $bookedBy['branch_id'] ?? null,
                        'branch'    => $bookedBy['branch'] ?? null,
                        'nama'      => $bookedBy['patient'] ?? null,
                        'jam'       => $bookedBy['start'] . '–' . $bookedBy['end'],
                    ];
                }

                $slots[] = $slot;

                $start->addMinutes($duration);
            }
        }

        // === 5. Group per sesi (Pagi / Siang / Sore) ===
        $sessions = [
            'pagi'  => ['label' => 'Pagi',  'range' => 'sebelum 12:00', 'slots' => []],
            'siang' => ['label' => 'Siang', 'range' => '12:00 – 16:59', 'slots' => []],
            'sore'  => ['label' => 'Sore',  'range' => '17:00 ke atas',  'slots' => []],
        ];

        foreach ($slots as $slot) {
            $h = (int) substr($slot['time'], 0, 2);
            if ($h < 12) {
                $sessions['pagi']['slots'][] = $slot;
            } elseif ($h < 17) {
                $sessions['siang']['slots'][] = $slot;
            } else {
                $sessions['sore']['slots'][] = $slot;
            }
        }

        foreach ($sessions as $key => $s) {
            $sessions[$key]['available_count'] = collect($s['slots'])->where('available', true)->count();
            $sessions[$key]['total_count']     = count($s['slots']);
        }

        $result = [
            'slots'         => $slots,
            'sessions'      => $sessions,
            'slot_interval' => $duration,
        ];

        if ($withDetail) {
            $result['other_branch_bookings'] = $this->otherBranchBookings($bookedRanges, $branchId);
        }

        return $result;
    }

    /**
     * Resolve duration efektif untuk (doctor, service[, detail]) di SEBUAH CABANG.
     * Prioritas: explicit duration → override per-dokter PER-CABANG
     * (raw_doctor_branch_service_duration) → service.duration_minutes → 30.
     *
     * Bila $detailId diisi, prioritas chain SUB-LAYANAN dipakai dulu:
     *  1. explicit duration
     *  2. override sub-layanan per-dokter PER-CABANG (raw_doctor_branch_detail_duration) — fitur flexible
     *  3. override sub-layanan per-dokter (raw_doctor_service_detail)
     *  4. raw_doc_service_detail.duration_minutes
     * lalu fallback ke chain SERVICE INDUK:
     *  5. override service per-dokter PER-CABANG (raw_doctor_branch_service_duration) — fitur flexible
     *  6. raw_doc_service.duration_minutes
     *  7. 30
     *
     * $branchId WAJIB diisi supaya override per-cabang terbaca; kalau null
     * (caller belum punya konteks cabang) langsung fallback ke durasi
     * default service/detail — aman, tidak bocor antar cabang.
     */
    protected function resolveDuration($doctorId, $serviceId = null, $explicitDuration = null, $branchId = null, $detailId = null): int
    {
        if ($explicitDuration !== null && $explicitDuration > 0) {
            return (int) $explicitDuration;
        }

        // === SUB-LAYANAN chain (kalau $detailId diisi) ===
        if ($detailId) {
            if (config('features.flexible_scheduling') && $branchId) {
                $detailBranchDur = DB::table('raw_doctor_branch_detail_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->where('raw_doc_service_detail_id', $detailId)
                    ->value('duration_minutes');

                if ($detailBranchDur !== null) {
                    return (int) round($detailBranchDur);
                }
            }

            // legacy fallback: override sub-layanan per-dokter (tanpa cabang)
            $detailDoctorDur = DB::table('raw_doctor_service_detail')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_doc_service_detail_id', $detailId)
                ->value('duration_minutes');

            if ($detailDoctorDur !== null) {
                return (int) round($detailDoctorDur);
            }

            $detail = RawDocServiceDetail::find($detailId);
            if ($detail && $detail->duration_minutes !== null) {
                return (int) round($detail->duration_minutes);
            }
        }

        if (!$serviceId) {
            return 30;
        }

        if (config('features.flexible_scheduling') && $branchId) {
            $branchDur = DB::table('raw_doctor_branch_service_duration')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->where('raw_doc_service_id', $serviceId)
                ->value('duration_minutes');

            if ($branchDur !== null) {
                return (int) round($branchDur);
            }
        }

        $service = RawDocService::find($serviceId);
        if ($service && $service->duration_minutes !== null) {
            return (int) round($service->duration_minutes);
        }

        return 30;
    }

    /**
     * Durasi total perawatan seorang pasien (jumlah durasi tiap service;
     * "Lainnya"/kosong = 30 menit). Prioritas durasi: override per-dokter
     * PER-CABANG → service.duration_minutes → 30 (via resolveDuration).
     *
     * @param array<int,mixed> $serviceIds
     */
    protected function patientDuration($doctorId, array $serviceIds, bool $lainnya, $branchId = null): int
    {
        if ($lainnya) {
            return 30;
        }
        $total = 0;
        foreach ($serviceIds as $sid) {
            if (! $sid) {
                continue;
            }
            $total += $this->resolveDuration($doctorId, $sid, null, $branchId);
        }
        return $total > 0 ? $total : 30;
    }

    /**
     * Working ranges (jam praktik) dokter di sebuah tanggal — recurring
     * schedule + exception add/replace (kalau fitur flexible aktif).
     *
     * @return \Illuminate\Support\Collection<int,array{start_hour:string,end_hour:string}>
     */
    protected function resolveWorkingRanges($branchId, $doctorId, $date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        // Ambil SEMUA row jadwal (work + break) untuk (dokter, cabang, hari).
        // Work range di-subtract dengan break range supaya jam istirahat tidak
        // masuk pool slot bookable — otomatis efektif di admin CRUD & public
        // /reservasi karena keduanya lewat computeTimeSlots() → sini.
        $praJadwal = $this->praMuatJadwal[$this->praKunci($doctorId, $branchId)] ?? null;

        $rows = $praJadwal !== null
            ? $praJadwal->where('day', $dayOfWeek)->sortBy('start_hour')->values()
            : RawDoctorSchedule::where('raw_branch_id', $branchId)
                ->where('raw_doctor_id', $doctorId)
                ->where('day', $dayOfWeek)
                ->orderBy('start_hour')
                ->get();

        $workRows  = $rows->where('type', '!=', RawDoctorSchedule::TYPE_BREAK);
        $breakRows = $rows->where('type', RawDoctorSchedule::TYPE_BREAK);

        $ranges = collect();
        foreach ($workRows as $w) {
            foreach ($this->subtractBreaks($w->start_hour, $w->end_hour, $breakRows) as $r) {
                $ranges->push($r);
            }
        }

        // Cuti dokter pada tanggal ini (full-day). Cuti membatalkan jadwal
        // REGULER, tapi tidak membatalkan exception: 'replace' menang penuh
        // (ganti jam & tembus cuti), dan 'add' (Hari Tambahan) tetap dibuka
        // walau dokter cuti / hari itu biasanya libur.
        $praCuti = $this->praMuatCuti[(string) $doctorId] ?? null;

        if ($praCuti !== null) {
            $onLeave = $praCuti->contains(
                fn ($l) => (string) $l->start_date <= (string) $date
                        && (string) $l->end_date   >= (string) $date
            );
        } else {
            $onLeave = DB::table('raw_doctor_leave')
                ->where('raw_doctor_id', $doctorId)
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->exists();
        }

        $replaceRanges = collect();
        $addRanges     = collect();

        if (config('features.flexible_scheduling')) {
            $praKecuali = $this->praMuatPengecualian[$this->praKunci($doctorId, $branchId)] ?? null;

            $exceptions = $praKecuali !== null
                ? $praKecuali->filter(
                      fn ($e) => \Carbon\Carbon::parse($e->date)->toDateString()
                               === \Carbon\Carbon::parse($date)->toDateString()
                  )->values()
                : RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->where('date', $date)
                    ->get();

            $replaceRanges = $exceptions->where('type', RawDoctorScheduleException::TYPE_REPLACE)
                ->map(fn($e) => ['start_hour' => $e->start_hour, 'end_hour' => $e->end_hour]);
            $addRanges = $exceptions->where('type', RawDoctorScheduleException::TYPE_ADD)
                ->map(fn($e) => ['start_hour' => $e->start_hour, 'end_hour' => $e->end_hour]);
        }

        if ($replaceRanges->isNotEmpty()) {
            // 'replace' mengganti jam reguler (dan menembus cuti).
            $ranges = $replaceRanges;
        } elseif ($onLeave) {
            // Cuti membatalkan jadwal reguler bila tidak ada 'replace'.
            $ranges = collect();
        }

        // 'add' (Hari Tambahan) selalu ditambahkan — membuka tanggal walau
        // dokter cuti atau hari itu biasanya libur.
        //
        // Digabung dulu supaya rentang yang BERTINDIH tidak masing-masing
        // mencetak deret slotnya sendiri — lihat gabungRentangBertindih().
        $ranges = collect($this->gabungRentangBertindih($ranges->concat($addRanges)));

        return $ranges;
    }

    /**
     * Satukan rentang jam yang saling BERTINDIH jadi satu rentang.
     *
     * Kenapa perlu: satu tanggal bisa mewarisi lebih dari satu rentang untuk
     * dokter+cabang yang SAMA — dua baris jadwal rutin yang tumpang tindih, atau
     * 'add' (Hari Tambahan) yang dibuat di hari yang ternyata SUDAH punya jadwal
     * rutin. computeTimeSlots() mencetak deret slot per rentang lalu
     * menempelkannya, jadi tanpa penggabungan jam yang sama muncul dua kali di
     * layar. Kejadian nyata 10 Sep 2026: Cinere rutin 17:00-20:00 (6 slot)
     * ditumpuk 'add' 17:00-18:30 (3 slot) = 9 kotak untuk 6 jam yang benar-benar
     * ada. Admin membacanya sebagai sistem rusak, dan itu memicu laporan.
     *
     * DIGABUNG, bukan dibuang yang kembar: kalau 'add' justru MEMPERPANJANG
     * (rutin 17:00-20:00 + add 19:00-21:00), yang benar adalah satu grid
     * 17:00-21:00. Membuang duplikat saja meninggalkan dua grid dengan titik
     * mulai berbeda — jamnya jadi ganjil begitu durasinya tak sejajar.
     *
     * Yang cuma BERSENTUHAN (10:00-12:00 lalu 12:00-15:00) sengaja TIDAK
     * digabung: itu dua sesi terpisah, dan menyatukannya menggeser titik grid
     * untuk durasi yang tak habis membagi rentang — perubahan perilaku di luar
     * cacat yang sedang diperbaiki. Karena itu pembandingnya '<', bukan '<='.
     *
     * @param  \Illuminate\Support\Collection|iterable  $ranges
     * @return array<int,array{start_hour:string,end_hour:string}>
     */
    protected function gabungRentangBertindih($ranges): array
    {
        // Rentang nol/terbalik sengaja TIDAK dibuang, walau ia tak pernah
        // menghasilkan slot. Pemanggil lain menilai "dokter dijadwalkan hari
        // ini?" dari koleksi ini kosong atau tidak — AttendanceReconciler
        // memakainya untuk memutuskan hadir/libur seorang dokter — jadi
        // membuangnya akan diam-diam menggeser hasil ABSENSI, jauh dari cacat
        // yang sedang diperbaiki. Bukan kekhawatiran teoretis: prod punya satu
        // baris begitu (Minggu 22:00-20:00).
        //
        // Menormalkan saja sudah cukup aman: rentang seperti itu tak bisa
        // menyerap tetangganya (pembandingnya '<') maupun memanjangkannya
        // (hanya melebar bila end-nya LEBIH BESAR).
        $list = [];
        foreach ($ranges as $r) {
            $list[] = [
                'start_hour' => Carbon::parse($r['start_hour'])->format('H:i:s'),
                'end_hour'   => Carbon::parse($r['end_hour'])->format('H:i:s'),
            ];
        }

        if (count($list) < 2) {
            return $list;
        }

        usort($list, fn ($a, $b) => $a['start_hour'] <=> $b['start_hour']);

        $hasil = [array_shift($list)];
        foreach ($list as $r) {
            $i = count($hasil) - 1;
            if ($r['start_hour'] < $hasil[$i]['end_hour']) {
                if ($r['end_hour'] > $hasil[$i]['end_hour']) {
                    $hasil[$i]['end_hour'] = $r['end_hour'];
                }
            } else {
                $hasil[] = $r;
            }
        }

        return $hasil;
    }

    /**
     * Subtract kumpulan break dari 1 range work → list of sub-range yang
     * masih bookable. Break yang di luar range diabaikan. Support N break
     * (bisa overlap parsial atau nested — semua di-normalize dulu).
     *
     * @param  \Illuminate\Support\Collection|iterable  $breakRows
     * @return array<int,array{start_hour:string,end_hour:string}>
     */
    protected function subtractBreaks($workStart, $workEnd, $breakRows)
    {
        $ws = Carbon::parse($workStart)->format('H:i:s');
        $we = Carbon::parse($workEnd)->format('H:i:s');

        // Collect break intervals yang beririsan dengan work, di-clamp ke
        // batas work supaya subtract-nya aman.
        $breaks = [];
        foreach ($breakRows as $b) {
            $bs = Carbon::parse($b->start_hour)->format('H:i:s');
            $be = Carbon::parse($b->end_hour)->format('H:i:s');
            if ($be <= $ws || $bs >= $we) continue;         // di luar work
            $breaks[] = [max($bs, $ws), min($be, $we)];
        }

        if (empty($breaks)) {
            return [['start_hour' => $workStart, 'end_hour' => $workEnd]];
        }

        // Merge overlap antar break supaya subtract cukup 1x pass.
        usort($breaks, fn($a, $b) => strcmp($a[0], $b[0]));
        $merged = [$breaks[0]];
        for ($i = 1; $i < count($breaks); $i++) {
            $last = &$merged[count($merged) - 1];
            if ($breaks[$i][0] <= $last[1]) {
                $last[1] = max($last[1], $breaks[$i][1]);
            } else {
                $merged[] = $breaks[$i];
            }
        }

        // Iterasi: potong work di setiap break.
        $result = [];
        $cursor = $ws;
        foreach ($merged as [$bs, $be]) {
            if ($cursor < $bs) {
                $result[] = ['start_hour' => $cursor, 'end_hour' => $bs];
            }
            $cursor = max($cursor, $be);
        }
        if ($cursor < $we) {
            $result[] = ['start_hour' => $cursor, 'end_hour' => $we];
        }
        return $result;
    }

    /**
     * Rentang [start,end] (H:i) yang sudah ter-booking utk dokter+tanggal.
     *
     * SENGAJA TANPA FILTER CABANG. Seorang dokter tidak bisa berada di dua
     * cabang pada jam yang sama, jadi reservasi di cabang MANA PUN menahan
     * slotnya. Menambahkan ->where('raw_branch_id', ...) di sini membuka
     * double-booking dokter yang sungguhan, dan gagalnya diam — tidak ada
     * error, cuma dokter yang tiba-tiba punya dua pasien di dua tempat pada
     * jam yang sama. Yang dulu kurang adalah PENJELASANNYA di layar, bukan
     * filternya; karena itu baris ini sekarang ikut membawa cabang & nama
     * pasien, yang cuma dipakai computeTimeSlots() bila pemanggil eksplisit
     * minta 'with_booking_detail'.
     *
     * @return array<int,array{start:string,end:string,branch_id:mixed,branch:?string,patient:?string}>
     */
    protected function resolveBookedRanges(
        $doctorId,
        $date,
        string $reservationTable = 'raw_cus_reservation',
        $excludeId = null,
        bool $bolehPraMuat = false
    ): array {
        /*
         * Jalur pra-muat — lihat properti $praMuat* di kepala trait.
         *
         * ⚠ Bawaannya MENOLAK, dan arah itu disengaja. Metode ini
         *   mengembalikan LIMA kunci (start · end · branch_id · branch ·
         *   patient), sedangkan pra-muat hanya menyediakan dua yang pertama —
         *   cukup untuk menghitung slot, tidak cukup untuk spanduk "dokter ini
         *   juga di cabang lain" maupun detail booking.
         *
         *   Kalau bawaannya mengizinkan, pemanggil baru yang lupa akan
         *   mendapat array tanpa kunci `branch`/`patient` dan layarnya kosong
         *   tanpa pesan galat. Dengan menolak lebih dulu, yang terjadi paling
         *   buruk adalah satu kueri tambahan — arah gagal yang benar.
         *
         * Dua syarat lain: pra-muat diambil dari 'raw_cus_reservation' dan
         * tidak mengecualikan baris apa pun, jadi sandbox yang mengirim
         * 'raw_cus_reservation_dev' dan jalur edit yang mengecualikan dirinya
         * sendiri WAJIB tetap menanyai DB.
         */
        if ($bolehPraMuat
            && $this->praMuatTerpesan !== null
            && $excludeId === null
            && $reservationTable === 'raw_cus_reservation') {
            return $this->praMuatTerpesan[$this->praKunci($doctorId, $date)] ?? [];
        }

        return DB::table($reservationTable . ' as r')
            ->leftJoin('raw_branch as b', 'r.raw_branch_id', '=', 'b.id')
            ->leftJoin('raw_customer as c', 'r.raw_customer_id', '=', 'c.id')
            ->where('r.raw_doctor_id', $doctorId)
            ->where('r.date', $date)
            ->whereNotNull('r.start_hour')
            ->whereNotNull('r.end_hour')
            // Reservasi yang DIBATALKAN atau masuk WAITING LIST tidak lagi menahan
            // slot — jamnya otomatis kembali tersedia untuk pasien lain. Baris dgn
            // status NULL (data lama) tetap dianggap terisi supaya tidak salah bebas.
            ->where(function ($q) {
                $q->whereNotIn('r.status', [
                    \App\Models\RawCusReservation::STATUS_CANCELLED,
                    \App\Models\RawCusReservation::STATUS_WAITLIST,
                ])->orWhereNull('r.status');
            })
            ->when($excludeId, fn($q) => $q->where('r.id', '!=', $excludeId))
            // Kolom di-list eksplisit: tanpa ini `id` dari tiga tabel saling
            // menimpa dan exclude_id/branch_id ikut kacau.
            ->get([
                'r.start_hour',
                'r.end_hour',
                'r.raw_branch_id',
                'b.name as branch_name',
                'c.name as patient_name',
            ])
            ->map(fn($r) => [
                'start'     => Carbon::parse($r->start_hour)->format('H:i'),
                'end'       => Carbon::parse($r->end_hour)->format('H:i'),
                'branch_id' => $r->raw_branch_id,
                'branch'    => $r->branch_name,
                'patient'   => $r->patient_name,
            ])->toArray();
    }

    /**
     * Janji dokter ini di CABANG LAIN pada tanggal yang sama — bahan spanduk
     * hari di form reservasi admin.
     *
     * Sengaja dihitung dari $bookedRanges (satu hari penuh, lintas cabang),
     * BUKAN dari slot: sistem cuma mencegah jam bertabrakan, tak pernah
     * mencegah cabang berpindah. Jadi kasus "pindah cabang nol menit" — 17:30
     * di Cinere memang kosong, tapi dokternya di Kalideres sampai 17:30 —
     * tetap ketahuan walau semua slot di cabang yang dibuka bebas.
     *
     * @param  array<int,array<string,mixed>>  $bookedRanges
     * @return array<int,array{branch_id:mixed,branch:?string,nama:?string,jam:string}>
     */
    private function otherBranchBookings(array $bookedRanges, $branchId): array
    {
        $out = [];

        foreach ($bookedRanges as $r) {
            $rowBranch = $r['branch_id'] ?? null;
            if ($rowBranch === null || (string) $rowBranch === (string) $branchId) {
                continue;
            }

            $jam = $r['start'] . '–' . $r['end'];
            // De-dup: satu keluarga bisa punya beberapa baris di jam & cabang
            // yang sama — spanduknya cukup sekali.
            $out[$rowBranch . '|' . $jam . '|' . ($r['patient'] ?? '')] = [
                'branch_id' => $rowBranch,
                'branch'    => $r['branch'] ?? null,
                'nama'      => $r['patient'] ?? null,
                'jam'       => $jam,
            ];
        }

        return array_values($out);
    }

    /**
     * Alokasikan jam untuk SEKUMPULAN pasien (1 nomor / keluarga) dalam 1
     * tanggal. Tiap pasien punya dokter & durasi sendiri.
     *
     * Aturan:
     *  - Pasien dgn dokter SAMA → disusun berurutan (tidak saling tabrak).
     *  - Pasien dgn dokter BEDA → dihitung dari jadwal dokter itu sendiri
     *    (timeline antar dokter independen / paralel).
     *  - Tidak pernah menabrak reservasi existing (resolveBookedRanges) atau
     *    anggota keluarga lain yang sudah dialokasikan.
     *  - Default mulai = $defaultStart; kalau dokter itu tak punya slot di
     *    >= $defaultStart, ambil slot paling awal hari itu; kalau dokter tak
     *    praktik / penuh → waiting=true (tim konfirmasi).
     *
     * @param array<int,array{key:mixed,doctor_id:string,duration:int}> $items
     * @return array<mixed,array{start:?string,end:?string,waiting:bool,reason:?string}>
     */
    protected function assignFamilySlots(
        $branchId,
        string $date,
        array $items,
        ?string $defaultStart = null,
        string $reservationTable = 'raw_cus_reservation'
    ): array {
        $isToday = Carbon::parse($date)->isToday();
        $nowHi   = Carbon::now()->format('H:i');
        $result  = [];

        // Cache per dokter: ranges, booked (existing), consumed (keluarga ini),
        // dan cursor (jam selesai pasien terakhir dokter itu).
        $byDoctor = [];

        foreach ($items as $item) {
            $doctorId = $item['doctor_id'];
            $duration = max(1, (int) $item['duration']);
            $key      = $item['key'];

            if (!isset($byDoctor[$doctorId])) {
                $byDoctor[$doctorId] = [
                    'ranges'   => $this->resolveWorkingRanges($branchId, $doctorId, $date),
                    'booked'   => $this->resolveBookedRanges($doctorId, $date, $reservationTable),
                    'consumed' => [],
                    'cursor'   => null,
                ];
            }
            $D = &$byDoctor[$doctorId];

            if ($D['ranges']->isEmpty()) {
                $result[$key] = ['start' => null, 'end' => null, 'waiting' => true,
                    'reason' => 'Dokter tidak praktik di tanggal ini'];
                continue;
            }

            $lowerBound = $D['cursor'] ?: ($defaultStart ?: '00:00');
            if ($isToday && $nowHi > $lowerBound) {
                $lowerBound = $nowHi;
            }

            $chosen = $this->firstFreeStart($D, $duration, $lowerBound);

            // Fallback: tidak ada slot >= lowerBound → coba paling awal hari
            // itu (tetap hormati now kalau hari ini).
            if ($chosen === null && $D['cursor'] === null) {
                $earliest = ($isToday && $nowHi > '00:00') ? $nowHi : '00:00';
                $chosen   = $this->firstFreeStart($D, $duration, $earliest);
            }

            if ($chosen === null) {
                $result[$key] = ['start' => null, 'end' => null, 'waiting' => true,
                    'reason' => 'Slot dokter penuh di tanggal ini'];
                continue;
            }

            $end = Carbon::parse($chosen)->addMinutes($duration)->format('H:i');
            $D['consumed'][] = ['start' => $chosen, 'end' => $end];
            $D['cursor']     = $end;

            $result[$key] = ['start' => $chosen, 'end' => $end, 'waiting' => false, 'reason' => null];
        }

        return $result;
    }

    /**
     * Cari start paling awal (>= $lowerBound) dengan durasi $duration yang
     * muat di salah satu working range dan tidak tabrakan booked/consumed.
     * Kandidat di-generate dgn step = $duration (konsisten dgn grid slot).
     */
    private function firstFreeStart(array $D, int $duration, string $lowerBound): ?string
    {
        foreach ($D['ranges'] as $range) {
            $cursor = Carbon::parse($range['start_hour']);
            $rangeEnd = Carbon::parse($range['end_hour']);

            while ($cursor->copy()->addMinutes($duration)->lte($rangeEnd)) {
                $s = $cursor->format('H:i');
                $e = $cursor->copy()->addMinutes($duration)->format('H:i');

                if ($s >= $lowerBound && !$this->overlaps($s, $e, $D['booked'], $D['consumed'])) {
                    return $s;
                }
                $cursor->addMinutes($duration);
            }
        }
        return null;
    }

    /** True kalau [$s,$e) menabrak salah satu rentang booked/consumed. */
    private function overlaps(string $s, string $e, array $booked, array $consumed): bool
    {
        foreach (array_merge($booked, $consumed) as $r) {
            if ($s < $r['end'] && $e > $r['start']) {
                return true;
            }
        }
        return false;
    }
}
