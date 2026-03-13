@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('rawaffiliate') }}">Affiliate</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
                <h4 class="page-title">Detail Affiliate</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Info Affiliate --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Informasi Affiliate</h4></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('rawaffiliate.update', $affiliate->id) }}">
                        @csrf
                        @method('PUT')

                        <a href="{{ route('rawaffiliate') }}" class="btn btn-sm btn-warning mb-3">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="table table-bordered" width="100%">
                            <tr>
                                <td width="25%">Nama <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control" type="text" name="name"
                                        value="{{ old('name', $affiliate->name) }}" required />
                                </td>
                            </tr>
                            <tr>
                                <td>No. WhatsApp</td>
                                <td>
                                    <input class="form-control" type="text" name="phone"
                                        value="{{ old('phone', $affiliate->phone) }}" />
                                </td>
                            </tr>
                            <tr>
                                <td>Info Rekening Bank</td>
                                <td>
                                    <input class="form-control" type="text" name="bank_account_info"
                                        value="{{ old('bank_account_info', $affiliate->bank_account_info) }}" />
                                </td>
                            </tr>
                            <tr>
                                <td>Commission Rate (%)</td>
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
                                <td>Status</td>
                                <td>
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" class="custom-control-input" id="isActive"
                                            name="is_active" value="1"
                                            {{ $affiliate->is_active ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="isActive">Aktif</label>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteAffiliate()">
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
        </div>
    </div>

    {{-- Promo List --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Kode Promo</h4></div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
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
                                <tr>
                                    <td><strong>{{ $promo->promo_code }}</strong></td>
                                    <td>{{ $promo->discount_type == 'nominal' ? 'Nominal' : 'Persentase' }}</td>
                                    <td>
                                        @if($promo->discount_type == 'nominal')
                                            Rp {{ number_format($promo->discount_value, 0, ',', '.') }}
                                        @else
                                            {{ $promo->discount_value }}%
                                        @endif
                                    </td>
                                    <td>{{ $promo->valid_until ? $promo->valid_until->format('d/m/Y') : 'Selamanya' }}</td>
                                    <td>
                                        @if($promo->is_active && (!$promo->valid_until || $promo->valid_until->isFuture()))
                                            <span class="badge badge-success">Aktif</span>
                                        @else
                                            <span class="badge badge-secondary">Nonaktif/Expired</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">Belum ada promo</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Commission List --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Riwayat Komisi</h4></div>
                <div class="card-body">
                    <table class="table table-bordered table-hover">
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
                                    <td>{{ $commission->created_at->format('d/m/Y') }}</td>
                                    <td>{{ $commission->reservation->date->format('d/m/Y') ?? '-' }}</td>
                                    <td>Rp {{ number_format($commission->amount, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge badge-{{ $statusOpt['color'] ?? 'secondary' }}">
                                            {{ $statusOpt['label'] ?? $commission->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">Belum ada komisi</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    function deleteAffiliate() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data affiliate yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#deleteForm').submit(); }
        })
    }
</script>
@endsection