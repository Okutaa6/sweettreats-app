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
                            <li class="breadcrumb-item active">User</li>
                            <li class="breadcrumb-item active">Ubah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Ubah</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-12">
                <div class="card">

                    <div class="card-body">

                        <div class="table-responsive">
                            <form method="post" action="{{ route('setusermanagement.update', $user->id) }}">
                                @csrf
                                @method('PUT')

                                <a href="{{ route('setusermanagement') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Email</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                                type="text" name="email" value="{{ old('email', $user['email']) }}" />
                                            @if ($errors->has('email'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('email') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nama</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                                type="text" name="nama" value="{{ old('nama', $user['nama']) }}" />
                                            @if ($errors->has('nama'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('nama') }}
                                                </div>
                                            @endif
                                        </td>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Role</td>
                                        <td>
                                            <select
                                                class="select2 form-control mb-3 custom-select {{ $errors->has('group') ? 'is-invalid' : '' }}"
                                                name="group_id">
                                                <option value="">- Pilih Role - </option>
                                                @foreach ($groups as $row)
                                                    <option value="{{ $row['id'] }}"
                                                        {{ $row['id'] == $user['group_id'] ? ' selected' : '' }}>
                                                        {{ $row['nama'] }}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('group'))
                                                <div class="invalid-feedback">
                                                    {{ $errors->first('group') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
    <td>Cabang</td>
    <td>
        <select class="select2 form-control mb-3 custom-select" name="raw_branch_id">
            <option value="">- Semua Cabang -</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch['id'] }}"
                    {{ $branch['id'] == $user['raw_branch_id'] ? 'selected' : '' }}>
                    {{ $branch['name'] }}
                </option>
            @endforeach
        </select>
    </td>
</tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                            <button type="button" class="btn btn-outline-info"
                                                onclick="deleteData('{{ $user->id }}')"><i class="fa fa-trash"></i>
                                                Hapus </button>
                                        </td>
                                    </tr>

                                </table>
                            </form>
                            <form action="{{ route('setusermanagement.destroy', $user->id) }}" method="POST"
                                id="delete">
                                @csrf
                                @method('DELETE')

                            </form>

                        </div>
                    </div>
                    <!--end card-body-->
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
