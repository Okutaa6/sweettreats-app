@php
    $campaign     = $campaign     ?? null;
    $statusColors = \App\Models\EpKonten::statusColors();

    $oldBulan = old('bulan', $campaign->bulan ?? request('bulan', now()->month));
    $oldTahun = old('tahun', $campaign->tahun ?? request('tahun', now()->year));
    $oldStatus = old('status', $campaign->status ?? 'planning');

    // Existing weeks (kalau edit) — convert ke array index
    $existingWeeks = [];
    if ($campaign && $campaign->weeks) {
        foreach ($campaign->weeks as $w) {
            $existingWeeks[$w->minggu_ke - 1] = ['fokus' => $w->fokus, 'deliverables' => $w->deliverables];
        }
    }
    // Pastikan minimal 4 minggu (1-4) selalu ada untuk render. Minggu ke-5 muncul dynamically via "Tambah Minggu".
    $weekCount = max(4, count($existingWeeks));
@endphp

<div class="note-page">
    {{-- Title --}}
    <div class="note-title-wrap">
        <input type="text" name="campaign_name"
               class="note-title-input {{ $errors->has('campaign_name') ? 'is-invalid' : '' }}"
               value="{{ old('campaign_name', $campaign->campaign_name ?? '') }}"
               placeholder="Nama campaign..." required>
        @if($errors->has('campaign_name'))
            <div class="text-danger small mt-1">{{ $errors->first('campaign_name') }}</div>
        @endif
    </div>

    {{-- Properties --}}
    <div class="note-props">
        <div class="note-prop">
            <div class="note-prop-key"><i class="fa-solid fa-calendar"></i><span>Bulan</span></div>
            <div class="note-prop-val">
                <select name="bulan" class="note-prop-input note-prop-select" required>
                    @foreach ($bulanOpt as $num => $label)
                        <option value="{{ $num }}" {{ $oldBulan == $num ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="note-prop">
            <div class="note-prop-key"><i class="fa-solid fa-calendar-alt"></i><span>Tahun</span></div>
            <div class="note-prop-val">
                <input type="number" name="tahun" class="note-prop-input"
                       value="{{ $oldTahun }}" min="2020" max="2100" required>
            </div>
        </div>

        <div class="note-prop">
            <div class="note-prop-key"><i class="fa-solid fa-adjust"></i><span>Status</span></div>
            <div class="note-prop-val">
                <div class="ep-quick-pills">
                    @foreach ($statuses as $key => $opt)
                        @php $sc = $statusColors[$key] ?? null; @endphp
                        <label class="ep-quick-pill ep-quick-pill-radio" style="--pill-bg:{{ $sc['bg'] ?? '#f3f4f6' }}; --pill-text:{{ $sc['text'] ?? '#4b5563' }};">
                            <input type="radio" name="status" value="{{ $key }}" {{ $oldStatus === $key ? 'checked' : '' }} required>
                            <span><span class="status-dot" style="background:{{ $sc['dot'] ?? '#9ca3af' }};"></span>{{ $opt['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="note-divider"></div>

    {{-- Big Idea + Target --}}
    <div class="note-body">
        <div class="note-body-field">
            <div class="note-body-label"><i class="fa-solid fa-lightbulb"></i><span>Big Idea</span></div>
            <textarea name="big_idea" class="note-body-input" rows="3"
                      placeholder="Konsep besar / tagline campaign...">{{ old('big_idea', $campaign->big_idea ?? '') }}</textarea>
        </div>
        <div class="note-body-field">
            <div class="note-body-label"><i class="fa-solid fa-bullseye"></i><span>Target / KPI</span></div>
            <textarea name="target" class="note-body-input" rows="3"
                      placeholder="Target yang mau dicapai (revenue, engagement, awareness, dll)...">{{ old('target', $campaign->target ?? '') }}</textarea>
        </div>
    </div>

    <div class="note-divider"></div>

    {{-- Weekly Breakdown --}}
    <div class="cmp-week-section">
        <div class="cmp-week-header">
            <div>
                <div class="cmp-week-title"><i class="fa-solid fa-calendar-week"></i> Breakdown Mingguan</div>
                <div class="cmp-week-sub">Detail apa yang harus difokuskan & dideliver tiap minggu di campaign ini</div>
            </div>
            <button type="button" class="cmp-week-add-btn" onclick="addWeek()">
                <i class="fa fa-plus"></i> Tambah Minggu
            </button>
        </div>

        <div id="cmpWeeksContainer">
            @for ($i = 0; $i < $weekCount; $i++)
                @include('crud.ep-campaign._week_card', [
                    'idx'  => $i,
                    'week' => $existingWeeks[$i] ?? ['fokus' => '', 'deliverables' => '']
                ])
            @endfor
        </div>
    </div>
</div>


<script>
    let weekCount = {{ $weekCount }};

    function addWeek() {
        if (weekCount >= 5) {
            Swal.fire({ title: 'Limit', text: 'Maksimal 5 minggu per campaign.', type: 'info', timer: 1500, showConfirmButton: false });
            return;
        }
        const idx = weekCount;
        weekCount++;
        const html = renderWeekCard(idx);
        document.getElementById('cmpWeeksContainer').insertAdjacentHTML('beforeend', html);
    }

    function removeWeek(btn) {
        const card = btn.closest('.cmp-week-card');
        if (!card) return;
        // Reduce count only if removing last
        const idx = parseInt(card.dataset.idx);
        card.remove();
        // Re-number remaining cards
        renumberWeeks();
    }

    function renumberWeeks() {
        const cards = document.querySelectorAll('#cmpWeeksContainer .cmp-week-card');
        cards.forEach((card, i) => {
            card.dataset.idx = i;
            card.querySelector('.cmp-week-num-pill').textContent = i + 1;
            card.querySelector('.cmp-week-num-text').textContent = 'Minggu ' + (i + 1);
            card.querySelectorAll('input, textarea').forEach(el => {
                const name = el.name;
                if (name) {
                    el.name = name.replace(/weeks\[\d+\]/, `weeks[${i}]`);
                }
            });
        });
        weekCount = cards.length;
    }

    function renderWeekCard(idx) {
        const num = idx + 1;
        return `
        <div class="cmp-week-card" data-idx="${idx}">
            <div class="cmp-week-card-head">
                <div class="cmp-week-card-num">
                    <span class="cmp-week-num-pill">${num}</span>
                    <span class="cmp-week-num-text">Minggu ${num}</span>
                </div>
                <button type="button" class="cmp-week-remove-btn" onclick="removeWeek(this)" title="Hapus minggu ini">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="cmp-week-row">
                <label class="cmp-week-label">Fokus minggu ini</label>
                <input type="text" name="weeks[${idx}][fokus]" class="cmp-week-input"
                       placeholder="Mis. Teaser & build hype">
            </div>
            <div class="cmp-week-row">
                <label class="cmp-week-label">Deliverables</label>
                <textarea name="weeks[${idx}][deliverables]" rows="2" class="cmp-week-input"
                          placeholder="Apa saja yang harus dideliver minggu ini..."></textarea>
            </div>
        </div>`;
    }
</script>
