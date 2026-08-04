{{--
    Notion-style creative form untuk EP Konten.
    Variables: $konten (?EpKonten), $pillars, $platforms, $tipes, $statuses, $prios,
               $breakdowns, $campaigns, $selectedPillarIds (array), $tipeColors, $platformColors
--}}
@php
    $konten            = $konten            ?? null;
    $selectedPillarIds = $selectedPillarIds ?? [];
    $tipeColors        = $tipeColors        ?? \App\Models\EpKonten::tipeKontenColors();
    $platformColors    = $platformColors    ?? \App\Models\EpKonten::platformColors();
    $statusColors      = $statusColors      ?? \App\Models\EpKonten::statusColors();
    $prioColors        = [
        'low'    => ['bg' => '#f3f4f6', 'text' => '#4b5563', 'dot' => '#9ca3af'],
        'normal' => ['bg' => '#dbeafe', 'text' => '#1e40af', 'dot' => '#3b82f6'],
        'high'   => ['bg' => '#fef3c7', 'text' => '#92400e', 'dot' => '#f59e0b'],
        'urgent' => ['bg' => '#fee2e2', 'text' => '#991b1b', 'dot' => '#ef4444'],
    ];

    $oldPlatforms = old('platforms', $konten?->platforms ?? []);
    $oldStatus    = old('status', $konten->status ?? 'draft');
    $oldPrio      = old('prioritas', $konten->prioritas ?? 'normal');
    $oldTipe      = old('tipe_konten', $konten->tipe_konten ?? '');
    // Breakdown mode: aktif kalau (a) konten sudah is_breakdown=1, atau (b) caller paksa via $showBreakdownFields=true
    $isBreakdown  = ($konten && $konten->is_breakdown) || ($showBreakdownFields ?? false);
@endphp

