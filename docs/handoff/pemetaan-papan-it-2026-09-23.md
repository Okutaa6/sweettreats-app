# Pemetaan & audit papan IT Tracker — 23 September 2026

> Disusun HUB sesudah menyelamatkan tiga branch menggantung, meng-cherry-pick
> perbaikan cuti ke atas main, dan menguji E2E di dev.
> **47 tiket** (naik dari 42 pagi ini — 5 lahir hari ini: 043, 044, 045→kembali
> ANTRE, 046, 047).

---

## Papan hari ini

| Fase | Jumlah | Catatan |
|---|---|---|
| ANTRE | 11 | 4 lahir hari ini |
| GOLIVE | 11 | ⚠️ lihat §3 |
| TRIASE | 7 | ⚠️ 5 **tanpa PIC**, tertua **50 hari** |
| SELESAI | 6 | +IT-003 (Alpajri) hari ini |
| DITUNDA | 5 | sadar, tak perlu tindakan |
| DEV | 3 | +IT-047 (cuti) hari ini |
| ANALISIS | 2 | ORTHO & COGS |
| EVALUASI | 1 | |

---

## 1. Yang dikerjakan & dibuktikan hari ini

**Perbaikan cuti hari libur — siap naik prod.**
`origin/rilis/cuti-hari-libur` = cherry-pick `4c6fc98` di atas main, **merge bersih**,
`AttendanceReconciler` tetap memakai `kolomAda()` milik main (tidak mundur ke
`Schema::hasColumn` yang membunuh mysqld lokal).

**E2E di dev: 10 lulus · 0 gagal.** Skenario cuti Minggu–Selasa melintasi Senin:

| Uji | Hasil |
|---|---|
| Minggu (klinik buka) dipotong | ✅ |
| Selasa (klinik buka) dipotong | ✅ |
| **Senin (libur) TIDAK dipotong** | ✅ inti perbaikannya |
| Potongan lahir 2, bukan 3 | ✅ |
| Idempoten (panggil ulang tak dobel) | ✅ |
| Gagal-aman (tanggal 3 tahun ke depan) | ✅ tak meledak |
| Rollback bersih | ✅ nol jejak |

⚠️ **Yang dipakai harness E2E, BUKAN phpunit** — `phpunit` tidak terpasang di dev
(`composer install --no-dev`), dan mysqld lokal sudah tidak mau hidup sejak 17 Sep
(InnoDB LSN in the future). Memasang dev-dependency di dev berarti menulis ulang
`vendor/` di lingkungan bersama — ditolak sebagai terlalu berisiko demi satu uji.
**Jadi 1.627 baris uji formal di branch itu BELUM pernah dijalankan.** Itu utang,
dan sengaja dicatat.

**Dev dikembalikan persis:** `test/reza-checklist-dev @ d95f9d3`, 6 berkas
untracked, identik dengan keadaan awal. Tiga berkas untracked yang menghalangi
checkout dicadangkan lalu dipasang kembali.

## 2. ⚠️ Lubang yang ditemukan: kerjaan siap tanpa tiket

Perbaikan cuti sudah selesai, teruji, dan merge-bersih — **tapi tidak ada satu
tiket pun yang melacaknya.** `IT-2026-010 Jatah cuti per kontrak 12 bulan` (GOLIVE)
itu mekanisme **jatahnya**, bukan potongan hari libur.

→ Dibuka **IT-2026-047** (M/P1, fase DEV, PIC Ikhsan, sumber **insiden**).

Ini kelas masalah yang sama dengan branch menggantung: **pekerjaan hidup di luar
papan.** Kalau papan dipakai untuk memutuskan apa yang dikerjakan, pekerjaan tak
bertiket tak akan pernah dijadwalkan — ia cuma menunggu seseorang ingat.

## 3. Yang perlu putusanmu (HUB sengaja TIDAK memindahkan)

**a. Empat tiket evaluasi duduk di GOLIVE 19 hari.**
IT-031 (Petty Cash) · IT-032 (Checklist) · IT-033 (Lead Cekat) · IT-034 (ROP).
Jenjang M berakhir di GOLIVE (`pathFor('M')` tak punya EVALUASI), jadi langkah
berikutnya SELESAI.

**IT-2026-034 kandidat paling jelas:** kemajuannya sudah berbunyi *"DIJAWAB 17 Sep:
alurnya TIDAK menahan…"*, dan temuan itu **sudah melahirkan IT-040** yang kini
SELESAI. Evaluasinya tuntas dan sudah ditindaklanjuti.
Tiga sisanya berkemajuan **kosong** — belum dievaluasi, jadi GOLIVE memang benar.

HUB tidak memindahkannya sendiri: menyatakan sebuah evaluasi "selesai" itu
putusan bisnis, bukan putusan IT.

**b. Lima TRIASE tanpa PIC, tertua 50 hari.**
IT-015 (Rolling global, **50 hari**) · IT-014 · IT-016 · IT-017 · IT-029.
Tiket di TRIASE menunggu **penilaian jenjang**, dan itu wewenang Head IT. Selama
tak dinilai, ia tak masuk antrean dan tak terlihat sebagai kerjaan siapa pun.

**c. Judul IT-2026-026 tidak lagi menggambarkan isinya.**
Judulnya "Cabut Admin* dari ROLE_PENGAWAS" — tapi pencabutan itu **sengaja
ditolak** build chat (konstanta itu menggerakkan lima hal, termasuk siapa boleh
jadi evaluator). Yang dibuat: konstanta baru `ROLE_PARAF_BO`. Papan sedang
menjanjikan hal yang tidak akan terjadi.

## 4. Antrean naik prod menurut HUB

| # | Apa | Kenapa urutannya begitu |
|---|---|---|
| 1 | **IT-047 cuti hari libur** (`rilis/cuti-hari-libur`) | satu-satunya yang menyentuh UANG dan cacatnya berjalan sekarang |
| 2 | **IT-002 + IT-026 WiFi dedup** (`feat/wifi-dedup-role-pengawas`) | gerbong kecil sendiri — kalau besoknya ada yang tak bisa daftar WiFi, penyebabnya tak tercampur |
| — | rop-tabs | **tidak masuk antrean**; bahan rancangan, bukan barang siap pasang |

Nol SQL untuk keduanya. Nol `public/assets/**` → **tanpa rsync**.

⚠️ Sebelum IT-002/026 naik: jalankan `WifiDedupTest` & `ParafBusinessOwnerTest`,
pastikan **kendali positif** hijau (jaringan BARU tetap DITERIMA). Pagar yang
terlalu galak di jalur ini berakhir sebagai orang yang tidak bisa absen.

## 5. Utang yang dicatat, bukan disembunyikan

1. **1.627 baris uji cuti belum pernah dijalankan** — phpunit tak ada di dev,
   mysqld lokal mati. Perlu keputusan: pasang dev-dependency di dev (berisiko,
   lingkungan bersama), hidupkan lagi DB lokal, atau terima harness E2E sebagai
   penggantinya.
2. **`laravel.log` prod 312 MB**, didominasi galat webhook Maret.
3. **`Authenticate.php:15` memanggil `route('login')`** yang tak ada — kode mati
   sekarang, ranjau begitu middleware `auth` dipakai di rute web.
4. **405 pada absen HP belum bisa direproduksi** — butuh jam + siapa + sedang
   menekan apa.
