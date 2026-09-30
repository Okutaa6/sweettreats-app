{{--
    Empty state. Dua bunyi berbeda, karena artinya berbeda:
      • tersaring habis → tawarkan melepas filternya
      • memang kosong   → itu KABAR BAIK, jangan bernada peringatan
--}}
<div class="st-empty">
    <div class="st-empty__icon">
        <i class="fas {{ ($antrean ?? false) || ($selesai ?? false) ? 'fa-check-circle' : 'fa-calendar-alt' }}"></i>
    </div>

    @if ($adaFilter ?? false)
        <div class="st-empty__title">Tidak ada yang cocok dengan filter ini.</div>
        <div class="st-empty__text">
            Lepas satu filter atau reset semuanya untuk melihat jadwal lain di {{ $namaCabang }}.
        </div>
        <div style="margin-top:14px;">
            <a href="{{ route('ortho.index') }}" class="ort-obtn">
                <i class="fas fa-undo" style="font-size:10px;"></i> Reset filter
            </a>
        </div>
    @elseif ($selesai ?? false)
        <div class="st-empty__title">Belum ada pasien yang selesai.</div>
        <div class="st-empty__text">
            Perawatan yang ditutup — lewat paket Freedom atau Lepas Bracket di rekam medis,
            atau Tandai Selesai — akan muncul di sini, riwayat kontrolnya tetap utuh.
        </div>
    @elseif ($antrean ?? false)
        <div class="st-empty__title">Antrean bersih.</div>
        <div class="st-empty__text">
            Semua pasien behel di {{ $namaCabang }} sudah punya jadwal kontrol berikutnya.
        </div>
    @else
        <div class="st-empty__title">Antrean bersih.</div>
        <div class="st-empty__text">
            Tidak ada jadwal kontrol yang menunggu di {{ $namaCabang }} untuk saringan ini.
        </div>
    @endif
</div>