<div class="note-page">
    {{-- ─────────── BIG TITLE ─────────── --}}
    <div class="note-title-wrap">
        <textarea name="judul" rows="1" id="noteTitleInput"
                  class="note-title-input {{ $errors->has('judul') ? 'is-invalid' : '' }}"
                  placeholder="Judul konten..." required>{{ old('judul', $konten->judul ?? '') }}</textarea>
        @if($errors->has('judul'))
            <div class="text-danger small mt-1">{{ $errors->first('judul') }}</div>
        @endif
    </div>

    {{-- ─────────── TAB NAVIGATION ─────────── --}}
    <div class="note-tabs" role="tablist">
        <button type="button" class="note-tab is-active" data-tab="detail" role="tab" aria-selected="true">
            <i class="fa-solid fa-file-alt"></i>
            <span>Detail Konten</span>
        </button>
        <button type="button" class="note-tab" data-tab="foto" role="tab" aria-selected="false">
            <i class="fa-solid fa-images"></i>
            <span>Foto / Gambar</span>
            @if(isset($konten) && $konten && $konten->images && $konten->images->count() > 0)
                <span class="note-tab-badge">{{ $konten->images->count() }}</span>
            @endif
        </button>
    </div>

    {{-- ─────────── TAB PANE: DETAIL ─────────── --}}
    <div class="note-tab-pane is-active" data-pane="detail" role="tabpanel">

    {{-- ─────────── PROPERTY ROWS ─────────── --}}
    <div class="note-props">
        {{-- Konten Pillar --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-tags"></i>
                <span>Konten Pillar</span>
            </div>
            <div class="note-prop-val">
                <div class="ep-pillar-trigger" onclick="togglePillarPanel(this)">
                    <div class="ep-pillar-selected" id="pillarSelectedDisplay">
                        @if(empty($selectedPillarIds))
                            <span class="ep-pillar-empty">Kosong</span>
                        @else
                            @foreach($pillars->whereIn('id', $selectedPillarIds) as $p)
                                @php $pColor = $p->color ?: '#13928e'; @endphp
                                <span class="ep-pillar-chip" style="background:{{ $pColor }};">
                                    {{ $p->nama }}
                                    <span class="ep-pillar-chip-x" onclick="removePillar(event, '{{ $p->id }}')"><i class="fa fa-times"></i></span>
                                </span>
                            @endforeach
                        @endif
                    </div>
                    <button type="button" class="ep-pillar-toggle-btn">
                        <i class="fa fa-chevron-down"></i>
                    </button>
                </div>
                <div class="ep-pillar-panel" id="pillarPanel" style="display:none;">
                    <div class="ep-pillar-search-wrap">
                        <i class="fa fa-search ep-search-icon"></i>
                        <input type="text" class="ep-pillar-search" id="pillarSearch"
                               placeholder="Cari pillar..." onkeyup="filterPillars(this.value)">
                    </div>
                    <div class="ep-quick-pills ep-pillar-list" id="quickAddPillars">
                        @foreach ($pillars as $p)
                            @php $pColor = $p->color ?: '#13928e'; @endphp
                            <label class="ep-quick-pill" style="--pill-bg:{{ $pColor }}1a; --pill-text:{{ $pColor }};"
                                   data-pillar-name="{{ strtolower($p->nama) }}">
                                <input type="checkbox" name="pillar_ids[]" value="{{ $p->id }}"
                                       data-name="{{ $p->nama }}" data-color="{{ $pColor }}"
                                       {{ in_array($p->id, old('pillar_ids', $selectedPillarIds)) ? 'checked' : '' }}
                                       onchange="updatePillarChips()">
                                <span>{{ $p->nama }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Platform --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-share-alt"></i>
                <span>Platform</span>
            </div>
            <div class="note-prop-val">
                <div class="ep-quick-pills">
                    @foreach ($platforms as $key => $label)
                        @php $pc = $platformColors[$key] ?? null; @endphp
                        <label class="ep-quick-pill" style="--pill-bg:{{ $pc['bg'] ?? '#e6f5f4' }}; --pill-text:{{ $pc['text'] ?? '#0a4f4d' }};">
                            <input type="checkbox" name="platforms[]" value="{{ $key }}"
                                   {{ in_array($key, $oldPlatforms) ? 'checked' : '' }}>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Tipe Konten --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-shapes"></i>
                <span>Tipe Konten</span>
            </div>
            <div class="note-prop-val">
                <div class="ep-quick-pills">
                    @foreach ($tipes as $key => $label)
                        @php $tc = $tipeColors[$key]; @endphp
                        <label class="ep-quick-pill ep-quick-pill-radio" style="--pill-bg:{{ $tc['bg'] }}; --pill-text:{{ $tc['text'] }};">
                            <input type="radio" name="tipe_konten" value="{{ $key }}" {{ $oldTipe === $key ? 'checked' : '' }}>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-adjust"></i>
                <span>Status</span>
            </div>
            <div class="note-prop-val">
                <div class="ep-quick-pills">
                    @foreach ($statuses as $key => $opt)
                        @php $sc = $statusColors[$key] ?? null; @endphp
                        <label class="ep-quick-pill ep-quick-pill-radio" style="--pill-bg:{{ $sc['bg'] ?? '#f3f4f6' }}; --pill-text:{{ $sc['text'] ?? '#4b5563' }};">
                            <input type="radio" name="status" value="{{ $key }}" {{ $oldStatus === $key ? 'checked' : '' }} required>
                            <span>
                                <span class="status-dot" style="background:{{ $sc['dot'] ?? '#9ca3af' }};"></span>
                                {{ $opt['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Prioritas --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-flag"></i>
                <span>Prioritas</span>
            </div>
            <div class="note-prop-val">
                <div class="ep-quick-pills">
                    @foreach ($prios as $key => $opt)
                        @php $pc = $prioColors[$key]; @endphp
                        <label class="ep-quick-pill ep-quick-pill-radio" style="--pill-bg:{{ $pc['bg'] }}; --pill-text:{{ $pc['text'] }};">
                            <input type="radio" name="prioritas" value="{{ $key }}" {{ $oldPrio === $key ? 'checked' : '' }} required>
                            <span>{{ $opt['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Tanggal Upload --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-calendar-day"></i>
                <span>Tanggal Upload</span>
            </div>
            <div class="note-prop-val">
                <input type="date" name="tanggal_upload" class="note-prop-input"
                       value="{{ old('tanggal_upload', $konten?->tanggal_upload?->format('Y-m-d')) }}">
            </div>
        </div>

        {{-- Estimasi Pengerjaan --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-clock"></i>
                <span>Estimasi</span>
            </div>
            <div class="note-prop-val">
                <input type="text" name="waktu_pengerjaan" class="note-prop-input"
                       value="{{ old('waktu_pengerjaan', $konten->waktu_pengerjaan ?? '') }}"
                       placeholder="Mis. 2 hari, 4 jam">
            </div>
        </div>

        {{-- Framework --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-project-diagram"></i>
                <span>Framework</span>
            </div>
            <div class="note-prop-val">
                <input type="text" name="framework" class="note-prop-input"
                       value="{{ old('framework', $konten->framework ?? '') }}"
                       placeholder="AIDA, PAS, Storytelling, dll">
            </div>
        </div>

        {{-- ─── Breakdown-only fields: Lokasi + ACC By ─── --}}
        @if($isBreakdown)
            <div class="note-prop">
                <div class="note-prop-key">
                    <i class="fa-solid fa-map-marker-alt"></i>
                    <span>Lokasi</span>
                </div>
                <div class="note-prop-val">
                    <input type="text" name="lokasi" class="note-prop-input"
                           value="{{ old('lokasi', $konten->lokasi ?? '') }}"
                           placeholder="Mis. Bintaro, Studio, Outdoor">
                </div>
            </div>

            <div class="note-prop">
                <div class="note-prop-key">
                    <i class="fa-solid fa-user-check"></i>
                    <span>ACC By</span>
                </div>
                <div class="note-prop-val">
                    <input type="text" name="acc_by" class="note-prop-input"
                           value="{{ old('acc_by', $konten->acc_by ?? '') }}"
                           placeholder="Nama PJ yang ACC">
                </div>
            </div>
        @endif

        {{-- Tautan ke Campaign --}}
        <div class="note-prop">
            <div class="note-prop-key">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Campaign</span>
            </div>
            <div class="note-prop-val">
                <select name="ep_campaign_framework_id" class="note-prop-input note-prop-select">
                    <option value="">— Tidak terkait —</option>
                    @foreach ($campaigns as $c)
                        @php $bln = \App\Models\EpCampaignFramework::bulanOptions()[$c->bulan] ?? $c->bulan; @endphp
                        <option value="{{ $c->id }}" {{ old('ep_campaign_framework_id', $konten->ep_campaign_framework_id ?? '') == $c->id ? 'selected' : '' }}>{{ $c->campaign_name }} — {{ $bln }} {{ $c->tahun }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- ─────────── BODY SECTIONS ─────────── --}}
    <div class="note-body">
        @php
            $bodyFields = [
                ['name' => 'deskripsi', 'label' => 'Deskripsi Singkat', 'icon' => 'fa-align-left',     'rows' => 2, 'placeholder' => 'Brief singkat tentang konten ini...'],
                ['name' => 'script',    'label' => 'Script',            'icon' => 'fa-pen-fancy',     'rows' => 5, 'placeholder' => 'Naskah / dialog konten...'],
                ['name' => 'caption',   'label' => 'Caption',           'icon' => 'fa-quote-right',   'rows' => 3, 'placeholder' => 'Caption untuk posting...'],
                ['name' => 'visual_plan','label'=> 'Visual Plan',       'icon' => 'fa-palette',       'rows' => 4, 'placeholder' => 'Storyboard / shot list / referensi visual...'],
                ['name' => 'referensi', 'label' => 'Referensi',         'icon' => 'fa-bookmark',      'rows' => 3, 'placeholder' => 'Link IG/TikTok inspirasi, link drive, dll...'],
                ['name' => 'note',      'label' => 'Note',              'icon' => 'fa-sticky-note',   'rows' => 2, 'placeholder' => 'Catatan internal, deadline khusus, dll...'],
            ];
        @endphp

        @foreach ($bodyFields as $f)
            <div class="note-body-field">
                <div class="note-body-label">
                    <i class="fa-solid {{ $f['icon'] }}"></i>
                    <span>{{ $f['label'] }}</span>
                </div>
                <textarea name="{{ $f['name'] }}" class="note-body-input"
                          rows="{{ $f['rows'] }}"
                          placeholder="{{ $f['placeholder'] }}">{{ old($f['name'], $konten->{$f['name']} ?? '') }}</textarea>
            </div>
        @endforeach
    </div>

    </div>{{-- /tab-pane: detail --}}

    {{-- ─────────── TAB PANE: FOTO / GAMBAR ─────────── --}}
    <div class="note-tab-pane" data-pane="foto" role="tabpanel" hidden>
        @include('crud.ep-konten._image_section', ['konten' => $konten])
    </div>{{-- /tab-pane: foto --}}
</div>


<script>
    // ─── Pillar collapsible (sama dengan modal calendar) ───────────────
    function togglePillarPanel(triggerEl) {
        const panel   = document.getElementById('pillarPanel');
        const trigger = triggerEl || document.querySelector('.ep-pillar-trigger');
        const isOpen  = panel.style.display === 'block';
        panel.style.display = isOpen ? 'none' : 'block';
        trigger.classList.toggle('is-open', !isOpen);
        if (!isOpen) {
            const search = document.getElementById('pillarSearch');
            if (search) search.focus();
        }
    }

    function updatePillarChips() {
        const display = document.getElementById('pillarSelectedDisplay');
        const checked = document.querySelectorAll('#quickAddPillars input[type="checkbox"]:checked');

        if (checked.length === 0) {
            display.innerHTML = '<span class="ep-pillar-empty">Kosong</span>';
            return;
        }
        let html = '';
        checked.forEach(inp => {
            const name  = inp.dataset.name;
            const color = inp.dataset.color || '#13928e';
            const id    = inp.value;
            html += `<span class="ep-pillar-chip" style="background:${color};">
                ${name}
                <span class="ep-pillar-chip-x" onclick="removePillar(event, '${id}')"><i class="fa fa-times"></i></span>
            </span>`;
        });
        display.innerHTML = html;
    }

    function removePillar(e, id) {
        e.stopPropagation();
        const inp = document.querySelector(`#quickAddPillars input[value="${id}"]`);
        if (inp) {
            inp.checked = false;
            updatePillarChips();
        }
    }

    function filterPillars(term) {
        const t = term.trim().toLowerCase();
        document.querySelectorAll('#quickAddPillars label[data-pillar-name]').forEach(label => {
            const name = label.dataset.pillarName;
            label.dataset.hidden = (t && !name.includes(t)) ? 'true' : 'false';
        });
    }

    // Click outside to close pillar panel
    document.addEventListener('click', function (e) {
        const panel = document.getElementById('pillarPanel');
        const trigger = document.querySelector('.ep-pillar-trigger');
        if (!panel || !trigger) return;
        if (!panel.contains(e.target) && !trigger.contains(e.target)) {
            panel.style.display = 'none';
            trigger.classList.remove('is-open');
        }
    });

    // ─── Tab switching (Detail Konten / Foto) ───────────────
    (function () {
        const tabs  = document.querySelectorAll('.note-tabs .note-tab');
        const panes = document.querySelectorAll('.note-tab-pane');
        if (!tabs.length) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = tab.dataset.tab;
                tabs.forEach(function (t) {
                    const active = t.dataset.tab === target;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panes.forEach(function (p) {
                    const active = p.dataset.pane === target;
                    p.classList.toggle('is-active', active);
                    if (active) { p.removeAttribute('hidden'); }
                    else        { p.setAttribute('hidden', ''); }
                });
            });
        });
    })();

    // ─── Judul: textarea auto-grow, tanpa newline ───────────
    (function () {
        const title = document.getElementById('noteTitleInput');
        if (!title) return;

        function resize() {
            title.style.height = 'auto';
            title.style.height = title.scrollHeight + 'px';
        }

        title.addEventListener('input', function () {
            if (title.value.indexOf('\n') !== -1) {
                title.value = title.value.replace(/\n/g, ' ');
            }
            resize();
        });

        title.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (title.form) title.form.requestSubmit();
            }
        });

        window.addEventListener('resize', resize);
        resize();
    })();
</script>
