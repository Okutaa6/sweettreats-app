@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">User</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="st-card">
            <div class="st-card__body">
                <a href="{{ route('setusermanagement.new') }}" class="st-btn-brand mb-3 d-inline-block">
                    <i class="fa fa-plus"></i> Tambah
                </a>

                <div class="st-table-wrap--scroll">
                    <table class="st-table" id="dataTable">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Approved List</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $row)
                                <tr>
                                    <td>{{ $row->nama }}</td>
                                    <td>{{ $row->email }}</td>
                                    <td>{{ $row->group->nama }}</td>
                                    <td>
                                        @foreach ($row->approvals as $apv)
                                            <span class="st-pill st-pill--primary">{{ $apv->user->nama }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if ($row->status == 1)
                                            <span class="st-pill st-pill--success">Aktif</span>
                                        @else
                                            <span class="st-pill st-pill--danger">Tidak Aktif</span>
                                        @endif
                                    </td>
                                    <td><span class="st-date">{{ $row->last_login }}</span></td>
                                    <td nowrap>
                                        <div class="d-flex" style="gap:4px;">
                                            <a href="{{ route('setusermanagement.edit', $row->id) }}"
                                                class="st-icon-btn" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="{{ route('setusermanagement.reset', $row->id) }}"
                                                class="st-icon-btn" title="Reset Password">
                                                <i class="fa fa-key"></i>
                                            </a>
                                            <a href="{{ route('setusermanagement.apv', $row->id) }}"
                                                class="st-icon-btn" title="Approval">
                                                <i class="fa fa-user"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
@endsection
