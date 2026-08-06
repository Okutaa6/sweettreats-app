@extends('layouts.app')

{{-- Aturan Kerja — 3 tab: Aturan (jam per hari) · Penugasan (per orang) ·
     Kalender Libur (exception). docs/rencana/rencana-aturan-kerja.md.
     Zona cokelat default (peta-tema §4), desktop-first, ikon SVG tanpa emoji.
     Halaman ini MENULIS aturan yang dibaca mesin; mesin tidak disentuh. --}}

@php
    $urutanHari = [1, 2, 3, 4, 5, 6, 0];   // tampil Senin..Minggu; index = Carbon dayOfWeek
    $namaHari   = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $j = fn ($t) => $t ? substr($t, 0, 5) : '—';
@endphp

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .ak-wrap { font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px 26px 30px; display: flex; flex-direction: column; gap: 16px; color: #3B1500; }
    .ak-wrap .num { font-variant-numeric: tabular-nums; }
    .ak-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .ak-title { font-size: 20px; font-weight: 800; margin: 0; }
    .ak-subtitle { font-size: 12.5px; font-weight: 500; color: #9A7256; margin-top: 2px; }

    /* alert flash */
    .ak-alert { border-radius: 11px; padding: 11px 15px; font-size: 13px; font-weight: 600; }
    .ak-alert.ok { background: #E3F4E8; color: #2F8E55; }
    .ak-alert.err { background: #FBE3DE; color: #C0392B; }

    /* tabs — dialek fin-tabs (Kehadiran/Recap) versi cokelat: aktif = terisi
       aksen zona, non-aktif = pill ber-hover yang jelas bisa diklik */
    .ak-tabs { display: inline-flex; gap: 4px; background: #fff; border: 1px solid #E7D3BE; border-radius: 13px; padding: 5px; align-self: flex-start; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .ak-tab { border: 0; background: transparent; border-radius: 9px; padding: 9px 16px; font-family: inherit; font-size: 13px; font-weight: 700; color: #7A5B43; cursor: pointer; }
    .ak-tab:hover { background: #FBF4EC; color: #3B1500; }
    .ak-tab.on { background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; box-shadow: 0 4px 12px rgba(20,20,18,.18); }
    .ak-tab.on:hover { background: linear-gradient(135deg, #6B2400, #461500); color: #FBEADB; }
    .ak-pane { display: none; flex-direction: column; gap: 16px; }
    .ak-pane.on { display: flex; }

    /* kartu umum */
    .ak-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); overflow: hidden; }
    .ak-card-pad { padding: 16px 20px; }
    .ak-card h5 { font-size: 14.5px; font-weight: 800; color: #3B1500; margin: 0; }
    .ak-hint { font-size: 12px; color: #9A7256; }

    /* tombol */
    .ak-btn { display: inline-flex; align-items: center; gap: 7px; border-radius: 11px; padding: 9px 15px; font-family: inherit; font-size: 13px; font-weight: 700; cursor: pointer; border: 1px solid transparent; }
    .ak-btn svg { flex: none; }
    .ak-btn-pri { background: linear-gradient(135deg, #6B2400, #461500); color: #fff; box-shadow: 0 4px 12px rgba(20,20,18,.22); }
    .ak-btn-pri:hover { filter: brightness(1.12); }
    .ak-btn-sec { background: #fff; border-color: #E7D3BE; color: #5A3A22; }
    .ak-btn-sec:hover { background: #FBF4EC; }
    .ak-btn-bahaya { background: #fff; border-color: #F0CFC8; color: #C0392B; }
    .ak-btn-bahaya:hover { background: #FBE3DE; }
    .ak-btn-kecil { padding: 6px 11px; font-size: 12px; border-radius: 9px; }

    /* form kontrol */
    .ak-input, .ak-select { border: 1px solid #E7D3BE; border-radius: 10px; background: #fff; padding: 8px 11px; font-family: inherit; font-size: 13px; color: #3B1500; }
    .ak-input::placeholder { color: #C7AC92; }
    .ak-input[type=time] { padding: 6px 8px; width: 92px; }
    .ak-lbl { font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #A9866A; display: block; margin-bottom: 4px; }

    /* header tabel: gradient di thead TR, th transparan (peta-tema §3) */
    .ak-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ak-table thead tr { background: linear-gradient(120deg, #5A2410, #3A1100); }
    .ak-table th { background: transparent; color: #FBEADB; font-size: 11px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; padding: 11px 16px; text-align: left; border: 0; }
    .ak-table td { padding: 11px 16px; font-size: 13px; border-bottom: 1px solid #F4EADD; vertical-align: middle; }
    .ak-table tbody tr:hover { background: #FBF4EC; }
    .ak-table tr.tanpa-hover:hover { background: transparent; }

    /* badge */
    .ak-badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 8px; padding: 4px 10px; font-size: 11.5px; font-weight: 700; }
    .ak-b-baku { background: #E4ECFB; color: #3F6BAF; }
    .ak-b-flex { background: #F1E9DF; color: #A08A76; }
    .ak-b-libur { background: #FBEFD9; color: #B5701A; }
    .ak-b-tanpa { background: #FBE3DE; color: #C0392B; }
    .ak-b-hijau { background: #E3F4E8; color: #2F8E55; }

    /* Tab 1: kartu aturan */
    .ak-rule-head { display: flex; align-items: center; gap: 12px; padding: 15px 20px; border-bottom: 1px solid #F4EADD; flex-wrap: wrap; }
    .ak-rule-head .nm { font-size: 15.5px; font-weight: 800; }
    .ak-rule-head .spacer { margin-left: auto; display: flex; gap: 8px; align-items: center; }
    .ak-hari-libur td { color: #A9866A; }
    .ak-editor { display: none; border-top: 1px dashed #E7D3BE; background: #FCF7F0; padding: 16px 20px; }
    .ak-editor.on { display: block; }
    .ak-editor-grid { display: grid; grid-template-columns: 90px repeat(4, auto) 1fr; gap: 8px 14px; align-items: center; margin-top: 12px; }
    .ak-editor-grid .kepala { font-size: 11px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase; color: #A9866A; }
    .ak-cek { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: #5A3A22; cursor: pointer; margin: 0; }
    .ak-hari-mati input[type=time] { opacity: .35; pointer-events: none; }

    /* Tab 2 */
    .ak-toolbar { display: flex; align-items: center; gap: 11px; flex-wrap: wrap; }
    .ak-search { position: relative; flex: 1; min-width: 220px; max-width: 340px; }
    .ak-search svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #B79A82; }
    .ak-search input { width: 100%; padding-left: 34px; }
    .ak-count { margin-left: auto; font-size: 12.5px; color: #9A7256; }
    .ak-count b { color: #3B1500; }
    .ak-who .nm { font-weight: 700; }
    .ak-who .rl { font-size: 11.5px; color: #A9866A; }
    .ak-chev { border: 0; background: transparent; color: #8A3A12; opacity: .8; cursor: pointer; padding: 4px; transition: transform .15s; }
    tr:hover .ak-chev, .ak-chev:hover { opacity: 1; }
    .ak-chev.buka { transform: rotate(90deg); }
    .ak-detail-row { background: #FCF7F0; }
    .ak-detail-row:hover { background: #FCF7F0 !important; }
    .ak-detail-box { padding: 14px 16px; display: flex; flex-direction: column; gap: 12px; }
    .ak-riwayat { font-size: 12.5px; color: #5A3A22; display: flex; flex-direction: column; gap: 4px; }
    .ak-riwayat .baris { display: flex; gap: 10px; align-items: baseline; }
    .ak-riwayat .tgl { color: #9A7256; font-size: 11.5px; min-width: 190px; }
    .ak-ganti-form { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }

    /* Tab 3 */
    .ak-strip { display: flex; gap: 10px; align-items: flex-start; background: #E4ECFB; color: #3F6BAF; border-radius: 12px; padding: 12px 15px; font-size: 12.5px; font-weight: 600; }
    .ak-strip a { color: #3F6BAF; text-decoration: underline; font-weight: 800; }
    .ak-libur-form { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }

    /* log */
    .ak-log { font-size: 12.5px; }
    .ak-log td { padding: 8px 16px; }
    .ak-log .waktu { color: #9A7256; white-space: nowrap; font-size: 11.5px; }
    .ak-log .aksi { font-weight: 800; color: #5A3A22; white-space: nowrap; }
    .ak-log .detail { color: #7A5B43; word-break: break-word; }

    .ak-empty { text-align: center; padding: 34px 20px; }
    .ak-empty b { display: block; font-size: 14px; font-weight: 800; color: #5A3A22; margin-bottom: 4px; }
    .ak-empty span { font-size: 12.5px; color: #9A7256; }
</style>
@endsection

@section('content')
<div class="ak-wrap">

    <div class="ak-head">
        <div>
            <h4 class="ak-title">Aturan Kerja</h4>
            <div class="ak-subtitle">Jam kerja, penugasan per karyawan, dan kalender libur — yang dibaca mesin absensi.
                Perubahan berlaku <strong>ke depan</strong>; hari yang sudah dinilai tidak dihitung ulang.</div>
        </div>
    </div>

    @if(session('success'))<div class="ak-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ak-alert err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ak-alert err">{{ $errors->first() }}</div>@endif

    <div class="ak-tabs" role="tablist">
        <button type="button" class="ak-tab" data-tab="aturan">Aturan</button>
        <button type="button" class="ak-tab" data-tab="penugasan">Penugasan</button>
        <button type="button" class="ak-tab" data-tab="libur">Kalender Libur</button>
    </div>

    {{-- ══════════════════════════ TAB 1 · ATURAN ══════════════════════════ --}}
    <div class="ak-pane" data-pane="aturan">

        @foreach($rules as $rule)
        <div class="ak-card">
            <div class="ak-rule-head">
                <span class="nm">{{ $rule->nama }}</span>
                <span class="ak-badge {{ $rule->tipe === 'baku' ? 'ak-b-baku' : 'ak-b-flex' }}">{{ $rule->tipe }}</span>
                <span class="ak-hint num">{{ $rule->penugasan_aktif_count }} karyawan memakai</span>
                @if($rule->keterangan)<span class="ak-hint">· {{ $rule->keterangan }}</span>@endif
                <span class="spacer">
                    <button type="button" class="ak-btn ak-btn-sec ak-btn-kecil" data-editor="rule-{{ $rule->id }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        Ubah
                    </button>
                    @if($rule->penugasan_aktif_count === 0)
                    <form method="POST" action="{{ route('absensi.aturan.rule.hapus', $rule->id) }}"
                          onsubmit="return confirm('Hapus aturan ini? Hanya bisa kalau belum pernah dipakai.')">
                        @csrf
                        <button type="submit" class="ak-btn ak-btn-bahaya ak-btn-kecil">Hapus</button>
                    </form>
                    @endif
                </span>
            </div>

            @if($rule->tipe === 'baku')
            <table class="ak-table">
                <thead><tr><th>Hari</th><th>Jam masuk (window)</th><th>Jam pulang</th><th>Lembur mulai</th></tr></thead>
                <tbody>
                @foreach($urutanHari as $d)
                    @php $rd = $rule->days->firstWhere('day', $d); @endphp
                    <tr class="{{ ($rd && $rd->libur) ? 'ak-hari-libur' : '' }}">
                        <td style="font-weight:700">{{ $namaHari[$d] }}</td>
                        @if($rd && $rd->libur)
                            <td colspan="3"><span class="ak-badge ak-b-libur">Libur</span></td>
                        @else
                            <td class="num">{{ $j($rd->jam_masuk_awal ?? null) }} – {{ $j($rd->jam_masuk_akhir ?? null) }}</td>
                            <td class="num">{{ $j($rd->jam_pulang ?? null) }}</td>
                            <td class="num">{{ $j($rd->jam_lembur_mulai ?? null) }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            @else
            <div class="ak-card-pad ak-hint">Jam bebas — scan tidak dinilai telat/alpa, hanya penanda hadir.
                Lembur & WFH tetap lewat form.</div>
            @endif

            {{-- editor (tersembunyi sampai "Ubah") --}}
            <div class="ak-editor" id="rule-{{ $rule->id }}">
                <form method="POST" action="{{ route('absensi.aturan.rule.ubah', $rule->id) }}"
                      onsubmit="return konfirmUbahAturan(this, {{ $rule->penugasan_aktif_count }})">
                    @csrf
                    @include('absensi._aturan_form', ['rule' => $rule])
                </form>
            </div>
        </div>
        @endforeach

        <div class="ak-card">
            <div class="ak-rule-head">
                <span class="nm">Aturan baru</span>
                <span class="spacer">
                    <button type="button" class="ak-btn ak-btn-pri ak-btn-kecil" data-editor="rule-baru">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Tambah aturan
                    </button>
                </span>
            </div>
            <div class="ak-editor" id="rule-baru">
                <form method="POST" action="{{ route('absensi.aturan.rule.buat') }}">
                    @csrf
                    @include('absensi._aturan_form', ['rule' => null])
                </form>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════ TAB 2 · PENUGASAN ═════════════════════════ --}}
    <div class="ak-pane" data-pane="penugasan">

        @php $tanpaAturan = $karyawan->filter(fn ($u) => ! isset($aktif[$u->id]))->count(); @endphp

        <div class="ak-toolbar">
            <div class="ak-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="text" id="ak-cari" class="ak-input" placeholder="Cari nama / role / cabang…">
            </div>
            @if($tanpaAturan > 0)
                <span class="ak-badge ak-b-tanpa num">{{ $tanpaAturan }} karyawan belum punya aturan — scan-nya tidak dinilai</span>
            @endif
            <span class="ak-count num">Aktif: <b>{{ $karyawan->count() }}</b> karyawan</span>
        </div>

        <div class="ak-card">
            <table class="ak-table" id="ak-tabel-karyawan">
                <thead><tr><th>Karyawan</th><th>Cabang</th><th>Aturan aktif</th><th>Sejak</th><th style="width:36px"></th></tr></thead>
                <tbody>
                @forelse($karyawan as $u)
                    @php
                        $cur = $aktif[$u->id] ?? null;
                        $rw  = $riwayat[$u->id] ?? collect();
                    @endphp
                    {{-- data-buka, BUKAN data-toggle — atribut itu milik data-API Bootstrap --}}
                    <tr class="ak-baris-orang" data-cari="{{ strtolower($u->nama . ' ' . ($u->role_nama ?? '') . ' ' . ($u->cabang ?? '')) }}"
                        data-buka="det-{{ $u->id }}" style="cursor:pointer">
                        <td class="ak-who">
                            <div class="nm">{{ $u->nama }}</div>
                            <div class="rl">{{ $u->role_nama ?? '—' }}</div>
                        </td>
                        <td>{{ $u->cabang ?? '—' }}</td>
                        <td>
                            @if($cur)
                                <span class="ak-badge {{ optional($cur->rule)->tipe === 'baku' ? 'ak-b-baku' : 'ak-b-flex' }}">{{ optional($cur->rule)->nama ?? '?' }}</span>
                            @else
                                <span class="ak-badge ak-b-tanpa">belum ada</span>
                            @endif
                        </td>
                        <td class="num">{{ $cur->effective_from ?? '—' }}</td>
                        <td>
                            <button type="button" class="ak-chev" aria-label="Detail {{ $u->nama }}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                            </button>
                        </td>
                    </tr>
                    <tr class="ak-detail-row tanpa-hover" id="det-{{ $u->id }}" style="display:none">
                        <td colspan="5" style="padding:0">
                            <div class="ak-detail-box">
                                <form class="ak-ganti-form" method="POST" action="{{ route('absensi.aturan.penugasan') }}">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <div>
                                        <label class="ak-lbl">Aturan baru</label>
                                        <select name="rule_id" class="ak-select" required>
                                            <option value="">— pilih aturan —</option>
                                            @foreach($rules as $r)
                                                <option value="{{ $r->id }}" @if($cur && (int) $cur->rule_id === (int) $r->id) disabled @endif>{{ $r->nama }} ({{ $r->tipe }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="ak-lbl">Mulai berlaku</label>
                                        <input type="date" name="effective_from" class="ak-input" value="{{ $today }}" min="{{ $today }}" required>
                                    </div>
                                    <button type="submit" class="ak-btn ak-btn-pri ak-btn-kecil">Simpan penugasan</button>
                                    <span class="ak-hint">Baris lama ditutup otomatis — riwayat tersimpan sebagai jejak gaji.</span>
                                </form>
                                @if($rw->count())
                                <div class="ak-riwayat">
                                    <span class="ak-lbl" style="margin-bottom:0">Riwayat</span>
                                    @foreach($rw as $h)
                                        <div class="baris">
                                            <span class="tgl num">{{ $h->effective_from }} → {{ $h->effective_to ?? 'sekarang' }}</span>
                                            <span>{{ optional($h->rule)->nama ?? '?' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="ak-empty"><b>Belum ada karyawan aktif</b><span>Buat akun dulu di Pusat Akun.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="ak-card">
            <div class="ak-card-pad" style="border-bottom:1px solid #F4EADD">
                <h5>Aturan per role</h5>
                <div class="ak-hint" style="margin-top:3px">Akun baru otomatis memakai aturan role-nya (mis. Front
                    Office → FO-Asdent). Untuk karyawan yang <b>sudah ada</b>, pakai tombol
                    <b>Terapkan</b> — berlaku mulai hari ini, tidak mengubah masa lalu yang sudah ternilai.
                    Penugasan per orang di tabel atas tetap menang, dan orang yang sudah punya penugasan
                    terjadwal ke depan sengaja dilewati.</div>
            </div>
            <table class="ak-table">
                <thead><tr><th>Role</th><th>Aturan default</th><th style="width:140px"></th></tr></thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td style="font-weight:700">{{ $role->nama }}</td>
                        <td colspan="2">
                            <form method="POST" action="{{ route('absensi.aturan.default-role') }}" style="display:flex;gap:8px;align-items:center">
                                @csrf
                                <input type="hidden" name="group_id" value="{{ $role->id }}">
                                <select name="rule_id" class="ak-select">
                                    <option value="">— tidak otomatis —</option>
                                    @foreach($rules as $r)
                                        <option value="{{ $r->id }}" @if((int) $role->att_default_rule_id === (int) $r->id) selected @endif>{{ $r->nama }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="ak-btn ak-btn-sec ak-btn-kecil">Simpan</button>
                            </form>
                            @if($role->att_default_rule_id)
                                {{-- Tombol terpisah, bukan otomatis saat Simpan: mengubah
                                     aturan kerja orang yang sedang berjalan itu keputusan,
                                     bukan efek samping. --}}
                                <form method="POST" action="{{ route('absensi.aturan.default-role.terapkan') }}"
                                      style="margin-top:6px"
                                      onsubmit="return confirm('Terapkan aturan ini ke semua karyawan aktif ber-role {{ addslashes($role->nama) }}, mulai hari ini?')">
                                    @csrf
                                    <input type="hidden" name="group_id" value="{{ $role->id }}">
                                    <button type="submit" class="ak-btn ak-btn-kecil">Terapkan ke karyawan yang sudah ada</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══════════════════════ TAB 3 · KALENDER LIBUR ═══════════════════════ --}}
    <div class="ak-pane" data-pane="libur">

        <div class="ak-strip">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" style="flex:none;margin-top:1px"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M12 11v5"/></svg>
            <span>Libur nasional <strong>tidak memotong saldo cuti</strong>. Kalau pemerintah menetapkan
                <strong>cuti bersama</strong> (memotong saldo), aturlah di halaman
                <a href="{{ url('absensi/cuti') }}">Cuti</a> — jangan dicatat dua kali.
                Tanggal diinput manual dari SKB 3 Menteri tiap tahun.</span>
        </div>

        <div class="ak-card">
            <div class="ak-card-pad">
                <h5 style="margin-bottom:10px">Tambah libur</h5>
                <form class="ak-libur-form" method="POST" action="{{ route('absensi.aturan.libur.buat') }}">
                    @csrf
                    <div style="flex:1;min-width:220px">
                        <label class="ak-lbl">Nama libur</label>
                        <input type="text" name="alasan" class="ak-input" style="width:100%" placeholder="mis. Hari Kemerdekaan RI" required maxlength="255" value="{{ old('alasan') }}">
                    </div>
                    <div>
                        <label class="ak-lbl">Mulai</label>
                        <input type="date" name="tgl_from" class="ak-input" min="{{ $today }}" required value="{{ old('tgl_from') }}">
                    </div>
                    <div>
                        <label class="ak-lbl">Selesai <span style="text-transform:none;font-weight:600">(kosong = 1 hari)</span></label>
                        <input type="date" name="tgl_to" class="ak-input" min="{{ $today }}" value="{{ old('tgl_to') }}">
                    </div>
                    <div>
                        <label class="ak-lbl">Cabang</label>
                        <select name="raw_branch_id" class="ak-select">
                            <option value="">Semua cabang (nasional)</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="ak-btn ak-btn-pri">Catat libur</button>
                </form>
                <div class="ak-hint" style="margin-top:8px">Berlaku otomatis ke semua aturan <strong>baku</strong> —
                    karyawan tidak ditandai alpa di tanggal itu. Office/fleksibel memang tidak dinilai scan.</div>
            </div>
        </div>

        <div class="ak-card">
            <table class="ak-table">
                <thead><tr><th>Tanggal</th><th>Nama libur</th><th>Cakupan</th><th style="width:110px"></th></tr></thead>
                <tbody>
                @forelse($mendatang as $e)
                    <tr>
                        <td class="num" style="font-weight:700">{{ $e->tgl_from }}@if($e->tgl_to !== $e->tgl_from) – {{ $e->tgl_to }}@endif</td>
                        <td>{{ $e->alasan }}
                            @if(! $e->libur)<span class="ak-badge ak-b-libur" style="margin-left:6px">jam khusus</span>@endif
                        </td>
                        <td>{{ $e->raw_branch_id ? optional($e->branch)->name : 'Semua cabang' }}</td>
                        <td>
                            @if($e->tgl_from > $today)
                                <form method="POST" action="{{ route('absensi.aturan.libur.hapus', $e->id) }}"
                                      onsubmit="return confirm('Batalkan libur yang belum mulai ini?')">
                                    @csrf
                                    <button type="submit" class="ak-btn ak-btn-bahaya ak-btn-kecil">Batalkan</button>
                                </form>
                            @else
                                <span class="ak-hint">sedang berjalan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="ak-empty"><b>Belum ada libur mendatang</b><span>Tambahkan dari SKB 3 Menteri di form atas — Lebaran tahun depan jangan sampai kelewat.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($lampau->count())
        <div class="ak-card">
            <div class="ak-card-pad" style="border-bottom:1px solid #F4EADD"><h5>Sudah lewat</h5>
                <div class="ak-hint" style="margin-top:3px">Jejak penilaian — tidak bisa diubah dari sini.</div></div>
            <table class="ak-table ak-log">
                <tbody>
                @foreach($lampau as $e)
                    <tr>
                        <td class="num" style="color:#9A7256">{{ $e->tgl_from }}@if($e->tgl_to !== $e->tgl_from) – {{ $e->tgl_to }}@endif</td>
                        <td>{{ $e->alasan }}</td>
                        <td>{{ $e->raw_branch_id ? optional($e->branch)->name : 'Semua cabang' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ═══════════════ Riwayat perubahan (semua tab) ═══════════════ --}}
    <div class="ak-card">
        <div class="ak-card-pad" style="border-bottom:1px solid #F4EADD"><h5>Riwayat perubahan</h5>
            <div class="ak-hint" style="margin-top:3px">Siapa mengubah apa — 30 terakhir. Aturan kerja menentukan gaji;
                jejak ini tidak bisa dihapus.</div></div>
        <table class="ak-table ak-log">
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="waktu num">{{ $log->created_at ? $log->created_at->format('d M Y H.i') : '—' }}</td>
                    <td style="font-weight:700;white-space:nowrap">{{ $log->actor_nama ?? '—' }}</td>
                    <td class="aksi">{{ $log->aksi }}</td>
                    <td class="detail"><strong>{{ $log->target }}</strong>
                        @if($log->detail)
                            @php $det = json_decode($log->detail, true); @endphp
                            @if(is_array($det))
                                — @foreach($det as $k => $v)@if(! is_array($v)){{ $k }}: {{ $v }}@if(! $loop->last) · @endif @endif @endforeach
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="ak-empty"><b>Belum ada perubahan</b><span>Semua perubahan dari halaman ini akan tercatat di sini.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('js')
<script>
(function () {
    // ── tab (server kasih tab awal via ?tab=; pindah tab tanpa reload) ──
    var tabAwal = @json($tab);
    function bukaTab(nama) {
        document.querySelectorAll('.ak-tab').forEach(function (t) { t.classList.toggle('on', t.dataset.tab === nama); });
        document.querySelectorAll('.ak-pane').forEach(function (p) { p.classList.toggle('on', p.dataset.pane === nama); });
        try { history.replaceState(null, '', '?tab=' + nama); } catch (e) {}
    }
    document.querySelectorAll('.ak-tab').forEach(function (t) {
        t.addEventListener('click', function () { bukaTab(t.dataset.tab); });
    });
    bukaTab(['aturan', 'penugasan', 'libur'].indexOf(tabAwal) >= 0 ? tabAwal : 'aturan');

    // ── buka/tutup editor aturan ──
    document.querySelectorAll('[data-editor]').forEach(function (b) {
        b.addEventListener('click', function () {
            var e = document.getElementById(b.dataset.editor);
            if (e) e.classList.toggle('on');
        });
    });

    // ── baris karyawan → expand detail (chevron ikut berputar) ──
    document.querySelectorAll('[data-buka]').forEach(function (tr) {
        tr.addEventListener('click', function (ev) {
            if (ev.target.closest('form') || ev.target.closest('select') || ev.target.closest('input')) return;
            var det = document.getElementById(tr.dataset.buka);
            var buka = det.style.display === 'none';
            det.style.display = buka ? '' : 'none';
            var chev = tr.querySelector('.ak-chev');
            if (chev) chev.classList.toggle('buka', buka);
        });
    });

    // ── cari karyawan (client-side) ──
    var cari = document.getElementById('ak-cari');
    if (cari) cari.addEventListener('input', function () {
        var q = cari.value.toLowerCase().trim();
        document.querySelectorAll('.ak-baris-orang').forEach(function (tr) {
            var tampil = !q || tr.dataset.cari.indexOf(q) >= 0;
            tr.style.display = tampil ? '' : 'none';
            var det = document.getElementById(tr.dataset.buka);
            if (det && !tampil) { det.style.display = 'none'; var c = tr.querySelector('.ak-chev'); if (c) c.classList.remove('buka'); }
        });
    });

    // ── editor: tipe fleksibel → grid jam disembunyikan; libur → jam mati ──
    window.aturTipe = function (sel) {
        var grid = sel.closest('form').querySelector('.ak-editor-grid');
        if (grid) grid.style.display = sel.value === 'baku' ? '' : 'none';
    };
    window.aturLibur = function (cek) {
        var row = cek.closest('.ak-hari-row');
        if (row) row.classList.toggle('ak-hari-mati', cek.checked);
    };
    document.querySelectorAll('.ak-editor select[name=tipe]').forEach(function (s) { aturTipe(s); });
    document.querySelectorAll('.ak-editor .ak-hari-row input[type=checkbox]').forEach(function (c) { aturLibur(c); });

    // ── konfirmasi ubah aturan: "berlaku untuk N karyawan mulai besok" (rencana §3) ──
    window.konfirmUbahAturan = function (form, n) {
        if (n > 0) {
            return confirm('Perubahan jam aturan ini berlaku untuk ' + n +
                ' karyawan mulai perhitungan BESOK PAGI.\nMasa lalu tidak dihitung ulang. Lanjutkan?');
        }
        return true;
    };
})();
</script>
@endsection
