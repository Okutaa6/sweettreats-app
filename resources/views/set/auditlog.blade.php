@extends('layouts.app')

{{-- Audit Log — jejak aktivitas & perubahan (Pusat Akun). Tema grafit sama
     dengan halaman Pusat Akun lain (User/Akses/Kesehatan). --}}

@section('css')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    .al-wrap { font-family: 'Plus Jakarta Sans', sans-serif; padding: 20px 26px 30px; display: flex; flex-direction: column; gap: 16px; color: #3B1500; }
    .al-wrap .num { font-variant-numeric: tabular-nums; font-feature-settings: "tnum" 1; }
    .al-head h1 { font-size: 20px; font-weight: 800; margin: 0; }
    .al-head .sub { font-size: 12.5px; font-weight: 500; color: #9A7256; margin-top: 2px; }

    .al-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; background: #fff; border: 1px solid #EFE0CE; border-radius: 16px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(70,21,0,.04); }
    .al-toolbar select, .al-toolbar input { border: 1px solid #E7D3BE; border-radius: 10px; background: #fff; padding: 9px 12px; font-family: inherit; font-size: 13px; color: #3B1500; }
    .al-btn { display: inline-flex; align-items: center; gap: 7px; border-radius: 10px; padding: 9px 15px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: inherit; border: 1px solid transparent; text-decoration: none; }
    .al-btn-pri { background: linear-gradient(135deg, #3A3A36, #242422); color: #fff; box-shadow: 0 4px 12px rgba(20,20,18,.22); }
    .al-btn-pri:hover { filter: brightness(1.12); color: #fff; }
    .al-btn-sec { background: #fff; border-color: #E7D3BE; color: #5A3A22; }
    .al-btn-sec:hover { background: #FBF4EC; color: #5A3A22; }

    .al-card { background: #fff; border: 1px solid #EFE0CE; border-radius: 18px; overflow-x: auto; box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05); }
    table.al-table { border-collapse: separate; border-spacing: 0; width: 100%; min-width: 820px; }
    .al-table thead tr { background: linear-gradient(120deg, #2C2C29, #3A3A36); }
    .al-table thead th { background: transparent; color: #E3D8C9; font-size: 11px; font-weight: 800; letter-spacing: .5px; text-align: left; padding: 12px 16px; white-space: nowrap; }
    .al-table tbody td { padding: 11px 16px; border-bottom: 1px solid #F4EADD; font-size: 13px; color: #3B1500; vertical-align: top; }
    .al-table tbody tr:hover { background: #FBF4EC; }
    .al-time { font-size: 12px; color: #7A5B43; white-space: nowrap; }
    .al-user { font-weight: 700; }
    .al-model { font-size: 12px; color: #7A5B43; }
    .al-model small { color: #A9866A; }
    .al-ip { font-size: 12px; color: #9A7256; }
    .al-muted { color: #C9AE94; }

    /* Badge action — token status standar. */
    .al-act { display: inline-block; padding: 3px 9px; border-radius: 7px; font-size: 11px; font-weight: 800; letter-spacing: .02em; }
    .al-create        { background: #E3F4E8; color: #2F8E55; }
    .al-update        { background: #E4ECFB; color: #3F6BAF; }
    .al-delete        { background: #FBE3DE; color: #C0392B; }
    .al-login         { background: #FBEFD9; color: #B5701A; }
    .al-logout        { background: #F1E9DF; color: #8A6A52; }
    .al-status_change { background: #DFEDE9; color: #2E7D74; }

    .al-diff { display: flex; flex-wrap: wrap; gap: 3px 8px; margin-top: 3px; }
    .al-diff .chip { font-size: 11px; background: #F6EFE6; border: 1px solid #EADFCE; border-radius: 6px; padding: 1px 7px; color: #7A5B43; }
    .al-empty { text-align: center; padding: 34px 16px; color: #9A7256; font-size: 13px; }
</style>
@endsection

@section('content')
<div class="al-wrap">
    <div class="al-head">
        <h1>Audit Log</h1>
        <div class="sub">Jejak aktivitas &amp; perubahan sistem. Disimpan 6 bulan.</div>
    </div>

    <form method="GET" class="al-toolbar">
        <select name="user_id">
            <option value="">Semua User</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->nama }}</option>
            @endforeach
        </select>
        <select name="action">
            <option value="">Semua Aksi</option>
            @foreach($actions as $a)
                <option value="{{ $a }}" {{ request('action') == $a ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}">
        <input type="date" name="date_to" value="{{ request('date_to') }}">
        <button type="submit" class="al-btn al-btn-pri">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filter
        </button>
        <a href="{{ route('auditlog') }}" class="al-btn al-btn-sec">Reset</a>
    </form>

    <div class="al-card">
        <table class="al-table">
            <thead>
                <tr>
                    <th style="width:150px">WAKTU</th><th>USER</th><th>AKSI</th>
                    <th>MODEL</th><th>DESKRIPSI</th><th>IP</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $alSamar = function ($a) {
                        if (! is_array($a)) return [];
                        foreach (['password', 'token_hash', 'remember_token'] as $k) {
                            if (array_key_exists($k, $a)) $a[$k] = '[disamarkan]';
                        }
                        return $a;
                    };
                    $alFmt = fn ($x) => \Illuminate\Support\Str::limit(is_scalar($x) || $x === null ? (string) $x : json_encode($x), 30);
                @endphp
                @forelse($logs as $log)
                    <tr>
                        <td class="al-time num">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="al-user">{{ $log->user_name ?? '—' }}</td>
                        <td><span class="al-act al-{{ $log->action }}">{{ $log->action }}</span></td>
                        <td class="al-model">
                            @if($log->model_type)
                                {{ $log->model_type }}
                                <br><small>{{ \Illuminate\Support\Str::limit($log->model_id, 12) }}</small>
                            @else
                                <span class="al-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $log->description ?? '—' }}
                            @php $dNew = $alSamar($log->new_values); $dOld = $alSamar($log->old_values); @endphp
                            @if($dNew)
                                <div class="al-diff">
                                    @foreach($dNew as $k => $v)
                                        <span class="chip">{{ $k }}: @if(array_key_exists($k, $dOld)){{ $alFmt($dOld[$k]) }} → @endif{{ $alFmt($v) }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="al-ip num">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="al-empty">Tidak ada data audit log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
