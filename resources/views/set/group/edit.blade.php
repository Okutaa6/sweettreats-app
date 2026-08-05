@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Ubah Role</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="row">
            <div class="col-md-8">
                <div class="st-card">
                    <div class="st-card__body">
                        <form method="post" action="{{ route('setgroup.update', $group->id) }}">
                            @csrf
                            @method('PUT')
                            <a href="{{ route('setgroup') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>

                            <table class="st-table">
                                <tr>
                                    <td width="20%" style="font-weight:600;">Nama</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('nama') ? 'is-invalid' : '' }}"
                                            type="text" name="nama" value="{{ old('nama', $group->nama) }}" required />
                                        @if ($errors->has('nama'))
                                            <div class="invalid-feedback">{{ $errors->first('nama') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Deskripsi</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('deskripsi') ? 'is-invalid' : '' }}"
                                            type="text" name="deskripsi"
                                            value="{{ old('deskripsi', $group->deskripsi) }}" required />
                                        @if ($errors->has('deskripsi'))
                                            <div class="invalid-feedback">{{ $errors->first('deskripsi') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Landing Page</td>
                                    <td>
                                        <input class="form-control {{ $errors->has('landing_page') ? 'is-invalid' : '' }}"
                                            type="text" name="landing_page"
                                            value="{{ old('landing_page', $group->landing_page) }}" required />
                                        @if ($errors->has('landing_page'))
                                            <div class="invalid-feedback">{{ $errors->first('landing_page') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Atasan</td>
                                    <td>
                                        <select class="form-control" name="parent_id">
                                            <option value="">— Tidak ada (puncak) —</option>
                                            @foreach ($parents as $p)
                                                <option value="{{ $p->id }}" {{ old('parent_id', $group->parent_id) == $p->id ? 'selected' : '' }}>
                                                    {{ str_repeat('— ', $p->level) }}{{ $p->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small style="color:var(--text-subtle)">Diri sendiri &amp; bawahan tidak muncul (cegah siklus).</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Atasan tambahan</td>
                                    <td>
                                        <select class="form-control {{ $errors->has('parent_id_2') ? 'is-invalid' : '' }}" name="parent_id_2">
                                            <option value="">— Tidak ada —</option>
                                            @foreach ($parents as $p)
                                                <option value="{{ $p->id }}" {{ old('parent_id_2', $group->parent_id_2) == $p->id ? 'selected' : '' }}>
                                                    {{ str_repeat('— ', $p->level) }}{{ $p->nama }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('parent_id_2'))
                                            <div class="invalid-feedback">{{ $errors->first('parent_id_2') }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td>
                                        <button class="st-btn-brand" type="submit">
                                            <i class="fa fa-save"></i> Simpan
                                        </button>
                                        <button type="button" class="st-btn-outline-brown" onclick="deleteData('{{ $group->id }}')">
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
