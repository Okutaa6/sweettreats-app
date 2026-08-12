# Rencana: Penempatan (staf kerja sementara di cabang lain)

> Status: ACC 2026-08-04 — **dikerjakan REZA**. Pasangannya: fitur Aturan
> Kerja dikerjakan Ekky (`rencana-aturan-kerja.md`).
>
> ⚠️ **Basis kode (diperbarui 2026-08-07): cabang dari `integrasi/dev-2026-08-06`**
> — branch integrasi yang berlaku sekarang, isinya sama dengan `main`/prod.
> `feature/rop` dan `release/2026-08-pengeluaran` yang disebut versi lama
> dokumen ini sudah **pensiun** — jangan dipakai lagi. Semua kerjaan tetap
> bermuara ke SATU branch integrasi, tak ada yang lompat langsung ke `main`.

## 1. Masalah nyata

FO Sunter di-rolling backup ke Bintaro. Hari itu:
- **Absensi**: sudah beres — scan di mesin mana pun dihitung (lintas cabang
  by design, `att_day.cabang_terlibat`), ada flag pengawasan `luar_seharian`.
- **Checklist**: SALAH GEDUNG — checklist ditentukan `sys_user_position`
  (divisi+cabang) yang disalin dari cabang asal, jadi dia tetap disodori
  checklist Sunter padahal seharian di Bintaro.
- **Tampilan**: profil/kalender tetap "Sunter" tanpa jejak dia ditugaskan.

Tidak ada satu tempat yang menyatakan "hari X, si A ditugaskan di cabang B".
Orang tergoda mengubah cabang asal tiap rolling — itu merusak identitas
roster & rekap. Fitur ini = tempat resminya.

**Kasus nyata yang baru terjadi (2026-08-06/07) — pakai ini sebagai bahan uji:**
Rizka Noviyanti (Assistant Dentist, roster **Bintaro**) absen masuk di Bintaro
6 Agu pagi, siangnya dipindah ke **Kalideres**, menginap, lalu absen masuk lagi
dari Kalideres 7 Agu. Yang terjadi tanpa fitur ini: absensinya benar (lintas
cabang memang by design), tapi **tak ada catatan bahwa dia ditugaskan** — jadi
dia muncul di kalender sebagai orang Bintaro tanpa jejak, dan checklist yang
disodorkan tetap milik cabang asal. Ekky sempat ditawari memindahkan roster-nya
ke Kalideres dan **itu keputusan yang salah** (penempatannya sementara);
justru inilah lubang yang fitur ini tutup.

## 2. Bentuk

Tabel baru `att_penempatan` (SQL manual di `database/sql/`, bukan migration):

| kolom | isi |
|---|---|
| `user_id` | staf yang ditempatkan |
| `raw_branch_id` | cabang TUJUAN |
| `tanggal_from`, `tanggal_to` | **tanggal_to WAJIB diisi** (boleh diperpanjang; tanpa akhir = sumber "kok dia masih kebaca Bintaro" 3 bulan kemudian) |
| `tipe` | `backup` / `rolling` / `event` |
| `bawa_checklist` | tinyint default **1** — checklist ikut pindah; 0 = pengecualian (backup setengah tenaga yang tak pegang checklist penuh cabang tujuan) |
| `keterangan`, `dibuat_oleh`, timestamps | jejak wajib |

Helper pusat: **`cabangEfektif($userId, $tanggal)`** = penempatan aktif pada
tanggal itu ?: `users.raw_branch_id`. Satu sumber, semua fitur baca dari sini.

## 3. Siapa boleh menempatkan (keputusan Ekky 2026-08-04)

- Pelaku: **Supervisor (Anisa) & Coordinator Dentist (Irma)** — **LINTAS
  domain**: Irma boleh menempatkan GS/FO, Nissa boleh menempatkan asdent.
  Yang lihat bolongnya, dia yang nambal. (+ Head IT / Super Admin utk support.)
- Target: **staf di bawah bagan** (FO, GS, Assistant Dentist, dst) — bukan
  sesama koordinator ke atas. Cek via hierarki `group_path` (level), jangan
  hardcode daftar role.
- Tanpa alur approval — operasional harian. Pengamannya jejak `dibuat_oleh`
  + **notifikasi** ke staf yang ditempatkan (pakai `AdminNotification`).
- `users.raw_branch_id` TIDAK PERNAH disentuh fitur ini. Pindah permanen =
  alur admin yang sudah ada, urusan lain.

## 4. Titik sentuh per fitur

1. **Checklist (nilai terbesar, kode Reza sendiri)**: saat buka/bootstrap
   checklist harian & mingguan, resolve cabang lewat `cabangEfektif()` bila
   `bawa_checklist=1` — divisi tetap (FO tetap FO), cabangnya yang ikut.
   Catatan: `sys_user_position` sudah punya `is_primary` — boleh dipakai
   (posisi sekunder sementara) atau resolve saat runtime; pilih yang paling
   kecil dampaknya ke kode submission yang ada.
2. **Kalender Kehadiran**: orang TETAP di grup cabang asal (rekap stabil);
   sel hari ber-penempatan dapat badge kecil "→ BIN".
3. **Pengawasan absensi**: scan di cabang penempatan = anotasi "sesuai
   penugasan" pada flag `luar_seharian`; scan di cabang yang bukan asal DAN
   bukan penempatan = tetap alarm. (Boleh fase 2 kalau mau minim.)
4. **Profil/header**: "FO · Sunter — ditempatkan di Bintaro s/d 12 Agu".

## 5. Batas yang JANGAN dilewati

- **Jangan sentuh mesin rekonsiliasi absensi** (`reconcile()`, att_day) —
  absensi sudah lintas-cabang by design, tidak butuh apa-apa dari fitur ini.
- Jangan ubah jam kerja orang — aturan nempel ke ORANG, penempatan tidak
  mengubah jadwal (FO Sunter di Bintaro tetap 10:00–18:00).
- UI ikut peta tema: zona cokelat, tanpa emoji (ikon SVG), akses menu via
  SQL grant BY LINK + `access:rebuild` (`docs/spec/peta-tema.md`).

## 6. Di luar scope v1

Penempatan berulang (tiap Rabu), setengah hari, approval flow, integrasi
gaji/transport. Kalau butuh, fase berikutnya.
