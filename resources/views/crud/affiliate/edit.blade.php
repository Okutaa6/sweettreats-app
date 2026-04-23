@extends('layouts.app')

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
            <form method="POST" action="{{ route('rawaffiliate.update', $affiliate->id) }}">
                @csrf
                @method('PUT')

                <a href="{{ route('rawaffiliate') }}" class="st-btn-outline-brown mb-3 d-inline-block">
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
                        <td style="font-weight:600;">No. WhatsApp</td>
                        <td>
                            <input class="form-control" type="text" name="phone"
                                value="{{ old('phone', $affiliate->phone) }}" />
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
                        <td style="font-weight:600;">Commission Rate (%)</td>
                        <td>
                            <div class="input-group" style="max-width:200px">
                                <input class="form-control" type="number" name="commission_rate"
                                    step="0.01" min="0" max="100"
                                    value="{{ old('commission_rate', $affiliate->commission_rate) }}" />
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </td>
                    </tr>
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

    {{-- Promo List --}}
    <div class="st-card mb-3">
        <div class="st-card__body">
            <h6 class="st-section-title"><i class="fa fa-tags"></i> Kode Promo</h6>
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
