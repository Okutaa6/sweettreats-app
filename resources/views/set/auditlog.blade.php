@extends('layouts.app')

@section('css')
<style>
    .log-action { display:inline-block; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:600; }
    .log-create { background:#e8f5e9; color:#2e7d32; }
    .log-update { background:#e3f2fd; color:#1565c0; }
    .log-delete { background:#ffebee; color:#c62828; }
    .log-login { background:#fff3e0; color:#e65100; }
    .log-logout { background:#f3e5f5; color:#7b1fa2; }
    .log-status_change { background:#e0f7fa; color:#00695c; }
    .json-preview { max-height:120px; overflow:auto; font-size:0.72rem; background:#f8f8f8; padding:6px; border-radius:4px; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title"><i class="fa fa-history mr-2"></i> Audit Log</h4>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- Filters --}}
            <form method="GET" class="form-inline mb-3">
                <select name="user_id" class="form-control form-control-sm mr-2">
                    <option value="">- Semua User -</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
                    @endforeach
                </select>
                <select name="action" class="form-control form-control-sm mr-2">
                    <option value="">- Semua Action -</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ request('action') == $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" class="form-control form-control-sm mr-2" value="{{ request('date_from') }}" placeholder="Dari">
                <input type="date" name="date_to" class="form-control form-control-sm mr-2" value="{{ request('date_to') }}" placeholder="Sampai">
                <button class="btn btn-sm btn-primary mr-2"><i class="fa fa-filter"></i> Filter</button>
                <a href="{{ route('auditlog') }}" class="btn btn-sm btn-secondary">Reset</a>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead style="background:rgb(250,245,243)">
                        <tr>
                            <th style="width:140px">Waktu</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Model</th>
                            <th>Deskripsi</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td nowrap style="font-size:0.8rem">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->user_name ?? '-' }}</td>
                            <td><span class="log-action log-{{ $log->action }}">{{ $log->action }}</span></td>
                            <td style="font-size:0.8rem">
                                @if($log->model_type)
                                    {{ $log->model_type }}
                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($log->model_id, 12) }}</small>
                                @else
                                    -
                                @endif
                            </td>
                            <td style="font-size:0.85rem">
                                {{ $log->description ?? '-' }}
                                @php
                                    // Samarkan lagi saat render — baris lama (pra-masking) bisa masih simpan hash.
                                    $dSamar = function ($a) {
                                        if (! is_array($a)) return [];
                                        foreach (['password', 'token_hash', 'remember_token'] as $k) {
                                            if (array_key_exists($k, $a)) $a[$k] = '[disamarkan]';
                                        }
                                        return $a;
                                    };
                                    $dNew = $dSamar($log->new_values); $dOld = $dSamar($log->old_values);
                                @endphp
                                @if($dNew)
                                    <div style="display:flex;flex-wrap:wrap;gap:3px 8px;margin-top:2px">
                                        @foreach($dNew as $k => $v)
                                            <small class="text-muted" style="background:#f6f1ea;border-radius:5px;padding:0 6px">{{ $k }}: @if(array_key_exists($k, $dOld)){{ \Illuminate\Support\Str::limit(is_scalar($dOld[$k]) || $dOld[$k] === null ? (string) $dOld[$k] : json_encode($dOld[$k]), 30) }} → @endif{{ \Illuminate\Support\Str::limit(is_scalar($v) || $v === null ? (string) $v : json_encode($v), 30) }}</small>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td style="font-size:0.8rem">{{ $log->ip_address }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data audit log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
