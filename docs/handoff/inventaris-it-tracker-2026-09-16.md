# Inventaris IT Tracker vs kenyataan PROD — 16 Sep 2026

> Disusun HUB. Tiap baris **diadu langsung dengan prod**, bukan dibaca dari tiket.
> Prod = `23fb5b1`. Untuk Ikhsan.

---

## 1. Sudah di PROD, tapi papannya belum menyusul

Tiga tiket ini menahan tempat padahal barangnya sudah jalan.

| tiket | fase sekarang | bukti di prod | seharusnya |
|---|---|---|---|
| **IT-2026-018 Pusat Akun** | ANTRE, 54 hr | rute `setkesehatan` + `setusermanagement/reset` **hidup** | maju ke UAT/GOLIVE — kamu benar soal ini |
| **IT-2026-004 Target: input step=1** | DEV, **71 hr** | `crud/branch/edit.blade.php:148` sudah `step="1"` | **tutup** — ikut naik lewat gerbong lain |
| **IT-2026-005 IT Request Tracker** | UAT | papannya berisi 36 tiket, kita baca hari ini | fase UAT wajar; yang salah **modulnya** (lihat §4) |

---

## 2. Belum di PROD — tiketnya akurat

| tiket | fase | bukti HUB |
|---|---|---|
| IT-2026-002 WiFi: cegat IP kembar | DEV 34 hr | commit `9fac952` cuma ada di `origin/feature/wifi-belajar-sendiri`, **bukan** main |
| IT-2026-003 Alpajri koreksi checklist 27 Agu | DEV 15 hr | baris 27 Agu masih `status=missed` di prod |
| IT-2026-009 Komisi dokter 70% + baris manual | DEV 6 hr | modul dasar live, tapi logika persentase belum ada |
| ~~IT-2026-010 Jatah cuti~~ | **SUDAH LIVE** | ⚠️ RALAT 16 Sep: `att_cuti_periode` 33 baris + `CutiService` lengkap (sisa/potong/cutiBersama/koreksi/kontrakBaru/resetKedaluwarsa) + layar `absensi/cuti` + tersambung **Lembar Gaji**. Tiket sudah dipindah ke GOLIVE. |
| IT-2026-019 Security hardening | DEV 6 hr | ⛔ `middlewareGroups['web']` **TANPA throttle** — P0 terkonfirmasi |
| IT-2026-025 Detail pasien di reservasi | DEV **85 hr** | `_modal_patient_detail.blade.php` tak ada di main/prod; branch **23 Juni**, 1 commit |
| IT-2026-026 Cabut Admin* dari ROLE_PENGAWAS | DEV 12 hr | prod masih memuat `Admin General`, `Admin Backup`, `Admin` |
| IT-2026-027 Merge saklar Accurate | DEV 19 hr | branch 2 commit belum masuk main |

---

## 3. ⛔ Tiga tiket di UAT yang kodenya belum sampai prod

UAT berarti "menunggu pemohon mencoba". Tapi tiga ini **tak ada yang bisa
dicoba di prod** — jadi fasenya lebih maju daripada barangnya.

| tiket | keadaan sebenarnya |
|---|---|
| **IT-2026-006 Alur Persetujuan + delegasi** | Tak ada di main, tak ada di branch fitur. ✅ **RALAT 16 Sep: kerjaannya AMAN** — kelima berkas byte-identik (beda cuma CRLF) dengan `origin/wip/snapshot-konsultan-alur-2026-08-22` yang sudah di remote. Peringatan HUB sebelumnya bahwa ia bisa hilang kena `git reset --hard` **KELIRU dan dicabut**. Yang benar: ia **mandek**, bukan terancam — sudah 25 hari tak bergerak, dan dev pun sudah pindah ke branch Reza. |
| **IT-2026-007 Zona akun dokter** | nol rute dokter di prod. Belum jalan di DB mana pun. |
| **IT-2026-008 Laporan Target vs Pencapaian** | `PencapaianTarget` tak ada di prod. Branch 6 commit, berhenti 1 Sep. ⚠️ Yang **sudah** beres 29 Agu itu **DATANYA** (target cuma terisi 7 dari 72 baris) — bukan kodenya. Itu sebabnya terasa sudah selesai. |

---

## 4. Katalog Modul — angkanya tidak bisa dipakai

⚠️ **RALAT 16 Sep — bagian ini semula KELIRU.** HUB sempat menulis katalognya
melaporkan "26 dari 37 tak terpakai". **Katalognya tidak pernah bilang begitu.**
Modul tanpa `pakai_tabel` disimpan **NULL** dan layar menulis "—" (belum
terukur), sengaja dibedakan dari 0 (sunyi). Yang keliru adalah skrip probe HUB
sendiri, yang meng-cast NULL jadi 0.

