@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Detail Branch</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- Form Info Cabang --}}
    <div class="row">
        <div class="col-md-12">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    <h6 class="st-section-title"><i class="fa fa-building"></i> Informasi Cabang</h6>
                    <form method="post" action="{{ route('rawbranch.update', $branch->id) }}">
                        @csrf
                        @method('PUT')

                        <a href="{{ route('rawbranch') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <table class="st-table">
                            <tr>
                                <td width="25%" style="font-weight:600;">Nama Cabang <span class="text-danger">*</span></td>
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
                                <td></td>
                                <td>
                                    <button class="st-btn-brand" type="submit">
                                        <i class="fa fa-save"></i> Simpan
                                    </button>
                                    <button type="button" class="st-btn-outline-brown" onclick="deleteData()">
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

    {{-- Target Bulanan --}}
    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__head">
                    <h6 class="st-section-title mb-0"><i class="fa fa-bullseye"></i> Target Bulanan</h6>
                    <form method="GET" action="{{ route('rawbranch.edit', $branch->id) }}"
                        class="d-flex align-items-center" style="gap:8px;">
                        <label class="mb-0" style="font-weight:700; font-size:13px; white-space:nowrap; color:var(--text-subtle)">Tahun:</label>
                        <select name="tahun" class="form-control form-control-sm" style="width:120px;"
                            onchange="this.form.submit()">
                            @foreach($tahunList as $t)
                                <option value="{{ $t }}" {{ $tahun == $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
                <div class="st-card__body">
                    <form method="POST" action="{{ route('rawbranch.target', $branch->id) }}">
                        @csrf
                        <input type="hidden" name="tahun" value="{{ $tahun }}">

                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="30%">Bulan</th>
                                    <th>Target (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(\App\Models\RawBranchTarget::namaBulan() as $bulan => $namaBulan)
                                    @php
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
                                                <input type="number" class="form-control"
                                                    name="nominal[{{ $bulan }}]"
                                                    value="{{ $nilaiTarget }}" min="0" step="1"
                                                    placeholder="Kosongkan jika belum ada target">
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr style="background:var(--cream-header);">
                                    <td><strong>Total Target {{ $tahun }}</strong></td>
                                    <td><strong>Rp {{ number_format($targets->sum('nominal'), 0, ',', '.') }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>

                        <button type="submit" class="st-btn-brand mt-3">
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
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) { $('#delete').submit(); }
        })
    }
</script>
@endsection
