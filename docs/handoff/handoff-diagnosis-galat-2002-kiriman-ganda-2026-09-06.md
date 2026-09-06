# Handoff — Diagnosis "sistem sering error & double input" (6 Sep 2026)

> Hasil pemeriksaan prod 25 Agu–6 Sep 2026 oleh chat HUB. **Belum ada yang
> diperbaiki.** Dokumen ini untuk chat build. Semua angka diukur langsung di
> prod (`laravel.log`, tabel `att_*`, `petty_cash_transactions`), bukan dari dev.

## 0. Ringkasan satu layar

Satu keluhan, **tiga sebab berbeda**, tak satu pun berupa "sistem absensi rusak":

| gejala yang dilaporkan | sebab sebenarnya | bukti |
|---|---|---|
| "absensi sering error / gagal" | halaman **Pengawasan** menembakkan 49–179 request foto serentak → hosting menolak koneksi DB (`[2002]`) | 272 dari 278 kegagalan pemeriksa akses ada di `link=pengawasan`; 9 galat dalam 1 detik |
| "double absen" | sesudah absen masuk, tombol **langsung** jadi "Pulang" tanpa jeda → ketukan kedua = pulang; pulang sungguhan sore harinya **ditolak** | 4 kasus sejak 5 Agu (6–65 detik); nol duplikat sungguhan |
| "gagal input padahal berhasil" (petty cash) | form **tambah** pengeluaran tak punya pengaman klik ganda; dua baris identik lahir dalam 0–4 detik | Firda #187/#188 (22 Agu, +4 s), #344/#345 (2 Sep, +0 s) |

Yang **bukan** sebabnya, sudah diukur: server tidak lambat (signin 56–139 ms,
load 0,6 per core), tak ada galat PHP fatal (`error_log` terakhir Juli), tak ada
galat pada perintah **tulis** (0 dari 574 galat 2002 mengenai INSERT/UPDATE),
`max_user_connections=150` bukan pelaku (galatnya akan 1226, bukan 2002).

## 1. `[2002] Operation not permitted` — dipicu halaman Pengawasan

**Angka (1–5 Sep):** 192 galat; 0 di jam masuk 07–09; 28 di jam pulang 17–19;
sisanya 10–15. Berkelompok dalam satu menit (14 pada 1 Sep 11:24) bahkan satu
detik (9 pada 5 Sep 13:25:50).

**Sebaran per kueri (25 Agu–6 Sep, 574 galat):**

| kueri yang ditolak | jumlah | artinya |
|---|---|---|
| `select group_access … link=pengawasan` | 272 | request foto Pengawasan mati di AuthGuard → thumbnail rusak |
| `select admin_notification … limit 20` | 84 | poller lonceng notifikasi (30 dtk, `layouts/app.blade.php:190`) — mati diam-diam |
| `select raw_cus_reservation … created_at >` | 34 | poller reservasi baru (20 dtk, `reservation/list.blade.php:1463`) — mati diam-diam |
| `select force_logout_at from users` | 24 | middleware logout-paksa, request apa pun |
| `link=checklist/my-checklist/absensi` | 6 | **halaman sungguhan** orang lain ikut mati |

**Mekanisme.** `resources/views/absensi/pengawasan.blade.php` baris **264** dan
**290** merender `<img src="{{ route('pengawasan.foto', $b->id) }}">` untuk tiap
selfie hari itu (rata-rata **49/hari**) dan tiap baris flag (mode `?semua=1`:
**179** flag terbuka). Satu gambar = satu request PHP penuh: boot Laravel,
sesi, kueri `group_access`, koneksi DB. Prod memakai **HTTP/2**, jadi browser
mengirim semuanya serentak. Batas proses/koneksi per akun di hosting bersama
tembus → `connect()` ditolak (EPERM) → galat 2002. `loading="lazy"` hanya
menahan yang di luar layar.

**Siapa yang memicu (group_id di kueri):** Finance 178 · Super Admin (Reza) 42
· Supervisor 24 · Coordinator Dentist 16 · Admin Backup (Nisa) 12.

**Dampak ke orang lain:** kecil tapi nyata — 6 pemuatan halaman sungguhan dan
118 panggilan poller mati di detik yang sama. Poller mati tak terlihat
pengguna; halaman mati terlihat sebagai "error".

**Batas hosting tak terbaca** (`/proc/lve` tertutup, `lveinfo` tak ada).
Kesimpulan mekanisme diambil dari pola waktu (gerombolan sedetik, satu
group_id per gerombolan), bukan dari membaca angka batasnya.

