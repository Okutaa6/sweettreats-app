# Runbook deploy — malam 16 Sep 2026

> EMPAT gerbong, TIGA jendela. Disusun HUB, semuanya sudah diaudit & diuji.
> Prod saat mulai: `23fb5b1`.

---

## Yang sudah disiapkan

| | commit | SQL | rsync | keadaan |
|---|---|---|---|---|
| **Security throttle** | `dde45ae` | — | — | sudah di-push · **diuji end-to-end di dev** |
| **ROP Batalkan persetujuan** | `868effc` | — | **WAJIB** | belum di-push |
| **Penjodohan Omnicare** | `42e3923` | **2, manual** | — | belum di-push |
| **Petty cash** | `42398cc` | **2, manual** | **WAJIB** | 3 commit · diuji end-to-end di dev (7 adegan) |
| Dokumentasi | `eded2fb` | — | — | di `main` lokal, Drive sudah sinkron (94 entri, md5 cocok) |

### ⚠️ URUTAN DIUBAH — security DULU, bukan terakhir

Saran HUB sebelumnya "security naik terpisah **di akhir**". **Itu direvisi.**
Alasannya: satu-satunya gerbong yang bisa **mengunci semua orang keluar dari
sistem** adalah security. Kalau ia naik terakhir bersama dua yang lain dan login
rusak, ada tiga tersangka — dan tak ada yang bisa masuk untuk memperbaikinya.

Naik duluan sendirian: kalau login rusak, yang dibalik **satu commit**, dan dua
gerbong lain belum tersentuh sama sekali.

⛔ **`deploy.sh` TIDAK menjalankan SQL.** Keduanya dijalankan tangan.

---

## JENDELA 1 — security sendirian

```bash
cd ~/Downloads/sweettreats-main
git fetch origin && git merge --no-ff origin/hotfix/security-throttle
git push origin main

ssh -p 65002 -i ~/.ssh/rblas_ga u695655413@93.127.219.164
cd /home/u695655413/domains/app.sweettreats.id/laravel
bash deploy.sh main
php artisan config:clear && php artisan route:clear
```

⛔ **BERHENTI DI SINI dan buka `/signin` sungguhan sebelum lanjut.** Uji yang
wajib dilakukan manusia, bukan curl:

1. Salah ketik sandi **5×** dari satu akun → muncul pesan Indonesia bertuliskan
   sisa waktunya.
2. **Dari komputer yang sama**, masuk dengan akun LAIN memakai sandi yang benar
   → **harus berhasil**. Kalau ikut terkunci, `git revert` merge-nya sekarang
   juga dan jangan lanjut.

Sudah terbukti di dev (email A terkunci di percobaan ke-6, email B dari IP & sesi
sama tetap normal) — tapi dev bukan prod, dan yang dipertaruhkan di sini adalah
akses semua staf.

### Opsional, kapan saja sesudahnya: tutup webhook MaxChat

⛔ **Jangan dikerjakan kalau token belum disepakati dengan MaxChat.** Membiarkannya
terbuka semalam tidak lebih buruk dari keadaan sekarang; menutupnya lebih cepat
dari kesiapan mereka menghentikan ±859 chat/minggu **tanpa ada yang sadar**.

```bash
# sesudah token dipasang di sisi MaxChat:
# tambahkan MAXCHAT_WEBHOOK_TOKEN=<token> ke .env prod
php artisan config:clear
# lalu pantau beberapa jam:
#   SELECT COUNT(*) FROM raw_chat WHERE created_at >= NOW() - INTERVAL 2 HOUR;
# berhenti tumbuh = tokennya tidak cocok
```

Urutan ini sudah dilatih di dev: tanpa token 401 · token salah 401 · token benar
201 dan barisnya benar-benar masuk.

---

## JENDELA 2 — ROP + Penjodohan

### 0. Prasyarat — periksa dulu, jangan langsung jalan

```bash
# prod masih di titik yang diharapkan?
ssh -p 65002 -i ~/.ssh/rblas_ga u695655413@93.127.219.164 \
  'cd /home/u695655413/domains/app.sweettreats.id/laravel && git log --oneline -1'
# harus: titik sesudah JENDELA 1 (merge security), bukan 23fb5b1 lagi
```

⚠️ Kalau prod belum bergerak dari `23fb5b1`, berarti Jendela 1 gagal — **jangan
lanjut**, bereskan itu dulu.

### 1. Push & satukan ke main

