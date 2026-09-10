{{--
    IT Request Tracker — Papan Antrean (Fase 1) · rupa RONDE 3a (mockup r3, "samain aja dulu").

    Sumbu papan = FASE (7 tahap templat) dikelompokkan Masuk/Rancang/Kerjakan/
    Rilis; "menunggu siapa" = strip di kartu + filter Macet, BUKAN kolom
    (keputusan design brief §3). Panel beban di atas menjawab pertanyaan
    Executive dalam 5 detik.

    Tiga tingkat tab yang berbeda rupa: (1) jenis permintaan = band grafit,
    (2) tahap di dalam kolom = teks bergaris bawah, (3) status parkiran = chip
    cokelat bergaris putus. Tiket belum dinilai tampil di KEDUA tab jenis
    (keputusan Ekky 5 Sep) — badge menyebutnya, bukan menyembunyikannya.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
    $pendek   = fn ($nomor) => '#' . preg_replace('/^IT-\d{4}-/', '', (string) $nomor);
    $fAktif = array_filter([
        'q'       => $filter['q'] !== '' ? $filter['q'] : null,
        'macet'   => $filter['macet'] ? 1 : null,
        'sumber'  => $filter['sumber'],
        'jenjang' => $filter['jenjang'],
        'unit'    => $filter['unit'] ?? null,
        'modul'   => $filter['modul'] ?? null,
    ]);
    // tiap chip MEMPERTAHANKAN filter lain yang aktif
    $fLink = fn (array $ubah) => route('it-tracker.index', array_filter(array_merge($fAktif, $ubah)));
    $adaFilter = count($fAktif) > 0;
    $semuaKartu = collect($groups)->flatten(1)->flatMap(fn ($f) => $f['cards']);
    $nSupport = $semuaKartu->filter(fn ($c) => $c['t']->jenjang === 'S')->count();
    $nFitur   = $semuaKartu->filter(fn ($c) => in_array($c['t']->jenjang, ['M', 'L'], true))->count();
    $nBaru    = $semuaKartu->filter(fn ($c) => ! $c['t']->jenjang)->count();
    $jenisAwal = $filter['jenjang'] === 'S' ? 'support' : 'fitur';
    $SUB = ['MASUK' => 'Requirement · penilaian awal & antre', 'RANCANG' => 'Analysis & Design',
            'KERJAKAN' => 'Development · Internal Testing', 'RILIS' => 'UAT · Go Live · Evaluation'];
    $SHORT = ['DEV' => 'Dev', 'UJI' => 'Uji', 'UAT' => 'UAT', 'GOLIVE' => 'Go Live', 'EVALUASI' => 'Evaluasi'];
    $inisial = mb_strtoupper(mb_substr((string) session('name'), 0, 1)) ?: 'IT';
@endphp
<div class="itk-surface">

    <div class="itk-topbar">
        <div>
            <h1>IT Request Tracker</h1>
        </div>
        <span class="itk-badge-zona">ZONA IT</span>
        <span class="sub">{{ now()->translatedFormat('l, j M Y') }} · 2 orang IT · 5 cabang + RB Las</span>
        <div class="itk-topbar-nav">
            {{-- tautan dua arah: papan ⇄ katalog. Papan = apa yang sedang bergerak;
                 katalog = apa yang kami punya. Dua pertanyaan, dua halaman. --}}
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.katalog') }}" title="Apa saja yang kami punya — 33 entri, 8 departemen">Katalog Modul</a>
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.permintaan') }}">
                {{ $adalahIt ? '+ Inisiatif IT' : 'Permintaan Saya' }}</a>
            <div class="itk-avatar" title="{{ session('name') }} · {{ session('group_name') }}">{{ $inisial }}</div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Panel Executive: terbaca 5 detik (brief §5b no. 1-3) --}}
    <div class="itk-beban">
        <div class="itk-beban-bar"><b>BEBAN IT SAAT INI</b><span class="itk-badge-fase">FASE 1</span></div>
        <div class="itk-beban-grid">
            <div>
                <span class="itk-big num">{{ $beban['aktif'] }}</span><span class="itk-big-lbl">item aktif · 2 orang IT</span>
                @php $tot = max(1, $beban['aktif']); @endphp
                <div class="itk-splitbar">
                    <div class="perm" style="width:{{ round($beban['permintaan'] / $tot * 100) }}%"></div>
                    <div class="init" style="width:{{ round($beban['inisiatif'] / $tot * 100) }}%"></div>
                </div>
                <div class="itk-split-leg">
                    <span><i style="background:#3A3A36"></i>{{ $beban['permintaan'] }} dari formulir permintaan</span>
                    <span><i style="background:#A8481B"></i>{{ $beban['inisiatif'] }} inisiatif IT sendiri</span>
                </div>
            </div>
            <div>
                <span class="itk-big num" style="color:{{ $beban['macet'] ? '#C0392B' : '#2F8E55' }}">{{ $beban['macet'] }}</span>
                <span class="itk-big-lbl">macet menunggu di luar IT</span>
                @if ($macetChips)
                    {{-- satu chip per tiket macet: nomor + berapa hari — klik membuka detailnya --}}
                    <div class="itk-macet-chips">
                        @foreach (array_slice($macetChips, 0, 8) as $m)
                            <a class="itk-macet-chip num panas{{ $m['heat'] }}" href="{{ route('it-tracker.show', $m['id']) }}"
                               title="{{ $m['label'] }}">{{ $pendek($m['nomor']) }} · {{ $m['days'] }} hr</a>
                        @endforeach
                        @if (count($macetChips) > 8)
                            <a class="itk-macet-chip panas1" href="{{ $fLink(['macet' => 1]) }}">+{{ count($macetChips) - 8 }} lagi</a>
                        @endif
                    </div>
                @else
                    <div class="itk-macet-none">Tidak ada — semua tiket bergerak di pihak yang benar.</div>
                @endif
            </div>
            <div>
                {{-- Kalimat konsekuensi — inilah yang mengubah refleks jadi keputusan sadar --}}
                <div class="itk-konsekuensi">Tambah satu permintaan sekarang →
                    <strong>antre di belakang {{ $beban['diTangan'] }}</strong> yang sudah di tangan IT.</div>
                <div style="margin-top:10px">
                    <a class="itk-btn itk-btn-grafit" href="{{ route('it-tracker.permintaan') }}">
                        {{ $adalahIt ? '+ Catat inisiatif IT' : 'Ajukan permintaan' }}</a>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ TINGKAT 1 — tab JENIS permintaan: segmen besar di band grafit ═══
         Belum dinilai tampil di KEDUA tab (keputusan Ekky 5 Sep): ia belum punya
         jenjang, jadi belum bisa dipilihkan; badge tiap tab = yang benar-benar
         tampil di tab itu, dan catatan kanan menjelaskan hitungan gandanya. --}}
    <div class="itk-tabband" id="itkTabJenis" data-jenis-awal="{{ $jenisAwal }}">
        <button type="button" class="itk-tj" data-jenis="fitur">
            <b>Fitur / Modul</b><i class="num" data-tj-n>{{ $nFitur + $nBaru }}</i>
            <span>jenjang M · L — lewat 5 atau 7 fase</span>
        </button>
        <button type="button" class="itk-tj" data-jenis="support">
            <b>Support</b><i class="num" data-tj-n>{{ $nSupport + $nBaru }}</i>
            <span>jenjang S — Requirement → Development</span>
        </button>
        <span class="itk-tj-note">Belum dinilai (<span class="num">{{ $nBaru }}</span>) belum punya jenjang —<br>tampil di kedua tab, ikut dihitung di keduanya.</span>
    </div>

    {{-- Filter sekunder — bisa digabung; tiap chip mempertahankan yang lain --}}
    <div class="itk-filterrow">
        <form method="get" action="{{ route('it-tracker.index') }}" class="itk-cari">
            @foreach ($fAktif as $k => $vv)
                @if ($k !== 'q')<input type="hidden" name="{{ $k }}" value="{{ $vv }}">@endif
            @endforeach
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.6" stroke="#B79A82" stroke-width="1.5"/><path d="M10.6 10.6 14 14" stroke="#B79A82" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="IT-2026-005 / accurate...">
        </form>
        <a class="itk-fchip {{ $filter['macet'] ? 'aktif' : '' }}" href="{{ $fLink(['macet' => $filter['macet'] ? null : 1]) }}">Macet{{ $beban['macet'] ? ' · ' . $beban['macet'] : '' }}{{ $filter['macet'] ? '  ✕' : '' }}</a>
        <a class="itk-fchip {{ $filter['sumber'] === 'permintaan' ? 'aktif' : '' }}" href="{{ $fLink(['sumber' => $filter['sumber'] === 'permintaan' ? null : 'permintaan']) }}">Permintaan{{ $filter['sumber'] === 'permintaan' ? '  ✕' : '' }}</a>
        <a class="itk-fchip {{ $filter['sumber'] === 'inisiatif' ? 'aktif' : '' }}" href="{{ $fLink(['sumber' => $filter['sumber'] === 'inisiatif' ? null : 'inisiatif']) }}">Inisiatif IT{{ $filter['sumber'] === 'inisiatif' ? '  ✕' : '' }}</a>
        <a class="itk-fchip {{ ($filter['unit'] ?? null) === 'rblas' ? 'aktif' : '' }}" href="{{ $fLink(['unit' => ($filter['unit'] ?? null) === 'rblas' ? null : 'rblas']) }}">RB Las{{ ($filter['unit'] ?? null) === 'rblas' ? '  ✕' : '' }}</a>
        @if (! empty($filter['modul']))
            {{-- datang dari katalog: sebutkan modulnya + jalan balik ke ringkasannya --}}
            <a class="itk-fchip aktif" href="{{ $fLink(['modul' => null]) }}" title="Lepas saringan modul">Modul: {{ $filter['modulNama'] }}  ✕</a>
            <a class="itk-fchip" href="{{ route('it-tracker.katalog', ['modul' => $filter['modul']]) }}">Ringkasan modul di katalog ›</a>
        @endif
        <button type="button" class="itk-parkir-toggle" id="itkParkirToggle">Parkiran · <span class="num" data-parkir-total>{{ $parkir->count() }}</span> <span class="caret">▾</span></button>
    </div>

    {{-- Bar filter menyala: selalu terlihat saat ada yang aktif --}}
    @if ($adaFilter)
        @php
            $fTeks = array_filter([
                $filter['macet'] ? 'Macet' : null,
                $filter['sumber'] === 'permintaan' ? 'Permintaan' : ($filter['sumber'] === 'inisiatif' ? 'Inisiatif IT' : null),
                ($filter['unit'] ?? null) === 'rblas' ? 'RB Las' : null,
                $filter['jenjang'] ? 'jenjang ' . $filter['jenjang'] : null,
                ! empty($filter['modul']) ? 'modul ' . $filter['modulNama'] : null,
                $filter['q'] !== '' ? 'cari "' . $filter['q'] . '"' : null,
            ]);
        @endphp
        <div class="itk-filterbar">
            <span class="lampu"></span>
            <b>Filter menyala:</b>
            <span>{{ implode(' · ', $fTeks) }}</span>
            <span class="n num">— {{ $semuaKartu->count() }} dari {{ $totalAktif }} tiket aktif tampil</span>
            <a class="matikan" href="{{ route('it-tracker.index') }}">Matikan semua filter</a>
        </div>
    @endif

    {{-- Parkiran (TINGKAT 3): lipat DI ATAS papan — ditunda tetap tampil dengan
         alasannya, ditunda bukan berarti hilang. Ikut tab jenis di atas. --}}
    @php $urutStatus = ['DITUNDA' => 'Ditunda', 'DITOLAK' => 'Ditolak', 'SELESAI' => 'Selesai']; @endphp
    <div class="itk-parkiran-box" id="itkParkiran" hidden>
        <div class="itk-parkiran-hd">
            <b>PARKIRAN</b>
            @foreach ($urutStatus as $kode => $label)
                <button type="button" class="itk-pk" data-pk="{{ $kode }}">{{ $label }} <span class="n num">· <span data-pk-n>{{ $parkir->where('fase', $kode)->count() }}</span></span></button>
            @endforeach
            <span class="catatan">Ditunda bukan berarti hilang — alasannya ikut tampil.</span>
        </div>
        @foreach ($urutStatus as $kode => $label)
            <div class="itk-parkir-panel" data-pkp="{{ $kode }}" hidden>
                <div class="itk-parkir">
                    @foreach ($parkir->where('fase', $kode) as $p)
                        @php $jp = $p->jenjang === 'S' ? 'support' : ($p->jenjang ? 'fitur' : 'baru'); @endphp
                        <div class="itk-parkir-card" data-parkir data-jenis="{{ $jp }}" onclick="window.location='{{ route('it-tracker.show', $p->id) }}'">
                            <div class="itk-card-top">
                                <span class="itk-no num" style="font-size:11.5px;color:#9A7256">{{ $p->nomor }}</span>
                                <span class="itk-pk-st {{ $p->fase }}">
                                    @if ($p->fase === 'DITUNDA') DITUNDA — TETAP TERCATAT
                                    @elseif ($p->fase === 'DITOLAK') DITOLAK
                                    @else SELESAI · {{ $p->fase_since->translatedFormat('j M') }}
                                    @endif
                                </span>
                                <span class="chev">›</span>
                            </div>
                            <div class="itk-card-title" style="font-size:13px;color:#5A3A22;margin-top:5px">{{ $p->judul }}</div>
                            @if ($p->alasan_parkir)
                                <div style="font-size:12px;color:#7A5B43;margin-top:5px;line-height:1.45">{{ $p->alasan_parkir }}</div>
                            @endif
                            @if ($p->fase === 'DITUNDA' && $bolehKerjaIt)
                                <form method="post" action="{{ route('it-tracker.buka', $p->id) }}" onclick="event.stopPropagation()">
                                    @csrf
                                    {{-- yang belum pernah dinilai kembali ke Penilaian awal, bukan Antre --}}
                                    <button type="submit" class="itk-bukalagi">Buka lagi → {{ $p->jenjang ? 'Antre' : \App\Services\ItTracker\PhaseEngine::TXT_TRIASE }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                    <div class="itk-parkir-kosong" data-pk-kosong hidden>Tidak ada tiket di sini.</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Papan: sumbu = FASE, dikelompokkan Masuk/Rancang/Kerjakan/Rilis --}}
    @if ($semuaKartu->isEmpty())
        <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;padding:44px;text-align:center;margin-bottom:14px">
            <div style="font-size:15px;font-weight:800;color:#5A3A22">
                {{ $filter['macet'] ? 'Tidak ada yang macet — antrean sehat' : ($adaFilter ? 'Tidak ada tiket yang cocok dengan filter ini.' : 'Papan kosong') }}
            </div>
            <div style="font-size:13px;color:#9A7256;margin-top:6px">
                {{ $adaFilter
                    ? 'Semua tiket sedang di tempat lain. Matikan filternya untuk melihat semuanya.'
                    : 'Belum ada tiket. Permintaan pertama akan langsung tampil di kolom Requirement.' }}
            </div>
            @if ($filter['q'] !== '' && $parkir->isNotEmpty())
                {{-- Pencarian menjangkau parkiran: tiket yang sudah ditutup tetap ADA,
                     dan papan yang diam saja membuatnya seolah tak pernah ada. --}}
                <div style="font-size:13px;color:#5A3A22;font-weight:700;margin-top:12px">
                    Tapi <span class="num">{{ $parkir->count() }}</span> tiket yang cocok ada di <strong>parkiran</strong>:
                    {{ $parkir->take(3)->map(fn ($p) => $p->nomor . ' (' . ucfirst(strtolower($p->fase)) . ')')->implode(' · ') }}{{ $parkir->count() > 3 ? ' …' : '' }}
                </div>
                <div style="margin-top:9px"><button type="button" class="itk-btn itk-btn-putih" onclick="itkBukaParkiran()">Buka parkiran</button></div>
            @endif
        </div>
    @endif

    <div class="itk-board" id="itkBoard" data-saring-teks="{{ $saring['teks'] ?? '' }}">
        @foreach ($groups as $nama => $fases)
            <div class="itk-kolom" data-kolom>
                {{-- Kepala wilayah: tegas, angka besar --}}
                <div class="itk-kolom-hd">
                    <span class="n num" data-kolom-n>{{ collect($fases)->sum(fn ($f) => $f['cards']->count()) }}</span>
                    <div class="nm"><b>{{ $nama }}</b><span>{{ $SUB[$nama] ?? '' }}</span></div>
                </div>

                {{-- TINGKAT 2 — tab TAHAP di dalam kolom (hanya kolom bertahap ganda) --}}
                @if (count($fases) > 1)
                    <div class="itk-fasetab" data-fasetab>
                        @foreach ($fases as $i => $f)
                            <button type="button" class="itk-ft" data-ft="{{ $i }}">
                                {{ $SHORT[$f['k']] ?? $f['label'] }} <i class="n num" data-ft-n>{{ $f['cards']->count() }}</i></button>
                        @endforeach
                    </div>
                @endif

                <div class="itk-kolom-body">
                @foreach ($fases as $i => $f)
                    @php $raw = $rawFase[$f['k']] ?? []; @endphp
                    <div class="itk-fase-panel" data-fp="{{ $i }}" data-fase="{{ $f['k'] }}" data-label="{{ $f['label'] }}"
                         data-raw-fitur="{{ $raw['fitur'] ?? 0 }}" data-raw-support="{{ $raw['support'] ?? 0 }}" data-raw-baru="{{ $raw['baru'] ?? 0 }}">
                        @if (count($fases) === 1)
                            <div class="itk-fase-hd"><span class="n num" data-ft-n>{{ $f['cards']->count() }}</span></div>
                        @endif
                    @foreach ($f['cards'] as $c)
                        @php
                            $t = $c['t']; $w = $c['wait'];
                            $jenisKartu = $t->jenjang === 'S' ? 'support' : ($t->jenjang ? 'fitur' : 'baru');
                            $srcTxt = $t->sumber === 'inisiatif' ? 'INISIATIF IT'
                                : ($t->sumber === 'insiden' ? 'INSIDEN'
                                : mb_strtoupper(($t->pemohon->nama ?? 'PERMINTAAN') . (! empty($c['dept']) ? ' · ' . $c['dept'] : '')));
                        @endphp
                        {{-- Kartu dua-detik: nomor · siapa · PIC · prio → judul → (kemajuan) →
                             (dep / tanpa paraf) → strip menunggu. Jenjang & fase TIDAK di kartu
                             (sudah dikatakan tab & kolom). RB Las = garis kiri oranye.
                             div ber-onclick, bukan <a>: kartu memuat form aksi. --}}
                        <div class="itk-card {{ $t->unit === 'rblas' ? 'itk-rblas' : '' }} {{ $t->fase === 'TRIASE' ? 'itk-triase' : '' }}"
                             style="cursor:pointer" data-kartu data-jenis="{{ $jenisKartu }}"
                             onclick="window.location='{{ route('it-tracker.show', $t->id) }}'">
                            <div class="itk-card-top">
                                <span class="itk-no num" title="{{ $t->nomor }}">{{ $pendek($t->nomor) }}</span>
                                <span class="itk-src {{ $t->sumber }}">{{ $srcTxt }}</span>
                                <span class="kanan">
                                    @if ($c['pic'])<span class="itk-pic-chip" title="dikerjakan {{ $c['pic'] }}">{{ $c['pic'] }}</span>@endif
                                    @if ($t->prioritas_it)<span class="itk-prio {{ $t->prioritas_it }}">{{ $t->prioritas_it }}</span>@endif
                                    @if ($t->fase === 'TRIASE')<span class="itk-tag-triase">BELUM DITRIASE</span>@endif
                                </span>
                            </div>
                            <div class="itk-card-title">{{ $t->judul }}</div>
                            @if ($c['kemajuan'])
                                @php $hariKm = $c['kemajuan']['at'] ? (int) $c['kemajuan']['at']->diffInDays(now()) : null; $basi = $hariKm !== null && $hariKm >= 7; @endphp
                                {{-- "sudah sampai mana": baris ikon kutip; basi = kuning + umur --}}
                                <div class="itk-kemajuan {{ $basi ? 'basi' : '' }}" title="catatan kemajuan dari IT">
                                    <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor"><path d="M3 9.5C3 6.5 4.6 4.4 7.3 3.3l.6 1.2C6.3 5.3 5.5 6.3 5.3 7.6c1.2 0 2.1.9 2.1 2.1 0 1.2-1 2.1-2.2 2.1S3 10.9 3 9.5Zm6 0c0-3 1.6-5.1 4.3-6.2l.6 1.2c-1.6.8-2.4 1.8-2.6 3.1 1.2 0 2.1.9 2.1 2.1 0 1.2-1 2.1-2.2 2.1S9 10.9 9 9.5Z"/></svg>
                                    <span class="txt">{{ $c['kemajuan']['txt'] }}</span>
                                    @if ($hariKm !== null)
                                        <span class="num">{{ $basi ? $hariKm . ' hr · basi' : ($hariKm === 0 ? 'hari ini' : $hariKm . ' hr') }}</span>
                                    @endif
                                </div>
                            @endif
                            @if ($c['deps'] || ! empty($c['diteruskan']))
                                <div class="itk-card-meta">
                                    @if ($c['deps'])<span class="itk-dep-chip num" title="menunggu tiket lain">⛓ menunggu {{ implode(', ', $c['deps']) }}</span>@endif
                                    @if (! empty($c['diteruskan']))<span class="itk-lewat-chip" title="Diteruskan {{ $c['diteruskan']['oleh'] }} tanpa paraf Business Owner · {{ $c['diteruskan']['alasan'] }}">tanpa paraf BO</span>@endif
                                </div>
                            @endif
                            {{-- Strip menunggu-siapa: jantung kartu --}}
                            <div class="itk-wait {{ \App\Services\ItTracker\PhaseEngine::waitCls($w, $t->fase) }}">
                                <i></i>
                                <span class="num">
                                    @if ($t->fase === 'ANTRE') Antre di belakang {{ $c['antre'] }} item
                                    @else {{ $w['label'] }} · {{ $w['days'] }} hr
                                    @endif
                                </span>
                                @if ($c['prog'])<span class="itk-wait-prog num" title="{{ $c['prog']['label'] }}">{{ $c['prog']['i'] }}/{{ $c['prog']['n'] }}</span>@endif
                                <span class="chev">›</span>
                            </div>
                            @if ($c['tombol'])
                                @if ($c['tombol']['jenis'] === 'triase')
                                    <button type="button" class="itk-btn itk-btn-grafit itk-card-btn"
                                        onclick="event.stopPropagation(); itkBukaTriase('{{ $t->id }}', '{{ $t->nomor }}', @js($t->judul), @js($t->prioritas_pemohon))">
                                        {{ $c['tombol']['label'] }}</button>
                                @else
                                    {{-- tombol maju HIJAU (keputusan Ekky 5 Sep) — beda dari Penilaian awal yang grafit --}}
                                    <form method="post" action="{{ route('it-tracker.maju', $t->id) }}"
                                          onclick="event.stopPropagation()">
                                        @csrf
                                        <button type="submit" class="itk-btn itk-btn-hijau itk-card-btn">{{ $c['tombol']['label'] }}</button>
                                    </form>
                                @endif
                            @elseif ($c['blokir'] && $c['blokirLuar'])
                                {{-- hanya kekurangan yang BUKAN pekerjaan IT sendiri; sisanya di detail --}}
                                <div class="itk-blokir">{{ $c['blokir'] }}</div>
                            @endif
                        </div>
                    @endforeach
                        <div class="itk-fase-kosong" data-fase-kosong hidden>Tidak ada tiket di tahap ini.</div>
                        @if ($saring)
                            {{-- Kosong karena FILTER ≠ kosong sungguhan: sebut ke mana tiketnya
                                 pergi + satu jalan keluar sekali klik. Tanpa ini orang menyimpulkan
                                 "Dev lagi sepi" padahal cuma tersaring. --}}
                            <div class="itk-fase-saring" data-fase-saring hidden>
                                <div class="hd"><span class="lbl">{{ $f['label'] }}</span><span class="num" data-saring-n>0 yang cocok</span></div>
                                <div class="txt"><span data-saring-txt></span></div>
                                <a class="aksi" href="{{ $saring['href'] }}">{{ $saring['aksi'] }}</a>
                            </div>
                        @endif
                    </div>
                @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <div class="itk-tab-kosong" id="itkTabKosong" hidden></div>

    {{-- Sistem warna: tiap arti punya SALURAN sendiri — tak saling meminjam --}}
    <div class="itk-legenda-warna" style="margin-top:12px">
        <b>SISTEM WARNA</b>
        <span><i class="lg-strip"></i> strip menunggu — <strong>isi kartu</strong>, memanas hanya bila penunggunya di luar IT (≥3 / ≥7 hr)</span>
        <span><i class="lg-prio1">P1</i><i class="lg-prio2">P2</i> prioritas — <strong>grafit</strong>, tanpa warna</span>
        <span><i class="lg-rb"></i> RB Las — <strong>garis kiri oranye</strong>, tepi kartu; Sweet Treats polos</span>
        <span><i class="lg-paraf">tanpa paraf BO</i> pelewatan paraf — <strong>garis putus merah</strong>, bukan isian</span>
        <span><i class="lg-parkir"></i> parkir — <strong>hanya di parkiran</strong>, bergaris putus</span>
        <span><i class="lg-basi">❝ 12 hr</i> catatan kemajuan basi — <strong>teks kuning + umur</strong>, tanpa latar</span>
    </div>
    <div class="itk-legenda">Badge: <strong>Fase 1</strong> = formulir + penilaian awal + papan · <strong>Fase 2</strong> = tujuh fase + gerbang ·
        <strong>Fase 3</strong> = sambungan Project Tracker. Tiga tingkat tab: jenis (band grafit) → tahap (garis bawah di kolom) → status parkir (chip bergaris putus).</div>

    <script>
    (function () {
        // Tab jenis permintaan + tab tahap di dalam kolom. Semua kartu tetap di DOM;
        // yang berubah cuma yang terlihat, jadi hitungan di kepala kolom & tab
        // DIHITUNG ULANG tiap ganti tab — bukan angka statis yang lekas berbohong.
        var bar = document.getElementById('itkTabJenis'), board = document.getElementById('itkBoard');
        if (!bar || !board) return;
        var kosongPapan = document.getElementById('itkTabKosong');
        // belum ditriase (data-jenis="baru") tampil di KEDUA tab — keputusan Ekky 5 Sep
        var cocok = function (kartu, jenis) {
            var j = kartu.getAttribute('data-jenis');
            return j === 'baru' || j === jenis;
        };
        // Tahap kosong menyebut ALASANNYA, bukan angka 0 polos.
        var alasanKosong = function (jenis, fase, label) {
            if (fase === 'UJI') return label + ' kosong — hanya jenjang Modul lewat sini';
            if (fase === 'GOLIVE') return label + ' kosong — dihuni beberapa hari saja';
            if (jenis === 'support' && fase !== 'REQ' && fase !== 'DEV') return label + ' kosong — jalur Support hanya Requirement → Development';
            return label + ' kosong';
        };

        function terap(jenis, simpanUrl) {
            bar.querySelectorAll('.itk-tj').forEach(function (b) {
                b.classList.toggle('aktif', b.getAttribute('data-jenis') === jenis);
            });
            var totalPapan = 0;
            board.querySelectorAll('[data-kolom]').forEach(function (kol) {
                var totalKol = 0, panels = kol.querySelectorAll('.itk-fase-panel');
                panels.forEach(function (panel, i) {
                    var n = 0;
                    panel.querySelectorAll('[data-kartu]').forEach(function (k) {
                        var tampil = cocok(k, jenis);
                        k.hidden = ! tampil;
                        if (tampil) n++;
                    });
                    totalKol += n;
                    panel.setAttribute('data-n', n);
                    // kosong karena FILTER (server) vs kosong sungguhan — dua pesan berbeda
                    var rawTab = (+panel.getAttribute('data-raw-' + jenis) || 0) + (+panel.getAttribute('data-raw-baru') || 0);
                    var saring = panel.querySelector('[data-fase-saring]');
                    var tersaring = n === 0 && rawTab > 0 && !!saring;
                    var kosong = panel.querySelector('[data-fase-kosong]');
                    if (kosong) {
                        kosong.hidden = n !== 0 || tersaring;
                        kosong.textContent = alasanKosong(jenis, panel.getAttribute('data-fase') || '', panel.getAttribute('data-label') || 'Tahap');
                    }
                    if (saring) {
                        saring.hidden = ! tersaring;
                        if (tersaring) {
                            saring.querySelector('[data-saring-n]').textContent = '0 yang cocok';
                            saring.querySelector('[data-saring-txt]').textContent =
                                rawTab + ' tiket ' + (panel.getAttribute('data-label') || 'tahap ini') + ' ' + (board.getAttribute('data-saring-teks') || '');
                        }
                    }
                    var badge = panel.querySelector('[data-ft-n]');
                    if (badge) badge.textContent = n;
                    var tombol = kol.querySelector('[data-ft="' + i + '"]');
                    if (tombol) {
                        tombol.querySelector('[data-ft-n]').textContent = n;
                        tombol.classList.toggle('kosong', n === 0);
                    }
                });
                kol.querySelector('[data-kolom-n]').textContent = totalKol;
                // Keempat wilayah TETAP tampil di semua tab: strukturnya sama, yang kosong menyebut alasannya.
                kol.hidden = false;
                totalPapan += totalKol;

                // tab tahap: pertahankan yang aktif kalau berisi; kalau tidak, tahap pertama yang berisi
                var tabs = kol.querySelectorAll('[data-ft]');
                if (tabs.length) {
                    var aktif = kol.querySelector('[data-ft].aktif'), pilih = -1;
                    if (aktif && +aktif.querySelector('[data-ft-n]').textContent > 0) pilih = +aktif.getAttribute('data-ft');
                    if (pilih < 0) {
                        tabs.forEach(function (b) {
                            if (pilih < 0 && +b.querySelector('[data-ft-n]').textContent > 0) pilih = +b.getAttribute('data-ft');
                        });
                    }
                    if (pilih < 0) pilih = 0;
                    pilihTahap(kol, pilih);
                }
            });
            terapParkir(jenis);
            if (kosongPapan) {
                kosongPapan.hidden = totalPapan !== 0;
                kosongPapan.textContent = jenis === 'support'
                    ? 'Tidak ada Support aktif. Bug & insiden yang sudah selesai ada di parkiran.'
                    : 'Tidak ada Fitur / Modul aktif.';
            }
            if (simpanUrl && window.history.replaceState) {
                var u = new URL(window.location.href);
                u.searchParams.set('jenis', jenis);
                u.searchParams.delete('jenjang');
                window.history.replaceState({}, '', u);
            }
        }

        // Parkiran: tiga status jadi chip, isinya ikut tab jenis permintaan.
        var parkiran = document.getElementById('itkParkiran'), toggle = document.getElementById('itkParkirToggle');
        function terapParkir(jenis) {
            if (!parkiran) return;
            var total = 0, pertamaBerisi = null, aktifKini = parkiran.querySelector('[data-pk].aktif');
            parkiran.querySelectorAll('.itk-parkir-panel').forEach(function (panel) {
                var kode = panel.getAttribute('data-pkp'), n = 0;
                panel.querySelectorAll('[data-parkir]').forEach(function (k) {
                    var tampil = cocok(k, jenis);
                    k.hidden = ! tampil;
                    if (tampil) n++;
                });
                total += n;
                var kosong = panel.querySelector('[data-pk-kosong]');
                if (kosong) kosong.hidden = n !== 0;
                var tombol = parkiran.querySelector('[data-pk="' + kode + '"]');
                if (tombol) {
                    tombol.querySelector('[data-pk-n]').textContent = n;
                    tombol.classList.toggle('kosong', n === 0);
                }
                if (n > 0 && pertamaBerisi === null) pertamaBerisi = kode;
            });
            var pilih = pertamaBerisi || 'DITUNDA';
            if (aktifKini) {
                var kodeAktif = aktifKini.getAttribute('data-pk');
                if (+parkiran.querySelector('[data-pk="' + kodeAktif + '"] [data-pk-n]').textContent > 0) pilih = kodeAktif;
            }
            pilihParkir(pilih);
            var tot = document.querySelector('[data-parkir-total]');
            if (tot) tot.textContent = total;
        }
        function pilihParkir(kode) {
            parkiran.querySelectorAll('[data-pk]').forEach(function (b) { b.classList.toggle('aktif', b.getAttribute('data-pk') === kode); });
            parkiran.querySelectorAll('.itk-parkir-panel').forEach(function (p) { p.hidden = p.getAttribute('data-pkp') !== kode; });
        }
        function bukaParkir(buka, simpanUrl) {
            if (!parkiran) return;
            parkiran.hidden = ! buka;
            if (toggle) toggle.classList.toggle('buka', buka);
            if (simpanUrl && window.history.replaceState) {
                var u = new URL(window.location.href);
                if (buka) u.searchParams.set('parkir', '1'); else u.searchParams.delete('parkir');
                window.history.replaceState({}, '', u);
            }
        }
        function pilihTahap(kol, idx) {
            kol.querySelectorAll('[data-ft]').forEach(function (b) { b.classList.toggle('aktif', +b.getAttribute('data-ft') === idx); });
            kol.querySelectorAll('.itk-fase-panel').forEach(function (p) { p.hidden = +p.getAttribute('data-fp') !== idx; });
        }

        bar.querySelectorAll('.itk-tj').forEach(function (b) {
            b.onclick = function () { terap(b.getAttribute('data-jenis'), true); };
        });
        board.querySelectorAll('[data-ft]').forEach(function (b) {
            b.onclick = function () { pilihTahap(b.closest('[data-kolom]'), +b.getAttribute('data-ft')); };
        });
        document.querySelectorAll('[data-pk]').forEach(function (b) {
            b.onclick = function () { pilihParkir(b.getAttribute('data-pk')); };
        });
        if (toggle) toggle.onclick = function () { bukaParkir(parkiran.hidden, true); };
        window.itkBukaParkiran = function () { bukaParkir(true, true); if (parkiran) parkiran.scrollIntoView({ behavior: 'smooth', block: 'start' }); };

        var url = new URL(window.location.href), dariUrl = url.searchParams.get('jenis');
        terap(['support', 'fitur'].indexOf(dariUrl) >= 0 ? dariUrl : bar.getAttribute('data-jenis-awal'), false);
        bukaParkir(url.searchParams.get('parkir') === '1' || window.location.hash === '#itk-parkiran', false);
    })();
    </script>
</div>

{{-- Modal triase — partial yang SAMA dipakai halaman detail; bolehTriase = Head IT + Executive + Konsultan IT --}}
@if ($bolehTriase ?? false)
    @include('it-tracker._modal-triase')
@endif
@endsection
