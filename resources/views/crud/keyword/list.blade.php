@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Keyword / Intent</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">
                    <a href="{{ route('rawkeyword.new') }}" class="st-btn-brand mb-3" style="display:inline-flex">
                        <i class="fa fa-plus"></i> Tambah
                    </a>

                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--clickable">
                            <thead>
                                <tr>
                                    <th width="5%">No</th>
                                    <th>Keyword</th>
                                    <th>Deskripsi</th>
                                    <th>Berlaku Sampai</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($keywords as $key => $row)
                                    <tr data-href="{{ route('rawkeyword.edit', $row->id) }}">
                                        <td class="st-num">{{ $keywords->firstItem() + $key }}</td>
                                        <td class="st-primary">{{ $row->keyword }}</td>
                                        <td>{{ $row->description ?? '-' }}</td>
                                        <td>
                                            @if($row->end_date)
                                                <span class="st-date">{{ $row->end_date->format('d/m/Y') }}</span>
                                                @if($row->end_date->isPast())
                                                    <span class="st-pill st-pill--danger ml-1">Expired</span>
                                                @endif
                                            @else
                                                <span class="st-muted">Selamanya</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!$row->end_date || $row->end_date->isFuture())
                                                <span class="st-pill st-pill--success">Aktif</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $keywords->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
