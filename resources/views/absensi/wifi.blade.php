@extends('layouts.app')

{{-- Admin WiFi Cabang — daftar IP terdaftar per cabang (rencana-absen-hp §7 layar 3).
     Halaman teknis, jarang dibuka: fungsional > cantik, konsisten skin cream. --}}

@section('css')
<style>
    .wifi-wrap { max-width: 900px; margin: 0 auto; padding: 20px 16px; }
    .wifi-card { background: #fff; border: 1px solid #e8ddd5; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
    .wifi-card h5 { color: #461500; font-weight: 700; }
    .wifi-table th { color: #8a7263; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; border-top: 0; }
    .wifi-badge { padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; }
    .wifi-aktif { background: #e8f5e9; color: #1b5e20; }
    .wifi-nonaktif { background: #f1e7df; color: #8a7263; }
    .wifi-btn { background: #461500; color: #fff; border: 0; border-radius: 8px; padding: 8px 16px; font-weight: 600; }
    .wifi-btn-kecil { background: #f1e7df; color: #461500; border: 0; border-radius: 8px; padding: 4px 12px; font-size: 13px; }
</style>
@endsection

@section('content')
<div class="wifi-wrap">
    <h4 style="color:#461500;font-weight:800">WiFi Cabang</h4>
    <p style="color:#8a7263">Daftar jaringan internet yang dikenali sebagai cabang. Absen dari jaringan
        di luar daftar ini akan <strong>tetap diterima</strong> tapi ditandai untuk dicek supervisor.</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="wifi-card">
        <h5>Daftarkan jaringan baru</h5>
        <form method="POST" action="{{ route('absensi.wifi.store') }}" class="form-inline" style="gap:10px">
            @csrf
            <select name="raw_branch_id" class="form-control" required>
                <option value="">— cabang —</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
            <input type="text" name="ip" class="form-control" placeholder="IP publik, mis. 103.10.20.30" required>
            <input type="text" name="label" class="form-control" placeholder="Label (mis. jalur utama)" style="min-width:200px">
            <button type="submit" class="wifi-btn">Daftarkan</button>
        </form>
        <div style="font-size:13px;color:#8a7263;margin-top:8px">
            Tips: buka halaman ini dari perangkat yang terhubung WiFi cabang tersebut — IP kamu sekarang:
            <strong>{{ request()->ip() }}</strong>
        </div>
    </div>

    <div class="wifi-card">
        <h5>Terdaftar ({{ $ips->count() }})</h5>
        <table class="table wifi-table">
            <thead><tr><th>Cabang</th><th>IP</th><th>Label</th><th>Status</th><th>Terakhir terlihat</th><th></th></tr></thead>
            <tbody>
            @forelse($ips as $row)
                <tr>
                    <td><strong>{{ optional($row->branch)->name ?? '—' }}</strong></td>
                    <td><code>{{ $row->ip }}</code></td>
                    <td>{{ $row->label ?? '—' }}</td>
                    <td><span class="wifi-badge wifi-{{ $row->status }}">{{ $row->status }}</span></td>
                    <td>{{ $row->last_seen_at ? $row->last_seen_at->format('d M Y H.i') : 'belum pernah' }}</td>
                    <td>
                        <form method="POST" action="{{ route('absensi.wifi.toggle', $row->id) }}">
                            @csrf
                            <button type="submit" class="wifi-btn-kecil">{{ $row->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#8a7263">Belum ada — daftarkan jalur pertama di atas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
