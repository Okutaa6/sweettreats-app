@extends(request()->boolean('drawer') ? 'layouts.bare' : 'layouts.app')

@section('css')
    {{-- Redesign reservasi — agar form standalone konsisten dgn drawer (yg load CSS ini via list.blade). --}}
    <link rel="stylesheet" href="{{ asset('assets/css/reservasi-redesign.css') }}?v={{ @filemtime(public_path('assets/css/reservasi-redesign.css')) ?: '1' }}">
    {{-- Style quick-add pasien (.qp-*) ada di reservasi-redesign.css (scoped). --}}
@endsection

@section('content')
    <div class="container-fluid rr-form-page">
        {{-- Page Head --}}
        @php
            $isNewReservation = $reservation->created_at && \Carbon\Carbon::parse($reservation->created_at)->diffInHours(\Carbon\Carbon::now()) < 6;
        @endphp
        {{-- Chrome halaman (judul + notif + Kembali) — disembunyikan saat dimuat di drawer (?drawer=1). --}}
        @unless(request()->boolean('drawer'))
        <div class="row">
            <div class="col-sm-12">
                <div class="page-title-box">
                    <h4 class="page-title">
                        Detail Reservasi
                        @if($isNewReservation)
                            <span class="st-card__badge-new"><i class="fa fa-star"></i> Baru</span>
                        @endif
                    </h4>
                </div>
            </div>
        </div>

        @include('layouts.notif')

        <div class="mb-3">
            <a href="{{ route('rawreservation') }}" class="st-btn-back"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
        @endunless

        @include('crud.reservation._form', ['mode' => 'edit'])

@endsection

@section('js')


    {{-- Flatpickr untuk date picker dengan restrict hari dokter --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    {{-- Style quick-add pasien dipindah ke @section('css') agar ikut ter-render di bare layout (drawer). --}}
    <script src="{{ asset('assets/js/reservation-form.js') }}?v={{ @filemtime(public_path('assets/js/reservation-form.js')) ?: '1' }}"></script>
    <script>
        // Standalone edit: panggil sekali setelah DOM & plugin siap.
        // (Drawer Fase 1 nanti memanggil window.initReservationForm() lagi setelah inject.)
        $(function () { window.initReservationForm(); });
    </script>
@endsection