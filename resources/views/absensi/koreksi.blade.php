@extends('layouts.app')

{{-- Koreksi Absen — ubah status satu orang satu tanggal, berjejak audit.
     Sumber kebenarannya att_day_koreksi (buku besar), BUKAN att_day. --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
<style>
    /* Gaya inline, bukan berkas baru di public/assets — supaya deploy tak
       menambah utang rsync (public_html prod itu salinan, bukan symlink). */
    .kor-wrap { font-family: 'Plus Jakarta Sans', sans-serif; display: flex; flex-direction: column; gap: 16px; color: #3B1500; }
    .kor-card { background: #fff; border: 1px solid #EFE2D6; border-radius: 14px; padding: 18px 20px; }
    .kor-card h2 { font-size: 15px; font-weight: 800; margin: 0 0 4px; }
    .kor-card .sub { font-size: 12.5px; color: #9A7256; margin: 0 0 14px; }
    .kor-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; }
    .kor-field { display: flex; flex-direction: column; gap: 5px; }
    .kor-field.wide { grid-column: 1 / -1; }
    .kor-field label { font-size: 11.5px; font-weight: 700; color: #6B4A31; }
    .kor-field input, .kor-field select, .kor-field textarea {
        padding: 9px 11px; border: 1px solid #E7D9CC; border-radius: 9px; font: inherit; font-size: 13px;
        background: #FFFDFA; color: #3B1500; width: 100%; }
    .kor-field textarea { resize: vertical; min-height: 62px; }
    .kor-btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 11px; padding: 10px 16px;
        font-weight: 700; font-size: 13px; cursor: pointer; font-family: inherit; border: 1px solid transparent;
        background: linear-gradient(135deg, #6B2400, #461500); color: #fff; }
    .kor-btn:hover { filter: brightness(1.12); }
    .kor-btn-lite { background: #fff; color: #8A5A36; border-color: #E7D9CC; font-weight: 600; padding: 6px 11px; font-size: 12px; }
    .kor-note { background: #FBEFD9; color: #96601A; border-radius: 10px; padding: 11px 14px; font-size: 12.5px; line-height: 1.55; }
    .kor-note-lite { background: #F4EFE9; color: #6B4A31; margin-top: 10px; }
    .kor-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .kor-table th { text-align: left; font-size: 10.5px; letter-spacing: .6px; text-transform: uppercase;
        color: #A9866A; font-weight: 800; padding: 8px 10px; border-bottom: 1px solid #EFE2D6; white-space: nowrap; }
    .kor-table td { padding: 9px 10px; border-bottom: 1px solid #F4EAE1; vertical-align: top; }
    .kor-table tr.dicabut td { color: #A9866A; }
    .kor-table tr.dicabut .kor-pindah { text-decoration: line-through; }
    .kor-pindah { font-weight: 700; white-space: nowrap; }
    .kor-alasan { color: #6B4A31; }
    .kor-meta { font-size: 11.5px; color: #A9866A; }
    .kor-kosong { padding: 22px; text-align: center; color: #A9866A; font-size: 13px; }
</style>
@endsection

@section('content')
<div class="fin-wrap kor-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    <div class="kor-card">
        <h2>Koreksi absen</h2>
        <p class="sub">Ubah status satu orang pada satu tanggal. Tersimpan sebagai koreksi
            bernama dan beralasan — bukan perubahan diam-diam di basis data.</p>

        <div class="kor-note">
            Koreksi <strong>bertahan</strong>: rekonsiliasi harian menghitung ulang <code>att_day</code>
            tiap pagi, dan koreksi ini dibacanya sebagai kata terakhir. Tanggal di dalam
            <strong>periode gaji yang sudah ditutup akan ditolak</strong> — angka di sana sudah dibayarkan.
        </div>

        {{-- Ketiadaan "cuti" di dropdown DIJELASKAN, bukan dibiarkan jadi teka-teki.
             Pilihan yang hilang tanpa keterangan bikin orang mengira layarnya rusak,
             lalu mencari jalan memutar — biasanya langsung ke basis data. --}}
        <div class="kor-note kor-note-lite">
            <strong>"Cuti" sengaja tidak ada di daftar.</strong> Jatah cuti punya buku besarnya
            sendiri dan hanya berkurang lewat pengajuan yang di-ACC. Kalau disetel dari sini,
            kolom <em>Cuti</em> di Lembar Gaji naik sementara <em>Sisa Cuti</em> diam — orangnya
            dapat hari cuti yang tak memakan jatahnya.
            Pengajuannya sudah di-ACC tapi harinya belum berubah? Jalankan rekonsiliasi ulang.
            Belum ada pengajuannya? Buat dan setujui dulu.
        </div>

        <form method="POST" action="{{ route('absensi.koreksi.simpan') }}" style="margin-top:14px">
            @csrf
            <div class="kor-form">
                <div class="kor-field">
                    <label for="kor-user">Karyawan</label>
                    <select id="kor-user" name="user_id" required>
                        <option value="">— pilih —</option>
                        @foreach($orang as $o)
                            <option value="{{ $o->id }}" @selected(old('user_id') === $o->id)>{{ $o->nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kor-field">
                    <label for="kor-tgl">Tanggal</label>
                    <input id="kor-tgl" type="date" name="work_date" required
                           max="{{ now()->toDateString() }}" value="{{ old('work_date') }}">
                </div>
                <div class="kor-field">
                    <label for="kor-status">Status baru</label>
                    <select id="kor-status" name="status" required>
                        @foreach($status as $s)
                            <option value="{{ $s }}" @selected(old('status') === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kor-field wide">
                    <label for="kor-alasan">Alasan (wajib, tersimpan permanen)</label>
                    <textarea id="kor-alasan" name="alasan" required minlength="8" maxlength="500"
                              placeholder="Contoh: Sunter tidak libur Maulid, tapi yang bersangkutan memang diliburkan bergantian — dikonfirmasi Nissa.">{{ old('alasan') }}</textarea>
                </div>
            </div>
            <div style="margin-top:14px">
                <button type="submit" class="kor-btn">Simpan koreksi</button>
            </div>
        </form>
    </div>

    <div class="kor-card">
        <h2>Riwayat koreksi</h2>
        <p class="sub">Buku besar — baris tak pernah dihapus. Mencabut koreksi mengembalikan
            hari itu ke hasil rekonsiliasi, dan cabutannya ikut tercatat.</p>

        @if($riwayat->isEmpty())
            <div class="kor-kosong">Belum ada koreksi yang tercatat.</div>
        @else
        <div style="overflow-x:auto">
            <table class="kor-table">
                <thead>
                    <tr>
                        <th>Karyawan</th><th>Tanggal</th><th>Perubahan</th>
                        <th>Alasan</th><th>Oleh</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($riwayat as $r)
                    <tr class="{{ $r->dicabut_at ? 'dicabut' : '' }}">
                        <td>{{ $nama[$r->user_id] ?? '(?)' }}</td>
                        <td>{{ $r->work_date->format('d/m/Y') }}</td>
                        <td class="kor-pindah">{{ $r->status_lama ?? '—' }} &rarr; {{ $r->status }}</td>
                        <td class="kor-alasan">
                            {{ $r->alasan }}
                            @if($r->dicabut_at)
                                <div class="kor-meta">Dicabut {{ $r->dicabut_at->format('d/m/Y H:i') }}
                                    oleh {{ $r->dicabut_nama ?? '(?)' }}@if($r->dicabut_alasan) — {{ $r->dicabut_alasan }}@endif</div>
                            @endif
                        </td>
                        <td class="kor-meta">
                            {{ $r->dibuat_nama ?? ($nama[$r->dibuat_oleh] ?? '(?)') }}<br>
                            {{ $r->created_at?->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            @unless($r->dicabut_at)
                            <form method="POST" action="{{ route('absensi.koreksi.cabut', $r->id) }}"
                                  onsubmit="return confirm('Cabut koreksi ini? Hari itu kembali ke hasil rekonsiliasi.')">
                                @csrf
                                <button type="submit" class="kor-btn kor-btn-lite">Cabut</button>
                            </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
