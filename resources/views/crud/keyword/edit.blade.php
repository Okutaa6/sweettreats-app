@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Edit Keyword</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-8">
            <div class="st-card">
                <div class="st-card__body">
                    <form method="POST" action="{{ route('rawkeyword.update', $keyword->id) }}">
                        @csrf
                        @method('PUT')

                        <a href="{{ route('rawkeyword') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="st-table">
                            <tr>
                                <td width="30%" style="font-weight:600;">Keyword <span class="text-danger">*</span></td>
                                <td>
                                    <input class="form-control" type="text" name="keyword"
                                        value="{{ old('keyword', $keyword->keyword) }}" required />
                                    <small class="text-muted">Pengecekan tidak peduli huruf besar/kecil</small>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Deskripsi</td>
                                <td>
                                    <textarea class="form-control" name="description" rows="3">{{ old('description', $keyword->description) }}</textarea>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight:600;">Berlaku Sampai</td>
                                <td>
                                    <input class="form-control" type="date" name="end_date"
                                        value="{{ old('end_date', $keyword->end_date ? $keyword->end_date->format('Y-m-d') : '') }}" />
                                    <small class="text-muted">Kosongkan jika keyword aktif selamanya</small>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="st-btn-brand" type="submit">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                    <button type="button" class="st-btn-outline-brown" onclick="deleteKeyword()">
                                        <i class="fa fa-trash"></i> Hapus
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </form>
                    <form action="{{ route('rawkeyword.destroy', $keyword->id) }}" method="POST" id="deleteForm">
                        @csrf @method('DELETE')
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    function deleteKeyword() {
        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Keyword yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#deleteForm').submit(); }
        })
    }
</script>
@endsection
