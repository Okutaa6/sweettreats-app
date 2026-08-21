# Rencana — Libur cabang mengikuti CABANG BERTUGAS, bukan cabang asal

> **Status**: rencana, ACC Ekky 2026-08-21. **⏰ ADA TENGGAT: Selasa 25 Agu 2026.**
> Kalau perbaikan lolos uji dev & naik prod sebelum Selasa, tiga orang di §2
> tak perlu dikoreksi sama sekali. Kalau tidak, rencana cadangan di §8.
>
> Semua fakta di bawah diverifikasi ke kode dan DB **prod** pada 2026-08-21.

---

## 1. Masalah dalam satu kalimat

Libur per-cabang dicocokkan ke **cabang asal orang** (`users.raw_branch_id`),
bukan ke **cabang tempat dia benar-benar bertugas hari itu**. Padahal libur
cabang artinya *"klinik ini tutup"* — yang terdampak adalah siapa pun yang hari
itu bertugas di sana.

`AttendanceReconciler` **tidak pernah** memanggil `positionBranchOn()` maupun
`cabangSesiUntuk()`. Fitur rolling sudah tahu persis siapa di mana pada tanggal
berapa; pencocokan liburnya saja yang tak pernah bertanya ke situ.

**Konsekuensi terpenting: orang jadi butuh form untuk sesuatu yang seharusnya
otomatis.** Padahal datanya sudah ada.

---

## 2. Tiga kasus nyata — Selasa 25 Agustus 2026

Maulid Nabi. **Yang TUTUP: Bintaro, Cinere, Kalideres. Yang BUKA: Sunter,
Pamulang** (keputusan operasional, sengaja tidak nasional — jangan "diperbaiki"
jadi semua-cabang).

| Orang | Cabang asal | Bertugas 25 Agu | Sekarang | Seharusnya |
|---|---|---|---|---|
| **Verini Selvia Naskah** | Sunter (buka) | **Cinere (tutup)** | hari kerja → tak masuk = **ALPA** | **libur** |
| **Della Raqisiah** | Cinere (tutup) | **Sunter (buka)** | **libur** → kerjanya hilang | hari kerja biasa |
| **Ramdan Ramdhani** | Kalideres (tutup) | **Pamulang (buka)** | **libur** → kerjanya hilang | hari kerja biasa |

Penempatan yang berlaku (`sys_user_position`, `kind = rolling`):
Verini → Cinere sejak 13 Agu (tanpa akhir) · Della → Sunter 18–31 Agu ·
Ramdan → Pamulang sejak 13 Agu (tanpa akhir).

### Kenapa "kerjanya hilang" bukan bahasa berlebihan

`AttendanceReconciler::reconcileUser()`, cabang `if ($libur)`:

```php
if ($libur) {
    if ($lembur > 0) { ...'hadir'... }   // cuma sah kalau ada form lembur
    if ($n === 0) continue;
    $out[] = $this->row(..., 'libur', 0, null, 0, $now);  // ADA scan, tetap 'libur'
}
```

Della & Ramdan akan **masuk sehari penuh, absen masuk dan pulang — dan barisnya
tercatat `libur`**. Scan-nya dibuang, nol jam, penilaian telat dilewati.
Satu-satunya jalur yang membuatnya "hadir" adalah **form lembur** — yang berarti
membayar upah lembur untuk hari kerja biasa. Itu bukan perbaikan, itu tambalan
yang salah harga.

---

## 3. Perbaikan — TAMBAH pengertian baru, jangan tukar yang lama

⚠️ **Jebakan utama.** `$home` di reconciler dipakai untuk **tiga hal berbeda**
yang kebetulan dilebur jadi satu variabel:

| Dipakai di | Artinya | Harus pakai |
|---|---|---|
| `resolveDay()` — cocokkan libur cabang | "klinik tempat dia kerja" | **cabang bertugas hari itu** ← satu-satunya yang berubah |
| `isDateLocked()` — periode gaji tertutup | "gajinya ikut cabang mana" | **cabang asal** — JANGAN diubah |
| `hapusHariBasi()` — buang baris basi | ikut penguncian di atas | **cabang asal** — JANGAN diubah |

Menukar `$home` borongan akan membuat **penguncian periode gaji ikut
berpindah-pindah mengikuti rolling** — kerusakan yang jauh lebih sunyi daripada
masalah yang sedang diperbaiki, dan menyentuh angka yang sudah di-sign-off
Finance. Jadi: **tambah** parameter baru (mis. `$cabangKerja`), sisakan `$home`
apa adanya.

### Aturan resolusi cabang bertugas

Pakai aturan yang **sudah ada** di `positionBranchOn()` / `cabangSesiUntuk()`
(`app/Helpers.php:786` & `:823`) — jangan tulis aturan baru. Ringkasnya: baris
`rolling` yang melingkupi tanggal itu menang atas `home`; tak ada rolling →
jatuh ke cabang asal.

### ⚠️ Performa — ini bisa membunuh reconcile

Reconciler memuat semuanya **sekali di depan** (aturan, hari, exception, cabang
asal sebagai satu `pluck`). Cabang bertugas itu per-orang **per-tanggal**.

**JANGAN memanggil `positionBranchOn()` di dalam loop harian** — itu jadi
jumlah-orang × jumlah-hari kueri (puluhan ribu untuk satu bulan penuh).
Pramuat seluruh baris `sys_user_position` sekali, lalu selesaikan di memori,
persis pola `loadExceptions()` / `loadRules()`.

