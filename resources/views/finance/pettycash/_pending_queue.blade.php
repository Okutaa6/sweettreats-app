{{--
    Finance dashboard — antrian pengajuan top up dari Sales.
    Variables: $rupiah, $pendingRequests (collection<TopupRequest>)
--}}
<div class="fpc-section">
    <div class="fpc-section__head">
        <h5 class="fpc-section__title">
            <i class="fa fa-inbox"></i> Antrian Pengajuan Top Up
            <span class="fpc-title-count {{ $pendingRequests->isEmpty() ? 'is-zero' : '' }}">{{ $pendingRequests->count() }}</span>
        </h5>
        <small class="fpc-section__sub fpc-section__sub--italic">Tinjau &amp; setujui permintaan dana dari cabang</small>
    </div>

    @if($pendingRequests->isEmpty())
        {{-- Empty state — illustrated, friendly --}}
        <div class="fpc-empty fpc-empty--ok">
            <div class="fpc-empty__icon"><i class="fa fa-check"></i></div>
            <div class="fpc-empty__title">Inbox bersih! <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="vertical-align:-.15em"><path d="M8 1.8 9.3 5.6 13 7 9.3 8.4 8 12.2 6.7 8.4 3 7l3.7-1.4L8 1.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M12.8 11.4v2.8M11.4 12.8h2.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
            <div class="fpc-empty__desc">Tidak ada pengajuan top-up yang perlu di-review saat ini. Semua permintaan cabang sudah ditindaklanjuti.</div>
        </div>
    @else
        <div class="fpc-queue-list">
            @foreach($pendingRequests as $req)
                @php
                    // Cari nomor rekening untuk pasangan (cabang, fund_source)
                    // dari tabel fund_source_branch_accounts. Bisa null kalau
                    // belum di-set.
                    $accountNumber = $req->fundSource && $req->raw_branch_id
                        ? $req->fundSource->accountForBranch((string) $req->raw_branch_id)
                        : null;
                @endphp
                <div class="fpc-queue-card">
                    <div class="fpc-queue-card__main">
                        <div class="fpc-queue-card__meta">
                            <span class="fpc-queue-card__branch">{{ $req->branch?->name ?? '-' }}</span>
                            <span class="fpc-queue-card__sep">·</span>
                            <span class="fpc-queue-card__requester">{{ $req->requester?->nama ?? '-' }}</span>
                            <span class="fpc-queue-card__sep">·</span>
                            <span class="fpc-queue-card__time">{{ $req->requested_at?->translatedFormat('d M, H:i') ?? '-' }}</span>
                        </div>
                        <div class="fpc-queue-card__detail">
                            <span class="fpc-queue-card__amount">{{ $rupiah($req->amount_requested) }}</span>
                            <span class="fpc-queue-card__wallet-group">
                                <span class="fpc-queue-card__wallet">
                                    <i class="fa fa-wallet"></i> {{ $req->fundSource?->name ?? '-' }}
                                </span>
                                @if($accountNumber)
                                    <span class="fpc-queue-card__account" title="No. rekening tujuan top up">
                                        <i class="fa fa-credit-card"></i>
                                        <span class="fpc-queue-card__account-num">{{ $accountNumber }}</span>
                                        <button type="button"
                                                class="fpc-queue-card__copy"
                                                data-pc-copy="{{ $accountNumber }}"
                                                title="Salin nomor rekening"
                                                aria-label="Salin nomor rekening">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </span>
                                @else
                                    <span class="fpc-queue-card__account fpc-queue-card__account--missing"
                                          title="Cabang belum mengisi nomor rekening untuk wallet ini">
                                        <i class="fa fa-exclamation-circle"></i> No. rek belum diisi
                                    </span>
                                @endif
                            </span>
                        </div>
                        @if($req->description)
                            <div class="fpc-queue-card__desc">
                                <i class="fa fa-quote-left"></i> {{ $req->description }}
                            </div>
                        @endif
                    </div>
                    <div class="fpc-queue-card__actions">
                        <button type="button" class="fpc-btn fpc-btn--approve"
                                data-fpc-approve
                                data-fpc-detail-url="{{ route('finance.topup.show', $req->id) }}"
                                data-fpc-action-url="{{ route('finance.topup.approve', $req->id) }}">
                            <i class="fa fa-check"></i> Approve
                        </button>
                        <button type="button" class="fpc-btn fpc-btn--reject"
                                data-fpc-reject
                                data-fpc-action-url="{{ route('finance.topup.reject', $req->id) }}"
                                data-fpc-branch-name="{{ $req->branch?->name }}"
                                data-fpc-requester="{{ $req->requester?->nama }}"
                                data-fpc-amount="{{ (int) $req->amount_requested }}">
                            <i class="fa fa-times"></i> Reject
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
.fpc-queue-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 8px;
    border-radius: 11px; background: #f59e0b; color: #fff;
    font-size: 11px; font-weight: 800; margin-left: 4px;
}
/* Inline count badge next to section title — orange when active, muted when 0 */
.fpc-title-count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 22px; padding: 0 8px;
    border-radius: 11px;
    background: #fef3c7; color: #92400e;
    font-size: 11px; font-weight: 800;
}
.fpc-title-count.is-zero { background: #f1f5f9; color: #94a3b8; }

/* Card list instead of table — lebih lapang, modern */
.fpc-queue-list { display: flex; flex-direction: column; gap: 10px; }
.fpc-queue-card {
    display: flex; align-items: center; gap: 16px;
    padding: 14px 18px;
    background: #fff;
    border: 1px solid var(--divider-soft);
    border-left: 4px solid #f59e0b;
    border-radius: 12px;
    transition: box-shadow .15s, border-color .15s;
}
.fpc-queue-card:hover { box-shadow: 0 4px 12px rgba(70,21,0,0.06); }
.fpc-queue-card__main { flex: 1; min-width: 0; }
.fpc-queue-card__meta {
    display: flex; gap: 6px; flex-wrap: wrap;
    font-size: 11.5px; color: var(--text-muted);
    margin-bottom: 4px;
}
.fpc-queue-card__branch { font-weight: 700; color: var(--brown); }
.fpc-queue-card__sep { color: #cbd5e1; }
.fpc-queue-card__detail {
    display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;
}
.fpc-queue-card__amount { font-size: 18px; font-weight: 800; color: #0f172a; }
/* Group wallet + account chip — rapat satu unit visual */
.fpc-queue-card__wallet-group {
    display: inline-flex; align-items: center; gap: 6px;
    flex-wrap: wrap;
}
.fpc-queue-card__wallet {
    font-size: 12px; color: #475569; font-weight: 600;
    display: inline-flex; align-items: center; gap: 5px;
}
.fpc-queue-card__wallet i { color: var(--brown-2); font-size: 11px; }

/* No. rekening — inline tail di samping wallet, cream palette */
.fpc-queue-card__account {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 2px 4px 2px 10px;
    background: #fdf9f3;
    color: var(--brown);
    border: 1px solid #ead9c1;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 700;
    margin-left: -4px;
    font-family: ui-monospace, Menlo, Consolas, monospace;
}
.fpc-queue-card__account i { font-size: 9px; color: var(--brown-2); }
.fpc-queue-card__account-num { letter-spacing: 0.3px; color: #5c1f00; }
.fpc-queue-card__copy {
    display: inline-flex; align-items: center; justify-content: center;
    width: 20px; height: 20px;
    background: #fff; color: var(--brown-2);
    border: 1px solid #ead9c1;
    border-radius: 50%;
    cursor: pointer;
    transition: background .15s, color .15s;
}
.fpc-queue-card__copy:hover { background: var(--brown); color: #fff; border-color: var(--brown); }
.fpc-queue-card__copy.is-copied { background: #16a34a; color: #fff; border-color: #16a34a; }
.fpc-queue-card__copy i { font-size: 9px; }

/* Missing account number — warning chip subtle */
.fpc-queue-card__account--missing {
    background: #fef3c7; color: #92400e; border-color: #fde68a;
    font-family: inherit; letter-spacing: 0;
    padding: 2px 10px;
}
.fpc-queue-card__account--missing i { color: #d97706; font-size: 10px; }

.fpc-queue-card__desc {
    font-size: 12px; color: #64748b; margin-top: 4px; font-style: italic;
    line-height: 1.4;
}
.fpc-queue-card__desc i { font-size: 9px; margin-right: 4px; color: #cbd5e1; }
.fpc-queue-card__actions { display: flex; gap: 8px; flex-shrink: 0; }

.fpc-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    font-size: 12.5px; font-weight: 700;
    border-radius: 8px; cursor: pointer;
    border: 1px solid transparent; transition: all .15s;
    white-space: nowrap;
}
.fpc-btn--approve { background: #16a34a; color: #fff; border-color: #15803d; }
.fpc-btn--approve:hover { background: #15803d; transform: translateY(-1px); }
.fpc-btn--reject  { background: #fff; color: #dc2626; border-color: #fecaca; }
.fpc-btn--reject:hover  { background: #fef2f2; border-color: #fca5a5; }

/* Empty states — friendly, illustrated */
.fpc-empty {
    padding: 36px 24px;
    background: #fff;
    border: 1px dashed var(--divider-soft);
    border-radius: 14px;
    text-align: center;
}
.fpc-empty__icon {
    width: 56px; height: 56px;
    margin: 0 auto 14px;
    border-radius: 50%;
    background: #f1f5f9;
    color: #94a3b8;
    font-size: 24px;
    display: inline-flex; align-items: center; justify-content: center;
}
.fpc-empty--ok .fpc-empty__icon { background: #dcfce7; color: #16a34a; }
.fpc-empty__title { font-size: 15px; font-weight: 800; color: var(--brown); margin-bottom: 4px; }
.fpc-empty__desc  { font-size: 13px; font-weight: 500; color: #64748b; max-width: 360px; margin: 0 auto; line-height: 1.5; }

@media (max-width: 700px) {
    .fpc-queue-card { flex-direction: column; align-items: stretch; }
    .fpc-queue-card__actions { width: 100%; }
    .fpc-queue-card__actions .fpc-btn { flex: 1; justify-content: center; }
}
</style>
