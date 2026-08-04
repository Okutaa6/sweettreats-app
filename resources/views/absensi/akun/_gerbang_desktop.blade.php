{{--
    Gerbang sopan KEBALIKAN dari layouts/_gerbang_sopan: halaman Absen (selfie)
    dibuka di KOMPUTER → ditutup dengan penjelasan, absen lewat HP/tablet di
    titik absen cabang. HP + TABLET tetap boleh (keputusan Ekky 2026-08-05).

    Kenapa ditutup: absen sah = orang ADA di titik absen cabang. Di komputer
    syarat itu paling gampang dilanggar — webcam bisa diarahkan ke foto/layar,
    dan virtual-camera membuat berkas lama tampak seperti tangkapan langsung.
    (Halaman ini memakai getUserMedia, bukan pemilih berkas; yang dijaga bukan
    "unggah foto lama" lewat tombol, melainkan absen dari meja mana pun.)

    Menu TIDAK disembunyikan (hide senyap = kegagalan-senyap, katalog Pusat
    Akun) — halamannya tetap ada, isinya yang menjelaskan. Karena itu juga
    TIDAK ada tombol "Lanjut": gerbang ini kebijakan, bukan saran kenyamanan.

    Deteksi: pointer halus TANPA layar sentuh = desktop. Laptop layar sentuh
    ikut lolos. Ini gerbang NIAT, bukan penegakan: penentu sesungguhnya tetap
    WiFi cabang sebagai saksi + flag untuk SPV (rencana-absen-hp.md). Kalau
    kelak perlu ditegakkan betulan, tempatnya di AkunAbsenController::store.
--}}
<div id="gerbangAbsenDesktop" style="display:none; position:fixed; inset:0; z-index:2000; background:#F4ECE2; font-family:'Plus Jakarta Sans',system-ui,sans-serif; padding:24px 20px; overflow-y:auto;" role="dialog" aria-modal="true" aria-label="Absen lewat HP atau tablet">
    <div style="max-width:420px; margin:10vh auto 0; background:#fff; border:1px solid #EFE0CE; border-radius:18px; padding:26px 22px; text-align:center; box-shadow:0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05);">
        <div style="width:64px; height:64px; border-radius:18px; background:#FBF5ED; border:1px solid #F2E6D8; color:#8A5A16; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M11 5h2M12 18.2v.1"/></svg>
        </div>
        <div style="font-size:16.5px; font-weight:800; color:#3B1500; letter-spacing:-.2px;">Absen dilakukan lewat HP atau tablet</div>
        <p style="font-size:13px; color:#9A7256; line-height:1.55; margin:8px 0 4px;">
            Buka halaman ini dari HP atau tablet di titik absen cabang.
            Di sana kamera menyala langsung, jadi foto absen selalu diambil
            saat itu juga — bukan memilih berkas foto lama dari komputer.
        </p>
        <p style="font-size:13px; color:#9A7256; line-height:1.55; margin:8px 0 18px;">
            Kalau ada kendala (HP mati, kamera rusak), absen tetap bisa
            dibereskan lewat <b style="color:#5A3A22">Koreksi Absensi</b> di halaman Absensi Saya.
        </p>
        <a href="{{ route('akun.absensi') }}" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:48px; background:linear-gradient(135deg,#6B2400,#461500); color:#FBEADB; border:none; border-radius:13px; padding:13px 16px; font-size:14px; font-weight:800; cursor:pointer; text-decoration:none;">
            Kembali ke Absensi Saya
        </a>
    </div>
</div>
<script>
(function () {
    var el = document.getElementById('gerbangAbsenDesktop');
    if (!el) return;
    // Desktop = pointer utama halus (mouse/trackpad) DAN tak ada layar sentuh.
    // Tablet/HP: pointer coarse atau maxTouchPoints > 0 → gerbang tak muncul.
    var desktop = window.matchMedia('(pointer: fine)').matches && (navigator.maxTouchPoints || 0) === 0;
    if (!desktop) return;

    el.style.display = 'block';
    document.documentElement.style.overflow = 'hidden';

    // Halaman di belakang dimatikan betulan, bukan cuma tertutup: tanpa ini
    // Tab+Enter masih bisa menekan tombol Absen yang tak terlihat di baliknya.
    var isi = document.querySelector('.abshp-wrap');
    if (isi) { isi.inert = true; isi.setAttribute('aria-hidden', 'true'); }
    // Cadangan untuk browser tanpa dukungan `inert` (FA 5.8-era, Safari lama).
    if (!('inert' in HTMLElement.prototype)) {
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || el.style.display === 'none') return;
            if (!el.contains(document.activeElement)) {
                e.preventDefault();
                var f = el.querySelector('a[href]');
                if (f) f.focus();
            }
        }, true);
    }
    var tautan = el.querySelector('a[href]');
    if (tautan) tautan.focus();
})();
</script>
