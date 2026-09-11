@extends('layouts.app')

@section('content')
@php
    $monthName = $bulanLabel[$month] ?? '-';
    $rupiah = function ($v) { return 'Rp ' . number_format((float) $v, 0, ',', '.'); };
    $kind     = $kind ?? 'petty_cash';
    $tabs     = $tabs ?? [];
    $tabLabel = collect($tabs)->firstWhere('kind', $kind)['label'] ?? 'Petty cash';

    // Helper untuk preserve branch di semua URL (tab switcher, link footer
    // bulan ini, dll). Sales (di-lock via session) tidak perlu ?branch=.
    $branches        = $branches ?? collect();
    $branchId        = $branchId ?? null;
    $activeBranch    = $activeBranch ?? null;
    $branchAccountMap = $branchAccountMap ?? [];
    $branchParam     = $branches->isNotEmpty() && $branchId ? ['branch' => $branchId] : [];
@endphp
<div class="container-fluid">

    {{-- ─── HEADER ─── --}}
    <div class="pc-page-header pc-page-header--v2">
        <div class="pc-page-header__main">
            <div class="pc-page-icon">
                <i class="fa fa-wallet"></i>
            </div>
            <div class="pc-page-header__heading">
                <h4 class="pc-page-title">{{ $tabLabel }}</h4>
                <div class="pc-page-meta">
                    <span class="pc-page-meta__item">
                        <i class="fa fa-calendar"></i> {{ $monthName }} {{ $year }}
                    </span>
                    @if($activeBranch)
                        <span class="pc-page-meta__divider"></span>
                        <span class="pc-branch-chip">
                            <i class="fa fa-building"></i> {{ $activeBranch->name }}
                        </span>
                    @endif
                    <span class="pc-page-meta__divider"></span>
                    @if($period->is_closed)
                        <span class="pc-status pc-status--closed">
                            <i class="fa fa-lock"></i> Ditutup
                        </span>
                    @elseif($isCurrentMonth)
                        <span class="pc-status pc-status--active">
                            <span class="pc-status__dot"></span> Bulan berjalan
                        </span>
                    @else
                        <span class="pc-status pc-status--past">
                            <i class="fa fa-history"></i> Lampau
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="pc-page-header__actions">
            @if(isSuperAdmin())
                <a href="{{ route('fundsource') }}"
                   class="st-btn-outline-brown"
                   data-pc-tooltip-pos="bottom"
                   data-pc-tooltip="Kelola daftar wallet: tambah/edit jenis Cash, Bank, dan E-Wallet (nama, logo, warna, nomor rekening). Hanya Super Admin.">
                    <i class="fa fa-cog"></i> Kelola Wallet
                </a>
                @if(! $period->is_closed && $branches->isNotEmpty())
                    <button type="button"
                            class="st-btn-outline-brown"
                            data-pc-modal="pcModalMovePeriod"
                            data-pc-tooltip-pos="bottom"
                            data-pc-tooltip="Pindahkan / tukar seluruh periode kas kecil (transaksi + saldo) ke cabang lain. Untuk membereskan salah cabang akibat rolling. Hanya Super Admin.">
                        <i class="fa fa-exchange-alt"></i> Pindah Cabang
                    </button>
                @endif
                {{-- Cut-over: buka kunci saldo awal supaya operator cabang bisa
                     mengisi ulang Cash & Bank dari hitung fisik mulai dari
                     papan kosong. Transaksi tidak disentuh. --}}
                @if(! $period->is_closed && $isCurrentMonth)
                    @if($openingWindowOpen)
                        {{-- Migrasi sedang berjalan — tinggal dihentikan. Wallet yang
                             sudah terlanjur dipensiunkan TIDAK ikut kembali; itu
                             keputusan terpisah lewat Kelola Wallet. --}}
                        <form method="POST" action="{{ route('pettycash.opening.reset', ['id' => $period->id]) }}"
                              style="margin:0;"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from(
                                  "Hentikan migrasi saldo?\n\nOperator cabang tidak lagi bisa mengisi saldo dari hitung fisik. Wallet yang sudah dipensiunkan tetap pensiun."
                              ) }})">
                            @csrf
                            @include('crud.pettycash._filter_hidden')
                            @if(!session('raw_branch_id') && $branchId)
                                <input type="hidden" name="branch" value="{{ $branchId }}">
                            @endif
                            <input type="hidden" name="mode" value="cancel">
                            <button type="submit"
                                    class="st-btn-outline-brown"
                                    data-pc-tooltip-pos="bottom"
                                    data-pc-tooltip="Hentikan migrasi yang sedang berjalan — operator cabang tak lagi bisa mengisi saldo.">
                                <i class="fa fa-times"></i> Hentikan Migrasi
                            </button>
                        </form>
                    @else
                        <button type="button"
                                class="st-btn-outline-brown"
                                data-pc-modal="pcModalMigrasi"
                                data-pc-tooltip-pos="bottom"
                                data-pc-tooltip="Pindahkan cabang ini ke daftar wallet baru: pilih wallet yang dipertahankan, saldo dikosongkan, lalu operator cabang mengisi dari hitung fisik. Cabang lain tidak terpengaruh. Hanya Super Admin.">
                            <i class="fa fa-file-import"></i> Migrasi Saldo
                        </button>
                    @endif
                @endif
            @endif
            <a href="{{ route('pettycash.export', array_filter([
                    'tab'            => $kind,
                    'branch'         => $branches->isNotEmpty() ? $branchId : null,
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'categories'     => $categories,
                    'q'              => $search,
                    'fund_source_id' => ($fundSourceId ?? 0) ?: null,
               ])) }}"
               class="st-btn-outline-brown st-btn-excel"
               data-pc-tooltip-pos="bottom"
               data-pc-tooltip="Download laporan transaksi periode aktif ke Excel. Semua filter aktif (tanggal, cabang, kategori, wallet, search) ikut ke file. Transfer internal di-skip karena bersifat netral.">
                <i class="fa fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    @include('layouts.notif')

    {{-- ─── JENDELA ISI ULANG SALDO AWAL (cut-over) ───
         Muncul setelah Super Admin membuka kuncinya. Ini satu-satunya jalan
         masuk operator cabang ke modal Setoran Awal saat periode sudah punya
         saldo berjalan — tombol bawaan di empty-state hanya muncul untuk
         cabang yang benar-benar belum punya data. --}}
    @if(($openingWindowOpen ?? false) && ! $isReadOnly)
        <div class="pc-opening-window">
            <span class="pc-opening-window__icon" aria-hidden="true"><i class="fa fa-wallet"></i></span>
            <div class="pc-opening-window__text">
                <strong>Saldo awal siap diisi ulang.</strong>
                Masukkan saldo <strong>Cash</strong> dan <strong>Bank</strong> hasil hitung fisik
                (uang di laci &amp; saldo rekening). Angka yang diisi akan persis sama dengan
                yang tampil di kartu wallet &mdash;
                <strong>catatan pengeluaran dan transaksi lain tidak terhapus</strong>.
            </div>
            <button type="button" class="pc-opening-window__btn" data-pc-modal="pcModalOpening">
                <i class="fa fa-pen"></i> Isi Saldo Awal
            </button>
        </div>
    @elseif(! empty($openingSetByName))
        {{-- Keterangan sekali lewat: hilang sendiri setelah 1 menit, dan hilang
             permanen (per browser) kalau ditutup manual lewat tombol ×. --}}
        <div class="pc-opening-window pc-opening-window--done"
             id="pcOpeningDoneBanner"
             data-pc-opening-done-key="pcOpeningDone:{{ $period->id }}"
             hidden>
            <span class="pc-opening-window__icon" aria-hidden="true"><i class="fa fa-check"></i></span>
            <div class="pc-opening-window__text">
                Saldo awal periode ini diisi ulang oleh <strong>{{ $openingSetByName }}</strong>
                @if($period->handover_at)
                    pada {{ \Carbon\Carbon::parse($period->handover_at)->translatedFormat('d M Y, H:i') }}
                @endif
                . Transaksi bulan ini tetap utuh.
            </div>
            <button type="button" class="pc-opening-window__close" data-pc-opening-done-close
                    title="Tutup — tidak ditampilkan lagi" aria-label="Tutup">&times;</button>
        </div>
        <script>
        (function () {
            var el = document.getElementById('pcOpeningDoneBanner');
            if (!el) return;
            var key = el.dataset.pcOpeningDoneKey;

            // Sudah pernah ditutup manual → jangan tampilkan lagi.
            try { if (localStorage.getItem(key) === '1') return; } catch (e) {}

            el.hidden = false;

            function sembunyikan(permanen) {
                if (permanen) { try { localStorage.setItem(key, '1'); } catch (e) {} }
                el.style.transition = 'opacity .3s';
                el.style.opacity = '0';
                setTimeout(function () { el.hidden = true; el.style.opacity = ''; }, 300);
            }

            el.querySelector('[data-pc-opening-done-close]')
              .addEventListener('click', function () { sembunyikan(true); });

            // Hilang sendiri setelah 1 menit (tidak permanen — muncul lagi
            // kalau halaman dibuka ulang, sampai ditutup manual).
            setTimeout(function () { if (!el.hidden) sembunyikan(false); }, 60000);
        })();
        </script>
    @endif

    @if(($openingWindowOpen ?? false) || ! empty($openingSetByName))
        <style>
            .pc-opening-window{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #d97706;color:#78350f;padding:12px 14px;border-radius:8px;margin:0 0 14px;font-size:13.5px;line-height:1.5;}
            .pc-opening-window__icon{flex-shrink:0;width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:#fef3c7;color:#b45309;}
            .pc-opening-window__text{flex:1;min-width:220px;}
            .pc-opening-window__btn{flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#d97706;border:1px solid #d97706;border-radius:8px;color:#fff;font-size:13px;font-weight:700;cursor:pointer;transition:background .15s;}
            .pc-opening-window__btn:hover{background:#b45309;}
            .pc-opening-window--done{background:#f0fdf4;border-color:#bbf7d0;border-left-color:#16a34a;color:#166534;}
            .pc-opening-window--done .pc-opening-window__icon{background:#dcfce7;color:#15803d;}
            .pc-opening-window__close{flex-shrink:0;width:28px;height:28px;border:none;background:transparent;color:inherit;opacity:.55;font-size:20px;line-height:1;border-radius:6px;cursor:pointer;transition:opacity .15s,background .15s;}
            .pc-opening-window__close:hover{opacity:1;background:rgba(0,0,0,.06);}
        </style>
    @endif

    {{-- ─── READ-ONLY / VIEW-ONLY BANNER ─── --}}
    @if($isReadOnly)
        @php $isFinanceViewOnly = $isFinanceViewOnly ?? false; @endphp
        <div class="pc-readonly-banner {{ $isFinanceViewOnly ? 'pc-readonly-banner--finance' : '' }}" role="alert">
            <i class="fa {{ $isFinanceViewOnly ? 'fa-eye' : 'fa-lock' }} pc-readonly-banner__icon"></i>
            <div class="pc-readonly-banner__body">
                @if($isFinanceViewOnly)
                    <strong>Mode View-Only — role Finance.</strong>
                    Anda hanya bisa melihat data Petty Cash cabang. Penambahan, edit, dan hapus
                    transaksi (Top Up, Pengeluaran, Pindah Saldo, Tutup Periode) dilakukan oleh
                    pemegang akun cabang atau admin.
                @elseif($period->is_closed)
                    <strong>Periode {{ $monthName }} {{ $year }} sudah ditutup.</strong>
                    Data hanya bisa dilihat — tidak dapat ditambah, diedit, atau dihapus.
                    Hubungi Admin General jika perlu buka kembali.
                @else
                    <strong>Anda sedang melihat periode lampau ({{ $monthName }} {{ $year }}).</strong>
                    Data hanya bisa dilihat — untuk catat transaksi baru, kembali ke bulan berjalan.
                @endif
            </div>
        </div>
        <style>
            .pc-readonly-banner{display:flex;align-items:flex-start;gap:10px;background:#fef3c7;border-left:4px solid #f59e0b;color:#78350f;padding:12px 16px;border-radius:6px;margin:0 0 16px;font-size:14px;line-height:1.5;}
            .pc-readonly-banner__icon{font-size:18px;margin-top:2px;color:#b45309;flex-shrink:0;}
            .pc-readonly-banner__body strong{color:#78350f;}
            /* Varian Finance: ganti palette ke biru (informational, bukan warning) */
            .pc-readonly-banner--finance{background:#dbeafe;border-left-color:#2563eb;color:#1e3a8a;}
            .pc-readonly-banner--finance .pc-readonly-banner__icon{color:#1d4ed8;}
            .pc-readonly-banner--finance .pc-readonly-banner__body strong{color:#1e3a8a;}
        </style>
    @endif

    {{-- ─── SUMMARY CARDS ─── --}}
    @include('crud.pettycash._summary_cards', [
        'summary'            => $summary,
        'rupiah'             => $rupiah,
        'sourceBalances'     => $sourceBalances ?? [],
        'isReadOnly'         => $isReadOnly,
        'kind'               => $kind,
        'branchId'           => $branchId,
        'pendingTopupCount'  => $pendingTopupCount ?? 0,
        'activeBranch'       => $activeBranch ?? null,
    ])

    {{-- ─── FILTER ─── --}}
    @include('crud.pettycash._filter', [
        'startDate'       => $startDate,
        'endDate'         => $endDate,
        'monthStartStr'   => $monthStartStr,
        'monthEndStr'     => $monthEndStr,
        'search'          => $search,
        'categories'      => $categories,
        'categoryOptions' => $categoryFilterOptions ?? $categoryOptions,
        'fundSources'     => $fundSources,
        'fundSourceId'    => $fundSourceId ?? 0,
        'isReadOnly'      => $isReadOnly,
        'kind'            => $kind,
        'branches'        => $branches,
        'branchId'        => $branchId,
        'year'            => $year,
        'month'           => $month,
        'bulanLabel'      => $bulanLabel,
    ])

    {{-- ─── STRIP FILTER KATEGORI AKTIF ───
         Muncul saat sebuah kategori difilter — bikin nominal asli kategori
         itu langsung kelihatan, tanpa ikut mengubah kartu saldo di atas. --}}
    @if(! empty($summary['filter_categories']))
        <div class="pc-filter-active">
            <span class="pc-filter-active__icon" aria-hidden="true">
                <i class="fa fa-filter"></i>
            </span>
            <div class="pc-filter-active__text">
                Menampilkan kategori
                <strong>{{ implode(', ', $summary['filter_categories_labels']) }}</strong>
                &middot; {{ (int) ($summary['filter_category_count'] ?? 0) }} transaksi
                &middot; Total <strong>{{ $rupiah($summary['filter_category_total'] ?? 0) }}</strong>
            </div>
            <a href="{{ route('pettycash', array_filter([
                    'tab'            => $kind,
                    'branch'         => $branches->isNotEmpty() ? $branchId : null,
                    'start_date'     => $startDate,
                    'end_date'       => $endDate,
                    'q'              => $search ?: null,
                    'fund_source_id' => ($fundSourceId ?? 0) ?: null,
                ])) }}" class="pc-filter-active__clear" title="Hapus filter kategori">
                <i class="fa fa-times"></i> Semua kategori
            </a>
        </div>
        <style>
            .pc-filter-active{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:#eff6ff;border:1px solid #bfdbfe;border-left:4px solid #2563eb;color:#1e3a8a;padding:10px 14px;border-radius:8px;margin:0 0 14px;font-size:13.5px;line-height:1.5;}
            .pc-filter-active__icon{flex-shrink:0;width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;background:#dbeafe;color:#1d4ed8;}
            .pc-filter-active__text{flex:1;min-width:200px;}
            .pc-filter-active__text strong{font-weight:700;}
            .pc-filter-active__clear{flex-shrink:0;display:inline-flex;align-items:center;gap:5px;padding:5px 12px;background:#fff;border:1px solid #cbd5e1;border-radius:999px;color:#334155;text-decoration:none;font-size:12.5px;font-weight:600;transition:background .15s,border-color .15s;}
            .pc-filter-active__clear:hover{background:#f1f5f9;border-color:#94a3b8;}
        </style>
    @endif

    {{-- ─── STRIP BARIS DI LUAR PERIODE ───
         Transaksi yang tanggalnya keluar dari bulan periode ini. Nominalnya
         SUDAH ikut mengurangi saldo & kartu ringkasan di atas, tapi dulu
         barisnya tersaring filter tanggal sehingga uangnya berkurang tanpa
         wujud di layar mana pun (di bulan tetangga juga tak muncul — beda
         period_id). Sekarang barisnya dipaksa tampil, dan strip ini
         menyebutkan jumlahnya supaya tidak perlu dicari satu-satu. --}}
    @if((int) ($summary['outside_period_count'] ?? 0) > 0)
        <div class="pc-outside-strip">
            <span class="pc-outside-strip__icon" aria-hidden="true">
                <i class="fa fa-exclamation-triangle"></i>
            </span>
            <div class="pc-outside-strip__text">
                Ada <strong>{{ (int) $summary['outside_period_count'] }} transaksi</strong>
                yang tanggalnya di luar {{ $bulanLabel[$month] ?? '' }} {{ $year }}
                &middot; Selisih <strong>{{ $rupiah(abs((float) ($summary['outside_period_total'] ?? 0))) }}</strong>.
                Nominalnya <strong>sudah</strong> mengurangi saldo bulan ini.
                Barisnya ditandai <em>luar periode</em> di tabel — perbaiki dengan hapus lalu catat ulang
                bertanggal bulan ini.
            </div>
        </div>
        <style>
            .pc-outside-strip{display:flex;align-items:flex-start;gap:12px;background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #d97706;color:#78350f;padding:10px 14px;border-radius:8px;margin:0 0 14px;font-size:13.5px;line-height:1.55;}
            .pc-outside-strip__icon{flex-shrink:0;width:28px;height:28px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;background:#fef3c7;color:#b45309;}
            .pc-outside-strip__text{flex:1;min-width:200px;}
            .pc-outside-strip__text strong{font-weight:700;}
            /* Penanda per baris di tabel. Ditaruh di baris kedua sel tanggal
               supaya kolom tanggal (white-space:nowrap) tidak melebar. */
            .pc-outside-badge{display:block;margin-top:3px;font-size:11px;font-weight:700;color:#b45309;background:#fef3c7;border:1px solid #fde68a;border-radius:999px;padding:1px 8px;white-space:nowrap;}
            .pc-row-outside{background:#fffdf5;}
        </style>
    @endif

    {{-- ─── TABLE — split jadi 2 sub-tab ─── --}}
    @php
        // Klasifikasi "setor cash langsung": cash IN tapi BUKAN top up &
        // BUKAN transfer. LEGACY — fiturnya sudah dihapus, baris seperti ini
        // tidak bisa dibuat lagi; klasifikasinya dipertahankan supaya baris
        // lama tetap mendarat di tab kanan, bukan tiba-tiba pindah ke tab
        // Pengeluaran.
        $isSetorCash = function ($r) {
            return ! $r->is_transfer && ! $r->is_topup && (float) $r->cash_in > 0;
        };

        // Pisahkan transaksi:
        //   - regular  → top up + pengeluaran (bukan transfer, bukan setor cash)
        //   - transfer → composite transfer/deposit/tarik tunai + setor cash langsung
        $rowsRegular  = collect($rows)
            ->reject(fn($r) => $r->is_transfer || $isSetorCash($r))
            ->values();
        $rowsTransfer = collect($rows)
            ->filter(fn($r) => $r->is_transfer || $isSetorCash($r))
            ->values();

        // Footer "Total" tab Pengeluaran: kalau ada filter aktif (kategori,
        // cari, wallet, atau rentang tanggal dipersempit), total cash in/out
        // harus mengikuti baris yang tampil — bukan total sebulan penuh
        // ($summary) — supaya angka footer tidak menyesatkan. Saldo tetap
        // saldo akhir sesungguhnya (posisi kas terkini, apa pun filternya).
        $pcFilterActive = ! empty($categories)
            || $search !== ''
            || (int) ($fundSourceId ?? 0) > 0
            || $startDate !== $monthStartStr
            || $endDate   !== $monthEndStr;
        $regFootIn    = $pcFilterActive ? $rowsRegular->sum('cash_in')  : $summary['total_in'];
        $regFootOut   = $pcFilterActive ? $rowsRegular->sum('cash_out') : $summary['total_out'];
        $regFootLabel = $pcFilterActive ? 'Total hasil filter' : 'Total bulan ini';

        $tableHasAnySource = collect($sourceBalances ?? [])->contains(function ($sb) {
            return abs((float) ($sb->balance ?? 0)) > 0.005
                || abs((float) ($sb->in ?? 0))      > 0.005
                || abs((float) ($sb->out ?? 0))     > 0.005;
        });

        $emptyDescRegular  = 'Tab ini menampilkan <strong>Top Up</strong> (dana masuk) dan <strong>Pengeluaran</strong> dari wallet, lengkap dengan tanggal, wallet, dan nominal.';
        $emptyDescTransfer = 'Tab ini menampilkan <strong>Pindah Saldo</strong> &mdash; perpindahan uang antar wallet. Total saldo periode tidak berubah.';

        // CTA box (info + tombol) di empty state tabel. Berbeda per tab &
        // per state (apakah sudah ada wallet aktif atau belum).
        $emptyCtaRegular  = null;
        $emptyCtaTransfer = null;
        if (! $isReadOnly) {
            if (! $tableHasAnySource) {
                // Belum ada wallet — arahkan ke setoran awal untuk kedua tab.
                $emptyCtaRegular = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Belum ada wallet aktif &mdash; mulai dari setoran awal.</span>'
                    . '<button type="button" class="st-btn-brand" data-pc-modal="pcModalOpening">Mulai Setoran Awal</button>';
                $emptyCtaTransfer = $emptyCtaRegular;
            } else {
                // Sudah ada wallet — arahkan ke aksi yg relevan per tab.
                $emptyCtaRegular = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Catat top up atau pengeluaran dengan klik kartu wallet di atas.</span>';
                $emptyCtaTransfer = '<i class="fa fa-info-circle pc-empty-state__cta-icon"></i>'
                    . '<span class="pc-empty-state__cta-text">Catat Pindah Saldo dengan klik kartu wallet di atas.</span>';
            }
        }
    @endphp

    <div class="st-card mt-3 pc-table-card pc-trx-card" data-active-trx-tab="regular">
        {{-- Sub-tabs --}}
        <div class="pc-trx-subtabs" role="tablist">
            <button type="button"
                    class="pc-trx-subtab is-active"
                    data-trx-tab="regular"
                    role="tab"
                    aria-selected="true">
                <i class="fa fa-random"></i>
                Pengeluaran
                <span class="pc-trx-subtab__count">{{ $rowsRegular->count() }}</span>
            </button>
            <button type="button"
                    class="pc-trx-subtab"
                    data-trx-tab="transfer"
                    role="tab"
                    aria-selected="false">
                <i class="fa fa-exchange-alt"></i>
                Pindah Saldo
                <span class="pc-trx-subtab__count">{{ $rowsTransfer->count() }}</span>
            </button>
            <button type="button"
                    class="pc-trx-subtab"
                    data-trx-tab="riwayat"
                    role="tab"
                    aria-selected="false"
                    title="Jejak transaksi yang diedit atau dihapus">
                <i class="fa fa-history"></i>
                Riwayat
                <span class="pc-trx-subtab__count">{{ $rowsRiwayat->count() }}</span>
            </button>
        </div>

        {{-- TAB 1: Top Up & Pengeluaran --}}
        <div class="st-card__body pc-trx-pane" data-trx-content="regular" style="padding:0;">
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="140">Wallet</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Cash in</th>
                            <th class="text-right" width="130">Cash out</th>
                            <th class="text-right" width="150">Saldo</th>
                            @if($showTrxActions)
                                <th width="80"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('crud.pettycash._table_rows', [
                            'rowsInput'  => $rowsRegular,
                            'isReadOnly' => $isReadOnly,
                            'showTrxActions' => $showTrxActions,
                            'kind'       => $kind,
                            'branchId'   => $branchId,
                            'tabKind'    => 'regular',
                            'recorderNames' => $recorderNames,
                            'showRecorder'  => $showRecorder,
                            'emptyTitle' => 'Belum ada Top Up atau Pengeluaran bulan ini',
                            'emptyDesc'  => $emptyDescRegular,
                            'emptyIcon'  => 'fa-file-alt',
                            'emptyCta'   => $emptyCtaRegular,
                        ])
                    </tbody>
                    @if($rowsRegular->count() > 0)
                        <tfoot>
                            <tr class="pc-row-total">
                                <td colspan="3"><strong>{{ $regFootLabel }}</strong></td>
                                <td class="text-right pc-cash-in">{{ number_format($regFootIn, 0, ',', '.') }}</td>
                                <td class="text-right pc-cash-out">{{ number_format($regFootOut, 0, ',', '.') }}</td>
                                <td class="text-right pc-saldo-cell">
                                    <strong>{{ number_format($summary['closing'], 0, ',', '.') }}</strong>
                                    @if($pcFilterActive)
                                        <span class="pc-foot-hint">saldo akhir</span>
                                    @endif
                                </td>
                                @if($showTrxActions)<td></td>@endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        {{-- TAB 2: Pindah Saldo antar wallet --}}
        <div class="st-card__body pc-trx-pane" data-trx-content="transfer" style="padding:0;" hidden>
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="180">Dari → Ke</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Cash in</th>
                            <th class="text-right" width="130">Cash out</th>
                            <th class="text-right" width="150">Saldo</th>
                            @if($showTrxActions)
                                <th width="80"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @include('crud.pettycash._table_rows', [
                            'rowsInput'  => $rowsTransfer,
                            'isReadOnly' => $isReadOnly,
                            'showTrxActions' => $showTrxActions,
                            'kind'       => $kind,
                            'branchId'   => $branchId,
                            'tabKind'    => 'transfer',
                            'recorderNames' => $recorderNames,
                            'showRecorder'  => $showRecorder,
                            'emptyTitle' => 'Belum ada Pindah Saldo bulan ini',
                            'emptyDesc'  => $emptyDescTransfer,
                            'emptyIcon'  => 'fa-exchange-alt',
                            'emptyCta'   => $emptyCtaTransfer,
                        ])
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 3: Riwayat — jejak transaksi yang diedit / dihapus --}}
        <div class="st-card__body pc-trx-pane" data-trx-content="riwayat" style="padding:0;" hidden>
            <div class="st-table-wrap--scroll">
                <table class="st-table pc-table">
                    <thead>
                        <tr>
                            <th width="110">Tanggal</th>
                            <th width="140">Wallet</th>
                            <th>Keterangan</th>
                            <th class="text-right" width="130">Nominal</th>
                            <th width="110">Aksi</th>
                            <th width="150">Oleh</th>
                            <th width="150">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rowsRiwayat as $rw)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($rw->trx_date)->translatedFormat('d M Y') }}</td>
                                <td>{{ $rw->source_name ?? '—' }}</td>
                                <td>{{ $rw->description }}</td>
                                <td class="text-right">{{ number_format($rw->amount, 0, ',', '.') }}</td>
                                <td>
                                    @if($rw->action === 'deleted')
                                        <span class="pc-badge" style="background:#ffebee;color:#b71c1c;border:1px solid #ffcdd2;">
                                            <i class="fa fa-trash"></i> Dihapus
                                        </span>
                                    @else
                                        <span class="pc-badge" style="background:#fff4e0;color:#b9770e;border:1px solid #f6dca6;">
                                            <i class="fa fa-pen"></i> Diedit
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $rw->actor }}</td>
                                <td>{{ $rw->at ? \Carbon\Carbon::parse($rw->at)->translatedFormat('d M Y H:i') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="pc-empty-state">
                                        <i class="fa fa-history pc-empty-state__icon"></i>
                                        <div class="pc-empty-state__title">Belum ada riwayat perubahan</div>
                                        <div class="pc-empty-state__desc">Transaksi yang <strong>diedit</strong> atau <strong>dihapus</strong> bulan ini akan muncul di sini beserta siapa &amp; kapan.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ─── Lightbox preview untuk bukti pengeluaran ───
     Dipakai saat user klik icon paperclip di tabel. Support gambar
     (jpg/png/webp) tampil inline + PDF tampil via <iframe>. --}}
<div class="pc-lightbox" id="pcAttachLightbox" role="dialog" aria-modal="true" hidden>
    <div class="pc-lightbox__backdrop" data-pc-lightbox-close></div>
    <div class="pc-lightbox__dialog">
        <div class="pc-lightbox__header">
            <div class="pc-lightbox__title">
                <i class="fa fa-paperclip"></i> Bukti Pengeluaran
            </div>
            <div class="pc-lightbox__actions">
                <a href="#" class="pc-lightbox__btn" id="pcAttachDownload" download
                   title="Download file">
                    <i class="fa fa-download"></i>
                </a>
                <a href="#" class="pc-lightbox__btn" id="pcAttachOpenTab"
                   target="_blank" rel="noopener"
                   title="Buka di tab baru">
                    <i class="fa fa-external-link-alt"></i>
                </a>
                <button type="button" class="pc-lightbox__btn pc-lightbox__btn--close"
                        data-pc-lightbox-close
                        title="Tutup (Esc)">
                    <i class="fa fa-times"></i>
                </button>
            </div>
        </div>
        <div class="pc-lightbox__body" id="pcAttachBody">
            <!-- isi dinamis via JS: <img> atau <iframe> -->
        </div>
    </div>
</div>

{{-- Modal "Pindah Cabang" (Super Admin) — kondisi sama dgn tombol pemicu di
     header supaya tombol tak pernah menunjuk modal yang tak ter-render. --}}
@if(isSuperAdmin() && ! $period->is_closed && $branches->isNotEmpty())
    @include('crud.pettycash.modal-move-period', [
        'period'       => $period,
        'kind'         => $kind,
        'branches'     => $branches,
        'branchId'     => $branchId,
        'activeBranch' => $activeBranch ?? null,
        'monthName'    => $monthName,
        'year'         => $year,
    ])
@endif

{{-- Modal "Migrasi Saldo" (Super Admin) — kondisinya dijaga sama persis
     dengan tombol pemicunya di header. --}}
@if(isSuperAdmin() && ! $period->is_closed && $isCurrentMonth && ! ($openingWindowOpen ?? false))
    @include('crud.pettycash.modal-migrasi-saldo', [
        'period'             => $period,
        'kind'               => $kind,
        'branchId'           => $branchId,
        'activeBranch'       => $activeBranch ?? null,
        'monthName'          => $monthName,
        'year'               => $year,
        'fundSourcesGrouped' => $fundSourcesGrouped,
        'sourceBalances'     => $sourceBalances,
    ])
@endif

@if(!$isReadOnly)
    @include('crud.pettycash.modal-transfer', [
        'period'             => $period,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
        'branchAccountMap'   => $branchAccountMap,
        'sourceBalances'     => $sourceBalances ?? [],
    ])
    @include('crud.pettycash.modal-opening', [
        'period'             => $period,
        'kind'               => $kind,
        'fundSourcesGrouped' => $fundSourcesGrouped,
        'branchAccountMap'   => $branchAccountMap ?? [],
    ])
    @include('crud.pettycash.modal-edit-expense', [
        'period' => $period,
        'kind'   => $kind,
    ])

    {{-- Modal Ajukan Top Up — Sales selalu, admin saat buka cabang spesifik --}}
    @if($branchId && !$isReadOnly)
        @include('crud.pettycash._modal_request_topup', [
            'period'             => $period,
            'fundSourcesGrouped' => $fundSourcesGrouped,
            'branchId'           => $branchId,
            'activeBranch'       => $activeBranch,
            'branchAccountMap'   => $branchAccountMap,
            'requesterName'      => $requesterName,
            'sourceBalances'     => $sourceBalances,
        ])
    @endif

    {{-- Modal Rincian Pengajuan Menunggu — dibuka dari badge "N menunggu".
         Tampil kapan pun ada pengajuan pending untuk cabang aktif. --}}
    @if($branchId && ($pendingTopupCount ?? 0) > 0)
        @include('crud.pettycash._modal_pending_topups', [
            'pendingTopups' => $pendingTopups ?? collect(),
            'rupiah'        => $rupiah,
            'activeBranch'  => $activeBranch,
        ])
    @endif
@endif
@endsection

@section('js')
<script>
(function () {
    // Format Rupiah otomatis di input amount (thousand separator).
    document.querySelectorAll('.pc-amount-input').forEach(function (el) {
        const hidden = document.getElementById(el.dataset.target);

        function format() {
            const raw = el.value.replace(/\D/g, '');
            if (!raw) { el.value = ''; if (hidden) hidden.value = ''; return; }
            el.value = parseInt(raw, 10).toLocaleString('id-ID');
            if (hidden) hidden.value = raw;
        }
        el.addEventListener('input', format);
        format();
    });

    // Modal trigger
    document.querySelectorAll('[data-pc-modal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.dataset.pcModal;
            const modal = document.getElementById(id);
            if (modal) modal.classList.add('is-open');
        });
    });
    document.querySelectorAll('[data-pc-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.pc-modal').classList.remove('is-open');
        });
    });
    document.querySelectorAll('.pc-modal').forEach(function (m) {
        m.addEventListener('click', function (e) {
            if (e.target === m) m.classList.remove('is-open');
        });
    });

    // Klik card sumber dana → buka modal "Kelola Saldo Sumber Dana"
    // dengan source asal ter-populate. Modal punya 2 tab: Transfer dan
    // Top up.
    const transferModal = document.getElementById('pcModalTransfer');

    function switchModalTab(target) {
        if (!transferModal) return;
        // Refuse to switch to a disabled tab
        const targetBtn = transferModal.querySelector('[data-tab-target="' + target + '"]');
        if (targetBtn && targetBtn.disabled) return;
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            const active = btn.dataset.tabTarget === target;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        transferModal.querySelectorAll('[data-tab-content]').forEach(function (form) {
            form.hidden = form.dataset.tabContent !== target;
        });
    }

    function openSourceModalFromCard(card) {
        if (!transferModal) return;
        const id       = card.dataset.sourceId;
        const name     = card.dataset.sourceName;
        const balance  = parseFloat(card.dataset.sourceBalance || '0');
        const color    = card.dataset.sourceColor || '#64748b';
        const short    = card.dataset.sourceShort || '?';
        const logo     = card.dataset.sourceLogo || '';
        const category = card.dataset.sourceCategory || '';
        const isCash   = category === 'cash';
        if (!id) return;
        // Tolerance 0.005 untuk handle floating-point edge case
        const hasSaldo = balance >= 0.005;
        const fmt = function (n) { return Math.round(n).toLocaleString('id-ID'); };

        // Source preview (shared antar tab)
        document.getElementById('transferFromName').textContent = name;
        document.getElementById('transferFromBalance').textContent = 'Rp ' + fmt(balance);
        const badge = document.getElementById('transferFromBadge');
        badge.style.background = color;
        badge.innerHTML = logo
            ? '<img src="' + logo + '" alt="" class="fs-badge__img">'
            : '<span class="fs-badge__text">' + short + '</span>';
        badge.dataset.fromName = name;

        // Form Transfer: set from_source, filter dropdown destination, reset amount
        document.getElementById('transferFromSourceId').value = id;
        // Danger zone: populate source id untuk form "Hapus Data Sumber Dana Ini".
        const destroyIdInput = document.getElementById('pcSourceDestroyId');
        if (destroyIdInput) destroyIdInput.value = id;
        document.getElementById('transferMaxHint').textContent = fmt(balance);
        const sel = document.getElementById('transferToSelect');
        sel.value = '';
        // Sembunyikan wallet asal saja — semua wallet lain boleh jadi tujuan.
        // Dulu Cash ikut disembunyikan untuk wallet non-Cash karena penarikan
        // ke Cash punya tab sendiri. Tab itu sudah dilebur ke Pindah Saldo,
        // jadi aturannya dicabut — kalau tidak, Bank ke Cash jadi mustahil.
        // Pakai kombinasi hidden + display:none supaya konsisten lintas browser.
        Array.from(sel.options).forEach(function (opt) {
            const shouldHide = (opt.value === id);
            opt.hidden = shouldHide;
            opt.disabled = shouldHide;
            opt.style.display = shouldHide ? 'none' : '';
        });
        // Sembunyikan optgroup yang semua opsinya hidden — beberapa browser
        // tetap render label optgroup kosong, biar bersih.
        Array.from(sel.querySelectorAll('optgroup')).forEach(function (og) {
            const visible = Array.from(og.children).some(function (o) {
                return !o.hidden;
            });
            og.hidden = !visible;
            og.style.display = visible ? '' : 'none';
        });

        // Kalau tujuan yang tersisa cuma SATU — kondisi cabang yang sudah
        // dimigrasi (Cash + satu bank) — tidak ada yang perlu dipilih, jadi
        // langsung dipilihkan. Aturannya berdasar JUMLAH, bukan nama bank:
        // cabang yang memakai BSI dapat perilaku sama tanpa satu pun nama
        // bank tertulis di kode. Placeholder "Pilih wallet tujuan…" ikut
        // disembunyikan — menawarkan pilihan yang tidak ada itu menyesatkan.
        const kandidatTujuan = Array.from(sel.options).filter(function (o) {
            return o.value && !o.hidden && !o.disabled;
        });
        const jumlahTujuan = kandidatTujuan.length;
        const placeholderOpt = Array.from(sel.options).find(function (o) { return !o.value; });
        if (placeholderOpt) {
            placeholderOpt.hidden   = jumlahTujuan === 1;
            placeholderOpt.disabled = jumlahTujuan === 1;
        }
        sel.value = jumlahTujuan === 1 ? kandidatTujuan[0].value : '';

        // Dispatch SESUDAH value diisi supaya preview wallet tujuan & baris
        // nomor rekening ikut terisi sendiri.
        sel.dispatchEvent(new Event('change'));
        const transferAmtVisible = document.querySelector('[data-target="transferAmount"]');
        const transferAmtHidden  = document.getElementById('transferAmount');
        if (transferAmtVisible) {
            transferAmtVisible.value = '';
            transferAmtVisible.dataset.maxAmount = String(balance);
        }
        if (transferAmtHidden) transferAmtHidden.value = '';

        // Pre-set isBank/isEwallet untuk inline edit nomor rek di preview header.
        const acctValue = card.dataset.sourceAccount || '';
        const isBank    = category === 'bank';
        const isEwallet = category === 'ewallet';

        // From-source preview: tampilkan nomor + copy button kalau ada.
        // Kalau no rek/HP belum diisi & source = Bank/E-Wallet, tampilkan
        // inline add form supaya user bisa lengkapi tanpa lewat form top up.
        const fromAcctWrap   = document.getElementById('transferFromAccount');
        const fromAcctLabel  = document.getElementById('transferFromAccountLabel');
        const fromAcctValEl  = document.getElementById('transferFromAccountValue');
        const fromAcctCopy   = document.getElementById('transferFromAccountCopy');
        const fromAcctAdd    = document.getElementById('transferFromAccountAdd');
        const fromAcctAddBtn = document.getElementById('transferFromAccountAddBtn');
        const fromAcctAddLbl = document.getElementById('transferFromAccountAddLabel');
        const fromAcctAddForm  = document.getElementById('transferFromAccountAddForm');
        const fromAcctAddInput = document.getElementById('transferFromAccountAddInput');
        const fromAcctAddError = document.getElementById('transferFromAccountAddError');
        if (fromAcctWrap && fromAcctLabel && fromAcctValEl && fromAcctCopy) {
            if (acctValue && (isBank || isEwallet)) {
                fromAcctWrap.hidden = false;
                fromAcctLabel.textContent = isBank ? 'No. Rek' : 'No. HP';
                fromAcctValEl.textContent = acctValue;
                fromAcctCopy.dataset.pcCopy = acctValue;
                if (fromAcctAdd) fromAcctAdd.hidden = true;
            } else if (!acctValue && (isBank || isEwallet)) {
                fromAcctWrap.hidden = true;
                if (fromAcctAdd && fromAcctAddBtn && fromAcctAddLbl && fromAcctAddForm && fromAcctAddInput) {
                    fromAcctAdd.hidden = false;
                    fromAcctAddLbl.textContent = isBank ? 'No. Rek' : 'No. HP';
                    fromAcctAddInput.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                    fromAcctAddInput.value = '';
                    fromAcctAddForm.hidden = true;
                    fromAcctAddBtn.hidden = false;
                    if (fromAcctAddError) { fromAcctAddError.hidden = true; fromAcctAddError.textContent = ''; }
                    // Simpan source id untuk submit handler
                    fromAcctAdd.dataset.sourceId = id;
                }
            } else {
                fromAcctWrap.hidden = true;
                if (fromAcctAdd) fromAcctAdd.hidden = true;
            }
        }

        // Form Pengeluaran: lock fund_source_id, set max = saldo source,
        // reset amount.
        document.getElementById('expenseSourceFundSourceId').value = id;
        document.getElementById('expenseSourceMaxHint').textContent = fmt(balance);
        const expAmtVisible = document.querySelector('[data-target="expenseSourceAmount"]');
        const expAmtHidden  = document.getElementById('expenseSourceAmount');
        if (expAmtVisible) {
            expAmtVisible.value = '';
            expAmtVisible.dataset.maxAmount = String(balance);
        }
        if (expAmtHidden) expAmtHidden.value = '';

        // Reset description di semua tab form
        transferModal.querySelectorAll('[data-tab-content] input[name="description"]').forEach(function (el) {
            el.value = '';
        });

        // Aturan tab: Pindah Saldo & Pengeluaran sama-sama butuh saldo > 0
        // (tidak bisa memindahkan / membelanjakan wallet kosong). Pindah Saldo
        // punya syarat tambahan: harus ADA wallet tujuan. Cabang yang tinggal
        // satu wallet tidak punya ke mana — dulu modalnya tetap terbuka dengan
        // dropdown kosong tanpa penjelasan.
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            const target    = btn.dataset.tabTarget;
            const takAdaKe  = target === 'transfer' && jumlahTujuan === 0;
            const disabled  = !hasSaldo || takAdaKe;
            btn.disabled = disabled;
            btn.classList.toggle('is-disabled', disabled);
            btn.title = ! disabled
                ? ''
                : (takAdaKe
                    ? 'Tidak bisa pindah saldo — cabang ini tidak punya wallet lain'
                    : 'Tidak bisa ' + (target === 'transfer' ? 'pindah saldo' : 'pengeluaran') + ' — saldo kosong');
        });

        // Pilih tab pertama yang available; Pindah Saldo jadi default.
        for (const t of ['transfer', 'expense']) {
            const btn = transferModal.querySelector('[data-tab-target="' + t + '"]');
            if (btn && !btn.disabled) {
                switchModalTab(t);
                break;
            }
        }
        transferModal.classList.add('is-open');
    }

    document.querySelectorAll('[data-pc-source-card]').forEach(function (card) {
        card.addEventListener('click', function () { openSourceModalFromCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openSourceModalFromCard(card);
            }
        });
    });

    // Tab switcher buttons
    if (transferModal) {
        transferModal.querySelectorAll('[data-tab-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchModalTab(btn.dataset.tabTarget);
            });
        });
    }

    // Cap nominal ke saldo max (transfer & pengeluaran)
    function attachAmountCap(visibleSelector, hiddenId) {
        if (!transferModal) return;
        const visible = transferModal.querySelector(visibleSelector);
        if (!visible) return;
        visible.addEventListener('input', function () {
            const max = parseFloat(this.dataset.maxAmount || '0');
            const raw = parseInt(this.value.replace(/\D/g, ''), 10);
            if (max > 0 && raw > max) {
                this.value = max.toLocaleString('id-ID');
                const hidden = document.getElementById(hiddenId);
                if (hidden) hidden.value = String(max);
            }
        });
    }
    attachAmountCap('[data-target="transferAmount"]',      'transferAmount');
    attachAmountCap('[data-target="expenseSourceAmount"]', 'expenseSourceAmount');

    // Lightbox preview untuk icon attachment di tabel transaksi.
    // Klik [data-pc-attach-url] → tampilkan file dalam modal:
    //   - Gambar (jpg/png/webp) → <img>
    //   - PDF → <iframe>
    // Close via tombol X / klik backdrop / tombol Escape.
    (function () {
        const lb       = document.getElementById('pcAttachLightbox');
        const body     = document.getElementById('pcAttachBody');
        const dlBtn    = document.getElementById('pcAttachDownload');
        const tabBtn   = document.getElementById('pcAttachOpenTab');
        if (!lb || !body) return;

        const IMG_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        function open(url, ext) {
            ext = (ext || '').toLowerCase();
            const isImg = IMG_EXTS.indexOf(ext) !== -1;
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

        // Delegasi klik untuk tombol attachment (di tabel — bisa banyak)
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-pc-attach-url]');
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

    // Upload bukti (pengeluaran, top up, transfer): preview file yg dipilih,
    // format size, tombol remove untuk reset input. Validasi tipe & size di
    // server-side juga. Looping semua `.pc-attach-zone` di halaman supaya
    // expense + top up + transfer dapat handler sendiri-sendiri.
    (function () {
        function formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1024 / 1024).toFixed(2) + ' MB';
        }

        document.querySelectorAll('.pc-attach-zone').forEach(function (zone) {
            const input     = zone.querySelector('.pc-attach-input');
            const label     = zone.querySelector('.pc-attach-label');
            const preview   = zone.querySelector('.pc-attach-preview');
            const thumb     = zone.querySelector('.pc-attach-preview__thumb');
            const nameEl    = zone.querySelector('.pc-attach-preview__name');
            const sizeEl    = zone.querySelector('.pc-attach-preview__size');
            const removeBtn = zone.querySelector('.pc-attach-preview__remove');
            if (!input || !label || !preview) return;

            input.addEventListener('change', function () {
                const file = input.files && input.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert('Ukuran file melebihi 5 MB. Pilih file lain.');
                    input.value = '';
                    return;
                }
                if (nameEl) nameEl.textContent = file.name;
                if (sizeEl) sizeEl.textContent = formatSize(file.size);
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        thumb.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
                    };
                    reader.readAsDataURL(file);
                } else {
                    thumb.innerHTML = '<i class="fa fa-file-pdf"></i>';
                }
                label.hidden = true;
                preview.hidden = false;
            });

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    input.value = '';
                    preview.hidden = true;
                    label.hidden = false;
                    thumb.innerHTML = '<i class="fa fa-file"></i>';
                });
            }
        });
    })();

    // Edit Pengeluaran (modal-edit-expense): klik tombol pensil di table
    // row → prefill form dari data-trx-* attributes & set form action.
    // Window 24 jam sudah di-enforce di server-side; tombol cuma muncul
    // kalau row masih dalam window.
    (function () {
        const modal       = document.getElementById('pcModalEditExpense');
        const form        = document.getElementById('pcEditExpenseForm');
        if (!modal || !form) return;
        const dateLabel   = modal.querySelector('#pcEditTrxDate');
        const walletLabel = modal.querySelector('#pcEditWallet');
        const categorySel = modal.querySelector('#pcEditCategory');
        const amountVis   = modal.querySelector('#pcEditAmountVisible');
        const amountHid   = modal.querySelector('#pcEditAmount');
        const maxHint     = modal.querySelector('#pcEditAmountMaxHint');
        const descInput   = modal.querySelector('#pcEditDescription');
        const actionTpl   = form.getAttribute('data-action-tpl') || '';
        const submitBtn   = form.querySelector('button[type="submit"]');
        // Hitung mundur sisa waktu edit (window 8 jam). Hanya untuk user biasa;
        // Super Admin tak punya data-trx-edit-deadline → elemen tetap hidden.
        const countdownEl  = modal.querySelector('#pcEditCountdown');
        const countdownVal = modal.querySelector('#pcEditCountdownValue');
        let countdownTimer = null;
        // Elemen bukti (opsional).
        const attachCurrent = modal.querySelector('#pcEditAttachCurrent');
        const attachLink    = modal.querySelector('#pcEditAttachLink');
        const attachRemove  = modal.querySelector('#pcEditAttachRemove');
        const attachInput   = modal.querySelector('#editAttachInput');
        const attachZone    = modal.querySelector('#editAttachZone');

        // Reset zona upload bukti ke kondisi awal (dipanggil tiap buka modal).
        function resetAttachZone() {
            if (attachInput) attachInput.value = '';
            if (attachRemove) attachRemove.checked = false;
            if (attachZone) {
                const label   = attachZone.querySelector('.pc-attach-label');
                const preview = attachZone.querySelector('.pc-attach-preview');
                const thumb   = attachZone.querySelector('.pc-attach-preview__thumb');
                if (label)   label.hidden = false;
                if (preview) preview.hidden = true;
                if (thumb)   thumb.innerHTML = '<i class="fa fa-file"></i>';
            }
        }

        // Format Rp dengan thousand separator.
        function fmt(n) {
            return Number(n || 0).toLocaleString('id-ID');
        }

        // Lookup balance wallet berdasarkan source-id (dari kartu wallet
        // di atas — pc-source-card punya data-source-id & data-source-balance).
        function lookupWalletBalance(sourceId) {
            if (!sourceId) return 0;
            const card = document.querySelector(
                '.pc-source-card[data-source-id="' + sourceId + '"]'
            );
            return card ? parseFloat(card.dataset.sourceBalance || '0') : 0;
        }

        // Format sisa detik → teks "Xj Ym" (>1 jam) atau "Ym Zd" (<1 jam).
        function fmtRemaining(secs) {
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = Math.floor(secs % 60);
            if (h > 0) return h + 'j ' + m + 'm';
            if (m > 0) return m + 'm ' + s + 'd';
            return s + 'd';
        }

        function stopCountdown() {
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        }

        // Mulai hitung mundur ke deadline (epoch detik). Update tiap detik,
        // ganti warna saat mendekati batas, dan kunci tombol simpan bila habis.
        function startCountdown(deadlineTs) {
            stopCountdown();
            if (!countdownEl) return;
            if (!deadlineTs) { countdownEl.hidden = true; return; }
            countdownEl.hidden = false;

            function tick() {
                const remaining = deadlineTs - Math.floor(Date.now() / 1000);
                countdownEl.classList.remove('is-warning', 'is-danger');
                if (remaining <= 0) {
                    if (countdownVal) countdownVal.textContent = 'waktu edit sudah habis';
                    countdownEl.classList.add('is-danger');
                    if (submitBtn) submitBtn.disabled = true;
                    stopCountdown();
                    return;
                }
                if (countdownVal) countdownVal.textContent = fmtRemaining(remaining);
                if (remaining <= 900)       countdownEl.classList.add('is-danger');  // ≤15 menit
                else if (remaining <= 3600) countdownEl.classList.add('is-warning'); // ≤1 jam
                if (submitBtn) submitBtn.disabled = false;
            }
            tick();
            countdownTimer = setInterval(tick, 1000);
        }

        // Hentikan timer saat modal ditutup (hemat resource).
        modal.querySelectorAll('[data-pc-modal-close]').forEach(function (btn) {
            btn.addEventListener('click', stopCountdown);
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-pc-edit-trx]');
            if (!btn) return;
            e.preventDefault();

            const trxId         = btn.dataset.trxId || '';
            const trxDate       = btn.dataset.trxDate || '';
            const trxAmount     = parseFloat(btn.dataset.trxAmount || '0');
            const trxDesc       = btn.dataset.trxDescription || '';
            const trxCategory   = btn.dataset.trxCategory || '';
            const trxSourceName = btn.dataset.trxSourceName || '—';
            const trxSourceId   = btn.dataset.trxSourceId || '';
            const trxAttachment = btn.dataset.trxAttachment || '';
            const trxDeadline   = parseInt(btn.dataset.trxEditDeadline || '0', 10) || 0;

            // Set form action: replace __ID__ placeholder dengan id transaksi.
            form.action = actionTpl.replace('__ID__', trxId);

            // Bukti saat ini: tampilkan link kalau ada, reset zona upload.
            resetAttachZone();
            if (attachCurrent) {
                if (trxAttachment) {
                    if (attachLink) attachLink.href = trxAttachment;
                    attachCurrent.hidden = false;
                } else {
                    attachCurrent.hidden = true;
                }
            }

            // Prefill display fields.
            dateLabel.textContent   = trxDate;
            walletLabel.textContent = trxSourceName;
            // Kategori pensiunan (nonaktif) tak ada di dropdown. Kalau baris
            // ini masih memakainya, sisipkan opsinya khusus untuk baris ini —
            // jangan sampai edit nominal diam-diam mengganti kategorinya.
            if (trxCategory && !categorySel.querySelector('option[value="' + trxCategory + '"]')) {
                var legacy = {};
                try { legacy = JSON.parse(categorySel.dataset.pcLegacyCategories || '{}'); } catch (e) {}
                if (legacy[trxCategory]) {
                    var opt = document.createElement('option');
                    opt.value = trxCategory;
                    opt.textContent = legacy[trxCategory] + ' (nonaktif)';
                    opt.dataset.pcLegacyOption = '1';
                    categorySel.appendChild(opt);
                }
            }
            categorySel.value       = trxCategory;
            descInput.value         = trxDesc;
            amountVis.value         = fmt(trxAmount);
            amountHid.value         = String(trxAmount);

            // Hitung max amount = saldo wallet sekarang + nominal asli
            // (karena nominal asli sudah "termakan" di saldo saat ini, jadi
            // wallet sebenarnya bisa nampung sampai max ini).
            const walletBalance = lookupWalletBalance(trxSourceId);
            const maxAmount     = walletBalance + trxAmount;
            if (maxHint) maxHint.textContent = fmt(maxAmount);

            // Sisa waktu edit (hitung mundur). Super Admin: trxDeadline = 0 →
            // elemen countdown disembunyikan, tombol simpan tetap aktif.
            if (submitBtn) submitBtn.disabled = false;
            if (trxDeadline) {
                startCountdown(trxDeadline);
            } else {
                stopCountdown();
                if (countdownEl) countdownEl.hidden = true;
            }

            // Buka modal (konvensi project: .pc-modal.is-open → display:flex).
            modal.classList.add('is-open');
        });
    })();

    // Preview badge sumber dana saat user pilih option di dropdown.
    document.querySelectorAll('.pc-source-select').forEach(function (sel) {
        const preview = sel.parentElement.querySelector('[data-pc-source-preview]');
        if (!preview) return;
        function render() {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) { preview.innerHTML = ''; return; }
            const color = opt.dataset.color || '#64748b';
            const short = opt.dataset.short || '?';
            const logo  = opt.dataset.logo  || '';
            const name  = opt.textContent.trim();
            const inner = logo
                ? '<img src="' + logo + '" alt="" class="fs-badge__img">'
                : '<span class="fs-badge__text">' + short + '</span>';
            preview.innerHTML =
                '<span class="fs-badge fs-badge--md" style="background:' + color + '">' + inner + '</span>' +
                '<span class="pc-source-preview__name">' + name + '</span>';
        }
        sel.addEventListener('change', render);
        render();
    });

    // Transfer modal: toggle input nomor rekening / telepon untuk SUMBER
    // TUJUAN berdasarkan kategori. Cash → hidden. Pre-fill dengan nomor
    // existing (kalau ada) — user bisa update sambil transfer.
    (function () {
        const sel   = document.getElementById('transferToSelect');
        if (!sel) return;
        const row   = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-row]');
        const label = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-label]');
        const input = transferModal && transferModal.querySelector('[data-pc-transfer-to-account-input]');
        if (!row || !label || !input) return;
        function sync() {
            const opt  = sel.options[sel.selectedIndex];
            const cat  = (opt && opt.dataset.category) || '';
            const acct = (opt && opt.dataset.account)  || '';
            if (cat === 'bank' || cat === 'ewallet') {
                row.hidden = false;
                const isBank = cat === 'bank';
                label.innerHTML = (isBank ? 'No. Rekening' : 'No. Telepon')
                    + ' <small class="pc-muted">(opsional)</small>';
                input.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                input.value = acct;
            } else {
                row.hidden = true;
                input.value = '';
            }
        }
        sel.addEventListener('change', sync);
        sync();
    })();

    // Modal Top Up Saldo (global): toggle input nomor rekening / telepon
    // berdasarkan kategori sumber dana yang dipilih. Pre-fill dengan nomor
    // existing kalau ada (form ini bisa dipakai untuk update juga).
    document.querySelectorAll('[data-pc-topup-source]').forEach(function (sel) {
        const form    = sel.closest('form');
        if (!form) return;
        const row     = form.querySelector('[data-pc-topup-account-row]');
        const label   = form.querySelector('[data-pc-topup-account-label]');
        const input   = form.querySelector('[data-pc-topup-account-input]');
        if (!row || !label || !input) return;
        function sync() {
            const opt = sel.options[sel.selectedIndex];
            const cat = (opt && opt.dataset.category) || '';
            const acct = (opt && opt.dataset.account) || '';
            if (cat === 'bank' || cat === 'ewallet') {
                row.hidden = false;
                const isBank = cat === 'bank';
                label.innerHTML = (isBank ? 'No. Rekening' : 'No. Telepon')
                    + ' <small class="pc-muted">(opsional)</small>';
                input.placeholder = isBank ? 'Mis: 1234567890' : 'Mis: 08123456789';
                input.value = acct;
            } else {
                row.hidden = true;
                input.value = '';
            }
        }
        sel.addEventListener('change', sync);
        sync();
    });

    // Sub-tabs di card tabel transaksi: split Top Up/Pengeluaran vs Transfer.
    // Klik tombol [data-trx-tab=name] → set [data-active-trx-tab] di card +
    // toggle visibility pane [data-trx-content].
    (function () {
        const card = document.querySelector('.pc-trx-card');
        if (!card) return;
        const tabs  = card.querySelectorAll('[data-trx-tab]');
        const panes = card.querySelectorAll('[data-trx-content]');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.dataset.trxTab;
                card.dataset.activeTrxTab = target;
                tabs.forEach(function (b) {
                    const active = b.dataset.trxTab === target;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panes.forEach(function (p) {
                    p.hidden = (p.dataset.trxContent !== target);
                });
            });
        });
    })();

    // Toggle expand/collapse untuk composite transfer row di tabel.
    // Composite row punya 2 child <tr> (sisi OUT & IN) yang hidden by
    // default. Klik tombol [data-pc-toggle-target=ID] → unhide children
    // dengan data-pc-composite-child=ID.
    document.querySelectorAll('[data-pc-toggle-target]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const key = btn.dataset.pcToggleTarget;
            const children = document.querySelectorAll('[data-pc-composite-child="' + key + '"]');
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            children.forEach(function (tr) { tr.hidden = expanded; });
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-chevron-right', expanded);
                icon.classList.toggle('fa-chevron-down',  !expanded);
            }
        });
    });

    // Copy-to-clipboard untuk tombol .pc-copy-btn. Direct listener (bukan
    // delegation) karena tombol ini kadang nested di dalam container yang
    // call stopPropagation di onclick — kalau pakai document delegation,
    // event tidak pernah sampai ke document & handler tidak fire.
    function flashCopied(btn) {
        const original = btn.innerHTML;
        btn.classList.add('is-copied');
        btn.innerHTML = '<i class="fa fa-check"></i>';
        setTimeout(function () {
            btn.classList.remove('is-copied');
            btn.innerHTML = original;
        }, 1200);
    }
    function copyText(btn, text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { flashCopied(btn); });
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); flashCopied(btn); }
            catch (_) {}
            document.body.removeChild(ta);
        }
    }
    document.querySelectorAll('.pc-copy-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            copyText(btn, btn.dataset.pcCopy || '');
        });
    });

    // Copy nomor rekening/HP dengan klik langsung pada angkanya (tanpa tombol).
    // Feedback lewat class .is-copied (CSS menampilkan "✓ Tersalin"), jadi teks
    // nomor tetap utuh — beda dari flashCopied() yg menukar innerHTML tombol.
    document.querySelectorAll('.pc-copy-value').forEach(function (el) {
        function doCopy(e) {
            e.preventDefault();
            e.stopPropagation();
            var text = el.dataset.pcCopy || el.textContent.trim();
            if (!text) return;
            var done = function () {
                el.classList.add('is-copied');
                setTimeout(function () { el.classList.remove('is-copied'); }, 1200);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); done(); } catch (_) {}
                document.body.removeChild(ta);
            }
        }
        el.addEventListener('click', doCopy);
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') doCopy(e);
        });
    });

    // ─── Inline add / edit nomor rek/HP di preview modal ───
    // Mode "add"  : muncul saat card Bank/E-Wallet diklik & no rek kosong.
    // Mode "edit" : tombol pen di sebelah copy untuk ubah nomor existing.
    // Input dipaksa numeric (strip non-digit on-the-fly).
    // AJAX ke pettycash.account.save → update DOM + card.dataset supaya
    // klik berikutnya langsung tampil nomor (tanpa reload halaman).
    (function () {
        const addWrap   = document.getElementById('transferFromAccountAdd');
        const addBtn    = document.getElementById('transferFromAccountAddBtn');
        const addForm   = document.getElementById('transferFromAccountAddForm');
        const addInput  = document.getElementById('transferFromAccountAddInput');
        const saveBtn   = document.getElementById('transferFromAccountSaveBtn');
        const cancelBtn = document.getElementById('transferFromAccountCancelBtn');
        const errorEl   = document.getElementById('transferFromAccountAddError');
        const showWrap  = document.getElementById('transferFromAccount');
        const editBtn   = document.getElementById('transferFromAccountEdit');
        const valEl     = document.getElementById('transferFromAccountValue');
        if (!addWrap || !addBtn || !addForm || !addInput || !saveBtn || !cancelBtn) return;

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf     = csrfMeta ? csrfMeta.getAttribute('content') : '';

        const periodInp = document.querySelector('[data-tab-content="transfer"] input[name="period_id"]');
        const kindInp   = document.querySelector('[data-tab-content="transfer"] input[name="tab"]');
        const branchInp = document.querySelector('[data-tab-content="transfer"] input[name="branch"]');

        // State: 'add' (nomor kosong) vs 'edit' (nomor sudah ada → ubah)
        let mode = 'add';

        function showError(msg) {
            if (!errorEl) return;
            errorEl.textContent = msg;
            errorEl.hidden = false;
        }
        function clearError() {
            if (!errorEl) return;
            errorEl.hidden = true;
            errorEl.textContent = '';
        }

        // Strip non-digit saat user mengetik / paste — hanya angka yang masuk.
        addInput.addEventListener('input', function () {
            const cleaned = addInput.value.replace(/\D+/g, '');
            if (cleaned !== addInput.value) addInput.value = cleaned;
        });

        function openForm(prefill) {
            clearError();
            addWrap.hidden = false;
            addBtn.hidden = true;
            addForm.hidden = false;
            addInput.value = prefill || '';
            addInput.focus();
            addInput.select();
        }
        function closeForm() {
            clearError();
            addInput.value = '';
            addForm.hidden = true;
            if (mode === 'add') {
                addBtn.hidden = false;
            } else {
                // Edit: balikin tampilan nomor existing
                addWrap.hidden = true;
                if (showWrap) showWrap.hidden = false;
            }
        }

        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            mode = 'add';
            openForm('');
        });

        if (editBtn) {
            editBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                mode = 'edit';
                const current = valEl ? (valEl.textContent || '').trim() : '';
                // Set source id dari card yang sedang aktif
                const activeCard = document.querySelector('[data-pc-source-card].is-active')
                    || document.querySelector('[data-pc-source-card][data-source-id="'
                        + (document.getElementById('transferFromSourceId')?.value || '') + '"]');
                if (activeCard) addWrap.dataset.sourceId = activeCard.dataset.sourceId || '';
                if (showWrap) showWrap.hidden = true;
                openForm(current);
            });
        }

        cancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeForm();
        });

        addInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter')  { e.preventDefault(); saveBtn.click(); }
            if (e.key === 'Escape') { e.preventDefault(); cancelBtn.click(); }
        });

        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearError();
            const value = (addInput.value || '').trim();
            if (!value) { showError('Nomor tidak boleh kosong.'); return; }
            if (!/^[0-9]+$/.test(value)) { showError('Nomor hanya boleh berisi angka.'); return; }

            const sourceId = addWrap.dataset.sourceId
                || document.getElementById('transferFromSourceId')?.value
                || '';
            const periodId = periodInp ? periodInp.value : '';
            const tab      = kindInp ? kindInp.value : 'petty_cash';
            if (!sourceId || !periodId) { showError('Data tidak lengkap, coba tutup & buka lagi modal.'); return; }

            const body = new FormData();
            body.append('_token', csrf);
            body.append('period_id', periodId);
            body.append('fund_source_id', sourceId);
            body.append('account_number', value);
            body.append('tab', tab);
            if (branchInp && branchInp.value) body.append('branch', branchInp.value);

            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

            fetch("{{ route('pettycash.account.save') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: body,
            })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); })
            .then(function (res) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i>';
                if (!res.ok || !res.body || !res.body.ok) {
                    // 422 Laravel validation: ambil pesan dari errors.account_number
                    let msg = (res.body && res.body.message) || 'Gagal menyimpan. Coba lagi.';
                    if (res.body && res.body.errors && res.body.errors.account_number) {
                        msg = res.body.errors.account_number[0];
                    }
                    showError(msg);
                    return;
                }
                // Sukses: pindahkan UI ke mode "tampil nomor + copy + edit"
                const acctValue = res.body.account_number;
                const label     = res.body.label || 'No. Rek';
                const lblEl     = document.getElementById('transferFromAccountLabel');
                const copyEl    = document.getElementById('transferFromAccountCopy');
                if (showWrap && lblEl && valEl && copyEl) {
                    lblEl.textContent = label;
                    valEl.textContent = acctValue;
                    copyEl.dataset.pcCopy = acctValue;
                    showWrap.hidden = false;
                }
                addWrap.hidden = true;
                addForm.hidden = true;
                addBtn.hidden = false;
                // Sync ke kartu sumber dana di halaman supaya klik berikutnya
                // langsung tampil nomor (tanpa reload).
                const card = document.querySelector('[data-pc-source-card][data-source-id="' + sourceId + '"]');
                if (card) card.dataset.sourceAccount = acctValue;
            })
            .catch(function () {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa fa-check"></i>';
                showError('Network error. Coba lagi.');
            });
        });
    })();
})();
</script>

@endsection
