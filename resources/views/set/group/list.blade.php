@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Role</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="st-card">
            <div class="st-card__body">
                <a href="{{ route('setgroup.new') }}" class="st-btn-brand mb-3 d-inline-block">
                    <i class="fa fa-plus"></i> Tambah
                </a>

                <div class="st-table-wrap--scroll">
                    <table class="st-table" id="dataTable">
                        <thead>
                            <tr>
                                <th width="5%">No.</th>
                                <th>Name</th>
                                <th>Deskripsi</th>
                                <th>Landing Page</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $index => $list)
                                <tr>
                                    <td class="st-num">{{ $index + 1 }}</td>
                                    <td>{{ $list->nama }}</td>
                                    <td>{{ $list->deskripsi }}</td>
                                    <td><code style="font-size:12px; color:var(--text-subtle)">{{ $list->landing_page }}</code></td>
                                    <td nowrap>
                                        <div class="d-flex" style="gap:4px;">
                                            <a href="{{ route('setgroup.edit', $list->id) }}"
                                                class="st-icon-btn" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="{{ route('setgroup.access', $list->id) }}"
                                                class="st-icon-btn" title="Akses">
                                                <i class="fa fa-key"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $groups->links() }}</div>
            </div>
        </div>
    </div>
@endsection
