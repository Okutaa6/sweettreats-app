@extends('layouts.app')

{{-- Pusat Akun → User Detail — hi-fi PRESISI dari prototype
     "Pusat Akun User Detail.dc.html": identitas TERPUSAT (kolom), breadcrumb
     kicker, 4 aksi (AKSI AKUN), flag-notice, timeline ikon+garis. --}}

@section('css')
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .ud { font-family: 'Plus Jakarta Sans', sans-serif; color: #3B1500; padding: 18px 26px 30px; }
    .ud-num { font-variant-numeric: tabular-nums; }
    .ud-crumb { display: flex; align-items: center; gap: 13px; margin-bottom: 16px; }
    .ud-backbtn { width: 40px; height: 40px; border-radius: 12px; background: #fff; border: 1px solid #EFE0CE; color: #5A3A22; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 17px; }
    .ud-kick { font-size: 11.5px; color: #9A7256; font-weight: 600; }
    .ud-title { font-size: 20px; font-weight: 800; line-height: 1.2; }
    .ud-grid { display: grid; grid-template-columns: 360px 1fr; gap: 18px; align-items: start; }
    @media (max-width: 980px) { .ud-grid { grid-template-columns: 1fr; } }
    .ud-col { display: flex; flex-direction: column; gap: 16px; }
    .ud-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    /* identitas — KOLOM TERPUSAT (prototype) */
    .ud-idhead { background: linear-gradient(120deg,#2C2C29,#3A3A36); padding: 22px; display: flex; flex-direction: column; align-items: center; text-align: center; }
    .ud-av { width: 76px; height: 76px; border-radius: 50%; background: #B4562E; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 30px; box-shadow: 0 0 0 4px rgba(255,255,255,.12); }
    .ud-idhead .nm { font-size: 18px; font-weight: 800; color: #fff; margin-top: 12px; line-height: 1.25; }
    .ud-idhead .em { font-size: 12.5px; color: #C6BCAE; margin-top: 3px; }
    .ud-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 800; border-radius: 20px; padding: 4px 12px; margin-top: 11px; }
    .ud-badge .d { width: 6px; height: 6px; border-radius: 50%; }
    .ud-badge.on { background: #E3F4E8; color: #2F8E55; } .ud-badge.on .d { background: #2F8E55; }
    .ud-badge.off { background: #EFE7E1; color: #8A6A52; } .ud-badge.off .d { background: #8A6A52; }
    .ud-rows { padding: 8px 4px; }
    .ud-row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 16px; font-size: 13px; }
    .ud-row + .ud-row { border-top: 1px solid #F4EADD; }
    .ud-row span { color: #A9866A; font-weight: 600; font-size: 12px; } .ud-row b { color: #3B1500; font-weight: 700; text-align: right; }
    /* flag notice */
    .ud-flag { background: #FDF0EA; border: 1px solid #F0D3C8; border-left: 4px solid #C0392B; border-radius: 14px; padding: 14px 16px; }
    .ud-flag b { font-size: 12.5px; font-weight: 800; color: #C0392B; display: flex; align-items: center; gap: 8px; }
    .ud-flag p { font-size: 12px; color: #8A4A3E; margin: 6px 0 0; line-height: 1.5; }
    .ud-flag a { display: inline-flex; margin-top: 9px; font-size: 12px; font-weight: 700; color: #C0392B; text-decoration: none; }
    /* aksi akun */
    .ud-sec { padding: 16px 18px; }
    .ud-h6 { font-size: 11px; color: #7A5B43; text-transform: uppercase; letter-spacing: .6px; font-weight: 800; margin: 0 0 12px; }
    .ud-actbtn { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; border: 1px solid #E7D3BE; background: #fff; border-radius: 12px; padding: 12px 14px; margin-bottom: 8px; cursor: pointer; font-family: inherit; }
    .ud-actbtn:hover { background: #FBF4EC; }
    .ud-actbtn .ic { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; flex: none; background: #F1E9DF; color: #5A3A22; }
    .ud-actbtn.danger .ic { background: #FBE3DE; color: #C0392B; }
    .ud-actbtn .tx { flex: 1; } .ud-actbtn .tx b { color: #3B1500; font-size: 13.5px; } .ud-actbtn .tx small { color: #9A7256; display: block; font-size: 11.5px; }
    .ud-actbtn.danger .tx b { color: #C0392B; }
    .ud-actbtn .chev { color: #c9b6a4; }
    /* perangkat */
    .ud-cardhead { display: flex; align-items: center; gap: 9px; padding: 16px 18px 4px; }
    .ud-cardhead b { font-size: 15px; font-weight: 800; }
    .ud-cardhead .cnt { font-size: 11px; font-weight: 800; background: #F4E7D6; color: #9A6614; border-radius: 20px; padding: 2px 9px; }
    .ud-cardhead .note { margin-left: auto; font-size: 11.5px; color: #9A7256; }
    .ud-dev { display: flex; align-items: center; gap: 12px; border: 1px solid #EFE0CE; border-radius: 13px; padding: 12px 14px; margin: 0 18px 10px; }
    .ud-dev .di { width: 38px; height: 38px; border-radius: 10px; background: #F1E9DF; color: #5A3A22; display: flex; align-items: center; justify-content: center; flex: none; }
    .ud-dev .lbl { flex: 1; min-width: 0; } .ud-dev .lbl b { color: #3B1500; font-size: 13.5px; } .ud-dev .lbl span { color: #9A7256; font-size: 12px; display: block; }
    .ud-tag { font-size: 10.5px; font-weight: 800; border-radius: 20px; padding: 2px 9px; flex: none; }
    .ud-tag.live { background: #E3F4E8; color: #2F8E55; } .ud-tag.dead { background: #EFE7E1; color: #8A6A52; }
    .ud-cabut { border: 1px solid #E7D3BE; background: #fff; color: #5A3A22; border-radius: 9px; padding: 6px 14px; font-size: 12.5px; font-weight: 700; cursor: pointer; flex: none; }
    /* timeline */
    .ud-tl { display: flex; gap: 13px; }
    .ud-tl .rail { display: flex; flex-direction: column; align-items: center; flex: none; }
    .ud-tl .ti { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: #F1E9DF; color: #5A3A22; }
    .ud-tl .ti.ok { background: #E3F4E8; color: #2F8E55; } .ud-tl .ti.out { background: #EFE7E1; color: #8A6A52; } .ud-tl .ti.sec { background: #FBEFD9; color: #9A6614; }
    .ud-tl .line { width: 2px; flex: 1; background: #F4EADD; margin: 4px 0; }
    .ud-tl .tc { padding-bottom: 16px; min-width: 0; }
    .ud-tl .tc b { font-size: 13px; font-weight: 700; color: #3B1500; line-height: 1.35; display: block; }
    .ud-tl .tc .sub { font-size: 11.5px; color: #9A7256; margin-top: 2px; }
    .ud-tl .tc .tm { font-size: 11px; color: #B79A82; margin-top: 3px; }
    .ud-footnote { font-size: 11px; color: #B79A82; text-align: center; padding: 8px 0 14px; border-top: 1px solid #F4EADD; margin: 2px 18px 0; }
    .ud-empty { color: #b7a595; font-size: 12.5px; font-style: italic; padding: 4px 18px 16px; }
    /* modal reset */
    .ud-scrim { position: fixed; inset: 0; background: rgba(30,12,0,.4); z-index: 1090; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .ud-modal { width: 460px; max-width: 100%; background: #fff; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 60px rgba(30,12,0,.4); }
    .ud-modal-h { background: linear-gradient(120deg,#2F8E55,#237045); color: #fff; padding: 18px 20px; display: flex; align-items: center; gap: 12px; }
    .ud-modal-h .mi { width: 40px; height: 40px; border-radius: 11px; background: rgba(255,255,255,.18); display: flex; align-items: center; justify-content: center; }
    .ud-modal-h .k { font-size: 10.5px; font-weight: 800; letter-spacing: .5px; opacity: .9; }
    .ud-modal-h h3 { margin: 2px 0 0; font-size: 18px; font-weight: 800; }
    .ud-modal-b { padding: 18px 20px 20px; }
    .ud-modal-b p { color: #5A3A22; font-size: 13.5px; margin: 0 0 14px; }
    .ud-pwbox { display: flex; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid #E7D3BE; background: #FCF7F0; border-radius: 12px; padding: 14px 16px; }
    .ud-pwbox .pw { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: 28px; font-weight: 800; letter-spacing: 4px; color: #2F8E55; }
    .ud-pwbox button { background: #2F8E55; color: #fff; border: 0; border-radius: 9px; padding: 8px 16px; font-weight: 800; font-size: 13px; cursor: pointer; }
    .ud-warnbox { background: #FBE3DE; color: #C0392B; border-radius: 10px; padding: 10px 12px; font-size: 12.5px; margin: 14px 0; }
    .ud-done { width: 100%; background: linear-gradient(135deg,#3A3A36,#242422); color: #fff; border: 0; border-radius: 12px; padding: 13px; font-weight: 800; font-size: 14px; cursor: pointer; }
    .ud-toastok { background: #E3F4E8; color: #237045; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
</style>
@endsection

@section('content')
@php
    $ic = fn ($p, $sz=18) => '<svg width="'.$sz.'" height="'.$sz.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'.$p.'</svg>';
    $avPal = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    $avColor = $avPal[hexdec(substr(md5((string) $user->id), 0, 6)) % 8];
    $act = $user->last_seen_at ?? $user->last_login;
    $aktif = (int) $user->status === 1;
    $flagText = [
        'landing'   => 'Halaman pembuka role user ini tidak di-grant — dia kena 403 begitu login.',
        'bertuan'   => 'Akun aktif tapi tidak pernah/lama tidak terlihat — pastikan masih dipakai.',
        'perangkat' => 'Ada perangkat "diingat" yang masih hidup padahal akun nonaktif.',
    ];
    // ikon + kelas timeline per action
    $tlMeta = function ($a) {
        return match (true) {
            str_contains($a, 'login')  => ['ok',  '<path d="M10 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h5M15 8l4 4-4 4M19 12H8"/>'],
            str_contains($a, 'logout') => ['out', '<path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5M9 16l-4-4 4-4M5 12h11"/>'],
            $a === 'create'            => ['sec', '<circle cx="12" cy="12" r="8.5"/><path d="M12 8.5v7M8.5 12h7"/>'],
            default                    => ['sec', '<path d="M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z"/>'],
        };
    };
@endphp
<div class="ud">
    <div class="ud-crumb">
        <a href="{{ route('setusermanagement') }}" class="ud-backbtn">‹</a>
        <div>
            <div class="ud-kick">Pusat Akun · User / Detail</div>
            <div class="ud-title">{{ $user->nama }}</div>
        </div>
    </div>

    @if(session('success') || session('error'))
        <div class="ud-toastok" @if(session('error')) style="background:#fdecea;color:#b71c1c" @endif>
            {{ session('success') ?: session('error') }}
        </div>
    @endif

    <div class="ud-grid">
        {{-- KIRI --}}
        <div class="ud-col">
            <div class="ud-card">
                <div class="ud-idhead">
                    <div class="ud-av" style="background:{{ $avColor }}">{{ mb_strtoupper(mb_substr(trim($user->nama), 0, 1)) }}</div>
                    <div class="nm">{{ $user->nama }}</div>
                    <div class="em ud-num">{{ $user->email }}</div>
                    <span class="ud-badge {{ $aktif ? 'on' : 'off' }}"><span class="d"></span>{{ $aktif ? 'Aktif' : 'Nonaktif' }}</span>
                </div>
                <div class="ud-rows">
                    <div class="ud-row"><span>Role</span><b>{{ $user->group->nama ?? '—' }}</b></div>
                    <div class="ud-row"><span>Cabang</span><b>{{ optional($user->branch)->name ?? '—' }}</b></div>
                    <div class="ud-row"><span>Wajib ganti password</span><b>{{ $user->must_change_password ? 'Ya' : 'Tidak' }}</b></div>
                    <div class="ud-row"><span>Login terakhir</span><b class="ud-num">{{ $user->last_login ? \Carbon\Carbon::parse($user->last_login)->format('d M Y · H.i') : '—' }}</b></div>
                    <div class="ud-row"><span>Terlihat terakhir</span><b class="ud-num">{{ $act ? \Carbon\Carbon::parse($act)->format('d M Y · H.i') : '—' }}</b></div>
                </div>
            </div>

            @if($flagJenis)
                <div class="ud-flag">
                    <b>{!! $ic('<path d="M12 4L2.5 20h19L12 4z"/><path d="M12 10.5v4M12 17.2v.1"/>', 15) !!} Ditandai detektor Kesehatan</b>
                    <p>{{ $flagText[$flagJenis] ?? 'User ini muncul di salah satu detektor.' }}</p>
                    <a href="{{ route('setkesehatan') }}">Lihat di Kesehatan →</a>
                </div>
            @endif

            <div class="ud-card">
                <div class="ud-sec">
                    <div class="ud-h6">Aksi Akun</div>

                    <form method="POST" action="{{ route('setusermanagement.reset.process', $user->id) }}"
                          onsubmit="return confirm('Reset password {{ $user->nama }}? Password lama langsung tidak berlaku.')">
                        @csrf @method('PUT')
                        <button type="submit" class="ud-actbtn">
                            <span class="ic">{!! $ic('<circle cx="8.5" cy="8.5" r="4.5"/><path d="M11.5 11.5L20 20M16 16l2.5-2.5M18.5 18.5l2-2"/>') !!}</span>
                            <span class="tx"><b>Reset Password</b><small>Buat kata sandi sementara sekali pakai</small></span>
                            <span class="chev">›</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('setusermanagement.toggle-status', $user->id) }}"
                          onsubmit="return confirm('{{ $aktif ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $user->nama }}?{{ $aktif ? ' Semua perangkat diingat ikut dicabut.' : '' }}')">
                        @csrf
                        <button type="submit" class="ud-actbtn">
                            <span class="ic">{!! $ic($aktif ? '<circle cx="12" cy="12" r="8.5"/><path d="M6 6l12 12"/>' : '<circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>') !!}</span>
                            <span class="tx"><b>{{ $aktif ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</b><small>{{ $aktif ? 'Cegah user login sementara' : 'Izinkan user login kembali' }}</small></span>
                            <span class="chev">›</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('setusermanagement.force-logout', $user->id) }}"
                          onsubmit="return confirm('Paksa logout semua sesi {{ $user->nama }}?')">
                        @csrf
                        <button type="submit" class="ud-actbtn">
                            <span class="ic">{!! $ic('<path d="M14 4h5a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-5M9 16l-4-4 4-4M5 12h11"/>') !!}</span>
                            <span class="tx"><b>Paksa Logout</b><small>Akhiri semua sesi login aktif (≤1 menit)</small></span>
                            <span class="chev">›</span>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('setusermanagement.revoke-all', $user->id) }}"
                          onsubmit="return confirm('Cabut SEMUA perangkat diingat {{ $user->nama }}?')" style="margin-bottom:0">
                        @csrf
                        <button type="submit" class="ud-actbtn danger" style="margin-bottom:0">
                            <span class="ic">{!! $ic('<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M4 4l16 16"/>') !!}</span>
                            <span class="tx"><b>Cabut semua perangkat</b><small>Hapus semua perangkat yang diingat</small></span>
                            <span class="chev">›</span>
                        </button>
                    </form>

                    <a href="{{ route('setusermanagement.edit', $user->id) }}" style="display:inline-block;margin-top:12px;font-size:12.5px;font-weight:700;color:#7A5B43;text-decoration:none">✎ Edit identitas (nama, email, role, cabang) →</a>
                </div>
            </div>
        </div>

        {{-- KANAN --}}
        <div class="ud-col">
            <div class="ud-card">
                <div class="ud-cardhead">
                    {!! $ic('<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2"/>', 17) !!}
                    <b>Perangkat diingat</b>
                    @php $nLive = $devices->whereNull('revoked_at')->count(); @endphp
                    @if($nLive)<span class="cnt ud-num">{{ $nLive }}</span>@endif
                </div>
                <div style="height:10px"></div>
                @forelse($devices as $d)
                    <div class="ud-dev" @if($d->revoked_at) style="opacity:.55" @endif>
                        <span class="di">{!! $ic(str_contains(strtolower($d->label ?? ''), 'windows') || str_contains(strtolower($d->label ?? ''), 'mac')
                            ? '<rect x="3" y="4.5" width="18" height="12" rx="2"/><path d="M8 20h8M12 16.5V20"/>'
                            : '<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2"/>') !!}</span>
                        <div class="lbl">
                            <b>{{ $d->label ?: 'Perangkat' }}</b>
                            <span>{{ $d->last_used_at ? 'terakhir ' . \Carbon\Carbon::parse($d->last_used_at)->format('d M Y · H.i') : '—' }}</span>
                        </div>
                        @if($d->revoked_at)
                            <span class="ud-tag dead">Dicabut</span>
                        @else
                            <span class="ud-tag live">Aktif</span>
                            <form method="POST" action="{{ route('setusermanagement.revoke-device', [$user->id, $d->id]) }}" onsubmit="return confirm('Cabut perangkat ini?')">
                                @csrf<button type="submit" class="ud-cabut">Cabut</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="ud-empty">Tidak ada perangkat yang diingat.</div>
                @endforelse
                <div style="height:8px"></div>
            </div>

            <div class="ud-card">
                <div class="ud-cardhead">
                    {!! $ic('<circle cx="12" cy="12" r="8.5"/><path d="M12 8v4.2l2.6 1.6"/>', 17) !!}
                    <b>Riwayat</b>
                    <span class="note">Login/logout &amp; perubahan akun</span>
                </div>
                <div class="ud-sec" style="padding-top:10px">
                    @forelse($trail as $i => $t)
                        @php [$cls, $path] = $tlMeta($t->action); @endphp
                        <div class="ud-tl">
                            <div class="rail">
                                <span class="ti {{ $cls }}">{!! $ic($path, 15) !!}</span>
                                @if($i < count($trail) - 1)<span class="line"></span>@endif
                            </div>
                            <div class="tc">
                                <b>{{ $t->description ?: ucfirst($t->action) }}</b>
                                @if($t->user_name && $t->user_id != $user->id)<div class="sub">oleh {{ $t->user_name }}</div>@endif
                                @php
                                    // Fase D: nilai lama → baru. Samarkan LAGI saat render —
                                    // baris lama (pra-masking) bisa masih menyimpan hash.
                                    $tlDec = function ($v) {
                                        $a = is_string($v) ? json_decode($v, true) : null;
                                        if (! is_array($a)) return [];
                                        foreach (['password', 'token_hash', 'remember_token'] as $k) {
                                            if (array_key_exists($k, $a)) $a[$k] = '[disamarkan]';
                                        }
                                        return $a;
                                    };
                                    $tlOld = $tlDec($t->old_values ?? null);
                                    $tlNew = $tlDec($t->new_values ?? null);
                                    $tlFmt = fn ($x) => \Illuminate\Support\Str::limit(is_scalar($x) || $x === null ? (string) $x : json_encode($x), 42);
                                @endphp
                                @if($tlNew)
                                    <div class="sub ud-num" style="display:flex;flex-wrap:wrap;gap:4px 10px;margin-top:3px">
                                        @foreach($tlNew as $k => $v)
                                            <span style="font-size:11px;background:#F6EFE6;border:1px solid #EADFCE;border-radius:6px;padding:1px 7px;color:#7A5B43">{{ $k }}: @if(array_key_exists($k, $tlOld)){{ $tlFmt($tlOld[$k]) }} → @endif{{ $tlFmt($v) }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="tm ud-num">{{ \Carbon\Carbon::parse($t->created_at)->format('d M Y · H.i') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="ud-empty" style="padding:4px 0 10px">Belum ada jejak aktivitas.</div>
                    @endforelse
                </div>
                <div class="ud-footnote">Perubahan role &amp; akses ikut tercatat otomatis. Log disimpan 6 bulan.</div>
            </div>
        </div>
    </div>
</div>

{{-- modal kata sandi sementara (sekali tampil) --}}
@if(session('reset_temp_pw'))
<div class="ud-scrim" id="ud-scrim">
    <div class="ud-modal">
        <div class="ud-modal-h">
            <span class="mi">{!! $ic('<circle cx="8.5" cy="8.5" r="4.5"/><path d="M11.5 11.5L20 20M16 16l2.5-2.5"/>', 20) !!}</span>
            <div><div class="k">RESET BERHASIL</div><h3>Kata sandi sementara</h3></div>
        </div>
        <div class="ud-modal-b">
            <p>Berikan kata sandi ini ke <b>{{ session('reset_user') }}</b>. Wajib diganti saat login berikutnya.</p>
            <div class="ud-pwbox">
                <span class="pw" id="ud-pw">{{ session('reset_temp_pw') }}</span>
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('ud-pw').textContent);this.textContent='Tersalin'">Salin</button>
            </div>
            <div class="ud-warnbox">Kata sandi ini <b>tidak akan ditampilkan lagi</b> setelah dialog ditutup. Salin sekarang bila belum.</div>
            <button type="button" class="ud-done" onclick="document.getElementById('ud-scrim').remove()">Selesai — saya sudah salin</button>
        </div>
    </div>
</div>
@endif
@endsection
