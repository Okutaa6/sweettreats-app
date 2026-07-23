@extends('layouts.app')

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/finance.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/finance.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="fin-wrap">
    @include('layouts.notif')
    @include('absensi._finance_tabs')

    <div class="fin-two">
        {{-- ── Form upload ── --}}
        <div>
            <div class="fin-panel">
                <div class="fin-panel-h"><i class="fa fa-upload"></i> Unggah Data Mesin Absen</div>
                <p class="fin-panel-sub">
                    File mentah dari mesin (CSV/XLS), jangan diedit dulu. Pilih cabang manual —
                    file mesin tidak menyimpan nama cabang.
                </p>

                <form action="{{ route('absensi.upload.file') }}" method="POST" enctype="multipart/form-data" class="fin-form">
                    @csrf

                    <div class="fin-field">
                        <label>CABANG</label>
                        @if($lockedBranch)
                            @php $lb = $branches->firstWhere('id', $lockedBranch); @endphp
                            <input type="hidden" name="raw_branch_id" value="{{ $lockedBranch }}">
                            <input type="text" class="fin-input" value="{{ $lb->name ?? '—' }}" readonly>
                        @else
                            <select name="raw_branch_id" class="fin-input" required>
                                <option value="">— pilih cabang —</option>
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}" @selected(old('raw_branch_id') === $b->id)>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="fin-field">
                        <label>FILE</label>
                        <input type="file" name="file" class="fin-input" accept=".csv,.txt,.xls,.xlsx" required>
                        <div class="fin-hint">Maks 8 MB. Format log per-scan (Bintaro) atau matriks (cabang lain) — terdeteksi otomatis.</div>
                    </div>

                    <button type="submit" class="fin-btn"><i class="fa fa-upload"></i> Unggah &amp; Proses</button>
                </form>
            </div>

            <div class="fin-panel">
                <div class="fin-note">
                    <strong>Catatan.</strong> Unggah ulang file yang sama aman — scan yang sudah ada tidak
                    digandakan. Supaya edit di mesin ikut ketahuan, atur rentang export mesin
                    <strong>2 bulan terakhir</strong>.
                </div>
            </div>
        </div>

        {{-- ── Riwayat upload ── --}}
        <div class="fin-tablewrap">
            @if($batches->isEmpty())
                <div class="fin-empty">Belum ada unggahan.</div>
            @else
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>CABANG</th><th>PERIODE</th><th>FORMAT</th>
                            <th class="fin-tnum">BARU</th><th class="fin-tnum">DUP</th><th class="fin-tnum">BLM MAP</th><th>WAKTU</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $b)
                            <tr>
                                <td class="fin-pengaju">{{ $b->branch->name ?? '—' }}</td>
                                <td class="num" style="white-space:nowrap">{{ optional($b->periode_from)->format('d/m') }}–{{ optional($b->periode_to)->format('d/m/y') }}</td>
                                <td><span class="fin-chip">{{ $b->format }}</span></td>
                                <td class="fin-tnum num">{{ $b->new_rows }}</td>
                                <td class="fin-tnum num" style="color:var(--fin-ink-3)">{{ $b->dup_rows }}</td>
                                <td class="fin-tnum num">@if($b->unmapped_rows)<span class="fin-warn">{{ $b->unmapped_rows }}</span>@else 0 @endif</td>
                                <td class="num" style="white-space:nowrap">{{ optional($b->uploaded_at)->format('d/m H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
