@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Semua Reservasi</h4>
                </div>
            </div>
        </div>

        {{-- Filter Bar --}}
        <form method="GET" action="{{ route('dashboard.reservasi') }}">
            <div class="st-card mb-3" style="background:var(--cream-surface);">
                <div class="st-card__body d-flex flex-wrap align-items-end" style="gap:12px; padding:16px 20px;">
                    <div>
                        <label class="small mb-1">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control form-control-sm"
                            value="{{ request('date_from') }}">
                    </div>
                    <div>
                        <label class="small mb-1">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control form-control-sm"
                            value="{{ request('date_to') }}">
                    </div>
                    <div>
                        <label class="small mb-1">Status</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">Semua</option>
                            @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                                    {{ $opt['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="small mb-1">Dokter</label>
                        <select name="doctor" class="form-control form-control-sm">
                            <option value="">Semua</option>
                            @foreach ($doctors as $doc)
                                <option value="{{ $doc->id }}" {{ request('doctor') == $doc->id ? 'selected' : '' }}>
                                    {{ $doc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="small mb-1">Cabang</label>
                        <select name="branch" class="form-control form-control-sm">
                            <option value="">Semua</option>
                            @foreach ($branches as $br)
                                <option value="{{ $br->id }}" {{ request('branch') == $br->id ? 'selected' : '' }}>
                                    {{ $br->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex" style="gap:4px;">
                        <button type="submit" class="st-btn-brand"><i class="fa fa-search"></i> Filter</button>
                        <a href="{{ route('dashboard.reservasi') }}" class="st-btn-outline-brown">Reset</a>
                    </div>
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="st-card">
            <div class="st-card__body">
                <div class="st-table-wrap--scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th width="4%">No</th>
                                <th>Pasien</th>
                                <th>Dokter</th>
                                <th>Cabang</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reservations as $i => $row)
                                @php $si = $row->status_info; @endphp
                                <tr id="row-{{ $row->id }}">
                                    <td class="st-num">{{ $reservations->firstItem() + $i }}</td>
                                    <td>{{ $row->customer->name ?? '-' }}</td>
                                    <td>{{ $row->doctor->name ?? '-' }}</td>
                                    <td>{{ $row->branch->name ?? '-' }}</td>
                                    <td><span class="st-date">{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</span></td>
                                    <td class="st-tnum">{{ $row->start_hour }} - {{ $row->end_hour }}</td>
                                    <td>
                                        <select class="inline-update" id="status-{{ $row->id }}"
                                            data-id="{{ $row->id }}" data-original="{{ $row->status }}"
                                            data-field="status" data-value="{{ $row->status }}"
                                            onchange="changeStatus(this)">
                                            @foreach (\App\Models\RawCusReservation::statusOptions() as $key => $opt)
                                                <option value="{{ $key }}"
                                                    {{ $row->status === $key ? 'selected' : '' }}>
                                                    {{ $opt['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="st-muted">
                                            <i class="fa fa-inbox fa-2x mb-2 d-block"></i>
                                            Tidak ada data reservasi
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $reservations->links() }}</div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        var statusColors = @json(\App\Models\RawCusReservation::statusOptions());

        function changeStatus(el) {
            var id = $(el).data('id');
            var newStatus = $(el).val();
            var originalStatus = $(el).data('original');

            if (newStatus === originalStatus) return;

            Swal.fire({
                title: 'Ubah Status?',
                text: 'Status akan diubah ke "' + statusColors[newStatus].label + '"',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#461500',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Ya, ubah!',
                cancelButtonText: 'Batal'
            }).then(function(result) {
                if (result.value) {
                    $(el).prop('disabled', true);
                    $.ajax({
                        url: '/dashboard/reservasi/' + id + '/status',
                        method: 'PATCH',
                        data: { _token: '{{ csrf_token() }}', status: newStatus },
                        success: function(res) {
                            $(el).data('original', res.status).data('value', res.status).attr('data-value', res.status);
                            $(el).prop('disabled', false);
                            Swal.fire({ title: 'Berhasil!', text: 'Status berhasil diubah', type: 'success', timer: 2000, showConfirmButton: false });
                        },
                        error: function(xhr) {
                            $(el).val(originalStatus).prop('disabled', false);
                            Swal.fire({ title: 'Gagal!', text: xhr.responseJSON?.error || 'Terjadi kesalahan', type: 'error' });
                        }
                    });
                } else {
                    $(el).val(originalStatus);
                }
            });
        }
    </script>
@endsection
