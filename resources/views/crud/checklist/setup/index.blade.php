@extends('layouts.app')

@section('content')
<div class="container-fluid">

    @include('layouts.notif')

    <div style="max-width:1240px;margin:0 auto">

    {{-- ─── HERO ─── --}}
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #EFE0CE;border-radius:16px;padding:18px 22px;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div style="flex:1;min-width:240px">
            <div style="font-size:21px;font-weight:800;color:#3B1500">Atur Tugas Checklist @if($division)— {{ $division->name }}@endif</div>
            <div style="font-size:13px;color:#9A7256;margin-top:3px">Tentukan daftar pekerjaan untuk tiap divisi. <span style="color:#B07B45">Edit master tidak mengubah history isian lama.</span></div>
        </div>
        <a href="{{ route('checklist') }}" class="cl-btn-dash" style="flex:none;background:#fff;color:#5A3A22;border:1px solid #E7D3BE;border-radius:11px;padding:10px 16px;font-weight:700;font-size:13px;text-decoration:none">← Dashboard</a>
    </div>

    {{-- ─── INFO / PENGUMUMAN KARYAWAN (running-text global) ─── --}}
    @php $hasInfo = trim((string) checklistInfoText()) !== ''; @endphp
    <div style="background:#fff;border:1px solid #EFE0CE;border-radius:16px;overflow:hidden;box-shadow:0 1px 2px rgba(70,21,0,.04);margin-bottom:14px">
        <div id="clInfoToggle" style="display:flex;align-items:center;gap:11px;padding:14px 18px;cursor:pointer">
            <i class="fas fa-bullhorn" style="color:#8A3A12;font-size:15px"></i>
            <span style="font-size:13.5px;font-weight:800;color:#3B1500">Info / Pengumuman Karyawan</span>
            <span id="clInfoTayang" style="font-size:10.5px;font-weight:800;color:#2F8E55;background:#E3F4E8;border-radius:20px;padding:2px 8px;{{ $hasInfo ? '' : 'display:none' }}">Tayang</span>
            <i class="fas fa-chevron-down" id="clInfoCaret" style="margin-left:auto;color:#9A7256;font-size:12px;transition:transform .18s"></i>
        </div>
        <div id="clInfoBody" style="display:none;padding:0 18px 16px;border-top:1px solid #F6EEE1">
            <form method="POST" action="{{ route('checklist.setup.info') }}">
                @csrf
                <textarea name="info_text" id="clInfoText" rows="2" maxlength="500"
                          placeholder="Mis: Isi & submit checklist sebelum pulang. • Jaga kebersihan sesuai SOP. • Sapa pasien dengan senyum."
                          style="margin-top:14px;width:100%;box-sizing:border-box;border:1px solid #E7D3BE;border-radius:11px;padding:11px 13px;font-family:inherit;font-size:13px;color:#3B1500;background:#FFFCF8;resize:vertical;line-height:1.5">{{ checklistInfoText() }}</textarea>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px">
                    <span style="font-size:11px;color:#B79A82">Pakai • untuk memisah kalimat. Kosongkan untuk menyembunyikan banner.</span>
                    <span id="clInfoCount" style="margin-left:auto;font-size:11px;color:#9A7256">0/500</span>
                </div>
                <div id="clInfoPrevWrap" style="margin-top:10px;background:linear-gradient(120deg,#3A1505,#52200A);border-radius:11px;padding:9px 0;overflow:hidden;{{ $hasInfo ? '' : 'display:none' }}">
                    <div style="white-space:nowrap;display:inline-block;animation:clMarq 16s linear infinite">
                        <span class="clInfoPrev" style="color:#FBEADB;font-size:12.5px;font-weight:600;padding-right:80px">{{ checklistInfoText() }}</span>
                        <span class="clInfoPrev" style="color:#FBEADB;font-size:12.5px;font-weight:600;padding-right:80px">{{ checklistInfoText() }}</span>
                    </div>
                </div>
                <div style="margin-top:12px"><button type="submit" style="background:linear-gradient(135deg,#6B2400,#461500);color:#FFEAD6;border:none;border-radius:10px;padding:10px 18px;font-weight:700;font-size:12.5px;font-family:inherit;cursor:pointer">Simpan Info</button></div>
            </form>
        </div>
    </div>
    <style>@keyframes clMarq{from{transform:translateX(0)}to{transform:translateX(-50%)}}</style>
    <script>
    (function () {
        var tog = document.getElementById('clInfoToggle'), body = document.getElementById('clInfoBody'), caret = document.getElementById('clInfoCaret');
        var ta = document.getElementById('clInfoText'), count = document.getElementById('clInfoCount'), tayang = document.getElementById('clInfoTayang'), prevWrap = document.getElementById('clInfoPrevWrap');
        var prevs = document.querySelectorAll('.clInfoPrev');
        if (tog && body) tog.addEventListener('click', function () {
            var open = body.style.display !== 'none';
            body.style.display = open ? 'none' : 'block';
            if (caret) caret.style.transform = open ? '' : 'rotate(180deg)';
        });
        if (ta) {
            var sync = function () {
                var t = ta.value.trim();
                if (count) count.textContent = ta.value.length + '/500';
                prevs.forEach(function (el) { el.textContent = t || ' '; });
                var has = t !== '';
                if (tayang) tayang.style.display = has ? '' : 'none';
                if (prevWrap) prevWrap.style.display = has ? '' : 'none';
            };
            ta.addEventListener('input', sync);
            sync();
        }
    })();
    </script>

    {{-- ─── DIVISION ROW (segmented + chip ringkas) ─── --}}
    @if($division)
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <div style="display:flex;background:#fff;border:1px solid #EADCC9;border-radius:12px;padding:4px;gap:3px">
            @foreach($divisions as $div)
                @php
                    $on = $division->id === $div->id;
                @endphp
                <a href="{{ route('checklist.setup', ['division' => $div->id]) }}" class="cl-seg-tab" style="display:inline-flex;align-items:center;padding:8px 16px;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;{{ $on ? 'background:linear-gradient(135deg,#6B2400,#461500);color:#FFEAD6' : 'color:#7A5B43' }}">{{ $div->name }}</a>
            @endforeach
        </div>
        <span style="font-size:12px;font-weight:700;color:#7A5B43;background:#F4E7D6;border-radius:20px;padding:5px 12px">{{ $categories->count() }} kategori</span>
        <span style="font-size:12px;font-weight:700;color:#7A5B43;background:#F4E7D6;border-radius:20px;padding:5px 12px">{{ $totalTasks }} tugas aktif</span>
        <span style="margin-left:auto;font-size:12px;color:#9A7256">Divisi: <b style="color:#5A3A22">{{ $division->name }}</b></span>
    </div>
    @endif

    <div>
            @if(! $division)
                <div class="cl-empty">
                    <span class="cl-empty__icon"><i class="fas fa-exclamation-circle"></i></span>
                    <div class="cl-empty__title">Belum ada divisi checklist</div>
                    <div class="cl-empty__desc">Belum ada divisi checklist yang aktif. Hubungi admin/IT untuk menyiapkan divisi (FO / GS / Asdent) terlebih dahulu.</div>
                </div>
            @else
                {{-- ─── TYPE SELECTOR (Harian / Mingguan) ─── --}}
                <div class="cl-cad-tabs" data-cad-tabs role="tablist">
                    <button type="button" class="cl-cad-tab cl-cad-tab--daily is-active" data-cad-tab="daily" role="tab">
                        <span class="cl-cad-tab__icon"><i class="fas fa-sun"></i></span>
                        <span class="cl-cad-tab__body">
                            <strong>Checklist Harian</strong>
                            <small>{{ $dailyCategories->count() }} kategori · {{ $dailyTaskCount }} tugas</small>
                        </span>
                        <span class="cl-cad-tab__badge">AKTIF</span>
                    </button>
                    <button type="button" class="cl-cad-tab cl-cad-tab--weekly" data-cad-tab="weekly" role="tab">
                        <span class="cl-cad-tab__icon"><i class="fas fa-calendar-week"></i></span>
                        <span class="cl-cad-tab__body">
                            <strong>Checklist Mingguan</strong>
                            <small>{{ $weeklyTaskCount }} tugas terjadwal</small>
                        </span>
                        <span class="cl-cad-tab__badge">AKTIF</span>
                    </button>
                </div>

                {{-- ─── PANEL: KATEGORI HARIAN ─── --}}
                <div class="cl-cad-panel is-active" data-cad-panel="daily">
                    <div class="cl-cad-bar cl-cad-bar--daily">
                        <span class="cl-cad-bar__hint"><i class="fas fa-sun"></i> Tugas muncul di Form Hari Ini setiap hari operasional</span>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            @if($dailyCategories->count() > 1)
                                <button type="button" class="st-btn-outline-brown" id="clExpandAll" data-mode="expand" style="padding:6px 14px; font-size:12px;">
                                    <i class="fas fa-angle-double-down"></i> <span data-label>Buka semua</span>
                                </button>
                            @endif
                            <button type="button" class="st-btn-brand" data-toggle="modal" data-target="#categoryModalNewDaily">
                                <i class="fas fa-plus"></i> Tambah Kategori
                            </button>
                        </div>
                    </div>
                    @if($dailyCategories->isEmpty())
                        <div class="cl-section__empty">
                            <i class="fas fa-folder-open"></i>
                            Belum ada kategori harian. Klik <strong>+ Tambah Kategori</strong> untuk mulai.
                        </div>
                    @else
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            @foreach($dailyCategories as $idx => $cat)
                                @include('crud.checklist.setup._category_block', ['cat' => $cat, 'idx' => $idx + 1])
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ─── PANEL: TASK MINGGUAN (langsung per hari, tanpa kategori) ─── --}}
                <div class="cl-cad-panel" data-cad-panel="weekly">
                    <div class="cl-cad-bar cl-cad-bar--weekly">
                        <span class="cl-cad-bar__hint"><i class="fas fa-calendar-week"></i> Tugas langsung dijadwalkan per hari (Selasa–Minggu · Senin klinik tutup).</span>
                        <button type="button" class="cl-btn-teal" style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#1F6E5F,#155148);color:#E4F3EF;border:none;border-radius:10px;padding:9px 16px;font-weight:700;font-size:12.5px;font-family:inherit;cursor:pointer" data-toggle="modal" data-target="#weeklyTaskModalNew">
                            <i class="fas fa-plus"></i> Tambah Tugas
                        </button>
                    </div>
                    @if($weeklyTaskCount === 0)
                        <div class="cl-section__empty">
                            <i class="fas fa-calendar-week"></i>
                            Belum ada tugas mingguan. Klik <strong>+ Tambah Tugas</strong>, isi judul lalu pilih harinya.
                        </div>
                    @else
                        <div class="cl-wdays">
                            @foreach(\App\Models\ChecklistTask::dayLabels() as $dow => $dayLabel)
                                @php $dayTasks = $weeklyTasksByDay->get($dow, collect()); @endphp
                                <div class="cl-wday {{ $dayTasks->isEmpty() ? 'is-empty' : '' }}">
                                    <div class="cl-wday__head">
                                        <span class="cl-wday__day"><i class="fas fa-calendar-day"></i> {{ $dayLabel }}</span>
                                        <span class="cl-wday__count">{{ $dayTasks->count() }} tugas</span>
                                    </div>
                                    @if($dayTasks->isEmpty())
                                        <div class="cl-wday__empty">Belum ada tugas</div>
                                    @else
                                        @foreach($dayTasks as $task)
                                            <div class="cl-wtask {{ $task->is_active ? '' : 'is-off' }}">
                                                <div class="cl-wtask__main">
                                                    <span class="cl-wtask__title">{{ $task->title }}</span>
                                                    @if($task->weeksOfMonthArray())
                                                        <span class="cl-wtask__tag" style="background:#EDE3FB;color:#6B3FA0;" title="Berlaku minggu ke-{{ implode(', ', $task->weeksOfMonthArray()) }} tiap bulan"><i class="fas fa-sync-alt"></i> {{ $task->weeksLabel() }}</span>
                                                    @endif
                                                    @if($task->requires_photo)
                                                        <span class="cl-wtask__tag cl-wtask__tag--photo"><i class="fas fa-camera"></i> {{ $task->photo_before_after ? 'Foto before/after' : 'Perlu foto' }}</span>
                                                    @endif
                                                    @if(! $task->is_active)
                                                        <span style="font-size:10px;font-weight:800;color:#9B8578;background:#EFE7DE;border-radius:20px;padding:3px 9px">Nonaktif</span>
                                                    @endif
                                                </div>
                                                <div class="cl-wtask__actions">
                                                    <button type="button" class="st-icon-btn" title="Edit tugas" data-toggle="modal" data-target="#taskModalEdit-{{ $task->id }}">
                                                        <i class="fas fa-pen"></i>
                                                    </button>
                                                    <button type="button" class="st-icon-btn" title="Hapus tugas"
                                                            style="color:var(--cl-danger); border-color:var(--cl-danger-bg);"
                                                            data-cl-del-form="taskDestroy-{{ $task->id }}"
                                                            data-cl-del-msg="Hapus tugas &quot;{{ $task->title }}&quot;?">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
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
    .cl-cad-label {
        font-size: 12px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .5px; color: #5C2E15; margin-bottom: 9px;
    }
    .cl-cad-tabs {
        display: flex; gap: 10px; flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .cl-cad-tab {
        flex: 1; min-width: 220px;
        display: flex; align-items: center; gap: 12px;
        padding: 13px 16px;
        background: #FFFFFF;
        border: 1.5px solid #E0D2C1;
        border-radius: 14px;
        cursor: pointer;
        text-align: left;
        transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
        box-shadow: 0 2px 6px rgba(45,24,16,.04);
    }
    .cl-cad-tab:hover { border-color: #C9B69A; box-shadow: 0 4px 12px rgba(45,24,16,.08); }
    .cl-cad-tab__icon {
        width: 40px; height: 40px;
        border-radius: 11px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 16px; color: #FFFFFF;
        flex-shrink: 0;
    }
    .cl-cad-tab--daily  .cl-cad-tab__icon { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 3px 8px rgba(166,96,25,.22); }
    .cl-cad-tab--weekly .cl-cad-tab__icon { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 3px 8px rgba(31,110,95,.22); }
    .cl-cad-tab__body { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: 2px; }
    .cl-cad-tab__body strong { font-size: 15px; font-weight: 800; color: #2D1810; line-height: 1.2; }
    .cl-cad-tab__body small { font-size: 11.5px; color: #6B4423; }
    .cl-cad-tab__badge {
        opacity: 0; flex-shrink: 0;
        font-size: 10.5px; font-weight: 800; color: #FFFFFF;
        background: rgba(255,255,255,.2); border-radius: 20px; padding: 3px 10px;
        transition: opacity .16s ease;
    }

    /* Aktif = kartu terisi penuh, jelas mana yang sedang dipilih */
    .cl-cad-tab.is-active { border-color: transparent; transform: translateY(-1px); }
    .cl-cad-tab--daily.is-active  { background: linear-gradient(135deg, #A66019, #5C2E15); box-shadow: 0 7px 18px rgba(92,46,21,.28); }
    .cl-cad-tab--weekly.is-active { background: linear-gradient(135deg, #1F6E5F, #155148); box-shadow: 0 7px 18px rgba(21,81,72,.28); }
    .cl-cad-tab.is-active .cl-cad-tab__body strong { color: #FFFFFF; }
    .cl-cad-tab.is-active .cl-cad-tab__body small { color: #FFFFFF; opacity: .82; }
    .cl-cad-tab.is-active .cl-cad-tab__icon { background: rgba(255,255,255,.18); box-shadow: none; }
    .cl-cad-tab.is-active .cl-cad-tab__badge { opacity: 1; }

    .cl-cad-panel { display: none; }
    .cl-cad-panel.is-active { display: block; }

    /* Bar tipis pengganti judul besar — konteks Harian/Mingguan sudah dari tab aktif */
    /* Wrapper toolbar — kartu putih dgn bayangan halus (lebih tipis dari kartu
       kategori) supaya kebaca sebagai 'toolbar', ikon chip jadi jangkar warna. */
    .cl-cad-bar {
        display: flex; justify-content: space-between; align-items: center;
        gap: 12px; flex-wrap: wrap;
        margin-bottom: 12px;
    }
    .cl-cad-bar__hint {
        font-size: 12.5px; color: #9A7256; font-weight: 600;
        display: inline-flex; align-items: center; gap: 8px; min-width: 0;
    }
    .cl-cad-bar__hint i { display: none; }

    /* ── Daftar task mingguan per hari (tanpa kategori) — gaya handoff:
       kartu putih polos (tanpa aksen kiri/gradient), chip hari teal,
       hari kosong memudar dgn teks italic. ── */
    .cl-wdays { display: flex; flex-direction: column; gap: 12px; }
    .cl-wday {
        background: #FFFFFF;
        border: 1px solid #EFE0CE;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(70,21,0,.04);
    }
    .cl-wday__head {
        display: flex; align-items: center;
        gap: 10px; padding: 12px 16px;
        border-bottom: 1px solid #F1E6D7;
    }
    .cl-wday__day {
        font-size: 13.5px; font-weight: 800; color: #3B1500;
        display: inline-flex; align-items: center; gap: 8px;
    }
    .cl-wday__day i { display: none; }
    .cl-wday__count {
        font-size: 11px; font-weight: 800; color: #1F6E5F;
        background: #E0EFEB; padding: 2px 9px; border-radius: 20px;
    }
    .cl-wday__empty {
        padding: 13px 16px; font-size: 12px;
        color: #C7AC92; font-style: italic;
    }
    /* Hari tanpa tugas → kartu memudar (55%) seperti prototipe */
    .cl-wday.is-empty { opacity: .55; }
    .cl-wday.is-empty .cl-wday__head { border-bottom: none; }
    .cl-wday.is-empty .cl-wday__count { background: #EFE7DE; color: #9B8578; }
    .cl-wtask {
        display: flex; justify-content: space-between; align-items: center;
        gap: 9px; padding: 11px 16px;
        border-top: 1px solid #F6EEE1;
    }
    .cl-wtask:first-of-type { border-top: none; }
    .cl-wtask.is-off .cl-wtask__title { color: #B79A82; }
    .cl-wtask__main { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-width: 0; flex: 1; }
    .cl-wtask__title { font-size: 13px; font-weight: 600; color: #3B1500; }
    .cl-wtask__tag {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 10px; font-weight: 800; padding: 3px 9px; border-radius: 20px;
    }
    .cl-wtask__tag i { font-size: 9px; }
    .cl-wtask__tag--photo { background: #E0EFEB; color: #1F6E5F; }
    .cl-wtask__tag--note  { background: var(--cl-info-bg, #E7F1FB); color: var(--cl-info, #2B6CB0); }
    .cl-wtask__actions { display: flex; gap: 6px; flex-shrink: 0; }

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

    /* ─── Field "Jadwal tugas" (rotasi mingguan) ─── */
    .cl-wk__modes { display: flex; gap: 8px; flex-wrap: wrap; }
    .cl-wk__mode { flex: 1; min-width: 160px; margin: 0; }
    .cl-wk__mode > input { position: absolute; opacity: 0; width: 0; height: 0; }
    .cl-wk__mode > span {
        display: flex; flex-direction: column; gap: 2px;
        border: 1.5px solid #E7D9C7; border-radius: 11px; padding: 10px 13px;
        background: #FFFDFB; cursor: pointer; transition: border-color .12s, background .12s, box-shadow .12s;
    }
    .cl-wk__mode > span strong { font-size: 12.5px; font-weight: 700; color: #5C2E15; }
    .cl-wk__mode > span small  { font-size: 10.5px; color: #9B8578; line-height: 1.3; }
    .cl-wk__mode > input:checked + span { border-color: #9B5E2C; background: #FBF1E7; box-shadow: inset 0 0 0 1px #9B5E2C; }
    .cl-wk__mode > input:focus-visible + span { outline: 2px solid #C98A4B; outline-offset: 1px; }

    .cl-wk__weeks { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
    .cl-wk__weeks.is-hidden { display: none; }
    .cl-wk__chip { margin: 0; }
    .cl-wk__chip > input { position: absolute; opacity: 0; width: 0; height: 0; }
    .cl-wk__chip > span {
        display: inline-flex; align-items: center; justify-content: center; gap: 5px;
        min-width: 66px; padding: 8px 14px; border-radius: 22px;
        border: 1.5px solid #E7D9C7; background: #FFFDFB;
        font-size: 12px; font-weight: 700; color: #7A5B43; cursor: pointer;
        transition: border-color .12s, background .12s, color .12s;
    }
    .cl-wk__chip > input:checked + span { border-color: #6B3FA0; background: #EDE3FB; color: #6B3FA0; }
    .cl-wk__chip > input:checked + span::before { content: "\f00c"; font-family: "Font Awesome 5 Free"; font-weight: 900; font-size: 10px; }
    .cl-wk__chip > input:focus-visible + span { outline: 2px solid #B79AE0; outline-offset: 1px; }
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
<div class="modal fade cl-modal" id="categoryModalNewDaily" tabindex="-1" role="dialog" aria-hidden="true">
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

{{-- ──────── MODAL: Tambah Tugas — Mingguan (langsung pilih hari) ──────── --}}
<div class="modal fade cl-modal" id="weeklyTaskModalNew" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ route('checklist.setup.weekly-task.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="division_id" value="{{ $division->id }}">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-week" style="color:#1F6E5F; font-size:13px; margin-right:6px;"></i>
                    Tambah Tugas Mingguan — {{ $division->name }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div style="background:#E1F5EE; border:1px solid #B8DFCF; border-radius:8px; padding:8px 12px; margin-bottom:14px; font-size:11.5px; color:#155148;">
                    <i class="fas fa-calendar-week" style="color:#1F6E5F;"></i> Tugas langsung dijadwalkan ke satu hari — tidak perlu bikin kategori.
                </div>
                <div class="form-group">
                    <label>Nama Tugas <span class="text-danger">*</span></label>
                    <input type="text" name="title" maxlength="255" required class="form-control" placeholder="Mis: Laporan stok opname" autofocus>
                </div>
                <div class="form-group">
                    <label>Hari Pengerjaan <span class="text-danger">*</span></label>
                    <select name="day_of_week" class="form-control" required>
                        <option value="">— Pilih hari —</option>
                        @foreach(\App\Models\ChecklistTask::dayLabels() as $dn => $dl)
                            <option value="{{ $dn }}">{{ $dl }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted" style="font-size:11.5px;">Tugas muncul di Form Mingguan pada hari ini (Senin klinik tutup).</small>
                </div>
                @include('crud.checklist.setup._weeks_of_month_field', ['selected' => []])
                <div class="form-group">
                    <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                    <textarea name="description" rows="2" maxlength="1000" class="form-control" placeholder="Detail / SOP singkat..."></textarea>
                </div>
                <div class="form-group">
                    <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                        <input type="checkbox" name="requires_photo" value="1" data-photo-parent>
                        Perlu foto (buka kamera)
                    </label>
                    <small class="text-muted" style="font-size:11.5px;">Muncul tombol kamera untuk melampirkan foto bukti — opsional (tidak memblokir centang).</small>
                    @if(\App\Models\ChecklistDivision::supportsPhotoBeforeAfter($division->id))
                        <label class="d-flex align-items-center" style="gap:8px; cursor:pointer; margin-top:8px; margin-left:22px;">
                            <input type="checkbox" name="photo_before_after" value="1" data-photo-ba>
                            Perlu foto before/after
                        </label>
                        <small class="text-muted" style="font-size:11.5px; margin-left:22px; display:block;">Dua slot foto: sebelum & sesudah pengerjaan.</small>
                    @endif
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
    <div class="modal fade cl-modal" id="categoryModalEdit-{{ $cat->id }}" tabindex="-1" role="dialog" aria-hidden="true">
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
                            <small class="text-muted" style="font-size:11.5px;">Mengubah ke Harian akan mengosongkan kolom hari di semua tugas — submission lama tetap aman (snapshot).</small>
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
    <form method="POST" action="{{ route('checklist.setup.cat.destroy', $cat->id) }}" id="catDestroy-{{ $cat->id }}" style="display:none;">
        @csrf @method('DELETE')
    </form>

    {{-- MODAL: Tambah Tugas ke kategori ini --}}
    <div class="modal fade cl-modal" id="taskModalNew-{{ $cat->id }}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('checklist.setup.task.store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="category_id" value="{{ $cat->id }}">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Tugas — {{ $cat->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Tugas <span class="text-danger">*</span></label>
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
                        @include('crud.checklist.setup._weeks_of_month_field', ['selected' => []])
                    @endif
                    <div class="form-group">
                        <label>Deskripsi <small class="text-muted">(opsional)</small></label>
                        <textarea name="description" rows="2" maxlength="1000" class="form-control" placeholder="Detail / SOP singkat..."></textarea>
                    </div>
                    @if($cat->isWeekly())
                        <div class="form-group">
                            <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                                <input type="checkbox" name="requires_photo" value="1" data-photo-parent>
                                Perlu foto (buka kamera)
                            </label>
                            <small class="text-muted" style="font-size:11.5px;">Muncul tombol kamera untuk melampirkan foto bukti — opsional (tidak memblokir centang).</small>
                            @if(\App\Models\ChecklistDivision::supportsPhotoBeforeAfter($division->id))
                                <label class="d-flex align-items-center" style="gap:8px; cursor:pointer; margin-top:8px; margin-left:22px;">
                                    <input type="checkbox" name="photo_before_after" value="1" data-photo-ba>
                                    Perlu foto before/after
                                </label>
                                <small class="text-muted" style="font-size:11.5px; margin-left:22px; display:block;">Dua slot foto: sebelum & sesudah pengerjaan.</small>
                            @endif
                        </div>
                    @endif
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
        <div class="modal fade cl-modal" id="taskModalEdit-{{ $task->id }}" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('checklist.setup.task.update', $task->id) }}" class="modal-content">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Tugas</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Nama Tugas <span class="text-danger">*</span></label>
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
                            @include('crud.checklist.setup._weeks_of_month_field', ['selected' => $task->weeksOfMonthArray()])
                        @endif
                        <div class="form-group">
                            <label>Deskripsi</label>
                            <textarea name="description" rows="2" maxlength="1000" class="form-control">{{ $task->description }}</textarea>
                        </div>
                        @if($cat->isWeekly())
                            <div class="form-group">
                                <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                                    <input type="checkbox" name="requires_photo" value="1" data-photo-parent {{ $task->requires_photo ? 'checked' : '' }}>
                                    Perlu foto (buka kamera)
                                </label>
                                <small class="text-muted" style="font-size:11.5px;">Muncul tombol kamera untuk melampirkan foto bukti — opsional (tidak memblokir centang).</small>
                                @if(\App\Models\ChecklistDivision::supportsPhotoBeforeAfter($division->id))
                                    <label class="d-flex align-items-center" style="gap:8px; cursor:pointer; margin-top:8px; margin-left:22px;">
                                        <input type="checkbox" name="photo_before_after" value="1" data-photo-ba {{ $task->photo_before_after ? 'checked' : '' }}>
                                        Perlu foto before/after
                                    </label>
                                    <small class="text-muted" style="font-size:11.5px; margin-left:22px; display:block;">Dua slot foto: sebelum & sesudah pengerjaan.</small>
                                @endif
                            </div>
                        @endif
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
        <form method="POST" action="{{ route('checklist.setup.task.destroy', $task->id) }}" id="taskDestroy-{{ $task->id }}" style="display:none;">
            @csrf @method('DELETE')
        </form>
    @endforeach
@endforeach
@endif

<style>
    /* ─── Modal bertema Sweet Treats (scoped .cl-modal, tidak bocor ke modal lain) ─── */
    .cl-modal .modal-dialog { max-width: 460px; }
    .cl-modal .modal-content {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(45,24,16,.28);
    }
    .cl-modal .modal-header {
        background: linear-gradient(135deg, var(--cl-brown-2) 0%, var(--cl-brown) 100%);
        border-bottom: none;
        padding: 17px 22px;
        align-items: center;
    }
    .cl-modal .modal-header .modal-title,
    .cl-modal .modal-header h5.modal-title {
        color: #FFF6EC !important;
        font-size: 15.5px;
        font-weight: 700;
        letter-spacing: .2px;
    }
    .cl-modal .modal-header .close {
        color: #FFF6EC;
        opacity: .8;
        text-shadow: none;
        font-size: 22px;
        font-weight: 400;
        margin: -2px -6px -2px 0;
        transition: opacity .15s;
    }
    .cl-modal .modal-header .close:hover { opacity: 1; }
    .cl-modal .modal-body {
        background: var(--cl-cream-1);
        padding: 20px 22px 6px;
    }
    .cl-modal .modal-body .form-group { margin-bottom: 16px; }
    .cl-modal .modal-body label {
        font-size: 12.5px;
        font-weight: 700;
        color: var(--cl-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    /* Label checkbox: jangan uppercase / bold berlebih biar terbaca natural */
    .cl-modal .modal-body label.d-flex {
        text-transform: none;
        letter-spacing: 0;
        font-weight: 600;
        color: var(--cl-ink);
        font-size: 13px;
    }
    .cl-modal .modal-body .form-control {
        border: 1px solid var(--cl-line);
        border-radius: 10px;
        padding: 10px 13px;
        font-size: 13.5px;
        color: var(--cl-ink);
        background: #FFFFFF;
        box-shadow: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .cl-modal .modal-body .form-control:focus {
        border-color: var(--cl-brown-2);
        box-shadow: 0 0 0 3px rgba(92,46,21,.12);
        outline: none;
    }
    .cl-modal .modal-body .form-control::placeholder { color: #B8A897; }
    .cl-modal .modal-body input[type="checkbox"] {
        width: 16px; height: 16px;
        accent-color: var(--cl-brown-2);
        flex: none;
    }
    .cl-modal .modal-body small.text-muted { color: var(--cl-subtle) !important; }
    .cl-modal .modal-footer {
        background: var(--cl-cream-1);
        border-top: 1px solid var(--cl-cream-3);
        padding: 14px 22px;
    }

    /* Accordion setup kategori — kartu putih terangkat, header putih (bukan cream)
       biar tepi kartu tak menyatu dgn background halaman. */
    .cl-cat { border-color: #E7D8C4; box-shadow: 0 6px 20px rgba(45,24,16,.10); }
    .cl-cat--daily  { border-left: 4px solid #A66019; }
    .cl-cat--weekly { border-left: 4px solid #1F6E5F; }
    .cl-cat__head {
        background: #FFFFFF;
        border-bottom: 1px solid #EADCC9;
    }
    .cl-cat__head-left {
        cursor: pointer;
        border-radius: 10px;
        padding: 4px 8px;
    }
    .cl-cat__caret {
        margin-left: 10px; align-self: center; flex-shrink: 0;
        width: 26px; height: 26px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        background: #FFFFFF; border: 1px solid #E0D2C1;
        color: var(--cl-brown); font-size: 12px;
        transition: transform .2s ease, background-color .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }
    /* Hover di area toggle → tombol dropdown terisi warna brand */
    .cl-cat__head-left:hover .cl-cat__caret {
        background: var(--cl-brown-2);
        border-color: var(--cl-brown-2);
        color: #FFFFFF;
        box-shadow: 0 2px 6px rgba(71,21,0,.22);
    }
    .cl-cat.is-collapsed .cl-cat__caret { transform: rotate(-90deg); }
    .cl-cat.is-collapsed .cl-cat__head { border-bottom: none; }

    /* Buka/tutup halus via grid-template-rows (0fr → 1fr) */
    .cl-cat__body {
        display: grid;
        grid-template-rows: 1fr;
        transition: grid-template-rows .3s ease;
    }
    .cl-cat.is-collapsed .cl-cat__body { grid-template-rows: 0fr; }
    .cl-cat__body-inner { overflow: hidden; min-height: 0; }

    /* Modal konfirmasi hapus (brand, pengganti confirm() browser) */
    .cl-confirm { position: fixed; inset: 0; z-index: 1090; display: flex; align-items: center; justify-content: center; }
    .cl-confirm[hidden] { display: none; }
    .cl-confirm__backdrop { position: absolute; inset: 0; background: rgba(30,15,5,.55); }
    .cl-confirm__box { position: relative; z-index: 1; width: min(92vw, 420px); background: #FAF4EC; border-radius: 16px; overflow: hidden; box-shadow: 0 24px 60px rgba(45,24,16,.28); }
    .cl-confirm__head { background: linear-gradient(135deg, #A32D2D, #7C2929); color: #fff; padding: 15px 20px; font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 9px; }
    .cl-confirm__body { padding: 18px 20px; }
    .cl-confirm__body p { margin: 0; font-size: 13.5px; color: #4A2810; line-height: 1.55; }
    .cl-confirm__foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid #E6D9C8; }
    .cl-confirm__del { border: 0; background: #A32D2D; color: #fff; padding: 9px 18px; border-radius: 999px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 7px; transition: .15s; }
    .cl-confirm__del:hover { background: #8A2525; }
</style>
<script>
(function () {
    var cats = Array.prototype.slice.call(document.querySelectorAll('.cl-cat'));
    // Default: hanya kategori pertama yang terbuka (sisanya collapsed via markup).
    cats.forEach(function (c, i) { c.classList.toggle('is-collapsed', i !== 0); });

    var btn = document.getElementById('clExpandAll');
    function syncExpandAll() {
        if (! btn) return;
        var anyCollapsed = cats.some(function (c) { return c.classList.contains('is-collapsed'); });
        btn.dataset.mode = anyCollapsed ? 'expand' : 'collapse';
        var lbl = btn.querySelector('[data-label]');
        if (lbl) lbl.textContent = anyCollapsed ? 'Buka semua' : 'Tutup semua';
        var ic = btn.querySelector('i');
        if (ic) ic.className = anyCollapsed ? 'fas fa-angle-double-down' : 'fas fa-angle-double-up';
    }

    // Klik header → buka/tutup kategori itu saja (boleh banyak terbuka sekaligus).
    document.querySelectorAll('.cl-cat__head-left[data-cat-toggle]').forEach(function (head) {
        head.addEventListener('click', function () {
            var cat = head.closest('.cl-cat');
            if (cat) cat.classList.toggle('is-collapsed');
            syncExpandAll();
        });
    });

    if (btn) {
        btn.addEventListener('click', function () {
            var expand = btn.dataset.mode === 'expand';
            cats.forEach(function (c) { c.classList.toggle('is-collapsed', ! expand); });
            syncExpandAll();
        });
        syncExpandAll();
    }
})();
</script>

{{-- Modal konfirmasi hapus (brand) — dipakai semua tombol hapus kategori/tugas --}}
<div class="cl-confirm" id="clConfirmModal" hidden>
    <div class="cl-confirm__backdrop" data-cl-confirm-close></div>
    <div class="cl-confirm__box" role="dialog" aria-modal="true" aria-labelledby="clConfirmTitle">
        <div class="cl-confirm__head" id="clConfirmTitle"><i class="fas fa-exclamation-triangle"></i> Konfirmasi Hapus</div>
        <div class="cl-confirm__body"><p id="clConfirmMsg">Yakin hapus item ini?</p></div>
        <div class="cl-confirm__foot">
            <button type="button" class="st-btn-outline-brown" data-cl-confirm-close>Batal</button>
            <button type="button" class="cl-confirm__del" id="clConfirmOk"><i class="fas fa-trash"></i> Hapus</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('clConfirmModal');
    var msgEl = document.getElementById('clConfirmMsg');
    var okBtn = document.getElementById('clConfirmOk');
    var pendingId = null;
    function open(formId, msg) {
        pendingId = formId;
        if (msgEl) msgEl.textContent = msg || 'Yakin hapus item ini?';
        if (modal) modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }
    function close() {
        if (modal) modal.hidden = true;
        pendingId = null;
        document.body.style.overflow = '';
    }
    document.querySelectorAll('[data-cl-del-form]').forEach(function (b) {
        b.addEventListener('click', function () {
            open(b.getAttribute('data-cl-del-form'), b.getAttribute('data-cl-del-msg'));
        });
    });
    if (okBtn) okBtn.addEventListener('click', function () {
        if (pendingId) { var f = document.getElementById(pendingId); if (f) f.requestSubmit(); }
        close();
    });
    document.querySelectorAll('[data-cl-confirm-close]').forEach(function (el) { el.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>

<script>
// Konsistensi checkbox foto: "before/after" mengimplikasikan "perlu foto".
// Mematikan "perlu foto" otomatis mematikan "before/after".
(function () {
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || t.type !== 'checkbox') return;
        var form = t.closest('form');
        if (!form) return;
        var parent = form.querySelector('[data-photo-parent]');
        var ba = form.querySelector('[data-photo-ba]');
        if (!parent || !ba) return;
        if (t === ba && ba.checked) parent.checked = true;
        if (t === parent && !parent.checked) ba.checked = false;
    });
})();
</script>

<script>
// Jadwal tugas: tampilkan/sembunyikan chip "Minggu 1..5" mengikuti pilihan
// "Sama tiap minggu" vs "Ganti tiap minggu".
(function () {
    document.addEventListener('change', function (e) {
        var t = e.target;
        if (!t || !t.classList || !t.classList.contains('cl-wk-mode')) return;
        var wrap = t.closest('.cl-wk');
        if (!wrap) return;
        var weeks = wrap.querySelector('.cl-wk__weeks');
        if (!weeks) return;
        var specific = t.value === 'specific' && t.checked;
        weeks.classList.toggle('is-hidden', !specific);
        weeks.querySelectorAll('input[type=checkbox]').forEach(function (c) {
            c.disabled = !specific;
            if (!specific) c.checked = false;
        });
    });
})();
</script>
@endsection
