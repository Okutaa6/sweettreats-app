{{--
    Panel detail satu pengajuan — dipakai dua tempat: halaman detail di HP dan
    kolom kanan master-detail di layar lebar. Satu berkas supaya isinya tidak
    pernah beda antara dua tampilan itu.

    $rop  : RopRequest (sudah di-load relasinya)
    $logs : koleksi RopStatusLog
    $milikSendiri : boolean — aksi hanya muncul untuk pemiliknya
--}}
@php
    $rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');

    // Timeline empat langkah tetap: yang belum terjadi TETAP ditampilkan
    // (redup) supaya pengaju tahu masih ada tahap berikutnya, bukan mengira
    // prosesnya sudah selesai.
    $capai = fn (string $st) => $logs->firstWhere('ke_status', $st);
    $langkah = [
        ['diajukan',  'Diajukan',          $capai('diajukan')],
        ['disetujui', 'Disetujui Finance', $capai('disetujui')],
        ['dibayar',   'Dana ditransfer',   $capai('dibayar')],
        ['selesai',   'Nota diterima — selesai', $capai('selesai')],
    ];
    $ditolak = $capai('ditolak');
    $kadaluarsa = $capai('kadaluarsa');

    $lampiran = array_filter([
        'Penawaran / invoice'   => $rop->attachment_path,
        'Bukti transfer'        => $rop->paid_proof_path,
        'Nota pertanggungjawaban' => $rop->nota_path,
    ]);
@endphp

<div class="rop-card rop-hero">
    <div class="rop-hero__vendor">{{ $rop->namaPenerima() }}</div>
    <div class="rop-hero__nominal rop-num">{{ $rupiah($rop->amount) }}</div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        @include('rop._pill', ['status' => $rop->status])
        @if ($rop->is_urgent)<span class="rop-badge-darurat">Darurat</span>@endif
    </div>

    <p class="rop-hero__ket">{{ $rop->description }}</p>

    <div class="rop-hero__grid">
        <div>
            <span class="rop-label">Butuh</span>
            <b class="rop-num">{{ $rop->needed_by?->translatedFormat('d M Y') ?: 'Tidak ditentukan' }}</b>
        </div>
        <div>
            <span class="rop-label">Dibebankan</span>
            <b>{{ $rop->costCentre?->nama ?: $rop->cost_centre }}</b>
        </div>
        <div>
            <span class="rop-label">Nomor</span>
            <b class="rop-num">{{ $rop->number }}</b>
        </div>
        @if ($rop->coa_no)
            <div>
                <span class="rop-label">Akun beban</span>
                <b>{{ $rop->coa_no }} — {{ $rop->coa_name }}</b>
            </div>
        @endif
    </div>
</div>

@if ($ditolak && $rop->reject_reason)
    <div class="rop-note rop-note--merah" style="margin-top:12px">
        <b>Ditolak.</b> {{ $rop->reject_reason }}
        <div style="margin-top:4px;opacity:.85">
            {{ $ditolak->actor?->nama ?: 'Finance' }} &middot;
            {{ $ditolak->created_at?->translatedFormat('d M Y') }}
        </div>
    </div>
@endif

@if ($kadaluarsa)
    <div class="rop-note rop-note--kuning" style="margin-top:12px">
        <b>Kadaluarsa.</b> Pengajuan ini lewat 30 hari tanpa keputusan dan ditutup otomatis.
        Ajukan ulang kalau masih dibutuhkan.
    </div>
@endif

