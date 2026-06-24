@extends(request()->boolean('drawer') ? 'layouts.bare' : 'layouts.app')

@section('css')
    <style>
        /* Quick-add pasien (1 nomor → banyak pasien) */
        .qp-trigger-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:6px; }
        .qp-trigger-wrap small { color:#8b7a6b; }
        .qp-trigger {
            display:inline-flex; align-items:center; gap:7px;
            background:#fff; color:#461500; border:1.5px dashed rgba(70,21,0,.35);
            border-radius:9px; padding:7px 14px; font-size:.82rem; font-weight:700;
            cursor:pointer; transition:.15s;
        }
        .qp-trigger:hover { background:#fff4e8; border-color:#461500; }
        .qp-card {
            margin-top:12px; border:1px solid #e6d5c4; border-radius:12px;
            background:#fffaf5; overflow:hidden;
            box-shadow:0 6px 18px rgba(70,21,0,.06);
        }
        .qp-card__head {
            display:flex; align-items:center; justify-content:space-between;
            padding:11px 16px; background:linear-gradient(135deg,#461500,#6b2400);
            color:#fff; font-weight:700; font-size:.9rem;
        }
        .qp-card__head i { margin-right:7px; }
        .qp-close {
            background:transparent; border:none; color:#fff; font-size:1.25rem;
            line-height:1; cursor:pointer; opacity:.85; padding:0 4px;
        }
        .qp-close:hover { opacity:1; }
        .qp-card__body { padding:16px; }
        .qp-card__body label { font-size:.78rem; font-weight:700; color:#5b4636; margin-bottom:4px; }
        .qp-card__body .form-control { border-radius:9px; border-color:#e0d5c8; }
        .qp-card__body .form-control:focus { border-color:#461500; box-shadow:0 0 0 .15rem rgba(70,21,0,.12); }
        .qp-req { color:#dc2626; }
        .qp-opt { color:#8b7a6b; font-weight:400; font-size:.72rem; }
        .qp-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
        .qp-status { min-height:20px; margin:4px 0 2px; font-size:.82rem; }
        .qp-msg { display:inline-flex; align-items:center; gap:6px; font-weight:600; }
        .qp-ok  { color:#16a34a; }
        .qp-err { color:#dc2626; }
        .st-btn-ghost {
            background:#f0e8e0; color:#5b4636; border:none; border-radius:9px;
            padding:9px 18px; font-weight:700; font-size:.85rem; cursor:pointer;
        }
        .st-btn-ghost:hover { background:#e6dacc; }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        {{-- Chrome halaman (judul + notif + Kembali) — disembunyikan saat dimuat di drawer (?drawer=1). --}}
        @unless(request()->boolean('drawer'))
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">Tambah Reservasi</h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="mb-3">
            <a href="{{ route('rawreservation') }}" class="st-btn-back"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
        @endunless

        @include('crud.reservation._form', ['mode' => 'add'])

    </div>
@endsection

@section('js')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    {{-- Style quick-add pasien dipindah ke @section('css') agar ikut ter-render di bare layout (drawer). --}}
    <script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
    <script>
        // Standalone tambah: panggil sekali setelah DOM & plugin siap.
        // (Drawer Fase 1 nanti memanggil window.initReservationForm() lagi setelah inject.)
        $(function () { window.initReservationForm(); });
    </script>
@endsection
