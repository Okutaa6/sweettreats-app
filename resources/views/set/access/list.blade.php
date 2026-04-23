@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Akses</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="st-card">
            <div class="st-card__body">
                <a href="{{ route('setaccess.new') }}" class="st-btn-brand mb-3 d-inline-block">
                    <i class="fa fa-plus"></i> Tambah
                </a>

                @csrf
                <div class="st-table-wrap--scroll">
                    <table class="st-table" id="dataTable">
                        <tbody>
                            @foreach ($access as $row)
                                <tr class="parent" data-id="{{ $row['id'] }}" style="cursor:pointer;">
                                    <td>
                                        <h5 class="d-inline-block mb-0" style="font-size:14px; font-weight:700; color:var(--text-primary)">
                                            <span class="toggle" style="margin-right:8px;">&#9658;</span>
                                            {{ $row['nama'] }}
                                        </h5>
                                    </td>
                                    <td width="5%">
                                        <a href="{{ route('setaccess.edit', $row['id']) }}" class="st-icon-btn" title="Edit">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>

                                @if (!empty($row['sub']))
                                    @foreach ($row['sub'] as $rowsub)
                                        <tr class="sub parent-{{ $row['id'] }}" data-id="{{ $rowsub['id'] }}" style="cursor:pointer; display:none;">
                                            <td style="padding-left: 36px;">
                                                <h5 class="d-inline-block mb-0" style="font-size:13px; font-weight:600; color:var(--text-muted)">
                                                    <span class="toggle" style="margin-right:8px;">&#9658;</span>
                                                    {{ $rowsub['nama'] }}
                                                </h5>
                                            </td>
                                            <td width="5%">
                                                <a href="{{ route('setaccess.edit', $rowsub['id']) }}" class="st-icon-btn" title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>

                                        @if (!empty($rowsub['child']))
                                            @foreach ($rowsub['child'] as $rowchild)
                                                <tr class="child sub-{{ $rowsub['id'] }}" style="display:none;">
                                                    <td style="padding-left: 66px;">
                                                        <span style="font-size:13px; color:var(--text-subtle)">{{ $rowchild['nama'] }}</span>
                                                    </td>
                                                    <td width="5%">
                                                        <a href="{{ route('setaccess.edit', $rowchild['id']) }}" class="st-icon-btn" title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endif
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/plugins/listree/listree.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('js')
    <script src="{{ asset('assets/plugins/listree/listree.umd.min.js') }}"></script>
    <script>
        $(document).ready(() => {
            $('tr.parent, tr.sub').on('click', function(e) {
                if ($(e.target).is('a, i')) return;

                var targetId = $(this).data('id');
                var toggleIcon = $(this).find('.toggle');

                toggleIcon.html(toggleIcon.html() === '►' ? '▼' : '►');

                $('.parent-' + targetId + ', .sub-' + targetId).each(function() {
                    $(this).toggle();
                    if ($(this).is(':hidden')) {
                        $(this).find('.toggle').html('►');
                        var subTargetId = $(this).data('id');
                        $('.sub-' + subTargetId).hide();
                    }
                });
            });
        });
    </script>
@endsection