```bash
git -C ~/Downloads/sweettreats push origin feat/penjodohan-omnicare
git -C ~/Downloads/sweettreats-rop-batal push origin feat/rop-batal-persetujuan

cd ~/Downloads/sweettreats-main        # sudah di main, sudah berisi docs 5e2dda6
git merge --no-ff feat/penjodohan-omnicare
git merge --no-ff feat/rop-batal-persetujuan
git push origin main
```

⚠️ **Merge TIDAK akan fast-forward** — kedua branch dibangun di atas basis lama.
Itu normal dan sudah diperiksa: berkas kode kedua gerbong **identik** dengan
main, jadi tak ada konflik nyata. Kalau tetap konflik, **berhenti dan lapor** —
berarti ada yang berubah sesudah audit.

### 2. SQL penjodohan — URUTAN MENGIKAT

Jalankan di phpMyAdmin prod (DB `u695655413_app_sweettreat`), **satu per satu,
baca keluarannya**:

```
1. database/sql/2026_09_16_omnicare_dob_backfill.sql
2. database/sql/2026_09_16_customer_omnicare_link_multi_pid.sql
```

- Berkas 1 mencetak `SEBELUM` / `RENCANA` / `SESUDAH` / `HASIL`.
  **Patokan prod: cakupan DOB sebelum = 7.597 / 15.732 (48,3%).**
  ⚠️ `RENCANA.akan_terisi` harus SAMA dengan `HASIL.benar_benar_terisi`.
- Berkas 2 berakhir dengan **dua** kueri pemeriksaan:
  - pid menempel ke >1 pasien → **harus NOL BARIS**. Kalau ada isinya,
    **BERHENTI** — dua orang berisiko bertukar rekam medis.
  - daftar index → harus tampil `uq_col_omnicare` (unik) dan
    `idx_col_customer` (tidak unik). `uq_col_customer` harus **hilang**.

### 3. Deploy kode

```bash
ssh -p 65002 -i ~/.ssh/rblas_ga u695655413@93.127.219.164
cd /home/u695655413/domains/app.sweettreats.id/laravel
bash deploy.sh main
```

### 4. ⛔ RSYNC — dibutuhkan ROP, jangan dilewat

ROP menyentuh `public/assets/css/rop.sweettreats.css`. `public_html` prod itu
**SALINAN**, bukan symlink.

```bash
rsync -av --delete \
  /home/u695655413/domains/app.sweettreats.id/laravel/public/assets/ \
  /home/u695655413/domains/app.sweettreats.id/public_html/assets/
```

⛔ **DIBATASI ke `assets/`.** Rsync seluruh `public/` pernah **mematikan prod 6
menit** — `index.php` prod sengaja berbeda.

⛔ Verifikasinya **bukan** kode 200 + ukuran (aset di-cache 7 hari):

```bash
md5sum /home/u695655413/domains/app.sweettreats.id/public_html/assets/css/rop.sweettreats.css
curl -s "https://app.sweettreats.id/assets/css/rop.sweettreats.css?v=$(date +%s)" | md5sum
```

Dua md5 itu **harus sama**. Kalau beda, coretan & cap "DIBATALKAN" di lembar
cetak tidak akan muncul — persis pengaman yang gerbong ROP bangun.

### 5. Perintah penjodohan — dry-run dulu, selalu

```bash
php artisan omnicare:match-patients --dry-run
php artisan omnicare:match-patients

php artisan omnicare:reconcile --perbaiki-batal --dry-run
php artisan omnicare:reconcile --perbaiki-batal

php artisan omnicare:reconcile --revisit --days=90 --dry-run
php artisan omnicare:reconcile --revisit --days=90
```

⚠️ Jalankan `--perbaiki-batal` **terpisah** dari `--revisit`. Kalau bersamaan,
tak akan ketahuan perubahan angka no-show disebabkan yang mana.

⚠️ **Kalau angka no-show berubah drastis, itu TEMUAN bukan kesalahan.** Patokan
yang sudah diketahui: pembatalan yang ikut dicap tidak hadir menaikkan angka
dari 18,2% jadi 26,8%.

⛔ Kalau laporan `match-patients` menyebut baris DITOLAK database, baca
sebabnya: `uq_col_omnicare` = pagar bekerja (periksa); `uq_col_customer` masih
terpasang = SQL langkah 2 belum jalan, **perbaikan ini nol efek**.

### 6. Backfill DOB SEKALI LAGI

```
database/sql/2026_09_16_omnicare_dob_backfill.sql
```