Yang BENAR: 25 dari 37 modul **belum punya alat ukur** — belum terukur, bukan
salah label. ✅ **Sudah dikerjakan 16 Sep**: 7 alat ukur dipasang, kini **19
modul terukur**. Yang ikut terpasang dan langsung membuktikan sesuatu:

- **Pengawasan Absen** -> `att_flag` = **114** dalam 7 hari (tersibuk ke-7)
- **Akun Saya** -> `att_request` = 84 · **Data Pasien** -> `raw_customer` = 44
- **IT Request Tracker** -> `it_ticket_fase` = 12 · **Master Jadwal** = 3
- **Referral & Member** = 1 · **Promo** = 0 (terukur & sunyi — itu informasi)

⛔ **18 modul sengaja TETAP tanpa alat ukur**: 6 baca-saja (Dashboard Sales/
Marketing, Executive Report, Komisi Dokter, Laporan Penjualan, Ringkasan) —
tak ada tabel yang tumbuh saat orang melihat, dan prod tak punya jejak
kunjungan halaman; 1 Pusat Akun (`users.updated_at` DITOLAK: 32 akun tersentuh
serentak = pembaruan massal, bukan pemakaian); 11 sisanya belum live.

### Yang sudah terukur (12) — angkanya sehat

```
Kontak & Percakapan 845 · Omnicare 688 · Reservasi 360 · Absensi 318
Accurate 172 · Checklist 117 · Petty Cash 102 · Lead Cekat 92
Persetujuan 64 · ROP 30 · Konten 19 · Keluhan Sistem 0
```

### Status modul juga melenceng

3 modul bertanda `dibangun` — ini yang kamu lihat di bagian "dikerjakan":

| modul | kenyataan |
|---|---|
| **IT Request Tracker** | ⛔ **salah** — sudah LIVE, papannya berisi 36 tiket. Ubah ke `hidup`. |
| **Target vs Pencapaian** | benar — branch 6 commit, belum naik |
| **Akun Dokter** | benar — nol rute di prod |

Jadi dari 3 yang "dikerjakan", **cuma 2 yang benar-benar dalam pengerjaan.**

---

## 5. Yang sedang jalan malam ini

| gerbong | tiket terkait | keadaan |
|---|---|---|
| ROP batal persetujuan | IT-2026-034 (GOLIVE, evaluasi ROP) | commit `868effc`, teraudit, siap |
| Penjodohan Omnicare | IT-2026-011 (ANTRE) | 77 uji hijau, siap, belum di-commit |
| Petty cash Reza | IT-2026-031 (GOLIVE, evaluasi petty cash) | ditahan — pagar `close()` |

⭐ **IT-2026-034 bisa langsung dijawab**: pertanyaannya *"adopsi meledak, apakah
alurnya menahan?"* — jawabannya **tidak**, dan sudah terbukti. Antrian tembus 41
pengajuan, dan 3 pengajuan Della tertahan 13 hari karena pintu Tolak tak pernah
dibuat. Perbaikannya sudah jadi dan naik malam ini.

---

## 6. Urutan rapi-rapi yang HUB sarankan

**Hari ini, sebelum apa pun:**

1. ~~Selamatkan Alur Persetujuan~~ — **DICABUT 16 Sep**: sudah aman di
   `origin/wip/snapshot-konsultan-alur-2026-08-22` (byte-identik). Yang tersisa
   bukan penyelamatan melainkan keputusan: mau dilanjutkan ke branch fitur &
   dinaikkan, atau tiketnya diturunkan dari UAT.

**Pembetulan papan (data, bukan koding):**

2. Tutup **IT-2026-004** — sudah di prod.
3. Majukan **IT-2026-018 Pusat Akun** dari ANTRE.
4. Turunkan **IT-2026-007** dan **IT-2026-008** dari UAT — kodenya belum sampai prod.
5. Ubah modul **IT Request Tracker** dari `dibangun` → `hidup`.
6. Isi `pakai_tabel` untuk 25 modul sisanya **sebelum angka katalog dipakai di
   deck mana pun**. Modul yang tak punya tabel penanda = temuan tersendiri:
   artinya kita tak punya cara tahu ia dipakai atau tidak.

**Jawab yang tinggal dijawab:**

7. Empat evaluasi GOLIVE menunggu `executive` 12 hari. IT-2026-034 sudah punya
   jawabannya.

**Dibiarkan atas keputusanmu:** IT-2026-015 Rolling (dijeda beberapa hari).
