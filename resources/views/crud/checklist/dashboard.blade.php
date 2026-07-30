@extends('layouts.app')

@section('content')
@php
    // ── Peta warna & aset — disamakan dengan handoff `01-dashboard` ──
    $DIVS = [
        'FO' => ['bg' => '#E9EFF9', 'color' => '#3C5FA8', 'icon' => 'fa-headset'],
        'GS' => ['bg' => '#E0EFEB', 'color' => '#1F6E5F', 'icon' => 'fa-broom'],
        'AS' => ['bg' => '#FBEAD3', 'color' => '#A66019', 'icon' => 'fa-plus-circle'],
    ];
    $AVA = ['#8A3A12','#A8481B','#2F8E55','#3C5FA8','#B5701A','#7A3415','#5A6B2E','#8E2419'];
    // status → warna pill (mengikuti ST prototipe)
    $stColors = [
        'neutral' => ['c' => '#9B8578', 'bg' => '#EFE7DE'],
        'slate'   => ['c' => '#9B8578', 'bg' => '#EFE7DE'],
        'warning' => ['c' => '#B5701A', 'bg' => '#FBEFD9'],
        'success' => ['c' => '#2F8E55', 'bg' => '#E3F4E8'],
        'danger'  => ['c' => '#C0392B', 'bg' => '#FBE3DE'],
        'coffee'  => ['c' => '#8A5A2B', 'bg' => '#F2E7D8'],
    ];
    $pctColor = fn ($p) => $p >= 80 ? '#2F8E55' : ($p >= 50 ? '#C9962F' : '#C0392B');
@endphp

