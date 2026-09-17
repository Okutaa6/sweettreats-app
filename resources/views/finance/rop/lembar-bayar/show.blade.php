{{--
    Isi satu lembar bayar + tandai dibayar SEKALIGUS.

    Layar ini yang membuat fiturnya sepasang. Tanpa dia, lembar cetak cuma
    memindahkan kerja Finance: dari mengirim 30 berkas jadi mengunggah bukti
    transfer yang sama 15 kali, karena bos membayar beberapa ROP dalam satu
    transfer tapi paid_proof_path itu per-ROP.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $rupiah   = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $dibayar  = $lembar->jumlahDibayar();
    $bisaBayar = $lembar->itemBelumDibayar();

    // Item yang keluar dari lembar SESUDAH lembar dibuat — persetujuannya
    // dibatalkan, atau pengajuannya kadaluarsa. rop_payment_sheets.total itu
    // kolom beku (dihitung sekali saat lembar dibuat) dan memang TIDAK ikut
    // turun; tanpa keterangan ini selisihnya terbaca sebagai salah hitung.
    $batal      = $lembar->items->filter(fn ($i) => $i->rop && in_array(
                      $i->rop->status,
                      [\App\Models\RopRequest::STATUS_DITOLAK, \App\Models\RopRequest::STATUS_KADALUARSA],
                      true
                  ));
    // SNAPSHOT (`items.amount`), bukan `rop->amount` yang hidup — `lembar->total`
    // juga dijumlahkan dari snapshot saat lembar dibuat, jadi pengurangannya
    // harus dari sumber yang sama atau selisihnya melenceng.
    $nilaiBatal = (float) $batal->sum(fn ($i) => (float) $i->amount);
@endphp

<div class="rop-fin">

    <div class="rop-fin__head">
        <div>
            <h1 class="rop-num">{{ $lembar->number }}</h1>
            <div class="rop-sub">
                Dibuat {{ $lembar->dibuat_at?->translatedFormat('d M Y, H:i') }}
                oleh {{ $lembar->pembuat?->nama ?: '—' }}
                &middot; {{ $lembar->jumlah_item }} item &middot; {{ $rupiah($lembar->total) }}
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop.lembar') }}">
                @include('rop._ikon', ['n' => 'kembali'])
                Lembar Bayar
            </a>
            {{-- Tab baru: halaman cetak berdiri sendiri (tanpa sidebar), dan
                 Finance biasanya masih perlu layar ini sesudah mencetak. --}}
            <a class="rop-btn rop-btn--fin" href="{{ route('finance.rop.lembar.cetak', $lembar->id) }}"
               target="_blank" rel="noopener">
                @include('rop._ikon', ['n' => 'unduh'])
                Cetak / Simpan PDF
            </a>
        </div>
    </div>

    @include('layouts.notif')

    <div class="rop-fin-stats">
        <div class="rop-fin-stat rop-fin-stat--utama">
            <span class="rop-label">Total diminta</span>
            <b class="rop-num">{{ $rupiah($lembar->total) }}</b>
            <small>{{ $lembar->jumlah_item }} pengajuan</small>
        </div>
        <div class="rop-fin-stat">
            <span class="rop-label">Sudah dibayar</span>
            <b class="rop-num">{{ $dibayar }} / {{ $lembar->jumlah_item }}</b>
            <small>
                {{ $bisaBayar->count() }} masih menunggu uang@if ($batal->isNotEmpty()), {{ $batal->count() }} dibatalkan@endif
            </small>
        </div>
        <div class="rop-fin-stat {{ $lembar->sudahDikirim() ? '' : 'rop-fin-stat--bahaya' }}">
            <span class="rop-label">Dikirim ke bos</span>
            <b class="rop-num" style="font-size:19px">
                {{ $lembar->sudahDikirim() ? $lembar->dikirim_at->translatedFormat('d M Y, H:i') : 'Belum' }}
            </b>
            <small>
                @if ($lembar->sudahDikirim())
                    oleh {{ $lembar->pengirim?->nama ?: '—' }}
                @else
                    cetak dulu, kirim, lalu tandai di bawah
                @endif
            </small>
        </div>
    </div>

    @if ($batal->isNotEmpty())
        {{-- Selisih total dijelaskan di tempat orang melihatnya. Angka di kepala
             lembar SENGAJA tidak ikut turun: lembar cetak yang sudah dikirim ke
             bank itu dokumen sejarah, bukan laporan hidup. --}}
        <div class="rop-note rop-note--kuning" style="margin-bottom:14px">
            <b>{{ $batal->count() }} item keluar dari lembar ini sesudah dibuat</b> —
            {{ $rupiah($nilaiBatal) }} tidak jadi dibayar
            ({{ $batal->map(fn ($i) => $i->rop->number)->implode(', ') }}).
            Total di atas tetap {{ $rupiah($lembar->total) }} karena itu angka yang sudah tercetak
            dan terkirim; yang benar-benar dibayar {{ $rupiah((float) $lembar->total - $nilaiBatal) }}.
        </div>
    @endif

    {{-- Penuntun langkah (pola-interaksi P5/P6: layar mengajarkan langkah
         berikutnya, tidak menunggu orangnya hafal). Langkah yang SEDANG
         berjalan disorot; yang sudah lewat diberi centang. Lembar bayar
         dipakai mungkin seminggu sekali — terlalu jarang untuk dihafal,
         terlalu sering untuk dibuatkan dokumen sendiri. --}}
    @php
        $langkah = [
            ['n' => 1, 'judul' => 'Cetak jadi PDF',        'ket' => 'tombol di kanan atas, pilih A4',      'selesai' => $lembar->sudahDikirim()],
            ['n' => 2, 'judul' => 'Kirim ke bos',          'ket' => 'satu berkas lewat WhatsApp',          'selesai' => $lembar->sudahDikirim()],
            ['n' => 3, 'judul' => 'Tandai sudah dikirim',  'ket' => 'supaya ketahuan mana yang menggantung', 'selesai' => $lembar->sudahDikirim()],
            ['n' => 4, 'judul' => 'Tandai dibayar',        'ket' => 'centang yang cair, unggah satu bukti', 'selesai' => $dibayar >= $lembar->jumlah_item && $lembar->jumlah_item > 0],
        ];
        // Langkah 1 TIDAK dicentang otomatis: sistem tak tahu apakah lembarnya
        // benar-benar sudah dicetak. Yang diketahui cuma 'sudah dikirim' —
        // dan kalau sudah dikirim, berarti dicetak lebih dulu.
        $aktif = $lembar->sudahDikirim() ? 4 : 1;
    @endphp
    <ol class="rop-langkah">
        @foreach ($langkah as $l)
            <li class="{{ $l['selesai'] ? 'is-selesai' : '' }} {{ $l['n'] === $aktif && ! $l['selesai'] ? 'is-aktif' : '' }}">
                <b>{{ $l['selesai'] ? '✓' : $l['n'] }}</b>
                <div>
                    <span>{{ $l['judul'] }}</span>
                    <small>{{ $l['ket'] }}</small>
                </div>
            </li>
        @endforeach
    </ol>

    @if (! $lembar->sudahDikirim())
        <form method="POST" action="{{ route('finance.rop.lembar.dikirim', $lembar->id) }}"
              style="margin-bottom:14px">
            @csrf
            <button class="rop-btn rop-btn--ghost">
                @include('rop._ikon', ['n' => 'kirim'])
                Tandai sudah dikirim ke bos
            </button>
        </form>
    @endif

    @if ($lembar->catatan)
        <div class="rop-note" style="margin-bottom:14px">
            <b>Catatan di lembar:</b> {{ $lembar->catatan }}
        </div>
    @endif

    {{-- ══ Isi lembar + tandai dibayar sekaligus ══ --}}
    <form method="POST" action="{{ route('finance.rop.lembar.bayar-banyak', $lembar->id) }}"
          enctype="multipart/form-data" id="lbBayarForm">
        @csrf

        <div class="rop-tabel-wrap">
            <table class="rop-tabel">
                <colgroup>
                    <col style="width:42px"><col style="width:14%"><col style="width:11%">
                    <col style="width:20%"><col style="width:19%"><col style="width:12%">
                    <col style="width:13%"><col style="width:9%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="rop-cek-cell">
                            @if ($bisaBayar->isNotEmpty())
                                <input type="checkbox" id="lbSemuaBayar" aria-label="Pilih semua yang belum dibayar">
                            @endif
                        </th>
                        <th>Nomor</th>
                        <th>Pengaju</th>
                        <th>Dibayarkan kepada</th>
                        <th>Untuk apa</th>
                        <th class="rop-td-kanan">Nominal</th>
                        <th>Status</th>
                        <th>Lampiran</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lembar->items as $item)
                    @php
                        $r     = $item->rop;
                        $lunas = $r && in_array($r->status, ['dibayar', 'selesai'], true);
                        $path  = $r?->attachment_path;
                        $ext   = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;
                        $beda  = $r && (float) $r->amount !== (float) $item->amount;
                        // Tujuan boleh dibetulkan hanya selagi lembar belum dikirim
                        // DAN uangnya belum keluar. Mengubah tujuan setelah transfer
                        // cuma bikin catatan berbeda dari kenyataan.
                        $bisaUbahTujuan = ! $lembar->sudahDikirim() && ! $lunas;
                    @endphp
                    <tr>
                        <td class="rop-cek-cell">
                            @if ($r && $r->status === 'disetujui')
                                <input type="checkbox" name="pilih[]" value="{{ $r->id }}"
                                       class="lb-bayar-cek" data-amount="{{ (int) $item->amount }}"
                                       aria-label="Tandai {{ $r->number }} sudah dibayar">
                            @endif
                        </td>
                        <td>
                            <div class="rop-num">{{ $r?->number ?: '—' }}</div>
                            <div class="rop-mini">{{ $r?->costCentre?->nama ?: $r?->cost_centre }}</div>
                        </td>
                        <td>{{ $r?->requester?->nama ?: '—' }}</td>
                        {{-- Tujuan transfer yang BERLAKU: lengkapan Finance kalau ada.
                             Selama lembar belum dikirim ke bos, masih bisa dibetulkan
                             di sini — sesudah dikirim dikunci, karena dokumennya sudah
                             beredar dan dua versi kebenaran lebih berbahaya daripada
                             satu versi yang kurang rapi. --}}
                        <td>
                            <span class="rop-rek">{{ $item->tujuanBerlaku() }}</span>
                            @if ($item->tujuanDilengkapi())
                                <div class="rop-mini">dilengkapi {{ $item->pelengkap?->nama ?: 'Finance' }}</div>
                            @endif
                            @if (! $item->tujuanBernomor())
                                <div class="rop-mini" style="color:#B5701A">belum ada nomor rekening</div>
                            @endif
                            {{-- Kolom ini berada DI DALAM form bayar-banyak, dan
                                 HTML melarang form bersarang — parser browser
                                 membuang tag form dalamnya, sehingga tombolnya
                                 malah mengirim form bayar-banyak. Karena itu
                                 form-nya ditaruh di luar tabel dan medannya
                                 ditautkan lewat atribut form="" (HTML5). --}}
                            @if ($bisaUbahTujuan && $r)
                                <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                                    <input type="text" class="rop-input rop-input--mini" name="tujuan"
                                           form="formTujuan{{ $r->id }}"
                                           value="{{ $item->tujuanBerlaku() }}" maxlength="191"
                                           aria-label="Tujuan transfer {{ $r->number }}">
                                    <button class="rop-btn rop-btn--ghost" form="formTujuan{{ $r->id }}"
                                            style="min-height:34px;padding:6px 12px;font-size:12.5px">Simpan</button>
                                </div>
                            @elseif ($r && ! $lembar->sudahDikirim() && $lunas)
                                <div class="rop-mini">terkunci — sudah dibayar</div>
                            @endif
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($r?->description, 70) }}</td>
                        <td class="rop-td-kanan rop-num" style="font-weight:700">
                            {{ $rupiah($item->amount) }}
                            @if ($beda)
                                {{-- Angka di lembar yang sudah dikirim ke bos adalah
                                     yang berlaku; kalau ROP-nya berubah setelah itu,
                                     bedanya disebut, bukan didiamkan. --}}
                                <div class="rop-mini" style="color:#B5701A">
                                    kini {{ $rupiah($r->amount) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($r)
                                @include('rop._pill', ['status' => $r->status, 'pendek' => true])
                            @else
                                <span class="rop-mini">pengajuan hilang</span>
                            @endif
                        </td>
                        <td>
                            {{-- Gambar kecil, bukan cuma tautan: sebelum lembar
                                 dikirim ke bos, Finance perlu memastikan tiap baris
                                 punya invoice yang benar — dan itu tidak bisa dinilai
                                 dari kata "foto". PDF ikut bergambar karena
                                 perendernya sudah ada (hasil render yang sama
                                 dipakai halaman cetak, jadi tidak ada kerja dobel). --}}
                            @php $gbr = $pratinjau[$item->rop_id] ?? null; @endphp
                            @if (! $path)
                                <span class="rop-tanda rop-tanda--awas">tak ada</span>
                            @elseif ($gbr)
                                <a class="rop-lampiran-gbr" href="{{ asset($path) }}"
                                   target="_blank" rel="noopener"
                                   title="Buka lampiran {{ $r?->number }}">
                                    <img src="{{ asset($gbr) }}" alt="Lampiran {{ $r?->number }}" loading="lazy">
                                    @if ($ext === 'pdf')<span class="rop-lampiran-gbr__tag">PDF</span>@endif
                                </a>
                            @else
                                {{-- Ada berkasnya tapi tak bisa dijadikan gambar
                                     (mis. PDF gagal dirender) — jangan diam, beri tautan. --}}
                                <a class="rop-tanda rop-tanda--info"
                                   href="{{ asset($path) }}" target="_blank" rel="noopener">buka berkas</a>
                            @endif
                            @if ($lunas && $r->paid_proof_path)
                                <div class="rop-mini">
                                    <a href="{{ asset($r->paid_proof_path) }}" target="_blank" rel="noopener">bukti transfer</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5">Total tercetak di lembar</td>
                        <td class="rop-td-kanan rop-num" style="font-weight:800">{{ $rupiah($lembar->total) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($bisaBayar->isNotEmpty())
            <div class="rop-card" style="padding:14px;margin-top:14px">
                <div class="rop-label" style="margin-bottom:10px">Tandai dibayar sekaligus</div>
                <p class="rop-hint" style="margin:0 0 12px">
                    Bos sering membayar beberapa pengajuan dalam satu transfer. Centang yang
                    sudah cair, unggah <b>satu</b> bukti transfer — bukti yang sama dipakai
                    untuk semuanya, dan tiap pengajuan tetap dapat jejaknya sendiri.
                </p>

                <div class="rop-bayar-grid">
                    <div class="rop-field" style="margin:0">
                        <label class="rop-label" for="lbTanggal">Tanggal transfer</label>
                        <input type="date" class="rop-input" id="lbTanggal" name="paid_at" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="rop-field" style="margin:0">
                        <label class="rop-label" for="lbBukti">Bukti transfer</label>
                        <input type="file" class="rop-input" id="lbBukti" name="bukti" accept="image/*,application/pdf">
                    </div>
                    <div class="rop-field" style="margin:0">
                        <label class="rop-label" for="lbCatatanBayar">Catatan (opsional)</label>
                        <input type="text" class="rop-input" id="lbCatatanBayar" name="catatan" maxlength="450"
                               placeholder="mis. transfer gabungan BCA">
                    </div>
                </div>

                <div style="display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap">
                    <button class="rop-btn rop-btn--ok rop-btn--mati" id="lbBayarKirim" disabled>
                        @include('rop._ikon', ['n' => 'cek'])
                        <span id="lbBayarLabel">Centang yang sudah dibayar dulu</span>
                    </button>
                </div>
            </div>
        @elseif ($lembar->jumlah_item > 0)
            <div class="rop-note rop-note--hijau" style="margin-top:14px">
                <b>Semua item di lembar ini sudah dibayar.</b>
                Pengaju sudah diberi tahu dan diminta mengunggah nota pertanggungjawaban.
            </div>
        @endif
    </form>

    {{-- Form pelengkap tujuan transfer ditaruh DI LUAR form bayar-banyak.
         Medannya di dalam tabel ditautkan lewat atribut form="" (HTML5).
         Form bersarang itu HTML tidak sah: parser browser membuang tag
         dalamnya, dan tombol Simpan malah mengirim form bayar-banyak. --}}
    @foreach ($lembar->items as $item)
        @php $r = $item->rop; $lunas = $r && in_array($r->status, ['dibayar', 'selesai'], true); @endphp
        @if ($r && ! $lembar->sudahDikirim() && ! $lunas)
            <form method="POST" id="formTujuan{{ $r->id }}"
                  action="{{ route('finance.rop.lembar.tujuan', $lembar->id) }}">
                @csrf
                <input type="hidden" name="rop_id" value="{{ $r->id }}">
            </form>
        @endif
    @endforeach
</div>
@endsection

@section('js')
<script>
(function () {
    var cek   = Array.prototype.slice.call(document.querySelectorAll('.lb-bayar-cek'));
    var semua = document.getElementById('lbSemuaBayar');
    var kirim  = document.getElementById('lbBayarKirim');
    if (! cek.length || ! kirim) return;

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    function segarkan() {
        var terpilih = cek.filter(function (c) { return c.checked; });
        var total = terpilih.reduce(function (t, c) { return t + (Number(c.dataset.amount) || 0); }, 0);
        var label = document.getElementById('lbBayarLabel');

        if (semua) semua.checked = terpilih.length === cek.length;

        if (! terpilih.length) {
            kirim.disabled = true; kirim.classList.add('rop-btn--mati');
            label.textContent = 'Centang yang sudah dibayar dulu';
        } else {
            kirim.disabled = false; kirim.classList.remove('rop-btn--mati');
            label.textContent = 'Tandai ' + terpilih.length + ' dibayar (' + rupiah(total) + ')';
        }
    }

    cek.forEach(function (c) { c.addEventListener('change', segarkan); });
    if (semua) {
        semua.addEventListener('change', function () {
            cek.forEach(function (c) { c.checked = semua.checked; });
            segarkan();
        });
    }

    document.querySelectorAll('#lbBayarForm tbody tr').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('input, select, a, button, label')) return;
            var c = tr.querySelector('.lb-bayar-cek');
            if (c) { c.checked = ! c.checked; segarkan(); }
        });
    });

    // Unggahan bukti bisa beberapa detik — tombol yang diam terasa seperti
    // kliknya tidak masuk, dan klik kedua = pembayaran dicatat dua kali.
    document.getElementById('lbBayarForm').addEventListener('submit', function () {
        kirim.disabled = true;
        document.getElementById('lbBayarLabel').textContent = 'Menyimpan…';
    });

    segarkan();
})();
</script>
@endsection
