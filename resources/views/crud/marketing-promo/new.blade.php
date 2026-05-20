@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box">
                <h4 class="page-title">Tambah Promo</h4>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="{{ route('marketingpromo') }}" class="st-btn-outline-brown">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>
            </div>

            <form method="POST" action="{{ route('marketingpromo.store') }}">
                @csrf
                @include('crud.marketing-promo._form')

                <div class="st-card">
                    <div class="st-card__body d-flex justify-content-end" style="gap: 8px;">
                        <a href="{{ route('marketingpromo') }}" class="st-btn-outline-brown">Batal</a>
                        <button class="st-btn-brand" type="submit">
                            <i class="fa fa-save"></i> Simpan Promo
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
