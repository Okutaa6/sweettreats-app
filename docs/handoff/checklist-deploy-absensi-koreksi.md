# Checklist Deploy — Koreksi Absen · Ambang DODO · Ekspor Rentang

> Branch `feat/absensi-koreksi`. Dev lulus **72 uji, 197 assertion**.
> **Prod belum disentuh.** Berkas ini dibuat supaya urutannya tak perlu diingat
> ulang, dan supaya yang butuh keputusan manusia tidak diam-diam diputuskan chat.

---

## 0. Yang HARUS diputuskan Ekky lebih dulu (bukan kerjaan koding)

| # | Keputusan | Kenapa tak bisa diputuskan sendiri oleh chat |
|---|---|---|
| **0a** | **Ambang DODO mau mulai berlaku kapan?** | Lembar Gaji **menghitung ulang bulan lampau secara langsung**, jadi ambang 7 jam **mengubah bulan yang mungkin sudah dibayar**. Lihat §2. |
| **0b** | **Della/Finance sudah diberi tahu?** | Kolom DODO mereka akan berubah. Pola yang sama pernah diurus saat kolom CSV di-rename — jangan diulang terbalik. |
| **0c** | **Tanggal Dodi benar-benar mulai kerja** | Skrip §B memakai **hari ini** karena Dodi nol scan — tak ada bukti. Kalau HR tahu tanggal aslinya, ganti sebelum jalan. |
| **0d** | **Alasan koreksi Melani & Putri** | Wajib diketik manusia dan tersimpan permanen. Draf: *"Sunter tidak libur Maulid, tapi ybs diliburkan bergantian — dikonfirmasi Nissa."* Ganti kalau faktanya lain. |
| **0e** | **Perbaikan cuti hari libur yang menggantung mau diselamatkan?** | Utang #13. Skrip §A tetap aman tanpa itu, tapi **pengajuan cuti berikutnya masih memotong hari libur**. Ini pekerjaan terpisah. |
| **0f** | **Izin prod eksplisit** | Semua langkah di bawah menyentuh produksi. |

---

## 1. Deploy kode

**Butuh:** 1 SQL · deploy **PENUH** (5 kelas baru) · **nol rsync** (gaya sengaja
inline di blade).

```
# a. SQL dulu — tabel baru, belum dirujuk siapa pun sampai kodenya naik
#    (kodenya pun aman kalau terbalik: ada penjaga Schema::hasTable)
database/sql/2026_09_05_att_day_koreksi.sql

# b. deploy
cd <prod-root> && bash deploy.sh main
```

**Verifikasi sesudahnya — jangan cuma baca log:**
```
curl -s -o /dev/null -w '%{http_code}' https://app.sweettreats.id/     # WAJIB 200
```
`laravel.log` nol galat **tidak** berarti sehat — gagalnya bisa sebelum Laravel
hidup. Periksa juga URL akhirnya, `curl -L` bisa mengikuti pentalan ke login
lalu melapor 200.

---

## 2. ⚠️ Ambang DODO — set ke **0** dulu, bukan dibiarkan default

Bawaan di kode **420 menit (7 jam)**. Kalau dibiarkan, ia langsung berlaku —
**termasuk untuk bulan-bulan yang sudah lewat**, karena Lembar Gaji menghitung
ulang tiap kali dibuka.

Catatan deploy 24–27 Agu menyebut DODO Agustus prod: **Afrina 115 menit** dan
**Della R 120 menit** (total 484). Keduanya klaim **satu hari di bawah 7 jam** →
dengan ambang 420 keduanya jadi **0 DODO**, pindah ke lembur biasa. Totalnya
tidak berubah (`biasa + DODO` kekal), tapi **pembagiannya** berubah — dan
pembagian itulah yang dikalikan tarif berbeda.

> Angka di atas dari catatan, **bukan diukur** — chat ini tak menyentuh prod.
> Ukur ulang sebelum memutuskan.

**Yang disarankan:**
```sql
-- perilaku PERSIS seperti hari ini, nol kejutan
INSERT INTO sys_setting (`key`, `value`) VALUES ('dodo_ambang_menit', '0')
ON DUPLICATE KEY UPDATE `value` = '0';
```
Lalu buka **Absensi → Lembar Gaji → Agustus** dan pastikan angkanya **sama
persis** dengan sebelum deploy. Sesudah Finance siap, ubah ke `420` lewat layar
Lembar Gaji (tercatat di jejak audit, tak perlu deploy).

