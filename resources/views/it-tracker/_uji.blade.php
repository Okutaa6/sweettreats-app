{{--
    FRM-IT-04 — Internal Testing (Kelas B, Fase 2b). IT sendiri, tanpa paraf pihak lain.

    Empat angka TERHITUNG (TC · Pass · Fail/Belum · Major terbuka) + bar.
    TC-01..05 = lima pemeriksaan tetap, TERKUNCI (gembok): skenario tak bisa
    diubah/dihapus, tapi aktual & Pass/Fail diisi normal DAN ikut terhitung.
    TC berikutnya lewat baris hantu. Temuan Minor/Major; Major menahan gerbang.

    Butuh: $t, $v, $bolehUji, $picOpsi (boleh kosong), $gate
--}}
@php
    $tc     = $v['fr04']['tc'] ?? [];
    $temuan = $v['fr04']['temuan'] ?? [];
    $ed     = $bolehUji;
    $nPass  = count(array_filter($tc, fn ($r) => ($r['status'] ?? null) === 'pass'));
    $nFail  = count(array_filter($tc, fn ($r) => ($r['status'] ?? null) === 'fail'));
    $nBelum = count(array_filter($tc, fn ($r) => empty($r['status'])));
    $nMajor = count(array_filter($temuan, fn ($x) => $x['tingkat'] === 'Major' && $x['status'] !== 'Selesai'));
    $nTc    = count($tc);
    $picNama = collect($picOpsi ?? [])->keyBy('id');
@endphp

