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

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <form method="post" action="{{ route('rawbranch.update', $branch->id) }}">
                            @csrf
                            @method('PUT')

                            <a href="{{ route('rawbranch') }}" class="btn btn-sm btn-warning mb-3">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="table table-bordered table-detail">
                                <colgroup>
                                    <col class="c-label">
                                    <col class="c-value">
                                </colgroup>
                                <tbody>
                                    <tr>
                                        <td>Nama Cabang</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name"
                                                value="{{ old('name', $branch->name) }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    {{-- ← BARU --}}
                                    <tr>
                                        <td>Target Tahunan (Rp)</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('target_tahunan') ? 'is-invalid' : '' }}"
                                                type="number" name="target_tahunan" min="0"
                                                value="{{ old('target_tahunan', $branch->target_tahunan) }}"
                                                placeholder="Contoh: 500000000" />
                                            <small class="text-muted">Kosongkan jika belum ada target</small>
                                            @if ($errors->has('target_tahunan'))
                                                <div class="invalid-feedback">{{ $errors->first('target_tahunan') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fa fa-save"></i> Simpan
                                            </button>
                                            <button type="button" class="btn btn-outline-danger"
                                                onclick="deleteData()">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
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