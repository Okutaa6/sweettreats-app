@extends('layouts.app')

@section('content')
<style>
    .rv-seg { display:flex; background:#fff; border:1px solid #EADCC9; border-radius:12px; padding:4px; gap:3px; }
    .rv-seg a {
        display:inline-flex; align-items:center; gap:7px;
        border-radius:9px; padding:8px 15px;
        font-weight:700; font-size:12.5px; text-decoration:none;
        color:#7A5B43; transition:background-color .15s ease, filter .15s ease; white-space:nowrap;
    }
    .rv-seg a:hover { background:#F1E4D3; color:#5A3A22; }
    .rv-seg a.is-active--reopen { background:linear-gradient(120deg,#A6531A,#6B2400); color:#FBEADB; }
    .rv-fpill {
        display:inline-flex; align-items:center; gap:6px; text-decoration:none;
        font-size:12px; font-weight:700; color:#7A5B43;
        border:1px solid #E7D3BE; background:#fff; border-radius:20px; padding:6px 13px;
    }
    .rv-fpill.is-active { background:linear-gradient(135deg,#6B2400,#461500); color:#FFEAD6; border-color:transparent; }
    .rv-table { width:100%; border-collapse:collapse; min-width:820px; }
    .rv-table thead tr { background:linear-gradient(120deg,#3A1505,#52200A); }
    .rv-table thead th { padding:13px 16px; text-align:left; font-size:11px; font-weight:800; letter-spacing:.6px; color:#EAD3BC; text-transform:uppercase; }
    .rv-table tbody td { padding:12px 16px; border-bottom:1px solid #F4EADD; vertical-align:top; }
    .rv-table tbody tr:hover { background:#FBF4EC; }
    .rv-approve, .rv-reject {
        display:inline-flex; align-items:center; gap:6px; border:none; border-radius:9px;
        padding:7px 13px; font-weight:700; font-size:12px; font-family:inherit; cursor:pointer;
    }
    .rv-approve { background:linear-gradient(135deg,#2F8E55,#1F6E5F); color:#fff; }
    .rv-approve:hover { filter:brightness(1.08); }
    .rv-reject { background:#fff; color:#C0392B; border:1px solid #E7C3BC; }
    .rv-reject:hover { background:#FCEBEB; }
    .rv-reject-note { width:100%; box-sizing:border-box; border:1px solid #E7D3BE; border-radius:9px; padding:8px 11px; font-size:12px; font-family:inherit; resize:vertical; margin-top:6px; }
    .st-pill { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:800; padding:4px 11px; border-radius:20px; white-space:nowrap; }
    .st-pill--warning { background:#FBEFD9; color:#B5701A; }
    .st-pill--success { background:#E3F4E8; color:#2F8E55; }
    .st-pill--danger  { background:#FCEBEB; color:#C0392B; }
</style>
<div class="container-fluid">

    @include('layouts.notif')

    <div style="max-width:1240px;margin:0 auto">

    {{-- ─── HERO ─── --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:18px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div style="flex:1;min-width:240px">
            <div style="font-size:21px;font-weight:800;color:#3B1500">Permintaan Buka Kembali</div>
            <div style="font-size:13px;color:#9A7256;margin-top:3px">Karyawan minta membuka checklist tanggal lampau (tidak diisi / lupa dikirim). Setujui untuk membuka tanggal itu agar bisa diisi.</div>
        </div>
        <a href="{{ route('checklist.review') }}" class="cl-btn-dash" style="flex:none;background:#fff;color:#5A3A22;border:1px solid #E7D3BE;border-radius:11px;padding:10px 16px;font-weight:700;font-size:13px;text-decoration:none">← Review</a>
    </div>

    {{-- ─── SEG ROW ─── --}}
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
        <div class="rv-seg" style="margin-left:auto">
            <a href="{{ route('checklist.review') }}"><i class="fas fa-sun"></i> Checklist Harian</a>
            <a href="{{ route('checklist.review.weekly') }}"><i class="fas fa-calendar-week"></i> Checklist Mingguan</a>
            <a href="{{ route('checklist.reopen.index') }}" class="is-active--reopen"><i class="fas fa-unlock-alt"></i> Permintaan Buka
                @if($pendingCount > 0)
                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#C0392B;color:#fff;font-size:10.5px;font-weight:800;">{{ $pendingCount }}</span>
                @endif
            </a>
        </div>
    </div>

    {{-- ─── FILTER STATUS + CABANG ─── --}}
    <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin-bottom:14px">
        @php $filterOpts = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua']; @endphp
        @foreach($filterOpts as $val => $label)
            <a href="{{ route('checklist.reopen.index', array_filter(['status' => $val, 'branch' => $branchId])) }}"
               class="rv-fpill {{ $statusFilter === $val ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach

        @if(canSeeAllBranches() && $branches->count())
            <form method="GET" action="{{ route('checklist.reopen.index') }}" style="margin-left:auto">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <select name="branch" onchange="this.form.submit();" style="border:1px solid #E7D3BE;border-radius:11px;padding:8px 12px;font-family:inherit;font-size:13px;color:#3B1500;background:#fff;cursor:pointer">
                    <option value="">Semua Cabang</option>
                    @foreach($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    {{-- ─── TABEL PERMINTAAN ─── --}}
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:18px;overflow-x:auto;box-shadow:0 1px 2px rgba(70,21,0,.04),0 10px 26px rgba(70,21,0,.05)">
        @if($requests->isEmpty())
            <div style="padding:34px 20px;text-align:center;font-size:12.5px;color:#9A7256">
                Tidak ada permintaan {{ $statusFilter !== 'all' ? '"' . ($filterOpts[$statusFilter] ?? $statusFilter) . '"' : '' }} untuk filter ini.
            </div>
        @else
            <table class="rv-table">
                <thead>
                    <tr>
                        <th>Karyawan</th>
                        <th style="width:110px;">Jenis</th>
                        <th style="width:180px;">Target</th>
                        <th>Alasan</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:210px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        <tr>
                            <td>
                                <div style="font-size:13px;font-weight:700;color:#3B1500">{{ $req->user?->nama ?? '—' }}</div>
                                <div style="font-size:11.5px;color:#9A7256">{{ $req->division?->name ?? $req->division_id }} · {{ $req->branch?->name ?? '—' }}</div>
                                <div style="font-size:11px;color:#B0855F;margin-top:2px">diajukan {{ optional($req->created_at)->translatedFormat('d M Y, H:i') }}</div>
                            </td>
                            <td style="font-size:12.5px;color:#7A5B43">
                                @if($req->scope === \App\Models\ChecklistReopenRequest::SCOPE_WEEKLY)
                                    <span><i class="fas fa-calendar-week" style="color:#1F6E5F"></i> Mingguan</span>
                                @else
                                    <span><i class="fas fa-sun" style="color:#A66019"></i> Harian</span>
                                @endif
                            </td>
                            <td style="font-size:12.5px;color:#3B1500;font-weight:600">{{ $req->targetLabel() }}</td>
                            <td style="font-size:12.5px;color:#5A3A22;max-width:280px">{{ $req->reason }}</td>
                            <td>
                                <span class="st-pill st-pill--{{ $req->statusColor() }}">{{ $req->statusLabel() }}</span>
                                @if($req->status === \App\Models\ChecklistReopenRequest::STATUS_REJECTED && $req->review_notes)
                                    <div style="font-size:11px;color:#C0392B;margin-top:5px">“{{ $req->review_notes }}”</div>
                                @endif
                                @if($req->reviewer)
                                    <div style="font-size:11px;color:#9A7256;margin-top:3px">oleh {{ $req->reviewer->nama }}</div>
                                @endif
                            </td>
                            <td>
                                @if($req->status === \App\Models\ChecklistReopenRequest::STATUS_PENDING)
                                    <form method="POST" action="{{ route('checklist.reopen.approve', $req->id) }}" style="display:inline-block"
                                          data-confirm="Setujui permintaan buka kembali {{ $req->targetLabel() }}?">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="rv-approve"><i class="fas fa-check"></i> Setujui</button>
                                    </form>
                                    <details style="display:inline-block;margin-top:6px">
                                        <summary style="cursor:pointer;list-style:none"><span class="rv-reject"><i class="fas fa-times"></i> Tolak</span></summary>
                                        <form method="POST" action="{{ route('checklist.reopen.reject', $req->id) }}" style="margin-top:6px">
                                            @csrf @method('PATCH')
                                            <textarea name="review_notes" rows="2" maxlength="500" required class="rv-reject-note" placeholder="Alasan menolak (wajib)…"></textarea>
                                            <button type="submit" class="rv-reject" style="margin-top:6px"><i class="fas fa-times"></i> Kirim penolakan</button>
                                        </form>
                                    </details>
                                @else
                                    <span style="font-size:12px;color:#9A7256">{{ optional($req->reviewed_at)->translatedFormat('d M Y, H:i') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($requests->hasPages())
        <div style="margin-top:16px">{{ $requests->links() }}</div>
    @endif

    </div>{{-- /max-width --}}
</div>
@endsection
