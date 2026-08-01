{{--
    "Gerbang sopan" — halaman desktop-only dibuka di HP (peta-tema §5).
    Akses TIDAK dihilangkan: cuma pesan ramah + tombol "Lanjut lihat"
    (tampilkan apa adanya, scroll horizontal). JANGAN pernah hard-hide:
    menu kosong di HP = kegagalan-senyap.

    Dipasang otomatis oleh layouts/app.blade.php untuk segment desktop-first.
    Pilihan "Lanjut lihat" diingat per sesi browser (sessionStorage) supaya
    tidak nanya berulang-ulang tiap pindah halaman.
--}}
<div id="gerbangSopan" style="display:none; position:fixed; inset:0; z-index:2000; background:#F4ECE2; font-family:'Plus Jakarta Sans',system-ui,sans-serif; padding:24px 20px; overflow-y:auto;" role="dialog" aria-modal="true" aria-label="Halaman versi komputer">
    <div style="max-width:420px; margin:10vh auto 0; background:#fff; border:1px solid #EFE0CE; border-radius:18px; padding:26px 22px; text-align:center; box-shadow:0 1px 2px rgba(70,21,0,.04), 0 10px 26px rgba(70,21,0,.05);">
        <div style="width:64px; height:64px; border-radius:18px; background:#FBF5ED; border:1px solid #F2E6D8; color:#8A5A16; display:flex; align-items:center; justify-content:center; margin:0 auto 14px;">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="19" height="12.5" rx="2"/><path d="M8 20h8M12 16.5V20"/></svg>
        </div>
        <div style="font-size:16.5px; font-weight:800; color:#3B1500; letter-spacing:-.2px;">Halaman ini paling nyaman dibuka di komputer</div>
        <p style="font-size:13px; color:#9A7256; line-height:1.55; margin:8px 0 18px;">
            Isinya tabel/panel lebar yang sempit di layar HP. Kamu tetap boleh
            melihatnya — nanti geser layar ke samping untuk kolom yang terpotong.
        </p>
        <button type="button" id="gerbangLanjut" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:48px; background:linear-gradient(135deg,#6B2400,#461500); color:#FBEADB; border:none; border-radius:13px; padding:13px 16px; font-family:inherit; font-size:14px; font-weight:800; cursor:pointer;">
            Lanjut lihat
            <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M6 3.5 10.5 8 6 12.5" stroke="#FBEADB" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <a href="{{ url('akun') }}" style="display:inline-block; margin-top:13px; font-size:12.5px; font-weight:700; color:#8A3A12; text-decoration:none;">atau buka halaman untuk HP: Akun Saya →</a>
    </div>
</div>
<script>
(function () {
    var el = document.getElementById('gerbangSopan');
    if (!el) return;
    var mq = window.matchMedia('(max-width: 900px)');
    function tutup() { el.style.display = 'none'; document.documentElement.style.overflow = ''; }
    if (mq.matches && sessionStorage.getItem('gerbangLanjut') !== '1') {
        el.style.display = 'block';
        document.documentElement.style.overflow = 'hidden';
    }
    // HP diputar ke landscape / window dilebarkan → gerbang tak relevan lagi
    (mq.addEventListener ? mq.addEventListener.bind(mq) : mq.addListener.bind(mq))('change', function (e) {
        if (!e.matches) tutup();
    });
    document.getElementById('gerbangLanjut').addEventListener('click', function () {
        sessionStorage.setItem('gerbangLanjut', '1');
        tutup();
    });
})();
</script>
