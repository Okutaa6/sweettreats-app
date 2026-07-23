@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/pasien-redesign.css') }}?v={{ @filemtime(public_path('assets/css/pasien-redesign.css')) ?: '1' }}">
@endsection

@section('content')
<div class="container-fluid">
    <div class="page-title-box"><h4 class="page-title">Detail Pasien</h4></div>

    @include('layouts.notif')

    @php
        // ── Helper tampilan (derivasi sudah di controller; ini formatting saja) ──
        $waNum = function ($phone) {
            $p = preg_replace('/[^0-9]/', '', (string) $phone);
            if ($p && str_starts_with($p, '0')) $p = '62' . substr($p, 1);
            return $p;
        };
        $wa = $waNum($customer->phone);
        $initial = mb_strtoupper(mb_substr(trim($customer->name ?: '?'), 0, 1));

        $sexLabel = ['L' => 'Laki-laki', 'P' => 'Perempuan'][$customer->jenis_kelamin] ?? '—';
        $usia = ($customer->tanggal_lahir && $customer->tanggal_lahir != '0000-00-00')
            ? \Carbon\Carbon::parse($customer->tanggal_lahir)->age : null;

        $isBaru = $customer->pasien_baru_lama === 'baru';
        $tierLabels = ['vip' => 'VIP', 'bernilai' => 'Bernilai', 'reguler' => 'Reguler'];
        $tier = $detail['tier'];

        $compPct = $detail['compPct'];
        $missing = $detail['missingFields'];
        $compColor = count($missing) === 0 ? '#2F8E55' : (count($missing) <= 1 ? '#C9962F' : '#B5701A');

        $dokterShort = $detail['dokterUtama']
            ? preg_replace('/^drg\.?\s*/i', '', $detail['dokterUtama']) : '—';
        $terdaftar = $detail['terdaftar'] ? \Carbon\Carbon::parse($detail['terdaftar'])->format('d M Y') : '—';
        $lastVisitLbl = $detail['lastVisit'] ? \Carbon\Carbon::parse($detail['lastVisit'])->format('d M Y') : '—';

        // Kalau ada error validasi (dari update()), paksa tab Data Pasien aktif
        // supaya field yg invalid (mis. Nama/WA) kelihatan, bukan ke-hide di tab lain.
        if ($errors->any()) { $activeTab = 'data'; }
    @endphp

    <div class="pxd">

        {{-- ════════ BACK + BREADCRUMB ════════ --}}
        @php
            // Default kembali ke daftar pasien. Jika dibuka dari halaman lain (mis.
            // drawer reservasi) yang menitipkan URL asal via ?back=, kembalikan ke
            // sana — hanya URL internal (satu host) yang dihormati (cegah open-redirect).
            $backUrl   = route('rawcustomer');
            $backTitle = 'Kembali ke daftar pasien';
            $reqBack   = request('back');
            if (is_string($reqBack) && \Illuminate\Support\Str::startsWith($reqBack, url('/'))) {
                $backUrl   = $reqBack;
                $backTitle = 'Kembali';
            }
        @endphp
        <div class="pxd-back">
            <a href="{{ $backUrl }}" class="pxd-back__btn" title="{{ $backTitle }}">
                <i class="fa fa-chevron-left"></i>
            </a>
            <span class="pxd-back__crumb">Pasien{!! $customer->branch?->name ? ' · <strong>' . e($customer->branch->name) . '</strong>' : '' !!}</span>
        </div>

        {{-- ════════ IDENTITY HEADER ════════ --}}
        <div class="pxd-card pxd-head">
            <div class="pxd-avatar">{{ $initial }}</div>
            <div class="pxd-head__main">
                <div class="pxd-head__title">
                    <span class="pxd-head__name">{{ $customer->name }}</span>
                    <span class="pxd-badge {{ $isBaru ? 'pxd-badge--baru' : 'pxd-badge--lama' }}">{{ $isBaru ? 'Pasien Baru' : 'Pasien Lama' }}</span>
                    <span class="pxd-badge pxd-badge--{{ $tier }}">{{ $tierLabels[$tier] }}</span>
                    @if($detail['perawatanLabel'])
                        <span class="pxd-badge pxd-badge--treat">{{ $detail['perawatanLabel'] }}</span>
                    @endif
                </div>
                <div class="pxd-head__meta">
                    <span><i class="fa fa-user"></i> {{ $sexLabel }}@if($usia !== null) · {{ $usia }} th @endif</span>
                    <span><i class="fa fa-map-marker-alt"></i> {{ $customer->branch?->name ?? '—' }}</span>
                    @if($customer->phone)
                        <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="pxd-head__wa"><i class="fab fa-whatsapp"></i> {{ $customer->phone }}</a>
                    @endif
                    @if($customer->email && $customer->email !== '-')
                        <span><i class="fa fa-envelope"></i> {{ $customer->email }}</span>
                    @endif
                </div>
            </div>
            <div class="pxd-head__actions">
                <a href="{{ route('rawreservation.new', ['customer' => $customer->id]) }}" class="pxd-btn pxd-btn--primary"><i class="fa fa-calendar-plus"></i> Buat Reservasi</a>
                @if($customer->phone)
                    <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="pxd-btn pxd-btn--wa"><i class="fab fa-whatsapp"></i> Chat</a>
                @endif
            </div>
        </div>

        {{-- ════════ TWO COLUMN ════════ --}}
        <div class="pxd-grid">

            {{-- ── MAIN ── --}}
            <div class="pxd-main">

                {{-- Tab nav --}}
                <div class="pxd-tabs">
                    <button type="button" class="pxd-tab {{ $activeTab === 'riwayat' ? 'active' : '' }}" data-pxd-target="riwayat">
                        <i class="fa fa-clock"></i> Riwayat
                        <span class="pxd-tab__badge pxd-tab__badge--riwayat">{{ count($riwayat['items']) }}</span>
                    </button>
                    <button type="button" class="pxd-tab {{ $activeTab === 'aktivitas' ? 'active' : '' }}" data-pxd-target="aktivitas">
                        <i class="fa fa-comment-dots"></i> Aktivitas
                        @if($activities->count() > 0)<span class="pxd-tab__badge pxd-tab__badge--aktivitas">{{ $activities->count() }}</span>@endif
                    </button>
                    <button type="button" class="pxd-tab {{ $activeTab === 'data' ? 'active' : '' }}" data-pxd-target="data">
                        <i class="fa fa-user"></i> Data Pasien
                    </button>
                </div>

                {{-- ── TAB: RIWAYAT (timeline gabungan reservasi + rekam medis) ── --}}
                <div class="pxd-card" data-pxd-panel="riwayat" style="display:{{ $activeTab === 'riwayat' ? '' : 'none' }}; padding:22px 24px;">
                    <div class="pxd-sec-head"><i class="fa fa-route"></i><span class="pxd-sec-head__t">Riwayat Perjalanan Pasien</span></div>
                    <div class="pxd-sec-sub">Reservasi, kunjungan, dan rekam medis dalam satu alur kronologis.</div>

                    {{-- Filter: Semua / Reservasi / Rekam Medis --}}
                    <div class="pxd-tlf-bar">
                        <button type="button" class="pxd-tlf is-active" data-tlf="semua">Semua <span class="pxd-tlf__badge">{{ count($riwayat['items']) }}</span></button>
                        <button type="button" class="pxd-tlf" data-tlf="reservasi">Reservasi <span class="pxd-tlf__badge">{{ $riwayat['reservasi'] }}</span></button>
                        <button type="button" class="pxd-tlf" data-tlf="rekam">Rekam Medis <span class="pxd-tlf__badge">{{ $riwayat['rekam'] }}</span></button>
                    </div>

                    @if(count($riwayat['items']) === 0)
                        <div class="pxd-tl__empty">Belum ada riwayat reservasi atau rekam medis untuk pasien ini.</div>
                    @else
                        @php $months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des']; @endphp
                        <div class="pxd-tl">
                            <div class="pxd-tl__line"></div>
                            @foreach($riwayat['items'] as $it)
                                @php $dl = $it->date->format('d') . ' ' . $months[(int)$it->date->format('n') - 1] . ' ' . $it->date->format('Y'); @endphp

                                @if($it->kind === 'res')
                                    {{-- Reservasi --}}
                                    <div class="pxd-tl__item" data-kind="res">
                                        <div class="pxd-tl__dot pxd-tl__dot--res"><i class="fa fa-calendar-alt"></i></div>
                                        <div class="pxd-tl__card">
                                            <div class="pxd-tl__row">
                                                <span class="pxd-tl__badge pxd-tl__badge--res">RESERVASI</span>
                                                <span class="pxd-tl__date">{{ $dl }}</span>
                                                <span class="pxd-tl__sep">·</span>
                                                <span class="pxd-tl__doctor">{{ $it->doctor }}</span>
                                                <span class="pxd-tl__status" style="background:{{ $it->statusBg }}; color:{{ $it->statusColor }};">{{ $it->statusLabel }}</span>
                                            </div>
                                            <div class="pxd-tl__summary">{{ $it->summary }}</div>
                                        </div>
                                    </div>
                                @else
                                    {{-- Rekam Medis --}}
                                    @php
                                        $hasAnam = $it->keluhan || $it->objektif;
                                        $hasDx   = (bool) $it->diagnosa;
                                        $hasProc = count($it->tindakan) > 0;
                                    @endphp
                                    <div class="pxd-tl__item" data-kind="rekam">
                                        <div class="pxd-tl__dot pxd-tl__dot--rekam"><i class="fa fa-notes-medical"></i></div>
                                        <div class="pxd-tl__card">
                                            <div class="pxd-tl__row">
                                                <span class="pxd-tl__badge pxd-tl__badge--rekam">REKAM MEDIS</span>
                                                <span class="pxd-tl__date">{{ $dl }}</span>
                                                @if($it->time)<span class="pxd-tl__time">{{ $it->time }}</span>@endif
                                                @if($it->code)<span class="pxd-tl__code">{{ $it->code }}</span>@endif
                                                <span class="pxd-tl__sep">·</span>
                                                <span class="pxd-tl__doctor">{{ $it->doctor }}</span>
                                                <button type="button" class="pxd-tl__toggle">
                                                    <span class="pxd-tl__toggle-show">Lihat detail</span>
                                                    <span class="pxd-tl__toggle-hide">Sembunyikan</span>
                                                    <i class="fa fa-chevron-down pxd-tl__chev"></i>
                                                </button>
                                            </div>
                                            <div class="pxd-tl__summary">{{ $it->summary }}</div>

                                            <div class="pxd-tl__detail">
                                                @if($hasAnam)
                                                    <div>
                                                        <div class="pxd-tl__bhead"><div class="pxd-tl__bhead-l"><i class="fa fa-comment-medical"></i><span class="pxd-tl__btitle">ANAMNESIS &amp; PEMERIKSAAN</span></div></div>
                                                        <div class="pxd-tl__kv">
                                                            @if($it->keluhan)<span class="pxd-tl__k">Keluhan</span><span class="pxd-tl__v">{{ $it->keluhan }}</span>@endif
                                                            @if($it->objektif)<span class="pxd-tl__k">Objektif</span><span class="pxd-tl__v">{{ $it->objektif }}</span>@endif
                                                        </div>
                                                    </div>
                                                @endif
                                                @if($hasDx)
                                                    <div>
                                                        <div class="pxd-tl__bhead"><div class="pxd-tl__bhead-l"><i class="fa fa-stethoscope"></i><span class="pxd-tl__btitle">DIAGNOSA &amp; RENCANA</span></div></div>
                                                        <div class="pxd-tl__kv"><span class="pxd-tl__k">Diagnosa</span><span class="pxd-tl__v pxd-tl__v--strong">{{ $it->diagnosa }}</span></div>
                                                    </div>
                                                @endif
                                                @if($hasProc)
                                                    <div>
                                                        <div class="pxd-tl__bhead"><div class="pxd-tl__bhead-l"><i class="fa fa-syringe"></i><span class="pxd-tl__btitle">TINDAKAN / PROSEDUR</span></div><span class="pxd-tl__bcount">{{ count($it->tindakan) }} item</span></div>
                                                        <div class="pxd-tl__procs">
                                                            @foreach($it->tindakan as $proc)
                                                                <div class="pxd-tl__proc"><i class="fa fa-check"></i> {{ $proc }}</div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                                @if(!$hasAnam && !$hasDx && !$hasProc)
                                                    <div class="pxd-tl__empty-clinical">Tidak ada catatan klinis (kontrol singkat).</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── TAB: AKTIVITAS (CRM log — raw_cus_activity) ── --}}
                <div class="pxd-card" data-pxd-panel="aktivitas" style="display:{{ $activeTab === 'aktivitas' ? '' : 'none' }}; padding:22px 24px;">
                    <div class="pxd-sec-head"><i class="fa fa-comment-dots"></i><span class="pxd-sec-head__t">Aktivitas &amp; Catatan</span></div>
                    <div class="pxd-sec-sub">Catat setiap kontak — telepon, WA, kunjungan — agar follow-up tidak terlewat.</div>

                    {{-- Quick log --}}
                    <form method="post" action="{{ route('rawcustomer.activity', $customer->id) }}" class="pxd-act-log">
                        @csrf
                        <div class="pxd-act-quick">
                            <button type="submit" name="type" value="telepon" class="pxd-act-qbtn pxd-act-qbtn--telepon"><i class="fa fa-phone"></i> Ditelpon</button>
                            <button type="submit" name="type" value="wa" class="pxd-act-qbtn pxd-act-qbtn--wa"><i class="fab fa-whatsapp"></i> WA terkirim</button>
                            <button type="submit" name="type" value="kunjungan" class="pxd-act-qbtn pxd-act-qbtn--kunjungan"><i class="fa fa-clipboard"></i> Catat kunjungan</button>
                        </div>
                        <div class="pxd-act-note">
                            <input type="text" name="note" class="pxd-input" placeholder="Tulis catatan… mis. pasien minta dihubungi sore" />
                            <button type="submit" name="type" value="catatan" class="pxd-btn pxd-btn--primary">Catat</button>
                        </div>
                    </form>

                    {{-- Feed --}}
                    @php
                        $actMeta = [
                            'telepon'   => ['Ditelpon',           'fa-phone',     '#3C5FA8', '#E4ECFB'],
                            'wa'        => ['WhatsApp terkirim',   'fa-whatsapp',  '#2F8E55', '#E3F4E8'],
                            'kunjungan' => ['Kunjungan tercatat',  'fa-clipboard', '#9A6614', '#FBEFD9'],
                            'catatan'   => ['Catatan',             'fa-pen',       '#7A5B43', '#F1E9DF'],
                        ];
                        $actMonths = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
                    @endphp
                    @if($activities->count() === 0)
                        <div class="pxd-act-empty">Belum ada aktivitas. Catat kontak pertama di atas.</div>
                    @else
                        <div class="pxd-act-feed">
                            <div class="pxd-act-feed__line"></div>
                            @foreach($activities as $a)
                                @php
                                    $m = $actMeta[$a->type] ?? $actMeta['catatan'];
                                    $isWa = ($m[1] === 'fa-whatsapp');
                                    $ad = $a->created_at ? \Carbon\Carbon::parse($a->created_at) : null;
                                    $adLbl = $ad ? ($ad->format('d') . ' ' . $actMonths[(int)$ad->format('n') - 1] . ' ' . $ad->format('Y')) : '';
                                @endphp
                                <div class="pxd-act-item">
                                    <div class="pxd-act-dot" style="background:{{ $m[3] }}; color:{{ $m[2] }};"><i class="{{ $isWa ? 'fab' : 'fa' }} {{ $m[1] }}"></i></div>
                                    <div class="pxd-act-body">
                                        <div class="pxd-act-head">
                                            <span class="pxd-act-label">{{ $m[0] }}</span>
                                            <span class="pxd-act-meta">{{ $adLbl }}@if($a->created_by_name) · {{ $a->created_by_name }}@endif</span>
                                        </div>
                                        @if($a->note)<div class="pxd-act-text">{{ $a->note }}</div>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── TAB: DATA PASIEN (regroup: KONTAK / IDENTITAS / KLINIS / LAINNYA) ── --}}
                @php
                    $tgl = ($customer->tanggal_lahir && $customer->tanggal_lahir != '0000-00-00') ? $customer->tanggal_lahir : null;
                @endphp
                <div class="pxd-card" data-pxd-panel="data" style="display:{{ $activeTab === 'data' ? '' : 'none' }}; padding:24px;">
                    <form method="post" action="{{ route('rawcustomer.update', $customer->id) }}" class="pxd-form">
                        @csrf
                        @method('PUT')

                        {{-- ── KONTAK ── --}}
                        <div class="pxd-fsec">
                            <div class="pxd-fsec__head"><span class="pxd-fsec__tick"></span><span class="pxd-fsec__label">Kontak</span></div>
                            <div class="pxd-fgrid">
                                <div class="pxd-field">
                                    <label class="pxd-label">Nama <span class="pxd-req">*</span></label>
                                    <input class="pxd-input {{ $errors->has('name') ? 'pxd-input--err' : '' }}" type="text" name="name" value="{{ old('name', $customer->name) }}" required />
                                    @if ($errors->has('name'))<div class="pxd-err">{{ $errors->first('name') }}</div>@endif
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">No. WhatsApp <span class="pxd-req">*</span></label>
                                    <input class="pxd-input pxd-tnum {{ $errors->has('phone') ? 'pxd-input--err' : '' }}" type="text" name="phone" value="{{ old('phone', $customer->phone) }}" required />
                                    @if ($errors->has('phone'))<div class="pxd-err">{{ $errors->first('phone') }}</div>@endif
                                </div>
                                <div class="pxd-field pxd-field--full">
                                    <label class="pxd-label">Email</label>
                                    <input class="pxd-input" type="email" name="email" value="{{ old('email', $customer->email) }}" placeholder="nama@email.com" />
                                </div>
                            </div>
                        </div>

                        {{-- ── MEDIA SOSIAL (repeatable: platform + username, bisa >1 akun) ── --}}
                        @php $socials = old('social_media', $customer->social_media ?? []); @endphp
                        <div class="pxd-fsec">
                            <div class="pxd-fsec__head"><span class="pxd-fsec__tick"></span><span class="pxd-fsec__label">Media Sosial</span></div>
                            <div class="pxd-social" id="pxdSocial" data-next="{{ count($socials) }}">
                                <div class="pxd-social__list" id="pxdSocialList">
                                    @foreach($socials as $i => $s)
                                        @php
                                            $p = $s['platform'] ?? '';
                                            $u = $s['username'] ?? '';
                                            $url = \App\Models\RawCustomer::socialProfileUrl($p, $u);
                                        @endphp
                                        <div class="pxd-social__row" data-social-row>
                                            <select class="pxd-input pxd-social__platform" name="social_media[{{ $i }}][platform]" data-social-platform>
                                                <option value="">-- Platform --</option>
                                                <option value="instagram" {{ $p === 'instagram' ? 'selected' : '' }}>Instagram</option>
                                                <option value="tiktok" {{ $p === 'tiktok' ? 'selected' : '' }}>TikTok</option>
                                                <option value="facebook" {{ $p === 'facebook' ? 'selected' : '' }}>Facebook</option>
                                            </select>
                                            <div class="pxd-social__uwrap">
                                                <input class="pxd-input pxd-social__username" type="text" name="social_media[{{ $i }}][username]" value="{{ $u }}" placeholder="username" data-social-username>
                                                <a class="pxd-social__open" href="{{ $url ?: '#' }}" target="_blank" rel="noopener" title="Buka profil" data-social-open {{ $url ? '' : 'hidden' }}><i class="fa fa-external-link-alt"></i></a>
                                            </div>
                                            <button type="button" class="pxd-social__del" data-social-del title="Hapus akun"><i class="fa fa-trash"></i></button>
                                        </div>
                                    @endforeach
                                </div>
                                <button type="button" class="pxd-social__add" id="pxdSocialAdd"><i class="fa fa-plus"></i> Tambah akun</button>
                            </div>
                        </div>
                        <template id="pxdSocialTpl">
                            <div class="pxd-social__row" data-social-row>
                                <select class="pxd-input pxd-social__platform" name="social_media[__IDX__][platform]" data-social-platform>
                                    <option value="">-- Platform --</option>
                                    <option value="instagram">Instagram</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="facebook">Facebook</option>
                                </select>
                                <div class="pxd-social__uwrap">
                                    <input class="pxd-input pxd-social__username" type="text" name="social_media[__IDX__][username]" placeholder="username" data-social-username>
                                    <a class="pxd-social__open" href="#" target="_blank" rel="noopener" title="Buka profil" data-social-open hidden><i class="fa fa-external-link-alt"></i></a>
                                </div>
                                <button type="button" class="pxd-social__del" data-social-del title="Hapus akun"><i class="fa fa-trash"></i></button>
                            </div>
                        </template>
                        <style>
                            .pxd-social__list { display: flex; flex-direction: column; gap: 10px; }
                            .pxd-social__row { display: flex; gap: 8px; align-items: center; }
                            .pxd-social__platform { flex: 0 0 150px; max-width: 150px; }
                            .pxd-social__uwrap { position: relative; flex: 1 1 auto; min-width: 0; }
                            .pxd-social__uwrap .pxd-social__username { width: 100%; padding-right: 38px; }
                            .pxd-social__open { position: absolute; right: 7px; top: 50%; transform: translateY(-50%); width: 27px; height: 27px; display: inline-flex; align-items: center; justify-content: center; border-radius: 7px; color: #9A6614; text-decoration: none; font-size: 13px; }
                            .pxd-social__open:hover { background: #F5E9D6; color: #6b3f00; }
                            .pxd-social__del { flex: 0 0 auto; width: 40px; height: 40px; border: 1px solid #E3D6C4; background: #fff; color: #A3572E; border-radius: 10px; cursor: pointer; transition: .15s; }
                            .pxd-social__del:hover { background: #FBEBE4; border-color: #C86B3C; }
                            .pxd-social__add { margin-top: 10px; border: 1px dashed #C9A876; background: #FBF4E9; color: #7A4E12; font-weight: 600; font-size: 13px; padding: 9px 15px; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: .15s; }
                            .pxd-social__add:hover { background: #F5E9D6; border-color: #A3792F; }
                            @media (max-width: 560px) { .pxd-social__platform { flex-basis: 116px; max-width: 116px; } }
                        </style>

                        {{-- ── IDENTITAS ── --}}
                        <div class="pxd-fsec">
                            <div class="pxd-fsec__head"><span class="pxd-fsec__tick"></span><span class="pxd-fsec__label">Identitas</span></div>
                            <div class="pxd-fgrid">
                                <div class="pxd-field">
                                    <label class="pxd-label">Jenis Kelamin</label>
                                    <select class="pxd-input" name="jenis_kelamin">
                                        <option value="">-- Pilih --</option>
                                        <option value="L" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'L' ? 'selected' : '' }}>Laki-laki</option>
                                        <option value="P" {{ old('jenis_kelamin', $customer->jenis_kelamin) == 'P' ? 'selected' : '' }}>Perempuan</option>
                                    </select>
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Tanggal Lahir</label>
                                    <input class="pxd-input" type="date" name="tanggal_lahir" value="{{ old('tanggal_lahir', $tgl) }}" />
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Tempat Lahir</label>
                                    <input class="pxd-input" type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $customer->tempat_lahir) }}" />
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">NIK</label>
                                    <input class="pxd-input pxd-tnum" type="text" name="nik" value="{{ old('nik', $customer->nik) }}" placeholder="16 digit NIK" maxlength="20" />
                                </div>
                                <div class="pxd-field pxd-field--full">
                                    <label class="pxd-label">Alamat</label>
                                    <textarea class="pxd-input" name="alamat" rows="2">{{ old('alamat', $customer->alamat) }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- ── KLINIS & AKUISISI ── --}}
                        <div class="pxd-fsec">
                            <div class="pxd-fsec__head"><span class="pxd-fsec__tick"></span><span class="pxd-fsec__label">Klinis &amp; Akuisisi</span></div>
                            <div class="pxd-fgrid">
                                <div class="pxd-field">
                                    <label class="pxd-label">Cabang</label>
                                    @if($userBranch)
                                        <input class="pxd-input" type="text" value="{{ $userBranch->name }}" readonly />
                                        <input type="hidden" name="raw_branch_id" value="{{ $userBranch->id }}" />
                                    @else
                                        <select class="pxd-input" name="raw_branch_id">
                                            <option value="">-- Pilih Cabang --</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" {{ old('raw_branch_id', $customer->raw_branch_id) == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Dokter</label>
                                    <select class="pxd-input" name="raw_doctor_id">
                                        <option value="">-- Pilih Dokter --</option>
                                        @foreach ($doctors as $doctor)
                                            <option value="{{ $doctor->id }}" {{ old('raw_doctor_id', $customer->raw_doctor_id) == $doctor->id ? 'selected' : '' }}>{{ $doctor->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Perawatan</label>
                                    <select class="pxd-input" name="raw_doc_service_id">
                                        <option value="">-- Pilih Perawatan --</option>
                                        @foreach ($services as $service)
                                            <option value="{{ $service->id }}" {{ old('raw_doc_service_id', $customer->raw_doc_service_id) == $service->id ? 'selected' : '' }}>{{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Referensi</label>
                                    <input class="pxd-input" type="text" name="source_app" value="{{ old('source_app', $customer->source_app) }}" placeholder="Instagram, teman, dll" />
                                </div>
                            </div>
                        </div>

                        {{-- ── LAINNYA (field admin yg tak ada di handoff — biar tak hilang) ── --}}
                        <div class="pxd-fsec">
                            <div class="pxd-fsec__head"><span class="pxd-fsec__tick"></span><span class="pxd-fsec__label">Lainnya</span></div>
                            <div class="pxd-fgrid">
                                <div class="pxd-field">
                                    <label class="pxd-label">Tanggal Daftar</label>
                                    <input class="pxd-input" type="date" name="tanggal_daftar" value="{{ old('tanggal_daftar', $customer->tanggal_daftar) }}" />
                                </div>
                                <div class="pxd-field">
                                    <label class="pxd-label">Pasien Baru / Lama</label>
                                    <select class="pxd-input" name="pasien_baru_lama">
                                        <option value="">-- Pilih --</option>
                                        <option value="baru" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'baru' ? 'selected' : '' }}>Pasien Baru</option>
                                        <option value="lama" {{ old('pasien_baru_lama', $customer->pasien_baru_lama) == 'lama' ? 'selected' : '' }}>Pasien Lama</option>
                                    </select>
                                </div>
                                <div class="pxd-field pxd-field--full">
                                    <label class="pxd-label">Kode Promo / Referral</label>
                                    <select class="pxd-input" name="raw_promo_id">
                                        <option value="">-- Pilih Promo --</option>
                                        @foreach ($promos as $promo)
                                            <option value="{{ $promo->id }}" {{ old('raw_promo_id', $customer->raw_promo_id) == $promo->id ? 'selected' : '' }}>
                                                {{ $promo->promo_code }}@if($promo->affiliate) ({{ $promo->affiliate->name }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="pxd-field pxd-field--full">
                                    <label class="pxd-label">Catatan</label>
                                    <textarea class="pxd-input" name="catatan" rows="3" placeholder="Catatan internal tentang pasien...">{{ old('catatan', $customer->catatan) }}</textarea>
                                </div>
                            </div>
                        </div>

                        <div class="pxd-form__footer">
                            <button class="pxd-btn pxd-btn--save" type="submit"><i class="fa fa-save"></i> Simpan</button>
                            <button type="button" class="pxd-btn pxd-btn--danger" onclick="deleteData()"><i class="fa fa-trash"></i> Hapus</button>
                        </div>
                    </form>
                    <form action="{{ route('rawcustomer.destroy', $customer->id) }}" method="POST" id="delete">
                        @csrf @method('DELETE')
                    </form>
                </div>

            </div>

            {{-- ── RIGHT RAIL ── --}}
            <div class="pxd-rail">

                {{-- Nilai Pasien (LTV) --}}
                <div class="pxd-ltv">
                    <div class="pxd-ltv__label">NILAI PASIEN (LTV)</div>
                    <div class="pxd-ltv__value">Rp {{ number_format($detail['ltv'], 0, ',', '.') }}</div>
                    <div class="pxd-ltv__stats">
                        <div>
                            <div class="pxd-ltv__stat-n">{{ $detail['visits'] }}</div>
                            <div class="pxd-ltv__stat-l">Kunjungan</div>
                        </div>
                        <div>
                            <div class="pxd-ltv__stat-n">{{ $detail['reservasiCount'] }}</div>
                            <div class="pxd-ltv__stat-l">Reservasi</div>
                        </div>
                        <div>
                            <div class="pxd-ltv__stat-n">Rp {{ number_format($detail['perVisit'], 0, ',', '.') }}</div>
                            <div class="pxd-ltv__stat-l">Per kunjungan</div>
                        </div>
                    </div>
                </div>

                {{-- Ringkasan --}}
                <div class="pxd-rcard">
                    <div class="pxd-rcard__title">RINGKASAN</div>
                    <div class="pxd-sum">
                        <div class="pxd-sum__row"><span class="pxd-sum__k">Sumber akuisisi</span><span class="pxd-sum__v">{{ $detail['sumberAkuisisi'] }}</span></div>
                        <div class="pxd-sum__row"><span class="pxd-sum__k">Terdaftar</span><span class="pxd-sum__v">{{ $terdaftar }}</span></div>
                        <div class="pxd-sum__row"><span class="pxd-sum__k">Kunjungan terakhir</span><span class="pxd-sum__v">{{ $lastVisitLbl }}</span></div>
                        <div class="pxd-sum__row"><span class="pxd-sum__k">Dokter utama</span><span class="pxd-sum__v">{{ $dokterShort }}</span></div>
                    </div>
                </div>

                {{-- Kelengkapan Data --}}
                <div class="pxd-rcard">
                    <div class="pxd-comp__head">
                        <span class="pxd-rcard__title" style="margin-bottom:0;">KELENGKAPAN DATA</span>
                        <span class="pxd-comp__pct" style="color:{{ $compColor }};">{{ $compPct }}%</span>
                    </div>
                    <div class="pxd-comp__bar"><div class="pxd-comp__fill" style="width:{{ $compPct }}%; background:{{ $compColor }};"></div></div>
                    @if(count($missing) > 0)
                        <div class="pxd-comp__chips">
                            @foreach($missing as $m)
                                <span class="pxd-comp__chip">{{ $m }} belum diisi</span>
                            @endforeach
                        </div>
                    @else
                        <div class="pxd-comp__ok">✓ Data lengkap</div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // ── Tab switcher detail pasien ──
    (function () {
        var tabs = document.querySelectorAll('.pxd-tab');
        var panels = document.querySelectorAll('[data-pxd-panel]');
        function activate(name) {
            tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.pxdTarget === name); });
            panels.forEach(function (p) { p.style.display = p.dataset.pxdPanel === name ? '' : 'none'; });
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () { activate(t.dataset.pxdTarget); });
        });
    })();

    // ── Riwayat: filter Semua/Reservasi/Rekam Medis + expand rekam medis ──
    (function () {
        var panel = document.querySelector('[data-pxd-panel="riwayat"]');
        if (!panel) return;
        var filters = panel.querySelectorAll('.pxd-tlf');
        var items = panel.querySelectorAll('.pxd-tl__item');
        function applyFilter(kind) {
            filters.forEach(function (f) { f.classList.toggle('is-active', f.dataset.tlf === kind); });
            items.forEach(function (it) {
                var k = it.dataset.kind;
                var show = kind === 'semua' || (kind === 'reservasi' && k === 'res') || (kind === 'rekam' && k === 'rekam');
                it.style.display = show ? '' : 'none';
                // Filter "Rekam Medis" auto-expand; selain itu collapse (mirror handoff).
                if (k === 'rekam') { it.classList.toggle('is-open', kind === 'rekam'); }
            });
        }
        filters.forEach(function (f) { f.addEventListener('click', function () { applyFilter(f.dataset.tlf); }); });
        panel.querySelectorAll('.pxd-tl__toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.closest('.pxd-tl__item').classList.toggle('is-open');
            });
        });
    })();

    // ── Aktivitas: Enter di kolom catatan → submit tombol "Catat" (bukan tombol cepat pertama) ──
    (function () {
        var note = document.querySelector('.pxd-act-note .pxd-input');
        if (!note) return;
        note.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var btn = document.querySelector('.pxd-act-note button[value="catatan"]');
                if (btn) btn.click();
            }
        });
    })();

    // ── Media sosial: tambah/hapus baris + jadikan username tautan profil ──
    (function () {
        var wrap = document.getElementById('pxdSocial');
        if (!wrap) return;
        var list   = document.getElementById('pxdSocialList');
        var addBtn = document.getElementById('pxdSocialAdd');
        var tpl    = document.getElementById('pxdSocialTpl');

        function profileUrl(platform, username) {
            username = String(username || '').trim().replace(/^@+/, '');
            if (!username) return '';
            switch (platform) {
                case 'instagram': return 'https://instagram.com/' + username;
                case 'tiktok':    return 'https://tiktok.com/@' + username;
                case 'facebook':  return 'https://facebook.com/' + username;
                default:          return '';
            }
        }
        function refreshRow(row) {
            var sel  = row.querySelector('[data-social-platform]');
            var inp  = row.querySelector('[data-social-username]');
            var link = row.querySelector('[data-social-open]');
            if (!sel || !inp || !link) return;
            var url = profileUrl(sel.value, inp.value);
            if (url) { link.href = url; link.hidden = false; }
            else { link.href = '#'; link.hidden = true; }
        }
        function addRow() {
            var idx = parseInt(wrap.getAttribute('data-next') || '0', 10);
            wrap.setAttribute('data-next', idx + 1);
            var holder = document.createElement('div');
            holder.innerHTML = tpl.innerHTML.replace(/__IDX__/g, idx).trim();
            var row = holder.firstElementChild;
            list.appendChild(row);
            var sel = row.querySelector('[data-social-platform]');
            if (sel) sel.focus();
        }
        addBtn.addEventListener('click', addRow);
        list.addEventListener('click', function (e) {
            var del = e.target.closest('[data-social-del]');
            if (del) del.closest('[data-social-row]').remove();
        });
        list.addEventListener('input', function (e) {
            var row = e.target.closest('[data-social-row]');
            if (row) refreshRow(row);
        });
        list.addEventListener('change', function (e) {
            var row = e.target.closest('[data-social-row]');
            if (row) refreshRow(row);
        });
        list.querySelectorAll('[data-social-row]').forEach(refreshRow);
    })();

    function deleteData() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data pasien akan dihapus. Reservasi terkait tetap tersimpan tapi pasien-nya akan kosong.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then(function (result) {
            if (result.isConfirmed) { $('#delete').submit(); }
        })
    }
</script>
@endsection
