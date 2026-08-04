{{--
    PARTIAL: 1 blok kategori (header + tabel task).
    Variable:
      $cat → ChecklistCategory dengan eager load tasks
      $idx → nomor urut display
--}}
@php
    $tasksActive = $cat->tasks->where('deleted_at', null)->values();
    $countActive = $tasksActive->where('is_active', true)->count();
@endphp

<div class="cl-cat is-collapsed {{ $cat->isWeekly() ? 'cl-cat--weekly' : 'cl-cat--daily' }}">
    <div class="cl-cat__head">
        <div class="cl-cat__head-left" data-cat-toggle title="Klik untuk buka/tutup">
            <span class="cl-cat__num" style="background:#F4E7D6;color:#8A3A12;box-shadow:none">{{ $idx }}</span>
            <div style="min-width:0;">
                <div class="cl-cat__title">
                    {{ $cat->name }}
                    @if($cat->isWeekly())
                        <span style="font-size:10px;font-weight:800;color:#1F6E5F;background:#E0EFEB;border-radius:20px;padding:2px 8px;margin-left:6px;vertical-align:middle">Mingguan</span>
                    @else
                        <span style="font-size:10px;font-weight:800;color:#A66019;background:#FBEFD9;border-radius:20px;padding:2px 8px;margin-left:6px;vertical-align:middle">Harian</span>
                    @endif
                    @if(! $cat->is_active)
                        <span style="font-size:10px;font-weight:800;color:#9B8578;background:#EFE7DE;border-radius:20px;padding:2px 8px;margin-left:6px;vertical-align:middle">Nonaktif</span>
                    @endif
                    <span class="cl-cat__chip">{{ $countActive }} tugas aktif</span>
                </div>
                @if($cat->description)
                    <div class="cl-cat__desc">{{ $cat->description }}</div>
                @endif
            </div>
            <i class="fas fa-chevron-down cl-cat__caret"></i>
        </div>
        <div class="cl-cat__actions">
            <button type="button" class="cl-btn-tan" style="display:inline-flex;align-items:center;gap:6px;background:#F4E7D6;color:#8A3A12;border:none;border-radius:9px;padding:7px 13px;font-weight:700;font-size:12px;font-family:inherit;cursor:pointer" data-toggle="modal" data-target="#taskModalNew-{{ $cat->id }}">
                <i class="fas fa-plus"></i> Tambah Tugas
            </button>
            <button type="button" class="st-icon-btn" title="Edit kategori" data-toggle="modal" data-target="#categoryModalEdit-{{ $cat->id }}">
                <i class="fas fa-pen"></i>
            </button>
            <button type="button" class="st-icon-btn" title="Hapus kategori"
                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);"
                    data-cl-del-form="catDestroy-{{ $cat->id }}"
                    data-cl-del-msg="Hapus kategori &quot;{{ $cat->name }}&quot;? Snapshot di submission lama tetap aman.">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>

    <div class="cl-cat__body">
    <div class="cl-cat__body-inner">
    @if($tasksActive->isEmpty())
        <div style="padding:28px 16px; text-align:center; color:var(--cl-subtle); font-size:12.5px; font-style:italic; background:var(--cl-cream-1);">
            <i class="fas fa-clipboard" style="font-size:18px; display:block; margin-bottom:6px; color:var(--cl-line-strong);"></i>
            Belum ada tugas di kategori ini. Klik <strong style="color:var(--cl-brown-2);">+ Tambah Tugas</strong> untuk mulai.
        </div>
    @else
        <table class="st-table" style="margin:0; border:none;">
            <thead>
                <tr>
                    <th style="width:48px;">#</th>
                    @if($cat->isWeekly())
                        <th style="width:90px;">Hari</th>
                    @endif
                    <th>Tugas</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:110px; text-align:center;">Edit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tasksActive as $i => $task)
                    <tr>
                        <td style="color:var(--cl-subtle); font-weight:600;">{{ $i + 1 }}</td>
                        @if($cat->isWeekly())
                            <td>
                                @if($task->day_of_week)
                                    <span class="st-pill st-pill--info" style="font-size:10.5px;">{{ \App\Models\ChecklistTask::dayShortLabels()[$task->day_of_week] ?? '—' }}</span>
                                @else
                                    <span style="font-size:11px; color:#A32D2D; font-style:italic;">Belum diset</span>
                                @endif
                            </td>
                        @endif
                        <td>
                            <div style="font-weight:600; color:var(--cl-ink);">{{ $task->title }}</div>
                            @if($task->description)
                                <div style="font-size:11.5px; color:var(--cl-subtle); margin-top:2px;">{{ Str::limit($task->description, 100) }}</div>
                            @endif
                            @if($task->requires_photo)
                                <span style="display:inline-block; margin-top:4px; font-size:10.5px; padding:2px 8px; background:var(--cl-warn-bg, #FAEEDA); color:var(--cl-warn, #A66019); border-radius:10px; font-weight:600;">
                                    <i class="fas fa-camera" style="font-size:9px;"></i> {{ $task->photo_before_after ? 'Foto before/after' : 'Perlu foto' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($task->is_active)
                                <span class="st-pill st-pill--success">Aktif</span>
                            @else
                                <span class="st-pill st-pill--neutral">Nonaktif</span>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="st-icon-btn" title="Edit tugas" data-toggle="modal" data-target="#taskModalEdit-{{ $task->id }}">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="st-icon-btn" title="Hapus tugas"
                                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);"
                                    data-cl-del-form="taskDestroy-{{ $task->id }}"
                                    data-cl-del-msg="Hapus tugas &quot;{{ $task->title }}&quot;?">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </div>{{-- /cl-cat__body-inner --}}
    </div>{{-- /cl-cat__body --}}
</div>