<div class="container-fluid cl-dash">

    @include('layouts.notif')

    <div style="display:flex;flex-direction:column;gap:16px;max-width:1240px;width:100%;margin:0 auto">

        {{-- ─── HERO HEADER ─── --}}
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:18px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04)">
            <div style="width:46px;height:46px;flex:none;border-radius:13px;background:linear-gradient(135deg,#A66019,#5C2E15);display:flex;align-items:center;justify-content:center;box-shadow:0 6px 16px rgba(92,46,21,.25)">
                <i class="fas fa-clipboard-check" style="color:#FBEADB;font-size:19px"></i>
            </div>
            <div style="flex:1;min-width:220px">
                <div style="font-size:21px;font-weight:800;color:#3B1500">Monitoring Checklist Kegiatan</div>
                <div style="font-size:13px;color:#9A7256;margin-top:3px">Periode <b style="color:#5A3A22">{{ $today->translatedFormat('l, d F Y') }}</b> — pantau pengisian checklist akreditasi seluruh karyawan secara real-time.</div>
            </div>
            @if(canSeeAllBranches() && $branches->count())
                <form method="GET" action="{{ route('checklist') }}" style="flex:none;display:flex;align-items:center;gap:8px">
                    <i class="fas fa-map-marker-alt" style="color:#7A5B43;font-size:14px"></i>
                    <select name="branch" onchange="this.form.submit();" style="border:1px solid #E7D3BE;border-radius:11px;padding:10px 13px;font-family:inherit;font-size:13px;font-weight:600;color:#3B1500;background:#fff;cursor:pointer">
                        <option value="">— Semua Cabang —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- ─── KPI CARDS ─── --}}
        @include('crud.checklist._kpi_cards', ['kpis' => $kpis])

        {{-- ─── 2-PANEL SPLIT ─── --}}
        <div class="dash-2col">
            {{-- Progres per Divisi --}}
            <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(70,21,0,.04)">
                <div style="padding:15px 18px;border-bottom:1px solid #F1E6D7;font-size:14px;font-weight:800;color:#3B1500">Progres per Divisi</div>
                @if($perDivision->isEmpty() || $perDivision->sum('total') === 0)
                    <div style="padding:28px 18px;text-align:center;font-size:12.5px;color:#9A7256">
                        Belum ada karyawan aktif — <a href="{{ route('checklist.positions') }}" class="dash-link" style="color:#8A3A12;font-weight:700">set posisi karyawan</a> dulu.
                    </div>
                @else
                    @foreach($perDivision as $row)
                        @php
                            $divKey = $row->division->id === 'FO' ? 'FO' : ($row->division->id === 'GS' ? 'GS' : 'AS');
                            $dv     = $DIVS[$divKey];
                            $pc     = $pctColor($row->pct);
                        @endphp
                        <div style="padding:14px 18px;border-bottom:1px solid #F6EEE1;display:flex;flex-direction:column;gap:8px">
                            <div style="display:flex;align-items:center;gap:11px">
                                <div style="width:32px;height:32px;flex:none;border-radius:9px;background:{{ $dv['bg'] }};display:flex;align-items:center;justify-content:center">
                                    <i class="fas {{ $dv['icon'] }}" style="color:{{ $dv['color'] }};font-size:14px"></i>
                                </div>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:13.5px;font-weight:800;color:#3B1500">{{ $row->division->name }}</div>
                                    <div style="font-size:11.5px;color:#9A7256;font-variant-numeric:tabular-nums">{{ $row->submitted }}/{{ $row->total }} submit @if($row->excused > 0)· {{ $row->excused }} dikecualikan @endif</div>
                                </div>
                                <span style="font-size:12px;font-weight:800;padding:3px 11px;border-radius:20px;flex:none;background:{{ $pc }}1A;color:{{ $pc }};font-variant-numeric:tabular-nums">{{ $row->pct }}%</span>
                            </div>
                            <div style="height:8px;border-radius:6px;background:#F1E6D7;overflow:hidden">
                                <div style="height:100%;width:{{ $row->pct }}%;background:{{ $pc }};border-radius:6px;transition:width .3s"></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- Aktivitas Terbaru --}}
            <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(70,21,0,.04)">
                <div style="display:flex;align-items:center;padding:15px 18px;border-bottom:1px solid #F1E6D7">
                    <span style="font-size:14px;font-weight:800;color:#3B1500">Aktivitas Terbaru</span>
                    <a href="{{ route('checklist.review') }}" class="dash-link" style="margin-left:auto;font-size:12px;font-weight:700;color:#8A3A12">Lihat semua →</a>
                </div>
                @if($recent->isEmpty())
                    <div style="padding:28px 18px;text-align:center;font-size:12.5px;color:#9A7256">Belum ada aktivitas.</div>
                @else
                    @foreach($recent as $r)
                        @php
                            $opt      = \App\Models\ChecklistSubmission::statusOptions()[$r->status] ?? ['label' => $r->status, 'color' => 'neutral'];
                            $sc       = $stColors[$opt['color']] ?? $stColors['neutral'];
                            $avName   = trim($r->user?->nama ?? '');
                            $avParts  = preg_split('/\s+/', $avName, -1, PREG_SPLIT_NO_EMPTY);
                            $avInit   = strtoupper(mb_substr($avParts[0] ?? '?', 0, 1) . (isset($avParts[1]) ? mb_substr($avParts[1], 0, 1) : ''));
                            $avColor  = $AVA[(int) ($r->user?->id ?? 0) % 8];
                        @endphp
                        <a href="{{ route('checklist.review.show', $r->id) }}" class="dash-row" style="display:flex;align-items:center;gap:12px;padding:11px 18px;border-bottom:1px solid #F6EEE1;text-decoration:none">
                            <div style="width:36px;height:36px;flex:none;border-radius:50%;background:{{ $avColor }};color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px">{{ $avInit }}</div>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:700;color:#3B1500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $r->user?->nama ?? '—' }} <span style="font-weight:600;color:#9A7256">· {{ $r->division?->name ?? $r->division_id }}</span></div>
                                <div style="font-size:11.5px;color:#9A7256;margin-top:1px;font-variant-numeric:tabular-nums">{{ $r->submission_date->translatedFormat('d M Y') }} · {{ $r->done_tasks }}/{{ $r->total_tasks }} tugas @if($r->branch)· {{ $r->branch->name }} @endif</div>
                            </div>
                            <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:{{ $sc['bg'] }};color:{{ $sc['c'] }};white-space:nowrap;flex:none">{{ $opt['label'] }}</span>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- ─── QUICK ACTIONS ─── --}}
        <div class="dash-actions">
            <a href="{{ route('checklist.positions') }}" class="dash-row" style="display:flex;align-items:center;gap:15px;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:17px 20px;text-decoration:none;box-shadow:0 1px 2px rgba(70,21,0,.04)">
                <div style="width:42px;height:42px;flex:none;border-radius:12px;background:#F4E7D6;display:flex;align-items:center;justify-content:center">
                    <i class="fas fa-user-tag" style="color:#8A3A12;font-size:18px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:14.5px;font-weight:800;color:#3B1500">Posisi Karyawan</div>
                    <div style="font-size:12px;color:#9A7256;margin-top:2px">Assign karyawan ke divisi FO / GS / Asdent + cabang</div>
                </div>
                <i class="fas fa-chevron-right" style="color:#C9AE94;font-size:14px"></i>
            </a>
            <a href="{{ route('checklist.setup') }}" class="dash-row" style="display:flex;align-items:center;gap:15px;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:17px 20px;text-decoration:none;box-shadow:0 1px 2px rgba(70,21,0,.04)">
                <div style="width:42px;height:42px;flex:none;border-radius:12px;background:#E3EFEC;display:flex;align-items:center;justify-content:center">
                    <i class="fas fa-tasks" style="color:#1F6E5F;font-size:18px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:14.5px;font-weight:800;color:#3B1500">Setup Tugas Checklist</div>
                    <div style="font-size:12px;color:#9A7256;margin-top:2px">Atur kategori &amp; task per divisi — Harian &amp; Mingguan</div>
                </div>
                <i class="fas fa-chevron-right" style="color:#C9AE94;font-size:14px"></i>
            </a>
            @php
                // Jumlah unit AC yang butuh dicuci (terlambat + belum pernah dicuci),
                // scope cabang aktif, untuk badge quick-link.
                try {
                    $acLate = \App\Models\AcUnit::query()
                        ->withServiceSummary()
                        ->where('is_active', 1)
                        ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
                        ->get()
                        ->filter(fn ($u) => in_array($u->due_status, [\App\Models\AcUnit::STATUS_TERLAMBAT, \App\Models\AcUnit::STATUS_BELUM], true))
                        ->count();
                } catch (\Throwable $e) {
                    $acLate = 0;
                }
            @endphp
            <a href="{{ route('checklist.ac') }}" class="dash-row" style="display:flex;align-items:center;gap:15px;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:17px 20px;text-decoration:none;box-shadow:0 1px 2px rgba(70,21,0,.04)">
                <div style="width:42px;height:42px;flex:none;border-radius:12px;background:#E4F0F7;display:flex;align-items:center;justify-content:center">
                    <i class="fas fa-snowflake" style="color:#3C5FA8;font-size:18px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:14.5px;font-weight:800;color:#3B1500">Jadwal Cuci AC</div>
                    <div style="font-size:12px;color:#9A7256;margin-top:2px">Monitoring cuci &amp; isi freon AC seluruh cabang</div>
                </div>
                 @if($acLate > 0)
                    <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:800;padding:4px 11px;border-radius:20px;background:#FBE3DE;color:#C0392B;white-space:nowrap;flex:none">{{ $acLate }} perlu dicuci</span>
                 @endif
                <i class="fas fa-chevron-right" style="color:#C9AE94;font-size:14px"></i>
            </a>
        </div>

    </div>
</div>

<style>
    .dash-kpis    { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:13px; }
    .dash-2col    { display:grid; grid-template-columns:1.1fr 1fr; gap:14px; align-items:start; }
    .dash-actions { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
    .dash-row     { transition:background .12s ease; color:inherit; }
    .dash-row:hover { background:#FBF4EC !important; color:inherit; text-decoration:none; }
    .dash-link { transition:color .15s ease; }
    .dash-link:hover { color:#6B2400; text-decoration:none; }
    @media (max-width:1100px) { .dash-kpis { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:900px)  { .dash-2col { grid-template-columns:1fr; } }
    @media (max-width:960px)  { .dash-actions { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:640px)  { .dash-actions { grid-template-columns:1fr; } }
    @media (max-width:540px)  { .dash-kpis { grid-template-columns:1fr; } }
</style>
@endsection
