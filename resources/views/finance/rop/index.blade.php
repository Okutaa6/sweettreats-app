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
        <div class="rop-fin-stat rop-fin-stat--utama">
            <span class="rop-label">Menunggu ACC</span>
            <b class="rop-num">{{ $statistik['menunggu_jumlah'] }}</b>
            <small class="rop-num">{{ $rupiah($statistik['menunggu_nominal']) }}</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Disetujui belum dibayar</span>
            <b class="rop-num">{{ $statistik['disetujui_jumlah'] }}</b>
            <small class="rop-num">{{ $rupiah($statistik['disetujui_nominal']) }}</small>
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
                @if ($n)<span class="rop-tab__n">{{ $n }}</span>@endif
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

    <div class="rop-tabel-wrap">
        <table class="rop-tabel">
            {{-- Lebar dikunci di sini: kolom keputusan (nominal, butuh, umur,
                 aksi) tidak boleh menyempit gara-gara keterangan yang panjang. --}}
            <colgroup>
                <col style="width:15%"><col style="width:12%"><col style="width:23%">
                <col style="width:12%"><col style="width:10%"><col style="width:7%">
                <col style="width:9%"><col style="width:12%">
            </colgroup>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Pengaju</th>
                    <th>Untuk apa</th>
                    <th class="rop-td-kanan">Nominal</th>
                    <th>Butuh</th>
                    <th>Umur</th>
                    <th>Status</th>
                    <th class="rop-td-kanan">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($blok as $kunci => $g)
                @if ($g['judul'])
                    <tr class="rop-grup rop-grup--{{ $kunci }}">
                        <td colspan="8">
                            {{ $g['judul'] }} ({{ count($g['baris']) }})
                            <span class="rop-grup__total rop-num">{{ $rupiah($g['total']) }}</span>
                        </td>
                    </tr>
                @endif

                @foreach ($g['baris'] as $r)
                    <tr class="{{ $r->is_urgent ? 'rop-baris--darurat' : ($r->butuhMendesak() ? 'rop-baris--telat' : '') }}">
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
                        <td class="rop-td-kanan">
                            @if ($r->status === 'diajukan')
                                <button class="rop-btn rop-btn--fin" style="min-height:38px;padding:8px 14px;font-size:13.5px"
                                        data-rop-acc="{{ $r->id }}">Cek &amp; ACC</button>
                            @elseif ($r->status === 'disetujui')
                                <button class="rop-btn rop-btn--ok" style="min-height:38px;padding:8px 14px;font-size:13.5px"
                                        data-rop-bayar="{{ $r->id }}" data-rop-nomor="{{ $r->number }}"
                                        data-rop-nominal="{{ $rupiah($r->amount) }}">Tandai dibayar</button>
                            @else
                                <button class="rop-btn rop-btn--ghost" style="min-height:38px;padding:8px 14px;font-size:13.5px"
                                        data-rop-acc="{{ $r->id }}">Lihat</button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            @empty
            @endforelse

            @if ($daftar->isEmpty())
                <tr>
                    <td colspan="8" style="padding:44px;text-align:center;color:var(--rop-muted)">
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
        <div class="rop-card" style="padding:14px">
            <div id="ropDGrid" class="rop-hero__grid" style="margin:0;padding:0;border:0"></div>
            <p style="margin-top:12px;font-size:14.5px;line-height:1.55" id="ropDKet"></p>
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
             sadar, bukan tombol yang sejajar dengan setujui. --}}
        <div class="rop-card" style="padding:14px;margin-top:12px;display:none" id="ropDTolakBlok">
            <form method="POST" id="ropTolakForm">
                @csrf
                <div class="rop-label" style="margin-bottom:8px">Alasan penolakan</div>
                <textarea class="rop-textarea" name="alasan" id="ropDAlasan" maxlength="500" required
                          placeholder="Pengaju perlu tahu apa yang harus diperbaiki"></textarea>
                <div class="rop-chip-row" style="margin:8px 0">
                    @foreach (['Anggaran periode ini sudah habis', 'Perlu penawaran pembanding',
                               'Lampiran belum ada', 'Sebaiknya lewat petty cash'] as $a)
                        <button type="button" class="rop-chip" data-rop-alasan="{{ $a }}">{{ $a }}</button>
                    @endforeach
                </div>
                <button class="rop-btn rop-btn--danger rop-btn--block">Kirim penolakan</button>
            </form>
        </div>
    </div>

    <div class="rop-drawer__foot">
        <form method="POST" id="ropAccForm" style="flex:1;display:flex;gap:10px">
            @csrf
            <input type="hidden" name="coa_no" id="ropDAkunNilai">
            <button class="rop-btn rop-btn--ok rop-btn--mati" style="flex:1" id="ropSetujui" disabled>
                @include('rop._ikon', ['n' => 'cek'])
                Setujui
            </button>
        </form>
        <button class="rop-btn rop-btn--ghost" type="button" id="ropBukaTolak">Tolak</button>
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

    function buka()  { drawer.classList.add('is-buka'); scrim.classList.add('is-buka'); drawer.setAttribute('aria-hidden', 'false'); }
    function tutup() { drawer.classList.remove('is-buka'); scrim.classList.remove('is-buka'); drawer.setAttribute('aria-hidden', 'true'); }

    scrim.addEventListener('click', tutup);
    document.getElementById('ropTutup').addEventListener('click', tutup);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });

    // Tombol setujui tetap mati sampai akun dipilih — aturan "ACC wajib punya
    // akun beban" ditegakkan di layar DAN di server.
    function pilihAkun(no, nama) {
        nilaiAkun.value = no || '';
        selAkun.value = no || '';
        if (no) {
            tombolSetujui.disabled = false;
            tombolSetujui.classList.remove('rop-btn--mati');
        } else {
            tombolSetujui.disabled = true;
            tombolSetujui.classList.add('rop-btn--mati');
        }
    }
    selAkun.addEventListener('change', function () { pilihAkun(selAkun.value); });

    document.querySelectorAll('[data-rop-acc]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-rop-acc');

            fetch(basis + '/' + id, { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    document.getElementById('ropDNomor').textContent = d.number;
                    document.getElementById('ropDNominal').textContent = rupiah(d.amount);
                    document.getElementById('ropDKet').textContent = d.description;

                    var st = '<span class="rop-pill rop-pill--' + d.status + '">' + esc(d.status_label) + '</span>';
                    if (d.is_urgent) st += '<span class="rop-badge-darurat">Darurat</span>';
                    st += '<span class="rop-umur">' + d.umur_hari + ' hari menunggu</span>';
                    document.getElementById('ropDStatus').innerHTML = st;

                    document.getElementById('ropDGrid').innerHTML = [
                        ['Pengaju', esc(d.pengaju) + (d.jabatan ? '<div class="rop-mini">' + esc(d.jabatan) + '</div>' : '')],
                        ['Dibebankan', esc(d.cost_centre_nama || d.cost_centre)],
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
                          '<a class="rop-thumb" target="_blank" rel="noopener" href="{{ asset('') }}' + d.attachment_path +
                          '">Lihat penawaran / invoice</a></div></div>'
                        : '<div class="rop-note rop-note--kuning" style="margin-top:12px">' +
                          'Belum ada lampiran. Pertimbangkan minta foto penawaran sebelum ACC.</div>';

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

                    // Yang sudah diputuskan cuma bisa dilihat.
                    var bisaAksi = d.status === 'diajukan';
                    document.querySelector('.rop-drawer__foot').style.display = bisaAksi ? '' : 'none';
                    document.getElementById('ropDAkunBlok').style.display = bisaAksi ? '' : 'none';

                    pilihAkun('');
                    document.getElementById('ropDTolakBlok').style.display = 'none';
                    buka();
                });
        });
    });

    document.getElementById('ropBukaTolak').addEventListener('click', function () {
        var blok = document.getElementById('ropDTolakBlok');
        blok.style.display = '';
        blok.scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('ropDAlasan').focus();
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

    document.querySelectorAll('[data-rop-bayar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-rop-bayar');
            document.getElementById('ropBNomor').textContent = btn.getAttribute('data-rop-nomor');
            document.getElementById('ropBNominal').textContent = btn.getAttribute('data-rop-nominal');
            document.getElementById('ropBayarForm').action = basis + '/' + id + '/bayar';
            $('#ropBayarModal').modal('show');
        });
    });
})();
</script>
@endsection
