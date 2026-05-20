@extends('layouts.app')

@php
    // Tentukan URL "Kembali": pakai ?return= kalau diberikan & valid (host sama),
    // selain itu fallback ke list raw affiliate.
    $returnRaw = request('return');
    $backUrl   = route('rawaffiliate');
    if ($returnRaw) {
        $parsed = parse_url($returnRaw);
        $isSameHost = empty($parsed['host']) || $parsed['host'] === request()->getHost();
        if ($isSameHost) {
            $backUrl = $returnRaw;
        }
    }
@endphp

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Detail Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Info Affiliate --}}
    <div class="st-card mb-3">
        <div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-user-tie"></i> Informasi Referral</h6>
            <form method="POST" action="{{ route('rawaffiliate.update', ['id' => $affiliate->id, 'return' => $returnRaw]) }}">
                @csrf
                @method('PUT')
                @if($returnRaw)
                    <input type="hidden" name="return" value="{{ $returnRaw }}">
                @endif

                <a href="{{ $backUrl }}" class="st-btn-outline-brown mb-3 d-inline-block">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>

                <table class="st-table">
                    <tr>
                        <td width="25%" style="font-weight:600;">Nama <span class="text-danger">*</span></td>
                        <td>
                            <input class="form-control" type="text" name="name"
                                value="{{ old('name', $affiliate->name) }}" required />
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Role <span class="text-danger">*</span></td>
                        <td>
                            <select class="form-control" name="role">
                                @foreach(App\Models\RawAffiliate::roles() as $key => $label)
                                    <option value="{{ $key }}"
                                        {{ old('role', $affiliate->role ?? 'pasien') == $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Role menentukan jenis pemilik kode referral ini</small>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Cabang</td>
                        <td>
                            <select class="form-control" name="raw_branch_id" style="max-width:380px">
                                <option value="">- Pilih Cabang -</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}"
                                        {{ old('raw_branch_id', $affiliate->raw_branch_id) == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">No. WhatsApp</td>
                        <td>
                            <input class="form-control" type="text" name="phone"
                                value="{{ old('phone', $affiliate->phone) }}" />
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Email</td>
                        <td>
                            <input class="form-control" type="email" name="email"
                                value="{{ old('email', $affiliate->email) }}" placeholder="kamu@email.com" />
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">PIN Portal (6 digit)</td>
                        <td>
                            <div class="input-group" style="max-width:200px">
                                <input class="form-control" type="text" name="pin"
                                    maxlength="6" pattern="\d{6}" placeholder="Isi untuk set/ubah PIN"
                                    value="" autocomplete="off" />
                            </div>
                            @if($affiliate->pin)
                                <small class="text-success">PIN sudah diset</small>
                            @else
                                <small class="text-muted">Belum ada PIN. Isi 6 digit angka agar affiliate bisa login ke portal.</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Info Rekening Bank</td>
                        <td>
                            <input class="form-control" type="text" name="bank_account_info"
                                value="{{ old('bank_account_info', $affiliate->bank_account_info) }}" />
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Referral Scope</td>
                        <td>
                            <select class="form-control" name="referral_scope" style="max-width:380px">
                                <option value="">-- Belum di-set --</option>
                                @foreach(\App\Models\RawAffiliate::referralScopes() as $sKey => $sCfg)
                                    <option value="{{ $sKey }}"
                                        {{ old('referral_scope', $affiliate->referral_scope) === $sKey ? 'selected' : '' }}>
                                        {{ $sCfg['label'] }} (voucher Rp {{ number_format($sCfg['voucher_amount'], 0, ',', '.') }} · komisi Rp {{ number_format($sCfg['commission_amount'], 0, ',', '.') }}/pasien)
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">
                                Mengubah scope akan otomatis sync ke semua kode promo affiliate ini.
                                @if($affiliate->referral_scope)
                                    Saat ini: <strong>{{ \App\Models\RawAffiliate::referralScopes()[$affiliate->referral_scope]['short'] ?? '-' }}</strong>.
                                @endif
                            </small>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight:600;">Komisi per Pasien</td>
                        <td>
                            @php $scopeCfg = $affiliate->scopeConfig(); @endphp
                            @if($scopeCfg)
                                <div class="d-flex align-items-center" style="gap:14px; flex-wrap:wrap;">
                                    <div>
                                        <div style="font-size:11px; color:#8b7a6b; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">Komisi KOC/KOL</div>
                                        <div style="font-size:18px; font-weight:800; color:#461500;">
                                            Rp {{ number_format($scopeCfg['commission_amount'], 0, ',', '.') }}
                                            <small style="font-size:11px; color:#8b7a6b; font-weight:600;">/ pasien</small>
                                        </div>
                                    </div>
                                    <div style="width:1px; height:36px; background:#e5d6c2;"></div>
                                    <div>
                                        <div style="font-size:11px; color:#8b7a6b; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">Voucher Pasien</div>
                                        <div style="font-size:18px; font-weight:800; color:#461500;">
                                            Rp {{ number_format($scopeCfg['voucher_amount'], 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div style="width:1px; height:36px; background:#e5d6c2;"></div>
                                    <div>
                                        <div style="font-size:11px; color:#8b7a6b; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">Min. Klaim</div>
                                        <div style="font-size:18px; font-weight:800; color:#461500;">
                                            {{ $scopeCfg['claim_min_patients'] }} pasien
                                            <small style="font-size:11px; color:#8b7a6b; font-weight:600;">(= Rp 500.000)</small>
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i> Nominal otomatis ikut Referral Scope. Untuk ubah, ganti scope di atas.</small>
                            @else
                                <span class="st-muted"><i class="fa fa-exclamation-triangle"></i> Set Referral Scope dulu untuk lihat nominal komisi.</span>
                            @endif
                        </td>
                    </tr>
                    @if($affiliate->isKol())
                        <tr>
                            <td colspan="2" style="background: linear-gradient(90deg, #fef5ed, #fff); border-top: 2px solid rgba(70,21,0,0.1); padding: 14px 18px;">
                                <strong style="color:#461500; font-size:13px; text-transform:uppercase; letter-spacing:0.5px;">
                                    <i class="fa fa-star mr-1"></i> Detail KOL
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Kategori KOL</td>
                            <td>
                                <select class="form-control" name="kol_kategori" style="max-width:320px">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolKategoriOptions() as $key => $label)
                                        <option value="{{ $key }}" {{ old('kol_kategori', $affiliate->kol_kategori) == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Progress Approach</td>
                            <td>
                                <select class="form-control" name="kol_progress" style="max-width:320px">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolProgressOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ old('kol_progress', $affiliate->kol_progress) == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Status Relasi</td>
                            <td>
                                <select class="form-control" name="kol_status_relasi" style="max-width:320px">
                                    <option value="">- Pilih -</option>
                                    @foreach(\App\Models\RawAffiliate::kolStatusRelasiOptions() as $key => $opt)
                                        <option value="{{ $key }}" {{ old('kol_status_relasi', $affiliate->kol_status_relasi) == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Followers</td>
                            <td>
                                <input class="form-control" type="number" min="0" name="followers_count" style="max-width:200px"
                                    value="{{ old('followers_count', $affiliate->followers_count) }}" placeholder="Mis. 50000">
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Rate Card</td>
                            <td>
                                <input class="form-control" type="text" name="rate_card" style="max-width:320px"
                                    value="{{ old('rate_card', $affiliate->rate_card) }}" placeholder="Mis. Rp 5.000.000 atau 5-10jt">
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Contact Person</td>
                            <td>
                                <input class="form-control" type="text" name="contact_person" style="max-width:320px"
                                    value="{{ old('contact_person', $affiliate->contact_person) }}" placeholder="Manager / agency">
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Tanggal Approach</td>
                            <td>
                                <input class="form-control" type="date" name="tanggal_approach" style="max-width:200px"
                                    value="{{ old('tanggal_approach', $affiliate->tanggal_approach?->format('Y-m-d')) }}">
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="font-weight:600;">Status</td>
                        <td>
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="custom-control-input" id="isActive"
                                    name="is_active" value="1" {{ $affiliate->is_active ? 'checked' : '' }}>
                                <label class="custom-control-label" for="isActive">Aktif</label>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>
                            <button class="st-btn-brand" type="submit">
                                <i class="fa fa-save"></i> Simpan
                            </button>
                            <button type="button" class="st-btn-outline-brown" onclick="deleteAffiliate()">
                                <i class="fa fa-trash"></i> Hapus
                            </button>
                        </td>
                    </tr>
                </table>
            </form>
            <form action="{{ route('rawaffiliate.destroy', $affiliate->id) }}" method="POST" id="deleteForm">
                @csrf @method('DELETE')
            </form>
        </div>
    </div>

    {{-- ADD PROMO MODAL --}}
    <div class="modal fade" id="addPromoModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('rawaffiliate.add-promo', $affiliate->id) }}">
                @csrf
                <div class="modal-content" style="border-radius:10px;">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fa fa-plus-circle mr-2" style="color:var(--st-primary);"></i>
                            Tambah Kode Referral
                        </h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        @php $sc = $affiliate->scopeConfig(); @endphp
                        @if($sc)
                            <div style="background:#faf5f3; border-left:3px solid var(--st-primary); padding:12px 14px; margin-bottom:14px; font-size:13px;">
                                <div style="font-weight:700; color:var(--st-primary); margin-bottom:4px;">
                                    <i class="fa fa-user mr-1"></i> {{ $affiliate->name }}
                                </div>
                                <div style="color:#6b4a30;">
                                    Scope: <strong>{{ $sc['label'] }}</strong> ·
                                    Voucher pasien <strong>Rp {{ number_format($sc['voucher_amount'], 0, ',', '.') }}</strong>
                                </div>
                            </div>
                        @endif
                        <div class="form-group">
                            <label>Kode Promo <small class="text-muted">(opsional — kosongkan untuk auto-generate)</small></label>
                            <input type="text" name="promo_code" class="form-control" maxlength="50"
                                   placeholder="Contoh: {{ strtoupper(explode(' ', trim($affiliate->name))[0] ?? 'PROMO') }}500"
                                   style="text-transform:uppercase; font-weight:700;">
                            <small class="text-muted">Akan otomatis diubah ke huruf kapital. Harus unique.</small>
                        </div>
                        <div class="form-group">
                            <label>Berlaku Sampai <small class="text-muted">(opsional)</small></label>
                            <input type="date" name="valid_until" class="form-control">
                            <small class="text-muted">Kosongkan jika tidak ada batas waktu.</small>
                        </div>
                        <div class="st-alert st-alert--info" style="font-size:12px; margin-top:8px;">
                            <i class="fa fa-info-circle mr-1"></i>
                            Nilai voucher otomatis ikut scope affiliate ini.
                            Status default: <strong>Aktif</strong>.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-save mr-1"></i> Simpan Kode
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Promo List --}}
    <div class="st-card mb-3">
        <div class="st-card__body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="st-section-title mb-0"><i class="fa fa-tags"></i> Kode Referral</h6>
                <button type="button" class="st-btn-brand btn-sm" data-toggle="modal" data-target="#addPromoModal"
                    @if(!$affiliate->referral_scope) disabled title="Set Referral Scope dulu di atas" @endif>
                    <i class="fa fa-plus"></i> Tambah Kode
                </button>
            </div>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Tipe Diskon</th>
                            <th>Nilai</th>
                            <th>Berlaku Sampai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($affiliate->promos as $promo)
                            <tr class="st-row-clickable"
                                data-href="{{ route('rawpromo.edit', $promo->id) }}"
                                style="cursor:pointer;"
                                title="Klik untuk edit kode promo">
                                <td>
                                    <a href="{{ route('rawpromo.edit', $promo->id) }}" class="st-link-plain">
                                        <strong>{{ $promo->promo_code }}</strong>
                                        <i class="fa fa-external-link-alt ml-1" style="font-size:10px; opacity:.55;"></i>
                                    </a>
                                </td>
                                <td>{{ $promo->discount_type == 'nominal' ? 'Nominal' : 'Persentase' }}</td>
                                <td class="st-tnum">
                                    @if($promo->discount_type == 'nominal')
                                        Rp {{ number_format($promo->discount_value, 0, ',', '.') }}
                                    @else
                                        {{ $promo->discount_value }}%
                                    @endif
                                </td>
                                <td>{{ $promo->valid_until ? $promo->valid_until->format('d/m/Y') : 'Selamanya' }}</td>
                                <td>
                                    @if($promo->is_active && (!$promo->valid_until || $promo->valid_until->isFuture()))
                                        <span class="st-pill st-pill--success">Aktif</span>
                                    @else
                                        <span class="st-pill st-pill--neutral">Nonaktif/Expired</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center st-muted">Belum ada promo</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Commission List --}}
    <div class="st-card">
        <div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-money-bill-wave"></i> Riwayat Komisi</h6>
            <div class="st-table-wrap--scroll">
                <table class="st-table st-table--compact">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Reservasi</th>
                            <th>Jumlah Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($affiliate->commissions as $commission)
                            @php $statusOpt = \App\Models\RawCommission::statusOptions()[$commission->status] ?? []; @endphp
                            <tr>
                                <td><span class="st-date">{{ $commission->created_at->format('d/m/Y') }}</span></td>
                                <td><span class="st-date">{{ $commission->reservation->date->format('d/m/Y') ?? '-' }}</span></td>
                                <td class="st-tnum">Rp {{ number_format($commission->amount, 0, ',', '.') }}</td>
                                <td>
                                    @php
                                        $pillMap = ['pending' => 'warning', 'approved' => 'info', 'paid' => 'success', 'rejected' => 'danger'];
                                        $pillCls = $pillMap[$commission->status] ?? 'neutral';
                                    @endphp
                                    <span class="st-pill st-pill--{{ $pillCls }}">
                                        {{ $statusOpt['label'] ?? $commission->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center st-muted">Belum ada komisi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    document.addEventListener('click', function (e) {
        const tr = e.target.closest('tr.st-row-clickable');
        if (!tr) return;
        if (e.target.closest('button, a, input, select, textarea')) return;
        const href = tr.getAttribute('data-href');
        if (href) window.location.href = href;
    });

    function deleteAffiliate() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data referral yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#deleteForm').submit(); }
        })
    }
</script>
@endsection
