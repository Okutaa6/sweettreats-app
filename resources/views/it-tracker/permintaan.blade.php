{{--
    IT Request Tracker — Halaman Permintaan (Head) · Fase 1.

    Formulir FRM-IT-01 Kelas A: identitas TIDAK ditanyakan (otomatis dari akun,
    tetap tampil terisi saat ekspor). Kanan: ringkasan beban IT (Head melihat
    miliknya + RINGKASAN — bukan isi permintaan departemen lain, rencana §12)
    dan daftar "permintaanku" dengan posisi antrean + banner bola-di-tanganmu.

    Untuk IT (head_it/it_dev) halaman yang sama merangkap pencatat inisiatif /
    insiden — kerjaan yang lahir dari IT sendiri WAJIB ikut papan (rencana §10).
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $adalahIt = in_array($peran, ['head_it', 'it_dev'], true);
    $bolehPapan = $peran !== 'head';
@endphp
<div class="itk-surface">

    <div class="itk-head">
        <h1>{{ $adalahIt ? 'Catat Tiket' : 'Permintaanku' }}</h1>
        <span class="itk-badge-zona">ZONA IT</span>
        <div class="itk-head-nav">
            @if ($bolehPapan)
                <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.index') }}">Papan Antrean</a>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    <div class="itk-2col">

        {{-- Formulir Kelas A --}}
        <div class="itk-panel">
            <div class="itk-beban-bar">
                <b>{{ $adalahIt ? 'CATAT INISIATIF / INSIDEN / PERMINTAAN' : 'FORMULIR PERMINTAAN — FRM-IT-01' }}</b>
                <span class="itk-badge-fase">KELAS A · diisi pemohon</span>
            </div>
            <form method="post" action="{{ route('it-tracker.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="itk-panel-body">
                    <div class="itk-ident">
                        {{ session('name') }} · {{ session('group_name') }}
                        <span class="itk-auto">otomatis dari akun</span>
                    </div>
                    {{-- error validasi/flash sudah dirender layouts.notif di atas — jangan dobel --}}

                    @if ($adalahIt)
                        {{-- Tanpa opsi "Permintaan": permintaan HARUS diketik pemohonnya
                             sendiri (memaksa pemohon berpikir = tujuan sistem, rencana §1).
                             IT mencatat kerjaan yang lahir dari IT saja. --}}
                        <div>
                            <label class="itk-lbl">SUMBER TIKET</label>
                            <div style="display:flex;gap:7px">
                                <label><input class="itk-radio" type="radio" name="sumber" value="inisiatif" {{ old('sumber', 'inisiatif') === 'inisiatif' ? 'checked' : '' }}><span>Inisiatif IT → Antre</span></label>
                                <label><input class="itk-radio" type="radio" name="sumber" value="insiden" {{ old('sumber') === 'insiden' ? 'checked' : '' }}><span>Insiden → langsung Development</span></label>
                            </div>
                            <div class="itk-hint">Inisiatif & insiden wajib langsung diberi jenjang — untuk insiden, isian lain boleh menyusul (rencana §3).</div>
                        </div>
                    @else
                        <input type="hidden" name="sumber" value="permintaan">
                    @endif

                    <div class="itk-grid2">
                        <div>
                            <label class="itk-lbl">JENIS PERMINTAAN</label>
                            <select name="jenis_permintaan" data-isi-wajib required>
                                <option value="">— pilih —</option>
                                @foreach (['Modul baru', 'Fitur baru', 'Perubahan fitur', 'Integrasi', 'Lainnya'] as $j)
                                    <option {{ old('jenis_permintaan') === $j ? 'selected' : '' }}>{{ $j }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="itk-lbl">TARGET WAKTU / TENGGAT DARI LUAR</label>
                            <input class="itk-in" type="text" name="target" value="{{ old('target') }}" placeholder="mis. sebelum audit Okt">
                        </div>
                    </div>

                    <div>
                        <label class="itk-lbl">NAMA MODUL / FITUR</label>
                        <input class="itk-in" type="text" name="judul" value="{{ old('judul') }}" required maxlength="255"
                               placeholder="mis. Laporan KOC bulanan otomatis">
                    </div>

                    @if (! $adalahIt)
                        <div>
                            <label class="itk-lbl">SEBERAPA MENDESAK MENURUTMU</label>
                            <div style="display:flex;gap:7px">
                                @foreach (['Rendah', 'Sedang', 'Tinggi'] as $p)
                                    <label><input class="itk-radio" type="radio" name="prioritas_pemohon" value="{{ $p }}"
                                        {{ old('prioritas_pemohon') === $p ? 'checked' : '' }} required><span>{{ $p }}</span></label>
                                @endforeach
                            </div>
                            {{-- prioritas_pemohon ≠ prioritas_it — dua kolom, sengaja dipisah --}}
                            <div class="itk-hint">Prioritas akhir tetap ditetapkan IT saat triase — dua hal ini sengaja dipisah.</div>
                        </div>
                    @else
                        <div class="itk-grid2">
                            <div>
                                <label class="itk-lbl">JENJANG (INISIATIF / INSIDEN)</label>
                                <div style="display:flex;gap:6px">
                                    {{-- kata, bukan huruf — sama seperti badge di papan --}}
                                    @foreach (['S', 'M', 'L'] as $j)
                                        <label><input class="itk-radio" type="radio" name="jenjang" value="{{ $j }}"
                                            {{ old('jenjang') === $j ? 'checked' : '' }}><span>{{ \App\Services\ItTracker\ItTrackerService::jenjangLabel($j) }}</span></label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <label class="itk-lbl">PRIORITAS IT</label>
                                <div style="display:flex;gap:6px">
                                    @foreach (['P1', 'P2', 'P3'] as $p)
                                        <label><input class="itk-radio" type="radio" name="prioritas_it" value="{{ $p }}"
                                            {{ old('prioritas_it') === $p ? 'checked' : '' }}><span>{{ $p }}</span></label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label class="itk-lbl">APA MASALAHNYA HARI INI?</label>
                        <textarea name="latar" rows="2" data-isi-wajib required
                            placeholder="mis. Rekap KOC disusun manual dari 3 sumber tiap akhir bulan">{{ old('latar') }}</textarea>
                    </div>
                    <div>
                        <label class="itk-lbl">KALAU INI JADI, APA YANG BERUBAH?</label>
                        <textarea name="kebutuhan" rows="2" data-isi-wajib required
                            placeholder="hasil yang diharapkan — bukan solusi teknisnya">{{ old('kebutuhan') }}</textarea>
                    </div>
                    <div>
                        <label class="itk-lbl">SIAPA YANG TERDAMPAK</label>
                        <input class="itk-in" type="text" name="terdampak" value="{{ old('terdampak') }}" placeholder="mis. FO semua cabang">
                    </div>
                    <div>
                        {{-- §7 B Lampiran: screenshot · contoh laporan · alur proses · dokumen lain.
                             Tanpa ini buktinya lari ke WhatsApp — kebiasaan yang mau digantikan. --}}
                        <label class="itk-lbl">LAMPIRAN <span style="font-weight:600;color:#8A857C">(opsional — screenshot, contoh laporan, alur proses)</span></label>
                        <input class="itk-in itk-file" type="file" name="lampiran[]" multiple
                               accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.docx,.doc,.csv" data-lampiran-input>
                        <div class="itk-hint" style="margin:4px 0 0" data-lampiran-hint>Diterima: {{ \App\Services\ItTracker\ItTrackerService::lampiranDiterima() }}. Bisa ditambah lagi selama tiket belum ditriase.</div>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;border-top:1px solid #F1E5D5;padding-top:14px">
                        <button type="submit" class="itk-btn itk-btn-grafit">Kirim — nomor tiket langsung terbit</button>
                        <span class="itk-hint" style="margin:0">Masuk antrean triase; jenjang & prioritas ditetapkan IT.</span>
                    </div>
                </div>
            </form>
        </div>

        {{-- Milikku + beban IT --}}
        <div>
            <div class="itk-antrean-info" style="margin-bottom:12px">
                <b>ANTREAN IT SAAT INI</b>
                <p>IT sedang memikul <strong>{{ $beban['aktif'] }} item</strong>
                    ({{ $beban['permintaan'] }} permintaan + {{ $beban['inisiatif'] }} inisiatif IT).
                    Permintaan baru akan antre di belakang <strong>{{ $beban['diTangan'] }}</strong>
                    yang sudah di tangan IT.</p>
            </div>

            <div class="itk-fase-hd" style="margin-bottom:8px"><b>PERMINTAANKU</b>
                <span class="n num">{{ $milik->count() }}</span></div>

            @if ($milik->isEmpty())
                <div class="itk-antrean-info" style="text-align:center">
                    <p style="color:#9A7256">Belum ada — ajukan lewat formulir di samping; nomor tiket langsung terbit.</p>
                </div>
            @endif

            @foreach ($milik as $m)
                @php $t = $m['t']; $w = $m['wait']; @endphp
                {{-- div ber-onclick, bukan <a>: kartu UAT memuat form keputusan --}}
                <div class="itk-mine-card" style="cursor:pointer"
                     onclick="window.location='{{ route('it-tracker.show', $t->id) }}'">
                    <div class="itk-card-top">
                        <span class="itk-no num">{{ $t->nomor }}</span>
                        @if (in_array($t->fase, ['DITUNDA', 'DITOLAK', 'SELESAI'], true))
                            <span class="itk-st {{ $t->fase }}">{{ $t->fase }}</span>
                        @else
                            <span class="itk-prog num">{{ $m['prog']['label'] ?? 'Menunggu triase' }}</span>
                        @endif
                    </div>
                    <div class="itk-card-title" style="font-size:13.5px">{{ $t->judul }}</div>
                    @if ($m['prog'])
                        <div class="itk-mine-bar">
                            <div class="rail"><div class="isi" style="width:{{ round($m['prog']['i'] / $m['prog']['n'] * 100) }}%"></div></div>
                            <span class="num" style="font-size:11px;font-weight:800;color:#5A3A22">{{ $m['prog']['i'] }}/{{ $m['prog']['n'] }}</span>
                        </div>
                    @endif
                    <div style="font-size:12px;color:#7A5B43;font-weight:600;margin-top:7px">
                        @if ($t->fase === 'ANTRE') Antre di belakang {{ $m['antre'] }} item
                        @elseif ($t->fase === 'DITOLAK' || $t->fase === 'DITUNDA') {{ $t->alasan_parkir }}
                        @elseif ($w['label']) {{ $w['label'] }} · {{ $w['days'] }} hr
                        @endif
                    </div>
                    @if ($t->fase === 'UAT')
                        {{-- bola di tangan pemohon — hitungan hari berjalan di kolomnya (brief §5b no. 7) --}}
                        <div class="itk-bola panas{{ $w['heat'] }}" onclick="event.stopPropagation()">
                            <b>Bola di tanganmu — UAT menunggu kamu {{ $w['days'] }} hari</b>
                            <p>Selama belum kamu isi, hitungan harinya berjalan di kolommu, bukan di kolom IT.</p>
                            <div style="display:flex;gap:7px;margin-top:9px">
                                <form method="post" action="{{ route('it-tracker.uat', $t->id) }}">
                                    @csrf
                                    <input type="hidden" name="keputusan" value="setuju">
                                    <button type="submit" class="itk-btn itk-btn-hijau" style="padding:7px 12px;font-size:11.5px">Setujui Go Live</button>
                                </form>
                                <form method="post" action="{{ route('it-tracker.uat', $t->id) }}">
                                    @csrf
                                    <input type="hidden" name="keputusan" value="perbaikan">
                                    <button type="submit" class="itk-btn itk-btn-oranye" style="padding:7px 12px;font-size:11.5px">Perlu Perbaikan</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="itk-legenda">Badge: Fase 1 = formulir + antrean ini · Fase 2 = tujuh fase &amp; gerbang ·
                Fase 3 = sambungan Project Tracker.</div>
        </div>
    </div>
</div>

@if ($adalahIt)
<script>
// Insiden = formulir menyusul (rencana §3): lepaskan `required` HTML saat
// insiden dipilih supaya browser tak memblokir kiriman yang server izinkan.
function itkSesuaikanWajib() {
    var pilih = document.querySelector('input[name=sumber]:checked');
    var wajib = ! pilih || pilih.value !== 'insiden';
    document.querySelectorAll('[data-isi-wajib]').forEach(function (el) {
        el.required = wajib;
    });
}
document.querySelectorAll('input[name=sumber]').forEach(function (r) {
    r.addEventListener('change', itkSesuaikanWajib);
});
itkSesuaikanWajib();
</script>
@endif
@endsection
