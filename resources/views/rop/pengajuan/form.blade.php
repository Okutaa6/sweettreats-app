{{--
    Form pengajuan (HP-first).

    Tiga hal yang menjawab keluhan nyata pengaju:
      1. Baris "Ajukan lagi" di atas form — belanja rutin tidak perlu diisi
         dari nol lagi dan lagi.
      2. Nomor yang akan didapat ditampilkan di header, jadi orang tahu
         pengajuannya bakal punya identitas sebelum menekan kirim.
      3. Peringatan bayar dobel yang harus diakui dulu (klik pertama mengakui,
         klik kedua baru mengirim) — bukan blokir, tapi juga bukan sekadar
         tulisan yang bisa dilewati tanpa dibaca.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $dobel = session('rop_dobel');
    $ccTerpilih = old('cost_centre', $dari?->cost_centre ?: $ccDefault);
    $ccNama = $costCentres->firstWhere('kode', $ccTerpilih)?->nama;
@endphp

<div class="rop-surface">

    <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px">
        <a class="rop-btn rop-btn--ghost" style="min-height:40px;padding:8px 12px"
           href="{{ route('rop.pengajuan.index') }}">
            @include('rop._ikon', ['n' => 'kembali'])
        </a>
        <div>
            <h1 style="font-size:22px;font-weight:800;margin:0">
                {{ $dari && ! $ulangDari ? 'Ajukan Lagi' : ($ulangDari ? 'Ajukan Ulang' : 'Pengajuan Baru') }}
            </h1>
            <div class="rop-sub rop-num">{{ $nomorBerikutnya }} &middot; {{ $ccNama ?: 'pilih cost centre' }}</div>
        </div>
    </div>

    @include('layouts.notif')

    @if ($dari && ! $ulangDari)
        <div class="rop-note rop-note--hijau" style="margin-bottom:14px">
            <b>Data disalin dari pengajuan sebelumnya.</b>
            Yang perlu kamu cek: <b>jumlah</b> dan <b>butuh kapan</b>.
        </div>
    @endif

    @if ($ulangDari)
        <div class="rop-note rop-note--kuning" style="margin-bottom:14px">
            <b>Pengajuan ulang</b> dari {{ $dari->number }} yang ditolak.
            @if ($dari->reject_reason) Alasan sebelumnya: {{ $dari->reject_reason }} @endif
        </div>
    @endif

    {{-- Pintasan: tap = form terisi. Sengaja di ATAS form, bukan di bawah —
         kalau di bawah, orang sudah terlanjur mengetik dari nol. --}}
    @if ($terakhir->isNotEmpty() && ! $dari)
        <div class="rop-label" style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
            @include('rop._ikon', ['n' => 'ulang', 's' => 14])
            Ajukan lagi — tap untuk isi otomatis
        </div>
        <div class="rop-chip-row" style="margin-bottom:18px">
            @foreach ($terakhir as $t)
                <a class="rop-ulang" href="{{ route('rop.pengajuan.create', ['dari' => $t->id]) }}">
                    <b>{{ $t->namaPenerima() }}</b>
                    <i class="rop-num">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</i>
                    <small>{{ $t->description }}</small>
                </a>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('rop.pengajuan.store') }}" enctype="multipart/form-data" id="ropForm">
        @csrf
        @if ($ulangDari)<input type="hidden" name="resubmit_of" value="{{ $ulangDari }}">@endif
        <input type="hidden" name="abaikan_dobel" id="ropAbaikan" value="{{ $dobel ? '' : '' }}">

        <div class="rop-field">
            <label class="rop-label" for="ropVendor">Dibayarkan kepada</label>
            <input class="rop-input" list="ropVendorList" id="ropVendor" name="pay_to_text"
                   placeholder="Pilih atau ketik nama toko / vendor" autocomplete="off"
                   value="{{ old('pay_to_text', $dari?->namaPenerima()) }}">
            <datalist id="ropVendorList">
                @foreach ($vendors as $v)
                    <option value="{{ $v->nama }}"></option>
                @endforeach
            </datalist>
            {{-- Kalau yang diketik persis sama dengan vendor terdaftar, server
                 yang menautkannya ke master; pengaju tidak perlu tahu bedanya. --}}
            <div class="rop-hint">Boleh ketik bebas kalau vendornya belum terdaftar.</div>
        </div>

        <div class="rop-field">
            <label class="rop-label" for="ropKet">Untuk apa</label>
            <textarea class="rop-textarea" id="ropKet" name="description" maxlength="500" required
                      placeholder="Contoh: service AC poli 2, kompresor mati">{{ old('description', $dari?->description) }}</textarea>
        </div>

        <div class="rop-field">
            <label class="rop-label" for="ropJumlah">Jumlah</label>
            <div class="rop-uang">
                <span>Rp</span>
                <input id="ropJumlahTampil" inputmode="numeric" autocomplete="off" placeholder="0"
                       value="{{ old('amount', $dari ? (int) $dari->amount : '') }}">
            </div>
            <input type="hidden" name="amount" id="ropJumlah" value="{{ old('amount', $dari ? (int) $dari->amount : '') }}">
            <div class="rop-chip-row" style="margin-top:8px">
                <button type="button" class="rop-chip" data-rop-tambah="50000">+50rb</button>
                <button type="button" class="rop-chip" data-rop-tambah="100000">+100rb</button>
                <button type="button" class="rop-chip" data-rop-tambah="500000">+500rb</button>
                <button type="button" class="rop-chip" data-rop-tambah="0">Hapus</button>
            </div>

            {{-- Saran, bukan larangan: batas ini aturan operasional yang bisa
                 bergeser, dan pengaju yang tahu konteksnya (vendor minta
                 transfer, misalnya). Muncul hanya saat relevan. --}}
            <div class="rop-note rop-note--kuning" id="ropPettyHint"
                 style="display:none;margin-top:10px">
                Nominal di bawah Rp {{ number_format(\App\Services\RopService::AMBANG_PETTY_CASH, 0, ',', '.') }}
                biasanya lebih cepat lewat <b>petty cash cabang</b> — tunai, langsung dicatat kasir,
                tanpa menunggu transfer. Pakai pengajuan ini kalau memang harus dibayar transfer.
            </div>
        </div>

        <div class="rop-field">
            <label class="rop-label" for="ropButuh">Butuh kapan</label>
            <div class="rop-chip-row" style="margin-bottom:8px">
                <button type="button" class="rop-chip" data-rop-hari="0">Hari ini</button>
                <button type="button" class="rop-chip" data-rop-hari="1">Besok</button>
                <button type="button" class="rop-chip" data-rop-hari="3">3 hari</button>
                <button type="button" class="rop-chip" data-rop-hari="7">1 minggu</button>
            </div>
            <input type="date" class="rop-input" id="ropButuh" name="needed_by" value="{{ old('needed_by') }}">
        </div>

        <div class="rop-field">
            <label class="rop-label" for="ropCc">Dibebankan ke</label>
            <select class="rop-select" id="ropCc" name="cost_centre" required>
                @foreach ($costCentres as $cc)
                    <option value="{{ $cc->kode }}" {{ $ccTerpilih === $cc->kode ? 'selected' : '' }}>
                        {{ $cc->tipe === 'cabang' ? 'Cabang ' : 'Kantor · ' }}{{ $cc->nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="rop-field">
            <span class="rop-label" style="display:block;margin-bottom:7px">Foto penawaran / invoice</span>
            <div class="rop-file-btns">
                <button type="button" class="rop-chip" data-rop-ambil="kamera" data-rop-untuk="lampiran">
                    @include('rop._ikon', ['n' => 'kamera', 's' => 16])
                    Kamera
                </button>
                <button type="button" class="rop-chip" data-rop-ambil="galeri" data-rop-untuk="lampiran">
                    @include('rop._ikon', ['n' => 'klip', 's' => 16])
                    Galeri / berkas
                </button>
            </div>
            <input type="file" class="rop-file-asli" id="ropLampiran" name="lampiran"
                   accept="image/*,application/pdf" data-rop-file="lampiran">
            <input type="hidden" name="lampiran_base64" data-rop-b64="lampiran">
            <div class="rop-hint" data-rop-preview="lampiran"></div>
        </div>

        <label class="rop-field rop-toggle">
            <input type="checkbox" name="is_urgent" value="1" id="ropUrgent" {{ old('is_urgent') ? 'checked' : '' }}>
            <span>
                <b>Darurat</b>
                <span class="rop-hint">Pakai seperlunya — kalau semua darurat, tidak ada yang darurat.</span>
            </span>
        </label>

        {{-- Peringatan dobel: nomor & pengaju disebut supaya bisa dicek, bukan
             sekadar "sudah ada yang mirip". --}}
        @if ($dobel)
            <div class="rop-note rop-note--kuning" style="margin-bottom:12px">
                <b>Sudah ada pengajuan serupa.</b><br>
                {{ $dobel['number'] }} oleh {{ $dobel['pengaju'] }} —
                <span class="rop-num">Rp {{ number_format($dobel['amount'], 0, ',', '.') }}</span>,
                {{ $dobel['status'] }}.
                <div style="margin-top:8px">
                    <a class="rop-chip" href="{{ route('rop.pengajuan.show', $dobel['id']) }}">Lihat pengajuan itu</a>
                </div>
            </div>
        @endif

        <div class="rop-dock">
            <button type="submit" class="rop-btn {{ $dobel ? 'rop-btn--warn' : 'rop-btn--primary' }} rop-btn--block"
                    id="ropKirim" data-rop-perlu-akui="{{ $dobel ? '1' : '' }}">
                @include('rop._ikon', ['n' => 'kirim'])
                <span id="ropKirimTeks">{{ $dobel ? 'Tetap kirim — sudah saya cek' : 'Kirim pengajuan' }}</span>
            </button>
            <div class="rop-hint" id="ropKurang" style="text-align:center"></div>
        </div>
    </form>
</div>
@endsection

@section('js')
<script src="{{ asset('assets/js/rop-foto.js') }}?v={{ @filemtime(public_path('assets/js/rop-foto.js')) ?: '1' }}"></script>
<script>
(function () {
    var tampil = document.getElementById('ropJumlahTampil');
    var nilai  = document.getElementById('ropJumlah');
    var butuh  = document.getElementById('ropButuh');
    var kirim  = document.getElementById('ropKirim');
    var kurang = document.getElementById('ropKurang');
    var form   = document.getElementById('ropForm');

    var AMBANG_PETTY = {{ \App\Services\RopService::AMBANG_PETTY_CASH }};
    var petty = document.getElementById('ropPettyHint');

    function format(n) { return n ? n.toLocaleString('id-ID') : ''; }
    function angka() { return parseInt((nilai.value || '0'), 10) || 0; }
    function cekPetty() {
        var n = angka();
        petty.style.display = (n > 0 && n < AMBANG_PETTY) ? '' : 'none';
    }
    function setAngka(n) {
        nilai.value = n > 0 ? n : '';
        tampil.value = format(n);
        cekLengkap();
        cekPetty();
    }

    // Ribuan otomatis: yang dibaca server tetap angka polos di input hidden.
    tampil.addEventListener('input', function () {
        var bersih = parseInt(tampil.value.replace(/\D/g, ''), 10) || 0;
        nilai.value = bersih > 0 ? bersih : '';
        tampil.value = format(bersih);
        cekLengkap();
        cekPetty();
    });
    setAngka(angka());

    document.querySelectorAll('[data-rop-tambah]').forEach(function (b) {
        b.addEventListener('click', function () {
            var t = parseInt(b.getAttribute('data-rop-tambah'), 10);
            setAngka(t === 0 ? 0 : angka() + t);
        });
    });

    document.querySelectorAll('[data-rop-hari]').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = new Date();
            d.setDate(d.getDate() + parseInt(b.getAttribute('data-rop-hari'), 10));
            butuh.value = d.toISOString().slice(0, 10);
            document.querySelectorAll('[data-rop-hari]').forEach(function (x) {
                x.classList.remove('rop-chip--pilih');
            });
            b.classList.add('rop-chip--pilih');
            cekLengkap();
        });
    });

    // Tombol kirim redup + menyebut APA yang masih kurang, bukan cuma mati
    // tanpa penjelasan.
    function cekLengkap() {
        var perlu = [];
        if (!document.getElementById('ropVendor').value.trim()) perlu.push('dibayarkan kepada');
        if (!document.getElementById('ropKet').value.trim()) perlu.push('untuk apa');
        if (angka() < 1) perlu.push('jumlah');

        if (perlu.length) {
            kirim.classList.add('rop-btn--mati');
            kurang.textContent = 'Masih perlu diisi: ' + perlu.join(', ');
        } else {
            kirim.classList.remove('rop-btn--mati');
            kurang.textContent = '';
        }
    }
    ['ropVendor', 'ropKet'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', cekLengkap);
    });
    cekLengkap();

    // Peringatan dobel: klik pertama hanya MENGAKUI peringatan, klik kedua
    // baru mengirim. Tanpa jeda ini, peringatan cuma jadi tombol yang ditekan
    // refleks.
    var perluAkui = kirim.getAttribute('data-rop-perlu-akui') === '1';
    var sudahAkui = false;
    form.addEventListener('submit', function (e) {
        if (kirim.classList.contains('rop-btn--mati')) { e.preventDefault(); return; }
        if (perluAkui && !sudahAkui) {
            e.preventDefault();
            sudahAkui = true;
            document.getElementById('ropAbaikan').value = '1';
            document.getElementById('ropKirimTeks').textContent = 'Ya, kirim pengajuan ini';
            kirim.classList.remove('rop-btn--warn');
            kirim.classList.add('rop-btn--primary');
            return;
        }
        // Benar-benar terkirim: tombol bilang sedang bekerja + kebal klik dobel
        // (pola-interaksi P2 — menonaktifkan DI DALAM handler submit tidak
        // menghentikan pengiriman yang sudah berjalan).
        kirim.disabled = true;
        document.getElementById('ropKirimTeks').textContent = 'Mengirim…';
    });
})();
</script>
@endsection
