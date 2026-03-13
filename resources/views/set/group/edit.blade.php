@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>User Access</a></li>
                            <li class="breadcrumb-item active">Role</li>
                            <li class="breadcrumb-item active">Ubah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Ubah</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>
        <!-- end page title end breadcrumb -->

        @include('layouts.notif')
        <div class="row">

            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('setgroup.update', $group->id) }}">
                                @csrf
                                @method('PUT')
                                <a href="{{ route('setgroup') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;">
                                    <i class="fa fa-arrow-left"></i> Kembali
                                </a>

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                                type="text" name="nama" value="{{ old('nama', $group->nama) }}"
                                                required />
                                            @if ($errors->has('nama'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('nama') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Deskripsi</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('deskripsi') ? 'is-invalid' : '' }}"
                                                type="text" name="deskripsi"
                                                value="{{ old('deskripsi', $group->deskripsi) }}" required />
                                            @if ($errors->has('deskripsi'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('deskripsi') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Landing Page</td>
                                        <td>
                                            <input
                                                class="form-control {{ $errors->has('landing_page') ? 'is-invalid' : '' }}"
                                                type="text" name="landing_page"
                                                value="{{ old('landing_page', $group->landing_page) }}" required />
                                            @if ($errors->has('landing_page'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('landing_page') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                            <button type="button" class="btn btn-outline-info"
                                                onclick="deleteData('{{ $group->id }}')">
                                                <i class="fa fa-trash"></i> Hapus
                                            </button>
                                        </td>
                                    </tr>
                                </table>
                            </form>

                            <form id="delete" action="{{ route('setgroup.destroy', $group->id) }}" method="post">
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
    {{-- delete sweetalert --}}
    <script>
        function deleteData(id) {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
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
