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
    $categoryMap = \App\Models\PettyCashTransaction::categoryMap();
    // Window edit/hapus: Super Admin bebas; user biasa dibatasi N jam.
    $pcIsSuperAdmin  = isSuperAdmin();
    $pcEditWindowHrs = \App\Models\PettyCashTransaction::EDIT_WINDOW_HOURS;
    // Chip "dicatat oleh" — hanya aktif saat ada >1 pencatat berbeda di
    // periode ini (mis. rolling/backup). Default aman kalau tak dikirim.
    $recorderNames = $recorderNames ?? collect();
    $showRecorder  = $showRecorder ?? false;
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
        <td class="pc-date">{{ \Carbon\Carbon::parse($r->trx_date)->translatedFormat('d M Y') }}</td>
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
                <i class="fa-solid fa-random" style="color:#16a34a;"></i>
            @elseif($r->is_transfer && ! $isComposite)
                <i class="fa-solid fa-exchange-alt" style="color:#2563eb;"></i>
            @endif
            <strong>{{ $r->description }}</strong>
            @if(!empty($r->category) && isset($categoryMap[$r->category]))
                @php
                    // Baris pinjaman punya 3 varian tampilan:
                    //   - cash-in (pengembalian)             → pill "Pengembalian"
                    //   - cash-out dengan status (loan_status) → pill "Pinjaman"
                    //       + status LUNAS / SEBAGIAN / BELUM LUNAS + sisa
                    //   - kategori lain                       → pill kategori biasa
                    $isLoanReturn = $r->category === 'pinjaman' && ($r->cash_in ?? 0) > 0;
                @endphp
                @if($isLoanReturn)
                    <span class="pc-pill-category pc-pill-category--lunas">Pengembalian</span>
                @else
                    <span class="pc-pill-category pc-pill-category--{{ $r->category }}">{{ $categoryMap[$r->category] }}</span>
                    @if(!empty($r->loan_status))
                        @if($r->loan_status === 'lunas')
                            <span class="pc-pill-loan pc-pill-loan--lunas">Lunas</span>
                        @elseif($r->loan_status === 'sebagian')
                            <span class="pc-pill-loan pc-pill-loan--sebagian"
                                  title="Sudah dikembalikan Rp {{ number_format($r->loan_paid, 0, ',', '.') }} dari Rp {{ number_format($r->cash_out, 0, ',', '.') }}">
                                Sebagian · sisa Rp {{ number_format($r->loan_sisa, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="pc-pill-loan pc-pill-loan--belum">Belum Lunas</span>
                        @endif
                    @endif
                @endif
                @if(!empty($r->borrower_name) && ! $isLoanReturn)
                    <span class="pc-loan-borrower"><i class="fa fa-user"></i>{{ $r->borrower_name }}</span>
                @endif
            @endif
            @if($r->is_topup)
                <span class="pc-pill-topup">top up</span>
            @elseif($r->is_transfer)
                <span class="pc-pill-transfer">{{ $pillLabel }}</span>
            @endif
            @if($showRecorder && !empty($r->created_by) && !empty($recorderNames[$r->created_by]))
                <span class="pc-pill-recorder" title="Dicatat oleh {{ $recorderNames[$r->created_by] }}"><i class="fa fa-user-edit"></i>{{ $recorderNames[$r->created_by] }}</span>
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
        @if($showTrxActions)
            @php
                $delAmount = number_format(max($r->cash_in, $r->cash_out), 0, ',', '.');
                if ($isComposite) {
                    $delMsg = "PERHATIAN: Anda akan menghapus KEDUA sisi transfer ini "
                        . "(uang keluar Rp {$delAmount} dari " . ($r->source_name ?? '-')
                        . " DAN uang masuk Rp {$delAmount} ke " . (($r->source_to->name ?? '') ?: '-')
                        . "). Saldo kedua sumber akan berubah.\n\nTransaksi akan pindah ke tab Riwayat. Lanjutkan hapus?";
                } else {
                    $delType = $r->is_topup ? 'Top Up' : ($r->cash_in > 0 ? 'Pemasukan' : 'Pengeluaran');
                    $delMsg = "Hapus transaksi {$delType} Rp {$delAmount} dari "
                        . ($r->source_name ?? '-') . "?\n\nTransaksi akan pindah ke tab Riwayat.";
                }
            @endphp
            @php
                // Edit hanya untuk pengeluaran biasa (bukan topup / transfer)
                // dan dalam window sejak created_at. Super Admin bebas tanpa
                // batas waktu. Guard yang sama di-enforce di controller — UI
                // cuma sembunyikan tombol untuk discoverability.
                $inWindow = $pcIsSuperAdmin || (
                    !empty($r->created_at)
                    && \Carbon\Carbon::parse($r->created_at)->gt(now()->subHours($pcEditWindowHrs))
                );
                $canEdit = !$isComposite
                    && !$r->is_topup
                    && !$r->is_transfer
                    && $r->cash_out > 0
                    && $inWindow;
                // Hapus (semua jenis: pengeluaran / top up / transfer) juga
                // dibatasi window yang sama — server meng-enforce ulang di
                // destroy() (abortIfBeyondEditWindow).
                $canDelete = $inWindow;
                // Teks tooltip window (dipakai di tombol edit & hapus).
                $windowHint = $pcIsSuperAdmin ? 'tanpa batas waktu' : "dalam {$pcEditWindowHrs} jam";
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
                                data-trx-borrower="{{ $r->borrower_name }}"
                                data-trx-source-id="{{ $r->source_id }}"
                                data-trx-source-name="{{ $r->source_name }}"
                                data-trx-attachment="{{ !empty($r->attachment_path) ? asset($r->attachment_path) : '' }}"
                                @unless($pcIsSuperAdmin)@if(!empty($r->created_at))data-trx-edit-deadline="{{ \Carbon\Carbon::parse($r->created_at)->addHours($pcEditWindowHrs)->timestamp }}"@endif @endunless
                                title="Edit transaksi ({{ $windowHint }})">
                            <i class="fa fa-pen"></i>
                        </button>
                    @endif
                    @if($canDelete)
                        <form method="POST" action="{{ route('pettycash.destroy', ['id' => $r->id, 'tab' => $kind]) }}"
                              onsubmit="return confirm({{ \Illuminate\Support\Js::from($delMsg) }})">
                            @csrf
                            @method('DELETE')
                            @include('crud.pettycash._filter_hidden')
                            @if(!session('raw_branch_id') && $branchId)
                                <input type="hidden" name="branch" value="{{ $branchId }}">
                            @endif
                            <button type="submit" class="pc-icon-btn"
                                    title="{{ $isComposite ? 'Hapus transfer (kedua sisi, ' . $windowHint . ')' : 'Hapus transaksi (' . $windowHint . ')' }}">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    @endif
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
                @if($showTrxActions)<td></td>@endif
            </tr>
        @endforeach
    @endif
@empty
    {{-- Kalau ada baris "dompet operator lain" yang dirender di atas (shared
         list), JANGAN tampilkan empty-state besar — biar tidak kontradiktif
         (baris di atas + "belum ada pengeluaran"). --}}
    @unless($suppressEmpty ?? false)
    <tr>
        <td colspan="{{ $showTrxActions ? 7 : 6 }}">
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
    @endunless
@endforelse