### Arah perbaikan A
1. **Jangan render 49–179 `<img>` sekaligus.** Muat foto saat baris/kartu
   dibuka atau diklik; strip harian dibatasi N + "muat lagi". Ini yang paling
   murah dan menghilangkan gerombolannya.
2. Struktural (opsional): sajikan foto **tanpa boot Laravel** — tautan
   bertanda tangan/berumur pendek ke berkas yang dilayani web server
   langsung. Hemat satu proses PHP per foto.
3. Poller: hentikan `fetchNotifications` dan `checkNewReservations` saat tab
   tersembunyi (`document.visibilityState`). Bukan penyebab, tapi ikut
   memakan jatah koneksi tiap 20–30 detik per tab terbuka.

## 2. "Double absen" — masuk lalu pulang dalam hitungan detik

**Pagar duplikat BEKERJA.** 7 hari: 0 scan ganda persis, 0 `att_day` ganda,
0 `att_hp_bukti` ganda. `firstOrCreate` + `tanpaRaceDuplikat` +
penolakan "sudah absen jenis ini hari ini" (`AkunAbsenController::store`
baris 93–103) semuanya berfungsi.

**Yang sebenarnya terjadi — 4 kasus sejak absen HP hidup (5 Agu):**

| orang | tanggal | pulang setelah masuk | akibat |
|---|---|---|---|
| Cici Nurjanah | 15 Agu | +65 dtk | — |
| Robi | 21 Agu | +17 dtk | `single_scan` |
| Rokhmatun | 3 Sep | +19 dtk | `single_scan` (flag `baru`) |
| Mutiara Nurul Fajri | 5 Sep | +6 dtk | `single_scan` (flag `baru`) |

**Tidak** berkorelasi dengan galat 2002 (0 galat di sekitar keduanya minggu
ini). Murni ketukan ganda.

**Mekanisme.** `resources/views/absensi/akun/absen.blade.php` baris 136:
`$jenisBerikut = $masuk ? 'pulang' : 'masuk'`. Begitu masuk tercatat, halaman
reload (`btn-selesai` → `location.reload()`, baris 535) dan tombol utama
**langsung** "Absen Pulang". Orang yang mengira ketukan pertamanya gagal
mengetuk lagi → pulang. Lalu `AttendanceReconciler` melipat scan berjarak
≤5 menit (`$dedupMinutes = 5`) → `scan_count=1` → flag `single_scan` "lupa
pulang". Sore harinya pulang sungguhan **ditolak**: "Kamu sudah absen pulang
hari ini (09.58)". Orangnya tak bisa memperbaiki sendiri. **4 dari 12** flag
`single_scan` sejak Agustus sebenarnya kasus ini, bukan lupa.

### Arah perbaikan B
1. **Jeda minimum masuk→pulang** (usul: 30 menit) — tombol pulang mati dan
   *bilang kenapa* (pola P3): "Baru absen masuk 6 detik lalu."
2. Atau konfirmasi bila kurang dari jeda: "Kamu baru absen masuk N menit lalu.
   Yakin ini absen pulang?"
3. Pulang yang ≤5 menit setelah masuk boleh **ditimpa** pulang berikutnya
   (jangan ditolak) — ini yang menutup lubang "tak bisa memperbaiki sendiri".
4. Dua flag yang masih `baru` (Rokhmatun 3 Sep, Mutiara 5 Sep) ditutup lewat
   layar koreksi. Cici dan Robi sudah lewat periode — keputusan Ekky.

## 3. Petty cash — "gagal input tapi berhasil" = kiriman ganda

**Alur simpan aman:** `PettyCashController::store` (baris 658–725) validasi →
`create()` → redirect dengan flash. Tak ada langkah sesudah `create()` yang
bisa gagal. **0 galat 2002 pada perintah tulis** dalam 2 minggu.

**Yang ditemukan (14 hari, dari 7 baris yang dihapus):**

| baris | pembuat | selisih dengan kembarannya | siapa menghapus |
|---|---|---|---|
| #187 ↔ #188 "reimburse gojek th riska" Rp62.900 | Firda | **+4 detik** | Admin_Reza, besoknya |
| #344 ↔ #345 "kasbon drg nugroho" Rp25.000 | Firda | **+0 detik** | Firda, semenit kemudian |
| #296–298 (3 baris transfer) → #300–302 | Reza | dihapus & dibuat ulang 3 menit | Reza (koreksi, bukan galat) |
| #318, #395, #396 | Firda | tanpa kembaran | koreksi biasa |

