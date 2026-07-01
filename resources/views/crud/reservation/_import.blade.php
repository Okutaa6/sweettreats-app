@extends('layouts.app')

@section('css')
{{-- Redesign reservasi CSS — diperlukan untuk class `rr-drawer--form` dan
     `rr-drawer__panel` yg dipakai di drawer "Edit & Schedule Reservasi".
     Layout/app.blade.php tidak auto-load (per komentar line 80) — harus
     di-load eksplisit per page. --}}
<link rel="stylesheet" href="{{ asset('assets/css/reservasi-redesign.css') }}?v={{ @filemtime(public_path('assets/css/reservasi-redesign.css')) ?: '1' }}">
@endsection

@section('content')
@php
    $isViewOnly = $isViewOnly ?? false;
    $activeBranchName = $activeBranch?->name ?? '—';
    $hasActiveBatch = !empty($activeBatchId);
@endphp

<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Reservation</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ── Auto-process summary result (after redirect) ── --}}
    @if(session('auto_process_summary'))
        @php
            $apSummary = session('auto_process_summary');
        @endphp
        <div class="st-import-auto-result">
            <div class="st-import-auto-result__head">
                <div>
                    <h6 class="st-import-auto-result__title"><i class="fa fa-bolt"></i> Hasil Auto-Process</h6>
                    <p class="st-import-auto-result__sub">Engine selesai mem-proses semua baris pending.</p>
                </div>
                <button type="button" class="st-btn-icon-muted" onclick="this.closest('.st-import-auto-result').remove();" title="Tutup">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="st-import-auto-result__stats">
                <div class="st-import-auto-stat is-promoted">
                    <div class="st-import-auto-stat__num">{{ $apSummary['promoted'] }}</div>
                    <div class="st-import-auto-stat__lbl"><i class="fa fa-check-circle"></i> Reservasi dibuat</div>
                </div>
                <div class="st-import-auto-stat is-skipped">
                    <div class="st-import-auto-stat__num">{{ $apSummary['skipped'] }}</div>
                    <div class="st-import-auto-stat__lbl"><i class="fa fa-ban"></i> Di-skip</div>
                </div>
                <div class="st-import-auto-stat is-review">
                    <div class="st-import-auto-stat__num">{{ $apSummary['review'] }}</div>
                    <div class="st-import-auto-stat__lbl"><i class="fa fa-eye"></i> Perlu review</div>
                </div>
                @if(($apSummary['errors'] ?? 0) > 0)
                    <div class="st-import-auto-stat is-error">
                        <div class="st-import-auto-stat__num">{{ $apSummary['errors'] }}</div>
                        <div class="st-import-auto-stat__lbl"><i class="fa fa-exclamation-circle"></i> Error</div>
                    </div>
                @endif
            </div>
            @php
                $reviewReasons = collect($apSummary['details'] ?? [])
                    ->where('action', 'review')
                    ->groupBy('reason')
                    ->map->count()
                    ->sortDesc();
            @endphp
            @if($reviewReasons->count())
                <div class="st-import-auto-result__reasons">
                    <strong>Alasan perlu review:</strong>
                    @foreach($reviewReasons as $reason => $count)
                        <span class="st-import-auto-reason">{{ $count }}× {{ str_replace('_', ' ', $reason) }}</span>
                    @endforeach
                </div>
            @endif
            <div class="st-import-auto-result__cta">
                @if(($apSummary['promoted'] ?? 0) > 0)
                    <a href="{{ route('rawreservation') }}" class="st-btn-brand">
                        <i class="fa fa-external-link-alt"></i> Lihat di List Reservasi
                    </a>
                @endif
                @if(($apSummary['review'] ?? 0) > 0)
                    <a href="{{ route('rawreservation.import', ['batch_id' => $activeBatchId, 'state' => 'pending']) }}" class="st-btn-outline-brown">
                        <i class="fa fa-eye"></i> Review {{ $apSummary['review'] }} Baris
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissable fade show" role="alert" style="background:#FFF7E8;border:1px solid #E5C896;color:#8A5A1F;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Diagnosa kolom CSV:</strong>
            @foreach(explode(' | ', session('warning')) as $line)
                <div style="margin-left:24px;font-size:0.9em;">— {{ $line }}</div>
            @endforeach
            <div style="margin-top:6px;font-size:0.85em;">
                Tip: pastikan header CSV ada kolom <em>"pasien rata"</em> / <em>"Jam Mulai"</em> / <em>"Jam Praktek"</em> untuk jam praktek dokter.
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">

                    {{-- ── Toolbar + Tab toggle (re-use pattern dari list.blade.php) ── --}}
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap" style="gap:8px;">
                        <div class="d-flex align-items-center" style="gap:8px;">
                            <a href="{{ route('rawreservation') }}" class="st-btn-outline-brown" title="Kembali ke list reservasi">
                                <i class="fa fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                        <div class="d-flex align-items-center" style="gap:6px;font-size:0.95em;color:#7A6552;">
                            <i class="fa fa-file-import"></i>
                            <strong>Impor Sheets</strong>
                        </div>
                    </div>

                    {{-- ── Stepper: alur 3-langkah supaya user paham flow ── --}}
                    <div class="st-import-stepper">
                        <div class="st-import-stepper__item is-active">
                            <span class="st-import-stepper__num">1</span>
                            <div>
                                <div class="st-import-stepper__title">Upload File</div>
                                <small class="text-muted">CSV / XLSX dari Google Sheets</small>
                            </div>
                        </div>
                        <span class="st-import-stepper__sep"><i class="fa fa-chevron-right"></i></span>
                        <div class="st-import-stepper__item {{ $hasActiveBatch ? 'is-active' : '' }}">
                            <span class="st-import-stepper__num">2</span>
                            <div>
                                <div class="st-import-stepper__title">Pilah Pasien</div>
                                <small class="text-muted">Cek + edit data sebelum schedule</small>
                            </div>
                        </div>
                        <span class="st-import-stepper__sep"><i class="fa fa-chevron-right"></i></span>
                        <div class="st-import-stepper__item">
                            <span class="st-import-stepper__num">3</span>
                            <div>
                                <div class="st-import-stepper__title">Schedule</div>
                                <small class="text-muted">Pilih slot + layanan → reservasi resmi</small>
                            </div>
                        </div>
                    </div>

                    {{-- ── Hero card: Upload form (drag-drop visual) ── --}}
                    <div class="st-import-hero">
                        <div class="st-import-hero__head">
                            <div class="st-import-hero__head-row">
                                <div>
                                    <h5 class="st-import-hero__title">
                                        <i class="fa fa-cloud-upload-alt"></i> Impor Jadwal Reservasi
                                    </h5>
                                    <p class="st-import-hero__sub">
                                        Upload jadwal sheets cabang — diparsing ke staging,
                                        Anda pilah satu per satu sebelum jadi reservasi resmi.
                                    </p>
                                </div>
                                <button type="button" class="st-import-help-toggle" id="toggleImportHelp" aria-expanded="false">
                                    <i class="fa fa-question-circle"></i> Cara Pakai
                                </button>
                            </div>

                            {{-- Cara pakai (collapsible) --}}
                            <div class="st-import-help" id="importHelpPanel" hidden>
                                <div class="st-import-help__step">
                                    <strong>1. Upload file</strong> CSV / XLSX dari Google Sheets cabang.
                                    Format wajib: header mengandung kolom <em>"Nama Pasien"</em> dan <em>"Tanggal"</em>.
                                </div>
                                <div class="st-import-help__step">
                                    <strong>2. Pilah pasien</strong> di tabel staging. Baris noise
                                    (LIBUR, DRG CUTI, JADWAL DOK) sudah auto-skip. Cek badge:
                                    <span class="st-import-patient-badge is-existing"><i class="fa fa-user-check"></i> Lama</span>
                                    = pasien sudah ada di DB,
                                    <span class="st-import-patient-badge is-new"><i class="fa fa-user-plus"></i> Baru</span>
                                    = belum ada.
                                </div>
                                <div class="st-import-help__step">
                                    <strong>3. Klik baris</strong> → buka drawer edit → klik
                                    <strong>"Edit &amp; Schedule Reservasi"</strong> untuk pilih slot + layanan,
                                    atau <strong>"Quick Schedule"</strong> untuk promote langsung.
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('rawreservation.import.upload') }}" method="POST" enctype="multipart/form-data" class="st-import-hero__form" id="importUploadForm">
                            @csrf
                            {{-- Hidden inputs di-isi oleh Wizard sebelum submit --}}
                            <input type="hidden" name="column_mapping" id="mappingHiddenInput" value="">
                            <input type="hidden" name="header_row_index" id="headerRowHiddenInput" value="">
                            <div class="st-import-hero__meta">
                                <div class="st-import-hero__meta-item">
                                    <i class="fa fa-store"></i>
                                    <span>Cabang: <strong>{{ $activeBranchName }}</strong></span>
                                </div>
                                @if(canSeeAllBranches() && !session('raw_branch_id'))
                                    <div class="st-import-hero__meta-item">
                                        <select name="branch_id" class="form-control form-control-sm" required>
                                            <option value="">Pilih cabang…</option>
                                            @foreach($branches as $b)
                                                <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>

                            {{-- Drop zone (visual) --}}
                            <label for="importFile" class="st-import-dropzone" id="importDropzone">
                                <div class="st-import-dropzone__icon"><i class="fa fa-file-csv"></i></div>
                                <div class="st-import-dropzone__title" id="dropzoneTitle">Klik untuk pilih file</div>
                                <div class="st-import-dropzone__sub" id="dropzoneSub">atau drag &amp; drop file CSV / XLSX di sini</div>
                                <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required id="importFile" class="st-import-dropzone__input">
                            </label>

                            <div class="st-import-hero__submit">
                                <button type="submit" class="st-btn-brand st-import-hero__submit-btn" id="importDirectBtn">
                                    <i class="fa fa-upload"></i> Upload &amp; Parse
                                </button>
                                <button type="button" class="st-btn-outline-brown" id="importWizardBtn" title="Pilih mapping kolom manual (kalau auto-detect tidak fit)" style="display:none;">
                                    <i class="fa fa-table"></i> Pilih Kolom Manual…
                                </button>
                                <small class="st-import-hero__note-inline">
                                    <i class="fa fa-info-circle"></i> Maks 5MB. Auto-detect handle sebagian besar format CSV; klik <strong>"Pilih Kolom Manual"</strong> kalau format custom.
                                </small>
                            </div>
                        </form>
                    </div>

                    {{-- ── Section: Batch terbaru (dgn progress bar) ── --}}
                    @if($batches->count())
                        <div class="st-import-section">
                            <div class="st-import-section__head">
                                <h6><i class="fa fa-history"></i> Batch Upload Terbaru</h6>
                                <small class="text-muted">Klik batch untuk lihat staging-nya</small>
                            </div>
                            <div class="st-import-batch-list">
                                @foreach($batches as $b)
                                    @php
                                        $parsed = (int) $b->parsed_rows;
                                        $promoted = (int) $b->promoted_rows;
                                        $skipped = (int) $b->skipped_rows;
                                        $pending = max(0, $parsed - $promoted - $skipped);
                                        $pct = $parsed > 0 ? round(($promoted / $parsed) * 100) : 0;
                                    @endphp
                                    <div class="st-import-batch{{ $activeBatchId === $b->id ? ' is-active' : '' }}">
                                        <a href="{{ route('rawreservation.import', ['batch_id' => $b->id]) }}" class="st-import-batch__link">
                                            <div class="st-import-batch__row">
                                                <div class="st-import-batch__name">
                                                    <i class="fa fa-file-csv"></i>
                                                    <span title="{{ $b->file_name }}">{{ \Illuminate\Support\Str::limit($b->file_name, 50) }}</span>
                                                    @if($activeBatchId === $b->id)
                                                        <span class="st-import-batch__badge-active">AKTIF</span>
                                                    @endif
                                                </div>
                                                <div class="st-import-batch__meta">
                                                    <span title="Tanggal upload"><i class="fa fa-clock" style="font-size:0.85em;opacity:0.7;"></i> {{ optional($b->uploaded_at)->format('d M Y · H:i') }}</span>
                                                </div>
                                            </div>
                                            <div class="st-import-batch__progress">
                                                <div class="st-import-batch__progress-bar">
                                                    <div class="st-import-batch__progress-fill" style="width: {{ $pct }}%;" title="{{ $pct }}% sudah di-schedule"></div>
                                                </div>
                                                <div class="st-import-batch__progress-meta">
                                                    <span><strong>{{ $promoted }}</strong>/{{ $parsed }} promoted ({{ $pct }}%)</span>
                                                    <span class="text-muted">·</span>
                                                    <span title="Belum diproses"><i class="fa fa-circle" style="font-size:0.5em;color:#B5701A;"></i> {{ $pending }} pending</span>
                                                    @if($skipped > 0)
                                                        <span class="text-muted">·</span>
                                                        <span title="Di-skip"><i class="fa fa-ban" style="font-size:0.8em;opacity:0.6;"></i> {{ $skipped }} skip</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </a>
                                        <form action="{{ route('rawreservation.import.batch.destroy', $b->id) }}" method="POST" class="st-import-batch__action"
                                            onsubmit="return confirm('Hapus batch ini beserta semua baris staging?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="st-btn-icon-danger" title="Hapus batch">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- ── Re-Match Dokter: kalau ada row pending tanpa dokter resolved ── --}}
                    @if($hasActiveBatch)
                        @php
                            $unmatchedDoctorCount = \App\Models\RawCusReservationStaging::where('batch_id', $activeBatchId)
                                ->where('state', 'pending')
                                ->whereNull('resolved_raw_doctor_id')
                                ->whereNotNull('doctor_name_raw')
                                ->count();
                        @endphp
                        @if($unmatchedDoctorCount > 0)
                            <div class="st-import-rematch-cta">
                                <div class="st-import-rematch-cta__body">
                                    <i class="fa fa-user-md"></i>
                                    <div>
                                        <strong>{{ $unmatchedDoctorCount }} baris</strong> punya nama dokter yg belum match dgn master.
                                        Tambah dokter ke master Dokter dulu, lalu klik tombol ini untuk re-match tanpa upload ulang.
                                    </div>
                                </div>
                                <div class="st-import-rematch-cta__actions">
                                    <a href="{{ route('rawdoctor') }}" target="_blank" class="st-btn-outline-brown" rel="noopener">
                                        <i class="fa fa-external-link-alt"></i> Buka Master Dokter
                                    </a>
                                    <form action="{{ route('rawreservation.import.batch.rematch-doctors', $activeBatchId) }}" method="POST" style="margin:0;">
                                        @csrf
                                        <button type="submit" class="st-btn-brand">
                                            <i class="fa fa-sync-alt"></i> Re-Match Dokter
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    @endif

                    {{-- ── Auto-process CTA card (kalau ada baris pending) ── --}}
                    @if($hasActiveBatch && ($counters['pending'] ?? 0) > 0)
                        @php
                            $hasActiveFilter = $monthFilter !== '' || $dateFromFilter !== '' || $dateToFilter !== '' || $searchFilter !== '';
                            $filterLabel = '';
                            if ($monthFilter !== '') {
                                $filterLabel = 'bulan ' . \Carbon\Carbon::createFromFormat('Y-m', $monthFilter)->translatedFormat('F Y');
                            } elseif ($dateFromFilter !== '' || $dateToFilter !== '') {
                                $filterLabel = 'rentang ' . ($dateFromFilter ?: '...') . ' s/d ' . ($dateToFilter ?: '...');
                            } elseif ($searchFilter !== '') {
                                $filterLabel = 'pencarian "' . $searchFilter . '"';
                            }
                        @endphp
                        <div class="st-import-auto-cta">
                            <div class="st-import-auto-cta__icon"><i class="fa fa-bolt"></i></div>
                            <div class="st-import-auto-cta__body">
                                <h6>Proses Otomatis Tersedia</h6>
                                <p>Engine bisa auto-promote baris yg datanya lengkap (pasien + dokter + jam) ke reservasi resmi.
                                Baris yg perlu cek manual (dokter tidak match, slot penuh, dll) akan ditandai dgn alasan jelas.</p>
                                @if($hasActiveFilter)
                                    <div class="st-import-auto-cta__filter">
                                        <i class="fa fa-filter"></i> Hanya proses baris dgn filter aktif: <strong>{{ $filterLabel }}</strong>
                                        <a href="{{ route('rawreservation.import', ['batch_id' => $activeBatchId]) }}" class="st-import-auto-cta__filter-clear">
                                            <i class="fa fa-times"></i> hapus filter
                                        </a>
                                    </div>
                                @endif
                            </div>
                            <form action="{{ route('rawreservation.import.batch.auto-process', $activeBatchId) }}" method="POST"
                                onsubmit="if(!confirm('Auto-process {{ $filteredPendingCount }} baris pending{{ $hasActiveFilter ? ' (filter aktif: '.$filterLabel.')' : '' }}? Baris yg ambigu akan ditandai untuk review manual.\n\nProses bisa 1-2 menit untuk batch besar — jangan refresh.')) return false; this.querySelector('button').disabled = true; this.querySelector('button').innerHTML = '<i class=\'fa fa-spinner fa-spin\'></i> Memproses…';">
                                @csrf
                                {{-- Pass filter aktif ke endpoint auto-process supaya engine cuma
                                     proses subset yg user lihat di tabel. --}}
                                @if($monthFilter !== '')
                                    <input type="hidden" name="month" value="{{ $monthFilter }}">
                                @endif
                                @if($dateFromFilter !== '')
                                    <input type="hidden" name="date_from" value="{{ $dateFromFilter }}">
                                @endif
                                @if($dateToFilter !== '')
                                    <input type="hidden" name="date_to" value="{{ $dateToFilter }}">
                                @endif
                                @if($searchFilter !== '')
                                    <input type="hidden" name="q" value="{{ $searchFilter }}">
                                @endif
                                <button type="submit" class="st-btn-brand st-import-auto-btn" {{ $filteredPendingCount === 0 ? 'disabled' : '' }}>
                                    <i class="fa fa-bolt"></i>
                                    @if($hasActiveFilter)
                                        Proses ({{ $filteredPendingCount }} dari {{ $counters['pending'] }} baris)
                                    @else
                                        Proses Otomatis ({{ $filteredPendingCount }} baris)
                                    @endif
                                </button>
                            </form>
                        </div>
                    @endif

                    {{-- ── Section: Staging table ── --}}
                    @if($hasActiveBatch)
                        <div class="st-import-section">
                            <div class="st-import-section__head">
                                <h6><i class="fa fa-users"></i> Staging Pasien</h6>
                                {{-- Counter sekarang = filter chip clickable --}}
                                <div class="st-import-counters">
                                    @php
                                        $chipBase = ['batch_id' => $activeBatchId];
                                        if ($monthFilter) $chipBase['month'] = $monthFilter;
                                        if ($dateFromFilter) $chipBase['date_from'] = $dateFromFilter;
                                        if ($dateToFilter) $chipBase['date_to'] = $dateToFilter;
                                        if ($searchFilter) $chipBase['q'] = $searchFilter;
                                    @endphp
                                    <a href="{{ route('rawreservation.import', $chipBase + ['state' => 'pending']) }}"
                                        class="st-import-chip is-pending {{ $stateFilter === 'pending' ? 'is-selected' : '' }}"
                                        title="Klik untuk filter Pending">
                                        <i class="fa fa-circle"></i> Pending: <strong>{{ $counters['pending'] }}</strong>
                                    </a>
                                    <a href="{{ route('rawreservation.import', $chipBase + ['state' => 'promoted']) }}"
                                        class="st-import-chip is-promoted {{ $stateFilter === 'promoted' ? 'is-selected' : '' }}"
                                        title="Klik untuk filter Promoted">
                                        <i class="fa fa-check"></i> Promoted: <strong>{{ $counters['promoted'] }}</strong>
                                    </a>
                                    <a href="{{ route('rawreservation.import', $chipBase + ['state' => 'skipped']) }}"
                                        class="st-import-chip is-skipped {{ $stateFilter === 'skipped' ? 'is-selected' : '' }}"
                                        title="Klik untuk filter Skipped">
                                        <i class="fa fa-ban"></i> Skipped: <strong>{{ $counters['skipped'] }}</strong>
                                    </a>
                                    @if($counters['error'] > 0)
                                        <a href="{{ route('rawreservation.import', $chipBase + ['state' => 'error']) }}"
                                            class="st-import-chip is-error {{ $stateFilter === 'error' ? 'is-selected' : '' }}"
                                            title="Klik untuk filter Error">
                                            <i class="fa fa-exclamation-circle"></i> Error: <strong>{{ $counters['error'] }}</strong>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            {{-- Friendly hint: instruksi singkat --}}
                            <div class="st-import-howto">
                                <i class="fa fa-mouse-pointer"></i>
                                <span><strong>Klik baris</strong> pasien untuk edit data + jadwal reservasi-nya.
                                    Baris dgn <i class="fa fa-exclamation-triangle" style="color:#B5701A;"></i>
                                    berarti dokter belum ke-match — perlu dipilih manual via drawer Edit.</span>
                            </div>

                            {{-- Filter staging --}}
                            <form method="GET" action="{{ route('rawreservation.import') }}" class="st-import-filter">
                                <input type="hidden" name="batch_id" value="{{ $activeBatchId }}">
                                <div class="st-import-filter__row">
                                    <div class="st-import-filter__field">
                                        <label>State</label>
                                        <select name="state" class="form-control form-control-sm">
                                            @foreach(['pending' => 'Pending', 'promoted' => 'Promoted', 'skipped' => 'Skipped', 'error' => 'Error', 'all' => 'Semua'] as $k => $lbl)
                                                <option value="{{ $k }}" {{ $stateFilter === $k ? 'selected' : '' }}>{{ $lbl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="st-import-filter__field">
                                        <label>Bulan</label>
                                        <select name="month" class="form-control form-control-sm" onchange="if(this.value){this.form.date_from.value='';this.form.date_to.value='';}">
                                            <option value="">Semua bulan</option>
                                            @foreach($availableMonths as $ym)
                                                <option value="{{ $ym }}" {{ $monthFilter === $ym ? 'selected' : '' }}>
                                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->translatedFormat('F Y') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="st-import-filter__field">
                                        <label>Dari Tanggal</label>
                                        <input type="date" name="date_from" value="{{ $dateFromFilter }}" class="form-control form-control-sm" onchange="if(this.value)this.form.month.value='';">
                                    </div>
                                    <div class="st-import-filter__field">
                                        <label>Sampai Tanggal</label>
                                        <input type="date" name="date_to" value="{{ $dateToFilter }}" class="form-control form-control-sm" onchange="if(this.value)this.form.month.value='';">
                                    </div>
                                    <div class="st-import-filter__field st-import-filter__field--grow">
                                        <label>Cari</label>
                                        <input type="text" name="q" value="{{ $searchFilter }}" placeholder="Nama pasien / phone / dokter…" class="form-control form-control-sm">
                                    </div>
                                    <div class="st-import-filter__field st-import-filter__field--actions">
                                        <button type="submit" class="st-btn-outline-brown"><i class="fa fa-search"></i> Filter</button>
                                        <a href="{{ route('rawreservation.import', ['batch_id' => $activeBatchId]) }}" class="st-btn-icon-muted" title="Reset filter"><i class="fa fa-times"></i></a>
                                    </div>
                                </div>
                                <div class="st-import-filter__row" style="margin-top:8px;">
                                    <label class="st-import-check" style="margin:0;font-size:0.85em;">
                                        <input type="checkbox" name="hide_existing" value="1"
                                            {{ $hideExistingFilter ? 'checked' : '' }}
                                            onchange="this.form.submit()">
                                        Sembunyikan baris dgn pasien yg sudah ada reservasi (anti double-book)
                                    </label>
                                </div>
                            </form>

                            {{-- Bulk toolbar --}}
                            <form id="bulkForm" method="POST" class="st-import-bulk-toolbar">
                                @csrf
                                <div class="st-import-bulk-toolbar__left">
                                    <label class="st-import-check">
                                        <input type="checkbox" id="bulkCheckAll"> Pilih semua di halaman ini
                                    </label>
                                    <span id="bulkCount" class="st-import-bulk-count">0 dipilih</span>
                                </div>
                                <div class="st-import-bulk-toolbar__right">
                                    <button type="button" id="bulkPromoteBtn" class="st-btn-brand" disabled>
                                        <i class="fa fa-check-double"></i> Promote Terpilih
                                    </button>
                                    <button type="button" id="bulkSkipBtn" class="st-btn-outline-brown" disabled>
                                        <i class="fa fa-ban"></i> Skip Terpilih
                                    </button>
                                </div>
                            </form>

                            <div class="table-responsive st-import-table-wrap">
                                <table class="table table-sm st-import-table">
                                    <thead>
                                        <tr>
                                            <th style="width:32px;"></th>
                                            <th>Tanggal</th>
                                            <th>Jam</th>
                                            <th>Pasien</th>
                                            <th>Dokter</th>
                                            <th>Tindakan</th>
                                            <th>Status CSV</th>
                                            <th>State</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($stagingRows as $row)
                                        @php
                                            $isPending = $row->state === 'pending';
                                            $isPromoted = $row->state === 'promoted';
                                            $hasDoctorIssue = !$row->resolved_raw_doctor_id;
                                            $isWaiting = (int) $row->is_waiting === 1;
                                            $rowClass = '';
                                            if ($isWaiting) $rowClass = 'is-waiting';
                                            if ($hasDoctorIssue && $isPending) $rowClass = 'is-warn';
                                            if ($row->state === 'error') $rowClass = 'is-error';
                                            if ($row->hasDraft() && $isPending) $rowClass .= ' has-draft';

                                            // Phone-based dedup: cek apakah phone udah ada di raw_customer.
                                            $existing = $row->patient_phone
                                                ? ($existingByPhone[$row->patient_phone] ?? null)
                                                : null;
                                            $existingLastVisit = $existing && $existing->latestReservation
                                                ? optional($existing->latestReservation->date)?->format('d M Y')
                                                : null;

                                            // Existing upcoming reservasi untuk customer ini — kalau ada,
                                            // warn user supaya tidak double-schedule pasien yg sama.
                                            $upcomingResvs = ($existing && isset($upcomingByCustomer[$existing->id]))
                                                ? $upcomingByCustomer[$existing->id]
                                                : collect();
                                            $hasUpcoming = $upcomingResvs->isNotEmpty();
                                            // Cek kalau ada konflik tanggal sama persis (warning lebih tinggi)
                                            $hasSameDateResv = $hasUpcoming && $row->treatment_date
                                                ? $upcomingResvs->contains(
                                                    fn ($r) => optional($r->date)->format('Y-m-d') === $row->treatment_date->format('Y-m-d')
                                                )
                                                : false;
                                            if ($hasUpcoming && $isPending) {
                                                $rowClass .= ' has-existing-resv';
                                            }
                                            if ($hasSameDateResv && $isPending) {
                                                $rowClass .= ' has-same-date-resv';
                                            }
                                        @endphp
                                        <tr class="{{ $rowClass }} {{ $isPending ? 'js-row-click is-clickable' : '' }}"
                                            data-staging-id="{{ $row->id }}"
                                            data-patient-name="{{ $row->patient_name }}"
                                            data-patient-phone="{{ $row->patient_phone }}"
                                            data-patient-email="{{ $row->patient_email }}"
                                            data-patient-nik="{{ $row->patient_nik }}"
                                            data-branch-id="{{ $row->raw_branch_id }}"
                                            data-treatment-date="{{ $row->treatment_date?->format('Y-m-d') }}"
                                            data-start-hour="{{ $row->start_hour ? \Carbon\Carbon::parse($row->start_hour)->format('H:i') : '' }}"
                                            data-doctor-id="{{ $row->resolved_raw_doctor_id }}"
                                            data-status="{{ $row->mapped_status }}"
                                            data-tindakan="{{ $row->tindakan_text }}"
                                            data-waiting="{{ $row->is_waiting }}"
                                            data-promotable="{{ $row->isPromotable() ? '1' : '0' }}"
                                            data-raw-row="{{ json_encode($row->raw_row, JSON_UNESCAPED_UNICODE) }}"
                                            data-existing-id="{{ $existing?->id }}"
                                            data-existing-name="{{ $existing?->name }}"
                                            data-existing-email="{{ $existing?->email }}"
                                            data-existing-last-visit="{{ $existingLastVisit }}"
                                            data-existing-upcoming="{{ $hasUpcoming ? $upcomingResvs->map(fn($r) => [
                                                'date'    => optional($r->date)?->format('Y-m-d'),
                                                'time'    => $r->start_hour ? substr($r->start_hour, 0, 5) : null,
                                                'status'  => $r->status,
                                                'doctor'  => optional($r->doctor)->name,
                                                'same_date' => $row->treatment_date && optional($r->date)->format('Y-m-d') === $row->treatment_date->format('Y-m-d'),
                                            ])->toJson() : '' }}">
                                            <td class="st-import-nocell">
                                                @if($isPending)
                                                    <input type="checkbox" class="st-import-row-check" value="{{ $row->id }}">
                                                @endif
                                            </td>
                                            <td>
                                                @if($row->treatment_date)
                                                    {{ $row->treatment_date->format('d M Y') }}
                                                @elseif($isWaiting)
                                                    <span class="st-import-pill is-waiting">Waiting List</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($row->start_hour)
                                                    @php
                                                        $fromNote = is_array($row->parse_warnings) && in_array('time_from_note', $row->parse_warnings, true);
                                                    @endphp
                                                    <span class="st-import-hour {{ $fromNote ? 'is-from-note' : '' }}"
                                                        title="{{ $fromNote ? 'Diambil dari catatan pasien (request) — edit kalau clinic assign jam berbeda' : 'Jam mulai praktek dari CSV' }}">
                                                        {{ \Carbon\Carbon::parse($row->start_hour)->format('H:i') }}
                                                        @if($fromNote)
                                                            <i class="fa fa-question-circle" style="font-size:0.75em;opacity:0.7;margin-left:2px;"></i>
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="st-import-patient">
                                                    <strong>{{ $row->patient_name ?: '—' }}</strong>
                                                    @if($existing)
                                                        <span class="st-import-patient-badge is-existing" title="Pasien sudah ada di database — akan re-use record">
                                                            <i class="fa fa-user-check"></i> Lama
                                                        </span>
                                                    @elseif($row->patient_phone)
                                                        <span class="st-import-patient-badge is-new" title="Pasien baru — akan dibuat record baru saat promote">
                                                            <i class="fa fa-user-plus"></i> Baru
                                                        </span>
                                                    @endif

                                                    @if($hasSameDateResv)
                                                        <span class="st-import-patient-badge is-conflict" title="Sudah ada reservasi di tanggal yg sama — potensi double booking!">
                                                            <i class="fa fa-exclamation-circle"></i> Bentrok Tanggal
                                                        </span>
                                                    @elseif($hasUpcoming)
                                                        <span class="st-import-patient-badge is-busy" title="Pasien sudah punya {{ $upcomingResvs->count() }} reservasi mendatang — cek jadwal sebelum promote">
                                                            <i class="fa fa-calendar-check"></i> Sudah Booking ({{ $upcomingResvs->count() }})
                                                        </span>
                                                    @endif

                                                    @if($row->patient_phone)
                                                        <small class="text-muted d-block">{{ $row->patient_phone }}</small>
                                                    @endif
                                                    @if($row->patient_note)
                                                        <small class="text-muted d-block"><em>{{ \Illuminate\Support\Str::limit($row->patient_note, 60) }}</em></small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($row->doctor)
                                                    {{ $row->doctor->name }}
                                                @elseif($row->doctor_name_raw)
                                                    <div class="st-import-doctor-raw">
                                                        <span>{{ $row->doctor_name_raw }}</span>
                                                        <span class="st-import-doctor-pill" title="Klik baris untuk pilih dokter dari master">
                                                            <i class="fa fa-exclamation-triangle"></i> Belum match
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <small>{{ \Illuminate\Support\Str::limit($row->tindakan_text, 60) ?: '—' }}</small>
                                            </td>
                                            <td>
                                                <small>{{ $row->status_raw ?: '—' }}</small>
                                                @if($row->mapped_status)
                                                    <small class="d-block text-muted">→ {{ $row->mapped_status }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="st-import-pill is-{{ $row->state }}">
                                                    {{ ucfirst($row->state) }}
                                                </span>
                                                @if($row->hasDraft() && $isPending)
                                                    <span class="st-import-pill is-draft d-block mt-1" title="Form lengkap sudah disimpan sebagai draft — siap di-promote">
                                                        <i class="fa fa-bookmark"></i> Draft Siap
                                                    </span>
                                                @endif
                                                @php
                                                    // Surface alasan auto-process kalau row di-skip atau perlu review
                                                    $reviewReasonLabels = [
                                                        'doctor_not_resolved'         => 'Cek dokter',
                                                        'doctor_not_working_that_day' => 'Dokter off',
                                                        'all_slots_full'              => 'Slot penuh',
                                                        'slot_unavailable'            => 'Slot bermasalah',
                                                        'patient_name_missing'        => 'Nama kosong',
                                                        'patient_phone_missing'       => 'Phone kosong',
                                                        'treatment_date_missing'      => 'Tanggal kosong',
                                                        'csv_cancelled'               => 'CSV cancel',
                                                        'same_date_reservation_exists'=> 'Sudah ada di tgl sama',
                                                    ];
                                                    $autoReason = null;
                                                    if (is_array($row->parse_warnings)) {
                                                        foreach ($row->parse_warnings as $w) {
                                                            if (preg_match('/^(review|auto_skipped): (.+)$/', $w, $m)) {
                                                                $autoReason = $reviewReasonLabels[$m[2]] ?? str_replace('_', ' ', $m[2]);
                                                                break;
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                @if($autoReason)
                                                    <span class="st-import-review-tag" title="Alasan dari auto-process — handle manual">
                                                        <i class="fa fa-info-circle"></i> {{ $autoReason }}
                                                    </span>
                                                @endif
                                                @if($isPromoted && $row->promoted_to_id)
                                                    <a href="{{ route('rawreservation.edit', $row->promoted_to_id) }}" class="d-block st-import-link" title="Lihat reservasi hasil promote" onclick="event.stopPropagation();">
                                                        <small><i class="fa fa-external-link-alt"></i> Lihat</small>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fa fa-inbox" style="font-size: 2em; opacity: 0.4;"></i>
                                                <div>Tidak ada baris staging yang cocok dengan filter.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div>{{ $stagingRows->links() }}</div>
                        </div>
                    @else
                        <div class="st-import-empty">
                            <i class="fa fa-file-import"></i>
                            <h6>Belum ada batch upload</h6>
                            <p class="text-muted">Upload file CSV di atas untuk mulai impor jadwal cabang ini.</p>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Column Mapping Wizard Modal ──
     Muncul setelah file CSV dipilih. User pilih mapping kolom (kalau
     auto-detect tidak fit format CSV-nya). Submit lewat form upload
     dgn hidden input column_mapping (JSON). --}}
<div id="mappingWizard" class="st-drawer" aria-hidden="true">
    <div class="st-drawer__backdrop" data-mapping-close></div>
    <aside class="st-drawer__panel" role="dialog" aria-labelledby="mappingWizardTitle" style="width: min(720px, 100vw);">
        <header class="st-drawer__header">
            <h5 id="mappingWizardTitle" class="st-drawer__title">
                <i class="fa fa-table"></i> Atur Kolom CSV
            </h5>
            <button type="button" class="st-drawer__close" data-mapping-close aria-label="Tutup">
                <i class="fa fa-times"></i>
            </button>
        </header>
        <div class="st-drawer__body" style="gap: 16px;">
            <div class="st-mapping-hint">
                <i class="fa fa-info-circle"></i>
                Cek preview di bawah, lalu pilih kolom CSV mana untuk tiap field.
                <strong>Auto-detect sudah mencoba men-tebak</strong> — Anda hanya perlu fix yg salah.
                Header row di-pilih otomatis (biru); kalau salah, scroll preview dan klik nomor baris untuk set sebagai header.
            </div>

            <div class="st-mapping-section">
                <div class="st-mapping-section__title">Preview CSV (10 baris pertama)</div>
                <div class="st-mapping-preview-wrap">
                    <table id="mappingPreviewTable" class="st-mapping-preview">
                        <thead><tr id="mappingPreviewHead"></tr></thead>
                        <tbody id="mappingPreviewBody"></tbody>
                    </table>
                </div>
                <small class="text-muted"><i class="fa fa-mouse-pointer"></i> Klik nomor baris (kolom kiri) untuk pilih sebagai header row.</small>
            </div>

            <div class="st-mapping-section">
                <div class="st-mapping-section__title">Mapping Field → Kolom CSV</div>
                <div class="st-mapping-grid" id="mappingFields">
                    {{-- Diisi dgn JS, satu row per field --}}
                </div>
            </div>
        </div>
        <footer class="st-drawer__footer">
            <div class="st-drawer__actions-row">
                <button type="button" class="st-btn-outline-brown" data-mapping-close>Batal</button>
                <button type="button" id="mappingResetBtn" class="st-btn-outline-brown" title="Reset ke hasil auto-detect">
                    <i class="fa fa-undo"></i> Reset Auto
                </button>
                <button type="button" id="mappingConfirmBtn" class="st-btn-brand">
                    <i class="fa fa-check"></i> Pakai Mapping Ini &amp; Upload
                </button>
            </div>
        </footer>
    </aside>
</div>

{{-- ── Form Drawer (Schedule Reservasi lengkap) ── --}}
{{-- Markup + CSS class `rr-drawer--form` shared dgn list.blade.php — style sudah
     ada di reservasi-redesign.css. Isi form di-fetch via AJAX (?drawer=1, bare
     layout) → inject → run scripts → init reservation form. --}}
<div id="rrFormDrawer" class="rr-drawer rr-drawer--form" aria-hidden="true">
    <div class="rr-drawer__scrim" data-rrform-close></div>
    <aside class="rr-drawer__panel" role="dialog" aria-label="Form Reservasi">
        <div class="rr-drawer__head">
            <button type="button" class="rr-drawer__close" data-rrform-close aria-label="Tutup">&times;</button>
            <div class="rr-drawer__eyebrow">RESERVASI</div>
            <h3 class="rr-drawer__name" id="rrFormTitle">Reservasi</h3>
        </div>
        <div class="rr-drawer__body" id="rrFormBody">
            <div class="text-muted" style="padding:24px 4px;">
                <i class="fa fa-spinner fa-spin"></i> Memuat form…
            </div>
        </div>
    </aside>
</div>

{{-- ── Modal Debug Raw Row ── --}}
<div class="modal fade" id="stagingDebugModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index:1100;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-search"></i> Data CSV asli (raw row)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Setiap baris di bawah = satu kolom CSV dengan urutan asli (kolom 0, 1, 2, dst).
                    Gunakan untuk diagnosa kalau parser miss kolom tertentu (mis. Jam tidak ter-detect).
                </p>
                <table class="table table-sm" id="debugRawTable">
                    <thead><tr><th style="width:60px;">Col</th><th>Nilai</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ── Side Drawer: Edit Staging Row ── --}}
