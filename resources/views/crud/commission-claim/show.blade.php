@extends('layouts.app')

@section('content')
@php
    $opt    = $statusOpt[$batch->status] ?? ['label' => $batch->status, 'color' => 'neutral', 'desc' => ''];
    $scopeLabel = $batch->referral_scope === 'behel' ? 'Behel' : 'Non-Behel';
@endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-sm-12">
            <div class="page-title-box d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4 class="page-title mb-1">Detail Klaim Komisi</h4>
                    <div class="text-muted" style="font-size: 13px;">
                        <span class="st-pill st-pill--{{ $opt['color'] }}">{{ $opt['label'] }}</span>
                        <span class="ml-2">{{ $opt['desc'] }}</span>
                    </div>
                </div>
                <a href="{{ route('commissionclaim') }}" class="st-btn-outline-brown">
                    <i class="fa fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>

    @include('layouts.notif')

    <div class="row">
        {{-- LEFT: Detail batch + actions --}}
        <div class="col-lg-7">
            <div class="st-card mb-3">
                <div class="st-card__head">
                    <div class="st-card__title-wrap">
                        <span class="st-card__icon"><i class="fa fa-hand-holding-dollar"></i></span>
                        <h4 class="st-card__title">Informasi Klaim</h4>
                    </div>
                </div>
                <div class="st-card__body">
                    <table class="st-kv-table">
                        <tbody>
                            <tr>
                                <td>Affiliate</td>
                                <td>
                                    <strong>{{ $batch->affiliate->name ?? '-' }}</strong>
                                    @if($batch->affiliate?->phone)
                                        <br><small class="text-muted"><i class="fa fa-phone"></i> {{ $batch->affiliate->phone }}</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Scope</td>
                                <td><span class="st-pill st-pill--plain {{ $batch->referral_scope === 'behel' ? 'st-pill--info' : 'st-pill--success' }}">{{ $scopeLabel }}</span></td>
                            </tr>
                            <tr>
                                <td>Jumlah Pasien</td>
                                <td><strong>{{ $batch->patient_count }}</strong> pasien</td>
                            </tr>
                            <tr>
                                <td>Total Komisi</td>
                                <td><strong style="font-size:16px;">Rp {{ number_format($batch->total_amount, 0, ',', '.') }}</strong></td>
                            </tr>
                            <tr>
                                <td>PJ</td>
                                <td>{{ $batch->pj?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Diajukan</td>
                                <td><span class="st-date">{{ $batch->requested_at->format('d M Y H:i') }}</span></td>
                            </tr>
                            @if($batch->reviewed_at)
                                <tr><td>Mulai Review</td><td><span class="st-date">{{ $batch->reviewed_at->format('d M Y H:i') }}</span></td></tr>
                            @endif
                            @if($batch->approved_at)
                                <tr><td>Approved</td><td><span class="st-date">{{ $batch->approved_at->format('d M Y H:i') }}</span></td></tr>
                            @endif
                            @if($batch->tx_at)
                                <tr><td>Tanggal Transfer</td><td><span class="st-date">{{ $batch->tx_at->format('d M Y H:i') }}</span></td></tr>
                            @endif
                            @if($batch->completed_at)
                                <tr><td>Completed</td><td><span class="st-date">{{ $batch->completed_at->format('d M Y H:i') }}</span></td></tr>
                            @endif
                            @if($batch->notes)
                                <tr><td>Catatan</td><td>{{ $batch->notes }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ACTIONS sesuai status --}}
            <div class="st-card mb-3">
                <div class="st-card__head">
                    <div class="st-card__title-wrap">
                        <span class="st-card__icon"><i class="fa fa-bolt"></i></span>
                        <h4 class="st-card__title">Aksi</h4>
                    </div>
                </div>
                <div class="st-card__body">
                    @if($batch->status === \App\Models\CommissionClaimBatch::STATUS_REQUESTED)
                        <p class="text-muted" style="font-size:13px;">Klaim ini menunggu review. Klik tombol di bawah untuk ambil-alih sebagai PJ.</p>
                        <form method="POST" action="{{ route('commissionclaim.take', $batch->id) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="st-btn-brand">
                                <i class="fa fa-user-check"></i> Ambil Review (Jadi PJ)
                            </button>
                        </form>
                    @elseif($batch->status === \App\Models\CommissionClaimBatch::STATUS_IN_REVIEW)
                        <p class="text-muted" style="font-size:13px;">Konfirmasi nominal Rp {{ number_format($batch->total_amount, 0, ',', '.') }} ke <strong>{{ $batch->affiliate?->name }}</strong> via WhatsApp di nomor <strong>{{ $batch->affiliate?->phone }}</strong>. Setelah deal, klik approve untuk lanjut ke finance.</p>
                        <form method="POST" action="{{ route('commissionclaim.approve', $batch->id) }}">
                            @csrf @method('PATCH')
                            <div class="form-group">
                                <label>Catatan (opsional)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Mis. nomor referensi pengajuan ke finance">{{ $batch->notes }}</textarea>
                            </div>
                            <button type="submit" class="st-btn-brand">
                                <i class="fa fa-check"></i> Approve & Ajukan ke Finance
                            </button>
                        </form>
                    @elseif($batch->status === \App\Models\CommissionClaimBatch::STATUS_APPROVED)
                        <p class="text-muted" style="font-size:13px;">Setelah finance transfer, upload bukti transfer di bawah ini.</p>
                        <form method="POST" action="{{ route('commissionclaim.upload', $batch->id) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group">
                                <label>Bukti Transfer <span class="text-danger">*</span></label>
                                <input type="file" name="tx_proof" class="form-control" accept="image/*" required>
                                <small class="text-muted">Format: JPG/PNG/WEBP, max 4MB.</small>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Transfer</label>
                                <input type="datetime-local" name="tx_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                            <div class="form-group">
                                <label>Catatan tambahan (opsional)</label>
                                <textarea name="notes" class="form-control" rows="2">{{ $batch->notes }}</textarea>
                            </div>
                            <button type="submit" class="st-btn-brand">
                                <i class="fa fa-upload"></i> Upload Bukti & Mark Transferred
                            </button>
                        </form>
                    @elseif($batch->status === \App\Models\CommissionClaimBatch::STATUS_TRANSFERRED)
                        <p class="text-muted" style="font-size:13px;">Sudah ditransfer. Tunggu KOC konfirmasi terima di portal mereka. Atau, jika KOC tidak respon, force-complete:</p>
                        @if($batch->tx_proof_path)
                            <div class="mb-3">
                                <a href="{{ asset('uploads/commission_proof/' . $batch->tx_proof_path) }}" target="_blank" class="st-btn-outline-brown">
                                    <i class="fa fa-image"></i> Lihat Bukti Transfer
                                </a>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('commissionclaim.complete', $batch->id) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="st-btn-outline-brown" onclick="return confirm('Force-complete batch ini? Pakai hanya jika KOC tidak respon dalam waktu lama.')">
                                <i class="fa fa-check-double"></i> Force Complete
                            </button>
                        </form>
                    @else
                        <div class="text-center text-muted py-3">
                            <i class="fa fa-check-circle" style="font-size:24px; color:#2e7d32;"></i><br>
                            <strong>Klaim sudah selesai.</strong>
                            @if($batch->completed_at)
                                <br><small>Selesai pada {{ $batch->completed_at->format('d M Y H:i') }}</small>
                            @endif
                        </div>
                        @if($batch->tx_proof_path)
                            <div class="text-center mt-3">
                                <a href="{{ asset('uploads/commission_proof/' . $batch->tx_proof_path) }}" target="_blank" class="st-btn-outline-brown">
                                    <i class="fa fa-image"></i> Lihat Bukti Transfer
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT: Items (commissions) --}}
        <div class="col-lg-5">
            <div class="st-card">
                <div class="st-card__head">
                    <div class="st-card__title-wrap">
                        <span class="st-card__icon"><i class="fa fa-list"></i></span>
                        <h4 class="st-card__title">Pasien dalam Klaim Ini</h4>
                    </div>
                </div>
                <div class="st-card__body" style="padding: 0;">
                    <table class="st-table st-table--compact">
                        <thead>
                            <tr><th>Tanggal</th><th>Pasien</th><th class="text-right">Komisi</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($batch->items as $item)
                                @php
                                    $c = $item->commission;
                                    $cust = $c?->reservation?->customer;
                                @endphp
                                <tr>
                                    <td><span class="st-date">{{ $c?->reservation?->date?->format('d/m/Y') ?? '-' }}</span></td>
                                    <td>
                                        {{ $cust?->name ?? '-' }}
                                        @if($c?->reservation?->branch)
                                            <br><small class="text-muted">{{ $c->reservation->branch->name }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right st-tnum">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background:#faf5f3;">
                                <td colspan="2"><strong>Total ({{ $batch->items->count() }} pasien)</strong></td>
                                <td class="text-right st-tnum"><strong>Rp {{ number_format($batch->total_amount, 0, ',', '.') }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
