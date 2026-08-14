{{--
    Modal: Rincian Pengajuan Top Up yang Menunggu (read-only).
    Dibuka dari badge "N pengajuan menunggu Finance" di _summary_cards.

    Variables:
      $pendingTopups (collection<TopupRequest>, eager-load fundSource)
      $rupiah        (closure)
      $activeBranch  (RawBranch|null)

    Hanya menampilkan status — cabang tidak bisa approve/reject di sini
    (itu ranah Finance). Tujuannya biar user tahu pengajuan mana yang
    sedang ditunggu, berapa, ke wallet mana, dan sejak kapan.
--}}
@php
    $pendingTopups = $pendingTopups ?? collect();
    $activeBranch  = $activeBranch ?? null;
@endphp
<div class="pc-modal" id="pcModalPendingTopups" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <div class="pc-modal__header pc-modal__header--wallet">
            <span class="pc-modal__icon-box" aria-hidden="true">
                <i class="fa fa-clock"></i>
            </span>
            <div class="pc-modal__heading">
                <h5 class="pc-modal__title">Pengajuan Top Up Menunggu</h5>
                <small class="pc-muted">
                    Menunggu persetujuan Finance
                    @if($activeBranch)
                        &middot; cabang <strong>{{ $activeBranch->name }}</strong>
                    @endif
                </small>
            </div>
            <button type="button" class="pc-modal__close pc-modal__close--boxed" data-pc-modal-close aria-label="Tutup">&times;</button>
        </div>

        <div class="pc-modal__body">
            <div class="pc-tab-hint pc-tab-hint--deposit">
                <span>Pengajuan berikut sudah terkirim dan sedang ditinjau Finance. Begitu disetujui, top-up otomatis tercatat di kas cabang ini.</span>
            </div>

            @if($pendingTopups->isEmpty())
                <div class="ppt-empty">
                    <div class="ppt-empty__icon"><i class="fa fa-check"></i></div>
                    <div class="ppt-empty__title">Tidak ada pengajuan menunggu</div>
                    <div class="ppt-empty__desc">Semua pengajuan top-up cabang ini sudah ditindaklanjuti Finance.</div>
                </div>
            @else
                <div class="ppt-list">
                    @foreach($pendingTopups as $req)
                        @php
                            $typedName = trim((string) $req->requester_name);
                        @endphp
                        <div class="ppt-card {{ $req->is_urgent ? 'ppt-card--urgent' : '' }}">
                            <div class="ppt-card__top">
                                <span class="ppt-card__amount">{{ $rupiah($req->amount_requested) }}</span>
                                <span class="ppt-card__status">
                                    <i class="fa fa-clock"></i> Menunggu Finance
                                </span>
                            </div>
                            <div class="ppt-card__meta">
                                <span class="ppt-card__wallet">
                                    <i class="fa fa-wallet"></i> {{ $req->fundSource?->name ?? '—' }}
                                </span>
                                @if($typedName !== '')
                                    <span class="ppt-card__sep">·</span>
                                    <span class="ppt-card__requester"><i class="fa fa-user"></i> {{ $typedName }}</span>
                                @endif
                                @if($req->requested_at)
                                    <span class="ppt-card__sep">·</span>
                                    <span class="ppt-card__time" title="Waktu pengajuan">
                                        <i class="fa fa-paper-plane"></i> {{ $req->requested_at->translatedFormat('d M Y, H:i') }}
                                    </span>
                                @endif
                            </div>
                            <div class="ppt-card__flags">
                                @if($req->is_urgent)
                                    <span class="ppt-card__flag ppt-card__flag--urgent"><i class="fa fa-bolt"></i> DARURAT</span>
                                @endif
                                @if($req->needed_by)
                                    <span class="ppt-card__flag ppt-card__flag--needed">
                                        <i class="fa fa-calendar-day"></i> Butuh: {{ $req->needed_by->translatedFormat('d M Y') }}
                                    </span>
                                @endif
                            </div>
                            @if($req->description)
                                <div class="ppt-card__desc">
                                    <i class="fa fa-quote-left"></i> {{ $req->description }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="pc-modal__footer">
            <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Tutup</button>
        </div>
    </div>
</div>

<style>
/* ── Modal "Pengajuan Top Up Menunggu" (read-only) ── */
#pcModalPendingTopups .ppt-list { display: flex; flex-direction: column; gap: 10px; }
#pcModalPendingTopups .ppt-card {
    padding: 12px 14px;
    background: #fff;
    border: 1px solid #EFE0CE;
    border-left: 4px solid #f59e0b;
    border-radius: 12px;
}
#pcModalPendingTopups .ppt-card--urgent { border-left-color: #dc2626; }
#pcModalPendingTopups .ppt-card__top {
    display: flex; align-items: baseline; justify-content: space-between;
    gap: 10px; flex-wrap: wrap; margin-bottom: 6px;
}
#pcModalPendingTopups .ppt-card__amount {
    font-size: 20px; font-weight: 800; color: #3B1500;
    font-variant-numeric: tabular-nums;
}
#pcModalPendingTopups .ppt-card__status {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 2px 9px; border-radius: 999px;
    background: #fef3c7; color: #92400e; border: 1px solid #fde68a;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
#pcModalPendingTopups .ppt-card__status i { font-size: 9px; }
#pcModalPendingTopups .ppt-card__meta {
    display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
    font-size: 12.5px; color: #9A7256;
}
#pcModalPendingTopups .ppt-card__wallet { font-weight: 700; color: #5c1f00; }
#pcModalPendingTopups .ppt-card__meta i { font-size: 10px; color: #A8481B; }
#pcModalPendingTopups .ppt-card__sep { color: #d9c4ab; }
#pcModalPendingTopups .ppt-card__flags {
    display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;
}
#pcModalPendingTopups .ppt-card__flag {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 999px;
    font-size: 10.5px; font-weight: 700;
}
#pcModalPendingTopups .ppt-card__flag i { font-size: 9px; }
#pcModalPendingTopups .ppt-card__flag--urgent {
    background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; letter-spacing: .3px;
}
#pcModalPendingTopups .ppt-card__flag--needed {
    background: #fdf6e7; color: #92400e; border: 1px solid #f3e2bd;
}
#pcModalPendingTopups .ppt-card__desc {
    margin-top: 8px; font-size: 12.5px; color: #64748b; font-style: italic; line-height: 1.45;
}
#pcModalPendingTopups .ppt-card__desc i { font-size: 9px; margin-right: 4px; color: #cbd5e1; }

/* Empty state */
#pcModalPendingTopups .ppt-empty {
    padding: 28px 20px; text-align: center;
    background: #fff; border: 1px dashed #EFE0CE; border-radius: 12px;
}
#pcModalPendingTopups .ppt-empty__icon {
    width: 48px; height: 48px; margin: 0 auto 12px;
    border-radius: 50%; background: #dcfce7; color: #16a34a;
    font-size: 20px; display: inline-flex; align-items: center; justify-content: center;
}
#pcModalPendingTopups .ppt-empty__title { font-size: 14px; font-weight: 800; color: #3B1500; margin-bottom: 4px; }
#pcModalPendingTopups .ppt-empty__desc { font-size: 12.5px; color: #9A7256; max-width: 320px; margin: 0 auto; line-height: 1.5; }
</style>
