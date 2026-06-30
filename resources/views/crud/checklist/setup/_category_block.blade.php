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

<div class="cl-cat">
    <div class="cl-cat__head">
        <div class="cl-cat__head-left">
            <span class="cl-cat__num">{{ $idx }}</span>
            <div style="min-width:0;">
                <div class="cl-cat__title">
                    {{ $cat->name }}
                    @if($cat->isWeekly())
                        <span class="st-pill st-pill--info" style="margin-left:6px; vertical-align:middle;"><i class="fas fa-calendar-week" style="font-size:9px;"></i> Mingguan</span>
                    @else
                        <span class="st-pill st-pill--success" style="margin-left:6px; vertical-align:middle;"><i class="fas fa-sun" style="font-size:9px;"></i> Harian</span>
                    @endif
                    @if(! $cat->is_active)
                        <span class="st-pill st-pill--neutral" style="margin-left:6px; vertical-align:middle;">Nonaktif</span>
                    @endif
                    <span class="cl-cat__chip">{{ $countActive }} tugas aktif</span>
                </div>
                @if($cat->description)
                    <div class="cl-cat__desc">{{ $cat->description }}</div>
                @endif
            </div>
        </div>
        <div class="cl-cat__actions">
            <button type="button" class="st-btn-outline-brown" style="padding:6px 14px; font-size:12px;" data-toggle="modal" data-target="#taskModalNew-{{ $cat->id }}">
                <i class="fas fa-plus"></i> Tambah Task
            </button>
            <button type="button" class="st-icon-btn" title="Edit kategori" data-toggle="modal" data-target="#categoryModalEdit-{{ $cat->id }}">
                <i class="fas fa-pen"></i>
            </button>
            <button type="button" class="st-icon-btn" title="Hapus kategori"
                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);"
                    onclick="document.getElementById('catDestroy-{{ $cat->id }}').requestSubmit();">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>

    @if($tasksActive->isEmpty())
        <div style="padding:28px 16px; text-align:center; color:var(--cl-subtle); font-size:12.5px; font-style:italic; background:var(--cl-cream-1);">
            <i class="fas fa-clipboard" style="font-size:18px; display:block; margin-bottom:6px; color:var(--cl-line-strong);"></i>
            Belum ada task di kategori ini. Klik <strong style="color:var(--cl-brown-2);">+ Tambah Task</strong> untuk mulai.
        </div>
    @else
        <table class="st-table" style="margin:0; border:none;">
            <thead>
                <tr>
                    <th style="width:48px;">#</th>
                    @if($cat->isWeekly())
                        <th style="width:90px;">Hari</th>
                    @endif
                    <th>Task</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:110px; text-align:center;">Aksi</th>
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
                            @if($task->requires_note)
                                <span style="display:inline-block; margin-top:4px; font-size:10.5px; padding:2px 8px; background:var(--cl-info-bg); color:var(--cl-info); border-radius:10px; font-weight:600;">
                                    <i class="fas fa-pen-to-square" style="font-size:9px;"></i> Wajib catatan
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
                            <button type="button" class="st-icon-btn" title="Edit task" data-toggle="modal" data-target="#taskModalEdit-{{ $task->id }}">
                                <i class="fas fa-pen"></i>
                            </button>
                            <button type="button" class="st-icon-btn" title="Hapus task"
                                    style="color:var(--cl-danger); border-color:var(--cl-danger-bg);"
                                    onclick="document.getElementById('taskDestroy-{{ $task->id }}').requestSubmit();">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
