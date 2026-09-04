{{--
    IT Request Tracker — Papan Antrean (Fase 1).

    Sumbu papan = FASE (7 tahap templat) dikelompokkan Masuk/Rancang/Kerjakan/
    Rilis; "menunggu siapa" = strip di kartu + filter Macet, BUKAN kolom
    (keputusan design brief §3). Panel beban di atas menjawab pertanyaan
    Executive dalam 5 detik.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="itk-surface">

    <div class="itk-head">
        <h1>IT Request Tracker</h1>
        <span class="itk-badge-zona">ZONA IT</span>
        <div class="itk-head-nav">
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.permintaan') }}">
                {{ in_array($peran, ['head_it','it_dev']) ? 'Catat Inisiatif / Insiden' : 'Permintaanku' }}
            </a>
        </div>
        <div class="itk-head-sub">Dipikul 2 orang IT · 5 cabang · warna strip memanas hanya bila penunggunya di luar IT</div>
    </div>

    @include('layouts.notif')

    {{-- Tautan filter: tiap chip MEMPERTAHANKAN filter lain yang aktif --}}
    @php
        $fAktif = array_filter([
            'q'       => $filter['q'] !== '' ? $filter['q'] : null,
            'macet'   => $filter['macet'] ? 1 : null,
            'sumber'  => $filter['sumber'],
            'jenjang' => $filter['jenjang'],
        ]);
        $fLink = fn (array $ubah) => route('it-tracker.index', array_filter(array_merge($fAktif, $ubah)));
    @endphp

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
                <div style="margin-top:12px">
                    <a class="itk-chip {{ $filter['macet'] ? 'aktif' : '' }}"
                       href="{{ $fLink(['macet' => $filter['macet'] ? null : 1]) }}">Lihat yang macet</a>
                </div>
            </div>
            <div>
                {{-- Kalimat konsekuensi — inilah yang mengubah refleks jadi keputusan sadar --}}
                <div class="itk-konsekuensi">Tambah satu permintaan sekarang →
                    <strong>antre di belakang {{ $beban['diTangan'] }}</strong> yang sudah di tangan IT.</div>
            </div>
        </div>
    </div>

    {{-- Filter — bisa digabung; $fLink didefinisikan di atas panel beban --}}
    <div class="itk-filter">
        <form method="get" action="{{ route('it-tracker.index') }}" style="display:inline">
            @foreach ($fAktif as $k => $vv)
                @if ($k !== 'q')<input type="hidden" name="{{ $k }}" value="{{ $vv }}">@endif
            @endforeach
            <input type="text" name="q" value="{{ $filter['q'] }}" placeholder="IT-2026-005 / accurate...">
        </form>
        <a class="itk-chip {{ $filter['sumber'] === 'permintaan' ? 'aktif' : '' }}"
           href="{{ $fLink(['sumber' => $filter['sumber'] === 'permintaan' ? null : 'permintaan']) }}">Permintaan</a>
        <a class="itk-chip {{ $filter['sumber'] === 'inisiatif' ? 'aktif' : '' }}"
           href="{{ $fLink(['sumber' => $filter['sumber'] === 'inisiatif' ? null : 'inisiatif']) }}">Inisiatif IT</a>
        {{-- Chip S/M/L DIHAPUS: digantikan tab jenis permintaan di bawah (dua mekanisme
             untuk hal yang sama = keduanya akan berselisih). ?jenjang= lama tetap dilayani
             controller, dan tab menyesuaikan diri lewat data-jenis-awal. --}}
        @if ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
            <a class="itk-chip" href="{{ route('it-tracker.index') }}">bersihkan</a>
        @endif
        @if ($parkir->isNotEmpty())
            <a class="itk-chip" style="margin-left:auto" href="#itk-parkiran" id="itkChipParkir">Ditunda / ditolak / selesai (<span data-parkir-total>{{ $parkir->count() }}</span>) ▾</a>
        @endif
    </div>

    {{-- Papan --}}
    @php $totalKartu = collect($groups)->flatten(1)->sum(fn ($f) => $f['cards']->count()); @endphp
    @if ($totalKartu === 0)
        <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;padding:44px;text-align:center;margin-bottom:14px">
            <div style="font-size:15px;font-weight:800;color:#5A3A22">
                {{ ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
                    ? 'Tidak ada tiket yang cocok dengan filter ini.'
                    : 'Papan masih kosong.' }}
            </div>
            <div style="font-size:13px;color:#9A7256;margin-top:6px">
                {{ ($filter['q'] !== '' || $filter['macet'] || $filter['sumber'] || $filter['jenjang'])
                    ? 'Bersihkan filternya untuk melihat semua tiket aktif.'
                    : 'Permintaan dari Head masuk lewat formulir; kerjaan IT sendiri dicatat lewat tombol "Catat Inisiatif / Insiden" di kanan atas.' }}
            </div>
        </div>
    @endif
    {{-- ═══ Tab JENIS PERMINTAAN (keputusan Ekky 4 Sep) ═══
         S = Support Request (2 fase) · M/L = Fitur & Modul Request (5–7 fase).
         Tiket yang BELUM ditriase belum punya jenjang — muncul di KEDUA tab supaya
         tak ada yang hilang, dan ditandai "belum ditriase". Semua kartu tetap ada di
         DOM (pencarian browser & tautan langsung tetap jalan); tab hanya menyaring. --}}
    @php
        $semuaKartu = collect($groups)->flatten(1)->flatMap(fn ($f) => $f['cards']);
        $nSupport = $semuaKartu->filter(fn ($c) => $c['t']->jenjang === 'S')->count();
        $nFitur   = $semuaKartu->filter(fn ($c) => in_array($c['t']->jenjang, ['M', 'L'], true))->count();
        $nBaru    = $semuaKartu->filter(fn ($c) => ! $c['t']->jenjang)->count();
        $jenisAwal = $filter['jenjang'] === 'S' ? 'support' : 'fitur';
    @endphp
    <div class="itk-tabjenis" id="itkTabJenis" data-jenis-awal="{{ $jenisAwal }}">
        {{-- Badge = jumlah kartu yang BENAR-BENAR tampil di tab itu (jenisnya + yang belum
             ditriase). Karena itu 20 + 13 ≠ 26: yang belum ditriase dihitung di keduanya —
             dan catatan di sebelah kanan mengatakannya, bukan dibiarkan jadi teka-teki. --}}
        <button type="button" class="itk-tj" data-jenis="fitur">
            <b>Feature / Modul Request</b>
            <span>M fitur (5 fase) · L modul (7 fase)</span>
            <i class="num">{{ $nFitur + $nBaru }}</i>
        </button>
        <button type="button" class="itk-tj" data-jenis="support">
            <b>Support Request</b>
            <span>S — bug, insiden, tweak (2 fase)</span>
            <i class="num">{{ $nSupport + $nBaru }}</i>
        </button>
        @if ($nBaru)
            <span class="itk-tj-note"><i class="fa fa-info-circle" aria-hidden="true"></i>
                {{ $nBaru }} belum ditriase ({{ $nFitur }} fitur/modul · {{ $nSupport }} support sudah pasti)
                — yang belum ditriase ikut dihitung di <strong>kedua</strong> tab sampai jenjangnya ditetapkan.</span>
        @endif
    </div>

    <div class="itk-board" id="itkBoard">
        @foreach ($groups as $nama => $fases)
            <div class="itk-kolom" data-kolom>
                <div class="itk-kolom-hd"><b>{{ $nama }}</b>
                    <span class="n num" data-kolom-n>{{ collect($fases)->sum(fn ($f) => $f['cards']->count()) }}</span></div>

                {{-- >1 tahap → TAB, bukan bertumpuk: Development & Uji Internal (dan UAT ·
                     Go Live · Evaluasi) tak perlu digulir sampai bawah. --}}
                @if (count($fases) > 1)
                    <div class="itk-fasetab" data-fasetab>
                        @foreach ($fases as $i => $f)
                            <button type="button" class="itk-ft" data-ft="{{ $i }}">
                                {{ $f['label'] }} <i class="n num" data-ft-n>{{ $f['cards']->count() }}</i></button>
                        @endforeach
                    </div>
                @endif

                @foreach ($fases as $i => $f)
                    <div class="itk-fase-panel" data-fp="{{ $i }}">
                        @if (count($fases) === 1)
                            <div class="itk-fase-hd {{ $f['cards']->isEmpty() ? 'kosong' : '' }}">
                                {{ $f['label'] }} <span class="n num" data-ft-n>{{ $f['cards']->count() }}</span></div>
                        @endif
                    @foreach ($f['cards'] as $c)
                        @php
                            $t = $c['t']; $w = $c['wait'];
                            $jenisKartu = $t->jenjang === 'S' ? 'support' : ($t->jenjang ? 'fitur' : 'baru');
                        @endphp
                        {{-- div ber-onclick, bukan <a>: kartu memuat form/tombol aksi
                             dan form di dalam anchor itu HTML tidak valid --}}
                        <div class="itk-card" style="cursor:pointer" data-kartu data-jenis="{{ $jenisKartu }}"
                             onclick="window.location='{{ route('it-tracker.show', $t->id) }}'">
                            <div class="itk-card-top">
                                <span class="itk-no num">{{ $t->nomor }}</span>
                                <span class="itk-src {{ $t->sumber }}">
                                    @if ($t->sumber === 'inisiatif') INISIATIF IT
                                    @elseif ($t->sumber === 'insiden') INSIDEN
                                    @else {{ strtoupper($t->pemohon->nama ?? 'PERMINTAAN') }}
                                    @endif
                                </span>
                                <span class="itk-jenjang {{ $t->jenjang ?? '' }}">{{ $t->jenjang ?? '?' }}</span>
                            </div>
                            <div class="itk-card-title">{{ $t->judul }}</div>
                            <div class="itk-card-meta">
                                <span class="itk-prog num">{{ $c['prog']['label'] ?? 'Menunggu triase' }}</span>
                                @if ($t->prioritas_it)<span class="itk-prio {{ $t->prioritas_it }}">{{ $t->prioritas_it }}</span>@endif
                                @if ($c['anak'])<span class="itk-anak">{{ $c['anak'] }} fitur di dalam</span>@endif
                                {{-- Ronde 2: ketergantungan yang belum selesai & tiket asal --}}
                                @if ($c['deps'])<span class="itk-dep-chip num" title="menunggu tiket lain"><i class="fa fa-link" aria-hidden="true"></i> menunggu {{ implode(', ', $c['deps']) }}</span>@endif
                                @if ($c['parent'])<span class="itk-parent-chip num" title="lahir dari {{ $c['parent']['id'] }} · {{ $c['parent']['via'] }}">↳ dari {{ $c['parent']['id'] }}</span>@endif
                                @if (! empty($c['diteruskan']))<span class="itk-lewat-chip" title="Diteruskan {{ $c['diteruskan']['oleh'] }} tanpa paraf Business Owner · {{ $c['diteruskan']['alasan'] }}"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> tanpa paraf BO</span>@endif
                            </div>
                            <div class="itk-wait {{ \App\Services\ItTracker\PhaseEngine::waitCls($w, $t->fase) }}">
                                <i></i>
                                <span class="num">
                                    @if ($t->fase === 'ANTRE') Antre di belakang {{ $c['antre'] }} item
                                    @else {{ $w['label'] }} · {{ $w['days'] }} hr
                                    @endif
                                </span>
                            </div>
                            @if ($c['tombol'])
                                @if ($c['tombol']['jenis'] === 'triase')
                                    <button type="button" class="itk-btn itk-btn-grafit itk-card-btn"
                                        onclick="event.stopPropagation(); itkBukaTriase('{{ $t->id }}', '{{ $t->nomor }}', @js($t->judul), @js($t->prioritas_pemohon))">
                                        {{ $c['tombol']['label'] }}</button>
                                @else
                                    <form method="post" action="{{ route('it-tracker.maju', $t->id) }}"
                                          onclick="event.stopPropagation()">
                                        @csrf
                                        <button type="submit" class="itk-btn itk-btn-grafit itk-card-btn">{{ $c['tombol']['label'] }}</button>
                                    </form>
                                @endif
                            @elseif ($c['blokir'])
                                <div class="itk-blokir">{{ $c['blokir'] }}</div>
                            @endif
                        </div>
                    @endforeach
                        <div class="itk-fase-kosong" data-fase-kosong hidden>Tidak ada tiket di tahap ini.</div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="itk-tab-kosong" id="itkTabKosong" hidden></div>

    {{-- Parkiran: ditunda tetap tampil dengan alasannya — ditunda bukan berarti hilang.
         Ketiga statusnya BERTAB (bukan satu tumpukan campur), dan ikut tab jenis
         permintaan di atas: papan dan parkiran tak boleh bercerita beda. --}}
    @if ($parkir->isNotEmpty())
        @php $urutStatus = ['DITUNDA' => 'Ditunda', 'DITOLAK' => 'Ditolak', 'SELESAI' => 'Selesai']; @endphp
        <div id="itk-parkiran" style="margin-top:16px">
            <div class="itk-parkir-bar">
                <b>PARKIRAN</b>
                <div class="itk-fasetab" data-parkirtab>
                    @foreach ($urutStatus as $kode => $label)
                        <button type="button" class="itk-ft" data-pk="{{ $kode }}">
                            {{ $label }} <i class="n num" data-pk-n>{{ $parkir->where('fase', $kode)->count() }}</i></button>
                    @endforeach
                </div>
            </div>
            @foreach ($urutStatus as $kode => $label)
                <div class="itk-parkir-panel" data-pkp="{{ $kode }}">
                    <div class="itk-parkir">
                        @foreach ($parkir->where('fase', $kode) as $p)
                            @php $jp = $p->jenjang === 'S' ? 'support' : ($p->jenjang ? 'fitur' : 'baru'); @endphp
                            <a class="itk-parkir-card" data-parkir data-jenis="{{ $jp }}" href="{{ route('it-tracker.show', $p->id) }}">
                                <div class="itk-card-top">
                                    <span class="itk-no num">{{ $p->nomor }}</span>
                                    <span class="itk-st {{ $p->fase }}">{{ $p->fase }}</span>
                                    <span class="itk-jenjang {{ $p->jenjang ?? '' }}">{{ $p->jenjang ?? '?' }}</span>
                                </div>
                                <div class="itk-card-title" style="color:#5A3A22">{{ $p->judul }}</div>
                                @if ($p->alasan_parkir)
                                    <div style="font-size:12px;color:#7A5B43;margin-top:5px;line-height:1.45">{{ $p->alasan_parkir }}</div>
                                @endif
                            </a>
                        @endforeach
                        <div class="itk-fase-kosong" data-pk-kosong hidden>Tidak ada tiket {{ strtolower($label) }} di jenis permintaan ini.</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script>
    (function () {
        // Tab jenis permintaan + tab tahap di dalam kolom. Semua kartu tetap di DOM;
        // yang berubah cuma yang terlihat, jadi hitungan di kepala kolom & tab
        // DIHITUNG ULANG tiap ganti tab — bukan angka statis yang lekas berbohong.
        var bar = document.getElementById('itkTabJenis'), board = document.getElementById('itkBoard');
        if (!bar || !board) return;
        var kosongPapan = document.getElementById('itkTabKosong');
        var cocok = function (kartu, jenis) {
            var j = kartu.getAttribute('data-jenis');
            return j === 'baru' || j === jenis;              // belum ditriase → tampil di kedua tab
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
                    var kosong = panel.querySelector('[data-fase-kosong]');
                    if (kosong) kosong.hidden = n !== 0;
                    var badge = panel.querySelector('[data-ft-n]');
                    if (badge) badge.textContent = n;
                    var tombol = kol.querySelector('[data-ft="' + i + '"]');
                    if (tombol) {
                        tombol.querySelector('[data-ft-n]').textContent = n;
                        tombol.classList.toggle('kosong', n === 0);
                    }
                });
                kol.querySelector('[data-kolom-n]').textContent = totalKol;
                // Support = jalur 2 fase; kolom yang memang tak dilewatinya disembunyikan
                // (tapi hanya kalau benar-benar kosong — tiket nyasar tetap terlihat).
                kol.hidden = (jenis === 'support' && totalKol === 0);
                totalPapan += totalKol;

                // tab tahap: pilih tahap pertama yang ada isinya, kalau tak ada tahap pertama
                var tabs = kol.querySelectorAll('[data-ft]');
                if (tabs.length) {
                    var aktif = kol.querySelector('[data-ft].aktif'), pilih = -1;
                    if (aktif && +aktif.parentElement.querySelector('[data-ft="' + aktif.getAttribute('data-ft') + '"] [data-ft-n]').textContent > 0) {
                        pilih = +aktif.getAttribute('data-ft');
                    }
                    if (pilih < 0) {
                        tabs.forEach(function (b) {
                            var idx = +b.getAttribute('data-ft');
                            if (pilih < 0 && +b.querySelector('[data-ft-n]').textContent > 0) pilih = idx;
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
                    ? 'Tidak ada Support Request aktif. Bug & insiden yang sudah selesai ada di parkiran bawah.'
                    : 'Tidak ada Feature / Modul Request aktif.';
            }
            if (simpanUrl && window.history.replaceState) {
                var u = new URL(window.location.href);
                u.searchParams.set('jenis', jenis);
                u.searchParams.delete('jenjang');
                window.history.replaceState({}, '', u);
            }
        }

        // Parkiran: tiga status jadi tab, isinya ikut tab jenis permintaan.
        function terapParkir(jenis) {
            var wrap = document.getElementById('itk-parkiran');
            if (!wrap) return;
            var total = 0, pertamaBerisi = null, aktifKini = wrap.querySelector('[data-pk].aktif');
            wrap.querySelectorAll('.itk-parkir-panel').forEach(function (panel) {
                var kode = panel.getAttribute('data-pkp'), n = 0;
                panel.querySelectorAll('[data-parkir]').forEach(function (k) {
                    var tampil = cocok(k, jenis);
                    k.hidden = ! tampil;
                    if (tampil) n++;
                });
                total += n;
                var kosong = panel.querySelector('[data-pk-kosong]');
                if (kosong) kosong.hidden = n !== 0;
                var tombol = wrap.querySelector('[data-pk="' + kode + '"]');
                if (tombol) {
                    tombol.querySelector('[data-pk-n]').textContent = n;
                    tombol.classList.toggle('kosong', n === 0);
                }
                if (n > 0 && pertamaBerisi === null) pertamaBerisi = kode;
            });
            // pertahankan tab yang sedang dibuka kalau masih ada isinya
            var pilih = pertamaBerisi || 'DITUNDA';
            if (aktifKini) {
                var kodeAktif = aktifKini.getAttribute('data-pk');
                if (+wrap.querySelector('[data-pk="' + kodeAktif + '"] [data-pk-n]').textContent > 0) pilih = kodeAktif;
            }
            pilihParkir(pilih);
            wrap.hidden = total === 0;
            var chip = document.getElementById('itkChipParkir');
            if (chip) {
                chip.querySelector('[data-parkir-total]').textContent = total;
                chip.hidden = total === 0;
            }
        }

        function pilihParkir(kode) {
            var wrap = document.getElementById('itk-parkiran');
            wrap.querySelectorAll('[data-pk]').forEach(function (b) { b.classList.toggle('aktif', b.getAttribute('data-pk') === kode); });
            wrap.querySelectorAll('.itk-parkir-panel').forEach(function (p) { p.hidden = p.getAttribute('data-pkp') !== kode; });
        }

        function pilihTahap(kol, idx) {
            kol.querySelectorAll('[data-ft]').forEach(function (b) {
                b.classList.toggle('aktif', +b.getAttribute('data-ft') === idx);
            });
            kol.querySelectorAll('.itk-fase-panel').forEach(function (p) {
                p.hidden = +p.getAttribute('data-fp') !== idx;
            });
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

        var dariUrl = new URL(window.location.href).searchParams.get('jenis');
        terap(dariUrl === 'support' || dariUrl === 'fitur' ? dariUrl : bar.getAttribute('data-jenis-awal'), false);
    })();
    </script>

    <div class="itk-legenda">Legenda badge: <strong>Fase 1</strong> = formulir masuk + triase + papan ini ·
        <strong>Fase 2</strong> = tujuh fase + gerbang antar-fase · <strong>Fase 3</strong> = sambungan Project
        Tracker + evaluasi terjadwal. Warna strip menunggu memanas hanya bila penunggunya
        <strong>di luar IT</strong> (≥3 hari kuning, ≥7 merah).</div>
</div>

{{-- Modal triase (Head IT) — jenjang menentukan fase yang dilewati --}}
@if ($peran === 'head_it')
<div class="itk-modal-scrim" id="itkModalTriase" onclick="if (event.target === this) itkTutupTriase()">
    <div class="itk-modal">
        <div class="itk-modal-hd"><b>TRIASE</b><span class="itk-badge-fase">FASE 1</span>
            <button type="button" onclick="itkTutupTriase()">✕</button></div>
        <div class="itk-modal-body">
            <div>
                <span class="itk-no num" id="itkTriaseNo"></span>
                <div style="font-size:15px;font-weight:800" id="itkTriaseJudul"></div>
                <div class="itk-hint" id="itkTriasePrioP"></div>
            </div>
            <form method="post" id="itkTriaseForm">
                @csrf
                <label class="itk-lbl">JENJANG — MENENTUKAN FASE YANG DILEWATI</label>
                <div style="display:flex;gap:7px;margin-bottom:13px">
                    <label><input class="itk-radio" type="radio" name="jenjang" value="S" required><span><b>S</b> · bug/tweak — 2 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="M"><span><b>M</b> · fitur — 5 fase</span></label>
                    <label><input class="itk-radio" type="radio" name="jenjang" value="L"><span><b>L</b> · modul — 7 fase</span></label>
                </div>
                <div class="itk-hint" style="margin:-8px 0 13px">Jenjang juga menentukan papannya:
                    <strong>S → Support Request</strong>, <strong>M/L → Feature / Modul Request</strong>.</div>
                @if ($picOpsi->count() > 1)
                    <div style="margin-bottom:13px">
                        <label class="itk-lbl">DIKERJAKAN SIAPA (PIC)</label>
                        <div style="display:flex;gap:7px;flex-wrap:wrap">
                            @foreach ($picOpsi as $p)
                                <label><input class="itk-radio" type="radio" name="pic_user_id" value="{{ $p->id }}"><span>{{ $p->nama }}</span></label>
                            @endforeach
                        </div>
                        <div class="itk-hint">Opsional — boleh ditentukan nanti; tanpa PIC, yang pertama menarik tiketnya yang jadi pengerja.</div>
                    </div>
                @endif
                <div class="itk-grid2" style="margin-bottom:13px">
                    <div>
                        <label class="itk-lbl">BOBOT (1–5)</label>
                        <div style="display:flex;gap:6px">
                            @foreach (range(1, 5) as $b)
                                <label><input class="itk-radio" type="radio" name="bobot" value="{{ $b }}" required><span>{{ $b }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label class="itk-lbl">PRIORITAS IT</label>
                        <div style="display:flex;gap:6px">
                            @foreach (['P1', 'P2', 'P3'] as $p)
                                <label><input class="itk-radio" type="radio" name="prioritas_it" value="{{ $p }}" required><span>{{ $p }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button type="submit" class="itk-btn itk-btn-grafit" style="width:100%">Simpan triase → Antre</button>
            </form>
            <div class="itk-sekat"></div>
            <form method="post" id="itkParkirForm">
                @csrf
                <label class="itk-lbl">ATAU TUNDA / TOLAK — ALASANNYA WAJIB</label>
                <input class="itk-in" type="text" name="alasan" required maxlength="500"
                       placeholder="mis. ditunda sampai komisi 70% selesai">
                <div style="display:flex;gap:8px;margin-top:9px">
                    <button type="submit" name="ke" value="DITUNDA" class="itk-btn itk-btn-oranye">Tunda</button>
                    <button type="submit" name="ke" value="DITOLAK" class="itk-btn itk-btn-putih">Tolak…</button>
                </div>
                <div class="itk-hint">Ditunda tetap tampil di parkiran papan dengan alasannya — ditunda bukan berarti hilang.</div>
            </form>
        </div>
    </div>
</div>
<script>
function itkBukaTriase(id, nomor, judul, prioP) {
    document.getElementById('itkTriaseNo').textContent = nomor;
    document.getElementById('itkTriaseJudul').textContent = judul;
    document.getElementById('itkTriasePrioP').textContent = prioP
        ? 'Prioritas menurut pemohon: ' + prioP + ' — masukan, bukan keputusan. Kolomnya terpisah.'
        : '';
    document.getElementById('itkTriaseForm').action = '{{ url('it-tracker/tiket') }}/' + id + '/triase';
    document.getElementById('itkParkirForm').action = '{{ url('it-tracker/tiket') }}/' + id + '/parkir';
    document.getElementById('itkModalTriase').classList.add('buka');
}
function itkTutupTriase() {
    document.getElementById('itkModalTriase').classList.remove('buka');
}
</script>
@endif
@endsection
