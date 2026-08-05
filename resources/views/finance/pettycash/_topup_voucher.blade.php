{{--
    Kartu voucher 1 pengajuan top up — target rasterisasi html2canvas → PNG.
    Variables: $req (TopupRequest, eager-loaded), $rupiah (closure).

    Didesain html2canvas-friendly: warna solid, tanpa ikon Font Awesome
    (glyph font tak selalu ke-render → kotak kosong), tanpa box-shadow berat.
    Node berada di stage off-screen (.ftv-stage) supaya tetap punya layout.
--}}
@php
    $pemohon    = $req->requester_name ?: ($req->requester?->nama ?? '-');
    $walletName = $req->fundSource?->name ?? '-';
    $noRek      = $req->fundSource && $req->raw_branch_id
        ? $req->fundSource->accountForBranch((string) $req->raw_branch_id)
        : null;
@endphp
<div class="ftv-voucher" id="ftv-voucher-{{ $req->id }}">
    <div class="ftv-voucher__head">
        <div class="ftv-voucher__brand">Sweet Treats Dental Clinic</div>
        <div class="ftv-voucher__brandsub">Pengajuan Top Up Petty Cash</div>
    </div>

    <div class="ftv-voucher__statusrow">
        <span class="ftv-voucher__no">#{{ $req->id }}</span>
    </div>

    <div class="ftv-voucher__amount">
        <span class="ftv-voucher__amount-label">Nominal diminta</span>
        <span class="ftv-voucher__amount-val">{{ $rupiah($req->amount_requested) }}</span>
    </div>

    <div class="ftv-voucher__rows">
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">Cabang</span>
            <span class="ftv-voucher__v">{{ $req->branch?->name ?? '-' }}</span>
        </div>
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">Pemohon</span>
            <span class="ftv-voucher__v">{{ $pemohon }}</span>
        </div>
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">Tanggal pengajuan</span>
            <span class="ftv-voucher__v">{{ $req->requested_at?->translatedFormat('d M Y, H:i') ?? '-' }}</span>
        </div>
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">Butuh kapan</span>
            <span class="ftv-voucher__v">{{ $req->needed_by?->translatedFormat('d M Y') ?? '—' }}</span>
        </div>
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">Wallet tujuan</span>
            <span class="ftv-voucher__v">{{ $walletName }}</span>
        </div>
        <div class="ftv-voucher__row">
            <span class="ftv-voucher__k">No. Rekening</span>
            <span class="ftv-voucher__v ftv-voucher__v--mono">{{ $noRek ?: '—' }}</span>
        </div>
        @if($req->isApproved())
            <div class="ftv-voucher__row ftv-voucher__row--hi">
                <span class="ftv-voucher__k">Nominal disetujui</span>
                <span class="ftv-voucher__v">{{ $rupiah($req->amount_approved ?? $req->amount_requested) }}</span>
            </div>
        @endif
        @if($req->description)
            <div class="ftv-voucher__row ftv-voucher__row--stack">
                <span class="ftv-voucher__k">Keterangan</span>
                <span class="ftv-voucher__v">{{ $req->description }}</span>
            </div>
        @endif
        @if($req->isRejected() && $req->rejected_reason)
            <div class="ftv-voucher__row ftv-voucher__row--stack">
                <span class="ftv-voucher__k">Alasan ditolak</span>
                <span class="ftv-voucher__v ftv-voucher__v--no">{{ $req->rejected_reason }}</span>
            </div>
        @elseif($req->isApproved() && $req->approval_note)
            <div class="ftv-voucher__row ftv-voucher__row--stack">
                <span class="ftv-voucher__k">Catatan approval</span>
                <span class="ftv-voucher__v">{{ $req->approval_note }}</span>
            </div>
        @endif
        @if($req->reviewed_at)
            <div class="ftv-voucher__row">
                <span class="ftv-voucher__k">Direview oleh</span>
                <span class="ftv-voucher__v">{{ $req->reviewer?->nama ?? '-' }} · {{ $req->reviewed_at->translatedFormat('d M Y, H:i') }}</span>
            </div>
        @endif
    </div>

    <div class="ftv-voucher__foot">Dashboard Monitoring Petty Cash · Sweet Treats</div>
</div>

@once
<style>
/* Stage off-screen — node tetap ter-layout penuh untuk html2canvas.
   Jangan pakai width/height 0 atau overflow:hidden (bisa meng-clip
   pengukuran node saat capture). Posisi negatif sudah cukup menyembunyikan. */
.ftv-stage { position: fixed; left: -10000px; top: 0; pointer-events: none; }

.ftv-voucher {
    box-sizing: border-box;
    width: 420px;
    background: #ffffff;
    border: 1px solid #ead9c1;
    border-radius: 16px;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #3B1500;
}
.ftv-voucher * { box-sizing: border-box; }

.ftv-voucher__head {
    padding: 20px 22px 16px;
    background: #461500;
    color: #FFEAD6;
    text-align: center;
}
.ftv-voucher__brand { font-size: 21px; font-weight: 800; letter-spacing: .2px; }
.ftv-voucher__brandsub { margin-top: 3px; font-size: 12.5px; font-weight: 600; color: #E7C9AE; }

.ftv-voucher__statusrow {
    display: flex; align-items: center; justify-content: flex-end;
    padding: 14px 22px 0;
}
.ftv-voucher__no { font-size: 12px; font-weight: 700; color: #9A7256; }

.ftv-voucher__amount {
    margin: 12px 22px 0; padding: 12px 16px;
    background: #FDF6E7; border: 1px solid #ead9c1; border-radius: 12px;
    display: flex; align-items: baseline; justify-content: space-between;
}
.ftv-voucher__amount-label { font-size: 12px; font-weight: 600; color: #9A7256; }
.ftv-voucher__amount-val { font-size: 24px; font-weight: 800; color: #461500; font-variant-numeric: tabular-nums; }

.ftv-voucher__rows { padding: 8px 22px 4px; }
.ftv-voucher__row {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
    padding: 9px 0; border-bottom: 1px solid #f4eadd;
}
.ftv-voucher__row:last-child { border-bottom: none; }
.ftv-voucher__row--stack { flex-direction: column; gap: 3px; }
.ftv-voucher__row--hi .ftv-voucher__v { color: #166534; font-weight: 800; }
.ftv-voucher__k { font-size: 12px; font-weight: 700; color: #9A7256; white-space: nowrap; }
.ftv-voucher__v { font-size: 13.5px; font-weight: 600; color: #3B1500; text-align: right; }
.ftv-voucher__row--stack .ftv-voucher__v { text-align: left; font-weight: 500; }
.ftv-voucher__v--mono { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: .3px; }
.ftv-voucher__v--no { color: #b91c1c; }

.ftv-voucher__foot {
    padding: 12px 22px 16px;
    font-size: 10.5px; color: #b09a86; text-align: center;
    border-top: 1px solid #f4eadd; margin-top: 4px;
}
</style>
@endonce
