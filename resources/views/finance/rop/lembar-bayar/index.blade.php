{{--
    Lembar Bayar — layar Finance (zona burgundy, desktop).

    Pertanyaan yang dijawab: "apa saja yang mau saya mintakan uangnya ke bos
    sekali jalan?" Jadi yang di atas adalah daftar centang + total berjalan,
    bukan tabel yang enak dibaca. Riwayat lembar ada di bawahnya karena yang
    dicari di sana cuma "yang tadi sudah dikirim belum".

    Bedanya dengan /finance/rop/rekap: itu rekap AKUNTANSI bulanan (total per
    cost centre + draf Accurate). Yang ini dokumen minta uang. Layar sendiri.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/rop.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/rop.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp

<div class="rop-fin">

    <div class="rop-fin__head">
        <div>
            <h1>Lembar Bayar</h1>
            <div class="rop-sub">
                Satu berkas cetak untuk bos — daftar, total, terbilang, dan lampirannya.
                Pengganti kirim berkas satu per satu.
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop') }}">
                @include('rop._ikon', ['n' => 'kembali'])
                Antrian
            </a>
            <a class="rop-btn rop-btn--ghost" href="{{ route('finance.rop.rekap') }}">
                @include('rop._ikon', ['n' => 'grafik'])
                Rekap
            </a>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ══ Pilih isi lembar ══ --}}
    <form method="POST" action="{{ route('finance.rop.lembar.store') }}" id="lbForm">
        @csrf

        <div class="rop-fin-stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <div class="rop-fin-stat rop-fin-stat--utama">
                <span class="rop-label">Dipilih</span>
                <b class="rop-num" id="lbJumlah">0</b>
                <small class="rop-num" id="lbTotal">Rp 0</small>
            </div>
            <div class="rop-fin-stat">
                <span class="rop-label">Layak masuk lembar</span>
                <b class="rop-num">{{ $layak->count() }}</b>
                <small class="rop-num">{{ $rupiah($layak->sum('amount')) }} belum dibayar</small>
            </div>
        </div>

        <div class="rop-tabel-wrap">
            <table class="rop-tabel">
                <colgroup>
                    <col style="width:42px"><col style="width:13%"><col style="width:11%">
                    <col style="width:19%"><col style="width:17%"><col style="width:17%">
                    <col style="width:11%"><col style="width:9%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="rop-cek-cell">
                            {{-- Pilih semua: kebiasaan Finance memang "semua yang
                                 antre hari ini", jadi jalur tercepatnya satu klik. --}}
                            <input type="checkbox" id="lbSemua" aria-label="Pilih semua">
                        </th>
                        <th>Nomor</th>
                        <th>Pengaju</th>
                        <th>Dibayarkan kepada</th>
                        <th>Untuk apa</th>
                        <th>Akun beban</th>
                        <th class="rop-td-kanan">Nominal</th>
                        <th>Lampiran</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($layak as $r)
                    <tr class="{{ $r->is_urgent ? 'rop-baris--darurat' : ($r->butuhMendesak() ? 'rop-baris--telat' : '') }}">
                        <td class="rop-cek-cell">
                            <input type="checkbox" name="pilih[]" value="{{ $r->id }}"
                                   class="lb-cek" data-amount="{{ (int) $r->amount }}"
                                   data-butuh-akun="{{ (! $r->coa_no && isset($akun[$r->id]) && $akun[$r->id] !== []) ? 1 : 0 }}"
                                   aria-label="Masukkan {{ $r->number }}">
                        </td>
                        <td>
                            <div class="rop-num">{{ $r->number }}</div>
                            <div class="rop-mini">
                                @include('rop._pill', ['status' => $r->status, 'pendek' => true])
                            </div>
                            @if ($r->is_urgent)<span class="rop-badge-darurat" style="margin-top:4px">Darurat</span>@endif
                        </td>
                        <td>
                            {{ $r->requester?->nama ?: '—' }}
                            <div class="rop-mini">{{ $r->costCentre?->nama ?: $r->cost_centre }}</div>
                        </td>
                        {{-- pay_to_text = nomor rekening yang dipakai bos ("VA MANDIRI
                             - 896 085888064313"). Ditampilkan utuh, tidak dipotong.

                             Kalau pengaju menulisnya TANPA nomor rekening, Finance
                             melengkapinya di sini — di lembar, bukan di pengajuan:
                             pengajuan terkunci setelah dikirim, dan menambal
                             tulisan pengaju bikin jejaknya berbohong. --}}
                        @php $bernomor = \App\Models\RopPaymentSheetItem::terlihatBernomor($r->namaPenerima()); @endphp
                        <td>
                            <span class="rop-rek">{{ $r->namaPenerima() }}</span>
                            @unless ($bernomor)
                                <input type="text" class="rop-input rop-input--mini lb-tujuan"
                                       name="tujuan[{{ $r->id }}]"
                                       value="{{ $tujuanSebelumnya[$r->id] ?? $r->namaPenerima() }}"
                                       maxlength="191"
                                       placeholder="{{ $r->namaPenerima() }} — BCA 1234567890">
                                <div class="rop-mini" style="color:#B5701A">
                                    belum ada nomor rekening — lengkapi supaya bos bisa langsung transfer
                                </div>
                            @endunless
                        </td>
                        <td>
                            {{ \Illuminate\Support\Str::limit($r->description, 70) }}
                            @if ($r->needed_by)
                                <div class="rop-mini">butuh {{ $r->needed_by->translatedFormat('d M Y') }}</div>
                            @endif
                        </td>
                        <td class="rop-td-akun">
                            @if ($r->coa_no)
                                <span class="rop-num">{{ $r->coa_no }}</span>
                                <div class="rop-mini">{{ $r->coa_name }}</div>
                            @elseif (($akun[$r->id] ?? []) === [])
                                {{-- Daftar akun kosong = staging Accurate belum ada di
                                     server ini. Dikatakan terang-terangan, bukan
                                     dibiarkan tampak seperti dropdown yang rusak. --}}
                                <div class="rop-mini" style="color:#B5701A">
                                    Daftar akun belum tersedia di server ini — lembar tetap bisa dibuat.
                                </div>
                            @else
                                <select class="rop-select rop-select--mini lb-akun" name="akun[{{ $r->id }}]">
                                    <option value="">— pilih akun beban —</option>
                                    @foreach ($akun[$r->id] as $a)
                                        <option value="{{ $a['no'] }}"
                                            {{ ($saran[$r->id]['no'] ?? null) === $a['no'] ? 'selected' : '' }}>
                                            {{ $a['no'] }} — {{ $a['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($saran[$r->id] ?? null)
                                    <div class="rop-mini">terakhir dipakai untuk penerima ini</div>
                                @endif
                            @endif
                        </td>
                        <td class="rop-td-kanan rop-num" style="font-weight:700">{{ $rupiah($r->amount) }}</td>
                        <td>
                            @php
                                $path = $r->attachment_path;
                                $ext  = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;
                            @endphp
                            @if (! $path)
                                <span class="rop-tanda rop-tanda--awas">tak ada</span>
                            @elseif (in_array($ext, ['jpg','jpeg','png','webp','gif'], true))
                                <span class="rop-tanda rop-tanda--ok">foto</span>
                            @else
                                <span class="rop-tanda rop-tanda--info">PDF</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="padding:44px;text-align:center;color:var(--rop-muted)">
                            <b style="display:block;font-size:15px;color:var(--rop-ink)">Tidak ada yang perlu dimintakan</b>
                            Semua pengajuan sudah dibayar atau ditutup. Lembar bayar dibuat dari
                            pengajuan yang menunggu ACC dan yang sudah disetujui tapi belum dibayar.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($layak->isNotEmpty())
            <div class="rop-card" style="padding:14px;margin-top:14px">
                <label class="rop-label" for="lbCatatan">Catatan untuk bos (opsional)</label>
                <input type="text" class="rop-input" id="lbCatatan" name="catatan" maxlength="500"
                       placeholder="mis. mohon ditransfer sebelum Jumat">

                <div class="rop-note rop-note--kuning" style="margin-top:12px">
                    <b>Membuat lembar = menyetujui isinya.</b>
                    Pengajuan yang masih menunggu ACC akan naik ke <i>disetujui</i> dan pengajunya
                    diberi tahu. Yang sudah disetujui tidak berubah.
                </div>

                <div style="display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap">
                    {{-- Tombol mati wajib bilang kenapa (pola-interaksi P3) — labelnya
                         sendiri yang bicara, bukan disabled yang diam. --}}
                    <button class="rop-btn rop-btn--ok rop-btn--mati" id="lbKirim" disabled>
                        @include('rop._ikon', ['n' => 'cek'])
                        <span id="lbKirimLabel">Pilih pengajuan dulu</span>
                    </button>
                    <span class="rop-hint" id="lbHint" style="margin:0"></span>
                </div>
            </div>
        @endif
    </form>

    {{-- ══ Lembar terdahulu ══ --}}
    <h2 style="font-size:17px;font-weight:800;margin:26px 0 10px">Lembar terdahulu</h2>

    <div class="rop-tabel-wrap">
        <table class="rop-tabel">
            <colgroup>
                <col style="width:17%"><col style="width:16%"><col style="width:10%">
                <col style="width:15%"><col style="width:17%"><col style="width:21%">
                <col style="width:52px">
            </colgroup>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Dibuat</th>
                    <th class="rop-td-kanan">Item</th>
                    <th class="rop-td-kanan">Total</th>
                    <th>Dikirim ke bos</th>
                    <th>Sudah dibayar</th>
                    <th aria-hidden="true"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($lembar as $l)
                @php $dibayar = $l->jumlahDibayar(); @endphp
                <tr data-lb-buka="{{ route('finance.rop.lembar.show', $l->id) }}" title="Klik untuk lihat isi lembar">
                    <td class="rop-num" style="font-weight:700">{{ $l->number }}</td>
                    <td>
                        {{ $l->dibuat_at?->translatedFormat('d M Y, H:i') }}
                        <div class="rop-mini">{{ $l->pembuat?->nama ?: '—' }}</div>
                    </td>
                    <td class="rop-td-kanan rop-num">{{ $l->jumlah_item }}</td>
                    <td class="rop-td-kanan rop-num" style="font-weight:700">{{ $rupiah($l->total) }}</td>
                    <td>
                        @if ($l->sudahDikirim())
                            <span class="rop-tanda rop-tanda--ok">{{ $l->dikirim_at->translatedFormat('d M Y') }}</span>
                        @else
                            <span class="rop-tanda rop-tanda--awas">belum</span>
                        @endif
                    </td>
                    <td>
                        <span class="rop-num">{{ $dibayar }} / {{ $l->jumlah_item }}</span>
                        <div class="rop-bar" style="width: {{ $l->jumlah_item > 0 ? round($dibayar / $l->jumlah_item * 100) : 0 }}%"></div>
                    </td>
                    <td class="rop-chev-cell">@include('rop._ikon', ['n' => 'lanjut', 's' => 18])</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="padding:40px;text-align:center;color:var(--rop-muted)">
                        Belum ada lembar bayar. Centang pengajuan di atas, lalu tekan
                        <b>Buat Lembar Bayar</b> — nomornya keluar otomatis.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($lembar->hasPages())
        <div style="margin-top:14px">{{ $lembar->links() }}</div>
    @endif
</div>
@endsection

@section('js')
<script>
(function () {
    var cek   = Array.prototype.slice.call(document.querySelectorAll('.lb-cek'));
    var semua = document.getElementById('lbSemua');
    var kirim = document.getElementById('lbKirim');
    if (! cek.length) return;

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }

    // Baris yang dicentang tapi akun bebannya belum dipilih. Dihitung ulang
    // tiap perubahan supaya alasan tombol mati selalu jujur.
    function belumBerakun() {
        return cek.filter(function (c) {
            if (! c.checked || c.getAttribute('data-butuh-akun') !== '1') return false;
            var sel = c.closest('tr').querySelector('.lb-akun');
            return ! sel || ! sel.value;
        }).length;
    }

    function segarkan() {
        var terpilih = cek.filter(function (c) { return c.checked; });
        var total = terpilih.reduce(function (t, c) { return t + (Number(c.dataset.amount) || 0); }, 0);

        document.getElementById('lbJumlah').textContent = terpilih.length;
        document.getElementById('lbTotal').textContent = rupiah(total);
        if (semua) semua.checked = terpilih.length === cek.length && cek.length > 0;

        if (! kirim) return;
        var label = document.getElementById('lbKirimLabel');
        var hint  = document.getElementById('lbHint');
        var kosong = belumBerakun();

        if (! terpilih.length) {
            kirim.disabled = true; kirim.classList.add('rop-btn--mati');
            label.textContent = 'Pilih pengajuan dulu';
            hint.textContent = '';
        } else if (kosong) {
            kirim.disabled = true; kirim.classList.add('rop-btn--mati');
            label.textContent = 'Pilih akun beban dulu';
            hint.textContent = kosong + ' baris tercentang belum punya akun beban.';
        } else {
            kirim.disabled = false; kirim.classList.remove('rop-btn--mati');
            label.textContent = 'Buat Lembar Bayar (' + terpilih.length + ' item · ' + rupiah(total) + ')';
            hint.textContent = '';
        }
    }

    cek.forEach(function (c) { c.addEventListener('change', segarkan); });
    document.querySelectorAll('.lb-akun').forEach(function (s) {
        s.addEventListener('change', function () {
            // Memilih akun pada baris yang belum tercentang hampir selalu berarti
            // "baris ini ikut" — dicentangkan sekalian daripada dibiarkan diam.
            var c = s.closest('tr').querySelector('.lb-cek');
            if (c && ! c.checked) c.checked = true;
            segarkan();
        });
    });

    if (semua) {
        semua.addEventListener('change', function () {
            cek.forEach(function (c) { c.checked = semua.checked; });
            segarkan();
        });
    }

    // Klik di mana pun pada baris ikut mencentang — kolom kotak centang sempit,
    // dan di sini yang dilakukan Finance memang mencentang, bukan membaca.
    document.querySelectorAll('#lbForm tbody tr').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('input, select, a, button, label')) return;
            var c = tr.querySelector('.lb-cek');
            if (c) { c.checked = ! c.checked; segarkan(); }
        });
    });

    // Kirim sekali saja — membuat dua lembar karena klik dobel berarti dua nomor
    // terpakai dan satu lembar hantu yang harus dijelaskan ke bos.
    var form = document.getElementById('lbForm');
    if (form) {
        form.addEventListener('submit', function () {
            if (kirim) {
                kirim.disabled = true;
                document.getElementById('lbKirimLabel').textContent = 'Membuat lembar…';
            }
        });
    }

    segarkan();

    // Baris lembar terdahulu = pintu masuk isinya (pola chevron P1).
    document.querySelectorAll('[data-lb-buka]').forEach(function (tr) {
        tr.addEventListener('click', function (e) {
            if (e.target.closest('button, a, form, input, select, label')) return;
            window.location = tr.getAttribute('data-lb-buka');
        });
    });
})();
</script>
@endsection
