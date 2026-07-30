# Handoff — Security Hardening (SweetTreats)

Dokumen ini brief lengkap untuk **sesi/chat terpisah** yang fokus mengeraskan
keamanan aplikasi sebelum & sesudah ke prod. Ditulis 2026-07-25 dari audit
rate-limit read-only. Tujuannya: sesi baru bisa langsung kerja tanpa mengulang
audit dari nol — DAN diminta menyapu kemungkinan lubang lain di luar daftar ini.

> **Aturan kerja yang berlaku (sama dgn sesi utama):** diskusi dulu sebelum
> koding besar; test di LOKAL dulu; jangan deploy prod tanpa penyebutan prod
> eksplisit dari user; jangan push tanpa diminta. Bahasa UI Indonesia awam.

---

## 1. Konteks & jalur deploy

- Aplikasi klinik gigi 5 cabang, dipakai staf + publik (reservasi online).
- **Prod = branch `main`** (`app.sweettreats.id`). Dev = `dev.sweettreats.id`,
  saat ini di `feature/absensi`.
- Endpoint yang dibahas di sini (login, affiliate, reservasi publik, webhook)
  **hidup di `main`**, BUKAN bagian `feature/absensi`. Jadi perbaikannya
  **branch hotfix sendiri dari `main`** (pola persis `hotfix/reservasi-simpan-keluar`
  kemarin: cabang dari `origin/main`, commit kecil, ff ke main, `deploy.sh main`).
- `deploy.sh` TIDAK menjalankan migration/SQL. Fix keamanan ini **kode-only**
  (middleware + pesan) — tak butuh SQL.
- Resep SSH deploy ada di memori `reference_deploy_access`. Prod SSH
  classifier-gated: butuh user menyebut prod eksplisit.
- **Sifat risiko: steady-state, bukan regresi baru.** Sudah lama begini. Aman
  dikerjakan terjadwal, tapi ini **P0 nyata** — jangan dibiarkan slip.

## 2. Akar masalah struktural

- Grup middleware **`web`** (`app/Http/Kernel.php` ~baris 32) **tak punya
  throttle default apa pun**. Hanya grup **`api`** yang ter-throttle
  (`RouteServiceProvider.php:27` → `RateLimiter::for('api')` = 60/mnt per user/IP,
  hanya untuk prefix `/api`).
- Akibat: semua endpoint auth/publik di `routes/web.php` tanpa `throttle:`
  manual = **tanpa perlindungan brute-force sama sekali**.
- **Rekomendasi struktural:** taruh jaring pengaman throttle global ringan di
  grup `web` (mis. `throttle:120,1`), lalu throttle ketat per-route untuk auth.

## 3. Temuan per-endpoint (dari audit)

| Endpoint | Publik? | Throttle skrg | Risiko | Fix |
|---|---|---|---|---|
| `POST /signin` (web.php ~649, AuthController@loginProcess) | Ya | TIDAK ADA | **P0** brute-force + enumerasi email (pesan beda) | throttle by email+IP + samakan pesan |
| `POST /affiliate/login` (web.php:80, AffiliatePortalController@loginProcess:31) | Ya | TIDAK ADA | **P0** auth = **PIN 6 digit** (~20 bit), tanpa throttle → tertebus jam-an | throttle + lockout per-affiliate |
| `POST /forgot-password` (web.php:653) | Ya | TIDAK ADA | **P1** enumerasi email (pesan beda); punya reCAPTCHA | throttle:3,10 + pesan netral |
| `POST /reset-password/{token}` (web.php:656) | Ya | TIDAK ADA | P2 token 128-bit aman, tapi tanpa throttle | throttle:6,1 |
| `POST /ganti-password` (web.php ~665) | Semi | TIDAK ADA | Rendah (gate sesi + password lama) | opsional throttle:6,1 |
| `POST /reservasi` (web.php:65, LandingReservationController@store) | Ya | `throttle:20,1` | **P1** longgar (1.200/jam) + tanpa honeypot/captcha | throttle:5,1 + honeypot |
| `POST /affiliate/register` (web.php:82) | Ya | TIDAK ADA | **P1** spam pendaftaran affiliate | throttle:3,10 + honeypot |
| `GET /api/reservation/check-customer` (web.php:150) | Ya | `throttle:60,1` | P2 enumerasi HP pasien | throttle:15,1 + balas seragam |
| `GET /reservasi/riwayat` (web.php:113) | Ya | TIDAK ADA | Rendah (butuh session gate) | opsional throttle:30,1 |
| `POST /akun/absen` (web.php:348, AkunAbsenController@store) | Auth | TIDAK ADA | Rendah (auth + idempoten) | opsional throttle:30,1 anti-flood upload |
| `POST /api/webhooks/{contact,chat}` (api.php:11-12, MaxChatWebhookController) | Ya | `throttle:60,1` + token | **P1** cek `if($token && ...)` → kalau `config services.maxchat.webhook_token` KOSONG, auth **di-bypass** (fail-open) | wajibkan token (fail-closed) |

## 4. Prioritas eksekusi

**P0 (wajib sebelum apa pun ke prod):**
1. `/signin` — throttle + samakan pesan error.
2. `/affiliate/login` — throttle + lockout (PIN 6-digit = kredensial terlemah).

