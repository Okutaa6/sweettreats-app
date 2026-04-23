@extends('layouts.app')

@section('css')
<style>
    .cr-aff-cell { display:flex; align-items:center; gap:10px; }
    .cr-aff-avatar {
        width: 36px; height: 36px; flex-shrink: 0;
        background: var(--st-primary, #461500); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: 13px;
    }
    .cr-platform-chip {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 8px; font-size: 11px; font-weight: 700;
        border: 1px solid var(--st-border, #e6ded9); background: #fff;
    }
    .cr-caption {
        margin-top: 6px; padding: 6px 10px; background: #faf5f3;
        font-size: 12px; color: #555; font-style: italic;
        border-left: 2px solid var(--st-accent, #D0C4BF);
    }
    .cr-notes {
        margin-top: 6px; padding: 6px 10px; background: #fde8e8;
        font-size: 12px; color: #8e1a1a;
        border-left: 2px solid #c62828;
    }
    .cr-url {
        display: inline-flex; align-items: center; gap: 6px;
        color: var(--st-primary, #461500); text-decoration: none;
        font-size: 13px; word-break: break-all;
    }
    .cr-url:hover { text-decoration: underline; }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Review Konten Referral</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body">

                    {{-- SUMMARY CARDS --}}
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-pending-bg); color:var(--st-pending-text);">
                                    <i class="fa fa-clock"></i>
                                </div>
                                <div>
                                    <div class="st-dash-card__value">{{ $counts['pending'] }}</div>
                                    <div class="st-dash-card__label">Menunggu Review</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-completed-bg); color:var(--st-completed-text);">
                                    <i class="fa fa-check-circle"></i>
                                </div>
                                <div>
                                    <div class="st-dash-card__value">{{ $counts['approved'] }}</div>
                                    <div class="st-dash-card__label">Disetujui</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-cancelled-bg, #fde8e8); color:var(--st-cancelled-text, #c62828);">
                                    <i class="fa fa-times-circle"></i>
                                </div>
                                <div>
                                    <div class="st-dash-card__value">{{ $counts['rejected'] }}</div>
                                    <div class="st-dash-card__label">Ditolak</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="st-dash-card">
                                <div class="st-dash-card__icon" style="background:var(--st-confirmed-bg); color:var(--st-confirmed-text);">
                                    <i class="fa fa-users"></i>
                                </div>
                                <div>
                                    <div class="st-dash-card__value">{{ $typeCounts['koc'] + $typeCounts['kol'] }}</div>
                                    <div class="st-dash-card__label">Referral Aktif ({{ $typeCounts['koc'] }} KOC &middot; {{ $typeCounts['kol'] }} KOL)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- STATUS TABS --}}
                    <div class="st-tabs">
                        <a href="{{ route('content.review', ['status' => 'all', 'type' => $filterType, 'platform' => $filterPlatform, 'q' => $search]) }}"
                           class="{{ $filterStatus === 'all' ? 'active' : '' }}">
                            <i class="fa fa-list"></i> Semua <span class="st-tab-count">{{ $counts['all'] }}</span>
                        </a>
                        <a href="{{ route('content.review', ['status' => 'pending', 'type' => $filterType, 'platform' => $filterPlatform, 'q' => $search]) }}"
                           class="{{ $filterStatus === 'pending' ? 'active' : '' }}">
                            <i class="fa fa-clock"></i> Pending <span class="st-tab-count">{{ $counts['pending'] }}</span>
                        </a>
                        <a href="{{ route('content.review', ['status' => 'approved', 'type' => $filterType, 'platform' => $filterPlatform, 'q' => $search]) }}"
                           class="{{ $filterStatus === 'approved' ? 'active' : '' }}">
                            <i class="fa fa-check"></i> Approved <span class="st-tab-count">{{ $counts['approved'] }}</span>
                        </a>
                        <a href="{{ route('content.review', ['status' => 'rejected', 'type' => $filterType, 'platform' => $filterPlatform, 'q' => $search]) }}"
                           class="{{ $filterStatus === 'rejected' ? 'active' : '' }}">
                            <i class="fa fa-times"></i> Rejected <span class="st-tab-count">{{ $counts['rejected'] }}</span>
                        </a>
                    </div>

                    {{-- FILTER BAR --}}
                    <form method="GET" action="{{ route('content.review') }}" class="st-filter-bar">
                        <input type="hidden" name="status" value="{{ $filterStatus }}">

                        <label>Tipe</label>
                        <select name="type" onchange="this.form.submit();">
                            <option value="all" {{ $filterType === 'all' ? 'selected' : '' }}>Semua Tipe</option>
                            <option value="koc" {{ $filterType === 'koc' ? 'selected' : '' }}>KOC ({{ $typeCounts['koc'] }})</option>
                            <option value="kol" {{ $filterType === 'kol' ? 'selected' : '' }}>KOL ({{ $typeCounts['kol'] }})</option>
                            <option value="none" {{ $filterType === 'none' ? 'selected' : '' }}>Tanpa Tipe ({{ $typeCounts['none'] }})</option>
                        </select>

                        <label>Platform</label>
                        <select name="platform" onchange="this.form.submit();">
                            <option value="all" {{ $filterPlatform === 'all' ? 'selected' : '' }}>Semua Platform</option>
                            @foreach(\App\Models\AffiliateContent::platformOptions() as $key => $label)
                                <option value="{{ $key }}" {{ $filterPlatform === $key ? 'selected' : '' }}>
                                    {{ $label }} ({{ $platformCounts[$key] ?? 0 }})
                                </option>
                            @endforeach
                        </select>

                        <label>Cari</label>
                        <input type="text" name="q" value="{{ $search }}" placeholder="Nama atau nomor HP referral...">
                        <button type="submit" class="st-btn-brand">
                            <i class="fa fa-search"></i> Cari
                        </button>

                        @if($filterType !== 'all' || $filterPlatform !== 'all' || $search !== '')
                            <a href="{{ route('content.review', ['status' => $filterStatus]) }}" class="st-btn-outline-brown">
                                <i class="fa fa-undo"></i> Reset
                            </a>
                        @endif

                        <div class="ml-auto st-muted" style="font-size:12px;">
                            Menampilkan <strong class="st-primary">{{ $contents->count() }}</strong> dari <strong class="st-primary">{{ $contents->total() }}</strong> konten
                        </div>
                    </form>

                    {{-- TABLE --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th width="10%">Tanggal</th>
                                    <th width="20%">Referral</th>
                                    <th width="14%">Platform</th>
                                    <th>Konten</th>
                                    <th width="10%">Status</th>
                                    <th width="14%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $platforms = \App\Models\AffiliateContent::platformOptions();
                                    $types = \App\Models\AffiliateContent::contentTypeOptions();
                                    $platformIcons = [
                                        'tiktok'    => 'fab fa-tiktok',
                                        'instagram' => 'fab fa-instagram',
                                        'youtube'   => 'fab fa-youtube',
                                    ];
                                @endphp
                                @forelse($contents as $c)
                                    @php
                                        $affType      = $c->affiliate?->type;
                                        $typePillCls  = $affType === 'koc' ? 'st-pill--info' : ($affType === 'kol' ? 'st-pill--primary' : 'st-pill--neutral');
                                        $affName      = $c->affiliate?->name ?? '-';
                                        $initial      = strtoupper(mb_substr($affName, 0, 1));
                                        $statusPillCls = [
                                            'pending'  => 'st-pill--warning',
                                            'approved' => 'st-pill--success',
                                            'rejected' => 'st-pill--danger',
                                        ][$c->status] ?? 'st-pill--neutral';
                                    @endphp
                                    <tr class="{{ $c->status === 'pending' ? 'st-row--pending-highlight' : '' }}">
                                        <td nowrap>
                                            <span class="st-primary" style="font-weight:700;">{{ $c->created_at?->format('d/m/Y') }}</span>
                                            <div class="st-muted" style="font-size:11px; margin-top:2px;">
                                                <i class="fa fa-clock"></i> {{ $c->created_at?->format('H:i') }}
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cr-aff-cell">
                                                <div class="cr-aff-avatar">{{ $initial }}</div>
                                                <div>
                                                    <div class="st-primary" style="font-weight:700;">
                                                        {{ $affName }}
                                                        @if($affType)
                                                            <span class="st-pill st-pill--plain {{ $typePillCls }}" style="margin-left:4px;">{{ strtoupper($affType) }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="st-muted" style="font-size:11px;">
                                                        <i class="fa fa-phone"></i> {{ $c->affiliate?->phone ?? '-' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="cr-platform-chip">
                                                <i class="{{ $platformIcons[$c->platform] ?? 'fa fa-globe' }}"></i>
                                                {{ $platforms[$c->platform] ?? $c->platform }}
                                            </span>
                                            <div class="st-muted" style="font-size:11px; margin-top:4px;">
                                                <i class="fa fa-video"></i> {{ $types[$c->content_type] ?? $c->content_type }}
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ $c->content_url }}" target="_blank" rel="noopener" class="cr-url">
                                                <i class="fa fa-external-link-alt"></i>
                                                {{ \Illuminate\Support\Str::limit($c->content_url, 55) }}
                                            </a>
                                            @if($c->caption)
                                                <div class="cr-caption">{{ \Illuminate\Support\Str::limit($c->caption, 140) }}</div>
                                            @endif
                                            @if($c->status === 'rejected' && $c->review_notes)
                                                <div class="cr-notes"><strong>Catatan:</strong> {{ $c->review_notes }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="st-pill st-pill--plain {{ $statusPillCls }}">
                                                {{ \App\Models\AffiliateContent::statusOptions()[$c->status]['label'] ?? $c->status }}
                                            </span>
                                            @if($c->reviewed_at)
                                                <div class="st-muted" style="font-size:11px; margin-top:4px;">
                                                    <i class="fa fa-calendar"></i> {{ $c->reviewed_at->format('d/m/Y H:i') }}
                                                </div>
                                            @endif
                                        </td>
                                        <td nowrap>
                                            @if($c->status === 'pending')
                                                <form method="POST" action="{{ route('content.review.approve', $c->id) }}" style="display:inline;"
                                                      onsubmit="return confirm('Setujui konten ini?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="st-btn-approve" title="Setujui">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="st-btn-reject" title="Tolak"
                                                        data-toggle="modal" data-target="#rejectModal{{ $c->id }}">
                                                    <i class="fa fa-times"></i>
                                                </button>

                                                {{-- Reject Modal --}}
                                                <div class="modal fade st-modal" id="rejectModal{{ $c->id }}" tabindex="-1" role="dialog">
                                                    <div class="modal-dialog" role="document">
                                                        <form method="POST" action="{{ route('content.review.reject', $c->id) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title"><i class="fa fa-times-circle mr-2"></i>Tolak Konten</h5>
                                                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p class="mb-3" style="font-size:13px;">
                                                                        <strong>{{ $c->affiliate?->name }}</strong>
                                                                        @if($affType)
                                                                            <span class="st-pill st-pill--plain {{ $typePillCls }}">{{ strtoupper($affType) }}</span>
                                                                        @endif
                                                                        <br>
                                                                        <a href="{{ $c->content_url }}" target="_blank" rel="noopener" class="cr-url">
                                                                            <i class="fa fa-external-link-alt"></i> {{ \Illuminate\Support\Str::limit($c->content_url, 60) }}
                                                                        </a>
                                                                    </p>
                                                                    <div class="form-group">
                                                                        <label style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:0.4px;">
                                                                            Catatan Penolakan <span class="text-danger">*</span>
                                                                        </label>
                                                                        <textarea name="review_notes" class="form-control" rows="3" required minlength="3"
                                                                                  placeholder="Tulis alasan konten ditolak..."></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                                                                    <button type="submit" class="st-btn-reject">
                                                                        <i class="fa fa-times"></i> Tolak Konten
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="st-muted" style="font-size:11px;">
                                                    <i class="fa fa-user-check"></i> Oleh <strong>{{ $c->reviewer?->nama ?? '-' }}</strong>
                                                </div>
                                            @endif
                                            <div style="margin-top:6px;">
                                                <a href="{{ route('content.review.edit', $c->id) }}" class="st-btn-outline-brown" style="font-size:11px; padding:4px 8px;">
                                                    <i class="fa fa-edit"></i> Edit Review
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div class="st-empty">
                                                <div class="st-empty__icon"><i class="fa fa-inbox"></i></div>
                                                <div class="st-empty__title">Tidak ada konten untuk filter ini</div>
                                                <div class="st-empty__desc">Coba ubah filter atau reset pencarian kamu.</div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($contents->hasPages())
                        <div style="margin-top:14px;">
                            {{ $contents->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
