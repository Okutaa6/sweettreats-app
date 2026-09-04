{{--
    Katalog Modul — "apa saja yang kami punya" (rencana §14.24–14.25).

    Halaman SAUDARA papan antrean, bukan tab keempat: papan menjawab apa yang
    sedang bergerak, katalog menjawab apa yang dimiliki.

    ⛔ SALURAN WARNA PAPAN TIDAK DIPAKAI DI SINI untuk status. Panas = isi strip ·
    oranye tepi = unit usaha · putus-merah = pelewatan paraf · teks kuning =
    catatan basi — semuanya sudah punya arti di papan. Status memakai LABEL
    BERTULIS + tebal-pudarnya nama. Tak ada bar persen, skor, atau grafik:
    status bukan progres. Warna panas HANYA muncul di panel, pada strip
    "menunggu siapa" tiap tiket — di situ artinya memang sama.

    Tiga tampilan (Daftar · Kartu · Matriks) × dua lensa (departemen ⇄ jenis),
    semuanya lewat query string supaya bisa ditautkan dan di-bookmark.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    use App\Services\ItTracker\KatalogModul;
    use App\Services\ItTracker\PhaseEngine;

    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
    $inisial  = mb_strtoupper(mb_substr((string) session('name'), 0, 1)) ?: 'IT';
    $link = fn (array $ubah) => route('it-tracker.katalog', array_filter(array_merge(
        ['tampilan' => $tampilan, 'lensa' => $lensa, 'jenis' => $jenisSel, 'q' => $q !== '' ? $q : null], $ubah)));

    // "sejak" yang tak bisa dibuktikan ditulis apa adanya — bukan tanggal karangan,
    // bukan kosong yang tampak seperti data hilang.
    $sejakTxt = function ($m) {
        if ($m->sejak) return $m->sejak->translatedFormat($m->sejak_terbukti ? 'j M Y' : 'M Y');
        return $m->status === 'hidup' ? 'belum terbukti' : '—';
    };
    $sejakTip = function ($m) {
        if ($m->sejak && $m->sejak_terbukti) return 'Data paling awal yang bisa dibuktikan — bukan tanggal rilis resmi';
        if ($m->sejak) return 'Perkiraan bulan; tak ada tabel yang membuktikan hari pertamanya';
        return $m->status === 'hidup'
            ? 'Modul ini hidup, tapi tak ada tabel yang bisa membuktikan kapan data pertama masuk — bukan data hilang'
            : 'Belum ada';
    };
    $fiturTxt = fn ($m) => count($fitur[$m->id]) ? count($fitur[$m->id]) . ' fitur' : ($m->access_id ? '—' : 'tanpa menu');
    $utangTxt = function ($m) use ($cacah) {
        $c = $cacah[$m->id] ?? ['terbuka' => 0, 'ditunda' => 0];
        if (! $c['terbuka']) return null;
        return $c['terbuka'] . ' tiket' . ($c['ditunda'] ? ' · ' . $c['ditunda'] . ' tunda' : '');
    };
    // angka tiket bisa DIKLIK → papan yang sudah tersaring ke modul ini
    $kePapan = fn ($m) => route('it-tracker.index', ['modul' => $m->id]);
@endphp
<div class="itk-surface itk-katalog">

    <div class="itk-topbar">
        <div>
            <h1>Katalog Modul</h1>
        </div>
        <span class="itk-badge-zona">ZONA IT</span>
        <span class="sub">Apa saja yang kami punya — siapa pemiliknya, sejak kapan, apa yang sedang terjadi padanya</span>
        <div class="itk-topbar-nav">
            {{-- tautan dua arah: papan ⇄ katalog, dua tampilan atas satu data --}}
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.index') }}" title="Halaman aksi: triase, gerbang, UAT">Papan Antrean</a>
            <span class="itk-btn itk-btn-grafit" style="cursor:default">Katalog Modul</span>
            <div class="itk-avatar" title="{{ session('name') }} · {{ session('group_name') }}">{{ $inisial }}</div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Jawaban lima detik --}}
    <div class="itk-beban" style="margin-bottom:13px">
        <div class="itk-beban-bar"><b>APA SAJA YANG KAMI PUNYA</b>
            <span class="num" style="font-size:11px;font-weight:700;color:#B9B9B2;margin-left:auto">{{ $jumlah['dept'] }} departemen · {{ $jumlah['modul'] }} entri · {{ $jumlah['fitur'] }} halaman bisa dibuka</span></div>
        <div class="itk-kat-tops">
            <div><span class="mark hidup"></span><span class="num n">{{ $jumlah['hidup'] }}</span>
                <div class="lbl">modul hidup</div><div class="sub">dipakai orang hari ini</div></div>
            <div><span class="mark dibangun"></span><span class="num n">{{ $jumlah['dibangun'] }}</span>
                <div class="lbl">sedang dibangun</div><div class="sub">lulus dev, prod belum</div></div>
            <div><span class="mark rencana"></span><span class="num n">{{ $jumlah['rencana'] }}</span>
                <div class="lbl">direncanakan</div><div class="sub">disepakati, belum ada</div></div>
            <div class="akhir"><span class="mark tiket"></span><span class="num n">{{ $jumlah['berjalan'] }}</span>
                <div class="lbl">tiket berjalan</div><div class="sub">{{ $jumlah['bertaut'] }} utang terbuka — rincian di tiap modul</div></div>
        </div>
    </div>

    {{-- Toolbar: lensa · tampilan · penyaring jenis · cari · entri modul --}}
    <div class="itk-kat-bar">
        <div class="grup">
            <span class="lbl">KELOMPOKKAN</span>
            <a class="seg {{ $lensa === 'dept' ? 'aktif' : '' }}" href="{{ $link(['lensa' => 'dept', 'jenis' => null]) }}">Per departemen</a>
            <a class="seg {{ $lensa === 'jenis' ? 'aktif' : '' }}" href="{{ $link(['lensa' => 'jenis']) }}">Per jenis</a>
        </div>
        <div class="grup">
            <span class="lbl">TAMPILAN</span>
            <a class="seg {{ $tampilan === 'daftar' ? 'aktif' : '' }}" href="{{ $link(['tampilan' => 'daftar']) }}" title="Baris berkolom — paling padat informasi">☰ Daftar</a>
            <a class="seg {{ $tampilan === 'kartu' ? 'aktif' : '' }}" href="{{ $link(['tampilan' => 'kartu']) }}" title="Kartu per modul — enak dipindai">▤ Kartu</a>
            <a class="seg {{ $tampilan === 'matriks' ? 'aktif' : '' }}" href="{{ $link(['tampilan' => 'matriks']) }}" title="{{ $jumlah['modul'] }} modul sekali lihat: departemen × jenis">⊞ Matriks</a>
        </div>
        @if ($lensa === 'jenis' && $tampilan !== 'matriks')
            <div class="grup polos">
                @foreach (KatalogModul::JENIS as $j)
                    @php $n = $semua->where('jenis', $j)->count(); @endphp
                    <a class="itk-fchip {{ $jenisSel === $j ? 'aktif' : '' }}" href="{{ $link(['jenis' => $jenisSel === $j ? null : $j]) }}">{{ $j }} · {{ $n }}{{ $jenisSel === $j ? '  ✕' : '' }}</a>
                @endforeach
            </div>
        @endif
        <form method="get" action="{{ route('it-tracker.katalog') }}" class="itk-cari" style="margin-left:auto">
            <input type="hidden" name="tampilan" value="{{ $tampilan }}">
            <input type="hidden" name="lensa" value="{{ $lensa }}">
            @if ($jenisSel)<input type="hidden" name="jenis" value="{{ $jenisSel }}">@endif
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.6" stroke="#B79A82" stroke-width="1.5"/><path d="M10.6 10.6 14 14" stroke="#B79A82" stroke-width="1.5" stroke-linecap="round"/></svg>
            <input type="text" name="q" value="{{ $q }}" placeholder="cari modul / fitur…" style="width:200px">
        </form>
        @if ($peran === 'head_it')
            <button type="button" class="itk-btn itk-btn-grafit" onclick="itkBukaEntri()">＋ Entri modul</button>
        @endif
    </div>

    {{-- Legenda status: label bertulis, BUKAN saluran warna milik papan --}}
    <div class="itk-legenda-warna itk-kat-legenda">
        <b>STATUS</b>
        @foreach (KatalogModul::STATUS as $k => $s)
            <span><i class="itk-st-chip {{ $k }}">{{ $s['nama'] }}</i> {{ $s['sub'] }}</span>
        @endforeach
        <span class="ket">Label bertulis + tebal-pudarnya nama. Warna panas · oranye tepi · putus-merah · teks kuning tetap milik papan. Tak ada persen: status bukan progres.</span>
    </div>

    @if ($q !== '' && ! $groups && $tampilan !== 'matriks')
        <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:32px;text-align:center">
            <div style="font-size:14px;font-weight:800;color:#5A3A22">Tidak ada modul yang cocok dengan “{{ $q }}”</div>
            <div style="font-size:12.5px;color:#9A7256;margin-top:5px">Pencarian menyentuh nama modul, departemen, jenis, dan nama fiturnya.
                <a href="{{ $link(['q' => null]) }}">Hapus pencarian</a></div>
        </div>
    @endif

    {{-- ═══ MATRIKS: 33 modul sekali lihat (untuk yang datang sebulan sekali) ═══ --}}
    @if ($tampilan === 'matriks')
        <div class="itk-mx">
            <div class="itk-mx-grid">
                <span class="hd">DEPARTEMEN</span>
                @foreach (KatalogModul::JENIS as $j)
                    <span class="hd">{{ $j === 'Laporan & Dashboard' ? 'Laporan & Dasbor' : $j }} <i class="num">{{ $semua->where('jenis', $j)->count() }}</i></span>
                @endforeach
                <span class="hd kanan">TIKET</span>
                @foreach ($matriks as $r)
                    <span class="dept">
                        <b>{{ $r['nama'] }}</b>
                        <i class="num">{{ $r['nHidup'] }} hidup · {{ $r['nEntri'] }} entri</i>
                    </span>
                    @foreach (KatalogModul::JENIS as $j)
                        <span class="sel {{ $jenisSel === $j ? 'sorot' : '' }}">
                            @forelse ($r['sel'][$j] as $m)
                                @php $c = $cacah[$m->id] ?? ['terbuka' => 0]; @endphp
                                <button type="button" class="itk-mchip {{ $m->status }}" data-buka-modul="{{ $m->id }}"
                                    title="{{ $m->nama }} — {{ mb_strtolower(KatalogModul::statusLabel($m->status)) }}{{ $m->sejak ? ' sejak ' . $sejakTxt($m) : '' }}{{ $c['terbuka'] ? ' · ' . $c['terbuka'] . ' tiket terbuka' : '' }}">
                                    {{ $m->nama }}@if ($c['terbuka'])<i class="num">· {{ $c['terbuka'] }}</i>@endif
                                </button>
                            @empty
                                <span class="kosong">·</span>
                            @endforelse
                        </span>
                    @endforeach
                    <span class="tiket num {{ $r['utang'] >= 3 ? 'banyak' : ($r['utang'] ? 'ada' : '') }}">{{ $r['utang'] ?: '—' }}</span>
                @endforeach
            </div>
            <div class="kaki">Klik nama modul untuk membuka ringkasannya — tanpa pindah halaman. Angka kecil di chip = tiket terbuka.</div>
        </div>
    @endif

    {{-- ═══ KARTU ═══ --}}
    @if ($tampilan === 'kartu')
        @foreach ($groups as $g)
            <div class="itk-kat-grup">
                <div class="hd">
                    <b>{{ $g['nama'] }}</b>
                    @if ($lensa === 'jenis')<span class="sub">{{ $g['nEntri'] }} entri lintas {{ $g['nDept'] }} departemen</span>@endif
                    <span class="angka">
                        <span class="num"><strong>{{ $g['nHidup'] }}</strong> hidup</span>
                        <span class="num berjalan"><strong>{{ $g['nBerjalan'] }}</strong> berjalan</span>
                        <span class="num utang {{ $g['nUtang'] >= 3 ? 'banyak' : '' }}"><strong>{{ $g['nUtang'] }}</strong> utang</span>
                    </span>
                </div>
                @if ($g['kosong'] && $q === '')
                    @include('it-tracker._katalog-kosong', ['grup' => $g])
                @endif
                <div class="kartu">
                    @foreach ($g['rows'] as $m)
                        <div class="itk-kat-card {{ $m->status }}" data-buka-modul="{{ $m->id }}">
                            <div class="atas"><i class="itk-st-chip {{ $m->status }}">{{ KatalogModul::statusLabel($m->status) }}</i>
                                <span class="chev">›</span></div>
                            <div class="nama {{ $m->status }}">{{ $m->nama }}</div>
                            <div class="tag">
                                <span class="itk-jenis-chip {{ $lensa === 'jenis' ? 'pemilik' : \Illuminate\Support\Str::slug($m->jenis) }}">{{ $lensa === 'jenis' ? $m->departemen : $m->jenis }}</span>
                                @if (KatalogModul::warisan($m))<span class="itk-warisan">WARISAN · {{ $m->sejak->year }}</span>@endif
                            </div>
                            <div class="kaki">
                                <span class="num sejak {{ $m->sejak ? '' : 'kosong' }}">{{ $sejakTxt($m) }}</span>
                                <span class="num">{{ $fiturTxt($m) }}</span>
                                @if ($utangTxt($m))<span class="num tiket {{ ($cacah[$m->id]['terbuka'] ?? 0) >= 3 ? 'banyak' : '' }}">{{ $utangTxt($m) }}</span>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    {{-- ═══ DAFTAR (bawaan IT — paling padat, dipakai saat triase) ═══ --}}
    @if ($tampilan === 'daftar')
        @foreach ($groups as $g)
            <div class="itk-kat-panel">
                <div class="hd">
                    <b>{{ $g['nama'] }}</b>
                    @if ($lensa === 'jenis')<span class="sub">{{ $g['nEntri'] }} entri lintas {{ $g['nDept'] }} departemen</span>@endif
                    <span class="angka">
                        <span><strong class="num">{{ $g['nHidup'] }}</strong><i>HIDUP</i></span>
                        <span><strong class="num berjalan">{{ $g['nBerjalan'] }}</strong><i>BERJALAN</i></span>
                        <span><strong class="num utang {{ $g['nUtang'] >= 3 ? 'banyak' : '' }}">{{ $g['nUtang'] }}</strong><i>UTANG</i></span>
                    </span>
                </div>
                <div class="itk-kat-row kepala">
                    <span>STATUS</span><span>MODUL</span><span>{{ $lensa === 'jenis' ? 'PEMILIK' : 'JENIS' }}</span>
                    <span>SEJAK</span><span>FITUR</span><span>TIKET TERBUKA</span><span></span>
                </div>
                @if ($g['kosong'] && $q === '')
                    @include('it-tracker._katalog-kosong', ['grup' => $g])
                @endif
                @foreach ($g['rows'] as $m)
                    <div class="itk-kat-row {{ $m->status }}" data-buka-modul="{{ $m->id }}">
                        <span><i class="itk-st-chip {{ $m->status }}">{{ KatalogModul::statusLabel($m->status) }}</i></span>
                        <span class="modul">
                            <b class="{{ $m->status }}">{{ $m->nama }}</b>
                            @if (KatalogModul::warisan($m) || (! $m->access_id && $m->status === 'hidup'))
                                <span class="tag">
                                    @if (KatalogModul::warisan($m))<span class="itk-warisan">WARISAN · {{ $m->sejak->year }}</span>@endif
                                    @if (! $m->access_id && $m->status === 'hidup')<span class="tanpa-menu">tanpa menu — berjalan di belakang</span>@endif
                                </span>
                            @endif
                        </span>
                        <span><i class="itk-jenis-chip {{ $lensa === 'jenis' ? 'pemilik' : \Illuminate\Support\Str::slug($m->jenis) }}">{{ $lensa === 'jenis' ? $m->departemen : $m->jenis }}</i></span>
                        <span class="num sejak {{ $m->sejak ? '' : 'kosong' }}" title="{{ $sejakTip($m) }}">{{ $sejakTxt($m) }}</span>
                        <span class="num fitur">{{ $fiturTxt($m) }}</span>
                        <span>@if ($utangTxt($m))<i class="num tiket {{ ($cacah[$m->id]['terbuka'] ?? 0) >= 3 ? 'banyak' : '' }}">{{ $utangTxt($m) }}</i>@else<i class="nihil">—</i>@endif</span>
                        <span class="chev">›</span>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif

    {{-- Angka HARUS berjumlah: tiket yang tak menempel modul mana pun disebut,
         bukan dihilangkan. Tanpa baris ini angka katalog tak bisa dicocokkan
         dengan papan — dan angka yang tak bisa dicocokkan akan dipercaya
         padahal salah. ⛔ Tak ada tebakan modul dari judul: yang kosong kosong. --}}
    @if ($jumlah['yatim'] > 0 && $q === '')
        <div class="itk-yatim">
            <span class="ikon">?</span>
            <div style="flex:1;min-width:0">
                <div class="hd">
                    <b>Tanpa modul</b>
                    <span class="num pil">{{ $jumlah['yatim'] }} tiket terbuka</span>
                    <span class="num jumlah">{{ $jumlah['bertaut'] }} bertaut + {{ $jumlah['yatim'] }} tanpa modul = {{ $jumlah['terbuka'] }} tiket terbuka di papan</span>
                </div>
                <div class="ket">Belum menempel ke entri katalog mana pun — modulnya diisi Head IT saat triase (atau dari halaman tiketnya).
                    Sengaja tidak ditebak dari judul: tebakan yang meleset tak terlihat siapa pun.</div>
                <div class="daftar">
                    @foreach (array_slice($yatim, 0, 12) as $x)
                        <a class="{{ $x['t']->unit === 'rblas' ? 'rblas' : '' }}" href="{{ route('it-tracker.show', $x['t']->id) }}">
                            {{ '#' . preg_replace('/^IT-\d{4}-/', '', $x['t']->nomor) }} · {{ \Illuminate\Support\Str::limit($x['t']->judul, 46) }}{{ $x['t']->unit === 'rblas' ? ' — RB Las' : '' }}</a>
                    @endforeach
                    @if (count($yatim) > 12)<span class="lagi">+{{ count($yatim) - 12 }} lagi</span>@endif
                </div>
            </div>
        </div>
    @endif

    <div class="itk-legenda">Tiga angka menjawab tiga pertanyaan berbeda: <strong>{{ $jumlah['modul'] }} entri katalog</strong> (apa yang kami punya) ·
        <strong>{{ $jumlah['fitur'] }} fitur</strong> (halaman yang bisa dibuka, diturunkan dari menu — tak pernah basi) ·
        <strong>{{ $jumlah['terbuka'] }} tiket terbuka</strong> (apa yang sedang terjadi). Fitur & angka tiket tak pernah dirawat manual.</div>

    {{-- ═══ PANEL RINGKASAN per modul — dibaca di sini, tanpa pindah halaman ═══ --}}
    <div class="itk-panel-scrim" id="itkModulScrim" onclick="if (event.target === this) itkTutupModul()">
        @foreach ($semua as $m)
            @php
                $c = $cacah[$m->id] ?? ['terbuka' => 0, 'berjalan' => 0, 'ditunda' => 0];
                $daftarTiket = $tiket[$m->id] ?? [];
            @endphp
            <div class="itk-panel-geser" data-modul-panel="{{ $m->id }}" hidden>
                <div class="kepala">
                    <div class="baris">
                        <i class="itk-st-chip {{ $m->status }}">{{ KatalogModul::statusLabel($m->status) }}</i>
                        <span class="jenis">{{ $m->jenis }}</span>
                        <button type="button" onclick="itkTutupModul()">✕</button>
                    </div>
                    <div class="nama">{{ $m->nama }}</div>
                    <div class="pemilik">Pemilik: {{ $m->departemen }} — satu departemen yang memutuskan &amp; meneken UAT</div>
                </div>
                <div class="isi">
                    <div class="tiga">
                        <div><span class="k">SEJAK</span><span class="v num {{ $m->sejak ? '' : 'kosong' }}">{{ $sejakTxt($m) }}</span></div>
                        <div><span class="k">FITUR</span><span class="v num">{{ $fiturTxt($m) }}</span></div>
                        <div class="utang {{ $c['terbuka'] >= 3 ? 'banyak' : ($c['terbuka'] ? 'ada' : '') }}">
                            <span class="k">UTANG TERBUKA</span><span class="v num">{{ $c['terbuka'] }}</span></div>
                    </div>

                    @if (count($fitur[$m->id]))
                        <div>
                            <div class="itk-lbl" style="margin-bottom:7px">{{ count($fitur[$m->id]) }} HALAMAN YANG BISA DIBUKA — DITURUNKAN DARI MENU</div>
                            <div class="fitur">
                                @foreach ($fitur[$m->id] as $f)<span title="{{ $f['link'] }}">{{ $f['nama'] }}</span>@endforeach
                            </div>
                        </div>
                    @elseif (! $m->access_id)
                        <div class="itk-hint" style="margin:0">Tanpa menu — modul ini berjalan di belakang layar, jadi tak ada halaman yang bisa dibuka. Bukan berarti tak dipakai.</div>
                    @else
                        <div class="itk-hint" style="margin:0">Node menunya ada tapi belum berisi halaman ber-link di lingkungan ini.</div>
                    @endif

                    @if ($c['terbuka'])
                        <div>
                            <div class="itk-lbl" style="display:flex;align-items:center;gap:8px;margin-bottom:7px">
                                {{ $c['terbuka'] }} TIKET TERBUKA — RINGKASANNYA DI SINI
                                <a href="{{ $kePapan($m) }}" style="margin-left:auto;font-size:11px;font-weight:700;color:#3A3A36;white-space:nowrap;text-transform:none;letter-spacing:0">bertindak di papan ›</a>
                            </div>
                            <div class="tiket-list">
                                @foreach ($daftarTiket as $x)
                                    @php $t = $x['t']; $w = $x['wait']; @endphp
                                    <a class="tiket {{ $t->fase === 'DITUNDA' ? 'ditunda' : '' }}" href="{{ route('it-tracker.show', $t->id) }}">
                                        <div class="atas">
                                            <span class="num no">{{ '#' . preg_replace('/^IT-\d{4}-/', '', $t->nomor) }}</span>
                                            <span class="fase {{ PhaseEngine::waitCls($w, $t->fase) }}">
                                                @if ($t->fase === 'DITUNDA') Ditunda
                                                @elseif ($t->fase === 'TRIASE') Belum ditriase
                                                @elseif ($t->fase === 'ANTRE') Antre
                                                @else {{ PhaseEngine::phShort(PhaseEngine::phaseOf($t->fase)) }}
                                                @endif
                                            </span>
                                            @if ($t->jenjang)<span class="jenjang">Jenjang {{ $t->jenjang }}</span>@endif
                                            @if ($t->pic)<span class="pic">{{ $t->pic->nama }}</span>@endif
                                        </div>
                                        <div class="judul">{{ $t->judul }}</div>
                                        {{-- warna panas dipakai ulang DI SINI karena artinya sama: menunggu di luar IT --}}
                                        <span class="itk-wait {{ PhaseEngine::waitCls($w, $t->fase) }}"><i></i>
                                            <span class="num">
                                                @if ($t->fase === 'DITUNDA') {{ $t->alasan_parkir ?: 'Ditunda — tetap tercatat' }}
                                                @else {{ $w['label'] }} · {{ $w['days'] }} hr
                                                @endif
                                            </span></span>
                                        @if ($t->kemajuan)
                                            @php $hariKm = $t->kemajuan_at ? (int) $t->kemajuan_at->diffInDays(now()) : null; @endphp
                                            <div class="itk-kemajuan {{ $hariKm !== null && $hariKm >= 7 ? 'basi' : '' }}">
                                                <span class="txt">{{ $t->kemajuan }}</span>
                                                @if ($hariKm !== null)<span class="num">{{ $hariKm >= 7 ? $hariKm . ' hr · basi' : ($hariKm === 0 ? 'hari ini' : $hariKm . ' hr') }}</span>@endif
                                            </div>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="itk-kosong-hijau">Tak ada tiket terbuka — modul ini sedang tidak diapa-apakan.</div>
                    @endif

                    @if ($m->catatan || (! $m->sejak && $m->status === 'hidup'))
                        <div class="catatan">
                            @if (! $m->sejak && $m->status === 'hidup')
                                <div class="itk-nota bukti">Tak ada tabel yang bisa membuktikan kapan data pertama masuk — modulnya tetap hidup.</div>
                            @endif
                            @if ($m->catatan)
                                <div class="itk-nota {{ KatalogModul::warisan($m) ? 'warisan' : '' }}">{{ $m->catatan }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Dialog entri modul — Head IT saja (satu-satunya penulisan katalog dari layar) --}}
