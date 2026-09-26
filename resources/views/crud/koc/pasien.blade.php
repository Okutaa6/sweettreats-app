@extends('layouts.app')

{{-- Pilih rekam pasien untuk satu KOC/KOL. Tautan ini DISIMPAN
     (raw_affiliate.raw_customer_id) dan dipakai baris "jumlah kontrol".
     Mesin hanya mengisi yang cocok TUNGGAL; yang ganda/kosong diputus di sini. --}}

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Rekam Pasien KOC</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="st-card">
        <div class="st-card__body">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                <div>
                    <div style="font-size:18px; font-weight:700; color:#461500;">{{ $affiliate->name ?: '—' }}</div>
                    <small style="color:#7a6a5c;">
                        {{ strtoupper($affiliate->type ?: '-') }} · HP {{ $affiliate->phone ?: '—' }}
                    </small>
                </div>
                <a href="{{ $kembali }}" class="st-btn-outline-brown"><i class="fa fa-arrow-left"></i> Kembali</a>
            </div>

            <div style="margin-top:14px; padding:12px 14px; border-radius:8px; background:#fbf6f1; border:1px solid #eadccd;">
                @if($affiliate->customer)
                    <strong>Tertaut ke:</strong> {{ $affiliate->customer->name }}
                    @php $lahir = (string) $affiliate->customer->tanggal_lahir; @endphp
                    @if($lahir !== '' && !str_starts_with($lahir, '0000')) · lahir {{ \Carbon\Carbon::parse($lahir)->format('d M Y') }} @endif
                    · {{ $kontrolPasien[$affiliate->raw_customer_id]['jumlah'] ?? 0 }}× kontrol
                @elseif($affiliate->raw_customer_id)
                    <strong>Tertaut ke rekam yang sudah tidak ada</strong> ({{ $affiliate->raw_customer_id }}) — pilih ulang di bawah.
                @else
                    <strong>Belum tertaut.</strong>
                    @if($kandidat->count() > 1)
                        Nomor HP ini cocok ke <strong>{{ $kandidat->count() }} pasien</strong> — kemungkinan satu orang dengan rekam ganda. Pilih yang benar.
                    @elseif($kandidat->isEmpty())
                        Nomor HP ini tidak cocok ke pasien mana pun. Cari lewat nama di bawah.
                    @else
                        Satu pasien cocok — belum disimpan. Periksa lalu simpan.
                    @endif
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('koc.pasien.simpan', ['id' => $affiliate->id, 'return' => $kembali]) }}">
        @csrf
        @method('PATCH')

        <div class="st-card mt-3">
            <div class="st-card__body" style="padding:0;">
                <div style="padding:12px 16px; border-bottom:1px solid #eadccd; font-weight:600; color:#461500;">
                    Pasien dengan nomor HP yang sama
                </div>
                @include('crud.koc._pasien_tabel', ['baris' => $kandidat, 'kosong' => 'Tidak ada pasien dengan nomor ini.'])
            </div>
        </div>

        <div class="st-card mt-3">
            <div class="st-card__body" style="padding:0;">
                <div style="padding:12px 16px; border-bottom:1px solid #eadccd; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span style="font-weight:600; color:#461500;">Cari pasien lain</span>
                    <input type="text" form="form-cari" name="q" value="{{ $cari }}" placeholder="Nama atau HP (min. 3 huruf)" style="min-width:240px;">
                    <button type="submit" form="form-cari" class="st-btn-outline-brown"><i class="fa fa-search"></i> Cari</button>
                </div>
                @if($cari !== '')
                    @include('crud.koc._pasien_tabel', ['baris' => $hasilCari, 'kosong' => 'Tidak ada hasil untuk "' . $cari . '".'])
                @endif
            </div>
        </div>

        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap;">
            <button type="submit" class="st-btn-brand"><i class="fa fa-check"></i> Simpan pilihan</button>
            @if($affiliate->raw_customer_id)
                <button type="submit" name="lepas" value="1" formnovalidate class="st-btn-outline-brown"
                        onclick="return confirm({{ \Illuminate\Support\Js::from('Lepas tautan pasien untuk ' . ($affiliate->name ?: 'KOC ini') . "? Baris kontrol akan kembali berbunyi 'perlu dipilih'.") }});">
                    <i class="fa fa-unlink"></i> Lepas tautan
                </button>
            @endif
        </div>
    </form>

    <form id="form-cari" method="GET" action="{{ route('koc.pasien', $affiliate->id) }}">
        <input type="hidden" name="return" value="{{ $kembali }}">
    </form>
</div>
@endsection
