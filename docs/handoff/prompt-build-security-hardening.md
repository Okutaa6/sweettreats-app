# Security hardening — mulai dari sini (IT-2026-019)

> Untuk chat build. Bilang saja: **"baca `docs/handoff/prompt-build-security-hardening.md`"**.
> Disusun chat HUB 16 Sep 2026. Prod = `23fb5b1`.

---

## Baca dulu, jangan diulang

Spesifikasinya **sudah lengkap** di `docs/handoff/handoff-security-hardening.md`
(137 baris): tabel temuan per-endpoint, prioritas P0/P1/P2, nuansa
implementasi, dan mandat menyapu lubang lain. **Itu sumber kebenarannya —
jangan ditulis ulang.**

Berkas ini cuma menambahkan **apa yang HUB verifikasi ulang di PROD hari ini**,
karena brief itu disusun beberapa minggu lalu dan sebagian angkanya bisa
bergeser.

---

## ⛔ YANG PALING MENDESAK BUKAN LOGIN — WEBHOOK MAXCHAT TERBUKA SEKARANG

Brief menaruh ini di P1. **HUB menaikkannya jadi yang pertama dikerjakan**,
karena tiga alasan: ia tak butuh kredensial apa pun, ia menulis ke aliran data
tersibuk kita, dan perbaikannya satu baris.

Diverifikasi di prod hari ini — **kode dan konfigurasi, bukan dari brief**:

```php
// app/Http/Controllers/Webhook/MaxChatWebhookController.php:16-17
$token = config('services.maxchat.webhook_token');
if ($token && $request->header('X-Webhook-Token') !== $token) {
```

```
config('services.maxchat.webhook_token')  ->  KOSONG
```

Karena `$token` falsy, **seluruh pemeriksaan dilewati**. Siapa pun yang tahu
URL-nya bisa menyuntik data. Yang bisa dimasuki:

```
raw_contact   12.404 baris
raw_chat      35.737 baris   (859 dalam 7 hari terakhir)
```

Itu modul **paling sibuk** di katalog (Kontak & Percakapan, 853 dalam 7 hari) —
dan sumber bagi CRM Cekat yang sedang direncanakan.

### ⚠️ JANGAN langsung dibuat fail-closed tanpa koordinasi

Mengubah `if ($token && …)` jadi `if (! $token || …) abort(401)` **akan
menghentikan aliran MaxChat seketika** kalau tokennya belum dipasang di sisi
MaxChat juga. 859 chat/minggu berhenti masuk, dan tak ada yang akan sadar
sampai FO mengeluh.

Urutan yang benar:

1. Sepakati token dengan pihak MaxChat, pasang di sisi mereka.
2. Isi `services.maxchat.webhook_token` di prod.
3. **Baru** ubah kodenya jadi fail-closed.
4. Pantau `raw_chat` beberapa jam — kalau berhenti tumbuh, tokennya tidak cocok.

Kalau langkah 1 tak bisa cepat, alternatif sementara yang **tidak** memutus
aliran: tetap terima tanpa token tapi **catat** dan batasi (`throttle` ketat +
log alamat asal), supaya penyalahgunaan ketahuan. Itu bukan perbaikan, itu
penundaan yang terlihat — dan harus ditulis begitu.

---

## Yang HUB konfirmasi masih benar di prod

| temuan brief | keadaan 16 Sep |
|---|---|
| grup `web` tanpa throttle | **benar** — `middlewareGroups['web']` isinya cuma EncryptCookies, AddQueuedCookies, StartSession, ShareErrors, VerifyCsrf, SubstituteBindings. Nol throttle. |
| `POST /signin` tanpa throttle | **benar** — tak ada middleware throttle di rutenya |
| `POST /affiliate/login` tanpa throttle | **benar** — dan auth-nya PIN 6 digit |
| `POST /forgot-password` tanpa throttle | **benar** |
| `TrustProxies::$proxies` kosong | **benar** — `protected $proxies;` tanpa nilai |

---

## ⛔ TrustProxies — jangan disentuh sendirian

Brief sudah menyebutnya. HUB menegaskan **kenapa ini bukan urusan security saja**:

`TrustProxies` menentukan apa arti `request()->ip()`. Dan `request()->ip()`
adalah premis dari **WiFi-saksi absen HP — fitur yang dipakai karyawan setiap
hari di lima cabang**, termasuk IPv4 cocok-penuh dan IPv6 /48 yang keduanya
LIVE PROD sejak 27 Agustus.

Artinya:

- Kalau kalian **mengisi** `$proxies` dan prod ternyata TIDAK di belakang proxy →
  header `X-Forwarded-For` jadi bisa dipalsukan → **absen dari mana saja lolos
  sebagai "di WiFi cabang"**.
- Kalau kalian **membiarkannya kosong** dan prod ternyata DI BELAKANG proxy →
  semua orang terlihat satu IP → throttle-by-IP saling mengunci, DAN pencocokan
  WiFi rusak.

Jadi: **buktikan dulu topologi prod** (apakah ada proxy/CDN di depan), tulis
buktinya, baru ubah. Jangan menebak. Dan kalau menyentuhnya, sertakan uji yang
membuktikan absen HP masih mengenali WiFi cabang dengan benar sesudahnya —
memori `project_wifi_belajar_sendiri` dan `project_absen_hp` berisi peta
alamatnya, termasuk pagar yang **jangan** dicabut.

---

## Urutan yang HUB sarankan

Berbeda dari brief, dan alasannya di atas:

1. **Webhook MaxChat** — celah terbuka, nol kredensial, perbaikan satu baris
   (tapi ikuti urutan koordinasi di atas).
2. **`/signin` + `/affiliate/login`** — P0 brief. Throttle **by email+IP**, bukan
   murni IP (staf satu cabang berbagi IP publik; per-IP bisa mengunci satu
   cabang). Samakan pesan galat supaya email tak bisa dienumerasi.
3. **`/forgot-password`** — throttle + pesan netral.
4. `/reservasi` + `/affiliate/register` — honeypot lebih ringan daripada captcha.
5. TrustProxies — **terakhir**, dan hanya setelah topologinya dibuktikan.

Throttle global di grup `web` (P2 di brief) HUB sarankan **ditunda** sampai
TrustProxies jelas — throttle global di atas IP yang salah akan mengunci orang
banyak sekaligus, dan gejalanya mirip aplikasi rusak.

---

## Uji & catatan deploy

- Uji yang HUB harapkan: percobaan login gagal berulang **terkunci**, tapi orang
  lain **dari IP yang sama** tetap bisa masuk (itu inti pilihan email+IP).
  Plus kendali negatif: cabut throttle-nya, uji itu harus merah.
- ⚠️ `phpunit` di dev tumbang di >±17 uji ber-DB per proses — **jalankan per kelas**.
- Gerbong ini kemungkinan **tidak** menyentuh `public/assets/**`. Kalau ternyata
  menyentuh, deploy prod **WAJIB** diikuti `rsync` yang DIBATASI ke `assets/`.
- ⛔ Perubahan `.env`/config prod (token webhook) **bukan** bagian `deploy.sh` —
  dipasang manual, dan catat di log-update bahwa ia prasyarat.

## Kalau ada yang ditolak

Kalau kalian tidak setuju dengan urutan HUB — terutama menaikkan webhook ke
nomor satu — **katakan alasannya**. Bukan tidak mungkin ada sisi yang HUB tak
lihat. Yang penting jangan dibiarkan tanpa keputusan.
