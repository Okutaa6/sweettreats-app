{{--
    KOL Existing — Monthly accordion view.

    Variables: $kolsByMonth (Collection grouped by 'Y-m'), $monthlyTotal (int),
               $monthlyCapped (bool — apakah hasil dibatasi 500).

    Default-collapsed. Klik header → expand. State disimpan di localStorage
    biar konsisten antar reload.
--}}
@php
    $bulanLabel = [
        '01' => 'Jan',  '02' => 'Feb',  '03' => 'Mar',
        '04' => 'Apr',  '05' => 'May',  '06' => 'Jun',
        '07' => 'Jul',  '08' => 'Aug',  '09' => 'Sep',
        '10' => 'Oct',  '11' => 'Nov',  '12' => 'Des',
    ];
    $progressOpts = \App\Models\RawAffiliate::kolProgressOptions();
    $relasiOpts   = \App\Models\RawAffiliate::kolStatusRelasiOptions();

    // Re-group by year supaya ada visual separator antar tahun.
    // Hasil: ['2026' => ['2026-04' => Collection<>, '2026-03' => ...], '2025' => [...]]
    $kolsByYear = [];
    foreach ($kolsByMonth as $monthKey => $items) {
        $year = $monthKey ? substr($monthKey, 0, 4) : '—';
        $kolsByYear[$year][$monthKey] = $items;
    }

    // Bulan paling atas (di tahun teratas) → default ter-open saat page load.
    $firstMonthKey = null;
    foreach ($kolsByYear as $year => $months) {
        $firstMonthKey = array_key_first($months);
        break;
    }
@endphp

