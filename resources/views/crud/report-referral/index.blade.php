@extends('layouts.app')

@section('content')
<div class="container-fluid">
    @include('layouts.notif')

    @php
        // Tombol Kembali: pakai ?return= kalau halaman ini dibuka dari tempat
        // lain (mis. tab "Hasil Kode Reff" di /koc). Kalau tidak ada, pakai
        // halaman sebelumnya — kecuali itu halaman ini sendiri (akibat submit
        // filter), yang akan membuat tombolnya terasa rusak.
        $kembali = request('return');
        if (! $kembali) {
            $sebelumnya = url()->previous();
            $kembali = (! $sebelumnya || str_contains($sebelumnya, 'report-referral'))
                ? route('koc')
                : $sebelumnya;
        }
    @endphp

    <div class="row">
        <div class="col-md-12">
            <div class="st-card">
                <div class="st-card__body" style="padding:0;">

                    {{-- ─── HEADER ─── --}}
                    <div class="rr-head">
                        <div>
                            <h5 class="rr-head__title"><i class="fa fa-link"></i> Report Referral</h5>
                            <small class="rr-head__sub">
                                Reservasi yang memakai kode referral — beserta komisi dan status klaimnya.
                            </small>
                        </div>
                        <a href="{{ $kembali }}" class="st-btn-back">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>
                    </div>

                    {{-- Filter --}}
                    <form method="GET" action="{{ route('report.referral') }}" class="st-filter-bar rr-filter">
                        @if (request('return'))
                            <input type="hidden" name="return" value="{{ request('return') }}">
                        @endif
                        <label>KOC</label>
                        {{-- Pola sama dengan pencarian pasien di Tambah Reservasi
                             (public/assets/js/reservation-form.js:700-716): Select2
                             dengan kolom ketik di dalam panel. <datalist> sempat dicoba
                             supaya bisa diketik langsung di kotak, tapi daftar sarannya
                             dirender browser dan TIDAK BISA digayakan — tampil hitam,
                             lepas dari tema. --}}
                        <select name="affiliate_id" id="rrAffiliate" style="min-width:210px;">
                            <option value="">Semua KOC</option>
                            @foreach($affiliates as $a)
                                <option value="{{ $a->id }}" {{ $filterAffiliate == $a->id ? 'selected' : '' }}>
                                    {{ $a->name }} @if($a->role)({{ ucfirst($a->role) }})@endif
                                </option>
                            @endforeach
                        </select>

                        <label>Cabang</label>
                        <select name="branch_id" style="min-width:150px;">
                            <option value="">Semua Cabang</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}" {{ $filterBranch == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                            @endforeach
                        </select>

                        <label>Periode</label>
                        <input type="date" name="date_from" value="{{ $filterDateFrom }}">
                        <span class="rr-filter__sd">s/d</span>
                        <input type="date" name="date_to" value="{{ $filterDateTo }}">

                        <label>Klaim</label>
                        <select name="klaim" style="min-width:140px;">
                            <option value="">Semua Status Klaim</option>
                            <option value="1" {{ $filterKlaim === '1' ? 'selected' : '' }}>Sudah Diklaim</option>
                            <option value="0" {{ $filterKlaim === '0' ? 'selected' : '' }}>Belum Diklaim</option>
                        </select>

                        <button type="submit" class="st-btn-brand"><i class="fa fa-search"></i> Filter</button>
                        <a href="{{ route('report.referral', request('return') ? ['return' => request('return')] : []) }}"
                           class="st-btn-outline-brown" title="Reset filter"><i class="fa fa-undo"></i> Reset</a>

                        <div class="rr-filter__hasil">
                            Menampilkan <strong>{{ $reservations->count() }}</strong>
                            dari <strong>{{ (int) ($ringkas->total ?? 0) }}</strong> reservasi
                        </div>
                    </form>

                    {{-- ─── RINGKASAN ───
                         Keempat angka ini dihitung dari SELURUH hasil filter
                         (lihat $ringkas di controller), bukan dari halaman yang
                         sedang dibuka. Dua di antaranya dulu salah: begitu hasil
                         filter lebih dari satu halaman, "Sudah/Belum Diklaim"
                         melaporkan angka halaman saja tanpa ada yang sadar. --}}
                    <div class="rr-stat">
                        <div class="rr-stat__b">
                            <span class="rr-stat__ic rr-stat__ic--biru"><i class="fa fa-calendar-check"></i></span>
                            <div>
                                <div class="rr-stat__n">{{ (int) ($ringkas->total ?? 0) }}</div>
                                <div class="rr-stat__l">Total Reservasi</div>
                            </div>
                        </div>
                        <div class="rr-stat__b">
                            <span class="rr-stat__ic rr-stat__ic--hijau"><i class="fa fa-check-circle"></i></span>
                            <div>
                                <div class="rr-stat__n">{{ (int) ($ringkas->diklaim ?? 0) }}</div>
                                <div class="rr-stat__l">Sudah Diklaim</div>
                            </div>
                        </div>
                        <div class="rr-stat__b">
                            <span class="rr-stat__ic rr-stat__ic--oranye"><i class="fa fa-clock"></i></span>
                            <div>
                                <div class="rr-stat__n">{{ (int) ($ringkas->belum ?? 0) }}</div>
                                <div class="rr-stat__l">Belum Diklaim</div>
                            </div>
                        </div>
                        <div class="rr-stat__b">
                            <span class="rr-stat__ic rr-stat__ic--teal"><i class="fa fa-coins"></i></span>
                            <div>
                                <div class="rr-stat__n rr-stat__n--rp">Rp {{ number_format($totalKomisi, 0, ',', '.') }}</div>
                                <div class="rr-stat__l">Total Komisi — semua hasil filter</div>
                            </div>
                        </div>
                    </div>

                    {{-- Table --}}
                    <div class="st-table-wrap--scroll">
                        <table class="st-table st-table--compact">
                            <thead>
                                <tr>
                                    <th width="4%">No</th>
                                    <th>Affiliate</th>
                                    <th>Role</th>
                                    <th>Kode Referral</th>
                                    <th>Pasien</th>
                                    <th>Cabang</th>
                                    <th>Tanggal</th>
                                    <th style="min-width:150px">Perawatan</th>
                                    <th>Komisi</th>
                                    <th>Klaim</th>
                                    <th>Klaim Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reservations as $key => $row)
                                    @php
                                        $affiliate  = $row->promo->affiliate ?? null;
                                        $commission = $row->commission ?? null;
                                        $klaimBy    = $row->promo_klaim_by ? \App\Models\Users::find($row->promo_klaim_by) : null;
                                    @endphp
                                    <tr>
                                        <td class="st-num">{{ $reservations->firstItem() + $key }}</td>
                                        <td>
                                            @if($affiliate)
                                                <span class="st-primary">{{ $affiliate->name }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($affiliate && $affiliate->role)
                                                @php
                                                    $rolePill = ['pasien' => 'st-pill--info', 'influencer' => 'st-pill--warning', 'dokter' => 'st-pill--success'][$affiliate->role] ?? 'st-pill--neutral';
                                                @endphp
                                                <span class="st-pill st-pill--plain {{ $rolePill }}">{{ ucfirst($affiliate->role) }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->promo)
                                                <span class="st-pill st-pill--plain st-pill--primary">{{ $row->promo->promo_code }}</span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ $row->customer->name ?? '-' }}</td>
                                        <td>{{ $row->branch->name ?? '-' }}</td>
                                        <td><span class="st-date">{{ $row->date ? $row->date->format('d/m/Y') : '-' }}</span></td>
                                        <td style="white-space:normal;">
                                            @foreach($row->services as $svc)
                                                <span class="st-pill st-pill--plain st-pill--light" style="font-size:0.72rem; margin:1px;">
                                                    {{ $svc->docService->name ?? '-' }}
                                                </span>
                                            @endforeach
                                            @if($row->lainnya_text)
                                                <span class="st-pill st-pill--plain st-pill--warning" style="font-size:0.72rem; margin:1px;">
                                                    {{ $row->lainnya_text }}
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($commission)
                                                <strong style="color:var(--st-completed-text);">
                                                    Rp {{ number_format($commission->amount, 0, ',', '.') }}
                                                </strong>
                                                <br>
                                                @php
                                                    $commOpt   = \App\Models\RawCommission::statusOptions()[$commission->status] ?? [];
                                                    $commPills = ['success' => 'st-pill--success', 'warning' => 'st-pill--warning', 'danger' => 'st-pill--danger', 'info' => 'st-pill--info'];
                                                    $commPill  = $commPills[$commOpt['color'] ?? ''] ?? 'st-pill--neutral';
                                                @endphp
                                                <span class="st-pill {{ $commPill }}" style="font-size:0.7rem;">
                                                    {{ $commOpt['label'] ?? $commission->status }}
                                                </span>
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="st-center">
                                            @if($row->promo_klaim)
                                                <span class="st-pill st-pill--success"><i class="fa fa-check"></i> Diklaim</span>
                                            @else
                                                <span class="st-pill st-pill--neutral">Belum</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($row->promo_klaim && $klaimBy)
                                                {{ $klaimBy->name ?? '-' }}
                                            @else
                                                <span class="st-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4">
                                            <div class="st-muted">
                                                <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                                Tidak ada data referral ditemukan
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if($reservations->count() > 0)
                            <tfoot>
                                <tr>
                                    <td colspan="8" class="text-right" style="font-weight:700; background:var(--cream-header);">
                                        Total Komisi (halaman ini):
                                    </td>
                                    <td style="font-weight:700; background:var(--cream-header); color:var(--st-completed-text);">
                                        Rp {{ number_format(
                                            $reservations->getCollection()->sum(fn($r) => $r->commission->amount ?? 0),
                                            0, ',', '.'
                                        ) }}
                                    </td>
                                    <td colspan="2" style="background:var(--cream-header);"></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                    @if ($reservations->hasPages())
                        <div class="rr-pag">{{ $reservations->links() }}</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script>
    // ─── Filter KOC: Select2 dengan kolom ketik ───────────────────────
    // Select2 sudah dimuat global di layouts/app.blade.php (baris 15 & 155) —
    // tidak ada pustaka baru. Guard-nya mengikuti pola crud/konten-pillar:
    // kalau plugin tak ada, elemen tetap <select> biasa dan filter tetap jalan.
    if (window.jQuery && jQuery.fn.select2) {
        var $aff = jQuery('#rrAffiliate').select2({
            width: '210px',
            // 0 = kolom ketik SELALU ditampilkan. Ditulis eksplisit supaya tidak
            // ikut berubah kalau default plugin berubah suatu saat.
            minimumResultsForSearch: 0,
            language: {
                noResults:  function () { return 'KOC tidak ditemukan'; },
                searching:  function () { return 'Mencari…'; },
                inputTooShort: function () { return 'Ketik untuk mencari…'; }
            }
        });

        // Kelas gaya ditempelkan SESUDAH init, lewat instance-nya.
        //
        // TIDAK memakai opsi containerCssClass / dropdownCssClass — keduanya
        // butuh modul `select2/compat/*` yang TIDAK ADA di build
        // assets/plugins/select2/select2.min.js milik repo ini. Memakainya
        // membuat .select2() MELEMPAR "No select2/compat/dropdownCss",
        // init-nya batal diam-diam, dan kotaknya tetap <select> bawaan —
        // persis gejala yang sempat terjadi.
        var inst = $aff.data('select2');
        if (inst && inst.$container) inst.$container.addClass('rr-s2');

        $aff.on('select2:open', function () {
            // Panel dropdown baru ditempelkan ke <body> SAAT dibuka, jadi
            // kelasnya dipasang di sini — bukan sesudah init (inst.$dropdown
            // belum menunjuk elemen yang benar-benar tampil).
            var panel = document.querySelector('.select2-container--open .select2-dropdown');
            if (panel) panel.classList.add('rr-s2-drop');

            // Petunjuk di kolom ketik — tanpa ini kolomnya polos dan orang
            // tidak sadar boleh mengetik di situ.
            var cari = document.querySelector('.select2-container--open .select2-search__field');
            if (cari) { cari.setAttribute('placeholder', 'Ketik nama KOC…'); cari.focus(); }
        });
    }
