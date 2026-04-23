@extends('layouts.app')

@section('content')
@php
    use App\Models\Complaint;
    $pageTitle = $isAdmin ? 'Keluhan Sales' : 'Riwayat Keluhan Saya';
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">
                    <i class="dripicons-conversation"></i>&nbsp; {{ $pageTitle }}
                    @if ($isAdmin && $counts['new'] > 0)
                        <span class="badge badge-danger" style="font-size:0.65rem; vertical-align:middle;">{{ $counts['new'] }} baru</span>
                    @endif
                </h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">

                    {{-- STATUS TABS --}}
                    <div class="st-tabs">
                        <a href="{{ route('complaint.index', ['status' => 'all', 'branch_id' => $filterBranch]) }}"
                           class="{{ $filterStatus === 'all' ? 'active' : '' }}">
                            <i class="fa fa-list"></i> Semua <span class="st-tab-count">{{ $counts['all'] }}</span>
                        </a>
                        <a href="{{ route('complaint.index', ['status' => 'new', 'branch_id' => $filterBranch]) }}"
                           class="{{ $filterStatus === 'new' ? 'active' : '' }}">
                            <i class="fa fa-envelope"></i> Baru <span class="st-tab-count">{{ $counts['new'] }}</span>
                        </a>
                        <a href="{{ route('complaint.index', ['status' => 'received', 'branch_id' => $filterBranch]) }}"
                           class="{{ $filterStatus === 'received' ? 'active' : '' }}">
                            <i class="fa fa-inbox"></i> Diterima <span class="st-tab-count">{{ $counts['received'] }}</span>
                        </a>
                        <a href="{{ route('complaint.index', ['status' => 'handled', 'branch_id' => $filterBranch]) }}"
                           class="{{ $filterStatus === 'handled' ? 'active' : '' }}">
                            <i class="fa fa-check-circle"></i> Sudah Ditangani <span class="st-tab-count">{{ $counts['handled'] }}</span>
                        </a>
                    </div>

                    {{-- FILTER BAR (admin only) --}}
                    @if ($isAdmin)
                        <form method="GET" action="{{ route('complaint.index') }}" class="st-filter-bar">
                            <input type="hidden" name="status" value="{{ $filterStatus }}">

                            <label>Cabang</label>
                            <select name="branch_id" onchange="this.form.submit();">
                                <option value="">Semua Cabang</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ $filterBranch === $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>

                            @if ($filterBranch)
                                <a href="{{ route('complaint.index', ['status' => $filterStatus]) }}" class="st-btn-outline-brown">
                                    Reset
                                </a>
                            @endif
                        </form>
                    @endif

                    <div class="st-table-wrap--scroll">
                        <table class="st-table">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    @if ($isAdmin)
                                        <th width="14%">Nama Pengirim</th>
                                    @endif
                                    <th width="12%">Cabang</th>
                                    <th>Keluhan</th>
                                    <th>Umpan Balik</th>
                                    <th width="10%">Tanggal</th>
                                    <th width="11%">Status</th>
                                    @if ($isAdmin)
                                        <th width="11%">Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($complaints as $i => $c)
                                    <tr style="{{ $c->status === Complaint::STATUS_NEW ? 'background: #fffaf0;' : '' }}">
                                        <td class="st-num">{{ $complaints->firstItem() + $i }}</td>
                                        @if ($isAdmin)
                                            <td><strong>{{ $c->sender_name ?? '-' }}</strong></td>
                                        @endif
                                        <td>{{ $c->branch->name ?? '-' }}</td>
                                        <td style="white-space: normal; word-break: break-word; max-width: 340px;">
                                            {{ $c->message }}
                                        </td>
                                        <td style="white-space: normal; word-break: break-word; max-width: 300px;">
                                            @if ($c->reply)
                                                <span style="color:#2a5d3a;">{{ $c->reply }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="st-date">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</span>
                                            <small class="d-block text-muted">{{ \Carbon\Carbon::parse($c->created_at)->format('H:i') }}</small>
                                        </td>
                                        <td>
                                            <span class="st-pill {{ $c->status_pill_class }}">{{ $c->status_label }}</span>
                                        </td>
                                        @if ($isAdmin)
                                            <td nowrap>
                                                <div class="d-flex" style="gap:4px;">
                                                    @if ($c->status !== Complaint::STATUS_HANDLED)
                                                        <form action="{{ route('complaint.advance', $c->id) }}" method="POST" style="display:inline;">
                                                            @csrf @method('PATCH')
                                                            <input type="hidden" name="status" value="{{ $filterStatus }}">
                                                            <input type="hidden" name="branch_id" value="{{ $filterBranch }}">
                                                            <input type="hidden" name="page" value="{{ request('page', 1) }}">
                                                            <button class="st-icon-btn" type="submit"
                                                                title="{{ $c->status === Complaint::STATUS_NEW ? 'Tandai Diterima' : 'Tandai Sudah Ditangani' }}">
                                                                <i class="fa fa-check" style="color:{{ $c->status === Complaint::STATUS_NEW ? '#2d7a3a' : '#d49a1a' }}"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    <button class="st-icon-btn" type="button" data-toggle="modal" data-target="#replyModal{{ $c->id }}"
                                                        title="{{ $c->reply ? 'Edit Umpan Balik' : 'Balas Keluhan' }}">
                                                        <i class="fa fa-reply" style="color:#461500"></i>
                                                    </button>
                                                    <button class="st-icon-btn" onclick="deleteComplaint('{{ $c->id }}')" title="Hapus">
                                                        <i class="fa fa-trash" style="color:#B23A3A"></i>
                                                    </button>
                                                    <form action="{{ route('complaint.destroy', $c->id) }}" method="POST" id="deleteComplaint{{ $c->id }}" style="display:none;">
                                                        @csrf @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    @if ($isAdmin)
                                        {{-- Reply Modal --}}
                                        <div class="modal fade" id="replyModal{{ $c->id }}" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content">
                                                    <form method="POST" action="{{ route('complaint.reply', $c->id) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $filterStatus }}">
                                                        <input type="hidden" name="branch_id" value="{{ $filterBranch }}">
                                                        <input type="hidden" name="page" value="{{ request('page', 1) }}">
                                                        <div class="modal-header" style="background:linear-gradient(135deg,#461500,#6b2400); color:#fff; border-bottom:none;">
                                                            <h5 class="modal-title" style="color:#fff; font-weight:700;">
                                                                <i class="fa fa-reply"></i>&nbsp; Umpan Balik IT
                                                            </h5>
                                                            <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.9; text-shadow:none;">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3" style="background:#fffaf0; border:1px solid #f0e4d4; border-radius:10px; padding:12px;">
                                                                <div style="font-size:0.75rem; color:#8b7a6b; font-weight:600; margin-bottom:4px;">
                                                                    <i class="fa fa-user"></i> {{ $c->sender_name }}
                                                                    @if ($c->branch)
                                                                        &middot; {{ $c->branch->name }}
                                                                    @endif
                                                                    &middot; {{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y H:i') }}
                                                                </div>
                                                                <div style="color:#461500; font-size:0.88rem;">{{ $c->message }}</div>
                                                            </div>

                                                            <label style="font-weight:600; font-size:0.85rem; color:#461500;">
                                                                Umpan Balik / Keterangan
                                                            </label>
                                                            <textarea name="reply" class="form-control" rows="5" maxlength="2000"
                                                                placeholder="Tuliskan umpan balik untuk keluhan ini..."
                                                                style="border:2px solid #e8d5c4; border-radius:10px; padding:12px;">{{ $c->reply }}</textarea>
                                                            <small class="text-muted">Kosongkan lalu simpan kalau ingin menghapus umpan balik.</small>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-primary" style="background:linear-gradient(135deg,#461500,#6b2400); border:none;">
                                                                <i class="fa fa-save"></i>&nbsp; Simpan Umpan Balik
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ $isAdmin ? 8 : 6 }}" class="text-center st-muted" style="padding:40px 20px;">
                                            <i class="dripicons-conversation" style="font-size:2rem; display:block; margin-bottom:8px; color:#c0b0a0;"></i>
                                            @if ($isSales)
                                                Belum ada keluhan yang kamu kirim.
                                            @else
                                                Belum ada keluhan masuk.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $complaints->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    function deleteComplaint(id) {
        Swal.fire({
            title: 'Hapus Keluhan?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#461500',
            cancelButtonColor: '#aaa',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (result.isConfirmed) {
                $('#deleteComplaint' + id).submit();
            }
        });
    }
</script>
@endsection
