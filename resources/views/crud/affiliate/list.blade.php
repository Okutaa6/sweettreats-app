@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">

                    {{-- REGISTRATION STATUS TABS --}}
                    <div class="st-tabs">
                        <a href="{{ route('rawaffiliate', ['registration_status' => 'all', 'type' => $filterType, 'q' => $search]) }}"
                           class="{{ $filterRegStatus === 'all' ? 'active' : '' }}">
                            <i class="fa fa-list"></i> Semua <span class="st-tab-count">{{ $regCounts['all'] }}</span>
                        </a>
                        <a href="{{ route('rawaffiliate', ['registration_status' => 'pending', 'type' => $filterType, 'q' => $search]) }}"
                           class="{{ $filterRegStatus === 'pending' ? 'active' : '' }}">
                            <i class="fa fa-clock"></i> Menunggu Review <span class="st-tab-count">{{ $regCounts['pending'] }}</span>
                        </a>
                        <a href="{{ route('rawaffiliate', ['registration_status' => 'approved', 'type' => $filterType, 'q' => $search]) }}"
                           class="{{ $filterRegStatus === 'approved' ? 'active' : '' }}">
                            <i class="fa fa-check"></i> Disetujui <span class="st-tab-count">{{ $regCounts['approved'] }}</span>
                        </a>
                        <a href="{{ route('rawaffiliate', ['registration_status' => 'rejected', 'type' => $filterType, 'q' => $search]) }}"
                           class="{{ $filterRegStatus === 'rejected' ? 'active' : '' }}">
                            <i class="fa fa-times"></i> Ditolak <span class="st-tab-count">{{ $regCounts['rejected'] }}</span>
                        </a>
                    </div>

                    {{-- FILTER BAR --}}
                    <form method="GET" action="{{ route('rawaffiliate') }}" class="st-filter-bar">
                        <input type="hidden" name="registration_status" value="{{ $filterRegStatus }}">

                        <label>Tipe</label>
                        <select name="type" onchange="this.form.submit();">
                            <option value="all" {{ $filterType === 'all' ? 'selected' : '' }}>Semua Tipe</option>
                            <option value="koc" {{ $filterType === 'koc' ? 'selected' : '' }}>KOC</option>
                            <option value="kol" {{ $filterType === 'kol' ? 'selected' : '' }}>KOL</option>
                            <option value="none" {{ $filterType === 'none' ? 'selected' : '' }}>Tanpa Tipe</option>
                        </select>

                        <label>KOL Progress</label>
                        <select name="kol_progress" onchange="this.form.submit();">
                            <option value="all" {{ ($filterKolProgress ?? 'all') === 'all' ? 'selected' : '' }}>Semua</option>
                            @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                                <option value="{{ $key }}" {{ ($filterKolProgress ?? '') === $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                            @endforeach
                        </select>

                        <label>Cari</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama atau nomor HP...">
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>
                        @if($search || $filterType !== 'all')
                            <a href="{{ route('rawaffiliate', ['registration_status' => $filterRegStatus]) }}" class="st-btn-outline-brown">
                                Reset
                            </a>
                        @endif

                        <div class="ml-auto d-flex" style="gap:6px;">
                            <a href="{{ route('rawaffiliate.new') }}?type=koc" class="st-btn-brand">
                                <i class="fa fa-user-plus"></i> Tambah KOC
                            </a>
                            <a href="{{ route('rawaffiliate.new') }}?type=kol" class="st-btn-brand" style="background:linear-gradient(135deg,#c45200,#8b3a00);">
                                <i class="fa fa-star"></i> Tambah KOL
                            </a>
                            <a href="{{ route('export.komisi') }}" class="st-btn-outline-brown">
                                <i class="fa fa-download"></i> Export Komisi CSV
                            </a>
                        </div>
                    </form>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Nama</th>
                                    <th>Role / Tipe</th>
                                    <th>Scope</th>
                                    <th>Phone</th>
                                    <th>Komisi</th>
                                    <th>Promo</th>
                                    <th>Reg Status</th>
                                    <th>Aktif</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($affiliates as $key => $row)
                                    <tr class="st-row-clickable {{ $row->registration_status === 'pending' ? 'st-row--pending-highlight' : '' }}"
                                        data-href="{{ route('rawaffiliate.edit', ['id' => $row->id, 'return' => request()->fullUrl()]) }}"
                                        style="cursor:pointer;">
                                        <td class="st-num">{{ $affiliates->firstItem() + $key }}</td>
                                        <td>
                                            <span class="st-primary">{{ $row->name }}</span>
                                            @if($row->email)
                                                <br><small class="st-muted">{{ $row->email }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $rolePills = ['pasien' => 'st-pill--info', 'influencer' => 'st-pill--warning', 'dokter' => 'st-pill--success'];
                                                $rolePill  = $rolePills[$row->role] ?? 'st-pill--neutral';
                                            @endphp
                                            <span class="st-pill st-pill--plain {{ $rolePill }}">
                                                {{ ucfirst($row->role ?? 'pasien') }}
                                            </span>
                                            @if($row->type)
                                                <span class="st-pill st-pill--plain st-pill--dark">{{ strtoupper($row->type) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php $scopeCfg = $row->scopeConfig(); @endphp
                                            @if($scopeCfg)
                                                <span class="st-pill st-pill--plain {{ $row->isBehelScope() ? 'st-pill--info' : 'st-pill--success' }}">
                                                    {{ $scopeCfg['short'] }}
                                                </span>
                                            @else
                                                <span class="st-muted" style="font-size:11px;">—</span>
                                            @endif
                                            @if($row->isKol())
                                                @php
                                                    $progressOpt = $row->kol_progress ? \App\Models\RawAffiliate::kolProgressOptions()[$row->kol_progress] ?? null : null;
                                                    $kategori    = $row->kol_kategori ? \App\Models\RawAffiliate::kolKategoriOptions()[$row->kol_kategori] ?? null : null;
                                                @endphp
                                                @if($progressOpt)
                                                    <br><span class="st-pill st-pill--plain st-pill--{{ $progressOpt['color'] }} mt-1" style="font-size:10px;">
                                                        {{ $progressOpt['label'] }}
                                                    </span>
                                                @endif
                                                @if($kategori)
                                                    <br><small class="text-muted" style="font-size:10px;">{{ $kategori }}</small>
                                                @endif
                                                @if($row->followers_count)
                                                    <br><small class="text-muted" style="font-size:10px;">
                                                        <i class="fa fa-users"></i> {{ number_format($row->followers_count, 0, ',', '.') }} followers
                                                    </small>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="st-tnum">{{ $row->phone ?? '-' }}</td>
                                        <td class="st-tnum">
                                            @if(is_numeric($row->commission_rate) && $row->commission_rate > 1000)
                                                Rp {{ number_format((float) $row->commission_rate, 0, ',', '.') }}
                                            @else
                                                {{ $row->commission_rate }}%
                                            @endif
                                        </td>
                                        <td class="st-tnum">{{ $row->promos_count }} kode</td>
                                        <td>
                                            @php
                                                $regStatusCfg = [
                                                    'pending'  => ['label' => 'Menunggu', 'pill' => 'st-pill--warning'],
                                                    'approved' => ['label' => 'Disetujui', 'pill' => 'st-pill--success'],
                                                    'rejected' => ['label' => 'Ditolak', 'pill' => 'st-pill--danger'],
                                                ][$row->registration_status] ?? ['label' => 'Disetujui', 'pill' => 'st-pill--success'];
                                            @endphp
                                            <span class="st-pill {{ $regStatusCfg['pill'] }}">{{ $regStatusCfg['label'] }}</span>
                                            @if($row->registration_status === 'rejected' && $row->registration_notes)
                                                <div class="st-muted" style="font-size:10px; margin-top:2px;">
                                                    {{ \Illuminate\Support\Str::limit($row->registration_notes, 40) }}
                                                </div>
                                            @endif
                                            @if($row->registration_status === 'pending')
                                                <span class="d-inline-flex" style="gap:4px; margin-left:6px;">
                                                    <button type="button" class="st-btn-approve" title="Setujui"
                                                            data-toggle="modal" data-target="#approveModal{{ $row->id }}">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                    <button type="button" class="st-btn-reject" title="Tolak"
                                                            data-toggle="modal" data-target="#rejectModal{{ $row->id }}">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </span>

                                                {{-- APPROVE MODAL --}}
                                                <div class="modal fade" id="approveModal{{ $row->id }}" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog" role="document">
                                                        <form method="POST" action="{{ route('rawaffiliate.approve', $row->id) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="modal-content" style="border-radius:10px;">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">
                                                                        <i class="fa fa-check-circle mr-2" style="color:var(--st-completed-text);"></i>
                                                                        Setujui Pendaftaran
                                                                    </h5>
                                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div style="background:var(--st-bg, #faf5f3); border-left:3px solid var(--st-accent); padding:12px 14px; margin-bottom:14px; font-size:13px;">
                                                                        <div style="font-weight:700; color:var(--st-primary); font-size:14px; margin-bottom:6px;">
                                                                            <i class="fa fa-user mr-1"></i> {{ $row->name }}
                                                                        </div>
                                                                        <div class="row" style="font-size:12.5px;">
                                                                            <div class="col-md-6" style="line-height:1.8;">
                                                                                <i class="fa fa-phone mr-1 text-muted"></i> {{ $row->phone }}<br>
                                                                                @if($row->email)
                                                                                    <i class="fa fa-envelope mr-1 text-muted"></i> {{ $row->email }}<br>
                                                                                @endif
                                                                                @if($row->social_platform)
                                                                                    <i class="fa fa-share-alt mr-1 text-muted"></i>
                                                                                    {{ \App\Models\RawAffiliate::socialPlatforms()[$row->social_platform] ?? $row->social_platform }}:
                                                                                    <strong>{{ $row->social_username }}</strong>
                                                                                @endif
                                                                            </div>
                                                                            <div class="col-md-6" style="line-height:1.8;">
                                                                                @if($row->docService)
                                                                                    <i class="fa fa-tooth mr-1 text-muted"></i> {{ $row->docService->name }}<br>
                                                                                @endif
                                                                                @if($row->branch)
                                                                                    <i class="fa fa-map-marker-alt mr-1 text-muted"></i> {{ $row->branch->name }}<br>
                                                                                @endif
                                                                                @if($row->doctor)
                                                                                    <i class="fa fa-user-md mr-1 text-muted"></i> {{ $row->doctor->name }}
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        @if($row->reward_program)
                                                                            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--st-border);">
                                                                                <i class="fa fa-gift mr-1" style="color:var(--st-primary);"></i>
                                                                                <strong>{{ \App\Models\RawAffiliate::rewardPrograms()[$row->reward_program] ?? $row->reward_program }}</strong>
                                                                            </div>
                                                                        @endif
                                                                        @if($row->referral_scope)
                                                                            @php $regScopeCfg = \App\Models\RawAffiliate::referralScopes()[$row->referral_scope] ?? null; @endphp
                                                                            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed var(--st-border);">
                                                                                <i class="fas {{$regScopeCfg['icon'] ?? 'fa-tag' }} mr-1" style="color:var(--st-primary);"></i>
                                                                                Mendaftar sebagai: <strong>{{ $regScopeCfg['label'] ?? $row->referral_scope }}</strong>
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Type <span class="text-danger">*</span></label>
                                                                        <select name="type" class="form-control" required>
                                                                            <option value="">-- Pilih Type --</option>
                                                                            <option value="koc">KOC (Key Opinion Consumer)</option>
                                                                            <option value="kol">KOL (Key Opinion Leader)</option>
                                                                        </select>
                                                                        <small class="text-muted">Tentukan kategori berdasarkan reach &amp; pengaruh sosial media.</small>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>Referral Scope <span class="text-danger">*</span></label>
                                                                        <select name="referral_scope" class="form-control" required>
                                                                            <option value="">-- Pilih Scope --</option>
                                                                            @foreach(\App\Models\RawAffiliate::referralScopes() as $sKey => $sCfg)
                                                                                <option value="{{ $sKey }}"
                                                                                    {{ $row->referral_scope === $sKey ? 'selected' : '' }}>
                                                                                    {{ $sCfg['label'] }} (voucher Rp {{ number_format($sCfg['voucher_amount'], 0, ',', '.') }})
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <small class="text-muted">Default ikut pilihan saat pendaftaran. Voucher amount akan otomatis ikut scope ini.</small>
                                                                    </div>
                                                                    <div class="form-group">
                                                                        <label>PIN 6 Digit <span class="text-danger">*</span></label>
                                                                        <input type="text" name="pin" class="form-control"
                                                                               inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                                                                               placeholder="contoh: 123456"
                                                                               style="letter-spacing:4px; font-weight:700; font-size:16px;">
                                                                        <small class="text-muted">
                                                                            Tentukan PIN login. <strong>Catat &amp; share via WA</strong> ke pasien setelah approve.
                                                                        </small>
                                                                    </div>
                                                                    <div class="st-alert st-alert--info" style="font-size:12px; margin-top:8px;">
                                                                        <i class="fa fa-info-circle mr-1"></i>
                                                                        Promo code akan di-generate otomatis setelah approve.
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="st-btn-brand">
                                                                        <i class="fa fa-check mr-1"></i> Setujui &amp; Aktivasi
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="modal fade" id="rejectModal{{ $row->id }}" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog" role="document">
                                                        <form method="POST" action="{{ route('rawaffiliate.reject', $row->id) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="modal-content" style="border-radius:10px;">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">
                                                                        <i class="fa fa-times-circle mr-2"></i>
                                                                        Tolak Pendaftaran
                                                                    </h5>
                                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p><strong>{{ $row->name }}</strong> &middot; {{ $row->phone }}</p>
                                                                    <div class="form-group">
                                                                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                                                                        <textarea name="registration_notes" class="form-control" rows="3" required minlength="3"
                                                                                  placeholder="Tulis alasan kenapa pendaftaran ini ditolak..."></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="st-btn-brand" style="background:var(--st-cancelled-text);">
                                                                        <i class="fa fa-times mr-1"></i> Tolak
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->is_active)
                                                <span class="st-pill st-pill--success">Aktif</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="st-muted">
                                                <i class="fa fa-inbox fa-2x mb-2 d-block"></i>
                                                Tidak ada referral untuk filter ini.
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $affiliates->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const tr = e.target.closest('tr.st-row-clickable');
        if (!tr) return;
        if (e.target.closest('button, a, .modal, [data-toggle="modal"]')) return;
        const href = tr.getAttribute('data-href');
        if (href) window.location.href = href;
    });
</script>
@endsection