Pid baru membuka sumber DOB yang tadinya tak terjangkau. Aman dijalankan ulang —
jejak pemulihannya kini `CREATE TABLE IF NOT EXISTS` + `ON DUPLICATE KEY UPDATE`.

⚠️ Jalan kedua ini **tidak** memverifikasi ulang yang sudah terisi. Kalau pid
baru membawa DOB berbeda, hanya `omnicare:tinjau-tautan --dob` yang menangkapnya.

### 7. Triase tautan (tidak menghapus apa pun)

```bash
php artisan omnicare:tinjau-tautan --dob
php artisan omnicare:tinjau-tautan --dob --tulis
```

⚠️ **Harapkan angka besar: ~474 `dob_tertukar`.** Itu BUKAN 474 tautan salah
orang — itu jejak impor 12 Mei 2026 yang menulis hari↔bulan tertukar di sisi
kita. Yang patut dicurigai hanya `dob_bertentangan`.

---

## JENDELA 3 — Petty cash (paling akhir, sendirian)

> Branch `fix/pettycash-hentikan-migrasi` = **3 commit** (`212607e` rekonstruksi ·
> `a4dea38` pagar tutup-periode · `42398cc` tolak hentikan-migrasi).
> 25 berkas, 2 SQL, **rsync WAJIB**.

⛔ **Kenapa terakhir dan sendirian:** ini satu-satunya gerbong yang SQL-nya
menyentuh **skema dompet** dan yang perubahannya dilihat operator kas besok pagi.
Kalau ada yang aneh di petty cash, penyebabnya tidak boleh tercampur tiga gerbong
lain.

### 1. SQL — dua, manual, urutan bebas (keduanya aditif)

```
database/sql/2026_09_03_pettycash_retire_kategori_pinjaman.sql
database/sql/2026_09_09_pettycash_wallet_pensiun_per_cabang.sql
```

- Berkas 1 menonaktifkan kategori `pinjaman`. **Radiusnya kecil** — diukur prod:
  6 transaksi, Rp 168.000, terakhir dipakai **18 Agustus**. Riwayat lama tetap
  terbaca berlabel "Pinjaman"; yang hilang cuma kemampuan membuat pinjaman baru.
- Berkas 2 memakai `DELIMITER $$` yang **tidak bisa lewat PDO** — jalankan di
  phpMyAdmin. Kalau perlu jalur polos, kepala berkasnya menuliskan tiga `ALTER`
  yang setara.
- Pemeriksaan sesudahnya: `fund_source_branch_accounts` harus punya kolom
  `retired_at`, dan `account_number` harus jadi NULLABLE.

### 2. Deploy + rsync

```bash
bash deploy.sh main
rsync -av --delete \
  /home/u695655413/domains/app.sweettreats.id/laravel/public/assets/ \
  /home/u695655413/domains/app.sweettreats.id/public_html/assets/
```

⛔ Menyentuh `public/assets/css/components.sweettreats.css` → **rsync DIBATASI ke
`assets/`**, verifikasi md5 seperti Jendela 2.

ℹ️ CSS penanda "Migrasi berjalan" dari `42398cc` sengaja **inline di blade**,
bukan di `components.*` — jadi bagian itu tidak bergantung rsync. Yang butuh
rsync tetap commit pertama.

### 3. ⛔ JANGAN jalankan Migrasi Saldo malam ini

Deploy **tidak** memindahkan uang. `retiredIdsForBranch()` mengembalikan kosong
selama nol dompet dipensiunkan, jadi **setiap cabang tetap melihat semua
dompetnya**. Yang berubah besok pagi cuma: pinjaman hilang dari dropdown, lima
nama lama jadi "Pindah Saldo", kartu dompet tinggal 2 tombol.

Migrasi itu **opt-in, per cabang, Super Admin saja** — dan pengumumannya belum
benar untuk semua cabang (lihat di bawah).

### ⛔ Sebelum menjalankan migrasi di cabang mana pun — perbaiki pengumumannya

Pengumuman ke operator menyebut *"tinggal Cash & BCA"*. Diukur HUB di prod,
itu **tidak berlaku untuk dua cabang**:

```
Bintaro     BCA(117) · Cash(79) · GoPay(15) · BSI(10)     cocok
Sunter      BCA(76) · BSI(14) · GoPay(2) · Cash(1)        cocok
Cinere      BCA(86) · Cash(4)                             cocok
Kalideres   BSI(18) · BCA(9)                              ⛔ NOL Cash
Pamulang    Seabank(29) · BCA(9)                          ⛔ NOL Cash, dan SEABANK
```

