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
                            <li class="breadcrumb-item active">Akses</li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Edit</h4>
                </div>
                <!--end page-title-box-->
            </div>
            <!--end col-->
        </div>
        <!-- end page title end breadcrumb -->

        <div class="row">
            <div class="col-md-12">
                @include('layouts.notif')
                <div class="card">

                    <div class="card-body">

                        <div class="table-responsive">
                            <form method="post" action="{{ route('setaccess.update', $data->id) }}">
                                @csrf
                                @method('PUT')
                                <a href="{{ route('setaccess') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali </a>

                                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                    <tr>
                                        <td>Induk</td>
                                        <td>
                                            <select class="select2 form-control mb-3 custom-select" name="pid">
                                                <option value="">- Tanpa Induk - </option>
                                                <!-- parent -->
                                                @foreach ($access as $row)
                                                    @php
                                                        $row = (object) $row;
                                                    @endphp
                                                    <option value="{{ $row->id }}"
                                                        {{ $data->pid == $row->id ? 'selected' : '' }}>
                                                        {{ $row->nama }}</option>

                                                    <!-- sub -->
                                                    @if (!empty($row->sub))
                                                        @foreach ($row->sub as $rowsub)
                                                            @php
                                                                $rowsub = (object) $rowsub;
                                                            @endphp
                                                            <option value="{{ $rowsub->id }}"
                                                                {{ $data->pid == $rowsub->id ? 'selected' : '' }}>--
                                                                {{ $rowsub->nama }}</option>

                                                            <!-- child -->
                                                            @if (!empty($rowsub->child))
                                                                @foreach ($rowsub->child as $rowchild)
                                                                    @php
                                                                        $rowchild = (object) $rowchild;
                                                                    @endphp
                                                                    <option value="{{ $rowchild->id }}"
                                                                        {{ $data->pid == $rowchild->id ? 'selected' : '' }}>
                                                                        ------ {{ $rowchild->nama }}
                                                                    </option>
                                                                @endforeach
                                                            @endif
                                                            <!-- child -->
                                                        @endforeach
                                                    @endif
                                                    <!-- sub -->
                                                @endforeach
                                                <!-- parent -->
                                            </select>

                                            <small class="form-text text-danger">{{ $errors->first('group') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Nama</td>
                                        <td>
                                            <input class="form-control" type="text" name="nama"
                                                value="{{ $data->nama }}" required />
                                            <small class="form-text text-danger">{{ $errors->first('nama') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                    <tr>
                                        <td>Icon</td>
                                        <td>
                                            <input class="form-control" type="text" name="icon"
                                                value="{{ $data->icon }}" />
                                            <small class="form-text text-danger">{{ $errors->first('icon') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Urutan</td>
                                        <td>
                                            <input class="form-control" type="number" name="urutan"
                                                value="{{ $data->urutan }}" required />
                                            <small class="form-text text-danger">{{ $errors->first('urutan') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Link</td>
                                        <td>
                                            <input class="form-control" type="text" name="link"
                                                value="{{ $data->link }}" />
                                            <small class="form-text text-danger">{{ $errors->first('link') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>
                                            <button class="btn btn-danger" type="button"
                                                onclick="deleteData('{{ $data->id }}')"><i class="fa fa-trash"></i>
                                                Hapus</button>
                                        </td>
                                    </tr>

                                </table>
                            </form>
                            <form action="{{ route('setaccess.destroy', $data->id) }}" method="POST" id="delete">
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
