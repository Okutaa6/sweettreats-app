@extends('layouts.app')

@section('content')
@php
    $totalAll = array_sum($summary ?? []);
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">EP Bulanan / Mingguan</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:12px;">
        <div class="text-muted" style="font-size:13px;">
            Total: <strong>{{ $totalAll }}</strong> konten
            @foreach ($statuses as $key => $opt)
                <span class="ml-2">·</span>
                <span class="ml-1 st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}: {{ $summary[$key] ?? 0 }}</span>
            @endforeach
        </div>
        <a href="{{ route('epkonten.new') }}" class="st-btn-brand">
            <i class="fa fa-plus"></i> Tambah Konten
        </a>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    {{-- Quick periode chips --}}
                    <div class="d-flex align-items-center flex-wrap mb-3" style="gap:8px;">
                        <span class="text-muted" style="font-size:13px; font-weight:600;">
                            <i class="fa fa-bolt"></i> Lihat konten:
                        </span>
                        @php
                            $chips = [
                                ''           => 'Semua',
                                'today'      => 'Hari Ini',
                                'this_week'  => 'Minggu Ini',
                                'this_month' => 'Bulan Ini',
                                'next_month' => 'Bulan Depan',
                            ];
                            $qs = function ($overrides = []) {
                                return http_build_query(array_filter(array_merge(request()->all(), $overrides), fn($v) => $v !== null && $v !== ''));
                            };
                        @endphp
                        @foreach ($chips as $key => $label)
                            @php $isActive = ($filterPeriode ?? '') === $key && !$filterDateFrom && !$filterDateTo; @endphp
                            <a href="{{ route('epkonten') }}?{{ $qs(['periode' => $key, 'date_from' => null, 'date_to' => null]) }}"
                               class="st-pill st-pill--{{ $isActive ? 'success' : 'neutral' }}"
                               style="cursor:pointer; text-decoration:none;">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>

                    {{-- Filter form --}}
                    <form method="GET" class="row g-2">
                        <div class="col-md-3">
                            <label class="text-muted" style="font-size:12px;">Cari Judul</label>
                            <input type="text" name="search" class="form-control" placeholder="Judul konten..." value="{{ $filterSearch }}">
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted" style="font-size:12px;">Status</label>
                            <select name="status" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($statuses as $key => $opt)
                                    <option value="{{ $key }}" {{ $filterStatus == $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted" style="font-size:12px;">Pillar</label>
                            <select name="pillar_id" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($pillars as $p)
                                    <option value="{{ $p->id }}" {{ $filterPillar == $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted" style="font-size:12px;">Platform</label>
                            <select name="platform" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($platforms as $key => $label)
                                    <option value="{{ $key }}" {{ $filterPlatform == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted" style="font-size:12px;">Tipe</label>
                            <select name="tipe_konten" class="form-control">
                                <option value="">- Semua -</option>
                                @foreach ($tipes as $key => $label)
                                    <option value="{{ $key }}" {{ $filterTipe == $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end" style="gap:4px;">
                            <button type="submit" class="st-btn-brand" title="Filter"><i class="fa fa-filter"></i></button>
                            <a href="{{ route('epkonten') }}" class="st-btn-outline-brown" title="Reset"><i class="fa fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="st-card">
                <div class="st-card__body">
                    <div class="st-table-wrap--scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Tanggal</th>
                                    <th>Judul</th>
                                    <th>Platform</th>
                                    <th>Tipe</th>
                                    <th>Pillar</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th width="60">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($kontens as $key => $row)
                                    @php
                                        $statusOpt = $statuses[$row->status] ?? ['label' => $row->status, 'color' => 'neutral'];
                                        $prioOpt   = $prios[$row->prioritas] ?? ['label' => $row->prioritas, 'color' => 'neutral'];
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $kontens->firstItem() + $key }}</td>
                                        <td>
                                            @if($row->tanggal_upload)
                                                <span class="st-date">{{ $row->tanggal_upload->format('d M Y') }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                            @if($row->waktu_pengerjaan)
                                                <br><small class="text-muted"><i class="fa fa-clock"></i> {{ $row->waktu_pengerjaan }}</small>
                                            @endif
                                        </td>
                                        <td class="st-primary">
                                            <a href="{{ route('epkonten.edit', $row->id) }}" style="color:inherit; text-decoration:none;">
                                                {{ $row->judul }}
                                            </a>
                                            @if($row->framework)
                                                <br><small class="text-muted">Framework: {{ $row->framework }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($row->platforms))
                                                @foreach ($row->platforms as $plat)
                                                    <span class="st-pill st-pill--info st-pill--plain" style="font-size:10px;">{{ $platforms[$plat] ?? $plat }}</span>
                                                @endforeach
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->tipe_konten)
                                                {{ $tipes[$row->tipe_konten] ?? $row->tipe_konten }}
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->pillars->isNotEmpty())
                                                @foreach ($row->pillars->take(3) as $p)
                                                    <span class="st-pill st-pill--plain" style="font-size:10px; background:{{ $p->color ?? '#e6f5f4' }}; color:#0a4f4d;">{{ $p->nama }}</span>
                                                @endforeach
                                                @if($row->pillars->count() > 3)
                                                    <small class="text-muted">+{{ $row->pillars->count() - 3 }}</small>
                                                @endif
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="st-pill st-pill--{{ $prioOpt['color'] }}" style="font-size:10px;">{{ $prioOpt['label'] }}</span>
                                        </td>
                                        <td>
                                            <select class="st-status-select" data-id="{{ $row->id }}"
                                                    data-original="{{ $row->status }}" data-status="{{ $row->status }}"
                                                    onchange="changeKontenStatus(this)">
                                                @foreach ($statuses as $key => $opt)
                                                    <option value="{{ $key }}" {{ $row->status === $key ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <a href="{{ route('epkonten.edit', $row->id) }}" class="st-btn-outline-brown btn-sm" title="Edit">
                                                <i class="fa fa-pen"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($kontens->isEmpty())
                                    <tr><td colspan="9" class="text-center text-muted py-4">
                                        Belum ada konten EP.
                                        <br><a href="{{ route('epkonten.new') }}" class="text-primary">+ Tambah konten pertama</a>
                                    </td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    {{ $kontens->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    var kontenStatusOptions = @json(\App\Models\EpKonten::statusOptions());

    function changeKontenStatus(el) {
        var id         = el.dataset.id;
        var newStatus  = el.value;
        var origStatus = el.dataset.original;
        if (newStatus === origStatus) return;

        Swal.fire({
            title: 'Ubah status konten?',
            text: 'Status akan diubah ke "' + (kontenStatusOptions[newStatus]?.label ?? newStatus) + '"',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0e6e6b',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, ubah',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (!result.value) {
                el.value = origStatus;
                return;
            }
            el.disabled = true;
            $.ajax({
                url: '/ep-konten/' + id + '/status',
                method: 'PATCH',
                data: { _token: '{{ csrf_token() }}', status: newStatus },
                success: function (res) {
                    el.dataset.original = res.status;
                    el.dataset.status   = res.status;
                    el.disabled = false;
                    Swal.fire({ title: 'Berhasil', text: 'Status diubah ke "' + res.label + '"', type: 'success', timer: 1500, showConfirmButton: false });
                },
                error: function (xhr) {
                    el.value = origStatus;
                    el.disabled = false;
                    Swal.fire({ title: 'Gagal', text: xhr.responseJSON?.message || 'Terjadi kesalahan', type: 'error' });
                }
            });
        });
    }
</script>
@endsection
