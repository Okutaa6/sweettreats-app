{{--
    Tab Kalender — brief §12 / screenshot 11.

    Grid bulanan berisi JADWAL KONTROL (sch_date), bukan kunjungan. Gunanya
    melihat beban minggu depan dan menyisir yang terlewat.

    ⚠ SATU-SATUNYA TEMPAT YANG MASIH MENEMPATKAN PASIEN DI TANGGAL TERTENTU,
      dan itu disengaja. Di tab List, kolom Jadwal sudah berhenti menyebut
      tanggal dan berganti perkiraan ("± Nov", "Pekan ini") karena tanggalnya
      tebakan — irama pasien sendiri meleset 14 hari, dan tanggal pastinya baru
      lahir saat reservasi dibuat.

      Di sini tanggal tetap dipakai karena papan ini memang alat MENGHITUNG
      BEBAN: "berapa orang jatuh tempo minggu depan". Mengganti kotak tanggal
      dengan perkiraan membuat gridnya kehilangan seluruh gunanya. Yang dilihat
      di sini sebaran, bukan janji — dan perbedaan itu yang membuatnya tetap
      jujur meski bertanggal.

    Yang harus terasa:
      • Tanggal yang punya jadwal TERLEWAT diberi latar merah muda — supaya
        langsung kelihatan saat menggulir bulan ke belakang.
      • Chip = nama depan saja, warna mengikuti status. Maksimal 3, sisanya
        "+N lainnya" dalam warna pudar.
      • Klik DI MANA PUN di sel yang berisi membuka drawer harian, bukan
        drawer pasien.

    ⚠ Brief §22: TIDAK ADA teks "N jadwal kontrol di bulan ini" di kepala.
--}}
@php
    $kal    = $kalender;
    $bulan  = $kal['bulan'];
    $prev   = $bulan->copy()->subMonth()->format('Y-m');
    $next   = $bulan->copy()->addMonth()->format('Y-m');
    $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    $warnaChip = [
        'menunggu' => 'menunggu', 'usulan' => 'usulan', 'sudah_fu' => 'fu', 'ada_reservasi' => 'rsv',
        'reservasi_batal' => 'batal', 'terlewat' => 'telat', 'terlewat_parah' => 'telatlama',
    ];

    // Status per chip dihitung dari kolom mentah — tidak ada model di sini.
    $kunci = function ($r) use ($hariIni) {
        $telat = $r->sch_date < $hariIni->toDateString();
        return match (true) {
            $r->rsv_status === 'cancelled' => 'reservasi_batal',
            (bool) $r->is_proposal         => 'usulan',
            $telat                         => \Carbon\Carbon::parse($r->sch_date)->diffInDays($hariIni) > 14 ? 'terlewat_parah' : 'terlewat',
            (bool) $r->reservation_id      => 'ada_reservasi',
            (bool) $r->fu_date             => 'sudah_fu',
            default                        => 'menunggu',
        };
    };
@endphp

<div class="st-card" style="margin-top:13px;">

    {{-- Pindah bulan tinggal DI DALAM kartu kalender, bukan di pita
         navigasi: pita sudah dipakai sub-tab kegentingan yang berlaku
         di semua tab, dan bulan hanya berarti untuk kalender. --}}
    <div class="ort-cal-bar">
        <div class="ort-cal-nav">
            <a href="{{ $tautan(['bulan' => $prev, 'hari' => null]) }}" class="ort-cal-btn" title="Bulan sebelumnya"><i class="fas fa-chevron-left"></i></a>
            <span class="ort-cal-month">{{ $bulan->translatedFormat('F Y') }}</span>
            <a href="{{ $tautan(['bulan' => $next, 'hari' => null]) }}" class="ort-cal-btn" title="Bulan berikutnya"><i class="fas fa-chevron-right"></i></a>
        </div>
        <a href="{{ $tautan(['bulan' => null, 'hari' => null]) }}" class="ort-btn"><i class="fas fa-crosshairs"></i> Hari Ini</a>
    </div>

    <div class="ort-cal-dow">
        @foreach ($namaHari as $h)<div>{{ $h }}</div>@endforeach
    </div>

    <div class="ort-cal-grid">
        @for ($i = 0; $i < 42; $i++)
            @php
                $tgl     = $kal['awalGrid']->copy()->addDays($i);
                $key     = $tgl->toDateString();
                $isi     = $kal['perHari'][$key] ?? [];
                $luar    = $tgl->month !== $bulan->month;
                $hariIniY= $key === $hariIni->toDateString();
                $adaTelat= ! empty($isi) && $key < $hariIni->toDateString();
                $tampil  = array_slice($isi, 0, 3);
                $sisa    = count($isi) - count($tampil);
            @endphp
            @if (! empty($isi))
                <a href="{{ $tautan(['hari' => $key]) }}"
                   class="ort-cal-cell {{ $luar ? 'is-out' : '' }} {{ $adaTelat ? 'is-late' : '' }} {{ $hariIniY ? 'is-today' : '' }}">
            @else
                <div class="ort-cal-cell {{ $luar ? 'is-out' : '' }} {{ $hariIniY ? 'is-today' : '' }}">
            @endif
                <div class="ort-cal-date tabular"><span>{{ $tgl->day }}</span></div>
                @foreach ($tampil as $r)
                    <span class="ort-cal-chip ort-chip--{{ $warnaChip[$kunci($r)] ?? 'menunggu' }}">
                        <i></i>{{ \Illuminate\Support\Str::title(explode(' ', trim($r->patient_name))[0]) }}
                    </span>
                @endforeach
                @if ($sisa > 0)
                    <span class="ort-cal-more tabular">+{{ $sisa }} lainnya</span>
                @endif
            @if (! empty($isi))</a>@else</div>@endif
        @endfor
    </div>

    <div class="ort-cal-legend">
        @foreach (['menunggu' => 'Menunggu', 'fu' => 'Sudah di-FU', 'rsv' => 'Ada reservasi',
                   'hadir' => 'Hadir', 'absen' => 'Tidak hadir', 'telatlama' => 'Terlewat'] as $k => $l)
            <span><i class="ort-legend-dot ort-chip--{{ $k }}"></i>{{ $l }}</span>
        @endforeach
    </div>
</div>
