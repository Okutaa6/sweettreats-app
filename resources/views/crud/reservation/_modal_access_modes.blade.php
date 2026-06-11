{{--
    Modal "Kelola Akses Halaman Raw Reservation".
    Hanya muncul untuk Super Admin / Admin General (gate-nya di blade pemanggil).

    Untuk setiap role yang sudah punya akses ke rawreservation (via group_access
    + access_path WHERE link='rawreservation'), admin bisa pilih mode:
      - Full Access (default)
      - View Only — hide tombol Tambah, tab Waiting List, dan inline edits

    Submit ke pettycash.access-modes.save → backend upsert ke
    group_page_access_modes (mode 'full' = delete row supaya tabel ringkas).

    Variables: $accessModeGroups (collection of {id, nama, mode}).
--}}
@php
    $accessModeGroups = $accessModeGroups ?? [];
@endphp
<div class="pc-modal" id="rrModalAccessModes" role="dialog" aria-modal="true">
    <div class="pc-modal__dialog pc-modal__dialog--wide">
        <form method="POST" action="{{ route('rawreservation.access-modes.save') }}">
            @csrf

            <div class="pc-modal__header">
                <div>
                    <h5 class="pc-modal__title">
                        <i class="fa fa-user-shield" style="color:#7c2d12;"></i> Kelola Akses Halaman Raw Reservation
                    </h5>
                    <small class="pc-muted">Atur mode akses per role — Full Access atau View Only.</small>
                </div>
                <button type="button" class="pc-modal__close" data-pc-modal-close aria-label="Tutup">&times;</button>
            </div>

            <div class="pc-modal__body">
                <div class="pc-tab-hint">
                    <i class="fa fa-info-circle" style="color:#2563eb;"></i>
                    <span><strong>View Only:</strong> user dengan role tersebut hanya bisa lihat data tanpa bisa Tambah Reservasi, ubah status / FU / Kehadiran / Keterangan, atau klaim promo. Tab <strong>Waiting List</strong> juga di-hide otomatis. Super Admin &amp; Admin General <em>selalu</em> Full Access (tidak terpengaruh setting ini).</span>
                </div>

                @if(count($accessModeGroups) === 0)
                    <div class="rr-access-empty">
                        <i class="fa fa-folder-open"></i>
                        Belum ada role yang punya akses ke halaman Raw Reservation.
                        Beri akses dulu lewat menu <strong>Setting &rarr; Group &rarr; Akses</strong>.
                    </div>
                @else
                    @php $themesList = availableThemes(); @endphp
                    <div class="rr-access-table-wrap">
                        <table class="rr-access-table">
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th style="width:28%;">Mode Akses</th>
                                    <th style="width:24%;">Tema Warna</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accessModeGroups as $g)
                                    <tr>
                                        <td>
                                            <i class="fa fa-users" style="color:#7c2d12; margin-right:6px;"></i>
                                            <strong>{{ $g->nama }}</strong>
                                        </td>
                                        <td>
                                            <div class="rr-mode-toggle">
                                                <label class="rr-mode-opt">
                                                    <input type="radio"
                                                           name="modes[{{ $g->id }}]"
                                                           value="full"
                                                           {{ $g->mode === 'full' ? 'checked' : '' }}>
                                                    <span class="rr-mode-opt__pill rr-mode-opt__pill--full">Full Access</span>
                                                </label>
                                                <label class="rr-mode-opt">
                                                    <input type="radio"
                                                           name="modes[{{ $g->id }}]"
                                                           value="view_only"
                                                           {{ $g->mode === 'view_only' ? 'checked' : '' }}>
                                                    <span class="rr-mode-opt__pill rr-mode-opt__pill--view">View Only</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="themes[{{ $g->id }}]" class="rr-theme-select"
                                                    data-theme="{{ $g->theme }}">
                                                @foreach($themesList as $key => $label)
                                                    <option value="{{ $key }}" {{ $g->theme === $key ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="rr-theme-note">
                        <i class="fa fa-info-circle"></i>
                        Tema mengubah warna brand <strong>seluruh aplikasi</strong> (sidebar, tombol, banner) untuk user dengan role tersebut. Berlaku setelah user login berikutnya.
                    </div>
                @endif

                <div class="pc-form-actions">
                    <button type="button" class="st-btn-outline-brown" data-pc-modal-close>Batal</button>
                    @if(count($accessModeGroups) > 0)
                        <button type="submit" class="pc-btn-topup-solid">
                            <i class="fa fa-save"></i> Simpan Mode Akses
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<style>
/* Override lebar modal untuk Kelola Akses Halaman — perlu ruang lebih
   karena ada 3 kolom (Role, Mode Akses, Tema Warna). */
#rrModalAccessModes .pc-modal__dialog--wide { max-width: 820px !important; }

.rr-access-empty {
    text-align: center;
    padding: 28px 16px;
    color: #6b7280;
    background: #fafafa;
    border: 1.5px dashed #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    margin: 12px 0;
    line-height: 1.6;
}
.rr-access-empty i {
    display: block;
    font-size: 28px;
    margin-bottom: 8px;
    color: #9ca3af;
}

.rr-access-table-wrap {
    margin: 12px 0;
    max-height: 55vh;
    overflow-y: auto;
    border: 1px solid var(--divider-soft, #e5e7eb);
    border-radius: 8px;
}
.rr-access-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.rr-access-table thead th {
    background: #fff8f3;
    color: var(--brown);
    text-align: left;
    padding: 10px 14px;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 1px solid var(--brown-2);
    position: sticky;
    top: 0;
}
.rr-access-table tbody td {
    padding: 10px 14px;
    border-bottom: 1px dashed #f1f5f9;
    color: #111;
}
.rr-access-table tbody tr:last-child td { border-bottom: none; }

.rr-mode-toggle {
    display: inline-flex;
    gap: 8px;
}
.rr-mode-opt { cursor: pointer; user-select: none; margin: 0; }
.rr-mode-opt input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.rr-mode-opt__pill {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 16px;
    font-size: 12.5px;
    font-weight: 600;
    border: 1.5px solid transparent;
    transition: all .15s;
}
.rr-mode-opt__pill--full {
    background: #f3f4f6;
    color: #374151;
}
.rr-mode-opt__pill--view {
    background: #f3f4f6;
    color: #374151;
}
.rr-mode-opt input[type="radio"]:checked + .rr-mode-opt__pill--full {
    background: #16a34a;
    color: #fff;
    border-color: #15803d;
    box-shadow: 0 2px 6px rgba(22,163,74,0.25);
}
.rr-mode-opt input[type="radio"]:checked + .rr-mode-opt__pill--view {
    background: #f59e0b;
    color: #fff;
    border-color: #b45309;
    box-shadow: 0 2px 6px rgba(245,158,11,0.25);
}
.rr-mode-opt:hover .rr-mode-opt__pill {
    transform: translateY(-1px);
}

/* Theme dropdown */
.rr-theme-select {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    background: #fff;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
}
.rr-theme-select:focus {
    outline: none;
    border-color: var(--brown-2);
    box-shadow: 0 0 0 2px rgba(120,55,20,0.12);
}
.rr-theme-note {
    margin-top: 12px;
    padding: 8px 12px;
    background: #fff8f3;
    border-left: 3px solid var(--brown-2);
    border-radius: 4px;
    font-size: 12.5px;
    color: #6b4226;
    line-height: 1.5;
}
.rr-theme-note i { color: #2563eb; margin-right: 4px; }

/* Static display untuk view-only mode di tabel reservasi */
.rr-static-text {
    color: #374151;
    font-size: 13px;
    line-height: 1.4;
}
</style>
