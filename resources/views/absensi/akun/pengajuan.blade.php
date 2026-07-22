@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/absensi.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/absensi.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
<div class="container-fluid abs-akun">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex justify-content-between align-items-center">
                <h4 class="page-title mb-0">Ajukan Absensi</h4>
                <a href="{{ route('akun.absensi') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="card">
        <div class="card-body">
            <form action="{{ route('akun.pengajuan.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Jenis --}}
                <div class="form-group">
                    <label>Jenis Pengajuan <span class="text-danger">*</span></label>
                    <select name="tipe" id="tipe" class="form-control" required>
                        <option value="">— pilih —</option>
                        @foreach($tipeList as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('tipe', $tipe) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sub-tipe lembur (muncul kalau tipe = lembur) --}}
                <div class="form-group" id="grp-subtipe" style="display:none">
                    <label>Jenis Lembur</label>
                    <select name="sub_tipe" class="form-control">
                        <option value="jam" @selected(old('sub_tipe') === 'jam')>Lembur jam (hari kerja)</option>
                        <option value="tambah_hari" @selected(old('sub_tipe') === 'tambah_hari')>Tambah hari (hari libur)</option>
                    </select>
                </div>

                {{-- Tanggal --}}
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_from" class="form-control"
                               value="{{ old('tanggal_from', now()->toDateString()) }}" required>
                    </div>
                    <div class="form-group col-6">
                        <label>Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal_to" class="form-control"
                               value="{{ old('tanggal_to', now()->toDateString()) }}" required>
                    </div>
                </div>

                {{-- Jam (relevan utk lembur / izin sebagian) --}}
                <div class="form-row" id="grp-jam" style="display:none">
                    <div class="form-group col-6">
                        <label>Jam Mulai</label>
                        <input type="time" name="jam_from" id="jamFrom" class="form-control" value="{{ old('jam_from') }}">
                    </div>
                    <div class="form-group col-6">
                        <label>Jam Selesai</label>
                        <input type="time" name="jam_to" id="jamTo" class="form-control" value="{{ old('jam_to') }}">
                    </div>
                </div>

                {{-- Prefill lembur dari scan (§7 Fase C) — tetap wajib dicek/diedit --}}
                <div class="form-group" id="grp-prefill" style="display:none">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrefill">
                        <i class="fa fa-magic"></i> Isi jam dari scan
                    </button>
                    <span class="small text-muted ml-2" id="prefillNote"></span>
                </div>

                {{-- Alasan --}}
                <div class="form-group">
                    <label>Alasan <span class="text-danger">*</span></label>
                    <textarea name="alasan" class="form-control" rows="3" required
                              placeholder="Jelaskan singkat — approver membaca ini saat memutuskan.">{{ old('alasan') }}</textarea>
                </div>

                {{-- Foto bukti --}}
                <div class="form-group">
                    <label>Foto Bukti <span class="text-muted small">(disarankan utk lembur/WFH tanpa scan)</span></label>
                    <input type="file" name="foto" class="form-control-file" accept="image/*">
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fa fa-paper-plane"></i> Kirim Pengajuan
                </button>
            </form>
        </div>
    </div>
</div>

{{-- toggle field yang bergantung pada jenis --}}
<script>
(function () {
    var tipe = document.getElementById('tipe');
    var grpSub = document.getElementById('grp-subtipe');
    var grpJam = document.getElementById('grp-jam');
    var grpPrefill = document.getElementById('grp-prefill');
    function sync() {
        var isLembur = tipe.value === 'lembur';
        grpSub.style.display = isLembur ? '' : 'none';
        grpJam.style.display = (isLembur || tipe.value === 'izin') ? '' : 'none';
        grpPrefill.style.display = isLembur ? '' : 'none';   // prefill cuma utk lembur
    }
    tipe.addEventListener('change', sync);
    sync();

    // Prefill jam lembur dari scan (§7 Fase C) — pintu masuk terisi, tetap bisa diedit.
    var btn = document.getElementById('btnPrefill');
    var note = document.getElementById('prefillNote');
    if (btn) btn.addEventListener('click', function () {
        var tgl = document.querySelector('[name="tanggal_from"]').value;
        if (!tgl) { note.textContent = 'Pilih tanggal dulu.'; return; }
        note.textContent = 'Mengambil scan…';
        var url = '{{ route('akun.pengajuan.prefill') }}?tanggal=' + encodeURIComponent(tgl);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.ok) {
                    document.getElementById('jamFrom').value = d.jam_from;
                    document.getElementById('jamTo').value = d.jam_to;
                    note.textContent = 'Terisi dari scan (pulang ' + d.scan_last + '). Cek & edit bila perlu.';
                } else {
                    note.textContent = d.reason || 'Tidak bisa mengisi dari scan.';
                }
            })
            .catch(function () { note.textContent = 'Gagal mengambil scan.'; });
    });
})();
</script>
@endsection
