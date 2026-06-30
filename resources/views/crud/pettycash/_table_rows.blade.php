{{--
    Partial: render rows tabel transaksi petty cash.

    Dipakai untuk 2 sub-tab di halaman utama:
      - Tab "regular"  → top up + pengeluaran + setor cash
      - Tab "transfer" → semua transfer composite + child rows

    Variables:
      - $rowsInput        : collection / array baris yang dirender
      - $isReadOnly       : bool — sembunyikan tombol hapus
      - $kind             : 'petty_cash'
      - $branchId         : id cabang aktif (untuk hidden input form delete)
      - $tabKind          : 'regular' | 'transfer' — penentu copy empty state
      - $emptyTitle       : judul empty state
      - $emptyDesc        : Blade-safe text/markup deskripsi (string/htmlable)
--}}
@php
    // Resolve category label map sekali, dipakai untuk render badge kategori
    // di kolom keterangan. Map kosong → tidak render badge (transfer/topup
    // umumnya tidak punya kategori).
    $categoryMap = \App\Models\PettyCashTransaction::CATEGORIES;
@endphp
@forelse($rowsInput as $r)
    @php
        $isComposite  = ! empty($r->is_composite);
        $isTarikTunai = $r->is_transfer
            && \Illuminate\Support\Str::startsWith((string) $r->description, 'Tarik tunai');
        $isDeposit    = $r->is_transfer
            && \Illuminate\Support\Str::startsWith((string) $r->description, 'Deposit');
        $pillLabel    = $isTarikTunai ? 'tarik tunai' : ($isDeposit ? 'deposit' : 'transfer');
        $rowId        = 'pcrow-' . ($tabKind ?? 'r') . '-' . ($r->ref_no ?: $r->id);
    @endphp
    <tr class="{{ $r->is_topup ? 'pc-row-topup' : '' }} {{ $r->is_transfer ? 'pc-row-transfer' : '' }} {{ $isComposite ? 'pc-row-composite' : '' }}"
        @if($isComposite) data-pc-composite-parent="{{ $rowId }}" @endif>
        <td class="pc-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M') }}</td>
        <td>
            @if($isComposite && isset($r->source_to))
                <span class="pc-source-inline pc-source-inline--pair">
                    @include('crud.pettycash._fund_source_badge', [
                        'color' => $r->source_color,
                        'short' => $r->source_short,
                        'logo'  => $r->source_logo,
                        'name'  => $r->source_name,
                        'size'  => 'sm',
                    ])
                    <i class="fa fa-arrow-right pc-source-inline__arrow"></i>
                    @include('crud.pettycash._fund_source_badge', [
                        'color' => $r->source_to->color,
                        'short' => $r->source_to->short,
                        'logo'  => $r->source_to->logo,
                        'name'  => $r->source_to->name,
                        'size'  => 'sm',
                    ])
                </span>
            @elseif($r->source_name)
                <span class="pc-source-inline">
                    @include('crud.pettycash._fund_source_badge', [
                        'color' => $r->source_color,
                        'short' => $r->source_short,
                        'logo'  => $r->source_logo,
                        'name'  => $r->source_name,
                        'size'  => 'sm',
                    ])
                    <span class="pc-source-inline__name">{{ $r->source_name }}</span>
                </span>
            @else
                <span class="pc-muted">—</span>
            @endif
        </td>
        <td>
            @if($isComposite)
                <button type="button" class="pc-row-toggle"
                        data-pc-toggle-target="{{ $rowId }}"
                        aria-expanded="false"
                        title="Lihat detail">
                    <i class="fa fa-chevron-right"></i>
                </button>
            @endif
            @if($r->is_topup)
                <i class="fa-solid fa-arrow-down-up-across-line" style="color:#16a34a;"></i>
            @elseif($r->is_transfer && ! $isComposite)
                <i class="fa-solid fa-right-left" style="color:#2563eb;"></i>
            @endif
            <strong>{{ $r->description }}</strong>
            @if(!empty($r->category) && isset($categoryMap[$r->category]))
                <span class="pc-pill-category">{{ $categoryMap[$r->category] }}</span>
            @endif
            @if($r->is_topup)
                <span class="pc-pill-topup">top up</span>
            @elseif($r->is_transfer)
                <span class="pc-pill-transfer">{{ $pillLabel }}</span>
            @endif
            @if(!empty($r->edited_at))
                <span class="pc-pill-edited" title="Terakhir diedit {{ \Carbon\Carbon::parse($r->edited_at)->translatedFormat('d M Y H:i') }}">diedit</span>
            @endif
            @if(!empty($r->attachment_path))
                @php
                    $attachTitle = $r->is_topup
                        ? 'Lihat bukti top up'
                        : ($r->is_transfer
                            ? 'Lihat bukti mutasi'
                            : 'Lihat bukti pengeluaran');
                @endphp
                <button type="button"
                        class="pc-attach-icon"
                        data-pc-attach-url="{{ asset($r->attachment_path) }}"
                        data-pc-attach-ext="{{ strtolower(pathinfo($r->attachment_path, PATHINFO_EXTENSION)) }}"
                        title="{{ $attachTitle }}">
                    <i class="fa fa-paperclip"></i>
                </button>
            @endif
        </td>
        <td class="text-right pc-cash-in {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
            {{ $isComposite ? '—' : ($r->cash_in > 0 ? number_format($r->cash_in, 0, ',', '.') : '—') }}
        </td>
        <td class="text-right pc-cash-out {{ $r->is_transfer ? 'pc-cash--internal' : '' }}">
            {{ $isComposite ? '—' : ($r->cash_out > 0 ? number_format($r->cash_out, 0, ',', '.') : '—') }}
        </td>
        <td class="text-right pc-saldo-cell">{{ number_format($r->saldo, 0, ',', '.') }}</td>
        @if(!$isReadOnly)
            @php
                $delAmount = number_format(max($r->cash_in, $r->cash_out), 0, ',', '.');
                if ($isComposite) {
                    $delMsg = "PERHATIAN: Anda akan menghapus KEDUA sisi transfer ini "
                        . "(uang keluar Rp {$delAmount} dari " . ($r->source_name ?? '-')
                        . " DAN uang masuk Rp {$delAmount} ke " . (($r->source_to->name ?? '') ?: '-')
                        . "). Saldo kedua sumber akan berubah. Tindakan ini TIDAK BISA di-undo.\n\nLanjutkan hapus?";
                } else {
                    $delType = $r->is_topup ? 'Top Up' : ($r->cash_in > 0 ? 'Pemasukan' : 'Pengeluaran');
                    $delMsg = "Hapus transaksi {$delType} Rp {$delAmount} dari "
                        . ($r->source_name ?? '-') . "?\n\nTindakan ini TIDAK BISA di-undo.";
                }
            @endphp
            @php
                // Edit hanya untuk pengeluaran biasa (bukan topup / transfer)
                // dan dalam 24 jam sejak created_at. Guard yang sama di-enforce
                // di controller — UI cuma sembunyikan tombol untuk discoverability.
                $canEdit = !$isComposite
                    && !$r->is_topup
                    && !$r->is_transfer
                    && $r->cash_out > 0
                    && !empty($r->created_at)
                    && \Carbon\Carbon::parse($r->created_at)->gt(now()->subHours(24));
            @endphp
            <td>
                <div class="pc-row-actions">
                    @if($canEdit)
                        <button type="button" class="pc-icon-btn"
                                data-pc-edit-trx
                                data-trx-id="{{ $r->id }}"
                                data-trx-date="{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M Y') }}"
                                data-trx-amount="{{ $r->cash_out }}"
                                data-trx-description="{{ $r->description }}"
                                data-trx-category="{{ $r->category }}"
                                data-trx-source-id="{{ $r->source_id }}"
                                data-trx-source-name="{{ $r->source_name }}"
                                title="Edit transaksi (dalam 24 jam)">
                            <i class="fa fa-pen"></i>
                        </button>
                    @endif
                    <form method="POST" action="{{ route('pettycash.destroy', ['id' => $r->id, 'tab' => $kind]) }}"
                          onsubmit="return confirm({{ \Illuminate\Support\Js::from($delMsg) }})">
                        @csrf
                        @method('DELETE')
                        @if(!session('raw_branch_id') && $branchId)
                            <input type="hidden" name="branch" value="{{ $branchId }}">
                        @endif
                        <button type="submit" class="pc-icon-btn"
                                title="{{ $isComposite ? 'Hapus transfer (kedua sisi)' : 'Hapus transaksi' }}">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </div>
            </td>
        @endif
    </tr>
    @if($isComposite && !empty($r->children))
        @foreach($r->children as $child)
            <tr class="pc-row-transfer pc-row-child" data-pc-composite-child="{{ $rowId }}" hidden>
                <td class="pc-date pc-row-child__date">↳</td>
                <td>
                    @if($child->source_name)
                        <span class="pc-source-inline">
                            @include('crud.pettycash._fund_source_badge', [
                                'color' => $child->source_color,
                                'short' => $child->source_short,
                                'logo'  => $child->source_logo,
                                'name'  => $child->source_name,
                                'size'  => 'sm',
                            ])
                            <span class="pc-source-inline__name">{{ $child->source_name }}</span>
                        </span>
                    @endif
                </td>
                <td class="pc-row-child__desc">
                    <i class="fa fa-arrow-{{ $child->cash_in > 0 ? 'down-long' : 'up-long' }}"
                       style="color: {{ $child->cash_in > 0 ? '#16a34a' : '#dc2626' }};"></i>
                    {{ $child->description }}
                </td>
                <td class="text-right pc-cash-in pc-cash--internal">
                    {{ $child->cash_in > 0 ? number_format($child->cash_in, 0, ',', '.') : '—' }}
                </td>
                <td class="text-right pc-cash-out pc-cash--internal">
                    {{ $child->cash_out > 0 ? number_format($child->cash_out, 0, ',', '.') : '—' }}
                </td>
                <td class="text-right pc-saldo-cell pc-muted">—</td>
                @if(!$isReadOnly)<td></td>@endif
            </tr>
        @endforeach
    @endif
@empty
    <tr>
        <td colspan="{{ $isReadOnly ? 6 : 7 }}">
            <div class="pc-empty-state">
                <div class="pc-empty-state__icon">
                    <i class="fa {{ $emptyIcon ?? 'fa-folder-open' }}"></i>
                </div>
                <div class="pc-empty-state__title">{{ $emptyTitle ?? 'Belum ada transaksi' }}</div>
                <div class="pc-empty-state__desc">
                    {!! $emptyDesc ?? '' !!}
                </div>
                @if(!empty($emptyCta))
                    <div class="pc-empty-state__cta">{!! $emptyCta !!}</div>
                @endif
            </div>
        </td>
    </tr>
@endforelse
