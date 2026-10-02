{{--
    Tab Pasien Ortho — brief §13 / screenshot 13 (Daftar) & 14 (Kartu).

    Satu baris atau satu kartu = satu ORANG, bukan satu jadwal. Dipakai untuk
    melihat siapa saja yang sedang dirawat dan membuka riwayat satu pasien.

    Daftar adalah tampilan default (keputusan pemilik produk). Keduanya
    memakai paginasi yang sama, 10 per halaman.

    ⚠ Brief §22: TIDAK ADA chip "Aktif" di kartu pasien.

    Aktif ⇄ Selesai (`?rawat=selesai`): pasien yang perawatannya sudah ditutup
    — otomatis lewat paket Freedom / Lepas Bracket di rekam medis, atau lewat
    Tandai Selesai — TIDAK lenyap; ia pindah ke sini. Di mode itu kolom
    "Berikutnya" menjadi "Selesai" (tanggal tutup) dan tidak ada tugas Sales.
--}}
@php
    $kartuMode   = ($tampil ?? 'daftar') === 'kartu';
    $modeSelesai = ($rawat ?? 'aktif') === 'selesai';
    $labelTutup  = [
        \App\Models\OrtCase::STATUS_SELESAI       => 'Selesai',
        \App\Models\OrtCase::STATUS_PUTUS         => 'Putus',
        \App\Models\OrtCase::STATUS_PINDAH_KLINIK => 'Pindah klinik',
    ];
    $chipLabel = [
        'menunggu' => ['Menunggu', 'menunggu'],
        'sudah_fu' => ['Sudah di-FU', 'fu'],     'ada_reservasi' => ['Ada reservasi', 'rsv'],
        'reservasi_batal' => ['Reservasi batal', 'batal'],
        'terlewat' => ['Terlewat', 'telat'],     'terlewat_parah' => ['Terlewat', 'telatlama'],
    ];

    // Status per pasien diturunkan dari kontrol TERBUKA-nya — kecuali kalau
    // perawatannya sudah ditutup: jadwal terbuka yang tersisa bukan tugas.
    $statusPasien = function ($c) use ($hariIni, $chipLabel, $labelTutup) {
        if ($c->status !== \App\Models\OrtCase::STATUS_AKTIF) {
            return [$labelTutup[$c->status] ?? 'Selesai', 'selesai', 0];
        }
        $o = $c->openControl;
        if (! $o) {
            return ['Belum punya jadwal', 'menunggu', 0];
        }
        $key = $o->statusKey($hariIni);
        [$teks, $kelas] = $chipLabel[$key] ?? ['Menunggu', 'menunggu'];
        $telat = $o->hariTerlewat($hariIni);
        if ($telat > 0) {
            $teks = 'Terlewat ' . $telat . ' hari';
        }
        return [$teks, $kelas, $telat];
    };
@endphp

<div class="ort-roster-head">
    <span class="ort-roster-head__n">
        {{-- Di mode cabang lain angkanya BUKAN "pasien aktif di cabang kita" —
             itu pasien kita yang sedang dipegang cabang sebelah. Kalimatnya
             wajib ikut berubah, kalau tidak angka 1 di sini terbaca seolah
             cabang sendiri cuma punya satu pasien. --}}
        <span class="tabular">{{ $baris->total() }}</span>
        @if ($hanyaLihat)
            pasien {{ $namaCabang }} yang sedang dipegang
            {{ $peta[$cabangDilihat]['nama'] ?? $cabangDilihat }} — hanya bisa dilihat
        @elseif ($modeSelesai)
            pasien behel yang perawatannya sudah selesai di {{ $namaCabang }}
        @else
            pasien behel aktif di {{ $namaCabang }}
        @endif
    </span>
    <span class="ort-roster-head__r">
        {{-- Sumbu status perawatan. Pindah sumbu = kegentingan direset,
             karena "terlewat" tidak punya arti untuk pasien yang selesai. --}}
        <span class="ort-seg">
            <a href="{{ $tautan(['rawat' => null, 'urgensi' => null]) }}" class="{{ ! $modeSelesai ? 'is-active' : '' }}">
                Aktif <span class="tabular">{{ $hitungRoster['aktif'] ?? $baris->total() }}</span>
            </a>
            <a href="{{ $tautan(['rawat' => 'selesai', 'urgensi' => null]) }}" class="{{ $modeSelesai ? 'is-active' : '' }}">
                Selesai <span class="tabular">{{ $hitungRoster['selesai'] ?? 0 }}</span>
            </a>
        </span>
        <span class="ort-seg">
            <a href="{{ $tautan(['tampil' => null]) }}"  class="{{ ! $kartuMode ? 'is-active' : '' }}">Daftar</a>
            <a href="{{ $tautan(['tampil' => 'kartu']) }}" class="{{ $kartuMode ? 'is-active' : '' }}">Kartu</a>
        </span>
    </span>
