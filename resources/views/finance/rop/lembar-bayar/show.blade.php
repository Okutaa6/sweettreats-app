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
            <small>{{ $bisaBayar->count() }} masih menunggu uang</small>
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

    {{-- Penuntun langkah (pola-interaksi P5/P6: layar mengajarkan langkah
         berikutnya, tidak menunggu orangnya hafal). Langkah yang SEDANG
         berjalan disorot; yang sudah lewat diberi centang. Lembar bayar
         dipakai mungkin seminggu sekali — terlalu jarang untuk dihafal,
         terlalu sering untuk dibuatkan dokumen sendiri. --}}
    @php
        $adaYangDibayar = $dibayar > 0;
        $langkah = [
            ['n' => 1, 'judul' => 'Cetak jadi PDF',        'ket' => 'tombol di kanan atas, pilih A4',      'selesai' => true],
            ['n' => 2, 'judul' => 'Kirim ke bos',          'ket' => 'satu berkas lewat WhatsApp',          'selesai' => $lembar->sudahDikirim()],
            ['n' => 3, 'judul' => 'Tandai sudah dikirim',  'ket' => 'supaya ketahuan mana yang menggantung', 'selesai' => $lembar->sudahDikirim()],
            ['n' => 4, 'judul' => 'Tandai dibayar',        'ket' => 'centang yang cair, unggah satu bukti', 'selesai' => $dibayar >= $lembar->jumlah_item && $lembar->jumlah_item > 0],
        ];
        // Langkah aktif = yang pertama belum selesai (langkah 1 selalu dianggap
        // tersedia, jadi sorotan jatuh ke yang benar-benar menunggu tindakan).
        $aktif = $lembar->sudahDikirim() ? 4 : 2;
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
                        {{-- pay_to_text = nomor rekening. Utuh, tidak dipotong. --}}
                        <td><span class="rop-rek">{{ $r?->namaPenerima() ?: '—' }}</span></td>
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
                            @if (! $path)
                                <span class="rop-tanda rop-tanda--awas">tak ada</span>
                            @else
                                <a class="rop-tanda {{ in_array($ext, ['jpg','jpeg','png','webp','gif'], true) ? 'rop-tanda--ok' : 'rop-tanda--info' }}"
                                   href="{{ asset($path) }}" target="_blank" rel="noopener">
                                    {{ in_array($ext, ['jpg','jpeg','png','webp','gif'], true) ? 'foto' : 'PDF' }}
                                </a>
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
