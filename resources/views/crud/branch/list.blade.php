@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Branch</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">
                    <a href="{{ route('rawbranch.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                        <i class="fa fa-plus"></i> Tambah
                    </a>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Nama Cabang</th>
                                    <th width="28%">Link Pasien Baru</th>
                                    <th width="15%">Jumlah Jadwal</th>
                                    <th width="15%">Jumlah Reservasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($branches as $key => $row)
                                    <tr data-href="{{ route('rawbranch.edit', $row->id) }}">
                                        <td class="st-num">{{ $branches->firstItem() + $key }}</td>
                                        <td class="st-primary">{{ $row->name }}</td>
                                        <td>
                                            @if ($row->slug)
                                                <code style="font-size:12px;">/pasien-baru/{{ $row->slug }}</code>
                                                <button type="button" class="br-copy-btn"
                                                    data-copy="{{ route('pasien-baru.show', $row->slug) }}"
                                                    title="Salin link lengkap">
                                                    <i class="fa fa-copy"></i>
                                                </button>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="st-tnum">{{ $row->doctor_schedules_count }}</td>
                                        <td class="st-tnum">{{ $row->reservations_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2">{{ $branches->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .br-copy-btn {
        border: 1px solid var(--border, #e3d9d2);
        background: #fff;
        border-radius: 6px;
        padding: 2px 7px;
        margin-left: 6px;
        font-size: 11px;
        color: #461500;
        cursor: pointer;
    }
    .br-copy-btn:hover { background: #f7f1ec; }
</style>
@endsection

@section('js')
<script>
    // Salin link form Pasien Baru. Baris tabel clickable → jangan biarkan
    // klik tombol ikut membuka halaman edit cabang.
    document.querySelectorAll('.br-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var text = btn.dataset.copy || '';
            if (!text) return;
            var done = function () {
                var original = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-check"></i>';
                setTimeout(function () { btn.innerHTML = original; }, 1200);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (_) {}
                document.body.removeChild(ta);
            }
        });
    });
</script>
@endsection
