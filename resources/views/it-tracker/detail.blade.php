{{--
    IT Request Tracker — Detail Tiket (Fase 1).

    Fase 1 menampilkan: kepala tiket + rel kemajuan fase, isi FRM-IT-01
    (baca-saja), prioritas pemohon vs IT (dua kolom, sengaja), "menunggu siapa",
    langkah berikutnya (tombol mati MENYEBUT yang kurang — pola P3), dan
    tunda/tolak. Akordeon tujuh formulir per fase datang di Fase 2.
--}}
@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/it-tracker.sweettreats.css') }}?v={{ @filemtime(public_path('assets/css/it-tracker.sweettreats.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $labelFase = ['TRIASE' => 'Triase', 'ANTRE' => 'Antre', 'SELESAI' => 'Selesai',
                  'DITUNDA' => 'Ditunda', 'DITOLAK' => 'Ditolak'];
    $srcLabel  = ['permintaan' => strtoupper($t->pemohon->nama ?? 'PERMINTAAN'),
                  'inisiatif' => 'INISIATIF IT', 'insiden' => 'INSIDEN'];
@endphp
<div class="itk-surface itk-detail">

    <div class="itk-head">
        <a class="itk-btn itk-btn-putih" href="{{ $peran === 'head' ? route('it-tracker.permintaan') : route('it-tracker.index') }}">‹ Kembali</a>
        <h1 class="num">{{ $t->nomor }}</h1>
        <span class="itk-badge-zona">ZONA IT</span>
    </div>

    @include('layouts.notif')

    {{-- Kepala tiket + rel fase --}}
    <div class="itk-panel" style="margin-bottom:14px">
        <div class="itk-detail-head">
            <div style="flex:1;min-width:0">
                <div class="itk-card-top">
                    <span class="itk-no num" style="font-size:12.5px">{{ $t->nomor }}</span>
                    <span class="itk-src {{ $t->sumber }}">{{ $srcLabel[$t->sumber] ?? strtoupper($t->sumber) }}</span>
                    <span class="itk-jenjang {{ $t->jenjang ?? 'baru' }}">{{ \App\Services\ItTracker\ItTrackerService::jenjangLabel($t->jenjang) }}{{ $t->jenjang ? ' · ' . $t->jenjang : '' }}</span>
                </div>
                <div class="itk-detail-title">{{ $t->judul }}</div>
                <div class="itk-detail-meta">
                    @if ($t->pemohon)
                        <span>Pemohon: {{ $t->pemohon->nama }}{{ $t->branch ? ' · ' . $t->branch->name : '' }}</span>
                    @endif
                    @if ($t->pic)<span>PIC: {{ $t->pic->nama }}</span>@endif
                    <span>Masuk fase ini {{ $t->fase_since->translatedFormat('j M Y H:i') }}</span>
                    @if (! empty($v['parent']))<span class="num" title="lingkaran tertutup: lahir dari tindak lanjut tiket lain">↳ Lahir dari {{ $v['parent']['id'] }} · {{ $v['parent']['via'] }}</span>@endif
                </div>
                @if (! empty($v['fr02']['diteruskan']))
                    @php $lw = $v['fr02']['diteruskan']; @endphp
                    {{-- Penanda pelewatan paraf BO (§14.16): di KEPALA tiket supaya ikut sampai Go Live
                         dan tak bisa disembunyikan — bukan hanya di panel 02 --}}
                    <div class="itk-banner itk-lewat-banner num"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
                        Diteruskan {{ $lw['oleh'] }} (Head IT) <strong>tanpa paraf Business Owner</strong> · {{ \Carbon\Carbon::parse($lw['at'])->translatedFormat('j M H:i') }} · {{ $lw['alasan'] }}
                        @if (! empty($v['fr02']['paraf'])) <span class="itk-lewat-susul">BO memparaf menyusul {{ \Carbon\Carbon::parse($v['fr02']['paraf']['at'])->translatedFormat('j M H:i') }}</span>@endif
                    </div>
                @endif
                @if (count($v['children']))
                    <div style="display:flex;align-items:center;gap:7px;margin-top:9px;flex-wrap:wrap">
                        <span style="font-size:11px;font-weight:800;color:#3F6BAF">{{ count($v['children']) }} fitur di dalam modul ini:</span>
                        @foreach ($v['children'] as $anak)<span class="itk-anak">{{ $anak }}</span>@endforeach
                    </div>
                @endif
                @if (in_array($t->fase, ['DITUNDA', 'DITOLAK'], true))
                    <div class="itk-banner {{ $t->fase }}">
                        {{ $t->fase === 'DITUNDA' ? 'Ditunda' : 'Ditolak' }} — {{ $t->alasan_parkir }}
                        {{ $t->fase === 'DITUNDA' ? ' Tidak sedang dikerjakan, tetap tercatat.' : '' }}
                        @if ($t->fase === 'DITUNDA' && $peran === 'head_it')
                            <form method="post" action="{{ route('it-tracker.buka', $t->id) }}" style="margin-top:8px">
                                @csrf
                                {{-- tiket yang belum pernah ditriase kembali ke Triase, bukan Antre --}}
                                <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Buka lagi → {{ $t->jenjang ? 'Antre' : 'Triase' }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
            @php $boxCls = \App\Services\ItTracker\PhaseEngine::waitCls($wait, $t->fase); @endphp
            <div class="itk-waitbox itk-wait {{ $boxCls }}" style="display:block">
                <div class="lab">{{ $t->fase === 'ANTRE' ? 'POSISI ANTREAN' : 'MENUNGGU' }}</div>
                @if ($t->fase === 'ANTRE')
                    <div class="big num">{{ $antre }}</div>
                    <div class="sub">item di depannya</div>
                @elseif (in_array($t->fase, ['SELESAI', 'DITUNDA', 'DITOLAK'], true))
                    <div class="big">{{ $labelFase[$t->fase] }}</div>
                @else
                    <div class="big num">{{ $wait['days'] }} hr</div>
                    <div class="sub">{{ $wait['label'] }}</div>
                @endif
            </div>
        </div>
        @if ($t->jenjang && ! in_array($t->fase, ['DITUNDA', 'DITOLAK'], true))
            @php
                $kiniIdx = array_search(\App\Services\ItTracker\PhaseEngine::phaseOf($t->fase), $jalur, true);
                $selesai = $t->fase === 'SELESAI';
            @endphp
            <div class="itk-rel">
                @foreach ($jalur as $i => $k)
                    @php $st = $selesai || ($kiniIdx !== false && $i < $kiniIdx) ? 'lewat' : (($kiniIdx !== false && $i === $kiniIdx) ? 'kini' : ''); @endphp
                    <div class="itk-step {{ $st }}">
                        <div class="garis"></div>
                        <div class="dot">{{ $st === 'lewat' ? '✓' : $i + 1 }}</div>
                        <span class="lbl">{{ \App\Services\ItTracker\PhaseEngine::phShort($k) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="itk-detail-grid">
        <div>
            {{-- FRM-IT-01 — isi kebutuhan (baca-saja) --}}
            @if (! $form01 && ! (in_array($t->jenjang, ['M', 'L'], true) && $t->fase === 'DEV')
                 && ! ($t->jenjang === 'S' && in_array($t->fase, ['DEV', 'SELESAI'], true)))
                {{-- tiket tanpa isian formulir (insiden "formulir menyusul") —
                     jangan biarkan kolom kiri kosong tanpa penjelasan --}}
                <div class="itk-panel" style="margin-bottom:12px">
                    <div class="itk-panel-body">
                        <div class="itk-hint" style="margin:0">
                            {{ $t->sumber === 'insiden'
                                ? 'Insiden — masuk langsung ke Development, formulir diisi menyusul (rencana §3).'
                                : 'Belum ada isi formulir untuk tiket ini — formulir kerja muncul di sini begitu fasenya tiba.' }}
                        </div>
                    </div>
                </div>
            @endif
            @if ($form01)
                <div class="itk-panel" style="margin-bottom:12px">
                    <div class="itk-beban-bar"><b>FRM-IT-01 — REQUIREMENT REQUEST</b>
                        <span class="itk-badge-fase">KELAS A · diisi pemohon</span></div>
                    <div class="itk-panel-body itk-rows">
                        <div class="itk-row"><b>JENIS PERMINTAAN</b><span>{{ $form01['jenis'] ?? '—' }}</span></div>
                        <div class="itk-row"><b>TARGET WAKTU</b><span>{{ $form01['target'] ?? '—' }}</span></div>
                        <div class="itk-row"><b>LATAR / MASALAH SAAT INI</b><span>{{ $form01['latar'] ?? '—' }}</span></div>
                        <div class="itk-row"><b>HASIL YANG DIHARAPKAN</b><span>{{ $form01['kebutuhan'] ?? '—' }}</span></div>
                        <div class="itk-row"><b>PENGGUNA TERDAMPAK</b><span>{{ $form01['terdampak'] ?? '—' }}</span></div>
                        @include('it-tracker._lampiran')
                    </div>
                </div>
            @elseif ($bolehLampiran)
                {{-- tiket tanpa FRM-IT-01 (insiden/inisiatif): Head IT tetap bisa menempelkan bukti --}}
                <div class="itk-panel" style="margin-bottom:12px">
                    <div class="itk-beban-bar"><b>LAMPIRAN</b><span class="itk-badge-fase">FRM-IT-01 · bukti</span></div>
                    <div class="itk-panel-body itk-rows">@include('it-tracker._lampiran')</div>
                </div>
            @endif

            {{-- Konfirmasi Development Record — jenjang M/L di DEV (Kelas B).
                 Fase 2 menampilkan branch/commit hasil it:sync-git di sini;
                 Fase 1 cukup paraf "Benar, ini yang kukerjakan". --}}
            {{-- Jejak git tetap terlihat SESUDAH Development — saat Go Live
                 justru itu yang dibutuhkan (script mana yang harus dijalankan).
                 Tombol konfirmasinya sendiri hanya muncul di fase DEV. --}}
            @if (in_array($t->jenjang, ['M', 'L'], true)
                 && (\App\Services\ItTracker\PhaseEngine::rankOf($t->fase) >= 3 || $t->fase === 'SELESAI'))
                <div class="itk-panel" style="margin-bottom:12px">
                    <div class="itk-beban-bar"><b>FRM-IT-03 — DEVELOPMENT RECORD</b>
                        <span class="itk-badge-fase">KELAS B · terisi sendiri</span></div>
                    <div class="itk-panel-body">
                        @php $sudahKonfirmasi = (bool) optional($t->forms->firstWhere('kode', 'FRM-IT-03'))->dikonfirmasi_at; @endphp

                        {{-- Kelas B: TERISI SENDIRI dari git. IT tinggal mengonfirmasi —
                             kalau ini jadi kotak kosong untuk diketik ulang, sistemnya mati. --}}
                        <div style="display:flex;align-items:center;gap:8px;background:#E3F4E8;border-radius:10px;padding:8px 12px">
                            <span style="font-size:11.5px;font-weight:700;color:#2F8E55">
                                Terisi sendiri dari git lewat nomor tiket di commit message — IT tinggal mengonfirmasi, bukan mengetik ulang.</span>
                        </div>

                        <div class="itk-git">
                            <div class="itk-git-lbl">DARI GIT — DILACAK LEWAT {{ $t->nomor }} DI COMMIT MESSAGE</div>
                            @forelse ($t->gitRefs->groupBy('branch') as $branch => $refs)
                                <div class="itk-git-branch">{{ $branch ?: '(tanpa branch)' }}</div>
                                @foreach ($refs as $r)
                                    <div class="itk-git-commit">{{ substr($r->commit_sha, 0, 7) }} — {{ $r->subject }}</div>
                                    @foreach ($r->scriptDisebut() as $sq)
                                        <div class="itk-git-script">{{ $sq }}</div>
                                    @endforeach
                                @endforeach
                            @empty
                                <div class="itk-git-kosong">Belum ada commit yang menyebut {{ $t->nomor }}.
                                    Tulis nomornya di baris pertama commit message, lalu segarkan.</div>
                            @endforelse
                        </div>

                        @if ($bolehGerak['ok'])
                            <form method="post" action="{{ route('it-tracker.syncgit', $t->id) }}">
                                @csrf
                                <button type="submit" class="itk-btn itk-btn-putih" style="font-size:11.5px;padding:6px 12px">
                                    Segarkan jejak git</button>
                                <span class="itk-hint" style="margin:0 0 0 6px">Cron menariknya tiap 06:40; tombol ini untuk yang tak mau menunggu.</span>
                            </form>
                        @endif

                        @if ($sudahKonfirmasi)
                            <div style="font-size:11.5px;font-weight:800;color:#2F8E55;background:#E3F4E8;border-radius:8px;padding:5px 10px;align-self:flex-start">
                                ✓ Dikonfirmasi — tanpa paraf pihak lain (FRM-IT-03/04 memang begitu)</div>
                            @if (! empty($form03['note']))
                                <div style="font-size:13px;line-height:1.5">{{ $form03['note'] }}</div>
                            @endif
                        @elseif ($bolehGerak['ok'] && $t->fase === 'DEV')
                            <form method="post" action="{{ route('it-tracker.konfirmasi', $t->id) }}">
                                @csrf
                                <textarea name="catatan" rows="2"
                                    placeholder="opsional: keterangan tambahan — branch & commit sudah diambil sendiri di atas">{{ $form03['note'] ?? '' }}</textarea>
                                <button type="submit" class="itk-btn itk-btn-grafit" style="margin-top:8px">Benar, ini yang kukerjakan — konfirmasi</button>
                            </form>
                        @elseif ($t->fase === 'DEV')
                            <div class="itk-hint" style="margin:0">Menunggu konfirmasi pengerjanya ({{ $t->pic->nama ?? 'IT' }}).</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ═══ FASE 2b — empat formulir kerja (rencana §14.13). Tiap panel muncul
                 saat fasenya tiba dan TETAP terbaca (baca-saja) sesudahnya — jejak audit
                 tak boleh lenyap saat fase berganti. Jenjang S tak menyentuh satu pun. ═══ --}}
            @php $rankKini = \App\Services\ItTracker\PhaseEngine::rankOf($t->fase); $tutup = $t->fase === 'SELESAI'; @endphp
            @if (in_array($t->jenjang, ['M', 'L'], true) && ($rankKini >= 2 || $tutup))
                @include('it-tracker._analisis')
            @endif
            @if ($t->jenjang === 'L' && ($rankKini >= 4 || $tutup))
                @include('it-tracker._uji')
            @endif
            @if (in_array($t->jenjang, ['M', 'L'], true) && ($rankKini >= 3 || $tutup))
                @include('it-tracker._uat')
            @endif
            @if ($t->jenjang === 'L' && ($rankKini >= 7 || $tutup))
                @include('it-tracker._evaluasi')
            @endif

            {{-- FRM-IT-06 Checklist Go Live (Kelas B) — partial supaya detail tetap terbaca.
                 Tetap tampil (baca-saja) SESUDAH tiket ditutup: checklist deploy itu
                 jejak audit yang dicari Konsultan; kalau hilang saat fase berganti,
                 buktinya lenyap justru ketika mulai dibutuhkan. --}}
            @if ($t->fase === 'GOLIVE' || (! empty($v['fr06']['pasca']) || ! empty($v['fr06']['bo'])))
                @include('it-tracker._golive')
            @endif

            {{-- Catatan singkat pengerjaan — jenjang S (pengganti formulir penuh) --}}
            @if ($t->jenjang === 'S' && in_array($t->fase, ['DEV', 'SELESAI'], true))
                <div class="itk-panel" style="margin-bottom:12px">
                    <div class="itk-beban-bar"><b>CATATAN SINGKAT PENGERJAAN (JENJANG S)</b>
                        <span class="itk-badge-fase">KELAS A · diisi pemohon</span></div>
                    <div class="itk-panel-body">
                        @if ($bolehGerak['ok'] && $t->fase === 'DEV')
                            <form method="post" action="{{ route('it-tracker.catatan', $t->id) }}">
                                @csrf
                                <textarea name="catatan" rows="2" required
                                    placeholder="apa yang diubah + commit — ini pengganti formulir penuh">{{ $form03['note'] ?? '' }}</textarea>
                                <button type="submit" class="itk-btn itk-btn-putih" style="margin-top:8px">Simpan catatan</button>
                            </form>
                        @else
                            <div style="font-size:13px;line-height:1.5">{{ $form03['note'] ?? '—' }}</div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="itk-legenda">Fase 1 = intake + papan (halaman ini) · Fase 2 = tujuh formulir per fase +
                gerbang penuh · Fase 3 = sambungan Project Tracker + evaluasi terjadwal.</div>
        </div>

        {{-- Rail kanan --}}
        <div>
            @if ($gate && ($gate['kind'] ?? '') === 'advance' && ! in_array($t->fase, ['DITUNDA', 'DITOLAK', 'SELESAI'], true))
                <div class="itk-side">
                    <div class="itk-side-hd"><i></i><b>LANGKAH BERIKUTNYA</b></div>
                    @if ($t->fase === 'UAT')
                        @if ($bolehJawabUat)
                            {{-- Fase 2b: keputusan penuh (3 pilihan + skenario) ada di panel
                                 FRM-IT-05 sebelah kiri — rail hanya menunjuk ke sana --}}
                            <div class="itk-blokir" style="background:#FFF8EE;color:#B5701A">Pertanyaan untukmu ada di panel <strong>FRM-IT-05</strong> sebelah kiri: coba skenarionya, tandai Sesuai/Tidak, lalu putuskan.</div>
                        @else
                            {{-- UAT didiamkan MENYALA di papan, bukan otomatis diterima --}}
                            <div class="itk-blokir">Gerbang ini milik pemohon ({{ $t->pemohon->nama ?? 'pemohon' }}) —
                                menunggu keputusan UAT-nya {{ $wait['days'] }} hari.</div>
                        @endif
                    @elseif ($bolehGerak['ok'] || ($t->fase === 'EVALUASI' && $bolehEvaluasi))
                        {{-- evaluator (bukan IT) juga boleh menutup di EVALUASI — "menutup tiket adalah parafnya" --}}
                        <form method="post" action="{{ route('it-tracker.maju', $t->id) }}">
                            @csrf
                            <button type="submit" class="itk-btn itk-btn-grafit" style="width:100%"
                                {{ empty($gate['ok']) ? 'disabled' : '' }}>{{ $gate['label'] }}</button>
                        </form>
                        @if (! empty($gate['checks']))
                            {{-- Ronde 2: LAMPU per syarat — menyala sendiri saat terpenuhi,
                                 bukan checkbox untuk diklik; yang mati menyebut kekurangannya --}}
                            <ul class="itk-missing itk-lampu">
                                @foreach ($gate['checks'] as $c)<li class="{{ $c['ok'] ? 'on' : '' }}">{{ $c['t'] }}</li>@endforeach
                            </ul>
                        @elseif (empty($gate['ok']))
                            {{-- tombol mati yang bilang kenapa (pola P3) --}}
                            <ul class="itk-missing">
                                @foreach ($gate['missing'] as $m)<li>{{ $m }}</li>@endforeach
                            </ul>
                            <div class="itk-hint">Lampu menyala sendiri saat field-nya terisi — bukan untuk dicentang.</div>
                        @endif
                    @else
                        {{-- penonton non-IT: jelaskan sedang menunggu siapa, jangan
                             cuma "aksi ini milik IT" (menyesatkan di GOLIVE/EVALUASI
                             yang justru menunggu pihak luar) --}}
                        <div class="itk-blokir">
                            {{ $wait['label'] ? $wait['label'] . ' · ' . $wait['days'] . ' hari.' : $bolehGerak['why'] }}
                            {{ $t->fase === 'GOLIVE' ? 'Checklist Go-Live ada di panel FRM-IT-06 sebelah kiri; ACC Business Owner dibubuhkan Executive/Konsultan di panel yang sama.' : '' }}
                            @if ($t->fase === 'ANALISIS' && $peran === 'pengawas')
                                {{-- BO tak boleh mencari-cari: parafnya ada di baris PARAF paling bawah FRM-IT-02 --}}
                                <strong>Giliranmu:</strong> paraf Business Owner ada di panel <strong>FRM-IT-02</strong> sebelah kiri, baris PARAF paling bawah — tombolnya menyala begitu analisis IT lengkap.
                            @endif
                        </div>
                    @endif
                </div>
            @elseif ($t->fase === 'TRIASE' && $peran === 'head_it')
                <div class="itk-side">
                    <div class="itk-side-hd"><i></i><b>LANGKAH BERIKUTNYA</b></div>
                    <a class="itk-btn itk-btn-grafit" style="width:100%;text-align:center"
                       href="{{ route('it-tracker.index', ['q' => $t->nomor]) }}">Triase lewat papan</a>
                </div>
            @endif

            <div class="itk-side">
                <div class="itk-side-hd"><i></i><b>TENTANG TIKET</b></div>
                <div class="itk-duo">
                    <div class="pemohon"><div class="lab">PRIORITAS PEMOHON</div>
                        <div class="val">{{ $t->prioritas_pemohon ?? '—' }}</div></div>
                    <div class="it"><div class="lab">PRIORITAS IT</div>
                        <div class="val">{{ $t->prioritas_it ?? '—' }}</div></div>
                </div>
                <div class="itk-hint" style="margin-top:8px">Dua kolom terpisah — masukan pemohon ≠ keputusan IT.</div>
                <div class="itk-rows" style="margin-top:11px">
                    <div class="itk-row" style="grid-template-columns:1fr 1fr"><b>JENIS</b><span style="text-align:right;font-weight:700">{{ $t->jenis_permintaan ?? '—' }}</span></div>
                    <div class="itk-row" style="grid-template-columns:1fr 1fr"><b>BOBOT</b><span style="text-align:right;font-weight:700">{{ $t->bobot ?? '—' }}</span></div>
                    <div class="itk-row" style="grid-template-columns:1fr 1fr"><b>DIBUAT</b><span style="text-align:right;font-weight:700">{{ $t->created_at?->translatedFormat('j M Y') }}</span></div>
                </div>
                @if ($form01)
                    <a class="itk-btn itk-btn-putih" style="display:block;text-align:center;margin-top:11px"
                       href="{{ route('it-tracker.ekspor', $t->id) }}">Ekspor FRM-IT-01</a>
                @endif
                @if ($peran === 'head_it' && $picOpsi->count() > 1 && ! in_array($t->fase, ['DITOLAK', 'SELESAI', 'TRIASE'], true))
                    <form method="post" action="{{ route('it-tracker.pic', $t->id) }}"
                          style="display:flex;gap:8px;align-items:center;margin-top:11px;border-top:1px solid #F1E5D5;padding-top:11px">
                        @csrf
                        <label class="itk-lbl" style="margin:0;flex:none">PIC</label>
                        <select name="pic_user_id" style="flex:1;border:1px solid #E7D3BE;border-radius:9px;padding:6px 10px;font-family:inherit;font-size:12px;background:#fff">
                            @foreach ($picOpsi as $p)
                                <option value="{{ $p->id }}" {{ $t->pic_user_id === $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="itk-btn itk-btn-putih" style="padding:6px 11px;font-size:11.5px">Simpan</button>
                    </form>
                @endif
                @if ($peran === 'head_it' && ! in_array($t->fase, ['DITOLAK', 'SELESAI'], true))
                    <details style="margin-top:11px;border-top:1px solid #F1E5D5;padding-top:11px">
                        <summary style="font-size:12px;font-weight:700;color:#5A3A22;cursor:pointer">Tunda / Tolak…</summary>
                        <form method="post" action="{{ route('it-tracker.parkir', $t->id) }}" style="margin-top:9px">
                            @csrf
                            <input class="itk-in" type="text" name="alasan" required maxlength="500"
                                   placeholder="alasannya wajib — tampil di papan">
                            <div style="display:flex;gap:8px;margin-top:9px">
                                <button type="submit" name="ke" value="DITUNDA" class="itk-btn itk-btn-oranye">Tunda</button>
                                <button type="submit" name="ke" value="DITOLAK" class="itk-btn itk-btn-putih">Tolak</button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>

            {{-- Riwayat fase — jejak audit dari it_ticket_fase --}}
            <div class="itk-side">
                <div class="itk-side-hd"><i></i><b>RIWAYAT FASE</b></div>
                <div class="itk-rows">
                    @foreach ($t->riwayatFase->sortByDesc('masuk_at') as $r)
                        <div class="itk-row" style="grid-template-columns:1fr 1fr">
                            <b>{{ $labelFase[$r->fase] ?? \App\Services\ItTracker\PhaseEngine::phShort($r->fase) }}</b>
                            <span style="text-align:right;font-size:11.5px">{{ $r->masuk_at->translatedFormat('j M H:i') }}
                                {{ $r->keluar_at ? '→ ' . $r->keluar_at->translatedFormat('j M H:i') : '· berjalan' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="itk-fase3">
                <span class="tag">FASE 3</span> <b style="font-size:11px;font-weight:800;color:#7A5B43">SAMBUNGAN PROJECT TRACKER</b>
                <p>Tiket yang lolos gerbang Analisis melahirkan project di Project Tracker — fase tetap di sini,
                    eksekusi harian di sana.</p>
            </div>
        </div>
    </div>
</div>
@endsection
