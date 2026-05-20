@extends('layouts.app')

@section('content')
@php
    $statusOpt = \App\Models\MarketingPromo::statusOptions()[$promo->status] ?? ['label' => $promo->status, 'color' => 'neutral'];
    $campaignLabel = \App\Models\MarketingPromo::campaignTypes()[$promo->campaign_type] ?? $promo->campaign_type;
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="page-title mb-1">Edit Promo</h4>
                    <div class="text-muted" style="font-size: 13px;">
                        <span class="st-pill st-pill--{{ $statusOpt['color'] }}">{{ $statusOpt['label'] }}</span>
                        <span class="ml-2">{{ $campaignLabel }}</span>
                        @if ($promo->periode_start || $promo->periode_end)
                            <span class="ml-2">·</span>
                            <span class="ml-2">
                                <i class="fa fa-calendar-alt"></i>
                                {{ $promo->periode_start?->format('d M Y') ?? '...' }} – {{ $promo->periode_end?->format('d M Y') ?? '...' }}
                            </span>
                        @endif
                    </div>
                </div>
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
                <button type="button" class="st-btn-danger-outline"
                        onclick="if(confirm('Hapus promo ini? Tindakan ini tidak bisa di-undo.')) document.getElementById('deletePromo').submit()">
                    <i class="fa fa-trash"></i> Hapus
                </button>
            </div>

            <form method="POST" action="{{ route('marketingpromo.update', $promo->id) }}">
                @csrf
                @method('PUT')
                @include('crud.marketing-promo._form')

                <div class="st-card">
                    <div class="st-card__body d-flex justify-content-end" style="gap: 8px;">
                        <a href="{{ route('marketingpromo') }}" class="st-btn-outline-brown">Batal</a>
                        <button class="st-btn-brand" type="submit">
                            <i class="fa fa-save"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </form>

            <form id="deletePromo" method="POST" action="{{ route('marketingpromo.destroy', $promo->id) }}" style="display:none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</div>
@endsection