</div>

@if ($kartuMode)

    <div class="ort-cardgrid">
        @forelse ($baris as $c)
            @php
                [$sTeks, $sKelas, $telat] = $statusPasien($c);
                $pct   = $c->control_count > 0 ? (int) round($c->visit_count / max(1, $c->control_count) * 100) : 0;
                $warnaBerikut = $telat > 14 ? '#C0392B' : ($telat > 0 ? '#B5701A' : '#3B1500');
                $inisial = mb_strtoupper(mb_substr($c->patient_name, 0, 1));
            @endphp
            <a href="{{ $tautanDetail($c->id) }}"
               class="ort-pcard {{ $telat > 14 ? 'ort-pcard--bad' : ($telat > 0 ? 'ort-pcard--warn' : '') }}">
                <div class="ort-pcard__head">
                    <span class="ort-avatar">{{ $inisial }}</span>
                    <div style="flex:1;min-width:0;">
                        <div class="ort-pcard__name">{{ \Illuminate\Support\Str::title($c->patient_name) }}</div>
                        <div class="ort-pcard__meta">
                            {{ $c->omnicare_pid ?: 'belum tertaut RM' }}
                            @if ($c->bracket_type) · {{ $c->bracket_type }} @endif
                            @if ($c->bracket_arch) · {{ ucfirst($c->bracket_arch) }} @endif
                        </div>
                    </div>
                    <span class="ort-chip ort-chip--{{ $sKelas }}">{{ $sTeks }}</span>
                </div>

                <div class="ort-stat">
                    <div>
                        @if ($modeSelesai)
                            <div class="ort-stat__k">Selesai</div>
                            <div class="ort-stat__v tabular">
                                {{ $c->finished_at ? $c->finished_at->translatedFormat("j M 'y") : '—' }}
                            </div>
                        @else
                            <div class="ort-stat__k">Berikutnya</div>
                            <div class="ort-stat__v tabular" style="color:{{ $warnaBerikut }}">
                                {{ $c->next_sch_date ? \App\Models\OrtControl::perkiraanJatuhTempo($c->next_sch_date, $hariIni) : '—' }}
                            </div>
                        @endif
                    </div>
                    <div>
                        <div class="ort-stat__k">Terakhir datang</div>
                        <div class="ort-stat__v tabular">
                            {{ $c->last_visit_date ? $c->last_visit_date->translatedFormat("j M 'y") : '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="ort-stat__k">Kehadiran</div>
                        <div class="ort-stat__v tabular">{{ $pct }}%</div>
                        <div class="ort-bar {{ $pct < 60 ? 'ort-bar--low' : '' }}"><i style="width:{{ $pct }}%"></i></div>
                    </div>
                </div>

                <div class="ort-pcard__br">
                    @foreach ($c->branchesVisited() as $pid)
                        <span class="ort-pill-br {{ (string) $pid !== (string) $c->install_place_id ? 'ort-pill-br--other' : '' }}"
                              title="{{ (string) $pid === (string) $c->install_place_id ? 'Cabang pasang' : 'Pernah kontrol di' }} · {{ $peta[$pid]['nama'] ?? $pid }}">
                            {{ $peta[$pid]['nama'] ?? $pid }}
                        </span>
                    @endforeach
                    <span>Pasang {{ $c->install_date?->translatedFormat("j M 'y") }} di {{ $peta[$c->install_place_id]['nama'] ?? $c->install_place_id }}</span>
                </div>

                <div class="ort-pcard__foot">
                    <span><i class="far fa-user" style="color:#A9866A;margin-right:6px;"></i>{{ $c->doctor_name ?: '—' }}</span>
                    <b>Detail <i class="fas fa-chevron-right" style="font-size:9px;"></i></b>
                </div>
            </a>
        @empty
            <div class="st-card" style="grid-column:1/-1;"><div class="st-card__body">
                @include('crud.ortho._kosong', ['adaFilter' => $adaFilter, 'selesai' => $modeSelesai])
            </div></div>
        @endforelse
    </div>

    <div class="st-card" style="margin-top:14px;">
        @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'pasien'])
    </div>

