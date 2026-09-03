{{--
    FRM-IT-06 — Go Live Checklist (Kelas B, Fase 2a).
    Acuan: design_handoff_it_tracker/screenshots/03-checklist-golive.png

    "Formulir paling matang di seluruh templat — pertahankan apa adanya"
    (rencana §7). Dua baris pra-deploy MENYALA SENDIRI dan sengaja TIDAK bisa
    diklik: UAT dari keputusan pemohon (FRM-IT-05), file/script dari jejak git
    (it_git_ref). Sisanya dijawab petugas deploy — Ya / N-A, bukan mengetik
    ulang catatan yang sudah ada.

    Butuh: $t, $v, $peran.
--}}
@php
    $PRA   = \App\Services\ItTracker\PhaseEngine::PRA;
    $PASCA = \App\Services\ItTracker\PhaseEngine::PASCA;
    $fr06  = $v['fr06'] ?? [];
    $pra   = $fr06['pra'] ?? [];
    $pasca = $fr06['pasca'] ?? [];

    $nPra   = collect($PRA)->filter(fn ($p) => ! empty($pra[$p['k']]))->count();
    $nPasca = collect($PASCA)->filter(fn ($p) => ($pasca[$p['k']] ?? null) === 'pass')->count();

    // Yang mengisi checklist = yang melakukan deployment-nya (gerbang aslinya
    // tetap di controller; ini hanya menyembunyikan tombol yang akan ditolak).
    // Sesudah tiket lepas dari Go Live, panel ini jadi BACA-SAJA — layanan pun
    // menolak menulis di luar fase itu.
    $diGoLive = $t->fase === 'GOLIVE';
    $bolehIsi = $diGoLive && ($peran === 'head_it'
        || ($peran === 'it_dev' && $t->pic_user_id === (string) session('id')));
@endphp

