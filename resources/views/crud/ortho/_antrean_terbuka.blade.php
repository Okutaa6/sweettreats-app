{{--
    Panel ringkas untuk baris ANTREAN PENJADWALAN.

    Sepupu dari _baris_terbuka, tapi tidak bisa memakainya: panel itu digerakkan
    satu baris ort_control (punya fu_date, reservation_id, sch_date), sedangkan
    di sini barisnya satu ort_case yang justru BELUM punya kontrol terbuka.

    Isinya pindahan dari baris meta di bawah nama pasien — dulu rangkaian
    "pid · bracket · dokter · kontrol ke-N ditutup" diklem dua baris di kolom
    Pasien dan tetap terasa sesak. Di sini ia punya ruang, dan kolom Pasien
    kembali bersih: nama + nomor telepon saja.

    Yang SENGAJA tidak ada di sini:
      • "Batalkan follow-up" — belum ada kontrol, jadi belum ada follow-up.
      • "Tandai tidak hadir" — tidak ada jadwal yang bisa dilewatkan.
      • Segmented control tiga arah — "Tanggal reservasi" akan kosong untuk
        hampir semua baris, jadi ia cuma menambah kendali tanpa jawaban.
--}}
@php
    $mini = $riwayat[$c->id] ?? [];
    $kehadiran = ($c->control_count ?? 0) > 0
        ? $c->visit_count . ' dari ' . $c->control_count . ' kontrol'
        : 'belum ada kontrol';
@endphp

<div class="ort-expand" data-ort-expand="{{ $c->id }}" hidden>

    {{-- ── Kiri: ringkas pasien ──────────────────────────────────────── --}}
    <div>
        <div class="ort-expand__title">Ringkas pasien</div>

        <div class="ort-facts">
            <div>
                <div class="ort-fact__k">No. rekam medis</div>
                <div class="ort-fact__v tabular">{{ $c->omnicare_pid ?: 'belum tertaut' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Nomor HP</div>
                <div class="ort-fact__v tabular">{{ $c->patient_phone ?: '—' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Bracket · rahang</div>
                <div class="ort-fact__v">
                    {{ $c->bracket_type ?: '—' }}
                    @if ($c->bracket_arch) · {{ ucfirst($c->bracket_arch) }} @endif
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Dokter ortho</div>
                <div class="ort-fact__v">{{ $c->doctor_name ?: '—' }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Pasang behel</div>
                <div class="ort-fact__v">
                    {{ \Carbon\Carbon::parse($c->install_date)->translatedFormat("j M 'y") }}
                    di {{ $peta[$c->install_place_id]['nama'] ?? $c->install_place_id }}
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Kehadiran</div>
                <div class="ort-fact__v">{{ $kehadiran }}</div>
            </div>
            <div>
                <div class="ort-fact__k">Jarak rata-rata</div>
                <div class="ort-fact__v">
                    @if ($c->avg_gap_days)
                        <span class="tabular">{{ $c->avg_gap_days }}</span> hari
                        <span style="font-weight:500;color:#A9866A;">(target {{ \App\Models\OrtCase::TARGET_GAP }})</span>
                    @else
                        belum bisa dihitung
                    @endif
                </div>
            </div>
            <div>
                <div class="ort-fact__k">Kontrol terakhir datang</div>
                <div class="ort-fact__v">
                    {{ $c->last_visit_date
                        ? $c->last_visit_date->translatedFormat("j M 'y")
                        : 'belum pernah' }}
                </div>
            </div>
        </div>

        <div class="ort-expand__btns">
            <a href="{{ $tautanDetail($c->id) }}" class="ort-obtn" onclick="event.stopPropagation()">
                Buka detail pasien <i class="fas fa-chevron-right" style="font-size:9px;"></i>
            </a>
            <a href="{{ $tautan(['sheet' => 'pindahkan', 'kasus' => $c->id]) }}" class="ort-obtn"
               style="color:#6B2400;border-color:#E4C7AC;" onclick="event.stopPropagation()">
                <i class="fas fa-exchange-alt"></i> Pindahkan ke cabang lain
            </a>
        </div>
    </div>

    {{-- ── Kanan: kontrol terakhir ────────────────────────────────────── --}}
    <div>
        <div class="ort-mini-head">
            <span class="ort-expand__title" style="margin:0;">4 kontrol terakhir</span>
        </div>

        <div class="ort-mini">
            @forelse ($mini as $m)
                @php
                    $cabang = $peta[$m->place_id]['nama'] ?? $m->place_id;
                    /* Kamus chip sama persis dengan tampilan "Semua" di
                       _baris_terbuka — satu keadaan tidak boleh punya dua
                       nama antar panel. */
                    $st = match (true) {
                        (bool) $m->visit_date          => ['Hadir', 'hadir'],
                        $m->outcome === 'tidak_hadir'  => ['Tidak hadir', 'absen'],
                        $m->rsv_status === 'cancelled' => ['Reservasi batal', 'batal'],
                        /* Chip 'usulan' dibuang: kolom Jadwal adalah jatuh
                           tempo, jadi tanggal yang dihitung sistem bukan sebuah
                           STATUS tersendiri. Garis putus-putus di tanggalnya
                           yang menandai asal-usulnya. */
                        (bool) $m->reservation_id      => ['Ada reservasi', 'rsv'],
                        (bool) $m->fu_date             => ['Sudah di-FU', 'fu'],
                        default                        => ['Menunggu', 'menunggu'],
                    };
                @endphp
                <div class="ort-mini__row">
                    <span class="ort-mini__n">#{{ $m->seq }}</span>
                    {{-- Riwayat bertanggal, yang terbuka jadi perkiraan — aturan
                         yang sama dengan kolom Jadwal di tab List. --}}
                    <span class="ort-mini__d tabular">@if ($m->isTerbuka() && $m->is_proposal){{ \App\Models\OrtControl::perkiraanJatuhTempo($m->sch_date, $hariIni) }}@else{{ $m->sch_date->translatedFormat("j M 'y") }}@endif</span>
                    <span class="ort-mini__x">
                        FU {{ $m->fu_date ? $m->fu_date->translatedFormat('j M') : 'X' }} ·
                        RSV {{ $m->rsv_date ? $m->rsv_date->translatedFormat('j M') : 'X' }} ·
                        {{ $cabang }}
                    </span>
                    <span class="ort-chip ort-chip--{{ $st[1] }}">{{ $st[0] }}</span>
                </div>
            @empty
                <div class="ort-mini__row">
                    <span class="ort-mini__x">
                        Belum ada kontrol yang tercatat — pasien ini baru dimuat dari rekam medis.
                    </span>
                </div>
            @endforelse
        </div>
    </div>

</div>