@if ($peran === 'head_it')
<div class="itk-modal-scrim" id="itkModalEntri" onclick="if (event.target === this) itkTutupEntri()">
    <div class="itk-modal">
        <div class="itk-modal-hd"><b>ENTRI MODUL BARU — DIISI HEAD IT</b>
            <button type="button" onclick="itkTutupEntri()">✕</button></div>
        <form method="post" action="{{ route('it-tracker.katalog.store') }}">
            @csrf
            <div class="itk-modal-body">
                <div>
                    <label class="itk-lbl">NAMA MODUL</label>
                    <input class="itk-in" type="text" name="nama" required maxlength="120"
                           placeholder="yang disebut departemen kalau ditanya “sistem apa yang kalian punya?”">
                </div>
                <div class="itk-grid2">
                    <div>
                        <label class="itk-lbl">PEMILIK — SATU DEPARTEMEN</label>
                        <select name="departemen" required>
                            @foreach (KatalogModul::DEPARTEMEN as $d)<option>{{ $d }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="itk-lbl">JENIS PEKERJAAN</label>
                        <select name="jenis" required>
                            @foreach (KatalogModul::JENIS as $j)<option>{{ $j }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="itk-lbl">STATUS</label>
                    <div style="display:flex;gap:7px">
                        @foreach (KatalogModul::STATUS as $k => $s)
                            <label style="flex:1"><input class="itk-radio" type="radio" name="status" value="{{ $k }}" {{ $k === 'rencana' ? 'checked' : '' }} required>
                                <span style="display:block;text-align:center">{{ $s['nama'] }}<em style="display:block;font-size:10px;font-weight:600;opacity:.8;font-style:normal">{{ $s['sub'] }}</em></span></label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="itk-lbl">CATATAN <span style="font-weight:600;color:#8A857C">(opsional — bukti data, batas, peringatan)</span></label>
                    <textarea name="catatan" rows="2" maxlength="2000" placeholder="mis. lahir dari tiket IT-2026-031; belum ada tabel yang membuktikan tanggalnya"></textarea>
                </div>
                <div style="display:flex;align-items:center;gap:12px;border-top:1px solid #F1E5D5;padding-top:13px">
                    <button type="submit" class="itk-btn itk-btn-grafit">Tambah entri</button>
                    <span class="itk-hint" style="margin:0">Hanya entri modul yang ditulis tangan — <strong>fitur diturunkan dari menu</strong>, angka tiket dari tabel tiket.
                        Tanggal “sejak” sengaja tak ditanyakan: ia harus bisa dibuktikan.</span>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<script>
(function () {
    var scrim = document.getElementById('itkModulScrim');
    function tutup() {
        scrim.classList.remove('buka');
        scrim.querySelectorAll('[data-modul-panel]').forEach(function (p) { p.hidden = true; });
        if (window.history.replaceState) {
            var u = new URL(window.location.href); u.searchParams.delete('modul');
            window.history.replaceState({}, '', u);
        }
    }
    function buka(id) {
        var p = scrim.querySelector('[data-modul-panel="' + id + '"]');
        if (!p) return;
        scrim.querySelectorAll('[data-modul-panel]').forEach(function (x) { x.hidden = x !== p; });
        scrim.classList.add('buka');
        if (window.history.replaceState) {
            var u = new URL(window.location.href); u.searchParams.set('modul', id);
            window.history.replaceState({}, '', u);
        }
    }
    window.itkTutupModul = tutup;
    document.querySelectorAll('[data-buka-modul]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (e.target.closest('a')) return;   // tautan di dalam baris tetap tautan
            buka(el.getAttribute('data-buka-modul'));
        });
    });
    // Esc menutup panel & dialog (celah yang ditandai paket desain sendiri)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (scrim.classList.contains('buka')) tutup();
        var dlg = document.getElementById('itkModalEntri');
        if (dlg && dlg.classList.contains('buka')) dlg.classList.remove('buka');
    });
    // ?modul=ID → panel langsung terbuka (tautan dari mana pun tetap bekerja)
    var awal = new URL(window.location.href).searchParams.get('modul');
    if (awal) buka(awal);
})();
function itkBukaEntri() { document.getElementById('itkModalEntri').classList.add('buka'); }
function itkTutupEntri() { document.getElementById('itkModalEntri').classList.remove('buka'); }
</script>
@endsection
