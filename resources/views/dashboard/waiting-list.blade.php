@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">
                        <i class="fa fa-list-ol mr-2" style="color:#7C3AED"></i>
                        Waiting List Hari Ini
                    </h4>
                </div>
            </div>
        </div>

        <div class="st-card">
            <div class="st-card__body">
                <div class="st-table-wrap--scroll">
                    <table class="st-table">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Pasien</th>
                                <th>Dokter</th>
                                <th>Cabang</th>
                                <th>Layanan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($reservations as $res)
                                @php $si = $res->status_info; @endphp
                                <tr id="row-{{ $res->id }}">
                                    <td class="st-tnum"><strong>{{ $res->start_hour }} - {{ $res->end_hour }}</strong></td>
                                    <td>{{ $res->customer->name ?? '-' }}</td>
                                    <td>{{ $res->doctor->name ?? '-' }}</td>
                                    <td>{{ $res->branch->name ?? '-' }}</td>
                                    <td>
                                        @foreach ($res->services as $svc)
                                            <span class="st-pill st-pill--plain st-pill--light" style="font-size:0.72rem;margin:1px;">{{ $svc->docService->name ?? '-' }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="st-pill st-pill--neutral" id="badge-{{ $res->id }}">
                                            <i class="fa {{ $si['icon'] }}"></i> {{ $si['label'] }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex" style="gap:4px;">
                                            <button class="st-quick-btn st-quick-btn--info"
                                                onclick="quickStatus('{{ $res->id }}','confirmed')">
                                                <i class="fa fa-check"></i> Konfirmasi
                                            </button>
                                            <button class="st-quick-btn st-quick-btn--danger"
                                                onclick="quickStatus('{{ $res->id }}','cancelled')">
                                                <i class="fa fa-times"></i> Batal
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="st-muted">
                                            <i class="fa fa-check-circle fa-2x mb-2 d-block" style="color:#7C3AED"></i>
                                            Tidak ada waiting list hari ini
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        var PILL_MAP = {pending:'st-pill--warning',confirmed:'st-pill--info',completed:'st-pill--success',cancelled:'st-pill--danger',waitlist:'st-pill--neutral'};
        function quickStatus(id, status) {
            $.ajax({
                url: '/dashboard/reservasi/' + id + '/status',
                method: 'PATCH',
                data: { status: status, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    $('#badge-' + id).attr('class', 'st-pill ' + (PILL_MAP[status] || 'st-pill--neutral'))
                        .html('<i class="fa ' + res.icon + '"></i> ' + res.label);
                    $('#row-' + id + ' td:last').html('<span class="st-muted"><i class="fa fa-check-circle"></i> Updated</span>');
                    if (res.waitlist_prompt) { showWaitlistPrompt(res.waitlist_prompt); }
                },
                error: function() { alert('Gagal mengubah status'); }
            });
        }

        // Slot kosong akibat cancel → tawarkan assign pasien waiting list.
        function showWaitlistPrompt(p) {
            var msg = 'Slot dr. ' + p.doctor + ' tanggal ' + p.date + ' kini kosong. Ada '
                    + p.count + ' pasien di waiting list. Assign sekarang?';
            if (window.Swal) {
                Swal.fire({
                    title: 'Slot kosong — ada waiting list!',
                    text: msg, icon: 'info',
                    showCancelButton: true, confirmButtonColor: '#461500', cancelButtonColor: '#aaa',
                    confirmButtonText: 'Lihat Waiting List', cancelButtonText: 'Nanti', reverseButtons: true,
                }).then(function (r) { if (r.isConfirmed) window.location.href = p.url; });
            } else if (confirm(msg)) {
                window.location.href = p.url;
            }
        }
    </script>
@endsection
