@php
    use Carbon\Carbon;

    $selectedDoctor = $filterDoctor ? ($doctors->firstWhere('id', $filterDoctor)) : null;
    $selectedBranch = $filterBranch ? ($branches->firstWhere('id', $filterBranch)) : null;

    $fmtTanggal = function ($d) {
        if (!$d) return '-';
        return Carbon::parse($d)->translatedFormat('d M Y');
    };
    $hariList = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $fmtJam = function ($jam) {
        if (!$jam) return '-';
        return substr((string) $jam, 0, 5);
    };

    // Mapping FU value → label rapi.
    $fuLabel = [
        'confirm'    => 'Confirm',
        'reschedule' => 'Reschedule',
        'cancel'     => 'Cancel',
        'no_respon'  => 'No Respon',
    ];
    $kehadiranLabel = [
        'hadir'       => 'Hadir',
        'tidak_hadir' => 'Tidak Hadir',
        'reschedule'  => 'Reschedule',
        'cancel'      => 'Cancel',
    ];
    $statusOptions = \App\Models\RawCusReservation::statusOptions();
@endphp

<div id="viewJadwal">
    @if($jadwalEmptyState)
        {{-- ── Empty state: panduan visual cara pakai tab ini ── --}}
        <div class="jd-empty">
            <div class="jd-empty__hero">
                <div class="jd-empty__icon"><i class="fa fa-user-md"></i></div>
                <h3 class="jd-empty__title">Jadwal Dokter</h3>
                <p class="jd-empty__lead">
                    Kirim jadwal reservasi ke dokter via WhatsApp / email dalam bentuk gambar yang jelas.
                    Ikuti 3 langkah berikut:
                </p>
            </div>

            <div class="jd-steps">
                <div class="jd-step">
                    <div class="jd-step__num">1</div>
                    <div class="jd-step__body">
                        <div class="jd-step__title"><i class="fa fa-filter"></i> Buka Filter</div>
                        <div class="jd-step__desc">Klik tombol di bawah ini, lalu pilih satu dokter di kolom <strong>Dokter</strong>.</div>
                    </div>
                </div>
                <div class="jd-step">
                    <div class="jd-step__num">2</div>
                    <div class="jd-step__body">
                        <div class="jd-step__title"><i class="fa fa-calendar"></i> Atur Rentang Tanggal</div>
                        <div class="jd-step__desc">Default 30 hari ke depan. Bisa diubah di kolom <strong>Tanggal Mulai</strong> &amp; <strong>Tanggal Akhir</strong>.</div>
                    </div>
                </div>
                <div class="jd-step">
                    <div class="jd-step__num">3</div>
                    <div class="jd-step__body">
                        <div class="jd-step__title"><i class="fa fa-download"></i> Klik Download PNG</div>
                        <div class="jd-step__desc">Setelah tabel muncul, klik tombol <strong>Download PNG</strong> &mdash; file gambar siap dikirim ke dokter.</div>
                    </div>
                </div>
            </div>

            <button type="button" class="st-btn-brand jd-empty__cta" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="true">
                <i class="fa fa-filter"></i> Mulai &mdash; Buka Filter
            </button>
        </div>
    @else
        {{-- ── Action bar (di luar capture area) ── --}}
        <div class="jd-actions">
            <button type="button" id="jdDownloadBtn" class="jd-download-btn">
                <i class="fa fa-download"></i>
                <span class="jd-download-btn__main">Download Jadwal sebagai PNG</span>
                <span class="jd-download-btn__sub">Siap dikirim ke dokter</span>
            </button>
            <button type="button" class="jd-change-btn" data-toggle="collapse" data-target="#filterCollapse" aria-expanded="false" title="Ganti dokter / tanggal">
                <i class="fa fa-sliders-h"></i> Ganti Dokter / Tanggal
            </button>
        </div>

        {{-- ── Area yang akan di-capture (banner + tabel) ── --}}
        <div id="jdCaptureArea" class="jd-capture">

        {{-- ── Banner header (ikut ter-capture saat screenshot) ── --}}
        <div class="jd-banner">
            <div class="jd-banner__title">
                Jadwal {{ $selectedDoctor?->name ?? 'Dokter' }}
            </div>
            <div class="jd-banner__meta">
                <span><i class="fa fa-calendar"></i> {{ $fmtTanggal($filterDateFrom) }} &mdash; {{ $fmtTanggal($filterDateTo) }}</span>
                @if($selectedBranch)
                    <span class="jd-banner__sep">|</span>
                    <span><i class="fa fa-building"></i> Cabang {{ $selectedBranch->name }}</span>
                @endif
                <span class="jd-banner__sep">|</span>
                <span><i class="fa fa-list-ol"></i> {{ $reservations->total() }} reservasi</span>
            </div>
        </div>

        @php
            // Sort state untuk kolom JAM. Cycle: null → asc → desc → null.
            $sortJam   = request('sort_jam');
            $nextSort  = $sortJam === 'asc' ? 'desc' : ($sortJam === 'desc' ? '' : 'asc');
            $sortUrl   = route('rawreservation', array_merge(
                request()->except(['sort_jam', 'page']),
                $nextSort ? ['sort_jam' => $nextSort] : []
            ));
            $sortIcon  = $sortJam === 'asc' ? 'fa-sort-up'
                        : ($sortJam === 'desc' ? 'fa-sort-down' : 'fa-sort');
            $sortTitle = $sortJam === 'asc'
                ? 'Saat ini: Jam pagi → malam. Klik untuk balik (malam → pagi).'
                : ($sortJam === 'desc'
                    ? 'Saat ini: Jam malam → pagi. Klik untuk kembali ke urutan default (per tanggal).'
                    : 'Klik untuk urutkan berdasarkan jam (pagi → malam).');
        @endphp

        {{-- ── Tabel ramah-screenshot ── --}}
        <div class="jd-table-wrap jd-table-wrap--capture">
            <table class="jd-table">
                <thead>
                    <tr>
                        <th>Hari &amp; Tanggal</th>
                        <th>Pasien</th>
                        <th class="jd-th-sortable {{ $sortJam ? 'jd-th-sortable--active' : '' }}">
                            <a href="{{ $sortUrl }}" class="jd-sort-link" title="{{ $sortTitle }}">
                                Jam <i class="fa {{ $sortIcon }}"></i>
                            </a>
                        </th>
                        <th>Perawatan</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $row)
                        @php
                            $hariNama = $row->date ? $hariList[$row->date->dayOfWeek] : '';
                            $tanggal  = $row->date ? $hariNama . ', ' . $row->date->format('d M Y') : '-';
                            $jam      = $fmtJam($row->start_hour) . ' &ndash; ' . $fmtJam($row->end_hour);
                            $services = $row->services->map(fn($s) => $s->docService?->name)->filter()->implode(', ');
                            if (! $services && ! empty($row->lainnya_text)) {
                                $services = $row->lainnya_text;
                            }

                            // Status priority cascade: tampilkan HANYA tahap terakhir
                            // yang sudah diisi. Urutan: Kehadiran > FU H > FU H-1 > Status utama.
                            $kehadiran = $row->keterangan_kehadiran ?? null;
                            $fuH       = $row->follow_up_h ?? null;
                            $fuHmin1   = $row->follow_up_h_min_1 ?? null;

                            if ($kehadiran) {
                                $statusLine = ($kehadiranLabel[$kehadiran] ?? ucfirst($kehadiran)) . ' Kehadiran';
                            } elseif ($fuH) {
                                $statusLine = ($fuLabel[$fuH] ?? ucfirst($fuH)) . ' FU H';
                            } elseif ($fuHmin1) {
                                $statusLine = ($fuLabel[$fuHmin1] ?? ucfirst($fuHmin1)) . ' FU H-1';
                            } else {
                                $statusLine = $statusOptions[$row->status]['label'] ?? ($row->status ?: '—');
                            }

                            // Warna pill status: kehadiran menang (hijau/merah), else ikut status.
                            if ($kehadiran === 'hadir') {
                                $stPill = ['bg' => '#E3F4E8', 'color' => '#2F8E55'];
                            } elseif ($kehadiran === 'tidak_hadir') {
                                $stPill = ['bg' => '#FBE3DE', 'color' => '#C0392B'];
                            } else {
                                $opt = $statusOptions[$row->status] ?? null;
                                $stPill = ['bg' => $opt['bg'] ?? '#F0E6DA', 'color' => $opt['color'] ?? '#8A6A52'];
                            }
                        @endphp
                        <tr>
                            <td class="jd-cell-date">{{ $tanggal }}</td>
                            <td>{{ $row->customer?->name ?? '—' }}</td>
                            <td class="jd-cell-mono"><span class="jd-chip-jam">{!! $jam !!}</span></td>
                            <td>@if($services)<span class="jd-pill-treat">{{ $services }}</span>@else<span class="jd-muted">—</span>@endif</td>
                            <td>{{ $row->keterangan ?: '—' }}</td>
                            <td class="jd-cell-status">
                                <span class="jd-pill" style="background:{{ $stPill['bg'] }};color:{{ $stPill['color'] }};">{{ $statusLine }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="jd-empty-row">
                                <i class="fa fa-folder-open"></i>
                                <div class="jd-empty-row__title">Belum ada reservasi {{ $selectedDoctor ? 'untuk ' . $selectedDoctor->name : '' }} di periode ini</div>
                                <div class="jd-empty-row__hint">Coba ganti rentang tanggal lewat tombol <strong>Ganti Dokter / Tanggal</strong> di atas.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        </div> {{-- /#jdCaptureArea --}}

        @if($reservations->hasPages())
            <div class="jd-pagination">
                {{ $reservations->appends(request()->all())->links() }}
            </div>
        @endif
    @endif
</div>

<style>
/* ── Empty state: panduan visual ── */
.jd-empty {
    padding: 48px 24px;
    background: linear-gradient(180deg, var(--brown-cream, #fff8f3) 0%, #ffffff 100%);
    border: 2px dashed var(--brown-2);
    border-radius: 14px;
    margin: 16px 0;
    width: 100%;
}
.jd-empty__hero {
    text-align: center;
    margin-bottom: 32px;
}
.jd-empty__icon {
    width: 84px;
    height: 84px;
    line-height: 84px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: var(--brown);
    color: #fff;
    font-size: 38px;
    box-shadow: 0 4px 12px rgba(70,21,0,0.25);
}
.jd-empty__title {
    font-size: 1.6rem;
    color: var(--brown);
    margin-bottom: 8px;
    font-weight: 700;
}
.jd-empty__lead {
    color: var(--text-muted, #9A7256);
    font-size: 15px;
    max-width: 560px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Steps list */
.jd-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.jd-step {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    background: var(--cream-surface, #fff);
    border: 1px solid var(--divider-strong, #EFE0CE);
    border-radius: 12px;
    padding: 16px;
    transition: transform .15s, box-shadow .15s;
}
.jd-step:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(70,21,0,0.07);
}
.jd-step__num {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    line-height: 36px;
    text-align: center;
    background: var(--brown);
    color: #fff;
    border-radius: 50%;
    font-weight: 700;
    font-size: 16px;
}
.jd-step__body { flex: 1; min-width: 0; }
.jd-step__title {
    font-weight: 700;
    color: var(--text-primary, #3B1500);
    margin-bottom: 4px;
    font-size: 15px;
}
.jd-step__title i {
    color: var(--brown-2);
    margin-right: 4px;
}
.jd-step__desc {
    color: var(--text-muted, #9A7256);
    font-size: 13.5px;
    line-height: 1.5;
}

.jd-empty__cta {
    display: block;
    margin: 0 auto;
    font-size: 15px;
    padding: 12px 28px !important;
}

/* ── Action bar (tidak ikut ter-capture) ── */
.jd-actions {
    display: flex;
    align-items: stretch;
    gap: 12px;
    margin: 16px 0 12px;
    flex-wrap: wrap;
}

/* Tombol Download PNG yang menonjol */
.jd-download-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 12px 22px;
    background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
    color: #fff;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(22,163,74,0.25);
    transition: transform .15s, box-shadow .15s;
    font-family: inherit;
}
.jd-download-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(22,163,74,0.35);
    color: #fff;
}
.jd-download-btn:disabled {
    opacity: 0.65;
    cursor: wait;
}
.jd-download-btn > i {
    font-size: 22px;
}
.jd-download-btn__main {
    display: block;
    font-weight: 700;
    font-size: 15px;
    line-height: 1.2;
    text-align: left;
}
.jd-download-btn__sub {
    display: block;
    font-size: 11.5px;
    opacity: 0.9;
    text-align: left;
    margin-top: 2px;
}

/* Tombol ganti dokter/tanggal — outline */
.jd-change-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: #fff;
    color: var(--brown);
    border: 1.5px solid var(--brown-2);
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background .15s;
    font-family: inherit;
}
.jd-change-btn:hover {
    background: var(--brown-cream, #fff8f3);
}
/* ── Capture wrapper ── */
.jd-capture {
    background: #fff;
    padding: 4px;
    border-radius: 10px;
}
.jd-capture--exporting {
    overflow: visible !important;
}
.jd-capture--exporting .jd-table-wrap--capture {
    overflow: visible !important;
}
.jd-capture--exporting .jd-table {
    width: max-content !important;
    min-width: 100%;
}

/* ── Banner header (ramah-screenshot) ── */
.jd-banner {
    background: linear-gradient(135deg, var(--brown) 0%, var(--brown-2) 100%);
    color: #fff;
    padding: 20px 28px;
    border-radius: 10px;
    margin: 16px 0 12px;
    border-left: 6px solid rgb(255,193,7);
}
.jd-banner__title {
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 6px;
    letter-spacing: 0.2px;
}
.jd-banner__meta {
    font-size: 0.95rem;
    opacity: 0.95;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.jd-banner__meta i { margin-right: 4px; }
.jd-banner__sep { opacity: 0.5; }

/* ── Tabel ── */
.jd-table-wrap {
    overflow-x: auto;
    margin-bottom: 12px;
    /* Padding tipis supaya box-shadow tabel tidak kepotong scroll-area. */
    padding: 1px;
}
.jd-table {
    width: 100%;
    /* separate (bukan collapse) supaya border-radius sudut tabel ke-render —
       termasuk di PNG (html2canvas set overflow:visible, jadi clip wrapper tak jalan). */
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
    line-height: 1.5;
    background: #fff;
    border: 1px solid var(--divider-strong, #EFE0CE);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05);
}
/* Sudut membulat (separate → radius per-sel pojok). */
.jd-table thead th:first-child { border-top-left-radius: 16px; }
.jd-table thead th:last-child  { border-top-right-radius: 16px; }
.jd-table tbody tr:last-child td:first-child { border-bottom-left-radius: 16px; }
.jd-table tbody tr:last-child td:last-child  { border-bottom-right-radius: 16px; }
.jd-table tbody tr:last-child td { border-bottom: none; }
.jd-table thead th {
    background: linear-gradient(180deg, #5A2410, #3A1100);
    color: #EAD3BC;
    font-weight: 700;
    text-align: left;
    padding: 13px 12px;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-size: 12px;
    white-space: nowrap;
}
.jd-th-sortable { padding: 0; }
.jd-sort-link {
    display: block;
    padding: 12px 10px;
    color: #fff;
    text-decoration: none;
    transition: background .15s;
}
.jd-sort-link:hover { background: rgba(255,255,255,0.12); color: #fff; text-decoration: none; }
.jd-sort-link i { margin-left: 6px; opacity: 0.75; font-size: 12px; }
.jd-th-sortable--active .jd-sort-link i { opacity: 1; color: #ffc107; }
.jd-table tbody td {
    padding: 12px;
    border: none;
    border-bottom: 1px solid var(--divider-soft, #F4EADD);
    vertical-align: middle;
    color: var(--text-primary, #3B1500);
}
.jd-table tbody tr:nth-child(even) td {
    background: #FFFCF9;
}
.jd-table tbody tr:hover td { background: #FBF3E9; }
.jd-cell-date {
    white-space: nowrap;
    font-weight: 700;
    color: var(--text-primary, #3B1500);
}
.jd-cell-mono { white-space: nowrap; }
.jd-cell-status {
    white-space: nowrap;
}
/* Pill status / chip jam / pill perawatan (design View 5) */
.jd-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 11px; border-radius: 20px;
    font-size: 12px; font-weight: 700; white-space: nowrap;
}
.jd-chip-jam {
    display: inline-block; background: #F4E7D6; color: #7A5B43;
    border-radius: 7px; padding: 4px 9px; font-weight: 700; font-size: 12.5px;
    font-variant-numeric: tabular-nums; white-space: nowrap;
}
.jd-pill-treat {
    display: inline-block; background: #F1E9DF; color: #5A3A22;
    border-radius: 8px; padding: 4px 10px; font-size: 12.5px; font-weight: 600;
}
.jd-muted { color: var(--text-subtle, #B79A82); }
.jd-status-sub {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
    margin-top: 4px;
}
.jd-empty-row {
    text-align: center;
    padding: 40px 10px;
    color: #6b7280;
}
.jd-empty-row i {
    display: block;
    font-size: 36px;
    margin-bottom: 12px;
    color: #9ca3af;
}
.jd-empty-row__title {
    font-size: 15px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}
.jd-empty-row__hint {
    font-size: 13px;
    color: #6b7280;
}

/* ── Pagination ── */
.jd-pagination {
    display: flex;
    justify-content: center;
    padding: 12px 0;
}
</style>

@if(! $jadwalEmptyState)
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script>
(function () {
    const btn = document.getElementById('jdDownloadBtn');
    const area = document.getElementById('jdCaptureArea');
    if (!btn || !area) return;

    const doctorName = @json($selectedDoctor?->name ?? 'dokter');
    const dateFrom   = @json($filterDateFrom);
    const dateTo     = @json($filterDateTo);

    const slug = doctorName
        .replace(/^drg\.?\s*/i, '')
        .replace(/[^a-zA-Z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const filename = `Jadwal_${slug}_${dateFrom}_${dateTo}.png`;

    btn.addEventListener('click', function () {
        if (typeof html2canvas !== 'function') {
            alert('Library belum siap, coba lagi sebentar.');
            return;
        }

        const originalLabel = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i><span class="jd-download-btn__main">Memproses...</span><span class="jd-download-btn__sub">Tunggu sebentar</span>';

        area.classList.add('jd-capture--exporting');

        html2canvas(area, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            windowWidth: area.scrollWidth,
        }).then(function (canvas) {
            area.classList.remove('jd-capture--exporting');

            canvas.toBlob(function (blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }, 'image/png');

            btn.disabled = false;
            btn.innerHTML = originalLabel;
        }).catch(function (err) {
            area.classList.remove('jd-capture--exporting');
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            alert('Gagal menyimpan PNG: ' + (err?.message || err));
        });
    });
})();
</script>
@endif
