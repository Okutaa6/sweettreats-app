@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Edit Akses</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setaccess.update', $data->id) }}">
                            @csrf
                            @method('PUT')
                            <a href="{{ route('setaccess') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Induk</td>
                                    <td>
                                        <select class="select2 form-control" name="pid">
                                            <option value="">- Tanpa Induk -</option>
                                            @foreach ($access as $row)
                                                @php $row = (object) $row; @endphp
                                                <option value="{{ $row->id }}" {{ $data->pid == $row->id ? 'selected' : '' }}>
                                                    {{ $row->nama }}
                                                </option>
                                                @if (!empty($row->sub))
                                                    @foreach ($row->sub as $rowsub)
                                                        @php $rowsub = (object) $rowsub; @endphp
                                                        <option value="{{ $rowsub->id }}" {{ $data->pid == $rowsub->id ? 'selected' : '' }}>
                                                            -- {{ $rowsub->nama }}
                                                        </option>
                                                        @if (!empty($rowsub->child))
                                                            @foreach ($rowsub->child as $rowchild)
                                                                @php $rowchild = (object) $rowchild; @endphp
                                                                <option value="{{ $rowchild->id }}" {{ $data->pid == $rowchild->id ? 'selected' : '' }}>
                                                                    ------ {{ $rowchild->nama }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </select>
                                        <small class="form-text text-danger">{{ $errors->first('group') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control" type="text" name="nama" value="{{ $data->nama }}" required />
                                        <small class="form-text text-danger">{{ $errors->first('nama') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Icon</td>
                                    <td>
                                        <input class="form-control" type="text" name="icon" value="{{ $data->icon }}" />
                                        <small class="form-text text-danger">{{ $errors->first('icon') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Urutan</td>
                                    <td>
                                        <input class="form-control" type="number" name="urutan" value="{{ $data->urutan }}" required />
                                        <small class="form-text text-danger">{{ $errors->first('urutan') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Link</td>
                                    <td>
                                        <input class="form-control" type="text" name="link" value="{{ $data->link }}" />
                                        <small class="form-text text-danger">{{ $errors->first('link') }}</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>
                                        <button class="st-btn-brand" type="submit">
                                            <i class="fa fa-save"></i> Simpan
                                        </button>
                                        <button class="st-btn-outline-brown" type="button" onclick="deleteData('{{ $data->id }}')">
                                            <i class="fa fa-trash"></i> Hapus
                                        </button>
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
@endsection

@section('js')
<script>
    function deleteData(id) {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#delete').submit(); }
        })
    }
</script>
@endsection