<div class="rop-card" style="padding:16px;margin-top:12px">
    <div class="rop-label" style="margin-bottom:12px">Perjalanan pengajuan</div>
    <ul class="rop-time">
        @foreach ($langkah as [$kode, $judul, $log])
            <li class="{{ $log ? 'is-jalan' : '' }}">
                @if ($log)
                    <span class="rop-tanda-jalan">@include('rop._ikon', ['n' => 'cek', 's' => 12])</span>
                @endif
                <b>{{ $judul }}</b>
                <span>
                    @if ($log)
                        {{ $log->actor?->nama ?: 'Sistem' }} &middot;
                        {{ $log->created_at?->translatedFormat('d M Y') }}
                    @else
                        belum
                    @endif
                </span>
            </li>
        @endforeach
        @if ($ditolak)
            <li class="is-tolak">
                <b>Ditolak</b>
                <span>{{ $ditolak->actor?->nama ?: 'Finance' }} &middot;
                      {{ $ditolak->created_at?->translatedFormat('d M Y') }}</span>
            </li>
        @endif
    </ul>
</div>

@if ($lampiran)
    <div class="rop-card" style="padding:16px;margin-top:12px">
        <div class="rop-label" style="margin-bottom:10px">Lampiran</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            @foreach ($lampiran as $label => $path)
                <a class="rop-thumb" href="{{ asset($path) }}" target="_blank" rel="noopener">
                    @if (preg_match('/\.(jpe?g|png|webp)$/i', $path))
                        <img src="{{ asset($path) }}" alt="">
                    @else
                        @include('rop._ikon', ['n' => 'klip', 's' => 22])
                    @endif
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if ($milikSendiri ?? false)
    @if ($rop->status === 'dibayar')
        {{-- Aksi paling penting di status ini: menutup loop pertanggungjawaban. --}}
        <form class="rop-card" style="padding:16px;margin-top:12px" method="POST"
              action="{{ route('rop.pengajuan.selesai', $rop->id) }}" enctype="multipart/form-data">
            @csrf
            <div style="font-weight:800;margin-bottom:4px">Barang / dana sudah diterima?</div>
            <p style="color:var(--rop-muted);font-size:13.5px;margin-bottom:12px">
                Upload nota atau kwitansi pembeliannya, lalu pengajuan ini ditutup.
            </p>
            <div class="rop-field">
                <div class="rop-file-btns">
                    <button type="button" class="rop-chip" data-rop-ambil="kamera" data-rop-untuk="nota">
                        @include('rop._ikon', ['n' => 'kamera', 's' => 16])
                        Foto nota
                    </button>
                    <button type="button" class="rop-chip" data-rop-ambil="galeri" data-rop-untuk="nota">
                        @include('rop._ikon', ['n' => 'klip', 's' => 16])
                        Galeri / berkas
                    </button>
                </div>
                <input type="file" name="nota" class="rop-file-asli"
                       accept="image/*,application/pdf" data-rop-file="nota">
                <input type="hidden" name="nota_base64" data-rop-b64="nota">
                <div class="rop-hint" data-rop-preview="nota"></div>
            </div>
            <input type="text" name="catatan" class="rop-input" maxlength="500"
                   placeholder="Catatan (opsional)" style="margin-bottom:12px">
            <button class="rop-btn rop-btn--ok rop-btn--block">
                @include('rop._ikon', ['n' => 'kamera'])
                Barang diterima — upload nota
            </button>
        </form>
    @endif

    @if ($rop->bisaDiajukanUlang())
        <a class="rop-btn rop-btn--primary rop-btn--block" style="margin-top:12px"
           href="{{ route('rop.pengajuan.create', ['dari' => $rop->id]) }}">
            @include('rop._ikon', ['n' => 'ulang'])
            Ajukan ulang — data terisi otomatis
        </a>
    @endif

    @if ($rop->bisaDibatalkan())
        <form method="POST" action="{{ route('rop.pengajuan.batal', $rop->id) }}" style="margin-top:12px"
              onsubmit="return confirm('Batalkan pengajuan {{ $rop->number }}? Tindakan ini tidak bisa dikembalikan.');">
            @csrf
            <button class="rop-btn rop-btn--ghost rop-btn--block">Batalkan pengajuan</button>
        </form>
    @endif

    @if ($rop->status === 'selesai')
        <p style="text-align:center;color:var(--rop-muted);font-size:13.5px;margin-top:14px">
            Pengajuan ini sudah tutup.
        </p>
    @endif
@endif
