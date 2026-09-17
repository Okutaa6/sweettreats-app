@extends('layouts.app')

@section('content')
@php
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };

    // Filter aktif — ikut dibawa ke navigasi bulan, export, dan tombol reset
    // supaya pindah bulan / download tidak diam-diam membuang saringan user.
    $categoryOptions       = $categoryOptions ?? [];
    $categoryFilterOptions = $categoryFilterOptions ?? $categoryOptions;
    $categories      = $categories ?? [];
    $search          = $search ?? '';
    $hasFilter       = $hasFilter ?? (! empty($categories) || $search !== '');
    $filterQuery     = array_filter([
        'q'          => $search,
        'categories' => $categories,
    ]);
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="pc-page-header pc-page-header--v2 fpc-page-header">
        <div class="pc-page-header__main">
            <a href="{{ route('finance.pettycash', ['year' => $year, 'month' => $month]) }}"
               class="fbh-back" title="Kembali ke Monitoring Petty Cash">
                <i class="fa fa-arrow-left"></i>
            </a>
            <div class="pc-page-icon">
                <i class="fa fa-history"></i>
            </div>
            <div class="pc-page-header__heading">
                <h4 class="pc-page-title">Riwayat Transaksi — {{ $branch->name }}</h4>
                <div class="pc-page-meta">
                    <span class="pc-page-meta__item">
                        <i class="fa fa-calendar"></i> {{ $monthName }} {{ $year }}
                    </span>
                    <span class="pc-page-meta__divider"></span>
                    <span class="pc-page-meta__item">
                        <i class="fa fa-list"></i> {{ count($rows) }} pengeluaran{{ $hasFilter ? ' (tersaring)' : '' }}
                    </span>
                    @if($period->is_closed)
                        <span class="pc-page-meta__divider"></span>
                        <span class="pc-page-meta__item" style="color:#b45309;">
                            <i class="fa fa-lock"></i> Periode Ditutup
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="fpc-header-actions">
            {{-- Navigasi bulan --}}
            <a href="{{ route('finance.pettycash.branch-history', array_merge(['branchId' => $branch->id, 'year' => $prevDate->year, 'month' => $prevDate->month], $filterQuery)) }}"
               class="fpc-action-btn" title="Bulan sebelumnya">
                <i class="fa fa-chevron-left"></i>
            </a>
            <form method="GET"
                  action="{{ route('finance.pettycash.branch-history', ['branchId' => $branch->id]) }}"
                  class="fpc-month-picker">
                <select name="month" onchange="this.form.submit();" class="fpc-month-select">
                    @foreach($bulanLabel as $m => $label)
                        <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ $label }} {{ $year }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="year" value="{{ $year }}">
                {{-- Filter ikut terbawa saat ganti bulan. --}}
                @if($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
                @foreach($categories as $c)
                    <input type="hidden" name="categories[]" value="{{ $c }}">
                @endforeach
            </form>
            <a href="{{ route('finance.pettycash.branch-history', array_merge(['branchId' => $branch->id, 'year' => $nextDate->year, 'month' => $nextDate->month], $filterQuery)) }}"
               class="fpc-action-btn" title="Bulan berikutnya">
                <i class="fa fa-chevron-right"></i>
            </a>
            {{-- Export — isinya persis baris yang tampil (filter ikut). --}}
            <a href="{{ route('finance.pettycash.branch-history.export', array_merge(['branchId' => $branch->id, 'year' => $year, 'month' => $month], $filterQuery)) }}"
               class="fpc-action-btn fpc-action-btn--excel"
               title="Download jejak transaksi bulan ini ke Excel (mengikuti filter aktif)">
                <i class="fa fa-file-excel"></i> Export Excel
            </a>
            @if(isSuperAdmin() && pettyCashResetEnabled())
                {{-- Reset jejak transaksi cabang+bulan ini (hard delete) — hanya Super Admin, hanya di dev. --}}
                <form method="POST"
                      action="{{ route('finance.pettycash.branch-history.reset', ['branchId' => $branch->id]) }}"
                      onsubmit="return confirm('Reset SEMUA jejak transaksi {{ $branch->name }} untuk {{ $monthName }} {{ $year }}?\n\n• Seluruh transaksi (pengeluaran, top up, transfer) periode ini dihapus PERMANEN.\n• Aksi TIDAK bisa di-undo.\n\nLanjut?');"
                      style="margin:0;">
                    @csrf
                    <input type="hidden" name="year"  value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button type="submit" class="fpc-action-btn fpc-action-btn--danger" title="Reset jejak transaksi bulan ini (permanen)">
                        <i class="fa fa-trash"></i> Reset Jejak
                    </button>
                </form>
            @endif
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── SUMMARY STRIP ─── --}}
    <div class="fbh-summary">
        <div class="fbh-sumcard">
            <div class="fbh-sumcard__k">Saldo Awal (Opening)</div>
            <div class="fbh-sumcard__v">{{ $rupiah($opening) }}</div>
        </div>
        <div class="fbh-sumcard fbh-sumcard--in">
            <div class="fbh-sumcard__k"><i class="fa fa-arrow-down"></i> Total Cash In</div>
            <div class="fbh-sumcard__v">{{ $rupiah($cashIn) }}</div>
        </div>
        <div class="fbh-sumcard fbh-sumcard--out">
            <div class="fbh-sumcard__k"><i class="fa fa-arrow-up"></i> Total Cash Out</div>
            <div class="fbh-sumcard__v">{{ $rupiah($cashOut) }}</div>
        </div>
        <div class="fbh-sumcard fbh-sumcard--closing">
            <div class="fbh-sumcard__k">Saldo Akhir (Closing)</div>
            <div class="fbh-sumcard__v">{{ $rupiah($closing) }}</div>
        </div>
    </div>

    {{-- ─── LEDGER TABLE ─── --}}
    <div class="fpc-section">
        <div class="fpc-section__head">
            <h5 class="fpc-section__title"><i class="fa fa-list-ul"></i> Jejak Transaksi</h5>
            <small class="fpc-section__sub">Hanya menampilkan pengeluaran; top up &amp; transfer tak ditampilkan. Termasuk yang sudah dihapus (ditandai).</small>
        </div>

        {{-- ─── FILTER: kategori (multi) + cari keterangan ───
             GET ke halaman yang sama; bulan/tahun aktif ikut sebagai hidden.
             Filter hanya menyaring baris tabel — kartu ringkasan di atas tetap
             posisi kas sebenarnya untuk satu bulan penuh. --}}
        <form method="GET"
              action="{{ route('finance.pettycash.branch-history', ['branchId' => $branch->id]) }}"
              class="fbh-filter">
            <input type="hidden" name="year"  value="{{ $year }}">
            <input type="hidden" name="month" value="{{ $month }}">

            @if(! empty($categoryFilterOptions))
                <div class="fbh-filter__field fbh-catfilter" data-fbh-catfilter>
                    <label>Kategori</label>
                    <button type="button" class="fbh-catfilter__toggle" data-fbh-catfilter-toggle>
                        <span class="fbh-catfilter__summary">
                            @if(empty($categories))
                                Semua kategori
                            @elseif(count($categories) === 1)
                                {{ $categoryOptions[$categories[0]] ?? $categories[0] }}
                            @else
                                {{ count($categories) }} kategori dipilih
                            @endif
                        </span>
                        <i class="fa fa-chevron-down"></i>
                    </button>
                    <div class="fbh-catfilter__panel" data-fbh-catfilter-panel hidden>
                        @foreach($categoryFilterOptions as $catKey => $catLabel)
                            <label class="fbh-catfilter__opt">
                                <input type="checkbox" name="categories[]" value="{{ $catKey }}"
                                       {{ in_array($catKey, $categories, true) ? 'checked' : '' }}>
                                <span>{{ $catLabel }}</span>
                            </label>
                        @endforeach
                        <div class="fbh-catfilter__actions">
                            <button type="button" class="fbh-catfilter__reset" data-fbh-catfilter-reset>Reset</button>
                            <button type="submit" class="fbh-catfilter__apply">Terapkan</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="fbh-filter__field fbh-filter__field--grow">
                <label>Cari keterangan</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari keterangan...">
            </div>

            <div class="fbh-filter__actions">
                <button type="submit" class="fbh-filter__btn fbh-filter__btn--apply" title="Terapkan filter">
                    <i class="fa fa-search"></i>
                </button>
                @if($hasFilter)
                    <a href="{{ route('finance.pettycash.branch-history', ['branchId' => $branch->id, 'year' => $year, 'month' => $month]) }}"
                       class="fbh-filter__btn" title="Hapus semua filter">
                        <i class="fa fa-times"></i> Reset
                    </a>
                @endif
            </div>
        </form>

        @if(count($rows) === 0)
            <div class="fpc-empty">
                <div class="fpc-empty__icon"><i class="fa fa-history"></i></div>
                @if($hasFilter)
                    <div class="fpc-empty__title">Tidak ada transaksi yang cocok</div>
                    <div class="fpc-empty__desc">
                        Tidak ada pengeluaran di {{ $monthName }} {{ $year }} yang cocok dengan filter aktif.
                        <a href="{{ route('finance.pettycash.branch-history', ['branchId' => $branch->id, 'year' => $year, 'month' => $month]) }}">Hapus filter</a>.
                    </div>
                @else
                    <div class="fpc-empty__title">Belum ada transaksi</div>
                    <div class="fpc-empty__desc">Cabang {{ $branch->name }} belum mencatat transaksi apa pun di {{ $monthName }} {{ $year }}.</div>
                @endif
            </div>
        @else
            <div class="fpc-audit-card">
                <div class="fbh-table-wrap">
                    <table class="fbh-table">
                        <thead>
                            <tr>
                                <th width="96">Tanggal</th>
                                <th>Keterangan</th>
                                <th width="130">Kategori</th>
                                <th width="140">Wallet</th>
                                <th class="text-right" width="130">Jumlah</th>
                                <th class="fbh-col-meta" width="160">Dicatat / Diubah oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr class="{{ $r->deleted ? 'fbh-row--deleted' : '' }}">
                                    <td class="fbh-date">
                                        {{ $r->date->translatedFormat('d M Y') }}
                                        @if($r->recorded_at)
                                            <div class="fbh-date-time" title="Jam transaksi dicatat ke sistem">
                                                <i class="fa fa-clock"></i> {{ $r->recorded_at->translatedFormat('H:i') }} WIB
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="fbh-desc">
                                            {{ $r->description ?: '—' }}
                                            @if($r->deleted)
                                                <span class="fbh-tag fbh-tag--deleted"><i class="fa fa-trash"></i> Dihapus</span>
                                            @endif
                                            @if($r->attachment_path)
                                                {{-- Bukti pengeluaran — icon paperclip, buka lightbox.
                                                     Sama persis dengan tabel petty cash. --}}
                                                <button type="button"
                                                        class="pc-attach-icon"
                                                        data-pc-attach-url="{{ asset($r->attachment_path) }}"
                                                        data-pc-attach-ext="{{ strtolower(pathinfo($r->attachment_path, PATHINFO_EXTENSION)) }}"
                                                        title="Lihat bukti pengeluaran">
                                                    <i class="fa fa-paperclip"></i>
                                                </button>
                                            @endif
                                        </div>
                                        @if($r->ref_no)
                                            <div class="fbh-ref">Ref: {{ $r->ref_no }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($r->category_label)
                                            <span class="fbh-tag fbh-tag--cat">{{ $r->category_label }}</span>
                                        @else
                                            <span class="fbh-amt--muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fbh-wallet">
                                            <span class="fbh-wallet-badge" style="background: {{ $r->wallet_color }};">{{ $r->wallet_short }}</span>
                                            {{ $r->wallet_name }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <span class="fbh-amt fbh-amt--out">{{ $rupiah($r->amount) }}</span>
                                    </td>
                                    <td class="fbh-col-meta">
                                        <span class="fbh-by">
                                            @if($r->by !== '—')
                                                <span class="fbh-by-av">{{ strtoupper(mb_substr($r->by, 0, 1)) }}</span>
                                            @endif
                                            {{ $r->by }}
                                        </span>
                                        @if($r->edited)
                                            <div class="fbh-edited" title="Diedit oleh {{ $r->edited_by }}">
                                                <i class="fa fa-pen"></i> Diedit {{ $r->edited_at ? $r->edited_at->translatedFormat('d M, H:i') : '' }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fbh-foot">
                                <td colspan="4">
                                    @if($hasFilter)
                                        Total pengeluaran yang ditampilkan (transaksi aktif)
                                        <div class="fbh-foot__sub">Total satu bulan penuh: {{ $rupiah($cashOut) }}</div>
                                    @else
                                        Total pengeluaran bulan ini (transaksi aktif)
                                    @endif
                                </td>
                                <td class="text-right"><span class="fbh-amt fbh-amt--out">{{ $rupiah($hasFilter ? $rowsTotal : $cashOut) }}</span></td>
                                <td class="fbh-col-meta"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ─── Lightbox preview bukti pengeluaran ───
     Markup & id sama dengan halaman petty cash (class-nya global di
     components.sweettreats.css); handler-nya lokal di bawah karena halaman
     ini tidak menyertakan script index petty cash. --}}
<div class="pc-lightbox" id="pcAttachLightbox" role="dialog" aria-modal="true" hidden>
    <div class="pc-lightbox__backdrop" data-pc-lightbox-close></div>
    <div class="pc-lightbox__dialog">
        <div class="pc-lightbox__header">
            <div class="pc-lightbox__title">
                <i class="fa fa-paperclip"></i> Bukti Pengeluaran
            </div>
            <div class="pc-lightbox__actions">
                <a href="#" class="pc-lightbox__btn" id="pcAttachDownload" download title="Download file">
                    <i class="fa fa-download"></i>
                </a>
                <a href="#" class="pc-lightbox__btn" id="pcAttachOpenTab" target="_blank" rel="noopener" title="Buka di tab baru">
                    <i class="fa fa-external-link-alt"></i>
                </a>
                <button type="button" class="pc-lightbox__btn pc-lightbox__btn--close"
                        data-pc-lightbox-close title="Tutup (Esc)">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <div class="pc-lightbox__body" id="pcAttachBody">
            <!-- isi dinamis via JS: <img> atau <iframe> -->
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════
   Halaman ini berdiri sendiri (tidak menyertakan partial dashboard),
   jadi semua style yang dipakai didefinisikan di sini — termasuk yang
   di index/_audit_feed hanya lokal (fpc-section, fpc-audit-table, dst).
   ═══════════════════════════════════════════════════════════════ */

/* ── Header actions (bulan picker, nav, tombol) ── */
.fbh-back {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 10px;
    background: #fff; border: 1px solid var(--divider-soft);
    color: var(--brown); text-decoration: none; margin-right: 12px;
    transition: background .15s, border-color .15s;
}
.fbh-back:hover { background: #fffaf2; border-color: #d6c4a8; color: var(--brown); }

.fpc-header-actions { display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0; }
.fpc-month-picker { margin: 0; }
.fpc-month-select {
    appearance: none; -webkit-appearance: none;
    background: #fff url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23461500' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") no-repeat right 12px center / 10px;
    border: 1px solid var(--divider-soft); color: var(--brown);
    font-size: 12.5px; font-weight: 700; padding: 8px 32px 8px 14px;
    border-radius: 999px; cursor: pointer; transition: border-color .15s, box-shadow .15s;
}
.fpc-month-select:hover { border-color: var(--brown-2); }
.fpc-month-select:focus { outline: none; border-color: var(--brown); box-shadow: 0 0 0 3px rgba(70,21,0,0.08); }

.fpc-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; background: #fff; color: var(--brown);
    border: 1px solid var(--divider-soft); border-radius: 999px;
    font-size: 12.5px; font-weight: 700; cursor: pointer;
    text-decoration: none; transition: all .15s;
}
.fpc-action-btn:hover { border-color: var(--brown-2); color: var(--brown); text-decoration: none; transform: translateY(-1px); }
.fpc-action-btn i { font-size: 11px; }
.fpc-action-btn--primary { background: var(--brown); color: #fff; border-color: var(--brown); }
.fpc-action-btn--primary:hover { background: #5c1f00; color: #fff; }
.fpc-action-btn--danger { background: #fff; color: #b91c1c; border-color: #f3c9c9; }
.fpc-action-btn--danger:hover { background: #fef2f2; color: #b91c1c; border-color: #dc2626; }

/* ── Section wrapper ── */
.fpc-section { margin: 24px 0; }
.fpc-section__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; padding: 0 2px; flex-wrap: wrap; }
.fpc-section__title { margin: 0; font-size: 16px; font-weight: 800; color: var(--brown); display: inline-flex; align-items: center; gap: 10px; }
.fpc-section__title i { color: var(--brown); font-size: 13px; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; background: #f5e6d3; border-radius: 8px; }
.fpc-section__sub { color: #8b6f47; font-size: 12px; font-weight: 500; font-style: italic; }

/* ── Empty state ── */
.fpc-empty { text-align: center; padding: 48px 20px; background: #fff; border: 1px dashed var(--divider-soft); border-radius: 14px; }
.fpc-empty__icon { font-size: 30px; color: #cbb89a; margin-bottom: 12px; }
.fpc-empty__title { font-size: 15px; font-weight: 800; color: var(--brown); margin-bottom: 4px; }
.fpc-empty__desc { font-size: 12.5px; color: var(--text-muted); }

/* ── Summary strip ── */
.fbh-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 4px; }
.fbh-sumcard { background: #fff; border: 1px solid var(--divider-soft); border-radius: 14px; padding: 16px 18px; border-left: 4px solid #cbd5e1; }
.fbh-sumcard__k { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); margin-bottom: 6px; }
.fbh-sumcard__k i { font-size: 10px; margin-right: 3px; }
.fbh-sumcard__v { font-size: 22px; font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1.1; }
.fbh-sumcard--in { border-left-color: #16a34a; } .fbh-sumcard--in .fbh-sumcard__k { color: #15803d; }
.fbh-sumcard--out { border-left-color: #dc2626; } .fbh-sumcard--out .fbh-sumcard__k { color: #b91c1c; }
.fbh-sumcard--closing { border-left-color: var(--brown); background: linear-gradient(120deg, #fff 55%, #fdf6ee 100%); }
.fbh-sumcard--closing .fbh-sumcard__v { color: var(--brown); }

/* ── Ledger table ── */
.fpc-audit-card { background: #fff; border: 1px solid var(--divider-soft); border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(70,21,0,0.04); }
.fbh-table-wrap { overflow-x: auto; }
.fbh-table { width: 100%; min-width: 820px; border-collapse: collapse; font-size: 13px; }
.fbh-table thead th {
    background: var(--brown); color: #fff; font-size: 11px; text-transform: uppercase;
    letter-spacing: .5px; font-weight: 800; padding: 12px 16px; text-align: left; white-space: nowrap;
}
.fbh-table thead th.text-right,
.fbh-table td.text-right { text-align: right; }
.fbh-table thead th.fbh-col-meta { border-left: 1px solid rgba(255,255,255,.18); }
.fbh-table tbody td { padding: 13px 16px; border-bottom: 1px solid #f2e9dd; vertical-align: top; color: #334155; }
.fbh-table tbody tr:last-child td { border-bottom: none; }
.fbh-table tbody tr:hover { background: #fffaf2; }
.fbh-table .fbh-col-meta { border-left: 1px solid #f2e9dd; }

.fbh-date { font-size: 12px; color: #475569; font-weight: 700; white-space: nowrap; }
.fbh-date-time { font-size: 11px; color: #94a3b8; font-weight: 600; margin-top: 3px; font-variant-numeric: tabular-nums; }
.fbh-date-time i { font-size: 9px; margin-right: 2px; color: #b8c2cf; }
.fbh-desc { font-weight: 600; color: #1e293b; display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.fbh-ref { font-size: 11px; color: var(--text-muted); margin-top: 3px; }

.fbh-tag { font-size: 9.5px; font-weight: 800; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
.fbh-tag--topup { background: #dcfce7; color: #166534; }
.fbh-tag--transfer { background: #e0f2fe; color: #075985; }
.fbh-tag--cat { background: #f5e6d3; color: #92400e; }
.fbh-tag--deleted { background: #fee2e2; color: #991b1b; }
.fbh-tag--deleted i { font-size: 8px; margin-right: 2px; }

.fbh-wallet { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: #334155; }
.fbh-wallet-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 22px; padding: 0 7px; border-radius: 6px; color: #fff; font-size: 9.5px; font-weight: 800; letter-spacing: .4px; flex-shrink: 0; }

.fbh-amt { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
.fbh-amt--in { color: #16a34a; } .fbh-amt--out { color: #dc2626; }
.fbh-amt--muted { color: #cbd5e1; font-weight: 600; }
.fbh-saldo { font-weight: 800; color: #0f172a; font-variant-numeric: tabular-nums; white-space: nowrap; }
.fbh-by { font-weight: 700; color: #475569; font-size: 12.5px; display: inline-flex; align-items: center; gap: 6px; }
.fbh-by-av { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #ede2d1; color: var(--brown); font-size: 10px; font-weight: 800; flex-shrink: 0; }
.fbh-edited { font-size: 11px; color: #b45309; margin-top: 3px; }
.fbh-edited i { font-size: 9px; }

/* Baris terhapus — jejak audit, redup + coret */
.fbh-row--deleted { background: #fdf5f5; }
.fbh-row--deleted:hover { background: #fbeeee; }
.fbh-row--deleted td { color: #b0a3a3; }
.fbh-row--deleted .fbh-desc { color: #9a8686; text-decoration: line-through; text-decoration-color: #dcc0c0; }
.fbh-row--deleted .fbh-amt { color: #c9adad; text-decoration: line-through; }

.fbh-foot td { background: #fdf6ee; font-weight: 800; color: var(--brown); border-top: 2px solid #ecd9c2 !important; font-size: 12.5px; padding: 13px 16px; }
.fbh-foot .fbh-col-meta { border-left: 1px solid #ecd9c2; }
.fbh-foot__sub { font-size: 11px; font-weight: 600; color: #8b6f47; margin-top: 3px; font-style: italic; }

/* ── Tombol export CSV di header ── */
.fpc-action-btn--excel { color: #15803d; border-color: #cfe8d6; }
.fpc-action-btn--excel:hover { color: #15803d; border-color: #16a34a; background: #f0fdf4; }

/* ── Tombol bukti di kolom keterangan ── */
.fbh-row--deleted .pc-attach-icon { opacity: .6; }

/* ── Filter bar ── */
.fbh-filter {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px;
    background: #fff; border: 1px solid var(--divider-soft); border-radius: 14px;
    padding: 14px 16px; margin-bottom: 14px;
}
.fbh-filter__field { display: flex; flex-direction: column; gap: 5px; min-width: 170px; }
.fbh-filter__field--grow { flex: 1 1 240px; }
.fbh-filter__field > label {
    font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px;
    color: var(--text-muted); margin: 0;
}
.fbh-filter__field input[type=text] {
    width: 100%; padding: 7px 10px; font-size: 13.5px; color: #1f2937;
    background: #fff; border: 1px solid var(--divider-soft); border-radius: 8px;
}
.fbh-filter__field input[type=text]:focus { outline: none; border-color: var(--brown); box-shadow: 0 0 0 3px rgba(70,21,0,.07); }
.fbh-filter__actions { display: flex; align-items: center; gap: 8px; }
.fbh-filter__btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
    background: #fff; border: 1px solid var(--divider-soft); color: var(--brown);
    cursor: pointer; text-decoration: none;
}
.fbh-filter__btn:hover { border-color: var(--brown-2); color: var(--brown); text-decoration: none; }
.fbh-filter__btn--apply { background: var(--brown); border-color: var(--brown); color: #fff; }
.fbh-filter__btn--apply:hover { background: #5c1f00; color: #fff; }

/* ── Dropdown kategori (multi-select) ── */
.fbh-catfilter { position: relative; }
.fbh-catfilter__toggle {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; min-width: 170px; padding: 7px 10px;
    background: #fff; border: 1px solid var(--divider-soft); border-radius: 8px;
    font-size: 13.5px; color: #1f2937; cursor: pointer; text-align: left;
}
.fbh-catfilter__toggle:hover { border-color: var(--brown-2); }
.fbh-catfilter__toggle i { font-size: 11px; color: #6b7280; flex-shrink: 0; }
.fbh-catfilter__summary { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fbh-catfilter__panel {
    position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; min-width: 210px;
    max-height: 320px; overflow-y: auto;
    background: #fff; border: 1px solid var(--divider-soft); border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 8px;
    display: flex; flex-direction: column; gap: 2px;
}
.fbh-catfilter__opt {
    display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: 6px;
    font-size: 13.5px; color: #374151; cursor: pointer; margin: 0; font-weight: 600;
}
.fbh-catfilter__opt:hover { background: #fffaf2; }
.fbh-catfilter__opt input { width: 15px; height: 15px; accent-color: var(--brown); cursor: pointer; }
.fbh-catfilter__actions {
    display: flex; justify-content: space-between; gap: 8px; position: sticky; bottom: -8px;
    margin-top: 6px; padding-top: 8px; background: #fff; border-top: 1px solid var(--divider-soft);
}
.fbh-catfilter__reset, .fbh-catfilter__apply {
    padding: 6px 14px; border-radius: 7px; font-size: 12.5px; font-weight: 700; cursor: pointer;
}
.fbh-catfilter__reset { background: #fff; border: 1px solid #cbd5e1; color: #475569; }
.fbh-catfilter__reset:hover { background: #f1f5f9; }
.fbh-catfilter__apply { background: var(--brown); border: 1px solid var(--brown); color: #fff; }
.fbh-catfilter__apply:hover { background: #5c1f00; }
</style>

<script>
(function () {
    // Dropdown kategori: buka/tutup + reset centang.
    document.querySelectorAll('[data-fbh-catfilter]').forEach(function (root) {
        var toggle = root.querySelector('[data-fbh-catfilter-toggle]');
        var panel  = root.querySelector('[data-fbh-catfilter-panel]');
        var reset  = root.querySelector('[data-fbh-catfilter-reset]');
        if (toggle && panel) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.hidden = !panel.hidden;
            });
            document.addEventListener('click', function (e) {
                if (! root.contains(e.target)) panel.hidden = true;
            });
        }
        if (reset) {
            reset.addEventListener('click', function () {
                root.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
            });
        }
    });

    // Lightbox bukti: gambar tampil inline, selain itu (PDF) via iframe.
    (function () {
        var lb    = document.getElementById('pcAttachLightbox');
        var body  = document.getElementById('pcAttachBody');
        var dlBtn = document.getElementById('pcAttachDownload');
        var tabBtn= document.getElementById('pcAttachOpenTab');
        if (!lb || !body) return;

        var IMG_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        function open(url, ext) {
            ext = (ext || '').toLowerCase();
            var isImg = IMG_EXTS.indexOf(ext) !== -1;
            body.innerHTML = isImg
                ? '<img src="' + url + '" alt="bukti pengeluaran" class="pc-lightbox__img">'
                : '<iframe src="' + url + '" class="pc-lightbox__iframe" frameborder="0"></iframe>';
            if (dlBtn)  dlBtn.href  = url;
            if (tabBtn) tabBtn.href = url;
            lb.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function close() {
            lb.hidden = true;
            body.innerHTML = '';
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-pc-attach-url]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                open(btn.dataset.pcAttachUrl, btn.dataset.pcAttachExt);
                return;
            }
            if (e.target.closest('[data-pc-lightbox-close]')) {
                e.preventDefault();
                close();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lb.hidden) close();
        });
    })();
})();
</script>
@endsection