Saldo awal September: Kalideres **BSI Rp 513.900**, Pamulang **Seabank
Rp 338.000**. Kalau dua cabang ini menuruti pengumuman apa adanya, mereka
mempensiunkan dompet utamanya sendiri berisi **Rp 851.900**, lalu disuruh
mengisi dompet Cash yang tidak mereka pakai.

ℹ️ Sistem **tidak memaksa** Cash+BCA — kodenya cuma mewajibkan minimal satu
dompet tersisa. Jadi ini murni kebijakan, dan bisa ditentukan per cabang tanpa
mengubah kode.

⚠️ Dua koreksi lain di pengumuman: **"Admin klik Migrasi Saldo"** sebenarnya
**Super Admin saja**; dan sekarang benar juga bahwa selama saldo belum diisi
ulang, **periodenya tidak bisa ditutup** — itu menguatkan instruksi "isi di hari
yang sama".

### Verifikasi sesudah naik

- `/finance/pettycash` → kartu dompet punya **2 tombol** (Pindah Saldo ·
  Pengeluaran), dan kategori `pinjaman` **tidak ada** di dropdown transaksi baru.
- Riwayat pinjaman lama **masih terbaca** berlabel "Pinjaman".
- Jangan menekan Migrasi Saldo.

### Sudah dibuktikan di dev (17 Sep, 7 adegan)

```
cancel tanpa migrasi          -> info ramah
mulai migrasi                 -> kartu 0, pesan menyebut DUA angka
cancel selagi jendela terbuka -> DITOLAK, nol angka bergeser
tutup periode saat jendela    -> DITOLAK
Super Admin isi Setoran Awal  -> jendela tertutup  <- JALAN KELUARNYA
cancel sesudah selesai        -> info ramah, stempel tak disentuh
tutup periode                 -> BERHASIL
```

---

## ⏳ TIDAK ikut malam ini

| | sebab |
|---|---|
| `feat/checklist-review-redesain-dan-multi-foto` (`1e0f993`) | **13 berkas · 2.117 baris tambah · 1 SQL baru · NOL uji.** Lebih besar dari ROP + penjodohan digabung. Diaudit besok. |
| WiFi dedup (IT-2026-002) + ROLE_PENGAWAS (IT-2026-026) | belum ditulis — prompt ada di `prompt-build-wifi-dedup-role-pengawas.md` |

---

## Verifikasi sesudah semua jalan

```bash
# 1. beranda hidup — DAN periksa URL akhirnya
curl -sL -o /dev/null -w '%{http_code} %{url_effective}\n' https://app.sweettreats.id/
# ⚠️ curl -L bisa mengikuti pentalan ke halaman login lalu melapor 200.
#    Kalau url_effective berakhir di /signin, itu BUKAN bukti sehat.

# 2. galat baru?
tail -50 storage/logs/laravel.log
# ⚠️ laravel.log NOL galat TIDAK berarti sehat — tetap curl beranda.
```

**Layar yang wajib dibuka manusia:**
- `/finance/rop` → buka satu pengajuan berstatus `disetujui` → tombol
  **"Batalkan persetujuan"** harus muncul di kaki drawer, di bawah garis,
  lebih kecil dari "Tandai dibayar".
- Cetak lembar `BAYAR/2609/001` → tiga baris tercoret bercap **DIBATALKAN**,
  dan di bawah TOTAL ada **−Rp 820.290** serta **YANG HARUS DIBAYAR
  Rp 16.283.545**. TOTAL lama tetap tercetak.
- `/reconcile` → ember baru untuk vonis `cancelled` harus punya label sendiri,
  bukan berlabel "NO-SHOW".

---

## Kalau harus mundur

- **Kode**: `git revert` commit merge-nya, deploy ulang. Kedua gerbong berdiri
  sendiri — boleh mundur satu tanpa yang lain.
- **Backfill DOB**: resep pemulihan ada di kaki berkas SQL-nya. Ia sengaja
  **melewati** baris yang sesudahnya dibetulkan staf.
- **ALTER multi-pid**: tidak perlu dibalik. Melepas `uq_col_customer` tidak
  merusak apa pun walau kodenya mundur — yang lama cuma tak memakai kelonggaran
  itu. `uq_col_omnicare` tetap menjaga.
- **Tautan yang terlanjur dibuat**: `DELETE FROM customer_omnicare_link WHERE
  matched_at >= '<tanggal deploy>'` — tapi **jangan** dijalankan tanpa melihat
  dulu berapa barisnya.

