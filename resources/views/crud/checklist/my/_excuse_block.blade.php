{{-- Collapsible "Hari ini tidak perlu isi checklist?" (Dikecualikan). Form terpisah dari
     form submit — sengaja dipakai lewat @include supaya bisa ditaruh di dalam
     wrapper Kirim Checklist tanpa nested-form. Style-nya di index.blade.php. --}}
<details class="cl-excuse">
    <summary class="cl-excuse__trigger">
        <span class="cl-excuse__trigger-label"><i class="fas fa-mug-hot"></i> Hari ini tidak perlu isi checklist?</span>
        <i class="fas fa-chevron-down cl-excuse__caret"></i>
    </summary>
    <form method="POST" action="{{ route('mychecklist.excuse') }}" class="cl-excuse__form"
          data-confirm="Tandai checklist hari ini sebagai tidak perlu diisi?">
        @csrf
        <div class="cl-excuse__label">Tandai hari ini <b>Dikecualikan</b> — bukan pelanggaran, hanya info bahwa checklist tidak berlaku hari ini. Bisa dibatalkan selama belum direview.</div>
        <div class="cl-excuse__chips">
            <button type="button" class="cl-excuse__chip" data-excuse-preset="Cuti">Cuti</button>
            <button type="button" class="cl-excuse__chip" data-excuse-preset="Klinik tutup">Klinik tutup</button>
            <button type="button" class="cl-excuse__chip" data-excuse-preset="Libur">Libur</button>
            <button type="button" class="cl-excuse__chip" data-excuse-preset="Tidak ada pasien">Tidak ada pasien</button>
        </div>
        <textarea name="excuse_reason" class="cl-excuse__input" rows="2" maxlength="500" required
                  placeholder="Alasan (mis. klinik tutup renovasi)…"></textarea>
        <button type="submit" class="cl-excuse__submit" disabled>Tandai Dikecualikan</button>
    </form>
</details>
<script>
(function () {
    // Chip preset: isi alasan + tandai aktif; tombol hanya aktif kalau ada alasan (ikut handoff).
    var form  = document.querySelector('.cl-excuse__form');
    if (! form) return;
    var input = form.querySelector('.cl-excuse__input');
    var btn   = form.querySelector('.cl-excuse__submit');
    var chips = form.querySelectorAll('[data-excuse-preset]');

    function sync() {
        btn.disabled = input.value.trim() === '';
        chips.forEach(function (c) {
            c.classList.toggle('is-active', c.getAttribute('data-excuse-preset') === input.value.trim());
        });
    }
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            input.value = c.getAttribute('data-excuse-preset');
            sync();
        });
    });
    input.addEventListener('input', sync);
    sync();
})();
</script>
