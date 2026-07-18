@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Absensi — Upload Data Mesin</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        {{-- ── Form upload ── --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-1"><i class="fa fa-upload text-muted"></i> Upload Export Fingerprint</h5>
                    <p class="text-muted mb-3" style="font-size:.85rem">
                        File mentah dari mesin (CSV/XLS), tanpa diedit. Cabang dipilih manual —
                        file mesin tidak menyimpan nama cabang.
                    </p>

                    <form action="{{ route('absensi.upload.file') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label for="branchSel" class="mb-1" style="font-weight:600">Cabang</label>
                            @if($lockedBranch)
                                @php $lb = $branches->firstWhere('id', $lockedBranch); @endphp
                                <input type="hidden" name="raw_branch_id" value="{{ $lockedBranch }}">
                                <input type="text" class="form-control form-control-sm" value="{{ $lb->name ?? '—' }}" readonly>
                            @else
                                <select name="raw_branch_id" id="branchSel" class="form-control form-control-sm" required>
                                    <option value="">— pilih cabang —</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" @selected(old('raw_branch_id') === $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="importFile" class="mb-1" style="font-weight:600">File</label>
                            <input type="file" name="file" id="importFile" class="form-control-file"
                                   accept=".csv,.txt,.xls,.xlsx" required>
                            <small class="text-muted">Maks 8 MB. Format: log per-scan (Bintaro) atau matriks (cabang lain) — terdeteksi otomatis.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa fa-upload"></i> Upload &amp; Proses
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body text-muted" style="font-size:.82rem">
                    <strong>Catatan.</strong> Upload ulang file yang sama aman — scan yang sudah ada
                    tidak digandakan (idempoten). Untuk deteksi edit, set rentang export mesin
                    <strong>2 bulan terakhir</strong>.
                </div>
            </div>
        </div>

        {{-- ── Riwayat upload ── --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fa fa-history text-muted"></i> Riwayat Upload</h5>

                    @if($batches->isEmpty())
                        <p class="text-muted mb-0">Belum ada upload.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Cabang</th>
                                        <th>Periode</th>
                                        <th>Format</th>
                                        <th class="text-right">Baru</th>
                                        <th class="text-right">Dup</th>
                                        <th class="text-right">Blm map</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($batches as $b)
                                        <tr>
                                            <td>{{ $b->branch->name ?? '—' }}</td>
                                            <td style="white-space:nowrap">
                                                {{ optional($b->periode_from)->format('d/m') }}–{{ optional($b->periode_to)->format('d/m/y') }}
                                            </td>
                                            <td><span class="badge badge-light">{{ $b->format }}</span></td>
                                            <td class="text-right">{{ $b->new_rows }}</td>
                                            <td class="text-right text-muted">{{ $b->dup_rows }}</td>
                                            <td class="text-right">
                                                @if($b->unmapped_rows)
                                                    <span class="text-warning">{{ $b->unmapped_rows }}</span>
                                                @else 0 @endif
                                            </td>
                                            <td style="white-space:nowrap">{{ optional($b->uploaded_at)->format('d/m H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
