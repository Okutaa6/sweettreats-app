{{--
    Form inline "Catat Servis" (rincian biaya repeatable). Dipakai GS (submit →
    pending) & supervisor (submit → langsung approved) dengan $action berbeda.
    Param: $unit, $today, $action (URL submit).
--}}
<div class="ac-form" id="acForm-{{ $unit->id }}" hidden>
    <form method="POST" action="{{ $action }}" data-ac-service-form>
        @csrf
        <input type="hidden" name="ac_unit_id" value="{{ $unit->id }}">
        <div>
            <label class="ac-form__lbl">Tanggal Servis</label>
            <input type="date" name="service_date" value="{{ $today->toDateString() }}" max="{{ $today->toDateString() }}" required class="ac-inp">
        </div>
        <div>
            <label class="ac-form__lbl">Jenis Servis</label>
            <div class="ac-types">
                <div class="ac-type is-on" data-ac-type>
                    <label class="ac-type__check">
                        <input type="checkbox" name="wash" value="1" data-ac-type-toggle checked>
                        <i class="fas fa-broom"></i> Cuci AC
                    </label>
                    <div class="ac-amt">
                        <span>Rp</span>
                        <input type="text" name="wash_amount" inputmode="numeric" placeholder="0" class="ac-inp" data-ac-amount>
                    </div>
                </div>
                <div class="ac-type" data-ac-type>
                    <label class="ac-type__check">
                        <input type="checkbox" name="freon" value="1" data-ac-type-toggle>
                        <i class="fas fa-tint"></i> Isi Freon
                    </label>
                    <div class="ac-amt">
                        <span>Rp</span>
                        <input type="text" name="freon_amount" inputmode="numeric" placeholder="0" class="ac-inp" data-ac-amount disabled>
                    </div>
                </div>
            </div>
        </div>
        <div>
            <label class="ac-form__lbl">Rincian Biaya Lainnya <span class="ac-form__opt">(opsional)</span></label>
            <div class="ac-items" data-ac-items></div>
            <button type="button" class="ac-btn-add" data-ac-add-item>+ Tambah Rincian</button>
        </div>
        <div class="ac-total">
            <span>Total kunjungan</span>
            <b data-ac-total>Rp 0</b>
        </div>
        <div class="ac-form__actions">
            <button type="submit" class="ac-btn-save" disabled><i class="fas fa-check"></i> Simpan Catatan</button>
            <button type="button" class="ac-btn-cancel" data-ac-cancel>Batal</button>
        </div>
    </form>
</div>
