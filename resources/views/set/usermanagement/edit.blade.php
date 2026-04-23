@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Ubah User</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setusermanagement.update', $user->id) }}">
                            @csrf
                            @method('PUT')

                            <a href="{{ route('setusermanagement') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Email</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                            type="text" name="email" value="{{ old('email', $user['email']) }}" />
                                        @if ($errors->has('email'))
                                            <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                            type="text" name="nama" value="{{ old('nama', $user['nama']) }}" />
                                        @if ($errors->has('nama'))
                                            <div class="invalid-feedback">{{ $errors->first('nama') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Role</td>
                                    <td>
                                        <select class="select2 form-control {{ $errors->has('group') ? 'is-invalid' : '' }}"
                                            name="group_id">
                                            <option value="">- Pilih Role -</option>
                                            @foreach ($groups as $row)
                                                <option value="{{ $row['id'] }}"
                                                    {{ $row['id'] == $user['group_id'] ? 'selected' : '' }}>
                                                    {{ $row['nama'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('group'))
                                            <div class="invalid-feedback">{{ $errors->first('group') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Cabang</td>
                                    <td>
                                        <select class="select2 form-control" name="raw_branch_id">
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
                                    <td></td>
                                    <td>
                                        <button class="st-btn-brand" type="submit">
                                            <i class="fa fa-save"></i> Simpan
                                        </button>
                                        <button type="button" class="st-btn-outline-brown" onclick="deleteData('{{ $user->id }}')">
                                            <i class="fa fa-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            </table>
                        </form>
                        <form action="{{ route('setusermanagement.destroy', $user->id) }}" method="POST" id="delete">
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