---

## 3. §C Melani & Putri — pemakaian pertama fitur barunya

Lewat layar, **bukan tembak DB** — sekalian membuktikan fiturnya bekerja.

`/absensi/koreksi` → Melani, `2026-08-25`, status **libur**, alasan dari §0d.
Ulangi untuk Putri.

**Verifikasi:** buka Kehadiran Agustus → keduanya `libur`; lalu **jalankan
rekonsiliasi 25 Agu sekali lagi** dan pastikan statusnya **tetap** `libur`.
Itu membuktikan koreksinya bertahan melewati penjadwal harian — cacat yang
paling mungkin, dan yang paling sulit dihubungkan kalau terlewat.

⚠️ Kalau ditolak karena **periode gaji tertutup**, jangan diakali. Itu pagar
bekerja: angka di sana sudah dibayarkan. Buka periodenya lewat Finance dulu.

---

## 4. §B Aturan kerja — Alpajri, Ela, Dodi

```
database/sql/2026_09_05_aturan_kerja_alpajri_ela_dodi.sql     # per blok, baca tiap SELECT
php artisan att:reconcile 2026-09-02 2026-09-05               # backfill Ela — WAJIB
```

- `effective_from` Ela **diambil dari scan pertamanya**, bukan diketik.
- Cabang Alpajri **tetap NULL** — disengaja. Jangan "dirapikan".
- **Verifikasi ABG**: blok 4 mencetak daftar orang ber-aturan `fleksibel`.
  Ela & Dodi **harus** ada di situ — itulah arti "masuk ABG".

---

## 5. §A Koreksi buku besar cuti — Yovanka & Ramdan

```
database/sql/2026_09_05_koreksi_cuti_yovanka_ramdan.sql        # per blok
```

⚠️ **Ramdan: sisa TETAP 11 sesudahnya, dan itu BENAR.** Yang diperbaiki
catatannya (5 baris untuk 1 hari cuti), bukan angkanya. **Kalau saldo Ramdan
berubah, ada yang keliru** — blok 5 memuat cara membatalkannya.

Yovanka: minus → **tidak minus** (naik 2 hari).

Kendali negatif di blok 1 & 4 **harus sama persis**.

---

## 6. Sesudah semuanya

```
php artisan att:periksa-kejanggalan --hari=120
```
Harapan: Yovanka & Ramdan **tidak** muncul lagi (pembaliknya dikenali lewat
`ref_id = 'batal-<id>'`), dan daftar "tanpa aturan kerja" menyusut.

Penjadwal harian **06:30** menjalankannya sendiri mulai besok.

---

## 7. Yang SENGAJA belum dikerjakan — bukan lupa

1. **Matriks Kehadiran tak menandai sel yang sudah dikoreksi.** Jejaknya lengkap
   di `/absensi/koreksi` dan `audit_log`, tapi di layar yang orang benar-benar
   lihat, hari koreksian tampak identik dengan hasil hitungan. Perbaikannya
   menyentuh perender sel JS di layar yang dipakai harian — bukan pekerjaan yang
   dikerjakan berbarengan dengan deploy.
2. **Utang #13** — perbaikan "cuti tak memakan hari libur" masih menggantung
   tanpa commit di worktree `sweettreats-dodo`. Selama itu belum naik, §A
   membersihkan yang telanjur tapi keran barunya masih menetes.

---

## 8. Kalau harus mundur

- **Kode**: `git checkout <commit sebelumnya> && bash deploy.sh` — nol SQL yang
  merusak, tabel `att_day_koreksi` boleh ditinggal (kode lama tak menyentuhnya,
  ada penjaga `Schema::hasTable`).
- **Ambang DODO**: set `dodo_ambang_menit = 0` → perilaku lama seketika, tanpa
  deploy.
- **Koreksi absen**: cabut lewat layar; hari itu kembali ke hasil rekonsiliasi.
- **§A**: blok 5 di skripnya — pembalik ber-`ref_id` `batal-*` aman dihapus
  (belum jadi rujukan apa pun). **Baris aslinya jangan disentuh.**
