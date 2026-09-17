{{--
    Petty Cash — modal "Migrasi Saldo" (Super Admin).

    Cut-over cabang ke model wallet baru, DIJALANKAN CABANG PER CABANG:
      1. Super Admin memilih wallet mana yang tetap dipakai cabang ini.
      2. Saldo awal periode dikosongkan (transaksi TIDAK dihapus).
      3. Wallet yang tidak dipilih dipensiunkan untuk cabang ini saja —
         cabang lain tetap memakai wallet lamanya sampai gilirannya tiba.
      4. Operator cabang lalu mengisi saldo dari hitung fisik.

    Wallet dirender sebagai KARTU (bukan daftar centang polos) supaya bentuknya
    sama dengan kartu "Rincian Saldo per Wallet" di halaman — orang memilih
    benda yang sama yang dilihatnya sehari-hari. Klik kartu = toggle.

    Centang awal = semua wallet Cash + satu bank bersaldo terbesar. Itu
    menghasilkan "Cash + BCA" di cabang normal, tapi otomatis jadi
    "Cash + BSI" di cabang yang memang tidak pakai BCA — tanpa menuliskan
    nama bank mana pun di dalam kode.

    Variables: $period, $kind, $branchId, $fundSourcesGrouped, $sourceBalances,
               $activeBranch, $monthName, $year.
--}}
@php
    $kind         = $kind ?? 'petty_cash';
    $activeBranch = $activeBranch ?? null;
    $namaCabang   = $activeBranch->name ?? 'cabang ini';
    $rupiahMig    = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    // Saldo per wallet yang sedang tampil di kartu — dipakai supaya Super
    // Admin melihat angka yang akan ditinggalkan sebelum memutuskan.
    $saldoPerWallet = collect($sourceBalances ?? [])
        ->filter(fn ($sb) => ($sb->id ?? null) !== null)
        ->keyBy('id');

    $walletMigrasi = collect($fundSourcesGrouped ?? [])->flatten(1)->values();

    // Centang awal: seluruh Cash + satu bank dengan saldo terbesar.
    $centangAwal = $walletMigrasi
        ->filter(fn ($fs) => $fs->category === \App\Models\FundSource::CAT_CASH)
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $bankTerbesar = $walletMigrasi
        ->filter(fn ($fs) => $fs->category === \App\Models\FundSource::CAT_BANK)
        ->sortByDesc(fn ($fs) => (float) ($saldoPerWallet[$fs->id]->balance ?? 0))
        ->first();
    if ($bankTerbesar) {
        $centangAwal[] = (int) $bankTerbesar->id;
    }

    $totalSaldoTampil = $walletMigrasi->sum(fn ($fs) => (float) ($saldoPerWallet[$fs->id]->balance ?? 0));
