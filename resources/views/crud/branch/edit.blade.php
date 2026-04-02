@extends('layouts.app')

@section('content')

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('rawbranch') }}">Branch</a></li>
                        <li class="breadcrumb-item active">Detail</li>
                    </ol>
                </div>
                <h4 class="page-title">Detail Branch</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Form Info Cabang --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header"><h4 class="card-title">Informasi Cabang</h4></div>
                <div class="card-body">
                    <form method="post" action="{{ route('rawbranch.update', $branch->id) }}">
                        @csrf
                        @method('PUT')

                        <a href="{{ route('rawbranch') }}" class="btn btn-sm btn-warning mb-3">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="table table-bordered">
                            <tr>
                                <td width="25%">Nama Cabang <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                        type="text" name="name"
                                        value="{{ old('name', $branch->name) }}" />
                                    @if ($errors->has('name'))
                                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>&nbsp;</td>
                                <td>
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" onclick="deleteData()">
                                        <i class="fa fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </form>

                    <form action="{{ route('rawbranch.destroy', $branch->id) }}" method="POST" id="delete">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h4 class="card-title mb-0">Target Bulanan</h4>

                    {{-- Filter Tahun --}}
                    <form method="GET" action="{{ route('rawbranch.edit', $branch->id) }}"
                        class="d-flex align-items-center" style="gap:8px;">
                        <label class="mb-0 font-weight-bold" style="white-space:nowrap;">Tahun:</label>
                        <select name="tahun" class="form-control form-control-sm" style="width:120px;"
                            onchange="this.form.submit()">
                            @foreach($tahunList as $t)
                                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('rawbranch.target', $branch->id) }}">
                        @csrf

                        {{-- Kirim tahun yang sedang aktif --}}
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th width="30%">Bulan</th>
                                    <th>Target (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Models\RawBranchTarget::namaBulan() as $bulan => $namaBulan)
                                    @php
                                        // Ambil nilai target untuk bulan ini kalau ada
                                        $targetBulan = $targets->get($bulan);
                                        $nilaiTarget = $targetBulan ? $targetBulan->nominal : null;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $namaBulan }}</strong>
                                            <small class="text-muted">{{ $tahun }}</small>
                                        </td>
                                        <td>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">Rp</span>
                                                </div>
                                                <input type="number"
                                                    class="form-control"
                                                    name="nominal[{{ $bulan }}]"
                                                    value="{{ $nilaiTarget }}"
                                                    min="0"
                                                    step="1000"
                                                    placeholder="Kosongkan jika belum ada target">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-info">
                                    <td><strong>Total Target {{ $tahun }}</strong></td>
                                    <td>
                                        <strong>
                                            Rp {{ number_format($targets->sum('nominal'), 0, ',', '.') }}
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Simpan Target {{ $tahun }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@section('js')
    <script>
        function deleteData() {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#461500',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#delete').submit();
                }
            })
        }
    </script>
@endsection