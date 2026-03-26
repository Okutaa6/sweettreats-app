@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <div class="float-right">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a>CRUD</a></li>
                        <li class="breadcrumb-item active">Keyword</li>
                    </ol>
                </div>
                <h4 class="page-title">Keyword / Intent</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <a href="{{ route('rawkeyword.new') }}" class="btn btn-sm btn-primary float-left mb-3">
                            <i class="fa fa-plus"></i> Tambah
                        </a>

                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Keyword</th>
                                    <th>Deskripsi</th>
                                    <th>Berlaku Sampai</th>
                                    <th>Status</th>
                                    <th width="8%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($keywords as $key => $row)
                                    <tr>
                                        <td>{{ $keywords->firstItem() + $key }}</td>
                                        <td><strong>{{ $row->keyword }}</strong></td>
                                        <td>{{ $row->description ?? '-' }}</td>
                                        <td>
                                            @if($row->end_date)
                                                {{ $row->end_date->format('d/m/Y') }}
                                                @if($row->end_date->isPast())
                                                    <span class="badge badge-danger ml-1">Expired</span>
                                                @endif
                                            @else
                                                <span class="text-muted">Selamanya</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$row->end_date || $row->end_date->isFuture())
                                                <span class="badge badge-success">Aktif</span>
                                            @else
                                                <span class="badge badge-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('rawkeyword.edit', $row->id) }}"
                                                class="btn btn-info" style="padding:2px 6px" title="Edit">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        {{ $keywords->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection