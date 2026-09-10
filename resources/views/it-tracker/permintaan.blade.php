{{--
    IT Request Tracker — Halaman Permintaan (Head) · Fase 1 · rupa RONDE 3a.

    Formulir FRM-IT-01 Kelas A: identitas TIDAK ditanyakan (otomatis dari akun,
    tetap tampil terisi saat ekspor). Tombol kirim MATI sambil menyebut apa
    yang kurang (pola P3) — "Pilih jenis permintaannya dulu", dst.
    Kanan: ringkasan beban IT (Head melihat miliknya + RINGKASAN — bukan isi
    permintaan departemen lain, rencana §12) dan "Permintaan saya" dengan
    status, bar kemajuan, posisi antrean, dan banner bola-di-tanganmu.

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
    $inisial = mb_strtoupper(mb_substr((string) session('name'), 0, 1)) ?: 'IT';
@endphp
<div class="itk-surface">

    <div class="itk-topbar">
        <h1>{{ $adalahIt ? 'Inisiatif IT' : 'Permintaan Saya' }}</h1>
        <span class="itk-badge-zona">ZONA IT</span>
        <span class="sub">{{ now()->translatedFormat('l, j M Y') }} · 2 orang IT · 5 cabang + RB Las</span>
        <div class="itk-topbar-nav">
            @if ($bolehPapan)
                <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.index') }}">Papan Antrean</a>
            @endif
            {{-- katalog dicapai dari SEMUA halaman zona ini, termasuk halaman Head
                 yang tak punya papan — kalau tidak, "apa saja yang kami punya"
                 cuma bisa dibuka orang IT. --}}
            <a class="itk-btn itk-btn-putih" href="{{ route('it-tracker.katalog') }}" title="Apa saja yang kami punya">Katalog Modul</a>
            <div class="itk-avatar" title="{{ session('name') }} · {{ session('group_name') }}">{{ $inisial }}</div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="itk-2col">

        {{-- Formulir Kelas A --}}
        <div class="itk-panel">
            <div class="itk-form-hd">
                <b>{{ $adalahIt ? 'CATAT INISIATIF IT — TANPA PEMOHON' : 'AJUKAN PERMINTAAN — BANTU IT BERPIKIR BERSAMAMU' }}</b>
                <span class="itk-badge-fase">FASE 1</span>
            </div>
            <form method="post" action="{{ route('it-tracker.store') }}" enctype="multipart/form-data" id="itkFormPermintaan">
                @csrf
                <div class="itk-panel-body">
                    <div class="itk-ident">
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5.4" r="2.7" stroke="#7A5B43" stroke-width="1.5"/><path d="M2.8 13.5c.8-2.5 2.8-3.8 5.2-3.8s4.4 1.3 5.2 3.8" stroke="#7A5B43" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <span>
                            @if ($adalahIt) Dicatat sebagai inisiatif IT oleh {{ session('name') }} — pemohon tidak ada, tetap masuk papan yang sama.
                            @else {{ session('name') }} · {{ session('group_name') }}
                            @endif
                        </span>
                        <span class="itk-auto">otomatis dari akun</span>
                    </div>
                    {{-- error validasi/flash sudah dirender layouts.notif di atas — jangan dobel --}}

                    @if ($adalahIt)
                        {{-- Tanpa opsi "Permintaan": permintaan HARUS diketik pemohonnya sendiri
                             (memaksa pemohon berpikir = tujuan sistem, rencana §1). --}}
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

                    {{-- Unit usaha: satu papan untuk keduanya — yang dibedakan warnanya (oranye), bukan tempatnya --}}
                    <div>
                        <label class="itk-lbl">KERJAAN INI UNTUK</label>
                        <div style="display:flex;gap:7px;flex-wrap:wrap">
                            @foreach (\App\Services\ItTracker\ItTrackerService::UNIT as $k => $label)
                                <label><input class="itk-radio" type="radio" name="unit" value="{{ $k }}"
                                    {{ old('unit', 'sweettreats') === $k ? 'checked' : '' }} required><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                        <div class="itk-hint">Tiket RB Las tetap satu papan dengan Sweet Treats, hanya ditandai oranye supaya beban keduanya terlihat sekaligus.</div>
                    </div>

                    <div class="itk-grid2">
                        <div>
                            <label class="itk-lbl">JENIS PERMINTAAN</label>
                            <select name="jenis_permintaan" data-isi-wajib data-cek="jenis" required>
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
                        <input class="itk-in" type="text" name="judul" value="{{ old('judul') }}" required maxlength="255" data-cek="nama"
                               placeholder="mis. Laporan KOC bulanan otomatis" style="font-size:13.5px">
                    </div>

                    @if (! $adalahIt)
                        <div>
                            <label class="itk-lbl">SEBERAPA MENDESAK MENURUTMU</label>
                            <div style="display:flex;gap:7px">
                                @foreach (['Rendah', 'Sedang', 'Tinggi'] as $p)
                                    <label><input class="itk-radio" type="radio" name="prioritas_pemohon" value="{{ $p }}" data-cek="prio"
                                        {{ old('prioritas_pemohon') === $p ? 'checked' : '' }} required><span>{{ $p }}</span></label>
                                @endforeach
                            </div>
                            {{-- prioritas_pemohon ≠ prioritas_it — dua kolom, sengaja dipisah --}}
                            <div class="itk-hint">Prioritas akhir tetap ditetapkan IT saat penilaian awal — dua hal ini sengaja dipisah.</div>
                        </div>
                    @else
                        <div class="itk-grid2">
                            <div>
                                <label class="itk-lbl">JENJANG (INISIATIF / INSIDEN)</label>
                                <div style="display:flex;gap:6px">
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
                        <textarea name="latar" rows="2" data-isi-wajib data-cek="latar" required
                            placeholder="mis. Rekap KOC disusun manual dari 3 sumber tiap akhir bulan">{{ old('latar') }}</textarea>
                    </div>
                    <div>
                        <label class="itk-lbl">KALAU INI JADI, APA YANG BERUBAH?</label>
                        <textarea name="kebutuhan" rows="2" data-isi-wajib data-cek="kebutuhan" required
                            placeholder="hasil yang diharapkan — bukan solusi teknisnya">{{ old('kebutuhan') }}</textarea>
                    </div>
                    <div class="itk-grid2">
                        <div>
                            <label class="itk-lbl">SIAPA YANG TERDAMPAK</label>
                            <input class="itk-in" type="text" name="terdampak" value="{{ old('terdampak') }}" placeholder="mis. FO semua cabang">
                        </div>
                        <div>
                            {{-- §7 B Lampiran: screenshot · contoh laporan · alur proses. Tanpa ini buktinya lari ke WhatsApp. --}}
                            <label class="itk-lbl">LAMPIRAN</label>
                            <div class="itk-drop">Seret screenshot / contoh laporan ke sini
                                <input type="file" name="lampiran[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.docx,.doc,.csv" data-lampiran-input>
                            </div>
                            <div class="itk-hint" data-lampiran-hint>Diterima: {{ \App\Services\ItTracker\ItTrackerService::lampiranDiterima() }}. Bisa ditambah lagi selama tiket belum ditriase.</div>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:12px;border-top:1px solid #F1E5D5;padding-top:14px">
                        {{-- tombol mati yang bilang kenapa (P3) — teksnya berubah mengikuti isian --}}
                        <button type="submit" class="itk-btn itk-btn-grafit" id="itkKirim">{{ $adalahIt ? 'Catat inisiatif' : 'Kirim permintaan' }}</button>
                        <span class="itk-submit-note" id="itkKirimNote">Masuk antrean di belakang {{ $beban['diTangan'] }} item — nomor tiket langsung terbit.</span>
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

            <div class="itk-mine-hd"><b>{{ $adalahIt ? 'INISIATIF & INSIDEN IT' : 'PERMINTAAN SAYA' }}</b>
                <span class="n num">{{ $milik->count() }}</span></div>

            @if ($milik->isEmpty())
                <div style="background:#fff;border:1px solid #EFE0CE;border-radius:14px;padding:22px;text-align:center;font-size:12.5px;color:#9A7256;font-weight:600">
                    Belum ada — ajukan lewat formulir di samping; nomor tiket langsung terbit.</div>
            @endif

            @foreach ($milik as $m)
                @php
                    $t = $m['t']; $w = $m['wait'];
                    $stMap = ['TRIASE' => 'Menunggu penilaian IT', 'ANTRE' => 'Antre', 'DITUNDA' => 'Ditunda — tetap tercatat',
                              'DITOLAK' => 'Ditolak', 'SELESAI' => 'Selesai', 'UAT' => 'Bola di tanganmu'];
                    $stCls = isset($stMap[$t->fase]) ? $t->fase : 'KERJA';
                    $stTxt = $stMap[$t->fase] ?? 'Dikerjakan IT';
                    $aktifM = isset(\App\Services\ItTracker\PhaseEngine::RANK[$t->fase]);
                @endphp
                {{-- div ber-onclick, bukan <a>: kartu UAT memuat form keputusan --}}
                <div class="itk-mine-card" style="cursor:pointer" onclick="window.location='{{ route('it-tracker.show', $t->id) }}'">
                    <div class="itk-card-top" style="flex-wrap:nowrap;gap:7px">
                        <span class="itk-no num" style="font-size:11px">{{ $t->nomor }}</span>
                        <span class="itk-mine-st {{ $stCls }}">{{ $stTxt }}</span>
                        <span class="chev">›</span>
                    </div>
                    <div class="itk-card-title" style="font-size:13.5px;margin-top:5px">{{ $t->judul }}</div>
                    @if ($m['prog'] && $aktifM)
                        <div class="itk-mine-bar">
                            <div class="rail"><div class="isi" style="width:{{ round($m['prog']['i'] / $m['prog']['n'] * 100) }}%"></div></div>
                            <span class="num" style="font-size:11px;font-weight:800;color:#5A3A22">{{ $m['prog']['label'] }}</span>
                        </div>
                    @endif
                    <div style="font-size:12px;color:#7A5B43;font-weight:600;margin-top:7px">
                        @if (in_array($t->fase, ['ANTRE', 'TRIASE'], true)) Antre di belakang {{ $m['antre'] ?? '…' }} item · di tangan IT: pemilahan & prioritas.
                        @elseif ($t->fase === 'DITOLAK' || $t->fase === 'DITUNDA') {{ $t->alasan_parkir }}
                        @elseif ($t->fase === 'SELESAI') Selesai {{ $t->fase_since->translatedFormat('j M') }}.
                        @elseif ($t->fase === 'UAT')
                        @elseif ($w['label']) {{ $w['label'] }} · {{ $w['days'] }} hari — bola di IT, bukan di kamu.
                        @endif
                    </div>
                    @if ($t->fase === 'UAT' && ! $adalahIt)
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

<script>
(function () {
    var form = document.getElementById('itkFormPermintaan'), btn = document.getElementById('itkKirim'), note = document.getElementById('itkKirimNote');
    if (!form || !btn) return;
    var adalahIt = {{ $adalahIt ? 'true' : 'false' }};
    var labelSiap = btn.textContent, noteSiap = note ? note.textContent : '';
    function val(sel) { var el = form.querySelector(sel); return el ? el.value.trim() : ''; }
    function insiden() { var p = form.querySelector('input[name=sumber]:checked'); return !!p && p.value === 'insiden'; }
    // Tombol mati yang bilang kenapa (P3). Insiden = formulir menyusul (rencana §3):
    // hanya nama yang wajib, `required` HTML dilepas supaya browser tak memblokir.
    function kurang() {
        if (insiden()) return val('[data-cek=nama]') ? null : 'Beri nama insidennya dulu';
        if (!val('[data-cek=jenis]')) return 'Pilih jenis permintaannya dulu';
        if (!val('[data-cek=nama]')) return 'Beri nama modul/fiturnya dulu';
        if (!val('[data-cek=latar]')) return 'Ceritakan masalahnya dulu';
        if (!val('[data-cek=kebutuhan]')) return 'Jelaskan hasil yang diharapkan dulu';
        if (!adalahIt && !form.querySelector('[data-cek=prio]:checked')) return 'Pilih seberapa mendesak dulu';
        return null;
    }
    function segarkan() {
        var wajib = !insiden();
        form.querySelectorAll('[data-isi-wajib]').forEach(function (el) { el.required = wajib; });
        var k = kurang();
        btn.textContent = k || labelSiap;
        btn.classList.toggle('mati', !!k);
        btn.disabled = !!k;
        if (note) note.textContent = k ? '' : noteSiap;
    }
    form.addEventListener('input', segarkan);
    form.addEventListener('change', segarkan);
    segarkan();
})();
</script>
@endsection
