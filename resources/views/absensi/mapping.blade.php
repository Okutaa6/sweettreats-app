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

    <div class="fin-panel" style="margin-bottom:16px">
        <div class="fin-panel-h"><i class="fa fa-link"></i> Sambungkan No.ID Mesin ke Karyawan</div>
        <p class="fin-panel-sub" style="margin-bottom:0">
            No.ID mesin yang belum tersambung ke karyawan. Selama belum disambung, scan-nya
            <strong>tak terhitung</strong> (tak masuk absen, tak kesanding lembur). Sambungkan ke orangnya,
            atau <em>Abaikan</em> (dokter / tes enrollment / sudah keluar).
            Total scan belum tersambung: <strong class="num">{{ number_format($totalUnmapped) }}</strong>.
        </p>
    </div>

    <form method="GET" action="{{ route('absensi.mapping') }}" class="fin-filters" id="mapFilter">
        <div class="fin-field">
            <label>CABANG</label>
            <select name="branch" class="fin-input" onchange="document.getElementById('mapFilter').submit()">
                <option value="">Semua Cabang</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected($branchFilter === $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="fin-count"><span id="mapSuggestNote"></span>Menampilkan <b class="num">{{ $rows->count() }}</b> No.ID</div>
    </form>

    @if($rows->isEmpty())
        <div class="fin-tablewrap"><div class="fin-empty"><i class="fa fa-check-circle" style="color:var(--fin-green)"></i> Semua scan sudah tersambung (di filter ini).</div></div>
    @else
        <div class="fin-tablewrap">
            <table class="fin-table" id="mapTable">
                <thead>
                    <tr>
                        <th>CABANG</th><th>NO.ID</th><th>NAMA DI MESIN</th>
                        <th class="fin-tnum">SCAN</th><th>RENTANG</th><th style="min-width:300px">SAMBUNGKAN KE</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $r)
                        <tr data-machine="{{ $r->nama_di_mesin }}">
                            <td>{{ $r->branch_name ?? '—' }}</td>
                            <td><span class="fin-chip num">{{ $r->device_user_id }}</span></td>
                            <td class="fin-pengaju">{{ $r->nama_di_mesin ?: '—' }}</td>
                            <td class="fin-tnum num">{{ $r->scan_count }}</td>
                            <td class="fin-sub num" style="white-space:nowrap">
                                {{ \Illuminate\Support\Carbon::parse($r->first_scan)->format('d/m') }}–{{ \Illuminate\Support\Carbon::parse($r->last_scan)->format('d/m/y') }}
                            </td>
                            <td>
                                <form action="{{ route('absensi.mapping.assign') }}" method="POST" style="display:flex;gap:7px;align-items:center">
                                    @csrf
                                    <input type="hidden" name="raw_branch_id" value="{{ $r->raw_branch_id }}">
                                    <input type="hidden" name="device_user_id" value="{{ $r->device_user_id }}">
                                    <input type="hidden" name="nama_di_mesin" value="{{ $r->nama_di_mesin }}">
                                    <select name="user_id" class="fin-input js-mapsel" required style="min-width:200px;flex:1">
                                        <option value="">— pilih karyawan —</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->nama }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="fin-btn" style="padding:9px 13px" title="Sambungkan"><i class="fa fa-link"></i></button>
                                </form>
                            </td>
                            <td style="text-align:right">
                                <form action="{{ route('absensi.mapping.ignore') }}" method="POST"
                                      onsubmit="return confirm('Abaikan No.ID {{ $r->device_user_id }} ({{ $r->branch_name }})?');">
                                    @csrf
                                    <input type="hidden" name="raw_branch_id" value="{{ $r->raw_branch_id }}">
                                    <input type="hidden" name="device_user_id" value="{{ $r->device_user_id }}">
                                    <button type="submit" class="fin-btn-danger" title="Abaikan">Abaikan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="fin-hint" style="margin-top:10px">Maks 200 baris (scan terbaru dulu). <span style="color:var(--fin-green);font-weight:700">Hijau</span> = nama di mesin cocok dgn karyawan (usulan otomatis) — tetap cek sebelum sambungkan.</p>
    @endif
</div>

<script>
// Auto-suggest: cocokkan "nama di mesin" ke opsi karyawan (client-side, tak menyimpan apa-apa).
(function () {
  var norm = function (s) { return (s || '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim(); };
  var rows = document.querySelectorAll('#mapTable tbody tr');
  var hit = 0;
  rows.forEach(function (tr) {
    var machine = norm(tr.getAttribute('data-machine'));
    if (!machine) return;
    var mParts = machine.split(' ').filter(function (w) { return w.length > 2; });
    var sel = tr.querySelector('.js-mapsel');
    if (!sel) return;
    var best = null, bestScore = 0;
    Array.prototype.forEach.call(sel.options, function (opt) {
      if (!opt.value) return;
      var name = norm(opt.textContent);
      if (!name) return;
      var score = 0;
      if (name === machine) score = 100;
      else {
        var nParts = name.split(' ');
        mParts.forEach(function (w) { if (nParts.indexOf(w) !== -1) score += w.length; });
      }
      if (score > bestScore) { bestScore = score; best = opt; }
    });
    if (best && bestScore >= 3) {
      sel.value = best.value;
      sel.style.borderColor = '#2F8E55';
      sel.style.background = '#F1FAF4';
      hit++;
    }
  });
  var note = document.getElementById('mapSuggestNote');
  if (note && hit) note.innerHTML = '<b class="num" style="color:var(--fin-green)">' + hit + '</b> usulan cocok · ';
})();
</script>
@endsection