<div class="itk-panel" style="margin-bottom:12px">
    <div class="itk-beban-bar"><b>FRM-IT-04 — INTERNAL TESTING</b>
        <span class="itk-badge-fase">FASE 2b · KELAS B</span></div>

    <div class="itk-panel-body">
        <div class="itk-auto-note">Lima pemeriksaan tetap sudah menunggu; ringkasan dan gerbang dihitung sendiri. Developer testing templat dilebur ke sini.</div>

        {{-- ringkasan terhitung --}}
        <div class="itk-stat4">
            <div class="itk-stat"><div class="k">TEST CASE</div><div class="n num">{{ $nTc }}</div></div>
            <div class="itk-stat hijau"><div class="k">PASS</div><div class="n num">{{ $nPass }}</div></div>
            <div class="itk-stat {{ $nFail ? 'merah' : '' }}"><div class="k">FAIL · BELUM</div><div class="n num">{{ $nFail }} · {{ $nBelum }}</div></div>
            <div class="itk-stat {{ $nMajor ? 'merah' : 'hijau' }}"><div class="k">MAJOR TERBUKA</div><div class="n num">{{ $nMajor }}</div></div>
        </div>
        <div class="itk-bar2"><div class="p" style="width:{{ $nTc ? round($nPass / $nTc * 100) : 0 }}%"></div><div class="f" style="width:{{ $nTc ? round($nFail / $nTc * 100) : 0 }}%"></div></div>

        {{-- tabel TC --}}
        <form method="post" action="{{ route('it-tracker.tc', $t->id) }}" id="itkTcForm">
            @csrf
            <div class="itk-tbl itk-tbl-tc">
                <div class="itk-tbl-row itk-tbl-head">
                    <span>TC</span><span>SKENARIO</span><span>DIHARAPKAN</span><span>AKTUAL</span><span>HASIL</span><span></span>
                </div>
                @foreach ($tc as $i => $r)
                    @php $lk = ! empty($r['locked']); $st = $r['status'] ?? null; @endphp
                    <div class="itk-tbl-row {{ $st === 'fail' ? 'gagal' : ($st === 'pass' ? '' : 'belum') }}">
                        <span class="itk-tbl-no num">{{ $r['id'] }} @if ($lk)<span class="itk-lock" title="pemeriksaan tetap">🔒</span>@endif</span>
                        <span>
                            <input type="hidden" name="baris[{{ $i }}][kode]" value="{{ $r['id'] }}">
                            @if ($ed && ! $lk)<input class="itk-cell itk-cell-b" name="baris[{{ $i }}][skenario]" value="{{ $r['skenario'] }}" maxlength="500">
                            @else<span class="itk-ro itk-ro-b">{{ $r['skenario'] }}</span>@endif
                        </span>
                        <span>@if ($ed && ! $lk)<input class="itk-cell" name="baris[{{ $i }}][diharapkan]" value="{{ $r['harapan'] }}" maxlength="500" placeholder="hasil yang diharapkan">@else<span class="itk-ro">{{ $r['harapan'] ?: '—' }}</span>@endif</span>
                        <span>@if ($ed)<input class="itk-cell" name="baris[{{ $i }}][aktual]" value="{{ $r['aktual'] }}" maxlength="500" placeholder="yang terjadi">@else<span class="itk-ro">{{ $r['aktual'] ?: '—' }}</span>@endif</span>
                        <span class="itk-seg">
                            @if ($ed)
                                <label class="itk-seg-btn pass {{ $st === 'pass' ? 'on' : '' }}"><input type="radio" name="baris[{{ $i }}][status]" value="pass" {{ $st === 'pass' ? 'checked' : '' }}>Pass</label>
                                <label class="itk-seg-btn fail {{ $st === 'fail' ? 'on' : '' }}"><input type="radio" name="baris[{{ $i }}][status]" value="fail" {{ $st === 'fail' ? 'checked' : '' }}>Fail</label>
                            @else
                                <span class="itk-seg-btn {{ $st === 'pass' ? 'pass on' : ($st === 'fail' ? 'fail on' : '') }}">{{ $st === 'pass' ? 'Pass' : ($st === 'fail' ? 'Fail' : 'belum') }}</span>
                            @endif
                        </span>
                        <span class="itk-dotwrap">@if ($ed && ! $lk)<button type="submit" form="itkTcHapus-{{ $i }}" class="itk-x" title="Hapus">✕</button>@endif</span>
                    </div>
                @endforeach
                @if ($ed)
                    <div class="itk-tbl-row itk-tbl-ghost">
                        <span class="itk-tbl-no num">TC-{{ str_pad($nTc + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <span><input class="itk-cell itk-cell-b" name="baris[{{ $nTc }}][skenario]" maxlength="500" placeholder="skenario uji berikutnya… (kosong = diabaikan)"></span>
                        <span><input class="itk-cell" name="baris[{{ $nTc }}][diharapkan]" maxlength="500" placeholder="hasil yang diharapkan"></span>
                        <span><input class="itk-cell" name="baris[{{ $nTc }}][aktual]" maxlength="500" placeholder="yang terjadi"></span>
                        <span class="itk-seg">
                            <label class="itk-seg-btn pass"><input type="radio" name="baris[{{ $nTc }}][status]" value="pass">Pass</label>
                            <label class="itk-seg-btn fail"><input type="radio" name="baris[{{ $nTc }}][status]" value="fail">Fail</label>
                        </span>
                        <span></span>
                    </div>
                @endif
                <div class="itk-tbl-kaki">🔒 TC-01–05 = lima pemeriksaan tetap, ada di setiap tiket dan tak bisa dihapus. Ringkasan di atas dihitung sendiri.</div>
            </div>
            @if ($ed)
                <button type="submit" class="itk-btn itk-btn-grafit" style="margin-top:8px;padding:7px 13px;font-size:12px">Simpan hasil uji</button>
            @endif
        </form>
        @if ($ed)
            @foreach ($tc as $i => $r)
                @unless (! empty($r['locked']))
                    <form method="post" action="{{ route('it-tracker.tc.hapus', $t->id) }}" id="itkTcHapus-{{ $i }}" hidden>@csrf<input type="hidden" name="kode" value="{{ $r['id'] }}"></form>
                @endunless
            @endforeach
        @endif

        {{-- Temuan --}}
        <div>
            <div class="itk-tbl-hd"><span class="itk-lbl" style="margin:0">TEMUAN</span>
                <span class="itk-hint" style="margin:0">Major menahan gerbang ke UAT · Minor boleh dibawa</span></div>
            <div style="display:flex;flex-direction:column;gap:6px">
                @forelse ($temuan as $tm)
                    <div class="itk-temuan">
                        @if ($ed)
                            <form method="post" action="{{ route('it-tracker.temuan', $t->id) }}" style="display:contents">
                                @csrf<input type="hidden" name="aksi" value="ubah"><input type="hidden" name="row_id" value="{{ $tm['rowId'] }}">
                                <button type="submit" name="tingkat" value="{{ $tm['tingkat'] === 'Major' ? 'Minor' : 'Major' }}" class="itk-tingkat {{ $tm['tingkat'] }}" title="klik: Minor ⇄ Major">{{ $tm['tingkat'] }}</button>
                                <input class="itk-cell itk-cell-b" name="deskripsi" value="{{ $tm['desc'] }}" maxlength="500" style="flex:1">
                                <select name="pic_user_id" class="itk-pic-sel">
                                    @foreach ($picOpsi ?? [] as $p)<option value="{{ $p->id }}" {{ $tm['pic'] === $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>@endforeach
                                </select>
                                <button type="submit" name="status" value="{{ $tm['status'] === 'Selesai' ? 'Terbuka' : 'Selesai' }}" class="itk-tm-status {{ $tm['status'] }}">{{ $tm['status'] }}</button>
                                <button type="submit" class="itk-btn itk-btn-putih" style="padding:4px 9px;font-size:10.5px">Simpan</button>
                            </form>
                            <form method="post" action="{{ route('it-tracker.temuan', $t->id) }}">@csrf<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="row_id" value="{{ $tm['rowId'] }}"><button type="submit" class="itk-x">✕</button></form>
                        @else
                            <span class="itk-tingkat {{ $tm['tingkat'] }}">{{ $tm['tingkat'] }}</span>
                            <span class="itk-ro itk-ro-b" style="flex:1">{{ $tm['desc'] }}</span>
                            <span class="itk-hint" style="margin:0">PIC {{ $picNama[$tm['pic']]->nama ?? ($tm['pic'] ? '…' : '—') }}</span>
                            <span class="itk-tm-status {{ $tm['status'] }}">{{ $tm['status'] }}</span>
                        @endif
                    </div>
                @empty
                    <div class="itk-temuan-none">Belum ada temuan — kalau tetap begini sampai semua TC Pass, gerbang UAT terbuka.</div>
                @endforelse
                @if ($ed)
                    <form method="post" action="{{ route('it-tracker.temuan', $t->id) }}" style="display:flex;gap:6px">
                        @csrf<input type="hidden" name="aksi" value="tambah">
                        <input class="itk-in" name="deskripsi" maxlength="500" required placeholder="catat temuan baru (masuk sebagai Minor, klik badge untuk Major)…" style="flex:1;border-style:dashed">
                        <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Tambah</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="itk-paraf">
            <span class="itk-lbl" style="margin:0">PARAF</span>
            <span class="itk-paraf-it">IT sendiri — tanpa paraf pihak lain. Tombol gerbang = parafnya.</span>
        </div>
    </div>
</div>
