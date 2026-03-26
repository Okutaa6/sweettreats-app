@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <div class="float-right">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a>CRUD</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('rawdocservice') }}">Doctor Service</a></li>
                            <li class="breadcrumb-item active">Detail</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Detail Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Info Layanan Utama --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Layanan Utama</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <form method="post" action="{{ route('rawdocservice.update', $service->id) }}">
                                @csrf
                                @method('PUT')

                                <a href="{{ route('rawdocservice') }}" class="btn btn-sm btn-warning"
                                    style="margin-bottom: 20px;"><i class="fa fa-arrow-left"></i> Kembali</a>

                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <tr>
                                        <td width="20%">Nama Layanan</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                                type="text" name="name" value="{{ old('name', $service->name) }}" />
                                            @if ($errors->has('name'))
                                                <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Durasi Default (Menit)</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('duration_minutes') ? 'is-invalid' : '' }}"
                                                type="number" name="duration_minutes" min="1"
                                                value="{{ old('duration_minutes', $service->duration_minutes) }}" />
                                            <small class="text-muted">Dipakai kalau sub-detail belum dipilih admin</small>
                                            @if ($errors->has('duration_minutes'))
                                                <div class="invalid-feedback">{{ $errors->first('duration_minutes') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Harga Default (Rp)</td>
                                        <td>
                                            <input class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                                type="number" name="price" min="0"
                                                value="{{ old('price', $service->price) }}" />
                                            <small class="text-muted">Dipakai kalau sub-detail belum dipilih admin</small>
                                            @if ($errors->has('price'))
                                                <div class="invalid-feedback">{{ $errors->first('price') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>&nbsp;</td>
                                        <td>
                                            <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Simpan</button>
                                            <button type="button" class="btn btn-outline-danger" onclick="deleteData()"><i class="fa fa-trash"></i> Hapus</button>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                            <form action="{{ route('rawdocservice.destroy', $service->id) }}" method="POST" id="delete">
                                @csrf
                                @method('DELETE')
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sub-detail Perawatan --}}
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h4 class="card-title mb-0">Sub-detail Perawatan</h4>
                        <small class="text-muted">Admin pilih sub-detail ini saat konfirmasi reservasi</small>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-sm btn-primary mb-3" data-toggle="modal" data-target="#addDetailModal">
                            <i class="fa fa-plus"></i> Tambah Sub-detail
                        </button>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th>Nama Sub-detail</th>
                                        <th width="15%">Kode Service</th>
                                        <th width="15%">Durasi (Menit)</th>
                                        <th width="18%">Harga</th>
                                        <th width="8%">Urutan</th>
                                        <th width="8%">Status</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($service->allDetails as $key => $detail)
                                        <tr class="{{ $detail->is_active ? '' : 'text-muted' }}">
                                            <td>{{ $key + 1 }}</td>
                                            <td>
                                                {{ $detail->name }}
                                                @if (!$detail->is_active)
                                                    <span class="badge badge-secondary ml-1">Nonaktif</span>
                                                @endif
                                            </td>
                                            <td>{{ $detail->code_service ?? '-' }}</td>  
                                            <td>{{ $detail->duration_minutes }} menit</td>
                                            <td>Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                                            <td>{{ $detail->sort_order }}</td>
                                            <td>
                                                <span class="badge badge-{{ $detail->is_active ? 'success' : 'secondary' }}">
                                                    {{ $detail->is_active ? 'Aktif' : 'Nonaktif' }}
                                                </span>
                                            </td>
                                            <td nowrap>
                                                <button class="btn btn-info btn-sm btn-edit-detail"
                                                    style="padding:2px 8px"
                                                    data-id="{{ $detail->id }}"
                                                    data-name="{{ $detail->name }}"
                                                    data-code="{{ $detail->code_service }}"  
                                                    data-duration="{{ $detail->duration_minutes }}"
                                                    data-price="{{ $detail->price }}"
                                                    data-sort="{{ $detail->sort_order }}"
                                                    data-active="{{ $detail->is_active ? 1 : 0 }}"
                                                    title="Edit">
                                                    <i class="fa fa-pencil-alt"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" style="padding:2px 8px"
                                                    onclick="deleteDetail('{{ $detail->id }}')" title="Hapus">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                                <form action="{{ route('rawdocservice.detail.destroy', [$service->id, $detail->id]) }}"
                                                    method="POST" id="deleteDetail{{ $detail->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted"> {{-- ← colspan 8 karena ada kolom baru --}}
                                                Belum ada sub-detail. Klik "Tambah Sub-detail" untuk mulai.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Detail Modal --}}
    <div class="modal fade" id="addDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('rawdocservice.detail.store', $service->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Sub-detail — {{ $service->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama Sub-detail <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name"
                                placeholder="Contoh: Scaling Kelas 2" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kode Service</label>
                            <input type="text" class="form-control" name="code_service"
                                placeholder="Contoh: SC-001">
                            <small class="text-muted">Opsional — kode unik untuk identifikasi sub service</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Durasi (Menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_minutes" min="1" value="30" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Urutan Tampil</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="0">
                            <small class="text-muted">Angka kecil tampil lebih dulu</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Detail Modal --}}
    <div class="modal fade" id="editDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editDetailForm" action="">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Sub-detail — {{ $service->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama Sub-detail <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kode Service</label>
                            <input type="text" class="form-control" name="code_service" id="editCodeService"
                                placeholder="Contoh: SC-001">
                            <small class="text-muted">Opsional — kode unik untuk identifikasi sub service</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Durasi (Menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_minutes" id="editDuration" min="1" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" id="editPrice" min="0" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Urutan Tampil</label>
                                <input type="number" class="form-control" name="sort_order" id="editSort" min="0">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Status</label>
                                <select class="form-control" name="is_active" id="editActive">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan</button>
                    </div>
                </form>
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
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) { $('#delete').submit(); }
            })
        }

        function deleteDetail(id) {
            Swal.fire({
                title: 'Hapus Sub-detail?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) { $('#deleteDetail' + id).submit(); }
            })
        }

        // pas klik tombol edit detail → isi form modal dari data-attribute
        $(document).on('click', '.btn-edit-detail', function() {
            const id      = $(this).data('id');
            const baseUrl = '{{ route("rawdocservice.detail.update", [$service->id, "__ID__"]) }}';

            $('#editName').val($(this).data('name'));
            $('#editCodeService').val($(this).data('code'));  //new
            $('#editDuration').val($(this).data('duration'));
            $('#editPrice').val($(this).data('price'));
            $('#editSort').val($(this).data('sort'));
            $('#editActive').val($(this).data('active'));
            $('#editDetailForm').attr('action', baseUrl.replace('__ID__', id));

            $('#editDetailModal').modal('show');
        });
    </script>
@endsection