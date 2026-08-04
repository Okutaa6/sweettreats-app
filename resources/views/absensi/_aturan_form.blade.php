{{-- Editor aturan kerja — dipakai dua tempat: ubah aturan ($rule terisi) dan
     tambah aturan ($rule null). Induk: absensi/aturan.blade.php Tab 1.
     day 0=Minggu..6=Sabtu (Carbon); tampil urut Senin..Minggu.

     old() HANYA di form create: halaman ini memuat BANYAK form dengan nama
     field sama — kalau semua pakai old(), gagal-validasi di satu form
     menulari nilai form lain (bahaya: rename aturan tak sengaja). Form ubah
     selalu render dari DB. --}}
@php
    $urutanHari = [1, 2, 3, 4, 5, 6, 0];
    $namaHari   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $jam  = fn ($t) => $t ? substr($t, 0, 5) : '';
    $baru = $rule === null;
    $val  = fn ($key, $dbVal) => $baru ? old($key, $dbVal) : $dbVal;
@endphp

<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
    <div>
        <label class="ak-lbl">Nama aturan</label>
        <input type="text" name="nama" class="ak-input" required maxlength="100"
               placeholder="mis. GS, FO-Asdent" value="{{ $val('nama', $rule->nama ?? '') }}">
    </div>
    <div>
        <label class="ak-lbl">Tipe</label>
        <select name="tipe" class="ak-select" onchange="aturTipe(this)">
            <option value="baku" @if($val('tipe', $rule->tipe ?? 'baku') === 'baku') selected @endif>baku — dinilai telat/alpa dari scan</option>
            <option value="fleksibel" @if($val('tipe', $rule->tipe ?? '') === 'fleksibel') selected @endif>fleksibel — jam bebas, scan tak dinilai</option>
        </select>
    </div>
    <div style="flex:1;min-width:200px">
        <label class="ak-lbl">Keterangan (opsional)</label>
        <input type="text" name="keterangan" class="ak-input" style="width:100%" maxlength="255"
               value="{{ $val('keterangan', $rule->keterangan ?? '') }}">
    </div>
</div>

<div class="ak-editor-grid">
    <span class="kepala">Hari</span>
    <span class="kepala">Libur</span>
    <span class="kepala">Masuk awal</span>
    <span class="kepala">Masuk akhir</span>
    <span class="kepala">Pulang</span>
    <span class="kepala">Lembur mulai <span style="text-transform:none;font-weight:600">(opsional — sesudah jeda istirahat)</span></span>

    @foreach($urutanHari as $d)
        @php $rd = $rule ? $rule->days->firstWhere('day', $d) : null; @endphp
        {{-- satu baris hari = 6 sel grid; class penanda supaya JS bisa matikan jam saat libur --}}
        <span class="ak-hari-row" style="display:contents">
            <span style="font-weight:700;font-size:13px">{{ $namaHari[$d] }}</span>
            <label class="ak-cek">
                <input type="checkbox" name="hari[{{ $d }}][libur]" value="1" onchange="aturLibur(this)"
                       @if($val("hari.$d.libur", $rd->libur ?? 0)) checked @endif>
                libur
            </label>
            <input type="time" name="hari[{{ $d }}][jam_masuk_awal]" class="ak-input"
                   value="{{ $val("hari.$d.jam_masuk_awal", $jam($rd->jam_masuk_awal ?? null)) }}">
            <input type="time" name="hari[{{ $d }}][jam_masuk_akhir]" class="ak-input"
                   value="{{ $val("hari.$d.jam_masuk_akhir", $jam($rd->jam_masuk_akhir ?? null)) }}">
            <input type="time" name="hari[{{ $d }}][jam_pulang]" class="ak-input"
                   value="{{ $val("hari.$d.jam_pulang", $jam($rd->jam_pulang ?? null)) }}">
            <input type="time" name="hari[{{ $d }}][jam_lembur_mulai]" class="ak-input"
                   value="{{ $val("hari.$d.jam_lembur_mulai", $jam($rd->jam_lembur_mulai ?? null)) }}">
        </span>
    @endforeach
</div>

<div style="display:flex;gap:10px;align-items:center;margin-top:14px">
    <button type="submit" class="ak-btn ak-btn-pri ak-btn-kecil">{{ $rule ? 'Simpan perubahan' : 'Buat aturan' }}</button>
    <span class="ak-hint">Scan lebih awal dari "masuk akhir" = tepat waktu; lewat = telat (jam pulang ikut terdorong).
        Perubahan dinilai mulai reconcile besok pagi — masa lalu tidak dihitung ulang.</span>
</div>
