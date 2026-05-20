@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Konten Pillar</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:12px;">
        <div class="text-muted" style="font-size:13px;">
            <span class="ml-1 st-pill st-pill--success">Aktif: {{ $totalActive }}</span>
            <span class="ml-2">·</span>
            <span class="ml-2 st-pill st-pill--neutral">Nonaktif: {{ $totalInactive }}</span>
        </div>
        <a href="{{ route('kontenpillar.new') }}" class="st-btn-brand">
            <i class="fa fa-plus"></i> Tambah Pillar
        </a>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="st-card mb-3">
                <div class="st-card__body">
                    <form method="GET" class="row g-2">
                        <div class="col-md-6">
                            <label class="text-muted" style="font-size:12px;">Cari</label>
                            <input type="text" name="search" class="form-control" placeholder="Nama / slug pillar..." value="{{ $search }}">
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted" style="font-size:12px;">Status</label>
                            <select name="status" class="form-control">
                                <option value="all"      {{ $filterStatus === 'all' ? 'selected' : '' }}>Semua</option>
                                <option value="active"   {{ $filterStatus === 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="inactive" {{ $filterStatus === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end" style="gap:4px;">
                            <button type="submit" class="st-btn-brand"><i class="fa fa-filter"></i> Filter</button>
                            <a href="{{ route('kontenpillar') }}" class="st-btn-outline-brown"><i class="fa fa-times"></i></a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="st-card">
                <div class="st-card__body">
                    <p class="text-muted" style="font-size:13px;">
                        <i class="fa fa-info-circle"></i>
                        Pillar konten dipakai di EP Bulanan/Mingguan dan Breakdown Konten — bisa multi-pillar per konten.
                        Tambah/hapus pillar di sini supaya tim creative bisa pakai sesuai strategi.
                    </p>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="4%">#</th>
                                    <th>Nama</th>
                                    <th>Slug</th>
                                    <th>Deskripsi</th>
                                    <th width="10%">Urutan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pillars as $key => $row)
                                    <tr data-href="{{ route('kontenpillar.edit', $row->id) }}">
                                        <td class="st-num">{{ $pillars->firstItem() + $key }}</td>
                                        <td class="st-primary">
                                            @if($row->color)
                                                <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:{{ $row->color }}; margin-right:6px; vertical-align:middle;"></span>
                                            @endif
                                            {{ $row->nama }}
                                        </td>
                                        <td><code style="background:#f5e6d3; padding:2px 8px; border-radius:4px; font-size:11px; color:#7d4429;">{{ $row->slug }}</code></td>
                                        <td>{{ $row->deskripsi ?? '-' }}</td>
                                        <td class="st-num">{{ $row->sort_order }}</td>
                                        <td>
                                            @if ($row->is_active)
                                                <span class="st-pill st-pill--success">Aktif</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($pillars->isEmpty())
                                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pillar.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    {{ $pillars->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