</script>
@endsection

@section('css')
<style>
    /* ═══ Report Referral — perapian tampilan (IT-2026-045) ═══
       Ditaruh di blok <style> halaman ini, bukan di components.sweettreats.css,
       supaya tidak menambah kewajiban rsync saat naik prod dan tidak menyentuh
       halaman lain yang ikut memakai .st-filter-bar / .st-dash-card. */
    .rr-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px;
        flex-wrap:wrap; padding:16px 20px; border-bottom:1px solid #c4ebe9; background:#fff; }
    .rr-head__title { margin:0 0 4px; font-size:20px; font-weight:700; letter-spacing:-0.01em;
        color:#0a4f4d; display:flex; align-items:center; gap:8px; }
    .rr-head__title i { font-size:16px; color:#13928e; }
    .rr-head__sub { color:#5a7572; font-size:13px; }

    .rr-filter { margin:0; padding:12px 20px; border-bottom:1px solid #c4ebe9; background:#fcfefe; }
    .rr-filter input[type="date"] {
        height:36px; padding:0 12px; border:1px solid #d8ece9; border-radius:999px;
        background:#fff; color:#0a4f4d; font-size:13px; font-weight:500; line-height:36px;
        font-family:'Montserrat', sans-serif; box-shadow:0 1px 2px rgba(10,79,77,.04);
        transition:border-color .18s ease, box-shadow .18s ease; cursor:pointer;
    }
    .rr-filter input[type="date"]:hover { border-color:#c4ebe9; }
    .rr-filter input[type="date"]:focus { border-color:#13928e; outline:none;
        box-shadow:0 0 0 3px rgba(19,146,142,.12); }
    .rr-filter input[type="date"]::-webkit-calendar-picker-indicator { opacity:.55; cursor:pointer; filter:grayscale(1); }
    .rr-filter input[type="date"]:hover::-webkit-calendar-picker-indicator { opacity:.9; }
    .rr-filter input[type="date"]::-webkit-inner-spin-button,
    .rr-filter input[type="date"]::-webkit-clear-button { display:none; }
    .rr-filter__sd { font-size:11.5px; color:#8aa3a0; }
    .rr-filter__hasil { margin-left:auto; font-size:12px; color:#5a7572; }
    .rr-filter__hasil strong { color:#0a4f4d; font-variant-numeric:tabular-nums; }

    .rr-stat { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px;
        padding:16px 20px; background:#f4faf9; border-bottom:1px solid #c4ebe9; }
    @media (max-width:1000px) { .rr-stat { grid-template-columns:repeat(2, minmax(0,1fr)); } }
    .rr-stat__b { display:flex; align-items:center; gap:12px; background:#fff;
        border:1px solid #c4ebe9; border-radius:14px; padding:14px 16px;
        box-shadow:0 1px 2px rgba(10,79,77,.04); }
    .rr-stat__ic { width:34px; height:34px; border-radius:10px; flex:0 0 auto;
        display:inline-flex; align-items:center; justify-content:center; font-size:14px; }
    .rr-stat__ic--biru   { background:#E4ECFB; color:#3F6BAF; }
    .rr-stat__ic--hijau  { background:#E3F4E8; color:#2F8E55; }
    .rr-stat__ic--oranye { background:#FBEFD9; color:#B5701A; }
    .rr-stat__ic--teal   { background:#e6f5f4; color:#13928e; }
    .rr-stat__n { font-size:24px; font-weight:800; line-height:1.1; color:#0a4f4d;
        font-variant-numeric:tabular-nums; }
    .rr-stat__n--rp { font-size:17px; }
    .rr-stat__l { font-size:11px; font-weight:600; color:#5a7572; margin-top:3px; }

    /* ── Select2 disamakan dengan kontrol pil lain di filter bar ──
       select2.min.css dimuat di layout baris 15, blok ini lewat @yield('css')
       baris 45 — jadi menang tanpa perlu !important. */
    .rr-s2.select2-container--default .select2-selection--single {
        height:36px; border:1px solid #d8ece9; border-radius:999px; background:#fff;
        box-shadow:0 1px 2px rgba(10,79,77,.04); transition:border-color .18s ease, box-shadow .18s ease;
    }
    .rr-s2.select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height:34px; padding-left:14px; padding-right:30px;
        color:#0a4f4d; font-size:13px; font-weight:500; font-family:'Montserrat', sans-serif;
    }
    .rr-s2.select2-container--default .select2-selection--single .select2-selection__placeholder { color:#94a8a6; }
    .rr-s2.select2-container--default .select2-selection--single .select2-selection__arrow { height:34px; right:10px; }
    .rr-s2.select2-container--default .select2-selection--single .select2-selection__arrow b {
        border-color:#0a4f4d transparent transparent transparent;
    }
    .rr-s2.select2-container--default.select2-container--open .select2-selection--single,
    .rr-s2.select2-container--default .select2-selection--single:hover {
        border-color:#13928e; box-shadow:0 0 0 3px rgba(19,146,142,.12);
    }
    .rr-s2.select2-container--default.select2-container--open
        .select2-selection--single .select2-selection__arrow b {
        border-color:transparent transparent #0a4f4d transparent;
    }

    /* Panel + kolom ketik di dalamnya — inilah yang bikin ia "bisa diketik" */
    .rr-s2-drop.select2-dropdown {
        border:1px solid #c4ebe9; border-radius:14px; overflow:hidden;
        box-shadow:0 10px 28px rgba(10,79,77,.14);
    }
    .rr-s2-drop .select2-search--dropdown { padding:10px; background:#f4faf9; border-bottom:1px solid #c4ebe9; }
    .rr-s2-drop .select2-search--dropdown .select2-search__field {
        height:34px; padding:0 12px; border:1px solid #d8ece9; border-radius:999px;
        font-size:13px; color:#0a4f4d; font-family:'Montserrat', sans-serif; outline:none;
        background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a8a6' stroke-width='2.4' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M20 20l-3.5-3.5'/%3E%3C/svg%3E") no-repeat right 12px center;
        padding-right:32px;
    }
    .rr-s2-drop .select2-search--dropdown .select2-search__field:focus {
        border-color:#13928e; box-shadow:0 0 0 3px rgba(19,146,142,.12);
    }
    .rr-s2-drop .select2-results__options { max-height:260px; }
    .rr-s2-drop .select2-results__option { font-size:13px; color:#33514f; padding:9px 14px; }
    .rr-s2-drop .select2-results__option--highlighted[aria-selected] { background:#e6f5f4; color:#0a4f4d; }
    .rr-s2-drop .select2-results__option[aria-selected="true"] { background:#13928e; color:#fff; font-weight:600; }
    .rr-s2-drop .select2-results__message { font-size:12.5px; color:#8aa3a0; padding:12px 14px; }

    .rr-pag { padding:12px 20px; border-top:1px solid #c4ebe9; background:#f4faf9; }
    .rr-pag .pagination { margin:0; }
</style>
@endsection
