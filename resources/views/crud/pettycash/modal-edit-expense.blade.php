{{--
    Petty Cash / Transport — modal "Edit Pengeluaran".

    Variables:
      $period, $kind

    Window edit di-enforce di controller (24 jam sejak created_at, dan
    hanya untuk pengeluaran biasa). Modal ini di-trigger dari tombol pensil
    di _table_rows.blade.php — JS akan prefill form via data-* attributes.

    Field editable: amount, description, category.
    Field di-lock (read-only display saja, dikirim hidden): trx_date, wallet.
--}}
@php
    // Semua kategori (aktif + nonaktif) supaya transaksi lama yang kategorinya
    // sudah dinonaktifkan tetap muncul & bisa dipilih ulang saat edit.
    $editCategories = \App\Models\PettyCashTransaction::categoryMap();
    $editWindowHours = \App\Models\PettyCashTransaction::EDIT_WINDOW_HOURS;
@endphp
<div class="pc-modal" id="pcModalEditExpense" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog">
        <form method="POST" id="pcEditExpenseForm" enctype="multipart/form-data" data-action-tpl="{{ url('pettycash/transaction') }}/__ID__?tab={{ $kind }}">
            @csrf
            @method('PATCH')
            @include('crud.pettycash._filter_hidden')
            @if(!session('raw_branch_id') && $period->raw_branch_id)
                <input type="hidden" name="branch" value="{{ $period->raw_branch_id }}">
            @endif

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-pen" style="color:#7c2d12;"></i> Edit Pengeluaran
                    </h5>
                    <small class="pc-muted">
                        Boleh edit nominal, kategori, keterangan, dan bukti
                        @if(isSuperAdmin())
                            (Super Admin — tanpa batas waktu).
                        @else
                            (dalam {{ $editWindowHours }} jam sejak input).
                        @endif
                    </small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                {{-- Sisa waktu edit: hitung mundur sampai window {{ $editWindowHours }} jam
                     terkunci. Diisi & di-update oleh JS dari data-trx-edit-deadline.
                     Super Admin tanpa batas waktu → elemen ini tetap hidden. --}}
                <div class="pc-edit-countdown" id="pcEditCountdown" hidden>
                    <i class="fa fa-clock"></i>
                    <span class="pc-edit-countdown__text">
                        Sisa waktu edit: <strong id="pcEditCountdownValue">—</strong>
                    </span>
                </div>

                {{-- Info read-only: tanggal & wallet, supaya user paham field
                     ini di-lock & tahu konteks transaksi mana yang sedang diedit. --}}
                <div class="pc-edit-context">
                    <div class="pc-edit-context__item">
                        <span class="pc-edit-context__label">Tanggal</span>
                        <span class="pc-edit-context__value" id="pcEditTrxDate">—</span>
                    </div>
                    <div class="pc-edit-context__item">
                        <span class="pc-edit-context__label">Wallet</span>
                        <span class="pc-edit-context__value" id="pcEditWallet">—</span>
                    </div>
                </div>
                <small class="pc-muted pc-edit-locked-hint">
                    <i class="fa fa-lock"></i>
                    Tanggal &amp; wallet di-lock. Untuk ubah, hapus transaksi ini lalu buat ulang.
                </small>

                <div class="pc-form-row">
                    <label>Kategori <span class="text-danger">*</span></label>
                    <select name="category" id="pcEditCategory" required class="pc-input">
                        <option value="">— Pilih kategori —</option>
                        @foreach($editCategories as $catKey => $catLabel)
                            <option value="{{ $catKey }}">{{ $catLabel }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Peminjam: tampil hanya saat kategori "Pinjaman" (toggle JS di
                     index.blade.php). Server validasi required_if:category,pinjaman. --}}
                <div class="pc-form-row" id="pcEditBorrowerField" hidden>
                    <label>Peminjam <span class="text-danger">*</span></label>
                    <input type="text" name="borrower_name" id="pcEditBorrower"
                           maxlength="120" placeholder="Nama orang yang meminjam"
                           class="pc-input">
                </div>

                <div class="pc-form-row">
                    <label>Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" inputmode="numeric"
                           class="pc-input pc-amount-input"
                           id="pcEditAmountVisible"
                           data-target="pcEditAmount"
                           placeholder="0">
                    <input type="hidden" name="amount" id="pcEditAmount" required>
                    <small class="pc-muted">
                        Maks: Rp <span id="pcEditAmountMaxHint">0</span>
                        <span class="pc-muted">(saldo wallet + nominal asli)</span>
                    </small>
                </div>

                <div class="pc-form-row">
                    <label>Keterangan <span class="text-danger">*</span></label>
                    <input type="text" name="description" id="pcEditDescription"
                           required maxlength="255"
                           placeholder="Isi keterangan pengeluaran"
                           class="pc-input">
                </div>

                {{-- Bukti: tampil bukti lama (kalau ada) + opsi ganti/hapus.
                     Struktur .pc-attach-zone sama dengan form pengeluaran →
                     handler preview generik di index.blade.php ikut mengurusnya. --}}
                <div class="pc-form-row">
                    <label>Bukti <small class="pc-muted">— opsional</small></label>

                    <div class="pc-edit-attach-current" id="pcEditAttachCurrent" hidden>
                        <a href="#" target="_blank" rel="noopener" id="pcEditAttachLink" class="pc-edit-attach-link">
                            <i class="fa fa-paperclip"></i> Lihat bukti saat ini
                        </a>
                        <label class="pc-edit-attach-remove">
                            <input type="checkbox" name="remove_attachment" value="1" id="pcEditAttachRemove">
                            Hapus bukti
                        </label>
                    </div>

                    <div class="pc-attach-zone" id="editAttachZone">
                        <input type="file" name="attachment"
                               id="editAttachInput"
                               accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf"
                               class="pc-attach-input">
                        <label for="editAttachInput" class="pc-attach-label">
                            <i class="fa fa-camera"></i>
                            <span class="pc-attach-label__main">Tap untuk pilih foto / PDF</span>
                            <span class="pc-attach-label__hint">Maks 5 MB</span>
                        </label>
                        <div class="pc-attach-preview" id="editAttachPreview" hidden>
                            <div class="pc-attach-preview__thumb" id="editAttachThumb">
                                <i class="fa fa-file"></i>
                            </div>
                            <div class="pc-attach-preview__meta">
                                <div class="pc-attach-preview__name" id="editAttachName">—</div>
                                <div class="pc-attach-preview__size" id="editAttachSize">—</div>
                            </div>
                            <button type="button" class="pc-attach-preview__remove" id="editAttachRemove" title="Hapus file">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <small class="pc-muted">Upload untuk mengganti / menambah bukti. Kosongkan bila tidak ingin mengubah.</small>
                </div>
            </div>

            <div class="pc-modal__footer">
                <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                <button type="submit" class="st-btn-brand">
                    <i class="fa fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<style>
    .pc-edit-countdown{display:flex;align-items:center;gap:8px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:8px;padding:9px 14px;margin-bottom:12px;font-size:13px;font-weight:600;}
    .pc-edit-countdown i{font-size:13px;}
    .pc-edit-countdown strong{font-weight:800;}
    .pc-edit-countdown.is-warning{background:#fffbeb;border-color:#fcd34d;color:#92400e;}
    .pc-edit-countdown.is-danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c;}
    .pc-edit-context{display:flex;gap:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 14px;margin-bottom:6px;}
    .pc-edit-context__item{display:flex;flex-direction:column;gap:2px;}
    .pc-edit-context__label{font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#64748b;font-weight:600;}
    .pc-edit-context__value{font-size:14px;color:#0f172a;font-weight:600;}
    .pc-edit-locked-hint{display:flex;align-items:center;gap:4px;font-size:11px;color:#94a3b8;margin:0 0 12px;}
    .pc-edit-locked-hint i{font-size:10px;}
    .pc-pill-edited{display:inline-block;margin-left:6px;padding:1px 8px;border-radius:50px;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.4px;border:1px solid #fde68a;}
    .pc-edit-attach-current{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:8px 12px;margin-bottom:8px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;}
    .pc-edit-attach-link{display:inline-flex;align-items:center;gap:6px;color:#7c2d12;font-weight:600;font-size:13px;text-decoration:none;}
    .pc-edit-attach-link:hover{text-decoration:underline;}
    .pc-edit-attach-remove{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#b91c1c;cursor:pointer;}
</style>