<div class="st-card mt-3 kol-monthly-wrap">
    <button type="button" class="kol-monthly-toggle" id="kolMonthlyToggle"
            aria-expanded="false" aria-controls="kolMonthlyBody">
        <span class="kol-monthly-toggle__left">
            <i class="fa fa-chevron-right kol-monthly-toggle__chevron"></i>
            <i class="fa fa-th-large"></i>
            <span class="kol-monthly-toggle__title">{{ $monthlyTitle ?? 'Tampilan Bulanan (Kartu)' }}</span>
        </span>
        <span class="kol-monthly-toggle__right">
            <span class="kol-monthly-toggle__count">{{ $monthlyTotal }} KOL</span>
            @if($monthlyCapped)
                <span class="kol-monthly-toggle__hint" title="Dibatasi 500 entri terbaru — persempit filter untuk lihat selebihnya.">
                    <i class="fa fa-info-circle"></i>
                </span>
            @endif
        </span>
    </button>

    <div class="kol-monthly-body" id="kolMonthlyBody" hidden>
        @if($kolsByMonth->isEmpty())
            <div class="kol-monthly-empty">
                <i class="fa fa-inbox"></i>
                <span>Tidak ada KOL untuk filter saat ini.</span>
            </div>
        @else
            @php $defaultYear = array_key_first($kolsByYear); @endphp
            @if($showYearFilter ?? true)
            <div class="kol-monthly-yearfilter">
                <span class="kol-monthly-yearfilter__label">
                    <i class="fa fa-calendar"></i> Pilih Tahun
                </span>
                <div class="kol-monthly-yearfilter__pills" role="tablist">
                    @foreach($kolsByYear as $year => $months)
                        @php $yt = collect($months)->sum(fn ($c) => count($c)); @endphp
                        <button type="button"
                                class="kol-monthly-yearpill {{ $year === $defaultYear ? 'is-active' : '' }}"
                                data-year-pill="{{ $year }}"
                                role="tab"
                                aria-selected="{{ $year === $defaultYear ? 'true' : 'false' }}">
                            <span class="kol-monthly-yearpill__year">{{ $year }}</span>
                            <span class="kol-monthly-yearpill__count">{{ $yt }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            @foreach($kolsByYear as $year => $months)
                <div class="kol-monthly-year" data-year="{{ $year }}" {{ $year === $defaultYear ? '' : 'hidden' }}>

                    @foreach($months as $monthKey => $items)
                        @php
                            [$yyyy, $mm] = $monthKey ? explode('-', $monthKey) : ['—', '—'];
                            $monthHeader = ($bulanLabel[$mm] ?? '—') . ' ' . $yyyy;
                            $isDefaultOpen = ($monthKey === $firstMonthKey);
                        @endphp
                <div class="kol-monthly-section" data-month="{{ $monthKey }}">
                    <button type="button" class="kol-monthly-section__header {{ $isDefaultOpen ? 'is-open' : '' }}"
                            data-month-toggle="{{ $monthKey }}" aria-expanded="{{ $isDefaultOpen ? 'true' : 'false' }}">
                        <i class="fa fa-caret-down kol-monthly-section__chevron"></i>
                        <span>{{ $monthHeader }}</span>
                        <span class="kol-monthly-section__count">{{ count($items) }}</span>
                    </button>
                    <div class="kol-monthly-grid" data-month-body="{{ $monthKey }}" {{ $isDefaultOpen ? '' : 'hidden' }}>
                        @foreach($items as $kol)
                            @php
                                $handle = $kol->social_instagram
                                    ?: $kol->social_tiktok
                                    ?: $kol->social_username
                                    ?: $kol->name;

                                $platformLabel = 'IG';
                                if (!$kol->social_instagram) {
                                    if ($kol->social_tiktok)        $platformLabel = 'TikTok';
                                    elseif ($kol->social_youtube)   $platformLabel = 'YT';
                                    elseif ($kol->social_platform)  $platformLabel = ucfirst($kol->social_platform);
                                }

                                $foll = $kol->followers_count
                                    ? (
                                        $kol->followers_count >= 1_000_000
                                            ? number_format($kol->followers_count / 1_000_000, 1, '.', '') . 'M'
                                            : ($kol->followers_count >= 1_000
                                                ? round($kol->followers_count / 1_000) . 'K'
                                                : (string) $kol->followers_count)
                                    )
                                    : null;

                                $note = $kol->rencana_perawatan
                                    ?: $kol->feedback
                                    ?: ($kol->kol_kategori
                                        ? (\App\Models\RawAffiliate::kolKategoriOptions()[$kol->kol_kategori] ?? null)
                                        : null);

                                $progressCfg = $kol->kol_progress ? ($progressOpts[$kol->kol_progress] ?? null) : null;
                                $relasiCfg   = $kol->kol_status_relasi ? ($relasiOpts[$kol->kol_status_relasi] ?? null) : null;

                                $branchSlug = $kol->branch
                                    ? strtolower(preg_replace('/[^a-z0-9]/i', '', $kol->branch->name))
                                    : null;
                            @endphp
                            <a href="{{ route('rawaffiliate.edit', ['id' => $kol->id, 'return' => request()->fullUrl()]) }}"
                               class="kol-mc">
                                <div class="kol-mc__head">
                                    <span class="kol-mc__handle">{{ '@' . $handle }}</span>
                                </div>
                                @if($foll)
                                    <div class="kol-mc__meta">{{ $foll }} followers {{ $platformLabel }}</div>
                                @elseif($platformLabel)
                                    <div class="kol-mc__meta">{{ $platformLabel }}</div>
                                @endif

                                @if($note)
                                    <div class="kol-mc__title">{{ $note }}</div>
                                @endif

                                <div class="kol-mc__tags">
                                    @if($kol->branch)
                                        <span class="kol-mc__tag kol-mc__tag--branch st-pill--branch-{{ $branchSlug }}">{{ $kol->branch->name }}</span>
                                    @endif
                                    @if($relasiCfg)
                                        <span class="kol-mc__tag kol-mc__tag--relasi kol-mc__tag--{{ $relasiCfg['color'] }}">{{ $relasiCfg['label'] }}</span>
                                    @endif
                                    @if($progressCfg)
                                        <span class="kol-mc__tag kol-mc__tag--progress kol-mc__tag--{{ $progressCfg['color'] }}">{{ $progressCfg['label'] }}</span>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
                    @endforeach
                </div>{{-- /.kol-monthly-year --}}
            @endforeach
        @endif
    </div>
</div>

<script>
(function () {
    // ─── Outer accordion: seluruh "Tampilan Bulanan" ───────────────
    const OUTER_KEY = 'kolMonthlyOpen';
    const toggle = document.getElementById('kolMonthlyToggle');
    const body   = document.getElementById('kolMonthlyBody');
    if (!toggle || !body) return;

    function setOuterOpen(open) {
        toggle.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) body.removeAttribute('hidden');
        else      body.setAttribute('hidden', '');
    }
    try { if (localStorage.getItem(OUTER_KEY) === '1') setOuterOpen(true); } catch (e) {}

    toggle.addEventListener('click', function () {
        const open = !toggle.classList.contains('is-open');
        setOuterOpen(open);
        try { localStorage.setItem(OUTER_KEY, open ? '1' : '0'); } catch (e) {}
    });

    // ─── Inner accordion: per bulan (single-active) ────────────────
    // Hanya satu bulan yang boleh open. Default open: bulan paling atas
    // di tahun yang sedang ditampilkan. Klik bulan lain → bulan sebelumnya
    // otomatis tutup. Klik bulan yang sedang open → tutup.
    function setMonthOpen(header, open) {
        const monthKey = header.dataset.monthToggle;
        const grid = body.querySelector('[data-month-body="' + monthKey + '"]');
        header.classList.toggle('is-open', open);
        header.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!grid) return;
        if (open) grid.removeAttribute('hidden');
        else      grid.setAttribute('hidden', '');
    }

    function closeAllMonths() {
        body.querySelectorAll('[data-month-toggle].is-open').forEach(function (h) {
            setMonthOpen(h, false);
        });
    }

    function openTopMonthOfYear(yearEl) {
        if (!yearEl) return;
        const first = yearEl.querySelector('[data-month-toggle]');
        if (first) setMonthOpen(first, true);
    }

    body.addEventListener('click', function (e) {
        const header = e.target.closest('[data-month-toggle]');
        if (!header) return;
        const wasOpen = header.classList.contains('is-open');

        // Tutup semua bulan dulu, lalu toggle yang di-klik
        body.querySelectorAll('[data-month-toggle].is-open').forEach(function (h) {
            if (h !== header) setMonthOpen(h, false);
        });
        setMonthOpen(header, !wasOpen);
    });

    // ─── Year filter pills ─────────────────────────────────────────
    const YEAR_KEY = 'kolMonthlyYear';
    const pills = body.querySelectorAll('[data-year-pill]');

    function setActivePill(year) {
        pills.forEach(function (p) {
            const active = p.dataset.yearPill === year;
            p.classList.toggle('is-active', active);
            p.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function showYear(year) {
        if (!year) return;
        let target = null;
        body.querySelectorAll('[data-year]').forEach(function (el) {
            const match = el.dataset.year === year;
            if (match) { el.removeAttribute('hidden'); target = el; }
            else       { el.setAttribute('hidden', ''); }
        });
        setActivePill(year);
        closeAllMonths();
        openTopMonthOfYear(target);
    }

    if (pills.length) {
        // Restore saved year kalau masih ada di antara pills
        try {
            const saved = localStorage.getItem(YEAR_KEY);
            const exists = saved && [...pills].some(function (p) { return p.dataset.yearPill === saved; });
            if (exists) showYear(saved);
        } catch (e) {}

        pills.forEach(function (pill) {
            pill.addEventListener('click', function () {
                const y = pill.dataset.yearPill;
                showYear(y);
                try { localStorage.setItem(YEAR_KEY, y); } catch (e) {}
            });
        });
    }
})();
</script>
