{{--
    Finance dashboard — 20 request terakhir yang sudah di-review.
    Variables: $rupiah, $recentReviewed (collection<TopupRequest>)
--}}
@php
    // Helper: warna avatar berdasarkan inisial nama
    $avatarColors = ['#8A3A12', '#A8481B', '#2F8E55', '#3C5FA8', '#B5701A', '#7A3415', '#5A6B2E', '#8E2419'];
    $avatarBg = function ($name) use ($avatarColors) {
        if (! $name) return '#94a3b8';
        $sum = 0;
        for ($i = 0; $i < strlen($name); $i++) $sum += ord($name[$i]);
        return $avatarColors[$sum % count($avatarColors)];
    };
@endphp
<div class="fpc-section">
    <div class="fpc-section__head">
        <h5 class="fpc-section__title">
            <i class="fa fa-history"></i> Riwayat Review Terakhir
        </h5>
        @if(isSuperAdmin() && ! $recentReviewed->isEmpty())
            {{-- Reset riwayat review (hard delete pengajuan yang sudah di-review) — hanya Super Admin. --}}
            <form method="POST" action="{{ route('finance.pettycash.review.reset') }}"
                  onsubmit="return confirm('Reset SEMUA riwayat review?\n\n• Seluruh pengajuan yang sudah di-review (approved/rejected) dihapus PERMANEN.\n• Antrian pending & transaksi petty cash TIDAK terpengaruh.\n• Aksi TIDAK bisa di-undo.\n\nLanjut?');"
                  style="margin:0;">
                @csrf
                <button type="submit" class="fpc-reset-btn" title="Reset riwayat review (permanen)">
                    <i class="fa fa-trash"></i> Reset Riwayat
                </button>
            </form>
        @endif
    </div>

    @if($recentReviewed->isEmpty())
        <div class="fpc-empty">
            <div class="fpc-empty__icon"><i class="fa fa-history"></i></div>
            <div class="fpc-empty__title">Belum ada riwayat</div>
            <div class="fpc-empty__desc">Setelah Anda meng-approve atau reject pengajuan, jejaknya akan tampil di sini.</div>
        </div>
    @else
        <div class="fpc-audit-card">
            <table class="fpc-audit-table">
                <thead>
                    <tr>
                        <th width="130">Direview</th>
                        <th width="110">Status</th>
                        <th>Cabang · Pemohon</th>
                        <th>Wallet</th>
                        <th class="text-right" width="140">Nominal</th>
                        <th>Catatan</th>
                        <th width="140">Reviewer</th>
                        <th width="60" class="text-center">Gambar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentReviewed as $r)
                        @php
                            $walletName  = $r->fundSource?->name ?? '-';
                            $walletShort = $r->fundSource?->effectiveShortLabel() ?? '?';
                            $walletColor = $r->fundSource?->effectiveBrandColor() ?? '#64748b';
                            $reviewerNm  = $r->reviewer?->nama ?? '-';
                            $reviewerInt = strtoupper(mb_substr($reviewerNm, 0, 1));
                        @endphp
                        <tr>
                            <td class="fpc-audit-table__date">{{ $r->reviewed_at?->translatedFormat('d M, H:i') ?? '-' }}</td>
                            <td>
                                @if($r->isApproved())
                                    <span class="fpc-status fpc-status--ok"><i class="fa fa-check"></i> Approved</span>
                                @else
                                    <span class="fpc-status fpc-status--no"><i class="fa fa-times"></i> Rejected</span>
                                @endif
                            </td>
                            <td>
                                <div class="fpc-audit-table__branch">{{ $r->branch?->name ?? '-' }}</div>
                                <div class="fpc-audit-table__sub">{{ $r->requester?->nama ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="fpc-wallet-cell">
                                    <span class="fpc-wallet-badge" style="background: {{ $walletColor }};">{{ $walletShort }}</span>
                                    {{ $walletName }}
                                </span>
                            </td>
                            <td class="text-right fpc-audit-table__amount">{{ $rupiah($r->amount_requested) }}</td>
                            <td>
                                @if($r->isRejected() && $r->rejected_reason)
                                    <span style="color:#b91c1c;">{{ $r->rejected_reason }}</span>
                                @elseif($r->approval_note)
                                    {{ $r->approval_note }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="fpc-reviewer-cell">
                                    {{ $reviewerNm }}
                                    <span class="fpc-reviewer-avatar" style="background: {{ $avatarBg($reviewerNm) }};">{{ $reviewerInt }}</span>
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="fpc-img-btn"
                                        data-ftv-download="{{ $r->id }}"
                                        data-ftv-filename="pengajuan-topup_{{ \Illuminate\Support\Str::slug($r->branch?->name ?? 'cabang') }}_{{ optional($r->requested_at)->format('Ymd') }}_{{ $r->id }}.png"
                                        title="Unduh detail sebagai gambar" aria-label="Unduh gambar">
                                    <i class="fa fa-download"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<style>
.fpc-reset-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    background: #fff; color: #b91c1c;
    border: 1px solid #f3c9c9; border-radius: 999px;
    font-size: 12px; font-weight: 700; cursor: pointer;
    white-space: nowrap; transition: background .15s, border-color .15s;
}
.fpc-reset-btn:hover { background: #fef2f2; border-color: #dc2626; color: #b91c1c; }
.fpc-reset-btn i { font-size: 11px; }
.fpc-audit-card {
    background: #fff;
    border: 1px solid var(--divider-soft);
    border-radius: 12px;
    overflow: hidden;
}
.fpc-audit-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.fpc-audit-table thead th {
    background: var(--brown);
    color: #fff;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-weight: 800;
    padding: 12px 14px;
    text-align: left;
}
.fpc-audit-table thead th.text-right { text-align: right; }
.fpc-audit-table tbody td {
    padding: 14px;
    border-bottom: 1px solid #f5ece0;
    vertical-align: middle;
    color: #334155;
}
.fpc-audit-table tbody tr:last-child td { border-bottom: none; }
.fpc-audit-table tbody tr:hover { background: #fffaf2; }

.fpc-audit-table__date { font-size: 12px; color: #475569; font-weight: 600; white-space: nowrap; }
.fpc-audit-table__branch { font-weight: 700; color: var(--brown); }
.fpc-audit-table__sub { font-size: 11.5px; color: var(--text-muted); margin-top: 1px; }
.fpc-audit-table__amount { font-weight: 700; color: #0f172a; }

.fpc-status {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px;
    font-size: 10.5px; font-weight: 800;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.fpc-status--ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
.fpc-status--no { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
.fpc-status i { font-size: 9px; }

.fpc-wallet-cell {
    display: inline-flex; align-items: center; gap: 8px;
    font-weight: 600; color: #334155;
}
.fpc-wallet-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 32px; height: 22px; padding: 0 7px;
    border-radius: 6px;
    color: #fff;
    font-size: 9.5px; font-weight: 800;
    letter-spacing: 0.4px;
}

.fpc-reviewer-cell {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 12.5px; color: #475569;
}
.fpc-reviewer-avatar {
    display: inline-flex; align-items: center; justify-content: center;
    width: 26px; height: 26px;
    border-radius: 50%;
    color: #fff;
    font-size: 11px; font-weight: 800;
}

.fpc-audit-table thead th.text-center { text-align: center; }
.fpc-audit-table td.text-center { text-align: center; }
.fpc-img-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px;
    background: #fff; color: var(--brown);
    border: 1px solid var(--divider-soft); border-radius: 8px;
    cursor: pointer; transition: background .15s, border-color .15s;
}
.fpc-img-btn:hover { background: #fdf9f3; border-color: var(--brown-2); }
.fpc-img-btn i { font-size: 12px; }
</style>