<div id="stagingEditDrawer" class="st-drawer" aria-hidden="true">
    <div class="st-drawer__backdrop" data-st-drawer-close></div>
    <aside class="st-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="stagingEditDrawerTitle">
        <header class="st-drawer__header">
            <h5 id="stagingEditDrawerTitle" class="st-drawer__title">
                <i class="fa fa-pen"></i> Edit Staging Row
            </h5>
            <button type="button" class="st-drawer__close" data-st-drawer-close aria-label="Tutup">
                <i class="fa fa-times"></i>
            </button>
        </header>
        <form id="stagingEditForm" method="POST" class="st-drawer__body">
            @csrf
            @method('PATCH')
            {{-- Match panel: muncul saat phone staging = phone customer existing --}}
            <div id="patientMatchPanel" class="st-drawer__match" style="display:none;">
                <div class="st-drawer__match-icon"><i class="fa fa-user-check"></i></div>
                <div class="st-drawer__match-body">
                    <div class="st-drawer__match-title">Pasien sudah terdaftar di database</div>
                    <div class="st-drawer__match-detail">
                        <strong id="matchName"></strong>
                        <span id="matchEmail" class="text-muted"></span>
                    </div>
                    <div id="matchLastVisit" class="st-drawer__match-meta"></div>
                    <div class="st-drawer__match-hint">
                        <i class="fa fa-info-circle"></i> Reservasi akan dibuat untuk pasien existing — tidak akan duplikat record.
                    </div>
                </div>
            </div>
            <div id="patientNewPanel" class="st-drawer__match is-new" style="display:none;">
                <div class="st-drawer__match-icon"><i class="fa fa-user-plus"></i></div>
                <div class="st-drawer__match-body">
                    <div class="st-drawer__match-title">Pasien Baru — Lengkapi Data</div>
                    <div class="st-drawer__match-hint">
                        <i class="fa fa-info-circle"></i> Belum ada nomor ini di database. Record pasien baru akan dibuat saat di-Schedule dgn data di bawah.
                    </div>
                </div>
            </div>

            {{-- Warning panel: pasien sudah punya reservasi mendatang --}}
            <div id="patientBusyPanel" class="st-drawer__match is-busy" style="display:none;">
                <div class="st-drawer__match-icon"><i class="fa fa-calendar-check"></i></div>
                <div class="st-drawer__match-body">
                    <div class="st-drawer__match-title" id="busyTitle">Pasien sudah punya reservasi</div>
                    <div class="st-drawer__match-hint" id="busyHint">
                        <i class="fa fa-info-circle"></i> Cek dulu sebelum schedule supaya tidak double booking.
                    </div>
                    <ul class="st-drawer__busy-list" id="busyList"></ul>
                </div>
            </div>

            {{-- ── Section: Data Pasien ── --}}
            <div class="st-drawer__section">
                <div class="st-drawer__section-title">
                    <i class="fa fa-user"></i> Data Pasien
                </div>
                <div class="st-drawer__field">
                    <label>Nama Pasien <span class="st-drawer__req">*</span></label>
                    <input type="text" name="patient_name" class="form-control form-control-sm" required placeholder="Nama lengkap pasien">
                </div>
                <div class="st-drawer__row">
                    <div class="st-drawer__field">
                        <label>No. Telp <span class="st-drawer__req">*</span></label>
                        <input type="text" name="patient_phone" class="form-control form-control-sm" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="st-drawer__field">
                        <label>NIK</label>
                        <input type="text" name="patient_nik" class="form-control form-control-sm" placeholder="16 digit (opsional)">
                    </div>
                </div>
                <div class="st-drawer__field">
                    <label>Email</label>
                    <input type="email" name="patient_email" class="form-control form-control-sm" placeholder="opsional">
                </div>
                <div class="st-drawer__field">
                    <label>Cabang <span class="st-drawer__req">*</span></label>
                    <select name="raw_branch_id" class="form-control form-control-sm">
                        <option value="">— Pilih cabang —</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                    <small class="st-drawer__hint">Cabang tempat pasien akan terdaftar saat di-Schedule.</small>
                </div>
            </div>

            {{-- ── Section: Jadwal Reservasi ── --}}
            <div class="st-drawer__section">
                <div class="st-drawer__section-title">
                    <i class="fa fa-calendar-day"></i> Jadwal Reservasi
                </div>
                <div class="st-drawer__field">
                    <label>Dokter</label>
                    <select name="resolved_raw_doctor_id" class="form-control form-control-sm">
                        <option value="">— Pilih dokter —</option>
                        @foreach($doctors as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="st-drawer__row">
                    <div class="st-drawer__field">
                        <label>Tanggal Treatment</label>
                        <input type="date" name="treatment_date" class="form-control form-control-sm">
                    </div>
                    <div class="st-drawer__field">
                        <label>Jam</label>
                        <input type="time" name="start_hour" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="st-drawer__field">
                    <label>Status</label>
                    <select name="mapped_status" class="form-control form-control-sm">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="completed">Completed</option>
                        <option value="waitlist">Waitlist</option>
                    </select>
                </div>
                <div class="st-drawer__field">
                    <label>Tindakan / Keterangan</label>
                    <textarea name="tindakan_text" class="form-control form-control-sm" rows="3" placeholder="Catatan tindakan / kondisi pasien"></textarea>
                </div>
                <div class="st-drawer__field">
                    <label class="st-import-check" style="margin:0;">
                        <input type="checkbox" name="is_waiting" value="1">
                        Jadikan waiting list (tanpa tanggal/jam wajib)
                    </label>
                </div>
            </div>
        </form>
        <footer class="st-drawer__footer">
            {{-- Tombol utama: buka form reservasi lengkap (slot picker, services, dll) --}}
            <a href="#" id="drawerScheduleFullBtn" class="st-btn-brand st-drawer__primary-btn" title="Buka form reservasi lengkap dgn slot picker + layanan, pre-filled dari row ini">
                <i class="fa fa-calendar-plus"></i> Edit &amp; Schedule Reservasi
                <i class="fa fa-arrow-right" style="margin-left:6px;opacity:0.7;"></i>
            </a>
            <div class="st-drawer__actions-row">
                <button type="button" id="drawerPromoteBtn" class="st-btn-icon-success" title="Schedule cepat tanpa atur slot/services (langsung pakai data row)">
                    <i class="fa fa-check"></i> Quick Schedule
                </button>
                <button type="button" id="drawerSkipBtn" class="st-btn-icon-danger" title="Skip baris ini">
                    <i class="fa fa-times"></i> Skip
                </button>
                <button type="button" id="drawerDebugBtn" class="st-btn-icon-muted" title="Lihat data CSV asli">
                    <i class="fa fa-search"></i> CSV Asli
                </button>
            </div>
            <div class="st-drawer__actions-row">
                <button type="button" class="st-btn-outline-brown" data-st-drawer-close>Batal</button>
                <button type="submit" form="stagingEditForm" class="st-btn-brand"><i class="fa fa-save"></i> Simpan Perubahan</button>
            </div>
        </footer>
    </aside>
</div>

{{-- ── Inline styles untuk komponen impor (scoped via class prefix `st-import-*`) ── --}}
<style>
/* ── Stepper alur 3-langkah ───────────────────────────────────────── */
.st-import-stepper {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 18px;
    padding: 14px 18px;
    background: #fff;
    border: 1px solid #E5D3B8;
    border-radius: 12px;
    flex-wrap: wrap;
}
.st-import-stepper__item {
    display: flex; align-items: center; gap: 10px;
    opacity: 0.55;
    transition: opacity 0.2s;
}
.st-import-stepper__item.is-active { opacity: 1; }
.st-import-stepper__num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    border-radius: 50%;
    background: #F0E6DA;
    color: #7A6552;
    font-weight: 700;
    font-size: 0.85em;
}
.st-import-stepper__item.is-active .st-import-stepper__num {
    background: linear-gradient(135deg, #B5701A, #8A5A1F);
    color: #fff;
    box-shadow: 0 2px 6px rgba(181,112,26,0.32);
}
.st-import-stepper__title { font-weight: 600; color: #5B4B3D; font-size: 0.92em; line-height: 1.1; }
.st-import-stepper__sep { color: #C9B89A; }

.st-import-hero {
    background: linear-gradient(135deg, #FBE7CF 0%, #F4ECE2 100%);
    border: 1px solid #E5D3B8;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 20px;
}
.st-import-hero__head-row {
    display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
    flex-wrap: wrap;
}
.st-import-hero__title { color: #6B4628; font-weight: 600; margin: 0 0 4px; }
.st-import-hero__sub { color: #5B4B3D; margin: 0 0 14px; font-size: 0.95em; }
.st-import-hero__meta { display:flex; gap:18px; flex-wrap:wrap; margin-bottom:12px; font-size:0.9em; color:#6B4628; }
.st-import-hero__meta-item { display:flex; align-items:center; gap:6px; }

/* Help toggle button */
.st-import-help-toggle {
    background: rgba(255,255,255,0.75);
    border: 1px solid #E5C896;
    color: #6B4628;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.85em;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
}
.st-import-help-toggle:hover { background: #fff; }
.st-import-help-toggle[aria-expanded="true"] { background: #fff; border-color: #B5701A; color: #8A5A1F; }
.st-import-help {
    margin-top: 14px;
    padding: 14px 16px;
    background: rgba(255,255,255,0.7);
    border: 1px dashed #C9B89A;
    border-radius: 10px;
}
.st-import-help__step {
    padding: 6px 0;
    font-size: 0.88em;
    color: #5B4B3D;
    line-height: 1.5;
}
.st-import-help__step + .st-import-help__step {
    border-top: 1px dashed #E5D3B8;
}
.st-import-help__step strong { color: #6B4628; }

/* Drop zone (visual only — pakai native file input via label) */
.st-import-dropzone {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px;
    padding: 26px 20px;
    background: rgba(255,255,255,0.65);
    border: 2px dashed #C9B89A;
    border-radius: 12px;
    color: #6B4628;
    cursor: pointer;
    transition: all 0.18s;
    margin-bottom: 12px;
    position: relative;
}
.st-import-dropzone:hover {
    background: #fff;
    border-color: #B5701A;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(181,112,26,0.12);
}
.st-import-dropzone.is-dragover {
    background: #FFF7E8;
    border-color: #B5701A;
    border-style: solid;
}
.st-import-dropzone.is-filled {
    background: #E3F4E8;
    border-color: #2F8E55;
    border-style: solid;
    color: #2F7E4D;
}
.st-import-dropzone__icon { font-size: 2em; color: #B5701A; }
.st-import-dropzone.is-filled .st-import-dropzone__icon { color: #2F8E55; }
.st-import-dropzone__title { font-weight: 600; font-size: 1em; }
.st-import-dropzone__sub { font-size: 0.85em; opacity: 0.75; }
.st-import-dropzone__input {
    position: absolute; opacity: 0; width: 0; height: 0;
}

.st-import-hero__submit {
    display: flex; justify-content: space-between; align-items: center;
    gap: 12px; flex-wrap: wrap;
}
.st-import-hero__submit-btn { padding: 10px 18px; font-weight: 600; }
.st-import-hero__note-inline { color: #7B5E3F; font-size: 0.82em; }
.st-import-hero__note-inline i { color: #B5701A; margin-right: 4px; }

.st-import-section { margin-top:20px; }
.st-import-section__head { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
.st-import-section__head h6 { margin:0; color:#5B4B3D; font-weight:600; }
.st-import-counters { display:flex; gap:8px; flex-wrap:wrap; }

.st-import-chip { padding:4px 10px; border-radius:999px; font-size:0.8em; background:#F0E6DA; color:#5B4B3D; border:1px solid transparent; }
.st-import-chip.is-pending { background:#FBE7CF; color:#8A5A1F; }
.st-import-chip.is-promoted { background:#E3F4E8; color:#2F7E4D; }
.st-import-chip.is-skipped { background:#EFE6DA; color:#7A6552; }
.st-import-chip.is-error { background:#FBE3DE; color:#A2382B; }

.st-import-batch-list { display:flex; flex-direction:column; gap:8px; }
.st-import-batch {
    display:flex; align-items:center; gap:8px; padding:12px 14px;
    background:#fff; border:1px solid #E5D3B8; border-radius:10px;
    transition: all 0.15s;
}
.st-import-batch:hover { border-color: #B5701A; }
.st-import-batch.is-active { border-color:#B5701A; background:#FFF7EC; box-shadow: 0 0 0 2px rgba(181,112,26,0.12); }
.st-import-batch__link { flex:1; display:flex; flex-direction:column; gap:8px; color:#5B4B3D; text-decoration:none; }
.st-import-batch__link:hover { color:#6B4628; }
.st-import-batch__row { display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; }
.st-import-batch__name { font-weight:600; display:flex; align-items:center; gap:8px; color:#5B4B3D; }
.st-import-batch__badge-active {
    display:inline-block; padding:2px 8px;
    background:linear-gradient(135deg, #B5701A, #8A5A1F); color:#fff;
    font-size:0.65em; font-weight:700;
    border-radius:999px; letter-spacing:0.06em;
}
.st-import-batch__meta { font-size:0.8em; color:#7A6552; }
.st-import-batch__action button { background:transparent; border:none; }

/* Progress bar per batch */
.st-import-batch__progress { display:flex; flex-direction:column; gap:4px; }
.st-import-batch__progress-bar {
    height:6px; background:#F0E6DA; border-radius:3px; overflow:hidden;
}
.st-import-batch__progress-fill {
    height:100%;
    background:linear-gradient(90deg, #2F8E55, #59B374);
    transition: width 0.4s ease;
}
.st-import-batch__progress-meta {
    display:flex; gap:8px; flex-wrap:wrap;
    font-size:0.78em; color:#7A6552;
    align-items:center;
}
.st-import-batch__progress-meta strong { color:#2F7E4D; }

/* Counter chips — clickable */
a.st-import-chip {
    text-decoration:none;
    cursor:pointer;
    transition: all 0.15s;
    display:inline-flex; align-items:center; gap:5px;
}
a.st-import-chip:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(91,75,61,0.18); text-decoration:none; }
a.st-import-chip.is-selected {
    outline: 2px solid #6B4628;
    outline-offset: 1px;
}
a.st-import-chip i { font-size: 0.65em; }

/* Friendly how-to hint above table */
.st-import-howto {
    display:flex; align-items:flex-start; gap:10px;
    padding:10px 14px;
    background:#FFF7EC;
    border:1px dashed #E5C896;
    border-radius:8px;
    color:#6B4628;
    font-size:0.85em;
    margin-bottom:10px;
}
.st-import-howto i.fa-mouse-pointer { color:#B5701A; padding-top:2px; }

/* Doctor "Belum match" pill (lebih jelas dari sekedar ⚠) */
.st-import-doctor-pill {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 7px;
    border-radius: 999px;
    background: #FBE7CF;
    color: #8A5A1F;
    border: 1px solid #E5C896;
    font-size: 0.7em;
    font-weight: 600;
    white-space: nowrap;
}
.st-import-doctor-pill i { font-size: 0.85em; margin-right: 2px; }

.st-import-filter { margin-bottom:12px; background:#F4ECE2; border-radius:8px; padding:10px 12px; }
.st-import-filter__row { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
.st-import-filter__field { display:flex; flex-direction:column; min-width:140px; }
.st-import-filter__field--grow { flex:1 1 240px; }
.st-import-filter__field--actions { flex-direction:row; align-items:center; gap:6px; }
.st-import-filter__field label { font-size:0.78em; color:#6B4628; margin-bottom:3px; }

.st-import-bulk-toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:8px 12px; background:#fff; border:1px dashed #E5D3B8; border-radius:8px; margin-bottom:8px; flex-wrap:wrap; }
.st-import-bulk-toolbar__left { display:flex; gap:14px; align-items:center; }
.st-import-bulk-toolbar__right { display:flex; gap:8px; flex-wrap:wrap; }
.st-import-bulk-count { color:#7A6552; font-size:0.9em; }
.st-import-check { display:inline-flex; gap:6px; align-items:center; cursor:pointer; user-select:none; margin:0; }

.st-import-table th { background:#F4ECE2; color:#6B4628; font-weight:600; font-size:0.85em; }
.st-import-table td { vertical-align: middle; font-size:0.9em; }
.st-import-table tr.is-warn { background:#FFF7E8; }
.st-import-table tr.is-waiting { background:#F0E6DA; }
.st-import-table tr.is-error { background:#FBE3DE; }

.st-import-pill { padding:3px 8px; border-radius:999px; font-size:0.75em; font-weight:600; display:inline-block; }
.st-import-pill.is-pending { background:#FBE7CF; color:#8A5A1F; }
.st-import-pill.is-promoted { background:#E3F4E8; color:#2F7E4D; }
.st-import-pill.is-skipped { background:#EFE6DA; color:#7A6552; }
.st-import-pill.is-error { background:#FBE3DE; color:#A2382B; }
.st-import-pill.is-waiting { background:#DFEDE9; color:#2E7D74; }
.st-import-pill.is-draft {
    background: linear-gradient(135deg, #6B4628, #8A5A1F);
    color: #fff;
    box-shadow: 0 2px 5px rgba(91,75,61,0.22);
}
.st-import-pill.is-draft i { margin-right: 3px; }
.st-import-review-tag {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 7px;
    background: #FFF7EC;
    color: #8A5A1F;
    border: 1px solid #E5C896;
    border-radius: 6px;
    font-size: 0.7em;
    font-weight: 500;
    white-space: nowrap;
}
.st-import-review-tag i { margin-right: 3px; font-size: 0.85em; }
/* Row dgn draft = highlight halus + border kiri */
.st-import-table tr.has-draft {
    border-left: 3px solid #B5701A;
    background: linear-gradient(90deg, rgba(251,231,207,0.4) 0%, transparent 60%) !important;
}

.st-import-hour {
    display:inline-block;
    padding:3px 8px;
    background:#FBE7CF;
    color:#8A5A1F;
    border-radius:6px;
    font-weight:600;
    font-variant-numeric: tabular-nums;
    letter-spacing: 0.02em;
}
/* Jam yang di-extract dari catatan pasien (bukan slot resmi) — kasih
   visual lebih lembut + ikon tanda-tanya. */
.st-import-hour.is-from-note {
    background:#F0E6DA;
    color:#7A6552;
    font-style:italic;
    border:1px dashed #B5701A;
}

.st-import-warning { color:#B5701A; margin-left:4px; }
.st-import-doctor-raw { display:inline-flex; align-items:center; gap:4px; }
.st-import-link { color:#2F7E4D; }
.st-import-actions { display:flex; gap:4px; }

.st-btn-icon-success, .st-btn-icon-muted, .st-btn-icon-danger {
    border:none; padding:6px 10px; border-radius:6px; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center;
}
.st-btn-icon-success { background:#E3F4E8; color:#2F7E4D; }
.st-btn-icon-success:hover:not([disabled]) { background:#CFE8D6; }
.st-btn-icon-success[disabled] { opacity:0.4; cursor:not-allowed; }
.st-btn-icon-muted { background:#F0E6DA; color:#7A6552; }
.st-btn-icon-muted:hover { background:#E0D2C0; }
.st-btn-icon-danger { background:#FBE3DE; color:#A2382B; }
.st-btn-icon-danger:hover { background:#F2CCC4; }

/* ── Re-Match Dokter CTA ─────────────────────────────────────────── */
.st-import-rematch-cta {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px;
    background: #FFF7E8;
    border: 1px solid #E5C896;
    border-radius: 10px;
    margin: 14px 0 0;
    flex-wrap: wrap;
}
.st-import-rematch-cta__body {
    flex: 1; display: flex; align-items: center; gap: 12px;
    color: #6B4628; font-size: 0.88em;
}
.st-import-rematch-cta__body i.fa-user-md {
    font-size: 1.4em; color: #B5701A;
}
.st-import-rematch-cta__actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Auto-Process CTA card (panggil engine) ──────────────────────── */
.st-import-auto-cta {
    display: flex; align-items: center; gap: 16px;
    padding: 18px 22px;
    background: linear-gradient(135deg, #FFF7EC 0%, #FBE7CF 100%);
    border: 1px solid #E5C896;
    border-radius: 12px;
    margin: 18px 0;
    box-shadow: 0 2px 8px rgba(181,112,26,0.10);
}
.st-import-auto-cta__icon {
    flex: 0 0 auto;
    width: 52px; height: 52px;
    border-radius: 14px;
    background: linear-gradient(135deg, #B5701A, #8A5A1F);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6em;
    box-shadow: 0 4px 10px rgba(181,112,26,0.32);
}
.st-import-auto-cta__body { flex: 1; }
.st-import-auto-cta__body h6 { margin: 0 0 4px; color: #6B4628; font-weight: 600; }
.st-import-auto-cta__body p { margin: 0; font-size: 0.88em; color: #5B4B3D; line-height: 1.45; }
.st-import-auto-cta__filter {
    margin-top: 8px;
    padding: 6px 10px;
    background: rgba(255,255,255,0.7);
    border-radius: 6px;
    font-size: 0.82em;
    color: #6B4628;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
}
.st-import-auto-cta__filter i.fa-filter { color: #B5701A; }
.st-import-auto-cta__filter-clear {
    color: #A2382B;
    font-size: 0.92em;
    text-decoration: none;
}
.st-import-auto-cta__filter-clear:hover { text-decoration: underline; }
.st-import-auto-btn {
    padding: 12px 20px;
    font-size: 0.95em;
    font-weight: 600;
    white-space: nowrap;
}
.st-import-auto-btn:disabled { opacity: 0.7; cursor: not-allowed; }

/* ── Auto-Process result summary ─────────────────────────────────── */
.st-import-auto-result {
    background: #fff;
    border: 1px solid #E5D3B8;
    border-radius: 12px;
    padding: 18px 22px;
    margin-bottom: 18px;
    box-shadow: 0 2px 10px rgba(91,75,61,0.10);
}
.st-import-auto-result__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.st-import-auto-result__title { margin: 0; color: #6B4628; font-weight: 600; }
.st-import-auto-result__title i { color: #B5701A; margin-right: 4px; }
.st-import-auto-result__sub { margin: 2px 0 0; font-size: 0.85em; color: #7A6552; }
.st-import-auto-result__stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}
.st-import-auto-stat {
    padding: 12px 14px;
    border-radius: 10px;
    text-align: center;
}
.st-import-auto-stat__num { font-size: 1.8em; font-weight: 700; line-height: 1; }
.st-import-auto-stat__lbl { font-size: 0.8em; font-weight: 500; margin-top: 4px; }
.st-import-auto-stat__lbl i { margin-right: 3px; }
.st-import-auto-stat.is-promoted { background: #E3F4E8; color: #2F7E4D; }
.st-import-auto-stat.is-skipped { background: #EFE6DA; color: #7A6552; }
.st-import-auto-stat.is-review { background: #FBE7CF; color: #8A5A1F; }
.st-import-auto-stat.is-error { background: #FBE3DE; color: #A2382B; }
.st-import-auto-result__reasons {
    padding: 10px 14px;
    background: #FFF7EC;
    border-radius: 8px;
    font-size: 0.85em;
    color: #6B4628;
    margin-bottom: 12px;
}
.st-import-auto-reason {
    display: inline-block;
    margin: 3px 4px 0 0;
    padding: 2px 8px;
    background: rgba(255,255,255,0.6);
    border: 1px solid #E5C896;
    border-radius: 999px;
    font-size: 0.92em;
}
.st-import-auto-result__cta { display: flex; gap: 8px; flex-wrap: wrap; }
.st-import-auto-result__cta a { padding: 8px 14px; }

.st-import-empty { text-align:center; padding:40px 20px; color:#7A6552; }
.st-import-empty i { font-size:3em; opacity:0.3; display:block; margin-bottom:8px; }
.st-import-empty h6 { color:#5B4B3D; }

/* ── Side Drawer (Edit Staging Row) ────────────────────────────────── */
.st-drawer {
    position: fixed; inset: 0; z-index: 1080;
    pointer-events: none;
}
.st-drawer[aria-hidden="false"] { pointer-events: auto; }
.st-drawer__backdrop {
    position: absolute; inset: 0;
    background: rgba(91, 75, 61, 0.45);
    opacity: 0;
    transition: opacity 0.22s ease;
}
.st-drawer[aria-hidden="false"] .st-drawer__backdrop { opacity: 1; }
.st-drawer__panel {
    position: absolute; top: 0; right: 0; bottom: 0;
    width: min(460px, 100vw);
    background: #FFFDF9;
    border-left: 1px solid #E5D3B8;
    box-shadow: -8px 0 24px rgba(91, 75, 61, 0.18);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform 0.28s cubic-bezier(.22,.61,.36,1);
}
.st-drawer[aria-hidden="false"] .st-drawer__panel { transform: translateX(0); }
.st-drawer__header {
    display:flex; align-items:center; justify-content:space-between;
    padding: 16px 20px;
    border-bottom: 1px solid #E5D3B8;
    background: linear-gradient(135deg, #FBE7CF 0%, #F4ECE2 100%);
}
.st-drawer__title { margin: 0; color: #6B4628; font-weight: 600; font-size: 1.05em; }
.st-drawer__close {
    background: transparent; border: none;
    color: #6B4628; font-size: 1.1em; cursor: pointer;
    padding: 4px 10px; border-radius: 6px;
}
.st-drawer__close:hover { background: rgba(107, 70, 40, 0.12); }
.st-drawer__body {
    flex: 1 1 auto; overflow-y: auto;
    padding: 18px 20px;
    display: flex; flex-direction: column; gap: 12px;
}
.st-drawer__row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.st-drawer__field { display: flex; flex-direction: column; gap: 4px; }
.st-drawer__field label {
    font-size: 0.78em; color: #6B4628; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.04em;
    margin: 0;
}
.st-drawer__req { color: #C0392B; font-weight: 700; }
.st-drawer__hint { font-size: 0.78em; color: #7A6552; margin-top: 2px; display: block; }

/* Section grouping di dalam drawer */
.st-drawer__section {
    background: rgba(244,236,226,0.4);
    border: 1px solid #E5D3B8;
    border-radius: 10px;
    padding: 14px 14px 12px;
    display: flex; flex-direction: column; gap: 12px;
}
.st-drawer__section-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.85em;
    font-weight: 600;
    color: #6B4628;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding-bottom: 6px;
    border-bottom: 1px dashed #E5D3B8;
}
.st-drawer__section-title i { color: #B5701A; }
.st-drawer__footer {
    display: flex; flex-direction: column; gap: 10px;
    padding: 14px 20px;
    border-top: 1px solid #E5D3B8;
    background: #F8F1E5;
}
.st-drawer__primary-btn {
    display:flex; align-items:center; justify-content:center;
    gap:8px;
    padding:10px 16px;
    font-size:0.95em; font-weight:600;
    text-decoration:none;
}
.st-drawer__primary-btn:hover { color: #fff; }
.st-drawer__actions-row {
    display: flex; gap: 8px; flex-wrap: wrap;
}
.st-drawer__actions-row:last-child { justify-content: flex-end; }
.st-drawer__actions-row .st-btn-icon-success,
.st-drawer__actions-row .st-btn-icon-danger,
.st-drawer__actions-row .st-btn-icon-muted {
    flex: 1 1 auto;
    justify-content: center;
    gap: 6px;
    font-size: 0.88em;
    font-weight: 500;
}
body.st-drawer-open { overflow: hidden; }

/* ── Column Mapping Wizard ─────────────────────────────────────── */
.st-mapping-hint {
    padding: 10px 14px;
    background: #FFF7EC;
    border: 1px dashed #E5C896;
    border-radius: 8px;
    color: #6B4628;
    font-size: 0.85em;
    line-height: 1.5;
}
.st-mapping-hint i.fa-info-circle { color: #B5701A; margin-right: 4px; }
.st-mapping-section { display: flex; flex-direction: column; gap: 6px; }
.st-mapping-section__title {
    font-size: 0.82em; font-weight: 600;
    color: #6B4628; text-transform: uppercase;
    letter-spacing: 0.06em;
}
.st-mapping-preview-wrap {
    max-height: 280px; overflow: auto;
    background: #fff;
    border: 1px solid #E5D3B8;
    border-radius: 8px;
}
.st-mapping-preview { width: 100%; border-collapse: collapse; font-size: 0.82em; }
.st-mapping-preview th, .st-mapping-preview td {
    padding: 6px 10px;
    border-right: 1px solid #F0E6DA;
    border-bottom: 1px solid #F0E6DA;
    white-space: nowrap;
    color: #5B4B3D;
}
.st-mapping-preview th { background: #F4ECE2; color: #6B4628; font-weight: 600; position: sticky; top: 0; }
.st-mapping-preview tr.is-header-row td { background: #DFEDE9; color: #2E7D74; font-weight: 600; }
.st-mapping-preview .st-mapping-rownum { background: #F8F1E5; cursor: pointer; text-align: center; font-weight: 600; color: #7A6552; }
.st-mapping-preview .st-mapping-rownum:hover { background: #E5D3B8; }

.st-mapping-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.st-mapping-field { display: flex; flex-direction: column; gap: 4px; }
.st-mapping-field label {
    font-size: 0.78em; color: #6B4628; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.04em;
    display: flex; align-items: center; gap: 6px;
}
.st-mapping-auto-tag {
    display: inline-block;
    padding: 1px 6px;
    background: #DFEDE9;
    color: #2E7D74;
    border-radius: 4px;
    font-size: 0.75em;
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0;
}

/* Patient-match panel di dalam drawer */
.st-drawer__match {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 12px 14px;
    background: #E3F4E8;
    border: 1px solid #B8DAB8;
    border-radius: 8px;
    color: #2F7E4D;
    margin-bottom: 4px;
}
.st-drawer__match.is-new {
    background: #FBE7CF;
    border-color: #E5C896;
    color: #8A5A1F;
}
.st-drawer__match.is-busy {
    background: #FFF7E8;
    border-color: #E5C896;
    color: #8A5A1F;
}
.st-drawer__match.is-busy.is-conflict {
    background: #FBE3DE;
    border-color: #E8B4AA;
    color: #A2382B;
}
.st-drawer__busy-list {
    margin: 8px 0 0 0;
    padding: 0;
    list-style: none;
    display: flex; flex-direction: column; gap: 5px;
}
.st-drawer__busy-list li {
    padding: 6px 10px;
    background: rgba(255,255,255,0.65);
    border-radius: 6px;
    font-size: 0.85em;
    display: flex; gap: 8px; align-items: center; flex-wrap: wrap;
}
.st-drawer__busy-list li.same-date {
    background: #FBE3DE;
    color: #A2382B;
    font-weight: 600;
}
.st-drawer__busy-list time { font-weight: 600; }
.st-drawer__match-icon { font-size: 1.4em; flex: 0 0 auto; padding-top: 2px; }
.st-drawer__match-body { flex: 1; }
.st-drawer__match-title { font-weight: 600; font-size: 0.9em; }
.st-drawer__match-detail { margin-top: 2px; font-size: 0.95em; }
.st-drawer__match-detail .text-muted { margin-left: 6px; font-size: 0.85em; }
.st-drawer__match-meta { font-size: 0.82em; opacity: 0.85; margin-top: 2px; }
.st-drawer__match-hint { font-size: 0.78em; margin-top: 6px; opacity: 0.9; }

/* Badge di kolom Pasien — Lama (hijau) / Baru (warm) */
.st-import-patient-badge {
    display: inline-block;
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 0.7em;
    font-weight: 600;
    margin-left: 6px;
    vertical-align: middle;
    letter-spacing: 0.02em;
}
.st-import-patient-badge.is-existing {
    background: #DFEDE9; color: #2F7E4D; border: 1px solid #B8DAB8;
}
.st-import-patient-badge.is-new {
    background: #FBE7CF; color: #8A5A1F; border: 1px solid #E5C896;
}
.st-import-patient-badge.is-busy {
    background: #FFF7E8; color: #8A5A1F; border: 1px solid #E5C896;
}
.st-import-patient-badge.is-conflict {
    background: #FBE3DE; color: #A2382B; border: 1px solid #E8B4AA;
    font-weight: 700;
}

/* Row styling untuk reservasi existing */
.st-import-table tr.has-existing-resv {
    background: linear-gradient(90deg, rgba(251,247,232,0.6) 0%, transparent 70%) !important;
}
.st-import-table tr.has-same-date-resv {
    background: linear-gradient(90deg, rgba(251,227,222,0.6) 0%, transparent 70%) !important;
    border-left: 3px solid #C0392B;
}
.st-import-table tr.has-same-date-resv:hover {
    background: linear-gradient(90deg, rgba(251,227,222,0.8) 0%, rgba(244,236,226,0.5) 100%) !important;
}

/* Row pending = clickable (buka drawer). Non-pending = read-only */
.st-import-table tr.is-clickable { cursor: pointer; }
.st-import-table tr.is-clickable:hover {
    background: #F4ECE2 !important;
}
.st-import-table tr.is-clickable:hover.is-warn { background: #FFE9C2 !important; }
.st-import-table tr.is-clickable:hover.is-waiting { background: #E5D7C7 !important; }
/* Cell checkbox tidak ikut trigger click row */
.st-import-nocell { position: relative; }
</style>

<script>
// ════════ rrOpenFormDrawer — fetch /rawreservation/new?drawer=1 & inject ════════
// Copy mekanisme dari list.blade.php supaya halaman Impor Sheets bisa buka form
// reservasi LENGKAP (slot picker, services, dll) sebagai side drawer overlay
// tanpa navigate. Submit form = full page redirect balik ke import page.
(function () {
    var $fd = $('#rrFormDrawer');
    if (!$fd.length) return;
    var $body = $('#rrFormBody');

    window.rrOpenFormDrawer = function (opts) {
        opts = opts || {};
        $('#rrFormTitle').text(opts.title || 'Reservasi');
        $body.html('<div class="text-muted" style="padding:24px 4px;"><i class="fa fa-spinner fa-spin"></i> Memuat form…</div>');
        $fd.addClass('is-open').attr('aria-hidden', 'false');
        document.body.classList.add('rr-drawer-lock');

        var url = opts.url || '';
        if (!url) return;
        var fetchUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'drawer=1';

        fetch(fetchUrl, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var srcBody = doc.body || doc.documentElement;
                var scripts = Array.prototype.slice.call(srcBody.querySelectorAll('script'));
                scripts.forEach(function (s) { if (s.parentNode) s.parentNode.removeChild(s); });
                $body.html(srcBody.innerHTML);
                scripts.forEach(function (s) {
                    var n = document.createElement('script');
                    if (s.src) { n.src = s.src; } else { n.textContent = s.textContent; }
                    document.head.appendChild(n);
                    if (n.parentNode) n.parentNode.removeChild(n);
                });
                if (typeof window.initReservationForm === 'function') {
                    window.initReservationForm();
                }
            })
            .catch(function (err) {
                $body.html('<div class="text-danger" style="padding:24px 4px;"><i class="fa fa-exclamation-circle"></i> Gagal memuat form: '
                    + (err && err.message ? err.message : err) + '</div>');
            });
    };
    function rrFormClose() {
        $fd.removeClass('is-open').attr('aria-hidden', 'true');
        document.body.classList.remove('rr-drawer-lock');
    }
    window.rrCloseFormDrawer = rrFormClose;
    $(document).on('click', '[data-rrform-close]', rrFormClose);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $fd.hasClass('is-open')) rrFormClose();
    });
})();

(function(){
    'use strict';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
    };

    // ── Bulk checkbox handling ──────────────────────────────────────────
    const checkAll = document.getElementById('bulkCheckAll');
    const rowChecks = () => document.querySelectorAll('.st-import-row-check');
    const checkedIds = () => Array.from(rowChecks()).filter(c => c.checked).map(c => c.value);

    function refreshBulkState() {
        const n = checkedIds().length;
        document.getElementById('bulkCount').textContent = n + ' dipilih';
        document.getElementById('bulkPromoteBtn').disabled = n === 0;
        document.getElementById('bulkSkipBtn').disabled = n === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', () => {
            rowChecks().forEach(c => c.checked = checkAll.checked);
            refreshBulkState();
        });
    }
    rowChecks().forEach(c => c.addEventListener('change', refreshBulkState));

    // ── Promote / Skip helpers ──────────────────────────────────────────
    async function doPromote(id, force) {
        const url = `{{ url('rawreservation/import/staging') }}/${id}/promote`;
        const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify({ force: !!force }) });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok) {
            location.reload();
            return;
        }
        if (data.error === 'slot_conflict') {
            const c = data.conflict || {};
            const ok = confirm('Slot bentrok dengan reservasi atas nama "' + (c.patient || '—') + '" pada ' + (c.date || '') + ' ' + (c.start_hour || '') + '.\n\nTetap promote (force)?');
            if (ok) doPromote(id, true);
            return;
        }
        alert(data.message || 'Gagal promote.');
    }
    async function doSkip(id) {
        if (!confirm('Skip baris ini? Tidak akan dipromote.')) return;
        const url = `{{ url('rawreservation/import/staging') }}/${id}/skip`;
        const res = await fetch(url, { method: 'POST', headers });
        if (res.ok) location.reload();
        else alert('Gagal skip.');
    }

    // ── Edit side drawer ────────────────────────────────────────────────
    const editForm = document.getElementById('stagingEditForm');
    const editDrawer = document.getElementById('stagingEditDrawer');
    const drawerPromoteBtn = document.getElementById('drawerPromoteBtn');
    const drawerSkipBtn = document.getElementById('drawerSkipBtn');
    const drawerDebugBtn = document.getElementById('drawerDebugBtn');
    const drawerScheduleFullBtn = document.getElementById('drawerScheduleFullBtn');

    // Konteks row aktif yg lagi di-edit di drawer.
    let currentRowId = null;
    let currentRawRow = [];

    function openDrawer(drawer) {
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('st-drawer-open');
    }
    function closeDrawer(drawer) {
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('st-drawer-open');
    }
    editDrawer.querySelectorAll('[data-st-drawer-close]').forEach(el => {
        el.addEventListener('click', () => closeDrawer(editDrawer));
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && editDrawer.getAttribute('aria-hidden') === 'false') {
            closeDrawer(editDrawer);
        }
    });

    function openRowDrawer(tr) {
        currentRowId = tr.dataset.stagingId;
        try { currentRawRow = JSON.parse(tr.dataset.rawRow || '[]'); }
        catch (e) { currentRawRow = []; }

        editForm.action = `{{ url('rawreservation/import/staging') }}/${currentRowId}`;
        editForm.querySelector('[name="patient_name"]').value = tr.dataset.patientName || '';
        editForm.querySelector('[name="patient_phone"]').value = tr.dataset.patientPhone || '';
        editForm.querySelector('[name="patient_email"]').value = tr.dataset.patientEmail || '';
        editForm.querySelector('[name="patient_nik"]').value = tr.dataset.patientNik || '';
        editForm.querySelector('[name="raw_branch_id"]').value = tr.dataset.branchId || '';
        editForm.querySelector('[name="treatment_date"]').value = tr.dataset.treatmentDate || '';
        editForm.querySelector('[name="start_hour"]').value = tr.dataset.startHour || '';
        editForm.querySelector('[name="resolved_raw_doctor_id"]').value = tr.dataset.doctorId || '';
        editForm.querySelector('[name="mapped_status"]').value = tr.dataset.status || 'pending';
        editForm.querySelector('[name="tindakan_text"]').value = tr.dataset.tindakan || '';
        editForm.querySelector('[name="is_waiting"]').checked = tr.dataset.waiting === '1';

        // ── Patient match panel (phone dedup) ──
        const matchPanel = document.getElementById('patientMatchPanel');
        const newPanel = document.getElementById('patientNewPanel');
        const busyPanel = document.getElementById('patientBusyPanel');
        const existingId = tr.dataset.existingId || '';
        const phone = tr.dataset.patientPhone || '';
        if (existingId) {
            document.getElementById('matchName').textContent = tr.dataset.existingName || '—';
            document.getElementById('matchEmail').textContent = tr.dataset.existingEmail
                ? ' · ' + tr.dataset.existingEmail : '';
            const lv = tr.dataset.existingLastVisit;
            document.getElementById('matchLastVisit').textContent = lv
                ? 'Reservasi terakhir: ' + lv
                : 'Belum ada riwayat reservasi tercatat';
            matchPanel.style.display = 'flex';
            newPanel.style.display = 'none';
        } else if (phone) {
            matchPanel.style.display = 'none';
            newPanel.style.display = 'flex';
        } else {
            matchPanel.style.display = 'none';
            newPanel.style.display = 'none';
        }

        // ── Busy panel: existing upcoming reservasi (warn double-book) ──
        let upcomingRaw = tr.dataset.existingUpcoming || '';
        let upcoming = [];
        try { if (upcomingRaw) upcoming = JSON.parse(upcomingRaw); } catch (e) {}
        const busyList = document.getElementById('busyList');
        const busyTitle = document.getElementById('busyTitle');
        const busyHint = document.getElementById('busyHint');
        if (upcoming && upcoming.length) {
            const sameDate = upcoming.filter(r => r.same_date);
            busyPanel.classList.toggle('is-conflict', sameDate.length > 0);
            busyTitle.textContent = sameDate.length
                ? '⚠ POTENSI DOUBLE BOOKING — Reservasi di Tanggal Sama'
                : `Pasien sudah punya ${upcoming.length} reservasi mendatang`;
            busyHint.innerHTML = sameDate.length
                ? '<i class="fa fa-exclamation-triangle"></i> Pasien sudah ada reservasi di tanggal yg sama dgn staging row ini. Pastikan ini bukan duplikat sebelum schedule.'
                : '<i class="fa fa-info-circle"></i> Cek list di bawah supaya tidak double-book pasien yg sama.';
            busyList.innerHTML = '';
            upcoming.forEach(r => {
                const li = document.createElement('li');
                if (r.same_date) li.classList.add('same-date');
                const dateStr = r.date ? new Date(r.date + 'T00:00:00').toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '—';
                li.innerHTML = `<i class="fa fa-calendar-day"></i> <time>${dateStr}</time>`
                    + (r.time ? ` · ${r.time}` : '')
                    + (r.doctor ? ` · ${r.doctor}` : '')
                    + ` <span class="text-muted" style="font-size:0.85em;">(${r.status || '-'})</span>`
                    + (r.same_date ? ' <strong>← TANGGAL SAMA</strong>' : '');
                busyList.appendChild(li);
            });
            busyPanel.style.display = 'flex';
        } else {
            busyPanel.style.display = 'none';
        }

        // Disable tombol Schedule kalau row belum promotable.
        const promotable = tr.dataset.promotable === '1';
        drawerPromoteBtn.disabled = !promotable;
        drawerPromoteBtn.title = promotable
            ? 'Promote ke reservasi resmi'
            : 'Lengkapi nama pasien + dokter dulu, lalu Simpan';

        openDrawer(editDrawer);
    }

    // Klik di row pending = buka drawer. Skip click di sel checkbox supaya
    // user bisa select bulk tanpa nge-trigger drawer.
    document.querySelectorAll('tr.js-row-click').forEach(tr => {
        tr.addEventListener('click', (e) => {
            // Skip kalau click target ada di checkbox, link, atau button
            if (e.target.closest('input[type=checkbox], a, button')) return;
            openRowDrawer(tr);
        });
    });

    // Tombol aksi di footer drawer — operate di currentRowId.
    // ── Schedule Full: buka form reservasi LENGKAP sebagai overlay drawer ──
    // Mekanisme sama dgn list.blade.php — fetch /rawreservation/new?drawer=1&
    // staging_id=X (bare layout), inject ke #rrFormBody, jalankan scripts +
    // initReservationForm(). Bridge controller: ReservationController::create()
    // detect ?staging_id → flash old-input + redirect (yg ke-fetch sini =
    // redirected URL). Submit form di drawer = create reservasi + mark
    // staging promoted + full-page redirect balik ke /rawreservation/import.
    drawerScheduleFullBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (!currentRowId) return;
        const url = `{{ url('rawreservation/new') }}?staging_id=${encodeURIComponent(currentRowId)}`;
        closeDrawer(editDrawer); // tutup staging drawer dulu
        if (typeof window.rrOpenFormDrawer === 'function') {
            window.rrOpenFormDrawer({
                title: 'Edit & Schedule Reservasi',
                url: url,
                mode: 'add',
            });
        } else {
            window.location.href = url; // fallback
        }
    });

    drawerPromoteBtn?.addEventListener('click', () => {
        if (currentRowId) doPromote(currentRowId, false);
    });
    drawerSkipBtn?.addEventListener('click', () => {
        if (currentRowId) doSkip(currentRowId);
    });
    drawerDebugBtn?.addEventListener('click', () => {
        const tbody = document.querySelector('#debugRawTable tbody');
        tbody.innerHTML = '';
        if (!Array.isArray(currentRawRow) || !currentRawRow.length) {
            tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Raw row tidak tersedia.</td></tr>';
        } else {
            currentRawRow.forEach((v, i) => {
                const tr = document.createElement('tr');
                const td1 = document.createElement('td');
                td1.textContent = i;
                td1.style.fontWeight = '600';
                td1.style.color = '#7A6552';
                const td2 = document.createElement('td');
                td2.textContent = (v === null || v === undefined) ? '' : String(v);
                if (!td2.textContent) {
                    td2.innerHTML = '<em class="text-muted">(kosong)</em>';
                }
                tr.appendChild(td1); tr.appendChild(td2);
                tbody.appendChild(tr);
            });
        }
        if (window.jQuery) jQuery('#stagingDebugModal').modal('show');
    });

    // ── Bulk actions ────────────────────────────────────────────────────
    async function bulkAction(endpoint, label, force) {
        const ids = checkedIds();
        if (!ids.length) return;
        if (!confirm(`${label} ${ids.length} baris terpilih?`)) return;

        const res = await fetch(endpoint, {
            method: 'POST',
            headers,
            body: JSON.stringify({ ids, force: !!force }),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok) {
            if (data.summary) {
                const s = data.summary;
                alert(`Promoted: ${s.promoted}. Konflik: ${s.conflict}. Sudah dipromote sebelumnya: ${s.already}. Error: ${s.errors}.`);
            }
            location.reload();
        } else {
            alert('Aksi bulk gagal.');
        }
    }

    document.getElementById('bulkPromoteBtn')?.addEventListener('click', () => {
        bulkAction('{{ route('rawreservation.import.bulk-promote') }}', 'Promote', false);
    });
    document.getElementById('bulkSkipBtn')?.addEventListener('click', () => {
        bulkAction('{{ route('rawreservation.import.bulk-skip') }}', 'Skip', false);
    });

    refreshBulkState();

    // ── Column Mapping Wizard ─────────────────────────────────────────
    // Field kanonik yg di-recognize backend importer.
    const MAPPING_FIELDS = [
        { key: 'patient_name',       label: 'Nama Pasien',     required: true,  aliases: ['nama pasien', 'pasien', 'nama'] },
        { key: 'patient_phone',      label: 'No. Telp',        required: true,  aliases: ['no.telp', 'no telp', 'no. telp', 'phone', 'hp', 'nomor telp', 'wa'] },
        { key: 'patient_email',      label: 'Email',           required: false, aliases: ['email', 'alamat email'] },
        { key: 'patient_nik',        label: 'NIK',             required: false, aliases: ['nik'] },
        { key: 'tanggal_treatment',  label: 'Tanggal Treatment',required: true, aliases: ['tanggal treatment', 'tanggal', 'tgl treatment', 'tgl', 'date'] },
        { key: 'start_hour',         label: 'Jam',             required: false, aliases: ['jam', 'pasien rata', 'jam mulai', 'jam praktek', 'mulai', 'time', 'start hour'] },
        { key: 'doctor',             label: 'Dokter',          required: true,  aliases: ['dokter', 'doctor'] },
        { key: 'status_raw',         label: 'Status (Konfirmasi)',required: false, aliases: ['follow up h-1', 'konfirmasi', 'status'] },
        { key: 'tindakan',           label: 'Tindakan',        required: false, aliases: ['tindakan reservasi', 'tindakan'] },
        { key: 'total',              label: 'Total Reservasi', required: false, aliases: ['total reservasi', 'total', 'biaya'] },
        { key: 'pic',                label: 'PIC (yg input)',  required: false, aliases: ['pic', 'nama yang masukin jadwal'] },
        { key: 'tanggal_reservasi',  label: 'Tgl Reservasi (raw)',required: false, aliases: ['tanggal reservasi'] },
        { key: 'keterangan_kehadiran',label: 'Ket. Kehadiran', required: false, aliases: ['keterangan kehadiran'] },
    ];

    const fileInputElem = document.getElementById('importFile');
    const wizardModal = document.getElementById('mappingWizard');
    const wizardBtn = document.getElementById('importWizardBtn');
    const directBtn = document.getElementById('importDirectBtn');
    const mappingHidden = document.getElementById('mappingHiddenInput');
    const headerRowHidden = document.getElementById('headerRowHiddenInput');
    const previewHead = document.getElementById('mappingPreviewHead');
    const previewBody = document.getElementById('mappingPreviewBody');
    const fieldsContainer = document.getElementById('mappingFields');
    const resetBtn = document.getElementById('mappingResetBtn');
    const confirmBtn = document.getElementById('mappingConfirmBtn');

    let csvRows = [];        // parsed first 10-15 rows from file
    let autoMapping = {};    // auto-detected mapping (canonical → col index)
    let autoHeaderRow = 0;   // auto-detected header row index
    let currentMapping = {}; // current user mapping (canonical → col index or null)
    let currentHeaderRow = 0;

    function openWizard() { wizardModal.setAttribute('aria-hidden', 'false'); document.body.classList.add('st-drawer-open'); }
    function closeWizard() { wizardModal.setAttribute('aria-hidden', 'true'); document.body.classList.remove('st-drawer-open'); }
    wizardModal.querySelectorAll('[data-mapping-close]').forEach(el => el.addEventListener('click', closeWizard));

    // ── CSV parser sederhana (handle quoted fields + commas inside quotes) ──
    function parseCsvLine(line) {
        const result = [];
        let cur = '';
        let inQuote = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (inQuote) {
                if (ch === '"' && line[i+1] === '"') { cur += '"'; i++; }
                else if (ch === '"') { inQuote = false; }
                else { cur += ch; }
            } else {
                if (ch === '"') { inQuote = true; }
                else if (ch === ',') { result.push(cur); cur = ''; }
                else { cur += ch; }
            }
        }
        result.push(cur);
        return result;
    }
    function parseCsvText(text, maxRows = 15) {
        const lines = text.split(/\r?\n/);
        const out = [];
        for (let i = 0; i < lines.length && out.length < maxRows; i++) {
            if (lines[i].trim() === '' && out.length > 0) continue; // skip empty after data start
            out.push(parseCsvLine(lines[i]));
        }
        return out;
    }

    // ── Auto-detect header row + mapping ──
    function autoDetectHeader(rows) {
        // Cari row yg mengandung "pasien" + ("tanggal"|"dokter"|"tindakan")
        const limit = Math.min(15, rows.length);
        for (let i = 0; i < limit; i++) {
            const joined = rows[i].map(c => String(c || '').toLowerCase()).join(' | ');
            if (joined.includes('pasien') && (joined.includes('tanggal') || joined.includes('dokter') || joined.includes('tindakan'))) {
                return i;
            }
        }
        return 0;
    }
    function autoDetectMapping(headerRow) {
        const map = {};
        // Normalize headers
        const normHeaders = headerRow.map(h => String(h || '').toLowerCase().replace(/\s+/g, ' ').trim());
        MAPPING_FIELDS.forEach(field => {
            // Pass 1: exact alias match
            for (let i = 0; i < normHeaders.length; i++) {
                if (field.aliases.includes(normHeaders[i]) && map[field.key] === undefined) {
                    map[field.key] = i; return;
                }
            }
            // Pass 2: substring match
            for (let i = 0; i < normHeaders.length; i++) {
                if (normHeaders[i] && field.aliases.some(a => normHeaders[i].includes(a)) && map[field.key] === undefined) {
                    map[field.key] = i; return;
                }
            }
        });
        return map;
    }

    function renderWizard() {
        // Render preview table
        const numCols = Math.max(...csvRows.map(r => r.length), 0);
        let headHtml = '<th style="width:40px;">#</th>';
        for (let c = 0; c < numCols; c++) headHtml += `<th>Col ${c}</th>`;
        previewHead.innerHTML = headHtml;

        let bodyHtml = '';
        csvRows.forEach((row, idx) => {
            const isHeader = idx === currentHeaderRow;
            bodyHtml += `<tr class="${isHeader ? 'is-header-row' : ''}" data-row="${idx}">`;
            bodyHtml += `<td class="st-mapping-rownum" title="Klik untuk set sebagai header row">${idx}${isHeader ? ' ✓' : ''}</td>`;
            for (let c = 0; c < numCols; c++) {
                const val = row[c] !== undefined ? String(row[c]) : '';
                bodyHtml += `<td>${val.substring(0, 50)}${val.length > 50 ? '…' : ''}</td>`;
            }
            bodyHtml += '</tr>';
        });
        previewBody.innerHTML = bodyHtml;

        // Wire row-click to change header row
        previewBody.querySelectorAll('tr').forEach(tr => {
            tr.querySelector('.st-mapping-rownum')?.addEventListener('click', () => {
                currentHeaderRow = parseInt(tr.dataset.row);
                // Re-auto-detect mapping based on new header row
                autoMapping = autoDetectMapping(csvRows[currentHeaderRow] || []);
                currentMapping = { ...autoMapping };
                renderWizard();
            });
        });

        // Render mapping fields
        let fieldsHtml = '';
        MAPPING_FIELDS.forEach(field => {
            let optionsHtml = '<option value="">— Tidak ada / abaikan —</option>';
            const headerRow = csvRows[currentHeaderRow] || [];
            for (let c = 0; c < numCols; c++) {
                const headerVal = headerRow[c] !== undefined ? String(headerRow[c]).substring(0, 30) : '';
                const sample = (csvRows[currentHeaderRow + 1] || [])[c] || '';
                const selected = currentMapping[field.key] === c ? 'selected' : '';
                optionsHtml += `<option value="${c}" ${selected}>Col ${c}${headerVal ? ' — "' + headerVal + '"' : ''}${sample ? ' (mis. ' + String(sample).substring(0, 20) + ')' : ''}</option>`;
            }
            const reqLabel = field.required ? '<span class="st-drawer__req">*</span>' : '';
            const autoTag = autoMapping[field.key] === currentMapping[field.key] && currentMapping[field.key] !== undefined
                ? '<span class="st-mapping-auto-tag" title="Hasil auto-detect">auto</span>'
                : '';
            fieldsHtml += `
                <div class="st-mapping-field">
                    <label>${field.label} ${reqLabel} ${autoTag}</label>
                    <select data-field="${field.key}" class="form-control form-control-sm">${optionsHtml}</select>
                </div>
            `;
        });
        fieldsContainer.innerHTML = fieldsHtml;

        // Wire change events
        fieldsContainer.querySelectorAll('select').forEach(sel => {
            sel.addEventListener('change', () => {
                const v = sel.value;
                currentMapping[sel.dataset.field] = v === '' ? undefined : parseInt(v);
            });
        });
    }

    // ── File picked → parse + open wizard kalau perlu, atau auto-submit ──
    fileInputElem?.addEventListener('change', async (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        // XLSX tidak di-handle wizard (no JS XLSX parser di-bundle).
        // Hanya CSV/TXT yg trigger wizard.
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv' && ext !== 'txt') {
            wizardBtn.style.display = 'none';
            return;
        }

        // Parse client-side untuk preview
        try {
            const text = await file.slice(0, 64 * 1024).text(); // ambil 64KB pertama
            csvRows = parseCsvText(text, 15);
            autoHeaderRow = autoDetectHeader(csvRows);
            autoMapping = autoDetectMapping(csvRows[autoHeaderRow] || []);
            currentHeaderRow = autoHeaderRow;
            currentMapping = { ...autoMapping };
            wizardBtn.style.display = 'inline-flex';
        } catch (err) {
            console.warn('CSV preview failed:', err);
            wizardBtn.style.display = 'none';
        }
    });

    wizardBtn?.addEventListener('click', () => {
        if (csvRows.length === 0) {
            alert('Belum ada preview — pilih file CSV dulu.');
            return;
        }
        renderWizard();
        openWizard();
    });

    resetBtn?.addEventListener('click', () => {
        currentHeaderRow = autoHeaderRow;
        currentMapping = { ...autoMapping };
        renderWizard();
    });

    confirmBtn?.addEventListener('click', () => {
        // Validate required fields
        const missing = MAPPING_FIELDS.filter(f => f.required && currentMapping[f.key] === undefined);
        if (missing.length) {
            alert('Field wajib belum di-set:\n' + missing.map(f => '- ' + f.label).join('\n'));
            return;
        }

        // Map canonical names ke yg backend expect (alias keys di importer)
        // Beberapa key kanonik di backend slight different, mapping disini:
        const backendMapping = {};
        Object.keys(currentMapping).forEach(k => {
            if (currentMapping[k] !== undefined) backendMapping[k] = currentMapping[k];
        });

        mappingHidden.value = JSON.stringify(backendMapping);
        headerRowHidden.value = String(currentHeaderRow);
        closeWizard();

        // Submit form
        document.getElementById('importUploadForm').submit();
    });
})();
</script>

<script>
(function(){
    // ── Help panel toggle ──────────────────────────────────────────────
    const helpToggle = document.getElementById('toggleImportHelp');
    const helpPanel = document.getElementById('importHelpPanel');
    if (helpToggle && helpPanel) {
        helpToggle.addEventListener('click', () => {
            const isOpen = helpPanel.hidden === false;
            helpPanel.hidden = isOpen;
            helpToggle.setAttribute('aria-expanded', String(!isOpen));
        });
    }

    // ── Dropzone: show selected file name + drag-and-drop visual ───────
    const dropzone = document.getElementById('importDropzone');
    const fileInput = document.getElementById('importFile');
    const dzTitle = document.getElementById('dropzoneTitle');
    const dzSub = document.getElementById('dropzoneSub');

    function reflectFile(file) {
        if (!file) return;
        dropzone.classList.add('is-filled');
        dzTitle.textContent = file.name;
        const sizeKb = (file.size / 1024).toFixed(1);
        dzSub.textContent = `Siap upload · ${sizeKb} KB`;
    }
    fileInput?.addEventListener('change', (e) => {
        if (e.target.files && e.target.files[0]) reflectFile(e.target.files[0]);
    });
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault(); e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault(); e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
                fileInput.files = e.dataTransfer.files;
                reflectFile(e.dataTransfer.files[0]);
            }
        });
    }
})();
</script>

@endsection

@section('js')
{{-- Flatpickr + reservation-form.js — DIPERLUKAN untuk form Reservasi yang di-
     inject ke #rrFormDrawer via AJAX (?drawer=1). Layout `bare.blade.php` tidak
     bundle JS-nya; expectation: host page yg load. Mengikuti pola di
     list.blade.php (line 988-991). --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
@endsection
