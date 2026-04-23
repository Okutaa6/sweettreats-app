@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Detail Doctor Service</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        {{-- Info Layanan Utama --}}
        <div class="st-card mb-3">
            <div class="st-card__body">
                <h6 class="st-section-title"><i class="fa fa-stethoscope"></i> Layanan Utama</h6>
                <form method="post" action="{{ route('rawdocservice.update', $service->id) }}">
                    @csrf
                    @method('PUT')

                    <a href="{{ route('rawdocservice') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                        <i class="fa fa-arrow-left"></i> Kembali
                    </a>

                    <table class="st-table">
                        <tr>
                            <td width="20%" style="font-weight:600;">Nama Layanan <span class="text-danger">*</span></td>
                            <td>
                                <input class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                    type="text" name="name" value="{{ old('name', $service->name) }}" />
                                @if ($errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Kategori <span class="text-danger">*</span></td>
                            <td>
                                <select class="form-control {{ $errors->has('category') ? 'is-invalid' : '' }}" name="category">
                                    <option value="">-- Pilih Kategori --</option>
                                    @foreach ($categories as $key => $label)
                                        <option value="{{ $key }}" {{ old('category', $service->category) === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Menentukan di grup mana layanan ini muncul di form reservasi pasien</small>
                                @if ($errors->has('category'))
                                    <div class="invalid-feedback">{{ $errors->first('category') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Urutan Tampil</td>
                            <td>
                                <input class="form-control {{ $errors->has('sort_order') ? 'is-invalid' : '' }}"
                                    type="number" name="sort_order" min="0" value="{{ old('sort_order', $service->sort_order) }}" />
                                <small class="text-muted">Angka kecil tampil lebih dulu dalam kategorinya</small>
                                @if ($errors->has('sort_order'))
                                    <div class="invalid-feedback">{{ $errors->first('sort_order') }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight:600;">Durasi Default (Menit) <span class="text-danger">*</span></td>
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
                            <td style="font-weight:600;">Harga Default (Rp)</td>
                            <td>
                                <input class="form-control price-input {{ $errors->has('price') ? 'is-invalid' : '' }}"
                                    type="text" inputmode="numeric" name="price"
                                    value="{{ old('price', $service->price) }}" />
                                <small class="text-muted">Dipakai kalau sub-detail belum dipilih admin</small>
                                @if ($errors->has('price'))
                                    <div class="invalid-feedback">{{ $errors->first('price') }}</div>
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
                <form action="{{ route('rawdocservice.destroy', $service->id) }}" method="POST" id="delete">
                    @csrf @method('DELETE')
                </form>
            </div>
        </div>

        {{-- Sub-detail Perawatan --}}
        <div class="st-card">
            <div class="st-card__head">
                <h6 class="st-section-title mb-0"><i class="fa fa-list-ul"></i> Sub-detail Perawatan</h6>
                <small class="text-muted">Admin pilih sub-detail ini saat konfirmasi reservasi</small>
            </div>
            <div class="st-card__body">
                <button class="st-btn-brand mb-3" data-toggle="modal" data-target="#addDetailModal">
                    <i class="fa fa-plus"></i> Tambah Sub-detail
                </button>

                <div class="st-table-wrap--scroll">
                    <table class="st-table st-table--compact">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Sub-detail</th>
                                <th width="12%">Kode Service</th>
                                <th width="12%">Durasi</th>
                                <th width="15%">Harga</th>
                                <th width="8%">Urutan</th>
                                <th width="8%">Status</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($service->allDetails as $key => $detail)
                                <tr class="{{ $detail->is_active ? '' : 'text-muted' }}">
                                    <td class="st-num">{{ $key + 1 }}</td>
                                    <td>
                                        {{ $detail->name }}
                                        @if (!$detail->is_active)
                                            <span class="st-pill st-pill--neutral" style="font-size:0.7rem">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>{{ $detail->code_service ?? '-' }}</td>
                                    <td>{{ $detail->duration_minutes }} menit</td>
                                    <td class="st-tnum">Rp {{ number_format($detail->price, 0, ',', '.') }}</td>
                                    <td class="st-num">{{ $detail->sort_order }}</td>
                                    <td>
                                        <span class="st-pill st-pill--{{ $detail->is_active ? 'success' : 'neutral' }}">
                                            {{ $detail->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td nowrap>
                                        <div class="d-flex" style="gap:4px;">
                                            <button class="st-icon-btn btn-edit-detail"
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
                                            <button class="st-icon-btn" onclick="deleteDetail('{{ $detail->id }}')" title="Hapus">
                                                <i class="fa fa-trash" style="color:#B23A3A"></i>
                                            </button>
                                            <form action="{{ route('rawdocservice.detail.destroy', [$service->id, $detail->id]) }}"
                                                method="POST" id="deleteDetail{{ $detail->id }}">
                                                @csrf @method('DELETE')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center st-muted">
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
                            <label style="font-weight:600;">Nama Sub-detail <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" placeholder="Contoh: Scaling Kelas 2" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Kode Service</label>
                            <input type="text" class="form-control" name="code_service" placeholder="Contoh: SC-001">
                            <small class="text-muted">Opsional — kode unik untuk identifikasi sub service</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Durasi (Menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_minutes" min="1" value="30" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="text" inputmode="numeric" class="form-control price-input" name="price" value="0" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Urutan Tampil</label>
                            <input type="number" class="form-control" name="sort_order" min="0" value="0">
                            <small class="text-muted">Angka kecil tampil lebih dulu</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                        <button type="submit" class="st-btn-brand"><i class="fa fa-save"></i> Simpan</button>
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
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Sub-detail — {{ $service->name }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label style="font-weight:600;">Nama Sub-detail <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="editName" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Kode Service</label>
                            <input type="text" class="form-control" name="code_service" id="editCodeService" placeholder="Contoh: SC-001">
                            <small class="text-muted">Opsional</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Durasi (Menit) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_minutes" id="editDuration" min="1" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Harga (Rp) <span class="text-danger">*</span></label>
                                <input type="text" inputmode="numeric" class="form-control price-input" name="price" id="editPrice" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Urutan Tampil</label>
                                <input type="number" class="form-control" name="sort_order" id="editSort" min="0">
                            </div>
                            <div class="form-group col-md-6">
                                <label style="font-weight:600;">Status</label>
                                <select class="form-control" name="is_active" id="editActive">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                        <button type="submit" class="st-btn-brand"><i class="fa fa-save"></i> Simpan</button>
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
            title: 'Apakah Anda Yakin?', text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#delete').submit(); } })
    }

    function deleteDetail(id) {
        Swal.fire({
            title: 'Hapus Sub-detail?', text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning', showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal',
        }).then((result) => { if (result.isConfirmed) { $('#deleteDetail' + id).submit(); } })
    }

    $(document).on('click', '.btn-edit-detail', function () {
        const id = $(this).data('id');
        const baseUrl = '{{ route("rawdocservice.detail.update", [$service->id, "__ID__"]) }}';
        $('#editName').val($(this).data('name'));
        $('#editCodeService').val($(this).data('code'));
        $('#editDuration').val($(this).data('duration'));
        $('#editPrice').val($(this).data('price'));
        $('#editSort').val($(this).data('sort'));
        $('#editActive').val($(this).data('active'));
        $('#editDetailForm').attr('action', baseUrl.replace('__ID__', id));
        $('#editDetailModal').modal('show');
    });

    function formatNumber(n) { if (typeof n !== 'string') n = String(n); return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, "."); }
    function unformatNumber(n) { return String(n).replace(/\./g, ""); }
    $('.price-input').each(function() { $(this).val(formatNumber($(this).val())); });
    $(document).on('input', '.price-input', function(e){ $(this).val(formatNumber($(this).val())); });
    $('form').on('submit', function() { $(this).find('.price-input').each(function() { $(this).val(unformatNumber($(this).val())); }); });
</script>
@endsection