@else

    <div class="st-card" style="margin-top:13px;">
        <div class="ort-tablewrap">
            <div class="ort-thead">
                <div class="ort-rgrid">
                    <div>Pasien</div><div>Bracket</div><div>Dokter</div><div>Pasang</div>
                    <div>Cabang</div><div>{{ $modeSelesai ? 'Selesai' : 'Berikutnya' }}</div><div>Kehadiran</div><div></div>
                </div>
            </div>

            @forelse ($baris as $c)
                @php
                    [$sTeks, $sKelas, $telat] = $statusPasien($c);
                    $pillBg = ['menunggu' => '#F1E9DF|#A9866A', 'usulan' => '#F1E9DF|#A9866A', 'fu' => '#E4ECFB|#3F6BAF',
                               'rsv' => '#E4ECFB|#3F6BAF', 'batal' => '#FBE3DE|#C0392B',
                               'telat' => '#FBEFD9|#B5701A', 'telatlama' => '#FBE3DE|#C0392B'][$sKelas] ?? '#F1E9DF|#A9866A';
                    [$bg, $fg] = explode('|', $pillBg);
                @endphp
                <a href="{{ $tautanDetail($c->id) }}"
                   class="ort-tr {{ $telat > 14 ? 'ort-tr--bad' : ($telat > 0 ? 'ort-tr--warn' : '') }}"
                   style="display:block;text-decoration:none;color:inherit;">
                    <div class="ort-rgrid">
                        <div style="min-width:0;">
                            <div class="ort-name">{{ \Illuminate\Support\Str::title($c->patient_name) }}</div>
                            <div class="ort-meta">
                                {{ $c->omnicare_pid ?: 'belum tertaut RM' }}
                                @if ($c->bracket_arch) · {{ ucfirst($c->bracket_arch) }} @endif
                            </div>
                        </div>
                        <div class="ort-cell">{{ $c->bracket_type ?: '—' }}</div>
                        <div class="ort-cell ort-clamp2" title="{{ $c->doctor_name }}">{{ $c->doctor_name ?: '—' }}</div>
                        <div class="ort-cell tabular">{{ $c->install_date?->translatedFormat("j M 'y") }}</div>
                        <div>
                            @foreach ($c->branchesVisited() as $pid)
                                <span class="ort-pill-br {{ (string) $pid !== (string) $c->install_place_id ? 'ort-pill-br--other' : '' }}"
                                      title="{{ (string) $pid === (string) $c->install_place_id ? 'Cabang pasang' : 'Pernah kontrol di' }} · {{ $peta[$pid]['nama'] ?? $pid }}">
                                    {{ $peta[$pid]['nama'] ?? $pid }}
                                </span>
                            @endforeach
                        </div>
                        <div>
                            @if ($modeSelesai)
                                {{-- Tanggal tutup, bukan jadwal. Warnanya netral
                                     (kelas chip selesai) — tidak ada yang genting. --}}
                                @if ($c->finished_at)
                                    <span class="ort-datepill ort-chip--selesai tabular">
                                        {{ $c->finished_at->translatedFormat("j M 'y") }}
                                    </span>
                                @else
                                    <span class="ort-x">—</span>
                                @endif
                                <span class="ort-cell--sub">{{ $sTeks }}</span>
                            @elseif ($c->next_sch_date)
                                <span class="ort-datepill" style="background:{{ $bg }};color:{{ $fg }};">
                                    {{ \App\Models\OrtControl::perkiraanJatuhTempo($c->next_sch_date, $hariIni) }}
                                </span>
                                @if ($telat > 0)
                                    <span class="ort-cell--sub ort-sub-red tabular">Terlewat {{ $telat }} hari</span>
                                @endif
                            @else
                                <span class="ort-x">—</span>
                            @endif
                        </div>
                        <div class="ort-cell tabular">{{ $c->visit_count }}/{{ $c->control_count }}</div>
                        <div style="color:#C9AE94;"><i class="fas fa-chevron-right"></i></div>
                    </div>
                </a>
            @empty
                <div style="padding:14px 0;">@include('crud.ortho._kosong', ['adaFilter' => $adaFilter, 'selesai' => $modeSelesai])</div>
            @endforelse
        </div>

        @include('crud.ortho._paginasi', ['data' => $baris, 'satuan' => 'pasien'])
    </div>

@endif
