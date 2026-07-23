{{-- Collapsible "Hari ini tidak perlu diisi?" (Dikecualikan). Form terpisah dari
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
        <div class="cl-excuse__label">Pilih / tulis alasan (wajib):</div>
        <div class="cl-excuse__chips">
            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Cuti';">Cuti</button>
            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Klinik tutup';">Klinik tutup</button>
            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Libur';">Libur</button>
            <button type="button" class="cl-excuse__chip" onclick="this.closest('form').querySelector('.cl-excuse__input').value='Tidak ada pasien';">Tidak ada pasien</button>
        </div>
        <textarea name="excuse_reason" class="cl-excuse__input" rows="2" maxlength="500" required
                  placeholder="Contoh: libur / tanggal merah…"></textarea>
        <button type="submit" class="cl-excuse__submit"><i class="fas fa-check"></i> Tandai "Dikecualikan"</button>
    </form>
</details>
