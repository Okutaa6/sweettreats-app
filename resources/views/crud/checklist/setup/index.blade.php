@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Setup Tugas Checklist</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── HERO ─── --}}
    <div class="cl-hero">
        <div class="cl-hero__left">
            <h5 class="cl-hero__title">
                <span class="cl-hero__icon"><i class="fas fa-list-check"></i></span>
                Atur Tugas Checklist
            </h5>
            <p class="cl-hero__subtitle">
                Tentukan daftar pekerjaan untuk tiap divisi. Pisahkan antara <strong>Harian</strong> (tiap hari operasional) dan <strong>Mingguan</strong> (hari tertentu dalam seminggu). <strong>Edit master tidak mengubah history</strong>.
            </p>
        </div>
    </div>

    {{-- ─── CARD: TABS + SUMMARY + KATEGORI LIST ─── --}}
    <div class="cl-panel" style="padding:0;">
        @include('crud.checklist.setup._division_tabs', ['divisions' => $divisions, 'activeDivId' => $division->id ?? null])

        @if($division)
            <div class="cl-summary">
                <span class="cl-summary__chip"><i class="fas fa-folder" style="color:var(--cl-brown-2); font-size:11px;"></i> <strong>{{ $categories->count() }}</strong> kategori</span>
                <span class="cl-summary__sep"></span>
                <span class="cl-summary__chip"><i class="fas fa-list-check" style="color:var(--cl-brown-2); font-size:11px;"></i> <strong>{{ $totalTasks }}</strong> tugas aktif</span>
                <div class="cl-summary__right">
                    Divisi: <strong>{{ $division->name }}</strong>
                </div>
            </div>
        @endif

        <div style="padding:18px; background:var(--cl-cream-1); min-height:280px;">
            @if(! $division)
                <div class="cl-empty">
                    <span class="cl-empty__icon"><i class="fas fa-exclamation-circle"></i></span>
                    <div class="cl-empty__title">Belum ada divisi</div>
                    <div class="cl-empty__desc">Jalankan seeder <code>ChecklistDivisionSeeder</code> terlebih dahulu.</div>
                </div>
            @else
                {{-- ─── CADENCE TAB STRIP ─── --}}
                <div class="cl-cad-tabs" data-cad-tabs>
                    <button type="button" class="cl-cad-tab is-active" data-cad-tab="daily">
                        <span class="cl-cad-tab__icon cl-cad-tab__icon--daily"><i class="fas fa-sun"></i></span>
                        <span class="cl-cad-tab__body">
                            <strong>Harian</strong>
                            <small>{{ $dailyCategories->count() }} kategori · {{ $dailyTaskCount }} tugas</small>
                        </span>
                    </button>
                    <button type="button" class="cl-cad-tab" data-cad-tab="weekly">
                        <span class="cl-cad-tab__icon cl-cad-tab__icon--weekly"><i class="fas fa-calendar-week"></i></span>
                        <span class="cl-cad-tab__body">
                            <strong>Mingguan</strong>
                            <small>{{ $weeklyCategories->count() }} kategori · {{ $weeklyTaskCount }} tugas</small>
                        </span>
                    </button>
                </div>

                {{-- ─── PANEL: KATEGORI HARIAN ─── --}}
                <div class="cl-cad-panel is-active" data-cad-panel="daily">
                    <div class="cl-section__head">
                        <div class="cl-section__title">
                            <span class="cl-section__icon cl-section__icon--daily"><i class="fas fa-sun"></i></span>
                            <div>
                                <h5>Kategori Harian</h5>
                                <small>Diisi tiap hari operasional</small>
                            </div>
                        </div>
                        <button type="button" class="st-btn-brand" data-toggle="modal" data-target="#categoryModalNewDaily">
                            <i class="fas fa-plus"></i> Tambah Kategori Harian
                        </button>
                    </div>
                    @if($dailyCategories->isEmpty())
                        <div class="cl-section__empty">
                            <i class="fas fa-folder-open"></i>
                            Belum ada kategori harian. Klik <strong>+ Tambah Kategori Harian</strong> untuk mulai.
                        </div>
                    @else
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            @foreach($dailyCategories as $idx => $cat)
                                @include('crud.checklist.setup._category_block', ['cat' => $cat, 'idx' => $idx + 1])
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ─── PANEL: KATEGORI MINGGUAN ─── --}}
                <div class="cl-cad-panel" data-cad-panel="weekly">
                    <div class="cl-section__head">
                        <div class="cl-section__title">
                            <span class="cl-section__icon cl-section__icon--weekly"><i class="fas fa-calendar-week"></i></span>
                            <div>
                                <h5>Kategori Mingguan</h5>
                                <small>Diisi pada hari tertentu (Selasa s/d Minggu)</small>
                            </div>
                        </div>
                        <button type="button" class="st-btn-brand" data-toggle="modal" data-target="#categoryModalNewWeekly">
                            <i class="fas fa-plus"></i> Tambah Kategori Mingguan
                        </button>
                    </div>
                    @if($weeklyCategories->isEmpty())
                        <div class="cl-section__empty">
                            <i class="fas fa-calendar-week"></i>
                            Belum ada kategori mingguan. Klik <strong>+ Tambah Kategori Mingguan</strong> untuk task yang dilakukan di hari tertentu.
                        </div>
                    @else
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            @foreach($weeklyCategories as $idx => $cat)
                                @include('crud.checklist.setup._category_block', ['cat' => $cat, 'idx' => $idx + 1])
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ─── Cadence tab + section styling ─── --}}
<style>
    .cl-cad-tabs {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .cl-cad-tab {
        flex: 1; min-width: 200px;
        display: flex; align-items: center; gap: 12px;
        padding: 12px 16px;
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 12px;
        cursor: pointer;
        text-align: left;
        transition: border-color .15s, box-shadow .15s, transform .15s;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .cl-cad-tab:hover { border-color: #C9B69A; box-shadow: 0 4px 12px rgba(45,24,16,.08); }
    .cl-cad-tab.is-active { border-color: #5C2E15; box-shadow: 0 4px 14px rgba(71,21,0,.12); transform: translateY(-1px); }
    .cl-cad-tab.is-active[data-cad-tab="daily"]  { border-left: 4px solid #A66019; }
    .cl-cad-tab.is-active[data-cad-tab="weekly"] { border-left: 4px solid #1F6E5F; }
    .cl-cad-tab__icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 15px; color: #FFFFFF;
        flex-shrink: 0;
    }
    .cl-cad-tab__icon--daily  { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 3px 8px rgba(166,96,25,.22); }
    .cl-cad-tab__icon--weekly { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 3px 8px rgba(31,110,95,.22); }
    .cl-cad-tab__body { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .cl-cad-tab__body strong { font-size: 14px; font-weight: 800; color: #2D1810; line-height: 1.2; }
    .cl-cad-tab__body small { font-size: 11.5px; color: #6B4423; }

    .cl-cad-panel { display: none; }
    .cl-cad-panel.is-active { display: block; }

    .cl-section__head {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap;
        padding: 14px 18px;
        background: #FFFFFF;
        border: 1px solid #E0D2C1;
        border-radius: 12px;
        margin-bottom: 12px;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    [data-cad-panel="weekly"] .cl-section__head { border-left: 4px solid #1F6E5F; }
    [data-cad-panel="daily"]  .cl-section__head { border-left: 4px solid #A66019; }
    .cl-section__title { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .cl-section__title h5 {
        margin: 0; font-size: 15px; font-weight: 800; color: #2D1810; line-height: 1.2;
    }
    .cl-section__title small {
        display: block; font-size: 11.5px; color: #6B4423; margin-top: 2px;
    }
    .cl-section__icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 15px; color: #FFFFFF;
        flex-shrink: 0;
    }
    .cl-section__icon--daily  { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 3px 8px rgba(166,96,25,.22); }
    .cl-section__icon--weekly { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 3px 8px rgba(31,110,95,.22); }
    .cl-section__empty {
        background: #FFFFFF;
        border: 1px dashed #C9B69A;
        border-radius: 12px;
        padding: 26px 18px;
        text-align: center;
        font-size: 12.5px;
        color: #6B4423;
        font-style: italic;
    }
    .cl-section__empty i { display: block; font-size: 22px; color: #C9B69A; margin-bottom: 6px; font-style: normal; }
</style>

<script>
(function () {
    const tabs   = document.querySelectorAll('[data-cad-tab]');
    const panels = document.querySelectorAll('[data-cad-panel]');
    if (! tabs.length) return;

    // Restore last picked tab from sessionStorage (per-page key).
    const KEY = 'checklistSetup:cadTab';
    let saved = null;
    try { saved = sessionStorage.getItem(KEY); } catch (e) {}
    if (saved && (saved === 'daily' || saved === 'weekly')) {
        tabs.forEach(t => t.classList.toggle('is-active', t.dataset.cadTab === saved));
        panels.forEach(p => p.classList.toggle('is-active', p.dataset.cadPanel === saved));
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.cadTab;
            tabs.forEach(t => t.classList.toggle('is-active', t.dataset.cadTab === target));
            panels.forEach(p => p.classList.toggle('is-active', p.dataset.cadPanel === target));
            try { sessionStorage.setItem(KEY, target); } catch (e) {}
        });
    });
})();
</script>

{{-- ──────── MODAL: Tambah Kategori — Harian ──────── --}}
@if($division)
<div class="modal fade" id="categoryModalNewDaily" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('checklist.setup.cat.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="division_id" value="{{ $division->id }}">
            <input type="hidden" name="cadence" value="daily">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sun" style="color:#A66019; font-size:13px; margin-right:6px;"></i>
                    Tambah Kategori Harian — {{ $division->name }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div style="background:#FAEEDA; border:1px solid #E8C29A; border-radius:8px; padding:8px 12px; margin-bottom:14px; font-size:11.5px; color:#5C2E15;">
                    <i class="fas fa-sun" style="color:#A66019;"></i> <strong>Kategori Harian</strong> — task di dalamnya muncul di Form Hari Ini setiap hari operasional.
                </div>
                <div class="form-group">
                    <label>Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="128" required class="form-control" placeholder="Mis: Persiapan Ruang &amp; Alat" autofocus>
                </div>
                <div class="form-group">
                    <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                    <textarea name="description" rows="2" maxlength="255" class="form-control" placeholder="Penjelasan singkat kategori ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                <button type="submit" class="st-btn-brand"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- ──────── MODAL: Tambah Kategori — Mingguan ──────── --}}
<div class="modal fade" id="categoryModalNewWeekly" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('checklist.setup.cat.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="division_id" value="{{ $division->id }}">
            <input type="hidden" name="cadence" value="weekly">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-week" style="color:#1F6E5F; font-size:13px; margin-right:6px;"></i>
                    Tambah Kategori Mingguan — {{ $division->name }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div style="background:#E1F5EE; border:1px solid #B8DFCF; border-radius:8px; padding:8px 12px; margin-bottom:14px; font-size:11.5px; color:#155148;">
                    <i class="fas fa-calendar-week" style="color:#1F6E5F;"></i> <strong>Kategori Mingguan</strong> — task akan muncul di Form Mingguan dan tiap task wajib di-tag hari (Selasa s/d Minggu — Senin tutup).
                </div>
                <div class="form-group">
                    <label>Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" maxlength="128" required class="form-control" placeholder="Mis: Laporan Mingguan Sales" autofocus>
                </div>
                <div class="form-group">
                    <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                    <textarea name="description" rows="2" maxlength="255" class="form-control" placeholder="Penjelasan singkat kategori ini..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                <button type="submit" class="st-btn-brand"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal-modal edit kategori + task per kategori (di-generate sekali per kategori/task) --}}
@foreach($categories as $cat)
    {{-- MODAL: Edit Kategori --}}
    <div class="modal fade" id="categoryModalEdit-{{ $cat->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('checklist.setup.cat.update', $cat->id) }}" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" maxlength="128" required class="form-control" value="{{ $cat->name }}">
                    </div>
                    <div class="form-group">
                        <label>Frekuensi Pengisian <span class="text-danger">*</span></label>
                        <select name="cadence" class="form-control" required>
                            @foreach(\App\Models\ChecklistCategory::cadenceOptions() as $val => $opt)
                                <option value="{{ $val }}" {{ $cat->cadence === $val ? 'selected' : '' }}>{{ $opt['label'] }} — {{ $opt['desc'] }}</option>
                            @endforeach
                        </select>
                        @if($cat->cadence === \App\Models\ChecklistCategory::CADENCE_WEEKLY)
                            <small class="text-muted" style="font-size:11.5px;">Mengubah ke Harian akan mengosongkan kolom hari di semua task — submission lama tetap aman (snapshot).</small>
                        @endif
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" rows="2" maxlength="255" class="form-control">{{ $cat->description }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                            <input type="checkbox" name="is_active" value="1" {{ $cat->is_active ? 'checked' : '' }}>
                            Kategori aktif (kalau dimatikan, task di dalamnya tidak muncul di submission baru)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Form hapus kategori (di luar form edit supaya nested form tidak invalid HTML) --}}
    <form method="POST" action="{{ route('checklist.setup.cat.destroy', $cat->id) }}" id="catDestroy-{{ $cat->id }}" style="display:none;"
          onsubmit="return confirm('Hapus kategori &quot;{{ $cat->name }}&quot;? Snapshot di submission lama tetap aman.');">
        @csrf @method('DELETE')
    </form>

    {{-- MODAL: Tambah Task ke kategori ini --}}
    <div class="modal fade" id="taskModalNew-{{ $cat->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('checklist.setup.task.store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="category_id" value="{{ $cat->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Task — {{ $cat->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Judul Task <span class="text-danger">*</span></label>
                        <input type="text" name="title" maxlength="255" required class="form-control" placeholder="Mis: Membuka ruang tindakan" autofocus>
                    </div>
                    @if($cat->isWeekly())
                        <div class="form-group">
                            <label>Hari Pengerjaan <span class="text-danger">*</span></label>
                            <select name="day_of_week" class="form-control" required>
                                <option value="">— Pilih hari —</option>
                                @foreach(\App\Models\ChecklistTask::dayLabels() as $dn => $dl)
                                    <option value="{{ $dn }}">{{ $dl }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted" style="font-size:11.5px;">Task ini hanya muncul di Form Mingguan pada hari yang dipilih.</small>
                        </div>
                    @endif
                    <div class="form-group">
                        <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                        <textarea name="description" rows="2" maxlength="1000" class="form-control" placeholder="Detail / SOP singkat..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                            <input type="checkbox" name="requires_note" value="1">
                            Karyawan harus isi catatan saat centang
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                    <button type="submit" class="st-btn-brand"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL: Edit Task per task --}}
    @foreach($cat->tasks->whereNull('deleted_at') as $task)
        <div class="modal fade" id="taskModalEdit-{{ $task->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('checklist.setup.task.update', $task->id) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Task</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Judul Task <span class="text-danger">*</span></label>
                            <input type="text" name="title" maxlength="255" required class="form-control" value="{{ $task->title }}">
                        </div>
                        @if($cat->isWeekly())
                            <div class="form-group">
                                <label>Hari Pengerjaan <span class="text-danger">*</span></label>
                                <select name="day_of_week" class="form-control" required>
                                    <option value="">— Pilih hari —</option>
                                    @foreach(\App\Models\ChecklistTask::dayLabels() as $dn => $dl)
                                        <option value="{{ $dn }}" {{ (int) $task->day_of_week === $dn ? 'selected' : '' }}>{{ $dl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <textarea name="description" rows="2" maxlength="1000" class="form-control">{{ $task->description }}</textarea>
                        </div>
                        <div class="form-group">
                            <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                                <input type="checkbox" name="requires_note" value="1" {{ $task->requires_note ? 'checked' : '' }}>
                                Wajib isi catatan saat centang
                            </label>
                        </div>
                        <div class="form-group">
                            <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                                <input type="checkbox" name="is_active" value="1" {{ $task->is_active ? 'checked' : '' }}>
                                Task aktif (kalau dimatikan, tidak muncul di submission baru)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="st-btn-outline-brown" data-dismiss="modal">Batal</button>
                        <button type="submit" class="st-btn-brand"><i class="fas fa-save"></i> Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Form hapus task --}}
        <form method="POST" action="{{ route('checklist.setup.task.destroy', $task->id) }}" id="taskDestroy-{{ $task->id }}" style="display:none;"
              onsubmit="return confirm('Hapus task &quot;{{ $task->title }}&quot;?');">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endforeach
@endif
@endsection