---

## 4. Temuan kedua: kalender libur TIDAK PERNAH ditampilkan di layar mana pun

Ini yang membuat masalah §2 tak terdeteksi sampai Ekky bertanya.

- **Kehadiran** (`AbsensiKehadiranController`) hanya membaca `att_work_rule_day`
  (pola mingguan). Komentarnya sendiri: *"Hari libur DIBACA dari aturan kerja"*.
  `att_work_rule_exception` tak pernah disentuh.
- **Absensi Saya** memakai `hariLiburTerjadwal()`, yang docblock-nya menyatakan
  terang-terangan: *"Kalender libur nasional TIDAK dilihat di sini — itu urusan
  mesin rekonsiliasi."*

Jadi `att_work_rule_exception` **ditulis oleh satu layar dan dibaca oleh satu
pembaca saja: mesin rekonsiliasi.** Nisa tak punya cara memverifikasi
catatannya benar selain menunggu harinya lewat.

**Perbaikannya bukan kosmetik.** Kalau libur tampil di Kehadiran, Ekky akan
melihat sendiri cabang mana yang kosong — tanpa perlu bertanya. Layar yang
menampilkan keadaan adalah alat audit, bukan hiasan.

Catatan: pemisahan `hariLiburTerjadwal()` dari kalender libur **disengaja** dan
didokumentasikan (fungsi itu khusus pola mingguan, dipakai gerbang absen).
Jangan menambal dengan mengubah fungsi itu — **tambah sumber terpisah** untuk
tampilan kalender.

---

## 5. Temuan ketiga: rentang penempatan terbalik lolos tanpa validasi

Baris `sys_user_position` id `a2821d77-…` milik Verini:
`effective_from = 2026-08-15`, `effective_to = 2026-08-14` — **berakhir sebelum
dimulai**, panjangnya nol.

Tak berbahaya sekarang (baris lain yang berlaku). Tapi begitu perbaikan §3
mendarat, **data penempatan ikut menentukan libur, dan libur menentukan gaji.**
Rentang rusak yang lolos diam-diam jadi jauh lebih mahal. Tambahkan validasi
`effective_to >= effective_from` di titik simpannya, dan sapu baris yang sudah
telanjur ada.

---

## 6. Efek samping yang HARUS disadari

Setelah perbaikan ini, **data rolling menjadi menentukan gaji.** Rolling yang
salah cabang, lupa dicatat, atau lupa ditutup = libur yang salah untuk orang
yang salah. Sebelumnya kesalahan rolling "cuma" salah tampilan cabang.

Itu bukan alasan menolak perbaikan — cabang asal justru **sudah pasti salah**
untuk orang yang sedang rolling. Tapi §5 jadi bagian dari perbaikan, bukan
tambahan opsional.

---

## 7. Uji terima

Uji HTTP "hijau" tidak membuktikan apa pun: `curl -L` bisa mengikuti pentalan ke
halaman login lalu melapor 200. **Periksa URL akhirnya.**

| # | Yang diuji | Lulus kalau |
|---|---|---|
| 1 | Verini, 25 Agu | `att_day` = **libur**, tanpa form apa pun |
| 2 | Della, 25 Agu (dengan scan) | **hadir**, jam & telat dinilai normal |
| 3 | Ramdan, 25 Agu (dengan scan) | **hadir** |
| 4 | Orang non-rolling di Bintaro | tetap **libur** (tak ada regresi) |
| 5 | Orang non-rolling di Sunter | tetap **hari kerja** |
| 6 | Periode gaji tertutup | penguncian **tidak** ikut berpindah karena rolling |
| 7 | Reconcile sebulan penuh | jumlah kueri tak meledak; catat waktunya sebelum & sesudah |
| 8 | Libur ditambahkan SETELAH reconcile | `hapusHariBasi()` tetap membersihkan alpa lama |
| 9 | Kehadiran, 25 Agu | tampil penanda libur untuk 3 cabang tutup saja |
| 10 | Absensi Saya, 25 Agu | Verini melihat "Libur"; Della melihat hari kerja |
| 11 | Simpan penempatan `to` < `from` | **ditolak** |

Uji 1–3 **wajib memakai data prod yang diputar ulang di dev**, bukan data
karangan — ketiga orang itu kasus nyata dan namanya sudah diketahui.

---

## 8. Rencana cadangan kalau tidak sempat sebelum Selasa

Tidak apa-apa. Tinggal **3 baris `att_day`** yang dikoreksi setelah 25 Agu:
Verini `alpa` → `libur`; Della & Ramdan `libur` → `hadir`.

Dikerjakan chat HUB, dengan jejak audit bernama. **Jangan** menyuruh mereka
mengisi form lembur/izin — itu memasang harga yang salah pada keadaan yang
bukan salah mereka.

---

## 9. Yang sengaja TIDAK dikerjakan

1. **Jangan mengubah entri libur Nisa jadi "Semua cabang".** Sunter & Pamulang
   memang buka; tiga baris per-cabang itu **benar dan disengaja**.
2. **Jangan mengubah `hariLiburTerjadwal()`** untuk membaca kalender libur —
   fungsi itu dipakai gerbang absen dan pemisahannya disengaja (§4).
3. **Jangan menyentuh `isDateLocked()` / `hapusHariBasi()`** (§3).
4. Prod menunggu dev lulus §7.