Dua kiriman ganda, keduanya **tanpa** galat 2002 di menit itu. Selisih 0 detik
= klik ganda; 4 detik = "halaman belum berpindah, klik lagi". Halaman tujuan
redirect (`pettycash` index) berat — menghitung saldo semua sumber dana — jadi
jeda 2–4 detik sebelum flash "berhasil" muncul itu wajar, dan di jeda itulah
orang mengklik lagi.

**Form tambah pengeluaran TIDAK punya pengaman klik ganda.**
`submitBtn.disabled = true` di `crud/pettycash/index.blade.php` baris 1149
milik modal **edit** (hitung mundur jendela 8 jam), bukan form tambah.

**Tidak ada kembaran di ROP** (0 dari 14 hari) dan **tidak ada di att_request**
(satu temuan awal, Anisa lembur 3 Sep +51 dtk, ternyata dua lembur berbeda:
07:29–10:00 ke Bintaro dan 18:00–19:24 bikin materi — **bukan** duplikat).

### Arah perbaikan C
1. Pengaman kirim di form tambah dan transfer: `disabled` + teks "Menyimpan…"
   begitu diklik (pola P3), pulih kalau respons gagal.
2. Pagar sisi server: tolak baris identik (holder+amount+description+trx_date)
   yang datang ≤10 detik setelah yang terakhir — pola yang sama dengan pagar
   lembur kembar: kembar persis ditolak, mirip tetap boleh.
3. Periksa form lain dengan pola redirect-berat yang sama: pengajuan ROP,
   pengajuan absensi. Belum ada kembaran, tapi pengamannya sama-sama tak ada.

## 4. "Beberapa halaman sulit diakses" — belum terukur

Log tak mencatat halaman lambat, hanya halaman gagal. Yang bisa dipastikan:
server tidak lambat secara dasar (signin 56–139 ms, load 37,8 pada 64 core),
tak ada timeout atau memori habis di `error_log`. Kemungkinan yang tersisa:
(a) halaman yang kebetulan dimuat di detik gerombolan Pengawasan, (b) halaman
berat seperti petty cash index atau lembar gaji, (c) pentalan 302 ke landing
karena node menu tanpa grant (pola `SKIP_RBAC`, tak masuk log).
**Butuh nama halamannya dari Ekky** untuk diukur.

## 5. Catatan ops yang ditemukan sambil jalan
- `storage/logs/laravel.log` **tunggal 326 MB**, tak pernah dirotasi.
  `LOG_CHANNEL=daily` di `.env` menyelesaikannya; berkas lama dipangkas manual.
- `crontab -l` user **kosong** — penjadwal jalan lewat hPanel Hostinger.
  Tidak salah, tapi tak terlihat dari SSH.
- `error_log` di akar laravel cuma berisi peringatan `monarxprotect-php82.so`
  (modul hosting yang hilang), terakhir 29 Juli. Aman.

## 6. Cara mengulang pengukuran

```bash
L=storage/logs/laravel.log
# galat 2002 per hari
for d in 2026-09-0{1..6}; do echo "$d $(grep -c "^\[$d.*\[2002\]" $L)"; done
# halaman yang kena di AuthGuard
grep "\[2002\].*group_access" $L | grep -oE "and .link. = [a-z_/-]+" | sort | uniq -c
```

```sql
-- masuk→pulang ≤5 menit
SELECT m.user_id, m.taken_at, TIMESTAMPDIFF(SECOND, m.taken_at, p.taken_at) dt
FROM att_hp_bukti m
JOIN att_hp_bukti p ON p.user_id = m.user_id AND p.jenis = 'pulang'
  AND p.taken_at > m.taken_at AND p.taken_at <= m.taken_at + INTERVAL 5 MINUTE
WHERE m.jenis = 'masuk';

-- petty cash kembar (termasuk yang sudah dihapus)
SELECT a.id, b.id, a.amount, a.description, TIMESTAMPDIFF(SECOND, a.created_at, b.created_at) dt
FROM petty_cash_transactions a
JOIN petty_cash_transactions b ON b.created_by = a.created_by AND b.amount = a.amount
  AND b.description = a.description AND b.trx_date = a.trx_date AND b.id > a.id
  AND b.created_at <= a.created_at + INTERVAL 30 MINUTE;
```