<div class="itk-panel" style="margin-bottom:12px">
    <div class="itk-beban-bar"><b>FRM-IT-06 — GO LIVE CHECKLIST</b>
        <span class="itk-badge-fase">FASE 2a</span></div>

    <div class="itk-panel-body">
        <div style="display:flex;align-items:center;gap:8px;background:#E3F4E8;border-radius:10px;padding:8px 12px">
            <span style="font-size:11.5px;font-weight:700;color:#2F8E55">
                UAT &amp; file/script menyala sendiri; sisanya dijawab petugas deploy — bukan diketik ulang.</span>
        </div>

        {{-- ── Pra-deploy ── --}}
        <div class="itk-cek">
            <div class="itk-cek-hd"><b>CHECKLIST PRA-DEPLOY</b>
                <span class="sum num">{{ $nPra }}/{{ count($PRA) }} siap</span></div>
            <div class="itk-cek-bar"><div style="width:{{ round($nPra / count($PRA) * 100) }}%"></div></div>

            @foreach ($PRA as $p)
                @php
                    $val  = $pra[$p['k']] ?? null;
                    $auto = ! empty($p['auto']);
                @endphp
                <div class="itk-cek-row">
                    <span class="itk-cek-dot {{ $val ? 'on' : '' }}">{{ $val === 'na' ? 'NA' : ($val ? '✓' : '') }}</span>
                    <span class="itk-cek-t">{{ $p['t'] }}</span>

                    @if ($auto)
                        <span class="itk-auto" style="margin:0">otomatis</span>
                        <span class="itk-cek-info">
                            @if ($p['k'] === 'uat')
                                {{ ($v['fr05']['keputusan'] ?? null) === 'setuju'
                                    ? 'dari keputusan UAT pemohon'
                                    : 'menunggu keputusan UAT' }}
                            @else
                                {{ $t->gitRefs->count() }} commit terlacak
                            @endif
                        </span>
                    @endif

                    @if ($val)
                        <span class="itk-cek-val">{{ $val === 'na' ? 'N-A' : 'Ya' }}</span>
                    @endif

                    @if ($bolehIsi && ! $auto)
                        <form method="post" action="{{ route('it-tracker.pra', $t->id) }}" class="itk-cek-aksi">
                            @csrf
                            <input type="hidden" name="kunci" value="{{ $p['k'] }}">
                            <button type="submit" name="nilai" value="ya" class="itk-cek-btn ya">Ya</button>
                            <button type="submit" name="nilai" value="na" class="itk-cek-btn">N-A</button>
                        </form>
                    @elseif ($bolehIsi && $p['k'] === 'script' && ! $val && $t->gitRefs->isEmpty())
                        {{-- Tanpa jalan keluar ini, tiket tanpa commit ber-nomor
                             MACET PERMANEN di Go Live: barisnya tak bisa menyala
                             sendiri dan tak boleh diisi tangan. "Ya" tetap haram. --}}
                        <form method="post" action="{{ route('it-tracker.pra', $t->id) }}" class="itk-cek-aksi">
                            @csrf
                            <input type="hidden" name="kunci" value="script">
                            <button type="submit" name="nilai" value="na" class="itk-cek-btn"
                                title="Tak ada commit ber-nomor tiket ini — mis. perubahan konfigurasi murni">N-A · tak ada script</button>
                        </form>
                    @endif
                </div>
            @endforeach

            <div class="itk-cek-kaki">Lampu menyala sendiri saat syaratnya terpenuhi — bukan checkbox untuk diklik.</div>
        </div>

        {{-- ── Pasca Go-Live ── --}}
        <div class="itk-cek">
            <div class="itk-cek-hd"><b>PEMERIKSAAN PASCA GO-LIVE</b>
                <span class="sum num">{{ $nPasca }}/{{ count($PASCA) }} pass</span></div>
            <div class="itk-cek-bar"><div style="width:{{ round($nPasca / count($PASCA) * 100) }}%"></div></div>

            @foreach ($PASCA as $p)
                @php $ok = ($pasca[$p['k']] ?? null) === 'pass'; @endphp
                <div class="itk-cek-row">
                    <span class="itk-cek-dot {{ $ok ? 'on' : '' }}">{{ $ok ? '✓' : '' }}</span>
                    <span class="itk-cek-t">{{ $p['t'] }}</span>
                    @if ($ok)
                        <span class="itk-cek-val">Pass</span>
                    @elseif ($bolehIsi)
                        <form method="post" action="{{ route('it-tracker.pasca', $t->id) }}" class="itk-cek-aksi">
                            @csrf
                            <input type="hidden" name="kunci" value="{{ $p['k'] }}">
                            <button type="submit" class="itk-cek-btn ya">Pass</button>
                        </form>
                    @endif
                </div>
            @endforeach

            <div class="itk-cek-kaki">
                ⚠️ <strong>laravel.log nol galat TIDAK berarti sehat</strong> — prod pernah mati 6 menit dengan log
                bersih karena gagalnya terjadi sebelum Laravel hidup. Baris pertama itulah buktinya: curl beranda
                harus 200 <em>dan</em> URL akhirnya benar.
            </div>
        </div>

        {{-- ── Catatan deployment (§C) ── --}}
        @if ($bolehIsi)
            <form method="post" action="{{ route('it-tracker.deploy', $t->id) }}">
                @csrf
                <label class="itk-lbl">CATATAN DEPLOYMENT — WAKTU · AKTIVITAS · HASIL</label>
                <textarea name="catatan" rows="2"
                    placeholder="mis. 02:11 backup 48 MB · 02:20 deploy.sh main · 02:24 curl beranda 200">{{ $fr06['deploy'] ?? '' }}</textarea>
                <button type="submit" class="itk-btn itk-btn-putih" style="margin-top:8px;font-size:11.5px;padding:6px 12px">Simpan catatan</button>
            </form>
        @elseif (! empty($fr06['deploy']))
            <div>
                <label class="itk-lbl">CATATAN DEPLOYMENT</label>
                <div style="font-size:13px;line-height:1.5">{{ $fr06['deploy'] }}</div>
            </div>
        @endif

        {{-- ── ACC Business Owner: gerbang manusia, sengaja BUKAN IT ── --}}
        @if (! empty($fr06['bo']))
            <div style="font-size:11.5px;font-weight:800;color:#2F8E55;background:#E3F4E8;border-radius:8px;padding:5px 10px;align-self:flex-start">
                ✓ Disetujui Business Owner</div>
        @elseif ($diGoLive && $peran === 'pengawas')
            <form method="post" action="{{ route('it-tracker.bo', $t->id) }}">
                @csrf
                <button type="submit" class="itk-btn itk-btn-hijau">Setujui sebagai Business Owner</button>
            </form>
        @elseif ($diGoLive)
            <div class="itk-hint" style="margin:0">
                Menunggu ACC Business Owner (Executive / Konsultan) — sengaja tak bisa dibubuhkan IT sendiri.</div>
        @endif

        @unless ($diGoLive)
            <div class="itk-hint" style="margin:0">
                Catatan Go-Live ini <strong>baca-saja</strong> — tiket sudah lepas dari fase Go Live.</div>
        @endunless
    </div>
</div>
