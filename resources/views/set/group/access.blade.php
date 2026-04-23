@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Role Access</h4>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="st-card">
                    <div class="st-card__body">
                        <a href="{{ route('setgroup') }}" class="st-btn-outline-brown mb-3 d-inline-block">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        <form method="post" action="{{ route('setgroup.access', $group->id) }}">
                            @csrf
                            @method('PUT')
                            <table class="st-table">
                                <tbody>
                                    @foreach ($data_access as $row)
                                        <tr class="parent" data-id="{{ $row['id'] }}">
                                            <td>
                                                <input type="checkbox" value="{{ $row['id'] }}"
                                                    id="{{ $row['id'] }}" class="mr-2" name="access_id[]"
                                                    {{ in_array($row['id'], $slcaccess) ? 'checked' : '' }}>
                                                <h5 class="d-inline-block"><span class="toggle">&#9658;</span>
                                                    {{ $row['nama'] }}</h5>
                                            </td>
                                        </tr>

                                        @if (!empty($row['sub']))
                                            @foreach ($row['sub'] as $rowsub)
                                                <tr class="sub parent-{{ $row['id'] }}"
                                                    data-id="{{ $rowsub['id'] }}">
                                                    <td style="padding-left: 30px;">
                                                        <input type="checkbox" value="{{ $rowsub['id'] }}"
                                                            id="{{ $rowsub['id'] }}" class="mr-2 {{ $row['id'] }}"
                                                            name="access_id[]"
                                                            {{ in_array($rowsub['id'], $slcaccess) ? 'checked' : '' }}>
                                                        <h5 class="d-inline-block"><span class="toggle">&#9658;</span>
                                                            {{ $rowsub['nama'] }}</h5>
                                                    </td>
                                                </tr>

                                                @if (!empty($rowsub['child']))
                                                    @foreach ($rowsub['child'] as $rowchild)
                                                        <tr class="child sub-{{ $rowsub['id'] }}">
                                                            <td style="padding-left: 60px;">
                                                                <input type="checkbox" value="{{ $rowchild['id'] }}"
                                                                    id="{{ $rowchild['id'] }}"
                                                                    class="mr-2 {{ $row['id'] }} {{ $rowsub['id'] }}"
                                                                    name="access_id[]"
                                                                    {{ in_array($rowchild['id'], $slcaccess) ? 'checked' : '' }}>
                                                                <h5 class="d-inline-block">{{ $rowchild['nama'] }}</h5>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="d-flex justify-content-start mt-3">
                                <button class="st-btn-brand" type="submit">
                                    <i class="fa fa-save"></i> Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
<style>
    .parent, .sub { cursor: pointer; }
</style>
@endsection

@section('js')
<script>
    $(document).ready(function() {
        $('tr.parent, tr.sub').on('click', function(e) {
            if ($(e.target).is('input[type="checkbox"]')) return;

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

        $('.mr-2').on('click', function(e) {
            let slc_value = $(e.target).val();
            let slc_checked = !$('.' + slc_value).is(":checked");
            $('.' + slc_value).prop('checked', slc_checked);
        });
    });
</script>
@endsection
