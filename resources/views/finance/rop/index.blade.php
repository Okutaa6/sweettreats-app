{{--
    Antrian Pengajuan — layar kerja Finance (zona burgundy, desktop).

    Pertanyaan yang harus terjawab dalam lima detik: "hari ini bayar apa dulu?"
    Jawabannya bukan tabel yang bisa diurutkan, tapi baris yang SUDAH
    dikelompokkan per tingkat mendesak beserta subtotalnya — jadi keputusan
    bisa diambil tanpa membaca satu per satu.

    Burgundy hanya untuk chrome (header tabel, tab aktif, drawer). Merah terang
    tetap khusus makna negatif: ditolak, terlambat, darurat.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $kelasUmur = function (int $h) {
        if ($h >= 5) return 'rop-umur--5';
        if ($h >= 3) return 'rop-umur--3';
        if ($h >= 1) return 'rop-umur--1';
        return '';
    };
@endphp

<div class="rop-fin">

    <div class="rop-fin__head">
        <div>
            <h1>Antrian Pengajuan</h1>
            <div class="rop-sub">Pengganti formulir Request of Payment — ACC, bayar, dan catat akun bebannya di satu tempat.</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            {{-- Jalan masuk Lembar Bayar. Tidak dibuatkan node menu sendiri:
                 layar ini yang dibuka Finance tiap hari, dan node baru berarti
                 satu permukaan RBAC lagi yang harus di-grant di tiap server. --}}
            <a class="rop-btn rop-btn--fin" href="{{ route('finance.rop.lembar') }}">
                @include('rop._ikon', ['n' => 'kirim'])
                Lembar Bayar
            </a>
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop.rekap') }}">
                @include('rop._ikon', ['n' => 'grafik'])
                Rekap
            </a>
            {{-- Halaman petty cash dijaga canSeeAllBranches() saja, sedangkan
                 antrian ini juga boleh dibuka Head IT/IT. Tautannya karena itu
                 hanya muncul untuk yang benar-benar bisa membukanya — tombol
                 yang berujung 403 adalah kegagalan senyap. --}}
            @if (canSeeAllBranches())
                <a class="rop-btn rop-btn--ghost" href="{{ route('finance.pettycash') }}">
                    @include('rop._ikon', ['n' => 'dompet'])
                    Petty Cash
                </a>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    <div class="rop-fin-stats">
        {{-- data-n / data-rp = nilai mentah, dipakai JS untuk memperbarui angka
             tanpa reload saat ACC/tolak/bayar (jangan parsing teks "Rp …"). --}}
        <div class="rop-fin-stat rop-fin-stat--utama">
            <span class="rop-label">Menunggu ACC</span>
            <b class="rop-num" id="ropStatMenungguN" data-n="{{ $statistik['menunggu_jumlah'] }}">{{ $statistik['menunggu_jumlah'] }}</b>
            <small class="rop-num" id="ropStatMenungguRp" data-rp="{{ (int) $statistik['menunggu_nominal'] }}">{{ $rupiah($statistik['menunggu_nominal']) }}</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Disetujui belum dibayar</span>
            <b class="rop-num" id="ropStatDisetujuiN" data-n="{{ $statistik['disetujui_jumlah'] }}">{{ $statistik['disetujui_jumlah'] }}</b>
            <small class="rop-num" id="ropStatDisetujuiRp" data-rp="{{ (int) $statistik['disetujui_nominal'] }}">{{ $rupiah($statistik['disetujui_nominal']) }}</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Darurat menunggu</span>
            <b class="rop-num">{{ $statistik['darurat_jumlah'] }}</b>
            <small>ditandai mendesak oleh pengaju</small>
        </div>
        <div class="rop-fin-stat {{ $statistik['tunggu_terlama'] > 3 ? 'rop-fin-stat--bahaya' : '' }}">
            <span class="rop-label">Paling lama menunggu</span>
            <b class="rop-num">{{ $statistik['tunggu_terlama'] }} hari</b>
            <small>
                @if ($statistik['tertua_nomor'])
                    {{ $statistik['tertua_nomor'] }} &middot; {{ $statistik['tertua_pengaju'] }}
                @else
                    tidak ada yang mengantre
                @endif
            </small>
        </div>
    </div>

    <div class="rop-tabs">
        @foreach ([
            'menunggu'  => ['Menunggu ACC', $jumlahTab['menunggu']],
            'disetujui' => ['Antrian bayar', $jumlahTab['disetujui']],
            'riwayat'   => ['Riwayat', null],
        ] as $kode => [$label, $n])
            <a class="rop-tab {{ $tab === $kode ? 'rop-tab--aktif' : '' }}"
               href="{{ route('finance.rop', array_filter(['tab' => $kode, 'cc' => $cc, 'q' => $cari])) }}">
                {{ $label }}
                @if ($n)<span class="rop-tab__n" data-badge="{{ $kode }}">{{ $n }}</span>@endif
            </a>
        @endforeach
    </div>

    <form class="rop-toolbar" method="GET">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input class="rop-input rop-input--cari" type="search" name="q" value="{{ $cari }}"
               placeholder="Cari nomor, pengaju, vendor, atau keterangan">
        <select class="rop-select" name="cc">
            <option value="">Semua cost centre</option>
            @foreach ($costCentres as $c)
                <option value="{{ $c->kode }}" {{ $cc === $c->kode ? 'selected' : '' }}>{{ $c->nama }}</option>
            @endforeach
        </select>
        @if ($tab !== 'riwayat')
            <select class="rop-select" name="urut">
                <option value="prioritas" {{ $urut === 'prioritas' ? 'selected' : '' }}>Urut: prioritas</option>
                <option value="nominal"   {{ $urut === 'nominal' ? 'selected' : '' }}>Urut: nominal terbesar</option>
                <option value="lama"      {{ $urut === 'lama' ? 'selected' : '' }}>Urut: paling lama antre</option>
            </select>
        @endif
        <button class="rop-btn rop-btn--ghost" style="min-height:42px">
            @include('rop._ikon', ['n' => 'cari'])
            Terapkan
        </button>
    </form>

    @php
        // Tampilkan berkelompok kalau kelompoknya dihitung controller; kalau
        // pengguna memilih urutan lain, tampilkan datar supaya urutannya jujur.
        $blok = $grup ?: ['semua' => ['judul' => null, 'baris' => $daftar, 'total' => null]];
    @endphp

    {{-- Kolom Aksi HANYA di tab Antrian bayar: "Tandai dibayar" satu-satunya
         tombol yang melompati langkah (langsung ke modal). Membuka detail =
         klik baris, sama dengan tabel Reservasi/Pasien/Doctor — dulu tiap baris
         punya "Cek & ACC"/"Lihat" yang cuma membuka drawer, redundan dengan
         klik baris dan bikin pola antar halaman tidak konsisten. --}}
    @php $adaAksi = $tab === 'disetujui'; @endphp
    <div class="rop-tabel-wrap">
        <table class="rop-tabel">
            {{-- Lebar dikunci di sini: kolom keputusan (nominal, butuh, umur)
                 tidak boleh menyempit gara-gara keterangan yang panjang. --}}
            @if ($adaAksi)
                <colgroup>
                    <col style="width:15%"><col style="width:12%"><col style="width:22%">
                    <col style="width:12%"><col style="width:10%"><col style="width:7%">
                    <col style="width:8%"><col style="width:14%"><col style="width:52px">
                </colgroup>
            @else
                <colgroup>
                    <col style="width:16%"><col style="width:13%"><col style="width:27%">
                    <col style="width:13%"><col style="width:11%"><col style="width:8%">
                    <col style="width:12%"><col style="width:52px">
                </colgroup>
            @endif
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Pengaju</th>
                    <th>Untuk apa</th>
                    <th class="rop-td-kanan">Nominal</th>
                    <th>Butuh</th>
                    <th>Umur</th>
                    <th>Status</th>
                    @if ($adaAksi)<th class="rop-td-kanan">Aksi</th>@endif
                    {{-- kolom chevron: penanda "baris ini bisa dibuka" (pola Reservasi) --}}
                    <th aria-hidden="true"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($blok as $kunci => $g)
                @if ($g['judul'])
                    <tr class="rop-grup rop-grup--{{ $kunci }}">
                        <td colspan="{{ $adaAksi ? 9 : 8 }}">
                            {{ $g['judul'] }} (<span class="rop-grup__n rop-num">{{ count($g['baris']) }}</span>)
                            <span class="rop-grup__total rop-num" data-total="{{ (int) $g['total'] }}">{{ $rupiah($g['total']) }}</span>
                        </td>
                    </tr>
                @endif

                @foreach ($g['baris'] as $r)
                    <tr class="{{ $r->is_urgent ? 'rop-baris--darurat' : ($r->butuhMendesak() ? 'rop-baris--telat' : '') }}"
                        data-rop-buka="{{ $r->id }}" data-amount="{{ (int) $r->amount }}" title="Klik untuk lihat detail">
                        <td>
                            <div class="rop-num">{{ $r->number }}</div>
                            @if ($r->is_urgent)<span class="rop-badge-darurat" style="margin-top:4px">Darurat</span>@endif
                        </td>
                        <td>
                            {{ $r->requester?->nama ?: '—' }}
                            <div class="rop-mini">{{ $r->costCentre?->nama ?: $r->cost_centre }}</div>
                        </td>
                        <td>
                            {{ \Illuminate\Support\Str::limit($r->description, 62) }}
                            <div class="rop-mini">{{ $r->namaPenerima() }}</div>
                        </td>
                        <td class="rop-td-kanan rop-num" style="font-weight:700">{{ $rupiah($r->amount) }}</td>
                        <td class="rop-num" @if ($r->butuhMendesak()) style="color:#C0392B;font-weight:700" @endif>
                            {{ $r->needed_by?->translatedFormat('d M Y') ?: '—' }}
                        </td>
                        <td><span class="rop-umur {{ $kelasUmur($r->umurHari()) }}">{{ $r->umurHari() }} hari</span></td>
                        <td>@include('rop._pill', ['status' => $r->status, 'pendek' => true])</td>
                        @if ($adaAksi)
                            <td class="rop-td-kanan">
                                @if ($r->status === 'disetujui')
                                    <button class="rop-btn rop-btn--ok" style="min-height:38px;padding:8px 14px;font-size:13.5px"
                                            data-rop-bayar="{{ $r->id }}" data-rop-nomor="{{ $r->number }}"
                                            data-rop-nominal="{{ $rupiah($r->amount) }}">Tandai dibayar</button>
                                @endif
                            </td>
                        @endif
                        <td class="rop-chev-cell">@include('rop._ikon', ['n' => 'lanjut', 's' => 18])</td>
                    </tr>
                @endforeach
            @empty
            @endforelse

            @if ($daftar->isEmpty())
                <tr>
                    <td colspan="{{ $adaAksi ? 9 : 8 }}" style="padding:44px;text-align:center;color:var(--rop-muted)">
                        @if ($tab === 'menunggu')
                            <b style="display:block;font-size:15px;color:var(--rop-ink)">Antrian bersih</b>
                            Tidak ada pengajuan yang tenggelam.
                        @elseif ($tab === 'disetujui')
                            Tidak ada yang menunggu dibayar.
                        @else
                            Belum ada riwayat.
                        @endif
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    @if ($tab === 'riwayat' && method_exists($daftar, 'links'))
        <div style="margin-top:14px">{{ $daftar->links() }}</div>
    @endif
</div>

{{-- Toast setelah ACC: salah klik pada layar yang dikerjakan cepat itu wajar,
     jadi ada jalan mundur selama beberapa detik. Sesudah lewat, jalan yang
     tersisa adalah menolak dengan alasan — dan itu memang seharusnya
     meninggalkan jejak. --}}
@if (session('rop_urung'))
    @php $u = session('rop_urung'); @endphp
    <div class="rop-toast" id="ropToast">
        <div>
            <b>{{ $u['number'] }} disetujui</b>
            <small>Akun beban: {{ $u['akun'] }}</small>
        </div>
        <form method="POST" action="{{ route('finance.rop.urungkan', $u['id']) }}">
            @csrf
            <button type="submit">Urungkan</button>
        </form>
        <div class="rop-toast__bar"></div>
    </div>
@endif

{{-- ══ Drawer tinjau & ACC ══ --}}
<div class="rop-drawer-scrim" id="ropScrim"></div>
<aside class="rop-drawer rop-fin" id="ropDrawer" aria-hidden="true">
    <div class="rop-drawer__head" style="position:relative">
        <button class="rop-drawer__tutup" type="button" id="ropTutup" aria-label="Tutup">
            @include('rop._ikon', ['n' => 'tutup', 's' => 16])
        </button>
        <span class="rop-label rop-num" id="ropDNomor"></span>
        <div class="rop-drawer__nominal rop-num" id="ropDNominal"></div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap" id="ropDStatus"></div>
    </div>

    <div class="rop-drawer__body">
        {{-- Kerangka pemuatan: drawer terbuka SEKETIKA saat baris diklik,
             kerangka ini yang tampil sampai fetch detail selesai — koneksi
             lambat tidak boleh terasa seperti "kliknya gagal" (pola-interaksi
             P2 / backlog #2). --}}
        <div id="ropDMuat" hidden aria-hidden="true">
            <div class="rop-card" style="padding:14px">
                <div class="rop-muat__bar" style="width:38%"></div>
                <div class="rop-muat__bar" style="width:64%"></div>
                <div class="rop-muat__bar" style="width:52%"></div>
                <div class="rop-muat__bar" style="width:80%;margin-bottom:0"></div>
            </div>
            <div class="rop-card" style="padding:14px;margin-top:12px">
                <div class="rop-muat__bar" style="width:30%"></div>
                <div class="rop-muat__bar" style="width:100%;height:44px;margin-bottom:0"></div>
            </div>
        </div>

        <div id="ropDIsi">
        <div class="rop-card" style="padding:14px">
            <div id="ropDGrid" class="rop-hero__grid" style="margin:0;padding:0;border:0"></div>
            <div class="rop-drawer__ket">
                <span class="rop-label">Untuk apa</span>
                <p id="ropDKet"></p>
            </div>
        </div>

        <div id="ropDVendor"></div>
        <div id="ropDLampiran"></div>

        {{-- Akun beban: wajib, dan disusun supaya jalur tercepatnya adalah
             menerima saran — mengetik jadi jalan terakhir, bukan pertama. --}}
        <div class="rop-card" style="padding:14px;margin-top:12px" id="ropDAkunBlok">
            <div class="rop-label" style="margin-bottom:10px">Akun beban <span style="color:#C0392B">wajib</span></div>
            <div id="ropDSaran"></div>
            <div id="ropDChip" class="rop-chip-row" style="margin-bottom:10px"></div>
            <select class="rop-select" id="ropDAkun"></select>
            <div class="rop-hint" id="ropDBuku"></div>
        </div>

        <div class="rop-card" style="padding:14px;margin-top:12px">
            <div class="rop-label" style="margin-bottom:8px">Jejak</div>
            <div id="ropDJejak" style="font-size:13px;color:var(--rop-muted)"></div>
        </div>

        {{-- Blok tolak disembunyikan sampai diminta: menolak harus tindakan
             sadar, bukan tombol yang sejajar dengan setujui.

             SATU blok, DUA arti — judul, contoh alasan dan label tombolnya
             diganti JS lewat siapkanBlokTolak():
               • dari `diajukan`  → "Tolak": belum pernah disetujui.
               • dari `disetujui` → "Batalkan persetujuan": uangnya sudah masuk
                 lembar bayar dan mungkin sudah dikirim ke bank. Menolak di sini
                 berarti MENARIK KEMBALI persetujuan yang sudah keluar, jadi
                 kata-katanya tidak boleh sama. --}}
        <div class="rop-card" style="padding:14px;margin-top:12px;display:none" id="ropDTolakBlok">
            <form method="POST" id="ropTolakForm">
                @csrf
                <div class="rop-label" style="margin-bottom:8px" id="ropDTolakJudul">Alasan penolakan</div>
                {{-- Akibatnya ditulis di layar, bukan dibiarkan ditebak (pola P3). --}}
                <div class="rop-note rop-note--kuning" id="ropDTolakAkibat" style="display:none;margin-bottom:10px">
                    Persetujuan ditarik kembali dan pengaju langsung diberi tahu alasannya.
                    Kalau dananya masih dibutuhkan, dia harus mengajukan ulang — pembatalan ini
                    tidak bisa diurungkan dari layar.
                </div>
                <textarea class="rop-textarea" name="alasan" id="ropDAlasan" maxlength="500" required
                          placeholder="Pengaju perlu tahu apa yang harus diperbaiki"></textarea>
                <div class="rop-chip-row" style="margin:8px 0" id="ropDAlasanChipAjukan">
                    @foreach (['Anggaran periode ini sudah habis', 'Perlu penawaran pembanding',
                               'Lampiran belum ada', 'Sebaiknya lewat petty cash'] as $a)
                        <button type="button" class="rop-chip" data-rop-alasan="{{ $a }}">{{ $a }}</button>
                    @endforeach
                </div>
                {{-- Contoh alasan cabang `disetujui` beda isinya: yang bikin
                     persetujuan ditarik bukan "lampiran belum ada", tapi hal yang
                     baru ketahuan SESUDAH ACC — VA kedaluwarsa, nominal berubah. --}}
                <div class="rop-chip-row" style="margin:8px 0;display:none" id="ropDAlasanChipBatal">
                    @foreach (['VA sudah kedaluwarsa sebelum sempat dibayar', 'Nominal atau penerima berubah',
                               'Pembelian dibatalkan pengaju', 'Sudah dibayar lewat jalur lain'] as $a)
                        <button type="button" class="rop-chip" data-rop-alasan="{{ $a }}">{{ $a }}</button>
                    @endforeach
                </div>
                <button class="rop-btn rop-btn--danger rop-btn--block" id="ropTolakKirim">Kirim penolakan</button>
            </form>
        </div>
        </div>{{-- /#ropDIsi --}}
    </div>

    <div class="rop-drawer__foot">
        {{-- Cabang diajukan: Setujui + Tolak --}}
        <div id="ropFootAcc">
            <div class="rop-drawer__aksi">
                <form method="POST" id="ropAccForm">
                    @csrf
                    <input type="hidden" name="coa_no" id="ropDAkunNilai">
                    <button class="rop-btn rop-btn--ok rop-btn--mati" id="ropSetujui" disabled>
                        @include('rop._ikon', ['n' => 'cek'])
                        <span id="ropSetujuiLabel">Pilih akun beban dulu</span>
                    </button>
                </form>
                <button class="rop-btn rop-btn--ghost rop-btn--tolak" type="button" id="ropBukaTolak">Tolak</button>
            </div>
            {{-- Alasan tombol mati ditulis, bukan dibiarkan ditebak. --}}
            <div class="rop-drawer__hint" id="ropAccHint" style="margin-top:9px">Akun beban wajib diisi supaya rekap dan impor Accurate tidak perlu dirapikan lagi.</div>
        </div>
        {{-- Cabang disetujui: langsung ke modal bayar (handoff: canBayar).
             Jalur kedua di samping tombol di tabel Antrian bayar — dari drawer,
             Finance bisa periksa lampiran & akun dulu sebelum menandai lunas. --}}
        <div id="ropFootBayar" style="display:none">
            <button class="rop-btn rop-btn--ok rop-btn--block" type="button" id="ropDrawerBayar">
                @include('rop._ikon', ['n' => 'cek'])
                Tandai dibayar
            </button>
            {{-- Pintu yang selama ini tidak ada. Mesin negara sudah mengizinkan
                 disetujui → ditolak sejak awal (RopService::TRANSISI), tapi tak
                 satu layar pun punya tombolnya: 16 Sep 2026 tiga antrian bayar
                 tertahan 13 hari karena VA-nya kedaluwarsa dan Finance tidak
                 punya cara membatalkannya sendiri. Aksi sekunder, bukan
                 berdampingan seukuran dengan Tandai dibayar. --}}
            <div class="rop-aksi-sekunder">
                {{-- Keadaan lembar bayar tempat pengajuan ini duduk. Membatalkan
                     punya akibat di LUAR layar ini — lembarnya mungkin sudah
                     dikirim ke bos dan sebagian isinya sudah ditransfer. Ini
                     keterangan, bukan pagar: Finance tetap boleh membatalkan. --}}
                <div class="rop-drawer__hint rop-hint--lembar" id="ropDLembar" style="display:none"></div>
                <button class="rop-btn rop-btn--kecil rop-btn--tautan" type="button" id="ropBukaBatalAcc">Batalkan persetujuan</button>
                <div class="rop-drawer__hint" style="margin-top:4px">Pengaju diberi tahu alasannya dan perlu mengajukan ulang.</div>
            </div>
        </div>
    </div>
</aside>

{{-- ══ Modal tandai dibayar ══ --}}
<div class="modal fade" id="ropBayarModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content rop-fin" style="border-radius:16px;overflow:hidden">
            <form method="POST" id="ropBayarForm" enctype="multipart/form-data">
                @csrf
                <div class="rop-drawer__head">
                    <span class="rop-label">Tandai dibayar</span>
                    <div class="rop-drawer__nominal rop-num" id="ropBNominal"></div>
                    <div class="rop-num" id="ropBNomor" style="color:var(--rop-burgundy-ink)"></div>
                </div>
                <div style="padding:16px;background:var(--rop-bg)">
                    <div class="rop-field">
                        <label class="rop-label" for="ropBTanggal">Tanggal transfer</label>
                        <input type="date" class="rop-input" id="ropBTanggal" name="paid_at" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="rop-field">
                        <label class="rop-label" for="ropBBukti">Bukti transfer</label>
                        <input type="file" class="rop-input" id="ropBBukti" name="bukti" accept="image/*,application/pdf">
                    </div>
                    <input type="text" class="rop-input" name="catatan" maxlength="500" placeholder="Catatan (opsional)">
                    <p class="rop-hint">Pengaju langsung melihat bukti ini dan diminta upload nota.</p>
                </div>
                <div class="rop-drawer__foot">
                    <button class="rop-btn rop-btn--ok" style="flex:1">Simpan</button>
                    <button class="rop-btn rop-btn--ghost" type="button" data-dismiss="modal">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    var basis   = "{{ url('finance/rop') }}";
    var drawer  = document.getElementById('ropDrawer');
    var scrim   = document.getElementById('ropScrim');
    var selAkun = document.getElementById('ropDAkun');
    var nilaiAkun = document.getElementById('ropDAkunNilai');
    var tombolSetujui = document.getElementById('ropSetujui');

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    // Gulungan badan dikembalikan ke atas tiap kali drawer dibuka — kalau tidak,
    // posisi gulung pengajuan sebelumnya ikut terbawa dan label di baris pertama
    // (PENGAJU / DIBEBANKAN KE) tampak terpotong di balik kepala drawer.
    function buka()  {
        drawer.classList.add('is-buka'); scrim.classList.add('is-buka');
        drawer.setAttribute('aria-hidden', 'false');
        var badan = drawer.querySelector('.rop-drawer__body');
        if (badan) badan.scrollTop = 0;
    }
    function tutup() { drawer.classList.remove('is-buka'); scrim.classList.remove('is-buka'); drawer.setAttribute('aria-hidden', 'true'); }

    scrim.addEventListener('click', tutup);
    document.getElementById('ropTutup').addEventListener('click', tutup);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });

    // Tombol setujui tetap mati sampai akun dipilih — aturan "ACC wajib punya
    // akun beban" ditegakkan di layar DAN di server.
    function pilihAkun(no, nama) {
        nilaiAkun.value = no || '';
        selAkun.value = no || '';
        var label = document.getElementById('ropSetujuiLabel');
        var hint  = document.getElementById('ropAccHint');
        if (no) {
            tombolSetujui.disabled = false;
            tombolSetujui.classList.remove('rop-btn--mati');
            if (label) label.textContent = 'Setujui';
            if (hint) hint.style.display = 'none';
        } else {
            tombolSetujui.disabled = true;
            tombolSetujui.classList.add('rop-btn--mati');
            if (label) label.textContent = 'Pilih akun beban dulu';
            if (hint) hint.style.display = '';
        }
    }
    selAkun.addEventListener('change', function () { pilihAkun(selAkun.value); });

    // Pengajuan yang sedang terbuka di drawer — dipakai interceptor ACC/tolak
    // untuk memperbarui angka & melompat ke item berikutnya tanpa reload.
    var itemAktif = null;

    function bukaDetail(id) {
            // Drawer terbuka SEKETIKA dengan kerangka — jangan hening menunggu
            // jaringan (backlog #2: koneksi lambat ≠ "kliknya gagal").
            document.getElementById('ropDIsi').hidden = true;
            document.getElementById('ropDMuat').hidden = false;
            document.getElementById('ropDNomor').textContent = 'Memuat…';
            document.getElementById('ropDNominal').textContent = '';
            document.getElementById('ropDStatus').innerHTML = '';
            document.querySelector('.rop-drawer__foot').style.display = 'none';
            buka();

            fetch(basis + '/' + id, { headers: { Accept: 'application/json' } })
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (d) {
                    itemAktif = { id: id, number: d.number, amount: Number(d.amount) || 0, status: d.status };
                    document.getElementById('ropDMuat').hidden = true;
                    document.getElementById('ropDIsi').hidden = false;
                    document.getElementById('ropDNomor').textContent = d.number;
                    document.getElementById('ropDNominal').textContent = rupiah(d.amount);
                    document.getElementById('ropDKet').textContent = d.description;

                    var st = '<span class="rop-pill rop-pill--' + d.status + '">' + esc(d.status_label) + '</span>';
                    if (d.is_urgent) st += '<span class="rop-badge-darurat">Darurat</span>';
                    st += '<span class="rop-umur">' + d.umur_hari + ' hari menunggu</span>';
                    document.getElementById('ropDStatus').innerHTML = st;

                    document.getElementById('ropDGrid').innerHTML = [
                        ['Pengaju', esc(d.pengaju) + (d.jabatan ? '<div class="rop-mini">' + esc(d.jabatan) + '</div>' : '')],
                        ['Dibebankan ke', esc(d.cost_centre_nama || d.cost_centre)],
                        ['Dibayarkan kepada', esc(d.penerima)],
                        ['Butuh kapan', esc(d.needed_by || 'tidak ditentukan')]
                    ].map(function (b) {
                        return '<div><span class="rop-label">' + b[0] + '</span><b>' + b[1] + '</b></div>';
                    }).join('');

                    // Konteks belanja sebelumnya ke penerima yang sama — dasar
                    // menilai "wajar tidak nominal segini" tanpa buka layar lain.
                    var rv = d.riwayat_vendor;
                    document.getElementById('ropDVendor').innerHTML = rv
                        ? '<div class="rop-saran" style="margin-top:12px"><span class="rop-label">Riwayat penerima ini</span>' +
                          '<div style="margin-top:5px;font-size:13.5px">Terakhir dibayar ' + esc(rv.tanggal) +
                          ' <b>' + rupiah(rv.nominal) + '</b>' + (rv.akun ? ' (' + esc(rv.akun) + ')' : '') +
                          ' &middot; total ' + rv.jumlah + '× transaksi</div></div>'
                        : '';

                    document.getElementById('ropDLampiran').innerHTML = d.attachment_path
                        ? '<div class="rop-card" style="padding:14px;margin-top:12px">' +
                          '<span class="rop-label">Lampiran</span><div style="margin-top:8px">' +
                          '<a class="rop-thumb" target="_blank" rel="noopener" href="{{ asset('') }}' +
                          encodeURI(d.attachment_path) +
                          '">Lihat penawaran / invoice</a></div></div>'
                        : '<div class="rop-note rop-note--kuning" style="margin-top:12px">' +
                          // Saran "sebelum ACC" cuma masuk akal selagi masih bisa di-ACC.
                          (d.status === 'diajukan'
                              ? 'Belum ada lampiran. Pertimbangkan minta foto penawaran sebelum ACC.'
                              : 'Tidak ada lampiran.') + '</div>';

                    // Daftar akun mengikuti buku Accurate cost centre ini.
                    selAkun.innerHTML = '<option value="">— cari atau pilih akun beban —</option>' +
                        (d.akun || []).map(function (a) {
                            return '<option value="' + esc(a.no) + '">' + esc(a.no) + ' — ' + esc(a.name) + '</option>';
                        }).join('');
                    document.getElementById('ropDBuku').textContent = (d.akun && d.akun.length)
                        ? d.akun.length + ' akun dari buku ' + (d.cost_centre_nama || d.cost_centre)
                        : 'Daftar akun belum tersedia di server ini — data Accurate belum ditarik.';

                    var bahan = d.bahan_akun || {};
                    document.getElementById('ropDSaran').innerHTML = bahan.saran
                        ? '<div class="rop-saran" style="margin-bottom:10px">' +
                          '<span class="rop-label">Terakhir dipakai untuk penerima ini</span>' +
                          '<div style="margin:5px 0 8px;font-weight:700">' + esc(bahan.saran.no) + ' — ' + esc(bahan.saran.name) + '</div>' +
                          '<button type="button" class="rop-chip" data-rop-pakai="' + esc(bahan.saran.no) + '">Pakai akun ini</button></div>'
                        : '';

                    document.getElementById('ropDChip').innerHTML = (bahan.terakhir || []).map(function (a) {
                        return '<button type="button" class="rop-chip" data-rop-pakai="' + esc(a.no) + '">' +
                               esc(a.no) + ' — ' + esc(a.name) + '</button>';
                    }).join('');

                    document.querySelectorAll('[data-rop-pakai]').forEach(function (c) {
                        c.addEventListener('click', function () { pilihAkun(c.getAttribute('data-rop-pakai')); });
                    });

                    document.getElementById('ropDJejak').innerHTML = (d.jejak || []).map(function (j) {
                        return '<div style="margin-bottom:4px">' + esc(j.kapan) + ' &middot; <b>' + esc(j.status) +
                               '</b> &middot; ' + esc(j.oleh) + (j.catatan ? ' — ' + esc(j.catatan) : '') + '</div>';
                    }).join('');

                    document.getElementById('ropAccForm').action  = basis + '/' + id + '/setujui';
                    document.getElementById('ropTolakForm').action = basis + '/' + id + '/tolak';

                    // Footer per status: diajukan → Setujui/Tolak · disetujui →
                    // Tandai dibayar · selebihnya cuma bisa dilihat (footer hilang).
                    var bisaAcc   = d.status === 'diajukan';
                    var bisaBayar = d.status === 'disetujui';
                    document.querySelector('.rop-drawer__foot').style.display = (bisaAcc || bisaBayar) ? '' : 'none';
                    document.getElementById('ropFootAcc').style.display   = bisaAcc ? '' : 'none';
                    document.getElementById('ropFootBayar').style.display = bisaBayar ? '' : 'none';
                    document.getElementById('ropDAkunBlok').style.display = bisaAcc ? '' : 'none';
                    // Satu baris keadaan lembar, hanya di cabang yang bisa
                    // membatalkan — di status lain ia cuma menambah bising.
                    var elLembar = document.getElementById('ropDLembar');
                    if (bisaBayar && d.lembar) {
                        elLembar.textContent = 'Ada di lembar ' + d.lembar.number
                            + (d.lembar.dikirim ? ' · dikirim ke bos ' + d.lembar.dikirim : ' · belum dikirim')
                            + ' · ' + d.lembar.dibayar + ' dari ' + d.lembar.item + ' item sudah dibayar';
                        elLembar.style.display = '';
                    } else {
                        elLembar.textContent = '';
                        elLembar.style.display = 'none';
                    }

                    var fb = document.getElementById('ropDrawerBayar');
                    fb.setAttribute('data-id', id);
                    fb.setAttribute('data-nomor', d.number);
                    fb.setAttribute('data-nominal', rupiah(d.amount));

                    pilihAkun('');
                    document.getElementById('ropDTolakBlok').style.display = 'none';
                    document.getElementById('ropDAlasan').value = '';   // jangan bawa alasan item sebelumnya
                    // Mode blok alasan mengikuti status item yang baru dibuka —
                    // kalau tidak, judul "Batalkan persetujuan" dari item
                    // sebelumnya ikut terbawa ke pengajuan yang baru diajukan.
                    siapkanBlokTolak(bisaBayar ? 'batal-acc' : 'tolak');
                    buka();
                })
                .catch(function () {
                    tutup();
                    tampilkanToast('Gagal memuat detail — coba lagi.', '', null, true);
                });
    }

    // Baris tabel = pintu masuk detail (pola yang sama dengan tabel Reservasi/
    // Pasien/Doctor). Klik pada tombol/link di dalam baris tidak ikut membuka.
    document.querySelectorAll('[data-rop-buka]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('button, a, form, input, select, label')) return;
            bukaDetail(tr.getAttribute('data-rop-buka'));
        });
    });
    // Kompatibilitas: tombol lama yang masih menyandang data-rop-acc (kalau ada).
    document.querySelectorAll('[data-rop-acc]').forEach(function (btn) {
        btn.addEventListener('click', function () { bukaDetail(btn.getAttribute('data-rop-acc')); });
    });

    /* ══ Blok alasan: satu form, dua arti ═════════════════════════════════
       'tolak'     → dari `diajukan`, belum pernah disetujui.
       'batal-acc' → dari `disetujui`, menarik kembali persetujuan yang sudah
                     keluar. Dua tombol berlabel sama dengan arti berbeda itu
                     yang harus dihindari, jadi kata-katanya ikut berganti. */
    var modeTolak = 'tolak';

    function siapkanBlokTolak(mode) {
        modeTolak = (mode === 'batal-acc') ? 'batal-acc' : 'tolak';
        var batal = modeTolak === 'batal-acc';
        document.getElementById('ropDTolakJudul').textContent =
            batal ? 'Alasan pembatalan persetujuan' : 'Alasan penolakan';
        document.getElementById('ropDTolakAkibat').style.display = batal ? '' : 'none';
        document.getElementById('ropDAlasan').placeholder = batal
            ? 'Pengaju perlu tahu kenapa persetujuannya ditarik'
            : 'Pengaju perlu tahu apa yang harus diperbaiki';
        document.getElementById('ropDAlasanChipAjukan').style.display = batal ? 'none' : '';
        document.getElementById('ropDAlasanChipBatal').style.display  = batal ? '' : 'none';
        document.getElementById('ropTolakKirim').textContent =
            batal ? 'Batalkan persetujuan' : 'Kirim penolakan';
    }

    function bukaBlokTolak(mode) {
        siapkanBlokTolak(mode);
        var blok = document.getElementById('ropDTolakBlok');
        blok.style.display = '';
        // Fokus dulu dengan preventScroll — focus() bawaan ikut menggulung dan
        // membatalkan animasi scrollIntoView, hasilnya blok malah diam di tempat.
        // 'start', bukan 'center': bloknya lebih tinggi dari separuh badan drawer,
        // jadi yang harus terlihat adalah bagian ATAS (textarea alasan).
        document.getElementById('ropDAlasan').focus({ preventScroll: true });
        blok.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('ropBukaTolak').addEventListener('click', function () {
        bukaBlokTolak('tolak');
    });
    document.getElementById('ropBukaBatalAcc').addEventListener('click', function () {
        bukaBlokTolak('batal-acc');
    });

    document.querySelectorAll('[data-rop-alasan]').forEach(function (c) {
        c.addEventListener('click', function () {
            document.getElementById('ropDAlasan').value = c.getAttribute('data-rop-alasan');
        });
    });

    // Toast menghilang sendiri setelah jendela urungnya lewat.
    var toast = document.getElementById('ropToast');
    if (toast) {
        setTimeout(function () { toast.style.display = 'none'; }, 5400);
    }

    function bukaModalBayar(id, nomor, nominal) {
        document.getElementById('ropBNomor').textContent = nomor;
        document.getElementById('ropBNominal').textContent = nominal;
        document.getElementById('ropBayarForm').action = basis + '/' + id + '/bayar';
        $('#ropBayarModal').modal('show');
    }

    /* ══ Backlog #1 pola-interaksi: aksi tanpa reload ═════════════════════
       ACC / tolak / bayar dikirim via fetch → toast (+ "Urungkan" utk ACC &
       tolak) → baris hilang, angka-angka ikut turun, drawer langsung pindah
       ke pengajuan berikutnya. Form tetap form biasa sebagai fallback tanpa
       JS — interceptor ini hanya lapisan di atasnya. ═════════════════════ */

    var CSRF = (document.querySelector('#ropAccForm input[name=_token]') || {}).value || '';

    // ── toast (dibangun client-side; kembarannya versi flash tetap ada
    //    untuk jalur non-fetch) ──
    function tampilkanToast(judul, sub, urungId, galat) {
        var lama = document.getElementById('ropToastJs');
        if (lama) lama.remove();
        var t = document.createElement('div');
        t.className = 'rop-toast' + (galat ? ' rop-toast--galat' : '');
        t.id = 'ropToastJs';
        var isi = document.createElement('div');
        var b = document.createElement('b'); b.textContent = judul; isi.appendChild(b);
        if (sub) { var s = document.createElement('small'); s.textContent = sub; isi.appendChild(s); }
        t.appendChild(isi);
        if (urungId) {
            var btn = document.createElement('button');
            btn.type = 'button'; btn.textContent = 'Urungkan';
            btn.addEventListener('click', function () {
                btn.disabled = true; btn.textContent = 'Mengembalikan…';
                var fd = new FormData(); fd.append('_token', CSRF);
                fetch(basis + '/' + urungId + '/urungkan', {
                    method: 'POST', body: fd,
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                  .then(function (h) {
                      // Urungkan = jalur langka; muat ulang supaya semua angka
                      // kembali jujur tanpa akrobat DOM.
                      if (h.ok) { location.reload(); }
                      else { tampilkanToast(h.d.error || 'Gagal mengurungkan.', '', null, true); }
                  })
                  .catch(function () { tampilkanToast('Jaringan bermasalah — coba lagi.', '', null, true); });
            });
            t.appendChild(btn);
        }
        var bar = document.createElement('div'); bar.className = 'rop-toast__bar'; t.appendChild(bar);
        document.body.appendChild(t);
        // Ikut jendela urung server (RopService::DETIK_URUNG jauh lebih longgar,
        // tapi toast cukup selama animasi barnya).
        setTimeout(function () { if (t.parentNode) t.remove(); }, galat ? 6000 : 5400);
    }

    // ── angka hidup: statistik, badge tab, header grup ──
    function geserAngka(el, delta) {
        if (!el) return;
        var n = Math.max(0, (Number(el.dataset.n) || 0) + delta);
        el.dataset.n = n; el.textContent = n;
    }
    function geserRupiah(el, delta) {
        if (!el) return;
        var n = Math.max(0, (Number(el.dataset.rp) || 0) + delta);
        el.dataset.rp = n; el.textContent = rupiah(n);
    }
    function geserBadge(kode, delta) {
        var b = document.querySelector('[data-badge="' + kode + '"]');
        if (!b) return;
        var n = Math.max(0, (Number(b.textContent) || 0) + delta);
        b.textContent = n;
        if (n === 0) b.remove();   // server juga tidak merender badge 0
    }
    function perbaruiAngka(aksi, amount) {
        if (aksi === 'acc') {
            geserAngka(document.getElementById('ropStatMenungguN'), -1);
            geserRupiah(document.getElementById('ropStatMenungguRp'), -amount);
            geserAngka(document.getElementById('ropStatDisetujuiN'), +1);
            geserRupiah(document.getElementById('ropStatDisetujuiRp'), +amount);
            geserBadge('menunggu', -1); geserBadge('disetujui', +1);
        } else if (aksi === 'tolak') {
            geserAngka(document.getElementById('ropStatMenungguN'), -1);
            geserRupiah(document.getElementById('ropStatMenungguRp'), -amount);
            geserBadge('menunggu', -1);
        } else if (aksi === 'batal-acc') {
            // Menolak dari `disetujui` mengosongkan antrian BAYAR, bukan antrian
            // ACC — kalau ikut cabang 'tolak', angka "Menunggu ACC" turun padahal
            // item ini sudah lama keluar dari sana.
            geserAngka(document.getElementById('ropStatDisetujuiN'), -1);
            geserRupiah(document.getElementById('ropStatDisetujuiRp'), -amount);
            geserBadge('disetujui', -1);
        } else if (aksi === 'bayar') {
            geserAngka(document.getElementById('ropStatDisetujuiN'), -1);
            geserRupiah(document.getElementById('ropStatDisetujuiRp'), -amount);
            geserBadge('disetujui', -1);
        }
    }

    // ── baris hilang + header grupnya menyusut; kembalikan id berikutnya ──
    function hapusBaris(id, amount) {
        var tr = document.querySelector('tr[data-rop-buka="' + id + '"]');
        if (!tr) return null;
        var semua = Array.prototype.slice.call(document.querySelectorAll('tr[data-rop-buka]'));
        var i = semua.indexOf(tr);
        var berikut = semua[i + 1] || semua[i - 1] || null;

        // header grup di atas baris ini (kalau tampilan berkelompok)
        var grup = tr.previousElementSibling;
        while (grup && !grup.classList.contains('rop-grup')) grup = grup.previousElementSibling;
        if (grup) {
            var nEl = grup.querySelector('.rop-grup__n');
            var tEl = grup.querySelector('.rop-grup__total');
            var sisa = Math.max(0, (Number(nEl && nEl.textContent) || 1) - 1);
            if (nEl) nEl.textContent = sisa;
            if (tEl) { var tot = Math.max(0, (Number(tEl.dataset.total) || 0) - amount); tEl.dataset.total = tot; tEl.textContent = rupiah(tot); }
            if (sisa === 0) grup.remove();
        }
        tr.remove();
        return berikut ? berikut.getAttribute('data-rop-buka') : null;
    }

    function setelahAksi(aksi, id, amount, berikutnyaDariDrawer) {
        perbaruiAngka(aksi, amount);
        var berikut = hapusBaris(id, amount);
        if (berikutnyaDariDrawer) {
            if (berikut) bukaDetail(berikut);
            else tutup();
        }
    }

    // ── pengirim fetch untuk form (FormData ikut membawa file & _token) ──
    function kirimForm(form, tombol, labelSibuk, lalu) {
        var labelEl = tombol ? (tombol.querySelector('span') || tombol) : null;
        var labelAsli = labelEl ? labelEl.textContent : '';
        if (tombol) { tombol.disabled = true; if (labelEl) labelEl.textContent = labelSibuk; }
        fetch(form.action, {
            method: 'POST', body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
          .then(function (h) {
              if (h.ok && h.d.ok) { lalu(h.d); }
              else {
                  // 422 validasi Laravel: {message, errors}; error kita: {error}
                  tampilkanToast(h.d.error || h.d.message || 'Gagal menyimpan.', '', null, true);
              }
          })
          .catch(function () { tampilkanToast('Jaringan bermasalah — coba lagi.', '', null, true); })
          .finally(function () { if (tombol) { tombol.disabled = false; if (labelEl) labelEl.textContent = labelAsli; } });
    }

    document.getElementById('ropAccForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var aktif = itemAktif || {};
        kirimForm(this, document.getElementById('ropSetujui'), 'Menyimpan…', function (d) {
            tampilkanToast(d.urung.number + ' disetujui', 'Akun beban: ' + d.urung.akun, d.urung.id);
            setelahAksi('acc', d.urung.id, Number(d.amount) || aktif.amount || 0, true);
        });
    });

    document.getElementById('ropTolakForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var aktif = itemAktif || {};
        var tombol = this.querySelector('button[type=submit], button:not([type])');
        var modeKirim = modeTolak;   // dibekukan sebelum respons: drawer bisa sudah pindah item
        kirimForm(this, tombol, modeKirim === 'batal-acc' ? 'Membatalkan…' : 'Mengirim…', function (d) {
            // Status ASAL datang dari server (d.dari) — layar cuma cadangan
            // kalau respons lama belum membawanya.
            var batal = (d.dari || (modeKirim === 'batal-acc' ? 'disetujui' : 'diajukan')) === 'disetujui';
            if (batal) {
                // Tanpa "Urungkan": urungkanPenolakan() mengembalikan ke
                // `diajukan`, BUKAN ke `disetujui` — tombol yang memulihkan ke
                // status yang salah lebih berbahaya daripada tidak ada tombol.
                // Jalur pemulihannya: pengaju mengajukan ulang.
                tampilkanToast('Persetujuan ' + d.urung.number + ' dibatalkan',
                               'Pengaju diberi tahu dan bisa mengajukan ulang.', null);
            } else {
                tampilkanToast(d.urung.number + ' ditolak', 'Pengaju diberi tahu alasannya.', d.urung.id);
            }
            setelahAksi(batal ? 'batal-acc' : 'tolak', d.urung.id,
                        Number(d.amount) || aktif.amount || 0, true);
        });
    });

    document.getElementById('ropBayarForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;
        var id = (form.action.match(/rop\/(\d+)\/bayar/) || [])[1];
        var tombol = form.querySelector('button[type=submit], button:not([type])');
        kirimForm(form, tombol, 'Menyimpan…', function (d) {
            $('#ropBayarModal').modal('hide');
            form.reset();
            tampilkanToast(d.number + ' ditandai sudah dibayar', 'Pengaju diminta upload nota.', null);
            setelahAksi('bayar', id, Number(d.amount) || 0, false);
        });
    });

    document.querySelectorAll('[data-rop-bayar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            bukaModalBayar(btn.getAttribute('data-rop-bayar'),
                           btn.getAttribute('data-rop-nomor'),
                           btn.getAttribute('data-rop-nominal'));
        });
    });

    // Dari footer drawer: drawer ditutup dulu supaya tidak rebutan lapisan
    // dengan modal Bootstrap (dua-duanya elemen fixed ber-z-index tinggi).
    document.getElementById('ropDrawerBayar').addEventListener('click', function () {
        var b = this;
        tutup();
        bukaModalBayar(b.getAttribute('data-id'), b.getAttribute('data-nomor'), b.getAttribute('data-nominal'));
    });
})();
</script>
@endsection
