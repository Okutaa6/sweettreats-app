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
                            <li class="breadcrumb-item active">Tambah</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Tambah</h4>
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
                            <form method="post" action="{{ route('setaccess.store') }}">
                                @csrf
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
                                                        {{ old('pid') == $row->id ? 'selected' : '' }}>
                                                        {{ $row->nama }}</option>

                                                    <!-- sub -->
                                                    @if (!empty($row->sub))
                                                        @foreach ($row->sub as $rowsub)
                                                            @php
                                                                $rowsub = (object) $rowsub;
                                                            @endphp
                                                            <option value="{{ $rowsub->id }}"
                                                                {{ old('pid') == $rowsub->id ? 'selected' : '' }}>--
                                                                {{ $rowsub->nama }}</option>

                                                            <!-- child -->
                                                            @if (!empty($rowsub->child))
                                                                @foreach ($rowsub->child as $rowchild)
                                                                    @php
                                                                        $rowchild = (object) $rowchild;
                                                                    @endphp
                                                                    <option value="{{ $rowchild->id }}"
                                                                        {{ old('pid') == $rowchild->id ? 'selected' : '' }}>
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
                                                value="{{ old('nama') }}" required />
                                            <small class="form-text text-danger">{{ $errors->first('nama') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                    <tr>
                                        <td>Icon</td>
                                        <td>
                                            <input class="form-control" type="text" name="icon"
                                                value="{{ old('icon') }}" />
                                            <small class="form-text text-danger">{{ $errors->first('icon') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Urutan</td>
                                        <td>
                                            <input class="form-control" type="number" name="urutan"
                                                value="{{ old('urutan') }}" required />
                                            <small class="form-text text-danger">{{ $errors->first('urutan') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Link</td>
                                        <td>
                                            <input class="form-control" type="text" name="link"
                                                value="{{ old('link') }}" />
                                            <small class="form-text text-danger">{{ $errors->first('link') }}</small>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i>
                                                Simpan</button>

                                        </td>
                                    </tr>

                                </table>
                            </form>

                        </div>
                    </div>
                    <!--end card-body-->


                </div>
            </div>
        </div>

    </div>
@endsection