---

## Sesudah naik — jangan lupa

1. Perbarui entri log-update: ganti "belum di prod" jadi commit prod + angka
   hasil sungguhan (`match-patients` mencetak sebelum/sesudah). Tulis dari
   pohon `main`, lalu salin ke Drive — **periksa ISI, bukan jumlah entri**.
2. **Papan IT Tracker — geser SESUDAH naik, bukan sebelum.** Papan harus
   mencerminkan kenyataan, dan kenyataannya "belum naik" sampai benar-benar naik.

| tiket | dari | ke | catatan |
|---|---|---|---|
| **IT-2026-019** Security hardening | DEV | **GOLIVE** | ⚠️ mandat §6 belum disapu → **buka tiket baru** supaya sisanya tidak lenyap |
| **IT-2026-034** ROP evaluasi | GOLIVE | *(tetap)* | tidak digeser — **dijawab**. Tombol Batalkan tak punya tiket sendiri; ia jawaban atas evaluasi ini |
| **IT-2026-037** Penjodohan (BARU, lajur Support) | DEV | **SELESAI** | ✅ sudah dibuat 16 Sep — jenjang S, modul Integrasi Omnicare |
| **IT-2026-011** Reservasi & pasien dua arah | ANTRE | *(tetap ANTRE)* | ⛔ bukan ini yang naik — lihat di bawah |

### ⛔ Penjodohan ≠ reservasi dua arah — jangan disatukan

IT-2026-011 berjudul *"Reservasi & pasien dua arah ke Omnicare"*. Yang naik malam
ini **bukan itu**:

| | penjodohan (naik malam ini) | dua arah (IT-011, belum ada) |
|---|---|---|
| arah | **BACA** — data Omnicare yang sudah ditarik | **TULIS** — kirim reservasi/pasien KE Omnicare |
| tempat kerja | DB kita, algoritma pencocokan | API vendor, 6 endpoint WRITE |
| masalah yang ditutup | 35,7% kunjungan gagal jodoh | FO mengetik dua kali |
| bergantung pada vendor? | tidak | **ya** |

Menggeser IT-011 ke GOLIVE karena penjodohan naik akan **bohong** — sisi tulisnya
belum dibangun sama sekali.

✅ **SUDAH DIPUTUSKAN & DIKERJAKAN (Ikhsan, 16 Sep):** tiket baru **IT-2026-037**
dibuat di lajur **Support** (jenjang S, modul Integrasi Omnicare, fase DEV),
berisi catatan commit `42e3923` dan penegasan bahwa ini BUKAN sisi dorong.
IT-2026-011 tetap ANTRE untuk dua arah.

⚠️ Satu konsekuensi jenjang S: alurnya **Requirement → Development → Selesai**,
tanpa fase GOLIVE/Evaluasi. Jadi perubahan cara pasien dijodohkan ini tidak akan
punya fase evaluasi bawaan. Kalau kelak mau dievaluasi (mis. apakah 35,7% benar
turun), itu perlu tiket sendiri.

---

## Hasil — dieksekusi 17 Sep 2026 malam, EMPAT jendela, prod = `1d33d9c`

| Jendela | Isi | Bukti di prod |
|---|---|---|
| 1 | security throttle + honeypot | percobaan ke-6 terkunci; Ikhsan & Reza satu IP selang 1 dtk tak saling mengunci |
| 2 | ROP tolak setelah disetujui | BAYAR/2609/001: 15 dibayar Rp 16.283.545 + 3 ditolak Rp 820.290 |
| 3 | penjodohan Omnicare (rerun) | DOB 48,3%→91,4% · tautan +943 · kunjungan tertaut +323 · no-show 26,1%→16,9% · 598 `dob_tertukar` ditandai |
| 4 (tambahan) | petty cash audit-fix + checklist multi-foto | skema hidup, 0 dompet dipensiunkan (Migrasi Saldo oleh Reza 18 Sep) · backfill 1.029 foto, 0 gagal |

Papan IT Tracker sesudahnya: GOLIVE 11 · TRIASE 7 · ANTRE 6 · SELESAI 5 ·
DITUNDA 5 · DEV 4 · ANALISIS 2 · EVALUASI 1. Tiket baru: IT-2026-037 (penjodohan,
S), 040, 041. Dev dikembalikan ke `test/reza-checklist-dev @ d95f9d3`.
Branch uji sekali pakai `test/pettycash-checklist-dev` (48499b1) masih di origin.
