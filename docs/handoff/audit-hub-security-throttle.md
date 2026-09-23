# Audit HUB — `hotfix/security-throttle` (P0+P1)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/audit-hub-security-throttle.md`"**.
> Disusun HUB 16 Sep 2026. Uji dijalankan sendiri, bukan dipercaya dari laporan.

---

## Keberatan kalian atas urutan HUB: **DITERIMA**

> *"Menaikkan webhook ke nomor satu saya setujui secara risiko, tapi langkah
> 1–2-nya bukan pekerjaan chat build."*

Benar, dan HUB yang kurang tepat menaruhnya. Yang HUB tulis sebagai "nomor satu"
sebenarnya **dua pekerjaan berbeda**: menyepakati token dengan MaxChat (kerja
Ikhsan) dan mengeraskan kodenya (kerja kalian). Menagih keduanya ke satu chat
membuat yang bisa dikerjakan jadi tersandera yang tidak bisa.

Dan jalan keluar kalian **lebih baik daripada yang HUB minta**: kodenya sudah
fail-closed begitu token ada, jadi pintunya tertutup **tanpa deploy lagi** —
sementara keadaan terbuka sekarang tidak lagi senyap. Itu menyelesaikan
ketegangan yang HUB cuma bisa tuliskan sebagai peringatan.

---

## Yang lolos — diverifikasi HUB

| pemeriksaan | hasil |
|---|---|
| `GerbangMasukTest` | **11 uji · 31 assertion** ✅ |
| `WebhookMaxChatTest` | **6 uji · 7 assertion** ✅ |
| Basis branch | `23fb5b1` = main, **0 commit tertinggal** ✅ |
| `vendor/` di worktree | direktori nyata, bukan symlink ✅ |
| **Tiap limiter benar-benar TERPASANG di rute?** | **ya, kelimanya** ✅ |
| Ada limiter nyasar ke rute `GET`? | **tidak** ✅ |
| `Cache::add` untuk mengurung log — driver prod mendukung? | **ya, `file`** ✅ |

⭐ Pemeriksaan keempat itu yang paling mudah terlewat: `RateLimiter::for()` yang
didefinisikan tapi tak dipasang sebagai middleware **tidak berbuat apa-apa, dan
tidak mengeluh**. Kelimanya (`masuk`, `masuk-affiliate`, `lupa-sandi`,
`reset-sandi`, `daftar-affiliate`) HUB cocokkan satu per satu ke `routes/web.php`.

⭐ Dan `Cache::add` sebagai kurungan log cuma bekerja kalau cache-nya **melintasi
request**. Prod pakai driver `file` — jadi bekerja. Kalau suatu hari cache prod
diubah ke `array`, kurungan itu diam-diam lumpuh dan tiap kiriman menulis baris
baru. Layak ditulis di kode.

**Keputusan yang HUB anggap paling penting dan benar:**

- **Kunci identitas+IP, bukan IP.** Satu cabang keluar lewat satu IP publik
  (NAT). Pagar per-IP murni akan menghukum orang yang tidak bersalah — dan pagar
  seperti itu yang membuat orang berhenti memakai sistemnya. Kendali negatif
  kalian (ganti ke IP murni → uji rekan-sekantor merah) adalah bukti yang tepat.
- **Lapis ketiga per nomor, lintas IP, untuk portal affiliate.** PIN 6 digit =
  sejuta kemungkinan; tanpa lapis ini penyerang tinggal berganti alamat dan tiap
  alamat baru dapat jatah penuh. Dan kuncinya lepas sendiri dalam sejam, jadi
  orang iseng tak bisa mematikan akun orang lain.
- **Menolak perangkap bot terang-terangan.** Pura-pura berhasil memang lebih
  menyesatkan bot, tapi di klinik ongkos salahnya ditanggung pasien yang pulang
  mengira punya jadwal. Pertimbangan yang benar.
- **TrustProxies & throttle global grup `web` tidak disentuh.** Tepat.

`test_6` yang kalian tandai sendiri lebih lemah (menjaga bentuk kode, bukan
perilaku, karena `_captcha()` mem-`new` Guzzle di dalam method) — HUB tidak
menambahkan apa pun; menyebutnya sendiri sudah cukup.

---

## ⚠️ TEMUAN — prinsip kalian sendiri dipakai di satu tempat, tidak di dua lainnya

Alasan kalian mengurung log webhook ditulis begini:

> *"dikurung sekali per jam per alamat supaya satu penyerang tidak bisa
> menenggelamkan `laravel.log` dan justru menyembunyikan kejadian lain."*

Alasan itu **benar**. Tapi ia berlaku sama persis — lebih kuat, malah — untuk dua
`Log::warning` baru yang lain, dan keduanya **tidak dikurung**:

| berkas | `Log::` baru | dikurung? |
|---|---|---|
| `MaxChatWebhookController` | 1 | **ya** (`Cache::add`, 1 jam/alamat) |
| `LandingReservationController` | 1 | **tidak** |
| `AffiliatePortalController` | 1 | **tidak** |

Kenapa ini bukan sekadar kerapian:

- Keduanya di **form PUBLIK** — justru yang digedor bot, bukan webhook yang
  alamatnya jarang diketahui.
- `/reservasi` kini `throttle:5,1`. Itu 5 baris/menit **per alamat**; dari 50
  alamat = 250/menit = **360 ribu baris sehari**, dan itu masih di dalam batas.
- ⛔ `laravel.log` prod **sudah 312 MB** dan **tidak berotasi** — satu berkas
  tumbuh selamanya. Ia juga berkas yang dibuka orang ketika ada yang rusak.

Jadi pagar yang dipasang untuk melindungi log justru bisa dilewati lewat pintu
sebelah. **Usul:** pakai `Cache::add` yang sama untuk kedua log perangkap bot —
kunci per alamat, satu jam. Perangkap bot tidak perlu dicatat tiap kali; yang
berguna adalah **tahu bahwa ia terjadi**, dan itu cukup sekali per jam.

ℹ️ Di luar cakupan kalian, tapi terkait: rotasi `laravel.log` sudah lama jadi
utang. Gerbong ini menambah penulis baru ke berkas itu, jadi utangnya naik
sedikit lebih cepat.

---

## Catatan deploy

- **Nol SQL**, dan **tidak** menyentuh `public/assets/**` → **tidak perlu rsync**.
  HUB konfirmasi dari daftar berkas.
- ⛔ Prasyarat manual di luar `deploy.sh`: **`MAXCHAT_WEBHOOK_TOKEN` di `.env`
  prod** — dan urutannya jangan dibalik (pasang di sisi MaxChat dulu, baru isi
  di sini, lalu `config:clear`, lalu pantau `raw_chat` beberapa jam). Kalau
  `raw_chat` berhenti tumbuh, tokennya tidak cocok.
- ⚠️ Sesudah naik, **buka `/signin` sungguhan sekali**. Uji membuktikan logikanya;
  yang tidak diuji adalah apakah halaman 429 kustom itu benar-benar terbaca oleh
  staf klinik. Salah ketik lima kali dari satu akun, lihat pesannya, lalu masuk
  dengan akun lain dari komputer yang sama untuk memastikan cabang tidak ikut
  terkunci.

## Yang belum dikerjakan (dan kalian sudah menyebutnya)

Mandat §6 brief — IDOR, mass-assignment, upload, SQLi, XSS — belum disapu.
TrustProxies & throttle grup `web` sengaja ditunda. Keduanya benar untuk
ditunda; yang penting keduanya **tercatat**, bukan hilang.