**P1 (segera):**
3. `/forgot-password` throttle + pesan netral.
4. `/reservasi` perketat + honeypot.
5. `/affiliate/register` throttle + honeypot.
6. Webhook MaxChat fail-closed.

**P2 (defense-in-depth):** reset-password, check-customer, akun/absen, +
throttle global ringan di grup `web`.

## 5. Nuansa WAJIB diperhatikan saat implementasi

- **Throttle login by email+IP, BUKAN murni IP.** Staf satu cabang berbagi IP
  publik (NAT IPv4). Kalau per-IP, satu orang salah ketik bisa **ngunci satu
  cabang**. Pola: `Limit::perMinute(5)->by($request->input('email').'|'.$request->ip())`
  (mirip throttle auth bawaan Laravel). Nebak satu akun tetap keblokir, orang
  lain di cabang sama tak kena.
- **⚠️ TrustProxies KOSONG (lintas-isu, cek dulu topologi prod).** Kalau prod di
  belakang proxy/CDN/load-balancer, `request()->ip()` mengembalikan IP **proxy**,
  bukan klien — ini **merusak throttle-by-IP** (semua user terlihat satu IP →
  saling mengunci) SEKALIGUS premis **WiFi-saksi absen HP** (lihat memori
  `project_absen_hp`). Verifikasi apakah prod pakai proxy; kalau ya, set
  `app/Http/Middleware/TrustProxies.php` dengan benar SEBELUM andalkan IP.
- **Enumerasi:** rate-limit saja tak menutup bocornya "email terdaftar/tidak".
  Samakan pesan: login → "Email atau password salah" (satu pesan); forgot →
  "Jika email terdaftar, tautan reset dikirim". Lokasi: AuthController
  loginProcess (~86 vs 89), forgotProcess (~121 vs 123).
- **reCAPTCHA:** sudah dicabut dari login (loginProcess ~22-25 di-comment), masih
  aktif di forgot-password. Form `/reservasi` & `/affiliate/register` tak punya
  captcha/honeypot. Honeypot (field tersembunyi yang harus kosong) lebih ringan
  daripada captcha untuk form publik — pertimbangkan itu dulu.

## 6. MANDAT: sapu kemungkinan lubang LAIN (di luar rate-limit)

Audit ini hanya menutup satu sisi (anti-abuse). Sesi security diminta
**menyapu kategori kerentanan lain** — jangan berhenti di daftar §3. Minimal cek:

- **Otorisasi / IDOR:** bisakah user mengakses/mengubah data user lain dengan
  ganti `{id}` di URL? Cek reservation edit/update, detail pasien, konten
  affiliate, `pengawasan/foto/{id}` (serve selfie — ada cek hak?), gate
  `/ganti-password`. AuthGuard cuma cek segment-1 = punya menu; belum tentu cek
  kepemilikan baris.
- **Mass-assignment:** `request()->all()`/`except()` masuk ke `create()/update()`
  pada model ber-`$fillable` luas. (Sudah ketemu 1 di Set/Group `id` — cari yang
  lain: reservation, customer, affiliate, dll.)
- **File upload:** selfie absen, foto affiliate, impor CSV reservasi — validasi
  tipe/ukuran, nama file, disimpan di luar webroot? Ada risiko path traversal /
  eksekusi?
- **SQLi:** `DB::raw`/`whereRaw`/`selectRaw` dengan input user tak ter-bind.
- **XSS:** output `{!! !!}` tak ter-escape; `innerHTML`/`html()` di JS dgn data
  user (sudah ketemu & difix beberapa; sapu sisanya).
- **CSRF:** `VerifyCsrfToken::$except` — pastikan pengecualian (cekat/webhook)
  tak kelewat luas.
- **Session & auth kustom:** fiksasi sesi, kedaluwarsa, `session('id')` dipercaya
  di mana saja; open-redirect via `redirect()->to(session('landing_page'))`.
- **Secrets:** ada kredensial/kunci ter-hardcode di repo? `.env`/backup ke-commit?
- **Info leak:** `APP_DEBUG=true` di prod? stack trace bocor? header server?
- **Autentikasi API vendor (`api.client`):** kekuatan token, rotasi, per-client
  throttle.

Laporkan temuan dengan tingkat risiko + fix konkret, lalu (setelah ACC user)
kerjakan bertahap dari P0.

## 7. Pendekatan verifikasi

- Test throttle di LOKAL: tembak endpoint > limit, pastikan 429; pastikan
  email+IP keying tak saling-kunci antar-akun.
- Test pesan enumerasi seragam (email ada vs tidak → respons sama).
- Regресi: login normal masih jalan, reservasi publik masih jalan, webhook
  dengan token benar masih diterima, tanpa token ditolak.
- Audit adversarial diff sebelum ke prod (pola sesi utama).

## 8. Referensi memori terkait
`reference_deploy_access` (resep deploy), `project_absen_hp` (TrustProxies vs
WiFi-saksi), `project_git_workflow`. Sesi utama (absensi/cuti/redesign) jalan
paralel di chat lain — jangan bentrok; security murni dari `main`.