@endphp
<div class="pc-modal" id="pcModalMigrasi" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog pc-mig__dialog">
        <form method="POST" action="{{ route('pettycash.opening.reset', ['id' => $period->id]) }}"
              data-pc-migrasi-form>
            @csrf
            @include('crud.pettycash._filter_hidden')
            <input type="hidden" name="tab" value="{{ $kind }}">
            <input type="hidden" name="mode" value="open">
            @if(!session('raw_branch_id') && $branchId)
                <input type="hidden" name="branch" value="{{ $branchId }}">
            @endif

            <div class="pc-modal__header pc-modal__header--wallet">
                <span class="pc-modal__icon-box" aria-hidden="true">
                    <i class="fa fa-file-import"></i>
                </span>
                <div class="pc-modal__heading">
                    <h5 class="pc-modal__title">Migrasi Saldo</h5>
                    <small class="pc-muted">{{ $namaCabang }} &middot; {{ $monthName }} {{ $year }}</small>
                </div>
                <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                {{-- Papan hitung: berapa wallet sekarang → berapa sesudahnya.
                     Angkanya hidup mengikuti centang, jadi akibat pilihan
                     terbaca sebelum tombol ditekan. --}}
                <div class="pc-mig__scoreboard">
                    <div class="pc-mig__score">
                        <span class="pc-mig__score-num">{{ $walletMigrasi->count() }}</span>
                        <span class="pc-mig__score-lbl">wallet sekarang</span>
                    </div>
                    <i class="fa fa-long-arrow-alt-right pc-mig__score-arrow" aria-hidden="true"></i>
                    <div class="pc-mig__score pc-mig__score--after">
                        <span class="pc-mig__score-num" data-pc-mig-count>{{ count($centangAwal) }}</span>
                        <span class="pc-mig__score-lbl">setelah migrasi</span>
                    </div>
                    <div class="pc-mig__score-total">
                        <span class="pc-mig__score-lbl">Saldo tampil sekarang</span>
                        <strong>{{ $rupiahMig($totalSaldoTampil) }}</strong>
                        <span class="pc-mig__score-note">dikosongkan, lalu diisi dari hitung fisik</span>
                    </div>
                </div>

                <div class="pc-mig__section-title">
                    <span>Wallet yang dipertahankan</span>
                    <small>Klik kartu untuk memilih</small>
                </div>

                <div class="pc-mig__grid">
                    @foreach($walletMigrasi as $fs)
                        @php
                            $sb        = $saldoPerWallet[$fs->id] ?? null;
                            $saldo     = (float) ($sb->balance ?? 0);
                            $dicentang = in_array((int) $fs->id, $centangAwal, true);
                            $warna     = $fs->effectiveBrandColor();
                            $noRek     = $sb->account_number ?? $fs->accountForBranch($branchId);
                        @endphp
                        <label class="pc-mig__card {{ $dicentang ? 'is-keep' : '' }}"
                               style="--mig-brand: {{ $warna }};">
                            <input type="checkbox" name="keep[]" value="{{ $fs->id }}"
                                   class="pc-mig__check" data-pc-migrasi-keep
                                   data-nama="{{ $fs->name }}" {{ $dicentang ? 'checked' : '' }}>

                            <div class="pc-mig__card-head">
                                @include('crud.pettycash._fund_source_badge', [
                                    'color' => $warna,
                                    'short' => $fs->effectiveShortLabel(),
                                    'logo'  => $fs->logo_url,
                                    'name'  => $fs->name,
                                    'size'  => 'md',
                                ])
                                <div class="pc-mig__card-id">
                                    <span class="pc-mig__card-name">{{ $fs->name }}</span>
                                    <span class="pc-mig__card-sub">
                                        {{ \App\Models\FundSource::categoryLabel($fs->category) }}@if($noRek) &middot; {{ $noRek }}@endif
                                    </span>
                                </div>
                                <span class="pc-mig__tick" aria-hidden="true"><i class="fa fa-check"></i></span>
                            </div>

                            <div class="pc-mig__card-saldo">{{ $rupiahMig($saldo) }}</div>

                            <div class="pc-mig__card-state">
                                <span class="pc-mig__chip pc-mig__chip--keep">
                                    <i class="fa fa-check-circle"></i> Tetap dipakai
                                </span>
                                <span class="pc-mig__chip pc-mig__chip--drop">
                                    <i class="fa fa-archive"></i> Dipensiunkan di cabang ini
                                </span>
                            </div>
                        </label>
                    @endforeach
                </div>

                <p class="pc-mig__summary" data-pc-migrasi-ringkas></p>

                <div class="pc-mig__warn">
                    <i class="fa fa-exclamation-triangle"></i>
                    <div>
                        Seluruh saldo yang tampil sekarang <strong>dikosongkan</strong>, termasuk milik wallet
                        yang dipertahankan &mdash; angkanya diganti hasil hitung fisik operator cabang.
                        Angka lama tetap tersimpan di audit log, dan
                        <strong>tidak ada transaksi yang terhapus</strong>.
                        Wallet yang dipensiunkan hanya berhenti di <strong>{{ $namaCabang }}</strong>;
                        cabang lain tetap memakainya.
                    </div>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand" data-pc-migrasi-submit>
                    <i class="fa fa-file-import"></i> Mulai Migrasi
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .pc-mig__dialog { max-width: 640px; }

    /* Papan hitung sebelum → sesudah */
    .pc-mig__scoreboard {
        display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        padding: 14px 16px; margin-bottom: 18px;
        background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px;
    }
    .pc-mig__score { display: flex; flex-direction: column; align-items: center; min-width: 88px; }
    .pc-mig__score-num {
        font-size: 26px; font-weight: 800; line-height: 1;
        color: #92400e; font-variant-numeric: tabular-nums;
    }
    .pc-mig__score--after .pc-mig__score-num { color: #15803d; }
    .pc-mig__score-lbl {
        font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
        color: #a1782f; margin-top: 4px; text-align: center;
    }
    .pc-mig__score-arrow { color: #d97706; font-size: 18px; }
    .pc-mig__score-total {
        margin-left: auto; text-align: right; display: flex; flex-direction: column; gap: 2px;
    }
    .pc-mig__score-total strong {
        font-size: 17px; color: #78350f; font-variant-numeric: tabular-nums;
    }
    .pc-mig__score-total .pc-mig__score-lbl { text-align: right; margin: 0; }
    .pc-mig__score-note { font-size: 11px; color: #a1782f; }

    .pc-mig__section-title {
        display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
        margin-bottom: 8px;
    }
    .pc-mig__section-title > span {
        font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #78350f;
    }
    .pc-mig__section-title > small { color: #a1782f; font-size: 11.5px; }

    /* Kartu wallet — bentuknya sengaja mirip kartu Rincian Saldo per Wallet */
    .pc-mig__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 560px) { .pc-mig__grid { grid-template-columns: 1fr; } }

    .pc-mig__card {
        position: relative; display: flex; flex-direction: column; gap: 8px; margin: 0;
        padding: 12px 13px; cursor: pointer; user-select: none;
        background: #fff; border: 1px solid #e7e2db; border-left: 4px solid var(--mig-brand, #cbd5e1);
        border-radius: 10px; transition: box-shadow .15s, border-color .15s, background .15s, opacity .15s;
    }
    .pc-mig__card:hover { box-shadow: 0 3px 10px rgba(70, 21, 0, .10); }
    .pc-mig__check { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
    .pc-mig__check:focus-visible + .pc-mig__card-head { outline: 2px solid #b45309; outline-offset: 3px; border-radius: 6px; }

    .pc-mig__card-head { display: flex; align-items: center; gap: 9px; }
    .pc-mig__card-id { display: flex; flex-direction: column; min-width: 0; flex: 1; }
    .pc-mig__card-name {
        font-weight: 700; color: #1e293b; font-size: 13.5px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pc-mig__card-sub {
        font-size: 11px; color: #94a3b8; font-weight: 600;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .pc-mig__tick {
        flex: 0 0 auto; width: 22px; height: 22px; border-radius: 50%;
        border: 2px solid #d8d2ca; background: #fff; color: transparent;
        display: inline-flex; align-items: center; justify-content: center; font-size: 11px;
        transition: background .15s, border-color .15s, color .15s;
    }
    .pc-mig__card-saldo {
        font-size: 19px; font-weight: 800; color: #1e293b; font-variant-numeric: tabular-nums; line-height: 1.1;
    }
    .pc-mig__card-state { font-size: 11px; }
    .pc-mig__chip {
        display: none; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 50px; font-weight: 700; letter-spacing: .01em;
    }
    .pc-mig__chip--keep { background: #dcfce7; color: #15803d; }
    .pc-mig__chip--drop { background: #f1f5f9; color: #64748b; }

    /* State: dipertahankan */
    .pc-mig__card.is-keep { border-color: #86efac; border-left-color: var(--mig-brand, #cbd5e1); background: #f6fef8; }
    .pc-mig__card.is-keep .pc-mig__tick { background: #16a34a; border-color: #16a34a; color: #fff; }
    .pc-mig__card.is-keep .pc-mig__chip--keep { display: inline-flex; }

    /* State: dipensiunkan */
    .pc-mig__card:not(.is-keep) { opacity: .72; background: #fafafa; }
    .pc-mig__card:not(.is-keep) .pc-mig__card-name { text-decoration: line-through; color: #64748b; }
    .pc-mig__card:not(.is-keep) .pc-mig__card-saldo { color: #94a3b8; }
    .pc-mig__card:not(.is-keep) .pc-mig__chip--drop { display: inline-flex; }

    .pc-mig__summary {
        margin: 12px 0 0; font-size: 12.5px; color: #78350f; line-height: 1.5;
    }
    .pc-mig__summary b { font-weight: 700; }

    .pc-mig__warn {
        display: flex; gap: 10px; margin-top: 14px; padding: 11px 13px;
        background: #fef2f2; border: 1px solid #fecaca; border-left: 3px solid #dc2626;
        border-radius: 8px; font-size: 12.5px; color: #7f1d1d; line-height: 1.5;
    }
    .pc-mig__warn > i { margin-top: 2px; color: #dc2626; }
</style>

<script>
(function () {
    var form = document.querySelector('[data-pc-migrasi-form]');
    if (!form) return;

    var kotak   = Array.prototype.slice.call(form.querySelectorAll('[data-pc-migrasi-keep]'));
    var ringkas = form.querySelector('[data-pc-migrasi-ringkas]');
    var jumlah  = form.querySelector('[data-pc-mig-count]');
    var submit  = form.querySelector('[data-pc-migrasi-submit]');

    function belah() {
        var simpan = [], pensiun = [];
        kotak.forEach(function (el) {
            (el.checked ? simpan : pensiun).push(el.dataset.nama || '');
        });
        return { simpan: simpan, pensiun: pensiun };
    }

    function segarkan() {
        var b = belah();

        kotak.forEach(function (el) {
            el.closest('.pc-mig__card').classList.toggle('is-keep', el.checked);
        });

        if (jumlah) jumlah.textContent = b.simpan.length;

        // Minimal satu wallet harus tersisa — dijaga juga di server.
        if (submit) submit.disabled = b.simpan.length === 0;

        if (ringkas) {
            if (b.simpan.length === 0) {
                ringkas.innerHTML = '<b>Pilih minimal satu wallet.</b> Cabang tidak boleh kehilangan semua wallet.';
            } else if (b.pensiun.length === 0) {
                ringkas.innerHTML = 'Tidak ada wallet yang dipensiunkan &mdash; hanya saldonya yang dikosongkan.';
            } else {
                ringkas.innerHTML = 'Setelah migrasi, cabang ini memakai <b>' + b.simpan.join(' &amp; ')
                    + '</b>. Yang berhenti: <b>' + b.pensiun.join(', ') + '</b>.';
            }
        }
    }

    kotak.forEach(function (el) { el.addEventListener('change', segarkan); });
    segarkan();

    form.addEventListener('submit', function (e) {
        var b = belah();
        if (b.simpan.length === 0) { e.preventDefault(); return; }

        var pesan = 'Mulai migrasi saldo ' + @json($namaCabang) + '?\n\n'
            + '• Wallet yang tetap dipakai: ' + b.simpan.join(', ') + '\n'
            + (b.pensiun.length ? '• Dipensiunkan di cabang ini: ' + b.pensiun.join(', ') + '\n' : '')
            + '• Seluruh saldo dikosongkan, lalu diisi operator dari hitung fisik.\n'
            + '• Transaksi tidak ada yang terhapus.\n\nLanjutkan?';

        if (!confirm(pesan)) e.preventDefault();
    });
})();
</script>
